<?php

namespace App\Models;

use App\Traits\UsesTenantConnection;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CaptureZone extends Model
{
    use UsesTenantConnection;
    use HasFactory;
    protected $table = 'capture_zones';

    protected $fillable = ['name'];

    public function toArrayAssoc()
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
        ];
    }

    // Relación con el modelo Production
    public function productions()
    {
        return $this->hasMany(Production::class, 'capture_zone_id');
    }

    // Relación con el modelo Product
    public function products()
    {
        return $this->hasMany(Product::class, 'capture_zone_id');
    }
}
