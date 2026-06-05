<?php

namespace App\Models;

use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupplierLiquidation extends Model
{
    use UsesTenantConnection;
    use HasFactory;

    protected $fillable = [
        'supplier_id',
        'start_date',
        'end_date',
        'closed_at',
        'closed_by_user_id',
        'notes',
    ];

    protected $casts = [
        'closed_at' => 'datetime',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function closedBy()
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    public function rawMaterialReceptions()
    {
        return $this->hasMany(RawMaterialReception::class);
    }

    public function ceboDispatches()
    {
        return $this->hasMany(CeboDispatch::class);
    }
}
