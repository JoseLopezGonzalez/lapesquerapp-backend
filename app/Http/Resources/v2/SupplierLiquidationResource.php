<?php

namespace App\Http\Resources\v2;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierLiquidationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // withCount carga raw_material_receptions_count y cebo_dispatches_count
        // Si no están presentes (p.ej. tras un close), se hacen las consultas directas
        $receptionsCount = $this->raw_material_receptions_count
            ?? $this->rawMaterialReceptions()->count();
        $dispatchesCount = $this->cebo_dispatches_count
            ?? $this->ceboDispatches()->count();

        return [
            'id' => $this->id,
            'supplier_id' => $this->supplier_id,
            'supplier' => $this->whenLoaded('supplier', fn () => new SupplierResource($this->supplier)),
            'start_date' => $this->start_date instanceof \Carbon\Carbon
                ? $this->start_date->format('Y-m-d')
                : (string) $this->start_date,
            'end_date' => $this->end_date instanceof \Carbon\Carbon
                ? $this->end_date->format('Y-m-d')
                : (string) $this->end_date,
            'closed_at' => $this->closed_at?->toIso8601String(),
            'closed_by_user_id' => $this->closed_by_user_id,
            'closed_by' => $this->whenLoaded('closedBy', fn () => [
                'id' => $this->closedBy->id,
                'name' => $this->closedBy->name,
            ]),
            'notes' => $this->notes,
            'receptions_count' => $receptionsCount,
            'dispatches_count' => $dispatchesCount,
        ];
    }
}
