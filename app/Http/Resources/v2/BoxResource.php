<?php

namespace App\Http\Resources\v2;

use App\Enums\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BoxResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $production = $this->production;
        $product = $this->relationLoaded('product') ? $this->product : null;
        $palletId = null;
        $canViewManualCostContext = $request->user()?->hasAnyRole([
            Role::Administrador->value,
            Role::Tecnico->value,
        ]) ?? false;

        if ($this->relationLoaded('palletBox')) {
            $palletId = $this->palletBox?->pallet_id;
        } elseif ($this->pallet) {
            // Fallback para mantener compatibilidad si no hubo eager loading explícito.
            $palletId = $this->pallet->id;
        }

        $data = [
            'id' => $this->id,
            'palletId' => $palletId,
            'product' => $product ? [
                'species' => $product->relationLoaded('species') ? $product->species?->toArrayAssoc() : null,
                'captureZone' => $product->relationLoaded('captureZone') ? $product->captureZone?->toArrayAssoc() : null,
                'articleGtin' => $product->article_gtin,
                'boxGtin' => $product->box_gtin,
                'palletGtin' => $product->pallet_gtin,
                'name' => $product->name,
                'id' => $product->id,
            ] : null,
            'lot' => $this->lot,
            'gs1128' => $this->gs1_128,
            'grossWeight' => $this->gross_weight,
            'netWeight' => $this->net_weight,
            'createdAt' => $this->created_at, //formatear para mostrar solo fecha
            'isAvailable' => $this->isAvailable, // Flag que indica si la caja está disponible (no usada en producción)
            'production' => $production ? [
                'id' => $production->id,
                'lot' => $production->lot,
            ] : null, // Información de la producción más reciente en la que se usó esta caja
        ];

        if ($canViewManualCostContext) {
            $traceableCostPerKg = $this->traceable_cost_per_kg;
            $data['manualCostPerKg'] = $this->manual_cost_per_kg !== null ? (float) $this->manual_cost_per_kg : null;
            $data['traceableCostPerKg'] = $traceableCostPerKg !== null ? round((float) $traceableCostPerKg, 4) : null;
            $data['costPerKg'] = $this->cost_per_kg;
            $data['totalCost'] = $this->total_cost;
        }

        return $data;
    }
}
