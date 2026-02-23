# Especificación Frontend — Superadmin Panel v2

**Versión**: 2.0  
**Fecha**: 2026-02-23  
**Estado**: Backend implementado y listo. Frontend pendiente.  
**Audiencia**: Equipo de desarrollo frontend.  
**Documento anterior**: `02_Spec_frontend_superadmin.md` (v1 — leer primero para el contexto base).

---

## Resumen de novedades respecto a v1

Este documento cubre las **nuevas funcionalidades del Panel Superadmin v2** implementadas en el backend:

| Funcionalidad | Rutas nuevas | Sección |
|---|---|---|
| Impersonación ampliada | historial, sesiones activas, fin desde panel, `reason` obligatorio | §2 |
| Tokens activos por tenant | listar, revocar uno, revocar todos | §3 |
| Panel de migraciones | estado, ejecutar, historial | §4 |
| Login attempts (por tenant) | (solo backend — dato expuesto en §5 observabilidad) | — |
| Sistema de alertas | listar, resolver | §5 |
| Blocklist IP/email | bloquear, listar, desbloquear | §6 |
| Errores 500 centralizados | error logs por tenant y globales | §7 |
| Activity feed | feed de actividad reciente | §8 |
| Queue health | estado de la cola Redis | §9 |
| Feature flags | defaults por plan, overrides por tenant | §10 |
| `features` en `/me` tenant | array de flags activos (tenant app) | §11 |

**Convenciones de este documento:**
- `BASE_SUPERADMIN` = `https://api.lapesquerapp.es/api/v2/superadmin`
- `BASE_TENANT` = `https://api.lapesquerapp.es/api/v2`
- Todas las rutas de superadmin requieren `Authorization: Bearer {token}` y **NO** `X-Tenant`.
- Todas las rutas de tenant requieren `Authorization: Bearer {token}` y `X-Tenant: {subdomain}`.

---

## 1. Cambios en endpoints existentes

### 1.1 Impersonación silenciosa — `reason` ahora obligatorio

`POST BASE_SUPERADMIN/tenants/{tenant}/impersonate/silent`

```json
// Request — AHORA reason es REQUIRED
{
    "target_user_id": 3,
    "reason": "Soporte técnico urgente solicitado por el cliente el 2026-02-23"
}

// Response 200
{
    "impersonation_token": "5|xxxxxxxxxx",
    "redirect_url": "https://dev.lapesquerapp.es/auth/impersonate?token=5|xxxxxxxxxx",
    "log_id": 42
}

// Response 422 si reason falta
{
    "message": "Error de validación.",
    "errors": { "reason": ["El campo reason es obligatorio."] }
}
```

**UX**: El diálogo de confirmación de "Acceso directo" debe incluir un campo de texto obligatorio "Motivo del acceso" antes de habilitar el botón "Confirmar".

---

### 1.2 Usuarios del tenant — campo `last_login_at` eliminado

`GET BASE_SUPERADMIN/tenants/{tenant}/users`

```json
// Response 200 — schema actualizado
{
    "data": [
        {
            "id": 1,
            "name": "Admin Brisamar",
            "email": "admin@brisamar.es",
            "role": "administrador",
            "active": true,
            "created_at": "2025-06-15T10:00:00.000000Z"
        }
    ]
}
```

> `last_login_at` no existe en la tabla `users` de los tenants. Columna eliminada del schema.

---

### 1.3 Respuesta `TenantResource` — bloque `onboarding` enriquecido

Todos los endpoints que devuelven un tenant (GET, PUT, activate, suspend, cancel) incluyen ahora el objeto `onboarding`:

```json
{
    "data": {
        "id": 1,
        "name": "Congelados Brisamar S.L.",
        "subdomain": "brisamar",
        "database": "tenant_brisamar",
        "status": "active",
        "plan": "pro",
        "renewal_at": "2027-02-23",
        "timezone": "Europe/Madrid",
        "branding_image_url": "https://...",
        "last_activity_at": "2026-02-23T12:30:00.000000Z",
        "admin_email": "admin@brisamar.es",
        "created_at": "2025-06-15T10:00:00.000000Z",
        "updated_at": "2026-02-23T12:30:00.000000Z",
        "onboarding": {
            "step": 8,
            "total_steps": 8,
            "step_label": "Email de bienvenida",
            "status": "completed",
            "error": null,
            "failed_at": null
        }
    }
}
```

**Valores de `onboarding.status`:**

| Valor | Significado | Color sugerido |
|---|---|---|
| `completed` | Todos los pasos OK | Verde |
| `failed` | Falló en algún paso | Rojo |
| `in_progress` | En ejecución | Azul (pulsante) |
| `pending` | Aún no iniciado | Gris |

---

## 2. Impersonación ampliada

### 2.1 Historial de impersonaciones

`GET BASE_SUPERADMIN/impersonation/logs`

**Query params:**

| Param | Tipo | Descripción |
|---|---|---|
| `tenant_id` | int | Filtrar por tenant |
| `superadmin_user_id` | int | Filtrar por superadmin |
| `from` | datetime ISO | Filtrar desde fecha |
| `per_page` | int | Default 20 |
| `page` | int | Página |

```json
// Response 200
{
    "data": [
        {
            "id": 42,
            "superadmin": "Jose García",
            "tenant": "brisamar",
            "tenant_id": 1,
            "target_user_id": 3,
            "mode": "silent",
            "reason": "Soporte técnico urgente solicitado por el cliente",
            "started_at": "2026-02-23T14:30:00.000000Z",
            "ended_at": "2026-02-23T14:45:00.000000Z",
            "duration_minutes": 15
        },
        {
            "id": 41,
            "superadmin": "Jose García",
            "tenant": "costasur",
            "tenant_id": 2,
            "target_user_id": 1,
            "mode": "consent",
            "reason": null,
            "started_at": "2026-02-22T10:00:00.000000Z",
            "ended_at": null,
            "duration_minutes": null
        }
    ],
    "meta": {
        "current_page": 1,
        "last_page": 3,
        "total": 48
    }
}
```

**Pantalla sugerida**: tabla con columnas Fecha, Superadmin, Tenant, Usuario (ID), Modo (badge), Motivo, Inicio, Fin, Duración. Filtros por tenant y rango de fechas.

---

### 2.2 Sesiones activas

`GET BASE_SUPERADMIN/impersonation/active`

```json
// Response 200
{
    "data": [
        {
            "id": 44,
            "superadmin": "Jose García",
            "tenant": "brisamar",
            "tenant_id": 1,
            "target_user_id": 3,
            "mode": "silent",
            "reason": "Debug en producción urgente",
            "started_at": "2026-02-23T15:00:00.000000Z",
            "ended_at": null,
            "duration_minutes": null
        }
    ],
    "total": 1
}
```

**UX**: Mostrar como badge/alert en el header del panel si `total > 0`. Cada sesión activa tiene un botón "Terminar" que llama al endpoint §2.3.

---

### 2.3 Terminar sesión desde el panel

`POST BASE_SUPERADMIN/impersonation/logs/{log_id}/end`

```json
// Request — sin body

// Response 200
{ "message": "Sesión de impersonación finalizada desde el panel." }

// Response 404 si el log no existe
// Response 200 también si ya estaba terminada (idempotente)
```

---

## 3. Tokens activos por tenant

### 3.1 Listar tokens

`GET BASE_SUPERADMIN/tenants/{tenant}/tokens`

```json
// Response 200
{
    "data": [
        {
            "id": 101,
            "tokenable_id": 3,
            "name": "auth_token",
            "abilities": "[\"*\"]",
            "last_used_at": "2026-02-23T14:50:00.000000Z",
            "created_at": "2026-02-20T09:00:00.000000Z",
            "expires_at": null
        },
        {
            "id": 102,
            "tokenable_id": 3,
            "name": "impersonation",
            "abilities": "[\"impersonation\"]",
            "last_used_at": "2026-02-23T14:30:00.000000Z",
            "created_at": "2026-02-23T14:30:00.000000Z",
            "expires_at": null
        }
    ],
    "total": 2
}
```

**UX**: Tabla con columnas ID, Usuario (tokenable_id), Nombre, Último uso, Creado, Acciones (revocar). Detectar tokens con `name === 'impersonation'` para marcarlos con badge especial.

---

### 3.2 Revocar un token

`DELETE BASE_SUPERADMIN/tenants/{tenant}/tokens/{tokenId}`

```json
// Response 200
{ "message": "Token revocado correctamente." }

// Response 404 si no existe
{ "message": "Token no encontrado." }
```

---

### 3.3 Revocar todos los tokens

`DELETE BASE_SUPERADMIN/tenants/{tenant}/tokens`

```json
// Response 200
{
    "message": "Todos los tokens han sido revocados.",
    "tokens_revoked": 5
}
```

**UX**: Botón con confirmación "¿Revocar TODOS los tokens? Todos los usuarios del tenant perderán su sesión activa."

---

## 4. Panel de migraciones

### 4.1 Estado de migraciones de un tenant

`GET BASE_SUPERADMIN/tenants/{tenant}/migrations`

```json
// Response 200
{
    "data": {
        "migrations": [
            { "migration": "2024_01_01_000001_create_users_table", "ran": true, "batch": 1 },
            { "migration": "2026_02_23_210200_create_login_attempts_table", "ran": true, "batch": 3 },
            { "migration": "2026_03_01_000001_add_new_column", "ran": false, "batch": null }
        ],
        "total": 45,
        "ran": 44,
        "pending": 1,
        "raw_output": "..."
    }
}
```

**UX**: Mostrar resumen (44/45 ejecutadas). Si `pending > 0`, mostrar alerta amarilla y botón "Ejecutar migraciones".

---

### 4.2 Ejecutar migraciones de un tenant

`POST BASE_SUPERADMIN/tenants/{tenant}/migrations/run`

```json
// Response 200 — encola job, no espera resultado
{
    "message": "Migraciones encoladas para el tenant.",
    "run_id": 7,
    "tenant": "brisamar"
}
```

**UX**: Después de encolar, hacer polling al historial (§4.3) cada 3s hasta que el run_id aparezca con `finished_at`.

---

### 4.3 Historial de runs de migraciones

`GET BASE_SUPERADMIN/tenants/{tenant}/migrations/history`

**Query params**: `per_page` (default 15), `page`.

```json
// Response 200
{
    "data": [
        {
            "id": 7,
            "tenant_id": 1,
            "triggered_by_superadmin_id": 1,
            "migrations_applied": 1,
            "output": "\n   INFO  Running migrations.  \n\n  2026_02_23_210200_create_login_attempts_table ... 170ms DONE\n",
            "success": true,
            "started_at": "2026-02-23T15:10:00.000000Z",
            "finished_at": "2026-02-23T15:10:05.000000Z"
        }
    ],
    "meta": { "current_page": 1, "last_page": 1, "total": 7 }
}
```

**Columnas**: Fecha, Migraciones aplicadas, Estado (badge éxito/fallo), Duración, Iniciado por, Expandir output.

---

### 4.4 Ejecutar migraciones en todos los tenants activos

`POST BASE_SUPERADMIN/migrations/run-all`

```json
// Response 200
{
    "message": "Migraciones encoladas para todos los tenants activos.",
    "tenants_queued": 12
}
```

**UX**: Botón en una sección de mantenimiento del panel. Requiere confirmación.

---

## 5. Sistema de alertas

### 5.1 Listar alertas

`GET BASE_SUPERADMIN/alerts`

**Query params:**

| Param | Tipo | Default | Descripción |
|---|---|---|---|
| `severity` | string | — | `info`, `warning`, `critical` |
| `resolved` | string | `"false"` | `"true"` = solo resueltas, `"false"` = solo activas |
| `tenant_id` | int | — | Filtrar por tenant |
| `per_page` | int | 20 | — |

```json
// Response 200
{
    "data": [
        {
            "id": 1,
            "type": "onboarding_failed",
            "severity": "critical",
            "tenant_id": 3,
            "tenant": { "id": 3, "subdomain": "prueba" },
            "message": "El onboarding del tenant [prueba] ha fallado: Access denied...",
            "metadata": { "error": "Access denied...", "onboarding_step": 2 },
            "resolved_at": null,
            "resolved_by_superadmin_id": null,
            "created_at": "2026-02-23T12:00:00.000000Z",
            "updated_at": "2026-02-23T12:00:00.000000Z"
        },
        {
            "id": 2,
            "type": "suspicious_activity",
            "severity": "warning",
            "tenant_id": 1,
            "tenant": { "id": 1, "subdomain": "brisamar" },
            "message": "IP 192.168.1.100 tiene 15 intentos fallidos en la última hora en tenant brisamar.",
            "metadata": { "ip": "192.168.1.100", "attempts": 15 },
            "resolved_at": null,
            "resolved_by_superadmin_id": null,
            "created_at": "2026-02-23T11:00:00.000000Z",
            "updated_at": "2026-02-23T11:00:00.000000Z"
        }
    ],
    "meta": { "current_page": 1, "last_page": 1, "total": 2 }
}
```

**Tipos de alerta y su significado:**

| type | severity | Descripción |
|---|---|---|
| `onboarding_failed` | critical | El onboarding de un tenant falló |
| `onboarding_stuck` | warning | Onboarding sin completar > 30 min |
| `migrations_pending` | warning | Migraciones pendientes en tenant |
| `suspicious_activity` | warning | Demasiados intentos fallidos de login |
| `impersonation_started` | info | Sesión de impersonación iniciada |
| `queue_stopped` | critical | La cola de trabajos no responde |

**UX**: Mostrar contador de alertas críticas no resueltas en el sidebar. Badge rojo si `critical > 0`, naranja si solo `warning`.

---

### 5.2 Resolver una alerta

`POST BASE_SUPERADMIN/alerts/{alert_id}/resolve`

```json
// Response 200
{
    "message": "Alerta marcada como resuelta.",
    "data": {
        "id": 1,
        "resolved_at": "2026-02-23T16:00:00.000000Z",
        "resolved_by_superadmin_id": 1
    }
}

// Response 422 si ya estaba resuelta
{ "message": "La alerta ya estaba resuelta." }
```

---

## 6. Blocklist IP/email

### 6.1 Listar bloqueos de un tenant

`GET BASE_SUPERADMIN/tenants/{tenant}/blocks`

```json
// Response 200
{
    "data": [
        {
            "id": 1,
            "tenant_id": 1,
            "type": "ip",
            "value": "192.168.1.100",
            "blocked_by": { "id": 1, "name": "Jose García" },
            "reason": "Brute force detectado",
            "expires_at": "2026-02-24T12:00:00.000000Z",
            "created_at": "2026-02-23T12:00:00.000000Z",
            "updated_at": "2026-02-23T12:00:00.000000Z"
        },
        {
            "id": 2,
            "tenant_id": 1,
            "type": "email",
            "value": "spammer@example.com",
            "blocked_by": { "id": 1, "name": "Jose García" },
            "reason": "Spam",
            "expires_at": null,
            "created_at": "2026-02-23T11:00:00.000000Z",
            "updated_at": "2026-02-23T11:00:00.000000Z"
        }
    ]
}
```

---

### 6.2 Crear un bloqueo

`POST BASE_SUPERADMIN/tenants/{tenant}/block`

```json
// Request
{
    "type": "ip",
    "value": "203.0.113.45",
    "reason": "Múltiples intentos de acceso no autorizado",
    "expires_at": "2026-03-23T00:00:00Z"
}

// Request email block
{
    "type": "email",
    "value": "atacante@malicious.com",
    "reason": "Credenciales robadas reportadas",
    "expires_at": null
}

// Response 201
{
    "message": "Bloqueo creado.",
    "data": { /* TenantBlocklist */ }
}
```

**Campos:**

| Campo | Tipo | Requerido | Descripción |
|---|---|---|---|
| `type` | string | Sí | `"ip"` o `"email"` |
| `value` | string | Sí | IP o dirección de email |
| `reason` | string | No | Motivo del bloqueo |
| `expires_at` | datetime ISO | No | null = bloqueo indefinido |

---

### 6.3 Eliminar un bloqueo

`DELETE BASE_SUPERADMIN/tenants/{tenant}/blocks/{block_id}`

```json
// Response 200
{ "message": "Bloqueo eliminado." }
```

---

## 7. Error logs

### 7.1 Errores de un tenant específico

`GET BASE_SUPERADMIN/tenants/{tenant}/error-logs`

**Query params:** `per_page` (default 20), `days` (default 30), `page`.

```json
// Response 200
{
    "data": [
        {
            "id": 55,
            "tenant_id": 1,
            "user_id": 3,
            "method": "POST",
            "url": "https://brisamar.lapesquerapp.es/api/v2/productions",
            "error_class": "Illuminate\\Database\\QueryException",
            "error_message": "SQLSTATE[23000]: Integrity constraint violation...",
            "occurred_at": "2026-02-23T10:15:00.000000Z"
        }
    ],
    "meta": { "current_page": 1, "last_page": 2, "total": 23 }
}
```

---

### 7.2 Errores globales (todos los tenants)

`GET BASE_SUPERADMIN/error-logs`

**Query params:** `per_page` (default 50), `days` (default 30), `page`.

```json
// Response 200 — mismo schema, incluye relación tenant
{
    "data": [
        {
            "id": 55,
            "tenant_id": 1,
            "tenant": { "id": 1, "subdomain": "brisamar", "name": "Congelados Brisamar S.L." },
            "user_id": 3,
            "method": "POST",
            "url": "...",
            "error_class": "Illuminate\\Database\\QueryException",
            "error_message": "...",
            "occurred_at": "2026-02-23T10:15:00.000000Z"
        }
    ],
    "meta": { "current_page": 1, "last_page": 3, "total": 150 }
}
```

**UX**: Tabla con columnas Fecha, Tenant, Método, URL, Clase de error, Mensaje (truncado, expandible). Filtro por rango de días.

---

## 8. Activity feed

`GET BASE_SUPERADMIN/dashboard/activity`

**Query params:** `limit` (default 50, máx 50).

```json
// Response 200
{
    "data": [
        {
            "type": "impersonation",
            "icon": "user-switch",
            "severity": "info",
            "message": "Impersonación silent de Jose García en brisamar — Soporte técnico urgente",
            "tenant": "brisamar",
            "tenant_id": 1,
            "at": "2026-02-23T15:00:00.000000Z"
        },
        {
            "type": "migration",
            "icon": "database",
            "severity": "info",
            "message": "Migración tenant brisamar: 2 aplicadas",
            "tenant": "brisamar",
            "tenant_id": 1,
            "at": "2026-02-23T14:00:00.000000Z"
        },
        {
            "type": "alert",
            "icon": "alert-triangle",
            "severity": "critical",
            "message": "El onboarding del tenant [prueba] ha fallado: Access denied...",
            "tenant": "prueba",
            "tenant_id": 3,
            "at": "2026-02-23T12:00:00.000000Z",
            "resolved": false
        },
        {
            "type": "tenant_status",
            "icon": "building",
            "severity": "info",
            "message": "Tenant brisamar — estado: active",
            "tenant": "brisamar",
            "tenant_id": 1,
            "at": "2026-02-20T09:00:00.000000Z"
        }
    ]
}
```

**Tipos posibles:** `impersonation`, `migration`, `alert`, `tenant_status`.

**UX**: Feed cronológico en el Dashboard. Cada item muestra icono según `type`, color según `severity`, mensaje y enlace al tenant. Cargar en el sidebar derecho del dashboard.

---

## 9. Queue health

`GET BASE_SUPERADMIN/system/queue-health`

```json
// Response 200
{
    "data": {
        "pending_jobs": 3,
        "failed_jobs": 1,
        "redis_status": "ok",
        "healthy": true
    }
}

// Cuando Redis no responde
{
    "data": {
        "pending_jobs": 0,
        "failed_jobs": 0,
        "redis_status": "error: Connection refused",
        "healthy": false
    }
}
```

**UX**: Indicador de estado en el footer o sidebar del panel:
- `healthy: true` + `pending_jobs < 10` + `failed_jobs == 0` → punto verde
- `failed_jobs > 0` → badge naranja con número
- `healthy: false` → punto rojo + alerta visible

Hacer polling cada 60 segundos.

---

## 10. Feature flags

### 10.1 Defaults por plan

`GET BASE_SUPERADMIN/feature-flags`

```json
// Response 200
{
    "data": [
        {
            "flag_key": "feature.api_access",
            "description": "Acceso a la API REST para integraciones externas",
            "basic": false,
            "pro": false,
            "enterprise": true
        },
        {
            "flag_key": "feature.import_a3erp",
            "description": "Importación desde A3ERP",
            "basic": false,
            "pro": false,
            "enterprise": true
        },
        {
            "flag_key": "feature.import_facilcom",
            "description": "Importación desde Facilcom",
            "basic": false,
            "pro": false,
            "enterprise": true
        },
        {
            "flag_key": "module.cebo_dispatch",
            "description": "Módulo de despachos de cebo",
            "basic": false,
            "pro": true,
            "enterprise": true
        },
        {
            "flag_key": "module.inventory",
            "description": "Módulo de inventario (almacenes, palets, cajas)",
            "basic": true,
            "pro": true,
            "enterprise": true
        },
        {
            "flag_key": "module.labels",
            "description": "Módulo de etiquetas GS1-128",
            "basic": false,
            "pro": true,
            "enterprise": true
        },
        {
            "flag_key": "module.production",
            "description": "Módulo de producción (fileteado, congelado, enlatado)",
            "basic": false,
            "pro": true,
            "enterprise": true
        },
        {
            "flag_key": "module.punch_events",
            "description": "Módulo de fichajes de empleados",
            "basic": false,
            "pro": true,
            "enterprise": true
        },
        {
            "flag_key": "module.raw_material",
            "description": "Módulo de recepciones de materia prima",
            "basic": true,
            "pro": true,
            "enterprise": true
        },
        {
            "flag_key": "module.sales",
            "description": "Módulo de ventas y pedidos",
            "basic": true,
            "pro": true,
            "enterprise": true
        },
        {
            "flag_key": "module.statistics",
            "description": "Panel de estadísticas avanzadas y reportes",
            "basic": false,
            "pro": false,
            "enterprise": true
        },
        {
            "flag_key": "module.supplier_liquidations",
            "description": "Módulo de liquidaciones a proveedores",
            "basic": false,
            "pro": false,
            "enterprise": true
        }
    ]
}
```

**UX**: Tabla de matriz plan × flag. Útil para mostrar una "tabla de comparación de planes" al crear un tenant.

---

### 10.2 Flags efectivos de un tenant

`GET BASE_SUPERADMIN/tenants/{tenant}/feature-flags`

```json
// Response 200
{
    "tenant": "brisamar",
    "plan": "pro",
    "data": [
        { "flag_key": "module.sales", "enabled": true, "has_override": false },
        { "flag_key": "module.inventory", "enabled": true, "has_override": false },
        { "flag_key": "module.raw_material", "enabled": true, "has_override": false },
        { "flag_key": "module.production", "enabled": true, "has_override": false },
        { "flag_key": "module.cebo_dispatch", "enabled": true, "has_override": false },
        { "flag_key": "module.labels", "enabled": true, "has_override": false },
        { "flag_key": "module.punch_events", "enabled": true, "has_override": false },
        { "flag_key": "module.statistics", "enabled": false, "has_override": false },
        { "flag_key": "module.supplier_liquidations", "enabled": false, "has_override": false },
        { "flag_key": "feature.import_facilcom", "enabled": false, "has_override": false },
        { "flag_key": "feature.import_a3erp", "enabled": false, "has_override": false },
        { "flag_key": "feature.api_access", "enabled": true, "has_override": true }
    ]
}
```

**`has_override: true`**: el tenant tiene un override personalizado que difiere del default del plan. Mostrar con badge/indicador visual.

---

### 10.3 Establecer un override

`PUT BASE_SUPERADMIN/tenants/{tenant}/feature-flags/{flag_key}`

```json
// Request — habilitar módulo de estadísticas para un tenant en plan pro
{
    "enabled": true,
    "reason": "Acceso especial contratado verbalmente por 3 meses"
}

// Response 200
{
    "message": "Override guardado.",
    "data": {
        "id": 5,
        "tenant_id": 1,
        "flag_key": "module.statistics",
        "enabled": true,
        "overridden_by_superadmin_id": 1,
        "reason": "Acceso especial contratado verbalmente por 3 meses",
        "created_at": "2026-02-23T16:00:00.000000Z",
        "updated_at": "2026-02-23T16:00:00.000000Z"
    }
}
```

**Campos:**

| Campo | Tipo | Requerido |
|---|---|---|
| `enabled` | boolean | Sí |
| `reason` | string | No (recomendado) |

---

### 10.4 Eliminar un override

`DELETE BASE_SUPERADMIN/tenants/{tenant}/feature-flags/{flag_key}`

```json
// Response 200
{ "message": "Override eliminado. El tenant vuelve al valor del plan." }

// Response 404 si no había override
{ "message": "No existía override para este flag." }
```

---

## 11. Features en GET /me (tenant app — no superadmin)

Este endpoint pertenece a la **aplicación de tenant** (no al panel superadmin).

`GET BASE_TENANT/me` — requiere `X-Tenant` + `Authorization`

```json
// Response 200 — ahora incluye array "features"
{
    "id": 3,
    "name": "Ana García",
    "email": "ana@brisamar.es",
    "assigned_store_id": null,
    "company_name": "Congelados Brisamar S.L.",
    "company_logo_url": "https://...",
    "active": true,
    "role": "administrador",
    "created_at": "2025-06-15T10:00:00.000000Z",
    "updated_at": "2026-02-01T08:00:00.000000Z",
    "features": [
        "module.sales",
        "module.inventory",
        "module.raw_material",
        "module.production",
        "module.cebo_dispatch",
        "module.labels",
        "module.punch_events"
    ]
}
```

**Uso en el frontend de tenants:**
- Al cargar la app (después de `/me`), guardar el array `features` en el estado global.
- Para mostrar/ocultar elementos de navegación:

```javascript
// Ejemplo — mostrar módulo de producción solo si está habilitado
const showProduction = features.includes('module.production');

// Ejemplo — redirigir si el módulo no está disponible
if (!features.includes('module.statistics')) {
    router.replace('/403');
}
```

**Lista completa de flag keys disponibles:**

| Flag key | Módulo |
|---|---|
| `module.sales` | Ventas y pedidos |
| `module.inventory` | Inventario |
| `module.raw_material` | Recepciones de materia prima |
| `module.production` | Producción |
| `module.cebo_dispatch` | Despachos de cebo |
| `module.labels` | Etiquetas GS1-128 |
| `module.punch_events` | Fichajes de empleados |
| `module.statistics` | Estadísticas avanzadas |
| `module.supplier_liquidations` | Liquidaciones a proveedores |
| `feature.import_facilcom` | Importación Facilcom |
| `feature.import_a3erp` | Importación A3ERP |
| `feature.api_access` | Acceso API externo |

---

## 12. Pantallas nuevas — resumen y navegación sugerida

### Sidebar actualizado

```
┌──────────────────────┐
│  PesquerApp Admin    │
├──────────────────────┤
│  🏠 Dashboard        │  ← activity feed + alertas + queue
│  🏢 Tenants          │  ← lista + detalle (con tabs nuevos)
│  ─────────────────   │
│  🔍 Impersonaciones  │  ← historial + sesiones activas   [NUEVO]
│  🚨 Alertas          │  ← listado + resolver             [NUEVO]
│  ─────────────────   │
│  ⚙️  Sistema         │  ← migraciones globales + cola    [NUEVO]
├──────────────────────┤
│  Jose García         │
│  [Cerrar sesión]     │
└──────────────────────┘
```

### Dashboard actualizado

**Nuevas cards/widgets:**
1. **Alertas activas** — contador por severidad (critical, warning). Click → /alerts.
2. **Queue health** — indicador visual (verde/naranja/rojo).
3. **Activity feed** — últimas 10 acciones en columna lateral derecha.
4. **Sesiones activas** — si `total > 0`, mostrar alerta con botón "Ver".

### Detalle de tenant — nuevas pestañas

```
/tenants/{id}
│
├── 📋 General (datos, estado, onboarding) — existente
├── 👥 Usuarios — existente
├── 🔑 Tokens activos                       [NUEVO]
├── 🗄️  Migraciones                         [NUEVO]
├── ⚡ Feature flags                        [NUEVO]
├── 🔐 Blocklist                            [NUEVO]
└── 📊 Error logs                           [NUEVO]
```

### Nueva pantalla: /impersonation

```
┌─────────────────────────────────────────────────────────┐
│  Impersonaciones                                        │
├─────────────────────────────────────────────────────────┤
│  🔴 Sesiones activas: 1                      [Ver]      │
├─────────────────────────────────────────────────────────┤
│  Historial                                              │
│  Filtros: [Tenant ▾] [Desde ▾]                          │
│                                                         │
│  Fecha     │ Admin   │ Tenant  │ Modo     │ Motivo      │
│  23/02 15h │ Jose    │ brisa.. │ 🔕silent │ Debug prod  │
│  22/02 10h │ Jose    │ costa.. │ ✅consent│ Soporte     │
│                                                         │
│  ← 1 2 3 →                                              │
└─────────────────────────────────────────────────────────┘
```

### Nueva pantalla: /alerts

```
┌─────────────────────────────────────────────────────────┐
│  Alertas del sistema                                    │
│  [🔴 Critical] [🟠 Warning] [ℹ️  Info] [✅ Resuelta]    │
├─────────────────────────────────────────────────────────┤
│  🔴 onboarding_failed  │ prueba   │ Onboarding falló... │ [Resolver]
│  🟠 suspicious_activity│ brisamar │ IP 192... 15 intentos│ [Resolver]
│  ✅ onboarding_stuck   │ costasur │ Resuelto             │ —
└─────────────────────────────────────────────────────────┘
```

---

## 13. Errores y respuestas HTTP — ampliación

Los códigos base del v1 se mantienen. Nuevos casos:

| Código | Endpoint | Situación |
|---|---|---|
| 422 | `/impersonate/silent` | Falta `reason` |
| 422 | `/activate`, `/suspend`, `/cancel` | Transición de estado inválida (onboarding incompleto, etc.) |
| 404 | `/tokens/{id}` | Token no encontrado en tenant |
| 404 | `/feature-flags/{flag}` DELETE | No había override |
| 200 | `/impersonation/logs/{id}/end` | Idempotente aunque ya estuviera terminada |

**Respuesta 422 en transición de estado inválida:**

```json
{
    "message": "No se puede activar: el tenant no ha completado el onboarding (paso 2/8).",
    "onboarding": {
        "step": 2,
        "total_steps": 8,
        "step_label": "Base de datos creada",
        "status": "failed",
        "error": "Paso 2 (Crear base de datos): SQLSTATE[42000]: Access denied...",
        "failed_at": "2026-02-23T12:00:00.000000Z"
    }
}
```

---

## 14. Caché y refresco de datos

| Dato | TTL backend | Estrategia frontend |
|---|---|---|
| Blocklist por tenant | 5 min | Sin cache especial en front |
| Feature flags por tenant | 5 min | Guardar en estado local, refrescar al volver a la pestaña |
| Queue health | — (lectura directa) | Polling cada 60s |
| Sesiones impersonación activas | — | Polling cada 30s en dashboard |
| Alertas | — | Polling cada 60s para contador de sidebar |

---

## 15. Checklist de implementación (v2)

### Funcionalidades prioritarias (bloquean soporte)
- [ ] Historial de impersonaciones con filtros (`/impersonation/logs`)
- [ ] Ver/terminar sesiones activas de impersonación
- [ ] Campo "Motivo" obligatorio en impersonación silenciosa
- [ ] Tokens activos por tenant (listar + revocar)
- [ ] Alertas del sistema (listar + resolver) con badge en sidebar
- [ ] Queue health en dashboard

### Funcionalidades de gestión
- [ ] Panel de migraciones por tenant (estado + ejecutar + historial)
- [ ] Ejecutar migraciones en todos los tenants
- [ ] Blocklist de IPs y emails por tenant

### Funcionalidades de monitorización
- [ ] Error logs por tenant
- [ ] Error logs globales
- [ ] Activity feed en dashboard

### Funcionalidades de configuración
- [ ] Feature flags defaults por plan (tabla de comparación)
- [ ] Feature flags por tenant (overrides)
- [ ] Uso de `features[]` en la app de tenant para mostrar/ocultar módulos

### Actualizar pantallas existentes
- [ ] Diálogo impersonación silenciosa: añadir campo "Motivo"
- [ ] Detalle de tenant: quitar `last_login_at` de la tabla de usuarios, mostrar `created_at`
- [ ] Detalle de tenant: nuevas pestañas (tokens, migraciones, flags, blocklist, errors)
- [ ] Dashboard: añadir widgets de alertas, queue, actividad

---

## 16. Variables de entorno — sin cambios

```env
NEXT_PUBLIC_API_BASE_URL=https://api.lapesquerapp.es/api/v2/superadmin
# o en desarrollo:
NEXT_PUBLIC_API_BASE_URL=http://localhost:8000/api/v2/superadmin
```
