---
title: Contrato OpenAPI de la API v2
description: Arquitectura, comandos y reglas del contrato OpenAPI que consume el frontend Next.js.
updated: 2026-08-02
audience: Backend Engineers, Frontend Engineers, Agentes IA
---

# Contrato OpenAPI de la API v2

**Fuente de verdad del contrato: el código Laravel** (rutas, Form Requests, API Resources). El
OpenAPI generado es una *proyección* de ese código, no al revés. Si el spec y el comportamiento
real discrepan, el código manda y el spec está desactualizado — regenéralo.

Ver también: `API_CONTRACT_AUDIT.md` (auditoría original, 2026-08-02), `docs/frontend/api-conventions.md`
(convenciones de paginación/errores/serialización) y `docs/frontend-integration/backend-api-changes.md`
(changelog de cambios visibles al frontend).

---

## 1. Arquitectura

Herramienta: **[Scribe](https://scribe.knuckles.wtf) `^5.9`** (ya estaba instalada; no se ha
introducido una segunda herramienta de generación — ver `API_CONTRACT_AUDIT.md` §11-13 para la
justificación). Genera OpenAPI 3.1 ejecutando (`ResponseCalls`) las rutas `GET` reales contra una
base de datos, lo que permite capturar la forma real de respuestas que usan `toArrayAssoc()` en
vez de API Resources (ver §5).

**Dos configuraciones, dos audiencias:**

| Config | Uso | Incluye | Salida |
|---|---|---|---|
| `config/scribe.php` | Interno (equipo backend) | Todo `api/*`, incluido superadmin | `public/docs/` (no versionado, no desplegado) |
| `config/scribe_public.php` | **Contrato frontend** | `api/v2/*` de negocio, excluye superadmin/impersonación/observabilidad | `storage/app/scribe-public/` → se publica solo `openapi.yaml` en `public/openapi/frontend.yaml` (versionado) |

Solo **`public/openapi/frontend.yaml`** es el artefacto que debe consumir el frontend. Está
versionado en git (a diferencia de `public/docs/`) porque es pequeño (texto YAML) y necesita una
URL estable de despliegue a despliegue.

---

## 2. Comandos

| Comando | Qué hace |
|---|---|
| `composer contract:update` (= `php artisan contract:publish`) | Regenera el contrato frontend y lo escribe en `public/openapi/frontend.yaml` + `public/openapi/meta.json` (hash, fecha, commit). |
| `composer contract:verify` (= `php artisan contract:check`) | Regenera en una ruta temporal y compara estructuralmente contra el `frontend.yaml` commiteado. Reporta cambios `BREAKING` / `COMPATIBLE` / `INFO`. Sale con código ≠0 si hay `BREAKING` no reconocidos (`--allow-breaking` para migraciones deliberadas) o, con `--fail-on-any`, si hay *cualquier* cambio sin commitear (así se usa en CI). |
| `composer contract:test` | Ejecuta `tests/Feature/ApiDocumentationTest.php` (genera ambos specs, comprueba exclusiones sensibles y presencia de rutas de negocio). |
| `php artisan scribe:generate` | Spec interno completo (incluye superadmin) — solo para inspección local. |
| `php artisan contract:seed-fixture` | Crea el tenant `demo-tenant` + un admin de prueba para que `ResponseCalls` pueda autenticarse (CI o local, nunca contra una BD real). |

**Requiere una base de datos** (MySQL) accesible por `ResponseCalls`, porque Scribe ejecuta las
rutas `GET` reales. En local, usa tu BD de desarrollo habitual. En CI, un servicio `mysql:8.0`
efímero (ver `.github/workflows/api-contract.yml`).

---

## 3. Flujo de trabajo al modificar la API

Antes de dar por terminada una tarea que toque un endpoint (ruta, Form Request, Resource,
controlador o servicio que cambie la forma de una respuesta):

1. **Implementa el cambio** siguiendo las convenciones normales (Controller thin → Service →
   Resource explícita; ver CLAUDE.md §5-6).
2. **Regenera y revisa**: `composer contract:update`. Abre el diff de `public/openapi/frontend.yaml`
   y confirma que el cambio es el esperado (no un efecto colateral en otro endpoint).
3. **Verifica breaking changes**: `composer contract:verify`. Si aparece algo `BREAKING` que no
   esperabas, revisa si es un efecto no intencional antes de continuar.
4. **Si es un breaking change intencional** (p. ej. eliminar un campo obsoleto): documéntalo en
   `docs/frontend-integration/backend-api-changes.md` (nueva entrada de sprint) y en el evolution
   log si el bloque está bajo el workflow de CLAUDE.md §18. Regenera con
   `php artisan contract:check --allow-breaking` para confirmar que no hay *otros* breaking
   changes ocultos.
5. **Commitea** el código junto con `public/openapi/frontend.yaml` y `public/openapi/meta.json`
   actualizados. CI (`api-contract` en `.github/workflows/api-contract.yml`) falla si el contrato
   commiteado no coincide con el que genera el código de la PR.
6. **Añade/actualiza tests** del endpoint (Feature test) si el cambio afecta datos sensibles al
   negocio (no hace falta para cambios puramente cosméticos de documentación).

### Al añadir un endpoint nuevo

- Usa una API Resource explícita para la respuesta (no un array manual, no el modelo Eloquent
  crudo). Si es de solo lectura y de bajo riesgo (ej. un `options()`), un array explícito con
  forma fija está bien — lo que no vale es una forma que cambie según el contenido.
- Si el endpoint es sensible (superadmin, impersonación, observabilidad interna, debug): regístralo
  bajo un prefijo ya excluido en `config/scribe_public.php` (`v2/superadmin/*`,
  `v2/public/impersonation/*`, `v2/debug/*`, `v2/internal/*`, `v2/system/*`) o añade una nueva
  exclusión explícita si usa un prefijo distinto. **No asumas que quedará fuera por defecto.**
- Añade `@queryParam`/`@bodyParam` en el Form Request si los parámetros no son obvios desde las
  reglas de validación (ver `app/Http/Requests/v2/StoreOrderRequest.php` o
  `IndexOrderRequest.php` como ejemplo).

### Al modificar un endpoint existente

- Si cambias el *nombre*, *tipo* o *presencia* de un campo de respuesta: es potencialmente
  breaking. `contract:verify` te lo señalará.
- Si el campo depende de una relación cargada condicionalmente (`relationLoaded()`), documenta
  en el propio Resource (comentario) qué controladores cargan esa relación y cuáles no — el
  spec no puede expresar "null solo si no se pidió cargar" de forma distinta a "null por regla
  de negocio" (ver §5).
- No dupliques una entidad de negocio con una forma distinta sin necesidad (ver `FieldOrderResource`
  vs `OrderResource` en §5): si hace falta, documenta explícitamente por qué difieren.

---

## 4. Qué se excluye del contrato frontend y por qué

| Prefijo excluido | Motivo |
|---|---|
| `api/v2/superadmin/*` | Gestión de tenants, impersonación, seguridad, migraciones, feature flags — uso interno del panel Superadmin, no del ERP de cliente. |
| `api/v2/public/impersonation/*` | Aprobación/rechazo de impersonación por URL firmada. Sensible aunque no requiera auth: revela la existencia y forma del mecanismo. |
| `api/v2/debug/*`, `api/v2/internal/*`, `api/v2/system/*` | Prefijos reservados para observabilidad/depuración si se añaden en el futuro (no existen hoy, exclusión preventiva). |
| `GET /api/health` | Healthcheck de infraestructura, no es parte del contrato de negocio. |

`api/v2/public/tenant/{subdomain}` **sí se incluye**: es un endpoint público que el frontend usa
para resolver el tenant antes de login.

Test de regresión: `tests/Feature/ApiDocumentationTest.php::test_public_openapi_spec_excludes_sensitive_routes`
y `::test_public_openapi_spec_includes_business_routes`.

---

## 5. Deuda conocida (no resuelta en esta intervención)

Ver `API_CONTRACT_AUDIT.md` para el detalle completo. Resumen de lo que sigue pendiente y por qué
no se ha abordado ahora (alcance desproporcionado para una sola intervención):

- **`toArrayAssoc()` en 39 modelos** (`grep -rl "function toArrayAssoc" app/Models`): serialización
  manual de relaciones anidadas, invisible para análisis estático. Mitigado parcialmente porque
  Scribe usa `ResponseCalls` (ejecuta las rutas `GET` reales), así que el spec captura la forma
  real observada — pero **solo para `GET`**; las respuestas de escritura (`POST`/`PUT`/`PATCH`)
  de Resources que dependen de `toArrayAssoc()` no se verifican contra un esquema real y podrían
  divergir sin que el contrato lo detecte. Migración recomendada (progresiva, no bloqueante):
  sustituir `toArrayAssoc()` por Resources anidadas reales, empezando por los modelos más usados
  (`Customer`, `Order`-relacionados).
- **`relationLoaded()` condicional** (`OrderResource`, `OrderDetailsResource`, etc.): un campo
  puede ser `null` porque no aplica o porque esa vista no cargó la relación. El spec lo marca como
  `nullable` sin poder distinguir el motivo — es una limitación conocida de OpenAPI/Scribe con
  este patrón, no un bug.
- **`GET /v2/orders` con el parámetro `active`**: cambia de forma (sin `links`/`meta` cuando se
  usa `active`). Documentado como `deprecated` en `IndexOrderRequest` y en
  `OrderListService::list()`; no se ha cambiado el comportamiento por el riesgo de romper al
  frontend actual (Order Manager). Recomendación: migrar a `GET /v2/orders/active` (forma estable).
- **`FieldOrderResource` vs `OrderResource`**: mismo concepto de negocio, formas distintas
  (canal `repartidor_autoventa` vs interno). Es una decisión de producto razonable, pero no está
  documentada como tal en el código — queda así tras esta intervención.
- **Escrituras (`POST`/`PUT`/`DELETE`) sin `ResponseCalls`**: Scribe solo ejecuta `GET *`
  (`config/scribe.php` → `strategies.responses`). Las respuestas de éxito de escritura se
  documentan por inferencia de las Resources declaradas en el código, no por ejecución real.
- **Controladores de estadísticas y CRM sin Resource** (arrays manuales): su forma en el spec es
  la que Scribe pueda inferir de `ResponseCalls`; si el endpoint no tiene datos de ejemplo en la
  BD de generación, el spec puede quedar incompleto para ese caso concreto.
- **Reproducibilidad entre ejecuciones repetidas contra la misma BD**: `ResponseCalls` con
  `models_source: factoryCreate` crea modelos de ejemplo efímeros para parámetros de ruta (p. ej.
  `{orderId}`). Se envuelven en una transacción por conexión (`database_connections_to_transact`)
  que se revierte al terminar cada llamada, pero **el contador `AUTO_INCREMENT` de MySQL no es
  transaccional**: avanza igualmente aunque la fila se revierta. Ejecutar `contract:publish` o
  `contract:check` varias veces seguidas contra la **misma** base de datos de desarrollo puede
  producir specs ligeramente distintos entre sí (un parámetro de ruta que en una ejecución cae
  sobre un registro sembrado real y en la siguiente cae sobre un id inexistente, cambiando un
  `200` por un `404`). **No es un problema en CI** (una base de datos efímera por job: migrar →
  sembrar → generar una sola vez), pero en local, si `contract:check` reporta cambios que no
  esperabas, resetea la base de datos de generación (`migrate:fresh` + `contract:seed-fixture`)
  antes de confiar en el diff.

Ninguno de estos puntos bloquea el uso del contrato para los módulos recomendados en
`FRONTEND_OPENAPI_HANDOFF.md` — se documentan para que un cambio futuro en esas áreas no tome a
nadie por sorpresa.

---

## 6. Versionado y breaking changes

No hay versionado de API (`v2` es un prefijo de URL fijo, no hay negociación de contenido). El
criterio pragmático para decidir si un cambio necesita coordinación con el frontend:

| Tipo de cambio | Ejemplo | ¿Bloquea CI? |
|---|---|---|
| Compatible | Añadir un campo opcional a una respuesta; añadir un endpoint nuevo; relajar una validación (campo pasa de requerido a opcional) | No |
| Potencialmente incompatible | Cambiar el tipo de un campo; añadir un campo requerido a una request | Sí (`BREAKING` en `contract:verify`), a menos que se reconozca con `--allow-breaking` |
| Incompatible | Eliminar un endpoint; eliminar un campo de una respuesta | Sí, igual que el anterior |
| Requiere coordinación explícita | Cualquier `BREAKING` reconocido con `--allow-breaking` | CI pasa, pero **debe** quedar anotado en `docs/frontend-integration/backend-api-changes.md` |

`OpenApiContractDiffer` (`app/Services/OpenApi/OpenApiContractDiffer.php`) hace la comparación:
mira nombres/tipos de propiedades de nivel superior y si son requeridas, no un diff semántico
profundo de JSON Schema. Deliberado — evita falsos positivos por reordenación de claves o
metadatos de generación, a costa de no detectar cambios anidados a más de un nivel.

---

## 7. Referencias

- `API_CONTRACT_AUDIT.md` — auditoría original (hallazgos, comparación Scribe/Scramble).
- `FRONTEND_OPENAPI_HANDOFF.md` — entregable para el agente/equipo del repo Next.js.
- `docs/frontend/api-conventions.md` — convenciones de paginación, filtrado, errores.
- `docs/frontend-integration/backend-api-changes.md` — changelog de cambios visibles al frontend.
- `CLAUDE.md` §22 — reglas permanentes para agentes IA sobre el contrato API.
