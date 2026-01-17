# Sistema

Documentación de endpoints para gestión del sistema: usuarios, roles, sesiones, logs de actividad y configuración.

**Nota:** La mayoría de estos endpoints requieren rol `superuser`.

## Índice

- [Usuarios](#usuarios)
- [Roles](#roles)
- [Sesiones](#sesiones)
- [Logs de Actividad](#logs-de-actividad)
- [Configuración](#configuración)
- [Empleados](#empleados)
- [Fichajes](#fichajes)

---

## Usuarios

**Requiere rol:** `superuser`

### Listar Usuarios

```http
GET /api/v2/users
```

#### Headers
```http
X-Tenant: {subdomain}
Authorization: Bearer {access_token}
```

#### Query Parameters (Opcionales)

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| id | string | Búsqueda por ID (coincidencias parciales) |
| name | string | Búsqueda por nombre (coincidencias parciales) |
| email | string | Búsqueda por email (coincidencias parciales) |
| roles | array | Filtrar por nombres de roles |
| created_at | object | Filtro por fecha: `{start: "2024-01-01", end: "2024-12-31"}` |
| sort | string | Campo por el que ordenar (default: created_at) |
| direction | string | Dirección de ordenamiento: `asc` o `desc` (default: desc) |
| perPage | integer | Elementos por página (default: 10) |

#### Response Exitosa (200)

```json
{
  "data": [
    {
      "id": 1,
      "name": "Usuario Admin",
      "email": "admin@example.com",
      "roles": ["superuser", "admin"],
      "created_at": "2024-01-15T10:00:00",
      "updated_at": "2024-01-15T10:00:00"
    }
  ],
  "current_page": 1,
  "last_page": 5,
  "per_page": 10,
  "total": 50
}
```

**Nota:** El campo `roles` es un array de strings con los nombres de los roles asignados al usuario.

---

### Crear Usuario

```http
POST /api/v2/users
```

#### Headers
```http
X-Tenant: {subdomain}
Authorization: Bearer {access_token}
Content-Type: application/json
```

#### Request Body

```json
{
  "name": "Nuevo Usuario",
  "email": "nuevo@example.com",
  "password": "contraseña123",
  "active": true,
  "role_ids": [1, 2]
}
```

#### Campos Requeridos

| Campo | Tipo | Descripción |
|-------|------|-------------|
| name | string | Nombre del usuario |
| email | string | Email del usuario (único) |
| password | string | Contraseña (mínimo 8 caracteres) |
| role_ids | array | Array de IDs de roles (mínimo: 1) |

#### Campos Opcionales

| Campo | Tipo | Descripción |
|-------|------|-------------|
| active | boolean | Usuario activo (default: true) |

**Nota:** El campo `role_ids` es un array que permite asignar múltiples roles al usuario al crearlo.

#### Response Exitosa (201)

```json
{
  "message": "Usuario creado correctamente.",
  "data": {
    "id": 2,
    "name": "Nuevo Usuario",
    "email": "nuevo@example.com",
    "roles": ["admin", "manager"],
    "created_at": "2024-01-15T10:00:00",
    "updated_at": "2024-01-15T10:00:00"
  }
}
```

#### Response Errónea (422) - Validación

```json
{
  "message": "Error de validación.",
  "userMessage": "El campo role_ids es obligatorio.",
  "errors": {
    "role_ids": ["The role ids field is required."]
  }
}
```

---

### Mostrar Usuario

```http
GET /api/v2/users/{id}
```

#### Headers
```http
X-Tenant: {subdomain}
Authorization: Bearer {access_token}
```

#### Path Parameters

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| id | integer | ID del usuario |

#### Response Exitosa (200)

```json
{
  "message": "Usuario obtenido correctamente.",
  "data": {
    "id": 1,
    "name": "Usuario Admin",
    "email": "admin@example.com",
    "roles": ["admin", "manager"],
    "created_at": "2024-01-15T10:00:00",
    "updated_at": "2024-01-15T10:00:00"
  }
}
```

**Nota:** El campo `roles` es un array de strings con los nombres de los roles asignados al usuario.

---

### Actualizar Usuario

```http
PUT /api/v2/users/{id}
```

#### Headers
```http
X-Tenant: {subdomain}
Authorization: Bearer {access_token}
Content-Type: application/json
```

#### Path Parameters

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| id | integer | ID del usuario |

#### Request Body

```json
{
  "name": "Usuario Actualizado",
  "email": "actualizado@example.com",
  "password": "nueva_contraseña",
  "active": true,
  "role_ids": [1, 2]
}
```

#### Campos Opcionales

| Campo | Tipo | Descripción |
|-------|------|-------------|
| name | string | Nombre del usuario |
| email | string | Email del usuario (único, excepto el mismo) |
| password | string | Contraseña (mínimo 8 caracteres) |
| active | boolean | Usuario activo |
| role_ids | array | Array de IDs de roles (mínimo: 1 si se proporciona) |

**Nota:** 
- Todos los campos son opcionales al actualizar.
- Si se proporciona `role_ids`, reemplazará todos los roles actuales del usuario.
- Si no se proporciona `role_ids`, se mantienen los roles existentes.

#### Response Exitosa (200)

```json
{
  "message": "Usuario actualizado correctamente.",
  "data": {
    "id": 1,
    "name": "Usuario Actualizado",
    "email": "actualizado@example.com",
    "roles": ["admin", "manager"],
    "created_at": "2024-01-15T10:00:00",
    "updated_at": "2024-01-15T11:00:00"
  }
}
```

#### Response Errónea (422) - Validación

```json
{
  "message": "Error de validación.",
  "userMessage": "El campo role_ids debe ser un array.",
  "errors": {
    "role_ids": ["The role ids must be an array."]
  }
}
```

#### Response Errónea (404) - Usuario No Encontrado

```json
{
  "message": "Usuario no encontrado.",
  "userMessage": "El usuario solicitado no existe."
}
```

---

### Eliminar Usuario

```http
DELETE /api/v2/users/{id}
```

#### Response Exitosa (200)

```json
{
  "message": "Usuario eliminado correctamente."
}
```

---

### Opciones de Usuarios

```http
GET /api/v2/users/options
```

#### Headers
```http
X-Tenant: {subdomain}
Authorization: Bearer {access_token}
```

#### Response Exitosa (200)

```json
[
  {
    "id": 1,
    "name": "Usuario Admin"
  },
  {
    "id": 2,
    "name": "Usuario Operador"
  }
]
```

**Descripción:** Devuelve una lista simple de usuarios (id y name) para usar en opciones de formularios.

---

## Roles

**Requiere rol:** `superuser`

### Listar Roles

```http
GET /api/v2/roles
```

#### Response Exitosa (200)

```json
{
  "data": [
    {
      "id": 1,
      "name": "superuser",
      "display_name": "Super Usuario",
      "description": "Superusuario con acceso completo al sistema",
      "created_at": "2024-01-15T10:00:00.000000Z",
      "updated_at": "2024-01-15T10:00:00.000000Z"
    }
  ]
}
```

---

### Crear Rol

```http
POST /api/v2/roles
```

#### Headers
```http
X-Tenant: {subdomain}
Authorization: Bearer {access_token}
Content-Type: application/json
```

#### Request Body

```json
{
  "name": "nuevo_rol",
  "display_name": "Nuevo Rol",
  "description": "Descripción del nuevo rol"
}
```

#### Campos Requeridos

| Campo | Tipo | Descripción |
|-------|------|-------------|
| name | string | Nombre del rol (único, máximo 255 caracteres) |

#### Campos Opcionales

| Campo | Tipo | Descripción |
|-------|------|-------------|
| display_name | string | Nombre para mostrar del rol (máximo 255 caracteres) |
| description | string | Descripción del rol (máximo 1000 caracteres) |

#### Response Exitosa (201)

```json
{
  "message": "Rol creado correctamente.",
  "data": {
    "id": 3,
    "name": "nuevo_rol",
    "display_name": "Nuevo Rol",
    "description": "Descripción del nuevo rol",
    "created_at": "2024-01-15T10:00:00.000000Z",
    "updated_at": "2024-01-15T10:00:00.000000Z"
  }
}
```

#### Response Errónea (422) - Validación

```json
{
  "message": "Error de validación.",
  "userMessage": "El campo name es obligatorio.",
  "errors": {
    "name": ["The name field is required."]
  }
}
```

#### Response Errónea (422) - Nombre Duplicado

```json
{
  "message": "Error de validación.",
  "userMessage": "El nombre del rol ya existe.",
  "errors": {
    "name": ["The name has already been taken."]
  }
}
```

---

### Mostrar Rol

```http
GET /api/v2/roles/{id}
```

#### Headers
```http
X-Tenant: {subdomain}
Authorization: Bearer {access_token}
```

#### Path Parameters

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| id | integer | ID del rol |

#### Response Exitosa (200)

```json
{
  "data": {
    "id": 3,
    "name": "admin",
    "display_name": "Administrador",
    "description": "Administrador con permisos limitados",
    "created_at": "2024-01-15T10:00:00.000000Z",
    "updated_at": "2024-01-15T10:00:00.000000Z"
  }
}
```

#### Response Errónea (404) - Rol No Encontrado

```json
{
  "message": "No query results for model [App\\Models\\Role] 3",
  "userMessage": "El rol solicitado no existe."
}
```

---

### Actualizar Rol

```http
PUT /api/v2/roles/{id}
```

#### Headers
```http
X-Tenant: {subdomain}
Authorization: Bearer {access_token}
Content-Type: application/json
```

#### Path Parameters

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| id | integer | ID del rol |

#### Request Body

```json
{
  "name": "rol_actualizado",
  "display_name": "Rol Actualizado",
  "description": "Descripción actualizada"
}
```

#### Campos Opcionales

| Campo | Tipo | Descripción |
|-------|------|-------------|
| name | string | Nombre del rol (único, máximo 255 caracteres) |
| display_name | string | Nombre para mostrar del rol (máximo 255 caracteres) |
| description | string | Descripción del rol (máximo 1000 caracteres) |

#### Response Exitosa (200)

```json
{
  "message": "Rol actualizado correctamente.",
  "data": {
    "id": 3,
    "name": "rol_actualizado",
    "display_name": "Rol Actualizado",
    "description": "Descripción actualizada",
    "created_at": "2024-01-15T10:00:00.000000Z",
    "updated_at": "2024-01-15T11:00:00.000000Z"
  }
}
```

#### Response Errónea (404) - Rol No Encontrado

```json
{
  "message": "No query results for model [App\\Models\\Role] 3",
  "userMessage": "El rol solicitado no existe."
}
```

---

### Eliminar Rol

```http
DELETE /api/v2/roles/{id}
```

#### Headers
```http
X-Tenant: {subdomain}
Authorization: Bearer {access_token}
```

#### Path Parameters

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| id | integer | ID del rol |

#### Response Exitosa (200)

```json
{
  "message": "Rol eliminado correctamente."
}
```

#### Response Errónea (400) - Tiene Usuarios Asignados

```json
{
  "message": "No se puede eliminar el rol porque tiene usuarios asignados.",
  "userMessage": "Existen usuarios con este rol. Debe desasignarlos primero."
}
```

#### Response Errónea (404) - Rol No Encontrado

```json
{
  "message": "No query results for model [App\\Models\\Role] 3",
  "userMessage": "El rol solicitado no existe."
}
```

**Descripción:** Elimina un rol. No permite eliminar roles que tienen usuarios asignados.

---

### Opciones de Roles

```http
GET /api/v2/roles/options
```

#### Headers
```http
X-Tenant: {subdomain}
Authorization: Bearer {access_token}
```

#### Response Exitosa (200)

```json
[
  {
    "id": 1,
    "name": "superuser"
  },
  {
    "id": 2,
    "name": "manager"
  }
]
```

**Descripción:** Devuelve una lista simple de roles (id y name) para usar en opciones de formularios.

---

## Sesiones

**Requiere rol:** `superuser`

### Listar Sesiones Activas

```http
GET /api/v2/sessions
```

#### Response Exitosa (200)

```json
{
  "data": [
    {
      "id": 1,
      "user": {
        "id": 1,
        "name": "Usuario Admin",
        "email": "admin@example.com"
      },
      "ip_address": "192.168.1.100",
      "user_agent": "Mozilla/5.0...",
      "last_activity": "2024-01-15T12:00:00.000000Z",
      "created_at": "2024-01-15T10:00:00.000000Z"
    }
  ]
}
```

---

### Revocar Sesión

```http
DELETE /api/v2/sessions/{id}
```

#### Response Exitosa (200)

```json
{
  "message": "Sesión revocada correctamente."
}
```

---

## Logs de Actividad

**Requiere rol:** `superuser`

### Listar Logs de Actividad

```http
GET /api/v2/activity-logs
```

#### Query Parameters (Opcionales)

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| user_id | integer | ID del usuario |
| event | string | Tipo de evento |
| startDate | date | Fecha de inicio |
| endDate | date | Fecha de fin |
| perPage | integer | Elementos por página |

#### Response Exitosa (200)

```json
{
  "data": [
    {
      "id": 1,
      "user": {
        "id": 1,
        "name": "Usuario Admin"
      },
      "event": "created",
      "auditable_type": "App\\Models\\Order",
      "auditable_id": 1,
      "old_values": null,
      "new_values": {
        "customer_id": 1,
        "status": "pending"
      },
      "created_at": "2024-01-15T10:00:00.000000Z"
    }
  ]
}
```

---

### Mostrar Log

```http
GET /api/v2/activity-logs/{id}
```

---

## Configuración

### Obtener Configuración

```http
GET /api/v2/settings
```

#### Response Exitosa (200)

```json
{
  "data": {
    "key1": "value1",
    "key2": "value2",
    "company_name": "Mi Empresa",
    "company_logo": "https://example.com/logo.png"
  }
}
```

---

### Actualizar Configuración

```http
PUT /api/v2/settings
```

#### Request Body

```json
{
  "company_name": "Mi Empresa Actualizada",
  "company_logo": "https://example.com/nuevo-logo.png",
  "key1": "new_value1"
}
```

#### Response Exitosa (200)

```json
{
  "message": "Configuración actualizada correctamente.",
  "data": {
    "company_name": "Mi Empresa Actualizada",
    "key1": "new_value1"
  }
}
```

---

## Empleados

### Listar Empleados

```http
GET /api/v2/employees
```

#### Response Exitosa (200)

```json
{
  "data": [
    {
      "id": 1,
      "name": "Juan Pérez",
      "nfc_uid": "ABC123",
      "active": true,
      "created_at": "2024-01-15T10:00:00.000000Z"
    }
  ]
}
```

---

### Crear Empleado

```http
POST /api/v2/employees
```

#### Request Body

```json
{
  "name": "Juan Pérez",
  "nfc_uid": "ABC123",
  "active": true
}
```

#### Campos Requeridos

| Campo | Tipo | Descripción |
|-------|------|-------------|
| name | string | Nombre del empleado |
| nfc_uid | string | UID de la tarjeta NFC (único) |

---

### Mostrar Empleado

```http
GET /api/v2/employees/{id}
```

---

### Actualizar Empleado

```http
PUT /api/v2/employees/{id}
```

---

### Eliminar Empleado

```http
DELETE /api/v2/employees/{id}
```

---

### Eliminar Múltiples Empleados

```http
DELETE /api/v2/employees
```

#### Request Body

```json
{
  "ids": [1, 2, 3]
}
```

---

### Opciones de Empleados

```http
GET /api/v2/employees/options
```

#### Response Exitosa (200)

```json
[
  {
    "id": 1,
    "name": "Juan Pérez"
  }
]
```

---

## Fichajes

### Crear Fichaje (Público)

```http
POST /api/v2/punches
```

**Nota:** Esta ruta es pública (sin autenticación) para permitir que dispositivos NFC registren fichajes.

#### Headers
```http
X-Tenant: {subdomain}
Content-Type: application/json
```

#### Request Body

```json
{
  "uid": "ABC123",
  "device_id": "raspberry-pi-entrada-principal",
  "event_type": "IN"
}
```

#### Campos Requeridos

| Campo | Tipo | Descripción |
|-------|------|-------------|
| uid | string | UID de la tarjeta NFC del empleado |
| device_id | string | Identificador del dispositivo |
| event_type | string | Tipo de evento (`IN` o `OUT`) |

#### Response Exitosa (200)

```json
{
  "message": "Fichaje registrado correctamente.",
  "data": {
    "employee_name": "Juan Pérez",
    "event_type": "IN",
    "timestamp": "2024-01-15 08:30:00",
    "device_id": "raspberry-pi-entrada-principal"
  }
}
```

---

### Listar Fichajes

```http
GET /api/v2/punches
```

#### Query Parameters (Opcionales)

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| employee_id | integer | ID del empleado |
| startDate | date | Fecha de inicio |
| endDate | date | Fecha de fin |
| event_type | string | Tipo de evento (`IN` o `OUT`) |

---

### Mostrar Fichaje

```http
GET /api/v2/punches/{id}
```

---

### Actualizar Fichaje

```http
PUT /api/v2/punches/{id}
```

---

### Eliminar Fichaje

```http
DELETE /api/v2/punches/{id}
```

---

### Eliminar Múltiples Fichajes

```http
DELETE /api/v2/punches
```

#### Request Body

```json
{
  "ids": [1, 2, 3]
}
```

---

### Dashboard de Fichajes

```http
GET /api/v2/punches/dashboard
```

#### Response Exitosa (200)

```json
{
  "data": {
    "today": {
      "total_punches": 50,
      "employees_checked_in": 25,
      "employees_checked_out": 25
    },
    "this_week": {
      "total_punches": 300,
      "employees_checked_in": 150
    }
  }
}
```

---

## Respuestas Erróneas

### Error de Autenticación (401)

```json
{
  "message": "No autenticado."
}
```

### Error de Permisos (403)

```json
{
  "message": "No tiene permisos para realizar esta acción."
}
```

### Error de Validación (422)

```json
{
  "message": "Error de validación.",
  "userMessage": "El campo name es obligatorio.",
  "errors": {
    "name": ["The name field is required."],
    "email": ["The email has already been taken."]
  }
}
```

### Error de Solicitud Incorrecta (400)

```json
{
  "message": "No se han proporcionado IDs válidos.",
  "userMessage": "Debe proporcionar al menos un ID válido para eliminar."
}
```

### Recurso No Encontrado (404)

```json
{
  "message": "Sesión no encontrada.",
  "userMessage": "La sesión especificada no existe o ya fue cerrada."
}
```

---

## Headers Requeridos

Todos los endpoints requieren:

```http
X-Tenant: {subdomain}
Authorization: Bearer {access_token}
Content-Type: application/json  (para POST/PUT)
```

**Excepción:** `POST /api/v2/punches` es pública y no requiere `Authorization`.

