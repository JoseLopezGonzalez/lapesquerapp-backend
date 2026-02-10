# Guía Frontend — Auth Magic Link y OTP

Esta guía describe los endpoints y flujos para **inicio de sesión sin contraseña**: Magic Link (principal) y OTP por email (alternativa).

---

## 1. Resumen

- **Magic Link**: el usuario introduce su email → recibe un enlace único por correo → al hacer clic llega al frontend → el frontend llama a la API para canjear el token → sesión iniciada.
- **OTP**: el usuario introduce su email → recibe un código de 6 dígitos por correo → introduce el código en el frontend → el frontend llama a la API para canjearlo → sesión iniciada.
- **Contraseña**: eliminada. `POST /v2/login` devuelve 400; el acceso es solo por magic link u OTP.

Todas las rutas requieren el header **`X-Tenant`** (subdominio del tenant).

---

## 2. Endpoints

### 2.1 Solicitar Magic Link

**POST** `/api/v2/auth/magic-link/request`

**Headers:** `X-Tenant: {subdomain}`, `Content-Type: application/json`

**Body:**
```json
{
  "email": "usuario@ejemplo.com"
}
```

**Respuesta 200 (siempre la misma, por seguridad):**
```json
{
  "message": "Si el correo está registrado y activo, recibirás un enlace para iniciar sesión."
}
```

**Errores:** 500 si no se puede enviar el correo (configuración del tenant) o si falta la URL del frontend.

**Throttle:** 5 peticiones por minuto por IP.

---

### 2.2 Canjear Magic Link (iniciar sesión)

El usuario hace clic en el enlace del correo. El enlace debe llevar al frontend, por ejemplo:

`https://tu-frontend.com/auth/verify?token=XXXX`

El frontend debe:
1. Leer `token` de la query.
2. Llamar a **POST** `/api/v2/auth/magic-link/verify` con ese token y header `X-Tenant`.

**POST** `/api/v2/auth/magic-link/verify`

**Headers:** `X-Tenant: {subdomain}`, `Content-Type: application/json`

**Body:**
```json
{
  "token": "el_token_recibido_en_el_enlace"
}
```

**Respuesta 200 (éxito):** misma estructura que el login con contraseña:
```json
{
  "access_token": "...",
  "token_type": "Bearer",
  "user": {
    "id": 1,
    "name": "...",
    "email": "...",
    "assignedStoreId": 1,
    "companyName": "...",
    "companyLogoUrl": "...",
    "role": "administrador"
  }
}
```

**Errores:**
- 400: enlace no válido o expirado.
- 403: usuario desactivado.

**Throttle:** 10 peticiones por minuto por IP.

---

### 2.3 Solicitar código OTP

**POST** `/api/v2/auth/otp/request`

**Headers:** `X-Tenant: {subdomain}`, `Content-Type: application/json`

**Body:**
```json
{
  "email": "usuario@ejemplo.com"
}
```

**Respuesta 200:** mismo mensaje que en magic link request (no se revela si el email existe).

**Throttle:** 5 peticiones por minuto por IP.

---

### 2.4 Canjear código OTP (iniciar sesión)

**POST** `/api/v2/auth/otp/verify`

**Headers:** `X-Tenant: {subdomain}`, `Content-Type: application/json`

**Body:**
```json
{
  "email": "usuario@ejemplo.com",
  "code": "123456"
}
```

**Respuesta 200:** misma estructura que el login (access_token, user).

**Errores:** 400 si el código no es válido o ha expirado; 403 si el usuario está desactivado.

**Throttle:** 10 peticiones por minuto por IP.

---

## 3. Flujo recomendado en el frontend

1. **Pantalla de login**
   - Campo email.
   - Opción “Enviar enlace” (magic link) y/o “Enviar código” (OTP).
   - Opción “Entrar con contraseña” (si el usuario tiene contraseña).

2. **Tras “Enviar enlace” o “Enviar código”**
   - Llamar a `auth/magic-link/request` o `auth/otp/request`.
   - Mostrar mensaje: “Si el correo está registrado, recibirás un enlace/código en unos instantes.”

3. **Magic Link**
   - Ruta del frontend para el enlace: p. ej. `/auth/verify?token=xxx`.
   - En esa página: leer `token`, llamar a `auth/magic-link/verify` con `{ "token": "xxx" }` y `X-Tenant`.
   - Si 200: guardar `access_token` y datos de `user`, redirigir al dashboard.
   - Si 400: mostrar “Enlace no válido o expirado” y opción de solicitar uno nuevo.

4. **OTP**
   - Tras “Enviar código”, mostrar campo para los 6 dígitos.
   - Al enviar: llamar a `auth/otp/verify` con `email` + `code`.
   - Si 200: guardar token y user, redirigir.
   - Si 400: mostrar error y opción de reenviar código.

---

## 4. Configuración en backend

- **URL del frontend** (para generar el enlace del magic link en el email):
  - En **.env**: `FRONTEND_URL=https://{subdomain}.lapesquerapp.es`  
    El placeholder `{subdomain}` se sustituye por el tenant de la petición (brisamar, pymcolora, test, etc.), así el enlace queda p. ej. `https://brisamar.lapesquerapp.es/auth/verify?token=xxx`.
  - Opcional: en settings del tenant, clave `company.frontend_url` (si existe, prima sobre `FRONTEND_URL`).
- Validez del magic link y del OTP: **10 minutos**.

---

## 5. Usuarios creados sin contraseña (invitados)

Si un usuario se crea desde el panel de administración **sin contraseña**, solo podrá entrar con magic link u OTP. Si intenta usar “Entrar con contraseña”, la API devolverá 401 con el mensaje: *“Esta cuenta utiliza acceso por enlace o código. Solicita un enlace o código en la pantalla de inicio de sesión.”*

El frontend puede mostrar ese mensaje cuando reciba ese 401 en login con contraseña.

---

## 6. Reenviar invitación (panel de administración)

Un administrador puede enviar de nuevo un magic link a un usuario desde el panel (p. ej. si no recibió el correo o el enlace expiró).

**POST** `/api/v2/users/{id}/resend-invitation`

- **Headers:** `Authorization: Bearer {token}`, `X-Tenant: {subdomain}`.
- **Body:** ninguno.

**Respuesta 200:** `{ "message": "Se ha enviado un enlace de acceso al correo del usuario." }`

**Errores:** 403 si el usuario está desactivado; 404 si no existe; 500 si falla el envío del correo o la configuración del frontend.

En la ficha o lista de usuarios, un botón "Reenviar invitación" debe llamar a este endpoint con el `id` del usuario.
