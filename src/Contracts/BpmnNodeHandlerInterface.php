<?php

namespace Saccharine\Bpmn\Contracts;

use Saccharine\Bpmn\Workflows\BpmnInterpreterWorkflow;
use Saccharine\Bpmn\Models\ProcessNode;
use Saccharine\Bpmn\Models\ProcessVersion;

interface BpmnNodeHandlerInterface
{
    /**
     * Handle the execution of a specific BPMN node.
     *
     * @return \Generator Yields an array containing the next node ID and the mutated user data: [?string, array]
     */
    public function handle(
        BpmnInterpreterWorkflow $workflow, 
        ProcessNode $node, 
        ProcessVersion $version, 
        array $userData, 
        ?int $instanceId
    ): \Generator;
}