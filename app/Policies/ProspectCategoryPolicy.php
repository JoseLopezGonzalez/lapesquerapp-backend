<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\ProspectCategory;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProspectCategoryPolicy
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
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function view(User $user, ProspectCategory $prospectCategory): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function viewOptions(User $user): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function update(User $user, ProspectCategory $prospectCategory): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }

    public function delete(User $user, ProspectCategory $prospectCategory): bool
    {
        return $user->hasAnyRole($this->allowedRoles());
    }
}
