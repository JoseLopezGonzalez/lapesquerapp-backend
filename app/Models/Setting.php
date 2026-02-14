<?php

namespace App\Models;

use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use UsesTenantConnection;

    protected $table = 'settings';

    protected $fillable = [
        'key',
        'value',
        'type',
        'description',
    ];

    /**
     * Clave que no debe devolverse en claro en GET (se ofusca).
     */
    public const SENSITIVE_KEY_PASSWORD = 'company.mail.password';

    /**
     * Obtener todos los pares key => value del tenant.
     */
    public static function getAllKeyValue(): array
    {
        return self::query()->pluck('value', 'key')->all();
    }
}
