<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Activity Mappings
    |--------------------------------------------------------------------------
    |
    | This array maps the string identifier found in a BPMN <serviceTask> 
    | (the 'implementation' attribute) to the fully qualified class name 
    | of the Activity in your host application.
    |
    */
    'activities' => [
        // Example:
        // 'calculate_tax' => \App\Workflows\Activities\CalculateTaxActivity::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Trigger Mappings
    |--------------------------------------------------------------------------
    |
    | Map the string identifier found in a BPMN <startEvent> 
    | to the fully qualified class name of the Laravel Event that triggers it.
    |
    */
    'triggers' => [
        // 'custody_log_created' => \App\Events\CustodyLogCreated::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Canonical Dashboard URL
    |--------------------------------------------------------------------------
    |
    | Every "back to dashboard" link in the designer resolves through this key
    | first. Leave it null to auto-detect the Filament resource when present,
    | falling back to the standalone Blade dashboard.
    |
    */
    'dashboard_url' => null,

    /*
    |--------------------------------------------------------------------------
    | Filament Auto-Redirect
    |--------------------------------------------------------------------------
    |
    | When true, `bpmn.index` forwards to the Filament resource table if a panel
    | has the BPMN resources registered. This keeps a single canonical entry
    | point that resolves to whichever UI the host has installed.
    |
    */
    'filament_redirect' => true,
    
    /*
    |--------------------------------------------------------------------------
    | Routing
    |--------------------------------------------------------------------------
    |
    | `prefix` is the single source of truth for the package's URL surface. It
    | drives BOTH:
    |
    |   * the standalone web routes  -> /bpmn/workflows/definitions
    |   * the Filament resource slug -> /admin/bpmn/workflows/definitions
    |
    | Filament prepends its own panel path (host-controlled, e.g. `admin`) to
    | the resource slug, so this single key relocates both UIs in step.
    |
    */
    'prefix' => [
        'web' => 'bpmn/workflows',
        // Reserved for future headless/external token-based workers
        'api' => 'api/bpmn',
    ],

    /*
    |--------------------------------------------------------------------------
    | Package Routing Middleware
    |--------------------------------------------------------------------------
    |
    | These middleware will be assigned to every BPMN route. You can
    | add your own middleware here to protect the designer and dashboard
    | behind a login, such as the 'auth' middleware.
    |
    | Filament hosts: the `auth` middleware redirects guests to a route named
    | `login`, but Filament registers `filament.admin.auth.login` instead — add
    | `$middleware->redirectGuestsTo('/admin/login');` to the withMiddleware()
    | callback in bootstrap/app.php, otherwise guests receive a
    | "Route [login] not defined" 500 instead of a login redirect.
    |
    */
    'middleware' => [
        // Standard session auth protects web UI and its JSON telemetry endpoints
        'web' => ['web'],
        'api' => ['api'],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Standalone Blade Dashboard
    |--------------------------------------------------------------------------
    |
    | The package ships a lightweight Blade dashboard for hosts that do not use
    | Filament. Set this to `false` in a Filament-only app to stop registering
    | the index/store routes; the designer, instance tracking, and version-save
    | routes stay live so the engine remains usable headlessly.
    |
    */
    'dashboard' => true,
];