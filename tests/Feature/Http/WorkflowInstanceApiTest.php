<?php

use Saccharine\Bpmn\Models\WorkflowDefinition;
use Saccharine\Bpmn\Models\WorkflowInstance;
use Saccharine\Bpmn\Enums\WorkflowInstanceStatus;

it('returns active node IDs and status for a workflow instance via API', function () {
    // Scaffold Definition and Version
    $definition = WorkflowDefinition::create(['name' => 'API Track Test', 'key' => 'api-track-test']);
    $version = $definition->versions()->create(['version' => 1, 'bpmn_xml' => '<xml/>']);

    // Create Instance
    $instance = WorkflowInstance::create([
        'workflow_version_id' => $version->id,
        'status'              => WorkflowInstanceStatus::RUNNING,
        'durable_workflow_id' => 'dummy-durable-id-123',
    ]);

    // Attach two active parallel tokens
    $instance->tokens()->createMany([
        ['durable_workflow_id' => 'child-thread-1', 'bpmn_element_id' => 'Task_ProcessPayment'],
        ['durable_workflow_id' => 'child-thread-2', 'bpmn_element_id' => 'Task_GenerateInvoice'],
    ]);

    // Hit the Endpoint
    $response = $this->getJson(route('bpmn.instances.tokens', $instance->id));

    // Assertions
    $response->assertStatus(200)
        ->assertJson([
            'instance_id'     => $instance->id,
            'status'          => 'running',
            'active_node_ids' => [
                'Task_ProcessPayment',
                'Task_GenerateInvoice',
            ],
        ]);
});