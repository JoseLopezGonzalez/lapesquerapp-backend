# Revisión del Modelo de Dominio — PesquerApp Backend
**Fecha**: 2026-03-24 | **Auditoría**: Principal Backend v2 (actualización con evidencia de código)

---

## Mapa del Dominio (71 modelos)

El dominio observable se distribuye en cuatro capas:

### Core ERP (modelos principales)

| Bloque | Modelos clave | UsesTenantConnection |
|---|---|---|
| Ventas | Order, OrderPlannedProductDetail, Incident, Customer, Salesperson | ✓ todos |
| Inventario | Store, Pallet, Box, PalletBox, StoredPallet, StoredBox | ✓ todos |
| Materia prima | RawMaterialReception, RawMaterialReceptionProduct, CeboDispatch, CeboDispatchProduct, Cebo | ✓ todos |
| Producción | Production, ProductionRecord, ProductionInput, ProductionOutput, ProductionOutputConsumption, Process, CostCatalog, ProductionCost | ✓ todos |
| Productos y maestros | Product, ProductCategory, ProductFamily, Species, CaptureZone | ✓ todos |
| Catálogos | Transport, Incoterm, PaymentTerm, Country, Tax, FishingGear | ✓ todos |
| Proveedores | Supplier, SupplierLiquidation | ✓ todos |
| Etiquetas y fichajes | Label, PunchEvent, Employee | ✓ todos |
| Auth/sistema tenant | User, Role, ActivityLog, Session, MagicLinkToken | ✓ todos |
| Config tenant | Setting | ✓ |

### Canales de Operación
- DeliveryRoute, RouteStop, RouteTemplate, FieldOperator, ExternalUser, FieldOrder (autoventa)

### CRM Comercial
- Prospect, ProspectContact, ProspectNote, Offer, OfferLine, CommercialInteraction, CrmAgendaEntry

### Plataforma SaaS (BD central)
- Tenant, FeatureFlag, TenantFeatureOverride, TenantErrorLog, TenantMigrationRun, SuperadminUser, SuperadminMagicLinkToken, ImpersonationRequest, ImpersonationLog, SystemAlert, TenantBlocklist

---

## Estados y Transiciones Críticas

### Order (estados de pedido)

| Estado | Descripción | Transiciones posibles |
|---|---|---|
| `pending` | Pedido activo en preparación | → `finished`, → `incident` |
| `finished` | Pedido completado | Estado terminal |
| `incident` | Pedido con incidencia | → `pending` (si se resuelve) |

**Transiciones documentadas en modelo**: `markAsIncident()`, `markAsFinished()`. La consistencia de stock y totales depende de estas transiciones.

### Pallet (estados de almacén)

- `stored` — pallet en almacén (tiene StoredPallet asociado)
- Sin `stored` — pallet libre, en tránsito o asignado a pedido

### ProductionRecord (árbol de producción)

```
Production
  └── ProductionRecord (proceso raíz)
        ├── ProductionInput (cajas entrada)
        ├── ProductionOutput (cajas salida)
        │     └── ProductionOutputConsumption (consumo de cajas output)
        └── ProductionRecord hijos (sub-procesos)
```

La trazabilidad es a nivel de caja (`Box`). `ProductionInput` vincula cajas específicas a un proceso. Esta trazabilidad es la característica diferencial más compleja del sistema.

---

## Hallazgos de Modelado

### DM-H1 — Order sin índice en customer_id ni salesperson_id standalone (MEDIO)

La tabla `orders` tiene índice compuesto `(status, load_date)` pero no tiene índices standalone en `customer_id` ni `salesperson_id`. Las queries del CRM (historial por cliente) y las queries de comercial (mis pedidos) probablemente hacen full table scan + filter.

**Migración `2025_12_12_100000_ensure_indexes_for_orders_show_path.php`** añadió algunos índices pero no está claro si cubre `customer_id`.

### DM-H2 — Production: árbol sin límite de profundidad formal (BAJO)

`ProductionRecord` puede tener registros hijos (`ProductionRecord hijos`). No hay un límite de profundidad máxima definido. Aunque en la práctica los procesos tienen 1-3 niveles, una recursión sin límite en serialización puede causar problemas de memoria.

### DM-H3 — Múltiples librerías PDF coexistiendo (BAJO — deuda de dependencias)

`composer.json` incluye 7 librerías relacionadas con PDF:
- `barryvdh/laravel-dompdf` — PDF via PHP puro
- `barryvdh/laravel-snappy` + `beganovich/snappdf` — PDF via wkhtmltopdf
- `spatie/laravel-pdf` + `spatie/browsershot` — PDF via Chromium headless
- `spatie/pdf-to-text` — extracción de texto de PDF
- `smalot/pdfparser` — parsing de PDF

La coexistencia de 3 motores de renderizado (DomPDF, wkhtmltopdf, Chromium) aumenta el footprint de dependencias y la superficie de configuración. Si el proyecto usa principalmente uno, los demás son deuda.

### DM-H4 — tymon/jwt-auth sin uso visible (BAJO)

`composer.json` incluye `tymon/jwt-auth: ^2.1` pero todas las rutas activas usan `auth:sanctum`. JWT es probablemente un residuo de la migración a Sanctum. Si no hay rutas activas con `auth:api` (JWT), el paquete puede eliminarse.

---

## Valoración por Subdominio

| Subdominio | Claridad del modelo | Observación |
|---|---|---|
| ERP Core (ventas, inventario) | 9/10 | Muy bueno; estados documentados |
| Producción | 8.5/10 | Árbol complejo pero coherente; trazabilidad de caja correcta |
| Fichajes | 8.5/10 | Modelo simple y limpio; race condition en store |
| Materia prima | 8/10 | Bien modelado; escritura compleja en write service |
| CRM / Canales | 7.5/10 | En consolidación; algunos edges pendientes de Policy |
| SaaS / Superadmin | 8/10 | Bien segregado del dominio de negocio |

**Modelo de dominio global**: **8.5/10** (lenguaje claro, estados documentados, aislamiento correcto)
