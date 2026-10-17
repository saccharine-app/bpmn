<?php

namespace Saccharine\Bpmn\Contracts;

interface BpmnTriggerableEvent
{
    /**
     * A unique identifier to prevent the workflow from running twice 
     * for the exact same domain event (e.g., 'custody_log_9876').
     */
    public function getBusinessKey(): string;

    /**
     * The initial payload data that will be passed into the engine.
     */
    public function getWorkflowPayload(): array;
}