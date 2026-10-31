<?php

declare(strict_types=1);

namespace Saccharine\Bpmn\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use Saccharine\Bpmn\Models\ProcessInstance;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProcessInstancePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ProcessInstance');
    }

    public function view(AuthUser $authUser, ProcessInstance $ProcessInstance): bool
    {
        return $authUser->can('View:ProcessInstance');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ProcessInstance');
    }

    public function update(AuthUser $authUser, ProcessInstance $ProcessInstance): bool
    {
        return $authUser->can('Update:ProcessInstance');
    }

    public function delete(AuthUser $authUser, ProcessInstance $ProcessInstance): bool
    {
        return $authUser->can('Delete:ProcessInstance');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:ProcessInstance');
    }

    public function restore(AuthUser $authUser, ProcessInstance $ProcessInstance): bool
    {
        return $authUser->can('Restore:ProcessInstance');
    }

    public function forceDelete(AuthUser $authUser, ProcessInstance $ProcessInstance): bool
    {
        return $authUser->can('ForceDelete:ProcessInstance');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ProcessInstance');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ProcessInstance');
    }

    public function replicate(AuthUser $authUser, ProcessInstance $ProcessInstance): bool
    {
        return $authUser->can('Replicate:ProcessInstance');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ProcessInstance');
    }

}