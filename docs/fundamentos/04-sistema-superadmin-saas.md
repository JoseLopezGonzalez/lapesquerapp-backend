# Sistema Superadmin y Gestión SaaS

**Última actualización**: 2026-02-23 (v2)

Documentación técnica del sistema de administración de la plataforma SaaS de PesquerApp. Cubre la gestión centralizada de tenants, autenticación independiente de superadministradores, onboarding automatizado, CORS dinámico, impersonación, seguridad avanzada, observabilidad y feature flags.

---

## Índice

1. [Arquitectura general](#1-arquitectura-general)
2. [Modelo de datos central](#2-modelo-de-datos-central)
3. [Estados del tenant y ciclo de vida](#3-estados-del-tenant-y-ciclo-de-vida)
4. [Autenticación Superadmin](#4-autenticación-superadmin)
5. [CORS dinámico](#5-cors-dinámico)
6. [Gestión de tenants](#6-gestión-de-tenants)
7. [Onboarding automatizado](#7-onboarding-automatizado)
8. [Impersonación](#8-impersonación)
9. [Configuración por tenant](#9-configuración-por-tenant)
10. [Seguridad avanzada](#10-seguridad-avanzada)
11. [Observabilidad y alertas](#11-observabilidad-y-alertas)
12. [Feature flags](#12-feature-flags)
13. [Comandos Artisan y scheduler](#13-comandos-artisan-y-scheduler)
14. [Mapa de archivos](#14-mapa-de-archivos)

---

## 1. Arquitectura general

El sistema opera sobre dos capas de base de datos independientes:

```
+----------------------------------------------------------------+
|                      BD Central (mysql)                        |
|                                                                |
|  tenants                        superadmin_users               |
|  superadmin_magic_link_tokens   superadmin_personal_access_tokens |
|  impersonation_requests         impersonation_logs             |
|  tenant_migration_runs          system_alerts                  |
|  tenant_blocklists              tenant_error_logs              |
|  feature_flags                  tenant_feature_overrides       |
+----------------------------+-----------------------------------+
                             |
               +-------------+-------------+
               |             |             |
      +--------v--+  +------v----+  +-----v-----+
      | tenant_   |  | tenant_   |  | tenant_   |
      | brisamar  |  | costasur  |  | pymcol..  |
      |           |  |           |  |           |
      | users     |  | users     |  | users     |
      | settings  |  | settings  |  | settings  |
      | orders    |  | orders    |  | orders    |
      | ...       |  | ...       |  | ...       |
      +-----------+  +-----------+  +-----------+
```

**Principio fundamental**: La BD central contiene metadatos de la plataforma (tenants, superadmins, impersonación, seguridad, observabilidad, feature flags). Los datos de negocio residen exclusivamente en las BDs de cada tenant. Las rutas superadmin (`/api/v2/superadmin/*`) no pasan por `TenantMiddleware` y operan únicamente contra la BD central.

Las rutas de tenant (`/api/v2/*` excepto superadmin) pasan por `TenantMiddleware`, que resuelve el tenant por el header `X-Tenant` y configura la conexión `tenant` dinámicamente.

---

## 2. Modelo de datos central

### 2.1 Tabla `tenants`

Fuente de verdad de la plataforma. Cada registro representa una empresa cliente.

| Columna | Tipo | Default | Descripción |
|---|---|---|---|
| `id` | bigIncrements | - | PK |
| `name` | string | - | Razón social |
| `subdomain` | string (unique) | - | Subdominio en la plataforma |
| `database` | string | - | Nombre de la BD del tenant |
| `status` | enum | `'active'` | Estado del tenant (ver sección 3) |
| `plan` | string(50) nullable | null | Plan contratado (informativo) |
| `renewal_at` | date nullable | null | Fecha de renovación |
| `timezone` | string(50) | `'Europe/Madrid'` | Zona horaria |
| `branding_image_url` | string nullable | null | URL del logo |
| `last_activity_at` | timestamp nullable | null | Último login de cualquier usuario |
| `onboarding_step` | tinyInteger nullable | null | Último paso completado (1-8) |
| `admin_email` | string nullable | null | Email del administrador principal |
| `created_at`, `updated_at` | timestamps | - | - |

Índices: `idx_tenants_status` en `status`; `subdomain` es unique.

Modelo: `App\Models\Tenant` (`$connection = 'mysql'`).

Scopes: `Tenant::active()` filtra `status = 'active'`; `Tenant::byStatus('suspended')` filtra por status específico.

Accessor de compatibilidad: `$tenant->is_active` devuelve `true` si `status === 'active'`.

### 2.2 Tabla `superadmin_users`

Usuarios con acceso al panel de administración de la plataforma. Independiente de los usuarios de tenant.

| Columna | Tipo | Descripción |
|---|---|---|
| `id` | bigIncrements | PK |
| `name` | string | Nombre |
| `email` | string (unique) | Email (login vía magic link) |
| `email_verified_at` | timestamp nullable | - |
| `last_login_at` | timestamp nullable | Se actualiza en cada login |

Modelo: `App\Models\SuperadminUser` (`$connection = 'mysql'`). Usa `HasApiTokens` de Sanctum con override de `tokens()` para usar `SuperadminPersonalAccessToken`.

### 2.3 Tabla `superadmin_magic_link_tokens`

Tokens de acceso temporales para superadmins. Misma estructura que los tokens de tenant pero en la BD central.

| Columna | Tipo | Descripción |
|---|---|---|
| `id` | bigIncrements | PK |
| `email` | string (indexed) | Email del superadmin |
| `token` | string(64) (unique) | Hash SHA-256 del token |
| `type` | string(20) | `'magic_link'` o `'otp'` |
| `otp_code` | string(6) nullable | Código OTP en texto plano |
| `expires_at` | timestamp | Momento de expiración |
| `used_at` | timestamp nullable | null = no usado |

Modelo: `App\Models\SuperadminMagicLinkToken`. Scopes: `valid()`, `magicLink()`, `otp()`.

### 2.4 Tabla `superadmin_personal_access_tokens`

Tokens Sanctum de sesión para superadmins. Tabla separada de los tokens de tenant para evitar conflictos.

| Columna | Tipo | Descripción |
|---|---|---|
| `id` | bigIncrements | PK |
| `tokenable_type` | string | Siempre `App\Models\SuperadminUser` |
| `tokenable_id` | unsignedBigInteger | ID del superadmin |
| `name` | string | Nombre del token |
| `token` | string(64) (unique) | Hash SHA-256 |
| `abilities` | text nullable | Permisos del token |
| `last_used_at` | timestamp nullable | - |
| `expires_at` | timestamp nullable | - |

Modelo: `App\Sanctum\SuperadminPersonalAccessToken` (extiende `PersonalAccessToken`, fija `$connection = 'mysql'`).

### 2.5 Tabla `impersonation_requests`

Solicitudes de acceso con consentimiento del tenant.

| Columna | Tipo | Descripción |
|---|---|---|
| `id` | bigIncrements | PK |
| `superadmin_user_id` | FK -> superadmin_users | Quién solicita |
| `tenant_id` | FK -> tenants | Tenant objetivo |
| `target_user_id` | unsignedBigInteger | ID del usuario en la BD del tenant |
| `status` | enum | `'pending'`, `'approved'`, `'rejected'`, `'expired'` |
| `token` | string(64) nullable (unique) | Hash SHA-256 para URLs firmadas |
| `approved_at` | timestamp nullable | Momento de aprobación |
| `expires_at` | timestamp nullable | Ventana de validez (30 min) |

Modelo: `App\Models\ImpersonationRequest`. Relaciones: `superadminUser()`, `tenant()`. Scopes: `pending()`, `approved()`.

### 2.6 Tabla `impersonation_logs`

Registro de auditoría de todas las sesiones de impersonación.

| Columna | Tipo | Descripción |
|---|---|---|
| `id` | bigIncrements | PK |
| `superadmin_user_id` | FK -> superadmin_users | Quién impersonó |
| `tenant_id` | FK -> tenants | En qué tenant |
| `target_user_id` | unsignedBigInteger | A quién impersonó |
| `mode` | enum | `'consent'` o `'silent'` |
| `reason` | varchar(500) nullable | Motivo del acceso (obligatorio en modo `silent`) |
| `started_at` | timestamp | Inicio de la sesión |
| `ended_at` | timestamp nullable | Fin (null = activa) |
| `created_at` | timestamp | - |

### 2.7 Tabla `tenant_migration_runs` (v2)

Historial de ejecuciones de migraciones por tenant.

| Columna | Tipo | Descripción |
|---|---|---|
| `id` | bigIncrements | PK |
| `tenant_id` | FK -> tenants | Tenant migrado |
| `triggered_by_superadmin_id` | FK -> superadmin_users nullable | Quién disparó la migración |
| `migrations_applied` | int | Número de migraciones aplicadas |
| `output` | text nullable | Output crudo de Artisan |
| `success` | boolean | Resultado |
| `started_at` | timestamp | Inicio |
| `finished_at` | timestamp nullable | Fin |

Índice: `(tenant_id, started_at)`.

### 2.8 Tabla `system_alerts` (v2)

Alertas del sistema generadas por detección automática o eventos de jobs.

| Columna | Tipo | Descripción |
|---|---|---|
| `id` | bigIncrements | PK |
| `type` | enum | Ver tipos disponibles |
| `severity` | enum | `info`, `warning`, `critical` |
| `tenant_id` | FK -> tenants nullable | Tenant afectado (null = global) |
| `message` | string | Mensaje legible |
| `metadata` | json nullable | Datos adicionales |
| `resolved_at` | timestamp nullable | Cuándo fue resuelta |
| `resolved_by_superadmin_id` | FK -> superadmin_users nullable | Quién la resolvió |

**Tipos de alerta:** `onboarding_failed`, `onboarding_stuck`, `migrations_pending`, `suspicious_activity`, `impersonation_started`, `queue_stopped`.

Lógica de deduplicación: `SystemAlert::createIfNotExists()` solo crea si no existe ya una alerta no resuelta del mismo tipo para el mismo tenant.

### 2.9 Tabla `tenant_blocklists` (v2)

Bloqueos manuales de IPs o emails por tenant.

| Columna | Tipo | Descripción |
|---|---|---|
| `id` | bigIncrements | PK |
| `tenant_id` | FK -> tenants | Tenant al que aplica el bloqueo |
| `type` | enum | `ip`, `email` |
| `value` | string(255) | IP o email bloqueado |
| `blocked_by_superadmin_id` | FK -> superadmin_users nullable | Quién bloqueó |
| `reason` | string(500) nullable | Motivo |
| `expires_at` | timestamp nullable | null = indefinido |

El check de blocklist en `AuthController::requestAccess()` usa cache Redis 5 min por tenant.

### 2.10 Tabla `tenant_error_logs` (v2)

Registro centralizado de errores HTTP 500 producidos en peticiones de tenant.

| Columna | Tipo | Descripción |
|---|---|---|
| `id` | bigIncrements | PK |
| `tenant_id` | FK -> tenants nullable | Tenant origen |
| `user_id` | unsignedBigInteger nullable | Usuario que hizo la petición |
| `method` | string(10) | Método HTTP |
| `url` | string(1000) | URL completa |
| `error_class` | string(255) | Clase PHP de la excepción |
| `error_message` | text | Mensaje de error |
| `occurred_at` | timestamp | Momento del error |

Retención: 30 días (limpiado por `superadmin:prune-logs`). Sin stack trace (los logs del servidor tienen el detalle).

Inserción en `Handler.php` en los dos paths que devuelven 500: QueryException y excepciones genéricas.

### 2.11 Tabla `login_attempts` (BD tenant — v2)

Registro de intentos de login en cada base de datos de tenant (tabla `login_attempts`).

| Columna | Tipo | Descripción |
|---|---|---|
| `id` | bigIncrements | PK |
| `email` | string | Email intentado |
| `ip_address` | string(45) nullable | IP origen |
| `user_agent` | string(500) nullable | User agent |
| `success` | boolean | Si el acceso fue exitoso |
| `attempted_at` | timestamp | Momento del intento |

Índices: `(email, attempted_at)`, `(ip_address, attempted_at)`. Usada por `DetectSuspiciousActivity`.

---

## 3. Estados del tenant y ciclo de vida

### 3.1 Estados

| Estado | Significado | Acceso permitido |
|---|---|---|
| `pending` | Onboarding en curso | No (TenantMiddleware bloquea) |
| `active` | Operativo | Sí |
| `suspended` | Suspendido por superadmin | No (403 con mensaje específico) |
| `cancelled` | Cancelado | No (403 genérico) |

### 3.2 Transiciones

Desde `pending`: onboarding completado -> `active`; cancel -> `cancelled`.

Desde `active`: suspend -> `suspended`; cancel -> `cancelled`.

Desde `suspended`: activate -> `active`; cancel -> `cancelled`.

Desde `cancelled`: activate -> `active`.

### 3.3 Comportamiento del TenantMiddleware

Cuando una petición llega con header `X-Tenant`:

1. Busca el tenant por `subdomain` (sin filtrar por status).
2. Si no existe: `404 Tenant not found`.
3. Si `status === 'suspended'`: `403` con `userMessage: "Tu cuenta está suspendida. Contacta con soporte."` y campo `status: "suspended"`.
4. Si `status !== 'active'`: `403 Tenant not available`.
5. Si `status === 'active'`: configura conexión `tenant` y continúa.

### 3.4 Invalidación de caché CORS

Al cambiar el status de un tenant (activar, suspender, cancelar), el sistema invalida la clave de caché `cors:tenant:{subdomain}` para que el CORS dinámico refleje inmediatamente el nuevo estado.

---

## 4. Autenticación Superadmin

### 4.1 Aislamiento del guard

El sistema de autenticación superadmin es completamente independiente del de tenants:

| Aspecto | Tenant auth | Superadmin auth |
|---|---|---|
| Guard | `sanctum` (default) | `superadmin` (custom) |
| Provider | `users` | `superadmin_users` |
| Tabla tokens | `personal_access_tokens` (BD tenant) | `superadmin_personal_access_tokens` (BD central) |
| Tabla magic links | `magic_link_tokens` (BD tenant) | `superadmin_magic_link_tokens` (BD central) |
| Config de correo | `TenantMailConfigService` (settings del tenant) | Config global de `.env` |
| Middleware | `auth:sanctum` + `TenantMiddleware` | `SuperadminMiddleware` |

### 4.2 Flujo de autenticación

1. `POST /auth/request-access` con `{ email }` — busca en `superadmin_users`, genera token magic link + OTP (hasheados), envía email. Siempre devuelve 200 (no revela si el email existe).

2. Verificación por una de dos vías:
   - `POST /auth/verify-magic-link` con `{ token }` — verifica hash SHA-256 contra `superadmin_magic_link_tokens`.
   - `POST /auth/verify-otp` con `{ email, code }` — verifica OTP directo.

3. Si válido: marca token como usado, actualiza `last_login_at`, crea Sanctum token en `superadmin_personal_access_tokens`, devuelve `{ access_token, user }`.

4. Peticiones autenticadas: enviar `Authorization: Bearer {token}`. El `SuperadminMiddleware` resuelve el usuario.

5. Logout: `POST /auth/logout` — revoca el token Sanctum actual.

### 4.3 Mecanismo del SuperadminMiddleware

El middleware resuelve un problema clave: Sanctum usa una única tabla de tokens configurable globalmente. Para que los tokens de superadmin no se busquen en la tabla de tenant (y viceversa), el middleware intercambia temporalmente el modelo de token:

```php
// 1. Guardar modelo actual (tenant)
$previousModel = Sanctum::$personalAccessTokenModel;

// 2. Cambiar a modelo superadmin (BD central)
Sanctum::usePersonalAccessTokenModel(SuperadminPersonalAccessToken::class);

// 3. Autenticar contra la tabla central
$user = auth('sanctum')->user();

// 4. Restaurar modelo original
Sanctum::usePersonalAccessTokenModel($previousModel);

// 5. Verificar que el usuario autenticado es SuperadminUser
if (!$user || !($user instanceof SuperadminUser)) {
    return response()->json(['error' => 'Unauthorized'], 401);
}
```

Esto garantiza que un token de tenant nunca autentica como superadmin, y viceversa.

### 4.4 Tokens y expiración

- Los magic link tokens y OTP expiran según `config('magic_link.expires_minutes')` (default 10 min).
- Al enviar `request-access`, se crean dos registros: uno `magic_link` y otro `otp`.
- Los tokens se almacenan como hash SHA-256. El código OTP se guarda en plano en `otp_code`.
- Los tokens Sanctum de sesión no tienen expiración configurada por defecto.

### 4.5 Rate limiting

| Endpoint | Límite |
|---|---|
| `request-access` | 5/minuto |
| `verify-magic-link` | 10/minuto |
| `verify-otp` | 10/minuto |

---

## 5. CORS dinámico

### 5.1 Problema que resuelve

Cada tenant accede a la API desde su subdominio (`https://brisamar.lapesquerapp.es`). Sin CORS dinámico, cada nuevo tenant requeriría actualizar la configuración y redesplegar.

### 5.2 Funcionamiento

El middleware `DynamicCorsMiddleware` reemplaza a `HandleCors` de Laravel en el stack global. Su lógica:

1. Sin header `Origin`: pasa sin CORS.
2. Path no coincide con `config('cors.paths')` (ej. `api/*`): pasa sin CORS, solo `Vary: Origin`.
3. **Entorno local/testing**: valida contra lista estática (`allowed_origins` + `allowed_origins_patterns` de `config/cors.php`).
4. **Producción**: primero verifica orígenes fijos; luego extrae el subdominio del `Origin`, y consulta (con caché de 10 min) si es un tenant activo.
5. Si el origen es válido: añade headers CORS. Si no: responde sin headers CORS (el navegador bloquea).

### 5.3 Caché

- **Clave**: `cors:tenant:{subdomain}`
- **TTL**: 600 segundos (10 minutos)
- **Valor**: booleano (tenant existe y está activo)
- **Invalidación**: automática al cambiar status del tenant via `TenantManagementService::changeStatus()`

### 5.4 Dominios base

Configurables via env:

```
CORS_BASE_DOMAINS=lapesquerapp.es,congeladosbrisamar.es
```

El middleware extrae el subdominio: `https://brisamar.lapesquerapp.es` produce `brisamar`.

### 5.5 Orígenes fijos (siempre permitidos)

| Origen | Motivo |
|---|---|
| `http://localhost:3000` | Desarrollo frontend tenants |
| `http://localhost:5173` | Desarrollo con Vite |
| `https://lapesquerapp.es` | Dominio principal |
| `https://admin.lapesquerapp.es` | Panel superadmin |
| `https://brisamar.congeladosbrisamar.es` | Dominio legacy |

### 5.6 Headers CORS en respuestas permitidas

```
Access-Control-Allow-Origin: {origin}
Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS
Access-Control-Allow-Headers: *
Access-Control-Allow-Credentials: true
Access-Control-Max-Age: 86400
Vary: Origin
```

---

## 6. Gestión de tenants

### 6.1 Operaciones CRUD

El `TenantController` expone operaciones protegidas por `SuperadminMiddleware`. La lógica reside en `TenantManagementService`.

**Crear tenant** (`POST /tenants`):
1. Valida datos (nombre, subdominio único con regex `^[a-z0-9]([a-z0-9\-]*[a-z0-9])?$`, email admin).
2. Crea registro con `status = 'pending'`, `database = 'tenant_{subdomain}'`, `onboarding_step = 0`.
3. Despacha `OnboardTenantJob` a la cola.
4. Devuelve 201 inmediatamente.

**Actualizar** (`PUT /tenants/{id}`): campos editables son `name`, `plan`, `renewal_at`, `timezone`, `branding_image_url`, `admin_email`. Los campos `subdomain` y `database` son inmutables.

**Cambios de estado** (`POST /tenants/{id}/activate|suspend|cancel`): actualiza `status`, invalida caché CORS, devuelve tenant actualizado.

### 6.2 Consulta de usuarios del tenant

`GET /tenants/{id}/users` realiza una lectura cross-tenant: configura la conexión `tenant` con la BD del tenant solicitado y ejecuta `User::on('tenant')`. Operación de solo lectura.

### 6.3 Dashboard

`GET /dashboard` devuelve totales por status, total general, y datos del último onboarding.

---

## 7. Onboarding automatizado

### 7.1 Visión general

Aprovisiona completamente un nuevo tenant: crea BD, migraciones, datos iniciales, usuario admin, activación. El proceso es **idempotente y reanudable**.

### 7.2 Pasos

| Paso | Acción | Idempotencia |
|---|---|---|
| 1 | Registro del tenant | No-op (ya existe) |
| 2 | `CREATE DATABASE IF NOT EXISTS tenant_{subdomain}` | `IF NOT EXISTS` |
| 3 | Migraciones (`database/migrations/companies`) | Laravel detecta ejecutadas |
| 4 | Seeder de producción (`TenantProductionSeeder`) | `updateOrInsert` |
| 5 | Crear usuario administrador | Skip si email existe |
| 6 | Guardar settings iniciales de empresa | `updateOrInsert` |
| 7 | Activar tenant (`status` -> `active`) | Idempotente |
| 8 | Enviar email de bienvenida | Se envía siempre |

Cada paso actualiza `onboarding_step`. Si falla en el paso 4, al relanzar ejecuta desde el paso 5.

### 7.3 Ejecución

- **Panel superadmin**: despacha `OnboardTenantJob` (3 reintentos, backoff 30s).
- **CLI**: `php artisan tenant:onboard {subdomain} {admin_email} --name=...`.
- **Relanzar**: `POST /tenants/{id}/retry-onboarding`.

### 7.4 TenantProductionSeeder

Seeder separado del de demo. Solo catálogos esenciales:

- Roles, categorías, familias de producto, zonas de captura, artes de pesca, especies, países, condiciones de pago, incoterms, impuestos, zonas FAO.
- Almacén por defecto: "Almacén Principal".
- Settings iniciales: `company.display_name`, `company.tax_id`, `company.address`, `company.city`, `company.postal_code`, `company.phone`, `company.email`, `company.logo_url`, `company.date_format` (d/m/Y), `company.currency` (EUR).

### 7.5 Email de bienvenida

`TenantWelcomeEmail` al `admin_email` con nombre empresa, URL del tenant, instrucciones de acceso por magic link.

### 7.6 Convención de nombres de BD

`tenant_{subdomain}`. El usuario MySQL debe tener permiso `CREATE DATABASE`.

---

## 8. Impersonación

### 8.1 Dos modos

| Aspecto | Con consentimiento | Silenciosa |
|---|---|---|
| Notificación | Email al usuario del tenant | Ninguna |
| Aprobación | Sí (click en link firmado) | No |
| Validez | 30 min desde aprobación | Inmediata |
| Audit log | mode: `consent` | mode: `silent` |
| Caso de uso | Soporte con conocimiento del cliente | Debugging urgente |

### 8.2 Flujo con consentimiento

1. Superadmin: `POST /impersonate/request { target_user_id }`.
2. Se crea `impersonation_request` (pending) con token hasheado.
3. Se envía `ImpersonationRequestEmail` al usuario con URLs firmadas de aprobación/rechazo.
4. El usuario hace click en "Aprobar": `GET /public/impersonation/{token}/approve` (ruta firmada, sin auth). Status pasa a `approved`, `expires_at = now + 30 min`.
5. El superadmin genera token: `POST /impersonate/token`. Verifica request aprobada y no expirada, conecta a BD tenant, crea Sanctum token para el usuario, registra en `impersonation_logs`.
6. Devuelve `{ impersonation_token, redirect_url, log_id }`.
7. Redirige a `{subdomain}.lapesquerapp.es/auth/impersonate?token={token}`.

Las URLs de aprobación/rechazo son rutas firmadas de Laravel (`URL::signedRoute`). No requieren autenticación.

### 8.3 Flujo silencioso

1. Superadmin: `POST /impersonate/silent { target_user_id, reason }` — `reason` **obligatorio**.
2. Conecta a BD tenant, crea Sanctum token con ability `'impersonation'`.
3. Registra en `impersonation_logs` (mode: `silent`, persiste `reason`).
4. Devuelve `{ impersonation_token, redirect_url, log_id }`.

### 8.4 Fin de sesión

`POST /impersonate/end { log_id }` registra `ended_at` en el log y **revoca el token Sanctum** del usuario impersonado en la BD del tenant.

### 8.5 Fin desde el panel (v2)

`POST /impersonation/logs/{log_id}/end` — permite al superadmin terminar la sesión directamente desde el historial del panel, incluso si el token ya no está activo. Idempotente.

### 8.6 Historial y sesiones activas (v2)

- `GET /impersonation/logs` — historial paginado con filtros por tenant, superadmin y fecha.
- `GET /impersonation/active` — sesiones iniciadas en las últimas 2 horas sin `ended_at`.

### 8.7 Token de impersonación

Es un token Sanctum estándar asociado al usuario del tenant, con la ability `'impersonation'`. El frontend puede detectar esta ability para mostrar indicadores visuales de sesión impersonada.

---

## 9. Configuración por tenant

### 9.1 Split de responsabilidad

| Dato | Almacenamiento | Modificable por |
|---|---|---|
| Nombre legal, subdominio, status, plan | BD central (`tenants`) | Superadmin |
| Nombre comercial, NIF, dirección, logo | BD tenant (`settings`) | Admin del tenant |

### 9.2 Settings iniciales tras onboarding

| Key | Origen | Default |
|---|---|---|
| `company.display_name` | `tenant.name` | - |
| `company.logo_url` | `tenant.branding_image_url` | null |
| `company.tax_id` | - | vacío |
| `company.address` | - | vacío |
| `company.city` | - | vacío |
| `company.postal_code` | - | vacío |
| `company.phone` | - | vacío |
| `company.email` | - | vacío |
| `company.date_format` | - | `d/m/Y` |
| `company.currency` | - | `EUR` |

### 9.3 Uso en vistas

Las plantillas PDF y emails usan `tenantSetting('company.display_name')` en lugar de valores hardcodeados.

---

## 10. Seguridad avanzada (v2)

### 10.1 Aislamiento de autenticación

- Tokens Sanctum de superadmin y tenant en tablas diferentes.
- `SuperadminMiddleware` verifica que el usuario sea instancia de `SuperadminUser`.
- Un token de tenant no puede autenticar como superadmin, y viceversa.

### 10.2 Protección de endpoints

| Grupo de rutas | Protección |
|---|---|
| `superadmin/auth/request-access`, `verify-*` | Rate limiting, sin auth |
| `superadmin/auth/me`, `logout` | `SuperadminMiddleware` |
| `superadmin/tenants/*`, `dashboard`, `impersonate/*` | `SuperadminMiddleware` |
| `superadmin/impersonation/*`, `alerts/*` | `SuperadminMiddleware` |
| `superadmin/migrations/*` | `SuperadminMiddleware` |
| `public/impersonation/{token}/*` | URL firmada (sin auth) |
| `v2/*` (tenant routes) | `TenantMiddleware` + `auth:sanctum` |

### 10.3 Auditoría de impersonación

Toda sesión queda en `impersonation_logs`: quién, en qué tenant, a quién, modo, motivo (`reason`), timestamps de inicio y fin. Fin de sesión revoca el token en la BD del tenant.

### 10.4 Tokens activos por tenant

`TenantManagementService` expone lectura cross-tenant de `personal_access_tokens`:
- `getActiveTokens(Tenant)` — lista tokens activos.
- `revokeToken(Tenant, int $tokenId)` — elimina un token.
- `revokeAllTokens(Tenant)` — elimina todos.

Controlador: `SecurityController` en `app/Http/Controllers/v2/Superadmin/`.

### 10.5 Blocklist IP/email

Tabla `tenant_blocklists` en BD central. El check ocurre en `AuthController::requestAccess()`, cacheado 5 min en Redis por tenant+valor. `SecurityController` gestiona CRUD de bloqueos.

### 10.6 Login attempts por tenant

Tabla `login_attempts` en cada BD de tenant. Registrada en `AuthController` para:
- `requestAccess()`: success = false si usuario no existe/inactivo, true si envío correcto.
- `verifyMagicLink()` y `verifyOtp()`: success = true al verificar.
Usada por `DetectSuspiciousActivity` para detectar ataques de fuerza bruta.

### 10.7 CORS como capa de seguridad

En producción, solo subdominios de tenants activos reciben headers CORS. Al suspender un tenant, la invalidación de caché bloquea peticiones inmediatamente.

### 10.8 Hashing de tokens

Tokens de magic link e impersonación se almacenan como hash SHA-256. El OTP se guarda en plano para comparación directa.

### 10.9 Middleware `CheckFeatureFlag` (v2)

Registrado como alias `feature`. Uso en rutas:

```php
Route::middleware('feature:module.production')->group(function () {
    // rutas del módulo de producción
});
```

Resuelve el tenant vía `app('currentTenant')`, consulta `FeatureFlagService` (con caché Redis 5 min). Devuelve 403 si el flag no está activo para el tenant.

---

## 11. Observabilidad y alertas (v2)

### 11.1 Errores 500 centralizados

`Handler.php` inserta en `tenant_error_logs` (BD central) para cada error 500 en peticiones de API de tenant. Solo registra cuando `app()->bound('currentTenant')` está activo. Sin stack trace para minimizar tamaño; los logs del servidor contienen el detalle.

Retención: 30 días (limpiado por `superadmin:prune-logs`).

Servicio: `ObservabilityService`. Controlador: `ObservabilityController`.

```
GET /superadmin/tenants/{tenant}/error-logs  -- errores del tenant (paginado)
GET /superadmin/error-logs                   -- todos agrupados (paginado)
```

### 11.2 Activity feed

Agregado de actividad reciente sin tabla propia: consulta `impersonation_logs`, `tenant_migration_runs`, `system_alerts` y `tenants` (cambios de estado). Devuelve lista cronológica unificada.

```
GET /superadmin/dashboard/activity?limit=50
```

### 11.3 Queue health

Lee de Redis (`queues:default`) y tabla `failed_jobs`. Devuelve `pending_jobs`, `failed_jobs`, `redis_status` y `healthy`.

```
GET /superadmin/system/queue-health
```

### 11.4 Sistema de alertas

Tabla `system_alerts` con deduplicación (`createIfNotExists`). Generadas por:
- `OnboardTenantJob::failed()` → `onboarding_failed` (critical).
- `superadmin:check-onboarding-stuck` → `onboarding_stuck` (warning).
- `superadmin:detect-suspicious` → `suspicious_activity` (warning), `queue_stopped` (critical).

```
GET  /superadmin/alerts           -- filtros: severity, resolved, tenant_id
POST /superadmin/alerts/{id}/resolve
```

### 11.5 Scheduler (Kernel.php)

| Comando | Frecuencia | Acción |
|---|---|---|
| `app:clean-old-order-pdfs` | Diario 02:00 | Limpia PDFs viejos |
| `auth:cleanup-magic-tokens` | Diario 03:00 | Limpia tokens expirados |
| `superadmin:detect-suspicious` | Cada 15 min | Detecta actividad sospechosa |
| `superadmin:check-onboarding-stuck` | Horario | Detecta onboardings atascados |
| `superadmin:prune-logs` | Diario 04:00 | Purga logs antiguos |

---

## 12. Feature flags (v2)

### 12.1 Modelo de datos

Dos tablas en BD central:
- `feature_flags` — defaults por (`flag_key`, `plan`). 12 flags × 3 planes = 36 filas.
- `tenant_feature_overrides` — overrides por (`tenant_id`, `flag_key`). Unique constraint.

### 12.2 Plans

| Plan | Descripción |
|---|---|
| `basic` | Core: ventas, inventario, recepciones |
| `pro` | Basic + producción, cebo, etiquetas, fichajes |
| `enterprise` | Pro + estadísticas, liquidaciones, importaciones, API |

### 12.3 Evaluación de flags

`FeatureFlagService::getEffectiveFlags(Tenant)`:
1. Carga defaults del plan del tenant desde `feature_flags`.
2. Carga overrides específicos del tenant desde `tenant_feature_overrides`.
3. Merge: los overrides sobreescriben los defaults del plan.
4. Resultado cacheado 5 min en Redis (`feature_flags:tenant:{id}:{plan}`).
5. Invalidar caché al crear/eliminar override.

### 12.4 Uso en rutas tenant

```php
// En rutas de la aplicación de tenant:
Route::middleware('feature:module.production')->group(fn() => ...);
```

### 12.5 Respuesta en /me

`GET /api/v2/me` incluye el array `features` con los flag keys activos para el tenant. El frontend de tenant usa este array para mostrar/ocultar módulos sin llamadas adicionales.

### 12.6 API superadmin

```
GET  /superadmin/feature-flags                          -- defaults por plan
GET  /superadmin/tenants/{tenant}/feature-flags         -- flags efectivos
PUT  /superadmin/tenants/{tenant}/feature-flags/{flag}  -- set override
DELETE /superadmin/tenants/{tenant}/feature-flags/{flag} -- remove override
```

---

## 13. Comandos Artisan y scheduler

### `superadmin:create`

```bash
php artisan superadmin:create jose@lapesquerapp.es --name="Jose"
```

Crea un usuario superadmin en la BD central.

### `tenant:onboard`

```bash
php artisan tenant:onboard brisamar admin@brisamar.es \
    --name="Congelados Brisamar S.L." --plan=pro --timezone=Europe/Madrid
```

Ejecuta el onboarding completo de forma síncrona.

### `superadmin:detect-suspicious` (v2)

```bash
php artisan superadmin:detect-suspicious
```

Analiza `login_attempts` de todos los tenants activos (últimas 1h). Crea alertas si hay >10 intentos fallidos/hora por IP o >5 por email. También verifica que Redis responda.

### `superadmin:check-onboarding-stuck` (v2)

```bash
php artisan superadmin:check-onboarding-stuck
```

Detecta tenants `pending` con onboarding sin error registrado y creados hace >30 min. Crea alerta `onboarding_stuck`.

### `superadmin:prune-logs` (v2)

```bash
php artisan superadmin:prune-logs
```

Purga `tenant_error_logs` con `occurred_at` > 30 días y `system_alerts` resueltas con `resolved_at` > 90 días.

### Comandos existentes actualizados

Usan `Tenant::active()` en lugar de `where('active', true)`:
- `tenants:migrate`, `cleanup:magic-link-tokens`, `tenant:create-user`.

---

## 14. Mapa de archivos

### Modelos

```
app/Models/Tenant.php                              -- Modelo central (status, onboarding, plan)
app/Models/SuperadminUser.php                      -- Usuario superadmin
app/Models/SuperadminMagicLinkToken.php            -- Tokens de acceso
app/Models/ImpersonationRequest.php                -- Solicitudes de impersonación
app/Models/ImpersonationLog.php                    -- Audit log (+ reason v2)
app/Sanctum/SuperadminPersonalAccessToken.php      -- Token Sanctum (BD central)
-- v2:
app/Models/TenantMigrationRun.php                  -- Historial de migraciones
app/Models/SystemAlert.php                         -- Alertas del sistema
app/Models/TenantBlocklist.php                     -- Bloqueos IP/email
app/Models/TenantErrorLog.php                      -- Log de errores 500
app/Models/FeatureFlag.php                         -- Flags por plan
app/Models/TenantFeatureOverride.php               -- Overrides por tenant
```

### Servicios

```
app/Services/SuperadminAuthService.php             -- Magic link + OTP superadmin
app/Services/Superadmin/TenantManagementService.php   -- CRUD, dashboard, tokens, caché CORS
app/Services/Superadmin/TenantOnboardingService.php   -- Pipeline 8 pasos + observabilidad
app/Services/Superadmin/ImpersonationService.php      -- Consentimiento + silenciosa + historial
-- v2:
app/Services/Superadmin/TenantMigrationService.php    -- Estado y ejecución de migraciones
app/Services/Superadmin/ObservabilityService.php      -- Error logs, activity feed, queue
app/Services/Superadmin/FeatureFlagService.php        -- Flags con cache Redis
```

### Controladores

```
app/Http/Controllers/v2/Superadmin/AuthController.php
app/Http/Controllers/v2/Superadmin/TenantController.php
app/Http/Controllers/v2/Superadmin/DashboardController.php
app/Http/Controllers/v2/Superadmin/ImpersonationController.php    -- ampliado v2
app/Http/Controllers/Public/ImpersonationApprovalController.php
-- v2:
app/Http/Controllers/v2/Superadmin/SecurityController.php         -- tokens + blocklist
app/Http/Controllers/v2/Superadmin/TenantMigrationController.php  -- migraciones panel
app/Http/Controllers/v2/Superadmin/ObservabilityController.php    -- errores + feed + queue + alertas
app/Http/Controllers/v2/Superadmin/FeatureFlagController.php      -- feature flags
```

### Middleware

```
app/Http/Middleware/DynamicCorsMiddleware.php       -- CORS dinámico
app/Http/Middleware/SuperadminMiddleware.php        -- Auth guard superadmin
app/Http/Middleware/TenantMiddleware.php            -- Status check del tenant
app/Http/Middleware/CheckFeatureFlag.php            -- v2: alias 'feature'
```

### Jobs y Commands

```
app/Jobs/OnboardTenantJob.php                      -- 3 reintentos, backoff 30s, crea alerta al fallar
app/Jobs/MigrateTenantJob.php                      -- v2: migra un tenant y registra TenantMigrationRun
app/Console/Commands/CreateSuperadmin.php
app/Console/Commands/OnboardTenant.php
app/Console/Commands/MigrateTenants.php
app/Console/Commands/DetectSuspiciousActivity.php  -- v2
app/Console/Commands/CheckOnboardingStuck.php      -- v2
app/Console/Commands/PruneLogs.php                 -- v2
```

### Requests y Resources

```
app/Http/Requests/v2/Superadmin/StoreTenantRequest.php
app/Http/Requests/v2/Superadmin/UpdateTenantRequest.php
app/Http/Resources/v2/Superadmin/TenantResource.php     -- incluye bloque onboarding
app/Http/Resources/v2/Superadmin/TenantUserResource.php
```

### Migraciones (BD central)

```
-- v1:
2026_02_23_150000_alter_tenants_table_saas_infrastructure.php
2026_02_23_150100_create_superadmin_users_table.php
2026_02_23_150200_create_superadmin_magic_link_tokens_table.php
2026_02_23_150300_create_superadmin_personal_access_tokens_table.php
2026_02_23_150400_create_impersonation_requests_table.php
2026_02_23_150500_create_impersonation_logs_table.php
2026_02_23_200000_add_onboarding_error_to_tenants_table.php
-- v2:
2026_02_23_210000_add_reason_to_impersonation_logs.php
2026_02_23_210100_create_tenant_migration_runs_table.php
2026_02_23_210300_create_system_alerts_table.php
2026_02_23_210400_create_tenant_blocklists_table.php
2026_02_23_210500_create_tenant_error_logs_table.php
2026_02_23_210600_create_feature_flags_table.php
2026_02_23_210700_create_tenant_feature_overrides_table.php
```

### Migraciones (BD tenant)

```
-- v2:
database/migrations/companies/2026_02_23_210200_create_login_attempts_table.php
```

### Seeders

```
database/seeders/TenantProductionSeeder.php
database/seeders/FeatureFlagSeeder.php              -- v2: 12 flags × 3 planes
```

### Emails

```
app/Mail/SuperadminAccessEmail.php
app/Mail/TenantWelcomeEmail.php
app/Mail/ImpersonationRequestEmail.php
resources/views/emails/tenant-welcome.blade.php
resources/views/emails/impersonation-request.blade.php
resources/views/impersonation/approved.blade.php
resources/views/impersonation/rejected.blade.php
```

### Configuración

```
config/auth.php           -- Guard 'superadmin' + provider
config/cors.php           -- Orígenes fijos + base_domains
config/superadmin.php     -- frontend_url
```

### Tests

```
tests/Feature/SuperadminAuthTest.php         -- 11 tests
tests/Feature/SuperadminTenantCrudTest.php   -- 12 tests
tests/Feature/DynamicCorsTest.php            -- 8 tests
tests/Feature/ImpersonationTest.php          -- 4 tests
tests/Feature/CorsRegressionTest.php         -- 13 tests
```
