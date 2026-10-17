<?php

namespace Saccharine\Bpmn\Listeners;

use Illuminate\Support\Facades\DB;
use Saccharine\Bpmn\Contracts\BpmnTriggerableEvent;
use Saccharine\Bpmn\Models\WorkflowNode;
use Workflow\WorkflowStub;
use Saccharine\Bpmn\Workflows\BpmnInterpreterWorkflow;
use Saccharine\Bpmn\Models\WorkflowInstance;
use Saccharine\Bpmn\Enums\WorkflowInstanceStatus;

class WorkflowTriggerListener
{
    public function handle(string $eventName, array $payload)
    {
        // Extract the actual event instance from Laravel's payload array
        $eventInstance = $payload[0] ?? null;

        // Fast exit if this event doesn't implement our contract
        if (!$eventInstance instanceof BpmnTriggerableEvent) {
            return;
        }

        $triggerAliases = [];

        // CASE A: It's our built-in generic ModelEventTrigger
        if ($eventInstance instanceof \Saccharine\Bpmn\Events\ModelEventTrigger) {
            $modelClass = class_basename($eventInstance->model); // e.g., 'Order'
            $modelSnake = strtolower(\Illuminate\Support\Str::snake($modelClass)); // 'order'
            $action = $eventInstance->action; // 'created'

            // Looks for triggers matching 'order_created' or configured aliases
            $triggerAliases[] = "{$modelSnake}_{$action}";

            // Also check if the host app mapped this model in saccharine-bpmn.php triggers
            $triggersConfig = config('saccharine-bpmn.triggers', []);
            $configuredKey = array_search(get_class($eventInstance->model) . "@{$action}", $triggersConfig);
            if ($configuredKey) {
                $triggerAliases[] = $configuredKey;
            }
        } 
        // CASE B: Standard custom events using the reverse lookup registry
        else {
            // REVERSE LOOKUP: Check if the fired event class is registered in our config
            $triggers = config('saccharine-bpmn.triggers', []);
            $triggerAlias = array_search($eventName, $triggers);
            if ($triggerAlias) {
                $triggerAliases[] = $triggerAlias;
            }
        }

        // If it's not registered as a trigger, the engine ignores it entirely
        if (empty($triggerAliases)) {
            return;
        }

        // Find all active workflow versions that have a StartEvent mapped to this exact event alias
        $startNodes = WorkflowNode::where('type', 'startEvent')
            ->whereIn('implementation', $triggerAliases)
            ->whereHas('version', function ($query) {
                $query->where('is_active', true);
            })
            ->get();

        if ($startNodes->isEmpty()) {
            return;
        }

        $businessKey = $eventInstance->getBusinessKey();
        $workflowPayload = $eventInstance->getWorkflowPayload();

        // Pluck only unique version IDs to prevent duplicate launches on messy diagrams
        $uniqueVersionIds = $startNodes->pluck('workflow_version_id')->unique();

        foreach ($uniqueVersionIds as $versionId) {
            // Enforce Idempotency using a safe database transaction
            DB::transaction(function () use ($versionId, $businessKey, $workflowPayload) {
                $alreadyRan = DB::table('workflow_triggers_log')
                    ->where('workflow_version_id', $versionId)
                    ->where('business_key', $businessKey)
                    ->exists();

                if ($alreadyRan) {
                    return; // Skip: This process already triggered for this specific domain object
                }
                
                // Launch the Durable Workflow!
                $workflow = WorkflowStub::make(BpmnInterpreterWorkflow::class);

                // Create an Instance using Eloquent so we can capture the new ID
                $instance = WorkflowInstance::create([
                    'workflow_version_id' => $versionId,
                    'status'              => WorkflowInstanceStatus::RUNNING,
                    'durable_workflow_id' => $workflow->id(),
                ]);

                // Parameters: versionId, userData, startNodeId (null for master), instanceId
                $workflow->start($versionId, $workflowPayload, null, $instance->id);

                // Log the trigger to prevent future duplicates
                DB::table('workflow_triggers_log')->insert([
                    'workflow_version_id' => $versionId,
                    'business_key'        => $businessKey,
                    'durable_workflow_id' => $workflow->id(),
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ]);
            });
        }
    }
}