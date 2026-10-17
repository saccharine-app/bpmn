<?php

use Saccharine\Bpmn\Models\WorkflowDefinition;
use Saccharine\Bpmn\Workflows\BpmnInterpreterWorkflow;
use Saccharine\Bpmn\Handlers\Nodes\Events\EndEventHandler;
use Workflow\WorkflowStub;

it('returns a null node ID to terminate the execution path', function () {
    $definition = WorkflowDefinition::create(['name' => 'End Test', 'key' => 'end-test']);
    $version = $definition->versions()->create(['version' => 1, 'bpmn_xml' => '<xml/>']);
    
    $endNode = $version->nodes()->create(['bpmn_element_id' => 'End_1', 'type' => 'endEvent']);

    // $workflow = WorkflowStub::make(BpmnInterpreterWorkflow::class);
    $workflow = \Mockery::mock(BpmnInterpreterWorkflow::class)->makePartial();
    $handler = new EndEventHandler();

    $generator = $handler->handle($workflow, $endNode, $version, ['status' => 'completed'], null);
    
    while ($generator->valid()) { 
        $generator->next(); 
    }
    
    $result = $generator->getReturn();

    expect($result[0])->toBeNull()
        ->and($result[1])->toBe(['status' => 'completed']);
});