<?php

use Illuminate\Support\Facades\Route;
use Saccharine\Bpmn\Http\Controllers\WorkflowController;
use Saccharine\Bpmn\Http\Controllers\WorkflowInstanceController;

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
Route::get('/definitions/{definition}/design', [WorkflowController::class, 'design'])
    ->name('bpmn.design');

Route::patch('/definitions/{definition}', [WorkflowController::class, 'update'])
    ->name('bpmn.definitions.update');

Route::delete('/definitions/{definition}', [WorkflowController::class, 'destroy'])
    ->name('bpmn.definitions.destroy');

Route::post('/definitions/{definition}/versions', [WorkflowController::class, 'storeVersion'])
    ->name('bpmn.definitions.versions.store');


// --- Instance Monitoring & Controls ---
Route::get('/instances', [WorkflowInstanceController::class, 'index'])
    ->name('bpmn.instances.index');

Route::get('/instances/{id}/track', [WorkflowInstanceController::class, 'track'])
    ->name('bpmn.instances.track');

Route::post('/instances/{id}/suspend', [WorkflowInstanceController::class, 'suspend'])
    ->name('bpmn.instances.suspend');

Route::post('/instances/{id}/resume', [WorkflowInstanceController::class, 'resume'])
    ->name('bpmn.instances.resume');

Route::post('/instances/{id}/halt', [WorkflowInstanceController::class, 'halt'])
    ->name('bpmn.instances.halt');


// --- Internal Telemetry & Task Execution (Polled by Canvas / Web UI) ---
Route::prefix('/instances/{id}')->group(function () {
    Route::get('/tokens', [WorkflowInstanceController::class, 'tokens'])
        ->name('bpmn.instances.tokens');

    Route::get('/telemetry', [WorkflowInstanceController::class, 'telemetry'])
        ->name('bpmn.instances.telemetry');

    Route::post('/tasks/{elementId}/submit', [WorkflowInstanceController::class, 'submitUserTask'])
        ->name('bpmn.instances.task.submit');
});
