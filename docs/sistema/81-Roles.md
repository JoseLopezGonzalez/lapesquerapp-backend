# Sistema - Roles (Roles)

## ⚠️ Estado de la API
- **v1**: Obsoleta (no documentada)
- **v2**: Versión activa (este documento)

---

## 📋 Visión General

El modelo `Role` representa un **rol de usuario** para el sistema de autorización. Los roles definen los permisos y acceso de los usuarios a diferentes funcionalidades del sistema.

**Archivo del modelo**: `app/Models/Role.php`

**Autorización**: Los roles se usan con `RoleMiddleware` para controlar el acceso a rutas y funcionalidades.

---

## 🗄️ Estructura de Base de Datos

### Tabla: `roles`

**Migración**: `database/migrations/companies/2025_01_11_211806_create_roles_table.php`

**Campos**:

| Campo | Tipo | Nullable | Descripción |
|-------|------|----------|-------------|
| `id` | bigint | NO | ID único del rol |
| `name` | string | NO | Nombre del rol - **UNIQUE** |
| `description` | string | YES | Descripción del rol |
| `created_at` | timestamp | NO | Fecha de creación |
| `updated_at` | timestamp | NO | Fecha de última actualización |

**Índices**:
- `id` (primary key)
- `name` (unique)

### Tabla: `role_user`

**Migración**: `database/migrations/companies/2025_01_11_211806_create_role_user_table.php`

**Tabla pivot** para la relación many-to-many entre `users` y `roles`.

**Campos**:
- `id`: ID único
- `user_id`: FK a `users` (onDelete: cascade)
- `role_id`: FK a `roles` (onDelete: cascade)
- `created_at`: Timestamp
- `updated_at`: Timestamp

**Índices**:
- Primary key: `id`
- Foreign keys a `users` y `roles`

---

## 📦 Modelo Eloquent

### Fillable Attributes

```php
protected $fillable = ['name', 'description'];
```

### Traits

- `UsesTenantConnection`: Usa conexión tenant (multi-tenant)
- `HasFactory`: Para testing y seeders

---

## 🔗 Relaciones

### `users()` - Usuarios con este Rol
```php
public function users()
{
    return $this->belongsToMany(User::class, 'role_user');
}
```
- Relación muchos-a-muchos con `User`
- Usuarios que tienen este rol asignado

---

## 👥 Roles Disponibles

Según `database/seeders/RoleSeeder.php`, los roles predefinidos son:

### 1. `superuser`
- **Descripción**: "Superusuario con acceso completo al sistema"
- **Acceso**: Acceso total, gestión técnica, usuarios, logs, sesiones

### 2. `manager`
- **Descripción**: "Gerente con permisos de administración"
- **Acceso**: Gestión y administración

### 3. `admin`
- **Descripción**: "Administrador con permisos limitados"
- **Acceso**: Administración de datos

### 4. `store_operator`
- **Descripción**: "Operador de tienda con acceso a funciones específicas de la tienda asignada"
- **Acceso**: Operador de almacén (acceso limitado, pensado para restricción por `assigned_store_id`)

---

## 📡 Controlador

**Archivo**: `app/Http/Controllers/v2/RoleController.php`

**Permisos requeridos**: `role:superuser` (solo superusuarios pueden gestionar roles)

### Métodos del Controlador

#### `index(Request $request)` - Listar Roles
```php
GET /v2/roles
```

**⚠️ ERROR CRÍTICO**: El método está mal implementado. Consulta `User` en lugar de `Role`.

**Código actual** (líneas 18-41):
```php
$query = User::query(); // ⚠️ ERROR: Debería ser Role::query()
// ... filtros sobre usuarios ...
return RoleResource::collection($query->paginate($perPage));
```

**Problema**: Filtra usuarios pero intenta retornar `RoleResource`, causando error.

**Filtros disponibles** (si se corrigiera):
- `id`: Buscar por ID (LIKE)
- `name`: Buscar por nombre (LIKE)

**Orden**: Por nombre ascendente

**Query parameters**: `perPage` (default: 10)

**Respuesta**: Collection paginada de `RoleResource` (NO FUNCIONA)

#### `store(Request $request)` - Crear Rol
```php
POST /v2/roles
```

**Estado**: ⚠️ **NO IMPLEMENTADO** (método vacío)

#### `show($id)` - Mostrar Rol
```php
GET /v2/roles/{id}
```

**Estado**: ⚠️ **NO IMPLEMENTADO** (método vacío)

#### `update(Request $request, $id)` - Actualizar Rol
```php
PUT /v2/roles/{id}
```

**Estado**: ⚠️ **NO IMPLEMENTADO** (método vacío)

#### `destroy($id)` - Eliminar Rol
```php
DELETE /v2/roles/{id}
```

**Estado**: ⚠️ **NO IMPLEMENTADO** (método vacío)

#### `options()` - Opciones para Select
```php
GET /v2/roles/options
```

**Implementado**: ✅

**Respuesta**: Array simple con `id` y `name`
```json
[
    {
        "id": 1,
        "name": "superuser"
    },
    ...
]
```

---

## 📄 API Resource

**Archivo**: `app/Http/Resources/v2/RoleResource.php`

**Campos expuestos**:
```json
{
    "id": 1,
    "name": "superuser"
}
```

**Nota**: ⚠️ No incluye `description`, aunque existe en el modelo.

---

## 🛡️ Uso en Autorización

### RoleMiddleware

**Archivo**: `app/Http/Middleware/RoleMiddleware.php`

**Registro**: `app/Http/Kernel.php`

**Uso en rutas**:
```php
Route::middleware(['role:superuser'])->group(function () {
    // Solo superuser
});

Route::middleware(['role:superuser,manager,admin'])->group(function () {
    // Cualquiera de estos roles
});
```

Ver `docs/fundamentos/02-Autenticacion-Autorizacion.md` para más detalles.

---

## 🔐 Permisos y Autenticación

**Middleware requerido**:
- `auth:sanctum`: Autenticación requerida
- `role:superuser`: Solo superusuarios pueden gestionar roles

**Rutas**: Todas bajo `/v2/roles/*`

---

## 📝 Seeders

**Archivo**: `database/seeders/RoleSeeder.php`

Crea los 4 roles predefinidos si no existen:
- `superuser`
- `manager`
- `admin`
- `store_operator`

**Ejecución**: Se ejecuta automáticamente en seeders de tenant.

---

## Observaciones Críticas y Mejoras Recomendadas

### ⚠️ ERROR CRÍTICO: index() Consulta Modelo Incorrecto

1. **index() Consulta User en lugar de Role** (`app/Http/Controllers/v2/RoleController.php:20`)
   - Consulta `User::query()` pero debería ser `Role::query()`
   - **Líneas**: 20-40
   - **Problema**: Causará error al intentar retornar `RoleResource` de usuarios
   - **Recomendación**: Corregir para usar `Role::query()`

### ⚠️ CRUD Completo No Implementado

2. **Métodos Vacíos** (`app/Http/Controllers/v2/RoleController.php`)
   - `store()`, `show()`, `update()`, `destroy()` están vacíos
   - **Líneas**: 46-65
   - **Problema**: No se pueden crear, actualizar ni eliminar roles desde la API
   - **Recomendación**: Implementar métodos CRUD si se necesita gestión

### ⚠️ RoleResource No Incluye description

3. **description No en Resource** (`app/Http/Resources/v2/RoleResource.php:15-21`)
   - No incluye campo `description`
   - **Problema**: Información limitada en respuestas
   - **Recomendación**: Agregar `description` al resource

### ⚠️ Filtro por ID Usa LIKE

4. **Filtro ID Usa LIKE** (`app/Http/Controllers/v2/RoleController.php:23-26`)
   - Filtro por ID usa `LIKE` en lugar de igualdad
   - **Líneas**: 23-26
   - **Problema**: Comportamiento inesperado para IDs
   - **Recomendación**: Usar `where('id', $request->id)` o eliminar filtro por ID

### ⚠️ Sin Validación de Rol en Uso

5. **No Valida Uso Antes de Eliminar** (método no implementado)
   - Si se implementa `destroy()`, debería validar que el rol no esté en uso
   - **Problema**: Podría eliminar roles con usuarios asignados
   - **Recomendación**: Validar relaciones antes de eliminar

### ⚠️ Sin Validación de Nombre Único en Store

6. **No Valida Unicidad de Nombre** (método no implementado)
   - Si se implementa `store()`, debería validar unicidad
   - **Problema**: Podrían crearse roles duplicados
   - **Recomendación**: Agregar validación `unique:tenant.roles,name`

### ⚠️ Sin Relaciones Cargadas en Index

7. **No Carga Relaciones** (si se corrige `index()`)
   - Si se quiere mostrar usuarios con cada rol, necesitaría eager loading
   - **Recomendación**: Agregar `with('users')` si se necesita

---

**Última actualización**: Documentación generada desde código fuente en fecha de generación.

