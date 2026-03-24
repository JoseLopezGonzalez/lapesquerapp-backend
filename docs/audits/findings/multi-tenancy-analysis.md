# Análisis Multi-Tenancy — PesquerApp Backend
**Fecha**: 2026-03-24 | **Auditoría**: Principal Backend v2 (actualización con evidencia de código)

---

## Arquitectura de Aislamiento

- **Estrategia**: database-per-tenant. La BD central (`mysql`) contiene solo la tabla `tenants`. Todos los datos de negocio residen en la BD del tenant.
- **Resolución**: cabecera `X-Tenant` (subdomain) procesada por `TenantMiddleware`.
- **Trait**: `UsesTenantConnection` fija `$this->setConnection('tenant')` en todos los modelos de negocio.
- **Validaciones**: Form Requests usan `exists:tenant.{table},id` para reglas de existencia.

---

## Fortalezas

### MT-F1 — UsesTenantConnection en 121 de 71 modelos (alto)
Búsqueda en `app/Models/`: 121 archivos incluyen `UsesTenantConnection`. Los modelos que **no** lo usan son exclusivamente modelos de la capa Superadmin/central: `FeatureFlag`, `TenantErrorLog`, `TenantFeatureOverride`, `TenantMigrationRun`, `SuperadminMagicLinkToken`, `ImpersonationLog`, `SuperadminUser`, `ImpersonationRequest`, `SystemAlert`, `TenantBlocklist`. Todos correctos: son modelos de la BD central.

### MT-F2 — Solo 2 usos de DB::connection('tenant')->table()
```
app/Services/Superadmin/TenantOnboardingService.php:183
app/Http/Controllers/v2/AuthController.php:217
```
Ambos justificados: el primero es onboarding (inserción inicial de settings), el segundo es registro de intentos de login fallidos en una tabla de seguridad. El resto del código accede al tenant via Eloquent con UsesTenantConnection.

### MT-F3 — CORS cachea el tenant lookup
`DynamicCorsMiddleware::isActiveTenant()` usa `Cache::remember("cors:tenant:{$subdomain}", 600)`. El CORS no hace query a la BD central en cada preflight request.

---

## Riesgos y Gaps

### MT-R1 — TenantMiddleware sin cache del tenant lookup (ALTO)

**Archivo**: `app/Http/Middleware/TenantMiddleware.php:19-20`

```php
$tenant = Tenant::where('subdomain', $subdomain)->first(); // Query a BD central sin cache
```

Cada request al API hace una query a la BD central para resolver el tenant. Con 100 req/s (20 usuarios × 5 tenants), son 100 queries/s a la misma tabla `tenants`. Aunque la tabla es pequeña y tiene índice en `subdomain`, bajo alta concurrencia este overhead se acumula.

**Inconsistencia**: el mismo DynamicCorsMiddleware SÍ cachea el lookup de tenant pero el TenantMiddleware no.

**Impacto adicional**: `DB::connection('tenant')->statement("SET time_zone = '+00:00'")` en línea 50 ejecuta una query extra en la BD del tenant en cada request. Esto puede resolverse configurando `timezone` en la conexión tenant de `config/database.php`.

### MT-R2 — Sin estrategia formal de cache tenant-aware para datos de negocio

**Evidencia**: solo 3 patrones de cache encontrados en el proyecto:
1. `Cache::remember("cors:tenant:{$subdomain}", 600)` — CORS
2. `Cache::remember("feature_flags:tenant:{$id}:{$plan}")` — feature flags Superadmin
3. `Cache::remember("geoip:{$ip}", ...)` — NO existe, debería implementarse

Los datos de negocio de alta frecuencia (settings del tenant, catálogos, opciones de selects) no están cacheados. Cada llamada a `GET /settings` o `GET /options` hace queries a la BD del tenant.

**Ejemplo crítico**: `SettingService` accede a la tabla `settings` en cada request que lee configuración. Con 50 settings por tenant, esta tabla es pequeña pero se consulta frecuentemente.

### MT-R3 — app()->instance('currentTenant', $subdomain) guarda solo el subdomain (BAJO)

```php
app()->instance('currentTenant', $subdomain); // Solo el string subdomain, no el objeto
```

Si algún código necesita acceder al objeto `Tenant` (id, database, etc.) después del middleware, debe hacer otra query. En el código actual esto no parece ser un problema, pero es una decisión de diseño subóptima — sería más útil guardar el objeto completo.

### MT-R4 — Jobs existentes: patrón correcto pero no documentado como estándar (BAJO)

Los 2 jobs existentes (`OnboardTenantJob`, `MigrateTenantJob`) operan sobre la BD central (Superadmin) y no necesitan conexión tenant. No hay jobs que operen en la BD de un tenant específico en la capa de negocio. Cuando se implementen (ej. job de generación de PDF asíncrono por tenant), el payload deberá incluir el subdomain y el job deberá ejecutar `TenantMiddleware::setupTenantConnection()` al iniciar.

---

## Estado del `SET time_zone` por request

El statement `SET time_zone = '+00:00'` en `TenantMiddleware.php:50` se ejecuta en cada request. Es correcto en su intención (garantizar UTC en la conexión tenant) pero ineficiente:

**Alternativa sin query**:
```php
// config/database.php
'connections' => [
    'tenant' => [
        'driver'    => 'mysql',
        'charset'   => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'timezone'  => '+00:00', // Equivale al SET time_zone pero aplicado en el DSN
        // ...
    ],
],
```

Con `timezone` en la config, Laravel aplica el timezone en el handshake de conexión sin statement adicional.

---

## Evaluación actualizada

| Aspecto | Nota | Evidencia |
|---|---|---|
| Aislamiento de datos | 9/10 | UsesTenantConnection en 121 modelos; solo 2 accesos raw justificados |
| Resolución por request | 8/10 | Correcto pero sin cache en TenantMiddleware |
| Consistencia de uso de conexión | 8.5/10 | Muy buena, sin fugas detectadas |
| Cache tenant-aware | 5/10 | Solo CORS y feature flags; datos de negocio no cacheados |
| Jobs tenant-aware | 7.5/10 | Patrón presente en capa Superadmin; pendiente en capa de negocio |

**Multi-tenancy global**: **8/10** (sólido en aislamiento, gap en cache de resolución y datos)
