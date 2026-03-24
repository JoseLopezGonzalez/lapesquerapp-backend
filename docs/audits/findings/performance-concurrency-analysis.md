# Análisis de Rendimiento bajo Carga Concurrente — PesquerApp Backend
**Fecha**: 2026-03-24 | **Auditoría**: Principal Backend v2

---

## 1. Modelo de Concurrencia Actual

### PHP-FPM (Laravel Sail / Coolify / IONOS VPS)

El proyecto usa Laravel Sail con imagen `sail-8.3/app`. No existe un archivo `php-fpm.conf` personalizado en el repositorio; por lo tanto, en producción se aplican los valores por defecto de Sail/Docker PHP-FPM:

| Parámetro | Valor por defecto (Sail) | Recomendado (VPS 4-8GB RAM) |
|---|---|---|
| `pm` | `dynamic` | `dynamic` |
| `pm.max_children` | ~20 | 30-50 según RAM |
| `pm.start_servers` | 2 | 10 |
| `pm.min_spare_servers` | 1 | 5 |
| `pm.max_spare_servers` | 3 | 15 |
| `pm.max_requests` | 0 (infinito) | 500 |
| `request_terminate_timeout` | 0 (infinito) | 60s |

**Sin `pm.max_requests` configurado**, los workers PHP acumulan memoria indefinidamente. Un worker que haya procesado 1000 requests puede consumir 150-300MB por fugas lentas (especialmente con Browsershot/Snappy que lanzan procesos externos).

### Relación Workers ↔ MySQL max_connections

Con arquitectura multi-tenant database-per-tenant, el pico de conexiones MySQL es:

```
conexiones_pico = pm.max_children × (1 app + 1 queue) + N_tenants_activos
```

Ejemplo: 20 workers FPM + 5 queue workers + 3 tenants activos simultáneos = **28 conexiones** como mínimo. El valor por defecto de MySQL (`max_connections = 151`) es suficiente en este escenario, pero si el VPS corre Coolify + MySQL + múltiples tenants con tráfico real, se puede acercar al límite.

**Gap detectado**: No hay config explícita de `innodb_buffer_pool_size` en el docker-compose ni en `docker/mysql/`. El valor por defecto es **128MB**, inadecuado para un ERP con tablas de producción, boxes y pallets con miles de filas.

---

## 2. Tabla de Endpoints Críticos — Estimación P50/P95 bajo Carga

Estimación basada en análisis estático del código (queries, relaciones, middleware overhead). Los valores asumen una BD con datos realistas (~1000 órdenes, ~500 pallets, ~5000 boxes).

| Endpoint | Queries aprox. | P50 estimado | P95 bajo 20 usuarios | Riesgo concurrencia |
|---|---|---|---|---|
| `GET /api/v2/orders?active=true` | 2 (orders + eager customers) | 80ms | 400ms | MEDIO — `.get()` sin paginar |
| `GET /api/v2/orders` (paginado) | 3-5 | 60ms | 200ms | BAJO |
| `GET /api/v2/pallets` (paginado) | 4-8 (with boxes.box.product) | 100ms | 350ms | MEDIO |
| `GET /api/v2/punches/dashboard` | 10-15 (stats por empleado) | 150ms | 600ms | MEDIO |
| `GET /api/v2/punches/statistics` | 15-25 (agrupaciones fecha) | 200ms | 900ms | ALTO |
| `GET /api/v2/productions` (paginado) | 5-10 | 80ms | 300ms | BAJO |
| `POST /api/v2/punches` (NFC) | 3-5 + transaction | 50ms | 250ms | **ALTO** — race condition |
| `GET /api/v2/orders/statistics/*` | 20-40 (GROUP BY, JOIN) | 300ms | 1200ms | ALTO — sin cache |
| `GET /excel/orders` (export) | 50-200 (sin paginación) | 3000ms | 15000ms | **CRÍTICO** — bloquea worker |
| `GET /pdf/orders/*/order-sheet` | CPU + I/O PDF | 800ms | 4000ms | ALTO — Browsershot |
| `POST /api/v2/auth/magic-link/verify` | 2 | 30ms | 150ms | **ALTO** — TOCTOU race |

**Overhead base por request** (no incluido arriba):
- `LogActivity` middleware: +50ms a +500ms (llamada HTTP externa a ip2location.io) — **DOMINANTE**
- `TenantMiddleware` DB lookup: +5-15ms (sin cache)
- `TenantMiddleware` SET time_zone: +2-5ms

---

## 3. Hallazgos de Race Conditions

### RC1 — Magic Link / OTP: Token consumption no atómica (CRÍTICO - seguridad)

**Archivo**: `app/Http/Controllers/v2/AuthController.php:104-127`
**Archivo**: `app/Models/MagicLinkToken.php:45-48`

```php
// AuthController.php — verifyMagicLink()
$record = MagicLinkToken::valid()->magicLink()->where('token', $hashedToken)->first();
// ↑ Lectura sin lockForUpdate — dos requests simultáneos ven el mismo registro válido

$record->markAsUsed(); // markAsUsed() = $this->update(['used_at' => now('UTC')])
// ↑ Ambos requests llegan aquí y ambos autentican con éxito
// Resultado: 2 tokens Sanctum emitidos con 1 solo magic link one-time
```

**Escenario real**: el frontend puede enviar dos peticiones si el usuario hace doble-click en "Acceder", o si hay un retry automático. Ambas peticiones autentican al mismo usuario con tokens independientes.

**Fix correcto**:
```php
// Opción A: lockForUpdate dentro de transacción
$record = DB::transaction(function () use ($hashedToken) {
    $r = MagicLinkToken::valid()->magicLink()
        ->where('token', $hashedToken)
        ->lockForUpdate()
        ->first();
    if ($r) $r->markAsUsed();
    return $r;
});

// Opción B: updateOrFail atómica (más simple)
$affected = MagicLinkToken::valid()->magicLink()
    ->where('token', $hashedToken)
    ->update(['used_at' => now('UTC')]);
if ($affected === 0) { return 400; }
$record = MagicLinkToken::where('token', $hashedToken)->first();
```

---

### RC2 — NFC Punch: determineEventType fuera de la transacción (ALTO)

**Archivo**: `app/Services/PunchEventWriteService.php:19-51, 321-335`

```php
// storeFromNfc()
$eventType = $this->determineEventType($employee, $timestamp); // ← query SIN lock
// ... otro request puede leer el mismo estado aquí ...
DB::beginTransaction();
PunchEvent::create([..., 'event_type' => $eventType]); // ← ambos crean TYPE_IN
DB::commit();
```

`determineEventType()` consulta el último fichaje del día sin `lockForUpdate`. Si el empleado pasa el lector dos veces en < 100ms (doble toque accidental), ambas peticiones determinan `TYPE_IN` independientemente.

**Consecuencia**: dos `TYPE_IN` consecutivos para el mismo empleado. La validación `validatePunchSequence()` no protege el flujo NFC (solo se llama en `storeManual()`).

**Fix correcto**: mover `determineEventType` dentro de la transacción con lock:
```php
DB::beginTransaction();
try {
    // Lock en el último registro del empleado hoy
    $lastEvent = PunchEvent::where('employee_id', $employee->id)
        ->whereDate('timestamp', today())
        ->orderBy('timestamp', 'desc')
        ->lockForUpdate()
        ->first();
    $eventType = (!$lastEvent || $lastEvent->event_type === PunchEvent::TYPE_OUT)
        ? PunchEvent::TYPE_IN
        : PunchEvent::TYPE_OUT;
    PunchEvent::create([...]);
    DB::commit();
}
```

---

### RC3 — Stock operations: sin lockForUpdate en decrementos

**Archivos**: `app/Services/v2/PalletWriteService.php`, `app/Services/v2/PalletActionService.php`

Los métodos `syncBoxesForUpdate()`, `assignToPosition()`, `moveToStore()` usan `DB::transaction()` pero sin `lockForUpdate()` en los registros de Pallet/StoredPallet que modifican. Con dos usuarios ejecutando `moveToStore()` sobre el mismo pallet simultáneamente:
1. Ambos leen el pallet con `Pallet::findOrFail($id)` — estado "stored"
2. Ambos crean/actualizan StoredPallet
3. Resultado: estado inconsistente en `stored_pallets`

La tabla tiene constraint `unique(pallet_id)` en `stored_pallets` (migración 2025_12_05_210354) que actúa como red de seguridad y lanzaría una excepción de base de datos, pero no es una protección explícita y deja el rollback al manejador de excepciones general.

---

## 4. Cache Tenant-Aware: Estado Actual

| Componente | ¿Tiene cache? | Namespace tenant | Riesgo contaminación |
|---|---|---|---|
| CORS middleware (`isActiveTenant`) | SÍ — `Cache::remember("cors:tenant:{$subdomain}", 600)` | SÍ | NULO |
| Feature flags (`FeatureFlagService`) | SÍ — `Cache::remember("feature_flags:tenant:{$id}:{$plan}")` | SÍ | NULO |
| TenantMiddleware (lookup de tenant) | **NO** | — | — |
| ActivityLog middleware (geoIP) | **NO** | — | **ALTO** — ver C1 |
| OrderListService.active() | **NO** | — | — |
| Statistics/dashboard endpoints | **NO** | — | — |
| Settings por tenant | **NO** | — | — |

**Gap principal**: El lookup de `Tenant::where('subdomain')` en TenantMiddleware es la query más ejecutada del sistema (100% de los requests). No tiene ningún nivel de cache.

**Implementación recomendada**:
```php
// TenantMiddleware.php — reemplazar línea 19
$tenant = Cache::remember(
    "tenant:subdomain:{$subdomain}",
    300, // 5 minutos
    fn() => Tenant::where('subdomain', $subdomain)->first()
);
// Invalidar en TenantManagementService::update() y suspend()
Cache::forget("tenant:subdomain:{$subdomain}");
```

**Nota de seguridad**: el cache usa el driver configurado (`CACHE_DRIVER`). Con driver `file` (valor en .env.example), el cache es por proceso y no es compartido entre workers — por lo que en realidad hay poco riesgo de contaminación pero también poca eficacia bajo múltiples workers. En producción debe usarse Redis.

---

## 5. Cuellos de Botella por Capa

### Capa PHP / FPM

| Problema | Evidencia | Impacto en P95 |
|---|---|---|
| `pm.max_requests` no configurado | Sin config custom en repo | Workers con memory leak acumulado |
| Excel sync export (1-30s) | `ExcelController::exportOrders()` línea 50 | Satura workers con pocos usuarios |
| Browsershot/Snappy PDF (1-5s) | `spatie/browsershot` en composer.json | Cada PDF lanza proceso Chromium |
| `Location::get($ip)` síncrono | `LogActivity.php:25` | +50-500ms en CADA request |

### Capa MySQL

| Problema | Evidencia | Impacto |
|---|---|---|
| `innodb_buffer_pool_size` = 128MB (default) | Sin config en docker/mysql/ | Cache de InnoDB insuficiente para tablas grandes |
| `slow_query_log` no configurado | Sin config en docker/mysql/ | Queries lentas invisibles en producción |
| OrderListService.active(): `.get()` sin LIMIT | Línea 181 | Full scan + serialización en memoria |
| `perPage` sin límite | ~12 servicios de listado | Potencial scan completo de tabla |
| N+1 en syncOutputs (production) | ProductionRecordService:130 | Queries individuales dentro de transacción |

### Capa de Colas

| Problema | Evidencia | Impacto |
|---|---|---|
| Cola única sin prioridad | docker-compose: `queue:work` sin `--queue=` | Jobs pesados (PDF, Excel) compiten con jobs críticos |
| Solo 1 worker de cola | docker-compose tiene 1 servicio `queue` | 1 export bloquea toda la cola |
| QUEUE_CONNECTION=sync en .env.example | `.env.example` línea 7 | Si producción hereda esto, no hay colas async |

---

## 6. Configuración Runtime Recomendada

### PHP-FPM (para VPS 4GB RAM)

```ini
; /etc/php/8.3/fpm/pool.d/www.conf
pm = dynamic
pm.max_children = 40
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20
pm.max_requests = 500
request_terminate_timeout = 60
slowlog = /var/log/php-fpm/slow.log
request_slowlog_timeout = 2
```

### OPcache

```ini
opcache.enable = 1
opcache.memory_consumption = 256
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 20000
opcache.revalidate_freq = 0
opcache.validate_timestamps = 0
opcache.jit = tracing
opcache.jit_buffer_size = 64M
```

### MySQL (my.cnf para VPS 4GB RAM)

```ini
[mysqld]
innodb_buffer_pool_size = 2G
innodb_buffer_pool_instances = 2
innodb_log_file_size = 256M
max_connections = 200
wait_timeout = 60
interactive_timeout = 60
slow_query_log = 1
slow_query_log_file = /var/log/mysql/slow.log
long_query_time = 0.2
log_queries_not_using_indexes = 1
```

### Redis (cache + colas)

```ini
maxmemory 512mb
maxmemory-policy allkeys-lru
```

---

## 7. Plan de Validación de Carga (k6)

### Escenario 1: Listado de órdenes paginado bajo concurrencia

```javascript
// k6/orders-list.js
import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
  stages: [
    { duration: '30s', target: 20 },  // Ramp-up
    { duration: '60s', target: 20 },  // Steady state
    { duration: '10s', target: 0 },   // Ramp-down
  ],
  thresholds: {
    http_req_duration: ['p(95)<300', 'p(99)<600'],
    http_req_failed: ['rate<0.01'],
  },
};

// k6 run k6/orders-list.js
// Objetivo: P95 < 300ms, error rate < 1%
```

### Escenario 2: NFC Punch race condition

```javascript
// k6/nfc-punch-race.js — simula doble toque accidental
// Enviar 2 requests del mismo employee_id en < 50ms
// Verificar que solo 1 punch es aceptado (o ambos con event_types correctos)
export const options = {
  vus: 5,
  iterations: 100,
  thresholds: {
    http_req_failed: ['rate<0.01'],
    // Custom metric: verify no double TYPE_IN
  },
};
```

### Escenario 3: Exports concurrentes (stress test de workers)

```javascript
// k6/exports-concurrent.js
// 5 usuarios ejecutando exportaciones Excel simultáneamente
// Verificar que el resto de endpoints no se degrada (P95 de /api/v2/orders < 500ms durante el test)
export const options = {
  scenarios: {
    exports: { executor: 'constant-vus', vus: 5, duration: '120s' },
    normal_traffic: { executor: 'constant-vus', vus: 15, duration: '120s' },
  },
};
```

---

## 8. Quick Wins de Rendimiento (< 1 día, sin riesgo de regresión)

| # | Cambio | Impacto en P95 | Riesgo |
|---|---|---|---|
| QW1 | Cache tenant lookup en TenantMiddleware con `Cache::remember("tenant:subdomain:{$sub}", 300)` | -10 a -20ms por request | MUY BAJO — invalidar en suspend/update |
| QW2 | Eliminar `SET time_zone = '+00:00'` statement de TenantMiddleware; configurar `timezone = UTC` en MySQL | -3 a -8ms por request | MUY BAJO |
| QW3 | Cache de geoIP en LogActivity: `Cache::remember("geoip:{$ip}", 3600, fn() => Location::get($ip))` | -50 a -500ms por request | BAJO |
| QW4 | Cap máximo de `perPage`: `min((int)$request->input('perPage', 10), 100)` en todos los ListService | Previene DoS | NULO |
| QW5 | Mover `ActivityLog::create()` a job async: `dispatch(fn() => ActivityLog::create([...]))` | -5ms por request + desacople de geoIP | BAJO — requiere QUEUE_CONNECTION != sync |
| QW6 | `innodb_buffer_pool_size = 1G` en MySQL config | -30 a -100ms en queries con tablas grandes | MUY BAJO |
| QW7 | `opcache.revalidate_freq = 0` en producción | -5 a -15ms en arranque de worker | NULO |

---

## 9. Escalabilidad Horizontal

El modelo database-per-tenant **facilita** la escalabilidad horizontal porque:
- No hay estado compartido en la BD central (solo `tenants`)
- Redis puede ser compartido entre instancias si se usa como driver de cache/queue
- Las sesiones deben usar `SESSION_DRIVER=redis` (no `file`) para múltiples instancias

**Bloqueadores actuales para segunda instancia**:
- `SESSION_DRIVER=file` en .env.example → sesiones no compartidas entre instancias
- `CACHE_DRIVER=file` → cache no compartida entre instancias
- Sin configuración explícita de `APP_KEY` idéntico en todas las instancias documentada
