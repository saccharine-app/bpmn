<?php

namespace Saccharine\Bpmn\Handlers\Nodes\Events;

use Saccharine\Bpmn\Contracts\BpmnNodeHandlerInterface;
use Saccharine\Bpmn\Workflows\BpmnInterpreterWorkflow;
use Saccharine\Bpmn\Models\ProcessNode;
use Saccharine\Bpmn\Models\ProcessVersion;

class EndEventHandler implements BpmnNodeHandlerInterface
{
    public function handle(
        BpmnInterpreterWorkflow $workflow,
        ProcessNode $node,
        ProcessVersion $version,
        array $userData,
        ?int $instanceId
    ): array // \Generator
    {
        // yield from []; // Satisfies the Generator return type
        
        // Terminal node; there is no next node.
        return [null, $userData];
    }
}