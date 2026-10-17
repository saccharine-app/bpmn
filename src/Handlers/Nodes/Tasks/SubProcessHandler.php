<?php

namespace Saccharine\Bpmn\Handlers\Nodes\Tasks;

use Saccharine\Bpmn\Contracts\BpmnNodeHandlerInterface;
use Saccharine\Bpmn\Workflows\BpmnInterpreterWorkflow;
use Saccharine\Bpmn\Models\WorkflowNode;
use Saccharine\Bpmn\Models\WorkflowVersion;
use Saccharine\Bpmn\Handlers\Traits\EvaluatesBoundaryEvents;
use Throwable;

class SubProcessHandler implements BpmnNodeHandlerInterface
{
    use EvaluatesBoundaryEvents;
    
    public function handle(
        BpmnInterpreterWorkflow $workflow,
        WorkflowNode $node,
        WorkflowVersion $version,
        array $userData,
        ?int $instanceId
    ): \Generator {
        
        try {
            // Yield the scoped child workflow, passing the subProcess node ID
            $childWorkflowResult = yield $workflow->makeScopedChildWorkflow(
                $version->id,
                $node->bpmn_element_id,
                $userData,
                $instanceId
            );

            // Merge the returned data back into the parent payload
            $userData = array_merge($userData, $childWorkflowResult);

            // Determine the next node to advance to in the parent process
            $nextNodeId = $workflow->getNextSequentialNode($version, $node->bpmn_element_id);

        } catch (Throwable $e) {
            // Failure: Check if we have an escape route defined in the BPMN diagram
            // Delegate to the boundary event trait
            $nextNodeId = $this->handleErrorBoundary($e, $version, $node->bpmn_element_id, $workflow, $userData);
        }

        return [$nextNodeId, $userData];
    }
}