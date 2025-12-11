<?php

namespace App\Http\Resources\v2;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RawMaterialReceptionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Construir array de precios en el mismo formato que el request
        // Solo para recepciones creadas en modo palets
        $prices = [];
        if ($this->creation_mode === 'pallets' && $this->relationLoaded('products')) {
            foreach ($this->products as $product) {
                if ($product->price !== null) {
                    $prices[] = [
                        'product' => [
                            'id' => $product->product_id,
                        ],
                        'lot' => $product->lot,
                        'price' => $product->price,
                    ];
                }
            }
        }

        return [
            'id' => $this->id,
            'supplier' => $this->whenLoaded('supplier', function () {
                return new SupplierResource($this->supplier);
            }),
            'date' => $this->date,
            'notes' => $this->notes,
            'creationMode' => $this->creation_mode, // 'lines' o 'pallets'
            'netWeight' => $this->netWeight,
            'species' => $this->species,
            'details' => RawMaterialReceptionProductResource::collection($this->products),
            'prices' => $prices, // Array de precios en formato request (solo para modo palets)
            'pallets' => \App\Http\Resources\v2\PalletResource::collection($this->pallets),
            'totalAmount' => $this->totalAmount,
            'canEdit' => $this->can_edit,
            'cannotEditReason' => $this->cannot_edit_reason,
        ];
    }


    
}
