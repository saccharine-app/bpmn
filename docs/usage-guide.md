# Saccharine BPMN Usage Guide

This guide covers configuring the engine, authoring triggers and activities, designing processes, managing human tasks, and integrating with Filament.

---

## 1. Configuration & URL Routing

The published configuration file lives at `config/saccharine-bpmn.php`.

### Granular URL Routing & Middleware

The package provides fine-grained control over web and API routes, allowing you to define custom URL prefixes and middleware stacks:

```php
// config/saccharine-bpmn.php
'prefix' => [
    'web' => 'bpmn/processes', // Default web route prefix
    'api' => 'api/bpmn',       // Default API route prefix
],

'middleware' => [
    'web' => ['web', 'auth'], // Default web middleware stack
    'api' => ['api'],        // Default API middleware stack
],
```

| Surface | Default URL (Web Prefix) | Route Name |
| :--- | :--- | :--- |
| **Standalone Dashboard** | `/bpmn/processes/definitions` | `bpmn.index` |
| **Filament Panel Table** | `/admin/bpmn/processes/definitions` | `filament.admin.resources.bpmn.processes.definitions.index` |
| **Visual Designer** | `/bpmn/processes/definitions/{id}/design` | `bpmn.design` |
| **Instance Cockpit** | `/bpmn/processes/instances/{id}/track` | `bpmn.instances.track` |
| **Instance Registry** | `/bpmn/processes/instances` | `bpmn.instances.index` |

### Standalone vs. Filament Auto-Resolution

The route `bpmn.index` dynamically forwards users to the appropriate interface:
1. `config('saccharine-bpmn.dashboard_url')` if explicitly set.
2. The Filament resource table if registered in an active panel.
3. The built-in Blade dashboard.

To disable the standalone Blade index entirely in a Filament-only app:

```php
'dashboard' => false,
```

### Securing Web Routes

By default, `middleware.web` is set to `['web']` for local evaluation. In production environments, protect these endpoints:

```php
'middleware' => [
    'web' => ['web', 'auth'],
    'api' => ['api'],
],
```

> **Note for Filament Users:** Laravel's default `auth` middleware expects a route named `login`. If your application relies on Filament's authentication routes, configure the unauthenticated redirect in `bootstrap/app.php`:
> ```php
> ->withMiddleware(function (Middleware $middleware) {
>     $middleware->redirectGuestsTo('/admin/login');
> })
> ```

---

## 2. Defining Domain Activities

Activities represent units of background work executed by Service Tasks. In `durable-workflow` v2, activities extend `Workflow\V2\Activity` and implement `handle()`.

### Generating an Activity

```bash
php artisan bpmn:make-activity SendInvoiceActivity
```

This creates `app/Workflows/Activities/SendInvoiceActivity.php`:

```php
namespace App\Workflows\Activities;

use Workflow\V2\Activity;

class SendInvoiceActivity extends Activity
{
    public function handle(array $userData): array
    {
        $orderId = $userData['order_id'];
        
        // Execute business logic (e.g., Stripe, PDF generation, mailers)
        
        return [
            'invoice_id' => 'INV-9901',
            'billed_at'  => now()->toIso8601String(),
        ];
    }
}
```

### Registering Activities

Register the mapping in `config/saccharine-bpmn.php`:

```php
'activities' => [
    'send_invoice' => \App\Workflows\Activities\SendInvoiceActivity::class,
],
```

---

## 3. Triggers & Event-Driven Launching

Processes can be started automatically by listening for domain events or Eloquent model changes.

### Model Lifecycle Triggers

Add the `TriggersBpmnProcesses` trait to any Eloquent model:

```php
use Illuminate\Database\Eloquent\Model;
use Saccharine\Bpmn\Traits\TriggersBpmnProcesses;

class Order extends Model
{
    use TriggersBpmnProcesses;
}
```

When an order is created, `ProcessTriggerListener` catches the event and matches any active process with a Message Start Event configured with the alias `order_created`. Model attributes and lifecycle metadata are injected into the initial payload:

```json
{
  "id": 42,
  "total": 150.00,
  "_model_class": "App\\Models\\Order",
  "_model_id": 42,
  "_action": "created"
}
```

### Custom Event Triggers

Generate custom trigger events:

```bash
php artisan bpmn:make-trigger CustomerOnboardedTrigger
```

This generates an event implementing `BpmnTriggerableEvent`:

```php
namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Saccharine\Bpmn\Contracts\BpmnTriggerableEvent;

class CustomerOnboardedTrigger implements BpmnTriggerableEvent
{
    use Dispatchable;

    public function __construct(public int $customerId) {}

    public function getBusinessKey(): string
    {
        return "customer_onboarded_{$this->customerId}";
    }

    public function getProcessPayload(): array
    {
        return ['customer_id' => $this->customerId];
    }
}
```

Register the trigger alias in `config/saccharine-bpmn.php`:

```php
'triggers' => [
    'customer_onboarded' => \App\Events\CustomerOnboardedTrigger::class,
],
```

---

## 4. Visual Designer & Element Templates

Access the designer by opening `/bpmn/processes/definitions/{id}/design`.

### Element Templates

Element templates allow developers to expose pre-configured Service Tasks with user-friendly form fields directly in the `bpmn-js` properties panel.

Generate a template:

```bash
php artisan bpmn:make-template "Charge Credit Card" --key="charge_card"
```

This creates a JSON template in `resources/bpmn/templates/`:

```json
{
  "$schema": "https://unpkg.com/@bpmn-io/element-templates@0.4.0/resources/schema.json",
  "name": "Charge Credit Card",
  "id": "com.app.tasks.ChargeCreditCard",
  "appliesTo": ["bpmn:ServiceTask"],
  "properties": [
    {
      "label": "Implementation Key",
      "type": "Hidden",
      "value": "charge_card",
      "binding": { "type": "property", "name": "camunda:class" }
    },
    {
      "label": "Charge Description",
      "type": "String",
      "value": "Order Payment",
      "binding": { "type": "camunda:inputParameter", "name": "charge_description" }
    }
  ]
}
```

---

## 5. Advanced BPMN Node Patterns

### Exclusive Gateways (XOR)

Define conditional branches using Symfony Expression Language inside sequence flow condition expressions:
* `total >= 1000`
* `status == "approved" and role in ["admin", "manager"]`

Leave one outgoing edge empty without an expression to designate it as the default/fallback route.

### Parallel Gateways (AND Split/Join)

* **Split:** Outgoing sequence flows from a Parallel Gateway spawn independent child coroutines executed concurrently on the queue using closures passed to `Workflow\V2\all()`.
* **Join:** Converging sequence flows wait at the synchronization barrier until all concurrent paths complete. Mutated payloads are merged back into the main process state.

### Error Boundary Events

Attach an Error Boundary Event to a Service Task, Call Activity, or Sub-Process. If an activity throws an unhandled exception:
1. `EvaluatesBoundaryEvents` catches the exception.
2. The engine checks for an attached error boundary event.
3. It bypasses queue retry policies, injects `_error_caught` into the payload, and navigates down the boundary sequence flow.

### User Tasks & Human Approvals

When a process reaches a User Task, execution suspends indefinitely without consuming queue workers:
* The engine emits a `UserTaskPending` event.
* Operators can click the pulsing green node in the **Live Cockpit** (`/instances/{id}/track`) to open an approval modal and submit decisions.

---

## 6. Runtime Process Instance Interventions

Active process instances can be monitored and controlled through the CLI, HTTP endpoints, the standalone tracking cockpit, or Filament resource actions.

All interventions use `submitUpdate()` on the durable workflow stub to mutate the workflow state safely across execution replays.

### Available Actions

| Action | State Transition | Description |
| :--- | :--- | :--- |
| **List** | — | Displays recent instances, statuses, and durable IDs. |
| **Suspend** | `RUNNING` $\rightarrow$ `SUSPENDED` | Pauses the workflow at its current execution boundary. The coroutine safely hibernates until resumed. |
| **Resume** | `SUSPENDED` $\rightarrow$ `RUNNING` | Unpauses the workflow, allowing it to advance on the next queue worker tick. |
| **Halt** | `RUNNING` / `SUSPENDED` $\rightarrow$ `HALTED` | Permanently terminates execution, cleans up active tokens, and prevents further progression. |
| **Submit Task** | `RUNNING` (at User Task) | Injects form payloads into a suspended User Task and resumes execution. |

---

### Command-Line Interface (CLI)

The `bpmn:instance` Artisan command provides full terminal control:

```bash
# List the 50 most recent instances
php artisan bpmn:instance list

# Suspend a running instance
php artisan bpmn:instance suspend {id}

# Resume a suspended instance
php artisan bpmn:instance resume {id}

# Permanently halt an instance
php artisan bpmn:instance halt {id}
```

---

### Web & API Endpoints

Interventions are accessible over HTTP (subject to configured route prefixes and `ProcessPermission` gates):

```http
# Suspend an instance
POST /bpmn/processes/instances/{id}/suspend

# Resume a suspended instance
POST /bpmn/processes/instances/{id}/resume

# Permanently halt an instance
POST /bpmn/processes/instances/{id}/halt

# Fetch active tokens (polled by UI)
GET /bpmn/processes/instances/{id}/tokens

# Fetch full telemetry & audit history
GET /bpmn/processes/instances/{id}/telemetry

# Submit human input for an active User Task
POST /bpmn/processes/instances/{id}/tasks/{elementId}/submit
Content-Type: application/json

{
    "payload": {
        "decision": "approved",
        "notes": "Reviewed and verified."
    }
}
```

---

### Interactive Cockpit & Filament Controls

* **Live Cockpit (`/instances/{id}/track`):** Top header buttons allow instant `Suspend`, `Resume`, and `Halt` actions. Clicking on an active, pulsing green User Task node opens an approval modal directly on the canvas to submit payloads in real time.
* **Filament Resources:** If using `ProcessInstanceResource`, table rows include individual action buttons for `Suspend`, `Resume`, `Halt`, and a `Live Cockpit` link with integrated confirmation modals.

---

## 7. Filament Integration

Scaffold the optional Filament resources:

```bash
php artisan bpmn:publish-filament-stubs
```

This publishes:
* `ProcessDefinitionResource`: Manage definitions and open the visual designer in a new tab.
* `ProcessInstanceResource`: Monitor live token badges and issue operator interventions (Suspend, Resume, Halt).
* `DemoOrderResource`: Interactive CRUD for testing model event triggers.