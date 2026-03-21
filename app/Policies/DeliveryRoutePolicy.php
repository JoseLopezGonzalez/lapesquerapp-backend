<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\DeliveryRoute;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class DeliveryRoutePolicy
{
    use HandlesAuthorization;

    protected function allowedInternalRoles(): array
    {
        return [
            Role::Tecnico->value,
            Role::Administrador->value,
            Role::Direccion->value,
            Role::Administracion->value,
            Role::Comercial->value,
            Role::Operario->value,
        ];
    }

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole($this->allowedInternalRoles());
    }

    public function view(User $user, DeliveryRoute $route): bool
    {
        return $user->hasAnyRole($this->allowedInternalRoles());
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole($this->allowedInternalRoles());
    }

    public function update(User $user, DeliveryRoute $route): bool
    {
        return $user->hasAnyRole($this->allowedInternalRoles());
    }

    public function delete(User $user, DeliveryRoute $route): bool
    {
        return $user->hasAnyRole($this->allowedInternalRoles());
    }

    public function viewAssigned(User $user, DeliveryRoute $route): bool
    {
        return $user->hasRole(Role::RepartidorAutoventa->value)
            && $user->fieldOperator !== null
            && $route->field_operator_id === $user->fieldOperator->id;
    }

    public function updateAssignedStop(User $user, DeliveryRoute $route): bool
    {
        return $this->viewAssigned($user, $route);
    }
}
