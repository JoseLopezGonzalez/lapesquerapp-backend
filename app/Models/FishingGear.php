<?php

namespace App\Models;

use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FishingGear extends Model
{
    use HasFactory;
    use UsesTenantConnection;

    /* Clave foranea fishing_gear - Actualización nueva  en Tabla Species*/
    /* $table->unsignedBigInteger('fishing_gear_id'); */

    protected $fillable = ['name'];

    public function species()
    {
        return $this->hasMany(Species::class);
    }

    public function toArrayAssoc()
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
        ];
    }
}
