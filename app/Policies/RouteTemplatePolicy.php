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
        return $user->hasAnyRole($this->allowedInternalRoles());
    }

    public function view(User $user, RouteTemplate $routeTemplate): bool
    {
        return $user->hasAnyRole($this->allowedInternalRoles());
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole($this->allowedInternalRoles());
    }

    public function update(User $user, RouteTemplate $routeTemplate): bool
    {
        return $user->hasAnyRole($this->allowedInternalRoles());
    }

    public function delete(User $user, RouteTemplate $routeTemplate): bool
    {
        return $user->hasAnyRole($this->allowedInternalRoles());
    }
}
