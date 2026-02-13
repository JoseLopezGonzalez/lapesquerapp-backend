# Checklist de Ejecución – Docker Sail PesquerApp

**Referencia:** Guía Entorno de Desarrollo PesquerApp + [IMPLEMENTATION_PLAN_DOCKER_SAIL.md](./IMPLEMENTATION_PLAN_DOCKER_SAIL.md)  
**Regla:** No avanzar al siguiente bloque hasta validar el actual.

---

## BLOQUE 1 – Docker & Sail

| # | Tarea | Verificación | Estado |
|---|--------|----------------|--------|
| 1.1 | Confirmar Sail en composer (`laravel/sail`) | `composer show laravel/sail` | ⬜ |
| 1.2 | Ejecutar `php artisan sail:install` (servicios 0,3,7: MySQL, Redis, Mailpit) | Existencia de `docker-compose.yml` en raíz | ⬜ |
| 1.3 | Revisar `docker-compose.yml`: servicios mysql, redis, mailpit, laravel.test | Puertos y nombres según guía | ⬜ |
| 1.4 | Levantar contenedores: `./vendor/bin/sail up -d` | `./vendor/bin/sail ps` muestra todos Running | ⬜ |
| 1.5 | Comprobar puertos: 80 (app), 3306 MySQL, 6379 Redis, 8025 Mailpit | Sin conflictos; acceso a Mailpit en :8025 | ⬜ |
| 1.6 | Generar APP_KEY si falta: `./vendor/bin/sail artisan key:generate` | APP_KEY definido en .env | ⬜ |

**Criterio de éxito del bloque:** Contenedores en marcha, `sail ps` OK, `sail artisan --version` responde.

---

## BLOQUE 2 – Configuración .env

| # | Tarea | Verificación | Estado |
|---|--------|----------------|--------|
| 2.1 | Crear/actualizar sección Docker en `.env.example`: DB_HOST=mysql, DB_DATABASE=pesquerapp, DB_USERNAME=sail, DB_PASSWORD=password | Variables documentadas | ⬜ |
| 2.2 | Redis Docker: REDIS_HOST=redis, REDIS_PORT=6379 | En .env.example y/o docs | ⬜ |
| 2.3 | Mail: MAIL_HOST=mailpit, MAIL_PORT=1025, MAIL_FROM_* según guía | Coincide con guía | ⬜ |
| 2.4 | Cache/Queue/Session: CACHE_DRIVER=redis, QUEUE_CONNECTION=redis, SESSION_DRIVER=redis | En .env para Sail | ⬜ |
| 2.5 | Sanctum: SANCTUM_STATEFUL_DOMAINS=localhost:3000,127.0.0.1:3000 | En .env.example y .env local | ⬜ |
| 2.6 | APP_NAME=PesquerApp, APP_URL según puerto (http://localhost o :8000) | Consistente con puerto expuesto | ⬜ |
| 2.7 | En entorno local: copiar/ajustar .env con valores Docker (sin tocar producción) | Conexión DB/Redis correcta con Sail | ⬜ |

**Criterio de éxito del bloque:** Con Sail up, `sail artisan tinker` → `DB::connection()->getPdo()` OK; Redis y Mailpit accesibles.

---

## BLOQUE 3 – Seeders

**Nota:** Se adapta a la arquitectura actual: roles vía `App\Enums\Role`, sin Spatie, sin columna `password` en users. FAOZone/Caliber solo si se crean modelos y migraciones.

| # | Tarea | Verificación | Estado |
|---|--------|----------------|--------|
| 3.1 | Decidir si se crean FAOZone y Caliber (migraciones + modelos). Si no: documentar y omitir esos seeders | Decisión documentada en plan | ⬜ |
| 3.2 | Crear/adaptar UsersSeeder: usuarios con `App\Enums\Role`, sin password (o con campo opcional si se añade para dev) | UsersSeeder ejecutable con tenant/central según diseño | ⬜ |
| 3.3 | Mantener RoleSeeder como no-op (roles en enum) o eliminar su llamada si no aporta | DatabaseSeeder / TenantDatabaseSeeder no fallan | ⬜ |
| 3.4 | Si existen FAOZone/Caliber: FAOZonesSeeder y CalibersSeeder según guía; si no: stubs o omitir | Seeders no referencian modelos inexistentes | ⬜ |
| 3.5 | Orden en DatabaseSeeder (central): RolesAndPermissions no aplica; orden seguro: RoleSeeder (no-op), UsersSeeder si es central, etc. | `sail artisan db:seed` (o flujo central) no falla | ⬜ |
| 3.6 | Orden en TenantDatabaseSeeder: mantener RoleSeeder, StoreOperatorUserSeeder, settings; añadir solo lo acordado | `tenants:migrate --seed` no falla | ⬜ |
| 3.7 | Ejecutar migraciones (central + tenant si aplica) y seed: `sail artisan migrate` y/o `tenants:migrate --seed` | Sin errores; datos coherentes en BD | ⬜ |

**Criterio de éxito del bloque:** Migraciones y seeders ejecutados sin error; usuarios (y roles enum) verificables; si hay FAOZone/Caliber, datos correctos.

---

## BLOQUE 4 – Integración Frontend

| # | Tarea | Verificación | Estado |
|---|--------|----------------|--------|
| 4.1 | CORS: en `config/cors.php` incluir `http://127.0.0.1:3000` y `http://localhost:3001` en allowed_origins | Frontend en 3000/3001 aceptado | ⬜ |
| 4.2 | Sanctum stateful: en `config/sanctum.php` usar `stateful` desde env o array con localhost:3000, 127.0.0.1:3000 | Dominios stateful configurados | ⬜ |
| 4.3 | .env: SANCTUM_STATEFUL_DOMAINS=localhost:3000,127.0.0.1:3000 (y SESSION_DRIVER=redis para Sail) | Cookies de sesión en SPA | ⬜ |
| 4.4 | Añadir ruta pública GET `/api/health`: estado app, opcional DB/Redis | Respuesta JSON 200 | ⬜ |
| 4.5 | Documentar en guía o README: frontend debe usar `withCredentials: true` (axios/fetch) para Sanctum | Docs actualizados | ⬜ |

**Criterio de éxito del bloque:** `curl -I http://localhost/api/health` 200; desde origen 3000, CORS y cookies permitidos.

---

## BLOQUE 5 – Validación final

| # | Tarea | Verificación | Estado |
|---|--------|----------------|--------|
| 5.1 | Migraciones: `sail artisan migrate:status` (y tenants si aplica) | Todas run | ⬜ |
| 5.2 | Seeders ejecutados (central y/o tenant) | Usuarios y datos esperados en BD | ⬜ |
| 5.3 | Roles: verificar que usuarios tienen `role` (enum) correcto | Consulta users.role | ⬜ |
| 5.4 | Redis: `sail artisan tinker` → `Cache::store('redis')->get('test'); Cache::store('redis')->put('test', 1);` | Sin error | ⬜ |
| 5.5 | Mailpit: http://localhost:8025 accesible | Interfaz visible | ⬜ |
| 5.6 | CORS: petición desde origen 3000 a api (p. ej. /api/health) con credentials | Sin error CORS | ⬜ |
| 5.7 | Sanctum: opcional login SPA y uso de cookie en siguiente request | Solo si se prueba flujo SPA | ⬜ |
| 5.8 | Generar `docs/FINAL_VALIDATION_REPORT.md` con resultados de este bloque | Documento creado | ⬜ |

**Criterio de éxito del bloque:** Todas las comprobaciones pasan; FINAL_VALIDATION_REPORT.md generado.

---

## Orden de ejecución

1. **BLOQUE 1** → Validar → seguir.
2. **BLOQUE 2** → Validar → seguir.
3. **BLOQUE 3** → Validar → seguir.
4. **BLOQUE 4** → Validar → seguir.
5. **BLOQUE 5** → Validar → redactar FINAL_VALIDATION_REPORT.md.

No continuar al bloque N+1 si el bloque N tiene algún fallo sin resolver.
