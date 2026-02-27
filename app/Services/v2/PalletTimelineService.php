<?php

namespace App\Services\v2;

use App\Models\Pallet;
use Illuminate\Support\Facades\DB;

class PalletTimelineService
{
    /**
     * Añade una entrada al timeline del palet.
     * Actualiza la columna JSON sin disparar eventos del modelo.
     *
     * @param  array<string, mixed>  $details  Datos específicos del tipo de evento
     */
    public static function record(Pallet $pallet, string $type, string $action, array $details = []): void
    {
        $user = auth()->user();
        $entry = [
            'timestamp' => now()->toISOString(),
            'userId' => $user?->id,
            'userName' => $user?->name ?? 'Sistema',
            'type' => $type,
            'action' => $action,
            'details' => $details,
        ];

        $timeline = $pallet->timeline ?? [];
        $timeline[] = $entry;

        $connection = $pallet->getConnectionName() ?? config('database.default');
        DB::connection($connection)->table('pallets')
            ->where('id', $pallet->id)
            ->update(['timeline' => json_encode($timeline)]);

        $pallet->setAttribute('timeline', $timeline);
    }

    /**
     * Construye el texto de action (título breve) para un evento pallet_updated.
     * El detalle completo está en $details; el frontend puede expandir o mostrar por separado.
     *
     * @param  array<string, mixed>  $details
     */
    public static function buildPalletUpdatedAction(array $details, bool $fromReception = false): string
    {
        return $fromReception ? 'Palet actualizado (desde recepción)' : 'Palet actualizado';
    }

    /**
     * Borra todo el historial (timeline) del palet. Solo actualiza la columna JSON.
     */
    public static function clear(Pallet $pallet): void
    {
        $connection = $pallet->getConnectionName() ?? config('database.default');
        DB::connection($connection)->table('pallets')
            ->where('id', $pallet->id)
            ->update(['timeline' => json_encode([])]);

        $pallet->setAttribute('timeline', []);
    }
}
