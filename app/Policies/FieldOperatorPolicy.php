<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\FieldOperator;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class FieldOperatorPolicy
{
    use HandlesAuthorization;

    protected function managementRoles(): array
    {
        return [
            Role::Administrador->value,
            Role::Tecnico->value,
            Role::Direccion->value,
            Role::Administracion->value,
        ];
    }

    protected function viewerRoles(): array
    {
        return [
            ...$this->managementRoles(),
            Role::Comercial->value,
            Role::Operario->value,
        ];
    }

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole($this->viewerRoles());
    }

    public function view(User $user, FieldOperator $fieldOperator): bool
    {
        return $user->hasAnyRole($this->viewerRoles());
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole($this->managementRoles());
    }

    public function update(User $user, FieldOperator $fieldOperator): bool
    {
        return $user->hasAnyRole($this->managementRoles());
    }

    public function delete(User $user, FieldOperator $fieldOperator): bool
    {
        return $user->hasAnyRole([
            Role::Administrador->value,
            Role::Tecnico->value,
        ]);
    }
}
