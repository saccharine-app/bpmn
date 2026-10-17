<?php

declare(strict_types=1);

namespace Saccharine\Bpmn\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use Saccharine\Bpmn\Models\DemoOrder;
use Illuminate\Auth\Access\HandlesAuthorization;

class DemoOrderPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DemoOrder');
    }

    public function view(AuthUser $authUser, DemoOrder $demoOrder): bool
    {
        return $authUser->can('View:DemoOrder');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DemoOrder');
    }

    public function update(AuthUser $authUser, DemoOrder $demoOrder): bool
    {
        return $authUser->can('Update:DemoOrder');
    }

    public function delete(AuthUser $authUser, DemoOrder $demoOrder): bool
    {
        return $authUser->can('Delete:DemoOrder');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:DemoOrder');
    }

    public function restore(AuthUser $authUser, DemoOrder $demoOrder): bool
    {
        return $authUser->can('Restore:DemoOrder');
    }

    public function forceDelete(AuthUser $authUser, DemoOrder $demoOrder): bool
    {
        return $authUser->can('ForceDelete:DemoOrder');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:DemoOrder');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:DemoOrder');
    }

    public function replicate(AuthUser $authUser, DemoOrder $demoOrder): bool
    {
        return $authUser->can('Replicate:DemoOrder');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:DemoOrder');
    }

}