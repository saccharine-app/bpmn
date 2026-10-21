<?php

namespace Saccharine\Bpmn\Handlers\Nodes\Gateways;

use Saccharine\Bpmn\Contracts\BpmnNodeHandlerInterface;
use Saccharine\Bpmn\Workflows\BpmnInterpreterWorkflow;
use Saccharine\Bpmn\Models\WorkflowNode;
use Saccharine\Bpmn\Models\WorkflowVersion;
use Saccharine\Bpmn\Events\BpmnExecutionLogged;
use Workflow\ChildWorkflowStub;
use function Workflow\all;

// Parallel Gateways (The AND Split/Join mechanism)
class ParallelGatewayHandler implements BpmnNodeHandlerInterface
{
    public function handle(
        BpmnInterpreterWorkflow $workflow,
        WorkflowNode $node,
        WorkflowVersion $version,
        array $userData,
        ?int $instanceId
    ): \Generator
    {
        $outgoingEdges = $version->edges->where('source_node_id', $node->bpmn_element_id);

        // CASE A: It is a SPLIT (Multiple outgoing paths)
        if ($outgoingEdges->count() > 1) {
            yield $workflow->logTelemetry(
                BpmnExecutionLogged::LEVEL_GATEWAY,
                "Parallel Split: Spawning " . $outgoingEdges->count() . " child workflow branches.",
                $node->bpmn_element_id
            );

            $parallelStubs = [];

            foreach ($outgoingEdges as $edge) {
                // Spawn a Child Workflow of the interpreter class,
                // passing the specific branch's starting node ID
                $parallelStubs[] = ChildWorkflowStub::make(
                    BpmnInterpreterWorkflow::class,
                    $version->id,
                    $userData,
                    $edge->target_node_id,
                    $instanceId // Pass the parent instance down
                );
            }

            // The Master Workflow sleeps safely here without generating a serialization error.
            // CRITICAL FIX: all() converts the array of stubs into a single PromiseInterface
            $branchResults = yield all($parallelStubs);

            yield $workflow->logTelemetry(
                BpmnExecutionLogged::LEVEL_GATEWAY,
                "Parallel Join: All concurrent branches resolved. Synchronizing payload.",
                $node->bpmn_element_id
            );

            // Merge variable mutations from all concurrent paths back into the main payload
            foreach ($branchResults as $result) {
                $userData = array_merge($userData, $result);
            }

            // Once merged, find the post-join node to advance the main process pointer.
            $nextNodeId = $this->findPostJoinNode($workflow, $version, $node->bpmn_element_id);
            return [$nextNodeId, $userData];
        }

        // CASE B: It is a JOIN (Single outgoing path, reached by a split branch)
        return [null, $userData];
    }

    /**
     * Finds the node immediately following the converging Parallel Join Gateway.
     */
    private function findPostJoinNode(
        BpmnInterpreterWorkflow $workflow,
        WorkflowVersion $version,
        string $splitGatewayId
    ): ?string
    {
        // For standard BPMN layouts, we trace an arbitrary branch from the split down to the join.
        // Once we find the join gateway, we grab its single outgoing edge.
        $firstBranchEdge = $version->edges->where('source_node_id', $splitGatewayId)->first();
        $pointerId = $firstBranchEdge ? $firstBranchEdge->target_node_id : null;

        while ($pointerId !== null) {
            $node = $version->nodes->where('bpmn_element_id', $pointerId)->first();
            
            if ($node && $node->type === 'parallelGateway') {
                // We found the converging Join! Get the node immediately after it.
                return $workflow->getNextSequentialNode($version, $pointerId);
            }
            
            $pointerId = $workflow->getNextSequentialNode($version, $pointerId);
        }

        return null;
    }
}
