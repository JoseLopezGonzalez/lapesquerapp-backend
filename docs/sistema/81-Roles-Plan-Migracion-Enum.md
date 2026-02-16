# Plan de implementación: Roles como enum (Opción A)

**Objetivo:** Fijar los roles en código (enum/constantes), eliminar la tabla `roles` y la tabla pivot `role_user`, y almacenar un único rol por usuario en la columna `users.role`.

**Estrategia de referencia:** Ver documento de estrategia de Roles y Permisos en PesquerApp (roles fijos, no editables por el usuario final).

**Decisión:** Opción A — roles solo en código, sin tabla `roles`.

---

## 1. Resumen de cambios

| Área | Acción |
|------|--------|
| **Nuevo** | Crear enum `App\Enums\Role` con los roles fijos de la estrategia |
| **BD** | Añadir columna `users.role` (string), migrar datos desde `role_user`/`roles`, eliminar `role_user` y `roles` |
| **Modelo User** | Sustituir relación `roles()` y métodos `hasRole`/`assignRole`/`removeRole`/`hasAnyRole` por lógica basada en `users.role` |
| **Modelo Role** | Eliminar (ya no existe entidad en BD) |
| **API Roles** | Eliminar CRUD de roles; mantener solo endpoint de “opciones” (lista de roles para selects) basado en el enum |
| **API Users** | Cambiar `role_ids` → `role` (string), respuestas `roles` → `role` (string) |
| **Auth** | Login/me: devolver `role` (string) en lugar de `roles` (array) |
| **Middleware** | Sin cambios de firma; seguir comprobando por nombre de rol (ahora contra `user->role`) |
| **Rutas** | Quitar `apiResource('roles')`; mantener `GET roles/options`; actualizar middlewares que usan nombres de rol (mapeo si aplica) |
| **Seeders** | Dejar de sembrar `roles`; asignar `user.role` en seeders de usuarios |
| **Comandos / Helpers** | Asignar rol con `user->role = ...` y validación contra enum |

---

## 2. Mapeo de roles actuales → roles estrategia

**Roles definidos en la estrategia (nombres sugeridos para enum):**

- `tecnico` — Técnico (super-superuser)
- `administrador` — Administrador (superuser empresa)
- `direccion` — Dirección (solo lectura y análisis)
- `administracion` — Administración
- `comercial` — Comercial
- `operario` — Operario

**Roles actuales en BD (RoleSeeder):**

- `superuser`
- `manager`
- `admin`
- `store_operator`

**Acción previa a implementar:** Definir y anotar el mapeo exacto para migración de datos, por ejemplo:

- `superuser` → `tecnico`
- `manager` → ¿`direccion` o `administrador`?
- `admin` → `administrador` o `administracion`
- `store_operator` → `operario`

Y decidir si durante la migración se asigna un rol por defecto cuando un usuario tiene varios roles (ej. el de mayor nivel) o se rechaza la migración hasta corregir datos.

---

## 3. Inventario de archivos a tocar

### 3.1 Crear (nuevos)

| Archivo | Descripción |
|---------|-------------|
| `app/Enums/Role.php` | Enum (o clase de constantes) con casos: tecnico, administrador, direccion, administracion, comercial, operario. Incluir métodos: `values()`, `labels()` o `displayName()` para frontend. |

### 3.2 Migraciones (tenant / companies)

| Archivo | Acción |
|---------|--------|
| `database/migrations/companies/XXXX_add_role_to_users_table.php` | **Nueva migración:** Añadir columna `role` (string, nullable al principio para migración). Tras migrar datos desde `role_user`/`roles`, hacer `role` not nullable y con valor por defecto si se define. |
| `database/migrations/companies/XXXX_migrate_roles_to_users_role.php` | **Nueva migración (o mismo archivo):** Por cada usuario, leer su rol desde `role_user` + `roles` (ej. primer rol o regla de negocio), escribir en `users.role` el valor del enum. Resolver múltiples roles según criterio acordado. |
| `database/migrations/companies/XXXX_drop_role_user_and_roles.php` | **Nueva migración:** Eliminar tabla `role_user`, luego tabla `roles`. |
| `database/migrations/companies/2025_01_11_211806_create_roles_table.php` | **No modificar** (histórico). Las nuevas migraciones deshacen en orden. |
| `database/migrations/companies/2025_01_11_211806_create_role_user_table.php` | **No modificar** (histórico). |
| `database/migrations/companies/2025_01_11_211807_create_role_user_table.php` | Revisar si es duplicado; si es idéntico, considerar marcar o documentar. |
| `database/migrations/companies/2026_01_17_125130_add_display_name_to_roles_table.php` | **No modificar** (histórico). Se elimina la tabla después. |

### 3.3 Modelos

| Archivo | Acción |
|---------|--------|
| `app/Models/User.php` | Añadir `role` a `$fillable` y `$casts` si se usa. Eliminar relación `roles()`. Sustituir `hasRole($role)`, `hasAnyRole(array $roles)`, `assignRole($roleName)`, `removeRole($roleName)` por lógica que use `$this->role` y validación contra `App\Enums\Role`. |
| `app/Models/Role.php` | **Eliminar** o deprecar (si algo lo referencia temporalmente, eliminar cuando no queden referencias). |

### 3.4 Controladores

| Archivo | Acción |
|---------|--------|
| `app/Http/Controllers/v2/RoleController.php` | Eliminar `index`, `store`, `show`, `update`, `destroy`. Mantener solo `options()`: devolver lista de roles desde `Role::cases()` (o equivalente) con valor y etiqueta para selects. Dejar de usar modelo `Role` y `RoleResource` en options. |
| `app/Http/Controllers/v2/UserController.php` | Validación: `role` (string, enum) en lugar de `role_ids` (array). En `store`/`update` asignar `$user->role = $validated['role']`. Eliminar `roles()->sync()` y `$user->load('roles')`. Filtro por rol: `$query->where('role', $request->role)` (o `whereIn` si se permite filtrar por varios). Respuesta sigue por `UserResource`. |
| `app/Http/Controllers/v2/AuthController.php` | En `login` y `me`: devolver `'role' => $user->role` (string) en lugar de `'roles' => $user->roles->pluck('name')`. Dejar de cargar `$user->load('roles')`. |

### 3.5 Resources

| Archivo | Acción |
|---------|--------|
| `app/Http/Resources/v2/UserResource.php` | Exponer `'role' => $this->role` en lugar de `'roles' => $this->roles->pluck('name')`. |
| `app/Http/Resources/v2/RoleResource.php` | **Eliminar** o dejar de usar (si `options()` devuelve array simple, no hace falta resource para roles). |

### 3.6 Rutas

| Archivo | Acción |
|---------|--------|
| `routes/api.php` | Quitar `Route::apiResource('roles', RoleController::class)`. Mantener `Route::get('roles/options', [RoleController::class, 'options'])` dentro del grupo `role:superuser` (o el rol que se use para “lista de roles”). Actualizar todas las referencias a `role:superuser`, `role:manager`, etc., a los nuevos nombres del enum (ej. `role:tecnico`, `role:administrador`, …) según el mapeo definido. |

### 3.7 Middleware

| Archivo | Acción |
|---------|--------|
| `app/Http/Middleware/RoleMiddleware.php` | Sigue recibiendo nombres de rol por parámetro. Cambio interno: en lugar de `$user->hasAnyRole($roles)`, comprobar que `$user->role` está en el array `$roles`. Asegurar que `$user->role` existe (no null). |

### 3.8 Seeders

| Archivo | Acción |
|---------|--------|
| `database/seeders/RoleSeeder.php` | **Eliminar** o vaciar (ya no se crean filas en `roles`). Si se mantiene el archivo para no romper `TenantDatabaseSeeder`/`DatabaseSeeder`, reducir a `return;` o comentar contenido. |
| `database/seeders/TenantDatabaseSeeder.php` | Dejar de llamar a `RoleSeeder::class` (o mantener la llamada si RoleSeeder queda vacío). |
| `database/seeders/DatabaseSeeder.php` | Dejar de llamar a `RoleSeeder::class` (o mantener si queda vacío). |
| `database/seeders/AlgarSeafoodUserSeeder.php` | Asignar rol con `$user->role = 'operario'` (o valor enum); guardar `$user->save()`. Eliminar uso de `Role`, `assignRole`, `hasRole`. |
| `database/seeders/StoreOperatorUserSeeder.php` | Igual: `$user->role = 'operario'`; eliminar uso de `Role` y `assignRole`. |

### 3.9 Comandos y helpers

| Archivo | Acción |
|---------|--------|
| `app/Console/Commands/CreateTenantUser.php` | En lugar de `$user->assignRole($roleName)`, validar que `$roleName` está en `Role::values()` (o similar) y asignar `$user->role = $roleName`; `$user->save()`. |
| `app/Helpers/tenant_helpers.php` | En `createTenantUser()`: si se pasa `$roleName`, validar contra enum y asignar `$user->role = $roleName`; `$user->save()`. Eliminar `assignRole`. |

### 3.10 Configuración / Kernel

| Archivo | Acción |
|---------|--------|
| `app/Http/Kernel.php` | Sin cambios (middleware `role` sigue registrado). |

### 3.11 Tests

| Ubicación | Acción |
|-----------|--------|
| `tests/` | No hay tests que referencien Role según búsqueda. Tras implementar, añadir o ajustar tests que creen usuarios con `role` y comprueben middleware y respuestas de login/me/users. |

### 3.12 Factory

| Archivo | Acción |
|---------|--------|
| `database/factories/UserFactory.php` | Actualmente no asigna roles. Tras añadir columna `role`: incluir en `definition()` un valor por defecto (ej. `'role' => \App\Enums\Role::Operario->value`) para que `User::factory()->create()` y seeders que usen factory generen usuarios con rol válido. Alternativa: definir valor por defecto en migración y no tocar factory si los usuarios creados por factory pueden tener ese default. |

### 3.13 Documentación (lista completa)

| Archivo | Acción |
|---------|--------|
| `docs/sistema/81-Roles.md` | Actualizar: enum, columna `users.role`, eliminación tablas `roles` y `role_user`, contrato API (role, options desde enum). Corregir también el error documentado de RoleController (index usaba User). |
| `docs/sistema/80-Usuarios.md` | Actualizar por completo: quitar tabla `role_user`, relación many-to-many y `role_ids`; documentar columna `users.role`, filtro por `role` (string), validación `role` (enum), respuestas con `role` (string). Actualizar ejemplos de request/response (role_ids → role, roles → role). |
| `docs/fundamentos/02-Autenticacion-Autorizacion.md` | Actualizar: `user.roles` → `user.role` en ejemplos de login/me; sección "Sistema de Roles" y modelo Role → referenciar enum y columna `users.role`. |
| `docs/api-references/sistema/README.md` | Referencia completa de API sistema: usuarios (filtro `roles`→`role`, `role_ids`→`role`, respuesta `roles`→`role`); eliminar documentación de CRUD roles (GET/POST/PUT/DELETE `/v2/roles`, `/v2/roles/{id}`); mantener solo `GET /v2/roles/options` y actualizar formato de respuesta (desde enum, sin id numérico). |
| `docs/api-references/autenticacion/README.md` | En ejemplos de login y me: `"roles": ["admin"]` → `"role": "administrador"` (o valor enum); nota sobre un solo rol. |
| `docs/referencia/97-Rutas-Completas.md` | Quitar de la tabla las rutas CRUD de roles (GET/POST/PUT/DELETE `/v2/roles`, `/v2/roles/{id}`). Actualizar descripción de rutas por rol: `role:superuser` → `role:tecnico`, etc. según mapeo. Mantener `GET /v2/roles/options`. |
| `docs/referencia/95-Modelos-Referencia.md` | User: quitar `roles()` BelongsToMany; documentar atributo `role` (string) y métodos `hasRole`/`hasAnyRole` (sin assignRole/removeRole si se eliminan). Eliminar o marcar como obsoleto el modelo Role. |
| `docs/referencia/96-Recursos-API.md` | UserResource: `roles` (array) → `role` (string). |
| `docs/referencia/100-Rendimiento-Endpoints.md` | Lista que incluye `roles/options`; sin cambio obligatorio; opcional: nota de que options viene de enum. |
| `docs/referencia/101-Plan-Mejoras-GET-orders-id.md` | Opcional: añadir nota de que la tabla `role_user` se elimina en la migración de roles a enum. |
| Otros docs en `docs/` | Varios archivos mencionan "roles" en contexto genérico (permisos, sistema). Revisar con búsqueda post-implementación; los críticos son los listados arriba. |

---

## 4. Contrato de API (cambios para el frontend)

- **Login (`POST /v2/login`) y Me (`GET /v2/me`):**  
  - Antes: `user.roles` (array de strings).  
  - Después: `user.role` (string, un solo valor del enum).

- **Users (listado, show, store, update):**  
  - Antes: `roles` (array) en respuesta; en create/update: `role_ids` (array de IDs).  
  - Después: `role` (string) en respuesta; en create/update: `role` (string, valor del enum).  
  - Filtro de listado: si existe, pasar de `roles` (array) a `role` (string único) o mantener filtro por un solo rol.

- **Roles:**  
  - Eliminados: `GET /v2/roles`, `POST /v2/roles`, `GET /v2/roles/{id}`, `PUT /v2/roles/{id}`, `DELETE /v2/roles/{id}`.  
  - Se mantiene: `GET /v2/roles/options` → respuesta generada desde el enum. **Formato:** Decidir si se mantiene `[{ "id": "tecnico", "name": "Técnico" }]` (sin id numérico) para compatibilidad con selects del frontend que usen `id`/`name`, o se cambia a `value`/`label`; en cualquier caso, el valor identificador es el string del enum.

---

## 5. Orden de implementación sugerido

1. **Definir enum y mapeo**  
   Crear `App\Enums\Role` con valores y etiquetas. Documentar mapeo antiguo → nuevo.

2. **Migraciones (tenant)**  
   - Añadir `users.role` (nullable).  
   - Migrar datos de `role_user` + `roles` a `users.role` (aplicando mapeo y regla para varios roles).  
   - Hacer `role` not nullable (y default si se define).  
   - Eliminar `role_user` y `roles`.

3. **Modelo User**  
   Implementar getter/setter y métodos `hasRole`/`hasAnyRole` (y quitar `assignRole`/`removeRole` si solo se usa asignación directa). Eliminar relación `roles()`.

4. **RoleController**  
   Dejar solo `options()` desde enum; eliminar resto de métodos y uso de modelo `Role`.

5. **UserController y AuthController**  
   Cambiar a `role` (string), validación enum y respuestas con `role`.

6. **UserResource**  
   Devolver `role` en lugar de `roles`.

7. **Middleware**  
   Usar `$user->role` en `RoleMiddleware`.

8. **Rutas**  
   Quitar `apiResource('roles')`, actualizar nombres de rol en `middleware(['role:...'])`.

9. **Seeders**  
   Eliminar o vaciar RoleSeeder; actualizar AlgarSeafoodUserSeeder y StoreOperatorUserSeeder; ajustar TenantDatabaseSeeder y DatabaseSeeder.

9b. **UserFactory**  
   Añadir `role` con valor por defecto en `definition()` (o asegurar default en migración) para que tests y seeders que usen factory no fallen.

10. **Comando y helper**  
    CreateTenantUser y createTenantUser() con validación enum y asignación a `user.role`.

11. **Eliminar modelo Role y RoleResource**  
    Borrar archivos y limpiar referencias (imports, etc.).

12. **Documentación y pruebas**  
    Actualizar 81-Roles.md, 02-Autenticacion-Autorizacion.md y comprobar login, me, users y rutas protegidas por rol.

---

## 6. Riesgos y consideraciones

- **Tenants existentes:** La migración de datos debe ejecutarse en cada BD tenant. Comprobar que el comando/migración de tenants ejecuta estas migraciones.
- **Usuarios con varios roles:** Definir regla (ej. tomar el de mayor nivel o el primero) y documentarla; opcionalmente log o aviso en migración.
- **Valores nulos o roles inexistentes:** Si hay usuarios sin rol o con rol no mapeable, definir valor por defecto (ej. `operario`) o fallo controlado en migración.
- **Frontend:** Coordinar con el equipo frontend el cambio de `roles`/`role_ids` a `role` y la desaparición del CRUD de roles.
- **Duplicado de migración role_user:** Revisar `2025_01_11_211807_create_role_user_table.php`; si es duplicado, no ejecutar dos veces drop sobre la misma tabla.
- **UserFactory y seeders:** Si la columna `role` es not nullable y sin default, `User::factory(10)->create()` fallará salvo que el factory o la migración definan un valor por defecto.
- **Validación:** No existen Form Requests para User ni Role; la validación está en los controladores. Cambios solo en UserController y RoleController.
- **Policies/Gates:** No hay Policies ni Gates que usen el modelo Role; no tocar AuthServiceProvider.

---

## 7. Checklist final antes de dar por cerrada la implementación

- [ ] Enum `Role` creado con todos los valores y labels.
- [ ] Migraciones creadas y probadas en tenant de desarrollo (add column, migrate data, drop tables).
- [ ] User: `role` en fillable; métodos de rol usan `$this->role` y enum; sin relación `roles()`.
- [ ] RoleController solo con `options()`; sin modelo Role.
- [ ] UserController y AuthController usan `role` (string); validación con enum.
- [ ] UserResource devuelve `role`.
- [ ] RoleMiddleware comprueba `$user->role`.
- [ ] Rutas actualizadas (sin apiResource roles; nombres de rol en middleware).
- [ ] Seeders y comandos/helpers actualizados; RoleSeeder vacío o eliminado.
- [ ] Modelo Role y RoleResource eliminados; sin referencias restantes.
- [ ] UserFactory actualizado (valor por defecto de `role`) o migración con default; usuarios creados por factory tienen rol válido.
- [ ] Documentación actualizada (todos los archivos listados en § 3.13: 81-Roles, 80-Usuarios, 02-Autenticacion-Autorizacion, API-references/sistema, API-references/autenticacion, 97-Rutas-Completas, 95-Modelos-Referencia, 96-Recursos-API; opcional 100 y 101).
- [ ] Frontend informado del cambio de contrato (roles → role, role_ids → role, eliminación CRUD roles).

---

## 8. Elementos detectados en el re-análisis (ampliación del plan)

- **Documentación:** Además de 81-Roles y 02-Autenticacion-Autorizacion, hay que actualizar explícitamente: 80-Usuarios (role_user, role_ids, filtro, respuestas), docs/api-references/sistema/README.md (referencia completa usuarios y roles), docs/api-references/autenticacion/README.md (login/me con role), docs/referencia/97-Rutas-Completas.md (quitar rutas CRUD roles, nombres de rol), docs/referencia/95-Modelos-Referencia.md (User y Role), docs/referencia/96-Recursos-API.md (UserResource). Opcional: 100-Rendimiento-Endpoints, 101-Plan-Mejoras.
- **UserFactory:** No referencia roles hoy; al hacer `role` obligatorio en users, hay que dar valor por defecto en factory o en migración para que `User::factory()->create()` siga siendo válido.
- **Formato de roles/options:** El frontend puede esperar `id`/`name`; al pasar a enum no hay id numérico — decidir formato de respuesta (ej. `id` = valor string del enum, `name` = etiqueta) para no romper selects.
- **Policies/Gates:** No usan Role; AuthServiceProvider no requiere cambios.
- **Form Requests:** No hay Form Requests para User/Role; validación solo en controladores.
