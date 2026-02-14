<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Salesperson;
use App\Models\User;

class SalespersonPolicy
{
    protected function allowedRoles(): array
    {
        return Role::values();
    }

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function view(User $user, Salesperson $salesperson): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function update(User $user, Salesperson $salesperson): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function delete(User $user, Salesperson $salesperson): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function restore(User $user, Salesperson $salesperson): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function forceDelete(User $user, Salesperson $salesperson): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }
}
