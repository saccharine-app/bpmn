---
extends: docs
section: docs_content
title: Quickstart Guide
description: Get up and running with Saccharine BPMN in under five minutes.
sectionTitle: Getting Started
order: 2
package: bpmn
---

# Quickstart Guide

Get up and running with `saccharine/bpmn` in under five minutes. This guide walks through installing the package, running migrations, seeding the demo workflows, and tracing a live process instance from trigger to completion.

---

## Requirements

* **PHP:** `^8.4`
* **Laravel Framework:** `^13.0`
* **Orchestration Core:** `durable-workflow/workflow` `^2.0`
* **Expression Parser:** `symfony/expression-language` `^8.1`
* **Database:** MySQL 8+, MariaDB 10.3+, PostgreSQL 13+, or SQLite 3.35+
* **Queue Driver:** Database, Redis, or SQS (a background worker running `php artisan queue:work` is required to advance durable workflows)

---

## 1. Installation

Install the package via Composer:

```bash
composer require saccharine/bpmn
```

Publish the package configuration and pre-compiled `bpmn-js` frontend assets, then run your database migrations:

```bash
php artisan bpmn:install
php artisan migrate
```

* `bpmn:install` publishes `config/saccharine-bpmn.php` (`saccharine-bpmn-config`) and the compiled browser assets to `public/vendor/saccharine/bpmn` (`saccharine-bpmn-assets`).
* `php artisan migrate` creates both the `durable-workflow` v2 runtime tables and the package's `process_*` tables (loaded automatically by `BpmnServiceProvider`).

---

## 2. Seed the Demo Workflows

The package ships with `BpmnDemoSeeder`, which compiles four example BPMN 2.0 process definitions into your database:

```bash
php artisan db:seed --class='Saccharine\Bpmn\Database\Seeders\BpmnDemoSeeder'
```

This populates:
1. **Demo: Order Processing (`order-processing`):** Triggered automatically when a `DemoOrder` model is created (`demo_order_created`). Routes orders `>= $1000` to a `Manager Review` User Task before sending a confirmation via the built-in `Saccharine\Bpmn\Workflows\Activities\SendNotificationActivity` (`bpmn_send_notification`).
2. **Demo: Parallel Fulfillment (`parallel-fulfillment`):** Demonstrates AND split/join execution across concurrent branches.
3. **Demo: SLA Escalation & Error Boundaries (`sla-escalation`):** Demonstrates a `PT24H` Timer Boundary Event on a User Task and an Error Boundary Event on a Service Task.
4. **Demo: Parent Pipeline with Call Activity (`parent-pipeline`):** Demonstrates an inline `subProcess` followed by a `callActivity` invoking another published process definition.

*(Optional)* If you use **Filament v5**, you can also publish a `DemoOrderResource` CRUD table and sample activity class to trigger orders directly from your admin panel:

```bash
php artisan bpmn:publish-filament-stubs
```

---

## 3. Running the Engine

Open two terminal windows in your project directory:

### Terminal A: Start a Queue Worker

Start your standard Laravel queue worker to execute workflow steps:

```bash
php artisan queue:work
```

### Terminal B: Trigger a Test Order

Create a `DemoOrder` record using the built-in test command:

```bash
# Triggers an order requiring human review (total >= 1000)
php artisan bpmn:test-order --amount=1500 --email=user@example.com
```

Because `DemoOrder` uses the `TriggersBpmnProcesses` trait, creating the record dispatches `ModelEventTrigger` (`demo_order_created`), which `ProcessTriggerListener` intercepts to launch an instance of `order-processing`.

In Terminal A, `BpmnConsoleLogListener` streams live execution transitions:

```text
  [BPMN:ENGINE] [#1] (Start_OrderPlaced) Process started from node [Start_OrderPlaced].
  [BPMN:GATEWAY] [#1] (Gateway_VIP) Evaluated conditions. Routing token -> [Task_ManualReview].
  [BPMN:HIBERNATE] [#1] (Task_ManualReview) User Task [Manager Review] reached. Workflow hibernating in database awaiting signal.
```

---

## 4. Inspecting & Resuming in the Live Cockpit

Open the standalone process dashboard in your browser:

```text
http://your-app.test/bpmn/processes/instances
```

> **Note:** The default web prefix is `bpmn/processes`, configurable via `prefix.web` in `config/saccharine-bpmn.php`. If you have registered `BpmnFilamentPlugin::make()` in a Filament v5 panel, instances are also available at `/admin/bpmn/processes/instances`.

### Completing the Hibernated User Task

1. Locate the running **Demo: Order Processing** instance and click **Live Cockpit** (`/bpmn/processes/instances/1/track`).
2. Inspect the diagram overlay:
   * **Completed nodes** (`.bpmn-history-completed`) are highlighted in slate blue.
   * **Active waiting nodes** (`.bpmn-active-token`) pulse in green.
3. Click the pulsing **Manager Review** node on the canvas to open the task approval modal.
4. Choose **Approve & Continue**, enter review notes, and click **Submit & Resume**.
5. In Terminal A, watch your queue worker immediately wake the hibernated Fiber, pass through the merge gateway, run `SendNotificationActivity` (logging the confirmation message), and complete at the End Event.

---

## CLI Scaffolding & Control Reference

Once you have verified the demo workflow, use the Artisan CLI to scaffold your own components or control running instances:

```bash
# Scaffold a Service Task Activity and register it in config/saccharine-bpmn.php
php artisan bpmn:make-activity SendInvoiceActivity

# Scaffold a custom BpmnTriggerableEvent and register its alias
php artisan bpmn:make-trigger CustomerOnboardedEvent

# Scaffold a Camunda-compatible Element Template JSON file in resources/bpmn/templates/
php artisan bpmn:make-template "Charge Credit Card" --key="charge_card"

# List and control running process instances
php artisan bpmn:instance list
php artisan bpmn:instance suspend {id}
php artisan bpmn:instance resume {id}
php artisan bpmn:instance halt {id}
```