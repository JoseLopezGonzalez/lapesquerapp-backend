<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

if (! function_exists('tenantSetting')) {
    function tenantSetting(string $key, mixed $default = null): mixed
    {
        // Cache local por petición (no persistente)
        static $settingsCache = [];

        // Si ya se ha cargado, devolver directamente
        if (array_key_exists($key, $settingsCache)) {
            return $settingsCache[$key];
        }

        // Leer de la base de datos del tenant
        $value = DB::table('settings')
            ->where('key', $key)
            ->value('value');

        // Usar fallback si no existe
        if (is_null($value)) {
            $value = config("company.{$key}", $default);
        }

        // Guardar en cache local
        $settingsCache[$key] = $value;

        return $value;
    }
}
