# Auditoría profunda del sistema de seeders, factories y dataset de desarrollo

> **Fecha:** 2026-03-26  
> **Metodología:** exploración directa del repositorio. Todo lo que se afirma aquí está verificado en código real o marcado explícitamente como inferencia razonable.

---

## 1. Resumen ejecutivo

El proyecto tiene una base de seeders de tenant **útil para arrancar y hacer una demo parcial**, pero todavía **no dispone de un sistema maestro de datos de desarrollo realmente completo** para trabajar todo el ERP con naturalidad.

### Diagnóstico corto

- **Fortalezas reales**:
  - Existe un `TenantDatabaseSeeder` relativamente amplio para catálogos y parte operativa.
  - Existe una separación sana entre **seed de desarrollo/demo** (`TenantDatabaseSeeder`) y **seed de onboarding/producción** (`TenantProductionSeeder`).
  - Hay varios seeders con intención clara de realismo: `Faker es_ES`, referencias del dominio pesquero, clientes/productos inspirados en backups o nomenclatura real.
  - El flujo multi-tenant de seed está integrado en comandos reales (`tenants:migrate --seed`, `tenants:seed`, `tenants:dev-migrate --seed`).

- **Debilidades estructurales importantes**:
  - La cobertura de datos de desarrollo está **muy concentrada en unos pocos módulos**: catálogos, pedidos, stock básico, recepciones, despachos, usuarios y parte de fichajes.
  - Hay **módulos completos soportados por el código pero sin seeders/factories de trabajo**, especialmente:
    - CRM
    - ofertas
    - rutas
    - incidencias
    - producción avanzada
    - costes
    - etiquetas
  - La cobertura de factories es **muy insuficiente** para el tamaño del dominio:
    - 70 modelos totales
    - 54 modelos con `HasFactory`
    - solo 8 archivos en `database/factories`
  - Los tests muestran una señal clara de deuda:
    - gran parte del dataset de prueba se construye manualmente en tests
    - varios tests ejecutan seeders sueltos o crean entidades a mano porque el sistema actual no cubre bien esos escenarios

### Veredicto

Hoy el sistema actual **sirve para arrancar el tenant y recorrer algunos bloques principales**, pero **no sirve todavía como entorno dev rico y generalista para todo el backend**.

No está en estado “mínimo roto”; está en un estado intermedio:

- suficientemente bueno para catálogos y parte de operaciones
- claramente insuficiente para una experiencia dev parecida a producción
- poco reutilizable en testing por falta de factories y escenarios componibles

### Nivel de madurez actual

**Maturidad global estimada: MEDIA-BAJA** para dataset dev completo.

**Maturidad por áreas**:

- catálogos base: **media-alta**
- usuarios/roles básicos: **media**
- inventario y pedidos básicos: **media**
- recepciones/despachos/fichajes: **media**
- CRM/rutas/producción avanzada/incidentes/ofertas/costes: **baja**
- reutilización vía factories: **baja**
- estrategia global central + tenant + onboarding + demo + edge cases: **media-baja**

## 1.1 Estado de ejecución del rediseño

> **Actualizado tras implementación parcial del plan.**

### Resumen visual

| Bloque | Estado | Icono | Avance | Situación actual |
|--------|--------|-------|--------|------------------|
| Bootstrap central SaaS | `IMPLEMENTADO` | ✅ | `100%` | `DatabaseSeeder` ya delega en `CentralDevBootstrapSeeder`; existen tenants base, superadmin y feature flags |
| Orquestación tenant por capas | `IMPLEMENTADO` | ✅ | `100%` | `TenantDatabaseSeeder` y `TenantProductionSeeder` ya son orquestadores cortos |
| Calibres y huérfanos críticos | `IMPLEMENTADO PARCIAL` | 🟡 | `85%` | `CalibersSeeder`, `FeatureFlagSeeder`, `DevTenantFeatureOverridesSeeder` y `SuperadminUserSeeder` ya están integrados; `AlgarSeafoodUserSeeder` sigue manual |
| Settings dev con fallback | `IMPLEMENTADO` | ✅ | `100%` | existe `TenantCompanySettingsSeeder` con valores plausibles por defecto |
| Stock relacional base | `IMPLEMENTADO PARCIAL` | 🟡 | `70%` | ya existen `PalletBoxSeeder` y `StoredPalletSeeder`; `StoredBox` sigue sin estrategia activa |
| Factories fundacionales | `IMPLEMENTADO` | ✅ | `100%` | el proyecto pasa de 8 a 43 factories |
| Factories CRM/rutas/producción/incidencias | `IMPLEMENTADO PARCIAL` | 🟡 | `92%` | CRM, rutas y producción ya tienen `states`/factories reutilizables; queda refinar cobertura secundaria y edge cases |
| Seeders avanzados de CRM/ofertas/rutas/producción | `IMPLEMENTADO PARCIAL` | 🟡 | `78%` | ya existen `TenantCrmDevSeeder`, `TenantRoutesDevSeeder` y `TenantProductionDevSeeder`; falta profundizar volúmenes y escenarios extremos |
| Edge cases y niveles de volumen | `IMPLEMENTADO PARCIAL` | 🟡 | `90%` | ya existen `TenantExtendedDatasetSeeder`, `TenantEdgeDatasetSeeder`, `TenantVolumeExpansionSeeder` y `TenantEdgeCasesSeeder`, además de soporte `--dataset=base|extended|edge` en comandos |
| Consolidación de tests contra factories nuevas | `IMPLEMENTADO PARCIAL` | 🟡 | `50%` | ya existen `BuildsCrmScenario`, `BuildsRouteScenario`, `BuildsProductionScenario` y `TenantSeedDatasetSupportTest`; aún quedan muchas suites legacy montando datos a mano |

### Lectura rápida

- Lo ya resuelto cubre bien los **bloques 1, 2 y buena parte del 3** del plan.
- El **bloque 4** queda encaminado, pero no cerrado del todo porque `StoredBox` y los escenarios operativos avanzados aún no están integrados.
- El **bloque 5 (CRM + ofertas)** ya está implementado a nivel de seeders tenant y helper reusable para tests.
- El **bloque 6 (rutas + permisos operativos)** ya está implementado a nivel de seeders tenant y helper reusable para tests.
- El **bloque 7 (producción + costes + incidencias + etiquetas)** ya está implementado a nivel base del tenant estándar y helper reusable para tests.
- El **bloque 8** ya está implementado en su base técnica con niveles oficiales `base / extended / edge`, aunque queda seguir migrando más tests legacy hacia factories/helpers reutilizables.

---

## 2. Estado actual del sistema de seeders y factories

## 2.1 Seeders detectados

Se detectan **69 seeders** en `database/seeders` tras la implementación parcial del rediseño.

- `DatabaseSeeder.php`
- `CentralDevBootstrapSeeder.php`
- `CentralTenantsSeeder.php`
- `TenantDatabaseSeeder.php`
- `TenantProductionSeeder.php`
- `TenantBaseCatalogSeeder.php`
- `TenantBaseActorsSeeder.php`
- `TenantCompanySettingsSeeder.php`
- `TenantOperationsSeeder.php`
- `TenantCrmDevSeeder.php`
- `TenantCrmProspectsSeeder.php`
- `TenantCrmAgendaSeeder.php`
- `TenantCrmOffersSeeder.php`
- `TenantRoutesDevSeeder.php`
- `TenantRouteTemplatesSeeder.php`
- `TenantDeliveryRoutesSeeder.php`
- `TenantProductionDevSeeder.php`
- `TenantProductionCostsSeeder.php`
- `TenantProductionAdvancedSeeder.php`
- `TenantIncidentsLabelsSeeder.php`
- `TenantExtendedDatasetSeeder.php`
- `TenantEdgeDatasetSeeder.php`
- `TenantVolumeExpansionSeeder.php`
- `TenantEdgeCasesSeeder.php`
- `TenantDemoUiSeeder.php`
- `UsersSeeder.php`
- `RepartidorAutoventaDevSeeder.php`
- `ProductCategorySeeder.php`
- `ProductFamilySeeder.php`
- `CaptureZonesSeeder.php`
- `FishingGearSeeder.php`
- `SpeciesSeeder.php`
- `ProcessSeeder.php`
- `CountriesSeeder.php`
- `PaymentTermsSeeder.php`
- `IncotermsSeeder.php`
- `TransportsSeeder.php`
- `SalespeopleSeeder.php`
- `TaxSeeder.php`
- `SupplierSeeder.php`
- `FAOZonesSeeder.php`
- `StoreSeeder.php`
- `ExternalUsersSeeder.php`
- `ExternalStoresSeeder.php`
- `StoreOperatorUserSeeder.php`
- `EmployeeSeeder.php`
- `PunchEventSeeder.php`
- `CustomerSeeder.php`
- `ProductSeeder.php`
- `RawMaterialReceptionSeeder.php`
- `RawMaterialReceptionProductSeeder.php`
- `CeboDispatchSeeder.php`
- `CeboDispatchProductSeeder.php`
- `OrderSeeder.php`
- `OrderPlannedProductDetailSeeder.php`
- `BoxSeeder.php`
- `PalletSeeder.php`
- `PalletBoxSeeder.php`
- `StoredPalletSeeder.php`
- `OrderPalletSeeder.php`
- `PalletTimelineSeeder.php`
- `FeatureFlagSeeder.php`
- `DevTenantFeatureOverridesSeeder.php`
- `SuperadminUserSeeder.php`
- `CalibersSeeder.php`
- `AlgarSeafoodUserSeeder.php`
- `RoleSeeder.php`

## 2.2 Factories detectadas

Actualmente hay **43 factories**.

### Cobertura nueva ya implementada

Además de las factories originales, ahora existen factories fundacionales y de módulos avanzados para:

- catálogo y maestros:
  - `CountryFactory`
  - `ProcessFactory`
  - `PaymentTermFactory`
  - `IncotermFactory`
  - `TaxFactory`
  - `TransportFactory`
  - `SupplierFactory`
- actores y operativa base:
  - `CustomerFactory`
  - `SalespersonFactory`
  - `ExternalUserFactory`
  - `FieldOperatorFactory`
  - `EmployeeFactory`
  - `OrderFactory`
  - `OrderPlannedProductDetailFactory`
  - `RawMaterialReceptionFactory`
  - `RawMaterialReceptionProductFactory`
  - `CeboDispatchFactory`
  - `CeboDispatchProductFactory`
- CRM y ofertas:
  - `ProspectFactory`
  - `ProspectContactFactory`
  - `CommercialInteractionFactory`
  - `AgendaActionFactory`
  - `OfferFactory`
  - `OfferLineFactory`
- rutas:
  - `DeliveryRouteFactory`
  - `RouteTemplateFactory`
  - `RouteTemplateStopFactory`
  - `RouteStopFactory`
- producción, costes e incidencias:
  - `ProductionFactory`
  - `ProductionRecordFactory`
  - `ProductionInputFactory`
  - `ProductionOutputFactory`
  - `CostCatalogFactory`
  - `ProductionCostFactory`
  - `IncidentFactory`

Esto sigue contrastando con:

- **70 modelos** totales
- **54 modelos con `HasFactory`**

### Hallazgo comprobado

El proyecto ya no está en situación “factory-poor” extrema, pero **todavía no alcanza cobertura completa**. El salto de 8 a 43 factories elimina una de las mayores deudas del sistema, aunque aún faltan factories o refinamiento en algunos modelos secundarios.

- Estado anterior: semánticamente “factory-ready”, operativamente “factory-poor”
- Estado actual: semánticamente y operativamente mucho más alineado, pero aún incompleto

## 2.3 Seeders huérfanos — existen pero no se ejecutan en ningún flujo

### Hallazgo comprobado

Tras la implementación parcial, la situación mejora de forma clara. Los seeders huérfanos críticos ya no son 5; queda básicamente un grupo residual y explícitamente manual.

| Seeder | Qué crea | Impacto de estar huérfano |
|--------|----------|--------------------------|
| `AlgarSeafoodUserSeeder.php` | 1 usuario demo para tenant "Algar Seafood PT" | Sigue siendo un seeder tenant-específico de uso manual |
| `TenantDemoUiSeeder.php` | Orquesta el timeline visual de palés | Es deliberadamente opcional y no debe colgar del seed base |

`CalibersSeeder`, `FeatureFlagSeeder`, `DevTenantFeatureOverridesSeeder` y `SuperadminUserSeeder` ya han dejado de ser huérfanos críticos.

## 2.4 Flujo actual real

### Seed central

`database/seeders/DatabaseSeeder.php` ya no es mínimo. Ahora delega en `CentralDevBootstrapSeeder`, que integra:

- `SuperadminUserSeeder`
- `FeatureFlagSeeder`
- `CentralTenantsSeeder`
- `DevTenantFeatureOverridesSeeder`

### Estado actual

- el bootstrap central SaaS básico ya existe
- queda pendiente enriquecerlo con más escenarios SaaS y más tenants especializados

### Seed tenant de desarrollo

`database/seeders/TenantDatabaseSeeder.php` sigue siendo el flujo principal de desarrollo tenant, pero ya no está monolítico. Ahora orquesta por capas:

1. `TenantBaseCatalogSeeder`
2. `TenantBaseActorsSeeder`
3. `TenantCompanySettingsSeeder`
4. `TenantOperationsSeeder`
5. `TenantCrmDevSeeder`
6. `TenantRoutesDevSeeder`
7. `TenantProductionDevSeeder`

### Niveles de dataset ya disponibles

El rediseño ya deja tres niveles oficiales de dataset tenant:

| Dataset | Seeder raíz | Estado | Icono | Uso recomendado |
|--------|-------------|--------|-------|-----------------|
| `base` | `TenantDatabaseSeeder` | `IMPLEMENTADO` | ✅ | desarrollo diario estándar |
| `extended` | `TenantExtendedDatasetSeeder` | `IMPLEMENTADO` | ✅ | demos internas, QA manual con más volumen y `TenantDemoUiSeeder` |
| `edge` | `TenantEdgeDatasetSeeder` | `IMPLEMENTADO PARCIAL` | 🟡 | reproducción de edge cases y validación manual extrema |

### Comandos ya preparados para niveles

Los comandos tenant ya aceptan `--dataset=base|extended|edge` sin romper el flujo previo:

- `php artisan tenants:dev-migrate --seed --dataset=base`
- `php artisan tenants:dev-migrate --seed --dataset=extended`
- `php artisan tenants:dev-migrate --seed --dataset=edge`
- `php artisan tenants:migrate --seed --dataset=base`
- `php artisan tenants:seed --dataset=extended`
6. `TenantRoutesDevSeeder`
7. `TenantProductionDevSeeder`

### Seed tenant de onboarding/producción

`database/seeders/TenantProductionSeeder.php` siembra solo:

- catálogos base
- actores mínimos
- almacenes base
- settings de empresa con fallback dev razonable

No mete:

- datos demo
- transacciones
- pedidos
- CRM
- producción
- usuarios operativos de desarrollo

Esta separación es buena y debe conservarse.

### Comandos reales

Se detectan tres flujos útiles:

- `tenants:migrate {--fresh} {--seed}`
- `tenants:seed {--class=}`
- `tenants:dev-migrate {--fresh} {--seed}`

Además, el onboarding SaaS llama a `db:seed` en contexto tenant desde `TenantOnboardingService`.

---

## 3. Cobertura actual por módulos y tablas

## 3.1 Cobertura buena o razonable

### Catálogos base

Cobertura razonable en:

- categorías y familias de producto
- zonas de captura
- artes de pesca
- especies
- procesos
- países
- formas de pago
- incoterms
- transportes
- impuestos
- proveedores
- zonas FAO

### Actores y estructura básica operativa

Cobertura razonable en:

- usuarios internos
- comercial ligado a `Salesperson`
- repartidor/autoventa con `FieldOperator`
- usuarios externos
- almacenes internos y externos
- empleados

### Operativa básica tenant

Cobertura razonable pero limitada en volumen en:

- clientes
- productos
- recepciones
- líneas de recepción
- despachos
- líneas de despacho
- pedidos
- líneas previstas de pedido
- cajas
- palets
- asignación pedido-palé
- timeline demo de palés
- fichajes

## 3.2 Cobertura parcial

### Settings / configuración

Hay cobertura funcional, pero orientada a bootstrap:

- `TenantDatabaseSeeder` vuelca `config/company` sobre `settings`
- `TenantProductionSeeder` crea defaults mínimos

Esto ya no depende de `config('company')` vacío de forma frágil: existe `TenantCompanySettingsSeeder` con fallback plausible. Sigue faltando variedad de configuración tenant, pero el problema de “empresa fantasma” queda mitigado.

### Superadmin y feature flags

Existe base técnica, pero no está integrada en un flujo dev central claro:

- `SuperadminUserSeeder` existe
- `FeatureFlagSeeder` existe
- `DevTenantFeatureOverridesSeeder` existe

Ya están integrados en el bootstrap central. Sigue pendiente ampliar escenarios multiplan y overrides no triviales.

### Tablas relacionales de inventario: PalletBox, StoredPallet, StoredBox

### Hallazgo comprobado

Existen modelos y migraciones para tres tablas que completan el módulo inventario:

- `PalletBox` — pivot entre `Pallet` y `Box` (qué cajas pertenecen a qué palé)
- `StoredPallet` — registra en qué almacén y posición está un palé almacenado
- `StoredBox` — registra el estado de almacenamiento de una caja

La situación aquí ha mejorado parcialmente:

- `PalletBox` ya tiene `PalletBoxSeeder`
- `StoredPallet` ya tiene `StoredPalletSeeder`
- `StoredBox` sigue sin cobertura ni estrategia clara

Esto significa que en dev:

- ya puede existir relación caja-palé
- ya puede existir posición real de palés en almacén
- la capa de almacenamiento de cajas individuales sigue incompleta

Sin una estrategia para `StoredBox`, el módulo inventario todavía no está completamente cerrado, pero ya ha dejado de estar “aislado” en su nivel más básico.

### Fichajes y stock

Sí hay datos, pero son más “muestras funcionales” que dataset rico:

- 40 fichajes
- 20 palets
- 30 cajas
- 10 recepciones
- 10 despachos

Esto puede servir para probar endpoints, pero se queda corto para:

- filtros ricos
- dashboards creíbles
- QA manual profunda
- demos de uso continuo

## 3.3 Cobertura baja o inexistente

### CRM

Cobertura ya implementada a nivel dev tenant en:

- `Prospect`
- `ProspectContact`
- `CommercialInteraction`
- `AgendaAction`

Seeders nuevos:

- `TenantCrmDevSeeder`
- `TenantCrmProspectsSeeder`
- `TenantCrmAgendaSeeder`

Estado actual:

- ya existe dataset CRM navegable por comercial
- ya hay separación entre comercial principal, segundo comercial y admin
- ya hay prospectos con estados `new`, `following`, `offer_sent`, `customer` y `discarded`
- ya hay contactos primarios y secundarios
- ya hay interacciones sobre prospectos y clientes
- ya hay agenda con `pending`, `done`, `cancelled` y `reprogrammed`

### Ofertas

Cobertura ya implementada a nivel dev tenant en:

- `Offer`
- `OfferLine`

Seeder nuevo:

- `TenantCrmOffersSeeder`

Estado actual:

- ya existen ofertas `draft`, `sent`, `accepted` y `rejected`
- ya hay líneas de oferta contra productos reales del tenant
- ya hay al menos una oferta aceptada con `order_id`
- sigue pendiente ampliar volumen y variantes extremas

### Rutas

Cobertura ya implementada a nivel dev tenant en:

- `DeliveryRoute`
- `RouteTemplate`
- `RouteStop`
- `RouteTemplateStop`

Seeders nuevos:

- `TenantRoutesDevSeeder`
- `TenantRouteTemplatesSeeder`
- `TenantDeliveryRoutesSeeder`

Estado actual:

- ya existen plantillas activas por admin, comercial principal y comercial secundario
- ya existen rutas `planned`, `in_progress`, `completed` y `cancelled`
- ya hay stops `pending`, `completed` y `skipped`
- ya hay ownership claro para admin, comercial y field operator
- ya hay rutas operativas visibles para `/api/v2/field/routes`

### Producción avanzada

Cobertura ya implementada a nivel dev tenant en:

- `Production`
- `ProductionRecord`
- `ProductionInput`
- `ProductionOutput`
- `ProductionOutputConsumption`
- `ProductionOutputSource`
- `ProductionCost`
- `CostCatalog`

Seeders nuevos:

- `TenantProductionDevSeeder`
- `TenantProductionCostsSeeder`
- `TenantProductionAdvancedSeeder`

Estado actual:

- ya existen lotes abiertos y cerrados
- ya existen records raíz e hijo
- ya hay inputs reales desde cajas disponibles
- ya hay outputs intermedios y finales
- ya hay consumos desde output del padre
- ya hay `sources` para trazabilidad de coste
- ya hay catálogos de coste y costes por proceso/lote

### Incidencias

Cobertura ya implementada a nivel dev tenant en:

- `Incident`

Estado actual:

- ya existen incidencias abiertas y resueltas vinculadas a pedidos

### Etiquetas

Existe `LabelFactory` y ahora también una capa base de seeding dev:

- `TenantIncidentsLabelsSeeder`

Estado actual:

- ya existen etiquetas de caja, palet y retail para usar el módulo sin partir de cero

### Costes

Cobertura ya implementada a nivel dev tenant en:

- `CostCatalog`
- `ProductionCost`

### Cebo (materia prima bait en stock)

### Hallazgo — inferencia razonable

Existe el modelo `Cebo` (`app/Models/Cebo.php`, `belongsTo: Product`) que es distinto de `CeboDispatch`. No hay seeder ni factory para `Cebo`. El audit cubre correctamente `CeboDispatch` y `CeboDispatchProductSeeder`, pero el modelo `Cebo` como entidad de stock de materia prima bait no tiene representación en el dataset dev.

### Datos centrales dev del SaaS

No hay un sistema central completo de datos de desarrollo para:

- tenants demo
- tenants en distintos estados
- feature flags base
- overrides por tenant
- superadmin listo para entorno local

---

## 4. Carencias críticas

## C1. El sistema de datos dev no cubre módulos enteros del backend real

- **Gravedad:** crítica
- **Hallazgo:** comprobado
- **Evidencia:**
  - existen modelos y tests para CRM, rutas, producción avanzada, ofertas, incidencias y costes
  - CRM + ofertas ya tienen seeders y factories reutilizables
  - rutas, producción avanzada, incidencias y costes siguen sin seeders equivalentes de trabajo
- **Modelos afectados:**
  - `DeliveryRoute`, `RouteTemplate`, `RouteStop`, `RouteTemplateStop`
  - `Production*`
  - `CostCatalog`, `ProductionCost`
  - `Incident`
- **Por qué perjudica el dataset dev:**
  - impide usar el sistema completo como un ERP vivo en desarrollo
  - obliga a crear datos a mano para probar módulos enteros
  - hace inviables demos integrales coherentes
- **Recomendación concreta:**
  - crear una segunda capa de seeders tenant por dominios:
    - `TenantRoutesSeeder`
    - `TenantProductionSeeder` de desarrollo avanzado, separado del de onboarding
    - `TenantIncidentsSeeder`
    - `TenantCostsSeeder`

## C2. Cobertura de factories claramente insuficiente para el tamaño del dominio

- **Gravedad:** crítica
- **Hallazgo:** comprobado
- **Evidencia:**
  - 54 modelos usan `HasFactory`
  - ahora existen 43 archivos en `database/factories`
  - muchos tests crean modelos manualmente con `create()` o `firstOrCreate()`
- **Por qué perjudica el entorno dev:**
  - baja reutilización
  - más fricción en tests
  - más duplicación de lógica de creación de datos
  - difícil generar escenarios combinatorios o edge cases de forma mantenible
- **Recomendación concreta:**
  - mantener factories como capa base del sistema y dejar los seeders como orquestadores
  - siguiente prioridad:
    - completar modelos secundarios todavía sin factory
    - añadir más `states`
    - migrar tests prioritarios a factories nuevas

## C3. Los tests revelan que el sistema actual no es suficientemente reusable

- **Gravedad:** crítica
- **Hallazgo:** comprobado
- **Evidencia:**
  - `tests/Feature/StockBlockApiTest.php` ejecuta varios seeders sueltos y crea un `Product` manual porque evita `ProductSeeder`
  - `CrmApiTest`, `RouteManagementApiTest` y `ProductionBlockApiTest` ya incorporan helpers reusables (`BuildsCrmScenario`, `BuildsRouteScenario`, `BuildsProductionScenario`), pero `OrderApiTest`, `CustomerApiTest`, `OperationalOrdersApiTest` y muchos más siguen montando entidades a mano
  - hay gran dependencia de `create()`/`firstOrCreate()` en tests
- **Por qué perjudica:**
  - si los tests no reutilizan un sistema estándar de datos, el sistema de seeders/factories no está haciendo su trabajo como plataforma de datos
  - aumenta el coste de mantenimiento y la dispersión de reglas implícitas
- **Recomendación concreta:**
  - definir una librería de factories y states primero
  - añadir seeders “test-friendly” sin dependencia de `$this->command`
  - crear helpers de escenarios compartidos o “scenario builders” para tests complejos

## C4. No existe un bootstrap central de entorno dev SaaS realmente completo

- **Gravedad:** crítica
- **Hallazgo:** comprobado
- **Evidencia:**
  - `DatabaseSeeder` solo llama `RoleSeeder` y `User::factory(10)->create()`
  - no crea tenants
  - no crea superadmin
  - no crea flags base
  - no deja un entorno SaaS listo de forma global
- **Por qué perjudica:**
  - para un proyecto multi-tenant, el entorno dev no puede depender solo del seed tenant
  - falta una forma consistente de levantar:
    - tenant demo
    - superadmin
    - flags
    - overrides
    - tenant(s) con estados distintos
- **Recomendación concreta:**
  - esta recomendación ya está parcialmente implementada con:
    - `CentralDevBootstrapSeeder`
    - `CentralTenantsSeeder`
  - siguiente paso:
    - ampliar escenarios SaaS y tenants demo más ricos

## C5. Seeders huérfanos — catálogos y sistema nunca ejecutados en dev

- **Gravedad:** crítica
- **Hallazgo:** comprobado
- **Evidencia:**
  - esta carencia ya está resuelta para los seeders críticos
  - solo persiste `AlgarSeafoodUserSeeder` como manual y `TenantDemoUiSeeder` como opcional
- **Modelos/tablas afectadas:**
  - `calibers` (vacía)
  - `feature_flags` (vacía)
  - `tenant_feature_overrides` (vacía)
  - usuarios superadmin (ausentes)
- **Por qué perjudica el dataset dev:**
  - Los calibres son datos de catálogo maestro; su ausencia impacta filtros, formularios y cualquier entidad que los referencie
  - El sistema de feature flags es estructural para el SaaS multiplan; sin datos base, los módulos controlados por flags no se pueden probar
  - El superadmin solo existe si alguien recuerda ejecutarlo manualmente, lo que hace el onboarding dev no reproducible
- **Estado actual:**
  - `CalibersSeeder`: implementado
  - `FeatureFlagSeeder`: implementado
  - `DevTenantFeatureOverridesSeeder`: implementado
  - `SuperadminUserSeeder`: implementado
  - `AlgarSeafoodUserSeeder`: pendiente como documentación/uso manual

---

## 5. Carencias importantes

## I1. El dataset actual es útil pero de poco volumen para varios bloques operativos

- **Gravedad:** importante
- **Hallazgo:** comprobado
- **Evidencia:**
  - pedidos objetivo: 20
  - palets: 20
  - cajas: 30
  - recepciones: 10
  - despachos: 10
  - fichajes: 40
- **Problema:**
  - suficiente para smoke/manual básico
  - insuficiente para listas, filtros, paginación, dashboards y escenarios más creíbles
- **Recomendación concreta:**
  - mantener y explotar los niveles ya introducidos:
    - `base`
    - `extended`
    - `edge`
  - seguir ajustando objetivos de volumen por dominio dentro de `TenantVolumeExpansionSeeder`

## I2. `TenantDatabaseSeeder` está creciendo como seeder monolítico de desarrollo

- **Gravedad:** importante
- **Hallazgo:** comprobado
- **Evidencia:**
  - mezcla catálogos, usuarios, stock, operativa y timeline
  - concentra demasiada responsabilidad en una sola cadena
- **Problema:**
  - difícil extender
  - difícil desactivar escenarios
  - difícil distinguir “datos base” de “datos demo” o “edge cases”
- **Recomendación concreta:**
  - convertirlo en un orquestador corto que llame a seeders agrupados por dominio

## I3. Hay mezcla imperfecta entre idempotencia real y seeders transaccionales acumulativos

- **Gravedad:** importante
- **Hallazgo:** comprobado
- **Evidencia:**
  - muchos catálogos usan `firstOrCreate` / `updateOrCreate`
  - varios seeders operativos usan `create()` con guardias por conteo
  - la propia documentación de `docs/instrucciones/actualizacion-seeders-migraciones.md` recomienda `--fresh --seed` como opción más segura
- **Problema:**
  - buena parte del sistema no está pensado para resembrar incrementalmente con precisión
- **Recomendación concreta:**
  - decidir por tipo de seeder:
    - catálogos y maestros: idempotentes
    - transaccionales demo: regenerables por lote o namespace
    - edge cases: activación explícita y aislada

## I4. Los datos de configuración, feature flags y central SaaS están poco representados en dev

- **Gravedad:** importante
- **Hallazgo:** comprobado
- **Problema:**
  - un SaaS multi-tenant necesita dataset central también útil
- **Recomendación concreta:**
  - sembrar:
    - varios tenants
    - distintos planes/status
    - flags por plan
    - overrides por tenant
    - superadmin listo

---

## 6. Variantes, estados y edge cases faltantes

## 6.1 Estados no representados o poco representados

### Pedidos

Actualmente hay cobertura básica de:

- `pending`
- `finished`
- `incident`

Pero faltan o no están claramente representados:

- cancelados
- incompletos
- con ruta asignada
- con field operator asignado
- con incidencias enlazadas
- con distintos grados de ejecución operativa

### Palets y stock

Hay estados básicos, pero faltan escenarios ricos como:

- palets sin posición
- palets con posición pero sin pedido
- palets asociados a recepción
- palets parcialmente usados en producción
- palets mixtos
- palets externos/internos vinculados a external user

### Producción

Prácticamente faltan todos los estados y ciclos reales:

- producción abierta/cerrada
- registros raíz/hijos
- outputs consumidos parcialmente
- outputs agotados
- costes asociados
- inputs desde stock
- flujos multi-etapa

### CRM

Faltan combinaciones clave:

- prospectos nuevos/en seguimiento/ganados/perdidos
- acciones pendientes/completadas/canceladas
- interacciones con distintos resultados
- prospectos convertidos a cliente
- prospectos sin contacto primario

### Rutas

Faltan:

- rutas planificadas, en curso, completadas, canceladas
- stops pendientes, completados, fallidos
- plantillas propias/ajenas
- rutas de comercial vs repartidor

## 6.2 Casos borde útiles ausentes

Faltan claramente escenarios como:

- cliente sin alias
- cliente sin transportista
- comercial sin usuario y con usuario
- external user activo e inactivo
- store externo sin stock y con stock
- pedidos sin líneas y con líneas complejas
- recepciones con datos declarados y sin datos declarados
- despachos con tipos de exportación variados
- fichajes anómalos:
  - doble IN
  - OUT sin IN previo
  - secuencias cruzadas
- etiquetas con formatos distintos
- incidencias abiertas y resueltas
- tenant suspendido con overrides o flags distintos

---

## 7. Problemas de realismo del dataset actual

## 7.1 Puntos fuertes reales

- uso consistente de `Faker::create('es_ES')` en muchos seeders
- varios nombres y referencias del sector pesquero
- algunos clientes y productos inspirados en datos o nomenclaturas reales
- cronologías operativas recientes en pedidos, recepciones y fichajes

## 7.2 Problemas de realismo

### R1. Demasiado “dataset de muestra” y no suficiente “sistema vivo”

- las cantidades y combinaciones se sienten más pensadas para endpoints que para un ERP usado durante semanas

### R2. Falta densidad histórica

- pocos históricos profundos
- poca distribución temporal larga
- faltan datos de varios meses/trimestres por módulo

### R3. Falta de proporciones creíbles entre módulos

Ejemplos:

- muy pocos pedidos respecto a clientes potenciales
- muy poco stock respecto a un almacén creíble
- casi inexistente producción avanzada
- nula red comercial sembrada por CRM/ofertas/rutas

### R4. Escasa representación de conflicto o irregularidad razonable

- faltan datos “sucios controlados”
- faltan secuencias imperfectas
- faltan estados ambiguos o incompletos

### R5. Dataset poco útil para demos de negocio completas

- se puede enseñar parte del stock/pedidos
- no se puede enseñar bien la película completa de:
  - captación comercial
  - oferta
  - pedido
  - ejecución operativa
  - incidencia
  - ruta
  - cierre

### R6. Settings de empresa sembrados desde config/company — valores placeholder en dev

### Hallazgo comprobado

`TenantDatabaseSeeder` siembra los settings de empresa volcando `config('company')` sobre la tabla `settings` con `Arr::dot()`. `TenantProductionSeeder` siembra 16 settings con valores vacíos por defecto (`''`).

El problema concreto: en un entorno dev estándar donde `config/company` no tiene valores reales configurados (lo habitual con un `.env` de desarrollo genérico), los settings sembrados serán strings vacíos:

- `company.display_name` → `''`
- `company.tax_id` → `''`
- `company.email` → `''`
- `company.mail.host` → `''`

Esto tiene consecuencias reales en desarrollo:
- Cualquier pantalla o informe que muestre el nombre de la empresa mostrará un campo vacío
- Los tests de envío de email pueden fallar silenciosamente con host vacío
- Las demos con el módulo de configuración muestran una empresa "fantasma"

**Recomendación concreta:** El seeder de settings debería tener valores dev razonables hardcodeados como fallback cuando el config esté vacío — una empresa ficticia pesquera con datos plausibles (nombre, NIF ficticio, dirección, etc.).

---

## 8. Problemas de coherencia relacional

## 8.1 Fortalezas

- buena parte de los seeders actuales respetan dependencias
- varios seeders abortan con `warn()` si faltan prerequisitos
- hay cuidado razonable con comerciales ligados a usuarios

## 8.2 Debilidades

### CR1. Faltan cadenas relacionales completas entre módulos

Ejemplos:

- CRM -> oferta -> pedido
- pedido -> incidencia
- ruta -> stop -> pedido/cliente/prospecto
- producción -> inputs -> outputs -> consumptions -> costes

### CR2. Muchos hijos dependen de datasets poco ricos de sus padres

Ejemplos:

- pedidos con líneas sí, pero sin vida posterior rica
- palets y cajas con poca evolución relacional
- recepciones y despachos sin suficiente ecosistema alrededor

### CR3. Se mezclan datos normales y datos demo extrema sin una taxonomía clara

- el timeline de palets es claramente demo/UI y está metido en la misma cadena general del tenant
- esto debería vivir en una capa de escenarios opcionales

### CR4. PalletSeeder no enlaza palets a recepciones de materia prima

### Hallazgo comprobado

El modelo `Pallet` tiene el campo `raw_material_reception_id` (FK a `raw_material_receptions`). Sin embargo, `PalletSeeder` crea 20 palets con vinculación opcional solo a pedidos — nunca a recepciones.

Esto rompe el flujo central del ERP:

```
Recepción materia prima → Palé → Stock → Pedido/Producción
```

En dev, los 10 registros de `RawMaterialReception` existen y sus líneas de producto también, pero no hay ningún palé que los trace como origen. El flujo de trazabilidad "¿de qué recepción viene este palé?" es funcionalmente indemostrable en el entorno de desarrollo actual.

**Recomendación concreta:** Al menos 3-5 palets del `PalletSeeder` deberían tener `raw_material_reception_id` apuntando a recepciones existentes para que el flujo de trazabilidad sea navegable.

---

## 9. Problemas de diseño técnico del sistema de seeders/factories

## D1. Falta una arquitectura por capas del sistema de datos

Hoy el sistema no está claramente dividido entre:

- bootstrap central
- bootstrap tenant
- catálogos
- maestros
- transacciones
- históricos
- edge cases
- demo visual

## D2. Los seeders son la unidad principal, no las factories

Esto hace que:

- la composición sea peor
- los tests reutilicen menos
- la expansión de escenarios sea más costosa

## D3. `DatabaseSeeder` central no representa el producto SaaS real

En un proyecto multi-tenant, el seeder central debería poder dejar listo al menos:

- superadmin
- flags
- tenant demo
- tenant suspendido
- tenant con plan distinto

Hoy no lo hace.

## D4. Los escenarios de UI/demo están mezclados con la cadena base

Ejemplo claro:

- `PalletTimelineSeeder`

No debería ejecutarse como parte del flujo base de datos operativos normales sin una categoría explícita de “demo/frontend”.

## D5. Ya existe contrato técnico de volumen, pero aún falta expandir su uso

### Hallazgo actualizado

Esta carencia ya no es total. El proyecto ya tiene estrategia formal en código para:

- dataset `base`
- dataset `extended`
- dataset `edge`

mediante:

- `TenantDatabaseSeeder`
- `TenantExtendedDatasetSeeder`
- `TenantEdgeDatasetSeeder`
- `TenantVolumeExpansionSeeder`
- `TenantEdgeCasesSeeder`
- `--dataset=base|extended|edge` en comandos tenant

### Pendiente real

Lo que sigue faltando es:

- mover más suites de test a estos niveles de dataset
- seguir afinando volúmenes por dominio
- introducir más edge cases específicos por permisos y flujos raros

## D6. Uso directo de `$this->command` sin nullsafe — seeders incompatibles con PHPUnit

### Hallazgo comprobado

Varios seeders usan `$this->command->warn()`, `$this->command->line()` o `$this->command->info()` directamente (sin verificar si `$this->command` es null). Cuando un seeder se ejecuta desde PHPUnit — sin un contexto de consola activo — `$this->command` es `null` y la llamada lanza un error.

Esta es la causa concreta documentada por la que `StockBlockApiTest.php` evita `ProductSeeder` y crea el producto manualmente:

```php
// En el test: "ProductSeeder uses $this->command in PHPUnit"
$product = Product::create([...]);
```

**Solución técnica concreta (PHP 8, una línea de cambio por seeder):**

```php
// Antes (rompe en PHPUnit):
$this->command->warn("Faltan prerequisitos: SpeciesSeeder");

// Después (nullsafe operator, PHP 8):
$this->command?->warn("Faltan prerequisitos: SpeciesSeeder");
```

Esta corrección es trivial, no cambia el comportamiento en consola y elimina la incompatibilidad con PHPUnit. Sin ella, los seeders con esta dependencia no son reutilizables en tests.

**Seeders con este patrón a corregir:** verificar todos los seeders que usen `$this->command->` y aplicar nullsafe (`?->`).

---

## 10. Propuesta de estructura ideal

## 10.1 Principio general

Reorganizar el sistema en **capas explícitas** donde los seeders orquesten factories y escenarios, y no al revés.

## 10.2 Estructura recomendada

### Capa A. Seeders centrales de entorno

- `CentralDevBootstrapSeeder`
- `SuperadminDevSeeder`
- `FeatureFlagsDevSeeder`
- `DemoTenantsSeeder`
- `TenantOverridesDevSeeder`

### Capa B. Seeders tenant base

- `TenantBaseCatalogSeeder`
- `TenantBaseActorsSeeder`
- `TenantBaseSettingsSeeder`
- `TenantBaseLogisticsSeeder`

### Capa C. Seeders tenant operativos estándar

- `TenantSalesDevSeeder`
- `TenantInventoryDevSeeder`
- `TenantReceptionsDevSeeder`
- `TenantDispatchesDevSeeder`
- `TenantWorkforceDevSeeder`

### Capa D. Seeders avanzados por dominio

- `TenantCrmDevSeeder` `HECHO`
- `TenantRoutesDevSeeder` `HECHO`
- `TenantProductionDevSeeder` `HECHO`
- `TenantIncidentsLabelsSeeder` `HECHO PARCIAL`
- `TenantExtendedDatasetSeeder` `HECHO`
- `TenantEdgeDatasetSeeder` `HECHO`

### Capa E. Seeders de edge cases / QA

- `TenantEdgeCasesSeeder` `HECHO`
- `TenantPermissionsScenariosSeeder` `PENDIENTE`
- `TenantDataAnomaliesSeeder` `PENDIENTE`

### Capa F. Seeders de demo visual / frontend

- `TenantDemoUiSeeder`
- aquí vivirían timelines, datos sintéticos visuales o escenarios narrativos

## 10.3 Rol de los orquestadores

### `DatabaseSeeder`

Debe pasar a orquestar el entorno central dev.

### `TenantDatabaseSeeder`

Debe convertirse en un orquestador corto de seed tenant estándar.

### `TenantProductionSeeder`

Debe mantenerse minimalista y aislado de cualquier dato demo.

---

## 11. Propuesta de factories enriquecidas

## 11.1 Prioridad alta

Crear o rehacer estas factories primero:

- `CustomerFactory`
- `SalespersonFactory`
- `SupplierFactory`
- `PaymentTermFactory`
- `TransportFactory`
- `IncotermFactory`
- `TaxFactory`
- `ExternalUserFactory`
- `FieldOperatorFactory`
- `EmployeeFactory`
- `PunchEventFactory`
- `OrderFactory`
- `OrderPlannedProductDetailFactory`
- `RawMaterialReceptionFactory`
- `RawMaterialReceptionProductFactory`
- `CeboDispatchFactory`
- `CeboDispatchProductFactory`

## 11.2 Prioridad media-alta

- `ProspectFactory`
- `ProspectContactFactory`
- `CommercialInteractionFactory`
- `AgendaActionFactory`
- `OfferFactory`
- `OfferLineFactory`
- `DeliveryRouteFactory`
- `RouteTemplateFactory`
- `RouteStopFactory`
- `RouteTemplateStopFactory`

## 11.3 Prioridad media

- `ProductionFactory`
- `ProductionRecordFactory`
- `ProductionInputFactory`
- `ProductionOutputFactory`
- `ProductionOutputConsumptionFactory`
- `ProductionCostFactory`
- `CostCatalogFactory`
- `IncidentFactory`
- `SettingFactory`

## 11.4 States requeridos

Cada factory importante debería exponer states explícitos, por ejemplo:

### `OrderFactory`

- `pending()`
- `finished()`
- `incident()`
- `cancelled()`
- `withLines()`
- `withRoute()`
- `withIncident()`

### `ProspectFactory`

- `new()`
- `contacted()`
- `qualified()`
- `won()`
- `lost()`
- `withPrimaryContact()`
- `withPendingAction()`

### `DeliveryRouteFactory`

- `planned()`
- `inProgress()`
- `completed()`
- `cancelled()`
- `forCommercial()`
- `forFieldOperator()`

### `ProductionFactory`

- `open()`
- `closed()`
- `withParentChildRecords()`
- `withOutputs()`
- `withConsumptions()`

### `ExternalUserFactory`

- `active()`
- `inactive()`
- `maquilador()`

---

## 12. Recomendaciones concretas por módulo

## 12.1 Central / SaaS

- Integrar `SuperadminUserSeeder` y `FeatureFlagSeeder` en un flujo central claro
- Crear tenants demo con estados distintos:
  - activo
  - suspendido
  - plan básico/pro/enterprise
- Integrar overrides por tenant en entorno dev

## 12.2 Usuarios y permisos

- Sembrar usuarios de todos los roles reales
- Incluir activos e inactivos
- Incluir relaciones cruzadas:
  - usuario comercial con salesperson
  - repartidor con field operator
  - usuarios externos activos e inactivos

## 12.3 Catálogos y maestros

- Mantener el enfoque actual, pero mover lógica repetible a factories
- Añadir más variantes de catálogos si condicionan filtros o combinaciones reales

## 12.4 Clientes y pedidos

- Aumentar volumen
- Añadir clientes sin alias, con múltiples emails, con distintos países y transportes
- Añadir pedidos con:
  - distintos estados
  - distintas fechas
  - distintas densidades de líneas
  - vínculos a rutas/incidencias cuando aplique

## 12.5 Stock

- Añadir escenarios de stock más variados:
  - interno
  - externo
  - posicionado
  - no posicionado
  - asociado a pedidos
  - usado en producción

## 12.6 Recepciones y despachos

- Añadir más variedad temporal
- Añadir líneas con lotes/precios/declarados cuando proceda
- Añadir combinaciones por proveedor y tipo de exportación

## 12.7 CRM

- `[HECHO]` Crear un seeder completo del bloque:
  - `TenantCrmDevSeeder`
  - `TenantCrmProspectsSeeder`
  - `TenantCrmAgendaSeeder`
  - `TenantCrmOffersSeeder`
- `[HECHO]` Sembrar:
  - prospectos de distintos orígenes
  - contactos primarios/secundarios
  - interacciones
  - agenda pendiente/completada/reprogramada/cancelada
  - conversión a cliente
  - ownership por comercial
- `[PENDIENTE]` ampliar:
  - volumen
  - escenarios extremos
  - dataset demo más profundo para dashboard y filtros

## 12.8 Rutas

- `[HECHO]` Sembrar plantillas y rutas reales mediante:
  - `TenantRoutesDevSeeder`
  - `TenantRouteTemplatesSeeder`
  - `TenantDeliveryRoutesSeeder`
- `[HECHO]` Incluir stops de distintos tipos y resultados
- `[HECHO]` Añadir ownership por comercial y por field operator
- `[HECHO]` Añadir helper reusable `BuildsRouteScenario` para tests
- `[PENDIENTE]` ampliar:
  - volumen extremo
  - escenarios más ricos ligados a pedidos operativos
  - migración de más tests a helpers/factories de rutas

## 12.9 Producción

- `[HECHO]` Crear dataset base de producción mediante:
  - `TenantProductionDevSeeder`
  - `TenantProductionCostsSeeder`
  - `TenantProductionAdvancedSeeder`
- `[HECHO]` Sembrar:
  - producciones abiertas y cerradas
  - records raíz/hijos
  - inputs desde cajas
  - outputs intermedios y finales
  - consumptions
  - `sources`
  - costes por lote y por proceso
- `[HECHO]` Añadir helper reusable `BuildsProductionScenario` para tests
- `[PENDIENTE]` ampliar:
  - más variedades de procesos
  - más profundidad de árbol
  - más casos de merma y rentabilidad

## 12.10 Incidencias, ofertas y etiquetas

- `[HECHO PARCIAL]` ofertas ya tienen seeding real dentro del bloque CRM
- `[HECHO]` incidencias y etiquetas ya tienen seeding base dentro de `TenantIncidentsLabelsSeeder`
- `[PENDIENTE]` ampliar volumen y escenarios funcionales más extremos
- Son módulos presentes en el código y deberían tener representación dev real

---

## 13. Quick wins

1. `[HECHO]` Integrar `CalibersSeeder` en la capa tenant base.
2. `[HECHO]` Integrar `FeatureFlagSeeder` + `DevTenantFeatureOverridesSeeder` + `SuperadminUserSeeder` en el bootstrap central.
3. `[HECHO PARCIAL]` Aplicar nullsafe operator en seeders sensibles a PHPUnit.
4. `[HECHO]` Dar valores dev razonables a los settings de empresa mediante `TenantCompanySettingsSeeder`.
5. `[HECHO]` Vincular parte de los palets a recepciones en `PalletSeeder`.
6. `[HECHO]` Sembrar `PalletBox`.
7. `[HECHO]` Rehacer `DatabaseSeeder` central para dejar listo el entorno SaaS básico.
8. `[HECHO]` Convertir `TenantDatabaseSeeder` en orquestador corto por dominios.
9. `[HECHO]` Crear factories base de `Customer`, `Salesperson`, `Order`, `Supplier`, `RawMaterialReception`.
10. `[HECHO]` CRM, rutas y producción ya implementados con `TenantCrmDevSeeder`, `TenantRoutesDevSeeder` y `TenantProductionDevSeeder`.
11. `[HECHO]` Mover `PalletTimelineSeeder` a una capa explícita de demo/UI opcional.
12. `[HECHO]` Introducir niveles de volumen (`base`, `extended`, `edge`) con soporte en comandos tenant.
13. `[HECHO PARCIAL]` Crear helpers reutilizables de test basados en factories, no en `create()` disperso.

Los quick wins 1-9 ya están absorbidos en la implementación parcial actual.

---

## 14. Riesgos o precauciones

## 14.1 No mezclar onboarding con demo

`TenantProductionSeeder` debe mantenerse:

- pequeño
- idempotente
- seguro
- sin datos operativos falsos

## 14.2 No meter edge cases en el seed base sin control

Los edge cases deben estar separados, porque si no:

- ensucian demos
- hacen más difícil depurar
- generan falsos positivos funcionales

## 14.3 No intentar resolver todo solo con seeders gigantes

Si no se apoya en factories/state builders:

- volverá a aparecer duplicación
- crecerá la complejidad
- empeorará la mantenibilidad

## 14.4 No dejar el bootstrap central incompleto

En este proyecto, un entorno dev “real” exige datos:

- centrales
- tenant
- superadmin
- flags

No solo tablas tenant.

---

## 15. Plan de implementación por fases

## Fase 1. Arquitectura del sistema `HECHA`

- redefinir `DatabaseSeeder`
- convertir `TenantDatabaseSeeder` en orquestador por dominios
- preservar `TenantProductionSeeder`
- crear taxonomía de capas

## Fase 2. Factories base `HECHA`

- clientes
- comerciales
- proveedores
- pedidos
- líneas
- recepciones
- despachos
- actores operativos

## Fase 3. Seed tenant estándar `HECHA`

- catálogos
- actores
- settings
- maestros
- operativa básica

## Fase 4. Módulos infra-sembrados `HECHA`

- CRM `HECHO`
- rutas `HECHO`
- ofertas `HECHO`
- producción `HECHO`
- incidencias `HECHO BASE`
- costes `HECHO BASE`
- etiquetas `HECHO BASE`

## Fase 5. Escenarios avanzados `HECHA PARCIAL`

- históricos `HECHO BASE`
- edge cases `HECHO BASE`
- demo UI `HECHO`
- permisos y visibilidad `HECHO BASE`

## Fase 6. Consolidación de tests `HECHA PARCIAL`

- `BuildsCrmScenario` `HECHO`
- `BuildsRouteScenario` `HECHO`
- `BuildsProductionScenario` `HECHO`
- `TenantSeedDatasetSupportTest` `HECHO`
- migración masiva del resto de suites legacy `PENDIENTE`

---

## 16. Dataset final objetivo

El sistema final debería dejar un entorno dev con los siguientes volúmenes objetivo por entidad:

### 16.1 Capa central (DatabaseSeeder)

| Entidad | Volumen objetivo | Estado/variantes | Estado actual | Icono | Justificación |
|---------|-----------------|-----------------|---------------|-------|---------------|
| Superadmin users | 1 | activo | `Implementado` | ✅ | Bootstrap mínimo del panel superadmin |
| Tenants | 3–4 | 2 activos (plan pro/enterprise), 1 suspendido, 1 plan basic | `Implementado parcial` | 🟡 | Probar lógica SaaS multiplan y estados |
| Feature flags | 11 (todos) | por plan | `Implementado` | ✅ | Base del sistema de feature flags |
| Tenant overrides | 3–5 | flags activados/desactivados manualmente | `Implementado base` | 🟡 | Probar overrides específicos por tenant |

### 16.2 Catálogos tenant (base, idempotente)

| Entidad | Volumen objetivo | Estado/variantes | Estado actual | Icono | Justificación |
|---------|-----------------|-----------------|---------------|-------|---------------|
| ProductCategory | 2 | Fresco / Congelado | `Implementado` | ✅ | Filtros en listado de productos |
| ProductFamily | 8 | Entero/Eviscerado/Fileteado × Fresco/Congelado + 2 procesados | `Implementado` | ✅ | Variantes de producto realistas |
| Species | 8–10 | con nombre científico y FAO | `Implementado` | ✅ | Nomenclatura pesquera completa |
| CaptureZone | 5–6 | Zonas FAO reales | `Implementado` | ✅ | Trazabilidad de origen |
| FishingGear | 5 | Arrastre, Cerco, Palangre, Nasas, Almadraba | `Implementado` | ✅ | Catálogo estándar |
| Process | 8 | Recepción, Clasificación, Eviscerado, Limpieza, Fileteado, Envasado, Congelación, Retail | `Implementado` | ✅ | Flujo producción completo |
| Country | 8–10 | UE + Marruecos | `Implementado` | ✅ | Clientes/proveedores internacionales |
| PaymentTerm | 5 | Contado, 30d, 60d, 90d, Transferencia | `Implementado` | ✅ | Variantes en pedidos y clientes |
| Incoterm | 7 | EXW, FCA, CPT, CIP, DAP, DPU, DDP | `Implementado` | ✅ | Comercio internacional sector pesquero |
| Tax | 4 | IVA 21%, 10%, 4%, Exento | `Implementado` | ✅ | Variantes en líneas de pedido |
| FAOZone | 10 | Zonas principales + subzonas | `Implementado` | ✅ | Trazabilidad geográfica |
| Caliber | 12 | Pulpo T1-T4, Calamar U5-U15, Sepia 1-2/2-4/4-6/6+ | `Implementado` | ✅ | Clasificación comercial de producto |

### 16.3 Actores y maestros tenant

| Entidad | Volumen objetivo | Estado/variantes | Estado actual | Icono | Justificación |
|---------|-----------------|-----------------|---------------|-------|---------------|
| Users (internos) | 10–12 | Admin×1, Técnico×1, Comercial×2, Operario×5, Repartidor×1, Almacenero×1 | `Implementado base` | 🟡 | Cobertura de todos los roles con permisos distintos |
| Salesperson | 4–6 | con/sin user_id, activo/inactivo | `Implementado base` | 🟡 | Probar permisos comercial (ve solo sus clientes/pedidos) |
| FieldOperator | 2 | con user_id | `Implementado` | ✅ | Rol autoventa/repartidor operativo |
| ExternalUser | 3 | 2 maquiladores activos, 1 inactivo | `Implementado base` | 🟡 | Almacenes externos y permisos |
| Employee | 10 | todos con NFC excepto 1 | `Implementado base` | 🟡 | Dashboard fichajes con variedad |
| Supplier | 12–15 | facilcom/a3erp, con/sin contacto | `Implementado base` | 🟡 | Filtros por tipo exportación |
| Store (interno) | 6 | distintas temperaturas y capacidades | `Implementado base` | 🟡 | Inventario multialmacén |
| Store (externo) | 4 | 2 por maquilador | `Implementado base` | 🟡 | Stock externo navegable |
| Customer | 25–30 | con/sin alias, distintos países/transportes/pagos, por comercial distinto | `Implementado parcial` | 🟡 | Listas y filtros de clientes ricos |
| Product | 30–35 | con GTIN, distintas familias/especies/calibres, facilcom/a3erp | `Implementado base` | 🟡 | Catálogo completo y filtrable |
| Transport | 5–6 | distintas empresas | `Implementado` | ✅ | Variedad en pedidos |

### 16.4 Módulo ventas

| Entidad | Volumen objetivo | Estado/variantes | Estado actual | Icono | Justificación |
|---------|-----------------|-----------------|---------------|-------|---------------|
| Order | 60–80 | 50% pending, 35% finished, 15% incident; distribución 3 meses | `Implementado parcial` | 🟡 | Paginación real, filtros por estado/fecha/comercial |
| OrderPlannedProductDetail | 120–200 | 1–4 líneas por pedido, con/sin tax | `Implementado parcial` | 🟡 | Totales y desglose de pedidos |
| Incident | 8–12 | abierta/resuelta; vinculadas a pedidos incident | `Implementado base` | 🟡 | Módulo incidencias operativo |
| Offer | 10–15 | vinculadas a prospecto o cliente; con/sin pedido resultante | `Implementado base` | 🟡 | Flujo CRM→Oferta→Pedido navegable |
| OfferLine | 25–40 | 1–3 líneas por oferta | `Implementado base` | 🟡 | Detalle de ofertas |

### 16.5 Módulo inventario y stock

| Entidad | Volumen objetivo | Estado/variantes | Estado actual | Icono | Justificación |
|---------|-----------------|-----------------|---------------|-------|---------------|
| Box | 80–100 | con lot, GS1-128, pesos realistas | `Implementado base` | 🟡 | Stock suficiente para filtros y paginación |
| Pallet | 30–40 | registered/stored/shipped/processed; 5 enlazados a recepción | `Implementado parcial` | 🟡 | Flujo trazabilidad completo |
| PalletBox | 100–150 | distribución cajas entre palets | `Implementado base` | 🟡 | Trazabilidad caja-palé imprescindible |
| StoredPallet | 20–25 | distintos almacenes y posiciones | `Implementado base` | 🟡 | Módulo almacenamiento operativo |
| OrderPallet | 15–20 | palets shipped vinculados a pedidos | `Implementado base` | 🟡 | Ciclo pedido-stock |

### 16.6 Materia prima y despachos

| Entidad | Volumen objetivo | Estado/variantes | Estado actual | Icono | Justificación |
|---------|-----------------|-----------------|---------------|-------|---------------|
| RawMaterialReception | 20–25 | distribución 3–4 meses, distintos proveedores | `Implementado parcial` | 🟡 | Histórico de recepciones visible en listados |
| RawMaterialReceptionProduct | 50–70 | 2–4 líneas por recepción | `Implementado parcial` | 🟡 | Detalle de recepciones |
| CeboDispatch | 15–20 | facilcom/a3erp, distintos proveedores | `Implementado parcial` | 🟡 | Historial de despachos bait |
| CeboDispatchProduct | 40–60 | 2–3 líneas por despacho | `Implementado parcial` | 🟡 | Detalle de despachos |

### 16.7 Módulo producción avanzada

| Entidad | Volumen objetivo | Estado/variantes | Estado actual | Icono | Justificación |
|---------|-----------------|-----------------|---------------|-------|---------------|
| Production | 5–8 | abierta/cerrada; distintas especies y zonas | `Implementado base` | 🟡 | Listados de producción navegables |
| ProductionRecord | 15–25 | raíz y nodos hijo; distintos procesos | `Implementado base` | 🟡 | Árbol de producción completo |
| ProductionInput | 20–30 | desde cajas existentes | `Implementado base` | 🟡 | Trazabilidad inputs reales |
| ProductionOutput | 15–25 | cajas de salida con pesos | `Implementado base` | 🟡 | Outputs reales con trazabilidad |
| CostCatalog | 6–8 | mano de obra, energía, packaging, transporte, frío, varios | `Implementado` | ✅ | Costes asociables a producciones |
| ProductionCost | 15–20 | distribuidos entre producciones | `Implementado base` | 🟡 | Rentabilidad calculable |

### 16.8 Módulo CRM

| Entidad | Volumen objetivo | Estado/variantes | Estado actual | Icono | Justificación |
|---------|-----------------|-----------------|---------------|-------|---------------|
| Prospect | 15–20 | new/contacted/qualified/won/lost; por comercial distinto | `Implementado base` | 🟡 | Pipeline CRM navegable |
| ProspectContact | 20–30 | 1–2 por prospecto; con/sin contacto primario | `Implementado base` | 🟡 | Agenda de contactos real |
| CommercialInteraction | 25–35 | call/meeting/email; distintos resultados | `Implementado base` | 🟡 | Historial de interacciones |
| AgendaAction | 15–20 | pending/completed/cancelled | `Implementado base` | 🟡 | Agenda de seguimiento activa |

### 16.9 Módulo rutas

| Entidad | Volumen objetivo | Estado/variantes | Estado actual | Icono | Justificación |
|---------|-----------------|-----------------|---------------|-------|---------------|
| RouteTemplate | 3–4 | por comercial y repartidor | `Implementado base` | 🟡 | Base de planificación |
| RouteTemplateStop | 10–15 | clientes y prospectos mezclados | `Implementado base` | 🟡 | Plantilla real de ruta |
| DeliveryRoute | 8–12 | planned/in_progress/completed/cancelled | `Implementado base` | 🟡 | Historial de rutas operativas |
| RouteStop | 25–40 | pending/completed/skipped; con `result_type` delivery/visit/autoventa/incident/no_contact y algunos pedidos enlazados | `Implementado base` | 🟡 | Ejecución de ruta detallada |

### 16.10 Módulo fichajes

| Entidad | Volumen objetivo | Estado/variantes | Estado actual | Icono | Justificación |
|---------|-----------------|-----------------|---------------|-------|---------------|
| PunchEvent | 100–120 | IN/OUT alternados; 7–10 días; NFC y manual; 1–2 anomalías (doble IN, OUT sin IN) | `Implementado parcial` | 🟡 | Dashboard de fichajes con variedad real |

### 16.11 Miscelánea

| Entidad | Volumen objetivo | Estado/variantes | Estado actual | Icono | Justificación |
|---------|-----------------|-----------------|---------------|-------|---------------|
| Label | 10–15 | formatos distintos | `Implementado base` | 🟡 | Módulo etiquetas funcional |
| Setting | 20 | settings empresa con valores realistas | `Implementado` | ✅ | Demo de configuración sin campos vacíos |

---

## 17. Conclusión final

El proyecto ya tiene una base valiosa de seeders tenant y una separación correcta entre demo y onboarding, pero todavía **no dispone de un sistema de datos de desarrollo completo, componible y mantenible** a la altura del backend que realmente existe.

La principal limitación no es que “falten algunos seeders”, sino que el sistema actual:

- cubre solo una parte del dominio
- depende demasiado de seeders manuales frente a factories
- no tiene un bootstrap central SaaS sólido
- no representa todavía la vida completa del sistema

La dirección correcta es clara:

1. **mantener** la separación entre onboarding y desarrollo
2. **reconstruir** el sistema alrededor de factories + orquestadores por dominio
3. **añadir** cobertura real para CRM, rutas, producción, ofertas, incidencias y costes
4. **formalizar** capas de volumen y edge cases
5. **alinear** tests, seeders y factories para que el entorno dev sea verdaderamente reusable

Con ese rediseño, el backend podrá tener por fin un entorno de desarrollo que no solo “arranque”, sino que **se sienta realmente vivo**.
