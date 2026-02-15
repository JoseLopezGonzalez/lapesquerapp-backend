# Revisión del Modelo de Dominio — PesquerApp Backend

**Documento de hallazgos** | Auditoría global Laravel  
**Fecha**: 2026-02-15

---

## 1. Contexto del Dominio

Dominio: **ERP para cooperativas pesqueras y procesado de productos del mar**. Conceptos principales observados en el código:

- **Ventas y logística**: Pedidos (Order), clientes (Customer), comerciales (Salesperson), transportes (Transport), incoterms, términos de pago (PaymentTerm).
- **Inventario y trazabilidad**: Productos (Product), familias y categorías, cajas (Box), palets (Pallet), almacenes (Store), lotes.
- **Materia prima**: Recepciones (RawMaterialReception), despachos de cebo (CeboDispatch), especies (Species), artes de pesca (FishingGear), zonas de captura (CaptureZone), calibres, zonas FAO.
- **Producción**: Production, ProductionRecord, ProductionInput, ProductionOutput, ProductionOutputConsumption, ProductionCost, CostCatalog, Process.
- **Personas y organización**: Usuarios (User), empleados (Employee), fichajes (PunchEvent).
- **Otros**: Proveedores (Supplier), liquidaciones, incidentes (Incident), etiquetas (Label), **configuración (Setting model)**.

---

## 2. Ubicación de la Lógica de Dominio

### En modelos (Eloquent)

- **Constantes y estados**: Por ejemplo `Order::STATUS_PENDING`, `STATUS_FINISHED`, `STATUS_INCIDENT`; `Setting::SENSITIVE_KEY_PASSWORD`.
- **Relaciones**: Definidas de forma clara (belongsTo, hasMany, etc.).
- **Accessors y atributos derivados**: Por ejemplo `Order::getFormattedIdAttribute`, `getSummaryAttribute`; lógica de presentación.
- **Scopes**: Por ejemplo `Order::withTotals()` para totales en listados.
- **Setting model**: `Setting::getAllKeyValue()`; uso de Eloquent para acceso a configuración. **Mejora respecto a auditoría anterior** (2026-02-14): ya existe modelo Setting.

### En controladores

- **Orquestación**: Los controladores han sido refactorizados y delegan en servicios para listados, estadísticas, escritura y documentos.
- **OrderController**: Delega en OrderListService, OrderDetailService, OrderStoreService, OrderUpdateService.
- **PunchController**: Delega en PunchDashboardService, PunchCalendarService, PunchStatisticsService, PunchEventListService, PunchEventWriteService.
- **SettingController**: Delega en SettingService.
- **Filtros y agregaciones**: Concentrados en servicios (OrderListService, PunchEventListService, etc.) en lugar de controladores. **Mejora respecto a auditoría anterior**.

### En servicios

- **Producción**: ProductionRecordService, ProductionOutputService, ProductionInputService, ProductionOutputConsumptionService, ProductionService.
- **Estadísticas**: OrderStatisticsService, StockStatisticsService, CeboDispatchStatisticsService, RawMaterialReceptionStatisticsService.
- **Listados**: OrderListService, PunchEventListService, CustomerListService, ProductListService, etc.
- **Documentos y correo**: OrderPDFService, OrderMailerService, TenantMailConfigService.
- **Configuración**: SettingService encapsula lectura/escritura de settings con ofuscación de datos sensibles.

La distribución es **adecuada**: la lógica compleja y transaccional está en servicios; los controladores actúan como orquestadores.

---

## 3. Claridad de Conceptos del Sector

- Los nombres de entidades (Order, RawMaterialReception, CeboDispatch, Production, Box, Pallet, Species, FishingGear, CaptureZone, Setting) son comprensibles y alineados con el dominio pesquero/industrial.
- Enums como `Role` y constantes como `Order::STATUS_*` aportan claridad.
- No hay lenguaje ubicuo formal (DDD); en la práctica, los modelos Eloquent y los servicios actúan como agregados y casos de uso.

**Conclusión**: El modelo de dominio es **claro** para mantener y extender. La refactorización de controladores mejora la separación de responsabilidades.

---

## 4. Riesgos en la Distribución de Responsabilidades

- **Controladores gruesos**: **Mitigado**. OrderController y PunchController han sido refactorizados; delegan en servicios.
- **Duplicación de criterios**: Centralizar criterios de filtrado en modelos (scopes) o servicios; revisar si hay repetición.
- **Accessors costosos**: Revisar que accessors que cargan relaciones no generen N+1 en listados; usar eager loading.

---

## 5. Resumen

| Dimensión | Valoración | Comentario |
|-----------|------------|------------|
| Claridad de entidades y relaciones | Alta | Nombres y relaciones coherentes con el sector. |
| Ubicación de reglas de negocio | Alta | Bien en servicios; controladores delegan. |
| Consistencia de estados y transiciones | Media | Constantes y enums ayudan; no hay máquina de estados explícita en todos los flujos. |
| Mantenibilidad del dominio | Alta | Refactorización de controladores; modelo Setting; servicios bien delimitados. |

**Conclusión**: El modelo de dominio está en buen estado. **Mejora respecto a auditoría anterior** en distribución de lógica y claridad de responsabilidades.
