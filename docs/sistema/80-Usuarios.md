# Sistema - Usuarios (Users)

## ⚠️ Estado de la API
- **v1**: Obsoleta (no documentada)
- **v2**: Versión activa (este documento)

---

## 📋 Visión General

El modelo `User` representa un **usuario** del sistema con autenticación (Laravel Sanctum) y **un único rol** almacenado en la columna `role`.

**Archivo del modelo**: `app/Models/User.php`

**Autorización**: El rol se usa con `RoleMiddleware`; los valores posibles están definidos en `App\Enums\Role`.

---

## 🗄️ Estructura de Base de Datos

### Tabla: `users`

**Migraciones**:
- `2014_10_12_000000_create_users_table.php` — Base
- `2025_09_05_105929_add_store_fields_to_users_table.php` — `assigned_store_id`, `company_name`, `company_logo_url`
- `2026_02_10_120000_migrate_roles_to_enum_on_users.php` — Columna `role`

**Campos**:

| Campo | Tipo | Nullable | Descripción |
|-------|------|----------|-------------|
| `id` | bigint | NO | ID único del usuario |
| `name` | string | NO | Nombre completo |
| `email` | string | NO | Email (único por tenant) |
| `email_verified_at` | timestamp | YES | Fecha de verificación del email |
| `remember_token` | string | YES | Token "recordar sesión" |
| `active` | boolean | NO | Usuario activo |
| `role` | string | NO | Rol del usuario (valor de `App\Enums\Role`: tecnico, administrador, direccion, administracion, comercial, operario). Default: `operario` |
| `assigned_store_id` | bigint | YES | FK a `stores` — Almacén asignado |
| `company_name` | string | YES | Nombre de la empresa |
| `company_logo_url` | string | YES | URL del logo |
| `created_at` | timestamp | NO | Fecha de creación |
| `updated_at` | timestamp | NO | Fecha de actualización |

**Índices**: `id` (PK), `email` (unique).

**Nota**: `assigned_store_id` está pensado para restringir acceso de usuarios con rol `operario` a un almacén concreto (cuando se implemente).

---

## 📦 Modelo Eloquent

### Fillable

```php
protected $fillable = [
    'name', 'email', 'active', 'role',
    'assigned_store_id', 'company_name', 'company_logo_url',
];
```
(No hay campo `password`; el acceso es por magic link u OTP.)

### Métodos de rol

- **`hasRole($role)`**: `$role` puede ser string o array de strings. Comprueba si `$this->role` coincide.
- **`hasAnyRole(array $roles)`**: Comprueba si `$this->role` está en el array.

No existen `roles()`, `assignRole()` ni `removeRole()`; el rol se asigna mediante el atributo `role`.

---

## 📡 Controlador

**Archivo**: `app/Http/Controllers/v2/UserController.php`

**Permisos**: Rutas de usuarios requieren `role:tecnico`.

### `index(Request $request)` — Listar usuarios

**Ruta**: `GET /v2/users`

**Filtros** (query):
- `id`, `name`, `email`: búsqueda (LIKE)
- **`role`**: filtrar por rol (string, un valor del enum)
- `created_at[start]`, `created_at[end]`: rango de fechas

**Orden**: `sort` (default: `created_at`), `direction` (default: `desc`).  
**Paginación**: `perPage` (default: 10).

**Respuesta**: Collection paginada de `UserResource`.

### `store(Request $request)` — Crear usuario

**Ruta**: `POST /v2/users`

**Validación**:
- `name`: required, string, max 255
- `email`: required, email, unique en tenant
- **`role`**: required, string, debe ser uno de `App\Enums\Role::values()`
- `active`: optional, boolean  

(No hay campo `password`; los usuarios acceden por magic link u OTP. Usar "Reenviar invitación" para enviarles el enlace.)

**Request body** (ejemplo):
```json
{
  "name": "Juan Pérez",
  "email": "juan@example.com",
  "role": "administracion",
  "active": true
}
```

**Respuesta** (201): `message` + `data` (UserResource con `role`).

### `show($id)` — Mostrar usuario

**Ruta**: `GET /v2/users/{id}`

**Respuesta**: `UserResource` (incluye `role`).

### `update(Request $request, $id)` — Actualizar usuario

**Ruta**: `PUT /v2/users/{id}`

**Validación** (todos opcionales):
- `name`, `email`, `active`
- **`role`**: string, uno de `Role::values()`  

(No hay campo `password`.)

Si se envía `role`, se actualiza el rol del usuario.

**Respuesta**: `UserResource`.

### `destroy($id)` — Eliminar usuario

**Ruta**: `DELETE /v2/users/{id}`

**Advertencia**: No comprueba datos asociados (logs, etc.).

### `options()` — Opciones para select

**Ruta**: `GET /v2/users/options`

**Respuesta**: Array `[{ "id", "name" }, ...]`.

---

## 📄 API Resource

**Archivo**: `app/Http/Resources/v2/UserResource.php`

**Campos**:
```json
{
  "id": 1,
  "name": "Juan Pérez",
  "email": "juan@example.com",
  "role": "administracion",
  "created_at": "2025-01-15 10:00:00",
  "updated_at": "2025-01-15 10:00:00"
}
```

`role` es un **string** (valor del enum), no un array.

---

## 🔐 Permisos

- **Middleware**: `auth:sanctum`, `role:tecnico` para gestión de usuarios.
- Ver `docs/fundamentos/02-Autenticacion-Autorizacion.md` para login y roles.

---

## 🏪 Almacén asignado (assigned_store_id)

Pensado para restringir a usuarios con rol `operario` a un almacén. Las restricciones por almacén están planificadas pero no implementadas aún.

---

**Última actualización**: Documentación actualizada tras migración a roles como enum (columna `users.role`).
