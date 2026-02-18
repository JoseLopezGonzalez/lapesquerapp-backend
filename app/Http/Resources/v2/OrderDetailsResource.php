<?php

namespace App\Http\Resources\v2;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderDetailsResource extends JsonResource
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
            'orderType' => $this->order_type ?? 'standard',
            'buyerReference' => $this->buyer_reference,
            'customer' => $this->customer?->toArrayAssoc(),
            'paymentTerm' => $this->payment_term?->toArrayAssoc(),
            'billingAddress' => $this->billing_address,
            'shippingAddress' => $this->shipping_address,
            'transportationNotes' => $this->transportation_notes,
            'productionNotes' => $this->production_notes,
            'accountingNotes' => $this->accounting_notes,
            'salesperson' => $this->salesperson?->toArrayAssoc(),
            'transport' => $this->transport?->toArrayAssoc(),
            'entryDate' => $this->entry_date,
            'loadDate' => $this->load_date,
            'status' => $this->status,
            'pallets' => $this->pallets->map(function ($pallet) {
                return $pallet->toArrayAssoc();
            }),
            'incoterm' => $this->incoterm?->toArrayAssoc(),
            'totalNetWeight' => $this->totalNetWeight,
            'numberOfPallets' => $this->numberOfPallets,
            'totalBoxes' => $this->totalBoxes,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
            'plannedProductDetails' => $this->plannedProductDetails
                ? $this->plannedProductDetails->map(function ($detail) {
                    return $detail->toArrayAssoc();
                })
                : null,
            'productionProductDetails' => $this->productionProductDetails,
            'productDetails' => $this->productDetails,
            'subTotalAmount' => $this->subTotalAmount,
            'totalAmount' => $this->totalAmount,
            'emails' => $this->emailsArray,
            'ccEmails' => $this->ccEmailsArray,
            'truckPlate' => $this->truck_plate,
            'trailerPlate' => $this->trailer_plate,
            'temperature' => $this->temperature,
            'incident' => $this->incident ? $this->incident->toArrayAssoc() : null,
        ];
    }
}
