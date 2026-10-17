<?php

namespace Saccharine\Bpmn\Traits;

use Saccharine\Bpmn\Events\ModelEventTrigger;

trait TriggersBpmnWorkflows
{
    public static function bootTriggersBpmnWorkflows(): void
    {
        static::created(function ($model) {
            ModelEventTrigger::dispatch($model, 'created');
        });

        static::updated(function ($model) {
            ModelEventTrigger::dispatch($model, 'updated', $model->getChanges());
        });

        static::deleted(function ($model) {
            ModelEventTrigger::dispatch($model, 'deleted');
        });
    }
}