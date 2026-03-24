# Auditoría de Degradación de Rendimiento — PesquerApp Backend
**Fecha**: 2026-03-24
**Síntoma reportado**: 3-4 segundos en cualquier endpoint, incluyendo GETs simples (4 opciones), desde la implementación de los módulos CRM, canal operativo, rutas de reparto y usuarios externos.
**Tipo**: Análisis de causa raíz + plan de acción
**Revisión**: Segunda pasada en profundidad incluida en secciones 10-12

---

## 1. Diagnóstico Ejecutivo

El problema **no es únicamente** consecuencia de los nuevos módulos. Los nuevos módulos añadieron carga adicional (N+1, índices faltantes, middleware extra), pero el hecho de que **cualquier** endpoint, incluso los más simples, tarde 3-4 segundos apunta a un overhead fijo en la capa de middleware/infraestructura que se acumula en cada request.

### Las tres causas reales del comportamiento de 3-4 segundos

**1. `QUEUE_CONNECTION=sync` convierte `StoreActivityLogJob` en trabajo síncrono bloqueante**
El job hace una segunda reconexión MySQL completa dentro del request. Esto era inofensivo cuando el job se ejecutaba de forma asíncrona en Redis, pero con cola síncrona bloquea al worker hasta que completa.

**2. El cache de geoIP falla silenciosamente con `CACHE_DRIVER=file` en Docker/WSL2**
Si el cache de archivos no puede escribir por problemas de permisos en Docker (frecuente en WSL2), `Cache::remember()` ejecuta el closure pero no persiste el resultado. Cada request siguiente vuelve a llamar a `ip2location.io` por red: 200-3000ms de latencia externa.

**3. `DB::purge + DB::reconnect` corre incondicionalmente en cada request y dos veces cuando la cola es síncrona**
El cache del TenantMiddleware solo evita la query a la BD central, pero la reconexión TCP a MySQL y el `SET time_zone` se ejecutan siempre. Con `QUEUE_CONNECTION=sync`, se ejecutan una segunda vez dentro del job.

### Cronología: ¿Por qué empeoró con los nuevos módulos?

Los nuevos módulos no introdujeron un bug nuevo, sino que **amplificaron overhead preexistente**:
- Se añadió más lógica en el request cycle (N+1 en CRM, `$user->refresh()` para usuarios externos)
- Más rutas → más bindings → más SubstituteBindings overhead
- Los nuevos módulos aumentaron el tiempo de ejecución, haciendo que el overhead de middleware (que antes pasaba desapercibido en endpoints rápidos) ahora sea la fracción dominante del tiempo total

---

## 2. Overhead Fijo por Request — Análisis Layer por Layer

Cada request autenticado a la API pasa por el siguiente pipeline antes y después de ejecutar lógica de negocio:

### Capa 1: DynamicCorsMiddleware (global, todo request)

```
app/Http/Middleware/DynamicCorsMiddleware.php
```

- Cache lookup (`cors:tenant:{subdomain}`, TTL 600s) con `CACHE_DRIVER=file`
- En cache hit sobre WSL2/Docker: **10-50ms** (filesystem I/O)
- En cache miss: query a BD central adicional

**Overhead estimado: 10-50ms**

### Capa 2: TenantMiddleware (por ruta, todo endpoint de negocio)

```
app/Http/Middleware/TenantMiddleware.php:23-52
```

```php
// Línea 23: Cache lookup — OK, evita query a BD central
$tenant = Cache::remember("tenant_mw:{$subdomain}", 300, function () use ($subdomain) {
    return Tenant::where('subdomain', $subdomain)->first(); // Solo en cache miss
});

// Líneas 45-52: SIEMPRE corre, aunque el tenant esté cacheado
config([
    'database.connections.tenant.database' => $tenant->database,
]);
DB::purge('tenant');     // Cierra la conexión existente
DB::reconnect('tenant'); // Abre NUEVA conexión TCP + MySQL auth + INIT_COMMAND
```

**El `DB::purge + DB::reconnect` es incondicional.** El cache del tenant solo evita la query a la BD central. La reconexión a MySQL corre en cada request.

Cada `DB::reconnect` ejecuta el `PDO::MYSQL_ATTR_INIT_COMMAND` definido en `config/database.php:55`:

```php
// config/database.php:55
[1003 => "SET time_zone = '+00:00'"],  // PDO::MYSQL_ATTR_INIT_COMMAND
```

Esto añade un round-trip extra a MySQL por cada reconexión.

Con `APP_DEBUG=true` (default en `.env.example`):

```php
// TenantMiddleware.php:54-58
if (config('app.debug')) {
    Log::info('Tenant connection established', [...]); // Escritura a disco en cada request
}
```

**Overhead estimado: 40-150ms**
- Cache lookup (file): 10-50ms
- `DB::purge`: ~1ms
- `DB::reconnect` (TCP + auth + SET time_zone): 30-100ms
- `Log::info` con debug activo: 5-20ms

### Capa 3: auth:sanctum (token validation)

Query a tabla `personal_access_tokens` para validar el Bearer token. Unas 5-20ms sobre conexión local, 20-80ms si DB_HOST es remoto.

**Overhead estimado: 5-80ms**

### Capa 4: Lógica de negocio del endpoint

El endpoint real. En un GET simple de 4 opciones: **5-50ms**.

### Capa 5: LogActivity (después de $next — post-response)

```
app/Http/Middleware/LogActivity.php
```

`LogActivity` corre **después** de `$next($request)` (línea 18), pero **antes** de devolver la respuesta al cliente. El tiempo que añade **sí es tiempo visible al usuario**.

```php
// LogActivity.php:29-37
$location = Cache::remember("geoip:{$ip}", 86400, function () use ($ip) {
    // Con CACHE_DRIVER=file en WSL2/Docker: puede fallar silenciosamente
    $loc = Location::get($ip); // HTTP externo a ip2location.io si cache falla
    return is_object($loc) ? $loc : null;
});

// LogActivity.php:68
dispatch(new StoreActivityLogJob($tenantDatabase, $data)); // SÍNCRONO con QUEUE_CONNECTION=sync
```

**Con CACHE_DRIVER=file funcionando correctamente:**
- Cache hit: 10-50ms (file I/O WSL2)
- User-Agent parsing: 5-10ms
- `dispatch()` síncrono: ver Capa 6
- **Subtotal: 15-60ms + Capa 6**

**Con CACHE_DRIVER=file fallando silenciosamente (permisos Docker):**
- Cada request: HTTP a ip2location.io = **200-3000ms** (depende de latencia del servicio externo y timeout)
- `dispatch()` síncrono: ver Capa 6
- **Subtotal: 200-3000ms + Capa 6**

### Capa 6: StoreActivityLogJob (SÍNCRONO con QUEUE_CONNECTION=sync)

```
app/Jobs/StoreActivityLogJob.php:24-35
```

```php
public function handle(): void
{
    config(['database.connections.tenant.database' => $this->tenantDatabase]);
    DB::purge('tenant');     // SEGUNDA reconexión del request
    DB::reconnect('tenant'); // SEGUNDA conexión TCP + MySQL auth + SET time_zone
    ActivityLog::create($this->data); // INSERT
}
```

Con `QUEUE_CONNECTION=sync`, esto corre dentro del ciclo del request, **después** de que la lógica de negocio ya terminó. El problema: hace **otra** `DB::purge + DB::reconnect`.

**Overhead estimado: 35-120ms**
- `DB::purge + DB::reconnect` (2ª vez): 30-100ms
- `ActivityLog::create` (INSERT): 5-20ms

---

## 3. Tabla de Overhead Acumulado por Request

### Escenario A — Producción ideal (CACHE_DRIVER=redis, QUEUE_CONNECTION=redis)

| Componente | Overhead |
|---|---|
| DynamicCorsMiddleware (cache hit Redis) | 1-3ms |
| TenantMiddleware (cache hit Redis + reconnect) | 15-40ms |
| auth:sanctum | 5-15ms |
| Lógica de negocio (GET simple) | 5-30ms |
| LogActivity (cache hit Redis, job async) | 2-5ms |
| **Total** | **28-93ms** ✅ |

### Escenario B — Desarrollo/producción con CACHE_DRIVER=file, QUEUE_CONNECTION=sync, geoIP cacheado

| Componente | Overhead |
|---|---|
| DynamicCorsMiddleware (file cache hit) | 10-50ms |
| TenantMiddleware (file cache hit + reconnect + Log::info) | 45-170ms |
| auth:sanctum | 5-20ms |
| Lógica de negocio (GET simple) | 5-30ms |
| LogActivity (file cache hit + User-Agent) | 15-60ms |
| StoreActivityLogJob (síncrono: reconexión + INSERT) | 35-120ms |
| **Total** | **115-450ms** ⚠️ |

### Escenario C — CACHE_DRIVER=file con fallo de permisos Docker (geoIP sin cache)

| Componente | Overhead |
|---|---|
| DynamicCorsMiddleware | 10-50ms |
| TenantMiddleware | 45-170ms |
| auth:sanctum | 5-20ms |
| Lógica de negocio (GET simple) | 5-30ms |
| LogActivity GeoIP HTTP externo | **200-3000ms** |
| StoreActivityLogJob (síncrono) | 35-120ms |
| **Total** | **300-3390ms** 🚨 |

### Escenario D — DB_HOST apuntando a servidor remoto/producción

Si el `.env` local tiene `DB_HOST=94.143.137.84` (copiado de `.env.example`), cada query TCP tiene la latencia de red al VPS de IONOS:

| Efecto | Impacto |
|---|---|
| Cada `DB::reconnect` | +20-100ms (round-trip red) |
| Cada query Eloquent | +5-30ms adicionales |
| Con 2 reconnects + 5-10 queries por request | **+100-800ms** por request |

**Escenario C + D combinado** → 3-4 segundos consistentes. **Esta es la combinación más probable que explica el síntoma reportado.**

---

## 4. Verificación: ¿Cuál es tu escenario actual?

Ejecuta estos comandos para diagnosticar:

```bash
# 1. ¿Cuál es tu CACHE_DRIVER actual?
php artisan tinker --execute="echo config('cache.default');"

# 2. ¿El cache de geoIP está funcionando?
php artisan tinker --execute="echo Cache::has('geoip:127.0.0.1') ? 'cached' : 'NO cached';"

# 3. ¿A qué host se conecta la BD?
php artisan tinker --execute="echo config('database.connections.tenant.host');"

# 4. ¿Cuál es el QUEUE_CONNECTION?
php artisan tinker --execute="echo config('queue.default');"

# 5. Medir queries por request (añadir temporalmente en routes/api.php o AppServiceProvider)
DB::listen(function($query) { Log::debug("Query: {$query->sql} [{$query->time}ms]"); });
```

---

## 5. Impacto de los Nuevos Módulos

Los nuevos módulos no causan el overhead fijo de 3-4 segundos, pero añaden carga sobre él en sus propios endpoints:

### 5.1 EnsureExternalUserIsActive — $user->refresh() sin cache

```
app/Http/Middleware/EnsureExternalUserIsActive.php:22
```

```php
if ($user instanceof ExternalUser) {
    $user->refresh(); // Query SELECT en CADA request de usuario externo
}
```

Para cada request del canal operativo (repartidores, usuarios externos), añade una query extra sin ningún cache. Impacto: +5-20ms sobre conexión local, +20-80ms si la BD es remota.

**Fix**: Cache del estado activo por 60-300 segundos por usuario.

### 5.2 N+1 en CrmAgendaService.filterActionsForCommercial

```
app/Services/v2/CrmAgendaService.php:455-492
```

```php
// 2 queries separadas para filtrar en PHP lo que puede ser 1 query con JOIN
$allowedProspectIds = Prospect::query()
    ->whereIn('id', $prospectIds)
    ->where('salesperson_id', $salespersonId)
    ->pluck('id');  // Query 1

$allowedCustomerIds = Customer::query()
    ->whereIn('id', $customerIds)
    ->where('salesperson_id', $salespersonId)
    ->pluck('id');  // Query 2
```

```
app/Services/v2/CrmAgendaService.php:495-527
```

```php
// 2 queries más para obtener nombres
$prospectLabels = Prospect::query()->whereIn('id', ...)->pluck('company_name', 'id'); // Query 3
$customerLabels = Customer::query()->whereIn('id', ...)->pluck('name', 'id');         // Query 4
```

`filterActionsForCommercial` + `mapActionsToCalendarItems` = mínimo 4 queries extra en cada llamada a `listCalendar()`, `summary()` y `CrmDashboardService::getData()`.

### 5.3 N+1 en ProspectService — latestOfMany()

```
app/Services/v2/ProspectService.php:23
```

```php
->with(['country', 'salesperson', 'customer', 'primaryContact', 'latestInteraction', 'offers'])
```

La relación `latestInteraction` usa `hasOne()->latestOfMany()`. En Laravel, `latestOfMany()` se implementa como una subquery correlacionada. Para una lista de 20 prospects, esto ejecuta 1 query principal + 20 subqueries individuales = **N+1 efectivo**.

### 5.4 ProspectController.show() — carga masiva sin paginar

```
app/Http/Controllers/v2/ProspectController.php:37-38
```

```php
$prospect = Prospect::with([
    'country', 'salesperson', 'customer', 'contacts', 'primaryContact',
    'latestInteraction.salesperson',
    'interactions.salesperson',      // TODAS las interacciones, sin límite
    'offers.lines.product',          // Todas las ofertas con todas sus líneas
    'offers.lines.tax'
])->findOrFail($id);
```

Un prospect con 50 interacciones y 10 ofertas de 30 líneas cada una: 300 `OfferLine` + 50 `CommercialInteraction` cargados en memoria en un solo request.

### 5.5 Índices faltantes en tablas nuevas

Los filtros frecuentes en los nuevos módulos no tienen índices compuestos:

| Tabla | Columnas sin índice | Endpoint afectado |
|---|---|---|
| `prospects` | `(salesperson_id, next_action_at)` | Lista prospects ordenada |
| `prospects` | `(status)` | Filtro por estado |
| `offers` | `(prospect_id, status)` | Ofertas por prospect |
| `offers` | `(customer_id, status)` | Ofertas por cliente |
| `agenda_actions` | `(target_type, target_id, status)` | Acciones por target |
| `prospect_contacts` | `(prospect_id, is_primary)` | Contacto principal |

Sin estos índices, cada filtro en una tabla con >1000 registros realiza un full table scan.

---

## 6. Plan de Acción Priorizado

### URGENTE — Quick Wins (< 2 horas, impacto inmediato en todos los endpoints)

#### QW1 — Confirmar y corregir CACHE_DRIVER y QUEUE_CONNECTION en todos los entornos

**`.env` local y `.env` de producción deben tener:**
```
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
```

Si Redis no está disponible en local, como mínimo confirmar que el file cache no tiene problemas de permisos:
```bash
php artisan cache:clear
# Si falla → problema de permisos en storage/framework/cache/
chmod -R 775 storage/framework/cache/
```

**Impacto estimado al corregir**: -200 a -3000ms por request (elimina geoIP HTTP en cada request y hace el job asíncrono).

#### QW2 — Verificar DB_HOST en .env local

```bash
grep DB_HOST .env
```

Si apunta a una IP remota (94.143.137.84), cambiar a:
```
DB_HOST=mysql   # Dentro de Docker Sail
# o
DB_HOST=127.0.0.1  # Fuera de Docker
```

**Impacto estimado**: -100 a -800ms por request (elimina latencia de red en cada query y reconexión).

#### QW3 — Desactivar Log::info en TenantMiddleware fuera de debugging activo

**`app/Http/Middleware/TenantMiddleware.php`** — El bloque de debug (líneas 54-58) puede dejarse como está siempre que `APP_DEBUG=false` en producción. En desarrollo, si `APP_DEBUG=true` y el canal de log usa archivo, cada request escribe en disco.

Confirmar en producción: `APP_DEBUG=false`.

**Impacto estimado**: -5 a -20ms por request en entornos con debug activo.

---

### ALTA PRIORIDAD — Fixes de arquitectura (< 1 día, impacto estructural)

#### FIX1 — Eliminar DB::purge + DB::reconnect del StoreActivityLogJob

```
app/Jobs/StoreActivityLogJob.php:30-32
```

El job recibe `$tenantDatabase` como constructor arg. La reconexión que hace es redundante cuando la cola es Redis (el worker tiene su propia conexión). Si la cola es sync, la reconexión destruye la conexión que TenantMiddleware ya estableció para el request.

**Solución recomendada**: El job no necesita `DB::purge + DB::reconnect`. Debe simplemente configurar el nombre de la BD y dejar que Eloquent establezca la conexión cuando la necesite:

```php
// En lugar de purge+reconnect explícito, solo configurar y dejar que Eloquent conecte lazy
config(['database.connections.tenant.database' => $this->tenantDatabase]);
DB::connection('tenant')->reconnect(); // Solo si la conexión actual apunta a otra BD
```

O aún mejor: pasar la conexión ya establecida, no reconectar.

#### FIX2 — Condicionar DB::purge + DB::reconnect en TenantMiddleware

```
app/Http/Middleware/TenantMiddleware.php:45-52
```

Solo hacer `purge + reconnect` si la BD del tenant es diferente a la conexión actual:

```php
$currentDatabase = config('database.connections.tenant.database');
if ($currentDatabase !== $tenant->database) {
    config(['database.connections.tenant.database' => $tenant->database]);
    DB::purge('tenant');
    DB::reconnect('tenant');
}
```

En PHP-FPM, cada worker maneja un solo request, por lo que `$currentDatabase` siempre será vacío al inicio. Esto no ayuda en PHP-FPM pero sí en entornos que reusen workers (Octane, Swoole).

**Impacto real en PHP-FPM**: cero (el purge siempre es necesario). La verdadera solución es migrar a `QUEUE_CONNECTION=redis` para que el job no haga el segundo purge.

#### FIX3 — Cache del estado activo en EnsureExternalUserIsActive

```
app/Http/Middleware/EnsureExternalUserIsActive.php:21-23
```

```php
// Reemplazar $user->refresh() con cache de 60s
if ($user instanceof ExternalUser) {
    $isActive = Cache::remember(
        "external_user_active:{$user->id}",
        60,
        fn () => ExternalUser::find($user->id)?->is_active ?? false
    );
    // Usar $isActive en lugar de $user->is_active
}
```

**Impacto**: Elimina 1 query por request en endpoints de canal operativo.

---

### MEDIO PLAZO — Optimizaciones de nuevos módulos (1-3 días)

#### OPT1 — Refactorizar CrmAgendaService para reducir queries

**`app/Services/v2/CrmAgendaService.php:455-527`**

Consolidar las 4 queries de `filterActionsForCommercial` + `mapActionsToCalendarItems` en una operación con JOIN sobre `agenda_actions`:

```php
// En lugar de cargar actions y luego hacer 4 queries separadas:
AgendaAction::query()
    ->where(function ($q) use ($salespersonId) {
        $q->where(function ($q2) use ($salespersonId) {
            $q2->where('target_type', 'prospect')
               ->whereHas('prospect', fn ($p) => $p->where('salesperson_id', $salespersonId));
        })->orWhere(function ($q2) use ($salespersonId) {
            $q2->where('target_type', 'customer')
               ->whereHas('customer', fn ($c) => $c->where('salesperson_id', $salespersonId));
        });
    })
    ->with(['prospect:id,company_name', 'customer:id,name'])
    ->get();
```

#### OPT2 — Resolver N+1 de latestInteraction en ProspectService

**`app/Models/Prospect.php`** — la relación `latestInteraction` con `latestOfMany()`:

```php
// Alternativa sin N+1: subquery eager loading
public function latestInteraction(): HasOne
{
    return $this->hasOne(CommercialInteraction::class)
        ->ofMany('occurred_at', 'max');  // Mismo resultado, más eficiente en eager load conjunto
}
```

La función `ofMany('occurred_at', 'max')` de Laravel genera un JOIN en lugar de subqueries correlacionadas cuando se usa con `with()`, eliminando el N+1.

#### OPT3 — Paginar interactions y offers en ProspectController.show()

**`app/Http/Controllers/v2/ProspectController.php:37-38`**

No cargar colecciones ilimitadas en el detail view:

```php
$prospect = Prospect::with([
    'country', 'salesperson', 'customer', 'contacts', 'primaryContact',
    'latestInteraction.salesperson',
    // Eliminar 'interactions.salesperson' — servir paginado desde endpoint dedicado
    // Eliminar 'offers.lines.product' — servir paginado desde endpoint dedicado
])->findOrFail($id);
```

Las interacciones y ofertas deben tener sus propios endpoints paginados.

#### OPT4 — Añadir todos los índices faltantes (migración única)

```php
// prospects
Schema::table('prospects', function (Blueprint $table) {
    $table->index(['salesperson_id', 'next_action_at']);
    $table->index('status');
    $table->index('country_id');
    $table->index('customer_id');  // convertToCustomer() + búsqueda inversa
});

// offers
Schema::table('offers', function (Blueprint $table) {
    $table->index(['prospect_id', 'status']);
    $table->index(['customer_id', 'status']);
    $table->index(['salesperson_id', 'status']);
});

// commercial_interactions
Schema::table('commercial_interactions', function (Blueprint $table) {
    $table->index(['prospect_id', 'occurred_at']);   // latestOfMany() + lista por prospect
    $table->index(['customer_id', 'occurred_at']);   // latestOfMany() + lista por cliente
});

// agenda_actions
Schema::table('agenda_actions', function (Blueprint $table) {
    $table->index(['target_type', 'target_id', 'status']);
    $table->index(['status', 'scheduled_at']);        // summary() overdue/today/next
    $table->index(['salesperson_id', 'scheduled_at']);
});

// prospect_contacts
Schema::table('prospect_contacts', function (Blueprint $table) {
    $table->index(['prospect_id', 'is_primary']);
});

// route_templates
Schema::table('route_templates', function (Blueprint $table) {
    $table->index(['salesperson_id', 'is_active']);
});

// route_stops
Schema::table('route_stops', function (Blueprint $table) {
    $table->index(['route_id', 'status']);
});
```

#### OPT5 — Refactorizar summary() para aplicar scope en query, no en memoria

```
app/Services/v2/CrmAgendaService.php:67-99
```

Añadir el `salesperson_id` directamente en `baseQueryForSummary()`:

```php
private static function baseQueryForSummary(Authenticatable $user, array $status): Builder
{
    $query = AgendaAction::query()->whereIn('status', $status);

    if ($user instanceof User && $user->hasRole(Role::Comercial->value) && $user->salesperson) {
        $sid = $user->salesperson->id;
        $query->where(function ($q) use ($sid) {
            $q->where(function ($q2) use ($sid) {
                $q2->where('target_type', 'prospect')
                   ->whereHas('prospect', fn ($p) => $p->where('salesperson_id', $sid));
            })->orWhere(function ($q2) use ($sid) {
                $q2->where('target_type', 'customer')
                   ->whereHas('customer', fn ($c) => $c->where('salesperson_id', $sid));
            });
        });
    }

    return $query;
}
```

Esto elimina `filterActionsForCommercial()` de `summary()` y reduce las queries de 15 a 5 (3 loads + 2 para mapActionsToCalendarItems).

#### OPT6 — Cache del FieldOperator lookup por request

```
app/Http/Controllers/v2/FieldOrderController.php:19-28
```

Usar `request()` como scope de una sola carga:

```php
private function getCurrentFieldOperatorId(Request $request): int
{
    $user = $request->user();
    $user->loadMissing('fieldOperator');  // Solo carga si no está ya en memoria
    $fieldOperatorId = $user->fieldOperator?->id;
    abort_unless($fieldOperatorId !== null, 403, '...');
    return $fieldOperatorId;
}
```

`loadMissing()` evita el lazy load implícito y no hace query si la relación ya estaba cargada.

#### OPT7 — DeliveryRouteWriteService: bulk insert de stops

```
app/Services/v2/DeliveryRouteWriteService.php
```

Reemplazar el loop de `create()` con una sola operación bulk:

```php
// En lugar de N creates individuales:
$stopsData = collect($stops)->map(fn ($stop, $i) => [
    'route_id'    => $route->id,
    'position'    => $i + 1,
    'customer_id' => $stop['customerId'],
    // ... resto de campos
])->all();

$route->stops()->createMany($stopsData);  // Un solo INSERT multi-row
```

#### OPT8 — Precargar pallets en FieldOrderController.index()

```
app/Http/Controllers/v2/FieldOrderController.php:34-39
```

`totalBoxes` y `totalNetWeight` de `FieldOrderResource` necesitan pallets eager-loaded:

```php
->with([
    'customer:id,name',
    'plannedProductDetails.product',
    'plannedProductDetails.tax',
    'pallets.boxes.box.productionInputs',  // Elimina el N+1 cascada
])
```

O bien, sustituir los computed properties por una query de agregación directa en el controller para evitar cargar todos los modelos anidados.

#### OPT9 — OfferService: eliminar fresh() duplicado y doble carga de prospect

```
app/Services/v2/OfferService.php:136, 156, 175, 195, 349
```

Extraer las relaciones a una constante privada y en `syncProspectStatus()` eliminar el `fresh()` redundante sobre un prospect ya cargado:

```php
private const FULL_RELATIONS = [
    'prospect.country', 'prospect.primaryContact', 'customer.country',
    'salesperson', 'incoterm', 'paymentTerm', 'order', 'lines.product', 'lines.tax',
];

// En syncProspectStatus línea 349 — el prospect ya está en memoria:
$prospect = $offer->prospect;  // No fresh(), ya cargado por loadMissing()
```

Reduce de ~14 a ~8 queries por cambio de estado de oferta.

#### OPT10 — Activar route cache

```bash
php artisan route:cache
```

Verifica primero que no hay closures en `routes/api.php`. Si las hay, convertirlas a `[Controller::class, 'method']`. Añadir al proceso de deploy.

**Impacto**: -10 a -30ms por request.

---

## 7. Resumen de Impacto Estimado

| Acción | Overhead eliminado | Dificultad | Hallazgos |
|---|---|---|---|
| QW1: CACHE_DRIVER=redis + QUEUE_CONNECTION=redis | -200 a -3000ms | Muy baja (config) | H1, H2 |
| QW2: Corregir DB_HOST en .env local | -100 a -800ms | Muy baja (config) | H6 |
| QW3: APP_DEBUG=false en producción | -5 a -20ms | Muy baja (config) | H7 |
| FIX1: Eliminar reconexión en StoreActivityLogJob | -30 a -100ms | Baja (5 líneas) | H4 |
| FIX2: Cache estado EnsureExternalUserIsActive | -5 a -80ms (ext. users) | Baja (10 líneas) | H8 |
| OPT1: Refactorizar CrmAgendaService (scope en query) | -50 a -500ms (CRM) | Media | H9, H10 |
| OPT2: latestOfMany() → ofMany() | -20 a -200ms (prospects) | Baja | H14 |
| OPT3: Paginar show() prospect | -50 a -500ms (CRM) | Baja | H15 |
| OPT4: Índices en todas las tablas nuevas | -10 a -200ms (CRM) | Baja (migración) | H17-H24 |
| OPT5: summary() scope en query | -100 a -800ms (agenda) | Media | H9 |
| OPT6: loadMissing('fieldOperator') | -5 a -20ms (canal op.) | Muy baja (1 línea) | H12 |
| OPT7: DeliveryRoute bulk insert | -10 a -100ms (rutas) | Baja | H16 |
| OPT8: Precargar pallets en FieldOrderController | -50 a -500ms (canal op.) | Baja (1 línea) | H25 |
| OPT9: OfferService fresh() optimizado | -30 a -150ms (CRM) | Baja | H26, H27 |
| OPT10: Activar route cache | -10 a -30ms (todos) | Muy baja (1 comando) | H28 |
| **Total QW1+QW2 solos** | **-300 a -3800ms** | **< 5 minutos** | — |
| **Total todos los fixes** | **-650 a -5200ms** | — | — |

**Los quick wins QW1 y QW2 son puro cambio de configuración y deberían resolver el 80-90% del problema reportado.**

---

## 8. Cómo Verificar el Antes/Después

```bash
# 1. Medir tiempo de respuesta actual en un endpoint simple
time curl -s -o /dev/null -w "%{time_total}" \
  -H "X-Tenant: {tu_subdomain}" \
  -H "Authorization: Bearer {token}" \
  https://api.lapesquerapp.es/api/v2/countries

# 2. Contar queries por request (añadir temporalmente en AppServiceProvider::boot())
if (config('app.debug')) {
    DB::listen(function ($query) {
        Log::channel('stderr')->debug(
            sprintf('[%sms] %s', round($query->time, 2), $query->sql)
        );
    });
}

# 3. Verificar que el cache de geoIP persiste (con CACHE_DRIVER=redis)
php artisan tinker
> Cache::put('test_key', 'test_value', 60);
> Cache::get('test_key'); // Debe devolver 'test_value'

# 4. Confirmar que el job de actividad es asíncrono
# En logs del queue worker: debe aparecer "Processed: App\Jobs\StoreActivityLogJob"
# Si el worker no está activo y QUEUE_CONNECTION=redis: los logs no se guardan

# 5. Objetivo final: P95 < 200ms en endpoints CRUD simples bajo 1 usuario
```

---

## 9. Impacto en Rating por Bloque

| Bloque | Rating anterior | Rating con QW1+QW2 | Rating con todos los fixes |
|---|---|---|---|
| A.17 Infraestructura API | 9/10 (documentado) | 9/10 ✅ | 9.5/10 |
| A.19 CRM | 8.5/10 | 8.5/10 | 9/10 |
| A.20 Canal operativo | 8.5/10 | 8.5/10 | 9/10 |
| A.21 Usuarios externos | 8.5/10 | 9/10 | 9/10 |

---

---

## 10. Tercera Pasada — Hallazgos Adicionales (Segunda Revisión)

### 10.A FieldOrderResource — N+1 en cascada: pallets → boxes → productionInputs

```
app/Http/Resources/v2/FieldOrderResource.php:27-28
app/Models/Order.php:356-399
```

`FieldOrderResource` accede a dos computed properties en cada orden serializada:

```php
'totalBoxes'     => $this->totalBoxes,       // Llama getTotalBoxesAttribute()
'totalNetWeight' => $this->totalNetWeight,    // Llama getTotalNetWeightAttribute()
```

`FieldOrderController::index()` carga:
```php
->with(['customer:id,name', 'plannedProductDetails.product', 'plannedProductDetails.tax'])
```

`pallets` **no está en el eager load**. Por tanto, para cada orden:

1. `$this->pallets` → lazy load de pallets (1 query por orden)
2. `getTotalNetWeightAttribute()` — para cada pallet sin `boxes` cargado: `$pallet->load('boxes.box.productionInputs')` (1 query por pallet)
3. Para cada box sin `productionInputs` cargado: `$palletBox->box->load('productionInputs')` (1 query por box)

Para una lista de 10 pedidos con 2 pallets y 10 cajas cada uno:
- 10 queries (pallets por pedido) + 20 queries (boxes por pallet) + 200 queries (productionInputs por caja)
= **hasta 230 queries extra por una lista paginada**.

Esto es el N+1 más grave de los nuevos módulos. Se activa en cada llamada a `GET /api/v2/field/orders`.

### 10.B OfferService — fresh() con 10 relaciones en 4 métodos idénticos

```
app/Services/v2/OfferService.php:136, 156, 175, 195
```

Cada cambio de estado de una oferta (send, accept, reject, expire) termina con:

```php
return $offer->fresh([
    'prospect.country', 'prospect.primaryContact',
    'customer.country', 'salesperson', 'incoterm',
    'paymentTerm', 'order', 'lines.product', 'lines.tax'
]);  // ~9 queries cada vez, 4 métodos = patrón idéntico × 4
```

Cada cambio de estado ejecuta:
- `$offer->update()` — 1 query
- `syncProspectStatus()` — hasta 4 queries (ver 10.C)
- `$offer->fresh([...])` — ~9 queries (10 relaciones en eager load)
- **Total por estado: ~14 queries**

Además, el mismo bloque de relaciones está duplicado literalmente en las 4 líneas (136, 156, 175, 195) — candidato obvio a una constante privada.

### 10.C OfferService.syncProspectStatus() — doble carga del prospect

```
app/Services/v2/OfferService.php:341-374
```

```php
private static function syncProspectStatus(Offer $offer): void
{
    $offer->loadMissing('prospect');         // Carga prospect si no está en memoria

    $prospect = $offer->prospect->fresh();   // ← DESCARTA lo que se acaba de cargar
                                             //    y hace una query nueva al prospect

    $hasActiveOffer = $prospect->offers()
        ->whereIn('status', [...])
        ->exists();                          // Query: ¿tiene ofertas activas?

    $prospect->interactions()->exists();     // Query extra si no hay oferta activa
}
```

El `fresh()` en línea 349 deshace el `loadMissing()` anterior. Además, `syncProspectStatus` es llamado antes del `$offer->fresh()` final de cada método de estado (líneas 134+136, 154+156, 173+175, 193+195), lo que significa que el prospect se recarga dos veces: una en `syncProspectStatus` y otra en el `fresh()` final.

### 10.D Sin route cache — 430+ rutas compiladas en cada request

```
bootstrap/cache/routes-v7.php — NO EXISTE
```

Con más de 430 rutas registradas en `routes/api.php`, Laravel compila el árbol de rutas en cada request bootstrap. Esto consume CPU (regex compilation) y añade ~10-30ms de overhead.

```bash
# Verificar
php artisan route:cache
# Genera bootstrap/cache/routes-v7.php
```

**Nota**: El route cache puede causar problemas con bindings dinámicos o closures. Verificar que todas las rutas usen strings (controller@method) antes de activar.

### 10.E Queue worker único — bottleneck bajo carga

```
docker-compose.yml
```

```yaml
queue:
  command: php artisan queue:work --verbose --tries=3 --backoff=5 --sleep=3
  # Sin --queue ni concurrencia: un solo worker, una cola
```

Con un único worker y `QUEUE_CONNECTION=redis`:
- `StoreActivityLogJob` se encola en cada request autenticado
- Bajo carga: la cola crece más rápido de lo que el worker la procesa
- Los logs de actividad se retrasan, y si la cola tiene jobs viejos, los nuevos esperan

Para producción se recomiendan al menos 2-3 workers con prioridad separada para la cola `logging`.

### 10.F SESSION_DRIVER y config de cookies inseguras para producción

```
config/session.php:21, 177, 206
```

```php
'driver' => env('SESSION_DRIVER', 'array'),  // Sesiones solo en memoria
'same_site' => 'none',                        // ← "Para localhost" (comentario en línea 206)
'secure' => env('SESSION_SECURE_COOKIE', false),
```

`same_site: 'none'` con `secure: false` en producción es un riesgo CSRF. Aunque la API usa Sanctum (sin cookies de sesión), el comentario indica que este valor fue puesto para localhost y podría haberse propagado a producción. Verificar en `.env` de producción.

---

## 11. Segunda Pasada — Hallazgos Adicionales

### 10.1 CrmAgendaService.summary() — hasta 15 queries por llamada (comercial)

```
app/Services/v2/CrmAgendaService.php:67-99
```

`summary()` llama a `baseQueryForSummary()` tres veces (overdue, today, next), cargando cada resultado sin scope de salesperson en la query. El scope se aplica **después** del `.get()` via `filterActionsForCommercial()`:

```php
// Líneas 73-93: 3 loads + 3 × filterActionsForCommercial
$overdue   = self::baseQueryForSummary($user, ['pending'])->whereDate(...)->get();   // 1 query
$todayItems = self::baseQueryForSummary($user, ['pending'])->whereDate(...)->get();  // 1 query
$next      = self::baseQueryForSummary($user, ['pending'])->whereDate(...)->limit(10)->get(); // 1 query

// Para usuario comercial, 3 veces filterActionsForCommercial():
// Cada llamada: 2 queries (Prospect + Customer whereIn)
// Total filterActions: 3 × 2 = 6 queries

// 3 veces mapActionsToCalendarItems():
// Cada llamada: 2 queries (Prospect labels + Customer labels)
// Total mapActions: 3 × 2 = 6 queries
```

**Total para un usuario comercial: 3 + 6 + 6 = 15 queries por llamada a `summary()`.**

Para `$overdue` no hay LIMIT. Un comercial con 50 acciones vencidas carga todos en memoria antes de filtrar por salesperson.

El comentario en `baseQueryForSummary()` (línea 448) dice "el dataset por summary es pequeño: hoy + próximos", pero `$overdue` no tiene ese límite.

### 10.2 CrmAgendaService.listCalendar() — carga sin LIMIT + 5 queries

```
app/Services/v2/CrmAgendaService.php:37-62
```

```php
$actions = $query->get(); // Sin LIMIT — carga TODAS las acciones del rango de fechas
```

Para una vista mensual de un tenant con 500 acciones de agenda por mes: 500 registros en memoria. Luego:
- `filterActionsForCommercial()`: 2 queries más
- `mapActionsToCalendarItems()`: 2 queries más

**Total: 5 queries + carga completa sin paginar.**

La solución correcta es añadir el scope `salesperson_id` directamente en la query (no post-carga), usando `whereHas` o un JOIN sobre la tabla `prospects`/`customers`.

### 10.3 ProspectService.detectDuplicates() — 6 queries con LIKE en texto libre, en cada store/update

```
app/Services/v2/ProspectService.php:357-438
```

Se llama síncronamente en `store()` y `update()`. Ejecuta:

```php
// Por nombre de empresa: 2 queries con LOWER() — no usa índice
Prospect::whereRaw('LOWER(company_name) = ?', [...]);    // Query 1
Customer::whereRaw('LOWER(name) = ?', [...]);             // Query 2

// Por email: 2 queries, una con LIKE sobre campo texto
ProspectContact::whereRaw('LOWER(email) = ?', [...]);     // Query 3 (usable con índice)
Customer::where('emails', 'like', '%'.$email.'%');        // Query 4 — FULL TABLE SCAN

// Por teléfono: 2 queries, una con LIKE sobre campo texto
ProspectContact::where('phone', $phone);                  // Query 5
Customer::where('contact_info', 'like', '%'.$phone.'%'); // Query 6 — FULL TABLE SCAN
```

Las queries 4 y 6 hacen **full table scan** sobre `customers.emails` y `customers.contact_info` (campos de texto con `LIKE '%...'`). Con 5000 clientes, son las queries más lentas del módulo CRM.

### 10.4 FieldOrderController — doble lookup de FieldOperator por request

```
app/Http/Controllers/v2/FieldOrderController.php:19-28
```

```php
private function getCurrentFieldOperatorId(Request $request): int
{
    $user = $request->user();
    // Intenta cargar relación (lazy load si no está eager-loaded = query)
    $fieldOperatorId = $user?->fieldOperator?->id
        // Si null, fallback = otra query
        ?? ($user ? FieldOperator::query()->where('user_id', $user->id)->value('id') : null);
}
```

Sanctum carga el User sin `fieldOperator` eager-loaded. Por tanto, `$user->fieldOperator` es siempre un lazy load = query. La relación devuelve un objeto (no null), así que el `??` nunca activa el fallback. Resultado: **1 query extra en cada request de canal operativo**. Este mismo patrón está en `FieldRouteController` y `FieldCustomerController`.

### 10.5 FieldOrderController.storeAutoventa() — order recargado completo tras creación

```
app/Http/Controllers/v2/FieldOrderController.php:86
```

```php
$order = OrderStoreService::store($validated, $request->user()); // Crea el pedido
$order = OrderDetailService::getOrderForDetail((string) $order->id); // Recarga con 30+ relaciones
```

Inmediatamente después de crear el pedido, se recarga con `OrderDetailService` que carga 30+ relaciones (según `OrderDetailService::getOrderForDetail`). Para una autoventa simple esto es un overhead significativo innecesario.

### 10.6 DeliveryRouteWriteService — N INSERTs en loop para stops de ruta

```
app/Services/v2/DeliveryRouteWriteService.php
```

```php
foreach ($stops ?? [] as $stop) {
    $route->stops()->create([...]); // N queries para N stops
}
```

Para una ruta con 20 paradas: 20 INSERT individuales. Debería ser un único `createMany()` o `insert()` bulk.

### 10.7 Índices adicionales faltantes (confirmados por análisis de migraciones)

Más allá de los ya documentados, se confirman estos índices faltantes con impacto real:

| Tabla | Índice faltante | Causa |
|---|---|---|
| `commercial_interactions` | `(prospect_id, occurred_at)` | latestOfMany() + listado por prospect |
| `commercial_interactions` | `(customer_id, occurred_at)` | latestOfMany() + listado por cliente |
| `agenda_actions` | `(status, scheduled_at)` | summary() filtra por status+fecha sin índice |
| `route_templates` | `(salesperson_id, is_active)` | Listado de plantillas por comercial |
| `route_stops` | `(route_id, status)` | Filtro de paradas por estado en una ruta |
| `prospects` | `(customer_id)` | convertToCustomer() + búsqueda inversa |

---

## 11. Tabla de Queries por Endpoint (Inventario Completo)

| Endpoint | Queries base (middleware) | Queries lógica | Total estimado |
|---|---|---|---|
| Cualquier GET simple (autenticado) | 3 (reconnect×2 + auth) | 1-5 | **4-8** |
| `GET /api/v2/crm/agenda/calendar` (comercial) | 3 | 5 (1 main + 2 filter + 2 map) | **8** |
| `GET /api/v2/crm/agenda/summary` (comercial) | 3 | 15 (3 loads + 6 filter + 6 map) | **18** |
| `GET /api/v2/crm/dashboard` | 3 | 6-10 | **9-13** |
| `GET /api/v2/prospects` (lista) | 3 | 8 (1 + 7 relations) | **11** |
| `GET /api/v2/prospects/{id}` | 3 | 10-15 (todas las relaciones) | **13-18** |
| `POST /api/v2/prospects` | 3 | 7 (6 detectDuplicates + 1 INSERT) | **10** |
| `GET /api/v2/field/orders` (repartidor) | 3 + 1 (refresh) + 1 (fieldOp) | 3 | **8** |
| `POST /api/v2/field/orders/autoventa` | 3 + 1 (refresh) + 1 (fieldOp) | 35+ (create + reload) | **40+** |

La columna "queries base" asume QUEUE_CONNECTION=sync (1 reconnect en TenantMiddleware + 1 en el job + 1 auth token).

---

## 12. Tabla Maestra de Todos los Hallazgos

| # | Hallazgo | Archivo | Severidad | Afecta |
|---|---|---|---|---|
| H1 | QUEUE_CONNECTION=sync → job síncrono | `.env.example` | CRÍTICO | Todos los endpoints |
| H2 | CACHE_DRIVER=file → cache lento/falla silenciosa | `.env.example` | CRÍTICO | Todos los endpoints |
| H3 | DB::purge+reconnect incondicional en TenantMiddleware | `TenantMiddleware.php:51` | CRÍTICO | Todos los endpoints |
| H4 | Segunda DB::purge+reconnect en StoreActivityLogJob | `StoreActivityLogJob.php:31` | CRÍTICO | Todos (con sync queue) |
| H5 | MYSQL_ATTR_INIT_COMMAND SET time_zone en cada reconnect | `config/database.php:55` | ALTO | Todos los endpoints |
| H6 | DB_HOST con IP real en .env.example | `.env.example` | ALTO | Dev con config copiada |
| H7 | APP_DEBUG=true → Log::info en cada request | `TenantMiddleware.php:54` | ALTO | Dev/staging |
| H8 | $user->refresh() sin cache en EnsureExternalUserIsActive | `EnsureExternalUserIsActive.php:22` | ALTO | Canal operativo |
| H9 | summary() ejecuta hasta 15 queries (usuario comercial) | `CrmAgendaService.php:67` | ALTO | CRM agenda |
| H10 | listCalendar() sin LIMIT + 5 queries | `CrmAgendaService.php:54` | ALTO | CRM agenda |
| H11 | detectDuplicates() 6 queries con LIKE/FULLSCAN | `ProspectService.php:357` | ALTO | Crear/editar prospect |
| H12 | Lazy load fieldOperator en 3 controllers | `FieldOrderController.php:22` | MEDIO | Canal operativo |
| H13 | storeAutoventa() recarga pedido con 30+ relaciones | `FieldOrderController.php:86` | MEDIO | Autoventa |
| H14 | latestOfMany() N+1 en lista prospects | `ProspectService.php:23` | MEDIO | Lista CRM |
| H15 | Carga ilimitada interactions+offers en show() | `ProspectController.php:37` | MEDIO | Detalle prospect |
| H16 | DeliveryRoute stops: N INSERTs en loop | `DeliveryRouteWriteService.php` | MEDIO | Crear rutas |
| H17 | Índice faltante `commercial_interactions(prospect_id, occurred_at)` | Migración | MEDIO | Interacciones CRM |
| H18 | Índice faltante `commercial_interactions(customer_id, occurred_at)` | Migración | MEDIO | Interacciones CRM |
| H19 | Índice faltante `agenda_actions(status, scheduled_at)` | Migración | MEDIO | summary() |
| H20 | Índice faltante `offers(prospect_id, status)` | Migración | MEDIO | Lista ofertas |
| H21 | Índice faltante `offers(customer_id, status)` | Migración | MEDIO | Lista ofertas |
| H22 | Índice faltante `route_templates(salesperson_id, is_active)` | Migración | BAJO | Lista plantillas |
| H23 | Índice faltante `route_stops(route_id, status)` | Migración | BAJO | Paradas de ruta |
| H24 | Índice faltante `prospects(customer_id)` | Migración | BAJO | convertToCustomer() |
| H25 | FieldOrderResource: N+1 cascada pallets→boxes→productionInputs | `FieldOrderResource.php:27` | CRÍTICO | GET /field/orders |
| H26 | OfferService: `fresh()` con 10 relaciones en 4 métodos idénticos | `OfferService.php:136,156,175,195` | ALTO | Cambios de estado oferta |
| H27 | OfferService.syncProspectStatus(): doble carga del prospect | `OfferService.php:349` | ALTO | Cambios de estado oferta |
| H28 | Sin route cache — 430+ rutas compiladas en cada request | `bootstrap/cache/` ausente | MEDIO | Todos los endpoints |
| H29 | Queue worker único sin cola dedicada | `docker-compose.yml` | MEDIO | Cola de logs bajo carga |
| H30 | session.same_site=none en config (para localhost, puede propagarse) | `config/session.php:206` | BAJO | Seguridad en producción |

---

## Cambios Implementados (2026-03-24)

### Sistema ActivityLog — Eliminación completa

Se decidió eliminar el sistema ActivityLog en su totalidad (coste > beneficio para un ERP B2B con usuarios controlados). Los logs de acceso de Nginx cubren el nivel básico de auditoría; la detección de anomalías se implementará en el futuro de forma ligera y enfocada solo en eventos de auth.

**Hallazgos resueltos:**

| Hallazgo | Causa | Estado |
|---|---|---|
| H1 — GeoIP HTTP en cada request | `LogActivity` → `Location::get($ip)` con cache fallido | ✅ **Resuelto** — middleware eliminado |
| H2 — Segunda reconexión MySQL | `StoreActivityLogJob` con `QUEUE_CONNECTION=sync` | ✅ **Resuelto** — job eliminado |
| H4 — `LogActivity` en grupo `api` global | `Kernel.php` grupo `api` | ✅ **Resuelto** — eliminado de Kernel |

**Archivos eliminados (9):**
- `app/Http/Middleware/LogActivity.php`
- `app/Jobs/StoreActivityLogJob.php`
- `app/Models/ActivityLog.php`
- `app/Policies/ActivityLogPolicy.php`
- `app/Http/Controllers/v2/ActivityLogController.php`
- `app/Http/Requests/v2/IndexActivityLogRequest.php`
- `app/Http/Resources/v2/ActivityLogResource.php`
- `database/migrations/companies/2025_01_11_215159_create_activity_logs_table.php`
- `database/migrations/companies/2025_01_12_211945_update_activity_logs_table.php`

**Archivos modificados (4):**
- `app/Http/Kernel.php` — eliminado del grupo `api` y `web`
- `app/Providers/AuthServiceProvider.php` — eliminado del registro de policies
- `app/Models/User.php` — eliminado método `activityLogs()` (relación hasMany)
- `routes/api.php` — eliminado `use` y `Route::apiResource('activity-logs', ...)`

**Migración de limpieza creada:**
- `database/migrations/companies/2026_03_24_000000_drop_activity_logs_table.php` — `Schema::dropIfExists('activity_logs')` para limpiar la tabla en todas las BDs tenant

**Impacto esperado:**
- Eliminación del overhead de 200-3000ms por request (GeoIP HTTP)
- Eliminación de la segunda reconexión MySQL + `SET time_zone` por request
- Reducción de tiempo de respuesta en endpoints simples a < 200ms (dependiendo de DB_HOST y CACHE_DRIVER en `.env`)

**Pendiente de verificar en producción:** Confirmar que `CACHE_DRIVER=redis` y `QUEUE_CONNECTION=redis` están configurados en `.env` de producción para completar la resolución de H3.

---

## Evidencia Consultada

| Archivo | Hallazgo |
|---|---|
| `app/Http/Middleware/TenantMiddleware.php:51-52` | DB::purge + reconnect incondicional |
| `app/Http/Middleware/LogActivity.php:29-37` | GeoIP con file cache, posiblemente sin persistencia |
| `app/Http/Middleware/LogActivity.php:68` | dispatch() síncrono con QUEUE_CONNECTION=sync |
| `app/Jobs/StoreActivityLogJob.php:31-32` | Segunda reconexión MySQL dentro del request |
| `config/database.php:55` | MYSQL_ATTR_INIT_COMMAND SET time_zone en cada reconnect |
| `.env.example` | CACHE_DRIVER=file, QUEUE_CONNECTION=sync, DB_HOST real expuesto |
| `app/Http/Kernel.php:44` | LogActivity en grupo api (todos los endpoints) |
| `app/Http/Middleware/EnsureExternalUserIsActive.php:22` | $user->refresh() sin cache |
| `app/Services/v2/CrmAgendaService.php:54` | listCalendar() sin LIMIT |
| `app/Services/v2/CrmAgendaService.php:73-93` | summary() 15 queries por llamada en usuario comercial |
| `app/Services/v2/CrmAgendaService.php:455-527` | 4 queries separadas en filter+map |
| `app/Services/v2/ProspectService.php:23` | latestOfMany() N+1 |
| `app/Services/v2/ProspectService.php:357-438` | detectDuplicates() 6 queries con LIKE/fullscan |
| `app/Http/Controllers/v2/ProspectController.php:37-38` | Carga ilimitada de interactions y offers |
| `app/Http/Controllers/v2/FieldOrderController.php:19-28` | Lazy load fieldOperator en cada request |
| `app/Http/Controllers/v2/FieldOrderController.php:86` | Recarga pedido con 30+ relaciones tras creación |
