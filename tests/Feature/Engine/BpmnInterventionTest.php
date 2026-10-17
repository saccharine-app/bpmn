<?php

use Saccharine\Bpmn\Models\WorkflowDefinition;
use Saccharine\Bpmn\Workflows\BpmnInterpreterWorkflow;
use Workflow\WorkflowStub;
use Illuminate\Support\Facades\Artisan;

it('can permanently halt a running workflow', function () {
    // Scaffold Database with a User Task so it pauses
    $definition = WorkflowDefinition::create(['name' => 'Halt Test', 'key' => 'halt-test']);
    $version = $definition->versions()->create(['version' => 1, 'bpmn_xml' => '<xml/>']);
    
    $version->nodes()->createMany([
        ['bpmn_element_id' => 'Start_1', 'type' => 'startEvent'],
        ['bpmn_element_id' => 'Task_Wait', 'type' => 'userTask'],
        ['bpmn_element_id' => 'End_1', 'type' => 'endEvent']
    ]);
    
    $version->edges()->createMany([
        ['bpmn_element_id' => 'Flow_1', 'source_node_id' => 'Start_1', 'target_node_id' => 'Task_Wait'],
        ['bpmn_element_id' => 'Flow_2', 'source_node_id' => 'Task_Wait', 'target_node_id' => 'End_1']
    ]);

    // Start Workflow
    $workflow = WorkflowStub::make(BpmnInterpreterWorkflow::class);
    $workflow->start($version->id, ['initial' => 'data']);
    Artisan::call('queue:work', ['--stop-when-empty' => true]);

    // Verify it is running and waiting at the User Task
    $workflow = WorkflowStub::load($workflow->id());
    expect($workflow->running())->toBeTrue();

    // Fire the Halt Signal
    $workflow->haltWorkflow();
    Artisan::call('queue:work', ['--stop-when-empty' => true]);

    // Assertions
    $workflow = WorkflowStub::load($workflow->id());
    expect($workflow->running())->toBeFalse(); // Should be terminated

    $output = $workflow->output();
    expect($output)->toBeArray()
        ->and($output['_system_status'])->toBe('halted');
});

it('can suspend a workflow and safely resume it later', function () {
    $definition = WorkflowDefinition::create(['name' => 'Suspend Test', 'key' => 'suspend-test']);
    $version = $definition->versions()->create(['version' => 1, 'bpmn_xml' => '<xml/>']);
    
    $version->nodes()->createMany([
        ['bpmn_element_id' => 'Start_1', 'type' => 'startEvent'],
        ['bpmn_element_id' => 'Task_Wait', 'type' => 'userTask'],
        ['bpmn_element_id' => 'End_1', 'type' => 'endEvent']
    ]);
    
    $version->edges()->createMany([
        ['bpmn_element_id' => 'Flow_1', 'source_node_id' => 'Start_1', 'target_node_id' => 'Task_Wait'],
        ['bpmn_element_id' => 'Flow_2', 'source_node_id' => 'Task_Wait', 'target_node_id' => 'End_1']
    ]);

    $workflow = WorkflowStub::make(BpmnInterpreterWorkflow::class);
    $workflow->start($version->id, ['status' => 'fresh']);
    Artisan::call('queue:work', ['--stop-when-empty' => true]);

    // Fire the Suspend Signal
    $workflow = WorkflowStub::load($workflow->id());
    $workflow->suspendWorkflow();
    Artisan::call('queue:work', ['--stop-when-empty' => true]);

    // Try to complete the user task while suspended
    $workflow->submitUserTask(['action' => 'approved']);
    Artisan::call('queue:work', ['--stop-when-empty' => true]);

    // Verify it is STILL running and hasn't advanced to the end!
    $workflow = WorkflowStub::load($workflow->id());
    expect($workflow->running())->toBeTrue();
    expect($workflow->output())->toBeNull(); 

    // Resume the Workflow
    $workflow->resumeWorkflow();
    Artisan::call('queue:work', ['--stop-when-empty' => true]);

    // Verify it woke up, grabbed the task data, and finished
    $workflow = WorkflowStub::load($workflow->id());
    expect($workflow->running())->toBeFalse();
    
    $output = $workflow->output();
    expect($output)->toBeArray()
        ->and($output['action'])->toBe('approved');
});