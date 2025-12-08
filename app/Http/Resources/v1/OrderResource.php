<?php

namespace App\Http\Resources\v1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
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
            'buyerReference' => $this->buyer_reference,
            'customer' => $this->customer ? $this->customer->toArrayAssoc() : null,
            'paymentTerm' => $this->payment_term ? $this->payment_term->toArrayAssoc() : null,
            'billingAddress' => $this->billing_address,
            'shippingAddress' => $this->shipping_address,
            'transportationNotes' => $this->transportation_notes,
            'productionNotes' => $this->production_notes,
            'accountingNotes' => $this->accounting_notes,
            'salesperson' => $this->salesperson ? $this->salesperson->toArrayAssoc() : null,
            'emails' => $this->emails,
            'transport' => $this->transport ? $this->transport->toArrayAssoc() : null,
            'entryDate' => $this->entry_date,
            'loadDate' => $this->load_date,
            'status' => $this->status,
            'numberOfPallets' => $this->numberOfPallets,
            'hasPalletsOnStorage' => $this->hasPalletsOnStorage(),
            /* 'pallets' => $this->pallets->map(function ($pallet) {
                return $pallet->toArrayAssoc();
            }),*/
            'incoterm' => $this->incoterm ? $this->incoterm->toArrayAssoc() : null,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }
}
