<?php

// app/Models/Incident.php

namespace App\Models;

use App\Casts\DateTimeUtcCast;
use App\Traits\UsesTenantConnection;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class Incident extends Model
{
    use UsesTenantConnection;
    use HasFactory;

    protected $casts = [
        'resolved_at' => DateTimeUtcCast::class,
    ];

    /**
     * Estados válidos del incidente
     */
    const STATUS_OPEN = 'open';
    const STATUS_RESOLVED = 'resolved';

    /**
     * Tipos de resolución válidos
     */
    const RESOLUTION_TYPE_RETURNED = 'returned';
    const RESOLUTION_TYPE_PARTIALLY_RETURNED = 'partially_returned';
    const RESOLUTION_TYPE_COMPENSATED = 'compensated';

    /**
     * Lista de todos los estados válidos
     */
    public static function getValidStatuses(): array
    {
        return [
            self::STATUS_OPEN,
            self::STATUS_RESOLVED,
        ];
    }

    /**
     * Lista de todos los tipos de resolución válidos
     */
    public static function getValidResolutionTypes(): array
    {
        return [
            self::RESOLUTION_TYPE_RETURNED,
            self::RESOLUTION_TYPE_PARTIALLY_RETURNED,
            self::RESOLUTION_TYPE_COMPENSATED,
        ];
    }

    protected $fillable = [
        'order_id',
        'description',
        'status',
        'resolution_type',
        'resolution_notes',
        'resolved_at',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function toArrayAssoc(): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'status' => $this->status,
            'resolutionType' => $this->resolution_type,
            'resolutionNotes' => $this->resolution_notes,
            'resolvedAt' => $this->resolved_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Boot del modelo - Validaciones y eventos
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($incident) {
            // Validar status valores válidos
            if ($incident->status && !in_array($incident->status, self::getValidStatuses())) {
                throw ValidationException::withMessages([
                    'status' => 'El estado del incidente no es válido. Valores permitidos: ' . implode(', ', self::getValidStatuses()),
                ]);
            }

            // Validar resolution_type valores válidos (si existe)
            if ($incident->resolution_type && !in_array($incident->resolution_type, self::getValidResolutionTypes())) {
                throw ValidationException::withMessages([
                    'resolution_type' => 'El tipo de resolución no es válido. Valores permitidos: ' . implode(', ', self::getValidResolutionTypes()),
                ]);
            }
        });
    }
}
