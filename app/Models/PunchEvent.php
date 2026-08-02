<?php

namespace App\Models;

use App\Casts\DateTimeUtcCast;
use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PunchEvent extends Model
{
    use HasFactory;
    use UsesTenantConnection;

    const TYPE_IN = 'IN';

    const TYPE_OUT = 'OUT';

    protected $fillable = [
        'employee_id',
        'event_type',
        'device_id',
        'timestamp',
    ];

    protected $casts = [
        'timestamp' => DateTimeUtcCast::class,
    ];

    /**
     * Relación con el empleado.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
