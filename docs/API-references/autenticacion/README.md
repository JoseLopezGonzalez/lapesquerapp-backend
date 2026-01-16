# Autenticación

Documentación de endpoints de autenticación y gestión de sesión.

## Índice

- [Login](#login)
- [Logout](#logout)
- [Obtener Usuario Actual](#obtener-usuario-actual)

---

## Login

Iniciar sesión en el sistema y obtener token de autenticación.

### Request

```http
POST /api/v2/login
```

#### Headers
```http
X-Tenant: {subdomain}
Content-Type: application/json
```

#### Request Body
```json
{
  "email": "usuario@example.com",
  "password": "contraseña123"
}
```

#### Campos Requeridos
| Campo | Tipo | Descripción |
|-------|------|-------------|
| email | string | Email del usuario |
| password | string | Contraseña del usuario |

### Response Exitosa (200)

```json
{
  "access_token": "1|abcdefghijklmnopqrstuvwxyz1234567890",
  "token_type": "Bearer",
  "user": {
    "id": 1,
    "name": "Juan Pérez",
    "email": "usuario@example.com",
    "assignedStoreId": 1,
    "companyName": "Mi Empresa",
    "companyLogoUrl": "https://example.com/logo.png",
    "role": ["admin"]
  }
}
```

### Response Errónea (401) - Credenciales Inválidas

```json
{
  "message": "Las credenciales proporcionadas son inválidas."
}
```

### Response Errónea (403) - Cuenta Desactivada

```json
{
  "message": "Su cuenta ha sido desactivada. Contacte con el administrador."
}
```

### Response Errónea (422) - Error de Validación

```json
{
  "message": "Error de validación.",
  "userMessage": "El campo email es obligatorio.",
  "errors": {
    "email": ["The email field is required."],
    "password": ["The password field is required."]
  }
}
```

### Rate Limiting
Esta ruta está protegida con rate limiting: máximo 5 intentos por minuto.

---

## Logout

Cerrar sesión y revocar el token de autenticación actual.

### Request

```http
POST /api/v2/logout
```

#### Headers
```http
X-Tenant: {subdomain}
Authorization: Bearer {access_token}
Content-Type: application/json
```

### Response Exitosa (200)

```json
{
  "message": "Sesión cerrada correctamente"
}
```

### Response Errónea (401) - No Autenticado

```json
{
  "message": "No autenticado."
}
```

---

## Obtener Usuario Actual

Obtener información del usuario autenticado actualmente.

### Request

```http
GET /api/v2/me
```

#### Headers
```http
X-Tenant: {subdomain}
Authorization: Bearer {access_token}
```

### Response Exitosa (200)

```json
{
  "id": 1,
  "name": "Juan Pérez",
  "email": "usuario@example.com",
  "assigned_store_id": 1,
  "company_name": "Mi Empresa",
  "company_logo_url": "https://example.com/logo.png",
  "active": true,
  "created_at": "2024-01-01T00:00:00.000000Z",
  "updated_at": "2024-01-01T00:00:00.000000Z",
  "roles": [
    {
      "id": 1,
      "name": "admin",
      "display_name": "Administrador"
    }
  ]
}
```

### Response Errónea (401) - No Autenticado

```json
{
  "message": "No autenticado."
}
```

