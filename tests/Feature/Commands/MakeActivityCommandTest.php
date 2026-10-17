<?php

use Illuminate\Support\Facades\File;

beforeEach(function () {
    // Setup a dummy config file in the Testbench app 
    // so the command can find it and update it.
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
    // Clean up generated files so they don't bleed into other tests
    File::deleteDirectory(app_path('Workflows'));
    File::delete($this->configPath);
});

it('generates an activity class and registers it in the config', function () {
    $this->artisan('bpmn:make-activity', ['name' => 'SendWelcomeEmailActivity'])
        ->expectsQuestion('What is the BPMN implementation key for this activity? (e.g., generate_contract)', 'send_welcome_email')
        ->expectsOutputToContain("Successfully registered 'send_welcome_email'")
        ->assertSuccessful();

    // Assert the physical file was created in the correct location
    $activityPath = app_path('Workflows/Activities/SendWelcomeEmailActivity.php');
    expect(File::exists($activityPath))->toBeTrue();

    // Assert the stub populated the class name correctly
    $fileContent = File::get($activityPath);
    expect($fileContent)->toContain('class SendWelcomeEmailActivity extends Activity');

    // Assert the config file was updated with the new key and namespace
    $configContent = File::get($this->configPath);
    expect($configContent)->toContain("'send_welcome_email' => \App\Workflows\Activities\SendWelcomeEmailActivity::class");
});

it('generates the class but warns if the config file is missing', function () {
    // Intentionally remove the config file
    File::delete($this->configPath);

    $this->artisan('bpmn:make-activity', ['name' => 'MissingConfigActivity'])
        ->expectsQuestion('What is the BPMN implementation key for this activity? (e.g., generate_contract)', 'missing_config')
        ->expectsOutputToContain('The config/saccharine-bpmn.php file was not found.')
        ->assertSuccessful();

    // The class should still generate, even if registration fails
    expect(File::exists(app_path('Workflows/Activities/MissingConfigActivity.php')))->toBeTrue();
});