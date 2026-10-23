<?php

namespace Saccharine\Bpmn;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Saccharine\Bpmn\Events\BpmnExecutionLogged;
use Saccharine\Bpmn\Listeners\WorkflowTriggerListener;
use Saccharine\Bpmn\Listeners\BpmnConsoleLogListener;
use Saccharine\Bpmn\Enums\WorkflowPermission;
use Saccharine\Bpmn\Support\PermissionManifest;

use Saccharine\Bpmn\Models\WorkflowDefinition;
use Saccharine\Bpmn\Models\WorkflowInstance;
use Saccharine\Bpmn\Policies\WorkflowDefinitionPolicy;
use Saccharine\Bpmn\Policies\WorkflowInstancePolicy;
use Saccharine\Bpmn\Filament\BpmnFilamentPlugin;

class BpmnServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Load the database migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Load migrations from the durable-workflow package. v1 does not call
        // loadMigrationsFrom() itself, so we register its migration directory here.
        // This avoids publishing migrations to the host app. When upgrading to
        // durable-workflow v2 (which loads its own migrations), this line can be removed.
        $this->loadMigrationsFrom(__DIR__ . '/../vendor/durable-workflow/workflow/src/migrations');

        // Tell the host app where to find Blade templates
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'saccharine-bpmn');

        // Load the package's internal API routes
        Route::prefix(config('saccharine-bpmn.api_prefix', 'api/bpmn'))
            ->middleware(config('saccharine-bpmn.api_middleware', ['api']))
            ->group(__DIR__ . '/../routes/api.php');

        // Tell the host app where to find web routes.
        // `prefix` is the canonical path segment for the whole package: it drives
        // these standalone routes AND the Filament resource slug (see the stubs).
        $webPrefix = trim(config('saccharine-bpmn.prefix', 'bpmn/workflows'), '/');
        $webMiddleware = config('saccharine-bpmn.middleware', ['web']);

        Route::prefix($webPrefix)
            ->middleware($webMiddleware)
            ->group(__DIR__ . '/../routes/web.php');

        // The standalone Blade dashboard is optional. A Filament-only host can set
        // `saccharine-bpmn.dashboard` to false and rely on the panel resource instead;
        // the designer and instance routes registered above remain available so the
        // engine still works headlessly.

        if (config('saccharine-bpmn.dashboard', true)) {
            Route::prefix($webPrefix)
                ->middleware($webMiddleware)
                ->group(__DIR__ . '/../routes/dashboard.php');
        }

        // Listen to all events to check for start event triggers
        Event::listen('*', [WorkflowTriggerListener::class, 'handle']);
        // Listen to BpmnExecutionLogged events to write log output for each BPMN step
        Event::listen(BpmnExecutionLogged::class, BpmnConsoleLogListener::class);

        if ($this->app->runningInConsole()) {
            // Register custom artisan commands
            $this->commands([
                \Saccharine\Bpmn\Console\Commands\MakeActivityCommand::class,
                \Saccharine\Bpmn\Console\Commands\MakeTriggerCommand::class,
                \Saccharine\Bpmn\Console\Commands\MakeTemplateCommand::class,
                \Saccharine\Bpmn\Console\Commands\InstallCommand::class,
                \Saccharine\Bpmn\Console\Commands\BpmnPublishFilamentStubsCommand::class,
                \Saccharine\Bpmn\Console\Commands\InstanceControlCommand::class,
                \Saccharine\Bpmn\Console\Commands\TestOrderCommand::class,
            ]);

            // Allow the host app to publish the config file
            $this->publishes([
                __DIR__ . '/../config/saccharine-bpmn.php' => config_path('saccharine-bpmn.php'),
            ], 'saccharine-bpmn-config');
            
            // Allow host applications to customize/override the view layout if needed
            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/saccharine/bpmn'),
            ], 'saccharine-bpmn-views');
            
            // Publish compiled frontend assets
            $this->publishes([
                __DIR__ . '/../public' => public_path('vendor/saccharine/bpmn'),
            ], 'saccharine-bpmn-assets');
        }

        $this->registerGates();

        // Map the Enum cases into an associative array: ['bpmn:view' => 'View Workflows']
        $permissionMap = [];
        foreach (\Saccharine\Bpmn\Enums\WorkflowPermission::cases() as $permission) {
            $permissionMap[$permission->value] = $permission->label();
        }

        PermissionManifest::register($permissionMap);
        
        // Inject directly into the Shield config.
        config([
            'filament-shield.shield_resource.tabs.custom_permissions' => true,
            'filament-shield.custom_permissions' => array_merge(
                config('filament-shield.custom_permissions', []),
                PermissionManifest::all()
            )
        ]);
        
        Gate::policy(WorkflowDefinition::class, WorkflowDefinitionPolicy::class);
        Gate::policy(WorkflowInstance::class, WorkflowInstancePolicy::class);
    }

    public function register()
    {
        // Merge the host app's published config with our package defaults
        $this->mergeConfigFrom(
            __DIR__ . '/../config/saccharine-bpmn.php', 'saccharine-bpmn'
        );

        // Register the internal DemoOrder alias fallback
        config([
            'saccharine-bpmn.triggers.demo_order_created' => \Saccharine\Bpmn\Models\DemoOrder::class . '@created',
            'saccharine-bpmn.activities.bpmn_send_notification' => \Saccharine\Bpmn\Workflows\Activities\SendNotificationActivity::class,
        ]);
    }

    /**
     * Register the default authorization gates.
     */
    protected function registerGates()
    {
        // View the dashboards and editor
        Gate::define(WorkflowPermission::VIEW->value, fn ($user = null) => app()->environment('local'));
        
        // Create workflows and save diagram changes
        Gate::define(WorkflowPermission::EDIT->value, fn ($user = null) => app()->environment('local'));
        
        // Delete workflows and their versions
        Gate::define(WorkflowPermission::DELETE->value, fn ($user = null) => app()->environment('local'));
        
        // Control running workflow instances
        Gate::define(WorkflowPermission::SUSPEND_INSTANCE->value, fn ($user = null) => app()->environment('local'));
        Gate::define(WorkflowPermission::RESUME_INSTANCE->value, fn ($user = null) => app()->environment('local'));
        Gate::define(WorkflowPermission::HALT_INSTANCE->value, fn ($user = null) => app()->environment('local'));
    }
}
