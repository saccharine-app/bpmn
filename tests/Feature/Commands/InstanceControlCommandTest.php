<?php

use Saccharine\Bpmn\Models\WorkflowDefinition;
use Saccharine\Bpmn\Models\WorkflowInstance;
use Saccharine\Bpmn\Enums\WorkflowInstanceStatus;
use Saccharine\Bpmn\Workflows\BpmnInterpreterWorkflow;
use Workflow\WorkflowStub;

beforeEach(function () {
    // Scaffold a baseline instance for the command to interact with
    $this->definition = WorkflowDefinition::create(['name' => 'CLI Test', 'key' => 'cli-test']);
    $this->version = $this->definition->versions()->create(['version' => 1, 'bpmn_xml' => '<xml/>']);
    
    $workflow = WorkflowStub::make(BpmnInterpreterWorkflow::class);
    
    $this->instance = WorkflowInstance::create([
        'workflow_version_id' => $this->version->id,
        'status' => WorkflowInstanceStatus::RUNNING,
        'durable_workflow_id' => $workflow->id(),
    ]);
});

it('suspends a running instance via CLI', function () {
    $this->artisan('bpmn:instance', [
        'action' => 'suspend',
        'id' => $this->instance->id,
    ])->expectsOutputToContain("Successfully suspended Workflow Instance [{$this->instance->id}].")
      ->assertSuccessful();

    $this->instance->refresh();
    expect($this->instance->status)->toBe(WorkflowInstanceStatus::SUSPENDED);
});

it('resumes a suspended instance via CLI', function () {
    // Manually suspend it first
    $this->instance->update(['status' => WorkflowInstanceStatus::SUSPENDED]);

    $this->artisan('bpmn:instance', [
        'action' => 'resume',
        'id' => $this->instance->id,
    ])->expectsOutputToContain("Successfully resumed Workflow Instance [{$this->instance->id}].")
      ->assertSuccessful();

    $this->instance->refresh();
    expect($this->instance->status)->toBe(WorkflowInstanceStatus::RUNNING);
});

it('halts an instance via CLI', function () {
    $this->artisan('bpmn:instance', [
        'action' => 'halt',
        'id' => $this->instance->id,
    ])->expectsOutputToContain("Successfully halted Workflow Instance [{$this->instance->id}].")
      ->assertSuccessful();

    $this->instance->refresh();
    expect($this->instance->status)->toBe(WorkflowInstanceStatus::HALTED);
});

it('lists active instances via CLI', function () {
    $this->artisan('bpmn:instance', ['action' => 'list'])
         ->expectsOutputToContain('CLI Test') // Definition Name
         // ->expectsOutputToContain('running')  // Status
         ->assertSuccessful();
});