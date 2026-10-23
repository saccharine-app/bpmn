<?php

namespace Saccharine\Bpmn\Console\Commands;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'bpmn:install';
    protected $description = 'Install Saccharine BPMN and publish assets';

    public function handle()
    {
        $this->info('Installing Saccharine BPMN...');

        // Publish the configuration file
        $this->info('Publishing configuration...');
        $this->call('vendor:publish', [
            '--tag' => 'saccharine-bpmn-config',
            '--force' => true,
        ]);

        // Publish the frontend assets (bpmn-js canvas)
        $this->info('Publishing frontend assets...');
        $this->call('vendor:publish', [
            '--tag' => 'saccharine-bpmn-assets',
            '--force' => true,
        ]);

        $this->info('Saccharine BPMN installed successfully!');
        $this->warn('Next, run `php artisan migrate` to apply the package database migrations.');
        $prefix = trim(config('saccharine-bpmn.prefix', 'bpmn/workflows'), '/');
        $this->line("You can now navigate to /{$prefix}/definitions to start designing.");
    }
}