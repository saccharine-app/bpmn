---
section: docs_content
title: BPMN 2.0 Compatibility Matrix
description: Complete breakdown of supported, visual-only, and planned BPMN 2.0 elements, event definitions, and gateway semantics in Saccharine BPMN.
sectionTitle: Architecture & RFC
order: 7
package: bpmn
---

# BPMN 2.0 Compatibility Matrix

`saccharine/bpmn` implements the core subset of the OMG BPMN 2.0 specification required for queue-driven Laravel workflows. Unsupported elements are automatically pruned from the visual modeler's palette, context pad, and replace menu via `engineCapabilities.js`.

## Legend

* **✅ Supported:** Parsed into the relational graph, enabled in the visual modeler, and executed by a dedicated runtime handler.
* **⚠️ Visual / Partial:** Allowed on the canvas for layout or partially implemented (see notes).
* **🗓️ Planned:** Tracked on the v1.0 milestone roadmap.
* **❌ Not Supported:** Excluded from the palette and runtime engine.

---

## 1. Events Matrix (Position × Trigger Type)

Rather than listing all 70+ abstract BPMN metamodel combinations, the matrix below maps the concrete event positions supported by `BpmnParserService` against standard OMG trigger definitions:

| Event Trigger Type | Start Event (`<bpmn:startEvent>`) | Intermediate Catch (`<bpmn:intermediateCatchEvent>`) | Boundary (`<bpmn:boundaryEvent>`) | End Event (`<bpmn:endEvent>`) | Notes |
| :--- | :---: | :---: | :---: | :---: | :--- |
| **None (Blank)** | ✅ | — | — | ✅ | Initiates manual/API runs; End Events clean up active branch tokens. |
| **Message** | ✅ | 🗓️ | 🗓️ | ❌ | Start Events bind `messageRef` to Eloquent/event triggers. Mid-process correlation is Milestone 1. |
| **Timer** | ❌ | ✅ | ✅ | — | Supports ISO 8601 durations (`timeDuration`, e.g., `PT24H`) on Intermediate Catch and User Task Boundaries. |
| **Error** | ❌ | — | ✅ | ❌ | Attaches to `serviceTask`, `callActivity`, and `subProcess`; injects `_error_caught` into `$userData`. |
| **Signal / Escalation** | ❌ | ❌ | ❌ | ❌ | Pruned from the modeler UI. |
| **Conditional** | ❌ | ❌ | ❌ | — | Use an Exclusive Gateway (`XOR`) downstream of a Start Event for conditional routing. |
| **Compensation / Cancel** | ❌ | ❌ | ❌ | ❌ | Implement rollback logic programmatically inside Activity classes. |
| **Terminate** | — | — | — | ❌ | Use administrative `haltProcess` interventions or allow active branches to reach standard End Events. |
| **Link** | — | ❌ | — | — | Connect nodes directly via `<bpmn:sequenceFlow>`. |

> **Note on Non-Interrupting & Event Sub-Processes:** Non-interrupting boundary events (`cancelActivity="false"`), Intermediate Throw Events (`<bpmn:intermediateThrowEvent>`), and Event Sub-Processes (`triggeredByEvent="true"`) are not currently supported.

---

## 2. Tasks & Activities

| BPMN 2.0 Element | XML Tag | Status | Engine Behavior & Notes |
| :--- | :--- | :---: | :--- |
| **Service Task** | `<bpmn:serviceTask>` | ✅ | Resolves `camunda:class` against `config('saccharine-bpmn.activities')` and merges `camunda:inputParameter` defaults. |
| **User Task** | `<bpmn:userTask>` | ✅ | Emits `UserTaskPending` and hibernates the Fiber until resumed via `submitUserTask` or a Timer Boundary timeout. |
| **Call Activity** | `<bpmn:callActivity>` | ✅ | Resolves `calledElement` key to an active `ProcessVersion` and runs as an isolated child workflow. |
| **Embedded Sub-Process** | `<bpmn:subProcess>` | ✅ | Scopes nested elements via `parent_element_id` and executes inside a bounded child workflow. |
| **Generic Task** | `<bpmn:task>` | ⚠️ | Enabled in `engineCapabilities.js` for sketching, but lacks a runtime handler in `NodeHandlerFactory`. |
| **Script / Business Rule / Manual / Send / Receive Tasks** | `<bpmn:scriptTask>`, etc. | ❌ | Pruned from the UI; implement custom logic in PHP `Workflow\V2\Activity` classes via Service Tasks. |
| **Multi-Instance Loops** | `multiInstanceLoopCharacteristics` | 🗓️ | Sequential and parallel collection iteration on tasks and sub-processes is not yet implemented. |

---

## 3. Gateways

| BPMN 2.0 Element | XML Tag | Status | Engine Behavior & Notes |
| :--- | :--- | :---: | :--- |
| **Exclusive Gateway (XOR)** | `<bpmn:exclusiveGateway>` | ✅ | Evaluates outgoing `conditionExpression` rules via Symfony ExpressionLanguage; supports expressionless default flows. |
| **Parallel Gateway (AND)** | `<bpmn:parallelGateway>` | ✅ | Spawns concurrent child workflows on split and synchronizes payload mutations at the join via `Workflow\V2\all()`. |
| **Inclusive Gateway (OR)** | `<bpmn:inclusiveGateway>` | 🗓️ | Multi-branch conditional routing and dynamic join synchronization under research. |
| **Event-Based / Complex Gateways** | `<bpmn:eventBasedGateway>`, `<bpmn:complexGateway>` | ❌ | Pruned from the modeler palette and replace menu. |

---

## 4. Connecting Objects, Swimlanes & Data

| BPMN 2.0 Element | XML Tag | Status | Engine Behavior & Notes |
| :--- | :--- | :---: | :--- |
| **Sequence Flow** | `<bpmn:sequenceFlow>` | ✅ | Compiled into `process_edges` with optional `<bpmn:conditionExpression>` logic. |
| **Pools, Lanes & Groups** | `<bpmn:laneSet>`, `<bpmn:lane>`, `<bpmn:group>` | ⚠️ | Allowed on the canvas for visual organization; ignored during runtime execution. |
| **Text Annotations** | `<bpmn:textAnnotation>` | ⚠️ | Preserved in stored `bpmn_xml` for documentation; ignored by `BpmnParserService`. |
| **Data Objects & Data Stores** | `<bpmn:dataObject>`, `<bpmn:dataStore>` | ❌ | Process state flows implicitly through `$userData`; declarative `camunda:outputParameter` mapping is Milestone 2. |
| **Collaborations & Message Flows** | `<bpmn:collaboration>`, `<bpmn:messageFlow>` | ❌ | Cross-pool inter-process message flows are not supported. |