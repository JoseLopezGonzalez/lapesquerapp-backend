# Performance Hotspots — PesquerApp Backend
**Fecha**: 2026-03-24 | **Auditoría**: Principal Backend v2 (actualización con evidencia de código)

---

## Hotspot H1 — LogActivity: Llamada HTTP externa síncrona en el grupo `api` (CRÍTICO)

**Archivos**:
- `app/Http/Kernel.php:44` — registrado en grupo `api` (afecta a todos los requests API)
- `app/Http/Middleware/LogActivity.php:25` — `Location::get($ip)`
- Driver default: `Stevebauman\Location\Drivers\Ip2locationio` → HTTP a api.ip2location.io

**Flujo del problema**:
```
Request API autenticado
  → LogActivity::handle()
      → Location::get($ip)               ← HTTP externo a ip2location.io: +50-500ms
      → ActivityLog::create([...])        ← escritura DB síncrona en cada request
  → resto del request
```

**Impacto bajo carga concurrente**: 20 usuarios × 5 tenants = 100 req/s → 100 llamadas HTTP externas/segundo hacia ip2location.io. Si ese servicio tiene 200ms de latencia promedio, **todos los P95 del sistema tienen ese overhead**. Si está caído, el timeout HTTP bloquea el worker durante varios segundos.

**El `try/catch` no resuelve el problema de tiempo**:
```php
// LogActivity.php:23-31
try {
    $location = Location::get($ip); // HTTP síncrono — el timeout consume tiempo aunque falle
} catch (\Exception $e) { ... }
```

**Quick win** (2h):
```php
// 1. Cachear geoIP por IP (las IPs raramente cambian de país)
$location = Cache::remember("geoip:{$ip}", 86400, fn() => Location::get($ip));

// 2. Desacoplar la escritura DB del request via job
dispatch(fn() => ActivityLog::create([
    'action' => $request->method().' '.$request->path(),
    // ... resto de campos
]))->onQueue('logging');
```

---

## Hotspot H2 — TenantMiddleware: 4 operaciones DB por request sin cache (ALTO)

**Archivo**: `app/Http/Middleware/TenantMiddleware.php`

**Secuencia actual por cada request**:
```php
// Línea 19 — query a BD central (no cacheada)
$tenant = Tenant::where('subdomain', $subdomain)->first();

// Líneas 44-50 — ciclo completo de conexión
config(['database.connections.tenant.database' => $tenant->database]);
DB::purge('tenant');        // libera conexión existente
DB::reconnect('tenant');    // establece nueva conexión
DB::connection('tenant')->statement("SET time_zone = '+00:00'"); // query extra por request
```

**Overhead estimado por request**: 10-35ms (1 query central + purge/reconnect + SET statement).

**Contraste interno**: `DynamicCorsMiddleware::isActiveTenant()` SÍ cachea con `Cache::remember("cors:tenant:{$subdomain}", 600)`. El middleware de producción no aplica el mismo patrón siendo mucho más crítico.

**Quick win** (2h):
```php
// Cache del tenant lookup — invalidar en suspend/update del tenant
$tenant = Cache::remember("tenant_mw:{$subdomain}", 300, function () use ($subdomain) {
    return Tenant::select('id', 'subdomain', 'database', 'status')
        ->where('subdomain', $subdomain)->first();
});

// Eliminar SET time_zone — configurar en config/database.php
'tenant' => [
    'driver' => 'mysql',
    // ...
    'timezone' => '+00:00', // aplica vía DSN, sin query extra
],
```

---

## Hotspot H3 — Exports Excel/PDF síncronos bloquean workers FPM (ALTO)

**Archivos**:
- `app/Http/Controllers/v2/ExcelController.php` — todos los métodos usan `Excel::download()` síncrono
- `.env.example:7` — `QUEUE_CONNECTION=sync` como valor por defecto

**Tiempo de bloqueo estimado por operación**:
| Operación | Tiempo estimado | Librería |
|---|---|---|
| Export órdenes completo | 3-30s | maatwebsite/excel |
| Export A3ERP con filtros | 5-20s | maatwebsite/excel |
| PDF con DomPDF | 0.5-3s | barryvdh/laravel-dompdf |
| PDF con Browsershot | 2-8s | spatie/browsershot (Chromium) |
| PDF con Snappy | 1-5s | barryvdh/laravel-snappy |

**Escenario de saturación** (con 20 workers FPM):
- 3 usuarios exportan Excel simultáneamente → 3 workers bloqueados 15-30s
- 5 usuarios generan PDFs → 5 workers bloqueados 2-8s
- 8 de 20 workers bloqueados = **40% del pool** sin atender requests normales

**Acción necesaria**: confirmar `QUEUE_CONNECTION=redis` en producción. Para exports async usar `Excel::queue()` con notificación posterior. Mínimo inmediato: rate limiting en endpoints de export.

---

## Hotspot H4 — perPage sin límite máximo en ~12 ListService (ALTO — vector DoS)

**Patrón detectado** (repetido en todos los servicios de listado):
```php
// Patrón en app/Services/v2/OrderListService.php:159
$perPage = $request->input('perPage', 10); // Sin validación de máximo
return $query->paginate($perPage); // Acepta perPage=1000000
```

**Archivos afectados** (selección):
- `app/Services/v2/OrderListService.php:159`
- `app/Services/v2/PalletListService.php:26`
- `app/Services/v2/SupplierListService.php:34`
- `app/Services/v2/RawMaterialReceptionListService.php:62`
- `app/Services/v2/CeboDispatchListService.php:21`
- `app/Services/v2/ProductListService.php:21`
- `app/Http/Controllers/v2/EmployeeController.php:52`
- y ~5 más

**Impacto**: `GET /api/v2/orders?perPage=50000` → 50.000 objetos Eloquent en memoria PHP con sus relaciones (customer, salesperson, transport). Potencial OOM en FPM. Con 3 requests simultáneos con perPage alto = saturación de memoria.

**Quick win** (30 min):
```php
$perPage = min((int) $request->input('perPage', 12), 100);
```

---

## Hotspot H5 — OrderListService.active(): Colección no paginada (MEDIO)

**Archivo**: `app/Services/v2/OrderListService.php:170-182`

```php
return $query->orderBy('load_date', 'desc')->get(); // Sin LIMIT
```

Incluye todos los pedidos en estado `pending` O con `load_date >= hoy`. Con histórico de 6 meses puede devolver 500+ registros. El índice `orders_status_load_date_index` hace la query eficiente, pero la serialización y transmisión de 500 pedidos con sus relaciones consume memoria y tiempo.

---

## Hotspot H6 — N+1 dentro de transacciones en ProductionRecordService (MEDIO)

**Archivo**: `app/Services/Production/ProductionRecordService.php:130-185`

```php
return DB::transaction(function () use ($record, $outputsData) {
    foreach ($outputsData as $outputData) {
        $output = ProductionOutput::find($outputData['id']); // Query individual por iteración
        $output->update([...]);                               // Update individual por iteración
    }
    // Con 20 outputs: 40 queries dentro de la transacción = lock extendido
});
```

**Impacto**: transacciones más largas = locks prolongados en `production_outputs`. Con usuarios concurrentes editando la misma producción, el segundo request espera.

---

## Hotspot H7 — PunchStatisticsService: Queries complejas sin cache (MEDIO)

**Archivo**: `app/Services/PunchStatisticsService.php` (441 líneas)

Genera estadísticas con `GROUP BY`, cálculos de horas trabajadas y agregaciones temporales. Ningún método usa cache. Con 50 empleados y 6 meses de historial, las queries de statistics pueden superar 500ms. Es el endpoint más lento del bloque Fichajes.

---

## Resumen de impacto relativo

| Hotspot | Impacto en P95 actual | P95 estimado con fix |
|---|---|---|
| H1 (LogActivity geoIP) | +50 a +500ms en **todos** los requests | -100 a -400ms |
| H2 (TenantMiddleware sin cache) | +15ms en **todos** los requests | -12ms |
| H3 (exports síncronos) | Bloqueo de 40% workers con 8 exports simultáneos | Workers libres |
| H4 (perPage sin límite) | Potencial OOM / P95 > 5s | Bounded a 100 items |
| H5 (active sin paginar) | +50-200ms según volumen de datos | -50ms con límite |
| H6 (N+1 en transaction) | +20-100ms en syncOutputs | -40ms |
| H7 (stats sin cache) | +200-600ms en endpoint /statistics | -300ms con cache 60s |
