<?php

use Saccharine\Bpmn\Models\WorkflowDefinition;
use Saccharine\Bpmn\Workflows\BpmnInterpreterWorkflow;
use Saccharine\Bpmn\Handlers\Nodes\Events\StartEventHandler;
use Workflow\WorkflowStub;

it('advances the token to the next sequential node', function () {
    $definition = WorkflowDefinition::create(['name' => 'Start Test', 'key' => 'start-test']);
    $version = $definition->versions()->create(['version' => 1, 'bpmn_xml' => '<xml/>']);
    
    $startNode = $version->nodes()->create(['bpmn_element_id' => 'Start_1', 'type' => 'startEvent']);
    $version->nodes()->create(['bpmn_element_id' => 'Task_1', 'type' => 'serviceTask']);
    $version->edges()->create(['bpmn_element_id' => 'Flow_1', 'source_node_id' => 'Start_1', 'target_node_id' => 'Task_1']);

    // $workflow = WorkflowStub::make(BpmnInterpreterWorkflow::class);
    $workflow = \Mockery::mock(BpmnInterpreterWorkflow::class)->makePartial();
    $handler = new StartEventHandler();

    $userData = ['process_started' => true];
    
    // Execute the handler
    $generator = $handler->handle($workflow, $startNode, $version, $userData, null);
    
    // Exhaust the generator (if there are any empty yields)
    while ($generator->valid()) { 
        $generator->next(); 
    }
    
    // Retrieve the final return array
    $result = $generator->getReturn();

    // Verify it resolved to the next node and preserved the payload
    expect($result[0])->toBe('Task_1')
        ->and($result[1])->toBe(['process_started' => true]);
});