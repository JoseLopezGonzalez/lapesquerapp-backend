<?php

namespace App\Models;

use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Incoterm extends Model
{
    use HasFactory;
    use UsesTenantConnection;

    protected $fillable = [
        'code',
        'description',
    ];

    public function toArrayAssoc(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'description' => $this->description,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }

    // has many orders
    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
