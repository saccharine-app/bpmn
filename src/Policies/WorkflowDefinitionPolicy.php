<?php

declare(strict_types=1);

namespace Saccharine\Bpmn\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use Saccharine\Bpmn\Models\WorkflowDefinition;
use Illuminate\Auth\Access\HandlesAuthorization;

class WorkflowDefinitionPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:WorkflowDefinition');
    }

    public function view(AuthUser $authUser, WorkflowDefinition $workflowDefinition): bool
    {
        return $authUser->can('View:WorkflowDefinition');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:WorkflowDefinition');
    }

    public function update(AuthUser $authUser, WorkflowDefinition $workflowDefinition): bool
    {
        return $authUser->can('Update:WorkflowDefinition');
    }

    public function delete(AuthUser $authUser, WorkflowDefinition $workflowDefinition): bool
    {
        return $authUser->can('Delete:WorkflowDefinition');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:WorkflowDefinition');
    }

    public function restore(AuthUser $authUser, WorkflowDefinition $workflowDefinition): bool
    {
        return $authUser->can('Restore:WorkflowDefinition');
    }

    public function forceDelete(AuthUser $authUser, WorkflowDefinition $workflowDefinition): bool
    {
        return $authUser->can('ForceDelete:WorkflowDefinition');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:WorkflowDefinition');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:WorkflowDefinition');
    }

    public function replicate(AuthUser $authUser, WorkflowDefinition $workflowDefinition): bool
    {
        return $authUser->can('Replicate:WorkflowDefinition');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:WorkflowDefinition');
    }

}