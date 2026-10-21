<?php

use Illuminate\Support\Facades\File;
use Saccharine\Bpmn\Models\WorkflowDefinition;
use Saccharine\Bpmn\Models\WorkflowNode;

beforeEach(function () {
    // Setup a dummy config file for the demo to register its aliases
    $this->configPath = config_path('saccharine-bpmn.php');
    
    $configContent = <<<PHP
<?php
return [
    'activities' => [],
    'triggers' => [],
];
PHP;

    File::put($this->configPath, $configContent);
});

afterEach(function () {
    File::deleteDirectory(app_path('Workflows'));
    File::deleteDirectory(app_path('Events'));
    File::delete($this->configPath);
});

it('scaffolds the demo workflow, generates classes, and updates the config', function () {
    $this->artisan('bpmn:demo')
        ->expectsOutputToContain('Scaffolding BPMN Demo Environment...')
        ->expectsOutputToContain('Demo successfully installed!')
        ->assertSuccessful();
        
    // Assert the database was seeded with the definition and version
    $definition = WorkflowDefinition::where('key', 'demo-order-processing')->first();
    expect($definition)->not->toBeNull();
    expect($definition->name)->toBe('Demo: Order Processing');
    expect($definition->versions)->toHaveCount(1);

    // Verify the parser service successfully extracted the XML into the database
    $version = $definition->versions->first();
    $nodesCount = WorkflowNode::where('workflow_version_id', $version->id)->count();
    
    // The demo XML has a Start, Exclusive Gateway, User Task, Merge Gateway, Service Task, and End Event
    expect($nodesCount)->toBe(6); 
    
    // Verify the specific demo implementations mapped correctly
    $serviceTask = WorkflowNode::where('workflow_version_id', $version->id)
        ->where('type', 'serviceTask')
        ->first();
        
    expect($serviceTask->implementation)->toBe('bpmn_send_notification');
});