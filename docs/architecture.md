# **Architecture & Under the Hood**

Saccharine BPMN decouples visual workflow modeling from host domain logic by translating standard BPMN 2.0 XML diagrams into durable, stateful PHP coroutines.

```
┌──────────────────┐       ┌────────────────────────┐       ┌───────────────────────────┐
│  Visual Modeler  │ ───>  │   BpmnParserService    │ ───>  │    Relational Schema      │
│    (bpmn-js)     │ XML   │  (XPath Decomposition) │       │ (nodes, edges, templates) │
└──────────────────┘       └────────────────────────┘       └─────────────┬─────────────┘
                                                                          │
                                                                          ▼
┌──────────────────┐       ┌────────────────────────┐       ┌───────────────────────────┐
│   Live Cockpit   │ <───  │    State Projection    │ <───  │  BpmnInterpreterWorkflow  │
│  (Token Monitor) │ SQL   │ (workflow_tokens / log)│ side- │   (durable-workflow loop) │
└──────────────────┘       └────────────────────────┘ effect└───────────────────────────┘
```

---

## 1. Relational Graph Decomposition (`BpmnParserService`)

Parsing raw BPMN XML during queue execution introduces serialization overhead and prevents indexing. 

When a workflow is saved and compiled:
1. `BpmnParserService` validates standard OMG BPMN 2.0 namespaces.
2. It extracts `<bpmn:process>` nodes, sequence flows, extension elements, and boundary definitions.
3. It stores the diagram in relational database tables (`workflow_nodes` and `workflow_edges`).

During background execution, graph traversal occurs via indexed database queries rather than in-memory XML parsing.

---

## 2. Coroutine State Machine (`BpmnInterpreterWorkflow`)

The execution engine is implemented as a single coroutine workflow extending `Workflow\Workflow` from the `durable-workflow` package.

* **Replay Determinism**: Workflow state is reconstructed from historical events on wake-up.
* **Non-Blocking Sleep**: When hibernating at a User Task or boundary timer, no worker processes or active threads are held open.
* **Infinite Execution**: Long-running processes spanning days or weeks survive server reboots and deployments.

---

## 3. The Node Handler Strategy Pattern

The interpreter loop delegates execution to isolated handlers via `NodeHandlerFactory`:

```php
interface BpmnNodeHandlerInterface
{
    public function handle(
        BpmnInterpreterWorkflow $workflow,
        WorkflowNode $node,
        WorkflowVersion $version,
        array $userData,
        ?int $instanceId
    ): \Generator;
}
```

Each handler yields generator promises (activities, child workflows, or inbox awaits) and returns a two-tuple containing the next node identifier and the updated state payload:

```php
return [$nextNodeId, $updatedUserData];
```

| Node Type | Handler | Execution Mechanism |
| :--- | :--- | :--- |
| `startEvent` | `StartEventHandler` | Resolves outgoing sequence flows and advances. |
| `serviceTask` | `ServiceTaskHandler` | Yields an `ActivityStub` to the background queue worker. |
| `userTask` | `UserTaskHandler` | Awaits inbox signals with optional boundary timer timeouts. |
| `exclusiveGateway` | `ExclusiveGatewayHandler` | Evaluates condition expressions via Symfony Expression Language. |
| `parallelGateway` | `ParallelGatewayHandler` | Spawns child coroutines and establishes an `all()` sync barrier. |
| `subProcess` | `SubProcessHandler` | Scopes tasks into a child workflow with boundary error containment. |
| `callActivity` | `CallActivityHandler` | Invokes a separate published workflow definition by key. |
| `endEvent` | `EndEventHandler` | Cleans up active tokens and terminates the branch. |

---

## 4. Parallel Splitting & Child Workflow Synchronization

Standard PHP generators cannot be serialized across queue steps while actively running. To support parallel processing:

1. When reaching a **Parallel Gateway Split**, the engine spawns independent instances of `BpmnInterpreterWorkflow` via `ChildWorkflowStub::make()`.
2. Each child coroutine begins execution at the starting node of its branch.
3. The parent workflow pauses at a `yield all($stubs)` barrier.
4. When all branches arrive at the converging join node, the parent workflow resumes, merges the branch payload mutations, and advances to the post-join node.

---

## 5. State Projection & Live Cockpit Telemetry

Durable workflows serialize execution state into internal logs, which cannot be directly queried with relational SQL.

To support real-time dashboards and monitoring:
* **Atomic Token Writes**: When entering a node, the engine executes `WorkflowStub::sideEffect()` to record the token's location in `workflow_tokens`. The `sideEffect` wrapper ensures the database write executes only once, preventing duplicates during coroutine replays.
* **Audit History Trail**: Completed steps are recorded in `workflow_history` alongside payload snapshots.
* **Cockpit Visualization**: The tracking cockpit at `/instances/{id}/track` pairs `bpmn-js/lib/NavigatedViewer` with polling endpoints to render traversed paths in blue and active waiting tokens in pulsing green.