# API de Perfil de Usuario

Documentación del circuito de lectura y edición del perfil del usuario autenticado.

---

## BREAKING CHANGE: Eliminación de campos deprecated

Los campos `company_name` y `company_logo_url` han sido **eliminados permanentemente** de la respuesta de `/api/v2/me` y de cualquier endpoint relacionado con usuarios internos (`User`).

Estos campos eran un vestigio de una implementación anterior a que existiera el sistema de Settings y los usuarios externos. El nombre y logo de la empresa del tenant están disponibles vía `GET /api/v2/settings` con las claves `company.name` y `company.logo_url`.

### Campos eliminados de `GET /api/v2/me`

```diff
- "company_name": "Algar Seafood",
- "companyName": "Algar Seafood",
- "company_logo_url": null,
- "companyLogoUrl": null,
```

Si el frontend mostraba el nombre o logo de empresa leyéndolo del payload de autenticación, debe migrarlo a:

```
GET /api/v2/settings
→ "company.name"
→ "company.logo_url"
```

---

## GET /api/v2/me — Perfil del usuario autenticado

Sin cambios de contrato salvo la eliminación de los campos deprecated arriba indicados.

**Headers requeridos:**
```
X-Tenant: {subdomain}
Authorization: Bearer {token}
```

**Respuesta 200:**
```json
{
  "id": 1,
  "name": "Maria García",
  "email": "maria@empresa.com",
  "role": "administrador",
  "active": true,
  "assigned_store_id": null,
  "assignedStoreId": null,
  "salespersonId": null,
  "fieldOperatorId": null,
  "isFieldOperator": false,
  "actorType": "internal_user",
  "externalUserType": null,
  "allowedStoreIds": [],
  "created_at": "2025-01-15T10:00:00Z",
  "updated_at": "2026-06-24T08:30:00Z",
  "features": [ ... ]
}
```

---

## PUT /api/v2/me — Editar perfil propio

Permite a cualquier usuario interno autenticado actualizar su propio nombre y/o email.

> Solo disponible para usuarios internos (`actorType: "internal_user"`). Los usuarios externos no tienen acceso a este endpoint.

**Headers requeridos:**
```
X-Tenant: {subdomain}
Authorization: Bearer {token}
Content-Type: application/json
```

**Body (todos los campos son opcionales):**
```json
{
  "name": "Maria García López",
  "email": "maria.garcia@empresa.com"
}
```

**Respuesta 200 — Perfil actualizado:**
```json
{
  "id": 1,
  "name": "Maria García López",
  "email": "maria.garcia@empresa.com",
  "role": "administrador",
  "active": true,
  "created_at": "2025-01-15T10:00:00+00:00",
  "updated_at": "2026-06-24T09:00:00+00:00"
}
```

> La respuesta del `PUT /api/v2/me` devuelve un subconjunto de campos (el `UserResource`). Si el frontend necesita el payload completo de sesión tras la actualización, puede hacer un `GET /api/v2/me` inmediatamente después.

**Respuesta 422 — Validación fallida:**
```json
{
  "message": "El email ya está en uso.",
  "errors": {
    "email": ["El email ya está en uso."]
  }
}
```

**Respuesta 403 — Intento de acceso por usuario externo:**
```json
{
  "message": "This action is unauthorized."
}
```

### Reglas de validación

| Campo | Tipo | Reglas |
|-------|------|--------|
| `name` | string | Opcional. Máx. 255 caracteres. |
| `email` | string | Opcional. Formato email válido. Único en el tenant (no puede coincidir con otro usuario interno ni externo). |

### Campos NO editables por el usuario

Los siguientes campos solo pueden ser modificados por un administrador vía `PUT /api/v2/users/{id}`:

- `role` — rol del usuario
- `active` — estado activo/inactivo
- `assigned_store_id` — tienda asignada

---

## Flujo recomendado en el frontend

```
1. Obtener datos actuales:   GET  /api/v2/me
2. Mostrar formulario con name y email actuales
3. Enviar cambios:           PUT  /api/v2/me  { name?, email? }
4. Actualizar estado local con la respuesta
5. Si necesitas el payload completo:  GET /api/v2/me  (refetch)
```

---

## Notas de implementación

- El endpoint usa validación `sometimes`, por lo que puedes enviar solo el campo que cambia.
- Si el usuario no cambia el email, no es necesario incluirlo en el body.
- Si se envía un email ya en uso por otro usuario (interno o externo del mismo tenant), el servidor devuelve 422.
- El `PUT /api/v2/me` no renueva el token; la sesión activa sigue siendo válida.
