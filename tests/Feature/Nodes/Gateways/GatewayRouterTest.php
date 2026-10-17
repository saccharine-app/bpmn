<?php

use Saccharine\Bpmn\Services\GatewayRouter;
use Saccharine\Bpmn\Models\WorkflowDefinition;
use Saccharine\Bpmn\Models\WorkflowVersion;
use Saccharine\Bpmn\Models\WorkflowEdge;
use Saccharine\Bpmn\Models\WorkflowNode;

it('routes a token through an exclusive gateway based on user data', function () {
    // Scaffold the database state
    $definition = WorkflowDefinition::create(['name' => 'Purchasing', 'key' => 'purchase']);
    $version = $definition->versions()->create(['version' => 1, 'bpmn_xml' => '<xml/>']);

    // Create the Gateway Node
    $version->nodes()->create([
        'bpmn_element_id' => 'Gateway_Approval',
        'type'            => 'exclusiveGateway'
    ]);

    // Create Path A: High Value (Requires Manager)
    $version->edges()->create([
        'bpmn_element_id'      => 'Flow_HighValue',
        'source_node_id'       => 'Gateway_Approval',
        'target_node_id'       => 'Task_ManagerApproval',
        'condition_expression' => 'amount >= 1000'
    ]);

    // Create Path B: Low Value (Auto Approve)
    $version->edges()->create([
        'bpmn_element_id'      => 'Flow_LowValue',
        'source_node_id'       => 'Gateway_Approval',
        'target_node_id'       => 'Task_AutoApprove',
        'condition_expression' => 'amount < 1000'
    ]);

    // Instantiate the Router Service
    $router = new GatewayRouter();

    // Test Path A Execution
    $highValueData = ['amount' => 5000];
    $nextNode = $router->getNextNodeId($version, 'Gateway_Approval', $highValueData);
    
    expect($nextNode)->toBe('Task_ManagerApproval');

    // Test Path B Execution
    $lowValueData = ['amount' => 250];
    $nextNode = $router->getNextNodeId($version, 'Gateway_Approval', $lowValueData);
    
    expect($nextNode)->toBe('Task_AutoApprove');
});

it('falls back to a default route if no conditions match', function () {
    $definition = WorkflowDefinition::create(['name' => 'Routing', 'key' => 'routing']);
    $version = $definition->versions()->create(['version' => 1, 'bpmn_xml' => '<xml/>']);

    // Path A has a condition that will fail
    $version->edges()->create([
        'bpmn_element_id'      => 'Flow_Strict',
        'source_node_id'       => 'Gateway_1',
        'target_node_id'       => 'Task_Strict',
        'condition_expression' => 'status == "VIP"'
    ]);

    // Path B is a "Default Flow" (it has no condition expression)
    $version->edges()->create([
        'bpmn_element_id'      => 'Flow_Default',
        'source_node_id'       => 'Gateway_1',
        'target_node_id'       => 'Task_Fallback',
        'condition_expression' => null 
    ]);

    $router = new GatewayRouter();
    
    // Pass data that fails the VIP check
    $nextNode = $router->getNextNodeId($version, 'Gateway_1', ['status' => 'Standard']);
    
    expect($nextNode)->toBe('Task_Fallback');
});

it('throws an exception if a gateway hits a dead end', function () {
    $definition = WorkflowDefinition::create(['name' => 'DeadEnd', 'key' => 'dead-end']);
    $version = $definition->versions()->create(['version' => 1, 'bpmn_xml' => '<xml/>']);

    $version->edges()->create([
        'bpmn_element_id'      => 'Flow_Only',
        'source_node_id'       => 'Gateway_Broken',
        'target_node_id'       => 'Task_Next',
        'condition_expression' => 'score == 100' // Fails if score is not exactly 100
    ]);

    $router = new GatewayRouter();
    
    // We expect this to throw a specific RuntimeException because it has nowhere to go
    $router->getNextNodeId($version, 'Gateway_Broken', ['score' => 50]);
})->throws(RuntimeException::class, 'Workflow stalled: No valid path matches the conditions');