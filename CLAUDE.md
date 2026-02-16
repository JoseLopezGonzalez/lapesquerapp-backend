# PesquerApp Backend — Contexto para Claude Code

Este archivo es leído por el agente de IA al inicio de cada sesión. Describe el proyecto de forma completa y específica.

---

## 1. Identidad del Proyecto

- **Nombre**: PesquerApp (backend Laravel del ERP).
- **Descripción**: Sistema ERP multi-tenant para cooperativas pesqueras y empresas de procesamiento y comercialización de productos del mar.
- **Industria**: Pesca, recepción de materia prima, producción (fileteado, congelado, enlatado), inventario (cajas, palets, almacenes), ventas (pedidos, clientes, comerciales), despachos de cebo, fichajes y catálogos del sector (especies, caladeros, artes de pesca, incoterms, etc.).
- **Stack**: Laravel 10, PHP 8.2+, MySQL 8.0; frontend Next.js 16 consumiendo API REST v2.
- **Objetivo actual**: **CORE v1.0 Consolidation** — consolidar todos los bloques funcionales con Rating ≥ 9/10 (escala 1–10 del workflow de evolución), eliminando deuda técnica y asegurando lógica de negocio coherente, sin construir desde cero.

---

## 2. Arquitectura Multi-Tenant

- **Un tenant = una empresa = una base de datos MySQL**. La base central (`mysql`) solo contiene la tabla `tenants` (catálogo de empresas); no hay datos de negocio en la central.
- **Conexión dinámica**: El middleware `TenantMiddleware` lee el header `X-Tenant` (subdominio), busca el tenant activo en la base central, asigna `config(['database.connections.tenant.database' => $tenant->database])`, hace `DB::purge('tenant')` y `DB::reconnect('tenant')`, y guarda el tenant en `app()->instance('currentTenant', $subdomain)`.
- **Modelos de negocio**: Usan el trait `UsesTenantConnection`, que fija `$this->setConnection('tenant')`, de modo que todo Eloquent use la BD del tenant actual.
- **Validaciones**: Reglas que comprueban existencia en tablas del tenant deben usar `exists:tenant.{table},columna` (ej. `exists:tenant.products,id`).
- **Seguridad**: NUNCA hacer queries cross-tenant. Evitar `DB::connection('tenant')->table(...)` sin encapsular en modelo o servicio; preferir Eloquent para mantener un solo punto de configuración de conexión. Si en el futuro se usan Jobs, el payload debe incluir identificador de tenant y configurar la conexión al arrancar el job.

Documentación: `docs/fundamentos/01-Arquitectura-Multi-Tenant.md`.

---

## 3. Modelos de Dominio Principales

- **Ventas**: Order, OrderPlannedProductDetail, Incident, Customer, Salesperson.
- **Inventario**: Store, Pallet, Box, PalletBox, StoredPallet, StoredBox.
- **Materia prima**: RawMaterialReception, RawMaterialReceptionProduct; CeboDispatch, CeboDispatchProduct, Cebo.
- **Producción**: Production, ProductionRecord, ProductionInput, ProductionOutput, ProductionOutputConsumption, Process, CostCatalog, ProductionCost.
- **Productos y maestros**: Product, ProductCategory, ProductFamily, Species, CaptureZone.
- **Catálogos**: Transport, Incoterm, PaymentTerm, Country, Tax, FishingGear.
- **Proveedores**: Supplier; liquidaciones (SupplierLiquidation).
- **Etiquetas y fichajes**: Label; PunchEvent, Employee.
- **Sistema y auth**: User, Role, ActivityLog, Session, MagicLinkToken; Tenant (en conexión central). Configuración por tenant: tabla `settings` (key-value); existe modelo Setting y SettingService.

Relaciones y propósito de cada entidad se detallan en código y en `docs/audits/findings/domain-model-review.md`.

---

## 4. Terminología Pesquera

- **Caladero / CaptureZone**: Zona de pesca.
- **Zona FAO**: Clasificación geográfica de pesca (atributo en productos/especies).
- **Calibre**: Tamaño/comercialización del producto.
- **Cebo**: Materia prima (pescado) para procesamiento; despachos de cebo = salidas de materia prima.
- **Estados de pedido (Order)**: `pending`, `finished`, `incident`; transiciones y reglas en el modelo Order (p. ej. `markAsIncident()`, `markAsFinished()`).
- **Estados de palet**: p. ej. `stored`; lógica en modelo Pallet.
- Documentar siempre estados y transiciones en STEP 0 del workflow antes de refactorizar.

---

## 5. Estructura de Carpetas (app/)

- **Http/Controllers/v2**: Controladores de la API v2; deben ser thin (< 200 líneas), solo orquestación: Request → authorize (Policy) → Service/Action → Response.
- **Http/Requests/v2**: Form Requests para validación y autorización (authorize en el Request o en el controller con Policy).
- **Http/Resources/v2**: API Resources para serialización JSON.
- **Policies**: Autorización por modelo (view, create, update, delete); registrar en AuthServiceProvider y usar `authorize()` en controladores.
- **Services** (y **Services/Production**): Lógica de negocio compleja, listados filtrados, reportes, escrituras transaccionales (producción, recepciones, despachos, PDF, correo, estadísticas).
- **Models**: Eloquent con `UsesTenantConnection` en modelos de negocio.
- **Traits**: p. ej. UsesTenantConnection.
- **Enums, Helpers**: Donde el proyecto los use de forma consistente.

No se usa capa Repository ni DDD estricto; identidad: **Laravel idiomático + capa de servicios** para dominios complejos.

---

## 6. Convenciones de Código

- **Controllers**: Thin; sin lógica de negocio ni consultas complejas ni `DB::connection()` directo en el controller; delegar en Services o Actions.
- **Form Requests**: Validación + autorización (authorize) en operaciones críticas; mensajes en español donde aplique; reglas tenant con `exists:tenant.*`.
- **Policies**: viewAny, view, create, update, delete por modelo; delete a menudo restringido a administrador y tecnico; usar `authorize('action', $model)` en el controller.
- **Services**: Lógica de aplicación, listados con filtros (ListService), dashboards (DashboardService), escrituras con transacciones.
- **Tests**: Feature tests para endpoints; usar trait/Setup que configure conexión tenant (ConfiguresTenantConnection o equivalente); objetivo de cobertura ≥ 80% en bloques críticos.
- **Transacciones**: `DB::transaction()` en operaciones que tocan varias tablas (producción, recepciones, despachos, palets, fichajes, etc.).
- **Nomenclatura**: Consistente con Laravel (plural recursos, camelCase métodos, nombres descriptivos).

---

## 7. Reglas de Negocio Críticas

- **Pedidos**: Estados `pending` | `finished` | `incident`; transiciones documentadas en modelo; impacto en stock y totales debe ser coherente.
- **Stock**: Cálculo y validación según almacenes, palets y cajas; no permitir incoherencias (p. ej. stock negativo donde no esté permitido).
- **Trazabilidad**: Producción con trazabilidad a nivel de caja; inputs/outputs/consumptions en ProductionRecord.
- **Código de barras**: GS1-128 donde aplique (etiquetas, cajas).
- **Permisos**: Por rol (middleware) y por recurso (Policies); no dejar recursos críticos solo protegidos por rol.
- **Multi-tenant**: Aislamiento estricto; validaciones `exists:tenant.{table}`; ningún dato de un tenant en otro.

---

## 8. Estado Actual del CORE v1.0

| Bloque | Rating | Estado |
|--------|--------|--------|
| A.1 Auth + Roles/Permisos | 9/10 | ✅ |
| A.2 Ventas | 8,5/10 | 🔄 En progreso |
| A.3 Inventario / Stock | 10/10 | ✅ |
| A.4 Recepciones Materia Prima | 9/10 | ✅ |
| A.5 Despachos de Cebo | 9/10 | ✅ |
| A.6 Producción | 9/10 | ✅ |
| A.7 Productos | 9/10 | ✅ |
| A.8 Catálogos | — | ⏳ Pendiente |
| A.9 Proveedores | — | ⏳ Pendiente |
| A.10 Etiquetas | 9/10 | ✅ |
| A.11 Fichajes | 9/10 | ✅ |
| A.12 Estadísticas | — | ⏳ |
| A.13 Configuración (Settings) | 9/10 | ✅ |
| A.14 Sistema | 9/10 | ✅ (comparte A.1) |

Detalle por bloque y sub-bloques en `docs/core-consolidation-plan-erp-saas.md` (ANEXO A) y en `docs/audits/laravel-evolution-log.md`.

---

## 9. Stack Tecnológico

- **Backend**: Laravel 10, PHP 8.2+, MySQL 8.0.
- **Frontend**: Next.js 16, Node.js.
- **Infra**: Docker, Coolify; despliegue en IONOS VPS.
- **Calidad**: Pint (estilo), PHPStan (análisis estático), Pest/PHPUnit para tests.

---

## 10. Estrategia de Testing

- Framework: PHPUnit/Pest; Feature tests para endpoints API v2.
- Tenant en tests: Configurar conexión tenant (trait o helper que fije BD de prueba por tenant); no mezclar datos entre tenants en tests.
- Cobertura objetivo: ≥ 80% en bloques críticos; tests de integración para flujos completos (auth + tenant + al menos un CRUD por dominio).
- Bloques con tests existentes: Auth (AuthBlockApiTest), Productos (ProductosBlockApiTest), Stock (StockBlockApiTest), Settings (SettingsBlockApiTest), Fichajes (FichajesBlockApiTest), Label (LabelApiTest).

---

## 11. Deployment

- IONOS VPS; Docker Compose; Coolify para gestión.
- Estrategia de backups documentada y probada para bases tenant y central.

---

## 12. API REST v2 Design

- **Base path**: `/api/v2/`.
- **Headers**: `X-Tenant` (obligatorio), `Authorization` (Bearer token, Sanctum).
- **Paginación**: Parámetro estándar (p. ej. `per_page`); convenciones documentadas en auditoría.
- **Filtrado y búsqueda**: Por query params; criterios coherentes por recurso (evitar fragmentación de contratos).
- **Error handling**: Handler unificado; respuestas JSON con `message`, `userMessage`, `errors`; ValidationException y QueryException traducidas a mensajes útiles.
- **Rate limiting**: Throttling en login, magic link, OTP y rutas sensibles.

---

## 13. Workflows Principales

- **Pedido**: Creación → confirmación → preparación → envío/incidencia; estados y transiciones en Order.
- **Recepción de materia prima**: RawMaterialReception con productos; importaciones (Facilcom, A3ERP); bulk update de datos declarados.
- **Producción**: Production → ProductionRecord (árbol); inputs, outputs, consumptions; costes (ProductionCost, CostCatalog); procesos (Process).
- **Despacho**: CeboDispatch con productos; estadísticas y exportaciones.
- **Inventario**: Stores, Pallets, Boxes; asignación a posición, movimiento entre almacenes, vinculación a pedidos.
- **Etiquetas**: Label CRUD; duplicado de etiqueta.
- **Fichajes**: PunchEvent (entrada/salida); empleados; dashboard, estadísticas, calendario; store público NFC y manual; bulk validate/store y destroyMultiple.

---

## 14. Integraciones Externas

- n8n para flujos de documentos.
- OpenAI (GPT) para clasificación cuando aplique.
- Webhooks si están definidos en el proyecto.

---

## 15. Problemas Conocidos y Decisiones Arquitectónicas

- **Multi-tenant con DB separada**: Elección explícita para aislamiento fuerte y cumplimiento; trade-off en operaciones multi-tenant (migrate/seed por tenant).
- **Autorización**: Policies registradas para varios modelos; uso de `authorize()` extendido en muchos controladores; en el pasado solo UserController usaba Policy — evolución en curso.
- **DB::connection('tenant')->table()**: Reducir uso; preferir Eloquent o servicios que encapsulen el acceso (ej. Setting con modelo).
- **Controladores gruesos**: OrderController y PunchController han sido o están siendo refactorizados (servicios de listado, dashboard, calendar, statistics); objetivo < 200 líneas.
- **Tests**: Cobertura desigual; prioridad en flujos críticos y en nuevos bloques.
- **Deuda técnica**: Documentada en `docs/audits/laravel-backend-global-audit.md` y en el evolution log por bloque.

---

## 16. Performance y Escalabilidad

- **N+1**: Uso de `with()` en listados (Order con customer, salesperson, transport, etc.); revisar nuevos listados y reportes.
- **Índices**: Índices en filtros habituales; compuestos (tenant implícito por conexión, fecha, estado) en tablas grandes (orders, punch_events, production_*).
- **Conexión tenant**: Una conexión por request; purge/reconnect en cada request; en alto tráfico valorar pooling o caché de config por tenant.

---

## 17. Seguridad

- **Autenticación**: Sanctum (API tokens); magic link y OTP con throttling.
- **Autorización**: Policies por modelo + middleware por rol; no dejar recursos sensibles solo con rol.
- **Validación**: Form Requests en operaciones críticas; no exponer secretos (ej. GET settings enmascara contraseña).
- **CORS, CSRF**: Configurados según entorno.
- **Auditoría**: ActivityLog; logs con contexto tenant y usuario cuando aplique.

---

## 18. Workflow de Evolución (para Claude Code)

1. **STEP 0a**: Scope & Entity Mapping — listar entidades y artefactos del bloque; confirmar con usuario.
2. **STEP 0**: Documentar comportamiento de negocio actual (estados, transiciones, impacto en stock, permisos).
3. **STEP 1**: Análisis — rating ANTES, componentes estructurales, deuda, riesgos.
4. **STEP 2**: Cambios propuestos (sin código); aprobación explícita.
5. **STEP 3**: Implementación (preservar comportamiento; multi-tenant seguro).
6. **STEP 4**: Validación — rating DESPUÉS; tests y verificación manual.
7. **STEP 5**: Entrada en `docs/audits/laravel-evolution-log.md` (formato definido en el prompt).

**Rating**: 1–10 (1–2 crítico, 3–4 pobre, 5–6 aceptable, 7–8 bueno, 9–10 excelente). No dar por cerrado un módulo hasta Rating ≥ 9 o bloqueo por decisión de negocio. Si Rating después < 9, documentar "Gap to 10/10" y proponer siguiente sub-bloque.

Documento maestro: `docs/prompts/01_Laravel incremental evolution prompt.md`.

---

## 19. Referencias Importantes

- **Plan CORE**: `docs/core-consolidation-plan-erp-saas.md`
- **Workflow evolución**: `docs/prompts/01_Laravel incremental evolution prompt.md`
- **Auditoría global**: `docs/audits/laravel-backend-global-audit.md`
- **Evolution log**: `docs/audits/laravel-evolution-log.md`
- **Arquitectura multi-tenant**: `docs/fundamentos/01-Arquitectura-Multi-Tenant.md`
- **Hallazgos**: `docs/audits/findings/` (domain-model-review, multi-tenancy-analysis, structural-components-usage, security-concerns, integration-patterns)

---

**Última actualización**: 2026-02-15. Mantener este archivo alineado con el estado real del CORE y con las convenciones aplicadas en el evolution log.
