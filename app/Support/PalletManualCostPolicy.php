<?php

namespace App\Support;

use App\Enums\Role;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Visibilidad y edición de costes manuales / contexto de coste en palets y cajas (API v2).
 */
final class PalletManualCostPolicy
{
    public static function authorized(?Authenticatable $actor): bool
    {
        if ($actor === null || ! method_exists($actor, 'hasAnyRole')) {
            return false;
        }

        return $actor->hasAnyRole([
            Role::Administrador->value,
            Role::Tecnico->value,
        ]);
    }

    /**
     * @param  array<string, mixed>  $pallet
     * @return array<string, mixed>
     */
    public static function stripFromPalletAssocArray(array $pallet): array
    {
        unset($pallet['costPerKg'], $pallet['totalCost']);

        if (! isset($pallet['boxes']) || ! is_array($pallet['boxes'])) {
            return $pallet;
        }

        $pallet['boxes'] = array_values(array_map(static function ($box) {
            if (! is_array($box)) {
                return $box;
            }
            foreach (['manualCostPerKg', 'traceableCostPerKg', 'costPerKg', 'totalCost'] as $key) {
                unset($box[$key]);
            }

            return $box;
        }, $pallet['boxes']));

        return $pallet;
    }
}
