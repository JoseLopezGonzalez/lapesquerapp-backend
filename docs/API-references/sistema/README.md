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

#### Response Exitosa (200)

```json
{
  "data": [
    {
      "id": 1,
      "name": "Usuario Admin",
      "email": "admin@example.com",
      "active": true,
      "roles": [
        {
          "id": 1,
          "name": "superuser"
        }
      ],
      "created_at": "2024-01-15T10:00:00.000000Z"
    }
  ]
}
```

---

### Crear Usuario

```http
POST /api/v2/users
```

#### Request Body

```json
{
  "name": "Nuevo Usuario",
  "email": "nuevo@example.com",
  "password": "contraseña123",
  "password_confirmation": "contraseña123",
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
| password_confirmation | string | Confirmación de contraseña |

#### Campos Opcionales

| Campo | Tipo | Descripción |
|-------|------|-------------|
| active | boolean | Usuario activo (default: true) |
| role_ids | array | IDs de roles asignados |

#### Response Exitosa (201)

```json
{
  "data": {
    "id": 2,
    "name": "Nuevo Usuario",
    "email": "nuevo@example.com",
    "active": true,
    "created_at": "2024-01-15T10:00:00.000000Z"
  }
}
```

---

### Mostrar Usuario

```http
GET /api/v2/users/{id}
```

#### Response Exitosa (200)

```json
{
  "data": {
    "id": 1,
    "name": "Usuario Admin",
    "email": "admin@example.com",
    "active": true,
    "roles": [...],
    "assigned_store_id": 1,
    "created_at": "2024-01-15T10:00:00.000000Z",
    "updated_at": "2024-01-15T10:00:00.000000Z"
  }
}
```

---

### Actualizar Usuario

```http
PUT /api/v2/users/{id}
```

#### Request Body

```json
{
  "name": "Usuario Actualizado",
  "email": "actualizado@example.com",
  "password": "nueva_contraseña",
  "password_confirmation": "nueva_contraseña",
  "active": true,
  "role_ids": [1]
}
```

**Nota:** El campo `password` es opcional al actualizar.

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
      "created_at": "2024-01-15T10:00:00.000000Z"
    }
  ]
}
```

---

### Crear Rol

```http
POST /api/v2/roles
```

#### Request Body

```json
{
  "name": "nuevo_rol",
  "display_name": "Nuevo Rol"
}
```

#### Campos Requeridos

| Campo | Tipo | Descripción |
|-------|------|-------------|
| name | string | Nombre del rol (único, formato snake_case) |
| display_name | string | Nombre para mostrar |

---

### Mostrar Rol

```http
GET /api/v2/roles/{id}
```

---

### Actualizar Rol

```http
PUT /api/v2/roles/{id}
```

---

### Eliminar Rol

```http
DELETE /api/v2/roles/{id}
```

---

### Opciones de Roles

```http
GET /api/v2/roles/options
```

#### Response Exitosa (200)

```json
[
  {
    "id": 1,
    "name": "superuser",
    "display_name": "Super Usuario"
  }
]
```

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

