<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Saccharine\Bpmn\Support\BpmnRouteHelper;

/*
|--------------------------------------------------------------------------
| Route & Template Configuration
|--------------------------------------------------------------------------
|
| Guards the contract that keeps the two UIs (standalone Blade dashboard and
| Filament panel table) from drifting apart: a single config key drives both,
| one canonical entry point resolves to whichever UI is installed, and the
| published stubs never reference routes that do not exist.
|
*/

const SACCHARINE_BPMN_PACKAGE_PATH = __DIR__ . '/../../..';

it('resolves the canonical dashboard route from the prefix config key', function () {
    expect(route('bpmn.index'))->toBe('http://localhost:8000/bpmn/workflows/definitions');
});

it('registers the designer and instance routes under the same prefix', function () {
    expect(Route::has('bpmn.design'))->toBeTrue()
        ->and(Route::has('bpmn.instances.index'))->toBeTrue()
        ->and(Route::has('bpmn.instances.suspend'))->toBeTrue()
        ->and(Route::has('bpmn.instances.resume'))->toBeTrue()
        ->and(Route::has('bpmn.instances.halt'))->toBeTrue();
});

it('leaves the standalone web routes unauthenticated by default', function () {
    // The `['web']` default is deliberate (local/demo ergonomics); hosts opt
    // into auth via config('saccharine-bpmn.middleware'). See
    // MiddlewareConfigurationTest for the opt-in behaviour.
    $middleware = Route::getRoutes()->getByName('bpmn.design')->gatherMiddleware();

    expect($middleware)
        ->toContain('web')
        ->not->toContain('auth');
});

it('redirects the documented short URL to the dashboard instead of 404ing', function () {
    $this->get('/bpmn/workflows')
        ->assertRedirect(route('bpmn.index'));
});

it('renders the standalone dashboard at the canonical URL', function () {
    $this->get(route('bpmn.index'))->assertOk();
});

it('keeps the designer views bound to the canonical dashboard resolver', function () {
    $views = [
        'resources/views/partials/workbench-header.blade.php',
        'resources/views/editor.blade.php',
        'resources/views/instances/index.blade.php',
    ];

    foreach ($views as $view) {
        $contents = File::get(SACCHARINE_BPMN_PACKAGE_PATH . '/' . $view);

        expect($contents)
            ->toContain('BpmnRouteHelper::dashboardUrl()')
            ->not->toContain("route('bpmn.index')");
    }
});

it('falls back to the standalone dashboard when Filament is absent', function () {
    expect(BpmnRouteHelper::filamentResourceRoute())->toBeNull()
        ->and(BpmnRouteHelper::dashboardUrl())->toBe(route('bpmn.index'));
});

it('resolves the Filament resource route derived from the prefix key', function () {
    Route::get('/admin/bpmn/workflows/definitions', fn () => 'ok')
        ->name('filament.admin.resources.bpmn.workflows.definitions.index');

    expect(BpmnRouteHelper::filamentResourceRoute())
        ->toBe('filament.admin.resources.bpmn.workflows.definitions.index');
});

it('resolves a Filament slug that was derived from the resource namespace', function () {
    Route::get('/admin/bpmn/workflow-definitions', fn () => 'ok')
        ->name('filament.admin.resources.bpmn.workflow-definitions.index');

    expect(BpmnRouteHelper::filamentResourceRoute())
        ->toBe('filament.admin.resources.bpmn.workflow-definitions.index');
});

it('prefers an explicit dashboard_url override over every other resolution', function () {
    Route::get('/admin/bpmn/workflows/definitions', fn () => 'ok')
        ->name('filament.admin.resources.bpmn.workflows.definitions.index');

    config(['saccharine-bpmn.dashboard_url' => 'https://example.test/custom-dashboard']);

    expect(BpmnRouteHelper::dashboardUrl())->toBe('https://example.test/custom-dashboard');
});

it('prefers the Filament table over the standalone dashboard', function () {
    $router = app('router');

    $router->get('/admin/bpmn/workflows/definitions', fn () => 'ok')
        ->name('filament.admin.resources.bpmn.workflows.definitions.index');

    // Force the router and url generator to rebuild their name dictionaries
    $router->getRoutes()->refreshNameLookups();
    app('url')->setRoutes($router->getRoutes());

    expect(BpmnRouteHelper::dashboardUrl())
        ->toBe('http://localhost:8000/admin/bpmn/workflows/definitions');
});

it('forwards the canonical entry point to the Filament table when one is registered', function () {
    $router = app('router');

    $router->get('/admin/bpmn/workflows/definitions', fn () => 'ok')
        ->name('filament.admin.resources.bpmn.workflows.definitions.index');

    // Force the router and url generator to rebuild their name dictionaries
    $router->getRoutes()->refreshNameLookups();
    app('url')->setRoutes($router->getRoutes());

    $this->get('/bpmn/workflows/definitions')
        ->assertRedirect('http://localhost:8000/admin/bpmn/workflows/definitions');
});

it('pins the canonical entry point to the Blade dashboard when the redirect is disabled', function () {
    Route::get('/admin/bpmn/workflows/definitions', fn () => 'ok')
        ->name('filament.admin.resources.bpmn.workflows.definitions.index');

    config(['saccharine-bpmn.filament_redirect' => false]);

    $this->get('/bpmn/workflows/definitions')->assertOk();
});
