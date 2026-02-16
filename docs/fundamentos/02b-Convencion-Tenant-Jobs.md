# Convención de Tenant para Jobs (Colas) — PesquerApp

**Fecha:** 2026-02-15  
**Estado:** Documento preparatorio — actualmente no hay Jobs/colas en el proyecto.  
**Propósito:** Definir la convención a seguir cuando se incorporen colas (Laravel Queue) para evitar ejecutar trabajos en el tenant equivocado.

---

## 1. Contexto

El backend de PesquerApp es **multi-tenant** con **una base de datos por tenant**. Cada request HTTP lleva el header `X-Tenant` y el middleware configura la conexión `tenant` antes de ejecutar la lógica.

Las **colas** se ejecutan en un proceso separado (worker) sin contexto HTTP. Si un Job no incluye explícitamente el tenant, el worker no sabrá a qué base de datos conectarse y podría:

- Ejecutarse en el tenant equivocado.
- Usar la última conexión configurada (race condition entre tenants).
- Fallar si no hay conexión configurada.

**Recomendación:** Aplicar esta convención desde el primer Job que toque datos de tenant.

---

## 2. Convención: Payload con identificador de tenant

### 2.1 Campos obligatorios en el payload

Todo Job que acceda a datos del tenant **debe incluir** en su payload uno de estos identificadores:

| Campo | Tipo | Uso |
|-------|------|-----|
| `tenant_subdomain` | string | Subdominio del tenant (ej. `brisamar`). |
| `tenant_database` | string | Nombre de la base de datos del tenant. |

**Recomendación:** Usar `tenant_subdomain` para facilitar debugging y logs. Si se prefiere rendimiento (evitar lookup en `tenants`), usar `tenant_database`.

### 2.2 Ejemplo de Job

```php
namespace App\Jobs;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class SendOrderConfirmationEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $tenantSubdomain,
        public int $orderId,
    ) {}

    public function handle(): void
    {
        $this->configureTenantConnection();

        // Lógica del job usando modelos del tenant
        $order = \App\Models\Order::findOrFail($this->orderId);
        // ...
    }

    private function configureTenantConnection(): void
    {
        $tenant = Tenant::on('mysql')->where('subdomain', $this->tenantSubdomain)->firstOrFail();

        config(['database.connections.tenant.database' => $tenant->database]);
        DB::purge('tenant');
        DB::reconnect('tenant');

        app()->instance('currentTenant', $this->tenantSubdomain);
    }
}
```

### 2.3 Despacho desde el controlador

```php
SendOrderConfirmationEmail::dispatch(
    tenantSubdomain: app('currentTenant'),
    orderId: $order->id,
);
```

---

## 3. Alternativa: Trait reutilizable

Para evitar duplicar la lógica de configuración en cada Job:

```php
namespace App\Jobs\Concerns;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

trait ConfiguresTenantFromPayload
{
    protected function configureTenantConnection(): void
    {
        $subdomain = $this->tenantSubdomain ?? $this->tenant_subdomain ?? null;
        if (!$subdomain) {
            throw new \RuntimeException('Job requires tenant_subdomain in payload.');
        }

        $tenant = Tenant::on('mysql')->where('subdomain', $subdomain)->firstOrFail();

        config(['database.connections.tenant.database' => $tenant->database]);
        DB::purge('tenant');
        DB::reconnect('tenant');

        app()->instance('currentTenant', $subdomain);
    }
}
```

Uso en Job:

```php
use App\Jobs\Concerns\ConfiguresTenantFromPayload;

class MyTenantJob implements ShouldQueue
{
    use ConfiguresTenantFromPayload;

    public function __construct(public string $tenantSubdomain, /* ... */) {}

    public function handle(): void
    {
        $this->configureTenantConnection();
        // ...
    }
}
```

---

## 4. Consideraciones

- **Serialización:** Los Jobs se serializan a la cola. Usar tipos primitivos (string, int) para tenant; no serializar modelos Tenant.
- **Failures:** Si el tenant no existe o está inactivo, `firstOrFail()` lanzará excepción y el Job fallará; el worker puede reintentar o mover a failed_jobs según configuración.
- **Logs:** Incluir `tenant_subdomain` en logs del Job para trazabilidad.
- **Horizon / Supervisor:** Si se usa Laravel Horizon, cada worker puede procesar Jobs de múltiples tenants; la configuración de conexión es por Job, no por worker.

---

## 5. Referencias

- **Arquitectura multi-tenant:** `docs/fundamentos/01-Arquitectura-Multi-Tenant.md`
- **CLAUDE.md:** Sección "Multi-tenant con DB separada" — si se usan Jobs, el payload debe incluir identificador de tenant.
