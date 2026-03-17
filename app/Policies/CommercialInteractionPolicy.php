<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\CommercialInteraction;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CommercialInteractionPolicy
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

    public function view(User $user, CommercialInteraction $interaction): bool
    {
        if ($user->hasRole(Role::Comercial->value)) {
            return $user->salesperson && $interaction->salesperson_id === $user->salesperson->id;
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
}
