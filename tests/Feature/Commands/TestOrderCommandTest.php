<?php

use Saccharine\Bpmn\Models\DemoOrder;
use Illuminate\Support\Facades\Event;
use Saccharine\Bpmn\Events\ModelEventTrigger;

it('creates a demo order via CLI and triggers the model event', function () {
    Event::fake([ModelEventTrigger::class]);

    $this->artisan('bpmn:test-order', [
        '--amount' => '450.00',
        '--email'  => 'customer@test.com',
        '--name'   => 'John Tester',
    ])
    ->expectsOutputToContain('Order Created Successfully')
    ->expectsOutputToContain('450')
    ->assertSuccessful();

    expect(DemoOrder::count())->toBe(1);

    $order = DemoOrder::first();
    expect($order->customer_email)->toBe('customer@test.com')
        ->and((float) $order->total)->toBe(450.00);

    Event::assertDispatched(ModelEventTrigger::class, function ($event) use ($order) {
        return $event->model->id === $order->id && $event->action === 'created';
    });
});