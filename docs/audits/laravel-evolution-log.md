# Laravel Evolution Log — PesquerApp Backend

Registro de cambios aplicados en el marco de la evolución incremental (CORE v1.0).  
Cada entrada sigue el formato definido en `docs/35-prompts/01_Laravel incremental evolution prompt.md`.

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
