<?php

use Illuminate\Support\Facades\Route;
use Saccharine\Bpmn\Http\Controllers\ProcessDefinitionController;
use Saccharine\Bpmn\Http\Controllers\ProcessInstanceController;

/*
|--------------------------------------------------------------------------
| Core BPMN Web Routes
|--------------------------------------------------------------------------
|
| Prefix and middleware are applied by BpmnServiceProvider.
| Protected by session authentication and CSRF validation.
|
*/

// --- Canvas & Definition Management ---
Route::get('/definitions/{definition}/design', [ProcessDefinitionController::class, 'design'])
    ->name('bpmn.design');

Route::patch('/definitions/{definition}', [ProcessDefinitionController::class, 'update'])
    ->name('bpmn.definitions.update');

Route::delete('/definitions/{definition}', [ProcessDefinitionController::class, 'destroy'])
    ->name('bpmn.definitions.destroy');

Route::post('/definitions/{definition}/versions', [ProcessDefinitionController::class, 'storeVersion'])
    ->name('bpmn.definitions.versions.store');


// --- Instance Monitoring & Controls ---
Route::get('/instances', [ProcessInstanceController::class, 'index'])
    ->name('bpmn.instances.index');

Route::get('/instances/{id}/track', [ProcessInstanceController::class, 'track'])
    ->name('bpmn.instances.track');

Route::post('/instances/{id}/suspend', [ProcessInstanceController::class, 'suspend'])
    ->name('bpmn.instances.suspend');

Route::post('/instances/{id}/resume', [ProcessInstanceController::class, 'resume'])
    ->name('bpmn.instances.resume');

Route::post('/instances/{id}/halt', [ProcessInstanceController::class, 'halt'])
    ->name('bpmn.instances.halt');


// --- Internal Telemetry & Task Execution (Polled by Canvas / Web UI) ---
Route::prefix('/instances/{id}')->group(function () {
    Route::get('/tokens', [ProcessInstanceController::class, 'tokens'])
        ->name('bpmn.instances.tokens');

    Route::get('/telemetry', [ProcessInstanceController::class, 'telemetry'])
        ->name('bpmn.instances.telemetry');

    Route::post('/tasks/{elementId}/submit', [ProcessInstanceController::class, 'submitUserTask'])
        ->name('bpmn.instances.task.submit');
});
