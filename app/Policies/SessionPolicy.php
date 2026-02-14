<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;
use App\Sanctum\PersonalAccessToken;
use Illuminate\Auth\Access\HandlesAuthorization;

class SessionPolicy
{
    use HandlesAuthorization;

    /**
     * Roles que pueden listar sesiones (administración / soporte).
     */
    protected function rolesCanViewAny(): array
    {
        return [
            Role::Administrador->value,
            Role::Tecnico->value,
            Role::Direccion->value,
        ];
    }

    /**
     * Roles que pueden revocar cualquier sesión (no solo la propia).
     */
    protected function rolesCanDeleteAny(): array
    {
        return [
            Role::Administrador->value,
            Role::Tecnico->value,
        ];
    }

    /**
     * Determine if the user can list sessions.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole($this->rolesCanViewAny());
    }

    /**
     * Determine if the user can delete (revoke) the session/token.
     * User can always revoke their own token; only admin/tecnico can revoke others'.
     */
    public function delete(User $user, PersonalAccessToken $token): bool
    {
        if ($token->tokenable_id === (int) $user->id && $token->tokenable_type === User::class) {
            return true;
        }
        return $user->hasAnyRole($this->rolesCanDeleteAny());
    }
}
