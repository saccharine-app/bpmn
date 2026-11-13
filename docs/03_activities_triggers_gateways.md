---
extends: docs
section: docs_content
title: Activities, Triggers & Gateways
description: Connect Laravel domain logic to BPMN diagrams using durable activities, Eloquent model hooks, sandboxed gateway expressions, and boundary events.
sectionTitle: Building Workflows
order: 3
package: bpmn
---

# Developer Guide: Activities, Triggers & Gateways

`saccharine/bpmn` separates visual process orchestration from your application's business logic. Rather than embedding PHP code inside XML files, diagrams reference string aliases that resolve to standard Laravel classes, Eloquent traits, and sandboxed routing expressions.

---

## 1. Service Tasks & Durable Activities

A **Service Task** (`<bpmn:serviceTask>`) executes automated background work on your Laravel queue workers. When an execution token reaches a Service Task, `ServiceTaskHandler` looks up the node's `implementation` key (`camunda:class`) in `config/saccharine-bpmn.php` and dispatches the corresponding Activity class.

### Scaffolding an Activity

Generate a new Activity class and auto-register its alias in `config/saccharine-bpmn.php` using Artisan:

```bash
php artisan bpmn:make-activity VerifyFraudScoreActivity
```

In `durable-workflow/workflow` v2, activities extend `Workflow\V2\Activity` and implement a synchronous `handle(array $userData): array` method:

```php
namespace App\Workflows\Activities;

use Illuminate\Support\Facades\Http;
use Workflow\V2\Activity;

class VerifyFraudScoreActivity extends Activity
{
    /**
     * Executes the task on a queue worker.
     * Receives the merged node input parameters and current process state ($userData).
     * Returns an associative array of mutations to merge back into $userData.
     */
    public function handle(array $userData): array
    {
        $response = Http::post('https://fraud-service.internal/check', [
            'email'  => $userData['customer_email'],
            'amount' => $userData['total'],
        ]);

        return [
            'fraud_score'       => $response->json('score'),
            'fraud_verified_at' => now()->toIso8601String(),
        ];
    }
}
```

### Configuration Registry & Input Merging

Activities are mapped in `config/saccharine-bpmn.php`:

```php
// config/saccharine-bpmn.php
'activities' => [
    'verify_fraud' => \App\Workflows\Activities\VerifyFraudScoreActivity::class,
],
```

Before invoking `handle()`, `ServiceTaskHandler` merges any static `<camunda:inputParameter>` values compiled from the diagram with the active process `$userData` (`array_merge($inputParameters, $userData)`), ensuring runtime payload keys take precedence over static template defaults.

### Built-in Notification Activity

The package registers `Saccharine\Bpmn\Workflows\Activities\SendNotificationActivity` under the key `bpmn_send_notification`. It supports `mail`, `webhook` (HTTP POST), and `log` channels with `{{ key }}` mustache interpolation against `$userData` (including nested dot-notation keys via `data_get()`).

---

## 2. Starting Processes (Model Hooks & Custom Events)

Processes start automatically when `ProcessTriggerListener` intercepts an event implementing `Saccharine\Bpmn\Contracts\BpmnTriggerableEvent` whose alias matches an active process version's Message Start Event (`<bpmn:startEvent>` with `<bpmn:messageEventDefinition>`).

### Option A: Eloquent Model Lifecycle Hooks

Attach the `TriggersBpmnProcesses` trait to any Eloquent model:

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Saccharine\Bpmn\Traits\TriggersBpmnProcesses;

class Order extends Model
{
    use TriggersBpmnProcesses;
}
```

When an `Order` is created, updated, or deleted, the trait dispatches `ModelEventTrigger`, which automatically matches Message Start Events configured with `{model_snake}_{action}` (e.g., `order_created`, `order_updated`, `order_deleted`) or a custom alias mapped in `config/saccharine-bpmn.php` as `App\Models\Order::class . '@created'`.

The model's attributes and lifecycle metadata are injected into `$userData`:

```json
{
  "id": 42,
  "total": 1500.00,
  "_model_class": "App\\Models\\Order",
  "_model_id": 42,
  "_action": "created",
  "_dirty": []
}
```

### Option B: Custom Domain Events

Generate a custom triggerable event class and register its alias:

```bash
php artisan bpmn:make-trigger OrderPlacedEvent
```

Implement `getBusinessKey()` and `getProcessPayload()`:

```php
namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Saccharine\Bpmn\Contracts\BpmnTriggerableEvent;

class OrderPlacedEvent implements BpmnTriggerableEvent
{
    use Dispatchable;

    public function __construct(
        public string $orderId,
        public float $amount,
        public string $customerEmail
    ) {}

    public function getBusinessKey(): string
    {
        return 'order_placed_' . $this->orderId;
    }

    public function getProcessPayload(): array
    {
        return [
            'order_id'       => $this->orderId,
            'amount'         => $this->amount,
            'customer_email' => $this->customerEmail,
        ];
    }
}
```

Register the alias in `config/saccharine-bpmn.php`:

```php
'triggers' => [
    'order_placed' => \App\Events\OrderPlacedEvent::class,
],
```

### Database-Enforced Idempotency

`ProcessTriggerListener` wraps workflow creation inside a database transaction checked against `process_triggers_log`, which enforces a composite unique index on `['process_version_id', 'business_key']`. If the same domain event fires twice for the same business key and process version, duplicate launches are prevented automatically.

---

## 3. Gateways & Sandboxed Routing

### Exclusive Gateways (XOR)

Exclusive Gateways evaluate `<bpmn:conditionExpression>` rules on outgoing `<bpmn:sequenceFlow>` edges using **Symfony ExpressionLanguage** inside `ExpressionEvaluator`—avoiding raw PHP `eval()`.

```xml
<bpmn:sequenceFlow id="Flow_HighValue" sourceRef="Gateway_VIP" targetRef="Task_ManualReview">
  <bpmn:conditionExpression xsi:type="bpmn:tFormalExpression">
    total &gt;= 1000 and customer_tier in ['vip', 'enterprise']
  </bpmn:conditionExpression>
</bpmn:sequenceFlow>
```

* **Evaluation Order:** Outgoing edges with condition expressions are evaluated sequentially; the first branch evaluating to `true` wins.
* **Default Fallback Flows:** An outgoing edge without a `conditionExpression` serves as the default route if no conditional branches match.
* **Safe Error Handling:** Invalid expression syntax is caught, logged via `Log::error()`, and evaluates to `false` without crashing the queue worker. If no conditional branch or default route matches, `GatewayRouter` throws a `RuntimeException` to stall the broken instance visibly.

### Parallel Gateways (AND Splits & Joins)

Parallel Gateways (`<bpmn:parallelGateway>`) execute multiple branches concurrently:

1. **Fork (Split):** When an outgoing gateway has multiple edges, `ParallelGatewayHandler` builds an array of deferred closures calling `$workflow->makeChildWorkflow(...)` for each branch target and passes them to `Workflow\V2\all($parallelTasks)`.
2. **Synchronize (Join):** Each branch runs independently across queue workers until reaching the converging join gateway (`return [null, $userData]`). Once all child workflows resolve, `ParallelGatewayHandler` merges each branch's returned `$userData` array back into the parent payload and advances past the join gateway.

---

## 4. Human-in-the-Loop User Tasks

When an execution token arrives at a `<bpmn:userTask>`:

1. `UserTaskHandler` dispatches `Saccharine\Bpmn\Events\UserTaskPending` with the `workflowId`, task name, and current `$userData`.
2. The Fiber suspends via `$workflow->awaitPromise(...)` (or `$workflow->awaitWithTimeoutPromise(...)` if a Timer Boundary Event is attached), committing the active node to `process_tokens` and releasing the queue worker immediately.
3. When an operator submits input via the Live Cockpit or `POST /bpmn/processes/instances/{id}/tasks/{elementId}/submit`, `ProcessInstanceController` calls `$workflow->submitUpdate('submitUserTask', $payload)`.
4. The Fiber wakes up, pops the payload from `$pendingUserTaskPayloads`, merges the submitted fields into `$userData`, and advances to the next node.

---

## 5. Sub-Processes, Call Activities & Boundary Events

### Modular Composition

* **Call Activities (`<bpmn:callActivity>`):** Uses the `calledElement` attribute (e.g., `calledElement="order-processing"`) to look up the active `ProcessVersion` of another `ProcessDefinition` and invoke it via `makeChildWorkflow()`, merging its final `$userData` back into the caller.
* **Inline Sub-Processes (`<bpmn:subProcess>`):** Groups nested elements scoped by `parent_element_id`. `SubProcessHandler` executes the bounded sub-graph via `makeScopedChildWorkflow()` starting from the sub-process's internal `startEvent`.

### Boundary Events & Intermediate Timers

* **Error Boundary Events (`<bpmn:errorEventDefinition>`):** Attached to a `serviceTask`, `callActivity`, or `subProcess`. When an unhandled `Throwable` occurs, `EvaluatesBoundaryEvents::handleErrorBoundary()` catches the exception, injects `_error_caught` with the exception message into `$userData`, and routes the token down the boundary edge instead of triggering queue retries.
* **Timer Boundary Events (`<bpmn:timerEventDefinition>`):** Attached to a `userTask` with an ISO 8601 `<bpmn:timeDuration>` (e.g., `PT24H`). If the duration elapses before human input arrives (and the process is not suspended or halted), `UserTaskHandler` sets `_timer_expired = true` in `$userData` and routes along the timer boundary path.
* **Intermediate Timer Catch Events (`<bpmn:intermediateCatchEvent>`):** Pauses sequential flow mid-process for an ISO 8601 `<bpmn:timeDuration>` (e.g., `PT30M` or `P2D`) via `$workflow->timerPromise($duration)` without holding a queue worker thread.