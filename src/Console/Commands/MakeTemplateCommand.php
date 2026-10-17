<?php

namespace Saccharine\Bpmn\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeTemplateCommand extends Command
{
    protected $signature = 'bpmn:make-template {name : The human-readable name of the template (e.g., "Send Welcome Email")} 
                                              {--key= : The underlying activity implementation key}';

    protected $description = 'Create a new BPMN Element Template JSON file for the visual designer';

    public function handle()
    {
        $name = $this->argument('name');
        
        // Ask for the implementation key if not provided via options
        $activityKey = $this->option('key') ?? $this->ask('What is the Activity implementation key? (e.g., send_welcome_email)');
        
        $id = Str::studly($name);
        $fileName = Str::kebab($name) . '.json';
        
        // Dynamically generate a safe reverse-domain app ID
        $safeAppName = Str::slug(config('app.name', 'laravel'));
        $appId = "com.{$safeAppName}.tasks";

        $directory = resource_path('bpmn/templates');
        $path = $directory . '/' . $fileName;

        if (!File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        if (File::exists($path)) {
            $this->error("Template {$fileName} already exists!");
            return;
        }

        $stub = File::get(__DIR__ . '/../../../stubs/bpmn-template.stub');

        $content = str_replace(
            ['{{ name }}', '{{ id }}', '{{ activityKey }}', '{{ appId }}'],
            [$name, $id, $activityKey, $appId],
            $stub
        );

        File::put($path, $content);

        $this->info("BPMN Element Template generated successfully at resources/bpmn/templates/{$fileName}");
    }
}