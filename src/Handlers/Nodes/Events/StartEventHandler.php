<?php

namespace Saccharine\Bpmn\Handlers\Nodes\Events;

use Saccharine\Bpmn\Contracts\BpmnNodeHandlerInterface;
use Saccharine\Bpmn\Workflows\BpmnInterpreterWorkflow;
use Saccharine\Bpmn\Models\WorkflowNode;
use Saccharine\Bpmn\Models\WorkflowVersion;

class StartEventHandler implements BpmnNodeHandlerInterface
{
    public function handle(
        BpmnInterpreterWorkflow $workflow,
        WorkflowNode $node,
        WorkflowVersion $version,
        array $userData,
        ?int $instanceId
    ): \Generator
    {
        // Find the outgoing edge and advance to the next sequential node
        $nextNodeId = $workflow->getNextSequentialNode($version, $node->bpmn_element_id);
        
        yield from []; // Satisfies the Generator return type
        return [$nextNodeId, $userData];
    }
}