# User Management API — Guía de integración frontend

**Base path:** `/api/v2/`  
**Headers obligatorios:** `X-Tenant: <subdomain>` · `Authorization: Bearer <token>`  
**Última revisión:** 2026-06-25

---

## Tabla de contenidos

1. [Matriz de acceso por rol](#1-matriz-de-acceso-por-rol)
2. [Endpoints](#2-endpoints)
3. [Formatos de request y response](#3-formatos-de-request-y-response)
4. [Reglas de negocio críticas](#4-reglas-de-negocio-críticas)
5. [Flujo completo: crear e invitar a un usuario](#5-flujo-completo-crear-e-invitar-a-un-usuario)
6. [Catálogo de errores](#6-catálogo-de-errores)
7. [Checklist de auditoría frontend](#7-checklist-de-auditoría-frontend)

---

## 1. Matriz de acceso por rol

La gestión de usuarios está restringida a roles de gestión. Los roles operativos (`comercial`, `operario`, `repartidor_autoventa`) no tienen acceso a ninguna operación de esta sección.

| Operación | `administrador` | `tecnico` | `direccion` | `administracion` | `supervisor` | `comercial` | `operario` | `repartidor_autoventa` |
|-----------|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| Listar usuarios | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| Ver usuario | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| Crear usuario | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| Actualizar usuario | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| Eliminar usuario | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Reenviar invitación | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| Opciones (dropdown) | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |

### Restricciones adicionales en asignación de roles

| Rol del gestor | Puede asignar |
|----------------|---------------|
| `administrador` / `tecnico` | Cualquier rol (incluido `administrador` y `tecnico`) |
| `direccion` / `administracion` / `supervisor` | Cualquier rol **excepto** `administrador` y `tecnico` |

Un usuario **nunca puede cambiar su propio rol** independientemente del rol que tenga.

---

## 2. Endpoints

### 2.1 Listar usuarios

```
GET /api/v2/users
```

**Query params (todos opcionales):**

| Param | Tipo | Descripción |
|-------|------|-------------|
| `id` | string | Búsqueda parcial (LIKE) por ID |
| `name` | string | Búsqueda parcial (LIKE) por nombre |
| `email` | string | Búsqueda parcial (LIKE) por email |
| `role` | string | Filtro exacto por rol |
| `created_at[start]` | string | Fecha inicio (`YYYY-MM-DD`), inclusivo desde las 00:00:00 |
| `created_at[end]` | string | Fecha fin (`YYYY-MM-DD`), inclusivo hasta las 23:59:59 |
| `sort` | string | Campo de ordenación: `name`, `email`, `role`, `created_at` (default: `created_at`) |
| `direction` | string | `asc` o `desc` (default: `desc`) |
| `perPage` | integer | Resultados por página, 1–100 (default: `10`) |

**Response 200:**
```json
{
  "data": [
    {
      "id": 1,
      "name": "María García",
      "email": "maria@empresa.com",
      "role": "administracion",
      "active": true,
      "created_at": "2025-01-15T10:00:00+00:00",
      "updated_at": "2026-06-20T08:30:00+00:00"
    }
  ],
  "links": {
    "first": "...",
    "last": "...",
    "prev": null,
    "next": "..."
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 3,
    "per_page": 10,
    "to": 10,
    "total": 25
  }
}
```

---

### 2.2 Crear usuario

```
POST /api/v2/users
Content-Type: application/json
```

**Body:**
```json
{
  "name": "Juan Rodríguez",
  "email": "juan@empresa.com",
  "role": "supervisor",
  "active": true
}
```

| Campo | Tipo | Req. | Validación |
|-------|------|------|------------|
| `name` | string | ✅ | max 255 caracteres |
| `email` | string | ✅ | Formato email válido · único en usuarios activos · no puede existir en usuarios externos del tenant |
| `role` | string | ✅ | Valor válido del enum (ver §4.1) · sujeto a jerarquía de roles del gestor |
| `active` | boolean | ❌ | Default: `true` |

**Response 201:**
```json
{
  "message": "Usuario creado correctamente.",
  "data": {
    "id": 42,
    "name": "Juan Rodríguez",
    "email": "juan@empresa.com",
    "role": "supervisor",
    "active": true,
    "created_at": "2026-06-25T09:00:00+00:00",
    "updated_at": "2026-06-25T09:00:00+00:00"
  }
}
```

> **Importante:** El usuario se crea sin contraseña y **sin recibir ningún email automático**. Para que pueda acceder al sistema hay que llamar explícitamente al endpoint de reenvío de invitación (§2.5) después de crear el usuario.

---

### 2.3 Ver usuario

```
GET /api/v2/users/{id}
```

**Response 200:**
```json
{
  "message": "Usuario obtenido correctamente.",
  "data": {
    "id": 42,
    "name": "Juan Rodríguez",
    "email": "juan@empresa.com",
    "role": "supervisor",
    "active": true,
    "created_at": "2026-06-25T09:00:00+00:00",
    "updated_at": "2026-06-25T09:00:00+00:00"
  }
}
```

---

### 2.4 Actualizar usuario

```
PUT /api/v2/users/{id}
Content-Type: application/json
```

Todos los campos son opcionales (PATCH semántico). Solo se actualizan los campos enviados.

| Campo | Tipo | Validación |
|-------|------|------------|
| `name` | string | No vacío · max 255 caracteres |
| `email` | string | Formato email · único (ignorando el propio) · no puede existir en usuarios externos |
| `role` | string | Valor válido del enum · sujeto a jerarquía · **no se puede cambiar el rol propio** |
| `active` | boolean | — |

**Response 200:**
```json
{
  "message": "Usuario actualizado correctamente.",
  "data": { ... }
}
```

> Si se intenta cambiar el propio rol la API responde `422` con `"No puedes cambiar tu propio rol."`.

---

### 2.5 Reenviar invitación (magic link)

```
POST /api/v2/users/{id}/resend-invitation
```

Sin body. Envía al email del usuario un mensaje con un **magic link** y un **código OTP de 6 dígitos**, ambos válidos durante **10 minutos**. Los tokens anteriores se invalidan automáticamente antes de generar los nuevos.

Solo funciona si el usuario tiene `active: true`.

**Response 200:**
```json
{
  "message": "Se ha enviado un enlace de acceso al correo del usuario."
}
```

**Errores específicos:**

| Código | Causa |
|--------|-------|
| `403` | El usuario está desactivado (`active: false`) |
| `500` | Error de configuración de email del tenant o FRONTEND_URL no configurada |

---

### 2.6 Eliminar usuario

```
DELETE /api/v2/users/{id}
```

Soft delete: el usuario queda marcado como eliminado (`deleted_at`) y todos sus tokens Sanctum son revocados. No se puede recuperar vía API.

**Restricciones:**
- Solo `administrador` y `tecnico` pueden eliminar.
- Nadie puede eliminarse a sí mismo.

**Response 200:**
```json
{
  "message": "Usuario eliminado correctamente."
}
```

---

### 2.7 Opciones para dropdown

```
GET /api/v2/users/options
```

Devuelve solo los usuarios **activos** con `id` y `name`. Útil para selectores.

**Response 200:**
```json
[
  { "id": 1, "name": "María García" },
  { "id": 2, "name": "Juan Rodríguez" }
]
```

> Nota: este endpoint no está paginado. Solo muestra usuarios activos.

---

## 3. Formatos de request y response

### 3.1 Enum de roles

Valores válidos para el campo `role`:

| Valor | Label sugerido |
|-------|----------------|
| `administrador` | Administrador |
| `tecnico` | Técnico |
| `direccion` | Dirección |
| `administracion` | Administración |
| `supervisor` | Supervisor |
| `comercial` | Comercial |
| `operario` | Operario |
| `repartidor_autoventa` | Repartidor / Autoventa |

### 3.2 Estructura de error de validación (422)

```json
{
  "message": "The name field is required.",
  "errors": {
    "name": ["El campo nombre es obligatorio."],
    "email": [
      "El email ya está en uso.",
      "El email ya está en uso por un usuario externo del tenant."
    ],
    "role": ["No tienes permisos para asignar el rol de administrador o técnico."]
  }
}
```

### 3.3 Estructura de error de autorización (403)

```json
{
  "message": "Acción no autorizada.",
  "userMessage": "No tienes permiso para acceder a esta ruta."
}
```

---

## 4. Reglas de negocio críticas

### 4.1 Roles disponibles y restricciones de creación

El frontend **debe filtrar el selector de rol** en el formulario de creación/edición según el rol del usuario autenticado:

- Si el gestor es `direccion`, `administracion` o `supervisor`: **ocultar** las opciones `administrador` y `tecnico` del selector.
- Si el gestor es `administrador` o `tecnico`: mostrar todos los roles.

Si el frontend no filtra y el usuario intenta asignar un rol no permitido, la API responderá `422`.

### 4.2 Unicidad de email cross-actor

El email de un usuario interno (`/users`) **no puede coincidir** con el email de ningún usuario externo (`/external-users`) del mismo tenant, ni siquiera aunque el usuario interno esté eliminado (soft delete).

### 4.3 El usuario creado no tiene acceso hasta recibir la invitación

El flujo **obligatorio** para que un usuario recién creado pueda entrar al sistema es:

```
POST /users          →  usuario creado, sin acceso aún
POST /users/{id}/resend-invitation  →  email enviado con magic link + OTP
```

Si el gestor crea el usuario con `active: false`, el email de invitación **no se puede enviar** hasta que el usuario sea activado (`PUT /users/{id} {"active": true}`).

### 4.4 Tokens de acceso

- El magic link expira en **10 minutos**.
- El OTP expira en **10 minutos**.
- Cada vez que se llama a `resend-invitation`, los tokens anteriores quedan **invalidados inmediatamente**.
- El usuario puede usar el magic link (clic en el email) o el OTP (código de 6 dígitos) de forma equivalente.

### 4.5 Auto-modificación de rol

Un usuario **nunca puede cambiar su propio rol** vía `PUT /users/{id}`. Si el frontend renderiza el formulario de edición para el usuario autenticado, debe **deshabilitar o eliminar el campo `role`**.

### 4.6 Eliminación

- Solo `administrador` y `tecnico` ven el botón de eliminar.
- El botón de eliminar debe estar **deshabilitado o ausente** cuando el usuario mostrado es el propio usuario autenticado.
- Tras eliminar, los tokens del usuario quedan revocados: si el usuario eliminado tenía sesiones abiertas, quedará desconectado en su próxima petición.

---

## 5. Flujo completo: crear e invitar a un usuario

```
Frontend                          Backend
   |                                 |
   |  POST /api/v2/users             |
   |  { name, email, role, active }  |
   |-------------------------------->|
   |                                 |  Valida campos
   |                                 |  Comprueba unicidad email
   |                                 |  Comprueba jerarquía de rol
   |                                 |  Crea User (sin password)
   |  201 { data: UserResource }     |
   |<--------------------------------|
   |                                 |
   |  [Opcional: mostrar confirmación|
   |   con botón "Enviar invitación"]|
   |                                 |
   |  POST /api/v2/users/{id}        |
   |  /resend-invitation             |
   |-------------------------------->|
   |                                 |  Invalida tokens anteriores
   |                                 |  Genera magic link token (sha256)
   |                                 |  Genera OTP de 6 dígitos
   |                                 |  Ambos expiran en 10 min
   |                                 |  Envía AccessEmail (link + OTP)
   |  200 { message }                |
   |<--------------------------------|
   |                                 |
   |  [Mostrar: "Invitación enviada  |
   |   a juan@empresa.com"]          |
```

**El usuario en su email:**
1. Hace clic en el magic link → autenticado directamente.
2. O bien introduce el código OTP de 6 dígitos en el campo de la pantalla de login.

---

## 6. Catálogo de errores

| HTTP | Causa | Qué mostrar al usuario |
|------|-------|------------------------|
| `401` | Token Sanctum ausente o expirado | Redirigir a login |
| `403` | Rol insuficiente para la operación | "No tienes permisos para realizar esta acción" |
| `403` (resend) | Usuario destino está desactivado | "No se puede enviar invitación a un usuario desactivado" |
| `404` | Usuario no encontrado | "Usuario no encontrado" |
| `422` | Error de validación (ver §3.2) | Mostrar mensajes del campo `errors` bajo cada input |
| `422` (role) | Jerarquía de rol no permitida | Mostrar bajo el selector de rol |
| `422` (role) | Intento de cambiar rol propio | Mostrar bajo el selector de rol |
| `500` | Error de configuración de email | "No se pudo enviar el correo. Contacta al administrador." |

---

## 7. Checklist de auditoría frontend

### Listado de usuarios

- [ ] La sección de gestión de usuarios **no es visible** para `comercial`, `operario` y `repartidor_autoventa`.
- [ ] Los filtros de búsqueda (`name`, `email`, `role`, `created_at`) se envían como query params correctamente.
- [ ] El filtro de rango de fechas se envía como `created_at[start]` y `created_at[end]`.
- [ ] La paginación usa el parámetro `perPage` y el frontend respeta el `meta.total` para calcular páginas.

### Formulario de creación

- [ ] El formulario solo es accesible para roles con permiso (`administrador`, `tecnico`, `direccion`, `administracion`, `supervisor`).
- [ ] El selector de rol **filtra las opciones** según el rol del usuario autenticado (§4.1).
- [ ] El campo `active` tiene valor `true` por defecto.
- [ ] Tras crear el usuario, el flujo **ofrece o ejecuta automáticamente** el envío de invitación.
- [ ] Los errores `422` se muestran bajo los campos correspondientes.

### Formulario de edición

- [ ] El campo `role` está **deshabilitado** cuando se edita el perfil del usuario autenticado.
- [ ] El selector de rol **filtra las opciones** según el rol del usuario autenticado (§4.1), también en edición.
- [ ] Se envía solo el `PUT` con los campos modificados (no se envían campos no tocados con `null`).
- [ ] Al desactivar un usuario (`active: false`), se refleja visualmente en el listado.

### Reenviar invitación

- [ ] El botón solo aparece si el usuario está **activo** (`active: true`).
- [ ] Tras el envío, se muestra confirmación con el email de destino.
- [ ] El frontend gestiona el error `500` con un mensaje claro sobre configuración de correo.

### Eliminación

- [ ] El botón de eliminar solo está visible para `administrador` y `tecnico`.
- [ ] El botón de eliminar está **ausente o deshabilitado** para el propio usuario autenticado.
- [ ] Tras eliminar, el usuario se retira del listado local (o se recarga la lista).

### Dropdown de opciones (`/users/options`)

- [ ] El endpoint solo se llama desde vistas accesibles a roles de gestión.
- [ ] El dropdown solo muestra usuarios activos (el backend ya filtra, pero verificar que no se cacheen datos con usuarios desactivados).
