<?php

use Saccharine\Bpmn\Models\WorkflowDefinition;
use Saccharine\Bpmn\Workflows\BpmnInterpreterWorkflow;
use Saccharine\Bpmn\Handlers\Nodes\Gateways\ExclusiveGatewayHandler;
use Workflow\WorkflowStub;

it('evaluates expression logic and routes the token to the correct path', function () {
    $definition = WorkflowDefinition::create(['name' => 'Gateway Test', 'key' => 'gateway-test']);
    $version = $definition->versions()->create(['version' => 1, 'bpmn_xml' => '<xml/>']);
    
    $gatewayNode = $version->nodes()->create(['bpmn_element_id' => 'Gateway_1', 'type' => 'exclusiveGateway']);

    // Path A: High Value
    $version->edges()->create([
        'bpmn_element_id' => 'Flow_High',
        'source_node_id' => 'Gateway_1',
        'target_node_id' => 'Task_High',
        'condition_expression' => 'amount > 1000'
    ]);

    // Path B: Low Value
    $version->edges()->create([
        'bpmn_element_id' => 'Flow_Low',
        'source_node_id' => 'Gateway_1',
        'target_node_id' => 'Task_Low',
        'condition_expression' => 'amount <= 1000'
    ]);

    // $workflow = WorkflowStub::make(BpmnInterpreterWorkflow::class);
    $workflow = \Mockery::mock(BpmnInterpreterWorkflow::class)->makePartial();
    $handler = new ExclusiveGatewayHandler();

    // Test Path A execution
    $generatorA = $handler->handle($workflow, $gatewayNode, $version, ['amount' => 1500], null);
    while ($generatorA->valid()) { $generatorA->next(); }
    $resultA = $generatorA->getReturn();
    
    expect($resultA[0])->toBe('Task_High');

    // Test Path B execution
    $generatorB = $handler->handle($workflow, $gatewayNode, $version, ['amount' => 500], null);
    while ($generatorB->valid()) { $generatorB->next(); }
    $resultB = $generatorB->getReturn();
    
    expect($resultB[0])->toBe('Task_Low');
});