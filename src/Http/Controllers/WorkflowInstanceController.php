<?php

namespace Saccharine\BpmnEngine\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Saccharine\BpmnEngine\Models\WorkflowInstance;
use Saccharine\BpmnEngine\Enums\WorkflowInstanceStatus;
use Saccharine\BpmnEngine\Enums\WorkflowPermission;
use Workflow\WorkflowStub;

class WorkflowInstanceController extends Controller
{
    /**
     * Display a list of all active and historical workflow instances.
     */
    public function index()
    {
        Gate::authorize(WorkflowPermission::VIEW->value);

        // Eager load the version, definition, and active tokens
        $instances = WorkflowInstance::with(['version.definition', 'tokens'])
            ->orderBy('id', 'desc')
            ->paginate(20);

        // We will build a 'bpmn-engine::instances.index' view to display this table
        return view('bpmn-engine::instances.index', compact('instances'));
    }
    
    /**
     * Display a list of all active and historical workflow instances.
     */
    public function track($id)
    {
        Gate::authorize(WorkflowPermission::VIEW->value);

        // Eager load the version, definition, and active tokens
        $instance = WorkflowInstance::findOrFail($id);

        // We will build a 'bpmn-engine::instances.index' view to display this table
        return view('bpmn-engine::instances.track', compact('instance'));
    }

    /**
     * Suspend a running workflow.
     */
    public function suspend($id)
    {
        Gate::authorize(WorkflowPermission::SUSPEND_INSTANCE->value);

        $instance = WorkflowInstance::findOrFail($id);

        if ($instance->status !== WorkflowInstanceStatus::RUNNING->value) {
            return back()->with('error', 'Only running workflows can be suspended.');
        }

        // Update the relational state. The engine loop will read this on its next cycle.
        $instance->update(['status' => WorkflowInstanceStatus::SUSPENDED->value]);

        $workflow = WorkflowStub::load($instance->durable_workflow_id);
        $workflow->suspendWorkflow();

        return back()->with('success', "Workflow instance [{$id}] suspended.");
    }

    /**
     * Resume a suspended workflow.
     */
    public function resume($id)
    {
        Gate::authorize(WorkflowPermission::RESUME_INSTANCE->value);

        $instance = WorkflowInstance::findOrFail($id);

        if ($instance->status !== WorkflowInstanceStatus::SUSPENDED->value) {
            return back()->with('error', 'Only suspended workflows can be resumed.');
        }

        // Update the database state
        $instance->update(['status' => WorkflowInstanceStatus::RUNNING->value]);

        // Load the durable coroutine and fire a signal to wake it up
        $workflow = WorkflowStub::load($instance->durable_workflow_id);
        $workflow->resumeWorkflow();

        return back()->with('success', "Workflow instance [{$id}] resumed.");
    }

    /**
     * Permanently halt/cancel a running or suspended workflow.
     */
    public function halt($id)
    {
        Gate::authorize(WorkflowPermission::HALT_INSTANCE->value);

        $instance = WorkflowInstance::findOrFail($id);

        if (
            in_array($instance->status,
            [WorkflowInstanceStatus::COMPLETED, WorkflowInstanceStatus::FAILED, WorkflowInstanceStatus::HALTED])
        ) {
            return back()->with('error', 'This workflow is already in a terminal state.');
        }
        
        $instance->update(['status' => WorkflowInstanceStatus::HALTED->value]);

        // Signal the engine to break its execution loop and clean up
        $workflow = WorkflowStub::load($instance->durable_workflow_id);
        $workflow->haltWorkflow();

        return back()->with('success', "Workflow instance [{$id}] permanently halted.");
    }

    /**
     * Fetch real-time active token locations and execution status for an instance.
     */
    public function tokens($id): JsonResponse
    {
        Gate::authorize(WorkflowPermission::VIEW->value);
        
        $instance = WorkflowInstance::with('tokens')->findOrFail($id);

        return response()->json([
            'instance_id' => $instance->id,
            'status'      => $instance->status->value,
            'active_node_ids' => $instance->tokens->pluck('bpmn_element_id')->values()->toArray(),
            'updated_at'  => $instance->updated_at?->toIso8601String(),
        ]);
    }

    public function telemetry($id): JsonResponse
    {
        Gate::authorize(WorkflowPermission::VIEW->value);

        $instance = WorkflowInstance::with(['tokens', 'version.definition'])->findOrFail($id);
        
        $history = DB::table('workflow_histories')
            ->where('workflow_instance_id', $id)
            ->orderBy('id', 'asc')
            ->get();

        return response()->json([
            'instance_id'     => $instance->id,
            'status'          => $instance->status->value,
            'active_tokens'   => $instance->tokens->pluck('bpmn_element_id')->values()->toArray(),
            'history_trail'   => $history->pluck('bpmn_element_id')->unique()->values()->toArray(),
            'history_log'     => $history,
            'updated_at'      => $instance->updated_at?->toIso8601String(),
        ]);
    }
}