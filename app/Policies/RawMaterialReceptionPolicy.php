<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\RawMaterialReception;
use App\Models\User;

class RawMaterialReceptionPolicy
{
    protected function allowedRoles(): array
    {
        return Role::values();
    }

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function view(User $user, RawMaterialReception $rawMaterialReception): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function update(User $user, RawMaterialReception $rawMaterialReception): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function delete(User $user, RawMaterialReception $rawMaterialReception): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }
}
