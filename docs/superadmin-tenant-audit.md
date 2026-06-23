# API Reference — Panel Superadmin (Gestión de Tenants)

> **Propósito:** Referencia completa de todos los endpoints del panel superadmin. Dirigida al equipo de frontend para implementar o verificar la integración con el backend.
>
> **Base URL:** `https://api.lapesquerapp.es/api/v2/superadmin/`  
> **Autenticación:** `Authorization: Bearer {superadmin_token}` (excepto endpoints de auth y el endpoint público)  
> **Header `X-Tenant`:** NO se usa en rutas superadmin  
> **Última revisión:** 2026-06-23 — sistema completamente implementado

---

## Índice

1. [Arquitectura del Sistema Superadmin](#1-arquitectura-del-sistema-superadmin)
2. [Autenticación Superadmin](#2-autenticación-superadmin)
3. [CRUD de Tenants](#3-crud-de-tenants)
4. [Gestión de Estado y Onboarding](#4-gestión-de-estado-y-onboarding)
5. [Usuarios del Tenant](#5-usuarios-del-tenant)
6. [Seguridad — Tokens y Blocklist](#6-seguridad--tokens-y-blocklist)
7. [Panel de Migraciones](#7-panel-de-migraciones)
8. [Impersonación](#8-impersonación)
9. [Feature Flags](#9-feature-flags)
10. [Observabilidad y Alertas](#10-observabilidad-y-alertas)
11. [Dashboard Superadmin](#11-dashboard-superadmin)
12. [Gestión de Usuarios Superadmin](#12-gestión-de-usuarios-superadmin)
13. [Endpoint Público (sin auth)](#13-endpoint-público-sin-auth)
14. [Referencia rápida de endpoints](#14-referencia-rápida-de-endpoints)
15. [Checklist para el Frontend](#15-checklist-para-el-frontend)

---

## 1. Arquitectura del Sistema Superadmin

### Separación total del sistema tenant

El sistema superadmin es completamente independiente del sistema multi-tenant:

| Aspecto | Sistema Tenant | Sistema Superadmin |
|---|---|---|
| **Base de datos** | `tenant_{subdomain}` (una por empresa) | `mysql` (base central) |
| **Autenticación** | `personal_access_tokens` + User | `superadmin_personal_access_tokens` + SuperadminUser |
| **Header requerido** | `X-Tenant: {subdomain}` | No — rutas fuera del TenantMiddleware |
| **Middleware** | `tenant` + `auth:sanctum` | `superadmin` (middleware custom) |
| **Prefijo API** | `/api/v2/` | `/api/v2/superadmin/` |

### Tablas en la base central (`mysql`)

```
tenants                             — registro de cada empresa
superadmin_users                    — usuarios con acceso superadmin
superadmin_magic_link_tokens        — tokens de autenticación (magic link + OTP)
superadmin_personal_access_tokens   — tokens Sanctum del superadmin
tenant_blocklists                   — bloqueos IP/email por tenant
tenant_migration_runs               — historial de ejecuciones de migraciones
tenant_error_logs                   — errores capturados por tenant
tenant_feature_overrides            — overrides de feature flags por tenant
impersonation_logs                  — auditoría de impersonaciones
system_alerts                       — alertas del sistema (ej. onboarding fallido)
```

### Modelo de estado de un Tenant

```
pending ──┬──────────────────────────────── cancelled
          │  (activación automática vía onboarding)
          └──> active ──┬── suspended ──┬── cancelled
                        │               │
                        └───────────────┘
                     suspended ──────────> active (si onboarding completo)
                     cancelled ──────────> active (si onboarding completo)
```

**Reglas de transición:**

| Estado actual | Puede ir a |
|---|---|
| `pending` | `cancelled` únicamente (la activación la hace el onboarding automáticamente) |
| `active` | `suspended`, `cancelled` |
| `suspended` | `active`, `cancelled` |
| `cancelled` | `active` (solo si `onboarding_step >= 8`) |

> **Importante:** Un tenant `pending` **no se puede activar manualmente**. El paso 7 del onboarding lo activa de forma automática. Si el onboarding falla, se usa el endpoint `retry-onboarding`.

### Comportamiento del blocklist

Los bloqueos de IP y email funcionan así en tiempo real:
- **IP:** el `TenantMiddleware` comprueba la IP de cada request contra la blocklist activa del tenant antes de procesarlo (resultado cacheado 5 min).
- **Email:** un middleware post-autenticación (`CheckTenantEmailBlocklist`) comprueba el email del usuario ya autenticado contra la blocklist activa (resultado cacheado 5 min).
- Cuando se crea o elimina un bloqueo, el caché del entry concreto se invalida inmediatamente.
- El endpoint `GET /blocks` devuelve todos los bloqueos (activos y expirados). El frontend debe filtrar por `expires_at` nulo o futuro para mostrar solo los activos.

---

## 2. Autenticación Superadmin

El superadmin **no usa contraseña**. El acceso es exclusivamente por magic link + OTP de 6 dígitos enviados al email registrado. El token resultante es un Bearer Sanctum almacenado en `superadmin_personal_access_tokens`, separado de los tokens de usuario tenant.

### 2.1 Solicitar acceso

```
POST /api/v2/superadmin/auth/request-access
Throttle: 5 intentos/minuto
Sin autenticación
```

**Request:**
```json
{ "email": "admin@lapesquerapp.es" }
```

**Respuesta éxito (200):**
```json
{
  "message": "Si el correo está registrado, recibirás un correo con un enlace y un código para acceder."
}
```

**Respuesta email no registrado (422):**
```json
{
  "message": "No existe un usuario superadmin con este correo electrónico.",
  "errors": { "email": ["No existe un usuario superadmin con este correo electrónico."] }
}
```

El email enviado contiene:
- Un **magic link** con token único (válido 10 minutos)
- Un **código OTP** de 6 dígitos (válido 10 minutos)

### 2.2 Verificar con magic link

```
POST /api/v2/superadmin/auth/verify-magic-link
Throttle: 10 intentos/minuto
Sin autenticación
```

**Request:**
```json
{ "token": "token-del-magic-link" }
```

**Respuesta éxito (200):**
```json
{
  "access_token": "1|abc123...",
  "token_type": "Bearer",
  "user": {
    "id": 1,
    "name": "Jose",
    "email": "admin@lapesquerapp.es"
  }
}
```

**Respuesta token inválido o expirado (400):**
```json
{ "message": "El enlace no es válido o ha expirado. Solicita uno nuevo." }
```

### 2.3 Verificar con código OTP

```
POST /api/v2/superadmin/auth/verify-otp
Throttle: 10 intentos/minuto
Sin autenticación
```

**Request:**
```json
{
  "email": "admin@lapesquerapp.es",
  "code": "123456"
}
```

**Respuesta éxito (200):** Mismo schema que el magic link.

**Respuesta código inválido o expirado (400):**
```json
{ "message": "El código no es válido o ha expirado. Solicita uno nuevo." }
```

### 2.4 Ver sesión activa

```
GET /api/v2/superadmin/auth/me
Authorization: Bearer {token}
```

**Respuesta (200):**
```json
{
  "data": {
    "id": 1,
    "name": "Jose",
    "email": "admin@lapesquerapp.es",
    "last_login_at": "2026-06-23T10:30:00.000000Z"
  }
}
```

### 2.5 Cerrar sesión

```
POST /api/v2/superadmin/auth/logout
Authorization: Bearer {token}
```

**Respuesta (200):**
```json
{ "message": "Sesión cerrada correctamente" }
```

---

## 3. CRUD de Tenants

Todos estos endpoints requieren `Authorization: Bearer {superadmin_token}`. No se usa `X-Tenant`.

### 3.1 Listar tenants

```
GET /api/v2/superadmin/tenants
```

**Query params:**

| Parámetro | Tipo | Descripción |
|---|---|---|
| `status` | string | Filtrar: `pending`, `active`, `suspended`, `cancelled` |
| `search` | string | Búsqueda LIKE por nombre o subdominio |
| `per_page` | int | Resultados por página (default: 15) |

**Respuesta (200):**
```json
{
  "data": [
    {
      "id": 1,
      "name": "Congelados Brisamar",
      "subdomain": "brisamar",
      "database": "tenant_brisamar",
      "status": "active",
      "plan": "professional",
      "renewal_at": "2026-12-31",
      "timezone": "Europe/Madrid",
      "branding_image_url": "https://cdn.lapesquerapp.es/brisamar-logo.png",
      "last_activity_at": "2026-06-23T10:30:00.000000Z",
      "admin_email": "admin@brisamar.es",
      "onboarding": {
        "step": 8,
        "total_steps": 8,
        "step_label": "Email de bienvenida",
        "status": "completed",
        "error": null,
        "failed_at": null
      },
      "created_at": "2026-01-15T12:00:00.000000Z",
      "updated_at": "2026-06-23T14:45:00.000000Z"
    }
  ],
  "links": { "first": "...", "last": "...", "prev": null, "next": "..." },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 3,
    "per_page": 15,
    "to": 15,
    "total": 42
  }
}
```

**Valores de `onboarding.status`:**

| Valor | Significado |
|---|---|
| `pending` | Step 0 — job no ejecutado aún |
| `in_progress` | Job en curso |
| `failed` | Falló — ver `onboarding.error` |
| `completed` | Step 8/8 completado |

> **Nota:** `last_activity_at` se actualiza automáticamente máximo una vez cada 5 minutos cuando cualquier usuario del tenant hace un request.

### 3.2 Crear tenant

```
POST /api/v2/superadmin/tenants
Content-Type: application/json
```

**Body:**
```json
{
  "name": "Congelados Brisamar S.L.",
  "subdomain": "brisamar",
  "admin_email": "admin@brisamar.es",
  "plan": "professional",
  "timezone": "Europe/Madrid",
  "branding_image_url": "https://cdn.lapesquerapp.es/logo.png"
}
```

**Campos:**

| Campo | Obligatorio | Reglas |
|---|---|---|
| `name` | ✅ | string, max 255 |
| `subdomain` | ✅ | solo `[a-z0-9]` y guiones, max 63, no guión al inicio/final, único en sistema |
| `admin_email` | ✅ | email válido, max 255 |
| `plan` | ❌ | string, max 50 |
| `timezone` | ❌ | string, max 50 (default: `Europe/Madrid`) |
| `branding_image_url` | ❌ | URL válida, max 500 |

**Respuesta éxito (201):**
```json
{
  "data": {
    "id": 5,
    "name": "Congelados Brisamar S.L.",
    "subdomain": "brisamar",
    "database": "tenant_brisamar",
    "status": "pending",
    "onboarding": {
      "step": 0,
      "total_steps": 8,
      "step_label": "Pendiente",
      "status": "pending",
      "error": null,
      "failed_at": null
    },
    "created_at": "2026-06-23T15:00:00.000000Z",
    "updated_at": "2026-06-23T15:00:00.000000Z"
  },
  "message": "Tenant creado. Onboarding en progreso."
}
```

> **Flujo tras crear:** El backend despacha `OnboardTenantJob` en background (8 pasos automáticos, ver sección 4). El frontend debe hacer polling en `GET /tenants/{id}/onboarding-status` hasta que `status` sea `completed` o `failed`.

**Respuesta subdomain duplicado (422):**
```json
{
  "message": "The subdomain has already been taken.",
  "errors": { "subdomain": ["The subdomain has already been taken."] }
}
```

### 3.3 Ver tenant

```
GET /api/v2/superadmin/tenants/{tenant}
```

`{tenant}` es el **ID numérico** del tenant.

**Respuesta (200):** Mismo schema que el objeto del listado.

### 3.4 Actualizar tenant

```
PUT /api/v2/superadmin/tenants/{tenant}
Content-Type: application/json
```

Todos los campos son opcionales — enviar solo los que se quieren modificar.

**Body:**
```json
{
  "name": "Brisamar Pesca S.L.",
  "plan": "enterprise",
  "renewal_at": "2027-01-01",
  "timezone": "Atlantic/Canary",
  "branding_image_url": "https://cdn.lapesquerapp.es/nuevo-logo.png",
  "admin_email": "nuevo-admin@brisamar.es"
}
```

**Campos actualizables:**

| Campo | Tipo | Notas |
|---|---|---|
| `name` | string, max 255 | — |
| `plan` | string, max 50, nullable | — |
| `renewal_at` | date, nullable | — |
| `timezone` | string, max 50, nullable | — |
| `branding_image_url` | URL, max 500, nullable | — |
| `admin_email` | email, max 255, nullable | — |

> `subdomain` y `database` son **inmutables** — no se pueden actualizar.

**Respuesta (200):** TenantResource actualizado.

### 3.5 Eliminar tenant

```
DELETE /api/v2/superadmin/tenants/{tenant}?confirm_delete=true&drop_database=false
```

**Query params:**

| Parámetro | Requerido | Descripción |
|---|---|---|
| `confirm_delete` | ✅ (valor `true`) | Confirmación explícita — debe ir en la URL, no es solo un modal |
| `drop_database` | ❌ (default: `false`) | Si `true` ejecuta `DROP DATABASE tenant_{subdomain}` |

**Restricción:** Solo se puede eliminar si el tenant está en `cancelled` o `pending`. Cancelar primero si está `active` o `suspended`.

**Respuesta éxito (200):**
```json
{
  "message": "Tenant eliminado correctamente.",
  "details": {
    "tenant": "brisamar",
    "database_dropped": true
  }
}
```

**Respuesta sin query param de confirmación (422):**
```json
{
  "message": "Debes confirmar la eliminación añadiendo ?confirm_delete=true",
  "tenant": "brisamar",
  "status": "cancelled"
}
```

**Respuesta estado inválido (422):**
```json
{
  "message": "Solo se pueden eliminar tenants en estado 'cancelled' o 'pending'. Estado actual: 'active'. Cancela el tenant primero."
}
```

---

## 4. Gestión de Estado y Onboarding

### 4.1 Cambiar estado

Tres endpoints dedicados, cada uno valida la transición en el backend:

```
POST /api/v2/superadmin/tenants/{tenant}/activate
POST /api/v2/superadmin/tenants/{tenant}/suspend
POST /api/v2/superadmin/tenants/{tenant}/cancel
```

Sin body. **Respuesta éxito (200):** TenantResource actualizado.

**Respuesta transición inválida (422):**
```json
{
  "message": "No se puede cambiar de 'pending' a 'active'.",
  "current_status": "pending",
  "requested_status": "active",
  "onboarding": {
    "step": 3,
    "total_steps": 8,
    "step_label": "Migraciones",
    "status": "in_progress"
  }
}
```

**Respuesta activar con onboarding incompleto (422):**
```json
{
  "message": "No se puede activar un tenant que no ha completado el onboarding (paso 6/8).",
  "current_status": "cancelled",
  "requested_status": "active",
  "onboarding": { "step": 6, "total_steps": 8, "status": "in_progress" }
}
```

### 4.2 Consultar estado de onboarding

```
GET /api/v2/superadmin/tenants/{tenant}/onboarding-status
```

Usar para polling tras crear un tenant o relanzar onboarding.

**Respuesta en progreso (200):**
```json
{
  "data": {
    "step": 5,
    "total_steps": 8,
    "step_label": "Usuario administrador",
    "status": "in_progress",
    "error": null,
    "failed_at": null
  }
}
```

**Respuesta cuando falla (200):**
```json
{
  "data": {
    "step": 3,
    "total_steps": 8,
    "step_label": "Migraciones",
    "status": "failed",
    "error": "Paso 3 (Migraciones): SQLSTATE[HY000] [2002] Connection refused",
    "failed_at": "2026-06-23T15:05:12.000000Z"
  }
}
```

### 4.3 Reintentar onboarding

```
POST /api/v2/superadmin/tenants/{tenant}/retry-onboarding
```

Sin body. Reanuda desde el paso donde falló (idempotente). Limpia el error y despacha `OnboardTenantJob` de nuevo.

**Respuesta relanzado (200):**
```json
{
  "message": "Onboarding relanzado.",
  "onboarding": {
    "step": 3,
    "total_steps": 8,
    "step_label": "Migraciones",
    "status": "in_progress",
    "error": null,
    "failed_at": null
  }
}
```

**Respuesta ya completado (200):**
```json
{
  "message": "El onboarding ya se completó.",
  "onboarding": { "step": 8, "status": "completed" }
}
```

### 4.4 Pasos del onboarding

| Paso | Label | Qué hace |
|---|---|---|
| 0 | Pendiente | Tenant creado, job aún no arrancó |
| 1 | Registro de tenant | No-op (registro ya creado) |
| 2 | Creación de base de datos | `CREATE DATABASE tenant_{subdomain}` |
| 3 | Migraciones | `php artisan migrate --path=database/migrations/companies` |
| 4 | Datos iniciales (seeder) | `TenantProductionSeeder` (catálogos del sector) |
| 5 | Usuario administrador | Crea usuario con rol `administrador` en BD del tenant |
| 6 | Configuración inicial | Sincroniza nombre y logo a tabla `settings` del tenant |
| 7 | Activación | Cambia `status` a `active` automáticamente |
| 8 | Email de bienvenida | Envía email al `admin_email` con la URL del tenant |

El job tiene **3 reintentos** con **30 segundos de backoff**. Si los 3 intentos fallan, se crea una `SystemAlert` de severidad `critical`.

---

## 5. Usuarios del Tenant

```
GET /api/v2/superadmin/tenants/{tenant}/users
```

Lee los usuarios desde la base de datos del tenant (cross-tenant read, solo lectura).

**Requisito:** `onboarding_step >= 5` (la BD del tenant debe estar provisionada con el paso de creación de usuario administrador completado).

**Respuesta éxito (200):**
```json
{
  "data": [
    {
      "id": 1,
      "name": "Admin Brisamar",
      "email": "admin@brisamar.es",
      "role": "administrador",
      "active": true,
      "created_at": "2026-01-15T12:00:00.000000Z"
    }
  ]
}
```

**Respuesta BD no disponible (422):**
```json
{
  "message": "La base de datos del tenant 'brisamar' aún no está disponible. Onboarding en paso 3/8.",
  "onboarding": { "step": 3, "total_steps": 8, "status": "in_progress" }
}
```

---

## 6. Seguridad — Tokens y Blocklist

### 6.1 Listar tokens Sanctum activos

```
GET /api/v2/superadmin/tenants/{tenant}/tokens
```

Lista los tokens Sanctum de todos los usuarios del tenant.

**Requisito:** `onboarding_step >= 5`.

**Respuesta (200):**
```json
{
  "data": [
    {
      "id": 42,
      "tokenable_id": 1,
      "name": "web_session",
      "abilities": ["*"],
      "last_used_at": "2026-06-23T10:00:00.000000Z",
      "created_at": "2026-06-20T09:00:00.000000Z",
      "expires_at": null
    }
  ],
  "total": 1
}
```

### 6.2 Revocar token específico

```
DELETE /api/v2/superadmin/tenants/{tenant}/tokens/{tokenId}
```

**Respuesta (200):** `{ "message": "Token revocado correctamente." }`  
**Token no encontrado (404):** `{ "message": "Token no encontrado." }`

### 6.3 Revocar todos los tokens

```
DELETE /api/v2/superadmin/tenants/{tenant}/tokens
```

**Respuesta (200):**
```json
{
  "message": "Todos los tokens han sido revocados.",
  "tokens_revoked": 5
}
```

### 6.4 Listar bloqueos

```
GET /api/v2/superadmin/tenants/{tenant}/blocks
```

Devuelve todos los bloqueos del tenant, activos y expirados. Para mostrar solo los activos en la UI, filtrar por `expires_at === null || expires_at > now`.

**Respuesta (200):**
```json
{
  "data": [
    {
      "id": 1,
      "tenant_id": 5,
      "type": "ip",
      "value": "192.168.1.100",
      "blocked_by": { "id": 1, "name": "Jose" },
      "reason": "Intentos de acceso sospechosos",
      "expires_at": "2026-07-01T00:00:00.000000Z",
      "created_at": "2026-06-23T15:00:00.000000Z",
      "updated_at": "2026-06-23T15:00:00.000000Z"
    }
  ]
}
```

### 6.5 Crear bloqueo

```
POST /api/v2/superadmin/tenants/{tenant}/block
Content-Type: application/json
```

**Body:**
```json
{
  "type": "ip",
  "value": "192.168.1.100",
  "reason": "Intentos de acceso sospechosos",
  "expires_at": "2026-07-01T00:00:00"
}
```

| Campo | Obligatorio | Valores |
|---|---|---|
| `type` | ✅ | `ip` o `email` |
| `value` | ✅ | La IP o email a bloquear |
| `reason` | ❌ | Texto libre, max 500 |
| `expires_at` | ❌ | Fecha de expiración (ausente o null = permanente) |

**Respuesta (201):**
```json
{
  "message": "Bloqueo creado.",
  "data": { ...el bloqueo creado... }
}
```

> El bloqueo es efectivo de inmediato. Los requests desde esa IP o de ese email quedan bloqueados con 403 en el siguiente request (el caché previo expira en máximo 5 min).

### 6.6 Eliminar bloqueo

```
DELETE /api/v2/superadmin/tenants/{tenant}/blocks/{blockId}
```

**Respuesta (200):** `{ "message": "Bloqueo eliminado." }`

---

## 7. Panel de Migraciones

### 7.1 Estado de migraciones de un tenant

```
GET /api/v2/superadmin/tenants/{tenant}/migrations
```

**Respuesta (200):**
```json
{
  "data": {
    "ran": [
      "2025_07_18_214606_create_tenants_table",
      "2026_02_23_150000_alter_tenants_table_saas_infrastructure"
    ],
    "pending": [
      "2026_06_20_200000_add_column_to_orders"
    ]
  }
}
```

### 7.2 Ejecutar migraciones en un tenant

```
POST /api/v2/superadmin/tenants/{tenant}/migrations/run
```

Sin body. Encola `MigrateTenantJob` en background. Crea registro en `tenant_migration_runs`.

**Respuesta (200):**
```json
{
  "message": "Migraciones encoladas para el tenant.",
  "run_id": 12,
  "tenant": "brisamar"
}
```

### 7.3 Historial de migraciones de un tenant

```
GET /api/v2/superadmin/tenants/{tenant}/migrations/history?per_page=15
```

**Respuesta (200):**
```json
{
  "data": [
    {
      "id": 12,
      "tenant_id": 5,
      "triggered_by_superadmin_id": 1,
      "migrations_applied": 3,
      "output": "Migrating: 2026_06_20...\nMigrated:  2026_06_20...",
      "success": true,
      "started_at": "2026-06-23T15:00:00.000000Z",
      "finished_at": "2026-06-23T15:00:05.000000Z"
    }
  ],
  "meta": { "current_page": 1, "last_page": 1, "total": 1 }
}
```

### 7.4 Ejecutar migraciones en todos los tenants activos

```
POST /api/v2/superadmin/migrations/run-all
```

Sin body.

**Respuesta (200):**
```json
{
  "message": "Migraciones encoladas para todos los tenants activos.",
  "tenants_queued": 8
}
```

---

## 8. Impersonación

Permite a un superadmin acceder como si fuera un usuario de un tenant. Todo queda registrado en `impersonation_logs`.

### 8.1 Solicitar impersonación con consentimiento

```
POST /api/v2/superadmin/tenants/{tenant}/impersonate/request
Content-Type: application/json
```

Envía email al admin del tenant solicitando aprobación. El admin recibe enlace firmado para aprobar o rechazar.

**Body:**
```json
{
  "target_user_id": 1,
  "reason": "Revisión de incidencia #1234"
}
```

**Respuesta (200):**
```json
{
  "message": "Solicitud de impersonación enviada. Esperando aprobación del usuario.",
  "request_id": 7
}
```

### 8.2 Impersonación silenciosa (sin consentimiento)

```
POST /api/v2/superadmin/tenants/{tenant}/impersonate/silent
Content-Type: application/json
```

Acceso inmediato sin esperar aprobación. `reason` es obligatorio.

**Body:**
```json
{
  "target_user_id": 1,
  "reason": "Investigación de error crítico en producción"
}
```

**Respuesta (200):**
```json
{
  "access_token": "token-sanctum-del-usuario-tenant",
  "log_id": 15,
  "tenant": "brisamar",
  "target_user": { "id": 1, "name": "Admin Brisamar" }
}
```

### 8.3 Generar token desde solicitud aprobada

```
POST /api/v2/superadmin/tenants/{tenant}/impersonate/token
```

Sin body. Tras aprobación del admin del tenant.

**Respuesta (200):** Igual que la impersonación silenciosa.

### 8.4 Finalizar sesión de impersonación

```
POST /api/v2/superadmin/impersonate/end
Content-Type: application/json
```

**Body:** `{ "log_id": 15 }`

**Respuesta (200):** `{ "message": "Sesión de impersonación finalizada." }`

### 8.5 Finalizar sesión desde el panel

```
POST /api/v2/superadmin/impersonation/logs/{log}/end
```

Sin body. Respuesta igual.

### 8.6 Historial de impersonaciones

```
GET /api/v2/superadmin/impersonation/logs
```

**Query params opcionales:**

| Parámetro | Tipo | Descripción |
|---|---|---|
| `tenant_id` | int | Filtrar por tenant |
| `superadmin_user_id` | int | Filtrar por superadmin |
| `from` | date | Desde esta fecha |
| `per_page` | int | Default: 20 |

**Respuesta (200):**
```json
{
  "data": [
    {
      "id": 15,
      "superadmin": "Jose",
      "tenant": "brisamar",
      "tenant_id": 5,
      "target_user_id": 1,
      "mode": "silent",
      "reason": "Investigación de error crítico",
      "started_at": "2026-06-23T15:00:00.000000Z",
      "ended_at": "2026-06-23T15:30:00.000000Z",
      "duration_minutes": 30
    }
  ],
  "meta": { "current_page": 1, "last_page": 1, "total": 1 }
}
```

### 8.7 Sesiones de impersonación activas

```
GET /api/v2/superadmin/impersonation/active
```

Lista sesiones iniciadas en las últimas 2 horas y no finalizadas.

**Respuesta (200):**
```json
{
  "data": [...],
  "total": 1
}
```

### Endpoints públicos de aprobación (sin auth)

Recibidos por email por el admin del tenant. Son URLs firmadas:

```
GET /api/v2/public/impersonation/{token}/approve
GET /api/v2/public/impersonation/{token}/reject
```

---

## 9. Feature Flags

### 9.1 Defaults de flags por plan

```
GET /api/v2/superadmin/feature-flags
```

**Respuesta (200):**
```json
{
  "data": [
    {
      "flag_key": "crm.enabled",
      "description": "Módulo CRM habilitado",
      "basic": false,
      "professional": true,
      "enterprise": true
    }
  ]
}
```

### 9.2 Flags efectivos de un tenant

```
GET /api/v2/superadmin/tenants/{tenant}/feature-flags
```

Combina los defaults del plan del tenant con sus overrides específicos.

**Respuesta (200):**
```json
{
  "tenant": "brisamar",
  "plan": "professional",
  "data": [
    {
      "flag_key": "crm.enabled",
      "enabled": true,
      "has_override": false
    },
    {
      "flag_key": "advanced_reports",
      "enabled": true,
      "has_override": true
    }
  ]
}
```

### 9.3 Establecer override de un flag

```
PUT /api/v2/superadmin/tenants/{tenant}/feature-flags/{flag}
Content-Type: application/json
```

**Body:**
```json
{
  "enabled": true,
  "reason": "Prueba piloto acordada con el cliente"
}
```

**Respuesta (200):**
```json
{
  "message": "Override guardado.",
  "data": { ...el override creado... }
}
```

### 9.4 Eliminar override (volver al default del plan)

```
DELETE /api/v2/superadmin/tenants/{tenant}/feature-flags/{flag}
```

**Respuesta (200):** `{ "message": "Override eliminado. El tenant vuelve al valor del plan." }`  
**Sin override previo (404):** `{ "message": "No existía override para este flag." }`

---

## 10. Observabilidad y Alertas

### 10.1 Logs de error de un tenant

```
GET /api/v2/superadmin/tenants/{tenant}/error-logs?per_page=20&days=30
```

| Parámetro | Default | Descripción |
|---|---|---|
| `per_page` | 20 | Resultados por página |
| `days` | 30 | Últimos N días |

**Respuesta (200):**
```json
{
  "data": [
    {
      "id": 100,
      "tenant_id": 5,
      "user_id": 1,
      "method": "POST",
      "url": "/api/v2/orders",
      "error_class": "Illuminate\\Database\\QueryException",
      "error_message": "SQLSTATE[23000]: Integrity constraint violation...",
      "occurred_at": "2026-06-23T10:15:00.000000Z"
    }
  ],
  "meta": { "current_page": 1, "last_page": 2, "total": 25 }
}
```

### 10.2 Logs de error globales (todos los tenants)

```
GET /api/v2/superadmin/error-logs?per_page=50&days=30
```

Misma respuesta que el anterior.

### 10.3 Feed de actividad reciente

```
GET /api/v2/superadmin/dashboard/activity?limit=50
```

Agrega impersonaciones, migraciones, alertas y cambios de tenant.

**Respuesta (200):**
```json
{
  "data": [
    {
      "type": "impersonation",
      "timestamp": "2026-06-23T15:00:00.000000Z",
      "description": "Jose impersonó a Admin Brisamar (tenant: brisamar)"
    },
    {
      "type": "migration",
      "timestamp": "2026-06-23T14:00:00.000000Z",
      "description": "Migraciones ejecutadas en brisamar (3 aplicadas)"
    }
  ]
}
```

### 10.4 Estado de la cola

```
GET /api/v2/superadmin/system/queue-health
```

**Respuesta (200):**
```json
{
  "data": {
    "pending_jobs": 0,
    "failed_jobs": 2,
    "driver": "database"
  }
}
```

### 10.5 Alertas del sistema

```
GET /api/v2/superadmin/alerts
```

**Query params opcionales:**

| Parámetro | Valores | Descripción |
|---|---|---|
| `severity` | `info`, `warning`, `critical` | Filtrar por severidad |
| `resolved` | `true`, `false` (default) | Incluir resueltas o no |
| `tenant_id` | int | Filtrar por tenant |
| `per_page` | int | Default: 20 |

**Respuesta (200):**
```json
{
  "data": [
    {
      "id": 3,
      "tenant": { "id": 5, "subdomain": "brisamar" },
      "type": "onboarding_failed",
      "severity": "critical",
      "message": "Onboarding falló en paso 3 para tenant brisamar",
      "resolved_at": null,
      "resolved_by_superadmin_id": null,
      "created_at": "2026-06-23T15:05:12.000000Z"
    }
  ],
  "meta": { "current_page": 1, "last_page": 1, "total": 1 }
}
```

### 10.6 Resolver una alerta

```
POST /api/v2/superadmin/alerts/{alert}/resolve
```

Sin body. Marca la alerta como resuelta por el superadmin autenticado.

**Respuesta (200):**
```json
{
  "message": "Alerta marcada como resuelta.",
  "data": { ...la alerta actualizada... }
}
```

**Ya estaba resuelta (422):** `{ "message": "La alerta ya estaba resuelta." }`

---

## 11. Dashboard Superadmin

```
GET /api/v2/superadmin/dashboard
```

**Respuesta (200):**
```json
{
  "total": 42,
  "active": 35,
  "suspended": 4,
  "pending": 2,
  "cancelled": 1,
  "last_onboarding": {
    "id": 42,
    "name": "Nueva Empresa S.L.",
    "subdomain": "nuevaempresa",
    "onboarding_step": 8,
    "created_at": "2026-06-23T14:00:00.000000Z"
  }
}
```

---

## 12. Gestión de Usuarios Superadmin

Permite crear, listar, editar y eliminar cuentas con acceso al panel superadmin. El nuevo admin recibirá el email de acceso con magic link y OTP.

### 12.1 Listar admins

```
GET /api/v2/superadmin/admins?per_page=20
```

**Respuesta (200):**
```json
{
  "data": [
    {
      "id": 1,
      "name": "Jose",
      "email": "jose@congeladosbrisamar.es",
      "email_verified_at": null,
      "last_login_at": "2026-06-23T10:30:00.000000Z",
      "created_at": "2026-01-01T00:00:00.000000Z",
      "updated_at": "2026-06-23T10:30:00.000000Z"
    }
  ],
  "links": { ... },
  "meta": { "current_page": 1, "last_page": 1, "per_page": 20, "total": 1 }
}
```

### 12.2 Ver admin

```
GET /api/v2/superadmin/admins/{admin}
```

**Respuesta (200):** Mismo schema que el objeto del listado.

### 12.3 Crear admin

```
POST /api/v2/superadmin/admins
Content-Type: application/json
```

**Body:**
```json
{
  "name": "Nuevo Admin",
  "email": "nuevo@empresa.es",
  "send_access_email": true
}
```

| Campo | Obligatorio | Notas |
|---|---|---|
| `name` | ✅ | string, max 255 |
| `email` | ✅ | email válido, único en `superadmin_users` |
| `send_access_email` | ❌ | boolean, default `true` — envía magic link + OTP |

**Respuesta éxito (201):**
```json
{
  "data": { ...SuperadminUserResource... },
  "message": "Usuario superadmin creado correctamente."
}
```

**Respuesta si el email falla pero el usuario sí se creó (201):**
```json
{
  "data": { ... },
  "warning": "Usuario creado pero no se pudo enviar el correo de acceso."
}
```

### 12.4 Actualizar admin

```
PUT /api/v2/superadmin/admins/{admin}
Content-Type: application/json
```

```json
{
  "name": "Nombre actualizado",
  "email": "nuevo-email@empresa.es"
}
```

**Respuesta (200):** SuperadminUserResource actualizado.

### 12.5 Eliminar admin

```
DELETE /api/v2/superadmin/admins/{admin}
```

Revoca automáticamente todos los tokens activos del admin eliminado.

**Respuesta (200):** `{ "message": "Usuario superadmin eliminado correctamente." }`

**Auto-eliminación (422):** `{ "message": "No puedes eliminar tu propia cuenta de superadmin." }`

---

## 13. Endpoint Público (sin auth)

```
GET /api/v2/public/tenant/{subdomain}
```

Sin autenticación. Sin header `X-Tenant`. Usado por el frontend del tenant para resolver nombre y logo antes del login.

**Respuesta (200):**
```json
{
  "data": {
    "name": "Congelados Brisamar",
    "status": "active",
    "branding_image_url": "https://cdn.lapesquerapp.es/brisamar-logo.png"
  }
}
```

**Tenant no encontrado (404):** `{ "message": "Tenant not found." }`

> Si `status` es `suspended`, el frontend del tenant debe mostrar un mensaje adecuado antes de intentar el login.

---

## 14. Referencia rápida de endpoints

### Autenticación

| Método | Endpoint | Auth | Descripción |
|---|---|---|---|
| POST | `/superadmin/auth/request-access` | No | Solicitar acceso (envía email) |
| POST | `/superadmin/auth/verify-magic-link` | No | Verificar magic link |
| POST | `/superadmin/auth/verify-otp` | No | Verificar código OTP |
| GET | `/superadmin/auth/me` | ✅ | Ver sesión activa |
| POST | `/superadmin/auth/logout` | ✅ | Cerrar sesión |

### Tenants

| Método | Endpoint | Descripción |
|---|---|---|
| GET | `/superadmin/tenants` | Listar (filtros: status, search, per_page) |
| POST | `/superadmin/tenants` | Crear tenant + lanzar onboarding |
| GET | `/superadmin/tenants/{id}` | Ver detalle |
| PUT | `/superadmin/tenants/{id}` | Actualizar (name, plan, renewal_at, timezone, branding_image_url, admin_email) |
| DELETE | `/superadmin/tenants/{id}?confirm_delete=true` | Eliminar (solo cancelled o pending) |
| POST | `/superadmin/tenants/{id}/activate` | Activar |
| POST | `/superadmin/tenants/{id}/suspend` | Suspender |
| POST | `/superadmin/tenants/{id}/cancel` | Cancelar |
| GET | `/superadmin/tenants/{id}/onboarding-status` | Estado del onboarding (para polling) |
| POST | `/superadmin/tenants/{id}/retry-onboarding` | Reintentar onboarding fallido |
| GET | `/superadmin/tenants/{id}/users` | Usuarios del tenant |

### Seguridad

| Método | Endpoint | Descripción |
|---|---|---|
| GET | `/superadmin/tenants/{id}/tokens` | Tokens Sanctum activos |
| DELETE | `/superadmin/tenants/{id}/tokens/{tokenId}` | Revocar un token |
| DELETE | `/superadmin/tenants/{id}/tokens` | Revocar todos los tokens |
| GET | `/superadmin/tenants/{id}/blocks` | Listar bloqueos (activos + expirados) |
| POST | `/superadmin/tenants/{id}/block` | Crear bloqueo IP o email |
| DELETE | `/superadmin/tenants/{id}/blocks/{blockId}` | Eliminar bloqueo |

### Migraciones

| Método | Endpoint | Descripción |
|---|---|---|
| GET | `/superadmin/tenants/{id}/migrations` | Estado (ejecutadas vs pendientes) |
| POST | `/superadmin/tenants/{id}/migrations/run` | Ejecutar en un tenant |
| GET | `/superadmin/tenants/{id}/migrations/history` | Historial de ejecuciones |
| POST | `/superadmin/migrations/run-all` | Ejecutar en todos los tenants activos |

### Impersonación

| Método | Endpoint | Descripción |
|---|---|---|
| POST | `/superadmin/tenants/{id}/impersonate/request` | Solicitar con consentimiento |
| POST | `/superadmin/tenants/{id}/impersonate/silent` | Impersonación silenciosa |
| POST | `/superadmin/tenants/{id}/impersonate/token` | Generar token tras aprobación |
| POST | `/superadmin/impersonate/end` | Finalizar sesión |
| POST | `/superadmin/impersonation/logs/{log}/end` | Finalizar desde el panel |
| GET | `/superadmin/impersonation/logs` | Historial |
| GET | `/superadmin/impersonation/active` | Sesiones activas |

### Feature Flags

| Método | Endpoint | Descripción |
|---|---|---|
| GET | `/superadmin/feature-flags` | Defaults por plan |
| GET | `/superadmin/tenants/{id}/feature-flags` | Flags efectivos del tenant |
| PUT | `/superadmin/tenants/{id}/feature-flags/{flag}` | Establecer override |
| DELETE | `/superadmin/tenants/{id}/feature-flags/{flag}` | Eliminar override |

### Observabilidad

| Método | Endpoint | Descripción |
|---|---|---|
| GET | `/superadmin/dashboard` | Stats globales |
| GET | `/superadmin/dashboard/activity` | Feed de actividad reciente |
| GET | `/superadmin/system/queue-health` | Estado de la cola |
| GET | `/superadmin/tenants/{id}/error-logs` | Logs de error del tenant |
| GET | `/superadmin/error-logs` | Logs de error globales |
| GET | `/superadmin/alerts` | Alertas del sistema |
| POST | `/superadmin/alerts/{id}/resolve` | Resolver una alerta |

### Gestión de admins

| Método | Endpoint | Descripción |
|---|---|---|
| GET | `/superadmin/admins` | Listar usuarios superadmin |
| POST | `/superadmin/admins` | Crear admin (+ email de acceso opcional) |
| GET | `/superadmin/admins/{id}` | Ver admin |
| PUT | `/superadmin/admins/{id}` | Actualizar nombre o email |
| DELETE | `/superadmin/admins/{id}` | Eliminar admin |

### Público (sin auth)

| Método | Endpoint | Descripción |
|---|---|---|
| GET | `/public/tenant/{subdomain}` | Resolver tenant por subdominio |
| GET | `/public/impersonation/{token}/approve` | Aprobar solicitud de impersonación |
| GET | `/public/impersonation/{token}/reject` | Rechazar solicitud de impersonación |

---

## 15. Checklist para el Frontend

Lista de verificación para confirmar que la web app cubre correctamente todas las capacidades del backend.

### Autenticación

- [ ] Formulario de solicitud de acceso con campo email → `POST /superadmin/auth/request-access`
- [ ] Pantalla de verificación con campo OTP de 6 dígitos → `POST /superadmin/auth/verify-otp`
- [ ] Soporte de magic link en URL (clic en el enlace del email) → `POST /superadmin/auth/verify-magic-link`
- [ ] Token Bearer del superadmin almacenado separado del token de sesión tenant
- [ ] Botón de logout → `POST /superadmin/auth/logout`
- [ ] Llamada a `GET /superadmin/auth/me` al entrar al panel para verificar sesión activa

### Dashboard

- [ ] Conteos: total, activos, suspendidos, pendientes, cancelados → `GET /superadmin/dashboard`
- [ ] Feed de actividad reciente → `GET /superadmin/dashboard/activity`
- [ ] Alertas no resueltas destacadas → `GET /superadmin/alerts?resolved=false`
- [ ] Indicador de salud de la cola → `GET /superadmin/system/queue-health`

### Listado de tenants

- [ ] Tabla paginada → `GET /superadmin/tenants`
- [ ] Filtro por estado
- [ ] Búsqueda por nombre o subdominio
- [ ] Badge visual del estado del onboarding (`pending`, `in_progress`, `failed`, `completed`)
- [ ] Indicador de color por estado del tenant

### Crear tenant

- [ ] Formulario con campos: nombre, subdominio, email admin, plan, timezone, logo URL
- [ ] Validación de subdominio: solo minúsculas, números y guiones
- [ ] Tras crear, muestra estado `pending` y arranca polling en `GET /superadmin/tenants/{id}/onboarding-status`
- [ ] Barra de progreso con paso actual (step X de 8) y label del paso
- [ ] Si `status === "failed"`: mostrar `error` y botón "Reintentar" → `POST /superadmin/tenants/{id}/retry-onboarding`

### Detalle / editar tenant

- [ ] Formulario de edición: nombre, plan, fecha renovación, timezone, logo, email admin → `PUT /superadmin/tenants/{id}`
- [ ] `subdomain` y `database` solo lectura (no editables)
- [ ] Sección de estado de onboarding con step actual
- [ ] Botones de estado condicionados correctamente:
  - [ ] `pending` → solo "Cancelar" disponible (no "Activar", no "Suspender")
  - [ ] `active` → "Suspender" y "Cancelar"
  - [ ] `suspended` → "Activar" y "Cancelar"
  - [ ] `cancelled` → "Activar" (solo si `onboarding.status === "completed"`)
- [ ] Confirmación modal antes de cambiar estado
- [ ] Modal de eliminación con checkbox para `drop_database` + `?confirm_delete=true` en la URL de la petición
- [ ] Botón "Eliminar" visible solo para tenants `cancelled` o `pending`

### Usuarios del tenant

- [ ] Lista de usuarios → `GET /superadmin/tenants/{id}/users`
- [ ] Mensaje informativo si el onboarding no llegó al paso 5 (BD no disponible)

### Seguridad

- [ ] Lista de tokens activos → `GET /superadmin/tenants/{id}/tokens`
- [ ] Revocar token individual → `DELETE /superadmin/tenants/{id}/tokens/{tokenId}`
- [ ] Revocar todos → `DELETE /superadmin/tenants/{id}/tokens`
- [ ] Lista de bloqueos filtrando activos (`expires_at === null || expires_at > now`) → `GET /superadmin/tenants/{id}/blocks`
- [ ] Formulario de bloqueo: tipo (ip/email), valor, razón, expiración → `POST /superadmin/tenants/{id}/block`
- [ ] Desbloquear → `DELETE /superadmin/tenants/{id}/blocks/{blockId}`

### Migraciones

- [ ] Estado (ejecutadas vs pendientes) → `GET /superadmin/tenants/{id}/migrations`
- [ ] Botón "Ejecutar migraciones" → `POST /superadmin/tenants/{id}/migrations/run`
- [ ] Historial paginado → `GET /superadmin/tenants/{id}/migrations/history`
- [ ] Botón global "Migrar todos" → `POST /superadmin/migrations/run-all`

### Impersonación

- [ ] Botón "Acceder como usuario" (silencioso) con campo de razón obligatorio → `POST /superadmin/tenants/{id}/impersonate/silent`
- [ ] Abrir el tenant en nueva pestaña usando el `access_token` recibido
- [ ] Finalizar sesión desde el panel → `POST /superadmin/impersonation/logs/{log}/end`
- [ ] Historial de impersonaciones con filtros → `GET /superadmin/impersonation/logs`
- [ ] Sesiones activas con posibilidad de cerrarlas → `GET /superadmin/impersonation/active`

### Feature flags

- [ ] Tabla de flags por plan → `GET /superadmin/feature-flags`
- [ ] Flags del tenant con indicador de override → `GET /superadmin/tenants/{id}/feature-flags`
- [ ] Toggle con campo de razón → `PUT /superadmin/tenants/{id}/feature-flags/{flag}`
- [ ] Botón "Restaurar a valor del plan" → `DELETE /superadmin/tenants/{id}/feature-flags/{flag}`

### Observabilidad

- [ ] Logs de error del tenant → `GET /superadmin/tenants/{id}/error-logs`
- [ ] Logs globales → `GET /superadmin/error-logs`
- [ ] Alertas con filtros de severidad y estado → `GET /superadmin/alerts`
- [ ] Botón "Resolver" por alerta → `POST /superadmin/alerts/{id}/resolve`

### Gestión de usuarios superadmin

- [ ] Lista de admins → `GET /superadmin/admins`
- [ ] Crear admin con opción de enviar email de acceso → `POST /superadmin/admins`
- [ ] Editar nombre o email → `PUT /superadmin/admins/{id}`
- [ ] Eliminar admin con botón deshabilitado para la propia cuenta → `DELETE /superadmin/admins/{id}`

---

*Revisión: 2026-06-23. Mantener sincronizado con `routes/api.php` y `app/Http/Controllers/v2/Superadmin/`.*
