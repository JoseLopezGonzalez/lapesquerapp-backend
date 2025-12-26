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
        if ($this->creation_mode === 'pallets') {
            // Primero, obtener precios de las líneas de recepción
            if ($this->relationLoaded('products')) {
                foreach ($this->products as $product) {
                    if ($product->price !== null) {
                        $key = "{$product->product_id}_{$product->lot}";
                        $prices[$key] = [
                            'product' => [
                                'id' => $product->product_id,
                            ],
                            'lot' => $product->lot,
                            'price' => $product->price,
                        ];
                    }
                }
            }
            
            // Si hay palets cargados, buscar precios desde las cajas para combinaciones que no tienen línea de recepción
            // Esto cubre el caso donde hay cajas con lotes que no tienen línea de recepción o tienen precio null
            if ($this->relationLoaded('pallets')) {
                foreach ($this->pallets as $pallet) {
                    if ($pallet->relationLoaded('boxes')) {
                        foreach ($pallet->boxes as $palletBox) {
                            $box = $palletBox->box;
                            if ($box) {
                                $key = "{$box->article_id}_{$box->lot}";
                                // Solo agregar si no existe ya en el array (prioridad a líneas de recepción)
                                if (!isset($prices[$key])) {
                                    // Obtener precio desde el accessor de la caja (que busca en la recepción)
                                    $boxPrice = $box->cost_per_kg;
                                    if ($boxPrice !== null) {
                                        $prices[$key] = [
                                            'product' => [
                                                'id' => $box->article_id,
                                            ],
                                            'lot' => $box->lot,
                                            'price' => $boxPrice,
                                        ];
                                    }
                                }
                            }
                        }
                    }
                }
            }
            
            // Convertir el array asociativo a array indexado
            $prices = array_values($prices);
        }

        return [
            'id' => $this->id,
            'supplier' => $this->whenLoaded('supplier', function () {
                return new SupplierResource($this->supplier);
            }),
            'date' => $this->date,
            'notes' => $this->notes,
            'declaredTotalAmount' => $this->declared_total_amount ? round((float) $this->declared_total_amount, 2) : null,
            'declaredTotalNetWeight' => $this->declared_total_net_weight ? round((float) $this->declared_total_net_weight, 2) : null,
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
