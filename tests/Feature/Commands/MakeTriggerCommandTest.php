<?php

use Illuminate\Support\Facades\File;

beforeEach(function () {
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
    File::deleteDirectory(app_path('Events'));
    File::delete($this->configPath);
});

it('generates a trigger event class and registers it in the config', function () {
    $this->artisan('bpmn:make-trigger', ['name' => 'OrderPlacedEvent'])
        ->expectsQuestion('What is the BPMN alias for this trigger? (e.g., custody_log_created)', 'order_placed')
        ->expectsOutputToContain("Successfully registered 'order_placed'")
        ->assertSuccessful();

    $triggerPath = app_path('Events/OrderPlacedEvent.php');
    expect(File::exists($triggerPath))->toBeTrue();

    $fileContent = File::get($triggerPath);
    expect($fileContent)->toContain('class OrderPlacedEvent implements BpmnTriggerableEvent');

    $configContent = File::get($this->configPath);
    expect($configContent)->toContain("'order_placed' => \App\Events\OrderPlacedEvent::class");
});