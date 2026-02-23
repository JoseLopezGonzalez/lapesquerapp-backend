# Plan de Ejecución: Infraestructura SaaS — PesquerApp

**Estado**: Aprobado por el usuario  
**Versión**: 1.0  
**Fecha**: 2026-02-23  
**Origen**: `docs/implementacion/00_Plan_infraestructura_SaaS.md` + afinación en 2 rondas de decisiones

---

## Índice

1. [Visión general](#1-visión-general)
2. [Arquitectura actual relevante](#2-arquitectura-actual-relevante)
3. [Bloque 1 — Ampliación tabla central de tenants](#3-bloque-1--ampliación-tabla-central-de-tenants)
4. [Bloque 2 — CORS dinámico](#4-bloque-2--cors-dinámico)
5. [Bloque 3 — Autenticación Superadmin](#5-bloque-3--autenticación-superadmin)
6. [Bloque 4 — Endpoints Superadmin](#6-bloque-4--endpoints-superadmin)
7. [Bloque 5 — Onboarding automatizado de tenants](#7-bloque-5--onboarding-automatizado-de-tenants)
8. [Bloque 6 — Configuración de tenant desde la app](#8-bloque-6--configuración-de-tenant-desde-la-app)
9. [Bloque 7 — Especificación frontend Superadmin](#9-bloque-7--especificación-frontend-superadmin)
10. [Impersonación](#10-impersonación)
11. [Migraciones — resumen de cambios en BD](#11-migraciones--resumen-de-cambios-en-bd)
12. [Contrato API — endpoints completos](#12-contrato-api--endpoints-completos)
13. [Estrategia de testing](#13-estrategia-de-testing)
14. [Orden de ejecución y dependencias](#14-orden-de-ejecución-y-dependencias)
15. [Riesgos y mitigaciones](#15-riesgos-y-mitigaciones)
16. [Glosario de decisiones](#16-glosario-de-decisiones)

---

## 1. Visión general

PesquerApp es un SaaS multi-tenant (database-per-tenant) sobre Laravel 10 + Next.js 16. Actualmente la gestión de tenants es manual: crear BD, ejecutar migraciones, añadir subdominios al CORS, configurar `.env`. No existe panel de administración de la plataforma ni flujo automatizado de onboarding.

**Objetivo de esta implementación**: Dotar al sistema de la infraestructura SaaS mínima necesaria para operar como plataforma:

- CORS dinámico basado en tenants activos.
- Tabla central de tenants formalizada con estados, plan y metadatos.
- Panel Superadmin con autenticación independiente.
- Onboarding automatizado de nuevos tenants.
- Configuración de empresa editable por cada tenant.
- Impersonación (con consentimiento + silenciosa con log).

**No incluye en esta fase**: Pasarela de pago, facturación automática, métricas avanzadas de uso.

---

## 2. Arquitectura actual relevante

| Componente | Estado | Ubicación |
|-----------|--------|-----------|
| Tabla `tenants` | Existe: id, name, subdomain, database, active, timestamps | BD central (`mysql`) |
| Modelo `Tenant` | Existe con `$connection = 'mysql'`; fillable incluye `branding_image_url` | `app/Models/Tenant.php` |
| `TenantMiddleware` | Resuelve tenant por header `X-Tenant`; exige `active = true` | `app/Http/Middleware/TenantMiddleware.php` |
| CORS | `config/cors.php` con `allowed_origins` (fijos) + `allowed_origins_patterns` (regex) | `config/cors.php` |
| `MigrateTenants` | Recorre tenants activos, ejecuta migraciones en cada BD | `app/Console/Commands/MigrateTenants.php` |
| `CreateTenantUser` | Crea usuario en BD de un tenant existente | `app/Console/Commands/CreateTenantUser.php` |
| `TenantDatabaseSeeder` | Seed completo (catálogos + datos operativos de demo) | `database/seeders/TenantDatabaseSeeder.php` |
| Settings | Tabla `settings` (key-value) en cada BD tenant; modelo `Setting` | `app/Models/Setting.php` |
| Auth | Sanctum + magic link + OTP; opera sobre BD tenant | `app/Services/MagicLinkService.php` |
| Endpoint público tenant | `GET /api/v2/public/tenant/{subdomain}` | `app/Http/Controllers/Public/TenantController.php` |
| API base | `api.lapesquerapp.es/api/v2/...` | Producción |

---

## 3. Bloque 1 — Ampliación tabla central de tenants

### Objetivo
Convertir la tabla `tenants` en la fuente de verdad de la plataforma: estados, plan, configuración inicial, tracking de onboarding.

### Migración: `alter_tenants_table_saas_infrastructure`

**Columnas a añadir:**

| Columna | Tipo | Default | Descripción |
|---------|------|---------|-------------|
| `status` | `enum('pending','active','suspended','cancelled')` | `'active'` | Sustituye a `active` (boolean) |
| `plan` | `string(50)` nullable | `null` | Plan contratado (ej. 'basic', 'pro'). Solo informativo |
| `renewal_at` | `date` nullable | `null` | Fecha de renovación del plan |
| `timezone` | `string(50)` | `'Europe/Madrid'` | Zona horaria del tenant |
| `last_activity_at` | `timestamp` nullable | `null` | Último login de cualquier usuario del tenant |
| `onboarding_step` | `tinyInteger` unsigned nullable | `null` | Último paso completado del onboarding (1-8). null = onboarding no aplica (tenant preexistente) |
| `admin_email` | `string` nullable | `null` | Email del administrador principal (para notificaciones de onboarding) |

**Columnas a eliminar:**

| Columna | Motivo |
|---------|--------|
| `active` | Sustituida por `status`. Se migran datos: `active=true` → `status='active'`; `active=false` → `status='suspended'` |

**Índices:**

- `idx_tenants_status` en `status`
- `idx_tenants_subdomain` ya existe (unique)

### Cambios en modelo `Tenant`

```php
// Eliminar 'active' de fillable y casts
// Añadir:
protected $fillable = [
    'name', 'subdomain', 'database', 'status', 'plan', 'renewal_at',
    'timezone', 'branding_image_url', 'last_activity_at',
    'onboarding_step', 'admin_email',
];

protected $casts = [
    'status' => 'string',
    'renewal_at' => 'date',
    'last_activity_at' => 'datetime',
    'onboarding_step' => 'integer',
];

// Accessor de compatibilidad (periodo de transición; eliminar cuando no haya código que use ->active)
public function getIsActiveAttribute(): bool
{
    return $this->status === 'active';
}

// Scopes
public function scopeActive($query) { return $query->where('status', 'active'); }
public function scopeByStatus($query, string $status) { return $query->where('status', $status); }
```

### Cambios en `TenantMiddleware`

```php
// Antes:
$tenant = Tenant::where('subdomain', $subdomain)->where('active', true)->first();

// Después:
$tenant = Tenant::where('subdomain', $subdomain)->first();

if (!$tenant) {
    return response()->json(['error' => 'Tenant not found'], 404);
}

if ($tenant->status === 'suspended') {
    return response()->json([
        'error' => 'Cuenta suspendida',
        'userMessage' => 'Tu cuenta está suspendida. Contacta con soporte.',
        'status' => 'suspended',
    ], 403);
}

if ($tenant->status !== 'active') {
    return response()->json(['error' => 'Tenant not available'], 403);
}
```

### Endpoint público de estado del tenant

El endpoint existente `GET /api/v2/public/tenant/{subdomain}` debe devolver el `status` del tenant para que el frontend muestre pantalla de "cuenta suspendida" antes de intentar auth.

**Response ampliada:**
```json
{
    "data": {
        "name": "Brisamar",
        "status": "active",
        "branding_image_url": "https://..."
    }
}
```

---

## 4. Bloque 2 — CORS dinámico

### Objetivo
Que solo los subdominios correspondientes a tenants **activos** sean aceptados como origen CORS, sin tocar `.env` ni redesplegar al añadir tenants.

### Diseño

**Entornos de desarrollo** (`APP_ENV=local`): se mantiene la lista estática actual en `config/cors.php` (`localhost:3000`, `localhost:5173`, etc.). Sin cambios.

**Producción** (`APP_ENV=production`): se usa lógica dinámica que:

1. Extrae el subdominio del header `Origin` (ej. `https://brisamar.lapesquerapp.es` → `brisamar`).
2. Verifica si el subdominio es un tenant activo (consultando cache o BD central).
3. Si lo es, permite el origen. Si no, no añade cabeceras CORS (el navegador bloquea).

**Dominios base**: Configurables por env:
```env
CORS_BASE_DOMAINS=lapesquerapp.es,congeladosbrisamar.es
```

**Origen fijo del superadmin**: `admin.lapesquerapp.es` se añade a `allowed_origins` en `cors.php` (es fijo, no dinámico).

### Implementación técnica

**Opción elegida**: Middleware custom `DynamicCorsMiddleware` que extiende o reemplaza la lógica de `HandleCors` para producción.

**Cache**: Cache Laravel (driver configurado — file o redis según entorno) por subdominio, TTL 10 minutos:
```php
$isValid = Cache::remember(
    "cors:tenant:{$subdomain}",
    600, // 10 min
    fn () => Tenant::where('subdomain', $subdomain)->where('status', 'active')->exists()
);
```

**Invalidación de cache**: Al cambiar el `status` de un tenant (activar, suspender), se invalida la clave `cors:tenant:{subdomain}`.

### Cambios en `config/cors.php`

```php
'allowed_origins' => [
    // Desarrollo (siempre presentes)
    'http://localhost:3000',
    'http://localhost:5173',
    // Superadmin (fijo)
    'https://admin.lapesquerapp.es',
    // Producción principal
    'https://lapesquerapp.es',
],

// Los patterns se mantienen como fallback; la lógica dinámica tiene prioridad en producción
'allowed_origins_patterns' => env('APP_ENV') === 'local' ? [
    '#^http://[a-z0-9\-]+\.localhost:3000\z#',
] : [],
```

### Archivos implicados

| Archivo | Cambio |
|---------|--------|
| `app/Http/Middleware/DynamicCorsMiddleware.php` | **Nuevo**. Lógica dinámica para producción |
| `config/cors.php` | Añadir `admin.lapesquerapp.es`; simplificar patterns |
| `app/Http/Kernel.php` | Registrar `DynamicCorsMiddleware` en lugar de (o junto a) `HandleCors` |
| `.env.example` | Añadir `CORS_BASE_DOMAINS=lapesquerapp.es` |

---

## 5. Bloque 3 — Autenticación Superadmin

### Objetivo
Sistema de autenticación independiente para los usuarios que gestionan la plataforma. Opera contra la BD central, no contra ningún tenant.

### Modelo de datos

**Tabla `superadmin_users`** en BD central (`mysql`):

| Columna | Tipo | Descripción |
|---------|------|-------------|
| `id` | bigIncrements | PK |
| `name` | string | Nombre del superadmin |
| `email` | string unique | Email (login vía magic link + OTP) |
| `email_verified_at` | timestamp nullable | Verificación |
| `last_login_at` | timestamp nullable | Último acceso |
| `created_at`, `updated_at` | timestamps | Estándar |

> **Nota**: Usamos tabla separada `superadmin_users` en lugar de `users` para evitar confusión con posibles futuros usos de `users` en la BD central y para que el guard sea explícito.

**Modelo**: `App\Models\SuperadminUser` con `$connection = 'mysql'`.

### Guard Sanctum separado

```php
// config/auth.php
'guards' => [
    // ... guards existentes ...
    'superadmin' => [
        'driver' => 'sanctum',
        'provider' => 'superadmin_users',
    ],
],

'providers' => [
    // ... providers existentes ...
    'superadmin_users' => [
        'driver' => 'eloquent',
        'model' => App\Models\SuperadminUser::class,
    ],
],
```

### Tokens Sanctum en BD central

La tabla `personal_access_tokens` de Sanctum debe existir en la BD central para los tokens del superadmin. Si actualmente solo existe en BDs tenant, crear migración en la BD central.

### Flujo de auth

Reutiliza el mismo mecanismo de magic link + OTP pero apuntando a la BD central:

1. `POST /api/v2/superadmin/auth/request-access` — recibe `email`, busca en `superadmin_users`, genera token magic link + OTP, envía email.
2. `POST /api/v2/superadmin/auth/verify-magic-link` — verifica token, devuelve Sanctum token con guard `superadmin`.
3. `POST /api/v2/superadmin/auth/verify-otp` — verifica OTP, devuelve Sanctum token.
4. `POST /api/v2/superadmin/auth/logout` — revoca token.
5. `GET /api/v2/superadmin/auth/me` — devuelve usuario autenticado.

**Servicio**: `SuperadminAuthService` — similar a `MagicLinkService` pero opera sobre `SuperadminUser` en conexión `mysql`. No usa `TenantMailConfigService` (no hay tenant); usa config de correo global (`.env`).

**Tabla `superadmin_magic_link_tokens`** en BD central: misma estructura que `magic_link_tokens` de tenant.

### Middleware

```php
// app/Http/Middleware/SuperadminMiddleware.php
// Verifica que el request tenga un token Sanctum válido con guard 'superadmin'
```

**No pasa por `TenantMiddleware`**: Las rutas `/api/v2/superadmin/*` se excluyen del middleware de tenant.

### Comando crear primer superadmin

```bash
php artisan superadmin:create jose@lapesquerapp.es --name="Jose"
```

Crea el registro en `superadmin_users`. El email se pasa como argumento, no en `.env`.

### Archivos implicados

| Archivo | Cambio |
|---------|--------|
| `database/migrations/yyyy_create_superadmin_users_table.php` | **Nuevo** |
| `database/migrations/yyyy_create_superadmin_magic_link_tokens_table.php` | **Nuevo** |
| `database/migrations/yyyy_create_superadmin_personal_access_tokens_table.php` | **Nuevo** (si no existe en central) |
| `app/Models/SuperadminUser.php` | **Nuevo** |
| `app/Models/SuperadminMagicLinkToken.php` | **Nuevo** |
| `app/Services/SuperadminAuthService.php` | **Nuevo** |
| `app/Http/Middleware/SuperadminMiddleware.php` | **Nuevo** |
| `app/Http/Controllers/v2/Superadmin/AuthController.php` | **Nuevo** |
| `app/Console/Commands/CreateSuperadmin.php` | **Nuevo** |
| `config/auth.php` | Añadir guard + provider |
| `routes/api.php` | Añadir grupo de rutas superadmin |
| `app/Http/Middleware/TenantMiddleware.php` | Excluir rutas `api/v2/superadmin/*` |

---

## 6. Bloque 4 — Endpoints Superadmin

### Objetivo
CRUD completo de tenants + gestión de estado + visualización de usuarios de tenant.

### Endpoints

Todos bajo prefijo `api/v2/superadmin/`, protegidos por middleware `superadmin`.

#### Tenants

| Método | Ruta | Acción | Descripción |
|--------|------|--------|-------------|
| GET | `/tenants` | index | Listar tenants con filtros (status, plan, búsqueda por nombre/subdominio) |
| GET | `/tenants/{id}` | show | Detalle de un tenant |
| POST | `/tenants` | store | Crear tenant (dispara onboarding) |
| PUT | `/tenants/{id}` | update | Editar datos del tenant (name, plan, renewal_at, timezone, branding_image_url) |
| POST | `/tenants/{id}/activate` | activate | Cambiar status a `active`. Invalida cache CORS |
| POST | `/tenants/{id}/suspend` | suspend | Cambiar status a `suspended`. Invalida cache CORS |
| POST | `/tenants/{id}/cancel` | cancel | Cambiar status a `cancelled`. Invalida cache CORS |
| POST | `/tenants/{id}/retry-onboarding` | retryOnboarding | Relanzar onboarding desde el paso donde quedó |
| GET | `/tenants/{id}/users` | tenantUsers | Listar usuarios del tenant (conecta a BD del tenant temporalmente) |

#### Dashboard / Métricas básicas

| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/dashboard` | Totales: tenants por status, tenants activos, último onboarding, etc. |

### Request/Response detallado

**POST `/tenants`** (crear + onboarding):
```json
// Request
{
    "name": "Congelados Brisamar S.L.",
    "subdomain": "brisamar",
    "admin_email": "admin@brisamar.es",
    "plan": "pro",
    "timezone": "Europe/Madrid",
    "branding_image_url": "https://..."  // opcional
}

// Response 201
{
    "data": {
        "id": 5,
        "name": "Congelados Brisamar S.L.",
        "subdomain": "brisamar",
        "database": "tenant_brisamar",
        "status": "pending",
        "plan": "pro",
        "timezone": "Europe/Madrid",
        "onboarding_step": 0,
        "admin_email": "admin@brisamar.es",
        "created_at": "2026-02-23T14:30:00Z"
    },
    "message": "Tenant creado. Onboarding en progreso."
}
```

**GET `/tenants/{id}/users`**:
```json
// Response 200
{
    "data": [
        {
            "id": 1,
            "name": "Admin Brisamar",
            "email": "admin@brisamar.es",
            "role": "administrador",
            "last_login_at": "2026-02-22T10:15:00Z"
        }
    ]
}
```

### Archivos implicados

| Archivo | Cambio |
|---------|--------|
| `app/Http/Controllers/v2/Superadmin/TenantController.php` | **Nuevo** |
| `app/Http/Controllers/v2/Superadmin/DashboardController.php` | **Nuevo** |
| `app/Http/Requests/v2/Superadmin/StoreTenantRequest.php` | **Nuevo** |
| `app/Http/Requests/v2/Superadmin/UpdateTenantRequest.php` | **Nuevo** |
| `app/Http/Resources/v2/Superadmin/TenantResource.php` | **Nuevo** |
| `app/Http/Resources/v2/Superadmin/TenantUserResource.php` | **Nuevo** |
| `app/Services/Superadmin/TenantManagementService.php` | **Nuevo** |
| `routes/api.php` | Añadir rutas del grupo superadmin |

---

## 7. Bloque 5 — Onboarding automatizado de tenants

### Objetivo
Flujo automatizado idempotente que, dados los datos de un nuevo tenant, aprovisione BD, migraciones, datos iniciales, usuario admin y notifique.

### State machine de onboarding

| Paso | `onboarding_step` | Acción | Idempotencia |
|------|--------------------|--------|-------------|
| 1 | 1 | Crear registro en `tenants` con status `pending` | Si ya existe (por subdomain), skip |
| 2 | 2 | Crear base de datos `tenant_{subdomain}` | Si BD ya existe, skip |
| 3 | 3 | Ejecutar migraciones (`database/migrations/companies`) | Laravel detecta migraciones ya ejecutadas |
| 4 | 4 | Ejecutar seeder de producción (catálogos + almacén + settings) | Usa `updateOrInsert` para evitar duplicados |
| 5 | 5 | Crear usuario administrador del tenant | Si email ya existe en la BD tenant, skip |
| 6 | 6 | Guardar configuración del tenant en `settings` (name, logo, timezone desde central) | `updateOrInsert` |
| 7 | 7 | Activar tenant: status → `active` | Idempotente (set status) |
| 8 | 8 | Enviar email de bienvenida al admin del tenant | Si ya enviado (flag o log), skip |

Cada paso actualiza `onboarding_step` al completarse. Al relanzar, se reanuda desde `onboarding_step + 1`.

### Comando Artisan

```bash
php artisan tenant:onboard {subdomain} {admin_email} --name="Empresa" --plan=pro --timezone=Europe/Madrid
```

El panel Superadmin llama a un Job (`OnboardTenantJob`) que internamente invoca este comando (o ejecuta la misma lógica).

### Seeder de producción

**Nuevo**: `TenantProductionSeeder` (separado del `TenantDatabaseSeeder` de demo):

Incluye:
- `RoleSeeder` (roles del sistema)
- `ProductCategorySeeder`, `ProductFamilySeeder`
- `CaptureZonesSeeder`, `FishingGearSeeder`, `SpeciesSeeder`
- `CountriesSeeder`, `PaymentTermsSeeder`, `IncotermsSeeder`
- `TaxSeeder`
- `FAOZonesSeeder`
- `StoreSeeder` (un almacén "Principal")
- Settings iniciales desde config central del tenant

**No incluye** (eso es solo demo): `CustomerSeeder`, `OrderSeeder`, `ProductSeeder`, `BoxSeeder`, `PalletSeeder`, `EmployeeSeeder`, `PunchEventSeeder`, etc.

### Convención nombre BD

`tenant_{subdomain}` — ejemplo: `tenant_brisamar`, `tenant_costasur`.

El campo `database` en la tabla `tenants` se rellena automáticamente al crear el registro.

### Requisito de servidor

El usuario MySQL configurado en `.env` (`DB_USERNAME`) debe tener permiso `CREATE DATABASE`. Documentar en el runbook de despliegue.

```sql
GRANT CREATE ON *.* TO 'pesquerapp_user'@'%';
```

### Email de bienvenida

Template: `App\Mail\TenantWelcomeEmail`

Contenido:
- Nombre de la empresa
- URL del tenant: `https://{subdomain}.lapesquerapp.es`
- Instrucciones: "Accede con tu email ({admin_email}) mediante magic link. No necesitas contraseña."
- Enlace directo al login del tenant

### Archivos implicados

| Archivo | Cambio |
|---------|--------|
| `app/Console/Commands/OnboardTenant.php` | **Nuevo** |
| `app/Jobs/OnboardTenantJob.php` | **Nuevo** |
| `app/Services/Superadmin/TenantOnboardingService.php` | **Nuevo** |
| `database/seeders/TenantProductionSeeder.php` | **Nuevo** |
| `app/Mail/TenantWelcomeEmail.php` | **Nuevo** |
| `resources/views/emails/tenant-welcome.blade.php` | **Nuevo** |

---

## 8. Bloque 6 — Configuración de tenant desde la app

### Objetivo
Que cada tenant pueda editar sus datos de empresa (nombre comercial, NIF, dirección, logo, formato) desde su panel `/admin/settings`.

### Split de configuración

**BD central (`tenants`)** — solo modificable por superadmin:

| Campo | Descripción |
|-------|-------------|
| `name` | Razón social / nombre legal |
| `subdomain` | Subdominio (inmutable post-creación) |
| `database` | Nombre BD (auto) |
| `status` | Estado del tenant |
| `plan`, `renewal_at` | Plan contratado |
| `timezone` | Zona horaria |
| `branding_image_url` | Logo de la plataforma para este tenant |

**BD tenant (`settings`)** — modificable por admin del tenant:

| Key | Tipo | Descripción |
|-----|------|-------------|
| `company.display_name` | string | Nombre comercial (puede diferir del legal) |
| `company.tax_id` | string | NIF / CIF |
| `company.address` | string | Dirección fiscal |
| `company.city` | string | Ciudad |
| `company.postal_code` | string | Código postal |
| `company.phone` | string | Teléfono de contacto |
| `company.email` | string | Email de contacto |
| `company.logo_url` | string (URL) | Logo personalizado; por defecto hereda `branding_image_url` de central |
| `company.date_format` | string | Formato de fecha (ej. `d/m/Y`) |
| `company.currency` | string | Moneda (ej. `EUR`) |
| `company.mail.*` | varios | Configuración de correo (ya existe) |

### Endpoints

Los endpoints de settings ya existen (`SettingController`). Se amplían si es necesario para soportar las nuevas keys. No se cambian permisos por ahora (D5.3: todos los roles acceden).

### Uso dinámico

Los componentes que actualmente usan valores hardcodeados de empresa (PDF, emails, navbar) deben leer de settings:

- **PDFs** (`resources/views/pdf/`): leer `company.display_name`, `company.tax_id`, `company.address`, `company.logo_url`.
- **Emails** (`app/Mail/`): leer `company.display_name`, `company.logo_url` para header.
- **Frontend**: ya consume settings vía API; solo necesita que las keys existan.

### Archivos implicados

| Archivo | Cambio |
|---------|--------|
| Vistas PDF en `resources/views/pdf/` | Leer de settings en lugar de hardcoded |
| Plantillas email en `resources/views/emails/` | Idem |
| `database/seeders/TenantProductionSeeder.php` | Incluir settings iniciales de empresa |

---

## 9. Bloque 7 — Especificación frontend Superadmin

### Arquitectura

- **URL**: `admin.lapesquerapp.es`
- **Tech**: Next.js (proyecto separado del frontend de tenants)
- **API**: Consume `api.lapesquerapp.es/api/v2/superadmin/*`
- **Auth**: Magic link + OTP (misma UX que tenants pero contra guard superadmin)

### Pantallas (wireframes textuales)

#### 7.1 Login (`/login`)
- Campo: Email
- Botón: "Solicitar acceso"
- Al enviar: muestra formulario de verificación (campo OTP + texto "O revisa tu email para el enlace de acceso")
- Post-verificación: redirige a `/dashboard`

#### 7.2 Dashboard (`/`)
- **Cards resumen**: Total tenants, activos, suspendidos, pendientes, último onboarding
- **Tabla rápida**: Últimos 5 tenants creados con status y fecha
- **Accesos rápidos**: "Crear tenant", "Ver todos"

#### 7.3 Lista de tenants (`/tenants`)
- **Filtros**: Por status (tabs: Todos / Activos / Suspendidos / Pendientes / Cancelados), búsqueda por nombre/subdominio
- **Tabla**: Nombre, subdominio, plan, status (badge color), última actividad, fecha creación
- **Acciones por fila**: Ver detalle, activar/suspender (según status actual)
- **Botón**: "Nuevo tenant" (abre formulario)

#### 7.4 Detalle de tenant (`/tenants/{id}`)
- **Sección datos**: Nombre, subdominio, BD, plan, renovación, timezone, logo, status, fechas
- **Acciones**: Editar datos, activar, suspender, cancelar (con confirmación), relanzar onboarding (si hay paso pendiente)
- **Sección usuarios**: Tabla con usuarios del tenant (nombre, email, rol, último login)
- **Sección impersonación**: Botón "Solicitar acceso" junto a usuario admin → flujo de consentimiento. Botón "Acceso directo" → impersonación silenciosa (con log)

#### 7.5 Crear tenant (`/tenants/new`)
- **Formulario**: Nombre empresa, subdominio (con validación en tiempo real de disponibilidad), email admin, plan (select), timezone (select), logo URL (opcional)
- **Botón**: "Crear y comenzar onboarding"
- **Post-envío**: Redirige a detalle del tenant con barra de progreso de onboarding

#### 7.6 Progreso de onboarding
- En el detalle del tenant, si `status = pending` y `onboarding_step < 8`:
- **Barra de progreso**: 8 pasos, el actual destacado
- **Log**: Paso actual, tiempo transcurrido
- **Polling**: Cada 3-5 segundos para actualizar progreso
- **Botón**: "Reintentar" si el paso actual lleva más de X tiempo

---

## 10. Impersonación

### Dos modos

#### 10.1 Impersonación con consentimiento

**Flujo:**

1. Superadmin pulsa "Solicitar acceso" junto a un usuario admin del tenant.
2. Backend crea registro en `impersonation_requests` con status `pending`.
3. Email al admin del tenant: "PesquerApp Soporte solicita acceso temporal a tu cuenta. [Aprobar 30 min] [Rechazar]"
4. Los links del email son URLs firmadas (signed URLs de Laravel) que llaman a endpoints públicos.
5. Si aprueba: status → `approved`, `expires_at` = now + 30 min.
6. El superadmin puede ahora generar un token de impersonación para ese usuario.
7. Redirige a `{subdomain}.lapesquerapp.es` con el token.
8. Al expirar o al pulsar "Terminar sesión", el token se revoca.

#### 10.2 Impersonación silenciosa

**Flujo:**

1. Superadmin pulsa "Acceso directo" junto a un usuario admin del tenant.
2. Backend genera token de impersonación directamente (sin solicitud ni email).
3. Se registra en `impersonation_logs` (BD central): superadmin_id, tenant_id, target_user_id, started_at, ended_at.
4. Redirige a `{subdomain}.lapesquerapp.es` con el token.
5. El tenant **no recibe notificación**.

### Tablas

**`impersonation_requests`** (BD central):

| Columna | Tipo |
|---------|------|
| `id` | bigIncrements |
| `superadmin_user_id` | foreignId → superadmin_users |
| `tenant_id` | foreignId → tenants |
| `target_user_id` | unsignedBigInteger (ID del user en BD tenant) |
| `status` | enum('pending','approved','rejected','expired') |
| `approved_at` | timestamp nullable |
| `expires_at` | timestamp nullable |
| `created_at`, `updated_at` | timestamps |

**`impersonation_logs`** (BD central):

| Columna | Tipo |
|---------|------|
| `id` | bigIncrements |
| `superadmin_user_id` | foreignId → superadmin_users |
| `tenant_id` | foreignId → tenants |
| `target_user_id` | unsignedBigInteger |
| `mode` | enum('consent','silent') |
| `started_at` | timestamp |
| `ended_at` | timestamp nullable |
| `created_at` | timestamp |

### Endpoints de impersonación

| Método | Ruta | Descripción |
|--------|------|-------------|
| POST | `/superadmin/tenants/{id}/impersonate/request` | Solicitar acceso (modo consentimiento) |
| GET | `/public/impersonation/{token}/approve` | Aprobar solicitud (URL firmada, sin auth) |
| GET | `/public/impersonation/{token}/reject` | Rechazar solicitud (URL firmada, sin auth) |
| POST | `/superadmin/tenants/{id}/impersonate/silent` | Impersonación silenciosa |
| POST | `/superadmin/tenants/{id}/impersonate/token` | Generar token (si solicitud aprobada) |
| POST | `/superadmin/impersonate/end` | Terminar sesión de impersonación |

### Archivos implicados

| Archivo | Cambio |
|---------|--------|
| `database/migrations/yyyy_create_impersonation_requests_table.php` | **Nuevo** |
| `database/migrations/yyyy_create_impersonation_logs_table.php` | **Nuevo** |
| `app/Models/ImpersonationRequest.php` | **Nuevo** |
| `app/Models/ImpersonationLog.php` | **Nuevo** |
| `app/Services/Superadmin/ImpersonationService.php` | **Nuevo** |
| `app/Http/Controllers/v2/Superadmin/ImpersonationController.php` | **Nuevo** |
| `app/Http/Controllers/Public/ImpersonationApprovalController.php` | **Nuevo** |
| `app/Mail/ImpersonationRequestEmail.php` | **Nuevo** |

---

## 11. Migraciones — resumen de cambios en BD

### BD central (`mysql`)

| Migración | Tabla | Acción |
|-----------|-------|--------|
| `alter_tenants_table_saas_infrastructure` | `tenants` | Añadir status, plan, renewal_at, timezone, last_activity_at, onboarding_step, admin_email; eliminar active |
| `create_superadmin_users_table` | `superadmin_users` | Crear |
| `create_superadmin_magic_link_tokens_table` | `superadmin_magic_link_tokens` | Crear |
| `create_superadmin_personal_access_tokens_table` | `personal_access_tokens` | Crear (si no existe en central) |
| `create_impersonation_requests_table` | `impersonation_requests` | Crear |
| `create_impersonation_logs_table` | `impersonation_logs` | Crear |

### BD tenant (sin cambios de esquema)

No hay migraciones nuevas en `database/migrations/companies/`. Los cambios son solo de datos (nuevas keys en `settings` vía seeder).

---

## 12. Contrato API — endpoints completos

### Grupo: Superadmin Auth (`/api/v2/superadmin/auth/`)

Sin middleware de tenant ni de superadmin (rutas públicas de auth).

| Método | Ruta | Body | Response |
|--------|------|------|----------|
| POST | `request-access` | `{ email }` | 200 `{ message }` |
| POST | `verify-magic-link` | `{ token }` | 200 `{ token, user }` |
| POST | `verify-otp` | `{ email, code }` | 200 `{ token, user }` |
| POST | `logout` | — | 200 `{ message }` |
| GET | `me` | — | 200 `{ data: SuperadminUser }` |

### Grupo: Superadmin Tenants (`/api/v2/superadmin/tenants/`)

Middleware: `superadmin`.

| Método | Ruta | Body/Params | Response |
|--------|------|-------------|----------|
| GET | `/` | `?status=&search=&per_page=` | 200 paginated `TenantResource` |
| POST | `/` | `{ name, subdomain, admin_email, plan?, timezone?, branding_image_url? }` | 201 `TenantResource` |
| GET | `/{id}` | — | 200 `TenantResource` (con detalle ampliado) |
| PUT | `/{id}` | `{ name?, plan?, renewal_at?, timezone?, branding_image_url? }` | 200 `TenantResource` |
| POST | `/{id}/activate` | — | 200 `TenantResource` |
| POST | `/{id}/suspend` | — | 200 `TenantResource` |
| POST | `/{id}/cancel` | — | 200 `TenantResource` |
| POST | `/{id}/retry-onboarding` | — | 200 `{ message, onboarding_step }` |
| GET | `/{id}/users` | — | 200 `TenantUserResource[]` |

### Grupo: Superadmin Dashboard (`/api/v2/superadmin/dashboard`)

| Método | Ruta | Response |
|--------|------|----------|
| GET | `/` | 200 `{ total, active, suspended, pending, cancelled, last_onboarding }` |

### Grupo: Impersonación (`/api/v2/superadmin/tenants/{id}/impersonate/`)

| Método | Ruta | Response |
|--------|------|----------|
| POST | `request` | 200 `{ message, request_id }` |
| POST | `silent` | 200 `{ impersonation_token, redirect_url }` |
| POST | `token` | 200 `{ impersonation_token, redirect_url }` (solo si request aprobada) |

### Grupo: Público impersonación (`/api/v2/public/impersonation/`)

| Método | Ruta | Response |
|--------|------|----------|
| GET | `{token}/approve` | 200 HTML/redirect con confirmación |
| GET | `{token}/reject` | 200 HTML/redirect con confirmación |

### Grupo: Público tenant (ya existe, ampliar)

| Método | Ruta | Response actual → nuevo |
|--------|------|------------------------|
| GET | `/api/v2/public/tenant/{subdomain}` | Añadir `status` al response |

---

## 13. Estrategia de testing

### Tests Feature por bloque

| Bloque | Test | Qué verifica |
|--------|------|-------------|
| Tabla tenants | `TenantMigrationTest` | Migración exitosa, datos migrados (active → status), constraints |
| CORS | `DynamicCorsTest` | Origen de tenant activo aceptado; origen de tenant inexistente rechazado; origen dev aceptado en local; cache funciona; invalidación al cambiar status |
| Auth Superadmin | `SuperadminAuthTest` | Request access, verify magic link, verify OTP, logout, me; token inválido rechazado |
| Endpoints Superadmin | `SuperadminTenantCrudTest` | CRUD completo; cambios de estado; validaciones (subdomain duplicado, etc.) |
| Onboarding | `TenantOnboardingTest` | Flujo completo: crear → BD existe → migraciones → seed → admin → active → email enviado; idempotencia (relanzar no duplica) |
| Config tenant | `TenantSettingsTest` | Keys de empresa legibles/editables; settings iniciales presentes tras onboarding |
| Impersonación | `ImpersonationTest` | Solicitud → aprobación → token; silenciosa → log; rechazo; expiración |

### Configuración de tests

- Tests de superadmin: no necesitan conexión tenant; operan contra BD central (SQLite en memoria o BD de test).
- Tests de onboarding: necesitan poder crear BD real (o mockear `CREATE DATABASE`).
- Tests de CORS: HTTP tests que verifican cabeceras en response.

---

## 14. Orden de ejecución y dependencias

```
Bloque 1: Migración tabla tenants
    │
    ├──→ Bloque 2: CORS dinámico
    │
    └──→ Bloque 3: Auth Superadmin (paralelo a CORS)
              │
              └──→ Bloque 4: Endpoints Superadmin
                        │
                        ├──→ Bloque 5: Onboarding Job
                        │
                        └──→ Bloque 10: Impersonación
    
    Bloque 6: Config tenant desde app (paralelo, independiente)
    
    Bloque 7: Especificación frontend (documento, sin código; al final)
```

**Estimación de esfuerzo** (orientativa):

| Bloque | Esfuerzo |
|--------|----------|
| 1. Migración tenants | Bajo (1 migración + cambios modelo/middleware) |
| 2. CORS dinámico | Medio (middleware custom + cache + tests) |
| 3. Auth Superadmin | Medio-alto (modelos, servicio, guard, comando, 5 endpoints) |
| 4. Endpoints Superadmin | Medio (controller, service, requests, resources) |
| 5. Onboarding | Alto (Job con 8 pasos, seeder producción, idempotencia, email) |
| 6. Config tenant | Bajo (ampliar settings, actualizar PDFs/emails) |
| 7. Spec frontend | Bajo (solo documento; ya escrito arriba) |
| 10. Impersonación | Alto (2 flujos, tablas, signed URLs, tokens, email) |

---

## 15. Riesgos y mitigaciones

| Riesgo | Impacto | Mitigación |
|--------|---------|-----------|
| Permiso `CREATE DATABASE` no disponible en servidor | Onboarding no funciona | Documentar requisito en runbook; verificar permisos antes de desplegar; fallback: crear BD manualmente |
| Migración de `active` → `status` rompe código que use `->active` | Error en TenantMiddleware u otros | Accessor `getIsActiveAttribute()` de compatibilidad; buscar todas las referencias a `->active` antes de migrar |
| CORS dinámico introduce latencia | Requests más lentos | Cache con TTL 10 min; en dev se usa lista estática (sin consulta BD) |
| Sanctum tokens de superadmin en BD central vs tenant | Conflicto de tablas | Tabla `personal_access_tokens` separada en central o configurar Sanctum para usar conexión según guard |
| Onboarding falla a mitad | Tenant queda en estado inconsistente | State machine idempotente; `onboarding_step` permite relanzar; endpoint `retry-onboarding` |
| Impersonación silenciosa sin consentimiento | Riesgo legal si se usa indebidamente | Log obligatorio en `impersonation_logs`; auditable; documentar política de uso |

---

## 16. Glosario de decisiones

Todas las decisiones tomadas en rondas 1 y 2 están documentadas en:
- `.ai_work_context/20260223_1430/00_working/decisions_pending.md` (ronda 1 consolidada)
- `.ai_work_context/20260223_1430/00_working/decisions_round2.md` (ronda 2)

---

**Fin del documento de ejecución.**
