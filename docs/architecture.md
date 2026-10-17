# **Architecture & Under the Hood**

Saccharine BPMN is designed to keep your host application's domain logic completely decoupled from the orchestration layer. It achieves this through a multi-layered architecture that translates visual diagrams into isolated background coroutines.

## **1. The Parser (BpmnParserService)**

BPMN 2.0 files are complex, nested XML documents. Parsing raw XML during a background queue job is inefficient and difficult to query.

When a user clicks "Save" in the visual designer, the BpmnParserService intercepts the raw XML. It strictly validates the OMG namespaces and uses XPath to translate the visual diagram into highly queryable relational database tables (workflow_nodes and workflow_edges). The execution engine uses these tables to navigate the graph instantly via fast SQL queries.

## **2. The Interpreter (BpmnInterpreterWorkflow)**

The brain of the engine is a single Durable Workflow class. Because it runs on Laravel's queue system via the durable-workflow package, it can run indefinitely. If a server crashes, the workflow automatically resumes from its last known state upon reboot.

The interpreter uses a deterministic while loop to navigate from node to node, resolving the next step until it hits a terminal event.

## **3. The Strategy Pattern (Node Handlers)**

To keep the engine infinitely scalable, the main interpreter loop knows nothing about *how* to execute a specific BPMN shape. Instead, it delegates execution to isolated Node Handlers using the Strategy Pattern.

When the interpreter lands on a node, it queries the NodeHandlerFactory (e.g., ServiceTaskHandler, UserTaskHandler, ExclusiveGatewayHandler). Each handler implements the BpmnNodeHandlerInterface and returns a PHP Generator, yielding control back to the engine. This allows developers to easily add new BPMN specifications in the future without modifying the core loop.

## **4. Parallel Execution & Sync Barriers**

Standard PHP generators cannot be serialized if they are actively running, making parallel processes notoriously difficult to pause and resume.

When the engine hits a **Parallel Gateway (AND Split)**, the ParallelGatewayHandler bypasses this limitation by spawning autonomous **Child Workflows** for each divergent branch. These child stubs are dispatched concurrently to the background queue. The master workflow then halts at a Workflow::all() sync barrier, safely sleeping in the database until all child branches arrive at the converging join gateway.

## **5. State Projection & Deterministic Tracking**

A serialized durable workflow is effectively a "black box" while at rest, making it impossible to build a live dashboard using raw SQL.

The engine solves this via **State Projection**. As the engine navigates nodes, it uses Workflow::sideEffect() to write atomic records to the workflow_tokens database table.

> * **Determinism:** sideEffect ensures that even if the workflow wakes up and replays its execution history, the database update only happens once.
> * **Concurrency Safety:** Because every parallel child workflow manages its own distinct token row mapped to its unique durable_workflow_id, multiple queue workers can update the dashboard simultaneously without encountering race conditions or lost updates.