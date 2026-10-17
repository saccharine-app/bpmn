<?php

use Saccharine\Bpmn\Models\WorkflowDefinition;
use Saccharine\Bpmn\Workflows\BpmnInterpreterWorkflow;
use Saccharine\Bpmn\Handlers\Nodes\Tasks\SubProcessHandler;
use Workflow\ChildWorkflowStub;

it('yields a child workflow stub for the internal scope and merges the returned payload', function () {
    $definition = WorkflowDefinition::create(['name' => 'SubProcess Execution', 'key' => 'subprocess-exec']);
    $version = $definition->versions()->create(['version' => 1, 'bpmn_xml' => '<xml/>', 'is_active' => true]);
    
    // Scaffold the Parent Node
    $subProcessNode = $version->nodes()->create([
        'bpmn_element_id' => 'SubProcess_1', 
        'type' => 'subProcess'
    ]);
    
    $version->nodes()->create(['bpmn_element_id' => 'Next_1', 'type' => 'endEvent']);
    $version->edges()->create(['bpmn_element_id' => 'Flow_1', 'source_node_id' => 'SubProcess_1', 'target_node_id' => 'Next_1']);

    // Mock the workflow interpreter
    $workflow = \Mockery::mock(BpmnInterpreterWorkflow::class)->makePartial();
    
    // We expect the handler to ask the interpreter to spawn a scoped child workflow
    // It should pass the subProcess node ID so the child knows its execution boundaries
    $workflow->shouldReceive('makeScopedChildWorkflow')
             ->with($version->id, 'SubProcess_1', ['base_data' => 100], null)
             ->once()
             ->andReturn('mocked_scoped_child_stub');

    $handler = new SubProcessHandler();
    $userData = ['base_data' => 100];
    
    // Execute the handler
    $generator = $handler->handle($workflow, $subProcessNode, $version, $userData, null);

    // The first yield should return the scoped ChildWorkflowStub
    $yielded = $generator->current();
    expect($yielded)->toBe('mocked_scoped_child_stub');

    // Simulate the scoped child workflow finishing its internal nodes and returning data
    $generator->send(['sub_process_result' => 'completed']);
    $result = $generator->getReturn();

    // Verify the parent node advances and the payloads merged successfully
    expect($result[0])->toBe('Next_1')
        ->and($result[1])->toBe([
            'base_data' => 100, 
            'sub_process_result' => 'completed'
        ]);
});