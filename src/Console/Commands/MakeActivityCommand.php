<?php

namespace Saccharine\Bpmn\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Facades\File;

class MakeActivityCommand extends GeneratorCommand
{
    /**
     * The console command signature.
     */
    protected $name = 'bpmn:make-activity';

    /**
     * The console command description.
     */
    protected $description = 'Create a new BPMN durable workflow activity class';

    /**
     * The type of class being generated.
     */
    protected $type = 'Activity';

    /**
     * Get the stub file for the generator.
     */
    protected function getStub(): string
    {
        // Resolve the path to the stub we just created
        return __DIR__ . '/../../../stubs/bpmn-activity.stub';
    }

    /**
     * Get the default namespace for the class.
     * 
     * This automatically places generated files into app/Workflows/Activities
     * in the host application.
     */
    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace . '\Workflows\Activities';
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Execute the standard Laravel class generation
        if (parent::handle() === false && ! $this->option('force')) {
            return false;
        }

        // Prompt the user for the implementation key
        $key = $this->ask('What is the BPMN implementation key for this activity? (e.g., generate_contract)');

        if ($key) {
            $this->registerActivityInConfig($key);
        }
    }

    /**
     * Append the new activity directly into the published host configuration.
     */
    protected function registerActivityInConfig(string $key)
    {
        $configPath = config_path('saccharine-bpmn.php');

        // Ensure the host application has actually published the config file
        if (!File::exists($configPath)) {
            $this->warn("The config/saccharine-bpmn.php file was not found. Please publish it first to auto-register activities.");
            return;
        }

        // Get the fully qualified class name (e.g., App\Workflows\Activities\GenerateContractActivity)
        $className = $this->qualifyClass($this->getNameInput());
        $configContents = File::get($configPath);

        // Format the new array entry
        $newLine = "        '{$key}' => \\{$className}::class,";

        // Prevent duplicate entries if the command is run multiple times
        if (str_contains($configContents, "\\{$className}::class") || str_contains($configContents, "'{$key}'")) {
            $this->warn("This activity or key is already registered in your config file.");
            return;
        }

        // Inject the new line right after the 'activities' => [ declaration
        $pattern = "/('activities'\s*=>\s*\[)/";
        $replacement = "$1\n" . $newLine;
        
        $newContents = preg_replace($pattern, $replacement, $configContents, 1);

        File::put($configPath, $newContents);

        $this->info("Successfully registered '{$key}' to {$className} in config/saccharine-bpmn.php!");
    }
}