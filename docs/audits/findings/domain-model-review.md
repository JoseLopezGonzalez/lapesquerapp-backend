# Revisión del Modelo de Dominio — PesquerApp Backend

**Documento de hallazgos** | Auditoría global Laravel  
**Fecha**: 2026-02-14

---

## 1. Contexto del Dominio

Dominio: **ERP para cooperativas pesqueras y procesado de productos del mar**. Conceptos principales observados en el código:

- **Ventas y logística**: Pedidos (Order), clientes (Customer), comerciales (Salesperson), transportes (Transport), incoterms, términos de pago (PaymentTerm).
- **Inventario y trazabilidad**: Productos (Product), familias y categorías, cajas (Box), palets (Pallet), almacenes (Store), lotes.
- **Materia prima**: Recepciones de materia prima (RawMaterialReception), despachos de cebo (CeboDispatch), especies (Species), artes de pesca (FishingGear), zonas de captura (CaptureZone), calibres, zonas FAO.
- **Producción**: Producción (Production), registros de producción (ProductionRecord), entradas/salidas (ProductionInput, ProductionOutput), consumos (ProductionOutputConsumption), costes (ProductionCost, CostCatalog).
- **Personas y organización**: Usuarios (User), empleados (Employee), fichajes (PunchEvent).
- **Otros**: Proveedores (Supplier), liquidaciones, incidentes (Incident), etiquetas (Label), configuración (settings key-value).

---

## 2. Ubicación de la Lógica de Dominio

### En modelos (Eloquent)

- **Constantes y estados**: Por ejemplo `Order::STATUS_PENDING`, `STATUS_FINISHED`, `STATUS_INCIDENT` y `Order::getValidStatuses()`.
- **Relaciones**: Definidas de forma clara (belongsTo, hasMany, etc.) en los modelos.
- **Accessors y atributos derivados**: Por ejemplo `Order::getFormattedIdAttribute`, `getSummaryAttribute`; lógica de “cómo se presenta o se resume” el agregado.
- **Scopes**: Por ejemplo `Order::withTotals()` para totales en listados.

Esto es coherente con un modelo “rico” en presentación y reglas de estado; parte de la lógica de “qué es un pedido” y “qué puede mostrar” está en el modelo.

### En controladores

- **Filtros y construcción de consultas**: En `OrderController` (y en otros) los filtros por fechas, estado, clientes, transportes, etc. se construyen en el controlador. Esto mezcla “qué puede filtrar la API” con “cómo se construye la consulta”, y hace los controladores gruesos.
- **Agregaciones para dashboards/reportes**: En `OrderController`, `PunchController`, etc., hay consultas que agrupan o sumarizan para vistas concretas. Sería más mantenible si esta lógica viviera en servicios o en “query objects” reutilizables.

### En servicios

- **Producción**: `ProductionRecordService`, `ProductionOutputService`, `ProductionInputService`, `ProductionOutputConsumptionService`, `ProductionService` encapsulan creación, actualización y sincronización de registros, outputs, inputs y consumos con transacciones. Bien delimitado.
- **Estadísticas**: `OrderStatisticsService`, `StockStatisticsService`, `CeboDispatchStatisticsService`, `RawMaterialReceptionStatisticsService` concentran cálculos para dashboards y reportes.
- **Documentos y correo**: `OrderPDFService`, `OrderMailerService`, `TenantMailConfigService` para generación de PDFs y envío de correo según configuración del tenant.

La distribución es **razonable**: la lógica más compleja y transaccional está en servicios; el desbalance está en controladores que asumen filtrado y agregación que podrían vivir en capa de aplicación o en consultas reutilizables.

---

## 3. Claridad de Conceptos del Sector

- Los nombres de entidades (Order, RawMaterialReception, CeboDispatch, Production, Box, Pallet, Species, FishingGear, CaptureZone) son comprensibles y alineados con el dominio pesquero/industrial.
- Enums como `Role` y constantes como `Order::STATUS_*` o modos de creación de recepciones aportan claridad.
- No se observa un “lenguaje ubicuo” formal (DDD) ni agregados explícitos con fronteras de consistencia; en la práctica, los modelos Eloquent y los servicios actúan como agregados y aplicaciones de caso de uso.

**Conclusión**: El modelo de dominio es **suficientemente claro** para mantener y extender; no es necesario imponer DDD completo. Mejorar la separación de responsabilidades (sacar filtros y reportes de controladores a servicios o query objects) aumentaría la claridad sin cambiar la estructura global.

---

## 4. Riesgos en la Distribución de Responsabilidades

- **Controladores gruesos**: OrderController y PunchController concentran mucha lógica. Cualquier cambio en reglas de listado o en reportes implica tocar el controlador y aumenta el riesgo de regresiones. Recomendación: extraer “OrderList”, “OrderReport”, “PunchDashboard”, etc., a clases dedicadas.
- **Duplicación de criterios**: Si los mismos criterios de filtrado o de estado se repiten en varios endpoints, conviene centralizarlos en el modelo (scopes) o en una capa de consulta para evitar inconsistencias.
- **Accessors costosos**: Accessors que cargan relaciones o hacen cálculos pesados pueden generar N+1 o lentitud si se usan en listados. Revisar que en colecciones se use eager loading o que los recursos API no disparen cargas adicionales innecesarias.

---

## 5. Resumen

| Dimensión | Valoración | Comentario |
|-----------|------------|------------|
| Claridad de entidades y relaciones | Alta | Nombres y relaciones coherentes con el sector. |
| Ubicación de reglas de negocio | Media | Bien en servicios de producción y estadísticas; mezclada en controladores en listados/reportes. |
| Consistencia de estados y transiciones | Media | Constantes y enums ayudan; no hay máquina de estados explícita en todos los flujos. |
| Mantenibilidad del dominio | Media | Mejorable extrayendo consultas y reportes de controladores. |

El modelo de dominio es adecuado para el tamaño y tipo de proyecto; la evolución natural es refinar responsabilidades en la capa de aplicación (controladores más delgados, servicios o query objects para listados y reportes) sin reestructurar el dominio por completo.
