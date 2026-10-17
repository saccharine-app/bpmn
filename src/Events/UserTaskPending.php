<?php

namespace Saccharine\Bpmn\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserTaskPending
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $workflowId,
        public ?string $taskName,
        public array $userData
    ) {}
}