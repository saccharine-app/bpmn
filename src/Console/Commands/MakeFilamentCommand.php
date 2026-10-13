<?php
namespace Saccharine\BpmnEngine\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class MakeFilamentCommand extends Command
{
    protected $signature = 'bpmn:filament {--force : Overwrite Filament resources that already exist}';
    protected $description = 'Publish Filament resources for the BPMN Engine';

    public function handle()
    {
        $this->info('Scaffolding Filament Resources for BPMN Engine...');

        // Ensure the host app has a Filament Resources directory
        $targetDir = app_path('Filament/Resources/Bpmn');
        if (!File::isDirectory($targetDir)) {
            File::makeDirectory($targetDir, 0755, true);
        }

        $resourcesDir = app_path('Filament/Resources/Bpmn');

        // Bail out early (unless --force) when the host has already scaffolded.
        // Silent overwrites are how published copies drift out of sync with the
        // stubs and end up resolving a different URL.
        if (!$this->option('force') && File::exists("{$resourcesDir}/WorkflowDefinitionResource.php")) {
            $this->warn('Filament resources already exist in app/Filament/Resources/Bpmn.');
            $this->warn('Re-run with --force to overwrite them with the current stubs.');

            if (!$this->confirm('Overwrite the existing resources anyway?', false)) {
                $this->info('Aborted. Nothing was written.');
                return self::SUCCESS;
            }
        }

        // Publish the Resources
        $resources = ['WorkflowDefinitionResource', 'WorkflowInstanceResource'];
        foreach ($resources as $resource) {
            File::ensureDirectoryExists($resourcesDir);
            File::copy(
                __DIR__ . "/../../../stubs/filament/{$resource}.stub", 
                "{$resourcesDir}/{$resource}.php"
            );
        }

        // Publish the List Pages
        $pages = [
            'WorkflowDefinitionResource' => 'ListWorkflowDefinitions',
            'WorkflowInstanceResource' => 'ListWorkflowInstances',
        ];

        foreach ($pages as $resourceFolder => $pageClass) {
            $pageDir = "{$resourcesDir}/{$resourceFolder}/Pages";
            File::ensureDirectoryExists($pageDir);
            
            File::copy(
                __DIR__ . "/../../../stubs/filament/{$pageClass}.stub", 
                "{$pageDir}/{$pageClass}.php"
            );
        }

        $this->newLine();
        $this->info('Filament integration complete! You should now see the BPMN resources in your admin panel.');

        $prefix = trim(config('bpmn-engine.prefix', 'bpmn/workflows'), '/');
        $panelPath = trim(config('filament.path', 'admin'), '/');
        $panelId = 'admin';

        $this->line("  Panel table:  /{$panelPath}/{$prefix}/definitions  (route: filament.{$panelId}.resources.{$prefix}.definitions.index)");
        $this->line("  Standalone:   /{$prefix}/definitions                     (route: bpmn.index)");
        $this->newLine();
        $this->comment('Both resolve from config(\'bpmn-engine.prefix\'). The standalone route forwards to');
        $this->comment('the panel table when Filament is present (see bpmn-engine.filament_redirect).');
    }
}