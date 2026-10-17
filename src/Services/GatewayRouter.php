<?php

namespace Saccharine\Bpmn\Services;

use Saccharine\Bpmn\Models\WorkflowVersion;
use Saccharine\Bpmn\Models\WorkflowEdge;
use RuntimeException;

class GatewayRouter
{
    protected WorkflowExpressionEvaluator $evaluator;

    public function __construct()
    {
        $this->evaluator = new WorkflowExpressionEvaluator();
    }

    /**
     * Determines the next node ID by evaluating outgoing edges from a given node.
     * * @param WorkflowVersion $version The active workflow version model
     * @param string $currentNodeId The bpmn_element_id of the gateway
     * @param array $userData The current state payload
     * @return string The bpmn_element_id of the next node
     * @throws RuntimeException If no valid path is found
     */
    public function getNextNodeId(WorkflowVersion $version, string $currentNodeId, array $userData): string
    {
        // Fetch all outgoing paths from this specific node
        $outgoingEdges = WorkflowEdge::where('workflow_version_id', $version->id)
            ->where('source_node_id', $currentNodeId)
            ->get();

        $nextTargetNodeId = null;
        $defaultRouteNodeId = null;

        // Evaluate the routing conditions sequentially
        foreach ($outgoingEdges as $edge) {
            
            // Track paths without expressions to act as fallback/else branches
            if (empty($edge->condition_expression)) {
                $defaultRouteNodeId = $edge->target_node_id;
                continue;
            }

            // Execute the sandbox logic check
            if ($this->evaluator->evaluate($edge->condition_expression, $userData)) {
                $nextTargetNodeId = $edge->target_node_id;
                break; // First match wins in standard BPMN Exclusive Gateways
            }
        }

        // Select the valid branch, fallback to default, or fail if dead-ended
        $resolvedNodeId = $nextTargetNodeId ?? $defaultRouteNodeId;

        if ($resolvedNodeId === null) {
            throw new RuntimeException("Workflow stalled: No valid path matches the conditions at Gateway [{$currentNodeId}].");
        }

        return $resolvedNodeId;
    }
}