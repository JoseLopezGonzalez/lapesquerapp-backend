# Sistema - Usuarios (Users)

## ⚠️ Estado de la API
- **v1**: Obsoleta (no documentada)
- **v2**: Versión activa (este documento)

---

## 📋 Visión General

El modelo `User` representa un **usuario** del sistema con autenticación y autorización basada en roles. Los usuarios pertenecen a un tenant específico y pueden tener múltiples roles asignados.

**Archivo del modelo**: `app/Models/User.php`

**Autenticación**: Laravel Sanctum (tokens API)

**Autorización**: Sistema de roles (many-to-many con `Role`)

---

## 🗄️ Estructura de Base de Datos

### Tabla: `users`

**Migración base**: `database/migrations/companies/2014_10_12_000000_create_users_table.php`

**Migración adicional**:
- `2025_09_05_105929_add_store_fields_to_users_table.php` - Agrega `assigned_store_id`, `company_name`, `company_logo_url`

**Campos**:

| Campo | Tipo | Nullable | Descripción |
|-------|------|----------|-------------|
| `id` | bigint | NO | ID único del usuario |
| `name` | string | NO | Nombre completo del usuario |
| `email` | string | NO | Email (único por tenant) |
| `email_verified_at` | timestamp | YES | Fecha de verificación del email |
| `password` | string | NO | Contraseña (hasheada) |
| `remember_token` | string | YES | Token para "recordar sesión" |
| `assigned_store_id` | bigint | YES | FK a `stores` - Almacén asignado (agregado después) |
| `company_name` | string | YES | Nombre de la empresa (agregado después) |
| `company_logo_url` | string | YES | URL del logo de la empresa (agregado después) |
| `created_at` | timestamp | NO | Fecha de creación |
| `updated_at` | timestamp | NO | Fecha de última actualización |

**Índices**:
- `id` (primary key)
- `email` (unique)

**Nota**: El campo `assigned_store_id` está pensado para restringir acceso de usuarios con rol `store_operator` a un almacén específico.

### Tabla: `role_user`

**Migración**: `database/migrations/companies/2025_01_11_211806_create_role_user_table.php`

**Tabla pivot** para la relación many-to-many entre `users` y `roles`.

**Campos**:
- `role_id`: FK a `roles`
- `user_id`: FK a `users`

**Índices**:
- Primary key compuesta: (`role_id`, `user_id`)

---

## 📦 Modelo Eloquent

### Fillable Attributes

```php
protected $fillable = [
    'name',
    'email',
    'password',
    'assigned_store_id',
    'company_name',
    'company_logo_url',
];
```

### Hidden Attributes

```php
protected $hidden = [
    'password',
    'remember_token',
];
```

### Casts

```php
protected $casts = [
    'email_verified_at' => 'datetime',
    'password' => 'hashed', // Auto-hashing en Laravel 10+
];
```

### Traits

- `UsesTenantConnection`: Usa conexión tenant (multi-tenant)
- `HasApiTokens` (Sanctum): Para autenticación API
- `HasFactory`: Para testing y seeders
- `Notifiable`: Para notificaciones

---

## 🔗 Relaciones

### 1. `roles()` - Roles del Usuario
```php
public function roles()
{
    return $this->belongsToMany(Role::class, 'role_user');
}
```
- Relación muchos-a-muchos con `Role`
- Un usuario puede tener múltiples roles

### 2. `activityLogs()` - Logs de Actividad
```php
public function activityLogs()
{
    return $this->hasMany(ActivityLog::class);
}
```
- Relación uno-a-muchos con `ActivityLog`
- Historial de acciones del usuario

---

## 🔢 Métodos Helper

### `hasRole($role)` - Verificar Rol

Verifica si el usuario tiene un rol específico.

```php
// Rol único
$user->hasRole('admin'); // bool

// Múltiples roles (al menos uno)
$user->hasRole(['admin', 'manager']); // bool
```

### `hasAnyRole(array $roles)` - Verificar Cualquier Rol

Verifica si el usuario tiene al menos uno de los roles especificados.

```php
$user->hasAnyRole(['admin', 'manager']); // bool
```

### `assignRole($roleName)` - Asignar Rol

Asigna un rol al usuario por nombre.

```php
$user->assignRole('admin');
```

### `removeRole($roleName)` - Quitar Rol

Elimina un rol del usuario por nombre.

```php
$user->removeRole('admin');
```

---

## 📡 Controlador

**Archivo**: `app/Http/Controllers/v2/UserController.php`

**Permisos requeridos**: `role:superuser` (solo superusuarios pueden gestionar usuarios)

### Métodos del Controlador

#### `index(Request $request)` - Listar Usuarios
```php
GET /v2/users
```

**Filtros disponibles** (query parameters):
- `id`: Buscar por ID (LIKE)
- `name`: Buscar por nombre (LIKE)
- `email`: Buscar por email (LIKE)
- `roles`: Filtrar por roles (array de nombres)
- `created_at[start]`: Fecha inicio creación
- `created_at[end]`: Fecha fin creación

**Ordenamiento**:
- `sort`: Campo de orden (default: `created_at`)
- `direction`: Dirección (default: `desc`)

**Query parameters**: `perPage` (default: 10)

**Respuesta**: Collection paginada de `UserResource`

#### `store(Request $request)` - Crear Usuario
```php
POST /v2/users
```

**Validación**:
```php
[
    'name' => 'required|string|max:255',
    'email' => 'required|email|unique:tenant.users,email',
    'password' => 'required|string|min:8',
    'role.id' => 'required|exists:tenant.roles,id',
]
```

**Request body**:
```json
{
    "name": "Juan Pérez",
    "email": "juan@example.com",
    "password": "password123",
    "role": {
        "id": 2
    }
}
```

**Comportamiento**:
- Crea el usuario con contraseña hasheada
- Asigna el rol especificado
- Usa transacción DB para garantizar consistencia

**Respuesta** (201):
```json
{
    "message": "Usuario creado correctamente.",
    "user_id": 1
}
```

#### `show($id)` - Mostrar Usuario
```php
GET /v2/users/{id}
```

**Eager Loading**: `roles`

**Respuesta**: JSON directo (no usa Resource)

#### `update(Request $request, $id)` - Actualizar Usuario
```php
PUT /v2/users/{id}
```

**Validación**:
```php
[
    'name' => 'sometimes|string|max:255',
    'email' => 'sometimes|email|unique:tenant.users,email,{id}',
    'password' => 'sometimes|string|min:8',
    'roles' => 'array|exists:tenant.roles,id',
]
```

**Comportamiento**:
- Actualiza solo los campos proporcionados
- Si se proporciona `password`, se hashea automáticamente
- Si se proporciona `roles` (array de IDs), sincroniza roles (reemplaza todos)

**Respuesta**: JSON directo del usuario actualizado

#### `destroy($id)` - Eliminar Usuario
```php
DELETE /v2/users/{id}
```

**Advertencia**: ⚠️ No valida si el usuario tiene datos asociados

#### `options()` - Opciones para Select
```php
GET /v2/users/options
```

**Respuesta**: Array simple con `id` y `name`

---

## 📄 API Resource

**Archivo**: `app/Http/Resources/v2/UserResource.php`

**Campos expuestos**:
```json
{
    "id": 1,
    "name": "Juan Pérez",
    "email": "juan@example.com",
    "roles": ["admin", "manager"],
    "created_at": "2025-01-15 10:00:00",
    "updated_at": "2025-01-15 10:00:00"
}
```

**Notas**:
- No incluye `password`, `assigned_store_id`, `company_name`, `company_logo_url`
- Solo incluye nombres de roles, no objetos completos

---

## 🔐 Permisos y Autenticación

**Middleware requerido**:
- `auth:sanctum`: Autenticación requerida
- `role:superuser`: Solo superusuarios pueden gestionar usuarios

**Rutas**: Todas bajo `/v2/users/*`

**Autenticación**: Ver `docs/fundamentos/02-Autenticacion-Autorizacion.md` para detalles sobre login/logout.

---

## 🏪 Almacén Asignado (assigned_store_id)

El campo `assigned_store_id` está pensado para restringir el acceso de usuarios con rol `store_operator` a un almacén específico.

**Estado**: Campo existe pero **no hay restricciones implementadas** en el código actual.

### Funcionalidades Pendientes

Las siguientes restricciones están planificadas pero **no implementadas aún**:

#### Restricciones para Store Operator

**Objetivo**: Limitar el acceso de usuarios con rol `store_operator` solo a su almacén asignado.

**Operaciones Permitidas** (cuando esté implementado):
- ✅ Ver su almacén asignado
- ✅ Ver palets de su almacén
- ✅ Crear palets en su almacén
- ✅ Actualizar palets de su almacén
- ✅ Asignar posiciones dentro de su almacén
- ✅ Cambiar estados de palets de su almacén

**Operaciones Restringidas** (cuando esté implementado):
- ❌ Ver otros almacenes
- ❌ Ver palets de otros almacenes
- ❌ Mover palets entre almacenes
- ❌ Crear/eliminar almacenes
- ❌ Acceder a estadísticas globales
- ❌ Operaciones masivas

#### Implementaciones Planificadas

1. **Middleware de Filtrado**: `FilterByAssignedStore` para filtrar automáticamente consultas
2. **Modificaciones en StoreController**: Filtrar almacenes por `assigned_store_id`
3. **Modificaciones en PalletController**: Filtrar palets por almacén asignado
4. **Scopes en Modelos**: Métodos helper para filtrar por almacén asignado
5. **Validaciones**: Verificar permisos en cada operación

**Referencia**: Información completa en `docs/store_operator_restrictions.md` (a ser eliminado después de integrar esta información).

---

## Observaciones Críticas y Mejoras Recomendadas

### ⚠️ UserResource No Incluye Todos los Campos

1. **Campos Faltantes en Resource** (`app/Http/Resources/v2/UserResource.php:15-25`)
   - No incluye `assigned_store_id`, `company_name`, `company_logo_url`
   - Solo incluye nombres de roles, no IDs
   - **Problema**: Información limitada en respuestas
   - **Recomendación**: Agregar campos si se necesitan

### ⚠️ Show No Usa Resource

2. **show() No Usa UserResource** (`app/Http/Controllers/v2/UserController.php:122-126`)
   - Retorna JSON directo en lugar de usar Resource
   - **Líneas**: 122-126
   - **Problema**: Inconsistencia con otros métodos
   - **Recomendación**: Usar `UserResource`

### ⚠️ Update No Usa Resource

3. **update() No Usa UserResource** (`app/Http/Controllers/v2/UserController.php:153`)
   - Retorna JSON directo en lugar de usar Resource
   - **Problema**: Inconsistencia
   - **Recomendación**: Usar `UserResource`

### ⚠️ Sin Validación de assigned_store_id

4. **assigned_store_id No Se Valida** (`app/Http/Controllers/v2/UserController.php`)
   - Campo en fillable pero no se valida ni guarda en store/update
   - **Problema**: Campo no se puede usar
   - **Recomendación**: Agregar validación y guardado si se necesita

### ⚠️ Eliminación Sin Validaciones

5. **No Valida Uso Antes de Eliminar** (`app/Http/Controllers/v2/UserController.php:159-164`)
   - No valida si el usuario tiene datos asociados (logs, etc.)
   - **Problema**: Puede eliminar usuarios con historial
   - **Recomendación**: Validar o usar soft deletes

### ⚠️ Sin Relación Definida con Store

6. **No Hay Relación store()** (`app/Models/User.php`)
   - Campo `assigned_store_id` existe pero no hay relación
   - **Problema**: No se puede acceder fácilmente al almacén
   - **Recomendación**: Agregar relación `belongsTo(Store::class, 'assigned_store_id')`

### ⚠️ Filtro por ID Usa LIKE

7. **Filtro ID Usa LIKE** (`app/Http/Controllers/v2/UserController.php:25-28`)
   - Filtro por ID usa `LIKE` en lugar de igualdad
   - **Líneas**: 25-28
   - **Problema**: Comportamiento inesperado para IDs
   - **Recomendación**: Usar `where('id', $request->id)`

### ⚠️ Roles en Store vs Update

8. **Inconsistencia en Manejo de Roles** (`app/Http/Controllers/v2/UserController.php`)
   - `store()` usa `role.id` (singular)
   - `update()` usa `roles` (plural, array)
   - **Problema**: Inconsistencia en API
   - **Recomendación**: Unificar formato (preferir plural)

### ⚠️ Sin Validación de Email Único en Update

9. **Validación Email Única Incompleta** (`app/Http/Controllers/v2/UserController.php:137`)
   - Validación `unique:tenant.users,email,{id}` puede tener problemas
   - **Problema**: Si se usa `{id}` literal en lugar de variable
   - **Recomendación**: Verificar que funcione correctamente

---

**Última actualización**: Documentación generada desde código fuente en fecha de generación.

