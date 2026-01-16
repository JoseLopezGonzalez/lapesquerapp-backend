# Sistema - Sesiones (Sessions)

## ⚠️ Estado de la API
- **v1**: Obsoleta (no documentada)
- **v2**: Versión activa (este documento)

---

## 📋 Visión General

El `SessionController` gestiona las **sesiones activas** de usuarios basadas en los tokens de Laravel Sanctum. Las sesiones corresponden a los tokens de autenticación almacenados en la tabla `personal_access_tokens`.

**Archivo del controlador**: `app/Http/Controllers/v2/SessionController.php`

**Nota**: Las sesiones se gestionan a través de los tokens de Sanctum. Cada token representa una sesión activa.

---

## 🗄️ Estructura de Base de Datos

### Tabla: `personal_access_tokens`

**Migración**: `database/migrations/companies/2019_12_14_000001_create_personal_access_tokens_table.php`

**Modelo personalizado**: `app/Sanctum/PersonalAccessToken.php` (usa `UsesTenantConnection`)

**Campos**:

| Campo | Tipo | Nullable | Descripción |
|-------|------|----------|-------------|
| `id` | bigint | NO | ID único del token |
| `tokenable_type` | string | NO | Tipo de modelo (normalmente `App\Models\User`) |
| `tokenable_id` | bigint | NO | ID del usuario (polimórfico) |
| `name` | string | NO | Nombre del token (ej: "auth_token") |
| `token` | string(64) | NO | Hash del token - **UNIQUE** |
| `abilities` | text | YES | Permisos del token (normalmente null) |
| `last_used_at` | timestamp | YES | Última vez que se usó el token |
| `expires_at` | timestamp | YES | Fecha de expiración del token |
| `created_at` | timestamp | NO | Fecha de creación |
| `updated_at` | timestamp | NO | Fecha de última actualización |

**Índices**:
- `id` (primary key)
- `token` (unique)

**Nota**: Los campos `ip_address`, `platform`, y `browser` **NO existen** en esta tabla. El controlador y el resource no referencian estos campos.

---

## 📦 Modelo Personalizado

**Archivo**: `app/Sanctum/PersonalAccessToken.php`

Extiende `Laravel\Sanctum\PersonalAccessToken` y añade:

```php
use UsesTenantConnection;
```

Permite que los tokens se almacenen en la base de datos tenant.

**Configuración**: `config/sanctum.php` (línea 66)
```php
'personal_access_token_model' => App\Sanctum\PersonalAccessToken::class,
```

---

## 📡 Controlador

**Archivo**: `app/Http/Controllers/v2/SessionController.php`

**Permisos requeridos**: `role:superuser` (solo superusuarios pueden gestionar sesiones)

### Métodos del Controlador

#### `index(Request $request)` - Listar Sesiones
```php
GET /v2/sessions
```

**Eager Loading**: `tokenable` (carga relación con `User`)

**Orden**: Por `last_used_at` descendente (más reciente primero)

**Filtros disponibles** (query parameters):
- `user_id`: Filtrar por ID de usuario

**Query parameters**: `per_page` (default: 10)

**Respuesta**: Collection paginada de `SessionResource`

#### `destroy($id)` - Cerrar Sesión
```php
DELETE /v2/sessions/{id}
```

**Comportamiento**:
- Busca el token por ID
- Elimina el token (cierra la sesión)
- Retorna mensaje de éxito

**Respuestas**:
- **200**: `{"message": "Sesión cerrada correctamente"}`
- **404**: `{"message": "Sesión no encontrada"}`

---

## 📄 API Resource

**Archivo**: `app/Http/Resources/v2/SessionResource.php`

**Campos expuestos**:
```json
{
    "id": 1,
    "user_id": 5,
    "user_name": "Juan Pérez",
    "email": "juan@example.com",
    "last_used_at": "2025-01-15 14:30:00",
    "created_at": "2025-01-10 10:00:00",
    "expires_at": "2025-02-10 10:00:00"
}
```

**Notas**:
- `user_name` y `email` se obtienen desde `tokenable` (relación con User)
- Si el usuario no existe, muestra "Desconocido"

---

## 🔐 Permisos y Autenticación

**Middleware requerido**:
- `auth:sanctum`: Autenticación requerida
- `role:superuser`: Solo superusuarios pueden gestionar sesiones

**Rutas**: Todas bajo `/v2/sessions/*`

**Rutas definidas**:
- `GET /v2/sessions` - Listar sesiones
- `DELETE /v2/sessions/{id}` - Cerrar sesión

**Rutas NO disponibles** (solo `index` y `destroy`):
- `POST /v2/sessions` - No existe (sesiones se crean en login)
- `GET /v2/sessions/{id}` - No existe
- `PUT /v2/sessions/{id}` - No existe

---

## 🔗 Relación con Autenticación

### Creación de Sesiones

Las sesiones se crean automáticamente cuando un usuario hace login:

**Archivo**: `app/Http/Controllers/v2/AuthController.php`
```php
$token = $user->createToken('auth_token')->plainTextToken;
```

Esto crea un registro en `personal_access_tokens`.

### Cierre de Sesiones

**Individual**: `DELETE /v2/sessions/{id}` (este controlador)

**Todas las sesiones del usuario**: `POST /v2/logout` (AuthController)
```php
$request->user()->tokens()->delete(); // Elimina todos los tokens del usuario
```

---

## ⏰ Expiración de Tokens

**Configuración**: `config/sanctum.php`
```php
'expiration' => 43200, // 30 días (en minutos)
```

Los tokens expiran después de **30 días** (43200 minutos). Después de esto, el usuario debe iniciar sesión nuevamente.

El campo `expires_at` en la tabla almacena la fecha de expiración calculada.

---

## Observaciones Críticas y Mejoras Recomendadas

### ✅ Filtros Actualizados

1. **Filtros limpios** (`app/Http/Controllers/v2/SessionController.php`)
   - Solo se filtra por `user_id`
   - Los filtros por `ip_address`, `platform`, y `browser` fueron eliminados (estos campos no existen en la tabla)

### ⚠️ Sin Método show()

2. **No Hay Método show()** (`app/Http/Controllers/v2/SessionController.php`)
   - No se puede obtener una sesión específica
   - **Problema**: Funcionalidad limitada
   - **Recomendación**: Agregar `show()` si se necesita

### ⚠️ Sin Información de Dispositivo/IP

3. **No Se Almacena Info de Sesión** (`database/migrations/companies/2019_12_14_000001_create_personal_access_tokens_table.php`)
   - No hay campos para IP, plataforma, navegador
   - **Problema**: No se puede rastrear desde dónde se inició la sesión
   - **Recomendación**: Agregar campos si se necesita seguridad/tracking

### ⚠️ Import No Usado

4. **PersonalAccessToken Importado Pero No Usado** (`app/Http/Controllers/v2/SessionController.php:7`)
   - Importa `App\Models\PersonalAccessToken` pero no lo usa
   - **Líneas**: 7
   - **Problema**: Import innecesario
   - **Recomendación**: Eliminar import

### ⚠️ Sin Validación de Permisos en destroy()

5. **destroy() No Valida Permisos** (`app/Http/Controllers/v2/SessionController.php:48-59`)
   - Cualquier superuser puede cerrar cualquier sesión
   - **Líneas**: 48-59
   - **Estado**: Puede ser intencional (admin puede cerrar cualquier sesión)
   - **Recomendación**: Documentar comportamiento esperado

### ⚠️ Sin Filtro por Tenant

6. **No Filtra por Tenant Explícitamente** (`app/Http/Controllers/v2/SessionController.php:19`)
   - Depende de `UsesTenantConnection` en el modelo
   - **Estado**: Funciona pero podría ser más explícito
   - **Recomendación**: Verificar que funcione correctamente con multi-tenant

### ⚠️ Sin Paginación por Defecto Documentada

7. **per_page vs perPage** (`app/Http/Controllers/v2/SessionController.php:37`)
   - Usa `per_page` pero otros controladores usan `perPage`
   - **Líneas**: 37
   - **Problema**: Inconsistencia en API
   - **Recomendación**: Usar `perPage` para consistencia

---

**Última actualización**: Documentación generada desde código fuente en fecha de generación.

