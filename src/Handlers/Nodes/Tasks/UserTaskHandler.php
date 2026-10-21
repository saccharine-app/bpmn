<?php

namespace Saccharine\Bpmn\Handlers\Nodes\Tasks;

use Saccharine\Bpmn\Contracts\BpmnNodeHandlerInterface;
use Saccharine\Bpmn\Workflows\BpmnInterpreterWorkflow;
use Saccharine\Bpmn\Models\WorkflowNode;
use Saccharine\Bpmn\Models\WorkflowVersion;
use Saccharine\Bpmn\Events\BpmnExecutionLogged;
use Saccharine\Bpmn\Handlers\Traits\EvaluatesBoundaryEvents;
use function Workflow\await;
use function Workflow\awaitWithTimeout;

class UserTaskHandler implements BpmnNodeHandlerInterface
{
    use EvaluatesBoundaryEvents;

    public function handle(
        BpmnInterpreterWorkflow $workflow,
        WorkflowNode $node,
        WorkflowVersion $version,
        array $userData,
        ?int $instanceId
    ): \Generator
    {
        // Announce to the host application that a human is needed
        event(new \Saccharine\Bpmn\Events\UserTaskPending(
            $workflow->uniqueId(), 
            $node->name,
            $userData
        ));

        yield $workflow->logTelemetry(
            BpmnExecutionLogged::LEVEL_WAIT,
            "User Task [{$node->name}] reached. Workflow hibernating in database awaiting signal.",
            $node->bpmn_element_id
        );

        // Look for attached boundary timers
        $boundaries = $this->getAttachedBoundaries($version, $node->bpmn_element_id);
        $timerBoundary = $boundaries->where('event_definition_type', 'timer')->first();

        // Decide how to sleep based on the boundary event
        // Hibernate the workflow until the inbox receives an unread message
        // We also need to allow halting/suspending while waiting for a user!
        $closure = fn () => $workflow->hasUnreadInbox() || $workflow->isSuspended() || $workflow->isHalted();

        if ($timerBoundary) {
            // ISO 8601 duration parsed from the XML (e.g., 'PT24H')
            $timeoutDuration = $timerBoundary->implementation; 
            
            // Wait for the human OR the timer to expire
            $completedInTime =
                yield $workflow->awaitWithTimeoutPromise($timeoutDuration, $closure);

            if (!$completedInTime && !$workflow->isSuspended() && !$workflow->isHalted()) {
                // The timer expired! The human was too slow.
                $userData['_timer_expired'] = true;
                $nextNodeId = $workflow->getNextSequentialNode($version, $timerBoundary->bpmn_element_id);
                return [$nextNodeId, $userData];
            }
        } else {
            // Standard wait (sleep forever until human acts)
            yield $workflow->awaitPromise($closure);

            yield $workflow->logTelemetry(
                BpmnExecutionLogged::LEVEL_TASK,
                "Signal received for [{$node->name}]. Resuming process.",
                $node->bpmn_element_id
            );
        }
        
        // If woken by a manual intervention, bypass reading the inbox and return immediately
        if ($workflow->isSuspended() || $workflow->isHalted()) {
            return [$node->bpmn_element_id, $userData]; // Return same node ID to re-evaluate at top of loop
        }

        // Pop the message out of the inbox securely using our wrapper method
        $signalPayload = $workflow->readNextInboxMessage();
        
        // Once resumed, merge the host app's form/button response back into global state
        if (is_array($signalPayload)) {
            $userData = array_merge($userData, $signalPayload);
        }
        
        // Find the outgoing edge and advance to the next sequential node
        $nextNodeId = $workflow->getNextSequentialNode($version, $node->bpmn_element_id);

        // Advance to the next sequential node in the graph layout
        return [$nextNodeId, $userData];
    }
}
