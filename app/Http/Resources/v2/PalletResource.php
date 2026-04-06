<?php

namespace App\Http\Resources\v2;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PalletResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $boxes = $this->relationLoaded('boxes') ? $this->boxes : collect();

        if ($this->relationLoaded('storedPallet')) {
            $position = $this->storedPallet?->position;
            $storeModel = $this->storedPallet?->store;
        } else {
            $position = $this->position;
            $storeModel = $this->store;
        }

        return [
            'id' => $this->id,
            'observations' => $this->observations,
            'state' => $this->stateArray,
            'productsNames' => $this->relationLoaded('boxes') ? $this->productsNames : [],
            'boxes' => $boxes->map(function ($box) {
                return $box->toArrayAssocV2();
            }),
            'lots' => $this->relationLoaded('boxes') ? $this->lots : [],
            'netWeight' => $this->relationLoaded('boxes') && $this->netWeight !== null ? round($this->netWeight, 3) : null,
            'position' => $position,
            'store' => $storeModel ? [
                'id' => $storeModel->id,
                'name' => $storeModel->name,
            ] : null,
            'orderId' => $this->order_id,
            'numberOfBoxes' => $this->relationLoaded('boxes') ? $this->numberOfBoxes : 0,
            // Campos calculados para cajas disponibles y usadas
            'availableBoxesCount' => $this->relationLoaded('boxes') ? $this->availableBoxesCount : 0,
            'usedBoxesCount' => $this->relationLoaded('boxes') ? $this->usedBoxesCount : 0,
            'totalAvailableWeight' => $this->relationLoaded('boxes') && $this->totalAvailableWeight !== null ? round($this->totalAvailableWeight, 3) : 0,
            'totalUsedWeight' => $this->relationLoaded('boxes') && $this->totalUsedWeight !== null ? round($this->totalUsedWeight, 3) : 0,
            // Nuevos campos de recepción y coste
            'receptionId' => $this->reception_id,
            'costPerKg' => $this->relationLoaded('boxes') && $this->cost_per_kg !== null ? round($this->cost_per_kg, 4) : null,
            'totalCost' => $this->relationLoaded('boxes') && $this->total_cost !== null ? round($this->total_cost, 2) : null,
        ];
    }
}
