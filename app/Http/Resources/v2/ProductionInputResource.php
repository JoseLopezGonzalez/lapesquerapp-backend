<?php

namespace App\Http\Resources\v2;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductionInputResource extends JsonResource
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
            'productionRecordId' => $this->production_record_id,
            'boxId' => $this->box_id,
            'box' => $this->whenLoaded('box', function () {
                return new BoxResource($this->box);
            }),
            // Datos calculados desde la caja
            'product' => $this->when($this->box, function () {
                return $this->box->product ? [
                    'id' => $this->box->product->id,
                    'name' => $this->box->product->name,
                ] : null;
            }),
            'lot' => $this->lot,
            'weight' => $this->weight,
            'pallet' => $this->when($this->pallet, function () {
                return $this->pallet ? [
                    'id' => $this->pallet->id,
                ] : null;
            }),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
