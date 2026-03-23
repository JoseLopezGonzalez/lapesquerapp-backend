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
        return $this->isPrivilegedInternalUser($user)
            || $this->isCommercialUser($user);
    }

    public function view(User $user, DeliveryRoute $route): bool
    {
        return $this->isPrivilegedInternalUser($user)
            || $this->ownsCommercialRoute($user, $route);
    }

    public function create(User $user): bool
    {
        return $this->isPrivilegedInternalUser($user)
            || $this->isCommercialUser($user);
    }

    public function update(User $user, DeliveryRoute $route): bool
    {
        return $this->isPrivilegedInternalUser($user)
            || $this->ownsCommercialRoute($user, $route);
    }

    public function delete(User $user, DeliveryRoute $route): bool
    {
        return $this->isPrivilegedInternalUser($user)
            || $this->ownsCommercialRoute($user, $route);
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

    private function isPrivilegedInternalUser(User $user): bool
    {
        return $user->hasAnyRole(array_values(array_filter(
            $this->allowedInternalRoles(),
            fn (string $role) => $role !== Role::Comercial->value
        )));
    }

    private function isCommercialUser(User $user): bool
    {
        return $user->hasRole(Role::Comercial->value)
            && $user->salesperson !== null;
    }

    private function ownsCommercialRoute(User $user, DeliveryRoute $route): bool
    {
        if (! $this->isCommercialUser($user)) {
            return false;
        }

        return $route->salesperson_id === $user->salesperson->id
            || ($route->salesperson_id === null && $route->created_by_user_id === $user->id);
    }
}
