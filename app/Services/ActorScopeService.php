<?php

namespace App\Services;

use App\Models\ExternalUser;
use Illuminate\Database\Eloquent\Builder;

class ActorScopeService
{
    public function isExternal(mixed $actor): bool
    {
        return $actor instanceof ExternalUser;
    }

    public function allowedStoreIds(mixed $actor): array
    {
        if (! $actor instanceof ExternalUser) {
            return [];
        }

        return $actor->allowedStoreIds();
    }

    public function canAccessStoreId(mixed $actor, ?int $storeId): bool
    {
        if (! $this->isExternal($actor)) {
            return true;
        }

        if ($storeId === null) {
            return false;
        }

        return in_array($storeId, $this->allowedStoreIds($actor), true);
    }

    public function scopeStores(Builder $query, mixed $actor): Builder
    {
        if (! $this->isExternal($actor)) {
            return $query;
        }

        return $query->whereIn('id', $this->allowedStoreIds($actor));
    }

    public function scopePallets(Builder $query, mixed $actor): Builder
    {
        if (! $this->isExternal($actor)) {
            return $query;
        }

        return $query->whereHas('storedPallet', function (Builder $builder) use ($actor) {
            $builder->whereIn('store_id', $this->allowedStoreIds($actor));
        });
    }
}
