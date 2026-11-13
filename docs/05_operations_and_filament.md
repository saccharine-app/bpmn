---
extends: docs
section: docs_content
title: Operations, Telemetry & Filament
description: Monitor live tokens in the Cockpit, control running instances via CLI, HTTP, or PHP, and integrate with Filament v5 and Filament Shield.
sectionTitle: Operations & Filament
order: 5
package: bpmn
---

# Operations, Telemetry & Filament Integration

`saccharine/bpmn` provides full Day-2 operational visibility and runtime control over active process instances through its standalone **Live Cockpit**, Artisan CLI commands, REST telemetry endpoints, and native **Filament v5** plugin.

---

## 1. Live Cockpit & Real-Time Telemetry

Navigate to `/bpmn/processes/instances` (or click **Live Cockpit** in Filament's `ProcessInstanceResource` table) to open the execution viewer at `/bpmn/processes/instances/{id}/track`.

### Visual Token Overlays

The cockpit pairs `bpmn-js/lib/NavigatedViewer` with a polling loop (`GET /bpmn/processes/instances/{id}/telemetry`) that updates CSS markers on the SVG diagram in real time:

* **Completed Nodes (`.bpmn-history-completed`):** Highlighted with a solid slate blue outline and fill, derived from `process_history_events`.
* **Active Waiting Nodes (`.bpmn-active-token`):** Highlighted with a vivid emerald green border and a pulsing CSS animation, derived from `process_tokens`.
* **Failed / Halted Nodes (`.bpmn-failed-token`):** Rendered in red when the instance enters `halted` or `failed` status.

### Completing Hibernated User Tasks

When an active green token sits on a `userTask` node, clicking that shape on the canvas opens the **Human Task Approval** modal (`openTaskModal()`). Submitting the modal posts a JSON payload to `POST /bpmn/processes/instances/{id}/tasks/{elementId}/submit`, injecting `decision`, `approved_by`, `comments`, and `submitted_at` into the workflow via `$workflow->submitUpdate('submitUserTask', $payload)` and resuming the background Fiber immediately.

---

## 2. Runtime Process Instance Interventions

Operators and host application code can suspend, resume, or permanently halt running process instances at any time. All runtime interventions invoke `submitUpdate()` on the `durable-workflow` v2 stub (`WorkflowStub::load($instance->durable_workflow_id)`), ensuring state transitions are recorded deterministically in the workflow event log.

### State Transitions Reference

| Action | Update Method | Status Transition | Runtime Behavior |
| :--- | :--- | :--- | :--- |
| **List** | — | — | Lists the 50 most recent instances, versions, statuses, and durable IDs. |
| **Suspend** | `suspendProcess` | `running` $\rightarrow$ `suspended` | Sets `$isSuspended = true`. At the next loop boundary (or immediately if waiting at a `userTask`), the Fiber hibernates via `await()`. |
| **Resume** | `resumeProcess` | `suspended` $\rightarrow$ `running` | Clears `$isSuspended = false`, waking the hibernated Fiber to advance on the next queue worker tick. |
| **Halt** | `haltProcess` | `running` / `suspended` $\rightarrow$ `halted` | Sets `$isHalted = true`, breaks the interpreter loop, sets `_system_status = 'halted'`, and deletes active rows from `process_tokens`. |
| **Submit Task** | `submitUserTask` | `running` (at `userTask`) | Appends form payloads into `$pendingUserTaskPayloads` and wakes `UserTaskHandler`. |

> **Cooperative Multitasking Note:** Because PHP Fibers yield cooperatively at framework boundaries (`activity()`, `await()`, `timer()`, `child()`), an intervention issued while a `ServiceTask` activity is actively executing on a queue worker takes effect as soon as that activity returns control to `BpmnInterpreterWorkflow`.

### Artisan CLI Control (`bpmn:instance`)

```bash
# Display a formatted table of the 50 most recent instances
php artisan bpmn:instance list

# Suspend a running instance
php artisan bpmn:instance suspend {id}

# Resume a suspended instance
php artisan bpmn:instance resume {id}

# Permanently halt an active or suspended instance
php artisan bpmn:instance halt {id}
```

### HTTP Endpoints

Web and telemetry routes are registered under `config('saccharine-bpmn.prefix.web')` (default: `bpmn/processes`):

```http
# Instance lifecycle interventions
POST /bpmn/processes/instances/{id}/suspend
POST /bpmn/processes/instances/{id}/resume
POST /bpmn/processes/instances/{id}/halt

# Real-time polling endpoints
GET  /bpmn/processes/instances/{id}/tokens
GET  /bpmn/processes/instances/{id}/telemetry

# Submit human input for an active User Task
POST /bpmn/processes/instances/{id}/tasks/{elementId}/submit
Content-Type: application/json

{
    "payload": {
        "decision": "approved",
        "approved_by": "Manager",
        "comments": "Verified and approved."
    }
}
```

### Programmatic Control in PHP

```php
use Saccharine\Bpmn\Enums\ProcessInstanceStatus;
use Saccharine\Bpmn\Models\ProcessInstance;
use Workflow\V2\WorkflowStub;

$instance = ProcessInstance::findOrFail($instanceId);
$workflow = WorkflowStub::load($instance->durable_workflow_id);

// Suspend execution
$instance->update(['status' => ProcessInstanceStatus::SUSPENDED]);
$workflow->submitUpdate('suspendProcess');

// Resume execution
$instance->update(['status' => ProcessInstanceStatus::RUNNING]);
$workflow->submitUpdate('resumeProcess');

// Permanently halt execution
$instance->update(['status' => ProcessInstanceStatus::HALTED]);
$workflow->submitUpdate('haltProcess');

// Submit a User Task payload
$workflow->submitUpdate('submitUserTask', [
    'decision'     => 'approved',
    'approved_by'  => auth()->user()?->name ?? 'System',
    'submitted_at' => now()->toIso8601String(),
]);
```

---

## 3. Filament v5 Admin Integration

The package ships with a native **Filament v5** plugin (`BpmnFilamentPlugin`) that registers process management resources directly inside your panel without copying resource stubs into your application.

### Registering the Plugin

Register `BpmnFilamentPlugin::make()` in your panel provider (e.g., `app/Providers/Filament/AdminPanelProvider.php`):

```php
namespace App\Providers\Filament;

use Filament\Panel;
use Filament\PanelProvider;
use Saccharine\Bpmn\Filament\BpmnFilamentPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->plugins([
                BpmnFilamentPlugin::make(),
            ]);
    }
}
```

This adds two resources under the **Process Orchestration** navigation group:
* **`ProcessDefinitionResource` (`/admin/bpmn/processes/definitions`):** Manage process definitions, view version counts, and launch the visual designer in a new tab.
* **`ProcessInstanceResource` (`/admin/bpmn/processes/instances`):** Monitor status badges and active token tags, open the Live Cockpit, and trigger `Suspend`, `Resume`, or `Halt` row actions with confirmation modals.

### Unified Routing & Dual-UI Resolution (`BpmnRouteHelper`)

Both the standalone Blade UI and the Filament resources derive their URLs from the single `prefix.web` key in `config/saccharine-bpmn.php` (default `bpmn/processes`).

`BpmnRouteHelper::dashboardUrl()` resolves the canonical dashboard link in the following priority order:
1. An explicit `saccharine-bpmn.dashboard_url` config override, if set.
2. The auto-detected Filament resource index route (`filament.{panel}.resources.bpmn.processes.definitions.index`), if registered.
3. The standalone Blade dashboard route (`bpmn.index`), unless `saccharine-bpmn.dashboard` is set to `false`.

When `saccharine-bpmn.filament_redirect` is `true` (default), visiting `/bpmn/processes/definitions` automatically redirects to the Filament resource table when present.

### Optional Demo Order Stubs

To scaffold an interactive `DemoOrderResource` CRUD table and sample invoice activity into your host application for testing Eloquent triggers from Filament:

```bash
php artisan bpmn:publish-filament-stubs
```

---

## 4. Authentication & Filament Shield Permissions

### Protecting Routes in Production

By default, `config/saccharine-bpmn.php` sets `'middleware.web' => ['web']` for friction-free local testing, and `BpmnServiceProvider::registerGates()` allows all BPMN gates automatically when `app()->environment('local')`.

Before deploying to staging or production, add `'auth'` to your published `config/saccharine-bpmn.php`:

```php
'middleware' => [
    'web' => ['web', 'auth'],
    'api' => ['api'],
],
```

> **Filament Guest Redirect Tip:** Filament panels register a panel-specific login route (e.g., `filament.admin.auth.login`) rather than Laravel's default `login` route. Add `$middleware->redirectGuestsTo('/admin/login');` inside `withMiddleware()` in `bootstrap/app.php` so unauthenticated guests are redirected cleanly instead of hitting a `Route [login] not defined` exception.

### Granular Permissions (`ProcessPermission`)

`BpmnServiceProvider` automatically registers six custom permissions into `PermissionManifest` and injects them into `filament-shield.custom_permissions`:

| Enum Case | Permission Key | Description |
| :--- | :--- | :--- |
| `ProcessPermission::VIEW` | `Bpmn:View` | View dashboards, designer canvas, and live telemetry. |
| `ProcessPermission::EDIT` | `Bpmn:Edit` | Create definitions, save/publish versions, and submit user tasks. |
| `ProcessPermission::DELETE` | `Bpmn:Delete` | Archive/delete process definitions. |
| `ProcessPermission::SUSPEND_INSTANCE` | `Bpmn:SuspendInstance` | Pause running process instances. |
| `ProcessPermission::RESUME_INSTANCE` | `Bpmn:ResumeInstance` | Resume suspended process instances. |
| `ProcessPermission::HALT_INSTANCE` | `Bpmn:HaltInstance` | Permanently terminate running or suspended instances. |

In addition, standard Eloquent policies (`ProcessDefinitionPolicy`, `ProcessInstancePolicy`, and `DemoOrderPolicy`) are registered for standard resource-level authorization (`ViewAny:ProcessDefinition`, `Update:ProcessDefinition`, etc.).