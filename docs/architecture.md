# Architecture & Under the Hood

Saccharine BPMN decouples visual process modeling from host domain logic by translating standard BPMN 2.0 XML diagrams into durable, stateful PHP Fibers.

```
┌──────────────────┐       ┌────────────────────────┐       ┌───────────────────────────┐
│  Visual Modeler  │ ───>  │   BpmnParserService    │ ───>  │    Relational Schema      │
│    (bpmn-js)     │ XML   │  (XPath Decomposition) │       │ (nodes, edges, templates) │
└──────────────────┘       └────────────────────────┘       └─────────────┬─────────────┘
                                                                          │
                                                                          ▼
┌──────────────────┐       ┌────────────────────────┐       ┌───────────────────────────┐
│   Live Cockpit   │ <───  │    State Projection    │ <───  │  BpmnInterpreterWorkflow  │
│  (Token Monitor) │ SQL   │ (process_tokens / log) │ side- │    (PHP Fiber runtime)    │
└──────────────────┘       └────────────────────────┘ effect└───────────────────────────┘
```

---

## 1. Relational Graph Decomposition (`BpmnParserService`)

Parsing raw BPMN XML during queue execution introduces serialization overhead and prevents indexing. 

When a process is saved and compiled:
1. `BpmnParserService` validates standard OMG BPMN 2.0 namespaces.
2. It extracts `<bpmn:process>` nodes, sequence flows, extension elements, and boundary definitions.
3. It stores the diagram in relational database tables (`process_nodes` and `process_edges`).

During background execution, graph traversal occurs via indexed database queries rather than in-memory XML parsing.

---

## 2. Fiber State Machine (`BpmnInterpreterWorkflow`)

The execution engine is implemented as a durable workflow extending `Workflow\V2\Workflow` from the `durable-workflow` v2 package.

* **Fiber-Based Cooperative Multitasking**: Instead of PHP generators yielding promises (`yield from`), execution runs on native PHP 8.1+ Fibers. Suspension and resumption happen transparently inside framework helpers (`activity()`, `await()`, `child()`, `sideEffect()`).
* **Replay Determinism**: When a workflow wakes up from an external signal or background job completion, historical steps return cached results from the event log without re-executing external side effects.
* **Non-Blocking Sleep**: When hibernating at a User Task or boundary timer, worker processes terminate and release system memory while the process state remains durably persisted in the relational database.

---

## 3. The Node Handler Strategy Pattern

The interpreter loop delegates execution to isolated handlers via `NodeHandlerFactory`:

```php
namespace Saccharine\Bpmn\Contracts;

use Saccharine\Bpmn\Workflows\BpmnInterpreterWorkflow;
use Saccharine\Bpmn\Models\ProcessNode;
use Saccharine\Bpmn\Models\ProcessVersion;

interface BpmnNodeHandlerInterface
{
    /**
     * @return array [?string $nextNodeId, array $updatedUserData]
     */
    public function handle(
        BpmnInterpreterWorkflow $workflow,
        ProcessNode $node,
        ProcessVersion $version,
        array $userData,
        ?int $instanceId
    ): array;
}
```

Because Fibers suspend from any depth in the call stack, handlers execute as synchronous methods returning a two-tuple `[?string $nextNodeId, array $userData]`:

| Node Type | Handler Class | Boundary Events | Execution Mechanism |
| :--- | :--- | :--- | :--- |
| `startEvent` | `StartEventHandler` | No | Resolves outgoing sequence flows and advances. |
| `endEvent` | `EndEventHandler` | No | Cleans up active tokens and terminates the branch. |
| `serviceTask` | `ServiceTaskHandler` | Yes (via `EvaluatesBoundaryEvents`) | Calls `$workflow->makeActivity(...)` (`activity()`), suspending until worker returns. |
| `userTask` | `UserTaskHandler` | Yes (via `EvaluatesBoundaryEvents`) | Suspends via `$workflow->awaitPromise(...)` or boundary timer check. |
| `exclusiveGateway` | `ExclusiveGatewayHandler` | No | Evaluates condition expressions via Symfony Expression Language. |
| `parallelGateway` | `ParallelGatewayHandler` | No | Dispatches concurrent closures via `Workflow\V2\all()`. |
| `callActivity` | `CallActivityHandler` | Yes (via `EvaluatesBoundaryEvents`) | Invokes a separate published process definition via `child()`. |
| `subProcess` | `SubProcessHandler` | Yes (via `EvaluatesBoundaryEvents`) | Scopes tasks into a child workflow with boundary error containment. |

---

## 4. Parallel Splitting & Child Workflow Synchronization

In `durable-workflow` v2, calling `child()` immediately suspends the active fiber unless deferred. To execute parallel branches concurrently:

1. When reaching a **Parallel Gateway Split**, the handler builds an array of closures wrapping `child()` invocations:
   ```php
   $parallelTasks = [];
   foreach ($outgoingEdges as $edge) {
       $targetId = $edge->target_node_id;
       $parallelTasks[] = fn () => $workflow->makeChildWorkflow(
           $version->id,
           $userData,
           $targetId,
           $instanceId
       );
   }
   ```
2. The array of closures is passed into `Workflow\V2\all($parallelTasks)`, which executes each branch concurrently across the queue.
3. When all child branches hit the converging join node (`return [null, $userData]`), `all()` resumes and merges payload mutations into the parent workflow state.

---

## 5. Sandboxed Expression Evaluation (`ExpressionEvaluator`)

Gateway condition expressions are evaluated through `ExpressionEvaluator`, wrapping Symfony's `ExpressionLanguage` in a sandboxed interpreter:

* **No raw `eval()`**: Expressions are parsed safely into an Abstract Syntax Tree (AST).
* **Payload-scoped variables**: `$userData` is injected into the evaluation scope, allowing rules like `total >= 1000` or `status == "approved" and role in ["admin", "manager"]`.
* **Graceful fallback**: A malformed expression is logged and returns `false`, preventing unhandled crashes from aborting the workflow queue worker.

---

## 6. State Projection & Live Cockpit Telemetry

Durable workflows serialize execution state into internal event logs. To provide fast SQL queries for user interfaces without loading full execution graphs:

* **Atomic Token Writes**: When entering a node, the engine invokes `Workflow\V2\sideEffect()` to update `process_tokens`. The `sideEffect` wrapper ensures the database mutation executes exactly once per step and is skipped during replay.
* **Audit History Trail**: Completed steps are recorded in `process_histories` alongside JSON snapshots of `$userData`.
* **Cockpit Visualization**: The tracking cockpit at `/instances/{id}/track` pairs `bpmn-js/lib/NavigatedViewer` with polling endpoints to render traversed paths in blue (`.bpmn-history-completed`), active waiting tokens in pulsing green (`.bpmn-active-token`), and failed nodes in red (`.bpmn-failed-token`).