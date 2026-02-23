# Plan Panel Superadmin v2 — Funcionalidades Avanzadas

**Versión**: 1.0 — 2026-02-23  
**Contexto**: ERP SaaS multi-tenant para cooperativas pesqueras. <20 tenants activos. Sin herramientas externas de observabilidad. Queue Redis activa con worker. Impersonación implementada pero no verificada en producción.

---

## Criterios de priorización

| Factor | Valor |
|--------|-------|
| Escala | <20 tenants → soluciones pragmáticas, nativas Laravel |
| Herramientas externas | Ninguna (solo laravel.log + Redis) |
| Seguridad | Alta (cooperativas con datos sensibles de producción/ventas) |
| Operaciones frecuentes | Onboarding, soporte, migraciones |

---

## Estado actual (baseline)

| Componente | Estado |
|-----------|--------|
| Auth superadmin (magic link + OTP) | ✅ Implementado y verificado |
| CRUD tenants + onboarding automático | ✅ Implementado y verificado |
| Observabilidad onboarding (error, step, failed_at) | ✅ Implementado |
| Transiciones de estado con guardas | ✅ Implementado |
| Delete tenant con confirmación | ✅ Implementado |
| Impersonación (silent + consent) | 🟡 Implementado, **pendiente de prueba real** |
| Log de impersonación (tabla) | 🟡 Tabla existe, **sin endpoint de consulta** |
| Dashboard KPIs básicos | ✅ Implementado |
| Panel migraciones | ❌ No implementado |
| Seguridad / tokens activos | ❌ No implementado |
| Feature flags | ❌ No implementado |
| Observabilidad global | ❌ No implementado |
| Alertas sistema | ❌ No implementado |

---

## BLOQUE B — Impersonación: Completar y verificar

**Prioridad**: 🔴 Alta — es la herramienta de soporte más crítica  
**Esfuerzo**: Pequeño — la infraestructura existe, faltan endpoints de consulta y el campo `reason`

### Gap actual

- `ImpersonationLog` existe pero no hay endpoint para listar el historial.
- No hay campo `reason` (motivo de la sesión) — solo `mode` (silent/consent).
- No hay endpoint para que el superadmin termine una sesión activa desde el panel.
- No se ha probado el flujo completo en entorno real.

### Cambios a implementar

#### B.1 — Migración: añadir `reason` a `impersonation_logs`

```sql
ALTER TABLE impersonation_logs
  ADD COLUMN reason VARCHAR(500) NULL AFTER mode;
```

- Obligatorio en modo `silent`, opcional en `consent`.

#### B.2 — Endpoint: historial de impersonación

```
GET /api/v2/superadmin/impersonation/logs
  ?tenant_id=4
  ?superadmin_user_id=1
  ?from=2026-01-01
  ?page=1
```

Devuelve: `superadmin_name`, `tenant_name`, `target_user_id`, `mode`, `reason`, `started_at`, `ended_at`, `duration_minutes`.

#### B.3 — Endpoint: sesiones activas

```
GET /api/v2/superadmin/impersonation/active
```

Sesiones con `started_at` en las últimas 2 horas y `ended_at` null.

#### B.4 — Endpoint: terminar sesión desde panel

```
POST /api/v2/superadmin/impersonation/logs/{log}/end
```

Actualiza `ended_at`, revoca el token Sanctum del usuario impersonado.

#### B.5 — Validar flujo completo

- Silent: `POST tenants/{id}/impersonate/silent` + `reason` en body → genera token + log.
- Consent: request → email firmado → approve → `POST .../token` → log.
- End: revocación del token + `ended_at`.

### Archivos afectados

- `database/migrations/..._add_reason_to_impersonation_logs.php` (nuevo)
- `app/Models/ImpersonationLog.php` — añadir `reason` a `$fillable`
- `app/Services/Superadmin/ImpersonationService.php` — propagate `reason`
- `app/Http/Controllers/v2/Superadmin/ImpersonationController.php` — nuevos endpoints
- `routes/api.php` — rutas de historial y sesiones activas

---

## BLOQUE C — Seguridad: Tokens y actividad

**Prioridad**: 🔴 Alta — operacionalmente crítico para soporte  
**Esfuerzo**: Medio

### C.1 — Tokens activos por tenant

Ver y revocar tokens Sanctum activos en la base del tenant.

```
GET  /api/v2/superadmin/tenants/{tenant}/tokens
POST /api/v2/superadmin/tenants/{tenant}/tokens/revoke-all
POST /api/v2/superadmin/tenants/{tenant}/tokens/{tokenId}/revoke
```

Implementación: cross-tenant read sobre la tabla `personal_access_tokens` del tenant, igual que `getTenantUsers()`.

### C.2 — Intentos fallidos de login

Actualmente no existe tabla de intentos fallidos. Necesita:

1. Nueva tabla `login_attempts` en cada BD de tenant (migración companies):
   ```sql
   id, email, ip_address, attempted_at, success (bool)
   ```

2. Registrar en `AuthController::requestAccess()` cuando el email no existe o la verificación falla.

3. Endpoint superadmin:
   ```
   GET /api/v2/superadmin/tenants/{tenant}/login-attempts
     ?failed_only=true&from=2026-02-01
   ```

### C.3 — Detección de actividad sospechosa (alertas automáticas)

Criterios sencillos y configurables:

| Regla | Umbral por defecto |
|-------|--------------------|
| Intentos fallidos en 1h | > 10 desde misma IP |
| Intentos fallidos en 1h | > 5 para mismo email |
| Login desde IP nueva (si historial > 10 logins) | Cualquiera |

Implementación: comando artisan `superadmin:detect-suspicious` que puede ejecutarse en scheduler cada 15 minutos. Guarda alertas en tabla `system_alerts` (central).

### C.4 — Bloqueo automático de IP/email

```
POST /api/v2/superadmin/tenants/{tenant}/block-ip
POST /api/v2/superadmin/tenants/{tenant}/block-email
```

Tabla `tenant_blocklists` en central: `{tenant_id, type: ip|email, value, blocked_by, reason, expires_at}`.
El `AuthController` del tenant consulta esta tabla antes de procesar el acceso.

### Archivos afectados

- `database/migrations/companies/..._create_login_attempts_table.php` (nuevo — tenant)
- `database/migrations/..._create_system_alerts_table.php` (nuevo — central)
- `database/migrations/..._create_tenant_blocklists_table.php` (nuevo — central)
- `app/Http/Controllers/v2/AuthController.php` — registrar intentos
- `app/Services/Superadmin/SecurityService.php` (nuevo)
- `app/Console/Commands/DetectSuspiciousActivity.php` (nuevo)

---

## BLOQUE D — Panel de Migraciones

**Prioridad**: 🟠 Media-Alta — crítico para operaciones a largo plazo  
**Esfuerzo**: Medio

### Contexto

Con 134 migraciones en `database/migrations/companies/` y tenants que pueden tener versiones distintas (creados en fechas distintas, onboarding fallido parcial, etc.), este panel es imprescindible para mantenimiento.

### D.1 — Estado de migraciones por tenant

```
GET /api/v2/superadmin/tenants/{tenant}/migrations
```

Devuelve lista de todas las migraciones con estado `ran | pending` para ese tenant. Implementación: `migrate:status --database=tenant --path=database/migrations/companies` via `Artisan::call()` capturando output estructurado.

### D.2 — Número de migraciones pendientes (resumen)

Añadir al `TenantResource` un campo `pending_migrations_count` calculado. Puede cachearse 5 minutos por tenant para no hacer queries pesadas en cada listado.

### D.3 — Ejecutar migraciones pendientes desde panel

```
POST /api/v2/superadmin/tenants/{tenant}/migrations/run
```

Ejecuta `migrate --database=tenant --path=database/migrations/companies --force`. Devuelve output del proceso. **Se encola como Job** para no bloquear la request.

```
GET /api/v2/superadmin/tenants/{tenant}/migrations/status
```

Estado del job de migración (similar a onboarding_step pero para migraciones).

### D.4 — Ejecutar migraciones en todos los tenants

```
POST /api/v2/superadmin/migrations/run-all
  ?status=active  (filtro opcional)
```

Despacha un `MigrateTenantJob` por cada tenant activo. Ya existe el comando `MigrateTenants` artisan — se reutiliza su lógica.

### D.5 — Historial de ejecuciones

Tabla `tenant_migration_runs` (central): `{tenant_id, ran_at, ran_by_superadmin_id, migrations_applied, output, success}`.

### Archivos afectados

- `database/migrations/..._create_tenant_migration_runs_table.php` (nuevo — central)
- `app/Services/Superadmin/TenantMigrationService.php` (nuevo)
- `app/Jobs/MigrateTenantJob.php` (nuevo)
- `app/Http/Controllers/v2/Superadmin/TenantMigrationController.php` (nuevo)
- `routes/api.php`

---

## BLOQUE E — Feature Flags

**Prioridad**: 🟡 Media — no hay urgencia pero bloquea el modelo de negocio a futuro  
**Esfuerzo**: Grande — requiere diseño + implementación + integración en frontend tenant

### Modelo propuesto

Dado que los planes no están definidos, se propone un modelo de **dos niveles**:

1. **Plan-level defaults**: cada plan (`basic`, `pro`, `enterprise`) define qué flags están ON por defecto.
2. **Tenant overrides**: el superadmin puede activar/desactivar flags individualmente por encima del plan.

#### Flags propuestos para este ERP

| Flag | Descripción | basic | pro | enterprise |
|------|-------------|-------|-----|------------|
| `module.sales` | Pedidos, clientes, comerciales | ✅ | ✅ | ✅ |
| `module.inventory` | Almacenes, palets, cajas | ✅ | ✅ | ✅ |
| `module.raw_material` | Recepciones materia prima | ✅ | ✅ | ✅ |
| `module.production` | Producción, procesos, costes | ❌ | ✅ | ✅ |
| `module.cebo` | Despachos de cebo | ❌ | ✅ | ✅ |
| `module.labels` | Etiquetas GS1-128 | ❌ | ✅ | ✅ |
| `module.timeclock` | Fichajes y empleados | ❌ | ❌ | ✅ |
| `module.statistics` | Estadísticas y reportes | ❌ | ✅ | ✅ |
| `feature.pdf_export` | Exportación PDF pedidos/despachos | ✅ | ✅ | ✅ |
| `feature.import_a3erp` | Importación A3ERP | ❌ | ✅ | ✅ |
| `feature.import_facilcom` | Importación Facilcom | ❌ | ✅ | ✅ |
| `feature.ai_classification` | Clasificación OpenAI | ❌ | ❌ | ✅ |

#### Implementación

**Tabla central** `feature_flags` (configuración de planes):
```sql
id, flag_key, plan, enabled (bool), created_at, updated_at
```

**Tabla central** `tenant_feature_overrides`:
```sql
id, tenant_id, flag_key, enabled (bool), overridden_by_superadmin_id, reason, created_at
```

**Resolución en runtime** (en el tenant): middleware o helper `tenantCan('module.production')` que:
1. Lee overrides del tenant (cache Redis 5min).
2. Si no hay override, usa el default del plan del tenant.

#### Endpoints superadmin

```
GET  /api/v2/superadmin/feature-flags                    (defaults por plan)
GET  /api/v2/superadmin/tenants/{tenant}/feature-flags   (flags efectivos del tenant)
PUT  /api/v2/superadmin/tenants/{tenant}/feature-flags/{flag}
     body: { "enabled": true, "reason": "Demo extendido" }
DELETE /api/v2/superadmin/tenants/{tenant}/feature-flags/{flag}  (elimina override)
```

### Archivos afectados

- `database/migrations/..._create_feature_flags_table.php` (nuevo — central)
- `database/migrations/..._create_tenant_feature_overrides_table.php` (nuevo — central)
- `app/Models/FeatureFlag.php`, `TenantFeatureOverride.php` (nuevos)
- `app/Services/Superadmin/FeatureFlagService.php` (nuevo)
- `app/Http/Middleware/CheckFeatureFlag.php` (nuevo — para uso en rutas tenant)
- `app/Http/Controllers/v2/Superadmin/FeatureFlagController.php` (nuevo)
- `database/seeders/FeatureFlagSeeder.php` (nuevo — defaults por plan)

---

## BLOQUE F — Observabilidad nativa

**Prioridad**: 🟡 Media — útil en soporte pero no bloqueante  
**Esfuerzo**: Medio — todo nativo, sin herramientas externas

### F.1 — Log centralizado de errores 500 por tenant

Interceptar en `Handler.php` los errores 500 cuando hay un tenant activo y guardarlos en tabla central:

```sql
-- tabla: tenant_error_logs (central)
id, tenant_id, user_id (nullable), method, url, 
error_class, error_message, trace (text), occurred_at
```

Retención configurable (ej. 30 días). No guarda errores de tests ni de CLI.

```
GET /api/v2/superadmin/tenants/{tenant}/error-logs
  ?from=2026-02-01&limit=50
GET /api/v2/superadmin/error-logs   (todos los tenants, agrupados)
```

### F.2 — Actividad reciente global (dashboard)

Aprovechar `last_activity_at` del tenant (ya existe). Añadir endpoint:

```
GET /api/v2/superadmin/dashboard/activity
```

Devuelve los últimos 20 eventos: onboardings recientes, cambios de estado, sesiones de impersonación, migraciones ejecutadas — todos de las tablas que ya existen.

### F.3 — Monitor de queue

```
GET /api/v2/superadmin/system/queue-health
```

Devuelve: jobs pendientes, jobs fallidos (últimas 24h), último job procesado. Lee directamente de Redis via `app('redis')`.

### Archivos afectados

- `database/migrations/..._create_tenant_error_logs_table.php` (nuevo — central)
- `app/Exceptions/Handler.php` — hook para capturar errores 500
- `app/Services/Superadmin/ObservabilityService.php` (nuevo)
- `app/Http/Controllers/v2/Superadmin/ObservabilityController.php` (nuevo)

---

## BLOQUE G — Dashboard: Alertas del sistema

**Prioridad**: 🟡 Media — complementa los bloques anteriores  
**Esfuerzo**: Pequeño una vez implementados los bloques C y D

Tabla `system_alerts` (central, ya mencionada en C.3):

```sql
id, type (enum), severity (info|warning|critical), 
tenant_id (nullable), message, metadata (json),
resolved_at (nullable), created_at
```

Tipos de alerta generados automáticamente:

| Tipo | Generado por | Severidad |
|------|-------------|-----------|
| `onboarding_failed` | OnboardTenantJob::failed() | warning |
| `onboarding_stuck` | Scheduler: tenant en pending > 30min | warning |
| `migrations_pending` | Scheduler diario | info |
| `suspicious_activity` | DetectSuspiciousActivity command | critical |
| `impersonation_started` | ImpersonationService | info |
| `queue_stopped` | Scheduler: sin jobs procesados en 10min | critical |

Endpoint:

```
GET /api/v2/superadmin/alerts?severity=critical&resolved=false
POST /api/v2/superadmin/alerts/{alert}/resolve
```

---

## Exclusiones justificadas

| Funcionalidad | Razón de exclusión |
|--------------|-------------------|
| Métricas de uso real (requests/s, storage por tenant) | <20 tenants, complejidad no justificada sin herramientas externas |
| Flags por usuario individual | Excesiva granularidad para el volumen actual |
| Plantillas email editables | Las plantillas Blade son suficientes, coste-beneficio bajo |
| Monitor de almacenamiento | MySQL no expone storage por DB fácilmente; fuera de scope |
| Logs 500 con stack trace completo en central | Stack traces pueden ser muy grandes; se guarda resumen + referencia al laravel.log |

---

## Plan de implementación

### Fase 1 — Operaciones críticas (semanas 1-2)

| Tarea | Bloque | Esfuerzo |
|-------|--------|---------|
| Verificar y completar flujo impersonación | B | S |
| Añadir `reason` obligatorio + historial de logs | B | S |
| Tokens activos por tenant + revocación | C.1 | S |
| Panel de migraciones (status + run one tenant) | D.1–D.3 | M |

### Fase 2 — Seguridad y observabilidad (semanas 3-4)

| Tarea | Bloque | Esfuerzo |
|-------|--------|---------|
| Registro intentos fallidos login | C.2 | S |
| Detección actividad sospechosa + alertas | C.3 + G | M |
| Errores 500 centralizados | F.1 | M |
| Dashboard actividad reciente | F.2 | S |
| Monitor queue health | F.3 | XS |

### Fase 3 — Feature Flags (semanas 5-6)

| Tarea | Bloque | Esfuerzo |
|-------|--------|---------|
| Diseño definitivo de flags y planes | E | — |
| Implementación backend (tablas, servicio, endpoints) | E | L |
| Middleware `CheckFeatureFlag` en rutas tenant | E | M |
| Seeder con defaults por plan | E | S |

### Fase 4 — Cierre y automatizaciones (semana 7)

| Tarea | Bloque | Esfuerzo |
|-------|--------|---------|
| Migraciones en todos los tenants (run-all + historial) | D.4–D.5 | M |
| Bloqueo IP/email desde panel | C.4 | M |
| Scheduler con checks automáticos (onboarding stuck, queue) | G | S |

**Leyenda esfuerzo**: XS < 2h · S = 2-4h · M = 4-8h · L = 1-2 días

---

## Dependencias entre bloques

```
B (Impersonación) → independiente
C.1 (Tokens)      → independiente
C.2 (Logins)      → necesita migración companies
C.3 (Alertas)     → necesita tabla system_alerts
D (Migraciones)   → independiente
E (Feature flags) → requiere decisión de planes (reunión previa)
F.1 (Errores 500) → independiente
F.2 (Actividad)   → necesita B y D para tener datos ricos
G (Dashboard)     → depende de C.3 y F.1
```

---

## Notas de arquitectura

1. **Todo nativo Laravel**: sin Sentry, sin Datadog, sin servicios externos adicionales. La centralización de logs se hace en tablas MySQL de la BD central.

2. **Cache Redis**: las consultas cross-tenant costosas (migration status, feature flags) se cachean con TTL corto (2-5min) para no penalizar el panel.

3. **Jobs para operaciones largas**: migraciones masivas y detección de actividad sospechosa siempre via queue, nunca bloqueando la request HTTP.

4. **Retención de datos**: errores 500 y login attempts → 30 días. Logs de impersonación → indefinido (auditoría). Alertas resueltas → 90 días.

5. **Feature flags en frontend tenant**: el endpoint `GET /api/v2/me` (tenant) deberá incluir los flags activos del tenant para que el frontend pueda ocultar módulos sin llamadas adicionales.
