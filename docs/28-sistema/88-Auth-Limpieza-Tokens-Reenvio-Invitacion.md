# Auth: limpieza automática de tokens y reenvío de invitación

Este documento describe la **limpieza automática** de la tabla `magic_link_tokens` y el **endpoint de reenvío de invitación** para usuarios.

---

## 1. Limpieza automática de tokens (magic link / OTP)

Cada solicitud de magic link o OTP crea un registro en `magic_link_tokens`. Para evitar crecimiento indefinido de la tabla, se ejecuta una limpieza periódica.

### 1.1 Comando artisan

**Comando:** `php artisan auth:cleanup-magic-tokens`

- Recorre **todos los tenants activos** (o uno concreto con `--tenant=subdominio`).
- En cada tenant elimina:
  - Registros **expirados** (`expires_at < ahora`).
  - Registros **ya usados** con antigüedad mayor a N días (configurable; ver abajo).

**Opciones:**

| Opción       | Descripción |
|-------------|-------------|
| `--tenant=subdominio` | Limpia solo ese tenant (ej. `--tenant=brisamar`). |
| `--dry-run` | Muestra cuántos registros se eliminarían **sin borrar**. Útil para comprobar antes de ejecutar. |

**Ejemplos:**

```bash
# Limpiar todos los tenants
php artisan auth:cleanup-magic-tokens

# Solo el tenant brisamar
php artisan auth:cleanup-magic-tokens --tenant=brisamar

# Ver qué se eliminaría sin tocar la BD
php artisan auth:cleanup-magic-tokens --dry-run
```

### 1.2 Programación (scheduler)

El comando está programado para ejecutarse **cada día a las 03:00** en `app/Console/Kernel.php`:

```php
$schedule->command('auth:cleanup-magic-tokens')->dailyAt('03:00');
```

Para que se ejecute solo, el **scheduler de Laravel** debe estar activo (un único cron en el servidor):

```bash
* * * * * cd /ruta/proyecto && php artisan schedule:run >> /dev/null 2>&1
```

### 1.3 Configuración

**Archivo:** `config/magic_link.php`

| Clave | Descripción | Por defecto |
|-------|-------------|-------------|
| `cleanup.delete_expired` | Eliminar registros con `expires_at` pasada | `true` |
| `cleanup.used_older_than_days` | Eliminar registros usados (`used_at` no null) más antiguos que N días. `0` = no eliminar por antigüedad | `1` (env: `MAGIC_LINK_CLEANUP_USED_DAYS`) |

**Variables de entorno opcionales** (en `.env`):

```env
# Días de antigüedad para borrar tokens ya usados (0 = no borrar por antigüedad)
MAGIC_LINK_CLEANUP_USED_DAYS=1
```

Tras cambiar la config, en producción no suele hacer falta limpiar caché si se usa `env()`; si usas `config:cache`, ejecuta `php artisan config:cache` después de tocar `.env`.

---

## 2. Reenvío de invitación (magic link por usuario)

Permite que un **administrador** envíe de nuevo un magic link a un usuario concreto desde el panel (por ejemplo, si no recibió el correo o expiró).

### 2.1 Endpoint

**POST** `/api/v2/users/{user}/resend-invitation`

- **Autenticación:** Bearer token (Sanctum) + mismo middleware de roles que el resto de usuarios.
- **Headers:** `X-Tenant`, `Authorization: Bearer {token}`, `Content-Type: application/json`.
- **Body:** ninguno (el usuario viene en la URL).

**Respuesta 200:**

```json
{
  "message": "Se ha enviado un enlace de acceso al correo del usuario."
}
```

**Errores:**

| Código | Situación |
|--------|-----------|
| 403 | Usuario desactivado (`active = false`). |
| 404 | Usuario no existe o está eliminado (soft delete). |
| 500 | Error al enviar el correo o URL del frontend no configurada. |

### 2.2 Cuándo usarlo

- Usuarios **invitados** (sin contraseña) que no recibieron o perdieron el enlace.
- Cualquier usuario activo al que se quiera enviar un **nuevo enlace de acceso** sin usar contraseña.

El enlace enviado es válido el mismo tiempo que los magic link normales (por defecto 10 minutos; ver `config/magic_link.php` y `MAGIC_LINK_EXPIRES_MINUTES`).

### 2.3 Frontend

En la ficha o fila del usuario, un botón tipo **"Reenviar invitación"** que llame a:

```http
POST /api/v2/users/{id}/resend-invitation
Authorization: Bearer {token}
X-Tenant: {subdomain}
```

Sin body. Mostrar el mensaje de la respuesta o el error según el código devuelto.

---

## 3. Referencias

- Plan general de auth: `docs/28-sistema/87-Plan-Auth-Magic-Link-OTP.md`
- Guía frontend magic link/OTP: `docs/33-frontend/Guia-Auth-Magic-Link-OTP.md`
- Configuración: `config/magic_link.php`
- Comando: `app/Console/Commands/CleanupMagicLinkTokens.php`
- Servicio de envío: `app/Services/MagicLinkService.php`
