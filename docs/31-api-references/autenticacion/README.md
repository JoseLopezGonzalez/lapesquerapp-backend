# Autenticación

Documentación de endpoints de autenticación y gestión de sesión. **El acceso es solo por Magic Link u OTP** (no hay login con contraseña).

## Índice

- [Magic Link: solicitar](#magic-link-solicitar)
- [Magic Link: canjear](#magic-link-canjear)
- [OTP: solicitar](#otp-solicitar)
- [OTP: canjear](#otp-canjear)
- [Login con contraseña (obsoleto)](#login-con-contraseña-obsoleto)
- [Logout](#logout)
- [Obtener Usuario Actual](#obtener-usuario-actual)
- [Obtener Información de Tenant](#obtener-información-de-tenant)

---

## Magic Link: solicitar

El usuario introduce su email; se envía un enlace por correo. **Throttle:** 5/min.

**POST** `/api/v2/auth/magic-link/request`

Headers: `X-Tenant`, `Content-Type: application/json`

Body: `{ "email": "usuario@example.com" }`

**200:** `{ "message": "Si el correo está registrado y activo, recibirás un enlace para iniciar sesión." }`  
**500:** Error de envío o configuración.

---

## Magic Link: canjear

Tras el clic en el enlace del correo, el frontend llama a este endpoint con el token. **Throttle:** 10/min.

**POST** `/api/v2/auth/magic-link/verify`

Body: `{ "token": "..." }`

**200:** `{ "access_token": "...", "token_type": "Bearer", "user": { "id", "name", "email", "assignedStoreId", "companyName", "companyLogoUrl", "role" } }`  
**400:** Enlace no válido o expirado. **403:** Usuario desactivado.

---

## OTP: solicitar

El usuario introduce su email; se envía un código de 6 dígitos por correo. **Throttle:** 5/min.

**POST** `/api/v2/auth/otp/request`

Body: `{ "email": "usuario@example.com" }`

**200:** Mismo mensaje que magic link (no se revela si el email existe).

---

## OTP: canjear

El usuario introduce el código recibido por correo. **Throttle:** 10/min.

**POST** `/api/v2/auth/otp/verify`

Body: `{ "email": "usuario@example.com", "code": "123456" }`

**200:** Misma estructura que magic-link/verify (`access_token`, `user`).  
**400:** Código no válido o expirado. **403:** Usuario desactivado.

---

## Login con contraseña (obsoleto)

**POST** `/api/v2/login` ya no permite acceso con contraseña. Cualquier petición recibe **400** con:

```json
{
  "message": "El acceso se realiza mediante enlace o código enviado por correo. Usa \"Enviar enlace\" o \"Enviar código\" en la pantalla de inicio de sesión."
}
```

No usar este endpoint para iniciar sesión; usar Magic Link u OTP.

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
  "role": "administrador",
  "created_at": "2024-01-01T00:00:00.000000Z",
  "updated_at": "2024-01-01T00:00:00.000000Z"
}
```

**Nota:** El campo `role` es un string; consistente con el endpoint de login.

### Response Errónea (401) - No Autenticado

```json
{
  "message": "No autenticado."
}
```

---

## Obtener Información de Tenant

Obtener información de un tenant por su subdominio. Este endpoint es público y no requiere autenticación.

### Request

```http
GET /api/v2/public/tenant/{subdomain}
```

#### Headers
```http
Content-Type: application/json
```

**Nota:** Este endpoint NO requiere `X-Tenant` ni `Authorization` ya que es público.

#### Path Parameters

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| subdomain | string | Subdominio del tenant |

### Response Exitosa (200)

Convención API Resources (envoltorio `data`):

```json
{
  "data": {
    "active": true,
    "name": "Mi Empresa"
  }
}
```

### Response Errónea (404) - Tenant No Encontrado

```json
{
  "error": "Tenant no encontrado"
}
```

### Response Errónea (422) - Subdominio Inválido

Si el subdominio contiene caracteres no permitidos (espacios, `@`, etc.):

```json
{
  "message": "El subdominio solo puede contener letras, números, guiones y guiones bajos.",
  "errors": {
    "subdomain": ["El subdominio solo puede contener letras, números, guiones y guiones bajos."]
  }
}
```

### Throttling

60 peticiones/minuto. Si se excede: **429 Too Many Requests**.

