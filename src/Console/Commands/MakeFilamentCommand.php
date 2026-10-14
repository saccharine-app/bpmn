<?php
namespace Saccharine\BpmnEngine\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class MakeFilamentCommand extends Command
{
    protected $signature = 'bpmn:filament 
                            {--force : Overwrite Filament resources that already exist}
                            {--demo : Also scaffold the DemoOrder resource for testing}';

    protected $description = 'Publish Filament resources for the BPMN Engine';

    public function handle()
    {
        $this->info('Scaffolding Filament Resources for BPMN Engine...');

        $resourcesDir = app_path('Filament/Resources/Bpmn');
        File::ensureDirectoryExists($resourcesDir);

        $resources = ['WorkflowDefinitionResource', 'WorkflowInstanceResource'];

        // Determine if DemoOrderResource should be published
        $publishDemo = $this->option('demo');
        if (! $publishDemo && ! $this->option('force')) {
            $publishDemo = $this->confirm('Would you also like to scaffold the Demo Order resource for interactive testing?', true);
        }

        if ($publishDemo) {
            $resources[] = 'DemoOrderResource';
        }

        // Check if files exist to guard against unintentional overwriting
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

        // Publish Resources
        foreach ($resources as $resource) {
            File::copy(
                __DIR__ . "/../../../stubs/filament/{$resource}.stub",
                "{$resourcesDir}/{$resource}.php"
            );
        }

        // Publish List Pages
        $pages = [
            'WorkflowDefinitionResource' => 'ListWorkflowDefinitions',
            'WorkflowInstanceResource'   => 'ListWorkflowInstances',
        ];

        if ($publishDemo) {
            $pages['DemoOrderResource'] = 'ListDemoOrders';
        }

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

        $this->line("  Definitions:  /{$panelPath}/{$prefix}/definitions");
        $this->line("  Instances:    /{$panelPath}/{$prefix}/instances");
        if ($publishDemo) {
            $this->line("  Demo Orders:  /{$panelPath}/{$prefix}/demo-orders");
        }
        $this->newLine();

        return self::SUCCESS;
    }
}