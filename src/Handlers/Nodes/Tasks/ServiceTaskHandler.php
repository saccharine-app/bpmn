<?php

namespace Saccharine\Bpmn\Handlers\Nodes\Tasks;

use Saccharine\Bpmn\Contracts\BpmnNodeHandlerInterface;
use Saccharine\Bpmn\Workflows\BpmnInterpreterWorkflow;
use Saccharine\Bpmn\Models\WorkflowNode;
use Saccharine\Bpmn\Models\WorkflowVersion;
use Saccharine\Bpmn\Handlers\Traits\EvaluatesBoundaryEvents;
use Throwable;

// Service Tasks (Business Logic)
class ServiceTaskHandler implements BpmnNodeHandlerInterface
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
        $activityClass = config("saccharine-bpmn.activities.{$node->implementation}");

        try {
            // Attempt the standard execution
            $inputParameters = $node->input_parameters ?? [];
            $effectiveUserData = array_merge($inputParameters, $userData);

            // Yield hands control back to Laravel Workflow to execute this safely on the queues
            // Use the workflow's wrapper
            $activityResult = yield $workflow->makeActivity($activityClass, $effectiveUserData);

            // Merge the results back into the global state
            $userData = array_merge($userData, $activityResult);

            // Success: Proceed down the normal sequence flow
            $nextNodeId = $workflow->getNextSequentialNode($version, $node->bpmn_element_id);

        } catch (Throwable $e) {
            // Failure: Check if we have an escape route defined in the BPMN diagram
            // Delegate to the boundary event trait
            $nextNodeId = $this->handleErrorBoundary($e, $version, $node->bpmn_element_id, $workflow, $userData);
        }
        
        return [$nextNodeId, $userData];
    }
}
