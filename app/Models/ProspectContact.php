<?php

namespace App\Models;

use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProspectContact extends Model
{
    use HasFactory;
    use UsesTenantConnection;

    protected $fillable = [
        'prospect_id',
        'name',
        'role',
        'phone',
        'email',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function prospect()
    {
        return $this->belongsTo(Prospect::class);
    }

    public function toArrayAssoc(): array
    {
        return [
            'id' => $this->id,
            'prospectId' => $this->prospect_id,
            'name' => $this->name,
            'role' => $this->role,
            'phone' => $this->phone,
            'email' => $this->email,
            'isPrimary' => $this->is_primary,
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
