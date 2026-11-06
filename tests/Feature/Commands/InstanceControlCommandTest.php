<?php

use Saccharine\Bpmn\Models\ProcessDefinition;
use Saccharine\Bpmn\Models\ProcessInstance;
use Saccharine\Bpmn\Enums\ProcessInstanceStatus;
use Saccharine\Bpmn\Workflows\BpmnInterpreterWorkflow;
use Workflow\V2\WorkflowStub;

beforeEach(function () {
    // Scaffold a baseline instance for the command to interact with
    $this->definition = ProcessDefinition::create(['name' => 'CLI Test', 'key' => 'cli-test']);
    $this->version = $this->definition->versions()->create(['version' => 1, 'bpmn_xml' => '<xml/>']);

    // Create nodes so the workflow has a place to wait
    /* $this->version->nodes()->createMany([
        ['bpmn_element_id' => 'Start_1', 'type' => 'startEvent'],
        ['bpmn_element_id' => 'Task_Wait', 'type' => 'userTask'],
        ['bpmn_element_id' => 'End_1', 'type' => 'endEvent'],
    ]);
    $this->version->edges()->createMany([
        ['bpmn_element_id' => 'Flow_1', 'source_node_id' => 'Start_1', 'target_node_id' => 'Task_Wait'],
        ['bpmn_element_id' => 'Flow_2', 'source_node_id' => 'Task_Wait', 'target_node_id' => 'End_1'],
    ]); */
    
    $workflow = WorkflowStub::make(BpmnInterpreterWorkflow::class);
    
    $this->instance = ProcessInstance::create([
        'process_version_id' => $this->version->id,
        'status' => ProcessInstanceStatus::RUNNING,
        'durable_workflow_id' => $workflow->id(),
    ]);

    // START the workflow so an active instance exists in the engine!
    $workflow->start($this->version->id, ['test' => 'data'], null, $this->instance->id);

    // Let the worker step to the User Task where it pauses
    // \Illuminate\Support\Facades\Artisan::call('queue:work', ['--stop-when-empty' => true]);
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