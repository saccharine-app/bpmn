<?php

use Illuminate\Support\Facades\Route;
use Saccharine\BpmnEngine\Http\Controllers\WorkflowController;

/*
|--------------------------------------------------------------------------
| Standalone Blade Dashboard Routes
|--------------------------------------------------------------------------
|
| Registered only when config('bpmn-engine.dashboard') is true. These are the
| "index" entry points of the package; the shared, UI-agnostic routes (designer,
| instance control, version saves) always live in routes/web.php so a Filament
| host can disable this file without losing functionality.
|
*/

// Canonical entry point. Everything that links "back to the dashboard" resolves
// this route (see Saccharine\BpmnEngine\Support\BpmnRouteHelper).
Route::get('/definitions', [WorkflowController::class, 'index'])->name('bpmn.index');

// Process form submission to create new workflows
Route::post('/definitions', [WorkflowController::class, 'store'])->name('bpmn.store');

// Convenience alias so the documented short URL (/bpmn/workflows) does not 404.
// README and the install command both point users here.
Route::get('/', fn () => redirect()->route('bpmn.index'));
