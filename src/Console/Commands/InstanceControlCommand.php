<?php

namespace Saccharine\Bpmn\Console\Commands;

use Illuminate\Console\Command;
use Saccharine\Bpmn\Models\WorkflowInstance;
use Saccharine\Bpmn\Enums\WorkflowInstanceStatus;
use Workflow\WorkflowStub;

class InstanceControlCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'bpmn:instance 
                            {action : The intervention action (list, suspend, resume, halt)} 
                            {id? : The relational ID of the workflow instance}';

    /**
     * The console command description.
     */
    protected $description = 'Control or list the execution state of a running BPMN workflow instance';

    public function handle()
    {
        $action = strtolower($this->argument('action'));

        // Intercept the 'list' action immediately
        if ($action === 'list') {
            return $this->listInstances();
        }
        
        // For all other actions, the 'id' is strictly required
        $id = $this->argument('id');

        $instance = WorkflowInstance::find($id);

        if (!$instance) {
            $this->error("Workflow instance [{$id}] not found in the database.");
            return;
        }

        $workflow = WorkflowStub::load($instance->durable_workflow_id);

        switch ($action) {
            case 'suspend':
                if ($instance->status !== WorkflowInstanceStatus::RUNNING) {
                    $this->warn("Cannot suspend: Instance is currently [{$instance->status->value}].");
                    return;
                }
                
                $instance->update(['status' => WorkflowInstanceStatus::SUSPENDED]);
                $workflow->suspendWorkflow(); // Fires the #[SignalMethod] on the stub
                
                $this->info("Successfully suspended Workflow Instance [{$id}].");
                break;

            case 'resume':
                if ($instance->status !== WorkflowInstanceStatus::SUSPENDED) {
                    $this->warn("Cannot resume: Instance is currently [{$instance->status->value}].");
                    return;
                }
                
                $instance->update(['status' => WorkflowInstanceStatus::RUNNING]);
                $workflow->resumeWorkflow();
                
                $this->info("Successfully resumed Workflow Instance [{$id}].");
                break;

            case 'halt':
                if (in_array($instance->status, [WorkflowInstanceStatus::COMPLETED, WorkflowInstanceStatus::FAILED, WorkflowInstanceStatus::HALTED])) {
                    $this->warn("Cannot halt: Instance is already in a terminal state [{$instance->status->value}].");
                    return;
                }

                $instance->update(['status' => WorkflowInstanceStatus::HALTED]);
                $workflow->haltWorkflow();
                
                $this->info("Successfully halted Workflow Instance [{$id}].");
                break;

            default:
                $this->error("Invalid action '{$action}'. Please use 'suspend', 'resume', or 'halt'.");
                break;
        }
    }

    /**
     * Helper method to render the instances in a CLI table.
     */
    protected function listInstances()
    {
        // Fetch the 50 most recent instances, eager loading the definition mapping
        $instances = WorkflowInstance::with('version.definition')
            ->orderBy('id', 'desc')
            ->take(50)
            ->get();

        if ($instances->isEmpty()) {
            $this->info("No workflow instances found.");
            return;
        }

        $headers = ['ID', 'Definition Name', 'Version', 'Status', 'Durable ID', 'Created At'];
        
        $rows = $instances->map(function ($instance) {
            $definitionName = $instance->version && $instance->version->definition 
                ? $instance->version->definition->name 
                : 'Unknown';
                
            $versionNum = $instance->version ? 'v' . $instance->version->version : 'N/A';
            
            // Format the status string with color tags for CLI readability
            $statusStr = $instance->status->value;
            $statusFormatted = match($statusStr) {
                'running' => "<fg=green>{$statusStr}</>",
                'suspended' => "<fg=yellow>{$statusStr}</>",
                'halted', 'failed' => "<fg=red>{$statusStr}</>",
                'completed' => "<fg=blue>{$statusStr}</>",
                default => $statusStr,
            };

            return [
                $instance->id,
                $definitionName,
                $versionNum,
                $statusFormatted,
                $instance->durable_workflow_id,
                $instance->created_at->format('Y-m-d H:i:s'),
            ];
        });

        $this->table($headers, $rows);
    }
}