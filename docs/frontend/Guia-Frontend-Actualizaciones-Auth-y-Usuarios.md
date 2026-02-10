# Guía Frontend — Actualizaciones Auth y Usuarios

Documento único con **todos los cambios de la API** que el frontend debe implementar: autenticación **solo** con Magic Link y OTP (sin contraseñas), gestión de usuarios (crear sin contraseña, reenviar invitación, eliminar).

---

**No se puede utilizar contraseña** en ningún flujo: ni para iniciar sesión ni al crear o editar usuarios. La API no acepta el campo `password` y `POST /v2/login` con email/password devuelve error. El acceso es únicamente por **enlace mágico** o **código OTP** enviado por correo.

---

**Base URL API:** `/api/v2`
**Header obligatorio en todas las peticiones:** `X-Tenant: {subdominio}` (ej. `brisamar`, `pymcolora`, `test`).

---

## 1. Resumen de cambios

| Área                          | Qué ha cambiado                                                                                                                                                                                                             |
| ------------------------------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Login**                | **Ya no hay login con contraseña.** `POST /v2/login` devuelve **400** con un mensaje indicando que el acceso es solo por enlace o código. No enviar email/password.                                          |
| **Auth**                 | **Única forma de acceso:** solicitar/canjear **Magic Link** o solicitar/canjear **OTP** por email. El usuario introduce su email y recibe un enlace o un código de 6 dígitos.                           |
| **Pantalla de login**    | Solo debe ofrecer: “Enviar enlace” y/o “Enviar código” (campo email). Ruta `/auth/verify?token=xxx` para cuando el usuario hace clic en el enlace del correo. **Quitar** la opción “Entrar con contraseña”. |
| **Crear usuario**        | **No existe el campo `password`.** Al crear usuario solo se envían name, email, role, active. El usuario entra siempre por magic link u OTP; usar “Reenviar invitación” para enviarle el enlace.                 |
| **Reenviar invitación** | Endpoint `POST /v2/users/{id}/resend-invitation` para enviar un magic link a un usuario desde el panel de administración.                                                                                                 |
| **Eliminar usuario**     | `DELETE /v2/users/{id}` (soft delete en backend). No cambia la forma de llamar al API.                                                                                                                                     |
| **Usuario y roles**      | Cada usuario tiene**`role`** (string), no `roles` (array). Valores: `tecnico`, `administrador`, `direccion`, `administracion`, `comercial`, `operario`.                                                |

---

## 2. Tabla de endpoints (auth y usuarios)

| Método        | Ruta                                           | Auth             | Descripción                                                              |
| -------------- | ---------------------------------------------- | ---------------- | ------------------------------------------------------------------------- |
| POST           | `/v2/login`                                  | No               | **Obsoleto:** devuelve 400; el acceso es solo por magic link u OTP. |
| POST           | `/v2/auth/magic-link/request`                | No               | Solicitar magic link por email.                                           |
| POST           | `/v2/auth/magic-link/verify`                 | No               | Canjear token del enlace → devuelve access_token y user.                 |
| POST           | `/v2/auth/otp/request`                       | No               | Solicitar código OTP por email.                                          |
| POST           | `/v2/auth/otp/verify`                        | No               | Canjear código OTP → devuelve access_token y user.                      |
| POST           | `/v2/logout`                                 | Bearer           | Cerrar sesión.                                                           |
| GET            | `/v2/me`                                     | Bearer           | Usuario actual.                                                           |
| GET            | `/v2/users`                                  | Bearer           | Listar usuarios.                                                          |
| POST           | `/v2/users`                                  | Bearer           | Crear usuario (sin campo password).                                       |
| GET            | `/v2/users/{id}`                             | Bearer           | Ver usuario.                                                              |
| PUT            | `/v2/users/{id}`                             | Bearer           | Actualizar usuario.                                                       |
| DELETE         | `/v2/users/{id}`                             | Bearer           | Eliminar usuario (soft delete).                                           |
| **POST** | **`/v2/users/{id}/resend-invitation`** | **Bearer** | **Reenviar magic link al usuario.**                                 |
| GET            | `/v2/roles/options`                          | Bearer           | Opciones para el select de rol.                                           |

---

## 3. Autenticación: Magic Link y OTP

### 3.1 Solicitar Magic Link

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

**Errores:** 500 si falla el envío del correo o la configuración del frontend en backend.

**Throttle:** 5 peticiones por minuto por IP.

---

### 3.2 Canjear Magic Link (página tras clic en el enlace del correo)

El enlace que recibe el usuario en el correo apunta al **frontend**, por ejemplo:

`https://brisamar.lapesquerapp.es/auth/verify?token=XXXX`

En la ruta del frontend (p. ej. `/auth/verify`):

1. Leer el query param **`token`**.
2. Llamar a la API para canjearlo.

**POST** `/api/v2/auth/magic-link/verify`

**Headers:** `X-Tenant: {subdomain}`, `Content-Type: application/json`

**Body:**

```json
{
  "token": "el_token_recibido_en_el_enlace"
}
```

**Respuesta 200:** misma estructura que el login con contraseña:

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

**Qué hacer:** guardar `access_token` y `user` (localStorage/sessionStorage o store), luego redirigir al dashboard.

**Errores:**

- **400:** enlace no válido o expirado. Mostrar mensaje y opción “Solicitar nuevo enlace”.
- **403:** usuario desactivado.

**Throttle:** 10 peticiones por minuto por IP.

---

### 3.3 Solicitar código OTP

**POST** `/api/v2/auth/otp/request`

**Headers:** `X-Tenant: {subdomain}`, `Content-Type: application/json`

**Body:**

```json
{
  "email": "usuario@ejemplo.com"
}
```

**Respuesta 200:** mismo mensaje que en magic link (no se revela si el email existe).

**Throttle:** 5 peticiones por minuto por IP.

---

### 3.4 Canjear código OTP

**POST** `/api/v2/auth/otp/verify`

**Headers:** `X-Tenant: {subdomain}`, `Content-Type: application/json`

**Body:**

```json
{
  "email": "usuario@ejemplo.com",
  "code": "123456"
}
```

**Respuesta 200:** misma estructura que el login (`access_token`, `user`). Guardar token y user y redirigir.

**Errores:** 400 si el código no es válido o ha expirado; 403 si el usuario está desactivado.

**Throttle:** 10 peticiones por minuto por IP.

---

## 4. Login con contraseña — eliminado

**POST** `/api/v2/login` **ya no permite acceso con contraseña.**

Cualquier petición a esta ruta (con o sin body) recibe **400** con:

```json
{
  "message": "El acceso se realiza mediante enlace o código enviado por correo. Usa \"Enviar enlace\" o \"Enviar código\" en la pantalla de inicio de sesión."
}
```

**Qué hacer en frontend:** no usar `POST /v2/login` con email/password. La pantalla de login debe ofrecer solo “Enviar enlace” y “Enviar código” (y la ruta `/auth/verify` para el clic en el correo).

---

## 5. Gestión de usuarios

### 5.1 Crear usuario (sin contraseña)

**POST** `/api/v2/users`

**Body:** no hay campo `password`. Campos obligatorios: name, email, role. Opcional: active.

```json
{
  "name": "Juan Pérez",
  "email": "juan@example.com",
  "role": "administracion",
  "active": true
}
```

Todos los usuarios entran por **magic link** u **OTP**. Tras crear el usuario, el admin puede usar “Reenviar invitación” para enviarle el enlace de acceso por correo.

**Qué hacer en frontend:**

- En el formulario de “Crear usuario” **no** incluir campo contraseña.
- Opcionalmente, tras crear, mostrar botón o aviso “Reenviar invitación” para enviar el magic link al correo del usuario.

---

### 5.2 Reenviar invitación (nuevo)

**POST** `/api/v2/users/{id}/resend-invitation`

**Headers:** `Authorization: Bearer {token}`, `X-Tenant: {subdomain}`

**Body:** ninguno.

**Respuesta 200:**

```json
{
  "message": "Se ha enviado un enlace de acceso al correo del usuario."
}
```

**Errores:**

- **403:** usuario desactivado.
- **404:** usuario no existe o está eliminado.
- **500:** fallo al enviar el correo o configuración del backend.

**Qué hacer en frontend:**

- En la **ficha** del usuario o en la **lista** de usuarios, añadir un botón **“Reenviar invitación”** (o “Enviar enlace de acceso”).
- Al hacer clic: `POST /api/v2/users/{id}/resend-invitation` con el `id` del usuario.
- Mostrar el mensaje de éxito o el error según la respuesta.
- Tiene sentido mostrarlo sobre todo para usuarios invitados (sin contraseña), pero el endpoint funciona para cualquier usuario activo.

---

### 5.3 Eliminar usuario (mismo endpoint, nuevo comportamiento)

**DELETE** `/api/v2/users/{id}`

La llamada es **igual** que antes. En backend el usuario pasa a **soft delete** (no se borra de la base de datos; deja de aparecer en el listado). No requiere cambios en la forma de llamar al API ni en la UI (sigue siendo “Eliminar usuario” y el usuario desaparece del listado).

---

## 6. Flujos de UI recomendados

### 6.1 Pantalla de login

- **Campo:** email (obligatorio).
- **Opciones (solo estas dos):**
  - **“Enviar enlace”** → llamar a `POST /v2/auth/magic-link/request` con el email. Mostrar: “Si el correo está registrado, recibirás un enlace en unos instantes.”
  - **“Enviar código”** → llamar a `POST /v2/auth/otp/request`. Mostrar el mismo mensaje y un **campo para introducir el código de 6 dígitos**. Al enviar el código → `POST /v2/auth/otp/verify` con `email` + `code`.
- **No** incluir opción “Entrar con contraseña” ni llamar a `POST /v2/login` para acceso.

### 6.2 Página tras clic en el enlace del correo

- **Ruta sugerida:** `/auth/verify` (con query `?token=xxx`).
- **Comportamiento:**
  1. Leer `token` de la URL.
  2. Llamar a `POST /v2/auth/magic-link/verify` con `{ "token": "..." }` y el mismo `X-Tenant` que use la app (según subdominio).
  3. Si 200: guardar `access_token` y `user`, redirigir al dashboard.
  4. Si 400: mostrar “Enlace no válido o expirado” y enlace/botón para volver a login o “Solicitar nuevo enlace”.

### 6.3 Panel de usuarios

- **Crear usuario:** formulario con nombre, email, rol, activo (sin campo contraseña). Enviar `POST /v2/users`. Tras crear, opcionalmente mostrar “Reenviar invitación” para enviar el magic link al correo del usuario.
- **Lista / ficha de usuario:** botón **“Reenviar invitación”** que llame a `POST /v2/users/{id}/resend-invitation`.
- **Eliminar:** `DELETE /v2/users/{id}` (el usuario deja de aparecer en el listado; soft delete en backend).

---

## 7. Formato común de “usuario” en login / me / verify

`GET /v2/me`, `POST /v2/auth/magic-link/verify` y `POST /v2/auth/otp/verify` devuelven (o incluyen) el mismo tipo de **user**:

```json
{
  "id": 1,
  "name": "...",
  "email": "...",
  "assignedStoreId": 1,
  "companyName": "...",
  "companyLogoUrl": "...",
  "role": "administrador"
}
```

En magic-link/verify y otp/verify la respuesta es:

```json
{
  "access_token": "...",
  "token_type": "Bearer",
  "user": { ... }
}
```

En `GET /v2/me` la respuesta es el objeto usuario directamente (con `created_at`, `updated_at`, etc.; el rol sigue siendo `role` string).

---

## 8. Tipos / TypeScript (sugerencia)

```ts
export type RoleValue =
  | 'tecnico'
  | 'administrador'
  | 'direccion'
  | 'administracion'
  | 'comercial'
  | 'operario';

export interface AuthUser {
  id: number;
  name: string;
  email: string;
  assignedStoreId?: number;
  companyName?: string;
  companyLogoUrl?: string;
  role: RoleValue;
}

export interface LoginResponse {
  access_token: string;
  token_type: string;
  user: AuthUser;
}

// Crear usuario: sin password (acceso por magic link u OTP)
export interface CreateUserPayload {
  name: string;
  email: string;
  role: RoleValue;
  active?: boolean;
}
```

---

## 9. Throttle (límites de peticiones)

| Endpoint                             | Límite              |
| ------------------------------------ | -------------------- |
| `POST /v2/login`                   | 5 por minuto por IP  |
| `POST /v2/auth/magic-link/request` | 5 por minuto por IP  |
| `POST /v2/auth/magic-link/verify`  | 10 por minuto por IP |
| `POST /v2/auth/otp/request`        | 5 por minuto por IP  |
| `POST /v2/auth/otp/verify`         | 10 por minuto por IP |

Si se supera el límite, la API devolverá **429 Too Many Requests**. Mostrar un mensaje tipo “Demasiados intentos; espera un momento antes de volver a intentar.”

---

## 10. Checklist de implementación

- [ ] Pantalla de login: solo “Enviar enlace” y “Enviar código” (sin contraseña).
- [ ] Llamadas a `auth/magic-link/request`, `auth/magic-link/verify`, `auth/otp/request`, `auth/otp/verify`.
- [ ] Ruta `/auth/verify?token=xxx` que canjea el token y guarda sesión.
- [ ] Formulario crear usuario: sin campo contraseña.
- [ ] Botón “Reenviar invitación” en lista/ficha de usuario → `POST /v2/users/{id}/resend-invitation`.
- [ ] Tipos/ interfaces: `role` (string), no `roles` (array); sin `password` en crear usuario.
- [ ] Manejo de 429 (throttle) en las pantallas de auth.

---

*Documento generado a partir de las actualizaciones del backend (auth Magic Link/OTP, usuarios, reenvío de invitación). Para dudas sobre roles y endpoints de usuarios ya existentes, ver también `Guia-Cambios-Roles-API-Paso-2.md`.*
