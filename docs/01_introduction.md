---
section: docs_content
title: Introduction & Overview
description: Saccharine BPMN turns standardized visual process diagrams into executable, queue-driven workflows inside your Laravel application.
sectionTitle: Getting Started
order: 1
package: bpmn
---

# Introduction: Native BPMN 2.0 for Laravel

`saccharine/bpmn` turns standardized visual process diagrams into executable, queue-driven workflows inside your Laravel application. It bridges the gap between visual process modeling and Laravel domain logic by pairing `bpmn-js` for interactive modeling with `durable-workflow/workflow` v2 for stateful, queue-backed PHP Fibers.

---

## What is BPMN 2.0?

Business Process Model and Notation (BPMN 2.0) is an open, vendor-neutral standard maintained by the Object Management Group (OMG). It establishes a visual syntax—flowcharts composed of events, activities, gateways, and sequence flows—designed to align business stakeholders and software developers.

In traditional applications, workflows drawn on whiteboards inevitably diverge from code. Business rules end up scattered across nested controller conditions, queued jobs, cron schedules, and event listeners.

**With `saccharine/bpmn`, the visual diagram is the executable source of truth.** The XML graph drawn in your browser is compiled directly into relational database records and executed by your existing Laravel queue workers.

---

## The Integration Bridge

`saccharine/bpmn` serves as an **integration bridge** uniting battle-tested open-source technologies inside a native Composer package:

```
┌────────────────────────────────────────────────────────┐
│       bpmn-js (Camunda) - Visual Modeler & SVG         │
└───────────────────────────┬────────────────────────────┘
                            │ BPMN 2.0 XML
                            ▼
┌────────────────────────────────────────────────────────┐
│   saccharine/bpmn - XPath Decomposition & Schema       │
└───────────────────────────┬────────────────────────────┘
                            │ Fiber Coroutines
                            ▼
┌────────────────────────────────────────────────────────┐
│   durable-workflow v2 - Deterministic Fiber State      │
└───────────────────────────┬────────────────────────────┘
                            │ Scheduled Jobs
                            ▼
┌────────────────────────────────────────────────────────┐
│    Laravel Queue Workers (Redis / SQS / Database)      │
└────────────────────────────────────────────────────────┘
```

* **Visual Canvas via [bpmn-js](https://bpmn.io/toolkit/bpmn-js/):** Embeds an interactive SVG modeler, capability-filtered palettes, Camunda-compatible Element Templates, and a real-time execution viewer in your browser or Filament v5 admin panel.
* **Native Fiber Engine via [durable-workflow](https://durable-workflow.com) v2:** Coordinates execution via native PHP 8.1+ Fibers. Workflows pause, hibernate to the database, and resume without holding queue worker threads or memory.
* **Sandboxed Routing via [Symfony ExpressionLanguage](https://symfony.com/doc/current/components/expression_language.html):** Evaluates conditional branching on Exclusive Gateways safely against execution payloads without raw PHP `eval()`.
* **Native Laravel Infrastructure:** Runs on your existing database connections and queue workers without requiring dedicated Java runtime servers or external daemons.

---

## Core Architectural Principles

### 1. Blueprint vs. Runtime Separation

The engine maintains a strict boundary between workflow blueprints and runtime state:

* **Blueprints (Compiled Definitions):** When a BPMN diagram is saved, `BpmnParserService` validates OMG BPMN 2.0 namespaces, parses XML elements via XPath, and writes the relational graph into indexed tables (`process_definitions`, `process_versions`, `process_nodes`, and `process_edges`). At runtime, graph traversal occurs via indexed database queries rather than in-memory XML parsing.
* **Runtime State (Tokens & Audit Logs):** Running workflow instances track active execution pointers inside `process_tokens`, while `process_history_events` records an immutable audit trail of completed steps alongside JSON snapshots of the `$userData` payload.

### 2. Zero Worker Blocking

Long-running business processes—such as waiting for human approval or pausing for an ISO 8601 timer duration—**never block a queue worker**.

When a process reaches a User Task or Timer:
1. The Fiber suspends transparently inside the execution runtime.
2. The queue worker commits the token checkpoint to `process_tokens` and releases its memory immediately.
3. The queue worker picks up the next job on the queue.
4. When an operator submits human input or a timer expires, the workflow instance wakes up, replays its deterministic event log, and advances from that exact step.

### 3. Decoupled Host Domain Logic

Your business logic stays inside standard Laravel classes:
* **Service Tasks** map string implementation keys in `config/saccharine-bpmn.php` to isolated `Workflow\V2\Activity` classes.
* **Start Events** listen to Eloquent model lifecycle hooks (`TriggersBpmnProcesses`) or custom `BpmnTriggerableEvent` classes with database-enforced idempotency.