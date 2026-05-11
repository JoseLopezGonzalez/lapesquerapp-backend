<?php

namespace App\Http\Resources\v2;

use App\Support\PalletManualCostPolicy;
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
            'customer' => $this->relationLoaded('customer') && $this->customer ? [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
            ] : null,
            'fieldOperatorId' => $this->field_operator_id,
            'routeId' => $this->route_id,
            'routeStopId' => $this->route_stop_id,

            // planned
            'plannedProductDetails' => $this->relationLoaded('plannedProductDetails')
                ? $this->plannedProductDetails->map(fn ($detail) => $detail->toArrayAssoc())->values()
                : [],

            // execution: same V2 pallet shape as OrderDetailsResource (costs, availability, position).
            // OrderDetailService eager-loads `pallets.boxes.box`; toArrayAssocV2 falls back from boxesV2.
            'pallets' => $this->relationLoaded('pallets')
                ? $this->pallets->map(function ($pallet) use ($request) {
                    $assoc = $pallet->toArrayAssocV2();
                    if (! PalletManualCostPolicy::authorized($request->user())) {
                        return PalletManualCostPolicy::stripFromPalletAssocArray($assoc);
                    }

                    return $assoc;
                })->values()
                : [],

            'totalBoxes' => $this->totalBoxes,
            'totalNetWeight' => $this->totalNetWeight,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }
}
