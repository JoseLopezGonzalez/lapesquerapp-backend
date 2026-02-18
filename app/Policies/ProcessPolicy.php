<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Process;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProcessPolicy
{
    use HandlesAuthorization;

    protected function allowedRoles(): array
    {
        return Role::values();
    }

    protected function rolesCanDelete(): array
    {
        return [
            Role::Administrador->value,
            Role::Tecnico->value,
        ];
    }

    public function viewAny(User $user): bool
    {
        if ($user->hasRole(Role::Comercial->value)) {
            return false;
        }
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function view(User $user, Process $process): bool
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

    public function update(User $user, Process $process): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function delete(User $user, Process $process): bool
    {
        return $user->hasAnyRole($this->rolesCanDelete());
    }
}
