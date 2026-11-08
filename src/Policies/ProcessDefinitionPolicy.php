<?php

declare(strict_types=1);

namespace Saccharine\Bpmn\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use Saccharine\Bpmn\Models\ProcessDefinition;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProcessDefinitionPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ProcessDefinition');
    }

    public function view(AuthUser $authUser, ProcessDefinition $ProcessDefinition): bool
    {
        return $authUser->can('View:ProcessDefinition');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ProcessDefinition');
    }

    public function update(AuthUser $authUser, ProcessDefinition $ProcessDefinition): bool
    {
        return $authUser->can('Update:ProcessDefinition');
    }

    public function delete(AuthUser $authUser, ProcessDefinition $ProcessDefinition): bool
    {
        return $authUser->can('Delete:ProcessDefinition');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:ProcessDefinition');
    }

    public function restore(AuthUser $authUser, ProcessDefinition $ProcessDefinition): bool
    {
        return $authUser->can('Restore:ProcessDefinition');
    }

    public function forceDelete(AuthUser $authUser, ProcessDefinition $ProcessDefinition): bool
    {
        return $authUser->can('ForceDelete:ProcessDefinition');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ProcessDefinition');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ProcessDefinition');
    }

    public function replicate(AuthUser $authUser, ProcessDefinition $ProcessDefinition): bool
    {
        return $authUser->can('Replicate:ProcessDefinition');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ProcessDefinition');
    }

}