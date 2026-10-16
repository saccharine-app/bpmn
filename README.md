# **Laravel BPMN Engine**

![bpmn-js screenshot](assets/bpmn-js-screenshot.png)

A lightweight, native PHP workflow orchestrator and visual designer for Laravel. This package allows you to model complex business processes visually using BPMN 2.0 and execute them as durable, stateful background jobs—**without any external Java dependencies** (like Camunda or Zeebe).

It bridges the gap between visual business diagrams and real-world execution by combining **bpmn-js** (for modeling) and **durable-workflow** (for resilient, suspendable background execution).

***Status: v0.4.0-alpha. This package is actively being developed. The core execution engine, token tracking, and manual intervention layers are operational, but it is not yet recommended for production environments.***

## 50-Second Tour:
1. **Model**: Configure dynamic recipient parameters inside the embedded visual designer and publish the blueprint.
2. **Trigger**: An Eloquent `DemoOrder` event fires in the background, matching the start trigger.
3. **Pause**: The workflow routes through an Exclusive Gateway and safely hibernates at a User Task wait state.
4. **Resume & Deliver**: An operator approves the task directly from the Live Cockpit, resuming the coroutine and delivering the notification to Mailpit.

https://github.com/user-attachments/assets/4b4f7225-0dec-4d8d-819e-0c8678654207

## **Key Features**

> * **No Heavy Infrastructure:** Runs entirely on your existing Laravel queue system (database, Redis, SQS, etc.).
> * **Durable Execution:** Workflows are resilient. If a server crashes or restarts mid-process, the engine resumes exactly where it left off.
> * **Embedded Visual Designer:** A beautiful, responsive drag-and-drop designer powered by bpmn-js is built right into your Laravel app.
> * **Event-Driven Architecture:** Natively listen to Laravel Events to trigger workflows automatically using BPMN Message Start Events.
> * **Advanced Routing & Scope:** Full support for Parallel Gateways (AND splits/joins), Inline Sub-Processes, and Call Activities (reusable child processes).
> * **Human-in-the-Loop:** Pause execution indefinitely to wait for external human input (signals), then resume automatically.
> * **State Projection:** Separates background coroutines from UI tracking using isolated relational workflow_tokens, enabling real-time dashboards with zero parallel race conditions.

## **Technical Dependencies**

> * **PHP 8.3+**
> * **Illuminate Support (^12.0 || ^13.0):** Native integration with the Laravel framework ecosystem.
> * **Symfony Expression Language (^7.0):** Used for evaluating sandbox-safe expressions in conditional routing.
> * **Durable Workflow (^1.0):** The robust PHP coroutine framework that provides suspendable workflow engines and queue orchestration.
> * **bpmn-js:** The frontend interactive modeling library, bundled natively via Vite.

## **Documentation**

> * [**Usage Guide**](docs/usage-guide.md): Learn how to design workflows, scaffold activities, use element templates, and implement advanced nodes like Sub-Processes and Boundary Events.
> * [**Architecture & Under the Hood**](docs/architecture.md): Explore the engine's design, including the XML parser, the Strategy Pattern node handlers, and deterministic token tracking.

## **Installation**

### **1. Require the Package**

For local development, link the package to a host application by adding a path repository to your host's composer.json:

```json
"repositories": [
    {
        "type": "path",
        "url": "../bpmn-engine",
        "options": {
            "symlink": true
        }
    }
],
```

Then require the package:

```bash
composer require "saccharine-app/bpmn-engine" "@dev"
```

### **2. Install and Initialize**

The package provides an installation command to publish configurations, frontend assets, migrations, and run the database setup.

```bash
php artisan bpmn:install
```

## **Routing**

The package exposes a **single canonical URL surface**, driven by one config key:

```php
// config/bpmn-engine.php
'prefix' => 'bpmn/workflows',
```

That key drives both presentation layers in step:

| Surface | URL | Route name |
| --- | --- | --- |
| Standalone Blade dashboard | `/bpmn/workflows/definitions` | `bpmn.index` |
| Filament panel table | `/admin/bpmn/workflows/definitions` | `filament.admin.resources.bpmn.workflows.definitions.index` |
| Designer canvas | `/bpmn/workflows/definitions/{id}/design` | `bpmn.design` |
| Instance control | `/bpmn/workflows/instances` | `bpmn.instances.index` |

Filament prepends its own panel path (host-controlled, e.g. `admin`) to the
resource slug, so changing `prefix` relocates both UIs together. The designer and
instance routes are UI-agnostic and shared by both layers — only the list/index
view is duplicated, which keeps maintenance to a minimum.

### **Which UI do I get?**

`bpmn.index` is the canonical entry point and resolves to whichever UI the host
has installed:

1. `config('bpmn-engine.dashboard_url')`, when explicitly set
2. The Filament resource table, when a panel has the BPMN resources registered
   (disable with `bpmn-engine.filament_redirect => false`)
3. The package's own Blade dashboard

Every "back to dashboard" link in the designer uses the same resolution, so
entering the designer from Filament returns you to Filament instead of ejecting
you to the standalone view.

### **Going headless or Filament-only**

- `bpmn-engine.dashboard => false` — stops registering the standalone
  `bpmn.index`/`bpmn.store` routes. The designer, instance control, and
  version-save routes stay live.
- `bpmn-engine.middleware` — defaults to `['web']` for local ergonomics. **In
  production set this to `['web', 'auth']`**, otherwise
  `/bpmn/workflows/definitions` is reachable by any guest. Filament panels are
  always behind their own guard regardless of this value.

  Two things to know when enabling `auth`:

  - `auth` means "must be logged in" — any authenticated user passes. It is
    not the Filament panel guard, and the designer canvas intentionally stays
    on the standalone `/bpmn/workflows/...` path (Filament only prepends
    `/admin` to the resource table).
  - On Filament hosts, Laravel's `auth` middleware redirects guests to a route
    named `login`, but Filament registers `filament.admin.auth.login` instead —
    without one of the following, guests get a `Route [login] not defined`
    500 instead of a redirect. Fix it once in your app:

    ```php
    // bootstrap/app.php
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectGuestsTo('/admin/login');
    })
    ```

    Alternatively, register a route named `login` that forwards to your login
    page.

## **Quickstart: The Interactive Demo**

You can instantly scaffold an "Order Processing" workflow to see the engine in action:

```bash
php artisan bpmn:demo
```

This command generates a demo trigger and activity, registers them in your config, and seeds your database with a complete BPMN diagram. Navigate to /bpmn/workflows/definitions in your browser to view the generated diagram, and follow the terminal instructions to dispatch the event and watch the background worker execute the logic.

## **Acknowledgments**

This package would not be possible without the incredible work of the open-source community:

> * **bpmn-js** by bpmn.io / Camunda: The gold standard for web-based BPMN 2.0 visualization.
> * **durable-workflow**: The elegant coroutine orchestration layer that makes suspendable, durable PHP processes a reality.
> * Symfony & Laravel: For providing the ultimate foundation of modern enterprise PHP development.

## **License**

This package is open-source software licensed under the [MIT License](https://opensource.org/license/mit).
