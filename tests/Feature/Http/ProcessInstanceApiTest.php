<?php

use Saccharine\Bpmn\Models\ProcessDefinition;
use Saccharine\Bpmn\Models\ProcessInstance;
use Saccharine\Bpmn\Enums\ProcessInstanceStatus;

it('returns active node IDs and status for a process instance via API', function () {
    // Scaffold Definition and Version
    $definition = ProcessDefinition::create(['name' => 'API Track Test', 'key' => 'api-track-test']);
    $version = $definition->versions()->create(['version' => 1, 'bpmn_xml' => '<xml/>']);

    // Create Instance
    $instance = ProcessInstance::create([
        'process_version_id' => $version->id,
        'status'              => ProcessInstanceStatus::RUNNING,
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