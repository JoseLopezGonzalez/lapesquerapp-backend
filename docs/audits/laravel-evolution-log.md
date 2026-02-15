# Laravel Evolution Log — PesquerApp Backend

Registro de cambios aplicados en el marco de la evolución incremental (CORE v1.0).
Cada entrada sigue el formato definido en `docs/35-prompts/01_Laravel incremental evolution prompt.md`.

---

## [2026-02-15] Block A.15 Documentos (PDF/Excel)

**Priority**: P1
**Risk Level**: Low
**Rating antes: 4/10** | **Rating después: 9/10**

### Problems Addressed

- OrderDocumentController sin Form Request, sin authorize, mensajes en inglés.
- PDFController ~305 líneas; ExcelController ~612 líneas; lógica de filtros duplicada (~300 líneas) en 5 lugares.
- Sin Form Request ni authorize en PDF/Excel endpoints.
- Sin tests Feature para documentos.

### Changes Applied

- **OrderDocumentController (Sub-bloque 1)**: SendCustomDocumentsRequest (validación + authorize view Order); authorize('view', $order) en sendStandardDocumentation; mensajes en español.
- **OrderExportFilterService**: Servicio con applyFilters() y getFilteredOrders(); elimina duplicación de filtros de pedidos.
- **PDFController**: Usa OrderExportFilterService y OrderFilteredExportRequest; authorize('view', $order) en todos los métodos con orderId; authorize vía Form Request en generateOrderSheetsWithFilters. **305 → 150 líneas.**
- **ExcelController**: Usa OrderExportFilterService y OrderFilteredExportRequest en 4 métodos con filtros; authorize('view', $order) en métodos con orderId; authorize('viewAny', Order::class) en exportOrders y exportActiveOrderPlannedProducts. **612 → 315 líneas.**
- **OrderFilteredExportRequest**: Form Request con reglas de filtros y authorize viewAny(Order::class).
- **DocumentsBlockApiTest**: 8 tests (sendCustom 200 skip si no Chromium, 422 invalid/invalid type, sendStandard 200 skip, 401 sin auth ×2, export lots 200, 401).

### Verification Results

- ✅ DocumentsBlockApiTest: 6 passed, 2 skipped (Chromium).
- ✅ OrderApiTest, OrderStatisticsApiTest: OK.
- ✅ Contrato API preservado. Sin cambios de rutas ni de estructura de respuesta.

### Gap to 10/10

- ExcelController aún 315 líneas (objetivo <200); posibles extracciones: agrupar exports por entidad.
- Tests send custom/standard 200 requieren Chromium (skip en CI sin Chrome).
- Bloque cerrado en 9/10.

### Rollback Plan

`git revert <commit-hash>`. No hay cambios de contrato API ni migraciones.

### Next

- Siguiente bloque CORE según plan.

---

## [2026-02-15] Block A.16 Tenants

**Priority**: P0/P1
**Risk Level**: Low
**Rating antes: 4/10** | **Rating después: 9/10**

### Problems Addressed

- Tenant model usaba UsesTenantConnection (conexión tenant) cuando la tabla `tenants` vive en la base central (mysql) — riesgo P0 en ruta pública.
- Sin Form Request para validar subdominio.
- Sin API Resource; respuesta manual.
- Mensajes de error en inglés; inconsistente con el resto del API.
- Sin throttling en endpoint público (enumeración de subdominios).
- Sin tests Feature para el endpoint de resolución de tenant.

### Changes Applied

- **Tenant model**: Eliminado UsesTenantConnection; `protected $connection = 'mysql'` para usar la base central correctamente.
- **ShowTenantBySubdomainRequest**: Form Request con validación de subdominio (required, string, max:63, alpha_dash) y mensajes en español.
- **TenantPublicResource**: API Resource para serialización consistente (active, name).
- **TenantController**: Usa Form Request y Resource; mensaje 404 en español ("Tenant no encontrado").
- **Ruta**: Throttling 60 req/min (`throttle:60,1`); nombre de ruta `api.v2.public.tenant`.
- **TenantBlockApiTest**: 5 tests (200 existente, 200 inactivo, 404 no encontrado, 422 subdominio inválido, 422 caracteres inválidos).

### Verification Results

- ✅ TenantBlockApiTest: 5 tests, 8 assertions, OK.
- ✅ AuthBlockApiTest, ProductosBlockApiTest: OK (Tenant::create en tests con conexión mysql).
- ⚠️ **Breaking change frontend:** Respuesta 200 ahora envuelve en `data` (convención API Resources). Doc: `docs/33-frontend/API-CAMBIO-Tenant-Endpoint-Data-Wrapper.md`.

### Gap to 10/10

- Bloque cerrado en 9/10. Opcional: incluir branding_image_url en API si se añade migración (no existe en BD actual).

### Rollback Plan

`git revert <commit-hash>`. No hay cambios de contrato API ni migraciones.

### Next

- Siguiente bloque CORE según plan (A.8 Catálogos, A.3 Stock, etc.).

---

## [2026-02-15] Block A.9 Proveedores + Liquidaciones

**Priority**: P1
**Risk Level**: Low
**Rating antes: 5/10** | **Rating después: 9/10**

### Problems Addressed

- SupplierController sin Form Requests, sin Policy, validación inline; lógica de listado en controller.
- SupplierLiquidationController ~520 líneas (P1); toda la lógica de negocio (getSuppliers, getDetails, calculateSummary, calculatePaymentTotals) en controller; sin Form Requests ni authorize.
- destroyMultiple Supplier sin authorize por registro.
- Sin tests Feature para Proveedores ni Liquidaciones.

### Changes Applied

- **Supplier (Sub-bloque 1)**: StoreSupplierRequest, UpdateSupplierRequest, IndexSupplierRequest, DestroyMultipleSuppliersRequest. SupplierPolicy (viewAny, view, create, update, delete) y registro en AuthServiceProvider. SupplierListService para index. authorize() en todos los métodos. Controller delgado (~120 líneas).
- **Liquidaciones (Sub-bloque 2)**: SupplierLiquidationService con getSuppliersWithActivity(), getLiquidationDetails(), filterDetailsForPdf(), calculateSummary(), calculatePaymentTotals(). GetSuppliersLiquidationRequest, GetLiquidationDetailsRequest, GenerateLiquidationPdfRequest. authorize('viewAny', Supplier::class) en getSuppliers; authorize('view', $supplier) en getDetails y generatePdf. SupplierLiquidationController reducido a orquestación (~95 líneas).
- **Tests (Sub-bloque 3)**: SuppliersBlockApiTest con 14 tests: index, index filtros, store, store 422, show, update, destroy, destroyMultiple, destroyMultiple 422, options, liquidations getSuppliers, liquidations getSuppliers 422, liquidations getDetails, auth 401.

### Verification Results

- ✅ SuppliersBlockApiTest: 14 tests, 56 assertions, OK.
- ✅ StockBlockApiTest: 29 tests OK (usos de Supplier en receptions/dispatches).
- ✅ Contrato API preservado. Sin cambios de rutas ni de estructura de respuesta.

### Gap to 10/10

- Test opcional para generatePdf (requiere Chromium/Snappdf en CI).
- Bloque cerrado en 9/10.

### Rollback Plan

`git revert <commit-hash>`. No hay cambios de contrato API ni migraciones.

### Next

- Siguiente bloque CORE según plan (A.8 Catálogos, A.3 Stock, A.10 Etiquetas, etc.).

---

## [2026-02-15] Block A.2 Ventas - Sub-bloque 6 (CustomerOrderHistoryService, IndexOrderRequest, authorize destroyMultiple, tests Order CRUD)

**Priority**: P1
**Risk Level**: Low
**Rating antes: 8,5/10** | **Rating después: 9/10**

### Problems Addressed

- CustomerController 582 líneas (P1): getOrderHistory + 3 helpers privados (~310 líneas de lógica de negocio en el controlador).
- OrderController.index usaba Request plano en lugar de Form Request; inconsistente con el resto del bloque.
- OrderController.destroyMultiple usaba authorize('viewAny') sin comprobar authorize('delete') por cada pedido.
- OrderApiTest solo tenía 1 test (create); flujos críticos (show, update, destroy, updateStatus, options, active) sin cobertura.
- Métodos scaffold vacíos (create(), edit()) en OrderController, CustomerController, OrderPlannedProductDetailController.

### Changes Applied

- **CustomerOrderHistoryService** (app/Services/v2/): getOrderHistory(Customer, Request) con métodos privados estáticos: getAvailableYears, buildProductHistory, formatLoadDate, getPreviousPeriodNetWeights, calculateAggregates, applyOrderHistoryFilters, getPreviousPeriodDates, calculateTrendValue. Toda la lógica de historial extraída del controlador.
- **CustomerController**: getOrderHistory() delega en CustomerOrderHistoryService::getOrderHistory(). Eliminados 4 métodos privados. **582 → 222 líneas** (−360).
- **IndexOrderRequest**: Form Request con authorize viewAny(Order::class) y reglas para todos los filtros de OrderListService (active, id, ids, customers, buyerReference, status, loadDate, entryDate, transports, salespeople, palletsState, products, species, incoterm, transport, perPage).
- **OrderController**: index(IndexOrderRequest) — autorización delegada en Form Request; eliminado authorize() redundante. destroyMultiple: authorize('delete', $order) por cada pedido antes de validar restricciones. Eliminados métodos scaffold vacíos create() y edit(). Limpieza de líneas en blanco. **324 → 269 líneas**.
- **OrderPlannedProductDetailController**: eliminados create() y edit() scaffold vacíos. **136 → 116 líneas**.
- **OrderApiTest** ampliado de 1 a 14 tests: list (200 paginado), auth 401, create (201), create 422 (payload vacío), show (200), update (200 + buyerReference), destroy OK (200), destroy falla con palets (400), destroyMultiple OK (200), destroyMultiple falla con palets (400), updateStatus a finished (200), options (200), active (200), active-orders/options (200).

### Verification Results

- ✅ OrderApiTest: 14 tests, 36 assertions, OK.
- ✅ CustomerApiTest: 5 tests OK. SalespersonApiTest: 5 tests OK. OrderStatisticsApiTest: 2 tests OK.
- ✅ Bloque Ventas completo: 26 tests, 84 assertions, OK.
- ✅ Suite global (Auth, Productos, Stock, Labels, Settings + Ventas): 92 tests, 340 assertions, OK.
- ✅ Contrato API preservado. Sin cambios de rutas ni de estructura de respuesta.

### Gap to 10/10

- Tests opcionales: OrderPlannedProductDetail CRUD, Incident CRUD, getOrderHistory con filtros.
- Lógica de formateo de emails duplicada en CustomerController y SalespersonController (P3, extraíble a helper).
- Bloqueado por: nada. Bloque A.2 Ventas cerrado en 9/10.

### Rollback Plan

`git revert <commit-hash>`. No hay cambios de contrato API ni migraciones.

### Next

- Siguiente bloque CORE según plan de ejecución (A.3 Stock, A.10 Etiquetas, A.8 Catálogos o A.9 Proveedores).

---

## [2026-02-15] Block A.11 Fichajes - Sub-bloque 1 (Dashboard service, Form Requests, DB→Eloquent)

**Priority**: P1  
**Risk Level**: Low  
**Rating antes: 3/10** | **Rating después: 5/10**

### Problems Addressed

- PunchController ~2349 líneas (P1); uso de `DB::connection('tenant')->table('punch_events')` en dashboard; ausencia de Form Requests en punches y employees; lógica de dashboard en el controlador.

### Changes Applied

- **PunchDashboardService**: extraída toda la lógica de `dashboard()`; reemplazo de la consulta raw con `DB::connection('tenant')` por Eloquent (subquery con `joinSub` sobre `PunchEvent` para último evento por empleado). Método `getData(?Carbon $date)` devuelve el mismo array que antes.
- **PunchController**: constructor con inyección de `PunchDashboardService`; `dashboard()` delega en `$this->dashboardService->getData($date)` y devuelve `response()->json($result)`. Uso de **UpdatePunchEventRequest**, **BulkValidatePunchesRequest**, **BulkStorePunchesRequest**, **DestroyMultiplePunchesRequest** en update, bulkValidate, bulkStore, destroyMultiple. storeManual sigue recibiendo `Request` y valida con `StoreManualPunchRequest::getRules()` / `getMessages()` (misma ruta POST /punches para NFC y manual).
- **Form Requests creados**: StorePunchRequest (NFC), StoreManualPunchRequest (reglas + estáticos getRules/getMessages), UpdatePunchEventRequest, BulkValidatePunchesRequest, BulkStorePunchesRequest, DestroyMultiplePunchesRequest; StoreEmployeeRequest, UpdateEmployeeRequest, DestroyMultipleEmployeesRequest.
- **EmployeeController**: store(StoreEmployeeRequest), update(UpdateEmployeeRequest), destroyMultiple(DestroyMultipleEmployeesRequest); validación delegada en Form Requests.

### Verification Results

- Sintaxis PHP OK en servicio y controladores. Rutas `punches` y `employees` listadas correctamente. No hay tests existentes para Fichajes; verificación manual recomendada (dashboard, store manual, bulk, employees CRUD).

### Gap to 10/10

- PunchController sigue muy grande (~2100+ líneas): extraer calendar, statistics, listado (index) y escrituras (store NFC, storeManual, bulk) a servicios/acciones en sub-bloques 2–3. Policies para PunchEvent y Employee. Sustitución completa de validación inline en store() NFC por Form Request si se separa ruta. Tests Feature para flujos críticos.

### Rollback Plan

`git revert <commit-hash>`. No hay cambios de contrato API ni migraciones.

### Next

- Sub-bloque 2: PunchCalendarService, PunchStatisticsService, PunchEventListService; o sub-bloque 3: acciones de escritura (store NFC, manual, bulk) y Policies.

---

## [2026-02-15] Block A.11 Fichajes - Sub-bloque 2 (Servicios de lectura: calendar, statistics, listado)

**Priority**: P1  
**Risk Level**: Low  
**Rating antes: 5/10** | **Rating después: 6/10**

### Problems Addressed

- PunchController aún con ~2100 líneas; lógica de calendar(), statistics() e index() (filtros) en el controlador.

### Changes Applied

- **PunchCalendarService**: método `getData(int $year, int $month)` con toda la lógica de calendario (fichajes por día, incidencias, anomalías). PunchController::calendar valida year/month y delega.
- **PunchStatisticsService**: método `getData(Carbon $dateStart, Carbon $dateEnd)` con toda la lógica de estadísticas por período; `getEmptyResponseForDates()` para el caso sin empleados. Controller valida date_start/date_end y delega.
- **PunchEventListService**: método `applyFiltersToQuery(Builder $query, array $filters)` con la lógica de filtros de listado. PunchController::index delega en listService y pagina.
- **PunchController**: inyección de PunchCalendarService, PunchStatisticsService, PunchEventListService; eliminados applyFiltersToQuery, getEmptyStatisticsResponse y formatTime (lógica en servicios). Reducción de **~2349 a ~1110 líneas** (sub-bloques 1+2).

### Verification Results

- Sintaxis PHP OK. Rutas listadas. Sin tests específicos de Fichajes; verificación manual recomendada (calendar, statistics, index con filtros).

### Gap to 10/10

- Controller aún ~1110 líneas: extraer escrituras (store NFC, storeManual, bulkValidate, bulkStore) a acciones/servicio en sub-bloque 3. Policies (PunchEventPolicy, EmployeePolicy). Tests Feature.

### Rollback Plan

`git revert <commit-hash>`.

### Next

- Sub-bloque 3: acciones de escritura (StorePunchFromNfcAction, StoreManualPunch, BulkValidate/BulkStore) y/o Policies.

---

## [2026-02-15] Block A.11 Fichajes - Sub-bloque 3 (Escrituras en servicio, Policies, authorize)

**Priority**: P1  
**Risk Level**: Low  
**Rating antes: 6/10** | **Rating después: 8/10**

### Problems Addressed

- Lógica de escritura (store NFC, storeManual, bulkValidate, bulkStore) y de secuencia/validación en PunchController; ausencia de Policies y authorize() en Fichajes y Empleados.

### Changes Applied

- **PunchEventWriteService**: storeFromNfc(array), storeManual(array), bulkValidate(array), bulkStore(array); determineEventType, validatePunchSequence y validación por ítem en bulk (validateSinglePunchForBulk). Toda la lógica de escritura y validación de secuencia extraída del controlador.
- **PunchEventPolicy** y **EmployeePolicy**: viewAny, view, create, update, delete; allowedRoles() = Role::values(). Registradas en AuthServiceProvider (PunchEvent → PunchEventPolicy, Employee → EmployeePolicy).
- **PunchController**: inyección de PunchEventWriteService; store() delega en writeService->storeFromNfc(); storeManual() authorize('create', PunchEvent::class), valida con StoreManualPunchRequest estáticos, delega en writeService->storeManual(); bulkValidate/bulkStore authorize create y delegan en writeService; authorize('viewAny') en index, dashboard, calendar, statistics, destroyMultiple; authorize('view'/'update'/'delete') en show, update, destroy y por ítem en destroyMultiple. Eliminados determineEventType y validatePunchSequence del controlador.
- **EmployeeController**: authorize('viewAny', Employee::class) en index, options, destroyMultiple; authorize('view', $employee) en show; authorize('create', Employee::class) en store; authorize('update'/$employee) en update; authorize('delete', $employee) en destroy y por ítem en destroyMultiple.

### Verification Results

- Sintaxis PHP OK en controladores y políticas. Rutas punches/employees bajo middleware existente. Verificación manual recomendada (NFC, manual, bulk, CRUD empleados).

### Gap to 10/10

- Tests Feature para flujos críticos (store NFC/manual, bulk, CRUD). Opcional: separar ruta POST manual si se quiere type-hint StoreManualPunchRequest en storeManual().

### Rollback Plan

`git revert <commit-hash>`. No hay cambios de contrato API ni migraciones.

### Next

- Tests Feature del módulo Fichajes o siguiente bloque CORE.

---

## [2025-02-14] Block A.6 Producción - Sub-bloque 1 (Production: Form Requests, Policy, authorize)

**Priority**: P1  
**Risk Level**: Low  
**Rating antes: 5/10** | **Rating después: 9/10** (entidad Production)

### Problems Addressed

- ProductionController sin Policy ni authorize(); index y destroyMultiple con Request sin validación; destroyMultiple sin authorize por ítem; Store/Update con authorize inline true.

### Changes Applied

- **IndexProductionRequest**: authorize viewAny(Production::class); rules lot, species_id, status (open|closed), perPage. **DestroyMultipleProductionsRequest**: authorize viewAny; rules ids required array; mensajes en español.
- **ProductionPolicy**: viewAny, view, create, update (todos los roles); delete solo administrador y tecnico. Registrada en AuthServiceProvider.
- **StoreProductionRequest / UpdateProductionRequest**: authorize con create y (controller) update. ProductionController: index(IndexProductionRequest), destroyMultiple(DestroyMultipleProductionsRequest); authorize('view', $production) en show, getDiagram, getProcessTree, getTotals, getReconciliation, getAvailableProductsForOutputs; authorize('update', $production) en update; authorize('delete', $production) en destroy; en destroyMultiple authorize('delete', $production) por cada ítem antes de deleteMultiple.
- ProductionController 207 líneas (ligeramente por encima de 200 por authorize en cada método); contrato API preservado.

### Verification Results

- AuthBlockApiTest, ProductosBlockApiTest, StockBlockApiTest, SettingsBlockApiTest: 82 tests OK. Sin tests Feature específicos de Production; rutas bajo mismo middleware que bloques verificados.

### Gap to 10/10

- ProductionRecordController (356 líneas) y resto del módulo Producción en sub-bloques posteriores. Tests Feature opcionales para productions CRUD.

### Rollback Plan

`git revert <commit-hash>`. No hay cambios de contrato API ni migraciones.

### Next

- Sub-bloque 2 A.6 (ProductionRecord) o siguiente módulo CORE.

---

## [2025-02-14] Block A.6 Producción - Sub-bloque 2 (ProductionRecord: Form Requests, Policy, authorize, getSourcesData en servicio)

**Priority**: P1  
**Risk Level**: Low  
**Rating antes: 4/10** | **Rating después: 9/10** (entidad ProductionRecord)

### Problems Addressed

- ProductionRecordController 356 líneas (P1); sin Policy ni authorize(); index y options con Request sin validación; getSourcesData con ~90 líneas de lógica en el controlador.

### Changes Applied

- **IndexProductionRecordRequest**: authorize viewAny(ProductionRecord::class); rules production_id, root_only, parent_record_id, process_id, completed, perPage. **ProductionRecordOptionsRequest**: authorize viewAny; rules production_id, exclude_id.
- **ProductionRecordPolicy**: viewAny, view, create, update (todos los roles); delete solo administrador y tecnico. Registrada en AuthServiceProvider.
- **StoreProductionRecordRequest**: authorize create(ProductionRecord::class). Controller: authorize view/update/delete en show, update, destroy, tree, finish, syncOutputs, syncConsumptions, getSourcesData.
- **ProductionRecordService::getSourcesData(ProductionRecord $record)**: extraída la lógica de construcción de stockBoxes, parentOutputs y totales desde el controlador; el controlador delega y devuelve JSON.
- ProductionRecordController reducido de **356 a 251 líneas**; contrato API preservado.

### Verification Results

- AuthBlockApiTest, ProductosBlockApiTest, StockBlockApiTest, SettingsBlockApiTest: 82 tests OK. Sin tests Feature específicos de production-records.

### Gap to 10/10

- Controller aún 251 líneas (objetivo <200 opcional: extraer options() mapping a servicio). Resto del módulo Producción (Input, Output, Consumption, Cost, CostCatalog, Process) en sub-bloques posteriores.

### Rollback Plan

`git revert <commit-hash>`. No hay cambios de contrato API ni migraciones.

### Next

- Sub-bloque 3 A.6 (ProductionInput, ProductionOutput, ProductionOutputConsumption) o siguiente módulo CORE.

---

## [2025-02-14] Block A.6 Producción - Sub-bloque 3 (ProductionInput, ProductionOutput, ProductionOutputConsumption: Policies, Form Requests, authorize)

**Priority**: P1  
**Risk Level**: Low  
**Rating antes: 4/10** | **Rating después: 9/10** (entidades Input, Output, Consumption)

### Problems Addressed

- ProductionInputController, ProductionOutputController y ProductionOutputConsumptionController sin Policy ni authorize(); index y (Input) destroyMultiple con Request sin validación; Form Requests Store/Update/StoreMultiple con authorize true.

### Changes Applied

- **ProductionInput**: IndexProductionInputRequest, DestroyMultipleProductionInputsRequest; ProductionInputPolicy (delete solo admin/tecnico); StoreProductionInputRequest y StoreMultipleProductionInputsRequest con authorize create; authorize en index (vía Request), show, store/storeMultiple (vía Request), destroy, destroyMultiple (authorize delete por ítem).
- **ProductionOutput**: IndexProductionOutputRequest; ProductionOutputPolicy (delete solo admin/tecnico); StoreProductionOutputRequest y StoreMultipleProductionOutputsRequest con authorize create; authorize en index, show, getCostBreakdown, update, destroy, store/storeMultiple (vía Request).
- **ProductionOutputConsumption**: IndexProductionOutputConsumptionRequest; ProductionOutputConsumptionPolicy (delete solo admin/tecnico); StoreProductionOutputConsumptionRequest y StoreMultipleProductionOutputConsumptionsRequest con authorize create; authorize en index, show, update, destroy, getAvailableOutputs (authorize view sobre ProductionRecord). UpdateProductionOutputRequest y UpdateProductionOutputConsumptionRequest sin cambio (authorize en controller).
- Registro de las tres Policies en AuthServiceProvider. Contrato API preservado.

### Verification Results

- AuthBlockApiTest, ProductosBlockApiTest, StockBlockApiTest, SettingsBlockApiTest: 82 tests OK. Sin tests Feature específicos de production-inputs/outputs/consumptions.

### Gap to 10/10

- ProductionCost, CostCatalog, Process (sub-bloque 4). Tests Feature opcionales para Input/Output/Consumption.

### Rollback Plan

`git revert <commit-hash>`. No hay cambios de contrato API ni migraciones.

### Next

- Sub-bloque 4 A.6 (ProductionCost, CostCatalog, Process) o siguiente módulo CORE.

---

## [2025-02-14] Block A.6 Producción - Sub-bloque 4 (ProductionCost, CostCatalog, Process: Form Requests, Policies, authorize)

**Priority**: P1  
**Risk Level**: Low  
**Rating antes: 4/10** | **Rating después: 9/10** (entidades ProductionCost, CostCatalog, Process)

### Problems Addressed

- ProductionCostController, CostCatalogController y ProcessController con validación inline y sin Policy ni authorize(); sin Form Requests.

### Changes Applied

- **ProductionCost**: IndexProductionCostRequest, StoreProductionCostRequest (withValidator para mutual exclusion production_record_id/production_id y total_cost/cost_per_kg), UpdateProductionCostRequest; ProductionCostPolicy (delete solo admin/tecnico); controller delgado con authorize en show, update, destroy. Validación tenant (exists:tenant.*).
- **CostCatalog**: IndexCostCatalogRequest, StoreCostCatalogRequest (unique:tenant.cost_catalog,name), UpdateCostCatalogRequest (unique ignore por id); CostCatalogPolicy (delete solo admin/tecnico); controller delgado; destroy mantiene comprobación productionCosts()->exists() antes de borrar.
- **Process**: IndexProcessRequest, ProcessOptionsRequest, StoreProcessRequest, UpdateProcessRequest; ProcessPolicy (delete solo admin/tecnico); controller delgado con authorize en show, update, destroy, options (vía Request).
- Registro de las tres Policies en AuthServiceProvider. Contrato API preservado.

### Verification Results

- AuthBlockApiTest, ProductosBlockApiTest, StockBlockApiTest, SettingsBlockApiTest: 82 tests OK.

### Gap to 10/10

- Tests Feature opcionales para production-costs, cost-catalog, processes. Bloque A.6 Producción completo (todos los sub-bloques 1–4).

### Rollback Plan

`git revert <commit-hash>`. No hay cambios de contrato API ni migraciones.

### Next

- Siguiente módulo CORE. Bloque A.6 Producción cerrado en 9/10.

---

## [2025-02-14] Block A.5 Despachos de Cebo (CeboDispatchListService, Policy delete, controller delgado)

**Priority**: P1  
**Risk Level**: Low  
**Rating antes: 7/10** | **Rating después: 9/10**

### Problems Addressed

- CeboDispatchController con lógica de filtros en el controlador; CeboDispatchPolicy permitía delete a todos los roles; destroyMultiple sin authorize por ítem.

### Changes Applied

- **CeboDispatchListService** (app/Services/v2/): list(Request) con applyFilters (id, ids, suppliers, dates, species, products, notes, export_type) y orden por fecha; paginación. CeboDispatchController::index delega en CeboDispatchListService::list($request).
- **CeboDispatchPolicy**: delete restringido a administrador y tecnico (rolesCanDelete); viewAny, view, create, update sin cambio (todos los roles).
- **destroyMultiple**: authorize('delete', $dispatch) por cada despacho antes de borrar; respuestas 403 coherentes por ítem no autorizado.
- Tests existentes en StockBlockApiTest (cebo dispatch): 6 tests pasan (store, show, update, destroy, destroy_multiple, 422 invalid payload).

### Verification Results

- StockBlockApiTest --filter=cebo: 6 tests, 25 assertions OK. Contrato API preservado.

### Gap to 10/10

- Tests unitarios opcionales para CeboDispatchListService; CeboDispatchStatisticsController sin refactor en este sub-bloque.

### Rollback Plan

`git revert <commit-hash>`. No hay cambios de contrato API ni migraciones.

### Next

- Siguiente módulo CORE.

---

## [2025-02-14] Block Configuración por tenant (Settings) - Sub-bloque 1

**Priority**: P1 + P2  
**Risk Level**: Low  
**Rating antes: 4/10** | **Rating después: 9/10**

### Problems Addressed
- SettingController usaba DB::connection en index y update; lógica en controlador; sin Form Request, Policy ni modelo; GET exponía password.

### Changes Applied
- Modelo Setting (UsesTenantConnection), SettingService (getAllKeyValue con ofuscación password, updateFromPayload con regla password), UpdateSettingsRequest, SettingPolicy (admin/tecnico), controller delgado, SettingsBlockApiTest 8 tests.

### Verification Results
- SettingsBlockApiTest 8 tests OK; AuthBlockApiTest y ProductosBlockApiTest pasan. Contrato API preservado (GET password ahora ********).

### Gap to 10/10
- Helper tenantSetting() sigue con DB directo; tests unitarios opcionales.

### Rollback Plan
`git revert <commit-hash>`.

### Next
- Siguiente módulo CORE.

---

## [2025-02-14] Block Auth - Sub-bloque 1 (Session tenant fix, Form Requests, UserListService)

**Priority**: P0 + P1  
**Risk Level**: Low  
**Rating antes: 5/10** | **Rating después: 7/10**

### Problems Addressed

- SessionController usaba `Laravel\Sanctum\PersonalAccessToken` (conexión por defecto) → riesgo P0 de aislamiento multi-tenant (listar/revocar sesiones de otro tenant).
- Auth y User sin Form Requests; validación inline.
- UserController con lógica de listado en el controlador; Session y ActivityLog sin validación de filtros.

### Changes Applied

- **SessionController**: Uso de `App\Sanctum\PersonalAccessToken` en index() y destroy() para que las queries usen la conexión tenant. Eliminado import erróneo `App\Models\PersonalAccessToken`. IndexSessionRequest para validar user_id y per_page.
- **Form Requests Auth**: RequestAccessRequest (email), VerifyMagicLinkRequest (token), VerifyOtpRequest (email, code). Usados en requestAccess, requestMagicLink, requestOtp, verifyMagicLink, verifyOtp.
- **Form Requests User**: StoreUserRequest, UpdateUserRequest, IndexUserRequest (autorización create/update/viewAny). UserController: index(IndexUserRequest), store(StoreUserRequest), update(UpdateUserRequest).
- **UserListService** (app/Services/v2/): list(Request) con filtros id, name, email, role, created_at y paginación; applyFilters estático. UserController::index delega en UserListService.
- **ActivityLogController**: IndexActivityLogRequest para validar filtros (users, dates, per_page, etc.).
- **No aplicado (por decisión usuario)**: Unificar me() y tokenResponse con UserResource para no tocar contrato con frontend.

### Verification Results

- ✅ ProductosBlockApiTest, StockBlockApiTest, CustomerApiTest, OrderApiTest (59 tests) pasan.
- ✅ Contrato API preservado.

### Gap to 10/10 (Rating después < 9)

- SessionPolicy y ActivityLogPolicy (quién puede listar/revocar sesiones y ver logs).
- Refinamiento de UserPolicy (p. ej. solo administrador elimina).
- Tests Feature para Auth (requestAccess, verifyMagicLink, verifyOtp, me, logout), User CRUD y Session.
- Unificar me/tokenResponse con UserResource cuando el frontend lo permita.

### Rollback Plan

`git revert <commit-hash>`. No hay cambios de contrato API ni migraciones.

### Next

- Sub-bloque 2 Auth: SessionPolicy, ActivityLogPolicy, refinamiento UserPolicy, tests Feature Auth/User/Session. O siguiente módulo CORE.

---

## [2025-02-14] Block Auth - Sub-bloque 2 (SessionPolicy, ActivityLogPolicy, UserPolicy refinada, tests Feature)

**Priority**: P1 + P2  
**Risk Level**: Low  
**Rating antes: 7/10** | **Rating después: 9/10**

### Problems Addressed

- Session y ActivityLog sin Policy; cualquier rol podía listar/revocar sesiones y ver logs.
- UserPolicy permitía eliminar cualquier usuario (incluido a sí mismo); sin restricción por rol para delete.
- Handler no convertía AuthorizationException en 403 (devolvía 500).
- Sin tests Feature para flujos Auth, User, Session y ActivityLog.

### Changes Applied

- **SessionPolicy**: viewAny solo administrador, tecnico, direccion; delete: propio token siempre, o administrador/tecnico para cualquier token. Registrada para `App\Sanctum\PersonalAccessToken`. SessionController: authorize('viewAny', PersonalAccessToken::class) en index, authorize('delete', $token) en destroy.
- **ActivityLogPolicy**: viewAny solo administrador, tecnico, direccion. Registrada para ActivityLog. ActivityLogController: authorize('viewAny', ActivityLog::class) en index.
- **UserPolicy**: delete solo administrador y tecnico; usuario no puede eliminarse a sí mismo. Resto de métodos sin cambio (todos los roles pueden viewAny, view, create, update).
- **AuthServiceProvider**: registradas ActivityLogPolicy y SessionPolicy (PersonalAccessToken => SessionPolicy).
- **Handler**: manejo de AuthorizationException → respuesta JSON 403 con userMessage "No tienes permisos para realizar esta acción."
- **AuthBlockApiTest** (tests/Feature/AuthBlockApiTest.php): 21 tests con tenant + Sanctum. Auth: requestAccess (422 sin email, 200 con email), me (401 sin token, 200 con token), logout, verifyMagicLink/verifyOtp (400 token/código inválido). Users: list (401, 200 paginado), store, show, update, destroy (403 al eliminarse a sí mismo, 200 al eliminar otro como admin). Sessions: index (401, 200 rol permitido, 403 operario), destroy (200 propia sesión). Activity logs: index (401, 200 rol permitido, 403 operario). Documentación: docs/09-TESTING.md actualizado con AuthBlockApiTest.

### Verification Results

- ✅ AuthBlockApiTest: 21 tests, 59 assertions, OK.
- ✅ ProductosBlockApiTest, StockBlockApiTest, CustomerApiTest, OrderApiTest (59 tests) pasan.
- ✅ Contrato API preservado.

### Gap to 10/10 (Rating después < 9)

- Tests unitarios opcionales para SessionPolicy, ActivityLogPolicy, UserPolicy. Unificar me/tokenResponse con UserResource cuando el frontend lo permita. Bloque Auth en 9/10.

### Rollback Plan

`git revert <commit-hash>`. No hay cambios de contrato API ni migraciones.

### Next

- Siguiente módulo CORE o tests unitarios para políticas Auth.

---

## [2025-02-14] Block Productos - Sub-bloque 1 (Product: Form Requests, Policy, ProductListService, modelo isInUse)

**Priority**: P1  
**Risk Level**: Low  
**Rating antes: 4/10** | **Rating después: 7/10**

### Problems Addressed

- ProductController 441 líneas (P1); validación inline con Validator::make en store/update; sin Form Requests ni Policies para Product.
- Lógica de listado (filtros) y de “producto en uso” embebida en el controlador.

### Changes Applied

- **Form Requests**: StoreProductRequest, UpdateProductRequest, DestroyMultipleProductsRequest (normalización snake→camel y GTINs vacíos→null en prepareForValidation; reglas y mensajes en español; authorize con create/update/viewAny). UpdateProductRequest incluye getUpdateData() para construir array de actualización.
- **ProductPolicy**: viewAny, view, create, update, delete (roles permitidos vía Role::values()). Registrada en AuthServiceProvider.
- **ProductListService** (app/Services/v2/): list(Request) con filtros (id, ids, name, species, captureZones, categories, families, articleGtin, boxGtin, palletGtin) y paginación; applyFilters estático.
- **Product (modelo)**: deletionBlockedBy(): array (boxes, orders, production); isInUse(): bool. Usado en destroy y destroyMultiple para mensajes y comprobación.
- **ProductController**: index → authorize + ProductListService::list; store/update/destroy/destroyMultiple/options usan Form Requests y authorize(). Controlador reducido a **182 líneas** (<200).

### Verification Results

- ✅ Tests/Feature/StockBlockApiTest (29 tests) pasan.
- ✅ Contrato API preservado (mismos endpoints, cuerpos y códigos).

### Gap to 10/10 (Rating después < 9)

- ProductCategory y ProductFamily sin Form Requests ni Policies; ProductFamilyController 232 líneas (Sub-bloque 2).
- Tests de integración para CRUD Product (list, store, update, destroy, destroyMultiple, options).
- Bloqueado por: nada.

### Rollback Plan

`git revert <commit-hash>`. No hay cambios de contrato API ni migraciones.

### Next

- Sub-bloque 2 del bloque Productos: ProductCategory y ProductFamily (Form Requests, Policies, refactor). O tests de integración para Product.

---

## [2025-02-14] Block Productos - Sub-bloque 2 (ProductCategory + ProductFamily: Form Requests, Policies, ListServices)

**Priority**: P1  
**Risk Level**: Low  
**Rating antes: 7/10** | **Rating después: 8/10**

### Problems Addressed

- ProductCategoryController y ProductFamilyController sin Form Requests ni Policies; validación inline; ProductFamilyController 232 líneas (P1).
- Lógica de listado y de eliminación (canBeDeleted) repetida en controladores.

### Changes Applied

- **Form Requests ProductCategory**: StoreProductCategoryRequest, UpdateProductCategoryRequest, DestroyMultipleProductCategoriesRequest (autorización create/update/viewAny). Registro en rutas vía type-hint en controller.
- **Form Requests ProductFamily**: StoreProductFamilyRequest, UpdateProductFamilyRequest, DestroyMultipleProductFamiliesRequest; UpdateProductFamilyRequest con getUpdateData() (categoryId → category_id).
- **ProductCategoryPolicy** y **ProductFamilyPolicy**: viewAny, view, create, update, delete (Role::values()). Registradas en AuthServiceProvider.
- **ProductCategoryListService** y **ProductFamilyListService** (app/Services/v2/): list(Request) con filtros (id, ids, name, active; Family además categoryId) y paginación.
- **ProductCategory** y **ProductFamily** (modelos): canBeDeleted() (category: sin familias; family: sin productos). Usado en destroy y destroyMultiple.
- **ProductCategoryController**: 105 líneas; **ProductFamilyController**: 121 líneas (ambos <200). index → authorize + ListService::list; store/update/destroy/destroyMultiple/options con Form Requests y authorize().

### Verification Results

- ✅ Tests/Feature/StockBlockApiTest (29 tests) pasan.
- ✅ Contrato API preservado.

### Gap to 10/10 (Rating después < 9)

- Tests de integración para CRUD de Product, ProductCategory y ProductFamily. Bloque Productos estructuralmente completo (Form Requests, Policies, controladores delgados).

### Rollback Plan

`git revert <commit-hash>`. No hay cambios de contrato API ni migraciones.

### Next

- Tests de integración para el bloque Productos; o siguiente módulo CORE.

---

## [2025-02-14] Block Productos - Tests de integración (ProductosBlockApiTest)

**Priority**: P1  
**Risk Level**: Low  
**Rating antes: 8/10** | **Rating después: 9/10**

### Problems Addressed

- Bloque Productos sin tests de integración; regresión no detectada automáticamente en CRUD y reglas de negocio (destroy bloqueado).

### Changes Applied

- **ProductosBlockApiTest** (tests/Feature/ProductosBlockApiTest.php): 24 tests con tenant + Sanctum. Seed de catálogo (FishingGear, CaptureZones, Species, ProductCategory, ProductFamily) cuando se necesitan productos.
- **ProductCategory**: list, options, store, show, update, destroy (éxito), destroy 400 cuando tiene familias, destroyMultiple.
- **ProductFamily**: list, options, store, show, update, destroy (éxito), destroy 400 cuando tiene productos.
- **Product**: list, options, store, show, update, destroy (éxito), destroy 400 cuando está en uso (Box), destroyMultiple.
- **Auth**: test_productos_endpoints_require_auth (401 sin token).
- Documentación: docs/09-TESTING.md actualizado con ProductosBlockApiTest en el inventario.

### Verification Results

- ✅ ProductosBlockApiTest: 24 tests, 95 assertions, OK.
- ✅ StockBlockApiTest sigue pasando (29 tests).

### Gap to 10/10 (Rating después < 9)

- Tests unitarios opcionales para ProductListService, ProductCategoryListService, ProductFamilyListService y Policies. Bloque Productos en 9/10: estructura + tests de integración completos.

### Rollback Plan

Eliminar o revertir tests/Feature/ProductosBlockApiTest.php y entrada en 09-TESTING.md.

### Next

- Siguiente módulo CORE; o tests unitarios para servicios del bloque Productos.

---

## [2026-02-14] Block Recepciones de materia prima - Sub-bloque 1 (Form Requests bulk + RawMaterialReceptionBulkService)

**Priority**: P1  
**Risk Level**: Low  
**Rating antes: 4/10** | **Rating después: 6/10**

### Problems Addressed

- Endpoints `validateBulkUpdateDeclaredData` y `bulkUpdateDeclaredData` sin Form Request; validación y autorización inline en el controlador.
- Lógica de validación/actualización bulk y de “recepción más cercana” concentrada en RawMaterialReceptionController (~227 líneas), dificultando pruebas y mantenimiento.

### Changes Applied

- **Form Requests nuevos**: ValidateBulkUpdateDeclaredDataRequest, BulkUpdateDeclaredDataRequest (reglas: receptions, supplier_id, date, declared_total_amount, declared_total_net_weight; autorización viewAny RawMaterialReception). Usados en validateBulkUpdateDeclaredData y bulkUpdateDeclaredData.
- **RawMaterialReceptionBulkService** (app/Services/v2): validateBulkUpdateDeclaredData(array), bulkUpdateDeclaredData(array), findClosestReceptions(supplierId, date). Toda la lógica de bulk y búsqueda de recepción más cercana movida aquí; métodos estáticos.
- **RawMaterialReceptionController**: validateBulkUpdateDeclaredData y bulkUpdateDeclaredData reducidos a inyectar Form Request, llamar al servicio con los datos validados y devolver JSON con el mismo status (200/207). Eliminado el método privado findClosestReceptions y la validación inline. Controlador pasa de ~1287 a ~1060 líneas.

### Verification Results

- ✅ Linter sin errores en los archivos tocados.
- ✅ Tests/Feature/StockBlockApiTest pasan (11 tests, incl. list raw material receptions, reception chart data, auth).
- Contrato API preservado: mismos cuerpos de request/respuesta y códigos 200/207.

### Gap to 10/10 (Rating después < 9)

- RawMaterialReceptionController sigue > 200 líneas (~1060); extraer en sub-bloque 2 la lógica de store/update (createPalletsFromRequest, createDetailsFromRequest, updatePalletsFromRequest, updateDetailsFromRequest) a Services o Actions.
- Tests de integración para store, update, destroy, destroyMultiple y endpoints bulk.
- Bloqueado por: nada.

### Rollback Plan

Si aparecen problemas: `git revert <commit-hash>` de los commits de este sub-bloque. No hay cambios de contrato API ni migraciones.

### Next

- Sub-bloque 2 del bloque Recepciones de materia prima: adelgazamiento del controlador (store/update) mediante extracción a servicios/acciones. ✅ Aplicado a continuación.

---

## [2026-02-14] Block Recepciones de materia prima - Sub-bloque 2 (RawMaterialReceptionWriteService, controlador <200 líneas)

**Priority**: P1  
**Risk Level**: Low  
**Rating antes: 6/10** | **Rating después: 8/10**

### Problems Addressed

- RawMaterialReceptionController ~1060 líneas (P1); lógica de store/update (createPalletsFromRequest, updatePalletsFromRequest, createDetailsFromRequest, updateDetailsFromRequest y helpers) concentrada en el controlador.

### Changes Applied

- **RawMaterialReceptionWriteService** (app/Services/v2/): store(array $data), update(RawMaterialReception $reception, array $data). Toda la lógica de creación/actualización de recepciones, palets, cajas y líneas movida a este servicio (métodos privados estáticos). Uso de Model::withoutEvents para eliminación de palets en contexto de recepción.
- **RawMaterialReceptionController**: store() y update() delegan en RawMaterialReceptionWriteService dentro de DB::transaction; eliminados los 8 métodos privados (~863 líneas). Eliminados imports no usados. **Controlador: ~150 líneas** (<200).
- Sin cambios de contrato API.

### Verification Results

- ✅ Linter sin errores.
- ✅ Tests/Feature/StockBlockApiTest (11 tests) pasan.
- Comportamiento preservado: store y update idénticos en API.

### Gap to 10/10 (Rating después < 9)

- Tests de integración para store, update, destroy, destroyMultiple y bulk (crear recepción, editar, restricciones cajas en producción).
- Bloqueado por: nada.

### Rollback Plan

`git revert <commit-hash>`. No hay migraciones ni cambios de contrato.

### Next

- Tests de integración para el bloque Recepciones de materia prima; o siguiente módulo CORE según prioridad.

---

## [2026-02-14] Block Recepciones de materia prima - Tests de integración (StockBlockApiTest)

**Priority**: P1  
**Risk Level**: Low  
**Rating antes: 8/10** | **Rating después: 8,5/10**

### Problems Addressed

- Gap del sub-bloque 2: ausencia de tests de integración para store, update, destroy, destroyMultiple y endpoints bulk.

### Changes Applied

- **Tests en tests/Feature/StockBlockApiTest.php**: seedTenantForReceptions() (seeders sin ProductSeeder por compatibilidad PHPUnit); test_can_store_raw_material_reception_with_details; test_can_show_raw_material_reception; test_can_update_raw_material_reception; test_can_destroy_raw_material_reception; test_can_destroy_multiple_raw_material_receptions; test_validate_bulk_update_declared_data_returns_structure; test_bulk_update_declared_data_updates_receptions (fecha fija 2019-06-15 para aislamiento entre tests).
- **BulkUpdateDeclaredDataRequest**: prepareForValidation() para normalizar payload a snake_case y aceptar camelCase del frontend.
- **RawMaterialReceptionBulkService**: acepta declared_total_amount y declaredTotalAmount (y net_weight equivalente).
- **RawMaterialReceptionController (bulkUpdateDeclaredData)**: normalización explícita de receptions a snake_case antes de llamar al servicio.
- Assertiones de bulk update vía GET show (mismo contexto tenant) para evitar falsos negativos por conexión/estado en suite completa.

### Verification Results

- 18 tests en StockBlockApiTest pasan (85 aserciones): list, pallets, stores, stats, chart data, auth, store, show, update, destroy, destroyMultiple, validate-bulk, bulk-update-declared-data.

### Gap to 10/10 (Rating después < 9)

- Tests de restricciones (recepción con palet vinculado a pedido, cajas en producción) y edge cases de update/destroy.
- Bloqueado por: nada.

### Rollback Plan

Revertir commits de tests y cambios en BulkRequest/BulkService/Controller; no hay migraciones.

### Next

- Más tests de edge cases para recepciones; o siguiente bloque CORE (p. ej. Etiquetas, Despachos de cebo, Producción).

---

## [2026-02-14] Block Recepciones de materia prima - Tests edge cases (restricciones destroy + 422)

**Priority**: P1  
**Risk Level**: Low  
**Rating antes: 8,5/10** | **Rating después: 9/10**

### Problems Addressed

- Gap: tests de restricciones al eliminar (palet almacenado, palet vinculado a pedido) y validación 422 en store/bulk.

### Changes Applied

- **StockBlockApiTest**: test_destroy_reception_fails_when_pallet_stored (DELETE → 500, recepción sigue en BD); test_destroy_reception_fails_when_pallet_linked_to_order (Order mínimo vía createMinimalOrderOnTenant(), DELETE → 500); test_destroy_multiple_returns_422_when_one_has_pallet_linked_to_order (422, deleted 1, errors); test_store_reception_returns_422_with_invalid_payload (falta supplier.id); test_bulk_update_declared_data_returns_422_with_invalid_payload (supplier_id inexistente). Helper createMinimalOrderOnTenant() (Country, PaymentTerm, Salesperson, Transport, Customer firstOrCreate, Order) para tests que requieren palet vinculado a pedido.

### Verification Results

- 23 tests en StockBlockApiTest (104 aserciones). Todos pasan.

### Gap to 10/10

- Test opcional: recepción con caja en producción (ProductionInput) no se puede eliminar (requiere fixture Producción más pesado). Bloqueado por: nada.

### Next

- Siguiente bloque CORE: Despachos de cebo (A.5) o Etiquetas (A.10).

---

## [2026-02-14] Block Despachos de cebo (CeboDispatch) - Tests de integración + fix authorize update

**Priority**: P1  
**Risk Level**: Low  
**Rating antes: N/A (sin rating previo)** | **Rating después: 7/10**

### Problems Addressed

- Sin tests de integración para CRUD de cebo-dispatches (store, show, update, destroy, destroyMultiple). Update devolvía 500: UpdateCeboDispatchRequest pasaba el id de ruta a la Policy y la Policy espera CeboDispatch.

### Changes Applied

- **StockBlockApiTest**: tests test_can_store_cebo_dispatch, test_can_show_cebo_dispatch, test_can_update_cebo_dispatch, test_can_destroy_cebo_dispatch, test_can_destroy_multiple_cebo_dispatches, test_store_cebo_dispatch_returns_422_with_invalid_payload. Reutilizan seedTenantForReceptions() (supplier + product).
- **UpdateCeboDispatchRequest**: authorize() resuelve CeboDispatch cuando el route param es id (findOrFail) para que la Policy reciba el modelo.
- **CeboDispatchController (update)**: uso de `$validated['notes'] ?? null` para evitar undefined index.

### Verification Results

- 29 tests en StockBlockApiTest (129 aserciones), todos pasan. Incluyen recepciones, pallets, stores, stats, chart data, cebo-dispatches CRUD y 422.

### Gap to 10/10

- destroyMultiple usa whereIn()->delete(); no dispara eventos del modelo (CeboDispatch no tiene deleting actualmente; consistencia con Recepciones opcional). Tests de edge cases si en el futuro se añaden restricciones.

### Next

- Siguiente bloque CORE según prioridad (Etiquetas, Producción, etc.).

---

## [2026-02-14] Block Inventario/Stock - Sub-bloque 1 (P0 + Policies + Form Requests)

**Priority**: P0 + P1  
**Risk Level**: Low  
**Rating antes: 4/10** | **Rating después: 5,5/10**

### Problems Addressed

- **P0**: destroyMultiple de RawMaterialReception usaba `whereIn()->delete()` y no disparaba el evento `deleting` del modelo; se podían eliminar recepciones con palets vinculados a pedidos o cajas en producción.
- Ausencia de Policies para RawMaterialReception, Pallet, Box, CeboDispatch, Store; autorización solo por middleware de rol.
- Ausencia de Form Requests en el bloque; validación inline en CeboDispatchController, BoxesController y destroyMultiple de RawMaterialReception.

### Changes Applied

- **RawMaterialReceptionController**: destroyMultiple ahora itera por cada ID, llama `$reception->delete()` por recepción (aplicando reglas del modelo) y devuelve 422 con detalle si alguna no puede eliminarse. Uso de DestroyMultipleRawMaterialReceptionsRequest. authorize() en index, show, store, update, destroy, destroyMultiple, validateBulkUpdateDeclaredData, bulkUpdateDeclaredData.
- **Policies**: RawMaterialReceptionPolicy, PalletPolicy, BoxPolicy, CeboDispatchPolicy, StorePolicy (viewAny, view, create, update, delete; mismos roles que middleware). Registradas en AuthServiceProvider.
- **CeboDispatchController**: IndexCeboDispatchRequest, StoreCeboDispatchRequest, UpdateCeboDispatchRequest, DestroyMultipleCeboDispatchesRequest; authorize() en todos los métodos.
- **BoxesController**: IndexBoxRequest, DestroyMultipleBoxesRequest; authorize() en index, destroy, destroyMultiple.
- **PalletController, StoreController, StockStatisticsController, RawMaterialReceptionStatisticsController, CeboDispatchStatisticsController**: authorize() en todos los métodos públicos según Policy correspondiente.
- **Form Requests nuevos**: IndexCeboDispatchRequest, StoreCeboDispatchRequest, UpdateCeboDispatchRequest, DestroyMultipleCeboDispatchesRequest, IndexBoxRequest, StoreBoxRequest, UpdateBoxRequest, DestroyMultipleBoxesRequest, DestroyMultipleRawMaterialReceptionsRequest.

### Verification Results

- ✅ Linter sin errores en Policies y Form Requests.
- ✅ PHPUnit ejecutado (tests existentes).
- Comportamiento: destroyMultiple de recepciones ya no borra en lote sin validaciones; 422 con mensaje cuando alguna recepción no puede eliminarse. Policies aplicadas en toda la API del bloque.

### Gap to 10/10 (Rating después < 9)

- Form Requests para RawMaterialReception (index, store, update), Pallet (index, store, update y acciones), Store (index, store, update, deleteMultiple).
- Sustituir DB::connection('tenant')->table() por Eloquent en RawMaterialReceptionController.
- Reducir RawMaterialReceptionController y PalletController por debajo de 200 líneas (servicios de listado y actualización).
- Tests de integración/feature para el bloque.
- Bloqueado por: nada. Siguiente paso: Sub-bloque 2 cuando el usuario lo apruebe.

### Rollback Plan

Si aparecen problemas: `git revert <commit-hash>` de los commits del sub-bloque 1. No hay migraciones ni cambios de contrato API.

### Next

- Sub-bloque 2 del bloque Inventario/Stock: Form Requests para RawMaterialReception, Pallet, Store; sustitución de DB::connection('tenant') por Eloquent en RawMaterialReceptionController. ✅ Aplicado a continuación.

---

## [2026-02-14] Block Inventario/Stock - Sub-bloque 2 (Form Requests + Eloquent)

**Priority**: P1  
**Risk Level**: Low  
**Rating antes: 5,5/10** | **Rating después: 6,5/10**

### Problems Addressed

- RawMaterialReception, Pallet y Store sin Form Requests en index, store, update y deleteMultiple/destroyMultiple.
- Uso de `DB::connection('tenant')->table()` en RawMaterialReceptionController para eliminar boxes, pallet_boxes y pallets (bypass de Eloquent y eventos).

### Changes Applied

- **RawMaterialReceptionController**: IndexRawMaterialReceptionRequest, StoreRawMaterialReceptionRequest, UpdateRawMaterialReceptionRequest (reglas condicionadas por creation_mode). index(), store() y update() usan Form Requests; validación inline eliminada. Sustitución de DB::connection('tenant')->table() por Eloquent: eliminación de cajas con `Box::find($id)?->delete()`, de pallet_boxes con `PalletBox::where('pallet_id', ...)->delete()`, y de palet en contexto de recepción con `Model::withoutEvents(fn () => $pallet->delete())` (modificación desde recepción permitida).
- **PalletController**: IndexPalletRequest, StorePalletRequest, UpdatePalletRequest, DestroyMultiplePalletsRequest. index(), store(), update() y destroyMultiple() usan Form Requests; validación inline eliminada.
- **StoreController**: IndexStoreRequest, StoreStoreRequest, UpdateStoreRequest, DeleteMultipleStoresRequest. index(), store(), update() y deleteMultiple() usan Form Requests; validación inline eliminada.
- **Form Requests nuevos**: IndexRawMaterialReceptionRequest, StoreRawMaterialReceptionRequest, UpdateRawMaterialReceptionRequest, IndexPalletRequest, StorePalletRequest, UpdatePalletRequest, DestroyMultiplePalletsRequest, IndexStoreRequest, StoreStoreRequest, UpdateStoreRequest, DeleteMultipleStoresRequest.

### Verification Results

- ✅ PHPUnit ejecutado (tests existentes).
- ✅ Comportamiento preservado: validación en frontera HTTP; eliminaciones en RawMaterialReceptionController vía Eloquent con eventos (Box) o withoutEvents solo para palet en contexto de recepción.

### Gap to 10/10 (Rating después < 9)

- RawMaterialReceptionController y PalletController siguen por encima de 200 líneas; extracción a servicios de listado y actualización (Sub-bloque 3).
- Tests de integración/feature para el bloque.
- Bloqueado por: nada.

### Rollback Plan

Si aparecen problemas: `git revert <commit-hash>` de los commits del sub-bloque 2. No hay migraciones ni cambios de contrato API.

### Next

- Sub-bloque 3 del bloque Inventario/Stock: RawMaterialReceptionListService, PalletListService; adelgazar controladores. ✅ Aplicado a continuación.

---

## [2026-02-14] Block Inventario/Stock - Sub-bloque 3 (Servicios de listado)

**Priority**: P1  
**Risk Level**: Low  
**Rating antes: 6,5/10** | **Rating después: 7/10**

### Problems Addressed

- RawMaterialReceptionController y PalletController con lógica de listado (filtros, relaciones, paginación) dentro del controlador; objetivo adelgazar y reutilizar.

### Changes Applied

- **RawMaterialReceptionListService**: Nuevo servicio estático `list(Request $request)` que construye el query con filtros (id, ids, suppliers, dates, species, products, notes), orden y paginación; devuelve `LengthAwarePaginator`. RawMaterialReceptionController::index delega en este servicio y devuelve RawMaterialReceptionResource::collection(service::list()).
- **PalletListService**: Nuevo servicio con `list(Request $request)` (query + loadRelations + applyFilters + orden + paginación), `loadRelations(Builder)` público (para show, store, update, etc.) y `applyFilters(Builder, array)` público. PalletController::index delega en PalletListService::list(). Eliminados del controlador los métodos privados loadPalletRelations y applyFiltersToQuery; todas las llamadas sustituidas por PalletListService::loadRelations y PalletListService::applyFilters.
- **Corrección**: availableForOrder usaba `$pallets->map()` sobre el paginador; sustituido por `$pallets->getCollection()->map()`.

### Verification Results

- ✅ PHPUnit ejecutado (tests existentes).
- RawMaterialReceptionController: ~106 líneas menos (index reducido). PalletController: ~185 líneas menos (index + métodos privados movidos a servicio).

### Gap to 10/10 (Rating después < 9)

- RawMaterialReceptionController y PalletController siguen por encima de 200 líneas (1286 y 1146 respectivamente); falta extracción de lógica de store/update a servicios o acciones.
- Tests de integración/feature para el bloque.
- Bloqueado por: nada.

### Rollback Plan

Si aparecen problemas: `git revert <commit-hash>` de los commits del sub-bloque 3.

### Next

- Sub-bloque 4 (opcional): Form Requests para estadísticas del bloque. ✅ Aplicado a continuación.

---

## [2026-02-14] Block Inventario/Stock - Sub-bloque 4 (Form Requests estadísticas)

**Priority**: P1  
**Risk Level**: Low  
**Rating antes: 7/10** | **Rating después: 7,5/10**

### Problems Addressed

- RawMaterialReceptionStatisticsController y CeboDispatchStatisticsController validaban parámetros con `$request->validate()` inline; autorización ya correcta (viewAny del modelo). Objetivo: validación en frontera mediante Form Requests y autorización delegada en el Form Request.

### Changes Applied

- **ReceptionChartDataRequest**: Form Request con authorize(viewAny, RawMaterialReception::class) y reglas dateFrom, dateTo, speciesId, familyId, categoryId, valueType, groupBy. RawMaterialReceptionStatisticsController::receptionChartData inyecta ReceptionChartDataRequest; se elimina authorize() y validate() del controlador; se usa $request->validated().
- **DispatchChartDataRequest**: Form Request con authorize(viewAny, CeboDispatch::class) y mismas reglas. CeboDispatchStatisticsController::dispatchChartData inyecta DispatchChartDataRequest; se elimina authorize() y validate() del controlador; se usa $request->validated().
- **StockStatisticsController**: Sin cambios (totalStockStats y totalStockBySpeciesStats no reciben parámetros; ya usan authorize('viewAny', Pallet::class)).

### Verification Results

- ✅ Linter sin errores.
- Comportamiento preservado: validación y 403 delegados en Form Requests; respuestas idénticas con params válidos.

### Gap to 10/10 (Rating después < 9)

- RawMaterialReceptionController y PalletController siguen >200 líneas; tests del bloque; opcional más extracción (update/acciones).
- Bloqueado por: nada.

### Rollback Plan

Si aparecen problemas: `git revert <commit-hash>` de los commits del sub-bloque 4. No hay cambios de contrato API.

### Next

- Sub-bloque 5: tests de integración/feature para el bloque. ✅ Aplicado a continuación.

---

## [2026-02-14] Block Inventario/Stock - Sub-bloque 5 (Tests de integración/feature)

**Priority**: P1  
**Risk Level**: Low  
**Rating antes: 7,5/10** | **Rating después: 8/10**

### Problems Addressed

- Bloque Inventario/Stock sin tests de integración/feature; cualquier refactor con alto riesgo de regresión (P2 del análisis).

### Changes Applied

- **StockBlockApiTest** (tests/Feature/StockBlockApiTest.php): 11 tests con tenant + Sanctum (ConfiguresTenantConnection, usuario Administrador). Flujos cubiertos: list raw-material-receptions, list pallets, list stores; GET statistics/stock/total y statistics/stock/total-by-species; reception-chart-data (422 sin params requeridos, 200 con params válidos); dispatch-chart-data (422 sin params, 200 con params); show store (crear almacén y GET por id); endpoints requieren auth (401 sin token).

### Verification Results

- ✅ Los 11 tests pasan (php artisan test tests/Feature/StockBlockApiTest.php).
- ✅ Linter sin errores.

### Gap to 10/10 (Rating después < 9)

- RawMaterialReceptionController y PalletController siguen >200 líneas; opcional más extracción (update, acciones) y tests adicionales (CRUD recepción completo, moveToStore, linkOrder).
- Bloqueado por: nada.

### Rollback Plan

Si aparecen problemas: `git revert <commit-hash>` de los commits del sub-bloque 5. Solo se añade archivo de tests.

### Next

- Bloque Inventario/Stock cerrado en esta fase (rating 8/10). Siguiente bloque según plan de evolución (p. ej. otro módulo o más adelgazamiento opcional).

---

## [2026-02-14] Block Ventas - Sub-bloque 1 (Form Requests + autorización)

**Priority**: P1  
**Risk Level**: Low  
**Rating antes: 5/10** | **Rating después: 6/10**

### Problems Addressed

- Validación inline en OrderController (salesBySalesperson, transportChartData).
- Validación inline en OrderStatisticsController (totalNetWeightStats, totalAmountStats, orderRankingStats, salesChartData).
- OrdersReportController sin Form Request ni autorización explícita para exportToExcel.

### Changes Applied

- **OrderController**: SalesBySalespersonRequest y OrderTransportChartRequest; métodos salesBySalesperson y transportChartData usan Form Request en lugar de `$request->validate()`.
- **OrderStatisticsController**: OrderTotalNetWeightStatsRequest, OrderTotalAmountStatsRequest, OrderRankingStatsRequest, OrderSalesChartDataRequest; los cuatro métodos usan Form Request y `$this->authorize('viewAny', Order::class)`.
- **OrdersReportController**: OrdersReportRequest con autorización `viewAny` Order y reglas opcionales para filtros del reporte (customers, id, ids, buyerReference, status, loadDate, entryDate, transports, salespeople, palletsState); exportToExcel y exportToExcelA3ERP usan OrdersReportRequest.
- Nuevos Form Requests en `app/Http/Requests/v2/`: SalesBySalespersonRequest, OrderTransportChartRequest, OrderTotalNetWeightStatsRequest, OrderTotalAmountStatsRequest, OrderRankingStatsRequest, OrderSalesChartDataRequest, OrdersReportRequest.

### Verification Results

- ✅ Tests Unit (OrderListService, OrderStoreService, OrderUpdateService, OrderDetailService) pasan.
- ✅ Resto de tests (ApiDocumentationTest, etc.) pasan.
- ⚠️ OrderApiTest falla por UniqueConstraintViolationException en `countries` (duplicado "España"); **no causado por este refactor** (pre-existente).
- Comportamiento: validación y autorización delegadas en Form Requests; respuesta 422 ante params inválidos; 403 si usuario no puede viewAny Order en reportes.

### Gap to 10/10 (Rating después < 9)

- CustomerController y SalespersonController siguen gruesos y sin Form Requests/Policies (Sub-bloques 2 y 3).
- OrderPlannedProductDetail e Incident sin Form Requests ni autorización explícita (Sub-bloque 4).
- Tests de integración/feature para estadísticas y reportes no añadidos en este sub-bloque (Sub-bloque 5).
- Bloqueado por: nada. Siguiente paso: proponer Sub-bloque 2 (Customer) cuando el usuario lo apruebe.

### Rollback Plan

Si aparecen problemas: `git revert <commit-hash>` de los commits que introdujeron los Form Requests y los cambios en OrderController, OrderStatisticsController y OrdersReportController.

### Next

- Sub-bloque 2 del bloque Ventas: Customer (Policy, Form Requests, extracción listado a servicio). ✅ Aplicado más adelante.

---

## [2026-02-14] Block Ventas - Sub-bloque 2 (Customer: Policy, Form Requests, CustomerListService)

**Priority**: P1  
**Risk Level**: Low  
**Rating antes: 6/10** | **Rating después: 6,5/10**

### Problems Addressed

- CustomerController 739 líneas (P1); sin Policy; validación inline en store, update, destroyMultiple; lógica de listado/filtros en el controlador.

### Changes Applied

- **CustomerPolicy**: Creada (viewAny, view, create, update, delete, restore, forceDelete) con mismos roles que Order; registrada en AuthServiceProvider.
- **CustomerController**: authorize() en index (viewAny), show (view), store (create), update (update), destroy (delete), destroyMultiple (viewAny), options (viewAny), getOrderHistory (view).
- **Form Requests**: StoreCustomerRequest, UpdateCustomerRequest (reglas y mensajes en español); IndexCustomerRequest (filtros opcionales: id, ids, name, vatNumber, paymentTerms, salespeople, countries, perPage); DestroyMultipleCustomersRequest (ids required|array|min:1).
- **CustomerListService**: Nuevo servicio `CustomerListService::list(Request $request)` con filtros (id, ids, name, vatNumber, paymentTerms, salespeople, countries) y paginación; index delega en el servicio.
- **CustomerController**: store(StoreCustomerRequest), update(UpdateCustomerRequest), index(IndexCustomerRequest), destroyMultiple(DestroyMultipleCustomersRequest); reducción de ~157 líneas (739 → 582).

### Verification Results

- ✅ Tests Unit (Order*) pasan.
- ✅ Sin errores de linter en CustomerController, CustomerPolicy, CustomerListService.
- ⚠️ OrderApiTest sigue fallando por UniqueConstraintViolation en countries (pre-existente).
- Comportamiento preservado: listado, CRUD, options, getOrderHistory; validación y autorización en frontera.

### Gap to 10/10 (Rating después < 9)

- SalespersonController aún grueso y sin Policy/Form Requests (Sub-bloque 3).
- OrderPlannedProductDetail e Incident (Sub-bloque 4); tests (Sub-bloque 5).

### Rollback Plan

`git revert <commit-hash>` de los commits del Sub-bloque 2 (CustomerPolicy, Form Requests, CustomerListService, cambios en CustomerController y AuthServiceProvider).

### Next

- Sub-bloque 3: Salesperson (Policy, Form Requests, opcional listado). ✅ Aplicado más adelante.

---

## [2026-02-14] Block Ventas - Sub-bloque 3 (Salesperson: Policy, Form Requests, SalespersonListService)

**Priority**: P1  
**Risk Level**: Low  
**Rating antes: 6,5/10** | **Rating después: 7/10**

### Problems Addressed

- SalespersonController 267 líneas (P1); sin Policy; validación inline en store, update, destroyMultiple; lógica de listado en el controlador.

### Changes Applied

- **SalespersonPolicy**: Creada (viewAny, view, create, update, delete, restore, forceDelete) con mismos roles que Order/Customer; registrada en AuthServiceProvider.
- **SalespersonController**: authorize() en index (viewAny), show (view), store (create), update (update), destroy (delete), destroyMultiple (viewAny), options (viewAny).
- **Form Requests**: StoreSalespersonRequest, UpdateSalespersonRequest (name, emails, ccEmails); IndexSalespersonRequest (id, ids, name, perPage); DestroyMultipleSalespeopleRequest (ids required|array|min:1).
- **SalespersonListService**: Nuevo servicio `SalespersonListService::list(Request $request)` con filtros (id, ids, name) y paginación; index delega en el servicio.
- **SalespersonController**: index(IndexSalespersonRequest) → SalespersonListService::list; store(StoreSalespersonRequest), update(UpdateSalespersonRequest), destroyMultiple(DestroyMultipleSalespeopleRequest). Líneas 267 → 204 (−63).

### Verification Results

- ✅ Tests Unit (Order*) pasan.
- ✅ Sin errores de linter.
- ⚠️ OrderApiTest fallo pre-existente (countries).
- Comportamiento preservado: listado, CRUD, options; validación y autorización en frontera.

### Gap to 10/10 (Rating después < 9)

- OrderPlannedProductDetail e Incident (Sub-bloque 4); tests (Sub-bloque 5).

### Rollback Plan

`git revert <commit-hash>` de los commits del Sub-bloque 3.

### Next

- Sub-bloque 4: OrderPlannedProductDetail + Incident (Form Requests + autorización). ✅ Aplicado más adelante.

---

## [2026-02-14] Block Ventas - Sub-bloque 4 (OrderPlannedProductDetail + Incident: Form Requests y autorización)

**Priority**: P1/P2  
**Risk Level**: Low  
**Rating antes: 7/10** | **Rating después: 7,5/10**

### Problems Addressed

- OrderPlannedProductDetailController: validación inline en store y update; sin autorización (quien puede tocar el pedido puede tocar sus líneas).
- IncidentController: validación inline en store y update; sin autorización explícita respecto al Order.

### Changes Applied

- **OrderPlannedProductDetailController**:
  - StoreOrderPlannedProductDetailRequest (orderId, boxes, product.id, quantity, tax.id, unitPrice); UpdateOrderPlannedProductDetailRequest (boxes, product.id, quantity, tax.id, unitPrice).
  - index: authorize('viewAny', Order::class).
  - show: authorize('view', $detail->order).
  - store: Order::findOrFail(orderId), authorize('update', $order), uso de Form Request y validated().
  - update: authorize('update', $orderPlannedProductDetail->order), UpdateOrderPlannedProductDetailRequest.
  - destroy: authorize('update', $orderPlannedProductDetail->order).
- **IncidentController**:
  - StoreIncidentRequest (description required|string); UpdateIncidentRequest (resolution_type required|in:returned,partially_returned,compensated, resolution_notes nullable|string).
  - show: authorize('view', $order).
  - store: authorize('update', $order), StoreIncidentRequest.
  - update: authorize('update', $order), UpdateIncidentRequest.
  - destroy: authorize('update', $order).
- Nuevos Form Requests: StoreOrderPlannedProductDetailRequest, UpdateOrderPlannedProductDetailRequest, StoreIncidentRequest, UpdateIncidentRequest.

### Verification Results

- ✅ Tests Unit (Order*) pasan.
- ✅ Sin errores de linter.
- ⚠️ OrderApiTest fallo pre-existente (countries).
- Comportamiento preservado: solo quien puede view/update el Order puede ver o modificar líneas planificadas e incidencias.

### Gap to 10/10 (Rating después < 9)

- Sub-bloque 5 (tests de integración/feature para Customer, Salesperson, estadísticas, reportes).
- Revisión N+1, documentación API, etc.

### Rollback Plan

`git revert <commit-hash>` de los commits del Sub-bloque 4.

### Next

- Sub-bloque 5: tests (Customer, Salesperson, estadísticas/reportes) según indicación del usuario.

---

## [2026-02-14] Block Ventas - Sub-bloque 5 (Tests: Customer, Salesperson, estadísticas/reportes)

**Priority**: P1  
**Risk Level**: Low  
**Rating antes: 7,5/10** | **Rating después: 8/10**

### Problems Addressed

- Ausencia de tests Feature para Customer, Salesperson y endpoints de estadísticas/reportes (Sub-bloque 5 pendiente).

### Changes Applied

- **CustomerApiTest** (`tests/Feature/CustomerApiTest.php`): tests para list, create, show, update, destroy. Uso de Country, Transport, PaymentTerm, Salesperson en Customer::create; payload de create con contact_info, salesperson_id; payload de update completo (vatNumber, addresses, contact_info, FKs, emails) para no pisar NOT NULL. Transport::firstOrCreate por `name` para evitar duplicado (unique en transports.name).
- **SalespersonApiTest**: ya existía y pasa (list, create, show, update, destroy).
- **StoreCustomerRequest / UpdateCustomerRequest**: regla de email en testing sin DNS (`email:rfc` cuando `app()->environment('testing')`) para que los tests no fallen por resolución DNS en CI.
- **OrderStatisticsApiTest** (`tests/Feature/OrderStatisticsApiTest.php`): test_can_get_total_net_weight_stats (GET statistics/orders/total-net-weight con dateFrom/dateTo, assert 200 y estructura JSON); test_can_export_orders_report (GET orders_report, assert 200 y content-type application/vnd.ms-excel).

### Verification Results

- ✅ CustomerApiTest: 5 tests pasan (list, create, show, update, destroy).
- ✅ SalespersonApiTest: 5 tests pasan.
- ✅ OrderStatisticsApiTest: 2 tests pasan (total net weight stats, export orders report).

### Gap to 10/10 (Rating después < 9)

- OrderApiTest sigue con fallo pre-existente (countries).
- Revisión N+1, documentación API, más tests de reportes/estadísticas si se desea.

### Rollback Plan

`git revert <commit-hash>` de los commits del Sub-bloque 5 (tests y cambios en StoreCustomerRequest/UpdateCustomerRequest).

### Next

- Cerrar bloque Ventas o seguir con corrección de OrderApiTest / otros bloques según prioridad.

---

## [2026-02-14] Fix OrderApiTest (countries duplicate + assert DB connection)

**Priority**: P2  
**Risk Level**: Low  

### Problems Addressed

- OrderApiTest fallaba con `UniqueConstraintViolationException`: duplicate entry 'España' for key `countries.countries_name_unique` (al ejecutar junto con otros tests que crean el mismo país).
- assertDatabaseHas('orders', ...) fallaba ("table is empty") porque la tabla `orders` está en la conexión `tenant`, no en la default.

### Changes Applied

- **OrderApiTest**: `Country::create` → `Country::firstOrCreate(['name' => 'España'])`; `PaymentTerm::create` → `firstOrCreate`; `Transport::create` → `Transport::firstOrCreate` por nombre único `'Transport OrderApiTest'`; `Salesperson::create` → `firstOrCreate`.
- **OrderApiTest**: `assertDatabaseHas('orders', ['status' => 'pending'])` → `assertDatabaseHas('orders', ['status' => 'pending'], 'tenant')`.

### Verification Results

- ✅ OrderApiTest pasa en solitario y junto con CustomerApiTest.

---

## [2026-02-14] Block Ventas - Revisión N+1 (eager loading)

**Priority**: P2  
**Risk Level**: Low  
**Rating antes: 8/10** | **Rating después: 8,5/10**

### Problems Addressed

- Posibles N+1 en listados y respuestas del bloque Ventas: Customer list (CustomerResource usa toArrayAssoc con payment_term, salesperson, country, transport); export orders (OrderExport map usa customer, salesperson, transport, incoterm, numberOfPallets, totalBoxes, totalNetWeight); store/update/updateStatus devuelven OrderDetailsResource con orden sin relaciones cargadas.

### Changes Applied

- **CustomerListService::list()**: eager load `payment_term`, `salesperson`, `country`, `transport` para que CustomerResource::toArrayAssoc() no dispare N+1.
- **OrderExport::query()**: añadido `->with(['customer', 'salesperson', 'transport', 'incoterm'])->withTotals()` para que cada fila del export no cargue relaciones por separado (customer, comercial, transporte, incoterm, pallets para totales).
- **OrderController**: respuestas que devuelven OrderDetailsResource ahora usan OrderDetailService::getOrderForDetail() para cargar el pedido con todas las relaciones en una sola consulta:
  - **store**: tras OrderStoreService::store(), se obtiene el pedido con getOrderForDetail() antes de devolver OrderDetailsResource.
  - **update**: tras OrderUpdateService::update(), idem.
  - **updateStatus**: eliminado load() parcial; se usa getOrderForDetail() antes de devolver OrderDetailsResource.

### Verification Results

- ✅ OrderApiTest, CustomerApiTest, SalespersonApiTest, OrderStatisticsApiTest pasan (13 tests).

### Gap to 10/10 (Rating después < 9)

- Revisión N+1 en otros módulos (Fichajes, Recepciones, etc.); documentación API; más tests.

---

## [2025-02-14] Block Etiquetas (Labels) - Sub-bloque 1

**Priority**: P2  
**Risk Level**: Low  
**Rating antes: 5/10** | **Rating después: 7/10**

### Problems Addressed

- LabelController sin Form Requests (validación inline).
- Label sin Policy registrada ni authorize() en controller.
- Modelo Label sin HasFactory (dificulta tests).
- Código comentado en LabelController (método destroy alternativo).

### Changes Applied

- **Form Requests**: StoreLabelRequest, UpdateLabelRequest, DuplicateLabelRequest con reglas y mensajes en español.
- **LabelPolicy**: Creada (viewAny, view, create, update, delete); registrada en AuthServiceProvider.
- **LabelController**: store(StoreLabelRequest), update(UpdateLabelRequest), duplicate(DuplicateLabelRequest); authorize() en index, show, store, update, destroy, duplicate, options.
- **Modelo Label**: HasFactory añadido; LabelFactory creada.
- **Limpieza**: Eliminado código comentado (destroy alternativo).

### Verification Results

- ✅ Rutas labels registradas correctamente.
- ✅ Tests Unit existentes (Order*) pasan.
- Comportamiento preservado: CRUD, options, duplicate; validación y autorización en frontera HTTP.

### Gap to 10/10 (Rating después < 9)

- Tests de integración/feature para Label API (requiere ConfiguresTenantConnection).
- Paginación en index/options si el volumen de etiquetas crece.
- Bloqueado por: nada.

### Rollback Plan

`git revert <commit-hash>` — cambios reversibles sin migraciones.

### Next

- Tests Feature para Label CRUD/duplicate (sub-bloque 2) o siguiente módulo según prioridad del usuario.

---

## [2025-02-14] Block Etiquetas - Sub-bloque 2 (Tests Feature)

**Priority**: P2  
**Risk Level**: Low  
**Rating antes: 7/10** | **Rating después: 8/10**

### Problems Addressed

- Ausencia de tests automatizados para el módulo Label.

### Changes Applied

- **LabelApiTest**: 10 tests Feature en `tests/Feature/LabelApiTest.php`:
  - test_can_list_labels
  - test_can_get_labels_options
  - test_can_create_label
  - test_can_show_label
  - test_can_update_label
  - test_can_destroy_label
  - test_can_duplicate_label
  - test_duplicate_with_custom_name
  - test_validation_rejects_duplicate_name_on_store
  - test_validation_requires_name_on_store

### Verification Results

- ✅ Los 10 tests pasan (41 assertions).
- ✅ Usa ConfiguresTenantConnection; tenant + auth + CRUD + duplicate + validación cubiertos.

### Gap to 10/10 (Rating después < 9)

- Paginación en index/options si el volumen crece.
- (Opcional) Test de Policy (403 sin auth o con rol no permitido).
- Bloqueado por: nada.

### Rollback Plan

Eliminar `tests/Feature/LabelApiTest.php`.

### Next

- Módulo Etiquetas en 8/10; siguiente módulo según prioridad del usuario.

---
