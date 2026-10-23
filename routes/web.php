<?php

use Illuminate\Support\Facades\Route;
use Saccharine\Bpmn\Http\Controllers\WorkflowController;
use Saccharine\Bpmn\Http\Controllers\WorkflowInstanceController;

// NOTE: The standalone dashboard entry points (bpmn.index / bpmn.store) live in
// routes/dashboard.php so a Filament-only host can disable them via
// config('saccharine-bpmn.dashboard') while keeping everything below live.

// The design editor canvas
Route::get('/definitions/{definition}/design', [WorkflowController::class, 'design'])->name('bpmn.design');
Route::patch('/definitions/{definition}', [WorkflowController::class, 'update'])
    ->name('bpmn.definitions.update');
Route::delete('/definitions/{definition}', [WorkflowController::class, 'destroy'])
    ->name('bpmn.definitions.destroy');

// The prefix and middleware are handled by the Service Provider
Route::post('/workflows/{definition}/versions', [WorkflowController::class, 'storeVersion']);

// Instance Tracking & Control Routes
Route::get('/instances', [WorkflowInstanceController::class, 'index'])->name('bpmn.instances.index');
Route::get('/instances/{id}/track', [WorkflowInstanceController::class, 'track'])->name('bpmn.instances.track');
Route::post('/instances/{id}/suspend', [WorkflowInstanceController::class, 'suspend'])->name('bpmn.instances.suspend');
Route::post('/instances/{id}/resume', [WorkflowInstanceController::class, 'resume'])->name('bpmn.instances.resume');
Route::post('/instances/{id}/halt', [WorkflowInstanceController::class, 'halt'])->name('bpmn.instances.halt');

// Real-time instance tracking API
Route::get('/instances/{id}/tokens', [WorkflowInstanceController::class, 'tokens'])
    ->name('bpmn.api.instances.tokens');
Route::get('/instances/{id}/telemetry', [WorkflowInstanceController::class, 'telemetry'])
    ->name('bpmn.api.instances.telemetry');
Route::post('/instances/{id}/tasks/{elementId}/submit', [WorkflowInstanceController::class, 'submitUserTask'])
    ->name('bpmn.api.instances.task.submit');