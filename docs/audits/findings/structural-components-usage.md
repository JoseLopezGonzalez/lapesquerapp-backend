# Uso de componentes estructurales Laravel — PesquerApp Backend

**Documento de hallazgos** | Auditoría global Laravel  
**Fecha**: 2026-02-15

---

## 1. Objetivo

Evaluar la presencia, corrección y consistencia de los bloques estructurales que Laravel y la comunidad recomiendan: Services, Actions, Jobs, Events/Listeners, Form Requests, Policies/Gates, Middleware, API Resources y otros.

---

## 2. Services (capa de aplicación)

| Aspecto | Valoración | Detalle |
|---------|------------|---------|
| Presencia | ✅ | 46 servicios en `app/Services/`, `app/Services/v2/`, `app/Services/Production/`. |
| Responsabilidad | ✅ | Producción, estadísticas (OrderStatisticsService, StockStatisticsService, etc.), listados (OrderListService, PunchEventListService, CustomerListService, etc.), SettingService, OrderPDFService, OrderMailerService. |
| Reutilización | ✅ | Servicios inyectados en controladores; transacciones y lógica compleja centralizadas. |
| Controladores refactorizados | ✅ | OrderController delega en OrderListService, OrderDetailService, OrderStoreService, OrderUpdateService; PunchController en PunchDashboardService, PunchCalendarService, PunchStatisticsService, PunchEventListService, PunchEventWriteService. |

**Conclusión**: Uso correcto y coherente. La lógica de aplicación está bien separada de controladores en dominios complejos. **Mejora respecto a auditoría anterior** (2026-02-14): controladores gruesos han sido refactorizados.

---

## 3. Actions (invocables de un solo propósito)

| Aspecto | Valoración | Detalle |
|---------|------------|---------|
| Presencia | ❌ | No existe carpeta `app/Actions` ni clases invocables de un solo propósito. |
| Necesidad | Opcional | La lógica está en servicios o en controladores; no es obligatorio introducir Actions. |

---

## 4. Jobs (colas)

| Aspecto | Valoración | Detalle |
|---------|------------|---------|
| Presencia | ❌ | No hay clases Job en el proyecto. |
| Riesgo futuro | ⚠️ | Si se añaden colas, el **contexto tenant** no se propaga por defecto. Debe definirse convención (payload con tenant_subdomain o tenant_database) y configurar la conexión tenant en el worker. |

---

## 5. Events y Listeners

| Aspecto | Valoración | Detalle |
|---------|------------|---------|
| Presencia | Solo por defecto | EventServiceProvider registra únicamente `Registered` → `SendEmailVerificationNotification`. |
| Eventos de dominio | ❌ | No hay eventos propios (por ejemplo OrderShipped, ProductionFinished). |
| Decoupling | N/A | Los efectos secundarios (correo, PDF) se invocan desde controladores o servicios de forma síncrona. Adecuado para el volumen actual. |

---

## 6. Form Requests

| Aspecto | Valoración | Detalle |
|---------|------------|---------|
| Presencia | ✅ | **137 clases** en `app/Http/Requests/v2/`. |
| Cobertura | ✅ | Prácticamente toda la API v2: Orders, Customers, Products, Punch, RawMaterialReception, CeboDispatch, Production, Settings, Suppliers, Transport, Catalogos, etc. |
| Consistencia | ✅ | Validación en frontera HTTP coherente; mensajes en español donde aplica. |

**Conclusión**: **Mejora significativa respecto a auditoría anterior** (2026-02-14 indicaba "solo Production, 14 clases"). Cobertura amplia y consistente.

---

## 7. Policies y Gates

| Aspecto | Valoración | Detalle |
|---------|------------|---------|
| Registro | ✅ | AuthServiceProvider registra ~30 políticas: Order, User, Customer, Salesperson, Label, RawMaterialReception, Pallet, Box, CeboDispatch, Store, Product, ProductCategory, ProductFamily, ActivityLog, Session, Setting, Production, ProductionRecord, ProductionInput, ProductionOutput, ProductionOutputConsumption, ProductionCost, CostCatalog, Process, PunchEvent, Employee, Supplier, Transport, Incoterm, PaymentTerm, Country, Tax, FishingGear. |
| Uso en controladores | ✅ | **La mayoría de controladores** llaman a `authorize()`: OrderController, CustomerController, PalletController, PunchController, SupplierController, EmployeeController, ProductionController, ProductionRecordController, ProductionInputController, ProductionOutputController, ProductionOutputConsumptionController, CostCatalogController, ProcessController, SettingController, FishingGearController, TaxController, CountryController, PaymentTermController, IncotermController, TransportController, ExcelController, PDFController, OrderDocumentController, SupplierLiquidationController, OrderPlannedProductDetailController, CeboDispatchStatisticsController, RawMaterialReceptionStatisticsController. |
| Consistencia | ✅ | Policies aplicadas en recursos críticos; autorización por recurso además del middleware de rol. |

**Conclusión**: **Mejora significativa respecto a auditoría anterior** (2026-02-14 indicaba "solo UserController usa authorize()"). Uso amplio y coherente.

---

## 8. Middleware

| Aspecto | Valoración | Detalle |
|---------|------------|---------|
| Presencia | ✅ | `tenant`, `auth:sanctum`, `role:...`, `throttle`, `LogActivity`. |
| Coherencia | ✅ | Rutas v2 bajo `tenant`; rutas protegidas con `auth:sanctum` y rol; throttling en login y magic link/OTP. |

---

## 9. API Resources

| Aspecto | Valoración | Detalle |
|---------|------------|---------|
| Presencia | ✅ | Múltiples recursos en `app/Http/Resources/v2/` (Order, OrderDetails, Product, PunchEvent, Pallet, etc.). |
| Uso | ✅ | Controladores devuelven recursos para serialización; respuestas JSON homogéneas. |

---

## 10. Otros (DTOs, Observers, Repositories)

- **DTOs**: No utilizados. No crítico para el tamaño actual.
- **Observers**: No registrados. Si se necesitan efectos secundarios al crear/actualizar/eliminar modelos, los Observers serían la opción Laravel idiomática.
- **Repositories**: No hay capa de repositorios; acceso vía Eloquent y modelos. **Mejora**: Se ha eliminado el acceso directo a `DB::connection('tenant')->table()` de los controladores; queda solo en el helper `tenantSetting()` para lectura de settings.

---

## 11. Resumen

| Componente | Presencia | Consistencia | Acción sugerida |
|------------|-----------|--------------|-----------------|
| Services | ✅ | ✅ | Mantener; ampliar si aparecen nuevos dominios complejos. |
| Actions | ❌ | — | Opcional; valorar para operaciones muy acotadas. |
| Jobs | ❌ | — | Al añadir colas: convención de contexto tenant. |
| Events/Listeners | Solo default | — | Valorar si se quiere desacoplar efectos secundarios. |
| Form Requests | ✅ | ✅ | Mantener cobertura; no hay acciones pendientes. |
| Policies | ✅ | ✅ | Mantener; verificar que nuevos recursos incluyan authorize(). |
| Middleware | ✅ | ✅ | Sin cambios. |
| API Resources | ✅ | ✅ | Sin cambios. |

**Conclusión general**: El proyecto utiliza bien Services, Form Requests, Policies y API Resources. **Mejora sustancial respecto a la auditoría anterior** en Form Requests, Policies y refactorización de controladores.
