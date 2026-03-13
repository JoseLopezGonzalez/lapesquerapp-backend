<?php

namespace App\Models;

use App\Traits\UsesTenantConnection;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class ExternalUser extends Authenticatable
{
    use HasApiTokens, Notifiable, UsesTenantConnection;

    public const TYPE_MAQUILADOR = 'maquilador';

    protected $fillable = [
        'name',
        'company_name',
        'email',
        'type',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function stores()
    {
        return $this->hasMany(Store::class);
    }

    public function allowedStoreIds(): array
    {
        return $this->stores()->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    public function toArrayAssoc(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'companyName' => $this->company_name,
            'email' => $this->email,
            'type' => $this->type,
            'isActive' => $this->is_active,
            'notes' => $this->notes,
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
