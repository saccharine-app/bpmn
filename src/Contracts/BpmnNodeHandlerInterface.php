<?php

namespace Saccharine\Bpmn\Contracts;

use Saccharine\Bpmn\Workflows\BpmnInterpreterWorkflow;
use Saccharine\Bpmn\Models\WorkflowNode;
use Saccharine\Bpmn\Models\WorkflowVersion;

interface BpmnNodeHandlerInterface
{
    /**
     * Handle the execution of a specific BPMN node.
     *
     * @return \Generator Yields an array containing the next node ID and the mutated user data: [?string, array]
     */
    public function handle(
        BpmnInterpreterWorkflow $workflow, 
        WorkflowNode $node, 
        WorkflowVersion $version, 
        array $userData, 
        ?int $instanceId
    ): \Generator;
}