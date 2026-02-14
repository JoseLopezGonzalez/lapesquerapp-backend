<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

trait ConfiguresTenantConnection
{
    /**
     * Comprueba que la BD esté alcanzable con un timeout corto para no colgar los tests.
     * Si no hay conexión, marca el test como skipped con un mensaje claro.
     */
    protected function ensureDatabaseReachable(): void
    {
        // Mismo host que config/database.php: si testing fuera de Sail y DB_HOST=mysql, usar 127.0.0.1
        $dbHost = getenv('DB_HOST') ?: '127.0.0.1';
        if (getenv('APP_ENV') === 'testing' && ! getenv('LARAVEL_SAIL') && $dbHost === 'mysql') {
            $dbHost = '127.0.0.1';
        }
        $host = $dbHost;
        $port = (int) (getenv('DB_PORT') ?: '3306');
        $errno = 0;
        $errstr = '';
        $timeout = 2;
        $fp = @fsockopen($host, $port, $errno, $errstr, $timeout);
        if ($fp === false) {
            $msg = "Base de datos no disponible en {$host}:{$port} (¿MySQL en marcha?). Error: {$errstr} ({$errno})";
            $this->markTestSkipped($msg);
        }
        fclose($fp);
    }

    /**
     * Configura la conexión tenant para tests (misma BD que default/testing)
     * y ejecuta las migraciones de companies (esquema tenant).
     */
    protected function setUpTenantConnection(): void
    {
        $this->ensureDatabaseReachable();

        $database = config('database.connections.' . config('database.default') . '.database') ?? env('DB_DATABASE', 'testing');

        config(['database.connections.tenant.database' => $database]);
        DB::purge('tenant');
        DB::reconnect('tenant');

        Artisan::call('migrate', [
            '--path' => 'database/migrations/companies',
            '--database' => 'tenant',
            '--force' => true,
        ]);
    }
}
