---
section: docs_content
title: Visual Modeler & Element Templates
description: Author BPMN 2.0 diagrams in the browser, manage draft and active versions, and build custom Element Template forms for domain tasks.
sectionTitle: Building Workflows
order: 4
package: bpmn
---

# Visual Modeler & Element Templates

`saccharine/bpmn` embeds Camunda's open-source `bpmn-js` editor and properties panel directly into Laravel Blade views, allowing teams to author, version, and publish executable BPMN 2.0 diagrams from the browser.

---

## 1. Using the Process Designer

Navigate to `/bpmn/processes/definitions` (or click **Open Designer** on any row in the Filament `ProcessDefinitionResource` table) to launch the designer at `/bpmn/processes/definitions/{id}/design`.

### Workbench Header & Versioning Lifecycle

The top workbench bar (`workbench-header.blade.php`) manages process metadata and immutable diagram versions:

* **Inline Process Renaming:** Clicking the process title edits `ProcessDefinition::$name` directly and auto-persists on blur via `PATCH /bpmn/processes/definitions/{id}`.
* **Save Draft:** Compiles the current canvas XML via `BpmnParserService` into a new incremental `ProcessVersion` row (`is_active = false`), leaving any currently active production version untouched.
* **Publish & Activate:** Atomically deactivates prior versions for that definition (`is_active = false`), creates the new version with `is_active = true`, and compiles its nodes and edges inside a single database transaction. New triggers immediately route to the newly activated version, while existing running instances finish safely on the version they started with.
* **Archive Process:** Deletes the process definition via `DELETE /bpmn/processes/definitions/{id}` and returns to the canonical dashboard resolved by `BpmnRouteHelper::dashboardUrl()`.

> **Frontend Resilience:** The workbench wires its `bpmn:save-requested` and `bpmn:archive-requested` DOM event listeners prior to initializing `window.initBpmnDesigner()`, ensuring save and archive actions remain responsive even if custom template parsing encounters an error.

---

## 2. Capability-Filtered Canvas UI

The full OMG BPMN 2.0 specification includes over 80 element types, many of which are unsupported by queue-based execution engines. To prevent analysts from drawing diagrams that cannot execute, `resources/js/engineCapabilities.js` serves as the single source of truth for supported elements (`DEFAULT_SUPPORTED_ELEMENTS`).

Three non-destructive `bpmn-js` middleware providers prune unsupported actions from the editor UI:

* **`FilteredPaletteProvider.js`:** Strips `create.*` entries from the left-hand tool palette that are not registered in `engineCapabilities.js`, while preserving manipulation tools (hand, lasso, space, global connect).
* **`FilteredContextPadProvider.js`:** Filters quick-append actions when clicking an existing shape on the canvas and injects direct append shortcuts for `bpmn:ServiceTask` and `bpmn:UserTask` on all non-EndEvent elements.
* **`FilteredReplaceMenuProvider.js`:** Filters the "wrench" element replacement menu (`bpmn-replace`) so elements can only be morphed into engine-supported types (such as converting a blank Start Event into a Message Start Event, or morphing a Boundary Event into an Error Boundary Event).

### Supported Canvas Elements

| Category | BPMN 2.0 Element | Engine Execution Behavior |
| :--- | :--- | :--- |
| **Events** | `bpmn:StartEvent` (None / Message) | Entry point; Message Start Events bind to trigger aliases. |
| **Events** | `bpmn:EndEvent` | Terminates the active branch and cleans up its token. |
| **Events** | `bpmn:IntermediateCatchEvent` (Timer) | Suspends Fiber execution for an ISO 8601 duration. |
| **Events** | `bpmn:BoundaryEvent` (Error / Timer) | Attaches to tasks/sub-processes for exception or timeout routing. |
| **Tasks** | `bpmn:ServiceTask` | Dispatches a registered `Workflow\V2\Activity` class. |
| **Tasks** | `bpmn:UserTask` | Hibernates the Fiber until a human task payload is submitted. |
| **Activities** | `bpmn:CallActivity` | Invokes another active `ProcessDefinition` by `calledElement` key. |
| **Activities** | `bpmn:SubProcess` | Executes a scoped inline sub-diagram (`parent_element_id`). |
| **Gateways** | `bpmn:ExclusiveGateway` | Evaluates conditional sequence flows via Symfony ExpressionLanguage. |
| **Gateways** | `bpmn:ParallelGateway` | Forks concurrent child workflows (`all()`) and synchronizes at join. |
| **Layout** | `bpmn:Group`, `bpmn:Lane`, `bpmn:LaneSet` | Visual organization annotations ignored by runtime execution. |

---

## 3. Custom Element Templates

Rather than requiring users to type raw implementation keys or XML extension elements manually, the designer integrates `@bpmn-io/element-template-chooser` and `bpmn-js-element-templates`. Element Templates transform the right-hand properties panel into structured forms tailored to your domain activities.

### Built-in vs. Host Templates

When loading the designer (`ProcessDefinitionController::design()`), the engine automatically scans and merges JSON templates from two locations:
1. **Package Built-in Templates (`resources/bpmn/templates/`):** Ships `send-notification.json` (`saccharine.bpmn.SendNotification`) and `model-event-trigger.json` (`saccharine.bpmn.ModelEventTrigger`).
2. **Host Application Templates (`resources/bpmn/templates/*.json`):** Any JSON array of template definitions placed in your application's `resources/bpmn/templates/` directory is automatically loaded into the template chooser.

### Scaffolding a Template

Generate a starter Element Template JSON file using Artisan:

```bash
php artisan bpmn:make-template "Charge Credit Card" --key="charge_card"
```

This creates `resources/bpmn/templates/charge-credit-card.json`. You can expand its `properties` array to bind friendly form inputs to `camunda:class` and `camunda:inputParameter` XML elements:

```json
[
  {
    "name": "Charge Credit Card",
    "id": "com.your-app.tasks.ChargeCreditCard",
    "appliesTo": ["bpmn:ServiceTask"],
    "properties": [
      {
        "label": "Implementation Class",
        "type": "Hidden",
        "value": "charge_card",
        "binding": {
          "type": "property",
          "name": "camunda:class"
        }
      },
      {
        "label": "Customer Email",
        "type": "String",
        "value": "@{{ customer_email }}",
        "description": "Supports process payload variables like @{{ customer_email }}.",
        "binding": {
          "type": "camunda:inputParameter",
          "name": "customer_email"
        },
        "constraints": {
          "notEmpty": true
        }
      },
      {
        "label": "Currency",
        "type": "Dropdown",
        "value": "USD",
        "choices": [
          { "name": "US Dollar (USD)", "value": "USD" },
          { "name": "Canadian Dollar (CAD)", "value": "CAD" },
          { "name": "Euro (EUR)", "value": "EUR" }
        ],
        "binding": {
          "type": "camunda:inputParameter",
          "name": "currency"
        }
      }
    ]
  }
]
```

> **Note:** In standard JSON template files, use standard double braces (`{{ customer_email }}`). The `@{{ ... }}` prefix shown above is only needed if embedding examples inside Blade/Jigsaw templates.

### How Template Inputs Flow at Runtime

When a diagram using an Element Template is saved:
1. `bpmn-js` serializes the hidden `camunda:class` attribute and `<bpmn:extensionElements><camunda:inputOutput><camunda:inputParameter>` tags into the BPMN 2.0 XML.
2. `BpmnParserService` extracts all `camunda:inputParameter` key-value pairs into the `input_parameters` JSON column on `process_nodes`.
3. At runtime, `ServiceTaskHandler` merges `$node->input_parameters` into `$userData` before invoking your Activity's `handle(array $userData)` method.