<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\RawMaterialReception;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class RawMaterialReceptionPolicy
{
    use HandlesAuthorization;

    protected function allowedRoles(): array
    {
        return Role::values();
    }

    /**
     * Determine if the user can view any raw material receptions.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    /**
     * Determine if the user can view the raw material reception.
     */
    public function view(User $user, RawMaterialReception $rawMaterialReception): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    /**
     * Determine if the user can create raw material receptions.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    /**
     * Determine if the user can update the raw material reception.
     */
    public function update(User $user, RawMaterialReception $rawMaterialReception): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    /**
     * Determine if the user can delete the raw material reception.
     */
    public function delete(User $user, RawMaterialReception $rawMaterialReception): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }
}
