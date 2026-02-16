# Análisis de rendimiento por endpoint

Este documento detalla el **rendimiento en tiempo y recursos** de los endpoints de la **API v2**, basado en el análisis del código actual de controladores, servicios y rutas (`routes/api.php`).

**Nota:** En la versión actual del proyecto **solo están registradas rutas v2**; no existen rutas v1 en `api.php`.

---

## Resumen ejecutivo

| Categoría | Descripción | Endpoints afectados |
|-----------|-------------|---------------------|
| **Crítico** | Alto uso de CPU/memoria, I/O pesado. Pueden superar 5–30 s o 512 MB. | PDFs (incl. masivo filtrado), Excel, `stores/total-stock-by-products`, `orders?active=`, `statistics/orders/*` (algunos) |
| **Alto** | Consultas con muchas relaciones, sin paginación en algunos filtros, o límites aumentados. | Listados con filtros complejos (orders, pallets), `orders/pdf/order-sheets-filtered`, liquidación proveedores (getSuppliers/getDetails), estadísticas |
| **Medio** | Paginación correcta, eager loading presente, consultas acotadas. | CRUD estándar v2 con paginate, options, show con `with()` |
| **Bajo** | Consultas simples, poco volumen de datos. | Auth (login con throttle), tenant, options de catálogos, settings, fichajes (punches) listado |

**Recomendación general:** Limitar rangos de fechas en estadísticas y exportaciones, usar paginación en listados, y considerar colas para generación de PDF/Excel pesados.

---

## Convenciones del documento

- **Tiempo:** estimado en condiciones normales (BD con índices, volumen medio).
- **Memoria:** pico de PHP durante la petición.
- **BD:** número aproximado de consultas o tipo de consulta (joins, subconsultas, `get()` sin límite).
- **Riesgo N+1:** si se cargan relaciones en bucle sin `with()`.
- **I/O:** disco (generación PDF/Excel) o red (APIs externas).

---

## API v2

Prefijo base: `/api/v2`. Middleware: `tenant`, y `auth:sanctum` en rutas protegidas.

---

### Autenticación y sistema

| Método | Ruta | Tiempo | Memoria | BD | Notas |
|--------|------|--------|---------|-----|-------|
| `POST` | `login` | Bajo | Bajo | 1–2 | Validación + búsqueda usuario + token. **Throttle:** 5 intentos por minuto (`throttle:5,1`). |
| `POST` | `logout` | Bajo | Bajo | 1–2 | Revocación token. |
| `GET` | `me` | Bajo | Bajo | 1 | Usuario actual + relaciones mínimas. |
| `GET` | `public/tenant/{subdomain}` | Bajo | Bajo | 1 | Lectura tenant por subdominio. |
| `POST` | `punches` (público) | Bajo | Bajo | 1–2 | Fichaje desde dispositivo NFC; no requiere auth. Creación de PunchEvent. |

---

### Opciones (dropdowns)

Todos los endpoints `*/options` y `customers/op` comparten un patrón similar:

- **Tiempo:** bajo.
- **Memoria:** baja.
- **BD:** una consulta `select id, name` (o equivalente) con `get()` sin paginación.

En catálogos pequeños (clientes, especies, transportes, etc.) el volumen es bajo. En entidades con muchos registros (por ejemplo `products/options`) el tamaño de la respuesta puede crecer; considerar paginación o búsqueda si se superan unos miles de registros.

Endpoints incluidos (entre otros):  
`customers/op`, `customers/options`, `salespeople/options`, `employees/options`, `transports/options`, `incoterms/options`, `suppliers/options`, `species/options`, `products/options`, `product-categories/options`, `product-families/options`, `taxes/options`, `capture-zones/options`, `processes/options`, `stores/options`, `orders/options`, `active-orders/options`, `fishing-gears/options`, `countries/options`, `payment-terms/options`, `labels/options`, `roles/options`, `users/options`, `production-records/options`.

**Nota:** No existen en la API actual `pallets/options`, `pallets/stored-options` ni `pallets/shipped-options`; sí existen `pallets/registered`, `pallets/search-by-lot`, `pallets/available-for-order` (consultas específicas, no listas para dropdown).

---

### Pedidos (Orders)

| Método | Ruta | Tiempo | Memoria | BD | Notas |
|--------|------|--------|---------|-----|-------|
| `GET` | `orders` | Medio–Alto | Medio | Variable | Paginación con `perPage` (default 10). Usa `Order::withTotals()->with([...])`. Muchos filtros opcionales: `whereHas` sobre `pallets`, `pallets.palletBoxes.box`, `pallets.palletBoxes.box.product`. Con filtros por productos/especies la consulta es más pesada. |
| `GET` | `orders?active=true` | **Alto** | **Alto** | 1 | **Sin paginación:** `Order::withTotals()->with([...])->where(...)->orWhereDate(...)->get()`. Carga todos los pedidos activos en memoria. Riesgo con muchos pedidos. |
| `GET` | `orders?active=false` | **Alto** | **Alto** | 1 | Mismo patrón que `active=true` para pedidos finalizados. |
| `GET` | `orders/active` | Medio | Bajo | 1 | **Optimizado:** Solo `Order::select(id,status,load_date,customer_id)->with(['customer'=>id,name])`, recurso `ActiveOrderCardResource` (id, formattedId, status, customer, loadDate). Índice `(status, load_date)`. Sin paginación por negocio. Ref.: 102-Plan-Mejoras-GET-orders-active.md. |
| `GET` | `orders/{id}` | Medio | Medio | 1 | `Order::with(['pallets.boxes.box.productionInputs','pallets.boxes.box.product'])->findOrFail()`. Relaciones profundas pero un solo pedido. |
| `POST` | `orders` | Medio | Medio | 1 + N líneas | Transacción: creación order + OrderPlannedProductDetail. Luego `load()` de relaciones para el resource. |
| `PUT` | `orders/{id}` | Medio | Medio | Varias | Actualización + posible cambio de estado de palets a shipped. Recarga relaciones. |
| `DELETE` | `orders`, `orders` (destroyMultiple) | Bajo–Medio | Bajo | 1 | Eliminación por ID o por lista de IDs. |
| `GET` | `orders/sales-by-salesperson` | Medio | Bajo | 1 | Query con joins (orders, pallets, pallet_boxes, boxes, production_inputs, salespeople). Agrupación y filtros por fechas. Eficiente. |
| `GET` | `orders/transport-chart-data` | Medio | Bajo | 1 | Similar a sales-by-salesperson: joins y agrupación por transporte. |
| `GET` | `orders/active` (listado activos) | Medio | Bajo | 1 | Ver fila `orders/active` arriba (optimizado). |
| `PUT` | `orders/{order}/status` | Medio | Medio | Varias | Actualización estado + posible cambio masivo de palets. |

---

### Estadísticas de pedidos (OrderStatisticsController)

| Método | Ruta | Tiempo | Memoria | BD | Notas |
|--------|------|--------|---------|-----|-------|
| `GET` | `statistics/orders/total-net-weight` | Medio | Bajo | 2 | Servicio con joins y SUM; compara con mismo rango año anterior. |
| `GET` | `statistics/orders/total-amount` | Medio–Alto | Medio | 2 | **set_time_limit(300), memory 512M.** Joins sobre orders, order_planned_product_details, products, taxes; agregaciones. Rangos muy amplios pueden ser pesados. |
| `GET` | `statistics/orders/ranking` | Medio–Alto | Medio | 1–2 | **set_time_limit(300), memory 512M.** Agrupación por cliente/país/producto y ordenación. |
| `GET` | `orders/sales-chart-data` | Medio | Bajo | 1 | OrderStatisticsService con agregación por día/semana/mes. |

---

### Palets (Pallets)

| Método | Ruta | Tiempo | Memoria | BD | Notas |
|--------|------|--------|---------|-----|-------|
| `GET` | `pallets` | Medio–Alto | Medio | 1 por página | **Eager loading profundo:** `storedPallet`, `reception`, `boxes.box.productionInputs.productionRecord.production`, `boxes.box.product`. Muchos filtros opcionales con `whereHas` (orders, species, products, stores, weights, etc.). Paginación con `perPage` (default 10). Páginas con muchos palets y cajas pueden ser pesadas. |
| `GET` | `pallets/{id}` | Medio | Medio | 1 | Mismas relaciones que index. |
| `POST` | `pallets` | Alto | Alto | Varias | Lógica compleja: cajas, posiciones, almacén; transacciones y múltiples escrituras. |
| `PUT` | `pallets/{id}` | Medio–Alto | Medio | Varias | Actualización y posible recálculo de relaciones. |
| `POST` | `pallets/assign-to-position`, `move-to-store`, `move-multiple-to-store`, `unlink-orders` | Medio | Bajo | Varias | Operaciones acotadas por palet/almacén; move-multiple y unlink-orders actúan sobre varios IDs. |
| `GET` | `pallets/registered`, `search-by-lot`, `available-for-order` | Medio | Medio | 1 | Consultas específicas (no son options); dependen de filtros y volumen. |
| `POST` | `pallets/update-state` (bulk) | Medio–Alto | Medio | N | Actualización masiva por IDs; N proporcional al tamaño del lote. |
| `DELETE` | `pallets`, `pallets` (destroyMultiple) | Medio | Bajo | 1 | Eliminación por lista de IDs. |

---

### Almacenes (Stores)

| Método | Ruta | Tiempo | Memoria | BD | Notas |
|--------|------|--------|---------|-----|-------|
| `GET` | `stores` | Bajo–Medio | Bajo | 1 por página | Paginación (perPage 12), filtros simples. |
| `GET` | `stores/{id}` | Medio–Alto | Medio | 1 | `Store::with(['palletsV2.boxes.box.productionInputs.productionRecord.production','palletsV2.boxes.box.product','palletsV2.storedPallet'])`. Muchos palets/cajas por almacén pueden hacer la respuesta pesada. |
| `GET` | `stores/total-stock-by-products` | **Crítico** | **Muy alto** | 3 + procesamiento en PHP | **Sin paginación.** Carga **todos** los StoredPallet con `pallet.boxes.box.productionInputs/product`, **todos** los Pallet registrados con relaciones, y **todos** los Product. Luego doble bucle anidado (productos × palets × cajas) en PHP. Riesgo alto de tiempo y memoria con muchos palets/cajas/productos. **Prioritario reescribir con agregación en BD.** |

---

### Estadísticas de stock (StockStatisticsController)

| Método | Ruta | Tiempo | Memoria | BD | Notas |
|--------|------|--------|---------|-----|-------|
| `GET` | `statistics/stock/total` | Medio | Bajo | 4–5 | StockStatisticsService: varias consultas con joins (pallets, boxes, production_inputs, products, stored_pallets). SUM y COUNT. |
| `GET` | `statistics/stock/total-by-species` | Medio | Bajo | 2 | Agregación por especie con join; Species::whereIn para nombres. |

---

### Recepciones de materia prima (RawMaterialReception)

| Método | Ruta | Tiempo | Memoria | BD | Notas |
|--------|------|--------|---------|-----|-------|
| `GET` | `raw-material-receptions` | Medio | Medio | 1 por página | Eager: `supplier`, `products.product`, `pallets.reception`, `pallets.boxes.box.productionInputs`. Paginación. |
| `GET` | `raw-material-receptions/{id}` | Medio | Medio | 1 | Mismas relaciones. |
| `GET` | `raw-material-receptions/reception-chart-data` | Medio | Bajo | 1 | RawMaterialReceptionStatisticsService; agregación por fechas. |
| `POST` | `raw-material-receptions/validate-bulk-update-declared-data`, `bulk-update-declared-data` | Medio | Medio | Varias | Validación y actualización masiva de datos declarados; volumen según IDs. |
| `GET` | `raw-material-receptions/facilcom-xls`, `a3erp-xls` | **Alto** | **Alto** | 1 + export | Export Excel; depende del volumen de recepciones. |
| Resto CRUD / destroyMultiple | - | Bajo–Medio | Bajo | Varias | Transacciones y eliminaciones. |

---

### Despachos de cebo (CeboDispatches)

| Método | Ruta | Tiempo | Memoria | BD | Notas |
|--------|------|--------|---------|-----|-------|
| `GET` | `cebo-dispatches` | Medio | Medio | 1 por página | apiResource con paginación. |
| `GET` | `cebo-dispatches/dispatch-chart-data` | Medio | Bajo | 1 | CeboDispatchStatisticsService; agregación temporal. |
| `GET` | `cebo-dispatches/facilcom-xlsx`, `a3erp-xlsx`, `a3erp2-xlsx` | **Alto** | **Alto** | 1 + export | Export Excel. |

---

### Productos, cajas, clientes, transportes, etc. (CRUD genérico)

Patrón típico:

- **index:** paginación con `perPage`, filtros simples o moderados. **Tiempo:** bajo–medio. **Memoria:** baja–media.
- **show:** `findOrFail` a veces con `with()` de relaciones. **Tiempo:** bajo–medio.
- **store/update:** validación + una o varias escrituras. **Tiempo:** bajo–medio.
- **destroy / destroyMultiple:** eliminación por ID(s). **Tiempo:** bajo–medio.

Entidades: `products`, `product-categories`, `product-families`, `boxes`, `customers`, `suppliers`, `transports`, `salespeople`, `employees`, `incoterms`, `payment-terms`, `countries`, `capture-zones`, `species`, `fishing-gears`, `labels` (incl. `POST labels/{label}/duplicate`), `processes`, `order-planned-product-details`, etc.

---

### Producción (Productions, ProductionRecords, Inputs/Outputs, Costes)

| Método | Ruta | Tiempo | Memoria | BD | Notas |
|--------|------|--------|---------|-----|-------|
| `GET` | `productions` | Medio | Medio | 1 por página | ProductionService list con filtros y paginación. |
| `GET` | `productions/{id}` | Medio–Alto | Medio | Varias | ProductionService getWithReconciliation; puede incluir árbol y reconciliación. |
| `GET` | `productions/{id}/diagram`, `process-tree`, `totals`, `reconciliation`, `available-products-for-outputs` | Medio | Medio | Varias | Dependen de la complejidad del lote y procesos. |
| `GET` | `production-records` | Medio | Medio | 1 por página | Paginación. |
| `GET` | `production-records/{id}/tree`, `production-records/{id}/sources-data` | Medio–Alto | Medio | Varias | Árbol de registros; sources-data para datos de fuentes. |
| `POST` | `production-records/{id}/finish` | Alto | Medio | Varias | Lógica de cierre y posible propagación. |
| `GET` | `production-outputs/{id}/cost-breakdown` | Medio | Bajo | Varias | Desglose de costes de una salida. |
| `POST` | `production-inputs/multiple`, `production-outputs/multiple`, etc. | Medio | Medio | Varias | Inserción múltiple en transacción. |
| CRUD | `cost-catalog`, `production-costs` | Bajo–Medio | Bajo | 1 por página / 1 | Catálogo de costes y costes de producción; apiResource estándar. |

---

### Generación de PDF (v2)

El controlador usa el trait **HandlesChromiumConfig** para configurar Chromium (Snappdf) de forma centralizada.

**Endpoints por pedido** `GET orders/{orderId}/pdf/*`:

- **BD:** `Order::findOrFail($orderId)` (sin eager loading explícito; las vistas pueden provocar N+1 si acceden a relaciones no cargadas).
- **I/O:** Snappdf (Chromium) genera el PDF desde HTML: **uso intensivo de CPU y tiempo** (típicamente 2–10+ s por documento).
- **Memoria:** media–alta durante el renderizado.

Rutas: `order-sheet`, `order-signs`, `order-packing-list`, `loading-note`, `restricted-loading-note`, `order-cmr`, `valued-loading-note`, `incident`, `order-confirmation`, `transport-pickup-request`.

**Endpoint masivo** `GET orders/pdf/order-sheets-filtered`:

- **Tiempo/memoria:** **Alto.** `ini_set('memory_limit','512M')`, `max_execution_time 300`. Aplica los mismos filtros que las exportaciones Excel, hace `$query->get()` (sin paginación) y genera **un único PDF combinado** con todas las hojas de pedido (vista `order_sheets_combined`). Si los filtros devuelven muchos pedidos, la consulta y el HTML/PDF pueden ser muy pesados.
- **Recomendación:** limitar filtros (p. ej. rango de fechas o lista de IDs) o mover a cola.

**Recomendación general PDF:** cargar relaciones necesarias con `Order::with([...])->findOrFail()` en endpoints por pedido y considerar cola para el PDF masivo.

---

### Exportación Excel (v2)

| Método | Ruta | Tiempo | Memoria | BD | Notas |
|--------|------|--------|---------|-----|-------|
| `GET` | `orders_report` | **Alto** | **Muy alto** | 1 + stream | **memory_limit 1024M, max_execution_time 300.** Export de todos los pedidos (con filtros opcionales). Muy sensible al volumen. |
| `GET` | `orders/{orderId}/xlsx/lots-report`, `boxes-report` | Alto | Alto | 1 + export | **memory_limit 1024M.** Un pedido pero con muchas cajas/lotes puede ser pesado. |
| `GET` | `orders/{orderId}/xls/A3ERP-sales-delivery-note`, A3ERP2, Facilcom single | Alto | Alto | 1 + export | **memory_limit 1024M.** Por pedido. |
| `GET` | `orders/xls/A3ERP-sales-delivery-note-filtered`, A3ERP2, Facilcom filtered | **Muy alto** | **Muy alto** | 1 + export | **memory_limit 1024M, max_execution_time 300.** Export masivo según filtros. |
| `GET` | `orders/xlsx/active-planned-products` | Alto | Alto | 1 + export | Productos planificados de pedidos activos. |
| `GET` | `boxes/xlsx` | Alto | Alto | 1 + export | Reporte de cajas. |
| Resto exports (recepciones, despachos) | - | Alto | Alto | 1 + export | Dependen del volumen filtrado. |

---

### Document AI (Azure)

En la versión actual del proyecto **la ruta `POST document-ai/parse` no está registrada** en `routes/api.php` (el controlador Azure Document AI existe en el código pero no está enlazado en rutas). Si se vuelve a exponer, tener en cuenta: tiempo muy alto (API externa + polling), uso de red y disco; recomendable ejecutar en cola.

---

### Fichajes (Punches) y empleados (Employees)

| Método | Ruta | Tiempo | Memoria | BD | Notas |
|--------|------|--------|---------|-----|-------|
| `POST` | `punches` (público) | Bajo | Bajo | 1–2 | Ver Autenticación; creación de fichaje (NFC o manual). |
| `GET` | `punches` | Medio | Bajo | 1 por página | Paginación (perPage 15), filtros (empleado, fechas, event_type, device_id). Eager `employee`. |
| `GET` | `punches/dashboard` | Medio | Medio | Varias | Datos para dashboard de fichajes; depende de la implementación. |
| `GET` | `punches/statistics` | Medio | Bajo | 1–2 | Agregaciones por período/empleado. |
| `GET` | `punches/calendar` | Medio | Medio | 1 | Eventos para vista calendario. |
| `POST` | `punches/bulk/validate`, `punches/bulk` | Medio | Medio | Varias | Validación e inserción masiva de fichajes manuales. |
| `DELETE` | `punches`, `punches` (destroyMultiple) | Bajo–Medio | Bajo | 1 | Eliminación por ID(s). |
| CRUD | `employees` | Bajo–Medio | Bajo | 1 por página / 1 | Paginación, filtros (name, nfc_uid). Opción `with_last_punch` para cargar último fichaje. |
| `GET` | `employees/options` | Bajo | Bajo | 1 | Lista id, name, nfcUid con `get()`; filtro opcional por nombre. |

---

### Liquidación de proveedores (Supplier Liquidations)

| Método | Ruta | Tiempo | Memoria | BD | Notas |
|--------|------|--------|---------|-----|-------|
| `GET` | `supplier-liquidations/suppliers` | **Alto** | Medio | 2 + N×2 | Proveedores con recepciones o despachos en rango de fechas. Para cada proveedor se hace `RawMaterialReception::where(...)->with('products')->get()` y `CeboDispatch::where(...)->with('products')->get()`; **riesgo N+1** y carga en memoria si hay muchos proveedores. Acotar rango de fechas. |
| `GET` | `supplier-liquidations/{supplierId}/details` | Medio–Alto | Medio | 2 | Recepciones y despachos del proveedor en rango, con `with(['products.product','supplier'])`; luego procesamiento en PHP (relación recepción–despachos, totales). |
| `GET` | `supplier-liquidations/{supplierId}/pdf` | **Alto** | Alto | Varias + Chromium | Generación de PDF de liquidación (Snappdf/HandlesChromiumConfig). Similar a otros PDFs; tiempo y CPU altos. |

---

### Sesiones, usuarios, roles, activity-logs

| Método | Ruta | Tiempo | Memoria | BD | Notas |
|--------|------|--------|---------|-----|-------|
| `GET` | `sessions` | Bajo–Medio | Bajo | 1 | Listado de sesiones; paginación recomendable si existe. |
| `GET` | `activity-logs` | Medio | Medio | 1 por página | Filtros por usuario, IP, fechas, path. Paginación. |
| `GET` | `users`, `roles`, `employees` | Bajo–Medio | Bajo | 1 por página | CRUD estándar; employees con paginación y filtros. |

---

### Configuración y envío de documentos

| Método | Ruta | Tiempo | Memoria | BD | Notas |
|--------|------|--------|---------|-----|-------|
| `GET` / `PUT` | `settings` | Bajo | Bajo | 1–2 | Lectura/actualización de configuración. |
| `POST` | `orders/{orderId}/send-custom-documents`, `send-standard-documents` | Alto | Medio | Varias | Generación de PDFs y envío por email; tiempo similar a los endpoints de PDF. |

---

## API v1

En la versión actual del backend **solo están registradas rutas v2** en `routes/api.php`. No hay rutas v1 (auth, CRUD, reportes, PDFs, etc.) expuestas; si en el futuro se volvieran a registrar, aplicar el mismo criterio de rendimiento que en v2 (paginación, límites de memoria/tiempo, agregación en BD).

---

## Resumen de endpoints críticos o de alto impacto

1. **GET** `v2/stores/total-stock-by-products` — Carga masiva (todos StoredPallet, Pallet registrados, Product) + bucles en PHP. Reescribir con agregación en BD.
2. **GET** `v2/orders?active=true|false` — Sin paginación; devuelven todos los pedidos activos o finalizados. **GET** `v2/orders/active` — Optimizado (payload ligero, índice, sin pallets); ver 102-Plan-Mejoras-GET-orders-active.md.
3. **GET** `v2/orders/pdf/order-sheets-filtered` — PDF masivo con filtros; `get()` sin límite + un PDF combinado; 512M, 300 s. Limitar filtros o usar cola.
4. **GET** `v2/orders_report` y exports Excel filtrados (A3ERP, A3ERP2, Facilcom) — 1024M memoria, 300 s; muy sensibles al volumen.
5. **GET** `v2/orders/{id}/pdf/*` (todos) — Chromium/Snappdf; alto tiempo de respuesta por documento.
6. **GET** `v2/supplier-liquidations/suppliers` — N+1 al cargar recepciones/despachos por proveedor; acotar rango de fechas.
7. **GET** `v2/statistics/orders/total-amount` y **ranking** — Límites elevados (300 s, 512M); acotar rangos de fechas.

---

## Recomendaciones generales

- **Paginación:** Usar siempre en listados (orders, pallets, activity-logs, etc.) y evitar `get()` sin límite cuando el conjunto pueda crecer.
- **Rangos de fechas:** En estadísticas y exportaciones, limitar `dateFrom`/`dateTo` (p. ej. máximo 1 año) y documentarlo.
- **Stores total-stock-by-products:** Sustituir la carga de todos los palets/productos y bucles en PHP por una consulta agregada (GROUP BY product, SUM(net_weight)).
- **PDF/Excel:** Para informes pesados (order-sheets-filtered, orders_report, exports filtrados) considerar generación en cola y descarga asíncrona (estado + URL cuando esté listo).
- **Eager loading:** Revisar vistas de PDF y recursos que serializan relaciones (orders, stores, pallets) para cargar con `with()` y evitar N+1.
- **Índices:** Asegurar índices en columnas de filtro y fechas (load_date, entry_date, created_at) y en FKs usadas en joins (order_id, pallet_id, box_id, etc.).

---

## Referencias

- [97-Rutas-Completas.md](./97-Rutas-Completas.md) — Listado de rutas v2.
- [24-Pedidos-Estadisticas.md](../pedidos/24-Pedidos-Estadisticas.md) — Parámetros de estadísticas de pedidos.
- [33-Estadisticas-Stock.md](../inventario/33-Estadisticas-Stock.md) — Estadísticas de stock.
- [90-Generacion-PDF.md](../utilidades/90-Generacion-PDF.md) — Generación de PDFs.
- [91-Exportacion-Excel.md](../utilidades/91-Exportacion-Excel.md) — Exportación Excel.
