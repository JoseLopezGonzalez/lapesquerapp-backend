# Auth: contraseñas de usuario eliminadas

Desde la migración a **Magic Link + OTP**, las contraseñas de usuario **ya no se usan** en la aplicación. Este documento resume qué se eliminó y qué se mantiene.

---

## Qué se eliminó (contraseña de usuario)

| Ubicación | Cambio |
|-----------|--------|
| **POST /v2/login** | Ya no valida email/password. Siempre devuelve **400** con mensaje: acceso solo por enlace o código. |
| **UserController store** | No se acepta ni se guarda `password`. Los usuarios se crean sin contraseña. |
| **UserController update** | No se acepta ni se actualiza `password`. |
| **User $fillable** | Se quitó `password` del array. |
| **User $hidden / $casts** | Se quitó `password` (la columna ya no existe en BD). |
| **Columna `users.password`** | Eliminada por migración `2026_02_10_150000_drop_password_from_users_table`. |
| **Tabla `password_reset_tokens`** | Eliminada por migración `2026_02_10_150001_drop_password_reset_tokens_table` (no se usaba). |
| **createTenantUser()** | Ya no recibe parámetro `$password`. Crea usuario sin asignar password (la columna ya no existe). |
| **Comando tenant:create-user** | Ya no pide argumento `password`. Crea usuario sin password. Si el usuario ya existe, no hace nada. |
| **UserFactory** | No incluye `password` en los atributos. |
| **Seeders (StoreOperatorUserSeeder, AlgarSeafoodUserSeeder)** | Crean usuarios sin campo `password`. |

---

## Qué se mantiene (no es contraseña de usuario)

| Ubicación | Motivo |
|-----------|--------|
| **company.mail.password, DB_PASSWORD, MAIL_PASSWORD, etc.** | Son contraseñas de **servicios** (SMTP, BD, correo), no de usuario. No se tocan. |
| **config/auth.php (passwords broker)** | Configuración por defecto de Laravel. No afecta al flujo actual. |

---

## Única forma de acceso

- **Magic Link:** `POST /v2/auth/magic-link/request` (solicitar) y `POST /v2/auth/magic-link/verify` (canjear).
- **OTP:** `POST /v2/auth/otp/request` (solicitar) y `POST /v2/auth/otp/verify` (canjear).

Tras canjear, la respuesta incluye `access_token` y `user` (misma forma que antes el login).

---

## Comando para crear usuario (CLI)

```bash
php artisan tenant:create-user {subdomain} {email} [--name=] [--role=]
```

Ya no se pasa contraseña. El usuario debe acceder por magic link u OTP; un admin puede usar “Reenviar invitación” desde el panel.

---

*Documento de referencia. Ver también: 87-Plan-Auth-Magic-Link-OTP.md, 88-Auth-Limpieza-Tokens-Reenvio-Invitacion.md.*
