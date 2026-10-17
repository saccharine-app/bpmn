<?php

use Saccharine\Bpmn\Models\WorkflowDefinition;
use Saccharine\Bpmn\Models\WorkflowVersion;
use Saccharine\Bpmn\Workflows\BpmnInterpreterWorkflow;
use Workflow\Activity;
use Workflow\WorkflowStub;

// A dummy activity representing the host application's business logic
class CalculateTaxActivity extends Activity
{
    public function execute(array $userData): array
    {
        return ['tax' => $userData['subtotal'] * 0.10];
    }
}

it('executes a workflow path and updates the user data payload', function () {
    // Bind the dummy activity to our package's config registry
    config(['saccharine-bpmn.activities.calculate_tax' => CalculateTaxActivity::class]);

    // Scaffold the database state
    $definition = WorkflowDefinition::create(['name' => 'Checkout', 'key' => 'checkout']);
    $version = $definition->versions()->create(['version' => 1, 'bpmn_xml' => '<xml/>']);

    // Nodes
    $version->nodes()->create(['bpmn_element_id' => 'Start_1', 'type' => 'startEvent']);
    $version->nodes()->create([
        'bpmn_element_id' => 'Task_Tax', 
        'type' => 'serviceTask', 
        'implementation' => 'calculate_tax' // Matches the config key
    ]);
    $version->nodes()->create(['bpmn_element_id' => 'End_1', 'type' => 'endEvent']);

    // Edges
    $version->edges()->create(['bpmn_element_id' => 'Flow_1', 'source_node_id' => 'Start_1', 'target_node_id' => 'Task_Tax']);
    $version->edges()->create(['bpmn_element_id' => 'Flow_2', 'source_node_id' => 'Task_Tax', 'target_node_id' => 'End_1']);

    // Start the Durable Workflow
    $workflow = WorkflowStub::make(BpmnInterpreterWorkflow::class);
    $workflow->start($version->id, ['subtotal' => 100]);

    // Process the queue deterministically!
    // This fires up a local worker, processes the workflow, processes the activity, 
    // wakes the workflow back up, finishes the execution, and then stops cleanly.
    \Illuminate\Support\Facades\Artisan::call('queue:work', [
        '--stop-when-empty' => true,
    ]);

    // CRITICAL: Reload the workflow object from the database to get the fresh state
    $workflow = WorkflowStub::load($workflow->id());

    if ($workflow->status() === 'failed') {
        $failedRecord = \Workflow\Models\Workflow::find($workflow->id());
        dd("Workflow failed in the background!", $failedRecord->output);
    }

    $result = $workflow->output();

    // The subtotal should still exist, and the new 'tax' key should have been added by the Activity
    expect($result)->toBeArray()
        ->and($result['subtotal'])->toBe(100)
        ->and($result['tax'])->toBe(10.0);
});