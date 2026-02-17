# Convenciones de la API REST v2 — PesquerApp

**Fecha:** 2026-02-15  
**Base path:** `/api/v2/`  
**Propósito:** Documentar convenciones de paginación, filtrado, serialización y manejo de errores para consumidores de la API.

---

## 1. Headers obligatorios

| Header | Requerido | Descripción |
|--------|-----------|-------------|
| `X-Tenant` | Sí | Subdominio del tenant (ej. `brisamar`). Obligatorio para todas las rutas excepto `api/health`, `api/test-cors` y endpoints públicos. |
| `Authorization` | Sí (rutas protegidas) | `Bearer {token}` — token Sanctum. |
| `Accept` | Recomendado | `application/json` para respuestas JSON. |

---

## 2. Paginación

Los listados paginados usan el patrón estándar de Laravel.

### Parámetros de query

| Parámetro | Tipo | Default | Descripción |
|-----------|------|---------|-------------|
| `page` | int | 1 | Número de página. |
| `per_page` o `perPage` | int | 10–20 (varía por recurso) | Elementos por página. Límite máximo típico: 100. |

**Nota:** Algunos endpoints usan `perPage` (camelCase) y otros `per_page` (snake_case). El frontend debe aceptar ambos; los Form Requests normalizan internamente.

### Estructura de respuesta paginada

```json
{
  "data": [...],
  "links": {
    "first": "https://api.example.com/api/v2/orders?page=1",
    "last": "https://api.example.com/api/v2/orders?page=5",
    "prev": null,
    "next": "https://api.example.com/api/v2/orders?page=2"
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 5,
    "path": "https://api.example.com/api/v2/orders",
    "per_page": 15,
    "to": 15,
    "total": 67
  }
}
```

---

## 3. Filtrado

Los filtros se pasan como query params. Cada recurso define sus propios filtros; los Form Requests (`Index*Request`) los validan.

**Convenciones habituales:**

| Patrón | Ejemplo | Descripción |
|--------|---------|-------------|
| `filters[id]` | `filters[id]=123` | Filtro por ID (a veces búsqueda parcial). |
| `filters[ids]` | `filters[ids][]=1&filters[ids][]=2` | Lista de IDs. |
| `filters[status]` | `filters[status]=pending` | Estado del recurso. |
| `filters[name]` | `filters[name]=texto` | Búsqueda por nombre (LIKE). |
| Parámetros directos | `status=pending` | Algunos recursos usan params directos sin prefijo `filters`. |

**Recomendación:** Consultar el Form Request `Index*Request` del recurso concreto para la lista exacta de filtros aceptados.

---

## 4. Serialización de recursos

### Recursos individuales

- **Con wrapper `data`:** La mayoría de respuestas GET de un único recurso devuelven `{ "data": { ... } }`.
- **Sin wrapper:** Algunos endpoints devuelven el objeto directamente. Ejemplo: `options` suele devolver un array de `{ id, name }`.

### API Resources

Los controladores usan clases `*Resource` para serializar. La estructura depende del recurso; campos comunes: `id`, `name`, relaciones cargadas según el contexto.

### Colecciones

- `*Resource::collection($query->paginate($perPage))` → estructura paginada estándar (data, links, meta).

---

## 5. Formato de errores

Todas las respuestas de error de la API (cuando `Accept: application/json` o ruta `api/*`) siguen un contrato unificado.

### Validación (422)

```json
{
  "message": "Error de validación.",
  "userMessage": "Mensaje legible para el usuario.",
  "errors": {
    "campo": ["El campo es obligatorio."]
  }
}
```

### No autenticado (401)

Cuando no hay token, el token es inválido o ha expirado:

```json
{
  "message": "No autenticado.",
  "userMessage": "Debes iniciar sesión para acceder a este recurso."
}
```

### Prohibido (403)

Cuando el usuario está autenticado pero no tiene permiso (Policy, rol, o regla de negocio). **Formato estándar unificado:**

```json
{
  "message": "Acción no autorizada.",
  "userMessage": "Mensaje concreto para el usuario (ej. motivo del rechazo o texto genérico)."
}
```

En respuestas 403 generadas por Policies (Handler), puede aparecer además la clave opcional `error` con el mensaje técnico de la excepción. El frontend debe usar **`userMessage`** para mostrar el texto al usuario.

### No encontrado (404)

```json
{
  "message": "No query results for model [App\\Models\\Order] 123"
}
```

O mensaje personalizado según el controlador.

### Error interno (500)

```json
{
  "message": "Server Error"
}
```

En desarrollo, puede incluir traza; en producción se ofusca.

### Respuestas multi-estado (207)

Algunos endpoints de `destroyMultiple` devuelven 207 con detalle por ID:

```json
{
  "message": "Algunos recursos no pudieron eliminarse.",
  "deleted": 3,
  "errors": [
    { "id": 5, "message": "No se puede eliminar: tiene pedidos vinculados." }
  ]
}
```

---

## 6. Endpoints `options`

Muchos recursos exponen `GET /api/v2/{recurso}/options` para desplegables. Devuelven arrays de `{ id, name }` (o estructura similar según recurso) sin paginación.

Ejemplo: `GET /api/v2/products/options` → `[{ "id": 1, "name": "Producto A" }, ...]`

---

## 7. Referencias

- **Documentación generada:** `public/docs/` (Scribe) — ver `docs/API_DOCUMENTATION_GUIDE.md`.
- **OpenAPI:** `public/docs/openapi.yaml` (si está generada).
- **Arquitectura multi-tenant:** `docs/fundamentos/01-Arquitectura-Multi-Tenant.md`.
