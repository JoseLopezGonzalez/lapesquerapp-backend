# Plan de Implementación: Docker Sail – PesquerApp Backend

**Fuente:** Guía Completa Entorno de Desarrollo PesquerApp con Docker Sail  
**Fecha de análisis:** 2025-02-12  
**Modo:** FASE 1 – Análisis inicial (sin modificar nada)

---

## 1. Estado actual del repositorio

### 1.1 Sail

| Aspecto | Estado | Detalle |
|--------|--------|---------|
| Sail en composer | ✅ Instalado | `laravel/sail: ^1.18` en `require-dev` |
| Configuración publicada | ❌ No | No existe `docker-compose.yml` en la raíz del proyecto |
| Script `sail` / uso | ⚠️ Pendiente | Tras `sail:install` se usará `./vendor/bin/sail` |

**Conclusión:** Sail está como dependencia pero no se ha ejecutado `php artisan sail:install`, por tanto no hay stack Docker definido.

### 1.2 Docker Compose

| Aspecto | Estado | Detalle |
|--------|--------|---------|
| `docker-compose.yml` | ❌ No existe | Solo referencias en la guía y en `.editorconfig` |
| Servicios esperados por guía | N/A | MySQL, Redis, Mailpit (selección 0,3,7 en `sail:install`) |

**Conclusión:** Es necesario ejecutar `sail:install` y elegir servicios 0,3,7 para alinear con la guía.

### 1.3 Servicios actuales

No hay contenedores definidos aún. Tras instalar Sail con 0,3,7 se tendrá:

- `laravel.test` (app)
- `mysql`
- `redis`
- `mailpit`

### 1.4 Archivo `.env`

| Variable | Estado actual (`.env.example`) | Guía (Docker) |
|----------|---------------------------------|---------------|
| `APP_NAME` | Laravel | PesquerApp |
| `APP_URL` | http://localhost | http://localhost |
| `DB_CONNECTION` | mysql | mysql |
| `DB_HOST` | 94.143.137.84 (remoto) | mysql |
| `DB_PORT` | 3308 | 3306 |
| `DB_DATABASE` | default | pesquerapp |
| `DB_USERNAME` | root | sail |
| `DB_PASSWORD` | (producción) | password |
| `REDIS_*` | 127.0.0.1, 6379 | redis, 6379 |
| `CACHE_DRIVER` | file | redis |
| `QUEUE_CONNECTION` | sync | redis |
| `SESSION_DRIVER` | file | redis |
| `MAIL_*` | mailpit:1025 ya presente | mailpit, 1025, MAIL_FROM_* ajustados |
| `SANCTUM_STATEFUL_DOMAINS` | No existe | localhost:3000, 127.0.0.1:3000 |
| `SESSION_DOMAIN` | No existe | Opcional para cookies |

**Conclusión:** `.env.example` está orientado a entorno remoto/producción. Para Sail hace falta un bloque específico Docker (DB, Redis, Mail, Cache, Queue, Session, Sanctum) o un `.env.sail.example` documentado.

### 1.5 Configuración de base de datos

- **Conexión por defecto:** `env('DB_CONNECTION', 'mysql')` → `mysql`.
- **Conexión tenant:** misma configuración (host, port, user, password) con `database` rellenado por tenant.
- **Migraciones:**
  - Raíz: `database/migrations/` (p. ej. tabla `tenants`).
  - Por tenant: `database/migrations/companies/` (usuarios, órdenes, etc.) vía `tenants:migrate`.
- **Flujo actual:** no hay un único “migrate:fresh --seed” sobre una sola BD; existe flujo central (migrate) + por-tenant (tenants:migrate --seed con `TenantDatabaseSeeder`).

**Conclusión:** La guía asume una única BD `pesquerapp` con usuarios y datos. Este proyecto es multi-tenant: BD central + BDs por tenant. Los pasos de migración y seed deben adaptarse a este flujo.

### 1.6 Spatie Laravel Permission

| Aspecto | Estado | Detalle |
|--------|--------|---------|
| Paquete `spatie/laravel-permission` | ❌ No instalado | No aparece en `composer.json` |
| Roles/permisos en código | ✅ Sí | `App\Enums\Role` (tecnico, administrador, direccion, administracion, comercial, operario) |
| Tablas `roles` / `permission` | ❌ Eliminadas | Migración `2026_02_10_120000_migrate_roles_to_enum_on_users` migró a columna `users.role` y eliminó `roles` y `role_user` |
| User y roles | ✅ Enum en columna | `User::$role` (string), `hasRole()` / `hasAnyRole()` sobre enum |

**Conclusión:** No se usa Spatie Permission. La guía propone `RolesAndPermissionsSeeder` con Spatie; aquí no aplica tal seeder. Los seeders de usuarios deben usar el enum `App\Enums\Role` y la columna `users.role`.

### 1.7 Seeders existentes

| Seeder | Uso actual | Guía |
|--------|------------|------|
| `DatabaseSeeder` | `RoleSeeder` + `User::factory(10)` | RolesAndPermissions, Users, FAOZones, Calibers, Clients, Products, Orders |
| `RoleSeeder` | No-op (roles en enum) | N/A (guía usa Spatie) |
| `TenantDatabaseSeeder` | RoleSeeder, StoreOperatorUserSeeder, settings | Se usa en `tenants:migrate --seed` |
| Otros | AlgarSeafoodUserSeeder, StoreOperatorUserSeeder, ProductCategorySeeder, ProductFamilySeeder, TenantDatabaseSeeder | Guía: ClientsSeeder, ProductsSeeder, FAOZonesSeeder, CalibersSeeder, OrdersSeeder |

**Conclusión:** Orden y contenido del `DatabaseSeeder` y seeders por tenant no coinciden con la guía. Hay que definir qué se ejecuta en “central” y qué en “tenant” y adaptar a enum de roles y modelos existentes.

### 1.8 Modelos FAOZone y Caliber

| Modelo | Estado | Detalle |
|--------|--------|---------|
| `FAOZone` | ❌ No existe | No hay modelo ni migración `fao_zones`; existe `CaptureZone` (otro dominio) |
| `Caliber` | ❌ No existe | No hay modelo ni migración `calibers` |

**Conclusión:** La guía asume tablas y modelos `fao_zones` y `calibers`. Aquí no existen. Para cumplir la guía habría que añadir migraciones y modelos (y decidir si viven en BD central o por tenant). Es un cambio estructural que debe quedar documentado y, si se hace, con migraciones explícitas.

### 1.9 Usuario y autenticación

| Aspecto | Estado | Guía |
|--------|--------|------|
| Columna `password` en `users` | ❌ Eliminada | Migración `2026_02_10_150000_drop_password_from_users_table` (acceso por magic link/OTP) |
| `User::$fillable` | Sin `password` | Guía crea usuarios con contraseña |
| Autenticación | Magic link / OTP | Guía: login con email/password para desarrollo |

**Conclusión:** No se pueden crear usuarios con contraseña sin revertir o compensar la migración que eliminó `password`. Los seeders de usuarios deben crearlos sin password (o documentar opción de reintroducir password solo para desarrollo local).

### 1.10 CORS

| Aspecto | Estado | Guía |
|--------|--------|------|
| `config/cors.php` | ✅ Configurado | paths api/*, sanctum/csrf-cookie |
| `allowed_origins` | Incluye localhost:3000, 5173, producción | Guía: localhost:3000, 3001, 127.0.0.1:3000 |
| `supports_credentials` | true | true |

**Conclusión:** CORS está alineado en esencia; solo falta asegurar `127.0.0.1:3000` y `localhost:3001` si se desea coincidir exactamente con la guía.

### 1.11 Sanctum

| Aspecto | Estado | Guía |
|--------|--------|------|
| Paquete | ✅ laravel/sanctum | - |
| `config/sanctum.php` stateful | `[]` | Debe incluir dominios del frontend (localhost:3000, 127.0.0.1:3000) |
| Variables `.env` | No hay SANCTUM_STATEFUL_DOMAINS | SANCTUM_STATEFUL_DOMAINS=localhost:3000,127.0.0.0.1:3000 |

**Conclusión:** Sanctum está instalado pero los dominios stateful están vacíos. Hay que configurar `stateful` (por config o por env) y, si se usa, `SANCTUM_STATEFUL_DOMAINS` en `.env` para desarrollo.

### 1.12 Endpoint de health

| Aspecto | Estado | Guía |
|--------|--------|------|
| Ruta `api/health` o similar | ❌ No encontrada | Frontend de prueba llama a `/health`; guía sugiere comprobar conexión backend |

**Conclusión:** Conviene añadir una ruta pública de health (p. ej. `GET /api/health`) para comprobaciones y para el frontend.

---

## 2. Diferencias respecto al documento guía

1. **Sail:** Solo en composer; falta publicar configuración (`sail:install`) y tener `docker-compose.yml`.
2. **.env:** Valores actuales para BD/Redis/Mail no son los de Docker; faltan variables para Sail y Sanctum.
3. **Base de datos:** Arquitectura multi-tenant (central + companies) vs guía con una sola BD; flujo de migración y seed distinto.
4. **Roles/permisos:** Enum en columna `users.role` en lugar de Spatie; no hay `RolesAndPermissionsSeeder` con Spatie.
5. **Usuarios:** Sin columna `password`; seeders de usuarios no pueden usar “password” sin un cambio previo (solo dev).
6. **FAOZone / Caliber:** No existen modelo ni tablas; la guía asume seeders que dependen de ellos.
7. **Seeders:** Orden y clases distintos; ClientsSeeder, ProductsSeeder, OrdersSeeder de la guía pueden existir o no; hay que mapear a TenantDatabaseSeeder y seeders por tenant.
8. **Sanctum stateful:** Vacío; la guía requiere dominios para SPA.
9. **Health:** No existe ruta explícita; la guía la usa para verificación.

---

## 3. Riesgos potenciales

| Riesgo | Mitigación |
|--------|------------|
| Sobrescribir `.env` de producción | No tocar `.env` de producción; usar `.env.example` / `.env.sail.example` y documentar; en local usar copia para Sail. |
| Conflicto puertos 80/3306/6379 | Documentar y, si hace falta, cambiar solo en `docker-compose.yml` y en `.env` de desarrollo, sin tocar producción. |
| Introducir Spatie sin criterio | No instalar Spatie para cumplir la guía; adaptar seeders al enum actual. |
| Reintroducir `password` en todos los entornos | Si se añade password para dev, que sea opcional (migración condicional o solo en entorno local). |
| Seeders que asumen modelos inexistentes | No crear FAOZone/Caliber sin acuerdo; si se crean, hacer migraciones + modelos y documentar. |
| Romper flujo tenant | Mantener `tenants:migrate` y TenantDatabaseSeeder; cualquier seeder nuevo integrarse en ese flujo o en uno “central” documentado. |

---

## 4. Cambios necesarios (resumen)

1. **Docker y Sail**  
   - Ejecutar `php artisan sail:install` (servicios 0,3,7).  
   - Verificar y, si hace falta, ajustar `docker-compose.yml` (puertos, nombres).

2. **.env para Sail**  
   - Añadir o documentar variables: DB_* (mysql/sail/password), REDIS_* (redis), MAIL_* (mailpit), CACHE_DRIVER, QUEUE_CONNECTION, SESSION_DRIVER, SANCTUM_STATEFUL_DOMAINS (y opcional SESSION_DOMAIN).  
   - Mantener APP_NAME=PesquerApp y APP_URL según puerto usado.

3. **Seeders**  
   - No implementar `RolesAndPermissionsSeeder` con Spatie.  
   - Adaptar “UsersSeeder” al enum Role y a la ausencia de password (o a una opción dev con password si se decide).  
   - Si se crean FAOZone/Caliber: migraciones + modelos + FAOZonesSeeder y CalibersSeeder; si no, dejar documentado y opcional.  
   - Definir orden de seeders en DatabaseSeeder (central) y en TenantDatabaseSeeder (tenant), sin romper flujo actual.

4. **Integración frontend**  
   - CORS: añadir 127.0.0.1:3000 y localhost:3001 si se desea paridad con la guía.  
   - Sanctum: configurar stateful (config y/o SANCTUM_STATEFUL_DOMAINS).  
   - Añadir ruta pública `GET /api/health` (y documentar `withCredentials` en frontend).

5. **Validación**  
   - Scripts o pasos comprobables: conexión MySQL, Redis, migraciones (central y, si aplica, tenant), seeders, CORS, Sanctum, health.

---

## 5. Dependencias críticas

| Dependencia | Tipo | Acción |
|-------------|------|--------|
| Sail publicado (docker-compose) | Bloque 1 | Ejecutar `sail:install`. |
| Variables .env Docker | Bloque 2 | Escribir en `.env.example` o `.env.sail.example` y en docs. |
| No depender de Spatie para roles | Bloque 3 | Usar solo App\Enums\Role y columna `role`. |
| Usuarios sin password (o password opcional dev) | Bloque 3 | Seeders sin password o migración opcional documentada. |
| FAOZone/Caliber | Bloque 3 | Opcional; si se implementan, migraciones + modelos primero. |
| Flujo tenant (tenants:migrate, TenantDatabaseSeeder) | Global | No reemplazar; integrar cualquier seeder nuevo en este flujo o en flujo central documentado. |

---

## 6. Siguiente paso

Generar **FASE 2:** `docs/instrucciones/EXECUTION_CHECKLIST.md` con checklist por bloques (Docker/Sail, .env, Seeders, Integración frontend, Validación final) y solo entonces ejecutar cambios en **FASE 3** por bloques verificables.
