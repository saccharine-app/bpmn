<?php

namespace Saccharine\Bpmn\Enums;

enum ProcessInstanceStatus: string
{
    case RUNNING = 'running';
    case SUSPENDED = 'suspended';
    case HALTED = 'halted';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
}