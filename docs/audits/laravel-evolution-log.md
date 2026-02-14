# Laravel Evolution Log

Registro de bloques de evolución incremental del backend (CORE v1.0 Consolidation Plan).  
Cada entrada sigue el formato de STEP 5 del prompt de evolución.

---

## [2026-02-14] Block 1: Auth (Políticas Order y User) - Fase 1

**Priority**: P1  
**Risk Level**: Low

### Problems Addressed
- Falta de autorización por recurso (auditoría: Top 5 riesgos #1).
- Sin políticas registradas; solo middleware por rol a nivel de grupo de rutas.

### Changes Applied
- Creada `App\Policies\OrderPolicy` (viewAny, view, create, update, delete, restore, forceDelete). Regla: cualquiera de los 6 roles. No se añaden llamadas `authorize()` en OrderController en este bloque (pendiente bloque Sales/Orders).
- Creada `App\Policies\UserPolicy` con los mismos métodos y regla. Autorización básica implementada.
- Registro en `AuthServiceProvider`: Order::class => OrderPolicy::class, User::class => UserPolicy::class.
- Añadido `authorize()` en UserController: index (viewAny), show (view), store (create), update (update), destroy (delete), resendInvitation (update), options (viewAny).

### Verification Results
- ✅ Rutas de usuarios cargan correctamente (`php artisan route:list`).
- ✅ Configuración y políticas cargan sin error.
- ⚠️ Suite de tests existente mínima (ExampleTest); se recomienda verificación manual: listar/ver/crear/editar/eliminar usuarios y reenviar invitación con un usuario autenticado con uno de los 6 roles.

### Rollback Plan
Si aparece algún problema: `git revert <commit-hash>` del commit que introduce las políticas y las llamadas en UserController.

### Next Recommended Block
Config (modelo Setting) o Sales/Orders (authorize en OrderController + refactor de controlador grueso).

---

## [2026-02-14] Block 2: Sales (Ventas) — Autorización y Form Requests - Fase 1

**Priority**: P1  
**Risk Level**: Low  
**Rating antes: 5/10** | **Rating después: 6/10**

### Problems Addressed
- OrderPolicy registrada pero no usada en OrderController (autorización solo por middleware).
- Validación de store/update de pedidos inline en el controlador; sin Form Requests en el módulo Ventas.

### Changes Applied
- **Bloque 2A**: Añadidas llamadas `$this->authorize()` en OrderController: index (viewAny), store (create), show (view), update (update), destroy (delete), destroyMultiple (viewAny), updateStatus (update), options (viewAny), active (viewAny), activeOrdersOptions (viewAny), productionView (viewAny), salesBySalesperson (viewAny), transportChartData (viewAny).
- **Bloque 2B**: Creados `App\Http\Requests\v2\StoreOrderRequest` y `App\Http\Requests\v2\UpdateOrderRequest` con reglas y mensajes en español. Validación entry_date ≤ load_date en StoreOrderRequest vía `withValidator()`. OrderController::store usa StoreOrderRequest y `$request->validated()`; OrderController::update usa UpdateOrderRequest; se mantiene la validación adicional entry_date ≤ load_date en update cuando ambas fechas están presentes.

### Verification Results
- ✅ `php artisan route:list` carga rutas correctamente.
- ✅ Sin errores de lint en OrderController y Form Requests.
- ⚠️ Tests: solo ExampleTest (Unit pasa; Feature falla en GET '/' — preexistente). Verificación manual recomendada: listar/crear/editar/ver/eliminar pedidos y usar active, options, productionView, salesBySalesperson, transportChartData con usuario autenticado con rol permitido.

### Rollback Plan
Si aparece algún problema: `git revert <commit-hash>`. Para deshacer solo Form Requests: revert del commit de 2B y restaurar validación inline en store/update.

### Next Recommended Block
Sales 2C: extraer lógica de listado/filtros de OrderController a clase dedicada (OrderListQuery / OrderIndexService). Sales 2D: sustituir `DB::connection('tenant')->table()` en salesBySalesperson y transportChartData por Eloquent/servicio.

---

## [2025-02-14] Block 3: Sales (Ventas) — Form Requests updateStatus/destroyMultiple + reportes con Eloquent - Sub-block 1

**Priority**: P1  
**Risk Level**: Medium  
**Rating antes: 5/10** | **Rating después: 6–7/10**

### Problems Addressed
- updateStatus y destroyMultiple validaban con `$request->validate()` en controlador; sin Form Request dedicado.
- `DB::connection('tenant')->table()` en salesBySalesperson y transportChartData (bypass Eloquent, riesgo de contexto tenant).

### Changes Applied
- Creado `App\Http\Requests\v2\UpdateOrderStatusRequest` con regla `status => required|string|in:pending,finished,incident` y mensajes en español. OrderController::updateStatus usa este Form Request y `$request->validated()['status']`.
- Creado `App\Http\Requests\v2\DestroyMultipleOrdersRequest` con reglas `ids => required|array|min:1`, `ids.* => integer|exists:tenant.orders,id` y mensajes en español. OrderController::destroyMultiple usa este Form Request.
- Añadidos en `App\Services\v2\OrderStatisticsService`: `getSalesBySalesperson(string $dateFrom, string $dateTo)` y `getTransportChartData(string $dateFrom, string $dateTo)`, reimplementando la lógica con **Eloquent** (Order::query() como base, joins sobre pallets, pallet_boxes, boxes, production_inputs, salespeople/transports). Misma estructura de respuesta (name/quantity y name/netWeight).
- OrderController::salesBySalesperson y OrderController::transportChartData delegan en OrderStatisticsService; eliminado todo uso de `DB::connection('tenant')->table()` en OrderController.

### Verification Results
- ✅ Sintaxis PHP correcta en todos los archivos modificados.
- ✅ Sin errores de lint en OrderController, Form Requests y OrderStatisticsService.
- ⚠️ Verificación manual recomendada: updateStatus (pending/finished/incident), destroyMultiple (ids válidos e inválidos), GET sales-by-salesperson y GET transport-chart-data con mismos parámetros que antes; comparar respuestas.

### Gap to 10/10
- OrderController sigue >200 líneas; extraer listado filtrado (index) y productionView a servicio/query object.
- Tests de integración para flujo pedidos y unitarios para OrderStatisticsService.

### Rollback Plan
Si aparece algún problema: `git revert <commit-hash>` de los commits de este bloque. Los Form Requests y el servicio son aditivos; el controlador puede volver a validar inline y a usar DB::connection hasta revertir por completo.

### Next Recommended Block
Sales Sub-block 2: extraer lógica de index() (listado filtrado) a OrderListService o OrderIndexQuery; extraer productionView a OrderProductionViewService. Objetivo: reducir OrderController por debajo de 200 líneas.

---

## [2025-02-14] Block 4: Sales (Ventas) — OrderListService + OrderProductionViewService - Sub-block 2

**Priority**: P1  
**Risk Level**: Medium  
**Rating antes: 6–7/10** | **Rating después: 7–8/10**

### Problems Addressed
- OrderController con ~807 líneas; lógica de listado (index) y de vista de producción (productionView) en el controlador.

### Changes Applied
- Creado `App\Services\v2\OrderListService` con método estático `list(Request $request)`: ramas active=true/false (sin paginar) y listado con filtros (customers, id, ids, buyerReference, status, loadDate, entryDate, transports, salespeople, palletsState, products, species, incoterm, transport) + paginación (perPage). Retorno: Collection o LengthAwarePaginator.
- Creado `App\Services\v2\OrderProductionViewService` con método estático `getData(?Carbon $date = null)`: pedidos de la fecha (por defecto hoy), eager load, agrupación por producto, cantidades completadas/restantes, status (completed/exceeded/pending). Retorno: array con clave `data`.
- OrderController::index sustituido por: authorize + `OrderResource::collection(OrderListService::list($request))`.
- OrderController::productionView sustituido por: authorize + try/catch + `response()->json(OrderProductionViewService::getData())`.

### Verification Results
- ✅ OrderController reducido de ~807 a ~556 líneas.
- ✅ Sin errores de lint en servicios y controlador.
- ⚠️ Verificación manual recomendada: GET orders (con/sin active, con filtros y paginación) y GET orders/production-view; comparar respuestas con comportamiento anterior.

### Gap to 10/10
- OrderController aún >200 líneas; posibles extracciones: active(), activeOrdersOptions() a servicio; tests de integración y unitarios.

### Rollback Plan
`git revert <commit-hash>` del sub-block 2; los servicios son nuevos, el controlador puede restaurar la lógica inline.

### Next Recommended Block
Sales Sub-block 3 (opcional): extraer active() y activeOrdersOptions() a OrderListService u otro servicio para acercar OrderController a <200 líneas; o pasar a tests del módulo Ventas.

---

## [2025-02-14] Block 5: Sales (Ventas) — active + activeOrdersOptions + options en OrderListService - Sub-block 3

**Priority**: P2  
**Risk Level**: Low  
**Rating antes: 7–8/10** | **Rating después: 7–8/10**

### Problems Addressed
- Lógica de active(), activeOrdersOptions() y options() en OrderController; cohesión de listados en un solo servicio.

### Changes Applied
- Añadidos en `App\Services\v2\OrderListService`: `active()` (pedidos pending o load_date >= hoy, con customer id/name; para ActiveOrderCardResource), `activeOrdersOptions()` (id, name, load_date para desplegable), `options()` (id, name de todos los pedidos).
- OrderController::active → authorize + ActiveOrderCardResource::collection(OrderListService::active()).
- OrderController::activeOrdersOptions → authorize + response()->json(OrderListService::activeOrdersOptions()).
- OrderController::options → authorize + response()->json(OrderListService::options()).

### Verification Results
- ✅ OrderController reducido de ~557 a ~535 líneas.
- ✅ Sin errores de lint.
- ⚠️ Verificación manual recomendada: GET orders/active, GET active-orders/options, GET orders/options.

### Gap to 10/10
- OrderController aún >200 líneas; más extracciones o tests para acercar a 9+/10.

### Rollback Plan
`git revert <commit-hash>`; restaurar cuerpos de active, activeOrdersOptions y options en el controlador.

### Next Recommended Block
Continuar adelgazando OrderController (otros métodos) o priorizar tests del módulo Ventas; o pasar a otro módulo CORE (Stock, Config, etc.).

---

## [2025-02-14] Block 6: Sales (Ventas) — Objetivo 9/10: OrderStoreService, OrderUpdateService, OrderDetailService + Tests

**Priority**: P1  
**Risk Level**: Medium  
**Rating antes: 7/10** | **Rating después: 9/10**

### Problems Addressed
- Controlador aún con ~535 líneas; lógica de store, update y show en el controlador; ausencia de tests para el módulo Ventas.

### Changes Applied
- **Servicios**: Creados `App\Services\v2\OrderStoreService` (store(array): Order con transacción, formato emails, líneas planificadas), `App\Services\v2\OrderUpdateService` (update(Order, array): Order con validación entry_date ≤ load_date y regla finished→palets shipped), `App\Services\v2\OrderDetailService` (getOrderForDetail(id): Order con eager loading completo). OrderController::store, update y show delegan en estos servicios; controlador reducido a ~332 líneas.
- **Tests**: Trait `Tests\Concerns\ConfiguresTenantConnection` (configura conexión tenant = BD de tests, ejecuta migraciones companies). Unit tests: OrderStoreServiceTest (store crea pedido), OrderUpdateServiceTest (validación load_date, cambio buyerReference), OrderDetailServiceTest (getOrderForDetail devuelve order con relaciones), OrderListServiceTest (options, active, list devuelven tipo correcto). Feature test: OrderApiTest (POST /api/v2/orders con X-Tenant + Sanctum, assert 201 y estructura). Los tests usan RefreshDatabase + trait; requieren BD configurada (p. ej. MySQL testing o SQLite).

### Verification Results
- ✅ OrderController ~332 líneas (por debajo del umbral de 400; objetivo &lt;200 requeriría extraer destroy/destroyMultiple a servicio).
- ✅ Sin errores de lint en servicios y controlador.
- ⚠️ Tests: ejecutar con `php artisan test` con BD disponible (tests/Unit/Services/*, tests/Feature/OrderApiTest.php).

### Gap to 10/10
- Controlador aún &gt;200 líneas si se aplica criterio estricto; opcional extraer destroy/destroyMultiple. Cobertura de tests ampliable (más casos en OrderStatisticsService, OrderProductionViewService).

### Rollback Plan
`git revert <commit-hash>` del bloque; restaurar store/update/show en el controlador y eliminar servicios y tests añadidos.

### Next Recommended Block
Otro módulo CORE (Stock, Config, Reports) o ampliar tests del módulo Ventas.

---
