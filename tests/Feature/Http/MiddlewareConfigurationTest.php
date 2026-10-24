<?php

use Illuminate\Support\Facades\Route;
use Saccharine\Bpmn\Models\WorkflowDefinition;

/*
|--------------------------------------------------------------------------
| Authenticated Route Configuration
|--------------------------------------------------------------------------
|
| Guards the `saccharine-bpmn.middleware` contract: the config key must drive
| the standalone web routes at registration time, and enabling `auth` must
| produce a proper guest redirect (Filament hosts need a `login` route or
| redirectGuestsTo — without one, Laravel throws "Route [login] not
| defined" and guests get a 500 instead of a login page).
|
*/

uses(Saccharine\Bpmn\Tests\Concerns\ForcesBpmnAuthMiddleware::class);

it('applies the configured auth middleware to the designer route', function () {
    $middleware = Route::getRoutes()->getByName('bpmn.design')->gatherMiddleware();

    expect($middleware)
        ->toContain('web')
        ->toContain('auth');
});

it('redirects guests to the login route instead of failing on the request', function () {
    // Simulate a host that defines a login route (e.g. via
    // $middleware->redirectGuestsTo('/admin/login') or a route named `login`).
    Route::get('/login', fn () => 'login form')->name('login');

    $definition = WorkflowDefinition::create([
        'name' => 'Order Fulfilment',
        'key'  => 'order-fulfilment',
    ]);

    $this->get(route('bpmn.design', $definition->id))
        ->assertRedirect('http://localhost:8000/login');
});
