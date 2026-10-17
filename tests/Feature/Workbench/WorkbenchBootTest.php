<?php

use Illuminate\Support\Facades\Route;
use Saccharine\Bpmn\BpmnServiceProvider;
use Saccharine\Bpmn\Models\WorkflowDefinition;
use Workbench\App\Providers\WorkbenchServiceProvider;
use Workbench\Database\Seeders\DatabaseSeeder;

/*
|--------------------------------------------------------------------------
| Workbench Integration Smoke Test
|--------------------------------------------------------------------------
|
| Proves the package boots end-to-end inside Orchestra Workbench with no host
| application: providers resolve, package + workbench migrations run, the
| workbench seeder populates a workflow definition, and the package's routes
| are registered and reachable.
|
*/

it('boots the package and workbench providers via testbench.yaml', function () {
    expect($this->app->getProviders(BpmnServiceProvider::class))->not->toBeEmpty()
        ->and($this->app->getProviders(WorkbenchServiceProvider::class))->not->toBeEmpty();
});

it('runs the package migrations and workbench seeder', function () {
    // The workbench `seeders:` entry is invoked on-demand (via `$this->seed()`),
    // matching Laravel's RefreshDatabase convention. Seeding here proves the
    // workbench DatabaseSeeder resolves and populates a workflow definition.
    $this->seed(DatabaseSeeder::class);

    expect(WorkflowDefinition::where('key', 'workbench-onboarding')->exists())->toBeTrue();
});

it('registers the package web routes without a host application', function () {
    $names = Route::getRoutes()->getRoutesByName();

    expect($names)->toHaveKeys(['bpmn.instances.index', 'bpmn.design']);
});

it('serves an instance dashboard route through the workbench app', function () {
    $this->get(route('bpmn.instances.index'))->assertOk();
});
