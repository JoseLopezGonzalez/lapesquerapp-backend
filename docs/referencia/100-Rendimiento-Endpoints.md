# Análisis de rendimiento por endpoint

Este documento detalla el **rendimiento en tiempo y recursos** de los endpoints de la API (v1 y v2), basado en el análisis del código de controladores, servicios y rutas.

---

## Resumen ejecutivo

| Categoría | Descripción | Endpoints afectados |
|-----------|-------------|---------------------|
| **Crítico** | Alto uso de CPU/memoria, I/O pesado o llamadas externas. Pueden superar 5–30 s o 512 MB. | PDFs, Excel, Document AI, `stores/total-stock-by-products`, `orders?active=`, `statistics/orders/*` (algunos) |
| **Alto** | Consultas con muchas relaciones, sin paginación en algunos filtros, o límites aumentados. | Listados con filtros complejos (orders, pallets), `orders/active`, estadísticas de stock/recepciones/despachos |
| **Medio** | Paginación correcta, eager loading presente, consultas acotadas. | CRUD estándar v2 con paginate, options, show con `with()` |
| **Bajo** | Consultas simples, poco volumen de datos. | Auth, tenant, options de catálogos, settings |

**Recomendación general:** Limitar rangos de fechas en estadísticas y exportaciones, usar paginación en listados, y considerar colas para generación de PDF/Excel y Document AI.

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
| `POST` | `login` | Bajo | Bajo | 1–2 | Validación + búsqueda usuario + token. |
| `POST` | `logout` | Bajo | Bajo | 1–2 | Revocación token. |
| `GET` | `me` | Bajo | Bajo | 1 | Usuario actual + relaciones mínimas. |
| `GET` | `public/tenant/{subdomain}` | Bajo | Bajo | 1 | Lectura tenant por subdominio. |

---

### Opciones (dropdowns)

Todos los endpoints `*/options` y `customers/op` comparten un patrón similar:

- **Tiempo:** bajo.
- **Memoria:** baja.
- **BD:** una consulta `select id, name` (o equivalente) con `get()` sin paginación.

En catálogos pequeños (clientes, especies, transportes, etc.) el volumen es bajo. En entidades con muchos registros (por ejemplo `products/options`, `pallets/options`) el tamaño de la respuesta puede crecer; considerar paginación o búsqueda si se superan unos miles de registros.

Endpoints incluidos (entre otros):  
`customers/op`, `customers/options`, `salespeople/options`, `transports/options`, `incoterms/options`, `suppliers/options`, `species/options`, `products/options`, `product-categories/options`, `product-families/options`, `taxes/options`, `capture-zones/options`, `processes/options`, `pallets/options`, `pallets/stored-options`, `pallets/shipped-options`, `stores/options`, `orders/options`, `active-orders/options`, `fishing-gears/options`, `countries/options`, `payment-terms/options`, `labels/options`, `roles/options`, `users/options`, `production-records/options`.

---

### Pedidos (Orders)

| Método | Ruta | Tiempo | Memoria | BD | Notas |
|--------|------|--------|---------|-----|-------|
| `GET` | `orders` | Medio–Alto | Medio | Variable | Paginación con `perPage` (default 10). Muchos filtros opcionales: `whereHas` sobre `pallets`, `pallets.palletBoxes.box`, `pallets.palletBoxes.box.product`. Con filtros por productos/especies la consulta es más pesada. |
| `GET` | `orders?active=true` | **Alto** | **Alto** | 1 + N | **Sin paginación:** `Order::where(...)->orWhereDate(...)->get()`. Carga todos los pedidos activos en memoria. Riesgo con muchos pedidos. |
| `GET` | `orders?active=false` | **Alto** | **Alto** | 1 + N | Mismo patrón que `active=true` para pedidos finalizados. |
| `GET` | `orders/active` | **Alto** | **Alto** | 1 | `Order::with([...])->where(...)->get()`. Eager loading correcto pero **sin paginación**; devuelve todos los pedidos activos con relaciones (customer, salesperson, transport, incoterm, pallets.boxes...). |
| `GET` | `orders/{id}` | Medio | Medio | 1 | `Order::with(['pallets.boxes.box.productionInputs','pallets.boxes.box.product'])->findOrFail()`. Relaciones profundas pero un solo pedido. |
| `POST` | `orders` | Medio | Medio | 1 + N líneas | Transacción: creación order + OrderPlannedProductDetail. Luego `load()` de relaciones para el resource. |
| `PUT` | `orders/{id}` | Medio | Medio | Varias | Actualización + posible cambio de estado de palets a shipped. Recarga relaciones. |
| `DELETE` | `orders`, `orders` (destroyMultiple) | Bajo–Medio | Bajo | 1 | Eliminación por ID o por lista de IDs. |
| `GET` | `orders/sales-by-salesperson` | Medio | Bajo | 1 | Query con joins (orders, pallets, pallet_boxes, boxes, production_inputs, salespeople). Agrupación y filtros por fechas. Eficiente. |
| `GET` | `orders/transport-chart-data` | Medio | Bajo | 1 | Similar a sales-by-salesperson: joins y agrupación por transporte. |
| `GET` | `orders/active` (listado activos) | **Alto** | **Alto** | 1 | Ver fila `orders/active` arriba. |
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
| `POST` | `pallets/assign-to-position`, `move-to-store`, etc. | Medio | Bajo | Varias | Operaciones acotadas por palet/almacén. |
| `GET` | `pallets/registered`, `search-by-lot`, `available-for-order` | Medio | Medio | 1 | Dependen de filtros y volumen; en general consultas acotadas. |
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

Entidades: `products`, `product-categories`, `product-families`, `boxes`, `customers`, `suppliers`, `transports`, `salespeople`, `incoterms`, `payment-terms`, `countries`, `capture-zones`, `species`, `fishing-gears`, `labels`, `processes`, `order-planned-product-details`, etc.

---

### Producción (Productions, ProductionRecords, Inputs/Outputs)

| Método | Ruta | Tiempo | Memoria | BD | Notas |
|--------|------|--------|---------|-----|-------|
| `GET` | `productions` | Medio | Medio | 1 por página | ProductionService list con filtros y paginación. |
| `GET` | `productions/{id}` | Medio–Alto | Medio | Varias | ProductionService getWithReconciliation; puede incluir árbol y reconciliación. |
| `GET` | `productions/{id}/diagram`, `process-tree`, `totals`, `reconciliation`, `available-products-for-outputs` | Medio | Medio | Varias | Dependen de la complejidad del lote y procesos. |
| `GET` | `production-records` | Medio | Medio | 1 por página | Paginación. |
| `GET` | `production-records/{id}/tree` | Medio–Alto | Medio | Varias | Árbol de registros. |
| `POST` | `production-records/{id}/finish` | Alto | Medio | Varias | Lógica de cierre y posible propagación. |
| `POST` | `production-inputs/multiple`, `production-outputs/multiple`, etc. | Medio | Medio | Varias | Inserción múltiple en transacción. |

---

### Generación de PDF (v2)

Todos los endpoints `GET orders/{orderId}/pdf/*` siguen el mismo patrón:

- **BD:** `Order::findOrFail($orderId)` (sin eager loading explícito en el controlador; las vistas pueden acceder a relaciones y provocar N+1 si no se cargan en la vista o en un scope).
- **I/O:** Snappdf (Chromium) genera el PDF desde HTML: **uso intensivo de CPU y tiempo** (típicamente 2–10+ segundos según complejidad del documento).
- **Memoria:** media–alta durante el renderizado.

Endpoints: `order-sheet`, `order-signs`, `order-packing-list`, `loading-note`, `restricted-loading-note`, `order-cmr`, `valued-loading-note`, `incident`, `order-confirmation`, `transport-pickup-request`.

**Recomendación:** cargar relaciones necesarias con `Order::with([...])->findOrFail()` antes de pasar a la vista y considerar cola para generación asíncrona en documentos pesados.

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

| Método | Ruta | Tiempo | Memoria | I/O | Notas |
|--------|------|--------|---------|-----|-------|
| `POST` | `document-ai/parse` | **Muy alto (10–60+ s)** | Medio | Red + disco | Subida PDF (max 20 MB), guardado temporal, envío a Azure Form Recognizer, **polling cada 2 s** hasta que el análisis termine. Tiempo dominado por la API externa. **Crítico:** no ejecutar en request síncrono en producción; usar cola. |

---

### Sesiones, usuarios, roles, activity-logs

| Método | Ruta | Tiempo | Memoria | BD | Notas |
|--------|------|--------|---------|-----|-------|
| `GET` | `sessions` | Bajo–Medio | Bajo | 1 | Listado de sesiones; paginación recomendable si existe. |
| `GET` | `activity-logs` | Medio | Medio | 1 por página | Filtros por usuario, IP, fechas, path. Paginación. |
| `GET` | `users`, `roles` | Bajo–Medio | Bajo | 1 por página | CRUD estándar. |

---

### Configuración y envío de documentos

| Método | Ruta | Tiempo | Memoria | BD | Notas |
|--------|------|--------|---------|-----|-------|
| `GET` / `PUT` | `settings` | Bajo | Bajo | 1–2 | Lectura/actualización de configuración. |
| `POST` | `orders/{orderId}/send-custom-documents`, `send-standard-documents` | Alto | Medio | Varias | Generación de PDFs y envío por email; tiempo similar a los endpoints de PDF. |

---

## API v1 (obsoleta)

Resumen de endpoints v1 más relevantes desde el punto de vista de rendimiento:

- **Auth:** `v1/register`, `v1/login`, `v1/logout`, `v1/me` — bajo.
- **CRUD** (stores, pallets, customers, orders, transports, etc.): en su mayoría sin middleware auth en bloque; varios listados sin paginación o con `get()` (p. ej. recursos apiResource que no definen paginate). **Riesgo:** listados grandes en memoria.
- **v1/boxes_report,** **v1/raw_material_receptions_report,** **v1/cebo_dispatches_report/*:** export Excel; análogos a v2 en consumo de memoria y tiempo.
- **v1/send_order_documentation**, **v1/send_order_documentation_transport:** envío de documentación; pueden generar PDFs y enviar correo (I/O y tiempo altos).
- **v1/orders/{id}/delivery-note**, **restricted-delivery-note**, **order-signs**, **order_CMR**, etc.: generación PDF con Snappdf/Chromium; **tiempo y CPU altos** (varios segundos por documento).
- **v1/rawMaterialReceptions/document,** **v1/ceboDispatches/document:** PDFs de recepciones/despachos; mismo perfil que el resto de PDFs.
- **v1/process-nodes-decrease,** **process-nodes-decrease-stats,** **v1/final-nodes-profit,** **final-nodes-cost-per-kg-by-day,** **final-nodes-daily-profit:** estadísticas; dependen de la implementación (consultas agregadas vs. cargar muchos modelos).
- **v1/raw-material-receptions-monthly-stats,** **annual-stats,** **daily-by-products-stats:** estadísticas por período; pueden ser pesadas si no están basadas en agregación en BD.
- **v1/total-inventory-by-species,** **v1/total-inventory-by-products:** análogos conceptuales a v2 stores/total-stock-by-products; si están implementados con bucles en PHP sobre todos los palets/cajas, **riesgo alto** de tiempo y memoria.
- **v1/process-options,** **v1/auto-sales-customers,** **v1/auto-sales,** **v1/insert-auto-sales-customers:** catálogos y autocompletado; normalmente ligeros.

---

## Resumen de endpoints críticos o de alto impacto

1. **GET** `v2/stores/total-stock-by-products` — Carga masiva + bucles en PHP. Reescribir con agregación en BD.
2. **GET** `v2/orders?active=true|false` y **GET** `v2/orders/active` — Sin paginación; devuelven todos los pedidos activos/finalizados.
3. **GET** `v2/orders_report` y exports Excel filtrados (A3ERP, A3ERP2, Facilcom) — 1024M memoria, 300 s; muy sensibles al volumen.
4. **POST** `v2/document-ai/parse` — Llamada externa + polling; mover a cola.
5. **GET** `v2/orders/{id}/pdf/*` (todos) — Chromium/Snappdf; alto tiempo de respuesta.
6. **GET** `v2/statistics/orders/total-amount` y **ranking** — Límites elevados (300 s, 512M); acotar rangos de fechas.

---

## Recomendaciones generales

- **Paginación:** Usar siempre en listados (orders, pallets, activity-logs, etc.) y evitar `get()` sin límite cuando el conjunto pueda crecer.
- **Rangos de fechas:** En estadísticas y exportaciones, limitar `dateFrom`/`dateTo` (p. ej. máximo 1 año) y documentarlo.
- **Stores total-stock-by-products:** Sustituir la carga de todos los palets/productos y bucles en PHP por una consulta agregada (GROUP BY product, SUM(net_weight)).
- **PDF/Excel:** Para informes pesados o por lotes, considerar generación en cola y descarga asíncrona (estado + URL cuando esté listo).
- **Document AI:** Ejecutar en job en cola; devolver un identificador de tarea y permitir consultar el resultado más tarde.
- **Eager loading:** Revisar vistas de PDF y recursos que serializan relaciones (orders, stores, pallets) para cargar con `with()` y evitar N+1.
- **Índices:** Asegurar índices en columnas de filtro y fechas (load_date, entry_date, created_at) y en FKs usadas en joins (order_id, pallet_id, box_id, etc.).

---

## Referencias

- [97-Rutas-Completas.md](./97-Rutas-Completas.md) — Listado de rutas v2.
- [24-Pedidos-Estadisticas.md](../pedidos/24-Pedidos-Estadisticas.md) — Parámetros de estadísticas de pedidos.
- [33-Estadisticas-Stock.md](../inventario/33-Estadisticas-Stock.md) — Estadísticas de stock.
- [90-Generacion-PDF.md](../utilidades/90-Generacion-PDF.md) — Generación de PDFs.
- [91-Exportacion-Excel.md](../utilidades/91-Exportacion-Excel.md) — Exportación Excel.
