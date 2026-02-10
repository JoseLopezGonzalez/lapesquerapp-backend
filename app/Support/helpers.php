<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

if (! function_exists('tenantSetting')) {
    function tenantSetting(string $key, mixed $default = null): mixed
    {
        // Cache local por petición (no persistente)
        static $settingsCache = [];

        // Normalizar clave: permitir tanto 'name' como 'company.name'
        $normalizedKey = str_starts_with($key, 'company.') ? $key : "company.$key";

        // Si ya se ha cargado, devolver directamente (cache por ambas claves)
        if (array_key_exists($key, $settingsCache)) {
            return $settingsCache[$key];
        }
        if (array_key_exists($normalizedKey, $settingsCache)) {
            return $settingsCache[$normalizedKey];
        }

        // Leer de la base de datos del tenant
        $value = DB::connection('tenant')->table('settings')
            ->where('key', $normalizedKey)
            ->value('value');

        // Usar fallback si no existe o está vacío
        if (is_null($value) || $value === '') {
            // Si la clave ya incluye prefijo 'company.', usarla tal cual
            $value = config($normalizedKey, $default);
        }

        // Guardar en cache local
        $settingsCache[$key] = $value;
        $settingsCache[$normalizedKey] = $value;

        return $value;
    }
}

if (! function_exists('magicLinkFrontendUrl')) {
    /**
     * URL base del frontend para enlaces de magic link (por tenant).
     * Se usa en emails: {url}/auth/verify?token=xxx
     * Si la URL contiene {subdomain}, se reemplaza por el subdominio del tenant actual (ej. brisamar).
     * Ejemplo en .env: FRONTEND_URL=https://{subdomain}.lapesquerapp.es
     */
    function magicLinkFrontendUrl(): string
    {
        $url = tenantSetting('company.frontend_url', env('FRONTEND_URL', config('app.frontend_url', '')));
        $url = (string) $url;

        if (str_contains($url, '{subdomain}') && app()->bound('currentTenant')) {
            $url = str_replace('{subdomain}', (string) app('currentTenant'), $url);
        }

        return rtrim($url, '/');
    }
}
