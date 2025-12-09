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
        return [
            'id' => $this->id,
            'observations' => $this->observations,
            'state' => $this->stateArray,
            'productsNames' => $this->productsNames,
            'boxes' => $this->boxes->map(function ($box) {
                return $box->toArrayAssocV2();
            }),
            'lots' => $this->lots,
            'netWeight' => $this->netWeight !== null ? round($this->netWeight, 3) : null,
            'position' => $this->position,
            'store' => /* si es null o no */
                $this->store ? [
                    'id' => $this->store->id,
                    'name' => $this->store->name,
                ] : null,
            'orderId' => $this->order_id,
            'numberOfBoxes' => $this->numberOfBoxes,
            // Campos calculados para cajas disponibles y usadas
            'availableBoxesCount' => $this->availableBoxesCount,
            'usedBoxesCount' => $this->usedBoxesCount,
            'totalAvailableWeight' => $this->totalAvailableWeight !== null ? round($this->totalAvailableWeight, 3) : null,
            'totalUsedWeight' => $this->totalUsedWeight !== null ? round($this->totalUsedWeight, 3) : null,
            // Nuevos campos de recepción y coste
            'receptionId' => $this->reception_id,
            'reception' => $this->reception ? [
                'id' => $this->reception->id,
                'date' => $this->reception->date,
            ] : null,
            'isFromReception' => $this->isFromReception,
            'costPerKg' => $this->cost_per_kg !== null ? round($this->cost_per_kg, 4) : null,
            'totalCost' => $this->total_cost !== null ? round($this->total_cost, 2) : null,
        ];
    }
}
