# Sistema

Documentación de endpoints para gestión del sistema: usuarios, roles, sesiones, logs de actividad y configuración.

**Nota:** La mayoría de estos endpoints requieren rol `tecnico`.

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

**Requiere rol:** `tecnico`

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
| role | string | Filtrar por rol (valor del enum: tecnico, administrador, direccion, administracion, comercial, operario) |
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
      "role": "tecnico",
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

**Nota:** El campo `role` es un string (valor del enum).

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

#### Request Body (sin campo password; el acceso es por magic link u OTP)

```json
{
  "name": "Nuevo Usuario",
  "email": "nuevo@example.com",
  "role": "administracion",
  "active": true
}
```

#### Campos Requeridos

| Campo | Tipo | Descripción |
|-------|------|-------------|
| name | string | Nombre del usuario |
| email | string | Email del usuario (único) |
| role | string | Rol: `tecnico`, `administrador`, `direccion`, `administracion`, `comercial`, `operario` |

#### Campos Opcionales

| Campo | Tipo | Descripción |
|-------|------|-------------|
| active | boolean | Usuario activo (default: true) |

**Nota:** No hay campo `password`. Los usuarios entran por magic link u OTP. Usar el endpoint **POST /api/v2/users/{id}/resend-invitation** para enviarles el enlace de acceso.

#### Response Exitosa (201)

```json
{
  "message": "Usuario creado correctamente.",
  "data": {
    "id": 2,
    "name": "Nuevo Usuario",
    "email": "nuevo@example.com",
    "role": "administracion",
    "created_at": "2024-01-15T10:00:00",
    "updated_at": "2024-01-15T10:00:00"
  }
}
```

#### Response Errónea (422) - Validación

```json
{
  "message": "Error de validación.",
  "userMessage": "El campo role es obligatorio.",
  "errors": {
    "role": ["The role field is required."]
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
    "role": "administracion",
    "created_at": "2024-01-15T10:00:00",
    "updated_at": "2024-01-15T10:00:00"
  }
}
```

**Nota:** El campo `role` es un string (valor del enum).

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

#### Request Body (sin campo password)

```json
{
  "name": "Usuario Actualizado",
  "email": "actualizado@example.com",
  "active": true,
  "role": "administracion"
}
```

#### Campos Opcionales

| Campo | Tipo | Descripción |
|-------|------|-------------|
| name | string | Nombre del usuario |
| email | string | Email del usuario (único, excepto el mismo) |
| active | boolean | Usuario activo |
| role | string | Rol: `tecnico`, `administrador`, `direccion`, `administracion`, `comercial`, `operario` |

**Nota:** Todos los campos son opcionales. No hay campo `password`.

#### Response Exitosa (200)

```json
{
  "message": "Usuario actualizado correctamente.",
  "data": {
    "id": 1,
    "name": "Usuario Actualizado",
    "email": "actualizado@example.com",
    "role": "administracion",
    "created_at": "2024-01-15T10:00:00",
    "updated_at": "2024-01-15T11:00:00"
  }
}
```

#### Response Errónea (422) - Validación

```json
{
  "message": "Error de validación.",
  "userMessage": "El campo role no es válido.",
  "errors": {
    "role": ["The selected role is invalid."]
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

Los roles están fijados en código (enum). No existe CRUD de roles; solo el endpoint de opciones para selects.

**Requiere rol:** `tecnico`

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
  { "id": "tecnico", "name": "Técnico" },
  { "id": "administrador", "name": "Administrador" },
  { "id": "direccion", "name": "Dirección" },
  { "id": "administracion", "name": "Administración" },
  { "id": "comercial", "name": "Comercial" },
  { "id": "operario", "name": "Operario" }
]
```

**Descripción:** Lista de roles para desplegables (crear/editar usuario). El valor a enviar en el campo `role` del usuario es el `id` (string).

**Endpoints eliminados:** `GET/POST/PUT/DELETE /api/v2/roles` y `GET /api/v2/roles/{id}` ya no existen.

---

## Sesiones

**Requiere rol:** `tecnico`

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

