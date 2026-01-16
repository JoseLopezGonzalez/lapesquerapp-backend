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
     * Relación con los roles del usuario.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_user');
    }

    

    /**
     * Verificar si el usuario tiene un rol específico.
     *
     * @param string|array $role
     * @return bool
     */
    public function hasRole($role)
    {
        if (is_array($role)) {
            return $this->roles->whereIn('name', $role)->isNotEmpty();
        }

        return $this->roles->where('name', $role)->isNotEmpty();
    }

    /**
     * Asignar un rol al usuario.
     *
     * @param string $roleName
     * @return void
     */
    public function assignRole($roleName)
    {
        $role = Role::where('name', $roleName)->first();

        if ($role && !$this->hasRole($roleName)) {
            $this->roles()->attach($role);
        }
    }

    /**
     * Eliminar un rol del usuario.
     *
     * @param string $roleName
     * @return void
     */
    public function removeRole($roleName)
    {
        $role = Role::where('name', $roleName)->first();

        if ($role && $this->hasRole($roleName)) {
            $this->roles()->detach($role);
        }
    }

    public function hasAnyRole(array $roles)
    {
        return $this->roles()->whereIn('name', $roles)->exists();
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
