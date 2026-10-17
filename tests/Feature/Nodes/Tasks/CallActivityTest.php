<?php

use Saccharine\Bpmn\Models\WorkflowDefinition;
use Saccharine\Bpmn\Workflows\BpmnInterpreterWorkflow;
use Saccharine\Bpmn\Handlers\Nodes\Tasks\CallActivityHandler;
use Workflow\ChildWorkflowStub;

it('yields a child workflow stub for the called element and merges the returned payload', function () {
    // Scaffold the Target (Called) Process
    $targetDefinition = WorkflowDefinition::create(['name' => 'Billing', 'key' => 'billing-process']);
    $targetDefinition->versions()->create(['version' => 1, 'bpmn_xml' => '<xml/>', 'is_active' => true]);

    // Scaffold the Parent (Calling) Process
    $parentDefinition = WorkflowDefinition::create(['name' => 'Checkout', 'key' => 'checkout-process']);
    $parentVersion = $parentDefinition->versions()->create(['version' => 1, 'bpmn_xml' => '<xml/>', 'is_active' => true]);
    
    $callNode = $parentVersion->nodes()->create([
        'bpmn_element_id' => 'Call_1', 
        'type' => 'callActivity', 
        'implementation' => 'billing-process' // Matches the target definition's key
    ]);
    
    $parentVersion->nodes()->create(['bpmn_element_id' => 'Next_1', 'type' => 'endEvent']);
    $parentVersion->edges()->create(['bpmn_element_id' => 'Flow_1', 'source_node_id' => 'Call_1', 'target_node_id' => 'Next_1']);

    // Mock the workflow interpreter
    $workflow = \Mockery::mock(BpmnInterpreterWorkflow::class)->makePartial();
    
    // We expect the handler to ask the interpreter to spawn a child workflow
    $workflow->shouldReceive('makeChildWorkflow')
             ->once()
             ->andReturn('mocked_child_workflow_stub');

    $handler = new CallActivityHandler();
    $userData = ['order_total' => 500];
    
    // Execute the handler
    $generator = $handler->handle($workflow, $callNode, $parentVersion, $userData, null);

    // The first yield should return the ChildWorkflowStub
    $yielded = $generator->current();
    expect($yielded)->toBe('mocked_child_workflow_stub');

    // Simulate the child workflow finishing and returning its mutated data
    $generator->send(['billing_status' => 'paid']);
    $result = $generator->getReturn();

    // Verify the node advances and the payloads merged successfully
    expect($result[0])->toBe('Next_1')
        ->and($result[1])->toBe([
            'order_total' => 500, 
            'billing_status' => 'paid'
        ]);
});

it('throws an exception if the called element workflow does not exist or has no active version', function () {
    $parentDefinition = WorkflowDefinition::create(['name' => 'Checkout', 'key' => 'checkout']);
    $parentVersion = $parentDefinition->versions()->create(['version' => 1, 'bpmn_xml' => '<xml/>']);
    
    $callNode = $parentVersion->nodes()->create([
        'bpmn_element_id' => 'Call_Broken', 
        'type' => 'callActivity', 
        'implementation' => 'non-existent-process'
    ]);

    $workflow = \Mockery::mock(BpmnInterpreterWorkflow::class)->makePartial();
    $handler = new CallActivityHandler();
    
    // This should fail before it ever tries to yield a child workflow
    $handler->handle($workflow, $callNode, $parentVersion, [], null)->current();

})->throws(\RuntimeException::class, 'BPMN Error: Target workflow [non-existent-process] not found or has no active version.');