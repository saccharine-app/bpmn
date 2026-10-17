<?php

namespace Saccharine\Bpmn\Handlers\Nodes\Gateways;

use Saccharine\Bpmn\Contracts\BpmnNodeHandlerInterface;
use Saccharine\Bpmn\Workflows\BpmnInterpreterWorkflow;
use Saccharine\Bpmn\Models\WorkflowNode;
use Saccharine\Bpmn\Models\WorkflowVersion;
use Saccharine\Bpmn\Services\GatewayRouter;

// Exclusive Gateways (Routing)
class ExclusiveGatewayHandler implements BpmnNodeHandlerInterface
{
    public function handle(
        BpmnInterpreterWorkflow $workflow,
        WorkflowNode $node,
        WorkflowVersion $version,
        array $userData,
        ?int $instanceId
    ): \Generator
    {
        $router = new GatewayRouter();
        $nextNodeId = $router->getNextNodeId($version, $node->bpmn_element_id, $userData);

        yield from []; // Satisfies the Generator return type
        return [$nextNodeId, $userData];
    }
}
