<?php

use Illuminate\Support\Facades\Route;
use Saccharine\BpmnEngine\Http\Controllers\WorkflowController;
use Saccharine\BpmnEngine\Http\Controllers\WorkflowInstanceController;

// NOTE: The standalone dashboard entry points (bpmn.index / bpmn.store) live in
// routes/dashboard.php so a Filament-only host can disable them via
// config('bpmn-engine.dashboard') while keeping everything below live.

// The design editor canvas
Route::get('/definitions/{definition}/design', [WorkflowController::class, 'design'])->name('bpmn.design');
Route::patch('/definitions/{definition}', [WorkflowController::class, 'update'])
    ->name('bpmn.definitions.update');
Route::delete('/definitions/{definition}', [WorkflowController::class, 'destroy'])
    ->name('bpmn.definitions.destroy');

// Instance Tracking & Control Routes
Route::get('/instances', [WorkflowInstanceController::class, 'index'])->name('bpmn.instances.index');
Route::post('/instances/{id}/suspend', [WorkflowInstanceController::class, 'suspend'])->name('bpmn.instances.suspend');
Route::post('/instances/{id}/resume', [WorkflowInstanceController::class, 'resume'])->name('bpmn.instances.resume');
Route::post('/instances/{id}/halt', [WorkflowInstanceController::class, 'halt'])->name('bpmn.instances.halt');