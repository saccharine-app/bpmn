<?php

namespace Saccharine\Bpmn\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class BpmnPublishFilamentStubsCommand extends Command
{
    protected $signature = 'bpmn:publish-filament-stubs';
    protected $description = 'Publishes Filament stubs for the BPMN demo.';

    protected function publishStub(string $stubName, string $targetPath)
    {
        $stubPath = __DIR__ . '/../../../stubs/' . $stubName;
        
        if (!File::exists(dirname($targetPath))) {
            File::makeDirectory(dirname($targetPath), 0755, true);
        }

        File::copy($stubPath, $targetPath);
        $this->line("Created: {$targetPath}");
    }

    protected function registerInConfig(string $arrayType, string $key, string $className)
    {
        $configPath = config_path('saccharine-bpmn.php');
        if (!File::exists($configPath)) return;

        $configContents = File::get($configPath);
        $newLine = "        '{$key}' => \\{$className}::class,";

        if (!str_contains($configContents, $newLine)) {
            $pattern = "/('{$arrayType}'\s*=>\s*\[)/";
            $replacement = "$1\n" . $newLine;
            $newContents = preg_replace($pattern, $replacement, $configContents, 1);
            File::put($configPath, $newContents);
        }
    }

    public function handle()
    {
        $this->info('Publishing BPMN Filament Demo Stubs...');

        // Parallel Demo
        $this->publishStub('demo-activity.stub', app_path('Workflows/Activities/DemoGenerateInvoiceActivity.php'));
        $this->registerInConfig('activities', 'demo_capture_payment', 'App\Workflows\Activities\DemoGenerateInvoiceActivity');
        $this->registerInConfig('activities', 'demo_reserve_stock', 'App\Workflows\Activities\DemoGenerateInvoiceActivity');
        $this->registerInConfig('activities', 'demo_send_confirmation', 'App\Workflows\Activities\DemoGenerateInvoiceActivity');

        // Boundary SLA Demo
        $this->registerInConfig('activities', 'demo_dispatch_pager', 'App\Workflows\Activities\DemoGenerateInvoiceActivity');
        $this->registerInConfig('activities', 'demo_log_system_alert', 'App\Workflows\Activities\DemoGenerateInvoiceActivity');

        // SubProcess & Call Activity Demo
        $this->registerInConfig('activities', 'demo_sanitize_data', 'App\Workflows\Activities\DemoGenerateInvoiceActivity');
        $this->registerInConfig('activities', 'demo_archive_context', 'App\Workflows\Activities\DemoGenerateInvoiceActivity');

        // Publish Demo Order Filament Resources
        $resourcesDir = app_path('Filament/Resources');
        File::ensureDirectoryExists($resourcesDir);

        $this->publishStub('Filament/Resources/DemoOrderResource.php.stub', "{$resourcesDir}/DemoOrderResource.php");
        $this->publishStub('Filament/Resources/DemoOrderResource/Pages/CreateDemoOrder.php.stub', "{$resourcesDir}/Pages/CreateDemoOrder.php");
        $this->publishStub('Filament/Resources/DemoOrderResource/Pages/EditDemoOrder.php.stub', "{$resourcesDir}/Pages/EditDemoOrder.php");
        $this->publishStub('Filament/Resources/DemoOrderResource/Pages/ListDemoOrders.php.stub', "{$resourcesDir}/Pages/ListDemoOrders.php");
        // $this->publishStub('Filament/Resources/DemoOrderResource/Widgets/DemoOrderStatsWidget.php.stub', "{$resourcesDir}/Widgets/DemoOrderStatsWidget.php");

        $this->info('Filament demo stubs published successfully!');
    }
}