<?php

namespace Saccharine\Bpmn\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Saccharine\Bpmn\Models\ProcessInstance;
use Saccharine\Bpmn\Enums\ProcessInstanceStatus;
use Saccharine\Bpmn\Enums\ProcessPermission;
use Workflow\V2\WorkflowStub;

class ProcessInstanceController extends Controller
{
    /**
     * Display a list of all active and historical process instances.
     */
    public function index()
    {
        Gate::authorize(ProcessPermission::VIEW->value);

        // Eager load the version, definition, and active tokens
        $instances = ProcessInstance::with(['version.definition', 'tokens'])
            ->orderBy('id', 'desc')
            ->paginate(20);

        // We will build a 'saccharine-bpmn::instances.index' view to display this table
        return view('saccharine-bpmn::instances.index', compact('instances'));
    }
    
    /**
     * Display a list of all active and historical process instances.
     */
    public function track($id)
    {
        Gate::authorize(ProcessPermission::VIEW->value);

        // Eager load the version, definition, and active tokens
        $instance = ProcessInstance::findOrFail($id);

        // We will build a 'saccharine-bpmn::instances.index' view to display this table
        return view('saccharine-bpmn::instances.track', compact('instance'));
    }

    /**
     * Suspend a running process.
     */
    public function suspend($id)
    {
        Gate::authorize(ProcessPermission::SUSPEND_INSTANCE->value);

        $instance = ProcessInstance::findOrFail($id);

        if ($instance->status !== ProcessInstanceStatus::RUNNING->value) {
            return back()->with('error', 'Only running processes can be suspended.');
        }

        // Update the relational state. The engine loop will read this on its next cycle.
        $instance->update(['status' => ProcessInstanceStatus::SUSPENDED->value]);

        $workflow = WorkflowStub::load($instance->durable_workflow_id);
        // $workflow->suspendWorkflow();
        $workflow->submitUpdate('suspendProcess');

        return back()->with('success', "Process instance [{$id}] suspended.");
    }

    /**
     * Resume a suspended process.
     */
    public function resume($id)
    {
        Gate::authorize(ProcessPermission::RESUME_INSTANCE->value);

        $instance = ProcessInstance::findOrFail($id);

        if ($instance->status !== ProcessInstanceStatus::SUSPENDED->value) {
            return back()->with('error', 'Only suspended processes can be resumed.');
        }

        // Update the database state
        $instance->update(['status' => ProcessInstanceStatus::RUNNING->value]);

        // Load the durable coroutine and fire a signal to wake it up
        $workflow = WorkflowStub::load($instance->durable_workflow_id);
        // $workflow->resumeWorkflow();
        $workflow->submitUpdate('resumeProcess');

        return back()->with('success', "Process instance [{$id}] resumed.");
    }

    /**
     * Permanently halt/cancel a running or suspended process.
     */
    public function halt($id)
    {
        Gate::authorize(ProcessPermission::HALT_INSTANCE->value);

        $instance = ProcessInstance::findOrFail($id);

        if (
            in_array($instance->status,
            [ProcessInstanceStatus::COMPLETED, ProcessInstanceStatus::FAILED, ProcessInstanceStatus::HALTED])
        ) {
            return back()->with('error', 'This process is already in a terminal state.');
        }
        
        $instance->update(['status' => ProcessInstanceStatus::HALTED->value]);

        // Signal the engine to break its execution loop and clean up
        $workflow = WorkflowStub::load($instance->durable_workflow_id);
        // $workflow->haltWorkflow();
        $workflow->submitUpdate('haltProcess');

        return back()->with('success', "Process instance [{$id}] permanently halted.");
    }

    /**
     * Fetch real-time active token locations and execution status for an instance.
     */
    public function tokens($id): JsonResponse
    {
        Gate::authorize(ProcessPermission::VIEW->value);
        
        $instance = ProcessInstance::with('tokens')->findOrFail($id);

        return response()->json([
            'instance_id' => $instance->id,
            'status'      => $instance->status->value,
            'active_node_ids' => $instance->tokens->pluck('bpmn_element_id')->values()->toArray(),
            'updated_at'  => $instance->updated_at?->toIso8601String(),
        ]);
    }

    public function telemetry($id): JsonResponse
    {
        Gate::authorize(ProcessPermission::VIEW->value);

        $instance = ProcessInstance::with(['tokens', 'version.definition'])->findOrFail($id);
        
        $history = DB::table('process_history_events')
            ->where('process_instance_id', $id)
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

    public function submitUserTask(Request $request, $id, $elementId): JsonResponse
    {
        Gate::authorize(ProcessPermission::EDIT->value);

        $instance = ProcessInstance::with('tokens')->findOrFail($id);

        // Verify that the token is actively waiting on this specific node
        $hasToken = $instance->tokens()->where('bpmn_element_id', $elementId)->exists();
        if (!$hasToken) {
            return response()->json([
                'success' => false,
                'message' => "No active token sitting at element [{$elementId}]."
            ], 422);
        }

        $workflow = WorkflowStub::load($instance->durable_workflow_id);

        // The payload can be form data or a simple action trigger
        $payload = $request->input('payload', [
            'approved_by' => auth()->user()?->name ?? 'Admin / Operator',
            'decision'    => 'approved',
            'submitted_at'=> now()->toIso8601String(),
        ]);

        // Fires #[SignalMethod] submitUserTask($payload) on BpmnInterpreterWorkflow
        // $workflow->submitUserTask($payload);
        // Updated Signal approach with durable-workflow V2
        // $workflow->attemptSignalWithArguments('user-task-submitted', $payload);
        // $workflow->attemptSignalWithArguments('submitUserTask', $payload);
        $workflow->submitUpdate('submitUserTask', $payload);

        return response()->json([
            'success' => true,
            'message' => "Task [{$elementId}] submitted. Process will resume on next queue cycle."
        ]);
    }
}