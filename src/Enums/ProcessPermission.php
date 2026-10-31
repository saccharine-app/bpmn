<?php

namespace Saccharine\Bpmn\Enums;

enum ProcessPermission: string
{
    case VIEW = 'Bpmn:View';
    case EDIT = 'Bpmn:Edit';
    case DELETE = 'Bpmn:Delete';
    case SUSPEND_INSTANCE = 'Bpmn:SuspendInstance';
    case RESUME_INSTANCE = 'Bpmn:ResumeInstance';
    case HALT_INSTANCE = 'Bpmn:HaltInstance';

    public function label(): string
    {
        return match ($this) {
            self::VIEW => 'View Processes',
            self::EDIT => 'Edit Processes',
            self::DELETE => 'Delete Processes',
            self::SUSPEND_INSTANCE => 'Suspend Process Instances',
            self::RESUME_INSTANCE => 'Resume Process Instances',
            self::HALT_INSTANCE => 'Halt Process Instances',
        };
    }
}