<?php

namespace Saccharine\Bpmn\Console\Commands;

use Illuminate\Console\Command;
use Saccharine\Bpmn\Models\ProcessInstance;
use Saccharine\Bpmn\Enums\ProcessInstanceStatus;
use Workflow\WorkflowStub;

class InstanceControlCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'bpmn:instance 
                            {action : The intervention action (list, suspend, resume, halt)} 
                            {id? : The relational ID of the process instance}';

    /**
     * The console command description.
     */
    protected $description = 'Control or list the execution state of a running BPMN process instance';

    public function handle()
    {
        $action = strtolower($this->argument('action'));

        // Intercept the 'list' action immediately
        if ($action === 'list') {
            return $this->listInstances();
        }
        
        // For all other actions, the 'id' is strictly required
        $id = $this->argument('id');

        $instance = ProcessInstance::find($id);

        if (!$instance) {
            $this->error("Process instance [{$id}] not found in the database.");
            return;
        }

        $workflow = WorkflowStub::load($instance->durable_workflow_id);

        switch ($action) {
            case 'suspend':
                if ($instance->status !== ProcessInstanceStatus::RUNNING) {
                    $this->warn("Cannot suspend: Instance is currently [{$instance->status->value}].");
                    return;
                }
                
                $instance->update(['status' => ProcessInstanceStatus::SUSPENDED]);
                $workflow->suspendWorkflow(); // Fires the #[SignalMethod] on the stub
                
                $this->info("Successfully suspended Process Instance [{$id}].");
                break;

            case 'resume':
                if ($instance->status !== ProcessInstanceStatus::SUSPENDED) {
                    $this->warn("Cannot resume: Instance is currently [{$instance->status->value}].");
                    return;
                }
                
                $instance->update(['status' => ProcessInstanceStatus::RUNNING]);
                $workflow->resumeWorkflow();
                
                $this->info("Successfully resumed Process Instance [{$id}].");
                break;

            case 'halt':
                if (in_array($instance->status, [ProcessInstanceStatus::COMPLETED, ProcessInstanceStatus::FAILED, ProcessInstanceStatus::HALTED])) {
                    $this->warn("Cannot halt: Instance is already in a terminal state [{$instance->status->value}].");
                    return;
                }

                $instance->update(['status' => ProcessInstanceStatus::HALTED]);
                $workflow->haltWorkflow();
                
                $this->info("Successfully halted Process Instance [{$id}].");
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
        $instances = ProcessInstance::with('version.definition')
            ->orderBy('id', 'desc')
            ->take(50)
            ->get();

        if ($instances->isEmpty()) {
            $this->info("No process instances found.");
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