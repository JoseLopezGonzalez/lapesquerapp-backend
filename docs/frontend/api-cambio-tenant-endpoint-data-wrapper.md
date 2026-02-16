# Cambio API: Endpoint Tenant — Envoltorio `data`

**Fecha:** 2026-02-15  
**Endpoint afectado:** `GET /api/v2/public/tenant/{subdomain}`  
**Tipo:** Cambio de estructura de respuesta (breaking change para consumidores)

---

## Resumen

El endpoint de resolución de tenant por subdominio pasa a seguir la convención estándar de Laravel API Resources: la respuesta exitosa envuelve el payload en una clave `data`.

---

## Antes

**Request:** `GET /api/v2/public/tenant/{subdomain}`

**Response 200:**
```json
{
  "active": true,
  "name": "Mi Empresa"
}
```

**Response 404:**
```json
{
  "error": "Tenant not found"
}
```

---

## Después

**Request:** `GET /api/v2/public/tenant/{subdomain}` (sin cambios)

**Response 200:**
```json
{
  "data": {
    "active": true,
    "name": "Mi Empresa"
  }
}
```

**Response 404:**
```json
{
  "error": "Tenant no encontrado"
}
```

**Response 422** (subdominio inválido; ej. caracteres no permitidos):
```json
{
  "message": "El subdominio solo puede contener letras, números, guiones y guiones bajos.",
  "errors": {
    "subdomain": ["El subdominio solo puede contener letras, números, guiones y guiones bajos."]
  }
}
```

---

## Acción requerida en el frontend

### 1. Ajustar el acceso a los datos en respuesta 200

Si el código actual hace algo como:

```javascript
const { active, name } = response.data;
```

debe cambiarse a:

```javascript
const { active, name } = response.data.data;
```

o, en TypeScript/axios:

```typescript
interface TenantResponse {
  data: {
    active: boolean;
    name: string;
  };
}

const res = await api.get<TenantResponse>(`/api/v2/public/tenant/${subdomain}`);
const { active, name } = res.data.data;
```

### 2. Mensaje de error 404

El texto pasa de `"Tenant not found"` a `"Tenant no encontrado"`. Si el frontend traducía o comparaba el mensaje, actualizar la referencia.

### 3. Nuevo caso 422

Si el subdominio contiene caracteres inválidos (espacios, `@`, etc.), la API devuelve **422** con estructura estándar de validación. Añadir manejo si procede.

### 4. Throttling

El endpoint tiene **rate limit de 60 peticiones/minuto**. En caso de abuso, la API devolverá 429 (Too Many Requests).

---

## Verificación

```bash
# Tenant existente
curl -s https://api.ejemplo.com/api/v2/public/tenant/miempresa | jq .

# Salida esperada:
# {
#   "data": {
#     "active": true,
#     "name": "Mi Empresa"
#   }
# }
```

---

## Referencia de documentación

- **API Auth / Tenant:** `docs/api-references/autenticacion/README.md`
- **Arquitectura multi-tenant:** `docs/fundamentos/01-Arquitectura-Multi-Tenant.md`
