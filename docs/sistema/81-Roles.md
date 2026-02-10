# Sistema - Roles

## ⚠️ Estado de la API
- **v1**: Obsoleta (no documentada)
- **v2**: Versión activa (este documento)

---

## 📋 Visión General

Los **roles** en PesquerApp están **fijados en código** (enum), no en base de datos. Forman parte del diseño del producto y no son editables por el usuario final.

**Archivo**: `app/Enums/Role.php`

**Autorización**: Los roles se usan con `RoleMiddleware` para controlar el acceso a rutas. Cada usuario tiene un único rol almacenado en la columna `users.role`.

---

## 🗄️ Dónde se guarda el rol

El rol del usuario se almacena en la tabla **`users`**, columna **`role`** (string, valores del enum).

- **Migración**: `database/migrations/companies/2026_02_10_120000_migrate_roles_to_enum_on_users.php`
- No existen tablas `roles` ni `role_user`; fueron eliminadas en la migración a roles como enum.

---

## 📦 Enum Role

**Namespace**: `App\Enums\Role`

### Valores (casos del enum)

| Valor (string)   | Etiqueta      | Descripción breve                    |
|------------------|---------------|--------------------------------------|
| `tecnico`        | Técnico       | Super-superuser, soporte y configuración |
| `administrador`  | Administrador | Superuser de la empresa              |
| `direccion`      | Dirección     | Solo lectura y análisis              |
| `administracion` | Administración| Administración                       |
| `comercial`      | Comercial     | Comercial                            |
| `operario`       | Operario      | Operario                             |

### Métodos útiles

- **`Role::values()`**: Array de strings válidos para validación.
- **`Role::optionsForApi()`**: Array `[{ "id": "tecnico", "name": "Técnico" }, ...]` para selects en frontend.
- **`$case->label()`**: Etiqueta legible del caso.
- **`Role::fromLegacyName(string)`**: Mapeo de nombres antiguos (solo para migración/legacy).

---

## 📡 API

### Único endpoint: Opciones de roles

**Ruta**: `GET /v2/roles/options`  
**Permiso**: `role:tecnico`

**Respuesta** (200):
```json
[
  { "id": "tecnico", "name": "Técnico" },
  { "id": "administrador", "name": "Administrador" },
  { "id": "direccion", "name": "Dirección" },
  { "id": "administracion", "name": "Administración" },
  { "id": "comercial", "name": "Comercial" },
  { "id": "operario", "name": "Operario" }
]
```

**Uso**: Lista para desplegables al crear/editar usuarios. El valor a enviar en `user.role` es el `id` (string).

### Eliminados (ya no existen)

- `GET /v2/roles` — Listar roles
- `POST /v2/roles` — Crear rol
- `GET /v2/roles/{id}` — Mostrar rol
- `PUT /v2/roles/{id}` — Actualizar rol
- `DELETE /v2/roles/{id}` — Eliminar rol

Los roles no se crean ni modifican desde la API; son fijos en código.

---

## 🛡️ Uso en autorización

### RoleMiddleware

**Archivo**: `app/Http/Middleware/RoleMiddleware.php`

**Uso en rutas**:
```php
Route::middleware(['role:tecnico'])->group(function () {
    // Solo técnico
});

Route::middleware(['role:tecnico,administrador,administracion'])->group(function () {
    // Cualquiera de estos roles
});
```

El middleware comprueba que `$user->role` esté en la lista de roles indicada.

---

## 📝 Seeders

**RoleSeeder** (`database/seeders/RoleSeeder.php`): Ya no crea datos; los roles viven en el enum. El seeder se mantiene vacío para no romper la cadena de seeders (TenantDatabaseSeeder, DatabaseSeeder).

---

**Última actualización**: Documentación actualizada tras migración a roles como enum (Opción A).
