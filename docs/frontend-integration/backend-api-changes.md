# Backend API Changes — Frontend Integration Reference

Documento acumulativo de cambios en el backend que afectan la integración con el frontend Next.js.

Cada sección indica el sprint de origen, los endpoints afectados, y el impacto esperado en el cliente.

---

## Sprint 1 — 2026-03-24 (Quick Wins P0)

### Resumen de cambios visibles al frontend

| Cambio | Tipo | Endpoints afectados | Acción requerida |
|---|---|---|---|
| perPage cap a 100 | Comportamiento | Todos los listados | Verificar que frontend no envíe `perPage > 100` |
| Resto de cambios | Interno | — | Ninguna |

---

### 1. Límite máximo de paginación: `perPage = 100`

**Fecha**: 2026-03-24
**Motivación**: Protección contra peticiones abusivas (`perPage=999999`) que podían causar OOM en el servidor.

**Comportamiento anterior**: El parámetro `perPage` no tenía límite superior. El frontend podía enviar cualquier valor.

**Comportamiento nuevo**: Todos los endpoints de listado tienen un límite máximo de **100 ítems por página**. Si se envía `perPage=200`, la respuesta contiene máximo 100 ítems.

**Endpoints afectados** (todos los listados paginados de la API v2):

| Endpoint | perPage default | perPage máximo |
|---|---|---|
| `GET /api/v2/orders` | 10 | 100 |
| `GET /api/v2/pallets` | 10 | 100 |
| `GET /api/v2/suppliers` | 12 | 100 |
| `GET /api/v2/raw-material-receptions` | 12 | 100 |
| `GET /api/v2/customers` | 10 | 100 |
| `GET /api/v2/field-operators` | 10 | 100 |
| `GET /api/v2/prospects` | 10 | 100 |
| `GET /api/v2/product-categories` | 12 | 100 |
| `GET /api/v2/commercial-interactions` | 10 | 100 |
| `GET /api/v2/product-families` | 12 | 100 |
| `GET /api/v2/cebo-dispatches` | 12 | 100 |
| `GET /api/v2/offers` | 10 | 100 |
| `GET /api/v2/products` | 14 | 100 |
| `GET /api/v2/users` | 10 | 100 |
| `GET /api/v2/salespeople` | 10 | 100 |

**Respuesta de ejemplo** con `?perPage=200` (antes devolvía 200, ahora devuelve 100):
```json
{
  "data": [ ...100 items... ],
  "links": { ... },
  "meta": {
    "current_page": 1,
    "per_page": 100,
    "total": 350,
    "last_page": 4
  }
}
```

**Acción requerida por el frontend**:
- Si alguna pantalla enviaba `perPage > 100` para cargar "todo", debe adaptarse a paginar correctamente.
- Para selects/dropdowns que necesiten todos los ítems, usar los endpoints `/options` o `/op` que devuelven listados completos sin paginación (estos no se ven afectados).

---

### 2. Autenticación magic link y OTP (sin cambio de contrato)

**Fecha**: 2026-03-24
**Cambio interno**: Los endpoints `POST /api/v2/auth/verify-magic-link` y `POST /api/v2/auth/verify-otp` tienen ahora protección contra doble uso del mismo token (fix de race condition TOCTOU).

**Impacto en frontend**: Ninguno. Las respuestas JSON son idénticas. El único cambio de comportamiento es que si dos requests simultáneos usan el mismo token, solo uno tendrá éxito (el correcto). El frontend no debería estar enviando el mismo token dos veces.

---

### 3. Mejoras de rendimiento internas (sin cambio de contrato)

**Fecha**: 2026-03-24

Los siguientes cambios son transparentes al frontend:

- **LogActivity**: El registro de actividad de usuario ahora usa cache de geolocalización (24h por IP) y escritura asíncrona. Los logs siguen creándose, solo más eficientemente.
- **TenantMiddleware**: El lookup de tenant ahora usa cache (5 min). No hay cambio en el comportamiento observable.
- **NFC Punch**: La determinación del tipo de fichaje (entrada/salida) es ahora atómica. El comportamiento visible es idéntico — siempre devuelve el tipo correcto.

---

## Sprint 2 — 2026-08-02 (Contrato OpenAPI)

### Resumen de cambios visibles al frontend

| Cambio | Tipo | Endpoints afectados | Acción requerida |
|---|---|---|---|
| `GET /v2/orders/{orderId}/incident` cambia de forma | **Breaking** | 1 endpoint | Actualizar el parseo: ya no es el modelo Eloquent crudo |

### 1. `GET /v2/orders/{orderId}/incident` ahora devuelve la misma forma que `POST`/`PUT` (breaking)

**Fecha**: 2026-08-02
**Motivación**: el mismo recurso `Incident` se serializaba de forma distinta según el verbo —
`GET` devolvía el modelo Eloquent crudo (columnas de BD en `snake_case`: `order_id`,
`resolution_type`, `created_at`, ...), mientras que `POST`/`PUT` devolvían `Incident::toArrayAssoc()`
(`camelCase`: `resolutionType`, `resolutionNotes`, ...). Detectado en `API_CONTRACT_AUDIT.md` §6-7
como bloqueador para generar un contrato OpenAPI fiable.

**Comportamiento anterior** (`GET`):
```json
{
  "id": 1,
  "order_id": 42,
  "description": "...",
  "status": "open",
  "resolution_type": null,
  "resolution_notes": null,
  "resolved_at": null,
  "created_at": "2026-08-02T10:00:00.000000Z",
  "updated_at": "2026-08-02T10:00:00.000000Z"
}
```

**Comportamiento nuevo** (`GET`, ahora idéntico a `POST`/`PUT`):
```json
{
  "id": 1,
  "description": "...",
  "status": "open",
  "resolutionType": null,
  "resolutionNotes": null,
  "resolvedAt": null,
  "createdAt": "2026-08-02T10:00:00+00:00",
  "updatedAt": "2026-08-02T10:00:00+00:00"
}
```

Nótese además: `order_id` ya no se expone (redundante, viene de la propia URL) y las fechas se
serializan vía `toIso8601String()` en vez del formato por defecto de Eloquent.

**Acción requerida por el frontend**: si algún componente lee `order_id`, `resolution_type`,
`resolution_notes`, `resolved_at`, `created_at` o `updated_at` de la respuesta de `GET .../incident`,
actualizarlo a los nombres camelCase (`resolutionType`, `resolutionNotes`, `resolvedAt`,
`createdAt`, `updatedAt`) — igual que ya se hacía para las respuestas de `POST`/`PUT` de este
mismo endpoint.

### 2. Endpoints internos/administrativos ya no son alcanzables desde el contrato publicado

**Fecha**: 2026-08-02
**Cambio**: no es un cambio de comportamiento de la API (las rutas siguen funcionando igual),
sino de qué aparece documentado en `public/openapi/frontend.yaml`. `v2/superadmin/*` y
`v2/public/impersonation/*` nunca deben usarse desde el frontend de negocio ni la app móvil — si
algo los está consumiendo hoy, es una integración fuera de contrato que debería revisarse.

**Impacto en frontend**: Ninguno si el frontend de negocio nunca llamó a esas rutas (lo esperado).

---

## Sprint 3 (pendiente)

---

## Sprint 3 (pendiente)

*Se actualizará cuando se implementen los cambios de Sprint 3.*

---

## Notas generales de integración

### Parámetros de paginación

El backend usa el parámetro `perPage` (camelCase) en los endpoints de la API v2. Excepción: algunos endpoints de Superadmin y de sesiones usan `per_page` (snake_case). No mezclar.

### Headers requeridos

Todos los requests a `/api/v2/*` (salvo rutas públicas) requieren:
- `X-Tenant: {subdomain}` — identificador del tenant
- `Authorization: Bearer {token}` — token Sanctum

### Formato de errores

```json
{
  "message": "Mensaje técnico",
  "userMessage": "Mensaje legible para el usuario (cuando aplica)",
  "errors": { "campo": ["error 1", "error 2"] }
}
```

### Códigos de estado comunes

| Código | Situación |
|---|---|
| 400 | Token inválido / datos incorrectos |
| 401 | No autenticado |
| 403 | Sin permisos / cuenta suspendida |
| 404 | Recurso o tenant no encontrado |
| 422 | Error de validación (con `errors`) |
| 429 | Rate limit superado (auth endpoints) |
