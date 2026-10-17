<?php

use Saccharine\Bpmn\Models\WorkflowDefinition;
use Saccharine\Bpmn\Workflows\BpmnInterpreterWorkflow;
use Saccharine\Bpmn\Handlers\Nodes\Tasks\ServiceTaskHandler;
use Workflow\WorkflowStub;
use Workflow\ActivityStub;
use Workflow\Activity;

class DummyIsolatedActivity extends Activity {
    public function execute(array $userData): array { 
        return ['action' => 'done']; 
    }
}

it('yields an ActivityStub and successfully merges the returned payload', function () {
    config(['saccharine-bpmn.activities.isolated_task' => DummyIsolatedActivity::class]);

    $definition = WorkflowDefinition::create(['name' => 'Service Task Test', 'key' => 'service-task-test']);
    $version = $definition->versions()->create(['version' => 1, 'bpmn_xml' => '<xml/>']);
    
    $taskNode = $version->nodes()->create([
        'bpmn_element_id' => 'Task_1', 
        'type' => 'serviceTask', 
        'implementation' => 'isolated_task'
    ]);
    
    $version->nodes()->create(['bpmn_element_id' => 'Next_1', 'type' => 'endEvent']);
    $version->edges()->create(['bpmn_element_id' => 'Flow_1', 'source_node_id' => 'Task_1', 'target_node_id' => 'Next_1']);

    // Mock the workflow to intercept the activity creation
    $workflow = \Mockery::mock(BpmnInterpreterWorkflow::class)->makePartial();
    
    // Tell the mock to expect the makeActivity call and return a dummy string/object
    $workflow->shouldReceive('makeActivity')
             ->with(DummyIsolatedActivity::class, ['initial_data' => 123])
             ->once()
             ->andReturn('mocked_activity_stub');

    $handler = new ServiceTaskHandler();

    $userData = ['initial_data' => 123];
    $generator = $handler->handle($workflow, $taskNode, $version, $userData, null);

    // The first yield should halt the handler and return our mocked stub
    $yielded = $generator->current();
    expect($yielded)->toBe('mocked_activity_stub');

    // We simulate the background worker finishing the job by sending data back into the generator
    $generator->send(['simulated_result' => 'success']);

    // The generator finishes, returning the next node and the newly merged payload
    $result = $generator->getReturn();

    expect($result[0])->toBe('Next_1')
        ->and($result[1])->toBe([
            'initial_data' => 123, 
            'simulated_result' => 'success'
        ]);
});