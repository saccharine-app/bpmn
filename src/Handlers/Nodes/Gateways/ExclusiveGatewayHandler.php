<?php

namespace Saccharine\Bpmn\Handlers\Nodes\Gateways;

use Saccharine\Bpmn\Contracts\BpmnNodeHandlerInterface;
use Saccharine\Bpmn\Workflows\BpmnInterpreterWorkflow;
use Saccharine\Bpmn\Models\ProcessNode;
use Saccharine\Bpmn\Models\ProcessVersion;
use Saccharine\Bpmn\Services\GatewayRouter;
use Saccharine\Bpmn\Events\BpmnExecutionLogged;

// Exclusive Gateways (Routing)
class ExclusiveGatewayHandler implements BpmnNodeHandlerInterface
{
    public function handle(
        BpmnInterpreterWorkflow $workflow,
        ProcessNode $node,
        ProcessVersion $version,
        array $userData,
        ?int $instanceId
    ): \Generator
    {
        $router = new GatewayRouter();
        $nextNodeId = $router->getNextNodeId($version, $node->bpmn_element_id, $userData);
        
        yield $workflow->logTelemetry(
            BpmnExecutionLogged::LEVEL_GATEWAY,
            "Evaluated conditions. Routing token -> [{$nextNodeId}].",
            $node->bpmn_element_id
        );

        yield from []; // Satisfies the Generator return type
        return [$nextNodeId, $userData];
    }
}
