<?php

namespace Saccharine\Bpmn\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Saccharine\Bpmn\Contracts\BpmnTriggerableEvent;

class ModelEventTrigger implements BpmnTriggerableEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Model $model,
        public string $action, // 'created', 'updated', 'deleted'
        public array $dirtyAttributes = []
    ) {}

    /**
     * Unique key to enforce idempotency per action/state.
     * e.g., 'App_Models_Order_1042_created'
     */
    public function getBusinessKey(): string
    {
        $classKey = str_replace('\\', '_', get_class($this->model));
        $id = $this->model->getKey();
        $updatedAt = optional($this->model->updated_at)->timestamp ?? time();

        // For 'created', key is unique to creation. For 'updated', keyed to the timestamp.
        if ($this->action === 'created') {
            return "{$classKey}_{$id}_created";
        }

        return "{$classKey}_{$id}_{$this->action}_{$updatedAt}";
    }

    /**
     * Normalizes the model's attributes into the workflow's initial $userData payload.
     */
    public function getWorkflowPayload(): array
    {
        $modelData = $this->model->toArray();

        return array_merge($modelData, [
            '_model_class' => get_class($this->model),
            '_model_id'    => $this->model->getKey(),
            '_action'      => $this->action,
            '_dirty'       => $this->dirtyAttributes,
        ]);
    }
}