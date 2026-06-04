<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Attachment;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class AttachmentPolicy
{
    use HandlesAuthorization;

    /** Roles que pueden borrar adjuntos. Confirmado en Fase 0: solo admin y técnico. */
    private function canDelete(User $user): bool
    {
        return $user->hasAnyRole([
            Role::Administrador->value,
            Role::Tecnico->value,
        ]);
    }

    /**
     * Listado de adjuntos de una entidad.
     * $attachable es el segundo argumento pasado con: authorize('viewAny', [Attachment::class, $attachable])
     */
    public function viewAny(User $user, Model $attachable): bool
    {
        return Gate::forUser($user)->allows('view', $attachable);
    }

    /** Ver metadatos de un adjunto concreto. */
    public function view(User $user, Attachment $attachment): bool
    {
        return Gate::forUser($user)->allows('view', $attachment->attachable);
    }

    /**
     * Subir un adjunto a una entidad.
     * $attachable es el segundo argumento pasado con: authorize('create', [Attachment::class, $attachable])
     */
    public function create(User $user, Model $attachable): bool
    {
        return Gate::forUser($user)->allows('update', $attachable);
    }

    /** Editar notes/metadata de un adjunto existente. */
    public function update(User $user, Attachment $attachment): bool
    {
        return Gate::forUser($user)->allows('update', $attachment->attachable);
    }

    /** Borrar un adjunto. Solo admin y técnico. */
    public function delete(User $user, Attachment $attachment): bool
    {
        return $this->canDelete($user);
    }

    /** Descargar el archivo físico de un adjunto. */
    public function download(User $user, Attachment $attachment): bool
    {
        return Gate::forUser($user)->allows('view', $attachment->attachable);
    }
}
