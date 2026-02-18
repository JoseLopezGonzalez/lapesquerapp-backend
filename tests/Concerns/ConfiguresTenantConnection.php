<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

trait ConfiguresTenantConnection
{
    /**
     * Obtiene un valor de configuración usando la app del test (evita resolver 'config' como clase).
     */
    private function getConfig(string $key, mixed $default = null): mixed
    {
        if (! isset($this->app) || ! $this->app->bound('config')) {
            return $default;
        }

        return $this->app['config']->get($key, $default);
    }

    /**
     * Comprueba que la BD esté alcanzable con un timeout corto para no colgar los tests.
     * Si no hay conexión, marca el test como skipped con un mensaje claro.
     */
    protected function ensureDatabaseReachable(): void
    {
        $dbHost = $this->getConfig('database.connections.mysql.host', getenv('DB_HOST') ?: '127.0.0.1');
        if ($this->getConfig('app.env') === 'testing' && ! getenv('LARAVEL_SAIL') && $dbHost === 'mysql') {
            $dbHost = '127.0.0.1';
        }
        $port = (int) $this->getConfig('database.connections.mysql.port', getenv('DB_PORT') ?: '3306');
        $errno = 0;
        $errstr = '';
        $timeout = 2;

        $fp = @fsockopen($dbHost, $port, $errno, $errstr, $timeout);
        if ($fp === false && $dbHost === '127.0.0.1' && getenv('LARAVEL_SAIL')) {
            $fp = @fsockopen('mysql', $port, $errno, $errstr, $timeout);
            if ($fp !== false) {
                $dbHost = 'mysql';
                if (isset($this->app) && $this->app->bound('config')) {
                    $this->app['config']->set('database.connections.mysql.host', 'mysql');
                    $this->app['config']->set('database.connections.tenant.host', 'mysql');
                }
            }
        }
        if ($fp === false) {
            $msg = "Base de datos no disponible en {$dbHost}:{$port} (¿MySQL en marcha?). Error: {$errstr} ({$errno})";
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

        $defaultConnection = $this->getConfig('database.default', 'mysql');
        $database = $this->getConfig('database.connections.' . $defaultConnection . '.database', env('DB_DATABASE', 'testing'));

        $this->app['config']->set('database.connections.tenant.database', $database);
        DB::purge('tenant');
        DB::reconnect('tenant');

        Artisan::call('migrate', [
            '--path' => 'database/migrations/companies',
            '--database' => 'tenant',
            '--force' => true,
        ]);
    }
}
