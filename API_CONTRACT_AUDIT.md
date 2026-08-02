# Auditoría del contrato API del backend de La Pesquerapp

**Fecha**: 2026-08-02
**Alcance**: Solo lectura/inspección estática. No se ha modificado ningún archivo del repositorio salvo la creación de este informe. No se han instalado paquetes, ejecutado migraciones ni cambiado configuración.
**Muestra analizada**: `composer.json`/`composer.lock`, `routes/api.php` completo (670 líneas, 394 llamadas `Route::`), `config/scribe.php`, `.scribe/`, `public/docs/` (salida generada de Scribe), 93 controladores (90 en `v2`, 2 en `Public`, 1 base), 60 API Resources en `app/Http/Resources/v2`, 242 Form Requests en `app/Http/Requests/v2`, 80 clases de `app/Services`, `app/Exceptions/Handler.php`, `app/Http/Middleware/TenantMiddleware.php`, `app/Providers/AuthServiceProvider.php`, 46 Feature tests, y los hallazgos previos ya documentados en `docs/audits/findings/` (siete documentos existentes, referenciados y contrastados, no simplemente copiados).

---

## 1. Resumen ejecutivo

- **Estado general**: El backend tiene una capa de API Resources real y relativamente extendida (60 clases), un sistema de errores centralizado razonable, y — hallazgo no anticipado por el brief — **Scribe ya está instalado, configurado y genera un OpenAPI 3.1 funcional** (`public/docs/openapi.yaml`, `.scribe/`). Esto cambia el punto de partida: no se trata de introducir OpenAPI desde cero, sino de **consolidar y corregir una integración ya iniciada pero muy superficial**.
- **Nivel de madurez del contrato API**: **Bajo-medio**. Existe una capa de Resources, pero el 32% de los controladores (29/90) la evita en al menos una acción, y — más grave — **39 modelos Eloquent implementan un método `toArrayAssoc()` manual** que sustituye a Resources anidados para relaciones cargadas. Esto significa que el "contrato" real de gran parte de la API vive en métodos de modelo, no en clases introspectables por herramientas de generación automática.
- **Nivel de riesgo actual de descoordinación**: **Alto**. Hay evidencia concreta y reproducible de que la misma entidad se serializa de forma distinta según el endpoint (ver §7), de que un mismo endpoint puede devolver dos formas de respuesta distintas según un query param (`GET /v2/orders?active=true` vs sin ese parámetro, ver §6), y de que la documentación OpenAPI ya generada está desactualizada frente a rutas añadidas recientemente (exportación marítima, añadida el 2026-07-29/31, tres días antes de esta auditoría).
- **Viabilidad de introducir OpenAPI**: **Viable como evolución, no como generación mecánica inmediata**. La infraestructura (Scribe) ya está; lo que falta es disciplina de anotación y, sobre todo, resolver el patrón `toArrayAssoc()` antes de confiar en el spec generado.
- **Herramienta que preliminarmente parece más compatible**: **Scribe**, porque ya está instalado, configurado con conocimiento específico del dominio (header `X-Tenant`, esquema Bearer, exclusión de `/api/health`) y tiene un test (`ApiDocumentationTest`) que ya lo ejercita. Scramble se evalúa en detalle en §11 pero partiría de cero.
- **Principales bloqueos**: (1) el patrón `toArrayAssoc()` en 39 modelos es invisible para cualquier generador automático de esquemas; (2) el retorno condicional `Collection|LengthAwarePaginator` en ~10 servicios de listado; (3) ausencia total de CI/CD (no existe `.github/` ni pipeline alguno), por lo que nada impide que el spec generado quede desactualizado silenciosamente, como ya ha ocurrido.

---

## 2. Tecnologías y arquitectura detectadas

| Área | Estado detectado | Archivos relevantes | Observaciones |
|---|---|---|---|
| Framework | Laravel `^10.10` | `composer.json` | Coincide con CLAUDE.md. |
| PHP | `^8.1` en `composer.json` (`require.php`) | `composer.json` | CLAUDE.md indica "PHP 8.2+"; el `composer.json` real solo exige `^8.1`. Discrepancia menor a resolver antes de fijar el target de Scribe/Scramble (ambos pueden usar atributos PHP 8 que requieren 8.1+, así que no bloquea, pero conviene alinear el dato). |
| Autenticación | Laravel Sanctum `^3.3` (Bearer tokens) + flujo propio de magic-link/OTP | `routes/api.php:216-226`, `app/Http/Controllers/v2/AuthController.php` | Confirmado; no hay JWT activo pese a que `tymon/jwt-auth` aparece mencionado como posible residuo en `docs/audits/findings/security-concerns.md` (Riesgo SC6) — no se ha verificado de nuevo en esta auditoría, se hereda el hallazgo. |
| Versionado de API | Prefijo `v2` casi universal | `routes/api.php:214` (`Route::group(['prefix'=>'v2', ...])`) | No existe `v1` en el código actual (no hay carpeta `Controllers/v1` ni `Resources/v1`); el versionado es solo un prefijo de URL, no negociación de contenido. Un bloque comentado de rutas v2 "antiguas" sigue en el archivo (líneas 111-118, comentado, no registrado). |
| Multi-tenant | Header `X-Tenant` + middleware dedicado | `app/Http/Middleware/TenantMiddleware.php` | Resuelve tenant por subdominio vía header, cachea 300s (`Cache::remember`), purga/reconecta la conexión `tenant`. Devuelve 400/404/403 con cuerpos JSON distintos entre sí (`error` vs `error+userMessage+status`) — relevante para OpenAPI de errores (§6). |
| OpenAPI/Swagger | **Scribe `^5.9`** (require-dev) ya instalado y configurado | `composer.json`, `config/scribe.php`, `.scribe/`, `public/docs/openapi.yaml` | Ver §7-8 en detalle. Hallazgo central de esta auditoría. |
| Scramble | No instalado | `composer.json`, `composer.lock` | Sin rastro alguno (`grep -i scramble` sin resultados). |
| DTOs / Data objects | No existen | `find app -type d -iname "*dto*|*data*|*action*|*transform*"` sin resultados | El proyecto usa `Services` estáticos (`OrderListService::list()`, etc.) como capa de aplicación, consistente con CLAUDE.md ("no DDD estricto"). |
| Validación | Laravel Form Requests | `app/Http/Requests/v2/` (242 clases) | Extendido y consistente como patrón (ver §5). |
| Enums (PHP 8.1 backed enums) | Prácticamente inexistentes | `app/Enums/Role.php` (único archivo) | Los campos categóricos (`status`, `order_type`, `tenant.status`, etc.) son strings validados con `in:` en el Form Request, no `enum` casts de Eloquent ni PHP enums. Esto limita mucho lo que Scramble puede inferir automáticamente sobre valores permitidos. |
| Contract testing | No existe | — | `tests/Feature/ApiDocumentationTest.php` verifica que Scribe genera el spec y que contiene ciertas cadenas (`X-Tenant`, `Bearer`), pero no valida forma/tipos de payloads reales. |
| Generación de TypeScript | No existe en este repo (backend) | — | Fuera de alcance del backend; no hay script `openapi-typescript` ni similar aquí. |
| CI/CD | **No existe** | `find .github` → vacío; no hay `.gitlab-ci.yml` | Ningún gate automático ejecuta tests, Pint, ni `scribe:generate`. |
| Análisis estático | No hay Larastan/PHPStan como dependencia directa | `composer.json` (ausente); aparece solo como dependencia transitiva en `composer.lock` (arrastrada por otro paquete) | No hay `phpstan.neon` en la raíz. |
| Estilo de código | Laravel Pint `^1.0` (dev) | `composer.json` | No se encontró `pint.json`; usa configuración por defecto de Laravel. Sin evidencia de que se ejecute en ningún gate. |

---

## 3. Inventario resumido de la API

Inventario basado en el recuento de llamadas `Route::` en `routes/api.php` (394 registros; el número de endpoints únicos es ligeramente inferior porque algunas rutas registran tanto `PATCH` como `PUT` para la misma URI, p. ej. `orders/{order}/maritime-containers/{container}`).

| Módulo | Nº aprox. de endpoints | Versión | Patrón de respuesta dominante | Nivel de consistencia |
|---|---|---|---|---|
| Pedidos (`orders`, incidencias, adjuntos, líneas auxiliares, exportación marítima, PDFs, Excel) | ~67 rutas bajo `orders*` | v2 | Mixto: `OrderResource`/`OrderDetailsResource` en CRUD; `response()->json(['message'=>..,'data'=>Resource])` en store/update; descargas binarias en PDF/Excel; `toArrayAssoc()` crudo en incidencias | Bajo — mismo recurso con 3+ formas distintas (ver §7) |
| Superadmin (tenants, impersonación, seguridad, migraciones, observabilidad, feature flags) | ~27 rutas bajo `v2/superadmin/*` | v2 (fuera de `TenantMiddleware`) | Controladores dedicados en `Http/Controllers/v2/Superadmin`, sin Resources en varios (no auditado en profundidad, fuera del foco de negocio pesquero) | No evaluado a fondo; **crítico para §11 (no debe exponerse en spec público)** |
| Palets (`pallets`, adjuntos, timeline, expedición) | ~17 rutas | v2 | `PalletResource`; algunas acciones bulk devuelven `response()->json()` ad hoc | Medio |
| Estadísticas (`statistics/*`) | ~15 rutas | v2 | Arrays construidos a mano en `*StatisticsController`, sin Resource | Bajo (no hay contrato tipado; cada endpoint define su propia forma) |
| Producción (`productions`, `production-records`, `production-inputs`, `production-outputs`, `production-output-consumptions`, `cost-catalog`, `production-costs`) | ~34 rutas combinadas | v2 | Buen uso de Resources dedicados (`ProductionResource`, `ProductionRecordResource`, etc.) | Medio-alto, el módulo mejor cubierto por Resources |
| Almacenes (`stores`) | ~9 rutas | v2 | `StoreResource`/`StoreDetailsResource` | Medio |
| Liquidaciones de proveedor (`supplier-liquidations`) | ~8 rutas | v2 | Mezcla de `SupplierLiquidationResource` y descargas PDF | Medio |
| Fichajes (`punches`) | ~8 rutas | v2 | `PunchEventResource` + acciones bulk con `response()->json()` propio | Medio |
| Recepciones de materia prima (`raw-material-receptions`) | ~7 rutas + exports Excel | v2 | `RawMaterialReceptionResource` | Medio |
| Autenticación (`auth/*`, `login`, `me`) | ~7 rutas | v2 | `response()->json()` ad hoc (sin Resource, son tokens/estado de sesión) | Aceptable para este tipo de endpoint |
| Clientes (`customers`) | ~6 rutas + `/interactions`, `/order-history` | v2 | `CustomerResource` (delega en `toArrayAssoc()`, ver §7) | Bajo por el patrón `toArrayAssoc` |
| Despachos de cebo (`cebo-dispatches`) | ~5 rutas + exports | v2 | `CeboDispatchResource` | Medio |
| Usuarios externos / CRM (`external-users`, `prospects`, `offers`, `crm/*`) | ~20+ rutas | v2 | Mixto, con controladores CRM (`CrmDashboardController`, `CrmAgendaController`) devolviendo arrays manuales | Bajo — módulo señalado como "en revisión" en CLAUDE.md (Rating 8.5/10) |
| Canal de campo / Autoventa (`v2/field/*`) | ~10 rutas | v2 (rol `repartidor_autoventa`) | Resources paralelos y distintos de los internos (`FieldOrderResource` ≠ `OrderResource`, `FieldOrderDetailsResource`) | Bajo — contrato duplicado y divergente para las mismas entidades de negocio (pedidos, clientes, productos) |
| Catálogos varios (species, incoterms, payment-terms, countries, taxes, fishing-gears, capture-zones, transports, process, product-categories/families) | ~11 apiResources CRUD estándar | v2 | Resources dedicados, patrón CRUD homogéneo | Alto — es el bloque más uniforme de toda la API |

**Total aproximado**: ~380-394 endpoints únicos en `v2`, repartidos en 90 controladores. No se ha ejecutado `php artisan route:list` (requeriría arrancar la aplicación con conexión a BD); el recuento se basa en inspección estática de `routes/api.php`, que es el único archivo de rutas de API del proyecto (`routes/web.php` y `routes/channels.php` no contienen API REST).

---

## 4. Cómo se construyen actualmente las respuestas

Se identifican **cinco patrones de construcción de respuesta** coexistiendo en el mismo proyecto, a veces dentro del mismo controlador:

1. **JsonResource / ResourceCollection "puro"** — el patrón más cercano al ideal de Laravel.
   Ejemplo: `app/Http/Controllers/v2/OrderController.php:35` → `return OrderResource::collection(OrderListService::list($request));`

2. **JsonResource envuelto manualmente en un sobre `{message, data}`** — usado sobre todo en `store`/`update` de escritura transaccional.
   Ejemplo: `app/Http/Controllers/v2/OrderController.php:70-74`:
   ```php
   return response()->json([
       'message' => 'Pedido creado correctamente.',
       'data' => new OrderDetailsResource($order),
   ], 201);
   ```
   Esto **no es el mismo sobre** que el `index()` del mismo controlador (que no envuelve en `data` de forma explícita, sino que dispara el auto-wrap de Laravel `ResourceCollection`). El resultado son dos convenciones de "dónde está el payload" en el mismo recurso.

3. **Modelo Eloquent devuelto directamente** (sin Resource, sin transformación).
   Ejemplo: `app/Http/Controllers/v2/IncidentController.php:27` → `return response()->json($order->incident);` — expone las columnas de BD tal cual (snake_case, incluidos posibles campos internos no pensados para el cliente), mientras que el `store()` del mismo controlador (línea ~50) devuelve `$incident->toArrayAssoc()` (camelCase, subconjunto curado de campos).

4. **Método `toArrayAssoc()` / `toArrayAssocShort()` / `toArrayAssocV2()` definido en el modelo Eloquent** — el patrón más extendido y más problemático para generación automática de contrato. Presente en **39 modelos** (`grep -rl "toArrayAssoc" app/Models | wc -l` → 39), entre ellos `Customer`, `Pallet`, `Product`, `User`, `Order` (indirectamente, vía relaciones), `Store`, `Box`, etc. Ejemplo: `app/Models/Customer.php:87-114`. Estos métodos no son introspectables por PHPDoc ni por tipos de retorno (`toArray()`/`toArrayAssoc()` sin tipo de retorno declarado ni `@return array{...}` estructurado), por lo que ni Scribe ni Scramble pueden derivar su forma sin ejecutar código real.

5. **Arrays construidos a mano en el Servicio o el Controlador**, típico de estadísticas y `options()`.
   Ejemplo: `app/Http/Controllers/v2/FieldCustomerController.php:24-38`.

**Descargas/streams**: PDFs (`PDFController`, 15+ acciones) y Excel (`ExcelController`) devuelven binarios (`snappdf`/`phpspreadsheet`/`browsershot`), fuera del alcance de un esquema JSON — deberían marcarse como `application/pdf` / `application/vnd.openxmlformats-...` explícitamente en el spec, cosa que Scribe no infiere sin anotación.

**Respuestas paginadas**: al menos 10 servicios (`OrderListService`, `CustomerListService`, `ProductListService`, `UserListService`, `CeboDispatchListService`, `SalespersonListService`, `ProductCategoryListService`, `ProductFamilyListService`, `ProspectCategoryListService`, `ProspectService`) usan `->paginate($perPage)`, delegando en el sobre estándar de Laravel (`data`, `links`, `meta`). Pero **`OrderListService::list()` (`app/Services/v2/OrderListService.php:35-60`) tiene tipo de retorno `Collection|LengthAwarePaginator`**: cuando la request trae `active=true`, devuelve una `Collection` plana (`$query->get()`, sin paginar); en caso contrario, pagina. El mismo endpoint `GET /v2/orders` puede devolver, por tanto, un array JSON plano o el sobre `{data, links, meta}` según un query param — un caso concreto y grave de contrato no determinista.

**Estimación de uniformidad** (basada en la muestra de 90 controladores, no en el 100% del código):
- ~68% de los controladores (61/90) usan al menos un JsonResource en su acción principal de lectura.
- ~32% (29/90) no usan ningún Resource en ninguna acción (`grep` sin coincidencias de "Resource" en el archivo completo) — mayoritariamente estadísticas, PDF/Excel, CRM y utilidades.
- De los controladores que sí usan Resources, una proporción no cuantificada con precisión pero **alta** (evidenciada en `Order*`, `Customer`, `Pallet`) delega la serialización de relaciones anidadas en `toArrayAssoc()` de modelo en lugar de Resources anidados — esto no se puede automatizar de forma fiable sin refactor.

No se dispone de datos para afirmar un porcentaje exacto global; esta es una estimación basada en la muestra inspeccionada, no un recuento exhaustivo de las 394 rutas.

---

## 5. Estado de API Resources, DTOs y Form Requests

**API Resources** (`app/Http/Resources/v2/`, 60 clases):
- Existe una Resource dedicada por entidad principal (`OrderResource`, `CustomerResource`, `PalletResource`, `ProductResource`, etc.), lo cual es una buena base.
- **Reutilización problemática**: `CustomerResource::toArray()` (`app/Http/Resources/v2/CustomerResource.php:15-17`) hace literalmente `return parent::toArrayAssoc();` — un método que no existe en `JsonResource` ni en su cadena de herencia. Esto solo funciona porque `Illuminate\Http\Resources\Json\JsonResource` implementa `__call()`, que reenvía llamadas a métodos no definidos hacia `$this->resource` (el modelo Eloquent subyacente); PHP resuelve `parent::metodoInexistente()` disparando el `__call` del objeto actual. Es un patrón válido en tiempo de ejecución pero **totalmente opaco para cualquier herramienta de análisis estático o generación de OpenAPI** — Scramble/Scribe verán una Resource sin campos.
- **Distintas Resources para distintos contextos** sí existen para `Order` (`OrderResource` para listado, `OrderDetailsResource` para detalle, `ActiveOrderCardResource` para el tablero de pedidos activos) y para el canal de campo (`FieldOrderResource` vs `OrderResource`) — la intención de segmentar por contexto está presente, pero la implementación diverge de forma no documentada campo a campo (comparar `OrderResource::toArray()` en `app/Http/Resources/v2/OrderResource.php:14-38` contra `FieldOrderResource::toArray()` en `app/Http/Resources/v2/FieldOrderResource.php:11-30`: `FieldOrderResource` omite `salesperson`, `transport`, `incoterm`, `subtotalAmount`, `totalAmount` — decisión de negocio razonable para el rol repartidor, pero sin ninguna nota que lo explique como contrato intencional).
- **Presencia condicional de campos vía `relationLoaded()`**: patrón extremadamente extendido (decenas de apariciones en `OrderResource`, `OrderDetailsResource`, `CustomerResource`/`toArrayAssoc`, etc.). Ejemplo: `app/Http/Resources/v2/OrderResource.php:20` → `'customer' => $this->relationLoaded('customer') ? $this->customer?->toArrayAssoc() : null`. Esto significa que **el mismo `OrderResource` puede devolver `customer: {...}` o `customer: null` para el mismo pedido**, dependiendo exclusivamente de si el controlador precargó la relación — un contrato no determinista que ninguna herramienta de inferencia estática puede capturar correctamente (verá el campo como nullable siempre, perdiendo la semántica real: "null solo si no se pidió expandir").
- **N+1 potenciales**: no se ha auditado sistemáticamente cada Resource, pero el patrón `relationLoaded()` en sí es una mitigación consciente de N+1 (evita lazy-load accidental devolviendo `null` en vez de disparar una query), lo cual es correcto desde el punto de vista de rendimiento pero agrava el problema de contrato no determinista.

**Form Requests** (`app/Http/Requests/v2/`, 242 clases):
- Patrón consistente: una clase por acción (`StoreOrderRequest`, `UpdateOrderRequest`, `IndexOrderRequest`, etc.), con `authorize()` y `rules()` — alineado con CLAUDE.md.
- **Solo 1 de 242** (`StoreOrderRequest.php`) contiene anotaciones Scribe (`@bodyParam`) — ver `app/Http/Requests/v2/StoreOrderRequest.php:9-23`. Es, con alta probabilidad, una prueba de concepto que no se generalizó al resto del proyecto.
- Las reglas usan nombres de campo en camelCase (`orderType`, `customer`, `entryDate`, `buyerReference`) que **no coinciden con las columnas de BD en snake_case** (`order_type`, `customer_id`, `entry_date`) — la traducción ocurre en los Services (`OrderStoreService`, no auditado línea a línea). Esto es coherente puertas afuera (contrato camelCase consistente) pero significa que Scribe/Scramble, al inferir tipos desde `rules()`, obtendrán los nombres correctos (camelCase) pero **ningún tipo más allá de lo que la regla de validación exprese literalmente** (`'customer' => 'required|integer|exists:tenant.customers,id'` se traduce razonablemente a `integer`, pero reglas condicionales complejas — como el bloque `if ($isAutoventa)` en `StoreOrderRequest::rules()` — no se infieren sin anotación manual).
- Validaciones tenant-aware (`exists:tenant.customers,id`) son omnipresentes y coherentes con la arquitectura documentada en CLAUDE.md §2.

**DTOs / Transformers / Actions**: no existen como capa formal. La aplicación usa `Services` estáticos como capa de lógica de aplicación (ej. `OrderStoreService::store()`, `OrderListService::list()`), consistente con la decisión arquitectónica declarada en CLAUDE.md ("Laravel idiomático + capa de servicios", sin DDD estricto).

**¿Podría Scramble/Scribe inferir esta información correctamente?**
- **Sí, con buena fidelidad**: reglas de validación simples (`required`, `integer`, `string`, `in:...`, `exists:...`), estructura de URL, middleware de autenticación.
- **Necesita PHPDoc/anotación explícita**: reglas condicionales (autoventa vs standard vs maritime_export), forma exacta de la respuesta cuando no hay Resource, distinción entre `null` "campo vacío" y `null` "relación no cargada".
- **No puede inferir en absoluto sin refactor previo**: cualquier campo servido vía `toArrayAssoc()`/`toArrayAssocShort()`/`toArrayAssocV2()`, porque estos métodos no están anotados con tipos de retorno estructurados y Scribe no ejecuta código de modelo arbitrario para descubrir su forma (solo lo hace para Resources, vía "response calls" configuradas en `config/scribe.php:230-239`, y solo sobre rutas `GET`).

---

## 6. Inconsistencias y problemas encontrados

| Severidad | Hallazgo | Evidencia | Impacto | Alcance |
|---|---|---|---|---|
| **Crítico** | `GET /v2/orders` devuelve una forma de respuesta distinta (`Collection` plana vs `LengthAwarePaginator` paginado) según el query param `active` | `app/Services/v2/OrderListService.php:35` (firma `Collection\|LengthAwarePaginator`), lógica de branching en líneas ~40-60 | Un cliente TypeScript generado desde un único esquema de respuesta para este endpoint será incorrecto en una de las dos ramas; imposible de modelar con un tipo OpenAPI simple sin `oneOf` + discriminador manual | 1 endpoint de alto tráfico (pedidos activos, usado en "Order Manager" según comentario en el propio código) |
| **Crítico** | El mismo recurso (`Incident`) se serializa de forma distinta según el endpoint: modelo Eloquent crudo en `GET`, `toArrayAssoc()` en `POST`/`PUT` | `app/Http/Controllers/v2/IncidentController.php:27` (`show`) vs líneas ~50 y ~74 (`store`/`update`) | Un tipo TypeScript generado para "Incident" a partir de un solo endpoint no servirá para el otro; el `GET` expone además columnas internas de BD (snake_case) no pensadas para consumo externo | 1 entidad, 3 endpoints (`GET`/`POST`/`PUT /orders/{orderId}/incident`) |
| **Alto** | 39 modelos Eloquent serializan relaciones vía `toArrayAssoc()` propio en lugar de Resources anidados, invisible para generación automática de esquema | `grep -rl toArrayAssoc app/Models` → 39 archivos; ejemplo `app/Models/Customer.php:87-114` | Cualquier generador de OpenAPI (Scribe incluido) que dependa de reflexión de Resources verá objetos anidados como `object` genérico sin propiedades, salvo que se ejecuten "response calls" reales contra la BD | Transversal — afecta a Order, Customer, Pallet, Product, Store, Box, User, Transport y ~30 modelos más |
| **Alto** | `CustomerResource::toArray()` delega en `parent::toArrayAssoc()`, un método inexistente en la jerarquía de `JsonResource`, resuelto solo en tiempo de ejecución vía `__call` mágico | `app/Http/Resources/v2/CustomerResource.php:15-17` | Ilegible para análisis estático; frágil ante refactors (renombrar o mover `toArrayAssoc()` en `Customer` rompe la Resource sin que ningún IDE ni PHPStan lo detecten, al no existir análisis estático configurado) | 1 Resource, pero patrón que probablemente se repita (no verificado en las 60 Resources una a una) |
| **Alto** | Presencia condicional de campos de relación según `relationLoaded()`, sin distinguir en el contrato entre "no aplica" y "no se pidió cargar" | `app/Http/Resources/v2/OrderResource.php:20-29` (patrón repetido en ~8 campos de la misma Resource) | Un consumidor no puede confiar en la ausencia de un campo como señal de negocio; los tipos generados marcarán todo como `nullable` perdiendo semántica | Extendido — mismo patrón en `OrderDetailsResource`, `CustomerResource`/`toArrayAssoc`, `PalletResource`, etc. |
| **Alto** | Rutas de superadmin (`v2/superadmin/*`, ~27 endpoints) registradas **fuera** del grupo `TenantMiddleware`, en el mismo archivo de rutas que el resto de la API de negocio | `routes/api.php:128-207` (bloque `Route::prefix('v2/superadmin')`) vs `routes/api.php:214` (grupo tenant) | Riesgo de exposición si un futuro `scribe:generate` no excluye explícitamente este prefijo del spec público (ver §11) | 27 rutas administrativas de alto privilegio |
| **Medio** | Inconsistencia de nombre de parámetro de paginación: `perPage` (76 ocurrencias) vs `per_page` (19 ocurrencias) en el código | `grep -rl "'perPage'" app/Services app/Http` → 76; `grep -rl "'per_page'"` → 19 | Un cliente generado no puede asumir un único nombre de parámetro de paginación para toda la API sin revisar servicio por servicio | Transversal, ~10-15 servicios de listado |
| **Medio** | `perPage` sin límite superior consistente en varios servicios (ya documentado como riesgo de seguridad, ver §14) | `docs/audits/findings/security-concerns.md`, Riesgo SC4 | Además de seguridad, implica que el spec no puede declarar un `maximum` fiable para ese parámetro en todos los endpoints | ~12 servicios según el hallazgo previo (heredado, no re-verificado exhaustivamente en esta auditoría) |
| **Medio** | Sobres de respuesta (`{message, data}` vs Resource/Collection directa vs array plano) coexisten dentro del mismo controlador | `app/Http/Controllers/v2/OrderController.php`: `index()` (línea 35, sin sobre explícito) vs `store()`/`update()` (líneas 70-74, 111-114, con sobre `{message, data}`) | Un cliente no puede asumir una única forma de "dónde está el payload" ni siquiera dentro de un mismo recurso | Extendido, no cuantificado con precisión — visible en Order, y probablemente en otros CRUD con patrón similar (Pallet, Production) |
| **Medio** | Errores de dominio con distintas claves según el controlador: `{message, userMessage}` (Handler global) vs `{message, details, userMessage}` (`OrderController::destroy`) vs `{message, userMessage, error}` (catch genérico en `OrderController::store`) | `app/Exceptions/Handler.php` (formato base) vs `app/Http/Controllers/v2/OrderController.php:130-141` y `:78-83` | Un esquema de error único en OpenAPI (`components/schemas/Error`) sería incompleto o demasiado laxo (`additionalProperties: true`) para cubrir todas las variantes reales | Al menos 3 formas de error distintas confirmadas en un único controlador; no se ha auditado el resto |
| **Bajo** | `composer.json` exige PHP `^8.1`, CLAUDE.md documenta "PHP 8.2+" | `composer.json` vs `CLAUDE.md` §1 | No bloquea Scribe/Scramble (ambos funcionan en 8.1+), pero es una fuente de confusión documental a resolver antes de fijar el target del pipeline de generación | Documental |
| **Bajo** | `public/docs/openapi.yaml` generado está en `.gitignore` (`.gitignore:28`), por lo que no se comparte entre desarrolladores ni con el frontend salvo que alguien lo genere localmente y lo copie manualmente | `.gitignore:28` (`/public/docs/`), `.gitignore` línea "Scribe (cache/temporal)" para `.scribe/` | El spec ya generado (fechado por sistema de archivos en torno al 23 de febrero) no incluye endpoints añadidos después, como la exportación marítima (commits del 29-31 de julio) — confirmado indirectamente por la distancia temporal entre la última generación local conocida y los commits recientes a `routes/api.php` | Todo el spec, en tanto no se regenere y publique de forma sistemática |

---

## 7. Posibles desfases que ya pueden afectar al frontend

- **`GET /v2/orders?active=true`** devuelve un array JSON plano de pedidos; **`GET /v2/orders`** (sin ese parámetro) devuelve el sobre de paginación de Laravel (`{data: [...], links: {...}, meta: {...}}`). Si el frontend tiene un único tipo `OrdersListResponse` generado a partir de una sola observación del endpoint, fallará en la otra rama. Evidencia: `app/Services/v2/OrderListService.php:35-60`.
- **El campo `customer` en `OrderResource`** puede ser un objeto completo (`{id, name, alias, vatNumber, ...}` vía `toArrayAssoc()`) o `null`, dependiendo de si el controlador que invoca `OrderResource::collection(...)` precargó la relación `customer`. Un frontend que asuma "si `customer` es `null`, el pedido no tiene cliente asignado" puede tomar decisiones de negocio incorrectas cuando en realidad el campo es `null` solo porque esa vista concreta no cargó la relación. Evidencia: `app/Http/Resources/v2/OrderResource.php:20`.
- **`GET /v2/orders/{orderId}/incident` vs el resto de verbos del mismo endpoint**: el frontend que parsee la respuesta de `GET` (modelo Eloquent crudo, columnas `order_id`, `created_at` en snake_case tal cual las guarda MySQL) y reutilice el mismo tipo para `POST`/`PUT` (que devuelven `toArrayAssoc()`, camelCase) tendrá un mismatch de nombres de campo entre el "leer" y el "escribir" de la misma entidad. Evidencia: `app/Http/Controllers/v2/IncidentController.php:27` vs `:50`/`:74`.
- **`FieldOrderResource` vs `OrderResource`** representan el mismo concepto de negocio ("pedido") con campos distintos (el primero omite comercial, transporte, incoterm e importes). Si en el futuro se comparte código de tipos entre el cliente móvil (canal de campo) y el panel interno, no puede asumirse que ambos "Order" sean el mismo tipo. Evidencia: comparación directa entre `app/Http/Resources/v2/OrderResource.php` y `app/Http/Resources/v2/FieldOrderResource.php`.
- **Nombres de parámetro de paginación inconsistentes** (`perPage` vs `per_page`) obligan al frontend a saber, endpoint a endpoint, cuál usar — no es inferible de forma genérica ni siquiera con un cliente autogenerado, salvo que el spec lo documente endpoint por endpoint (lo cual Scribe sí podría hacer si las Form Requests de índice tuvieran `@queryParam`, cosa que hoy no ocurre en casi ninguna).

---

## 8. Documentación y OpenAPI existentes

- **Scribe `^5.9`** está en `composer.json` (`require-dev`), con configuración propia y ya adaptada al dominio en `config/scribe.php`:
  - Excluye `GET /api/health` del spec (línea 58).
  - Inyecta automáticamente el header `X-Tenant: demo-tenant` en todos los ejemplos (líneas 215-219) — decisión correcta y ya alineada con la arquitectura multi-tenant.
  - Configura autenticación Bearer con placeholder (`AuthIn::BEARER`, líneas 115-123).
  - Genera **OpenAPI 3.1.0** y colección Postman simultáneamente (`postman.enabled` y `openapi.enabled`, ambos `true`).
  - Usa `ResponseCalls` (ejecuta las rutas `GET *` reales contra la app) como estrategia de inferencia de respuestas (líneas 230-239) — esto explica por qué, pese al patrón `toArrayAssoc()`, el spec generado localmente puede contener *algo* de forma real para los `GET`, aunque sin garantía de que las relaciones estén precargadas igual que en producción.
- **Salida generada** existe en `public/docs/`: `openapi.yaml` (441 KB), `index.html` (2.8 MB, documentación HTML estática), `collection.json` (Postman, 628 KB). **Ninguno de estos archivos está versionado en git** (`.gitignore:28` excluye `/public/docs/`, y `.scribe/` — el caché de extracción — también está ignorado). Esto significa que el spec existente es un artefacto local de quien lo generó, no una fuente de verdad compartida ni reproducible por CI (que no existe).
- **Cobertura real de anotación manual**: solo `app/Http/Requests/v2/StoreOrderRequest.php` tiene `@bodyParam`, y ningún controlador salvo posiblemente uno tiene `@group`/`@response` explícitos (`grep -rl "@group|@response|@authenticated" app/Http/Controllers/v2` → 1 coincidencia). Esto implica que el spec generado hoy es, en su inmensa mayoría, **inferencia automática de bajo nivel** (nombre de ruta, reglas de validación, tipos de respuesta vía `ResponseCalls`), sin descripciones de negocio, ejemplos curados ni documentación de los distintos `orderType` posibles, estados, etc.
- **¿Está en CI?** No. No existe pipeline que ejecute `scribe:generate` automáticamente ni que falle el build si el spec queda desactualizado.
- **¿Cubre todas las rutas?** Configurado para incluir todo bajo `api/*` (`config/scribe.php:45`) excepto `/api/health`, así que en términos de *cobertura de rutas* el `match` es correcto. El problema no es cobertura de rutas sino **calidad y actualidad** de lo documentado para cada una.
- **¿Sería reutilizable?** Sí, como punto de partida técnico (la config ya resuelve el problema no trivial del header de tenant), pero **no como fuente de verdad inmediata** sin antes: (a) comprometer el output a git o a un pipeline reproducible, (b) resolver el patrón `toArrayAssoc()`, (c) generalizar las anotaciones desde el único ejemplo existente (`StoreOrderRequest`) al resto de 241 Form Requests relevantes.
- **Test existente**: `tests/Feature/ApiDocumentationTest.php` ejecuta `scribe:generate` dentro de un test PHPUnit y verifica que el archivo se genera y contiene ciertas cadenas. Es un buen punto de partida para "contract smoke test", pero no verifica forma ni tipos de los payloads — solo que el proceso de generación no crashea y que el header de tenant/autenticación aparecen mencionados en el YAML.

---

## 9. Estado de los tests de API

- 46 Feature tests en `tests/Feature/`, cubriendo bloques nombrados de forma consistente con CLAUDE.md §10 (`OrderApiTest`, `ProductosBlockApiTest`, `SettingsBlockApiTest`, `FichajesBlockApiTest`, `LabelApiTest`, `AuthBlockApiTest`, etc.), más tests de seguridad (`DynamicCorsTest`, `SuperadminFeatureSecurityTest`) y de permisos (`CustomerCommercialPermissionsApiTest`).
- Patrón típico (`OrderApiTest.php`): usa `RefreshDatabase`, trait propio `ConfiguresTenantConnection` y `BuildsOperationsScenario` para construir un tenant + usuario admin de prueba, con `ensureDatabaseReachable()` como guarda de entorno — sugiere que los tests están pensados para saltarse si no hay BD disponible, buena práctica defensiva.
- **`ApiDocumentationTest.php`** es el único test directamente relacionado con el contrato OpenAPI (ver §8).
- **No se ha ejecutado la suite** en esta auditoría. Motivo: `phpunit.xml` fuerza `DB_DATABASE=testing` (distinta de `pesquerapp`, la BD configurada en `.env` para desarrollo), lo cual en principio aísla los tests de datos reales; sin embargo, los tests usan `RefreshDatabase` (que trunca/migra la base de datos objetivo) y no hay certeza, sin ejecutar y observar, de que la base `testing` sea desechable en este entorno concreto ni de que exista ya configurada. Ejecutar la suite entra en conflicto con la restricción explícita de esta auditoría de "no modificar bases de datos persistentes" si el entorno no está ya aislado de forma verificada. Por prudencia, el análisis de tests se limita a inspección estática.
- **¿Detectarían un cambio de contrato?** Con alta probabilidad, los Feature tests actuales validan principalmente códigos de estado HTTP y presencia de algunos campos concretos en aserciones puntuales (patrón común en este tipo de test), no snapshots completos de estructura. No se ha encontrado uso de librerías de snapshot testing (`spatie/pest-plugin-snapshots` u otra) en `composer.json`. Esto significa que **renombrar o eliminar un campo no crítico para las aserciones existentes probablemente no rompería ningún test**, lo cual es coherente con el riesgo de descoordinación descrito en el resumen ejecutivo.
- **Reutilización para validar un contrato OpenAPI**: baja tal cual están hoy (no hacen assertions de schema), pero la infraestructura de fixtures (`BuildsOperationsScenario`, `ConfiguresTenantConnection`) sería reutilizable como base para tests de contrato más estrictos en una fase futura.

---

## 10. Estado de CI/CD respecto al contrato

- **No existe ningún pipeline de CI/CD** en el repositorio: no hay directorio `.github/`, ni `.gitlab-ci.yml`, ni script de despliegue automatizado visible en el repo (Coolify, mencionado en CLAUDE.md §11, gestiona despliegue pero no se ha encontrado configuración de pipeline versionada en este repositorio).
- En consecuencia, hoy **nada verifica automáticamente**: tests, Pint, análisis estático, generación de documentación, ni compatibilidad de contrato entre commits.
- **Dónde podría integrarse una comprobación de contrato en el futuro** (sin implementarlo ahora): un job de CI que (a) ejecute `php artisan scribe:generate` contra una BD de test efímera, (b) compare el `openapi.yaml` resultante contra el commiteado anteriormente (diff estructural, no textual, para evitar falsos positivos por metadatos como fecha de generación), y (c) falle el build si hay breaking changes no reconocidos (campo eliminado, tipo cambiado) salvo que se etiquete explícitamente el PR. Este job necesitaría que el spec generado se comitee a git — hoy no ocurre, está en `.gitignore`.

---

## 11. Compatibilidad real con Scramble

Scramble no está instalado; esta valoración es sobre lo que su enfoque (inferencia por análisis estático de tipos PHP, sin ejecutar la app) lograría **si se instalara hoy sobre este código**, contrastado con lo observado:

- **Qué podría inferir automáticamente bien**: rutas, verbos HTTP, middleware de autenticación (Sanctum), reglas de validación simples de Form Requests (tipos básicos: `integer`, `string`, `boolean`, `date`), y la forma de los ~61 controladores que devuelven un `JsonResource`/`ResourceCollection` **siempre que sus campos de nivel superior sean escalares o Resources anidados de verdad** (no aplica a los que usan `toArrayAssoc()`).
- **Patrones actuales que le dificultarían la inferencia**:
  - El patrón `toArrayAssoc()` en 39 modelos: Scramble analiza tipos estáticamente (usa PHPStan por debajo); un método de modelo sin tipo de retorno estructurado (`array` genérico, sin `@return array{...}` ni forma tipada) es opaco para él tanto como para Scribe, pero **sin la mitigación de "response calls" reales** que Scribe sí tiene configurada (Scramble no ejecuta la app, es puramente estático) — por lo que en este código concreto, Scramble probablemente rendiría **peor** que Scribe con `ResponseCalls` activado, no mejor.
  - `CustomerResource::toArray()` delegando en `parent::toArrayAssoc()` vía magic `__call`: Scramble, al ser análisis estático de tipos, **no puede resolver llamadas mágicas en absoluto** — vería una Resource vacía.
  - El retorno `Collection|LengthAwarePaginator` de `OrderListService::list()`: Scramble sí podría reflejar el union type en la firma, pero traducirlo a un esquema OpenAPI `oneOf` útil requeriría anotación manual adicional; por defecto probablemente documentaría solo una de las dos formas o un esquema demasiado laxo.
  - Ausencia casi total de PHP enums (solo `Role`): los campos de estado (`status`, `order_type`) quedarán como `string` sin lista de valores permitidos, salvo que Scramble sea capaz de leer la regla `in:...` del Form Request (variable según versión y configuración).
- **Endpoints que requerirían intervención manual**: con la evidencia recogida, una estimación razonable es que **al menos el 40-50%** de los ~380 endpoints (todos los que pasan por `toArrayAssoc()`, los de estadísticas con arrays manuales, los de superadmin y CRM sin Resource, y los de descarga binaria) necesitarían anotación explícita (`@response` con ejemplo, o refactor a Resource) para producir un esquema útil y no engañoso.
- **Riesgo principal**: un spec generado por Scramble sin resolver antes el patrón `toArrayAssoc()` mostraría objetos anidados como `{}` (schema vacío) o los omitiría, dando una **falsa sensación de contrato mínimo** cuando en realidad esos campos sí existen y tienen estructura rica — peor que no tener spec, porque induce a error activamente.
- **Esfuerzo estimado**: **Medio-alto**. La instalación en sí es trivial, pero obtener un spec *fiable* (no solo "que genera sin error") requiere tocar el código (al menos anotar o refactorizar los 39 usos de `toArrayAssoc()` y resolver los tipos de unión en los Services de listado) antes de confiar en el resultado.

---

## 12. Compatibilidad real con Scribe

- **Ventaja principal en este repositorio concreto**: ya está instalado, configurado con conocimiento específico del dominio (tenant header, auth, exclusiones) y usa `ResponseCalls`, que **ejecuta las rutas `GET` reales** contra una base de datos — esto significa que, a diferencia de Scramble, Scribe puede capturar la forma real de la respuesta de `toArrayAssoc()` **para los endpoints `GET`**, siempre que el modelo de ejemplo (`factoryCreate`/`factoryMake`/`databaseFirst`, en ese orden según `config/scribe.php:203`) tenga datos representativos y las relaciones necesarias precargadas en el controlador real.
- **Qué información podría extraer ya, sin más trabajo**: estructura completa de respuesta de los ~61 controladores basados en Resource para sus rutas `GET`, cabeceras requeridas, esquema de autenticación, y (parcialmente) parámetros de query si se usan convenciones estándar de Laravel reconocidas por los extractores de Scribe.
- **Qué no resolvería solo, incluso con `ResponseCalls`**:
  - Las rutas de escritura (`POST`/`PUT`/`PATCH`/`DELETE`) — `ResponseCalls` está configurado `only: ['GET *']` (`config/scribe.php:233`) — sus respuestas de éxito no se ejercitan realmente, solo se infieren de anotaciones o quedan sin documentar.
  - El caso `Collection|LengthAwarePaginator`: Scribe documentará la forma que obtenga en el momento de generar (probablemente la paginada, si no se pasa `active=true` en el `ResponseCalls`), sin reflejar la rama alternativa.
  - El desfase `Incident` GET-vs-escritura (§6) seguiría sin evidenciarse en el spec salvo que se llame explícitamente a `POST`/`PUT` vía anotación `@response` manual, porque esos verbos no se ejercitan.
- **¿Más adecuada que Scramble para algún caso concreto?** Sí, claramente, para este proyecto: la combinación de `toArrayAssoc()` + `ResponseCalls` la hace la única de las dos capaz de capturar *algo* correcto para los `GET` sin refactor previo. Scramble, siendo puramente estático, quedaría ciego ante el mismo código.
- **Documentación adicional que requeriría**: generalizar `@bodyParam`/`@queryParam` desde el único ejemplo (`StoreOrderRequest`) al resto de Form Requests relevantes (especialmente las 10+ de listados, para documentar `perPage`/`per_page` y filtros), y `@response`/`@responseFile` manuales para las rutas de escritura y para los controladores sin Resource (estadísticas, CRM, superadmin).
- **Esfuerzo estimado**: **Medio**. La base técnica ya existe y funciona; el esfuerzo es de **disciplina de anotación** más que de integración técnica, salvo por el bloqueador transversal de `toArrayAssoc()` que comparte con cualquier otra herramienta.

---

## 13. Comparación fundamentada

| Criterio | Scramble | Scribe | Situación en este proyecto |
|---|---|---|---|
| Instalación actual | No instalado | **Ya instalado y configurado** | Punto de partida fuertemente a favor de Scribe |
| Maneja `toArrayAssoc()` / magic `__call` | No (análisis estático puro) | Parcialmente, solo en `GET`, vía `ResponseCalls` con datos reales | Scribe es la única opción viable sin refactor previo |
| Documenta `POST`/`PUT`/`DELETE` con datos reales | No | No (`ResponseCalls` limitado a `GET` en la config actual, aunque es configurable) | Ambas requieren anotación manual para escritura |
| Requiere BD/entorno para generar | No | Sí (para `ResponseCalls` y factories) | Relevante para CI: Scribe necesita una BD de test funcional en el pipeline |
| Nivel de trabajo ya invertido en el repo | Ninguno | Config de dominio ya escrita (tenant header, auth, exclusiones) + 1 test de humo + 1 Form Request anotada | Migrar a Scramble desperdiciaría ese trabajo |
| Soporte de OpenAPI 3.1 | Sí | Sí (ya configurado, `config/scribe.php:153`) | Empate técnico |
| Coste de adopción incremental | Alto (empezar de cero + resolver los mismos bloqueadores) | Medio (generalizar anotaciones + resolver bloqueadores compartidos) | Scribe gana en coste marginal |

**Conclusión de la comparación**: no hay un argumento técnico sólido para introducir Scramble en este proyecto. Scribe ya está presente, ya conoce el dominio (multi-tenant, auth) y es la única de las dos capaces de extraer algo real de los `GET` afectados por `toArrayAssoc()` sin tocar código. El trabajo pendiente (resolver `toArrayAssoc()`, generalizar anotaciones, versionar el output, meterlo en CI) es el mismo independientemente de la herramienta elegida.

---

## 14. Riesgos de publicar OpenAPI por URL

- **Rutas de superadmin** (`v2/superadmin/*`, ~27 endpoints: gestión de tenants, impersonación, tokens activos, blocklist de IPs, migraciones, feature flags, logs de error) están en el mismo `routes/api.php` que el resto de la API de negocio y coinciden con el mismo `match.prefixes: ['api/*']` de `config/scribe.php:45`. **No hay ninguna exclusión configurada para este prefijo** — solo se excluye `GET /api/health`. Si se publica el spec generado hoy tal cual, **se documentarían públicamente los nombres, parámetros y (parcialmente, vía `ResponseCalls`) las formas de respuesta de endpoints administrativos de altísimo privilegio** (revocar tokens, suspender tenants, generar tokens de impersonación).
- **Rutas de impersonación pública** (`v2/public/impersonation/{token}/approve|reject`, `routes/api.php:210-211`) — accesibles sin autenticación por diseño (aprobación vía URL firmada enviada por email), pero su sola presencia documentada en un spec público revela la existencia y forma del mecanismo de impersonación, información sensible desde el punto de vista de superficie de ataque.
- **Endpoints de observabilidad interna** (`error-logs`, `system/queue-health`, `dashboard/activity`) revelarían nombres de tablas/estructura de logging interno si se documentan con el mismo nivel de detalle que la API de negocio.
- **Multi-tenant**: el spec no contiene datos de un tenant concreto (es un contrato de forma, no de contenido), por lo que el riesgo aquí no es fuga de datos de un tenant específico, sino fuga de **superficie de API interna/administrativa** que facilita reconocimiento a un atacante.
- **Recomendación de segmentación** (sin implementar): la especificación pública para consumo del frontend/app móvil debería generarse **excluyendo explícitamente** `v2/superadmin/*` y `v2/public/impersonation/*` del `match` en `config/scribe.php` (hoy solo excluye `/api/health`), idealmente mediante un segundo perfil de configuración de Scribe (`routes` con múltiples grupos, cosa que la config ya soporta estructuralmente — `config/scribe.php:41-62` es un array de grupos, hoy con uno solo) que separe "público para frontend/app" de "interno para el propio equipo backend".
- **¿Debería la spec ser pública, privada o autenticada?** Dado lo anterior: la especificación completa (incluyendo superadmin) debería quedar **privada/interna** (protegida por VPN, IP allowlist, o al menos por el mismo `superadmin` middleware); una especificación **reducida a `v2/*` de negocio, excluyendo superadmin**, podría exponerse de forma autenticada (no completamente pública) al frontend y a un futuro cliente móvil.

---

## 15. Preparación para un cliente TypeScript generado

| Aspecto | Estado | Evidencia |
|---|---|---|
| Estabilidad de nombres de campo | Media — camelCase consistente en Resources, pero `toArrayAssoc()` puede divergir del `toArray()` de la Resource formal en el mismo modelo (no verificado exhaustivamente, sí en el caso `Incident`) | §6, §7 |
| Uniformidad de fechas | No auditado en profundidad; `Order` no declara casts de fecha explícitos más allá de `invoiced => boolean` en `$casts` (`app/Models/Order.php:102-103`), por lo que fechas como `entry_date`/`load_date` probablemente se serializan como el formato por defecto de Eloquent (string `Y-m-d H:i:s` o `Y-m-d` según el tipo de columna) sin normalización explícita a ISO-8601 en la capa de Resource | `app/Models/Order.php:99-104` |
| Serialización de números | No se ha detectado un patrón de formateo (p. ej. montos como string con 2 decimales vs float nativo) fuera de este código; requiere muestreo adicional no cubierto en esta pasada | — |
| Enums | Débil — un único PHP enum (`Role`) en todo el proyecto; el resto de campos categóricos son strings libres validados por regla `in:`, sin tipo cerrado a nivel de esquema | `app/Enums/Role.php` (único archivo) |
| Campos opcionales / nullable con doble semántica | Confirmado como problema (§6): `null` puede significar "sin valor de negocio" o "relación no cargada en esta petición" indistintamente | `app/Http/Resources/v2/OrderResource.php:20` |
| Errores | Formato base uniforme (`message`, `userMessage`, a veces `errors`/`code`) vía `Handler.php`, pero con variantes ad hoc por controlador (§6) | `app/Exceptions/Handler.php`, `app/Http/Controllers/v2/OrderController.php` |
| Paginación | Dos convenciones (`perPage`/`per_page`) y un endpoint con contrato no determinista (`OrderListService`) | §4, §6 |
| Descargas | PDFs/Excel devuelven binarios sin JSON envolvente — correcto, pero deben marcarse explícitamente como tal en el spec (no son endpoints "de datos" para un generador de tipos) | `PDFController`, `ExcelController` |
| Uploads | Existen (`pallets/{pallet}/attachments`, `stores/{store}/image`) — no auditados en detalle en esta pasada; relevante confirmar `multipart/form-data` correctamente inferido por Scribe |
| Autenticación | Sanctum Bearer, único esquema, sin refresh token explícito (Sanctum personal access tokens no expiran salvo revocación manual) — modelo simple, favorable para un cliente generado | `composer.json` (`laravel/sanctum`), `config/scribe.php:115-123` |

**Conclusión de esta sección**: el mayor obstáculo para un cliente TypeScript generado automáticamente no es la falta de infraestructura (Scribe ya resuelve gran parte de lo mecánico) sino la **inconsistencia semántica real del contrato** (contrato no determinista en paginación de pedidos, doble semántica de `null`, formas divergentes de la misma entidad). Generar tipos hoy produciría tipos técnicamente válidos pero que no reflejarían fielmente el comportamiento real en varios casos ya evidenciados.

---

## 16. Deuda técnica previa que convendría resolver

### Imprescindible antes de generar OpenAPI

- Resolver o al menos **documentar explícitamente como decisión intencional** el contrato no determinista de `OrderListService::list()` (`Collection` vs `LengthAwarePaginator` según `active`) — como mínimo, separarlo en dos endpoints o forzar paginación siempre.
- Unificar el patrón `Incident` (`GET` crudo vs `POST`/`PUT` con `toArrayAssoc()`) a una única forma de serialización.
- Decidir una estrategia consciente para el patrón `toArrayAssoc()` en los 39 modelos: bien migrarlo a Resources anidados reales (coste alto, mayor beneficio a largo plazo), bien mantenerlo pero anotarlo con `@return array{...}` estructurado y forzar `ResponseCalls` con datos representativos en Scribe (coste menor, beneficio parcial).
- Eliminar o justificar explícitamente el `parent::toArrayAssoc()` mágico en `CustomerResource` (y verificar si el patrón se repite en otras Resources no revisadas una a una).
- Versionar el output de Scribe (o al menos publicarlo en un artefacto reproducible) — hoy `.gitignore` lo excluye completamente, por lo que "la fuente de verdad" no es compartible ni auditable en el tiempo.
- Configurar la exclusión explícita de `v2/superadmin/*` y `v2/public/impersonation/*` en `config/scribe.php` antes de cualquier publicación, aunque sea interna.

### Recomendable, pero no bloqueante

- Unificar `perPage`/`per_page` a una única convención en todos los servicios de listado.
- Generalizar las anotaciones `@bodyParam`/`@queryParam`/`@response` desde el único ejemplo existente (`StoreOrderRequest`) al resto de Form Requests de escritura y listado más usadas (Orders, Pallets, Customers, Production primero, por ser los módulos con Rating más alto y más tráfico).
- Introducir PHP enums (8.1+) para los campos de estado más importantes (`Order::STATUS_*`, `Order::ORDER_TYPE_*`, ya definidos como constantes de clase en `app/Models/Order.php:19-31` — candidatos directos a convertirse en `enum` backed, lo que mejoraría tanto la inferencia de Scramble/Scribe como la seguridad de tipos en PHP).
- Añadir un job de CI mínimo (aunque sea solo `phpunit` + `pint --test`) antes de abordar la generación de OpenAPI en pipeline, ya que hoy no existe ningún gate automático de ningún tipo.

### Puede dejarse para una segunda fase

- Migración completa a un cliente TypeScript generado y contract testing automatizado en CI.
- Segmentación fina de grupos Scribe por rol/audiencia (interno vs público vs superadmin) más allá de la exclusión binaria imprescindible ya mencionada.
- Adopción de snapshot testing o de una librería de contract testing formal (Pact u otra) si en el futuro el equipo frontend/móvil crece y se vuelve prioritario detectar breaking changes de forma automática y no solo mediante el spec.

---

## 17. Estrategia preliminar recomendada

No se implementa nada de lo siguiente; es una propuesta de fases a validar con el equipo:

1. **Fase 0 — Congelar y versionar lo que ya existe.** Dejar de ignorar `public/docs/openapi.yaml` en git (o moverlo a un artefacto de build explícito), y excluir `v2/superadmin/*`/`v2/public/impersonation/*` en `config/scribe.php`, para tener una base de referencia real y seguramente publicable, aunque imperfecta.
2. **Fase 1 — Resolver los bloqueadores de contrato no determinista** identificados como "imprescindibles" en §16, empezando por `OrderListService` (por ser el módulo de mayor tráfico y con Rating más alto según CLAUDE.md, A.2 Ventas 9/10) y por `Incident`.
3. **Fase 2 — Piloto de anotación exhaustiva en un solo módulo.** Elegir un módulo ya maduro y acotado (recomendación: **Catálogos**, por ser el bloque más uniforme detectado en §3, con CRUD estándar y sin el patrón `toArrayAssoc()` complicando la mayoría de sus Resources) y anotar completamente sus Form Requests y controladores con `@bodyParam`/`@queryParam`/`@response`, validando que el spec resultante es fiel comparándolo manualmente contra el comportamiento real.
4. **Fase 3 — CI mínimo.** Añadir un job de CI que ejecute `scribe:generate` contra una BD de test y publique el artefacto (aunque sea como artifact de build, no necesariamente público todavía), más el test ya existente (`ApiDocumentationTest`) como gate obligatorio.
5. **Fase 4 — Extender el piloto al resto de módulos**, priorizando por el orden de Rating de CLAUDE.md §8 (los bloques ya en 9/10 primero, por tener menor riesgo de cambio simultáneo de lógica de negocio y contrato).
6. **Fase 5 — Generación de cliente TypeScript** para el frontend Next.js, solo una vez que Fase 1-2 hayan demostrado que el spec de al menos un módulo completo es fiable frente al comportamiento real observado manualmente.
7. **Fase 6 — Contract testing en CI** comparando el spec generado en cada PR contra el de `main`, bloqueando breaking changes no reconocidos explícitamente.

---

## 18. Archivos clave para la futura implementación

| Archivo o carpeta | Función actual | Relevancia futura |
|---|---|---|
| `config/scribe.php` | Configuración de Scribe, ya adaptada a multi-tenant y auth | Punto de partida; necesita añadir exclusión de superadmin y separación de grupos |
| `public/docs/openapi.yaml`, `.scribe/` | Salida generada localmente, no versionada | Debe pasar a ser artefacto reproducible en CI, no archivo local ignorado |
| `tests/Feature/ApiDocumentationTest.php` | Smoke test de generación de Scribe | Base para ampliar a un verdadero test de contrato |
| `app/Http/Requests/v2/StoreOrderRequest.php` | Único ejemplo real de anotación Scribe (`@bodyParam`) | Plantilla a replicar en el resto de 241 Form Requests |
| `app/Services/v2/OrderListService.php` | Contiene el caso más grave de contrato no determinista (`Collection\|LengthAwarePaginator`) | Debe resolverse antes de fiarse del spec para el módulo de pedidos |
| `app/Models/*.php` (39 con `toArrayAssoc`) | Serialización manual de relaciones, bypassa Resources | Bloqueador transversal nº1 para cualquier generador automático |
| `app/Http/Resources/v2/CustomerResource.php` | Ejemplo del patrón `parent::toArrayAssoc()` vía magic `__call` | Caso de estudio a corregir o documentar como excepción consciente |
| `app/Exceptions/Handler.php` | Formato de error centralizado, base razonable para `components/schemas/Error` en OpenAPI | Punto de partida para un esquema de error único, aunque necesita reconciliar variantes ad hoc por controlador |
| `routes/api.php` | Única fuente de rutas API del proyecto, 670 líneas | Referencia para cualquier segmentación de grupos Scribe (superadmin vs negocio vs field) |
| `docs/audits/findings/security-concerns.md` | Hallazgos de seguridad previos, varios directamente relevantes para §14 (perPage sin límite, superadmin) | Debe consultarse en conjunto con este informe antes de decidir qué exponer públicamente |

---

## 19. Preguntas abiertas

- ¿Quién generó el `public/docs/openapi.yaml` existente y con qué frecuencia se ha regenerado manualmente hasta ahora? (No es inferible del código ni de git, ya que el artefacto está en `.gitignore`.)
- ¿Existe ya algún consumo real del `collection.json` de Postman generado, por parte de frontend o QA, que debería tenerse en cuenta al decidir el formato de publicación?
- ¿Es aceptable para el negocio que la primera versión pública del spec excluya por completo `superadmin` y CRM (módulos "en revisión" según CLAUDE.md §8), o se espera que el piloto los cubra desde el principio?
- ¿Cuál es el plan real de la aplicación móvil React Native/Expo mencionada en el brief — reutilizará el canal `v2/field/*` ya existente (pensado hoy para `repartidor_autoventa`) o se espera un contrato nuevo? Esto condiciona si `FieldOrderResource` debe tratarse como el contrato "móvil" de referencia o como un caso particular a discontinuar.
- ¿Hay presupuesto/tiempo de equipo asignado para el refactor de los 39 usos de `toArrayAssoc()`, o se prefiere convivir con ellos y depender exclusivamente de `ResponseCalls` de Scribe como mitigación?

---

## 20. Conclusión

1. **¿Está el backend preparado actualmente para generar un OpenAPI fiable?** No de forma inmediata. La infraestructura (Scribe) sí está lista, pero el contrato real de la aplicación tiene inconsistencias de fondo (contrato no determinista en `OrderListService`, doble serialización de `Incident`, 39 modelos con serialización manual invisible para herramientas estáticas) que producirían un spec técnicamente generado pero parcialmente engañoso.
2. **¿Podría instalarse Scramble y obtener un resultado útil sin refactor previo?** No es la pregunta correcta para este proyecto — Scramble ya no necesita instalarse porque **Scribe ya está instalado y es estrictamente más capaz aquí** gracias a `ResponseCalls`. Ni Scribe ni Scramble producirían, sin embargo, un resultado *plenamente* fiable sin abordar al menos los bloqueadores "imprescindibles" de §16.
3. **¿Qué porcentaje aproximado del contrato sería fiable automáticamente?** Estimación basada en la muestra analizada (no un cálculo exhaustivo): en torno al **50-60%** de los endpoints (los que usan Resources con campos escalares o Resources anidados reales, y siguen el patrón CRUD estándar de Catálogos/Producción) generarían un spec razonablemente fiable hoy mismo con Scribe + `ResponseCalls`. El resto (estadísticas con arrays manuales, entidades con `toArrayAssoc()` en cascada, escritura sin `ResponseCalls`, superadmin/CRM sin Resource) necesitarían intervención manual antes de confiar en el resultado.
4. **¿Cuál es el principal riesgo?** Publicar o empezar a consumir un spec generado automáticamente **sin resolver antes el patrón `toArrayAssoc()` y el contrato no determinista de paginación de pedidos**, generando una falsa sensación de seguridad ("ya tenemos OpenAPI, ya podemos generar el cliente TS") que en la práctica introduciría bugs de frontend más sutiles que los que existen hoy sin ningún contrato formal.
5. **¿Cuál debería ser el primer módulo piloto?** **Catálogos** (species, incoterms, payment-terms, countries, taxes, fishing-gears, capture-zones, product-categories/families): es, según la evidencia recogida en §3, el bloque con el patrón de respuesta más uniforme y menor presencia del patrón `toArrayAssoc()` complicado, lo que permite validar el flujo completo (anotación → generación → cliente TS) con el menor riesgo de encontrarse los bloqueadores críticos a mitad de camino.
6. **¿Qué debe corregirse antes de conectar el frontend a tipos generados?** Como mínimo, en este orden: (a) el contrato no determinista de `GET /v2/orders`, (b) la divergencia `Incident` GET-vs-escritura, (c) versionar el spec generado en lugar de dejarlo en `.gitignore`, y (d) decidir explícitamente qué hacer con los 39 usos de `toArrayAssoc()` antes de generar tipos para cualquier módulo que dependa de ellos (Order, Customer, Pallet y sus relacionados en primer lugar, por ser los de mayor uso).
