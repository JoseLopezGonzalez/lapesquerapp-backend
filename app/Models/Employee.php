<?php

namespace App\Models;

use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use UsesTenantConnection;
    use HasFactory;

    protected $fillable = [
        'name',
        'nfc_uid',
    ];

    /**
     * Relación con los eventos de fichaje del empleado.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function punchEvents()
    {
        return $this->hasMany(PunchEvent::class);
    }

    /**
     * Obtener el último evento de fichaje del empleado.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function lastPunchEvent()
    {
        return $this->hasOne(PunchEvent::class)->latest('timestamp');
    }
}

