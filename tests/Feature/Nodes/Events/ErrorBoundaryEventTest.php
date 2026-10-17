<?php

use Saccharine\Bpmn\Models\WorkflowDefinition;
use Saccharine\Bpmn\Workflows\BpmnInterpreterWorkflow;
use Workflow\Activity;
use Workflow\WorkflowStub;
use Illuminate\Support\Facades\Artisan;
use Workflow\RetryPolicy;

// A dummy activity that is guaranteed to crash instantly without retries
class FailingApiActivity extends Activity
{
    public $tries = 1; // Tell Laravel's queue worker to only try once

    public function retryPolicy(): RetryPolicy
    {
        // Tell Durable Workflow to disable its automatic exponential backoff
        return RetryPolicy::new()->setMaximumAttempts(1);
    }

    public function execute(array $userData): array
    {
        throw new \Exception("Simulated API Timeout Failure");
    }
}

it('catches an exception using an error boundary event and routes to an alternate path', function () {
    // Bind the failing activity to the config
    config(['saccharine-bpmn.activities.failing_task' => FailingApiActivity::class]);

    // Scaffold Database
    $definition = WorkflowDefinition::create(['name' => 'Error Boundary', 'key' => 'error-boundary']);
    $version = $definition->versions()->create(['version' => 1, 'bpmn_xml' => '<xml/>']);

    // Nodes
    $version->nodes()->createMany([
        ['bpmn_element_id' => 'Start_1', 'type' => 'startEvent'],
        ['bpmn_element_id' => 'Task_Fail', 'type' => 'serviceTask', 'implementation' => 'failing_task'],
        // The Parasitic Boundary Event
        [
            'bpmn_element_id' => 'Boundary_Error', 
            'type' => 'boundaryEvent', 
            'event_definition_type' => 'error', 
            'attached_to_element_id' => 'Task_Fail'
        ],
        ['bpmn_element_id' => 'End_Success', 'type' => 'endEvent'],
        ['bpmn_element_id' => 'End_Fallback', 'type' => 'endEvent']
    ]);

    // Edges
    $version->edges()->createMany([
        ['bpmn_element_id' => 'Flow_1', 'source_node_id' => 'Start_1', 'target_node_id' => 'Task_Fail'],
        // Normal path (Should NOT be reached)
        ['bpmn_element_id' => 'Flow_2', 'source_node_id' => 'Task_Fail', 'target_node_id' => 'End_Success'], 
        // Error path (Should be reached)
        ['bpmn_element_id' => 'Flow_3', 'source_node_id' => 'Boundary_Error', 'target_node_id' => 'End_Fallback'] 
    ]);

    // Start Workflow
    $workflow = WorkflowStub::make(BpmnInterpreterWorkflow::class);
    $workflow->start($version->id, ['initial' => 'data']);

    // Process the queue
    Artisan::call('queue:work', ['--stop-when-empty' => true]);

    // Assertions
    $workflow = WorkflowStub::load($workflow->id());
    
    // The workflow should complete successfully because the error was handled
    expect($workflow->status())->toBe(\Workflow\States\WorkflowCompletedStatus::class);

    $output = $workflow->output();
    expect($output)->toBeArray()
        ->and($output['_error_caught'])->toContain('Simulated API Timeout Failure');
});

it('fails the workflow entirely if an exception occurs and no boundary event is attached', function () {
    config(['saccharine-bpmn.activities.failing_task' => FailingApiActivity::class]);

    $definition = WorkflowDefinition::create(['name' => 'No Boundary', 'key' => 'no-boundary']);
    $version = $definition->versions()->create(['version' => 1, 'bpmn_xml' => '<xml/>']);

    $version->nodes()->createMany([
        ['bpmn_element_id' => 'Start_1', 'type' => 'startEvent'],
        ['bpmn_element_id' => 'Task_Fail', 'type' => 'serviceTask', 'implementation' => 'failing_task'],
        ['bpmn_element_id' => 'End_Success', 'type' => 'endEvent']
    ]);

    $version->edges()->createMany([
        ['bpmn_element_id' => 'Flow_1', 'source_node_id' => 'Start_1', 'target_node_id' => 'Task_Fail'],
        ['bpmn_element_id' => 'Flow_2', 'source_node_id' => 'Task_Fail', 'target_node_id' => 'End_Success']
    ]);

    $workflow = WorkflowStub::make(BpmnInterpreterWorkflow::class);
    $workflow->start($version->id, []);

    Artisan::call('queue:work', ['--stop-when-empty' => true]);

    $workflow = WorkflowStub::load($workflow->id());
    
    // The workflow should crash and be marked as failed by Durable Workflow
    expect($workflow->status())->toBe(\Workflow\States\WorkflowFailedStatus::class);
});