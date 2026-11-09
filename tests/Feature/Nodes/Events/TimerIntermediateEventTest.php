<?php

use Saccharine\Bpmn\Models\ProcessDefinition;
use Saccharine\Bpmn\Models\ProcessNode;
use Saccharine\Bpmn\Models\ProcessEdge;
use Saccharine\Bpmn\Services\BpmnParserService;
use Saccharine\Bpmn\Workflows\BpmnInterpreterWorkflow;
use Saccharine\Bpmn\Handlers\Nodes\Events\IntermediateCatchEventHandler;
use Workflow\V2\Activity;
use Workflow\V2\WorkflowStub;
use Illuminate\Support\Facades\Artisan;

// A dummy activity to verify execution continues after the timer elapses
final class PostTimerActivity extends Activity
{
    public function handle(array $userData): array
    {
        return ['resumed_after_timer' => true];
    }
}

it('parses an intermediate timer catch event and extracts the ISO duration', function () {
    $definition = ProcessDefinition::create([
        'name' => 'Timer Catch Process',
        'key'  => 'timer-catch-process',
    ]);

    $version = $definition->versions()->create([
        'version'   => 1,
        'bpmn_xml'  => '<xml/>',
        'is_active' => true,
    ]);

    $xmlString = <<<XML
    <?xml version="1.0" encoding="UTF-8"?>
    <bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL" id="Definitions_1">
      <bpmn:process id="Process_1" isExecutable="true">
        <bpmn:intermediateCatchEvent id="Event_Delay" name="Wait 2 Hours">
          <bpmn:timerEventDefinition id="TimerDef_1">
            <bpmn:timeDuration>PT2H</bpmn:timeDuration>
          </bpmn:timerEventDefinition>
        </bpmn:intermediateCatchEvent>
      </bpmn:process>
    </bpmn:definitions>
    XML;

    $parser = new BpmnParserService();
    $parser->parseAndStore($version, $xmlString);

    $node = ProcessNode::where('bpmn_element_id', 'Event_Delay')->first();

    expect($node)->not->toBeNull()
        ->and($node->type)->toBe('intermediateCatchEvent')
        ->and($node->event_definition_type)->toBe('timer')
        ->and($node->implementation)->toBe('PT2H');
});

it('suspends on timer in isolation and advances to the next sequential node', function () {
    $definition = ProcessDefinition::create(['name' => 'Delay Flow', 'key' => 'delay-flow']);
    $version = $definition->versions()->create(['version' => 1, 'bpmn_xml' => '<xml/>', 'is_active' => true]);

    $timerNode = $version->nodes()->create([
        'bpmn_element_id'       => 'Event_Wait',
        'type'                  => 'intermediateCatchEvent',
        'event_definition_type' => 'timer',
        'implementation'        => 'PT30M',
    ]);

    $version->nodes()->create(['bpmn_element_id' => 'Next_Node', 'type' => 'endEvent']);
    $version->edges()->create([
        'bpmn_element_id' => 'Flow_After_Timer',
        'source_node_id'  => 'Event_Wait',
        'target_node_id'  => 'Next_Node',
    ]);

    $version->load(['nodes', 'edges']);

    // Mock the workflow interpreter to intercept the timer call
    $workflow = \Mockery::mock(BpmnInterpreterWorkflow::class)->makePartial();
    $workflow->shouldReceive('workflowId')->andReturn('wf-test-timer-123');
    $workflow->shouldReceive('logTelemetry')->andReturnNull();

    // In v2, the handler delegates timer sleep to the workflow wrapper
    $workflow->shouldReceive('timerPromise')
        ->with('PT30M')
        ->once()
        ->andReturnNull();

    $handler = new IntermediateCatchEventHandler();
    $userData = ['initial' => 'payload'];

    $result = $handler->handle($workflow, $timerNode, $version, $userData, null);

    // Expect node advancement to the target of the sequence flow and preservation of payload
    expect($result[0])->toBe('Next_Node')
        ->and($result[1])->toBe(['initial' => 'payload']);
});

it('executes an end-to-end process path pausing through a timer catch event', function () {
    config(['saccharine-bpmn.activities.post_timer_action' => PostTimerActivity::class]);

    $definition = ProcessDefinition::create(['name' => 'Full Timer Flow', 'key' => 'full-timer-flow']);
    $version = $definition->versions()->create(['version' => 1, 'bpmn_xml' => '<xml/>', 'is_active' => true]);

    // 1. Scaffold Nodes: Start -> Timer (0 seconds / PT0S for instant test resolution) -> Activity -> End
    $version->nodes()->createMany([
        ['bpmn_element_id' => 'Start_1', 'type' => 'startEvent'],
        [
            'bpmn_element_id'       => 'Timer_Delay',
            'type'                  => 'intermediateCatchEvent',
            'event_definition_type' => 'timer',
            'implementation'        => 'PT0S', // Zero-second duration for test speed
        ],
        [
            'bpmn_element_id' => 'Task_Followup',
            'type'            => 'serviceTask',
            'implementation'  => 'post_timer_action',
        ],
        ['bpmn_element_id' => 'End_1', 'type' => 'endEvent'],
    ]);

    // 2. Scaffold Sequence Edges
    $version->edges()->createMany([
        ['bpmn_element_id' => 'Flow_1', 'source_node_id' => 'Start_1', 'target_node_id' => 'Timer_Delay'],
        ['bpmn_element_id' => 'Flow_2', 'source_node_id' => 'Timer_Delay', 'target_node_id' => 'Task_Followup'],
        ['bpmn_element_id' => 'Flow_3', 'source_node_id' => 'Task_Followup', 'target_node_id' => 'End_1'],
    ]);

    // 3. Launch process instance
    $workflow = WorkflowStub::make(BpmnInterpreterWorkflow::class);
    $workflow->start($version->id, ['user_id' => 42]);

    // Process initial step (Start -> Timer)
    Artisan::call('queue:work', ['--stop-when-empty' => true]);

    // In a production delayed timer, queue:work would sleep or schedule a job.
    // With PT0S or upon queue advancement, the worker processes the resumed timer and followup task.
    Artisan::call('queue:work', ['--stop-when-empty' => true]);

    $workflow = WorkflowStub::load($workflow->id());

    expect($workflow->status())->toBe(\Workflow\V2\Enums\RunStatus::Completed->value);

    $output = $workflow->output();
    expect($output)->toBeArray()
        ->and($output['user_id'])->toBe(42)
        ->and($output['resumed_after_timer'])->toBeTrue();
});