<?php

namespace App\Models;

use App\Traits\UsesTenantConnection;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use UsesTenantConnection;
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'active',
        'role',
        'assigned_store_id',
        'company_name',
        'company_logo_url',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'active' => 'boolean',
    ];

    /**
     * Verificar si el usuario tiene un rol específico.
     *
     * @param string|array $role Uno o más valores del enum (string).
     * @return bool
     */
    public function hasRole($role): bool
    {
        if ($this->role === null) {
            return false;
        }
        if (is_array($role)) {
            return in_array($this->role, $role, true);
        }
        return $this->role === $role;
    }

    /**
     * Verificar si el usuario tiene al menos uno de los roles indicados.
     *
     * @param array $roles Valores del enum (strings).
     * @return bool
     */
    public function hasAnyRole(array $roles): bool
    {
        if ($this->role === null) {
            return false;
        }
        return in_array($this->role, $roles, true);
    }

    /* toasocArray */
    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }

    /* toArrayAssoc */
    public function toArrayAssoc()
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'emailVerifiedAt' => $this->email_verified_at,
            'assignedStoreId' => $this->assigned_store_id,
            'companyName' => $this->company_name,
            'companyLogoUrl' => $this->company_logo_url,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }
    
}
