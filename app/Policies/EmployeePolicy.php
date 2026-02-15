<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class EmployeePolicy
{
    use HandlesAuthorization;

    protected function allowedRoles(): array
    {
        return Role::values();
    }

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function view(User $user, Employee $employee): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function update(User $user, Employee $employee): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function delete(User $user, Employee $employee): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }
}
