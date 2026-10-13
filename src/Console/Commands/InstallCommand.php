<?php

namespace Saccharine\BpmnEngine\Console\Commands;

use Illuminate\Console\Command;
use Saccharine\BpmnEngine\Enums\WorkflowPermission;

class InstallCommand extends Command
{
    // protected $signature = 'bpmn:install';
    protected $signature = 'bpmn:install {--seed-permissions : Seed permissions for Spatie/Shield}';
    protected $description = 'Install the BPMN Engine, publish assets, and prepare the database';

    public function handle()
    {
        $this->info('Installing BPMN Engine...');

        // Publish the configuration file
        $this->info('Publishing configuration...');
        $this->call('vendor:publish', [
            '--tag' => 'bpmn-engine-config',
            '--force' => true,
        ]);

        // Publish the frontend assets (bpmn-js canvas)
        $this->info('Publishing frontend assets...');
        $this->call('vendor:publish', [
            '--tag' => 'bpmn-engine-assets',
            '--force' => true,
        ]);

        // Publish Durable Workflow Migrations
        $this->info('Publishing Durable Workflow migrations...');
        $this->call('vendor:publish', [
            '--provider' => 'Workflow\Providers\WorkflowServiceProvider',
            '--tag' => 'migrations',
        ]);

        // Offer to run migrations
        if ($this->confirm('Would you like to run the database migrations now?', true)) {
            $this->call('migrate');
        }

        if (class_exists('Spatie\Permission\Models\Permission')) {
            if ($this->option('seed-permissions') || $this->confirm('Seed BPMN permissions into Spatie/Shield?', true)) {
                $this->seedPermissions();

                // Add a helpful reminder for Shield users
                if (config()->has('filament-shield')) {
                    $this->warn('ℹ Filament Shield detected: The Custom Permissions tab has been auto-enabled to display BPMN permissions.');
                }
            }
        }

        $this->info('BPMN Engine installed successfully!');
        $prefix = trim(config('bpmn-engine.prefix', 'bpmn/workflows'), '/');
        $this->line("You can now navigate to /{$prefix}/definitions to start designing.");
    }

    /**
     * Seeds the Spatie permissions directly without relying on external Seeder classes.
     */
    protected function seedPermissions(): void
    {
        $permissionClass = config('permission.models.permission', 'Spatie\Permission\Models\Permission');
        $guardName = config('filament.auth.guard') ?? config('auth.defaults.guard', 'web');

        $count = 0;
        foreach (WorkflowPermission::cases() as $permission) {
            $permissionClass::firstOrCreate([
                'name' => $permission->value,
                'guard_name' => $guardName,
            ]);
            $count++;
        }

        $this->info("Successfully seeded {$count} BPMN custom permissions.");
    }
}