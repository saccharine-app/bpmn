<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Saccharine\Bpmn\Models\WorkflowDefinition;
use Saccharine\Bpmn\Contracts\BpmnTriggerableEvent;
use Saccharine\Bpmn\Listeners\WorkflowTriggerListener;

// A dummy event for testing that implements your strict contract
class DummyPaymentReceivedEvent implements BpmnTriggerableEvent {
    public function getBusinessKey(): string {
        return 'payment_12345';
    }
    public function getWorkflowPayload(): array {
        return ['amount' => 500];
    }
}

it('launches a workflow instance and enforces strict idempotency', function () {
    // Mock the configuration alias
    config(['saccharine-bpmn.triggers.payment_received' => DummyPaymentReceivedEvent::class]);

    // Scaffold a diagram with a StartEvent listening for this trigger
    $definition = WorkflowDefinition::create(['name' => 'Payment Process', 'key' => 'payment-proc']);
    $version = $definition->versions()->create(['version' => 1, 'bpmn_xml' => '<xml/>', 'is_active' => true]);
    $version->nodes()->create([
        'bpmn_element_id' => 'Start_1',
        'type'            => 'startEvent',
        'implementation'  => 'payment_received'
    ]);

    // Manually invoke the listener (simulating an application event)
    $listener = new WorkflowTriggerListener();
    $event = new DummyPaymentReceivedEvent();
    $listener->handle(DummyPaymentReceivedEvent::class, [$event]);

    // Assert the Instance and Log were created
    expect(DB::table('workflow_instances')->count())->toBe(1);
    expect(DB::table('workflow_triggers_log')->count())->toBe(1);
    
    $instance = DB::table('workflow_instances')->first();
    expect($instance->workflow_version_id)->toBe($version->id)
        ->and($instance->status)->toBe('running');

    // Fire the exact same event again (Idempotency Test)
    $listener->handle(DummyPaymentReceivedEvent::class, [$event]);

    // Assert no duplicates were created
    expect(DB::table('workflow_instances')->count())->toBe(1);
    expect(DB::table('workflow_triggers_log')->count())->toBe(1);
});