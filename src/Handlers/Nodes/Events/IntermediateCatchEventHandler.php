<?php

namespace Saccharine\Bpmn\Handlers\Nodes\Events;

use Saccharine\Bpmn\Contracts\BpmnNodeHandlerInterface;
use Saccharine\Bpmn\Workflows\BpmnInterpreterWorkflow;
use Saccharine\Bpmn\Models\ProcessNode;
use Saccharine\Bpmn\Models\ProcessVersion;
use Saccharine\Bpmn\Events\BpmnExecutionLogged;

class IntermediateCatchEventHandler implements BpmnNodeHandlerInterface
{
    public function handle(
        BpmnInterpreterWorkflow $workflow,
        ProcessNode $node,
        ProcessVersion $version,
        array $userData,
        ?int $instanceId
    ): array {
        if ($node->event_definition_type === 'timer') {
            $duration = $node->implementation ?? 'PT1H';

            $workflow->logTelemetry(
                BpmnExecutionLogged::LEVEL_WAIT,
                "Timer Catch Event [{$node->name}] reached. Hibernating for duration [{$duration}].",
                $node->bpmn_element_id
            );

            // Suspend the Fiber until the timer duration elapses
            $workflow->timerPromise($duration);

            $workflow->logTelemetry(
                BpmnExecutionLogged::LEVEL_TASK,
                "Timer Catch Event [{$node->name}] elapsed. Resuming process.",
                $node->bpmn_element_id
            );
        }

        $nextNodeId = $workflow->getNextSequentialNode($version, $node->bpmn_element_id);

        return [$nextNodeId, $userData];
    }
}