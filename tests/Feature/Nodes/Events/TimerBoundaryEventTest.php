<?php

use Saccharine\Bpmn\Models\WorkflowDefinition;
use Saccharine\Bpmn\Workflows\BpmnInterpreterWorkflow;
use Saccharine\Bpmn\Handlers\Nodes\Tasks\UserTaskHandler;

it('routes to the timer boundary event path when the timeout expires', function () {
    $definition = WorkflowDefinition::create(['name' => 'Timer Test', 'key' => 'timer-test']);
    $version = $definition->versions()->create(['version' => 1, 'bpmn_xml' => '<xml/>', 'is_active' => true]);
    
    $userTask = $version->nodes()->create(['bpmn_element_id' => 'Task_User', 'type' => 'userTask']);
    $boundary = $version->nodes()->create([
        'bpmn_element_id'        => 'Boundary_Timer', 
        'type'                   => 'boundaryEvent', 
        'event_definition_type'  => 'timer', 
        'attached_to_element_id' => 'Task_User',
        'implementation'         => 'PT2H' //hours
    ]);
    
    // Normal Path
    $version->nodes()->create(['bpmn_element_id' => 'Next_Standard', 'type' => 'endEvent']);
    $version->edges()->create(['bpmn_element_id' => 'Flow_Normal', 'source_node_id' => 'Task_User', 'target_node_id' => 'Next_Standard']);
    
    // Timeout Path
    $version->nodes()->create(['bpmn_element_id' => 'Next_Timeout', 'type' => 'endEvent']);
    $version->edges()->create(['bpmn_element_id' => 'Flow_Timeout', 'source_node_id' => 'Boundary_Timer', 'target_node_id' => 'Next_Timeout']);

    // Mock the workflow
    $workflow = \Mockery::mock(BpmnInterpreterWorkflow::class)->makePartial();
    $workflow->shouldReceive('uniqueId')->andReturn('test-id-123');
    $workflow->shouldReceive('isSuspended')->andReturn(false);
    $workflow->shouldReceive('isHalted')->andReturn(false);
    
    // Mock the wrapper methods to simulate an empty inbox
    $workflow->shouldReceive('hasUnreadInbox')->andReturn(false);
    $workflow->shouldReceive('readNextInboxMessage')->andReturn(null);

    // Mock logTelemetry so it doesn't try to touch database/side-effects in unit tests
    $workflow->shouldReceive('logTelemetry')->andReturn(null);

    // Mock awaitWithTimeoutPromise so it doesn't touch durable-workflow internals
    $workflow->shouldReceive('awaitWithTimeoutPromise')
        ->once()
        ->andReturn('mocked_await_timeout');

    $handler = new UserTaskHandler();
    $userData = ['initial' => 'data'];
    
    $generator = $handler->handle($workflow, $userTask, $version, $userData, null);

    // Step through yields until we hit the awaitWithTimeout promise (or an awaitable)
    while ($generator->valid()) {
        $current = $generator->current();

        // If this yield is mocked_await_timeout,
        // send 'false' (simulating timer timeout) and break.
        if ($current === "mocked_await_timeout") {
            $generator->send(false);
            break;
        }

        // Otherwise it's a telemetry or sideEffect yield; step forward
        $generator->next();
    }

    // Exhaust any remaining telemetry yields after resumption
    while ($generator->valid()) {
        $generator->next();
    }

    // The generator finishes, returning the alternate node ID and payload
    $result = $generator->getReturn();

    expect($result[0])->toBe('Next_Timeout')
        ->and($result[1])->toBe([
            'initial' => 'data',
            '_timer_expired' => true
        ]);
});

it('routes to the normal path if the human completes the task before the timer expires', function () {
    $definition = WorkflowDefinition::create(['name' => 'Timer Test 2', 'key' => 'timer-test-2']);
    $version = $definition->versions()->create(['version' => 1, 'bpmn_xml' => '<xml/>', 'is_active' => true]);
    
    $userTask = $version->nodes()->create(['bpmn_element_id' => 'Task_User', 'type' => 'userTask']);
    $version->nodes()->create([
        'bpmn_element_id'        => 'Boundary_Timer', 
        'type'                   => 'boundaryEvent', 
        'event_definition_type'  => 'timer', 
        'attached_to_element_id' => 'Task_User',
        'implementation'         => 'PT2H'
    ]);
    
    $version->nodes()->create(['bpmn_element_id' => 'Next_Standard', 'type' => 'endEvent']);
    $version->edges()->create(['bpmn_element_id' => 'Flow_Normal', 'source_node_id' => 'Task_User', 'target_node_id' => 'Next_Standard']);

    $workflow = \Mockery::mock(BpmnInterpreterWorkflow::class)->makePartial();
    $workflow->shouldReceive('uniqueId')->andReturn('test-id-456');
    $workflow->shouldReceive('isSuspended')->andReturn(false);
    $workflow->shouldReceive('isHalted')->andReturn(false);

    // Mock the wrapper methods to simulate a human responding
    $workflow->shouldReceive('hasUnreadInbox')->andReturn(true);
    $workflow->shouldReceive('readNextInboxMessage')->andReturn(['human_action' => 'approved']);
    
    // Mock logTelemetry so it doesn't try to touch database/side-effects in unit tests
    $workflow->shouldReceive('logTelemetry')->andReturn(null);
    
    // Mock awaitWithTimeoutPromise so it doesn't touch durable-workflow internals
    $workflow->shouldReceive('awaitWithTimeoutPromise')
        ->once()
        ->andReturn('mocked_await_timeout');

    $handler = new UserTaskHandler();
    $generator = $handler->handle($workflow, $userTask, $version, [], null);

    // Step through yields until we hit the awaitWithTimeout promise (or an awaitable)
    while ($generator->valid()) {
        $current = $generator->current();

        // Simulate the human acting in time. mocked_await_timeout yields 'true'
        // if the closure condition was met before the timer ran out.
        if ($current === "mocked_await_timeout") {
            $generator->send(true);
            break;
        }

        // Otherwise it's a telemetry or sideEffect yield; step forward
        $generator->next();
    }

    // Exhaust any remaining telemetry yields after resumption
    while ($generator->valid()) {
        $generator->next();
    }

    $result = $generator->getReturn();

    // It should proceed down the normal path, completely ignoring the boundary event,
    // and successfully merge the human's inbox payload.
    expect($result[0])->toBe('Next_Standard')
        ->and($result[1])->toBe(['human_action' => 'approved']);
});