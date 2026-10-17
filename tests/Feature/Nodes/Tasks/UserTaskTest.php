<?php

use Saccharine\Bpmn\Models\WorkflowDefinition;
use Saccharine\Bpmn\Models\WorkflowVersion;
use Saccharine\Bpmn\Workflows\BpmnInterpreterWorkflow;
use Workflow\WorkflowStub;
use Illuminate\Support\Facades\Artisan;

it('pauses execution at a userTask and resumes when a signal is received', function () {
    // Scaffold database state
    $definition = WorkflowDefinition::create(['name' => 'Human Verification', 'key' => 'human-verify']);
    $version = $definition->versions()->create(['version' => 1, 'bpmn_xml' => '<xml/>']);

    // Nodes
    $version->nodes()->create(['bpmn_element_id' => 'Start_1', 'type' => 'startEvent']);
    $version->nodes()->create([
        'bpmn_element_id' => 'Task_HumanApproval', 
        'type'            => 'userTask', 
        'implementation'  => 'manager-role' // Can be used by host app to restrict access
    ]);
    $version->nodes()->create(['bpmn_element_id' => 'End_1', 'type' => 'endEvent']);

    // Edges
    $version->edges()->create(['bpmn_element_id' => 'Flow_1', 'source_node_id' => 'Start_1', 'target_node_id' => 'Task_HumanApproval']);
    $version->edges()->create(['bpmn_element_id' => 'Flow_2', 'source_node_id' => 'Task_HumanApproval', 'target_node_id' => 'End_1']);

    // Start the workflow execution loop
    $workflow = WorkflowStub::make(BpmnInterpreterWorkflow::class);
    $workflow->start($version->id, ['submitted_by' => 'John Doe']);

    // Run the worker to process the Start Event up to the userTask
    Artisan::call('queue:work', ['--stop-when-empty' => true]);

    // ASSERTION: The workflow MUST still be running (paused, hibernating)
    $workflow = WorkflowStub::load($workflow->id());
    expect($workflow->running())->toBeTrue();
    expect($workflow->output())->toBeNull();

    // Simulate the Host App Action (e.g., clicking a button)
    $formResponsePayload = [
        'approved_by' => 'Admin Jane',
        'decision'    => 'approved'
    ];
    
    // Call the newly defined SignalMethod directly on the stub!
    $workflow->submitUserTask($formResponsePayload);

    // Run the worker again to pick up the signal and finish the loop
    Artisan::call('queue:work', ['--stop-when-empty' => true]);

    // FINAL ASSERTIONS
    $workflow = WorkflowStub::load($workflow->id());
    expect($workflow->running())->toBeFalse();
});