<?php

namespace App\Services;

use App\Models\ExternalUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuthActorService
{
    public function resolveByEmail(string $email): User|ExternalUser|null
    {
        $user = User::where('email', $email)->first();
        if ($user) {
            return $user;
        }

        return ExternalUser::where('email', $email)->first();
    }

    public function actorType(User|ExternalUser $actor): string
    {
        return $actor instanceof ExternalUser ? 'external_user' : 'internal_user';
    }

    public function allowedStoreIds(User|ExternalUser $actor): array
    {
        if ($actor instanceof ExternalUser) {
            return $actor->allowedStoreIds();
        }

        return [];
    }

    public function revokeTokens(User|ExternalUser $actor): void
    {
        $actor->tokens()->delete();
    }

    public function emailExistsOnOtherActor(string $email, string $actorClass): bool
    {
        $otherClass = $actorClass === User::class ? ExternalUser::class : User::class;
        $query = $otherClass::query()->where('email', $email);

        if ($otherClass === User::class) {
            $query->whereNull('deleted_at');
        }

        return $query->exists();
    }

    public function isExternalUser(?Model $actor): bool
    {
        return $actor instanceof ExternalUser;
    }

    public function isActive(User|ExternalUser|null $actor): bool
    {
        if ($actor instanceof ExternalUser) {
            return $actor->is_active;
        }

        return $actor?->active ?? false;
    }
}
