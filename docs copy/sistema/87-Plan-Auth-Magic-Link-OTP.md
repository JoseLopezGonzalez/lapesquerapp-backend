# Plan de implementación: Auth Magic Link + OTP y gestión de usuarios

Documento de referencia del plan acordado para pasar de autenticación por contraseña a **Magic Link** (principal) y **OTP por email** (fallback), manteniendo la contraseña de forma temporal y completando la gestión de usuarios (crear, editar, eliminar con soft delete).

---

## 1. Decisiones tomadas

| Tema | Decisión |
|------|----------|
| **URL del magic link** | Enlace al **frontend**. El frontend recibe el link, luego llama a `POST /v2/auth/magic-link/verify` con el token y header `X-Tenant`. |
| **Duración** | **10 minutos** para magic link y para OTP (configurable por env/constantes). |
| **Throttle** | **Sí**: límite de intentos por email (y opcionalmente por IP) en request/verify para evitar abuso. |
| **Crear usuario sin contraseña** | **Sí**: al crear usuario sin `password` se considera “invitación”; opcional enviar magic link desde el backend. |
| **Eliminar usuario** | **Soft delete**: campo `deleted_at` en `users` para poder referenciar actividad histórica. |
| **Tabla para tokens** | **Tabla nueva** `magic_link_tokens` (no reutilizar `password_reset_tokens`). |

---

## 2. Estrategia de autenticación

- **Principal**: Magic Link — usuario introduce email → recibe link único → clic → sesión iniciada (sin contraseña).
- **Fallback**: OTP por email — cuando el magic link expira, el cliente de correo no abre bien links, dispositivos raros/PWA/WebView, o el usuario prefiere “código”.
- **Contraseñas eliminadas:** ya no existe login por contraseña. `POST /v2/login` devuelve 400 indicando que el acceso es solo por magic link u OTP.

---

## 3. Fases de implementación

### Fase 1 — Base de datos

- **Nueva migración (por tenant)**  
  Tabla `magic_link_tokens`:
  - `id`, `email`, `token` (string único para el link), `type` (enum: `magic_link`, `otp`), `otp_code` (nullable, 6 dígitos), `expires_at`, `used_at` (nullable), `created_at`.
- **Migración en `users`**  
  Añadir `deleted_at` (nullable) para soft delete.
- **Migración en `users`**  
  Hacer `password` nullable (para usuarios invitados que solo usan magic link/OTP).
- **Modelo** `MagicLinkToken` con conexión tenant, scopes (válidos, no usados, no expirados), método para marcar como usado.

### Fase 2 — Auth: Magic Link

- **POST** `v2/auth/magic-link/request` (público, con `X-Tenant`).  
  Body: `{ "email": "..." }`.  
  - Validar email; comprobar que el usuario existe y está activo.  
  - Crear registro en `magic_link_tokens` (tipo `magic_link`, token aleatorio seguro, `expires_at` = now + 10 min).  
  - Enviar email con enlace al **frontend** (ej. `{FRONTEND_URL}/auth/verify?token=xxx`; la URL base por tenant debe ser configurable).  
  - Throttle por email (ej. 5/min).
- **POST** `v2/auth/magic-link/verify` (público, con `X-Tenant`).  
  Body: `{ "token": "..." }`.  
  - Comprobar que el token existe, no está usado y no ha expirado.  
  - Marcar como usado; crear sesión Sanctum; devolver mismo formato que `login` (`access_token`, `token_type`, `user`).

### Fase 3 — Auth: OTP

- **POST** `v2/auth/otp/request` (público, con `X-Tenant`).  
  Body: `{ "email": "..." }`.  
  - Usuario activo; crear registro tipo `otp` con código de 6 dígitos y `expires_at` = now + 10 min.  
  - Enviar email con el código.  
  - Throttle por email.
- **POST** `v2/auth/otp/verify` (público, con `X-Tenant`).  
  Body: `{ "email": "...", "code": "123456" }`.  
  - Buscar token OTP válido para ese email; marcar como usado; crear sesión Sanctum; misma respuesta que login.

### Fase 4 — Login híbrido

- **POST** `v2/login`: ya no acepta contraseña; devuelve 400 con mensaje para usar magic link u OTP.
- El frontend usará `magic-link/verify` y `otp/verify` para los flujos sin contraseña; la respuesta es idéntica a `login`.

### Fase 5 — Emails

- **Mailable (o Notification)** para Magic Link: asunto tipo “Inicia sesión en [App]”, cuerpo con botón/enlace y aviso de validez (10 min).
- **Mailable (o Notification)** para OTP: asunto “Tu código de acceso”, cuerpo con el código de 6 dígitos y validez.
- Usar la configuración de correo del tenant (`TenantMailConfigService`) al enviar.

### Fase 6 — Gestión de usuarios

- **Crear (store)**  
  - Sin campo `password`; todos los usuarios acceden por magic link u OTP.
- **Editar (update)**  
  - Sin campo `password`. Opcional: “reenviar invitación” (enviar magic link).
- **Eliminar (destroy)**  
  - **Soft delete**: actualizar `deleted_at` en lugar de borrar. Excluir usuarios con `deleted_at` en listados por defecto. Opcional: revocar todos los tokens del usuario al “eliminar”.

### Fase 7 — Seguridad y limpieza

- Throttle en `magic-link/request`, `otp/request`, `otp/verify` y opcionalmente `magic-link/verify`.
- Job o comando programado: borrar registros de `magic_link_tokens` expirados o usados con más de X días.

### Fase 8 — Documentación y frontend

- Documentar en `docs/frontend` (o similar) los nuevos endpoints y el flujo (pantalla “Introduce tu email” → “Te hemos enviado un enlace/código” → verificación y redirección).
- Documentar que el acceso es solo por magic link u OTP; login con contraseña eliminado.

---

## 4. Endpoints (resumen)

| Método | Ruta | Descripción |
|--------|------|-------------|
| POST | `v2/login` | Obsoleto: devuelve 400 (acceso solo por magic link/OTP). |
| POST | `v2/auth/magic-link/request` | Solicitar magic link (body: `email`). |
| POST | `v2/auth/magic-link/verify` | Canjear token del link (body: `token`). |
| POST | `v2/auth/otp/request` | Solicitar código OTP (body: `email`). |
| POST | `v2/auth/otp/verify` | Canjear OTP (body: `email`, `code`). |
| POST | `v2/logout` | Sin cambios. |
| GET | `v2/me` | Sin cambios. |
| CRUD | `v2/users` | `store` sin password; `destroy` con soft delete. |

---

## 5. Configuración necesaria

- **URL del frontend por tenant**: variable o convención para generar el enlace del magic link en el email (ej. `FRONTEND_URL` o `https://{subdomain}.tudominio.com`). Definir dónde se configura (env global vs settings por tenant).
- **Duración de tokens**: 10 minutos; recomendable hacerlo configurable (constante o env).

---

## 6. Referencias

- Estado actual: `app/Http/Controllers/v2/AuthController.php`, `UserController.php`, modelo `User`, migraciones en `database/migrations/companies/`.
- Tenant: header `X-Tenant`; middleware en `app/Http/Middleware/TenantMiddleware.php`.
- Correo tenant: `app/Services/TenantMailConfigService.php`.

---

## 7. Estado de implementación

| Fase | Estado |
|------|--------|
| 1. Migraciones (magic_link_tokens, deleted_at, password nullable) | ✅ Hecho |
| 2. Modelo MagicLinkToken, SoftDeletes en User | ✅ Hecho |
| 3. Mailables Magic Link y OTP | ✅ Hecho |
| 4. Endpoints magic-link/request y magic-link/verify | ✅ Hecho |
| 5. Endpoints otp/request y otp/verify | ✅ Hecho |
| 6. UserController: password opcional, soft delete, revocar tokens | ✅ Hecho |
| 7. Rutas, throttle, documentación frontend | ✅ Hecho |

**Implementado además:**
- Comando `auth:cleanup-magic-tokens` y programación diaria (03:00). Ver `docs/sistema/88-Auth-Limpieza-Tokens-Reenvio-Invitacion.md`.
- Endpoint `POST /v2/users/{user}/resend-invitation` para reenviar magic link desde el panel. Ver misma doc y guía frontend.

**Pendiente por tu parte:**
- Configurar `FRONTEND_URL` en `.env` (o `company.frontend_url` por tenant) si no está ya.
- Asegurar que el scheduler de Laravel esté activo en producción para la limpieza diaria.

---

*Documento creado a partir del plan acordado. Última actualización: febrero 2025.*
