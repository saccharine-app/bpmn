<?php

use Illuminate\Support\Facades\Route;
use Saccharine\BpmnEngine\Http\Controllers\WorkflowController;
use Saccharine\BpmnEngine\Http\Controllers\WorkflowInstanceController;

// The prefix and middleware are handled by the Service Provider
Route::post('/workflows/{definition}/versions', [WorkflowController::class, 'storeVersion']);

// Real-time instance tracking API
Route::get('/instances/{id}/tokens', [WorkflowInstanceController::class, 'tokens'])
    ->name('bpmn.api.instances.tokens');
Route::get('/instances/{id}/telemetry', [WorkflowInstanceController::class, 'telemetry'])
    ->name('bpmn.api.instances.telemetry');