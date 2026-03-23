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
* **Infraestructura API** — health, CORS (endpoints de verificación)
* **Utilidades** — Extracción de texto desde PDF (cuando esté expuesto)

**⚠️ Todo lo que quede fuera es “roadmap”, no core.** *(Inventario completo en ANEXO A: bloques A.1–A.18.)*

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

# ANEXO A — Inventario Vivo de Bloques y Puntuaciones

Este anexo deja de ser una foto cerrada y pasa a ser el **registro vivo** del estado del core. A partir de ahora, cualquier ejecución del prompt de implementación debe actualizar este mismo anexo con:

- nota antes y después por bloque
- fecha de revisión
- fuente de la nota
- gaps para llegar a `9/10` o `10/10`
- estado del bloque: `pendiente`, `en progreso`, `cerrado 9/10`, `cerrado 10/10`

## Regla de sincronización obligatoria

La referencia principal de puntuaciones del proyecto es este documento:

- `docs/core-consolidation-plan-erp-saas.md`

El prompt [13-prompt-implementacion-mejoras-por-bloques.md](/home/jose/lapesquerapp-backend/docs/prompts/13-prompt-implementacion-mejoras-por-bloques.md) debe actualizar este anexo en cada iteración relevante. Si además se usa un scoreboard auxiliar, ese documento será solo una vista secundaria, nunca la fuente de verdad.

## Escala de notas

- `10/10`: bloque ejemplar, estable, consistente, bien testeado y replicable
- `9/10`: bloque profesional, con deuda residual menor no estructural
- `8/10`: bloque sólido pero con huecos relevantes en tests, permisos, rendimiento o cohesión
- `7/10` o menos: bloque funcional con deuda ya visible o riesgo material

## Convención de fuentes

- `EL`: nota heredada o sustentada por `docs/audits/laravel-evolution-log.md`
- `AR`: nota ajustada por análisis del repo actual a fecha de hoy
- `EL+AR`: nota consolidada con ambas fuentes

---

## A.1–A.18 Bloques históricos del core

### A.1 Auth + Roles/Permisos
| Campo | Detalle |
|------|---------|
| **Entidades** | User, Role, Session, MagicLinkToken, ActivityLog |
| **Controladores** | AuthController, RoleController, UserController, SessionController, ActivityLogController |
| **Rutas clave** | login, logout, me, request-access, magic-link, otp, users, roles, sessions, activity-logs |
| **Estado actual** | Cerrado 9/10 |
| **Nota actual** | **9/10** |
| **Fuente** | EL |
| **Observaciones** | Form Requests, Policies y tests consolidados. Gap residual: más cobertura unitaria y homogeneizar algunos recursos de auth. |

### A.2 Ventas (Pedidos / Sales)
| Campo | Detalle |
|------|---------|
| **Entidades** | Order, OrderPlannedProductDetail, Incident, Customer, Salesperson |
| **Controladores** | OrderController, OrderPlannedProductDetailController, IncidentController, OrdersReportController, OrderStatisticsController, CustomerController, SalespersonController |
| **Rutas clave** | orders, order-planned-product-details, incident, customers, salespeople, orders_report, statistics/orders/* |
| **Estado actual** | Cerrado 9/10 |
| **Nota actual** | **9/10** |
| **Fuente** | EL |
| **Observaciones** | Buen reparto en services, policies y tests. Gap a 10/10: más contratos formales y cobertura adicional en variantes de export y reporting. |

### A.3 Inventario / Stock
| Campo | Detalle |
|------|---------|
| **Entidades** | Store, Pallet, Box, PalletBox, StoredPallet, StoredBox |
| **Controladores** | StoreController, PalletController, BoxesController, StockStatisticsController |
| **Rutas clave** | stores, pallets, boxes, statistics/stock/*, assign-to-position, move-to-store, link-order |
| **Estado actual** | Cerrado 10/10 |
| **Nota actual** | **10/10** |
| **Fuente** | EL+AR |
| **Observaciones** | Se corrige inconsistencia previa del documento: el evolution log sitúa A.3 en `10/10` y esta es la nota vigente. Es el bloque de referencia para refactors por services y reducción de controladores. |

### A.4 Recepciones de Materia Prima
| Campo | Detalle |
|------|---------|
| **Entidades** | RawMaterialReception, RawMaterialReceptionProduct |
| **Controladores** | RawMaterialReceptionController, RawMaterialReceptionStatisticsController |
| **Rutas clave** | raw-material-receptions, reception-chart-data, facilcom-xls, a3erp-xls, bulk-update-declared-data |
| **Estado actual** | Cerrado 9/10 |
| **Nota actual** | **9/10** |
| **Fuente** | EL |
| **Observaciones** | Bulk services y edge cases cubiertos. Gap a 10/10: revisar rendimiento y contratos de export masiva. |

### A.5 Despachos de Cebo
| Campo | Detalle |
|------|---------|
| **Entidades** | CeboDispatch, CeboDispatchProduct, Cebo |
| **Controladores** | CeboDispatchController, CeboDispatchStatisticsController |
| **Rutas clave** | cebo-dispatches, dispatch-chart-data, facilcom-xlsx, a3erp-xlsx, a3erp2-xlsx |
| **Estado actual** | Cerrado 9/10 |
| **Nota actual** | **9/10** |
| **Fuente** | EL+AR |
| **Observaciones** | Bloque maduro. Gap a 10/10: más verificación de exports alternativos y stress en listados/estadísticas. |

### A.6 Producción
| Campo | Detalle |
|------|---------|
| **Entidades** | Production, ProductionRecord, ProductionInput, ProductionOutput, ProductionOutputConsumption, Process, CostCatalog, ProductionCost |
| **Controladores** | ProductionController, ProductionRecordController, ProductionInputController, ProductionOutputController, ProductionOutputConsumptionController, CostCatalogController, ProductionCostController |
| **Rutas clave** | productions, production-records, production-inputs, production-outputs, production-output-consumptions, cost-catalog, production-costs, processes |
| **Estado actual** | Cerrado 9/10 |
| **Nota actual** | **9/10** |
| **Fuente** | EL+AR |
| **Observaciones** | Sigue siendo el módulo más complejo del core. Nota 9/10 se mantiene, pero conviene monitorizar trazabilidad, costes y endpoints árbol/reconciliation ante cambios futuros. |

### A.7 Productos y maestros anidados
| Campo | Detalle |
|------|---------|
| **Entidades** | Product, ProductCategory, ProductFamily, Species, CaptureZone |
| **Controladores** | ProductController, ProductCategoryController, ProductFamilyController, SpeciesController, CaptureZoneController |
| **Rutas clave** | products, product-categories, product-families, species, capture-zones |
| **Estado actual** | Cerrado 9/10 |
| **Nota actual** | **9/10** |
| **Fuente** | EL |
| **Observaciones** | Services de listado y tests completos. Gap a 10/10: más cobertura unitaria y revisión fina de performance en opciones/filtros. |

### A.8 Catálogos transaccionales
| Campo | Detalle |
|------|---------|
| **Entidades** | Transport, Incoterm, PaymentTerm, Country, Tax, FishingGear |
| **Controladores** | TransportController, IncotermController, PaymentTermController, CountryController, TaxController, FishingGearController |
| **Rutas clave** | transports, incoterms, payment-terms, countries, taxes, fishing-gears |
| **Estado actual** | Cerrado 9/10 |
| **Nota actual** | **9/10** |
| **Fuente** | EL |
| **Observaciones** | Bloque estándar, estable y bien encapsulado. |

### A.9 Proveedores + Liquidaciones
| Campo | Detalle |
|------|---------|
| **Entidades** | Supplier |
| **Controladores** | SupplierController, SupplierLiquidationController |
| **Rutas clave** | suppliers, supplier-liquidations/* |
| **Estado actual** | Cerrado 9/10 |
| **Nota actual** | **9/10** |
| **Fuente** | EL |
| **Observaciones** | Buen equilibrio entre servicios y policies. Gap a 10/10: ampliar pruebas de escenarios de liquidación complejos. |

### A.10 Etiquetas
| Campo | Detalle |
|------|---------|
| **Entidades** | Label |
| **Controladores** | LabelController |
| **Rutas clave** | labels, labels/options, labels/{id}/duplicate |
| **Estado actual** | Cerrado 9/10 |
| **Nota actual** | **9/10** |
| **Fuente** | EL |
| **Observaciones** | Bloque simple y estable. |

### A.11 Fichajes
| Campo | Detalle |
|------|---------|
| **Entidades** | PunchEvent, Employee |
| **Controladores** | PunchController, EmployeeController |
| **Rutas clave** | punches, punches/dashboard, punches/statistics, punches/calendar, punches/bulk, employees |
| **Estado actual** | Cerrado 9/10 |
| **Nota actual** | **9/10** |
| **Fuente** | EL+AR |
| **Observaciones** | Buen avance estructural y cobertura funcional amplia. La auditoría 2026-03-23 mantiene el `9/10`, pero identifica deuda localizada en `PunchController` por tamaño y validación inline residual. |

### A.12 Estadísticas e informes
| Campo | Detalle |
|------|---------|
| **Controladores** | OrderStatisticsController, StockStatisticsController, RawMaterialReceptionStatisticsController, CeboDispatchStatisticsController, OrdersReportController |
| **Rutas clave** | statistics/orders/*, statistics/stock/*, orders_report, reception-chart-data, dispatch-chart-data |
| **Estado actual** | Cerrado 9/10 |
| **Nota actual** | **9/10** |
| **Fuente** | EL+AR |
| **Observaciones** | Correcto a nivel de authorize y cobertura base. Gap a 10/10: revisar índices, tiempos de respuesta y consistencia de export/report endpoints. |

### A.13 Configuración por tenant
| Campo | Detalle |
|------|---------|
| **Entidades** | Setting |
| **Controladores** | SettingController |
| **Rutas clave** | settings |
| **Estado actual** | Cerrado 9/10 |
| **Nota actual** | **9/10** |
| **Fuente** | EL+AR |
| **Observaciones** | Sigue siendo bloque robusto. La deuda previa de acceso ad hoc a settings baja de severidad porque `tenantSetting()` ya usa `Setting::query()`, aunque se mantiene como bloque transversal a vigilar. |

### A.14 Sistema
| Campo | Detalle |
|------|---------|
| **Entidades** | User, Role, ActivityLog |
| **Controladores** | UserController, RoleController, ActivityLogController |
| **Rutas clave** | users, roles/options, activity-logs |
| **Estado actual** | Cerrado 9/10 |
| **Nota actual** | **9/10** |
| **Fuente** | EL |
| **Observaciones** | Se mantiene alineado con A.1; no duplicar trabajo salvo que aparezcan regresiones. |

### A.15 Documentos (PDF / Excel)
| Campo | Detalle |
|------|---------|
| **Controladores** | PDFController, ExcelController, OrderDocumentController |
| **Rutas clave** | orders/{id}/pdf/*, orders/xlsx/*, raw-material-receptions/*-xls, cebo-dispatches/*-xlsx, boxes/xlsx |
| **Estado actual** | Cerrado 9/10 |
| **Nota actual** | **9/10** |
| **Fuente** | EL+AR |
| **Observaciones** | Bloque amplio y bien protegido; gap a 10/10: homogeneizar contratos de export y reforzar casos de error de generación/envío. |

### A.16 Tenants públicos y resolución tenant
| Campo | Detalle |
|------|---------|
| **Entidades** | Tenant |
| **Controladores** | TenantController público |
| **Rutas clave** | v2/public/tenant/{subdomain} |
| **Estado actual** | Cerrado 9/10 |
| **Nota actual** | **9/10** |
| **Fuente** | EL+AR |
| **Observaciones** | Bloque pequeño y sólido. Ya no representa todo el ámbito tenant/SaaS; la gestión avanzada y los jobs asociados pasan a A.22 Superadmin SaaS. |

### A.17 Infraestructura API
| Campo | Detalle |
|------|---------|
| **Entidades** | Ninguna |
| **Controladores** | Closures y endpoints técnicos |
| **Rutas clave** | /health, /test-cors |
| **Estado actual** | Cerrado 9/10 |
| **Nota actual** | **9/10** |
| **Fuente** | EL |
| **Observaciones** | Bloque pequeño, estable y bien aislado. |

### A.18 Utilidades — Extracción de texto desde PDF
| Campo | Detalle |
|------|---------|
| **Entidades** | Ninguna |
| **Controladores** | PdfExtractionController |
| **Rutas clave** | /api/v2/pdf-extract |
| **Estado actual** | Cerrado 9/10 |
| **Nota actual** | **9/10** |
| **Fuente** | EL |
| **Observaciones** | Correcto para el alcance actual; vigilar consumo y límites de archivos si se incrementa uso real. |

---

## Nuevos bloques detectados en el proyecto actual

### A.19 CRM comercial
| Campo | Detalle |
|------|---------|
| **Entidades** | Prospect, ProspectContact, Offer, OfferLine, CommercialInteraction, acciones de agenda CRM |
| **Controladores** | ProspectController, OfferController, CommercialInteractionController, CrmAgendaController, CrmDashboardController |
| **Servicios / soporte** | ProspectService, OfferService, CommercialInteractionService, CrmAgendaService, CrmDashboardService |
| **Policies / Requests** | ProspectPolicy, OfferPolicy, CommercialInteractionPolicy; múltiples Form Requests dedicados |
| **Rutas clave** | crm/dashboard, crm/agenda/*, prospects/*, commercial-interactions, offers/* |
| **Tests detectados** | `tests/Feature/CrmApiTest.php` con cobertura amplia de prospectos, interacciones, agenda, ofertas y conversión a cliente |
| **Estado actual** | Cerrado 9/10 |
| **Nota actual** | **9/10** |
| **Fuente** | AR |
| **Observaciones** | La implementación 2026-03-23 endurece la consistencia de estados en ofertas CRM, de forma que enviar/aceptar/rechazar/expirar vuelve a sincronizar el estado del prospecto. La suite `CrmApiTest` quedó validada con `19` tests verdes y `1` warning residual, suficiente para cerrar el bloque en `9/10`. |

### A.20 Canal operativo / Autoventa / Reparto
| Campo | Detalle |
|------|---------|
| **Entidades** | FieldOperator, DeliveryRoute, RouteTemplate, RouteStop, pedidos operativos/autoventas |
| **Controladores** | FieldOperatorController, FieldCustomerController, FieldProductController, FieldOrderController, FieldRouteController, DeliveryRouteController, RouteTemplateController |
| **Policies / Requests** | FieldOperatorPolicy, DeliveryRoutePolicy, RouteTemplatePolicy; requests dedicados para rutas, pedidos operativos y autoventas |
| **Rutas clave** | field/customers/options, field/products/options, field/orders, field/autoventas, field/routes, field-operators, routes, route-templates |
| **Tests detectados** | `FieldOperatorApiTest`, `OperationalCustomersApiTest`, `OperationalOrdersApiTest`, `RouteManagementApiTest`, `OrdersRouteIntegrityApiTest` |
| **Estado actual** | Cerrado 9/10 |
| **Nota actual** | **9/10** |
| **Fuente** | AR |
| **Observaciones** | La implementación 2026-03-23 cierra una parte importante del gap: listados y CRUD de rutas/plantillas ya respetan mejor la pertenencia comercial y evitan asignaciones a otros comerciales. `RouteManagementApiTest` quedó validado con `4` tests verdes y `1` warning residual, suficiente para cerrar el subbloque de rutas en `9/10`. |

### A.21 Usuarios externos y canal externo
| Campo | Detalle |
|------|---------|
| **Entidades** | ExternalUser, tiendas externas relacionadas |
| **Controladores** | ExternalUserController |
| **Policies / Requests** | ExternalUserPolicy, IndexExternalUserRequest, StoreExternalUserRequest, UpdateExternalUserRequest |
| **Rutas clave** | external-users, external-users/options, resend-access, activate, deactivate |
| **Tests detectados** | `tests/Feature/ExternalUsersApiTest.php` |
| **Estado actual** | Cerrado 9/10 |
| **Nota actual** | **9/10** |
| **Fuente** | AR |
| **Observaciones** | La implementación 2026-03-23 alinea mejor el onboarding/acceso: reactivar un usuario externo vuelve a enviar acceso y `/me` bloquea actores desactivados refrescando el actor externo. `ExternalUsersApiTest` quedó validado con `6` tests verdes y `1` warning residual, suficiente para cerrar el bloque en `9/10`. |

### A.22 Superadmin SaaS
| Campo | Detalle |
|------|---------|
| **Entidades / ámbitos** | Tenants SaaS, impersonation, tokens, blocklist, migrations panel, observability, alerts, feature flags |
| **Controladores** | Superadmin AuthController, TenantController, DashboardController, ImpersonationController, SecurityController, TenantMigrationController, ObservabilityController, FeatureFlagController |
| **Servicios / soporte** | TenantManagementService, ImpersonationService, ObservabilityService, FeatureFlagService |
| **Requests** | StoreTenantRequest, UpdateTenantRequest y requests dedicados en auth, impersonation, feature flags y security |
| **Rutas clave** | v2/superadmin/* |
| **Tests detectados** | `SuperadminTenantCrudTest`, `ImpersonationTest`, trazas indirectas en `DynamicCorsTest` |
| **Estado actual** | Cerrado 9/10 |
| **Nota actual** | **9/10** |
| **Fuente** | AR |
| **Observaciones** | El alcance SaaS ya es claramente bloque propio y estratégico. La implementación 2026-03-23 elimina validación inline en auth/impersonation/feature flags/security con `FormRequest` dedicados y añade cobertura específica. Las suites `SuperadminAuthTest`, `ImpersonationTest` y `SuperadminFeatureSecurityTest` quedaron validadas con `22` tests verdes y `1` warning residual, suficiente para cerrar el bloque en `9/10`. |

---

## Mapeo CORE típico → PesquerApp actual

| Bloque CORE genérico | Bloques PesquerApp correspondientes |
|----------------------|------------------------------------|
| Auth + Roles/Permisos | A.1 Auth + A.14 Sistema |
| Clientes y ventas | A.2 Ventas |
| Stock / movimientos | A.3 Inventario + A.4 Recepciones + A.5 Despachos |
| Producción | A.6 Producción |
| Maestros de producto | A.7 Productos + A.8 Catálogos |
| Configuración tenant | A.13 Settings + A.16 resolución tenant |
| Documentos y exportación | A.15 Documentos + A.18 Utilidades PDF |
| Informes / estadísticas | A.12 Estadísticas |
| CRM comercial | A.19 CRM |
| Canal operativo | A.20 Autoventa / Reparto |
| Canal externo | A.21 Usuarios externos |
| Capa SaaS / plataforma | A.22 Superadmin SaaS |

---

## Resumen actual de puntuaciones

**Fecha de revisión:** 2026-03-23  
**Base usada:** rutas actuales, controladores, requests, policies, tests detectados y `docs/audits/laravel-evolution-log.md`

| Rating | Bloques |
|--------|---------|
| **10/10** | A.3 |
| **9/10** | A.1, A.2, A.4, A.5, A.6, A.7, A.8, A.9, A.10, A.11, A.12, A.13, A.14, A.15, A.16, A.17, A.18, A.19, A.20, A.21, A.22 |

**Resumen por rating:** 10/10 -> 1 bloque | 9/10 -> 21 bloques

---

## Matriz maestra de seguimiento

Esta tabla es la que debe actualizarse cuando se ejecute el prompt de implementación.

| Bloque | Estado | Nota actual | Objetivo | Fuente | Ultima revision | Gap principal |
|--------|--------|-------------|----------|--------|-----------------|---------------|
| A.1 Auth + Roles/Permisos | Cerrado | 9/10 | 10/10 opcional | EL | 2026-03-23 | Cobertura unitaria y homogeneización final de recursos de auth |
| A.2 Ventas | Cerrado | 9/10 | 10/10 opcional | EL | 2026-03-23 | Contratos de reporting/export y más tests de variantes |
| A.3 Inventario / Stock | Cerrado | 10/10 | 10/10 | EL+AR | 2026-03-23 | Mantener estándar; evitar regresiones |
| A.4 Recepciones | Cerrado | 9/10 | 10/10 opcional | EL | 2026-03-23 | Rendimiento y stress en bulk/export |
| A.5 Despachos de Cebo | Cerrado | 9/10 | 10/10 opcional | EL+AR | 2026-03-23 | Cobertura de variantes de export |
| A.6 Producción | Cerrado | 9/10 | 10/10 opcional | EL+AR | 2026-03-23 | Complejidad intrínseca y performance en árboles/costes |
| A.7 Productos | Cerrado | 9/10 | 10/10 opcional | EL | 2026-03-23 | Tests unitarios y filtros/opciones |
| A.8 Catálogos | Cerrado | 9/10 | 10/10 opcional | EL | 2026-03-23 | Pulido y cobertura complementaria |
| A.9 Proveedores + Liquidaciones | Cerrado | 9/10 | 10/10 opcional | EL | 2026-03-23 | Escenarios complejos de liquidación |
| A.10 Etiquetas | Cerrado | 9/10 | 10/10 opcional | EL | 2026-03-23 | Pulido menor |
| A.11 Fichajes | Cerrado | 9/10 | 10/10 opcional | EL+AR | 2026-03-23 | Dashboard/calendario y reducción del hotspot estructural en PunchController |
| A.12 Estadísticas e informes | Cerrado | 9/10 | 10/10 opcional | EL+AR | 2026-03-23 | Índices, tiempos de respuesta, exportes |
| A.13 Settings | Cerrado | 9/10 | 10/10 opcional | EL+AR | 2026-03-23 | Mantener el estándar del bloque y evitar regresiones en acceso/configuración tenant-aware |
| A.14 Sistema | Cerrado | 9/10 | 10/10 opcional | EL | 2026-03-23 | Sincronizado con A.1 |
| A.15 Documentos | Cerrado | 9/10 | 10/10 opcional | EL+AR | 2026-03-23 | Errores de generación/envío y contrato uniforme |
| A.16 Tenants públicos | Cerrado | 9/10 | 10/10 opcional | EL+AR | 2026-03-23 | Mantener simple, seguro y separado del bloque SaaS avanzado |
| A.17 Infraestructura API | Cerrado | 9/10 | 10/10 opcional | EL | 2026-03-23 | Mantenimiento |
| A.18 Utilidades PDF | Cerrado | 9/10 | 10/10 opcional | EL | 2026-03-23 | Límites de uso y consumo |
| A.19 CRM | Cerrado | 9/10 | 10/10 opcional | AR | 2026-03-23 | Warning residual en suite CRM y mejoras opcionales de generación documental/completitud |
| A.20 Canal operativo / Autoventa | Cerrado | 9/10 | 10/10 opcional | AR | 2026-03-23 | Completar integración total con autoventas/pedidos para aspirar a 10/10 |
| A.21 Usuarios externos | Cerrado | 9/10 | 10/10 opcional | AR | 2026-03-23 | Warning residual y más cobertura de flujos de canal externo no críticos |
| A.22 Superadmin SaaS | Cerrado | 9/10 | 10/10 opcional | AR | 2026-03-23 | Warning residual en auth mail/logging y remates de observabilidad tenant-aware |

---

## Instrucción para futuras ejecuciones

Cada vez que el prompt de implementación actúe sobre un bloque debe actualizar, como mínimo:

1. `Estado`
2. `Nota actual`
3. `Ultima revision`
4. `Gap principal`
5. Observaciones del bloque si cambia su alcance

No se debe cerrar un bloque en `9/10` o `10/10` sin dejar reflejado aquí el cambio.
