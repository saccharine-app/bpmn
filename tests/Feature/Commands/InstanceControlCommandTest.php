<?php

use Saccharine\Bpmn\Models\ProcessDefinition;
use Saccharine\Bpmn\Models\ProcessInstance;
use Saccharine\Bpmn\Enums\ProcessInstanceStatus;
use Saccharine\Bpmn\Workflows\BpmnInterpreterWorkflow;
use Workflow\WorkflowStub;

beforeEach(function () {
    // Scaffold a baseline instance for the command to interact with
    $this->definition = ProcessDefinition::create(['name' => 'CLI Test', 'key' => 'cli-test']);
    $this->version = $this->definition->versions()->create(['version' => 1, 'bpmn_xml' => '<xml/>']);
    
    $workflow = WorkflowStub::make(BpmnInterpreterWorkflow::class);
    
    $this->instance = ProcessInstance::create([
        'process_version_id' => $this->version->id,
        'status' => ProcessInstanceStatus::RUNNING,
        'durable_workflow_id' => $workflow->id(),
    ]);
});

it('suspends a running instance via CLI', function () {
    $this->artisan('bpmn:instance', [
        'action' => 'suspend',
        'id' => $this->instance->id,
    ])->expectsOutputToContain("Successfully suspended Process Instance [{$this->instance->id}].")
      ->assertSuccessful();

    $this->instance->refresh();
    expect($this->instance->status)->toBe(ProcessInstanceStatus::SUSPENDED);
});

it('resumes a suspended instance via CLI', function () {
    // Manually suspend it first
    $this->instance->update(['status' => ProcessInstanceStatus::SUSPENDED]);

    $this->artisan('bpmn:instance', [
        'action' => 'resume',
        'id' => $this->instance->id,
    ])->expectsOutputToContain("Successfully resumed Process Instance [{$this->instance->id}].")
      ->assertSuccessful();

    $this->instance->refresh();
    expect($this->instance->status)->toBe(ProcessInstanceStatus::RUNNING);
});

it('halts an instance via CLI', function () {
    $this->artisan('bpmn:instance', [
        'action' => 'halt',
        'id' => $this->instance->id,
    ])->expectsOutputToContain("Successfully halted Process Instance [{$this->instance->id}].")
      ->assertSuccessful();

    $this->instance->refresh();
    expect($this->instance->status)->toBe(ProcessInstanceStatus::HALTED);
});

it('lists active instances via CLI', function () {
    $this->artisan('bpmn:instance', ['action' => 'list'])
         ->expectsOutputToContain('CLI Test') // Definition Name
         // ->expectsOutputToContain('running')  // Status
         ->assertSuccessful();
});