<?php

namespace App\Models;

use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StoredBox extends Model
{
    use UsesTenantConnection;
    use HasFactory;

    protected $fillable = ['store_id', 'box_id', 'position'];

    public function box()
    {
        return $this->belongsTo(Box::class, 'box_id');
    }

    public function store()
    {
        return $this->belongsTo(Store::class, 'store_id');
    }
}
