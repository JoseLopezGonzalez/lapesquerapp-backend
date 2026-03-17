<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Prospect;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProspectPolicy
{
    use HandlesAuthorization;

    protected function allowedRoles(): array
    {
        return [
            Role::Tecnico->value,
            Role::Administrador->value,
            Role::Direccion->value,
            Role::Comercial->value,
        ];
    }

    public function viewAny(User $user): bool
    {
        if ($user->hasRole(Role::Comercial->value)) {
            return $user->salesperson !== null;
        }

        return $user->hasAnyRole($this->allowedRoles());
    }

    public function view(User $user, Prospect $prospect): bool
    {
        if ($user->hasRole(Role::Comercial->value)) {
            return $user->salesperson && $prospect->salesperson_id === $user->salesperson->id;
        }

        return $user->hasAnyRole($this->allowedRoles());
    }

    public function create(User $user): bool
    {
        if ($user->hasRole(Role::Comercial->value)) {
            return $user->salesperson !== null;
        }

        return $user->hasAnyRole($this->allowedRoles());
    }

    public function update(User $user, Prospect $prospect): bool
    {
        return $this->view($user, $prospect);
    }

    public function delete(User $user, Prospect $prospect): bool
    {
        return $this->view($user, $prospect);
    }
}
