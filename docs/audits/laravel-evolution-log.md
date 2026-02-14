# Laravel Evolution Log — PesquerApp Backend

Registro de cambios aplicados en el marco de la evolución incremental (CORE v1.0).  
Cada entrada sigue el formato definido en `docs/35-prompts/01_Laravel incremental evolution prompt.md`.

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
