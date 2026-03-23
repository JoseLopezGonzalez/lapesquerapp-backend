<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\RouteTemplate;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class RouteTemplatePolicy
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

    public function view(User $user, RouteTemplate $routeTemplate): bool
    {
        return $this->isPrivilegedInternalUser($user)
            || $this->ownsCommercialTemplate($user, $routeTemplate);
    }

    public function create(User $user): bool
    {
        return $this->isPrivilegedInternalUser($user)
            || $this->isCommercialUser($user);
    }

    public function update(User $user, RouteTemplate $routeTemplate): bool
    {
        return $this->isPrivilegedInternalUser($user)
            || $this->ownsCommercialTemplate($user, $routeTemplate);
    }

    public function delete(User $user, RouteTemplate $routeTemplate): bool
    {
        return $this->isPrivilegedInternalUser($user)
            || $this->ownsCommercialTemplate($user, $routeTemplate);
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

    private function ownsCommercialTemplate(User $user, RouteTemplate $routeTemplate): bool
    {
        if (! $this->isCommercialUser($user)) {
            return false;
        }

        return $routeTemplate->salesperson_id === $user->salesperson->id
            || ($routeTemplate->salesperson_id === null && $routeTemplate->created_by_user_id === $user->id);
    }
}
