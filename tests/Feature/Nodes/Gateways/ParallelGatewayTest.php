<?php

use Saccharine\Bpmn\Models\WorkflowDefinition;
use Saccharine\Bpmn\Workflows\BpmnInterpreterWorkflow;
use Workflow\Activity;
use Workflow\WorkflowStub;
use Illuminate\Support\Facades\Artisan;

// Two dummy activities to run in parallel
class ProcessPaymentActivity extends Activity
{
    public function execute(array $userData): array
    {
        return ['payment_status' => 'cleared'];
    }
}

class GenerateInvoiceActivity extends Activity
{
    public function execute(array $userData): array
    {
        return ['invoice_generated' => true];
    }
}

it('executes multiple paths in parallel and merges the payload at the join', function () {
    config(['saccharine-bpmn.activities.process_payment' => ProcessPaymentActivity::class]);
    config(['saccharine-bpmn.activities.generate_invoice' => GenerateInvoiceActivity::class]);

    // Scaffold Database
    $definition = WorkflowDefinition::create(['name' => 'Parallel Processing', 'key' => 'parallel-test']);
    $version = $definition->versions()->create(['version' => 1, 'bpmn_xml' => '<xml/>']);

    // Nodes
    $version->nodes()->createMany([
        ['bpmn_element_id' => 'Start_1', 'type' => 'startEvent'],
        ['bpmn_element_id' => 'Gateway_Split', 'type' => 'parallelGateway'],
        ['bpmn_element_id' => 'Task_Pay', 'type' => 'serviceTask', 'implementation' => 'process_payment'],
        ['bpmn_element_id' => 'Task_Invoice', 'type' => 'serviceTask', 'implementation' => 'generate_invoice'],
        ['bpmn_element_id' => 'Gateway_Join', 'type' => 'parallelGateway'],
        ['bpmn_element_id' => 'End_1', 'type' => 'endEvent']
    ]);

    // Edges
    $version->edges()->createMany([
        ['bpmn_element_id' => 'Flow_1', 'source_node_id' => 'Start_1', 'target_node_id' => 'Gateway_Split'],
        
        // The Split
        ['bpmn_element_id' => 'Flow_2a', 'source_node_id' => 'Gateway_Split', 'target_node_id' => 'Task_Pay'],
        ['bpmn_element_id' => 'Flow_2b', 'source_node_id' => 'Gateway_Split', 'target_node_id' => 'Task_Invoice'],
        
        // The Join
        ['bpmn_element_id' => 'Flow_3a', 'source_node_id' => 'Task_Pay', 'target_node_id' => 'Gateway_Join'],
        ['bpmn_element_id' => 'Flow_3b', 'source_node_id' => 'Task_Invoice', 'target_node_id' => 'Gateway_Join'],
        
        // Post-Join
        ['bpmn_element_id' => 'Flow_4', 'source_node_id' => 'Gateway_Join', 'target_node_id' => 'End_1']
    ]);

    // Execute
    $workflow = WorkflowStub::make(BpmnInterpreterWorkflow::class);
    $workflow->start($version->id, ['order_id' => 123]);

    Artisan::call('queue:work', ['--stop-when-empty' => true]);

    // Assertions
    $workflow = WorkflowStub::load($workflow->id());
    
    expect($workflow->status())->toBe(\Workflow\States\WorkflowCompletedStatus::class);
    
    $result = $workflow->output();
    expect($result)->toBeArray()
        ->and($result['order_id'])->toBe(123)
        ->and($result['payment_status'])->toBe('cleared')
        ->and($result['invoice_generated'])->toBeTrue();
});