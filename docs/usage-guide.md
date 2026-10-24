# **Saccharine BPMN Usage Guide**

This guide covers configuring the engine, authoring triggers and activities, designing workflows, managing human tasks, and integrating with Filament.

---

## 1. Configuration & URL Routing

The published configuration file lives at `config/saccharine-bpmn.php`.

### Granular URL Routing & Middleware

The package provides fine-grained control over web and API routes through the `config/saccharine-bpmn.php` file, allowing you to define separate prefixes and middleware stacks.

```php
// config/saccharine-bpmn.php
'prefix' => [
    'web' => 'bpmn/workflows', // Default web route prefix
    'api' => 'api/bpmn',       // Default API route prefix (optional)
],

'middleware' => [
    'web' => ['web', 'auth'], // Default web middleware stack
    'api' => ['api'],        // Default API middleware stack
],
```

The `prefix.web` configuration controls the standalone Blade dashboard and associated web routes. The `prefix.api` routes are optional and only registered if the `routes/api.php` file exists and contains content.

| Surface | Default URL (Web Prefix) | Default URL (API Prefix) | Route Name |
| :--- | :--- | :--- | :--- |
| **Standalone Dashboard** | `/bpmn/workflows/definitions` | N/A | `bpmn.index` |
| **Filament Panel Table** | `/admin/bpmn/workflows/definitions` | N/A | `filament.admin.resources.bpmn.workflows.definitions.index` |
| **Visual Designer** | `/bpmn/workflows/definitions/{id}/design` | N/A | `bpmn.design` |
| **Instance Cockpit** | `/bpmn/workflows/instances/{id}/track` | N/A | `bpmn.instances.track` |
| **Instance Registry** | `/bpmn/workflows/instances` | N/A | `bpmn.instances.index` |
| **External API (if enabled)** | N/A | `/api/bpmn/external` | `bpmn.api.external` |


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
By default, `middleware` is set to `['web']` for local evaluation. In production environments, protect these endpoints:

```php
'middleware' => ['web', 'auth'],
```

> **Note for Filament Users:** Laravel's default `auth` middleware expects a route named `login`. If your application relies on Filament's authentication routes, configure the unauthenticated redirect in `bootstrap/app.php`:
> ```php
> ->withMiddleware(function (Middleware $middleware) {
>     $middleware->redirectGuestsTo('/admin/login');
> })
> ```

---

## 2. Defining Domain Activities

Activities represent units of background work executed by Service Tasks.

### Generating an Activity
```bash
php artisan bpmn:make-activity SendInvoiceActivity
```

This creates `app/Workflows/Activities/SendInvoiceActivity.php`:

```php
namespace App\Workflows\Activities;

use Workflow\Activity;

class SendInvoiceActivity extends Activity
{
    public function execute(array $userData): array
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

Workflows can be started automatically by listening for domain events or Eloquent model changes.

### Model Lifecycle Triggers
Add the `TriggersBpmnWorkflows` trait to any Eloquent model:

```php
use Illuminate\Database\Eloquent\Model;
use Saccharine\Bpmn\Traits\TriggersBpmnWorkflows;

class Order extends Model
{
    use TriggersBpmnWorkflows;
}
```

When an order is created, the engine matches any active workflow with a Message Start Event configured with the alias `order_created`. The model attributes and lifecycle action are automatically injected into the initial payload:

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

    public function getWorkflowPayload(): array
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

Access the designer by opening `/bpmn/workflows/definitions/{id}/design`.

### Element Templates
Element templates allow developers to expose pre-configured Service Tasks with user-friendly form fields directly in the bpmn-js properties panel.

Generate a template:
```bash
php artisan bpmn:make-template "Charge Credit Card" --key="charge_card"
```

This creates a JSON template in `resources/bpmn/templates/`:

```json
[
  {
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
]
```

---

## 5. Supported Node Types & Handler Matrix

The execution engine delegates each BPMN node type to an isolated handler via `NodeHandlerFactory`. Boundary events (error and timer) are not standalone node types; they are evaluated through the `EvaluatesBoundaryEvents` trait mixed into `ServiceTaskHandler`, `UserTaskHandler`, `SubProcessHandler`, and `CallActivityHandler`.

| Node Type | Handler Class | Boundary Event Support | Execution Mechanism |
| :--- | :--- | :--- | :--- |
| `startEvent` | `StartEventHandler` | No | Resolves outgoing sequence flows and advances. |
| `endEvent` | `EndEventHandler` | No | Cleans up active tokens and terminates the branch. |
| `serviceTask` | `ServiceTaskHandler` | Yes | Yields an `ActivityStub` to the background queue worker. |
| `userTask` | `UserTaskHandler` | Yes | Awaits inbox signals with optional boundary timer timeouts. |
| `exclusiveGateway` | `ExclusiveGatewayHandler` | No | Evaluates condition expressions via Symfony Expression Language. |
| `parallelGateway` | `ParallelGatewayHandler` | No | Spawns child coroutines and establishes an `all()` sync barrier. |
| `callActivity` | `CallActivityHandler` | Yes | Invokes a separate published workflow definition by key. |
| `subProcess` | `SubProcessHandler` | Yes | Scopes tasks into a child workflow with boundary error containment. |

Boundary events (error or timer) attached to any of the above handlers are evaluated by the `EvaluatesBoundaryEvents` trait. When an activity throws an exception, an attached Error Boundary Event catches it, injects `_error_caught` into the payload, and routes down the boundary sequence flow. When a User Task has an attached Timer Boundary Event, the engine waits for either a human signal or timer expiry via `awaitWithTimeoutPromise()`.

---

## 6. Advanced BPMN Node Patterns

### Exclusive Gateways (XOR)
Define conditional branches using Symfony Expression Language inside the Sequence Flow's condition expression:
* `amount >= 1000`
* `status == "approved" and role in ["admin", "manager"]`

Leave one outgoing edge empty without an expression to designate it as the default/fallback route.

### Parallel Gateways (AND Split/Join)
* **Split**: Multiple outgoing sequence flows from a Parallel Gateway spawn independent child coroutines executed concurrently on the queue.
* **Join**: Converging sequence flows into a Parallel Gateway wait until all parallel branches complete before advancing. Variable mutations from all concurrent branches are merged back into the main payload.

### Error Boundary Events
Attach an Error Boundary Event directly to a Service Task. If the activity throws an unhandled exception, the engine catches it, skips standard queue retry policies, injects `_error_caught` into the payload, and navigates down the boundary sequence flow.

### User Tasks & Human Approvals
When a workflow reaches a User Task, execution suspends indefinitely without consuming worker resources.
* The engine emits a `UserTaskPending` event.
* Operators can click the pulsing node in the **Live Cockpit** (`/instances/{id}/track`) to submit approval decisions and resume the workflow.
* Alternatively, submit signals programmatically:
  ```php
  use Workflow\WorkflowStub;

  $instance = WorkflowInstance::find($instanceId);
  $workflow = WorkflowStub::load($instance->durable_workflow_id);
  $workflow->submitUserTask([
      'decision' => 'approved',
      'user_id'  => auth()->id(),
  ]);
  ```

---

## 7. Filament Integration

Scaffold the optional Filament resources:

```bash
php artisan bpmn:filament --demo
```

This publishes:
* `WorkflowDefinitionResource`: Manage definitions and open the designer.
* `WorkflowInstanceResource`: Monitor executions, active tokens, and manual interventions.
* `DemoOrderResource`: Interactive CRUD for testing model event triggers.