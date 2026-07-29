<?php

namespace App\Models;

use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class OrderMaritimeContainer extends Model
{
    use HasFactory;
    use UsesTenantConnection;

    protected $fillable = [
        'order_id',
        'container_number',
        'seal_number',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function toArrayAssoc(): array
    {
        return [
            'id' => $this->id,
            'orderId' => $this->order_id,
            'containerNumber' => $this->container_number,
            'sealNumber' => $this->seal_number,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }

    /**
     * Boot del modelo - Validaciones de integridad.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (self $container) {
            if (empty($container->container_number)) {
                throw ValidationException::withMessages([
                    'container_number' => 'El número de contenedor es obligatorio.',
                ]);
            }
        });
    }
}
