<?php

namespace App\Services\v2;

use App\Models\RawMaterialReception;
use Carbon\Carbon;

class RawMaterialReceptionBulkService
{
    /**
     * Validar actualización de datos declarados de múltiples recepciones (sin persistir).
     * Devuelve la misma estructura que el endpoint validate-bulk-update-declared-data.
     *
     * @param  array<int, array{supplier_id: int, date: string, declared_total_amount?: float|null, declared_total_net_weight?: float|null}>  $receptions
     * @return array{valid: int, invalid: int, ready_to_update: int, results: array, errors_details?: array}
     */
    public static function validateBulkUpdateDeclaredData(array $receptions): array
    {
        $results = [];
        $errors = [];

        foreach ($receptions as $receptionData) {
            $supplierId = (int) $receptionData['supplier_id'];
            $date = $receptionData['date'];
            $newDeclaredAmount = $receptionData['declared_total_amount'] ?? null;
            $newDeclaredWeight = $receptionData['declared_total_net_weight'] ?? null;

            try {
                $reception = RawMaterialReception::where('supplier_id', $supplierId)
                    ->whereDate('date', $date)
                    ->with('supplier')
                    ->first();

                if (! $reception) {
                    $closestReceptions = self::findClosestReceptions($supplierId, $date);

                    $errorDetails = [
                        'supplier_id' => $supplierId,
                        'date' => $date,
                        'valid' => false,
                        'error' => 'No existe recepción para este proveedor y fecha',
                        'message' => 'No existe recepción para este proveedor y fecha',
                    ];

                    if ($closestReceptions['closest']) {
                        $closest = $closestReceptions['closest'];
                        $direction = $closest['type'] === 'previous' ? 'anterior' : 'posterior';
                        $errorDetails['hint'] = "Recepción más cercana ({$direction}): {$closest['date']} (ID: {$closest['id']}) diferencia: {$closest['days_diff']}";
                    } else {
                        $errorDetails['hint'] = 'No existen recepciones para este proveedor';
                    }

                    $errors[] = $errorDetails;
                    continue;
                }

                $currentDeclaredAmount = $reception->declared_total_amount;
                $currentDeclaredWeight = $reception->declared_total_net_weight;

                $hasChanges = ($newDeclaredAmount !== null && $currentDeclaredAmount != $newDeclaredAmount) ||
                    ($newDeclaredWeight !== null && $currentDeclaredWeight != $newDeclaredWeight);

                $results[] = [
                    'supplier_id' => $supplierId,
                    'date' => $date,
                    'valid' => true,
                    'can_update' => true,
                    'has_changes' => $hasChanges,
                    'message' => $hasChanges
                        ? 'Recepción válida y lista para actualizar'
                        : 'Recepción válida pero sin cambios',
                    'supplier_name' => $reception->supplier->name ?? null,
                ];
            } catch (\Exception $e) {
                $errors[] = [
                    'supplier_id' => $supplierId,
                    'date' => $date,
                    'error' => 'Error al validar la recepción',
                    'message' => 'Error al validar la recepción',
                ];
            }
        }

        $response = [
            'valid' => count($results),
            'invalid' => count($errors),
            'ready_to_update' => count(array_filter($results, fn ($r) => $r['valid'] && $r['has_changes'])),
            'results' => $results,
        ];

        if (! empty($errors)) {
            $response['errors_details'] = $errors;
        }

        return $response;
    }

    /**
     * Actualizar datos declarados de múltiples recepciones.
     * Devuelve la misma estructura que el endpoint bulk-update-declared-data.
     *
     * @param  array<int, array{supplier_id: int, date: string, declared_total_amount?: float|null, declared_total_net_weight?: float|null}>  $receptions
     * @return array{updated: int, errors: int, errors_details?: array}
     */
    public static function bulkUpdateDeclaredData(array $receptions): array
    {
        $updated = 0;
        $errors = [];

        foreach ($receptions as $receptionData) {
            $supplierId = (int) $receptionData['supplier_id'];
            $date = $receptionData['date'];

            try {
                $reception = RawMaterialReception::where('supplier_id', $supplierId)
                    ->whereDate('date', $date)
                    ->first();

                if (! $reception) {
                    $closestReceptions = self::findClosestReceptions($supplierId, $date);

                    $errorDetails = [
                        'supplier_id' => $supplierId,
                        'date' => $date,
                        'error' => 'No existe recepción para este proveedor y fecha',
                        'message' => 'No existe recepción para este proveedor y fecha',
                    ];

                    if ($closestReceptions['closest']) {
                        $closest = $closestReceptions['closest'];
                        $direction = $closest['type'] === 'previous' ? 'anterior' : 'posterior';
                        $errorDetails['hint'] = "Recepción más cercana ({$direction}): {$closest['date']} (ID: {$closest['id']}) diferencia: {$closest['days_diff']}";
                    } else {
                        $errorDetails['hint'] = 'No existen recepciones para este proveedor';
                    }

                    $errors[] = $errorDetails;
                    continue;
                }

                $amount = $receptionData['declared_total_amount']
                    ?? $receptionData['declaredTotalAmount']
                    ?? $reception->declared_total_amount;
                $weight = $receptionData['declared_total_net_weight']
                    ?? $receptionData['declaredTotalNetWeight']
                    ?? $reception->declared_total_net_weight;

                $reception->update([
                    'declared_total_amount' => $amount,
                    'declared_total_net_weight' => $weight,
                ]);

                $updated++;
            } catch (\Exception $e) {
                $errors[] = [
                    'supplier_id' => $supplierId,
                    'date' => $date,
                    'error' => 'Error al actualizar la recepción',
                    'message' => 'Error al actualizar la recepción',
                ];
            }
        }

        $response = [
            'updated' => $updated,
            'errors' => count($errors),
        ];

        if (! empty($errors)) {
            $response['errors_details'] = $errors;
        }

        return $response;
    }

    /**
     * Buscar las recepciones más cercanas (anterior y posterior) a una fecha para un proveedor.
     *
     * @return array{previous: array{id: int, date: string, days_diff: int}|null, next: array{id: int, date: string, days_diff: int}|null, closest: array{id: int, date: string, type: string, days_diff: int}|null}
     */
    public static function findClosestReceptions(int $supplierId, string $date): array
    {
        $searchDate = Carbon::parse($date);

        $previousReception = RawMaterialReception::where('supplier_id', $supplierId)
            ->whereDate('date', '<=', $searchDate)
            ->orderBy('date', 'desc')
            ->first();

        $nextReception = RawMaterialReception::where('supplier_id', $supplierId)
            ->whereDate('date', '>=', $searchDate)
            ->orderBy('date', 'asc')
            ->first();

        $closestReception = null;
        $closestType = null;

        if ($previousReception && $nextReception) {
            $prevDiff = $searchDate->diffInDays(Carbon::parse($previousReception->date));
            $nextDiff = $searchDate->diffInDays(Carbon::parse($nextReception->date));

            if ($prevDiff <= $nextDiff) {
                $closestReception = $previousReception;
                $closestType = 'previous';
            } else {
                $closestReception = $nextReception;
                $closestType = 'next';
            }
        } elseif ($previousReception) {
            $closestReception = $previousReception;
            $closestType = 'previous';
        } elseif ($nextReception) {
            $closestReception = $nextReception;
            $closestType = 'next';
        }

        return [
            'previous' => $previousReception ? [
                'id' => $previousReception->id,
                'date' => Carbon::parse($previousReception->date)->format('Y-m-d'),
                'days_diff' => $searchDate->diffInDays(Carbon::parse($previousReception->date)),
            ] : null,
            'next' => $nextReception ? [
                'id' => $nextReception->id,
                'date' => Carbon::parse($nextReception->date)->format('Y-m-d'),
                'days_diff' => $searchDate->diffInDays(Carbon::parse($nextReception->date)),
            ] : null,
            'closest' => $closestReception ? [
                'id' => $closestReception->id,
                'date' => Carbon::parse($closestReception->date)->format('Y-m-d'),
                'type' => $closestType,
                'days_diff' => $searchDate->diffInDays(Carbon::parse($closestReception->date)),
            ] : null,
        ];
    }
}
