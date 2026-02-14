<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\CeboDispatch;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CeboDispatchPolicy
{
    use HandlesAuthorization;

    protected function allowedRoles(): array
    {
        return Role::values();
    }

    /**
     * Determine if the user can view any cebo dispatches.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    /**
     * Determine if the user can view the cebo dispatch.
     */
    public function view(User $user, CeboDispatch $ceboDispatch): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    /**
     * Determine if the user can create cebo dispatches.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    /**
     * Determine if the user can update the cebo dispatch.
     */
    public function update(User $user, CeboDispatch $ceboDispatch): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    /**
     * Determine if the user can delete the cebo dispatch.
     */
    public function delete(User $user, CeboDispatch $ceboDispatch): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }
}
