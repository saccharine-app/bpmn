<?php

namespace Saccharine\Bpmn\Handlers\Traits;

use Saccharine\Bpmn\Models\WorkflowVersion;
use Saccharine\Bpmn\Workflows\BpmnInterpreterWorkflow;

trait EvaluatesBoundaryEvents
{
    /**
     * Fetch all boundary events attached to a specific node.
     */
    protected function getAttachedBoundaries(WorkflowVersion $version, string $nodeId)
    {
        return $version->nodes
            ->where('type', 'boundaryEvent')
            ->where('attached_to_element_id', $nodeId);
    }

    /**
     * Handle an exception by checking for an attached Error Boundary Event.
     * Throws the exception back up if no boundary is defined.
     */
    protected function handleErrorBoundary(
        \Throwable $e, 
        WorkflowVersion $version, 
        string $nodeId, 
        BpmnInterpreterWorkflow $workflow, 
        array &$userData
    ): string {
        $boundaries = $this->getAttachedBoundaries($version, $nodeId);
        
        $errorBoundary = $boundaries->where('event_definition_type', 'error')->first();

        if ($errorBoundary) {
            // We caught the anticipated error! Inject into payload and route.
            $userData['_error_caught'] = $e->getMessage();
            return $workflow->getNextSequentialNode($version, $errorBoundary->bpmn_element_id);
        }

        // No escape hatch defined. Bubble it up to Durable Workflow's retry mechanism.
        throw $e;
    }
}