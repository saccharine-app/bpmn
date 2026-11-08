<?php

use Illuminate\Support\Facades\Route;
use Saccharine\Bpmn\Http\Controllers\ProcessDefinitionController;

/*
|--------------------------------------------------------------------------
| Standalone Blade Dashboard Routes
|--------------------------------------------------------------------------
|
| Registered only when config('saccharine-bpmn.dashboard') is true. These are the
| "index" entry points of the package; the shared, UI-agnostic routes (designer,
| instance control, version saves) always live in routes/web.php so a Filament
| host can disable this file without losing functionality.
|
*/

// Canonical entry point. Everything that links "back to the dashboard" resolves
// this route (see Saccharine\Bpmn\Support\BpmnRouteHelper).
Route::get('/definitions', [ProcessDefinitionController::class, 'index'])->name('bpmn.index');

// Process form submission to create new processes
Route::post('/definitions', [ProcessDefinitionController::class, 'store'])->name('bpmn.store');

// Convenience alias so the documented short URL (/bpmn/processes) does not 404.
// README and the install command both point users here.
Route::get('/', fn () => redirect()->route('bpmn.index'));
