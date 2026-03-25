<?php

namespace App\Http\Resources\v2;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FieldOrderDetailsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'orderType' => $this->order_type ?? 'standard',
            'status' => $this->status,
            'entryDate' => $this->entry_date,
            'loadDate' => $this->load_date,
            'buyerReference' => $this->buyer_reference,
            'customer' => $this->customer ? [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
            ] : null,
            'fieldOperatorId' => $this->field_operator_id,
            'routeId' => $this->route_id,
            'routeStopId' => $this->route_stop_id,

            // planned
            'plannedProductDetails' => $this->plannedProductDetails?->map(fn ($detail) => $detail->toArrayAssoc())->values(),

            // execution (match full order detail expectations: pallets -> boxes include box.id)
            // Use toArrayAssoc() because OrderDetailService eagerly loads `pallets.boxes.box`,
            // while V2 representation depends on `boxesV2` relation.
            'pallets' => $this->pallets?->map(fn ($pallet) => $pallet->toArrayAssoc())->values(),

            'totalBoxes' => $this->totalBoxes,
            'totalNetWeight' => $this->totalNetWeight,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }
}

