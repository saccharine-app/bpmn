<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Saccharine\BpmnEngine\Support\BpmnRouteHelper;

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

const BPMN_ENGINE_PACKAGE_PATH = __DIR__ . '/../../..';

it('resolves the canonical dashboard route from the prefix config key', function () {
    expect(route('bpmn.index'))->toBe('http://localhost/bpmn/workflows/definitions');
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
    // into auth via config('bpmn-engine.middleware'). See
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
    $this->get('/bpmn/workflows/definitions')->assertOk();
});

it('only references routes that actually exist from the published stubs', function () {
    $routes = collect(File::files(BPMN_ENGINE_PACKAGE_PATH . '/stubs/filament'))
        ->map(fn ($file) => File::get($file->getPathname()))
        ->implode("\n");

    // Only match route('name') calls. `::route('/')` is Filament's page path
    // definition, not a named route, so look behind to exclude it.
    preg_match_all("/(?<!::)route\\('([^']+)'/", $routes, $matches);

    foreach ($matches[1] as $referenced) {
        expect(Route::has($referenced))->toBeTrue(
            "The Filament stub references route('{$referenced}'), which is not registered by the package."
        );
    }
});

it('derives the Filament slug from the shared prefix key rather than hardcoding it', function () {
    foreach (['WorkflowDefinitionResource', 'WorkflowInstanceResource'] as $stub) {
        $contents = File::get(BPMN_ENGINE_PACKAGE_PATH . "/stubs/filament/{$stub}.stub");

        expect($contents)
            ->toContain("config('bpmn-engine.prefix'")
            ->not->toContain("'bpmn/workflows/definitions'")
            ->not->toContain("'bpmn/workflows/instances'");
    }
});

it('keeps the designer views bound to the canonical dashboard resolver', function () {
    $views = [
        'resources/views/partials/workbench-header.blade.php',
        'resources/views/editor.blade.php',
        'resources/views/instances/index.blade.php',
    ];

    foreach ($views as $view) {
        $contents = File::get(BPMN_ENGINE_PACKAGE_PATH . '/' . $view);

        expect($contents)
            ->toContain('BpmnRouteHelper::dashboardUrl()')
            ->not->toContain("route('bpmn.index')");
    }
});

it('builds the designer save URL from the api_prefix config key', function () {
    $contents = File::get(BPMN_ENGINE_PACKAGE_PATH . '/resources/views/editor.blade.php');

    expect($contents)
        ->toContain("config('bpmn-engine.api_prefix'")
        ->not->toContain("'/api/bpmn/workflows/");
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

    config(['bpmn-engine.dashboard_url' => 'https://example.test/custom-dashboard']);

    expect(BpmnRouteHelper::dashboardUrl())->toBe('https://example.test/custom-dashboard');
});

it('prefers the Filament table over the standalone dashboard', function () {
    Route::get('/admin/bpmn/workflows/definitions', fn () => 'ok')
        ->name('filament.admin.resources.bpmn.workflows.definitions.index');

    expect(BpmnRouteHelper::dashboardUrl())
        ->toBe('http://localhost/admin/bpmn/workflows/definitions');
});

it('forwards the canonical entry point to the Filament table when one is registered', function () {
    Route::get('/admin/bpmn/workflows/definitions', fn () => 'ok')
        ->name('filament.admin.resources.bpmn.workflows.definitions.index');

    $this->get('/bpmn/workflows/definitions')
        ->assertRedirect('http://localhost/admin/bpmn/workflows/definitions');
});

it('pins the canonical entry point to the Blade dashboard when the redirect is disabled', function () {
    Route::get('/admin/bpmn/workflows/definitions', fn () => 'ok')
        ->name('filament.admin.resources.bpmn.workflows.definitions.index');

    config(['bpmn-engine.filament_redirect' => false]);

    $this->get('/bpmn/workflows/definitions')->assertOk();
});
