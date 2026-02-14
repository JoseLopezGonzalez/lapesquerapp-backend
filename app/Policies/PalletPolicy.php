<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Pallet;
use App\Models\User;

class PalletPolicy
{
    protected function allowedRoles(): array
    {
        return Role::values();
    }

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function view(User $user, Pallet $pallet): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function update(User $user, Pallet $pallet): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function delete(User $user, Pallet $pallet): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }
}
