<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\ExternalProcessor;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ExternalProcessorPolicy
{
    use HandlesAuthorization;

    protected function managementRoles(): array
    {
        return [
            Role::Tecnico->value,
            Role::Administrador->value,
            Role::Direccion->value,
            Role::Administracion->value,
        ];
    }

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole($this->managementRoles());
    }

    public function view(User $user, ExternalProcessor $externalProcessor): bool
    {
        return $user->hasAnyRole($this->managementRoles());
    }

    public function viewOptions(User $user): bool
    {
        return $user->hasAnyRole($this->managementRoles());
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole($this->managementRoles());
    }

    public function update(User $user, ExternalProcessor $externalProcessor): bool
    {
        return $user->hasAnyRole($this->managementRoles());
    }

    public function delete(User $user, ExternalProcessor $externalProcessor): bool
    {
        return $user->hasAnyRole([
            Role::Tecnico->value,
            Role::Administrador->value,
            Role::Direccion->value,
        ]);
    }
}
