<?php

namespace Saccharine\Bpmn\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Facades\File;

class MakeTriggerCommand extends GeneratorCommand
{
    protected $name = 'bpmn:make-trigger';
    protected $description = 'Create a new BPMN triggerable event class and register it';
    protected $type = 'Event';

    protected function getStub(): string
    {
        return __DIR__ . '/../../../stubs/bpmn-trigger.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        // Places it directly into the host app's Events directory
        return $rootNamespace . '\Events';
    }

    public function handle()
    {
        if (parent::handle() === false && ! $this->option('force')) {
            return false;
        }

        $key = $this->ask('What is the BPMN alias for this trigger? (e.g., custody_log_created)');

        if ($key) {
            $this->registerTriggerInConfig($key);
        }
    }

    protected function registerTriggerInConfig(string $key)
    {
        $configPath = config_path('saccharine-bpmn.php');

        if (!File::exists($configPath)) {
            $this->warn("The config/saccharine-bpmn.php file was not found.");
            return;
        }

        $className = $this->qualifyClass($this->getNameInput());
        $configContents = File::get($configPath);

        $newLine = "        '{$key}' => \\{$className}::class,";

        if (str_contains($configContents, "\\{$className}::class") || str_contains($configContents, "'{$key}'")) {
            $this->warn("This trigger or alias is already registered in your config file.");
            return;
        }

        // Target the 'triggers' array instead of 'activities'
        $pattern = "/('triggers'\s*=>\s*\[)/";
        $replacement = "$1\n" . $newLine;
        
        $newContents = preg_replace($pattern, $replacement, $configContents, 1);
        File::put($configPath, $newContents);

        $this->info("Successfully registered '{$key}' to {$className} in config/saccharine-bpmn.php!");
    }
}