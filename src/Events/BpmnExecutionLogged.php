<?php

namespace Saccharine\Bpmn\Events;

use Illuminate\Foundation\Events\Dispatchable;

class BpmnExecutionLogged
{
    use Dispatchable;

    public const LEVEL_INFO     = 'info';      // White / Gray
    public const LEVEL_GATEWAY  = 'gateway';   // Yellow / Amber
    public const LEVEL_TASK     = 'task';      // Cyan / Blue
    public const LEVEL_WAIT     = 'wait';      // Magenta / Purple
    public const LEVEL_SUCCESS  = 'success';   // Bright Green
    public const LEVEL_ERROR    = 'error';     // Red

    public function __construct(
        public string $type,
        public string $message,
        public ?int $instanceId = null,
        public ?string $nodeId = null,
        public array $context = []
    ) {}
}