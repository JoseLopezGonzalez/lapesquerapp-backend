<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SupplierPolicy
{
    use HandlesAuthorization;

    protected function allowedRoles(): array
    {
        return Role::values();
    }

    public function viewAny(User $user): bool
    {
        if ($user->hasRole(Role::Comercial->value)) {
            return false;
        }
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function view(User $user, Supplier $supplier): bool
    {
        if ($user->hasRole(Role::Comercial->value)) {
            return false;
        }
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function viewOptions(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function delete(User $user, Supplier $supplier): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function restore(User $user, Supplier $supplier): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function forceDelete(User $user, Supplier $supplier): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }
}
