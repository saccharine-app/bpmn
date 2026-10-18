# **Saccharine BPMN Usage Guide**

This guide covers configuring the engine, authoring triggers and activities, designing workflows, managing human tasks, and integrating with Filament.

---

## 1. Configuration & URL Routing

The published configuration file lives at `config/saccharine-bpmn.php`.

### Unified URL Prefix
The package uses a single source of truth for routing. The `prefix` key controls both the standalone Blade endpoints and the Filament resource slugs:

```php
// config/saccharine-bpmn.php
'prefix' => 'bpmn/workflows',
```

| Surface | URL | Route Name |
| :--- | :--- | :--- |
| **Standalone Dashboard** | `/bpmn/workflows/definitions` | `bpmn.index` |
| **Filament Panel Table** | `/admin/bpmn/workflows/definitions` | `filament.admin.resources.bpmn.workflows.definitions.index` |
| **Visual Designer** | `/bpmn/workflows/definitions/{id}/design` | `bpmn.design` |
| **Instance Cockpit** | `/bpmn/workflows/instances/{id}/track` | `bpmn.instances.track` |
| **Instance Registry** | `/bpmn/workflows/instances` | `bpmn.instances.index` |

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

## 5. Advanced BPMN Node Patterns

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

## 6. Filament Integration

Scaffold the optional Filament resources:

```bash
php artisan bpmn:filament --demo
```

This publishes:
* `WorkflowDefinitionResource`: Manage definitions and open the designer.
* `WorkflowInstanceResource`: Monitor executions, active tokens, and manual interventions.
* `DemoOrderResource`: Interactive CRUD for testing model event triggers.