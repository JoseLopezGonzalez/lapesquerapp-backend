<?php

namespace App\Services\v2;

use App\Models\RawMaterialReception;
use Carbon\Carbon;

class RawMaterialReceptionStatisticsService
{
    /**
     * Obtiene datos de recepciones de materia prima agrupados por período (día, semana o mes)
     * con filtros opcionales por especie, familia o categoría.
     * 
     * @param string $dateFrom Fecha de inicio (formato: Y-m-d H:i:s)
     * @param string $dateTo Fecha de fin (formato: Y-m-d H:i:s)
     * @param string $valueType Tipo de valor: 'amount' o 'quantity'
     * @param string $groupBy Agrupación: 'day', 'week' o 'month'
     * @param int|null $speciesId ID de especie para filtrar
     * @param int|null $familyId ID de familia para filtrar
     * @param int|null $categoryId ID de categoría para filtrar
     * @return \Illuminate\Support\Collection
     */
    public static function getReceptionChartData(
        string $dateFrom,
        string $dateTo,
        string $valueType,
        string $groupBy,
        ?int $speciesId = null,
        ?int $familyId = null,
        ?int $categoryId = null
    ): \Illuminate\Support\Collection {
        $receptions = RawMaterialReception::with(
            'products.product.species',
            'products.product.family',
            'products.product.family.category'
        )
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->get();

        $grouped = [];

        foreach ($receptions as $reception) {
            if (!$reception->date) {
                continue;
            }

            $date = Carbon::parse($reception->date);

            switch ($groupBy) {
                case 'week':
                    $receptionDate = $date->startOfWeek()->format('Y-\WW'); // Ej: 2025-W27
                    break;
                case 'month':
                    $receptionDate = $date->format('Y-m'); // Ej: 2025-07
                    break;
                case 'day':
                default:
                    $receptionDate = $date->format('Y-m-d'); // Ej: 2025-07-02
                    break;
            }

            // Calcular valores solo de los productos que cumplen los filtros
            $filteredAmount = 0;
            $filteredQuantity = 0;

            foreach ($reception->products as $receptionProduct) {
                $product = $receptionProduct->product;
                
                if (!$product) {
                    continue;
                }

                // Verificar filtros
                $matchesSpecies = !$speciesId || ($product->species && $product->species->id == $speciesId);
                $matchesFamily = !$familyId || ($product->family && $product->family->id == $familyId);
                $matchesCategory = !$categoryId || (
                    $product->family && 
                    $product->family->category && 
                    $product->family->category->id == $categoryId
                );

                // Si no cumple todos los filtros, saltar este producto
                if (!$matchesSpecies || !$matchesFamily || !$matchesCategory) {
                    continue;
                }

                // Sumar cantidad (peso neto)
                $filteredQuantity += floatval($receptionProduct->net_weight);

                // Calcular monto (precio * peso neto)
                if ($receptionProduct->price) {
                    $filteredAmount += floatval($receptionProduct->price * $receptionProduct->net_weight);
                }
            }

            // Solo agregar si hay datos que cumplen los filtros
            if ($filteredQuantity > 0 || $filteredAmount > 0) {
                if (!isset($grouped[$receptionDate])) {
                    $grouped[$receptionDate] = [
                        'date' => $receptionDate,
                        'amount' => 0,
                        'quantity' => 0,
                    ];
                }

                $grouped[$receptionDate]['amount'] += $filteredAmount;
                $grouped[$receptionDate]['quantity'] += $filteredQuantity;
            }
        }

        return collect($grouped)
            ->sortKeys()
            ->map(fn($item) => [
                'date' => $item['date'],
                'value' => round($item[$valueType], 2),
            ])
            ->values();
    }
}

