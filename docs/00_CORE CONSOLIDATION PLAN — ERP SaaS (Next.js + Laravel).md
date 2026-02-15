# CORE CONSOLIDATION PLAN — ERP SaaS (Next.js + Laravel)

**Objetivo:**  ** **Consolidar el Core existente del ERP para declararlo estable (v1.0), eliminando inconsistencias, deuda técnica y riesgos, garantizando:

* **Funcionalidad completa y coherente**
* **Lógica de negocio sólida**
* **Base de datos consistente**
* **Buenas prácticas reales (Next.js + Laravel)**
* **Rendimiento y caché controlados**
* **Seguridad y permisos correctos**
* **Documentación técnica y para usuario final**
* **Preparación para escalar a nuevos tenants**

**Este documento NO es para construir desde cero.**  ** **Es para AUDITAR, MEJORAR y CONSOLIDAR lo que ya existe.

---

# FASE 0 — Definir qué es el CORE v1.0

**Antes de mejorar nada, debemos definir qué bloques forman el Core comercial estable.**

**Bloques del Core, como por ejemplo:**

* **Auth + Roles/Permisos**
* **Productos (y anidados)** — Product, ProductCategory, ProductFamily, Species, CaptureZone
* **Clientes** — Customer
* **Ventas** — Orders, OrderPlannedProductDetail, Incident, Salesperson
* **Inventario / Stock** — Store, Pallet, Box
* **Recepciones de materia prima** — RawMaterialReception
* **Despachos de cebo** — CeboDispatch
* **Producción** — Production, ProductionRecord, Inputs, Outputs
* **Catálogos** — Transport, Incoterm, PaymentTerm, Country, Tax, FishingGear
* **Proveedores + Liquidaciones**
* **Etiquetas** — Label
* **Fichajes** — PunchEvent, Employee
* **Estadísticas e informes**
* **Configuración por tenant** — Settings
* **Sistema** — Users, Roles, ActivityLogs

**⚠️ Todo lo que quede fuera es “roadmap”, no core.** *(Inventario completo en ANEXO A.)*

---

# FASE 1 — Auditoría Global de Calidad de Código

## 1.1 Auditoría Next.js

**Revisar:**

* **Separación correcta entre Server Components y Client Components**
* **Fetching consistente (sin duplicidades innecesarias)**
* **Manejo homogéneo de errores**
* **Estado global controlado (evitar renders innecesarios)**
* **Componentes reutilizables limpios**
* **Eliminación de lógica de negocio en UI**

**Verificar:**

* **No hay lógica crítica en el frontend que deba estar en backend**
* **Formularios correctamente validados**
* **Estructura coherente por features**

**Resultado esperado:**

* **Código legible**
* **Estructura consistente**
* **Sin patrones improvisados**

---

## 1.2 Auditoría Laravel

**Revisar:**

* **Controllers finos**
* **Validaciones en FormRequest**
* **Lógica de negocio en Services / Actions**
* **Uso correcto de Policies**
* **Eager loading correcto (sin N+1)**
* **Transacciones en operaciones críticas**
* **Uso consistente de Resources / DTOs**

**Eliminar:**

* **Lógica duplicada**
* **Métodos gigantes**
* **Consultas repetidas**

**Resultado esperado:**

* **Backend limpio**
* **Separación clara de responsabilidades**
* **Código escalable**

---

# FASE 2 — Auditoría y Consolidación de Lógica de Negocio

**Este es el punto más importante.**

**Para cada bloque del Core:**

## 2.1 Inventario funcional real

**Ejemplo: Ventas**

**Entidades involucradas:**

* **Productos**
* **Clientes**
* **Salesperson**
* **Orders**
* **OrderLines**
* **StockMovements**

**Estados existentes:**

* **draft**
* **confirmed**
* **shipped**
* **canceled**
* **etc.**

**Documentar cómo funciona HOY.**

---

## 2.2 Verificación de reglas

**Comprobar:**

* **¿Cuándo se impacta el stock?**
* **¿Se permite stock negativo?**
* **¿Qué campos se bloquean según estado?**
* **¿Los totales son 100% consistentes?**
* **¿Las anulaciones revierten correctamente?**

**Detectar incoherencias.**

---

## 2.3 Consolidar reglas

**Definir una única verdad:**

* **Regla clara de estados**
* **Regla clara de stock**
* **Regla clara de permisos**
* **Regla clara de numeración**

**Eliminar ambigüedades.**

**Resultado:** **Lógica predecible y consistente.**

---

# FASE 3 — Base de Datos: Normalización y Consistencia

## 3.1 Revisar integridad

* **Claves foráneas correctas**
* **Índices en campos críticos**
* **Unique constraints necesarias**
* **Eliminaciones coherentes (soft delete o no)**

## 3.2 Normalización

**Verificar:**

* **No hay duplicación innecesaria**
* **Relaciones bien definidas**
* **Estructura preparada para multi-tenant**

## 3.3 Rendimiento

**Añadir:**

* **Índices en filtros habituales**
* **Índices compuestos (tenant + fecha + estado)**
* **Optimización de queries pesadas**

**Resultado:** **BD sólida y eficiente.**

---

# FASE 4 — Estrategia de Caché Profesional

## 4.1 Caché en Next.js

**Definir:**

* **Qué se cachea**
* **Qué no se cachea**
* **TTL**
* **Estrategia de invalidación**

**Evitar:**

* **Datos cacheados inconsistentes**
* **Datos sensibles compartidos**

---

## 4.2 Caché en Laravel

**Aplicar cache solo en:**

* **Catálogos**
* **Configuraciones**
* **Lecturas pesadas**

**Implementar:**

* **Invalidación por evento**
* **TTL razonable**
* **No cachear datos transaccionales críticos sin control**

**Resultado:** **Mejor rendimiento sin incoherencias.**

---

# FASE 5 — Tests y Estabilidad

**No buscamos 100% coverage.**

**Buscamos estabilidad real del Core.**

## Backend

* **Tests de estados**
* **Tests de totales**
* **Tests de permisos**
* **Tests de stock**

## Frontend

* **Checklist reproducible por bloque**
* **Flujos críticos probados manualmente**

**Resultado:** **No se rompe al añadir nuevos tenants.**

---

# FASE 6 — Seguridad y Multi-Tenant Readiness

* **Aislamiento correcto por tenant**
* **Permisos revisados**
* **Auditoría mínima activada**
* **Logs con contexto (tenantId, userId)**
* **Backups probados**

---

# FASE 7 — Documentación Técnica Interna

**Generar documentación técnica viva (idealmente web):**

**Debe incluir:**

* **Arquitectura general**
* **Flujo de datos**
* **Estados por bloque**
* **Reglas de negocio consolidadas**
* **Estrategia de caché**
* **Modelo de BD simplificado**
* **Guía de despliegue**

**Objetivo:** **Cualquier desarrollador nuevo puede entender el sistema.**

---

# FASE 8 — Documentación Web para Usuario Final

**Fundamental antes de escalar.**

**Crear documentación tipo:**

* **Manual por bloques**
* **Cómo crear un pedido**
* **Cómo gestionar stock**
* **Estados y su significado**
* **FAQ**
* **Buenas prácticas de uso**

**Formato recomendado:**

* **Web tipo documentation site (Docusaurus / Nextra / similar)**
* **Buscable**
* **Con capturas reales**
* **Actualizable por versión**

**Esto reduce soporte y aumenta profesionalidad.**

---

# FASE 9 — Declaración de CORE v1.0

**Se puede declarar Core estable cuando:**

* **Lógica coherente y documentada**
* **BD consistente**
* **Sin incoherencias graves**
* **Caché controlada**
* **Tests básicos superados**
* **Documentación publicada**
* **No hay cambios estructurales constantes**

**A partir de aquí:**

* **Nuevas funcionalidades → roadmap**
* **Cambios estructurales → versionados**
* **Foco en estabilidad y nuevos tenants**

---

# RESULTADO FINAL

**Un ERP:**

* **Sólido**
* **Predecible**
* **Escalable**
* **Profesional**
* **Documentado**
* **Listo para vender e implantar**

---

# ANEXO A — Inventario de Bloques del Proyecto PesquerApp

Inventario detallado de bloques funcionales identificados en el backend (rutas, modelos, controladores, evolution log). Para cada bloque del Core se aplican las Fases 1–9 del plan.

---

## Bloques principales (Core comercial)

### A.1 Auth + Roles/Permisos
| Tipo | Detalle |
|------|---------|
| **Entidades** | User, Role, Session, MagicLinkToken, ActivityLog |
| **Controladores** | AuthController, RoleController, UserController, SessionController, ActivityLogController |
| **Rutas clave** | login, logout, me, request-access, magic-link, otp, users, roles, sessions, activity-logs |
| **Evolution log** | Sub-bloque 1: Session tenant fix, Form Requests Auth/User, UserListService, IndexSession/ActivityLog. Sub-bloque 2: SessionPolicy, ActivityLogPolicy, UserPolicy refinada (delete), Handler AuthorizationException→403, AuthBlockApiTest 21 tests. **Rating actual: 9/10** |

### A.2 Ventas (Pedidos / Sales)
| Tipo | Detalle |
|------|---------|
| **Entidades** | Order, OrderPlannedProductDetail, Incident, Customer, Salesperson |
| **Controladores** | OrderController, OrderPlannedProductDetailController, IncidentController, OrdersReportController, OrderStatisticsController, CustomerController, SalespersonController |
| **Rutas clave** | orders, order-planned-product-details, orders/{id}/incident, customers, salespeople, orders_report, statistics/orders/* |
| **Evolution log** | Sub-bloques 1–6: Form Requests, Policies, ListServices, N+1, CustomerOrderHistoryService, IndexOrderRequest, authorize destroyMultiple, OrderApiTest 14 tests. **Rating actual: 9/10** |

### A.3 Inventario / Stock
| Tipo | Detalle |
|------|---------|
| **Entidades** | Store, Pallet, Box, PalletBox, StoredPallet, StoredBox |
| **Controladores** | StoreController, PalletController, BoxesController, StockStatisticsController |
| **Rutas clave** | stores, pallets, boxes, statistics/stock/*, assign-to-position, move-to-store, link-order |
| **Evolution log** | Sub-bloques 1–5 aplicados; **Rating actual: 8/10** |

### A.4 Recepciones de Materia Prima
| Tipo | Detalle |
|------|---------|
| **Entidades** | RawMaterialReception, RawMaterialReceptionProduct |
| **Controladores** | RawMaterialReceptionController, RawMaterialReceptionStatisticsController |
| **Rutas clave** | raw-material-receptions, reception-chart-data, facilcom-xls, a3erp-xls, bulk-update-declared-data |
| **Evolution log** | Form Requests bulk, WriteService, BulkService, controlador &lt;200 líneas; tests integración + edge cases; **Rating actual: 9/10** |

### A.5 Despachos de Cebo
| Tipo | Detalle |
|------|---------|
| **Entidades** | CeboDispatch, CeboDispatchProduct, Cebo |
| **Controladores** | CeboDispatchController, CeboDispatchStatisticsController |
| **Rutas clave** | cebo-dispatches, dispatch-chart-data, facilcom-xlsx, a3erp-xlsx |
| **Evolution log** | Tests CRUD + fix authorize; CeboDispatchListService, Policy delete (admin/tecnico), controller delgado, destroyMultiple authorize; **Rating actual: 9/10** |

### A.6 Producción
| Tipo | Detalle |
|------|---------|
| **Entidades** | Production, ProductionRecord, ProductionInput, ProductionOutput, ProductionOutputConsumption, Process, CostCatalog, ProductionCost |
| **Controladores** | ProductionController, ProductionRecordController, ProductionInputController, ProductionOutputController, ProductionOutputConsumptionController, CostCatalogController, ProductionCostController |
| **Rutas clave** | productions, production-records, production-inputs, production-outputs, production-output-consumptions, cost-catalog, production-costs, processes |
| **Nota** | Módulo más complejo; trazabilidad a nivel de caja |
| **Evolution log** | Sub-bloques 1–4: Production, ProductionRecord, Input/Output/Consumption, ProductionCost, CostCatalog, Process (Form Requests, Policies, authorize, getSourcesData en servicio). **Rating actual: 9/10**. Bloque completo. |

### A.7 Productos (y maestros anidados)
| Tipo | Detalle |
|------|---------|
| **Entidades** | Product, ProductCategory, ProductFamily, Species, CaptureZone |
| **Controladores** | ProductController, ProductCategoryController, ProductFamilyController, SpeciesController, CaptureZoneController |
| **Rutas clave** | products, product-categories, product-families, species, capture-zones |
| **Evolution log** | Sub-bloques 1–2: Form Requests, Policies, ProductListService, ProductCategoryListService, ProductFamilyListService, controladores &lt;200 líneas. Tests: ProductosBlockApiTest 24 tests. **Rating actual: 9/10** |

### A.8 Catálogos transaccionales
| Tipo | Detalle |
|------|---------|
| **Entidades** | Transport, Incoterm, PaymentTerm, Country, Tax, FishingGear |
| **Controladores** | TransportController, IncotermController, PaymentTermController, CountryController, TaxController, FishingGearController |
| **Rutas clave** | transports, incoterms, payment-terms, countries, taxes, fishing-gears |

### A.9 Proveedores + Liquidaciones
| Tipo | Detalle |
|------|---------|
| **Entidades** | Supplier |
| **Controladores** | SupplierController, SupplierLiquidationController |
| **Rutas clave** | suppliers, supplier-liquidations/* |

### A.10 Etiquetas (Labels)
| Tipo | Detalle |
|------|---------|
| **Entidades** | Label |
| **Controladores** | LabelController |
| **Rutas clave** | labels, labels/options, labels/{id}/duplicate |
| **Evolution log** | Sub-bloques 1–2 aplicados; **Rating actual: 8/10** |

### A.11 Fichajes
| Tipo | Detalle |
|------|---------|
| **Entidades** | PunchEvent, Employee |
| **Controladores** | PunchController, EmployeeController |
| **Rutas clave** | punches (store público NFC), punches/dashboard, punches/statistics, punches/calendar, punches/bulk, employees |
| **Evolution log** | Sub-bloques 1–3: PunchDashboardService, Form Requests, DB→Eloquent; PunchCalendarService, PunchStatisticsService, PunchEventListService; PunchEventWriteService, PunchEventPolicy, EmployeePolicy, authorize en todos los métodos. **Rating actual: 8/10** |

### A.12 Estadísticas e informes
| Tipo | Detalle |
|------|---------|
| **Controladores** | OrderStatisticsController, StockStatisticsController, RawMaterialReceptionStatisticsController, CeboDispatchStatisticsController, OrdersReportController |
| **Rutas clave** | statistics/orders/*, statistics/stock/*, orders_report, reception-chart-data, dispatch-chart-data |

### A.13 Configuración por tenant
| Tipo | Detalle |
|------|---------|
| **Entidades** | Setting (key-value por tenant) |
| **Controladores** | SettingController |
| **Rutas clave** | settings (GET, PUT) |
| **Evolution log** | Sub-bloque 1: Modelo Setting, SettingService, UpdateSettingsRequest, SettingPolicy (admin/tecnico), GET enmascara password, SettingsBlockApiTest 8 tests. **Rating actual: 9/10** |

### A.14 Sistema
| Tipo | Detalle |
|------|---------|
| **Entidades** | User, Role, ActivityLog |
| **Controladores** | UserController, RoleController, ActivityLogController |
| **Rutas clave** | users, roles/options, activity-logs |
| **Nota** | Cubierto por el bloque Auth (A.1); mismo evolution log y **Rating actual: 9/10**. |

---

## Bloques transversales

### A.15 Documentos (PDF / Excel)
| Tipo | Detalle |
|------|---------|
| **Controladores** | PDFController, ExcelController, OrderDocumentController |
| **Rutas clave** | orders/{id}/pdf/*, orders/xlsx/*, raw-material-receptions/*-xls, cebo-dispatches/*-xlsx, boxes/xlsx |

### A.16 Tenants (multi-tenant)
| Tipo | Detalle |
|------|---------|
| **Entidades** | Tenant |
| **Controladores** | TenantController (público) |
| **Rutas clave** | v2/public/tenant/{subdomain} |

---

## Mapeo CORE típico → PesquerApp

| Bloque CORE genérico | Bloques PesquerApp correspondientes |
|----------------------|------------------------------------|
| Auth + Roles/Permisos | A.1 Auth + A.14 Sistema |
| Productos (y anidados) | A.7 Productos |
| Clientes | A.2 Ventas (Customer) |
| Ventas | A.2 Ventas |
| Stock / Movimientos | A.3 Inventario + A.4 Recepciones + A.5 Despachos |
| Informes básicos | A.12 Estadísticas |
| Configuración por tenant | A.13 Settings |
| — | A.6 Producción, A.9 Proveedores, A.10 Etiquetas, A.11 Fichajes (específicos dominio pesquero) |

---

*Fuente: análisis de rutas, modelos, controladores, `docs/audits/laravel-evolution-log.md` y documentación en `docs/`. Última revisión: 2026-02-15.*

---

## Resumen de valoraciones actuales (Evolution log)

| Bloque | Rating actual | Notas |
|--------|----------------|--------|
| **A.1** Auth + Roles/Permisos | **9/10** | Sub-bloques 1–2: Session tenant fix, Form Requests, UserListService, SessionPolicy, ActivityLogPolicy, UserPolicy refinada, AuthBlockApiTest 21 tests |
| **A.2** Ventas | **9/10** | Sub-bloques 1–6: Form Requests, Policies, ListServices, N+1, CustomerOrderHistoryService, IndexOrderRequest, authorize destroyMultiple, OrderApiTest 14 tests. Bloque cerrado. |
| **A.3** Inventario / Stock | **8/10** | Sub-bloques 1–5: Policies, Form Requests, ListServices, tests feature (StockBlockApiTest) |
| **A.4** Recepciones de Materia Prima | **9/10** | WriteService, BulkService, 23 tests (incl. edge cases) |
| **A.5** Despachos de Cebo | **9/10** | ListService, Policy delete, controller delgado |
| **A.6** Producción | **9/10** | Sub-bloques 1–4: Production, ProductionRecord, Input/Output/Consumption, Cost/CostCatalog/Process. Form Requests, Policies, authorize. Bloque completo. |
| **A.7** Productos | **9/10** | Sub-bloques 1–2: Form Requests, Policies, ListServices, ProductosBlockApiTest 24 tests |
| **A.8** Catálogos transaccionales | — | Sin entrada en evolution log |
| **A.9** Proveedores + Liquidaciones | **9/10** | Sub-bloques 1–3: Form Requests, SupplierPolicy, SupplierListService, SupplierLiquidationService, authorize, SuppliersBlockApiTest 14 tests |
| **A.10** Etiquetas | **8/10** | Form Requests, Policy, tests Feature |
| **A.11** Fichajes | **8/10** | Sub-bloques 1–3: Dashboard/Calendar/Statistics/List/Write services, Form Requests, PunchEventPolicy, EmployeePolicy, authorize. Gap: tests Feature. |
| **A.12** Estadísticas e informes | — | Sin entrada en evolution log |
| **A.13** Configuración por tenant | **9/10** | Setting model, SettingService, Policy, Form Request, GET enmascara password, SettingsBlockApiTest 8 tests |
| **A.14** Sistema | **9/10** | Mismo bloque que A.1 Auth (users, roles, activity-logs) |
| **A.15** Documentos (PDF/Excel) | — | Sin entrada en evolution log |
| **A.16** Tenants | — | Sin entrada en evolution log |
