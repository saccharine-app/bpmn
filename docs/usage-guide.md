# **Usage Guide**

This guide covers how to implement the Saccharine BPMN into your application, define your business logic, and utilize advanced orchestration features.

## **1. Configuration & Registration**

After running the install command, your configuration lives at config/saccharine-bpmn.php. This file is the bridge between your generic BPMN diagrams and your actual Laravel code.

It maps the string identifiers typed into the visual canvas to the fully qualified class names in your application.

```php
return [
    'activities' => [
        'send_welcome_email' => \App\Workflows\Activities\SendWelcomeEmailActivity::class,
    ],
    'triggers' => [
        'user_registered' => \App\Events\UserRegistered::class,
    ],
];
```

## **2. Defining Triggers & Activities**

The package provides Artisan commands to rapidly scaffold these implementation classes.

### **Triggers (Event-Driven Launching)**

To launch a process automatically when something happens in your app, create a Trigger:

```bash
php artisan bpmn:make-trigger
```

This generates a standard Laravel Event that implements the BpmnTriggerableEvent contract. It enforces a getBusinessKey() method to guarantee idempotency—meaning a specific event (like "Order #123 Placed") will never accidentally spawn duplicate workflow instances.

### **Activities (Background Service Tasks)**

To execute domain logic, create an Activity:

```bash
php artisan bpmn:make-activity
```

This generates a durable activity class. The execute(array $userData) method receives the workflow's current state, allowing you to interact with your application's database, call external APIs, and return mutated data back to the workflow token.

## **3. The Visual Designer & Element Templates**

Navigate to your app's route (e.g., /bpmn/workflows) to access the drag-and-drop modeler.
To prevent non-technical users from needing to memorize implementation keys, you can generate **Element Templates**:

```bash
php artisan bpmn:make-template "Send Welcome Email"
```

This creates a JSON file in your host application (resources/bpmn/templates/). When a user applies this template to a Service Task in the UI, it automatically wires up the hidden camunda:class property to route to your backend Activity.

## **4. Advanced Node Types**

The engine supports advanced BPMN 2.0 orchestration patterns natively.

### **Error Boundary Events**

Anticipate failures by attaching an Error Boundary Event directly to a Service Task on the canvas. If the associated Laravel Activity throws an \Exception, the engine catches it, skips the standard queue retry policies, and routes the token down the boundary's alternate sequence flow (e.g., to a "Send Failure Notice" task). The exception message is appended to the payload as _error_caught.

### **Inline Sub-Processes**

Group related tasks together using a <bpmn:subProcess>. When the engine enters a sub-process, it scopes the execution into a dedicated child workflow. This allows you to apply boundary events to an entire block of tasks at once.

### **Call Activities**

Keep your diagrams clean and modular by utilizing Call Activities.

> 1. Draw a Call Activity node.
> 2. In the properties panel, set the **Java Class** (implementation) to the **Unique Key** of another published workflow definition (e.g., billing-subroutine).
> 3. The engine will dynamically launch the active version of that target workflow as a child process, wait for it to finish, and merge its payload back into the master workflow.

## **5. Managing Instances**

You can view, suspend, resume, and halt running workflows via the included UI or the command line.

### Web Dashboard

Visit /bpmn/instances to see a real-time table of active instances and the exact Node IDs where tokens are currently resting.

### CLI Interventions

```bash
php artisan bpmn:instance list
```

```bash
php artisan bpmn:instance suspend {id}
```

```bash
php artisan bpmn:instance resume {id}
```

```bash
php artisan bpmn:instance halt {id}
```
