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

## Sprint 2 (pendiente)

*Se actualizará cuando se implementen los cambios de Sprint 2.*

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
