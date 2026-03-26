<?php

namespace App\Http\Resources\v2;

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
        
        return [
            'id' => $this->id,
            'palletId' => $this->relationLoaded('pallet') && $this->pallet ? $this->pallet->id : null,
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
    }
}
