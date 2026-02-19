<?php

namespace App\Casts;

use Carbon\Carbon;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Cast for datetime columns stored in UTC.
 * On get: parses the raw value as UTC. On set: converts to UTC and stores as Y-m-d H:i:s.
 */
class DateTimeUtcCast implements CastsAttributes
{
    /**
     * Cast the given value from storage (UTC string) to Carbon in UTC.
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Carbon
    {
        if ($value === null) {
            return null;
        }

        return Carbon::parse($value, 'UTC');
    }

    /**
     * Prepare the given value for storage: convert to UTC and format as Y-m-d H:i:s.
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        $carbon = $value instanceof Carbon
            ? $value->copy()->utc()
            : Carbon::parse($value)->utc();

        return $carbon->format('Y-m-d H:i:s');
    }
}
