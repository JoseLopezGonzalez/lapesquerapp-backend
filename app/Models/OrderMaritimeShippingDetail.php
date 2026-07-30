<?php

namespace App\Models;

use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderMaritimeShippingDetail extends Model
{
    use HasFactory;
    use UsesTenantConnection;

    protected $fillable = [
        'order_id',
        'customs_broker_id',
        'vessel_name',
        'voyage_number',
        'export_invoice_number',
        'swb_number',
        'loading_port',
        'discharge_port',
        'ultimate_consignee_name',
        'ultimate_consignee_address',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customsBroker(): BelongsTo
    {
        return $this->belongsTo(CustomsBroker::class);
    }

    public function toArrayAssoc(): array
    {
        return [
            'id' => $this->id,
            'orderId' => $this->order_id,
            'customsBrokerId' => $this->customs_broker_id,
            'customsBroker' => $this->relationLoaded('customsBroker') ? $this->customsBroker?->toArrayAssoc() : null,
            'vesselName' => $this->vessel_name,
            'voyageNumber' => $this->voyage_number,
            'exportInvoiceNumber' => $this->export_invoice_number,
            'swbNumber' => $this->swb_number,
            'loadingPort' => $this->loading_port,
            'dischargePort' => $this->discharge_port,
            'ultimateConsigneeName' => $this->ultimate_consignee_name,
            'ultimateConsigneeAddress' => $this->ultimate_consignee_address,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }
}
