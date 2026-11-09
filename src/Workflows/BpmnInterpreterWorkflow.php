<?php

namespace Saccharine\Bpmn\Workflows;

use Workflow\V2\Workflow;
use Workflow\V2\WorkflowStub;
// use Workflow\ActivityStub;
// use Workflow\ChildWorkflowStub;
// use Workflow\SignalMethod;
use Workflow\UpdateMethod;
use Workflow\V2\Attributes\Signal;
use function Workflow\V2\activity;
use function Workflow\V2\child;
use function Workflow\V2\await;
use function Workflow\V2\sideEffect;
use function Workflow\V2\timer;
use Saccharine\Bpmn\Models\ProcessVersion;
use Saccharine\Bpmn\Models\ProcessEdge;
use Saccharine\Bpmn\Models\ProcessToken;
use Saccharine\Bpmn\Models\ProcessHistoryEvent;
use Saccharine\Bpmn\Services\GatewayRouter;
use Saccharine\Bpmn\Handlers\NodeHandlerFactory;
use Saccharine\Bpmn\Events\BpmnExecutionLogged;
use RuntimeException;

class BpmnInterpreterWorkflow extends Workflow
{
    // Public property for the associated instance ID
    public ?int $instanceId = null;
    // Internal state trackers for manual interventions
    private bool $isSuspended = false;
    private bool $isHalted = false;

    private array $pendingUserTaskPayloads = [];

    public function isSuspended(): bool
    {
        return $this->isSuspended;
    }

    public function isHalted(): bool
    {
        return $this->isHalted;
    }

    // Define a generic signal receiver that pipes data into the durable Inbox
    // #[SignalMethod]
    #[UpdateMethod]
    public function submitUserTask(array $payload): array
    {
        // $this->inbox->receive($payload);
        $this->pendingUserTaskPayloads[] = $payload;

        return $this->pendingUserTaskPayloads;
    }

    // Workflow intervention signals
    // #[SignalMethod]
    #[UpdateMethod]
    public function suspendProcess(): bool
    {
        $this->isSuspended = true;

        return $this->isSuspended;
    }

    // #[SignalMethod]
    #[UpdateMethod]
    public function resumeProcess(): bool
    {
        $this->isSuspended = false;

        return $this->isSuspended;
    }

    // #[SignalMethod]
    #[UpdateMethod]
    public function haltProcess(): bool
    {
        $this->isHalted = true;

        return $this->isHalted;
    }
    
    /**
     * Wrapper for durable-workflow's Inbox::hasUnread() method.
     * This isolated testing, since Inbox is declared as final.
     */
    /* public function hasUnreadInbox()
    {
        return $this->inbox->hasUnread();
    } */
    public function hasUnreadInbox(): bool
    {
        return count($this->pendingUserTaskPayloads) > 0;
    }

    /**
     * Wrapper for durable-workflow's Inbox::nextUnread() method.
     * This isolated testing, since Inbox is declared as final.
     */
    /* public function readNextInboxMessage(): mixed
    {
        return $this->inbox->nextUnread();
    } */
    public function readNextInboxMessage(): mixed
    {
        return array_shift($this->pendingUserTaskPayloads);
    }

    public function awaitUserTaskCompletion(): void
    {
        await(fn () => count($this->inboxPayloads) > 0 || /* $this->isSuspended || */$this->isHalted);
    }

    /**
     * Wrapper for durable-workflow's await() method.
     */
    public function awaitPromise(\Closure $condition)
    {
        return await($condition);
    }

    /**
     * Wrapper for durable-workflow's awaitWithTimeout() method.
     */
    public function awaitWithTimeoutPromise(string $duration, \Closure $condition)
    {
        return awaitWithTimeout($duration, $condition);
    }

    public function timerPromise(string $duration): void
    {
        timer($duration);
    }

    /**
     * Wrapper for durable-workflow's static ActivityStub creation.
     * This decouples the handlers from the static context, allowing for isolated testing.
     */
    public function makeActivity(string $activityClass, array $userData)
    {
        // return \Workflow\ActivityStub::make($activityClass, $userData);
        return activity($activityClass, $userData);
    }

    /**
     * Wrapper for durable-workflow's static ChildWorkflowStub creation.
     * Used primarily for Call Activities to spawn independent workflow versions.
     */
    public function makeChildWorkflow(int $versionId, array $userData, ?string $startNodeId = null, ?int $instanceId = null)
    {
        // return \Workflow\ChildWorkflowStub::make(self::class, $versionId, $userData, $startNodeId, $instanceId);
        return child(self::class, $versionId, $userData, $startNodeId, $instanceId);
    }

    /**
     * Wrapper for durable-workflow's static ChildWorkflowStub creation.
     * Used for inline Sub-Processes to execute a scoped subset of nodes.
     */
    public function makeScopedChildWorkflow(int $versionId, string $subProcessNodeId, array $userData, ?int $instanceId = null)
    {
        $version = ProcessVersion::with('nodes')->find($versionId);
        
        // Find the start event strictly scoped inside this sub-process
        $startNode = $version->nodes
            ->where('type', 'startEvent')
            ->where('parent_element_id', $subProcessNodeId)
            ->first();

        if (!$startNode) {
            throw new RuntimeException("BPMN Error: No startEvent found inside Sub-Process [{$subProcessNodeId}].");
        }

        // return \Workflow\ChildWorkflowStub::make(self::class, $versionId, $userData, $startNode->bpmn_element_id, $instanceId);
        return child(self::class, $versionId, $userData, $startNode->bpmn_element_id, $instanceId);
    }

    // Add the optional 3rd parameter for branch executions
    //public function execute(
    public function handle(
        int $versionId,
        array $userData,
        ?string $startNodeId = null,
        ?int $instanceId = null
    ) {
        // Note: Querying the DB inside a workflow is safe ONLY IF the data is immutable.
        // Since ProcessVersions and their nodes never change once published,
        // this is fully deterministic.
        $version = ProcessVersion::with(['nodes', 'edges'])->findOrFail($versionId);

        $this->instanceId = $instanceId;

        // If no start node is provided, this is the Master Process starting from the beginning
        if ($startNodeId === null) {
            // Find the global start event (it must NOT belong to a sub-process)
            $startNode = $version->nodes
                ->where('type', 'startEvent')
                ->whereNull('parent_element_id') // Ensure it only picks up top-level start events
                ->first();

            if (!$startNode) {
                throw new RuntimeException("No global startEvent found.");
            }
            $currentNodeId = $startNode->bpmn_element_id;
        } else {
            // This is a Child Workflow starting its specific branch or sub-process
            $currentNodeId = $startNodeId;
        }

        // When the workflow initiates:
        /* yield */ $this->logTelemetry(
            BpmnExecutionLogged::LEVEL_INFO, 
            "Process started from node [{$currentNodeId}].", 
            $currentNodeId
        );

        while ($currentNodeId !== null) {
            // HALT CHECK: Immediately break the loop and terminate the process
            if ($this->isHalted) {
                /* yield */ $this->logTelemetry(
                    BpmnExecutionLogged::LEVEL_ERROR, 
                    "Process halted by operator intervention. Terminating execution.", 
                    $currentNodeId
                );
                // You can optionally inject a 'halted_at' flag into the payload
                $userData['_system_status'] = 'halted';
                break; 
            }

            // SUSPENSION CHECK: Hibernate safely until resumed (or halted while sleeping)
            if ($this->isSuspended) {
                /* yield */ $this->logTelemetry(
                    BpmnExecutionLogged::LEVEL_WAIT, 
                    "Process suspended via operator signal. Hibernating...", 
                    $currentNodeId
                );
                /* yield */ await(fn () => !$this->isSuspended || $this->isHalted);
                
                // Blocks here safely until the 'resume' signal is sent
                // await('resume');
                // $this->isSuspended = false; // Reset once woken up

                // Continue forces the loop to re-evaluate the Halt check immediately upon waking up
                continue; 
            }

            // Token tracker: SideEffect ensures this database query only runs exactly once per step, never on replay
            if ($instanceId !== null) {
                /* yield *//* WorkflowStub:: */sideEffect(function () use ($instanceId, $currentNodeId) {
                    ProcessToken::updateOrCreate(
                        [
                            'process_instance_id' => $instanceId,
                            'durable_workflow_id'  => $this->workflowId(),
                        ],
                        [
                            'bpmn_element_id' => $currentNodeId,
                        ]
                    );
                });
            }

            $node = $version->nodes->where('bpmn_element_id', $currentNodeId)->first();

            // RESOLVE HANDLER
            $handler = NodeHandlerFactory::make($node->type);

            // DELEGATE EXECUTION
            // We use 'yield from' because the handler itself contains yields (Activities/Awaits)
            [$nextNodeId, $userData] = /* yield from */ $handler->handle(
                $this, 
                $node, 
                $version, 
                $userData, 
                $instanceId
            );

            if ($instanceId !== null) {
                /* yield *//* WorkflowStub:: */sideEffect(function () use ($instanceId, $currentNodeId, $node, $userData) {
                    ProcessHistoryEvent::create([
                        'process_instance_id' => $instanceId,
                        'bpmn_element_id'      => $currentNodeId,
                        'node_type'            => $node->type,
                        'status'               => 'completed',
                        'state_snapshot'       => json_encode($userData),
                        'completed_at'         => now(),
                    ]);
                });
            }

            // Terminal Condition (End of Process)
            if ($node->type === 'endEvent') {
                $this->cleanupToken($instanceId);
                return $userData;
            }

            $currentNodeId = $nextNodeId;
        }

        // Failsafe cleanup if the loop breaks
        $this->cleanupToken($instanceId);
        
        return $userData;
    }

    /**
     * Helper to find the immediate next node in a straight line (non-gateway paths)
     */
    public function getNextSequentialNode(ProcessVersion $version, string $currentNodeId): ?string
    {
        // If relation is loaded and not empty, use collection; otherwise query directly
        $edge = $version->relationLoaded('edges') && $version->edges->isNotEmpty()
            ? $version->edges->where('source_node_id', $currentNodeId)->first()
            : $version->edges()->where('source_node_id', $currentNodeId)->first();
            
        return $edge ? $edge->target_node_id : null;
    }
    
    /**
     * Removes the active token from the database when a thread terminates.
     */
    protected function cleanupToken(?int $instanceId): void
    {
        if ($instanceId !== null) {
            // Because side effects cannot be yielded from a void return easily inside the execution structure,
            // we can trigger this directly in a blocking manner since it's the final action of the thread.
            ProcessToken::where('durable_workflow_id', $this->workflowId())->delete();
        }
    }

    /**
     * Deterministically dispatches a telemetry log event that only fires once,
     * safely ignored during replay cycles.
     */
    public function logTelemetry(string $type, string $message, ?string $nodeId = null, array $context = [])
    {
        $instanceId = $this->instanceId;

        return /* WorkflowStub:: */sideEffect(function () use ($type, $message, $instanceId, $nodeId, $context) {
            event(new BpmnExecutionLogged($type, $message, $instanceId, $nodeId, $context));
        });
    }
}