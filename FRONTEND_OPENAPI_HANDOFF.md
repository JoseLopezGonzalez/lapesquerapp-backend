# Handoff: contrato OpenAPI del backend PesquerApp para el frontend Next.js

Documento de coordinación para el agente/equipo que trabaja en el repositorio Next.js. No requiere
volver a auditar el backend — resume el resultado real de la implementación (2026-08-02).

---

## 1. Solución adoptada

**Scribe `^5.9`** (ya estaba instalado en el backend; no se ha introducido una segunda
herramienta). Genera OpenAPI 3.1 ejecutando (`ResponseCalls`) las rutas `GET` reales contra una
base de datos con datos de ejemplo, lo que captura la forma real de las respuestas incluso donde
el backend usa serialización manual (`toArrayAssoc()`) en vez de API Resources puras.

Dos configuraciones:
- `config/scribe.php` — spec interno completo (incluye superadmin), solo para uso del equipo backend.
- `config/scribe_public.php` — **el que te interesa**: excluye superadmin, impersonación y
  observabilidad interna. Es la única fuente que debes consumir.

## 2. Cómo obtener el contrato

**Archivo**: `public/openapi/frontend.yaml` (OpenAPI 3.1, YAML), commiteado en el repo del backend.

**URL en producción**: `{APP_URL_DEL_BACKEND}/openapi/frontend.yaml` — es un archivo estático
servido directamente desde `public/`, **sin autenticación ni header `X-Tenant`** (es un contrato
de forma, no de datos de un tenant concreto). Confirma con el equipo backend/DevOps la URL base
exacta del backend en cada entorno (no está fijada en este documento porque no forma parte del
código versionado — típicamente algo como `https://api.lapesquerapp.es` en producción; revisa
`APP_URL` en la configuración de despliegue de cada entorno).

**Metadatos de versión**: `public/openapi/meta.json`, junto al spec:
```json
{
  "generated_at": "2026-08-02T14:18:54+00:00",
  "git_commit": "ebf82ba",
  "contract_sha256": "6392e3280029a0b97262dfa3ab09954d371e42952958ea3fee5471226cb51297",
  "source": "config/scribe_public.php"
}
```
Sirve para detectar si el contrato cambió entre despliegues (compara `contract_sha256` o
`git_commit`) sin tener que parsear el YAML completo.

**No requiere pasos manuales**: el backend regenera y commitea el archivo cada vez que cambia un
endpoint (gate obligatorio en CI, ver `docs/api-contract.md`). Tu pipeline de generación de tipos
puede apuntar directamente a la URL o al archivo del repo backend (si tienes acceso a él como
submódulo/checkout) sin necesidad de que nadie te lo pase a mano.

## 3. Comandos del lado backend (referencia, no los ejecutas tú)

| Comando | Qué hace |
|---|---|
| `composer contract:update` | Regenera `public/openapi/frontend.yaml` + `meta.json` |
| `composer contract:verify` | Compara el contrato commiteado contra el código actual; falla si hay breaking changes sin reconocer |
| `composer contract:test` | Ejecuta los tests de contrato (`ApiDocumentationTest`) |

## 4. Módulos listos para el primer piloto

Recomendación: **empieza por Catálogos** (`species`, `incoterms`, `payment-terms`, `countries`,
`taxes`, `fishing-gears`, `capture-zones`, `product-categories`, `product-families`) — es el
bloque más uniforme (CRUD estándar, API Resources puras, sin `toArrayAssoc()` complicando la
respuesta). Genera tipos TypeScript para este bloque primero y valida el flujo completo
(fetch del spec → generación de tipos → cliente) antes de extender a otros módulos.

También razonablemente fiables (Resources reales, cubiertos por `ResponseCalls` autenticadas):
- **Producción** (`productions`, `production-records`, `production-inputs/outputs`, etc.)
- **Almacenes** (`stores`)
- **Fichajes** (`punches`)
- **Recepciones de materia prima** (`raw-material-receptions`)
- **Etiquetas** (`labels`)
- **Pedidos** (`orders`) e **Incidencias** (`orders/{id}/incident`) — con las salvedades del §7.

## 5. Módulos que NO deben migrarse todavía

- **CRM / Comercial** (`prospects`, `offers`, `crm/*`): varios controladores devuelven arrays
  manuales sin Resource; el bloque está "en revisión" según `CLAUDE.md` §8 (Rating 8.5/10).
- **Estadísticas** (`statistics/*`): arrays construidos a mano, forma no tipada de forma estable.
- **Canal de campo / Autoventa** (`v2/field/*`): usa Resources **paralelas y distintas** de las
  internas (`FieldOrderResource` ≠ `OrderResource`) — trátalo como un contrato aparte, no asumas
  que "pedido" tiene la misma forma en `v2/orders` y en `v2/field/orders`.
- **Superadmin**: excluido por diseño del contrato frontend (no es tuyo).

## 6. Rutas excluidas del contrato frontend

`v2/superadmin/*`, `v2/public/impersonation/*`, `v2/debug/*`, `v2/internal/*`, `v2/system/*`,
`GET /api/health`. `v2/public/tenant/{subdomain}` (lookup de tenant antes de login) sí está
incluido.

## 7. Convenciones del contrato

- **Casing**: `camelCase` en el JSON de request/response para los endpoints basados en API
  Resources y en `toArrayAssoc()`. Excepción conocida: algunos endpoints sin Resource (ver §5)
  pueden devolver claves en otro formato — el spec es la fuente de verdad, no asumas.
- **Paginación**: sobre estándar de Laravel `{ data: [...], links: {...}, meta: {...} }`. El
  parámetro de tamaño de página es mayoritariamente `perPage` (camelCase), pero algunos endpoints
  (sesiones, superadmin) usan `per_page` — revisa el spec por endpoint, no lo des por sentado
  globalmente. Límite máximo `100` en todos los listados paginados.
- **Errores**: formato unificado `{ message, userMessage, errors? }` (422 incluye `errors` por
  campo); ver `docs/frontend/api-conventions.md` §5 para el detalle completo por código HTTP.
- **Relaciones opcionales/no cargadas**: varios Resources (`OrderResource`,
  `OrderDetailsResource`, `CustomerResource`) devuelven `null` en un campo de relación tanto si
  "no aplica" como si esa vista concreta no cargó la relación (`relationLoaded()`). El spec no
  puede distinguir ambos casos — si un campo relacional es `null` en una respuesta, no asumas
  automáticamente "no tiene valor de negocio"; podría ser que ese endpoint concreto no la carga.
- **Autenticación**: Sanctum Bearer (`Authorization: Bearer {token}`), token obtenido vía
  login/magic-link/OTP. Sin refresh token — los tokens de acceso personal no expiran salvo
  revocación.
- **Header de tenant**: `X-Tenant: {subdomain}` obligatorio en todas las rutas de negocio (no en
  login/magic-link/OTP/health/tenant-lookup, que son públicas — marcadas `@unauthenticated` en el
  spec).
- **Uploads**: `multipart/form-data` en adjuntos de pedidos/palets y en imagen de almacén.
- **Descargas**: los endpoints de PDF/Excel (`/pdf/*`, `/xlsx/*`, `/xls/*`) devuelven binarios
  (`application/pdf`, hojas de cálculo), no JSON — no intentes parsearlos como el resto de
  endpoints ni generar un tipo TS de "respuesta" para ellos más allá del blob.

## 8. Breaking changes resueltos durante esta implementación

- **`GET /v2/orders/{orderId}/incident` cambia de forma** (antes: modelo Eloquent crudo en
  `snake_case`; ahora: igual que `POST`/`PUT`, `camelCase` vía `toArrayAssoc()`). Si algo en el
  frontend ya leía `order_id`/`resolution_type`/`created_at` de esa respuesta concreta, actualízalo
  a `resolutionType`/`createdAt`/etc. Detalle completo con ejemplos:
  `docs/frontend-integration/backend-api-changes.md` (Sprint 2, 2026-08-02).
- Ningún otro cambio de comportamiento visible: el resto de fixes de esta intervención
  (autenticación en la generación del contrato, null-safety en un par de Form Requests) no alteran
  ninguna respuesta real de la API.

## 9. Limitaciones conocidas / deuda pendiente (no bloquean el piloto recomendado, pero ten cuidado)

- **`GET /v2/orders?active=true|false` cambia de forma** frente a `GET /v2/orders` sin ese
  parámetro (sin `links`/`meta` cuando se usa `active`). No se ha cambiado el comportamiento por
  riesgo de romper el Order Manager actual. **Recomendación**: usa `GET /v2/orders/active` (forma
  estable, `ActiveOrderCardResource`) en vez de `GET /v2/orders?active=true` para pantallas nuevas.
- **39 modelos usan `toArrayAssoc()`** para serializar relaciones anidadas en vez de Resources
  reales. El contrato captura su forma real para `GET` (gracias a `ResponseCalls`), pero **no**
  para las respuestas de éxito de `POST`/`PUT`/`PATCH` (Scribe solo ejecuta `GET *`) — esas se
  infieren del código, no de una ejecución real. Si generas tipos para operaciones de escritura,
  verifica manualmente la respuesta real de al menos un caso antes de confiar ciegamente en el tipo.
- **Reproducibilidad del spec entre generaciones**: si notas que el spec cambia de forma sutil
  sin que haya habido un cambio de código correspondiente, es probablemente ruido de generación
  (datos de ejemplo distintos entre ejecuciones locales del backend, no un cambio de contrato real)
  — contrástalo con `meta.json`/`git_commit` antes de reportarlo como incidencia. Detalle técnico
  en `docs/api-contract.md` §5.
- **`FieldOrderResource` vs `OrderResource`**: mismo concepto de negocio, formas distintas (ver §5).

## 10. Recomendación concreta para el primer piloto

1. Genera tipos TypeScript a partir de `public/openapi/frontend.yaml` con tu herramienta habitual
   (p. ej. `openapi-typescript`) para el bloque **Catálogos** únicamente.
2. Valida contra un endpoint real (`GET /api/v2/species`, `GET /api/v2/incoterms`) que los tipos
   generados coinciden con la respuesta real.
3. Si el flujo funciona, extiende a **Producción**, **Almacenes** y **Pedidos** (con la salvedad
   del §9 sobre `active`), en ese orden — son los bloques con Rating más alto y contrato más
   estable según `CLAUDE.md` §8.
4. No generes todavía cliente/tipos para CRM, Estadísticas o Field/Autoventa (§5).
5. Cada vez que el backend publique un nuevo `public/openapi/frontend.yaml` con un `git_commit`
   distinto, regenera tus tipos — no asumas que el contrato es estático entre sprints.

## 11. Archivos del backend relevantes

| Archivo | Para qué |
|---|---|
| `public/openapi/frontend.yaml` | El contrato en sí |
| `public/openapi/meta.json` | Versión/hash/fecha de la última generación |
| `docs/api-contract.md` | Documentación operativa completa (generar, validar, publicar) |
| `docs/frontend/api-conventions.md` | Convenciones de paginación/errores/serialización |
| `docs/frontend-integration/backend-api-changes.md` | Changelog de cambios visibles al frontend, por sprint |
| `config/scribe_public.php` | Qué rutas se incluyen/excluyen del contrato frontend |
| `API_CONTRACT_AUDIT.md` | Auditoría original (contexto histórico completo) |

---

**Generado**: 2026-08-02. Corresponde al contrato con `git_commit: ebf82ba` (ver `meta.json` para
la versión vigente en cada momento — este documento no se actualiza automáticamente si el backend
sigue evolucionando el contrato).
