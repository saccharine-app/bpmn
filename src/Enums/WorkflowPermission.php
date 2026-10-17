<?php

namespace Saccharine\Bpmn\Enums;

enum WorkflowPermission: string
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
            self::VIEW => 'View Workflows',
            self::EDIT => 'Edit Workflows',
            self::DELETE => 'Delete Workflows',
            self::SUSPEND_INSTANCE => 'Suspend Workflow Instances',
            self::RESUME_INSTANCE => 'Resume Workflow Instances',
            self::HALT_INSTANCE => 'Halt Workflow Instances',
        };
    }
}