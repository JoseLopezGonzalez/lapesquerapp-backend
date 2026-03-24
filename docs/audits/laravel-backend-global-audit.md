# Auditoría Arquitectónica Global — Backend Laravel PesquerApp
**Fecha**: 2026-03-24
**Alcance**: Backend Laravel 10, API v2, multi-tenant database-per-tenant, capa SaaS/superadmin
**Tipo**: Auditoría principal integral con análisis profundo de concurrencia y rendimiento bajo carga

---

## 1. Executive Summary

PesquerApp Backend combina un núcleo Laravel idiomático sólido con una arquitectura multi-tenant bien ejecutada. El proyecto tiene 574 archivos PHP, 64 controladores v2, 63 servicios, 71 modelos, 42 Policies, 185 Form Requests y 314 métodos de test. La base es claramente profesional y supera con creces el nivel "funciona pero duele".

Sin embargo, esta auditoría identifica **5 hallazgos críticos de producción** que no estaban documentados en la iteración anterior:

1. **`LogActivity` middleware ejecuta una llamada HTTP síncrona a un servicio externo de geolocalización en cada request API**. Bajo carga concurrente, esto añade 50-500ms a todos los requests y puede bloquear workers si el servicio externo falla.

2. **`TenantMiddleware` no cachea el lookup del tenant** (query a BD central en cada request) y ejecuta un `SET time_zone` extra. El mismo patrón que el `DynamicCorsMiddleware` ya aplica con `Cache::remember` no se aplica en el middleware de producción.

3. **`QUEUE_CONNECTION=sync` en `.env.example`**: Excel exports y generación de PDF son síncronos y bloquean workers FPM. Con exports de 5-30s y un pool de 20 workers, 3-5 exports simultáneos saturan el 25-40% del pool.

4. **Race condition TOCTOU en la verificación de Magic Link / OTP**: dos peticiones simultáneas con el mismo token ambas autentican con éxito porque la lectura del token y su marcado como "usado" no son atómicos.

5. **Race condition en NFC Punch (`determineEventType` fuera de transacción)**: dos taps del mismo empleado en < 100ms pueden resultar en dos fichajes del mismo tipo (TYPE_IN + TYPE_IN).

Estos 5 problemas, sumados al `perPage` sin límite máximo en 12 servicios de listado, son los bloqueantes más urgentes para la operación bajo carga real.

**Nota global actual: 7.0/10**
**Nota tras quick wins (C1-C5 + A1): 8.0/10**
**Nota tras plan completo: 9.0/10**

---

## 2. Identidad Arquitectónica del Proyecto

- **Arquitectura**: Laravel MVC + capa de servicios de aplicación. Sin DDD estricto ni repositorios genéricos. La identidad es **Laravel idiomático + servicios para dominios complejos**.
- **API**: REST v2 con prefijo `api/v2`, mezcla de `apiResource` y endpoints de acción/reporte/exportación. Más de 430 rutas.
- **Multi-tenancy**: database-per-tenant resuelta por header `X-Tenant` y `TenantMiddleware`. Un tenant = una empresa = una BD MySQL.
- **Seguridad**: `auth:sanctum`, middleware de rol, 42 Policies por recurso, throttling en accesos sensibles.
- **Plataforma SaaS**: bloque `superadmin` segregado con onboarding, impersonation, feature flags, migrations panel y observabilidad.
- **Operación**: Sail + docker-compose con servicio `queue`, MySQL, Redis y Mailpit. Queue worker con `restart: unless-stopped` y `tries=3`.

---

## 3. Fortalezas Principales

1. **Aislamiento multi-tenant excelente**: `UsesTenantConnection` en 121 modelos. Solo 2 accesos raw con `DB::connection('tenant')->table()`, ambos justificados.
2. **42 Policies + 246 `authorize()`**: cobertura alta de autorización por recurso.
3. **Rate limiting en auth**: `throttle:5,1` en login/request-access, `throttle:10,1` en verify magic link y OTP.
4. **Índices clave presentes**: `orders(status, load_date)` compuesto, `punch_events(employee_id, timestamp)` compuesto, `productions(date)`.
5. **Transacciones en operaciones críticas**: PalletWriteService, ProductionRecordService, CeboDispatch, RawMaterialReception, ProspectService, DeliveryRoute.
6. **CORS middleware con cache** del tenant lookup (600s).
7. **314 tests distribuidos** en 31 archivos Feature + 4 Unit con cobertura en Auth, Stock, CRM, Settings, Fichajes, Labels, Suppliers, Operational.
8. **185 Form Requests** cubren la mayoría de operaciones con validaciones en español y `exists:tenant.*`.
9. **Documentación interna excelente**: CLAUDE.md completo, evolution log, auditorías previas estructuradas.
10. **Servicios bien divididos**: PunchDashboardService, PunchCalendarService, PunchStatisticsService, OrderListService, OrderDetailService, PalletListService, PalletWriteService, ProductionRecordService — responsabilidades claras.

---

## 4. Riesgos Sistémicos

### RS1 — LogActivity: geoIP síncrono en todos los requests (CRÍTICO)
Cada request API autenticado hace una llamada HTTP externa a ip2location.io + escritura en ActivityLog. Bajo concurrencia, esto es el overhead dominante del sistema.

### RS2 — TenantMiddleware sin cache (ALTO)
4 operaciones DB por request (query central + purge + reconnect + SET time_zone). Dominante en el overhead total del middleware.

### RS3 — QUEUE_CONNECTION=sync y exports bloqueantes (ALTO)
Con valores por defecto del `.env.example`, no hay colas async. Excel/PDF bloquean workers FPM durante segundos.

### RS4 — Race conditions en autenticación y fichajes (ALTO - seguridad + integridad)
TOCTOU en magic link/OTP y race en NFC punch sin lockForUpdate.

### RS5 — Cobertura de tests desigual en bloques críticos (MEDIO)
Producción (2 tests), RawMaterialReception (0 tests), CeboDispatch (0 tests) son los bloques más complejos del core con cobertura mínima.

---

## 5. Evaluación de Calidad de Código

### Controladores por encima de 200 líneas (target: < 200)

| Controlador | Líneas | Observación |
|---|---|---|
| `PunchController.php` | 459 | Mayor: tiene lógica de routing inline y autenticación manual en `store()` |
| `ExcelController.php` | 315 | Fino pero concentra muchos endpoints |
| `PalletController.php` | 310 | Candidato a dividir |
| `OrderController.php` | 298 | Bien delegado en servicios |
| `BoxesController.php` | 277 | Revisable |
| `AuthController.php` | 268 | Complejo por multi-método auth |
| `SpeciesController.php` | 264 | Catálogo con mucha configuración |
| `StoreController.php` | 258 | — |
| `ProductionRecordController.php` | 251 | Árbol complejo |
| `CustomerController.php` | 248 | CRM + datos |

**PunchController**: aunque delega en 5 servicios, el método `store()` tiene lógica de detección de tipo de fichaje (NFC vs manual) con autenticación diferenciada inline. Esta lógica debería ser middleware o un servicio de resolución de actor.

### Servicios grandes

| Servicio | Líneas | Observación |
|---|---|---|
| `RawMaterialReceptionWriteService.php` | 759 | Transacción en controller, no en service |
| `CrmAgendaService.php` | 528 | Mezcla lectura y escritura |
| `PunchEventWriteService.php` | 521 | Bien estructurado en privados |
| `PalletWriteService.php` | 514 | Dominio complejo; aceptable |
| `ProductionRecordService.php` | 487 | N+1 en syncOutputs |

### Inconsistencias de nomenclatura

- `perPage` (camelCase) en la mayoría de servicios vs `per_page` (snake_case) en `SessionController` y Superadmin controllers.
- Patrón de respuesta no uniforme: algunos devuelven `{'data': [...]}`, otros `{'message': '...', 'data': {...}}`.

---

## 6. Evaluación de Componentes Estructurales Laravel

| Componente | Cantidad | Estado | Nota |
|---|---|---|---|
| Services | 63 | Fuerte, algunos grandes | 8/10 |
| Form Requests | 185 | Muy buena cobertura | 8.5/10 |
| Policies | 42 | Alta cobertura; 5 controllers sin Policy en CRM | 8/10 |
| API Resources | 51 | Buena serialización | 8/10 |
| Middleware | 18 | Bien diseñados; LogActivity es el problema | 7/10 |
| Jobs | 2 | Solo Superadmin; sin patrón formal para negocio | 6/10 |
| Events/Listeners | Escasos | Side effects síncronos dominan | 5/10 |
| Actions/DTOs | Ausentes | Decisión arquitectónica explícita; OK | — |

---

## 7. Distribución de Lógica de Negocio

- **Ventas**: bien distribuida entre OrderController (thin), OrderListService, OrderStoreService, OrderUpdateService, OrderDetailService.
- **Fichajes**: PunchController delega en 5 servicios pero conserva lógica de routing y auth inline.
- **Producción**: árbol complejo bien encapsulado en ProductionRecordService, ProductionInputService, ProductionOutputConsumptionService.
- **Recepciones**: lógica de escritura en RawMaterialReceptionWriteService (759L) pero sin transacción propia.
- **CRM**: ProspectService, OfferService, CrmAgendaService bien separados, pero CrmDashboard/Agenda sin Policy.

La distribución es correcta en el 80% del sistema. Los casos problemáticos son los bordes donde controladores grandes actúan como mini-application-services.

---

## 8. Integridad Transaccional y Side Effects

### Transacciones presentes (correcto)
- `PalletWriteService`: todas las operaciones de stock con `DB::transaction()`
- `ProductionRecordService.syncOutputs()`: transacción en sincronización de outputs
- `RawMaterialReceptionController`: wrapper de transacción en store/update
- `CeboDispatchController`: transacción en store/update
- `ProspectService`: transacción en operaciones CRM
- `DeliveryRouteWriteService`: transacción

### Gap: N+1 dentro de transacciones

```php
// ProductionRecordService.php:130-175
DB::transaction(function () use ($record, $outputsData) {
    foreach ($outputsData as $outputData) {
        $output = ProductionOutput::find($outputData['id']); // Query individual en loop
        $output->update([...]);                               // Update individual en loop
    }
    // Con 20 outputs: 40 queries dentro de la transacción = lock extendido
});
```

### Gap: Transacción en controller en lugar de en service (RMR)

`RawMaterialReceptionWriteService` (759 líneas) no gestiona su propia integridad transaccional. Si se reutiliza desde otro contexto sin wrapper de transacción, puede quedar en estado inconsistente.

### Side effects síncronos pendientes de async

- Generación de PDFs: síncrona en el request
- Excel exports: síncronos, bloquean workers
- Emails: posiblemente síncronos según configuración de queue
- `ActivityLog::create()`: síncrono en cada request (ver C1)

---

## 9. Rendimiento bajo Carga Unitaria (N+1, índices, serialización)

### Positivo
- `OrderListService`: `Order::withTotals()->with(['customer', 'salesperson', 'fieldOperator', 'transport', 'incoterm', 'offer'])` — eager loading correcto
- `PalletListService`: `->with(['boxes.box.product', ...])` — profundidad de eager loading apropiada
- Índices existentes: `orders(status, load_date)`, `punch_events(employee_id, timestamp)`, `productions(date)`, `orders_show_path` (migración 2025_12_12)

### Gaps detectados

| Problema | Archivo | Impacto |
|---|---|---|
| N+1 en `syncOutputs` | `ProductionRecordService.php:130` | Dentro de transacción → lock extendido |
| `OrderListService.active()` sin LIMIT | `OrderListService.php:181` | Full dataset en memoria |
| `perPage` sin cap máximo | ~12 servicios | Potencial full table scan |
| Stats endpoints sin cache | `PunchStatisticsService.php`, `OrderStatisticsService.php` | Queries pesadas en cada call |

---

## 10. Rendimiento bajo Carga Concurrente

Esta sección documenta los hallazgos nuevos de la auditoría centrada en concurrencia.

### Overhead de middleware por request (base de todo el sistema)

Cada request API pasa por este overhead antes de ejecutar lógica de negocio:

| Componente | Tiempo añadido | Fuente |
|---|---|---|
| `LogActivity`: HTTP externo ip2location.io | **+50 a +500ms** | `app/Http/Middleware/LogActivity.php:25` |
| `TenantMiddleware`: query BD central | +5-15ms | `TenantMiddleware.php:19` |
| `TenantMiddleware`: purge + reconnect | +5-15ms | `TenantMiddleware.php:44-50` |
| `TenantMiddleware`: SET time_zone | +2-5ms | `TenantMiddleware.php:50` |
| **Total overhead base** | **62-535ms** | — |

**Consecuencia**: el P95 de cualquier endpoint del sistema tiene este overhead como base. Un endpoint de CRUD simple que debería responder en 80ms realmente responde en 140-600ms bajo carga concurrente.

### Modelo de concurrencia PHP-FPM

Sin configuración explícita (no hay `php-fpm.conf` en el repo), los valores son los defaults de Laravel Sail:
- `pm.max_children ≈ 20`
- `pm.max_requests = 0` (sin límite — acumulación de memoria)

**Problema de saturación por exports**:
- 3 exports Excel simultáneos (5-30s c/u) = 3/20 workers bloqueados = 15% del pool
- 5 PDFs con Browsershot simultáneos (2-8s c/u) = 5/20 workers bloqueados = 25% del pool
- Combinado: 40% del pool bloqueado → respuestas lentas para todos los demás usuarios

### Race conditions detectadas

#### RC1 — Magic Link / OTP: TOCTOU (CRÍTICO — seguridad)

```php
// AuthController.php:104-124
$record = MagicLinkToken::valid()
    ->magicLink()
    ->where('token', $hashedToken)
    ->first();              // ← Sin lockForUpdate
// ... validación de actor ...
$record->markAsUsed();     // ← Ambos requests llegan aquí y autentican
```

Dos requests simultáneos con el mismo token ambos superan el check `valid()` antes de que cualquiera llame a `markAsUsed()`. Resultado: 2 tokens Sanctum emitidos con 1 magic link one-time.

**Fix**: UPDATE atómico verificando affected rows, o `lockForUpdate()` dentro de transacción.

#### RC2 — NFC Punch: determineEventType fuera de transacción (ALTO)

```php
// PunchEventWriteService.php:30 — storeFromNfc()
$eventType = $this->determineEventType($employee, $timestamp); // Query sin lock
// <- Ventana de race condition aquí ->
DB::beginTransaction();
PunchEvent::create(['event_type' => $eventType, ...]);
DB::commit();
```

`determineEventType()` (líneas 321-335) lee el último fichaje sin lock. Dos taps NFC en < 100ms determinan el mismo tipo independientemente → dos fichajes TYPE_IN consecutivos.

**Fix**: mover la determinación del tipo dentro de la transacción con `lockForUpdate` en la query de último evento.

#### RC3 — Stock moves sin lockForUpdate explícito (BAJO-MEDIO)

Las operaciones `moveToStore()`, `assignToPosition()` en `PalletWriteService` y `PalletActionService` tienen transacción pero sin `lockForUpdate`. El constraint `unique(pallet_id)` en `stored_pallets` actúa como red de seguridad lanzando excepción de base de datos, pero no hay protección explícita.

### Tabla de endpoints críticos con P95 estimado

| Endpoint | P50 estimado | P95 bajo 20 usuarios | Bottleneck |
|---|---|---|---|
| `GET /api/v2/orders` (paginado) | 120ms | 350ms | Middleware overhead (C1+C2) |
| `GET /api/v2/orders?active=true` | 150ms | 450ms | `.get()` sin paginar + overhead |
| `GET /api/v2/pallets` | 160ms | 500ms | Eager loading + overhead |
| `GET /api/v2/punches/statistics` | 350ms | 1200ms | Stats pesadas sin cache |
| `POST /api/v2/punches` (NFC) | 100ms | 350ms | Race condition posible |
| `GET /api/v2/punches/dashboard` | 250ms | 900ms | Múltiples queries agregadas |
| `GET /excel/orders` | 5000ms | 30000ms | Síncrono, bloquea worker |
| `GET /pdf/orders/*/sheet` | 1500ms | 8000ms | Browsershot/Snappy síncrono |
| `POST /auth/magic-link/verify` | 80ms | 300ms | Race condition TOCTOU |

*Overhead base de middleware (C1: 100ms promedio + C2: 20ms) incluido en todos.*

---

## 11. Observaciones de Seguridad y Autorización

- **Aislamiento tenant**: excelente. 121 modelos con `UsesTenantConnection`. Solo 2 accesos raw justificados.
- **Policies**: 42 registradas. 246 llamadas `authorize()`. 5 controladores sin Policy (CRM y reportes) documentados como deuda.
- **Rate limiting**: correcto en todos los endpoints de auth.
- **Race condition TOCTOU en magic link** (ver sección 10): es un riesgo de seguridad activo.
- **`.env.example`**: contiene IP real (`94.143.137.84`) y contraseña real. Debería usar placeholders.
- **`tymon/jwt-auth`**: presente en composer.json pero sin uso aparente. Puede tener rutas registradas por el paquete.

---

## 12. Testing y Mantenibilidad

| Área | Tests | Cobertura |
|---|---|---|
| Auth | AuthBlockApiTest: 444L | Flujos completos incluyendo magic link |
| Stock/Inventario | StockBlockApiTest: 999L, 33 tests | Muy buena |
| Ventas/Pedidos | OrderApiTest, OperationalOrdersApiTest | Buena |
| Productos | ProductosBlockApiTest: 528L | Buena |
| Fichajes | FichajesBlockApiTest: 10 tests | Básica |
| CRM | CrmApiTest: 1314L | Buena |
| **Producción** | **ProductionBlockApiTest: 2 tests** | **MUY ESCASA** |
| **Recepciones** | **Sin archivo** | **NINGUNA** |
| **Despachos** | **Sin archivo** | **NINGUNA** |
| Superadmin | SuperadminAuthTest, SuperadminFeatureSecurityTest | Buena |

**Total**: 314 métodos de test en 31 archivos Feature + 4 Unit.

**Gaps críticos**: Los 3 bloques sin cobertura suficiente (Producción con árbol de trazabilidad, RawMaterialReception, CeboDispatch) son exactamente los dominios con mayor complejidad de negocio y más lógica transaccional.

**Unit tests**: Solo `tests/Unit/Services/` con 4 archivos sobre Order services. El dominio de Producción, Inventory y Punch no tienen unit tests.

---

## 13. Evaluación por Bloques

| Bloque | Nota actual | Delta vs anterior | Motivo del cambio |
|---|---|---|---|
| A.1 Auth + Roles | **8.5/10** | -0.5 | Race condition TOCTOU en magic link |
| A.2 Ventas | **8.5/10** | -0.5 | active() sin paginar; stats sin cache |
| A.3 Inventario / Stock | **9.5/10** | -0.5 | Sin lockForUpdate explícito en moves |
| A.4 Recepciones | **8/10** | -1 | Sin tests; transacción en controller |
| A.5 Despachos de Cebo | **8.5/10** | -0.5 | Sin tests Feature |
| A.6 Producción | **8/10** | -1 | 2 tests; N+1 en syncOutputs |
| A.7 Productos | 9/10 | = | Estable |
| A.8 Catálogos | 9/10 | = | Estable |
| A.9 Proveedores | **8.5/10** | -0.5 | Service 382L; liquidación compleja |
| A.10 Etiquetas | 9/10 | = | Estable |
| A.11 Fichajes | **8/10** | -1 | Race condition NFC; PunchController 459L |
| A.12 Estadísticas | **8.5/10** | -0.5 | Stats sin cache; endpoints pesados |
| A.13 Configuración | 9/10 | = | Estable |
| A.14 Sistema | **8.5/10** | -0.5 | Comparte gap de A.1 |
| A.15 Documentos | **8/10** | -1 | Exports síncronos; 7 libs PDF; queue=sync |
| A.16 Tenants públicos | 9/10 | = | Estable |
| A.17 Infraestructura API | **7/10** | -2 | LogActivity geoIP; TenantMiddleware sin cache; perPage |
| A.18 Utilidades PDF/IA | **8.5/10** | -0.5 | Librería múltiple; sync |
| A.19 CRM comercial | 8.5/10 | = | Sin Policy; en revisión |
| A.20 Canal operativo | 8.5/10 | = | En revisión |
| A.21 Usuarios externos | 8.5/10 | = | En revisión |
| A.22 Superadmin SaaS | 8/10 | = | En seguimiento |

---

## 14. Scoring por Dimensión

| Dimensión | Nota | Justificación |
|---|---|---|
| Multi-tenancy implementation maturity | **8/10** | Aislamiento excelente; TenantMiddleware sin cache |
| Domain modeling clarity | **8.5/10** | 71 modelos bien estructurados; estados documentados |
| API design consistency | **7/10** | `perPage` inconsistente; contratos no uniformes |
| Testing coverage and strategy | **6/10** | 314 tests pero Producción/RMR/CeboDispatch críticos sin cobertura |
| Deployment and DevOps practices | **6.5/10** | Docker bien; sin PHP-FPM custom; opcache sin config |
| Documentation quality | **8/10** | CLAUDE.md excelente, evolution log, auditorías |
| Technical debt level | **7/10** | Controladores grandes; JWT sin uso; 7 libs PDF |
| Code quality and maintainability | **7.5/10** | Servicios buenos; inconsistencias en bordes |
| **Performance under single-user load** | **7/10** | Índices presentes; N+1 localizado; perPage sin cap |
| **Performance under concurrent load** | **5/10** | C1/C2/C3 críticos; race conditions activas; sin FPM config |
| Security and authorization maturity | **7.5/10** | 42 policies; TOCTOU en magic link; .env.example con datos reales |

**Nota global actual: 7.0/10**

### Criterio específico: ¿Qué separa al proyecto de un 9/10?

- Resolver los 5 hallazgos críticos de concurrencia (C1-C5)
- Cubrir Producción, RawMaterialReception y CeboDispatch con tests Feature completos
- Configurar PHP-FPM, MySQL y OPcache explícitamente
- Confirmar `QUEUE_CONNECTION=redis` en producción
- Eliminar deuda de librerías (JWT, PDF duplicadas)

### Criterio de concurrencia para declarar production-ready

1. P95 < 300ms en los 5 endpoints más usados bajo 20 usuarios concurrentes
2. Cero race conditions conocidas en stock, fichajes y auth
3. PHP-FPM dimensionado con `pm.max_requests = 500` y `request_terminate_timeout = 60`
4. `slow_query_log` activo con `long_query_time = 0.2`
5. Jobs de exportación en cola separada con timeout configurado
6. Lookup de tenant cacheado; coste de TenantMiddleware < 10ms medido

---

## 15. Top 5 Riesgos Sistémicos

1. **`LogActivity` con HTTP externo síncrono**: es el overhead dominante del sistema. Bajo cualquier carga concurrente real, añade 50-500ms a todos los requests. Si ip2location.io falla, puede bloquear workers durante segundos.

2. **Race condition TOCTOU en Magic Link/OTP**: riesgo de seguridad activo. Autenticación doble con un token one-time bajo doble submit o retry de red.

3. **QUEUE_CONNECTION=sync + exports síncronos**: con el valor por defecto de `.env.example`, las operaciones de Excel/PDF bloquean workers FPM sincrónicamente. Bajo carga concurrente, esto satura el pool de workers.

4. **Cobertura de tests insuficiente en bloques críticos**: Producción (árbol de trazabilidad), Recepciones y Despachos son los dominios más complejos y tienen 0-2 tests. Cualquier refactor en estas áreas no tiene red de seguridad.

5. **Configuración de runtime no explícita**: sin `php-fpm.conf`, `mysql.cnf` ni `opcache.ini` en el repositorio, no hay garantía de que producción esté correctamente dimensionada. `innodb_buffer_pool_size = 128MB` (default) es insuficiente para un ERP con tablas grandes.

---

## 16. Top 5 Mejoras de Mayor Impacto

1. **Cache de geoIP + ActivityLog async** (C1): Impacto inmediato en P95 de TODOS los endpoints. Reduce el overhead dominante de 50-500ms a 0ms. Complejidad: baja (2h de implementación).

2. **Cache de tenant lookup + eliminar SET time_zone** (C2): Elimina 20-35ms del overhead base de cada request. Patrón ya existe en `DynamicCorsMiddleware`. Complejidad: muy baja (1h).

3. **Fix TOCTOU en magic link/OTP** (C4): Cierra un riesgo de seguridad real con 1 línea de cambio (`lockForUpdate()` o UPDATE atómico). Complejidad: muy baja (30min).

4. **Cap de perPage a 100** (A1): Previene el vector DoS más fácil de activar. Una línea por servicio. Complejidad: nula (30min).

5. **Tests de Producción, RMR y CeboDispatch**: Habilita refactor seguro de los bloques más complejos del core. Sin esto, cualquier mejora estructural en estos dominios es un riesgo. Complejidad: media (2-4 días).

---

## 17. Quick Wins de Bajo Riesgo (< 1 día, sin regresión posible)

| # | Acción | Archivo | Impacto | Tiempo |
|---|---|---|---|---|
| QW1 | `Cache::remember("geoip:{$ip}", 86400, ...)` en LogActivity | `LogActivity.php:25` | -50 a -500ms por request | 1h |
| QW2 | `Cache::remember("tenant_mw:{$sub}", 300, ...)` en TenantMiddleware | `TenantMiddleware.php:19` | -10 a -20ms por request | 1h |
| QW3 | Eliminar `SET time_zone` y usar `timezone => '+00:00'` en config | `TenantMiddleware.php:50` + `config/database.php` | -3 a -8ms por request | 30min |
| QW4 | UPDATE atómico en `verifyMagicLink()` y `verifyOtp()` | `AuthController.php:104-161` | Cierra TOCTOU | 30min |
| QW5 | `min((int)$request->input('perPage', 12), 100)` en todos los ListService | ~12 archivos | Previene DoS | 30min |
| QW6 | `innodb_buffer_pool_size = 1G` en docker/mysql | nuevo `my.cnf` | -30-100ms en queries con tablas grandes | 30min |
| QW7 | `opcache.revalidate_freq = 0` + `opcache.validate_timestamps = 0` en Dockerfile | `Dockerfile` | -5-15ms en arranque de worker | 30min |

**Total tiempo quick wins**: ~5 horas. Impacto en nota: de 7.0 a 8.0/10.

---

## 18. Mejoras Estructurales de Mayor Alcance

1. **Mover `ActivityLog::create()` a job async** con cola dedicada `logging`: desacopla la observabilidad del flujo de request. Requiere `QUEUE_CONNECTION=redis` activo.

2. **Fix race condition NFC Punch** (C5): mover `determineEventType()` dentro de la transacción con `lockForUpdate()`. Requiere revisión del flujo completo de `storeFromNfc()`.

3. **Tests Feature completos para Producción, RMR y CeboDispatch**: 3-5 tests por flujo crítico (store, update, trazabilidad, estados).

4. **Mover transacción de RMR del controller al service**: `RawMaterialReceptionWriteService` debe gestionar su propia integridad.

5. **Configuración explícita de PHP-FPM y MySQL**: añadir `docker/php-fpm.conf` y `docker/mysql/my.cnf` al repositorio con valores correctos para producción.

6. **Policies para CRM**: `CrmDashboardController`, `CrmAgendaController`, `OrdersReportController`.

7. **Cap de perPage con validación en Form Request**: mover la validación del límite a los Form Requests de listado en lugar de en los servicios.

8. **Eliminar tymon/jwt-auth**: confirmar que no hay rutas activas con `auth:api` y eliminar la dependencia.

9. **Cache de statistics con TTL 60s**: `PunchStatisticsService` y `OrderStatisticsService` pueden cachearse con clave `tenant:{id}:statistics:{params_hash}`.

---

## 19. Roadmap para Llegar a 9/10

### Sprint 1 — Quick wins urgentes (1 semana)

1. Cache geoIP en LogActivity (QW1)
2. Cache tenant en TenantMiddleware (QW2) + eliminar SET time_zone (QW3)
3. Fix TOCTOU magic link/OTP (QW4)
4. Cap de perPage (QW5)
5. Configurar innodb_buffer_pool_size (QW6)
6. Fix race condition NFC Punch (C5)
7. Confirmar `QUEUE_CONNECTION=redis` en producción

**Nota estimada tras Sprint 1**: 8.0/10

### Sprint 2 — Tests críticos + operabilidad (2 semanas)

1. Tests Feature para Producción (CRUD + syncOutputs + trazabilidad de caja)
2. Tests Feature para RawMaterialReception
3. Tests Feature para CeboDispatch
4. Mover transacción de RMR al service
5. Policies para CRM (CrmDashboard, CrmAgenda, OrdersReport)
6. Añadir `docker/php-fpm.conf` y `docker/mysql/my.cnf` al repositorio

**Nota estimada tras Sprint 2**: 8.5/10

### Sprint 3 — Rendimiento y calidad (3 semanas)

1. Cache de statistics (PunchStatistics, OrderStatistics) con TTL 60s
2. Fix N+1 en syncOutputs (preload en lugar de find en loop)
3. ActivityLog async en cola `logging`
4. Eliminar tymon/jwt-auth
5. Reducir PunchController a < 200 líneas
6. Consolidar A.19-A.22 (CRM, Canal operativo, Usuarios externos, Superadmin SaaS)

**Nota estimada tras Sprint 3**: 9.0/10

---

## Cierre Obligatorio

1. **Nota global actual**: **7.0/10**
2. **Nota potencial tras quick wins (Sprint 1)**: **8.0/10**
3. **Nota potencial tras plan completo (Sprint 1+2+3)**: **9.0/10**
4. **Secuencia recomendada**: quick wins urgentes → tests críticos → performance + calidad → cierre de bloques en revisión
5. **Criterios para declarar el backend en 9/10**:
   - Tests reproducibles en CI con MySQL/tenant configurados
   - Producción, RMR y CeboDispatch con cobertura Feature ≥ 80%
   - Hallazgos C1-C5 y A1 resueltos y verificados
   - PHP-FPM y MySQL configurados explícitamente en el repositorio
   - A.19-A.22 cerrados con trazabilidad equivalente al core clásico
6. **Criterios de production-ready bajo carga concurrente**:
   - P95 < 300ms en los 5 endpoints más usados bajo 20 usuarios concurrentes por tenant
   - Cero race conditions conocidas en stock, fichajes y auth
   - `pm.max_requests = 500` configurado (sin acumulación de memoria)
   - `slow_query_log` activo con `long_query_time = 0.2`
   - Jobs de export en cola separada con timeout
   - Lookup de tenant cacheado (medido < 5ms)

---

## Evidencia consultada

| Tipo | Archivos clave |
|---|---|
| Middleware | `app/Http/Middleware/TenantMiddleware.php`, `LogActivity.php`, `DynamicCorsMiddleware.php`, `Kernel.php` |
| Controladores críticos | `PunchController.php`, `OrderController.php`, `AuthController.php`, `RawMaterialReceptionController.php`, `PalletController.php` |
| Servicios críticos | `PunchEventWriteService.php`, `PalletWriteService.php`, `ProductionRecordService.php`, `RawMaterialReceptionWriteService.php`, `OrderListService.php` |
| Modelos y auth | `MagicLinkToken.php`, `Order.php`, `Tenant.php` |
| Migraciones | `create_orders_table`, `create_punch_events_table`, `add_indexes_for_audit_tables`, `add_orders_status_load_date_index` |
| Tests | `ProductionBlockApiTest.php`, `StockBlockApiTest.php`, `FichajesBlockApiTest.php`, `AuthBlockApiTest.php` |
| Configuración | `.env.example`, `config/queue.php`, `config/database.php`, `docker-compose.yml`, `composer.json` |
| Documentos de apoyo | `docs/audits/findings/performance-concurrency-analysis.md`, `security-concerns.md`, `multi-tenancy-analysis.md`, `block-priority-matrix.md` |

Esta auditoría sustituye y amplía la versión del **23 de marzo de 2026**, incorporando análisis profundo de comportamiento bajo carga concurrente y race conditions.
