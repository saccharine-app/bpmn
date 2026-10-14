<?php

namespace Saccharine\BpmnEngine\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TestCase extends Orchestra
{
    use WithWorkbench;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // This ensures Laravel's core schema migrations (like users) are run if needed
        $this->loadLaravelMigrations();
    }

    /**
     * Define environment setup.
     *
     * Workbench reads package providers from `testbench.yaml`, so they are no
     * longer registered here. This hook only tunes runtime config for tests.
     */
    protected function getEnvironmentSetUp($app)
    {
        // Provide a standard 32-character base64 string for Laravel's encrypter
        $app['config']->set('app.key', 'base64:Hupx3yAySikrM2/edkZQNQHslgDWYvnkFmTNFjq+95U=');

        // Ensure this uses the database queue
        $app['config']->set('queue.default', 'database');

        // Force all database connections to use memory
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        // Disable the failed_jobs log provider completely for testing context
        $app['config']->set('queue.failed', [
            'driver' => 'null',
        ]);
    }

    /**
     * Load the third-party Durable Workflow migrations that the engine depends
     * on. The package's own migrations are already loaded by the service
     * provider, and Workbench additionally loads `workbench/database/migrations`.
     */
    protected function defineDatabaseMigrations()
    {
        $this->loadMigrationsFrom(__DIR__ . '/../vendor/durable-workflow/workflow/src/migrations');
    }
}