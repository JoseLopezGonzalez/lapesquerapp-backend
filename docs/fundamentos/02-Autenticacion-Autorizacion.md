# Autenticación y Autorización

## ⚠️ Estado de la API
- **v1**: Obsoleta (no documentada)
- **v2**: Versión activa (este documento)

---

## 📋 Visión General

El sistema de autenticación y autorización de PesquerApp utiliza **Laravel Sanctum** para autenticación por tokens y un sistema de **roles basado en permisos** para controlar el acceso a diferentes endpoints.

---

## 🔐 Autenticación con Laravel Sanctum

### Concepto

Laravel Sanctum proporciona autenticación por **tokens Bearer** para APIs. Cada usuario autenticado recibe un token que debe incluirse en todas las requests subsiguientes.

### Flujo de Autenticación

**No hay login con contraseña.** El acceso es solo por **Magic Link** o **OTP** por email:

1. **Solicitar acceso**: El usuario introduce su email; el frontend llama a `POST /v2/auth/magic-link/request` o `POST /v2/auth/otp/request`.
2. **Canjear**: Tras el clic en el enlace del correo o al introducir el código, el frontend llama a `POST /v2/auth/magic-link/verify` o `POST /v2/auth/otp/verify` y recibe `access_token` y `user`.
3. **Requests**: El cliente incluye el token en el header `Authorization: Bearer {token}` y `X-Tenant`.
4. **Logout**: `POST /v2/logout` invalida el token actual.

**Documentación detallada de auth:** ver `docs/frontend/Guia-Frontend-Actualizaciones-Auth-y-Usuarios.md` y `docs/sistema/89-Auth-Contrasenas-Eliminadas.md`.

---

## 📡 Controlador de Autenticación

**Archivo**: `app/Http/Controllers/v2/AuthController.php`

### `login(Request $request)` — Obsoleto

**Ruta**: `POST /v2/login`  
**Ya no permite acceso con contraseña.** Cualquier petición devuelve **400** con un mensaje indicando que el acceso es solo por enlace o código (magic link u OTP). No usar este endpoint para iniciar sesión.

### Obtener token (Magic Link u OTP)

- **Solicitar enlace:** `POST /v2/auth/magic-link/request` con `{ "email": "..." }`.
- **Canjear enlace:** `POST /v2/auth/magic-link/verify` con `{ "token": "..." }` (el token viene en la URL del correo).
- **Solicitar código:** `POST /v2/auth/otp/request` con `{ "email": "..." }`.
- **Canjear código:** `POST /v2/auth/otp/verify` con `{ "email": "...", "code": "123456" }`.

**Respuesta exitosa** (200) de verify:
```json
{
    "access_token": "1|xxxxxxxxxxxxx...",
    "token_type": "Bearer",
    "user": {
        "id": 1,
        "name": "Juan Pérez",
        "email": "usuario@empresa.com",
        "assignedStoreId": 5,
        "companyName": "Empresa S.L.",
        "companyLogoUrl": "https://...",
        "role": "administrador"
    }
}
```

El campo `role` es un **string** (uno de: `tecnico`, `administrador`, `direccion`, `administracion`, `comercial`, `operario`).

### `logout(Request $request)` - Cerrar Sesión

**Ruta**: `POST /v2/logout` (requiere `auth:sanctum`)

**Request**:
```http
POST /v2/logout
Authorization: Bearer 1|xxxxxxxxxxxxx...
X-Tenant: empresa1
```

**Proceso**:
1. Obtiene usuario autenticado: `$request->user()`
2. Elimina el token actual: `$user->currentAccessToken()->delete()`

**Respuesta**:
```json
{
    "message": "Sesión cerrada correctamente"
}
```

### `me(Request $request)` - Usuario Autenticado

**Ruta**: `GET /v2/me` (requiere `auth:sanctum`)

**Request**:
```http
GET /v2/me
Authorization: Bearer 1|xxxxxxxxxxxxx...
X-Tenant: empresa1
```

**Respuesta**:
```json
{
    "id": 1,
    "name": "Juan Pérez",
    "email": "usuario@empresa.com",
    "assigned_store_id": 5,
    "company_name": "Empresa S.L.",
    "company_logo_url": "https://...",
    "active": true,
    "role": "administrador",
    "created_at": "...",
    "updated_at": "..."
}
```

El campo `role` es un string (valor del enum); consistente con la respuesta de login.

---

## 🔑 Configuración de Sanctum

**Archivo**: `config/sanctum.php`

### Expiración de Tokens

```php
'expiration' => 43200, // 30 días (en minutos)
```

Los tokens expiran después de **30 días** (43200 minutos). Después de esto, el usuario debe iniciar sesión nuevamente.

### Stateful Domains

```php
'stateful' => [],
```

Para APIs puras (no SPAs), se deja vacío. Si en el futuro se usa Sanctum con SPAs, agregar dominios aquí.

### Guards

```php
'guard' => ['web'],
```

Sanctum verifica estos guards antes de usar tokens Bearer.

---

## 👥 Sistema de Roles

Los roles están **fijados en código** (enum), no en base de datos. Cada usuario tiene **un único rol** en la columna `users.role`.

**Archivo**: `app/Enums\Role`

### Roles disponibles (valores del enum)

| Valor | Descripción breve |
|-------|-------------------|
| `tecnico` | Super-superuser, soporte y configuración |
| `administrador` | Superuser de la empresa |
| `direccion` | Solo lectura y análisis |
| `administracion` | Administración |
| `comercial` | Comercial |
| `operario` | Operario |

### Rol en el usuario

El modelo `User` tiene el atributo **`role`** (string). No existe tabla `roles` ni `role_user`; el rol se guarda directamente en `users.role`.

---

## 🛡️ Autorización por Roles

### Middleware RoleMiddleware

**Archivo**: `app/Http/Middleware/RoleMiddleware.php`

**Registro**: `app/Http/Kernel.php` (línea 100)
```php
'role' => \App\Http\Middleware\RoleMiddleware::class,
```

**Funcionamiento**:
```php
public function handle(Request $request, Closure $next, ...$roles)
{
    $user = $request->user();
    
    if (!$user || !$user->hasAnyRole($roles)) {
        return response()->json([
            'message' => 'No tienes permiso para acceder a esta ruta.'
        ], 403);
    }
    
    return $next($request);
}
```

**Uso en rutas**:
```php
Route::middleware(['role:tecnico'])->group(function () {
    // Solo técnico
});

Route::middleware(['role:tecnico,administrador,administracion'])->group(function () {
    // Cualquiera de estos roles
});
```

Los valores deben coincidir con el enum: `tecnico`, `administrador`, `direccion`, `administracion`, `comercial`, `operario`.

### Métodos en User Model

**Archivo**: `app/Models/User.php`

#### `hasRole($role)` - Verificar rol
Comprueba si `$user->role` coincide con el string o con alguno del array.
```php
$user->hasRole('tecnico');              // bool
$user->hasRole(['administrador', 'tecnico']); // bool
```

#### `hasAnyRole(array $roles)` - Verificar cualquiera de varios roles
```php
$user->hasAnyRole(['tecnico', 'administrador']); // bool
```

El rol se asigna editando el atributo `role` del usuario (p. ej. en UserController al crear/actualizar).

---

## 📍 Aplicación en Rutas

### Estructura de Protección

En `routes/api.php`, las rutas v2 están organizadas por roles (valores del enum):

```php
Route::group(['prefix' => 'v2', 'middleware' => ['tenant']], function () {
    Route::post('login', [AuthController::class, 'login']);
    
    Route::middleware(['auth:sanctum'])->group(function () {
        // Solo técnico (gestión usuarios, roles/options, etc.)
        Route::middleware(['role:tecnico'])->group(function () {
            Route::apiResource('users', UserController::class);
            Route::get('roles/options', [RoleController::class, 'options']);
        });
        
        // Múltiples roles
        Route::middleware(['role:tecnico,administrador,direccion,administracion,comercial,operario'])->group(function () {
            Route::apiResource('orders', OrderController::class);
            // ...
        });
    });
});
```

### Orden de Middleware

1. **tenant**: Configura conexión a base de datos
2. **auth:sanctum**: Autentica usuario
3. **role:xxx**: Verifica permisos

---

## 🔍 Gestión de Sesiones

### Modelo de Sesión

**Controlador**: `app/Http/Controllers/v2/SessionController.php`

**Concepto**: Las "sesiones" en este contexto son los tokens activos de Sanctum.

### Listar Sesiones Activas

**Ruta**: `GET /v2/sessions` (requiere `role:tecnico`)

Retorna todos los tokens activos del sistema (todos los usuarios).

### Eliminar Sesión

**Ruta**: `DELETE /v2/sessions/{id}` (requiere `role:tecnico`)

Elimina un token específico, cerrando la sesión del usuario.

---

## 🔐 Seguridad

**No se usan contraseñas de usuario.** El acceso es solo por magic link u OTP; no hay almacenamiento ni verificación de contraseña en el backend.

### Tokens Únicos

Cada token generado es único e irrepetible. Si se revoca (logout), no puede reutilizarse.

### Expiración Automática

Los tokens expiran después de 30 días. El frontend debe manejar la renovación o solicitar nuevo login.

### Aislamiento por Tenant

- Los usuarios solo existen en su base de datos tenant
- No pueden autenticarse en otros tenants
- El middleware `tenant` garantiza que la autenticación ocurra en la base correcta

---

## 📝 Ejemplos de Uso

### Flujo Completo de Autenticación

```bash
# 1. Solicitar magic link (o usar OTP: auth/otp/request y auth/otp/verify)
curl -X POST https://api.pesquerapp.es/api/v2/auth/magic-link/request \
  -H "Content-Type: application/json" \
  -H "X-Tenant: empresa1" \
  -d '{"email":"admin@empresa.com"}'

# El usuario recibe un correo con un enlace. Al hacer clic, el frontend llama a:
# POST /api/v2/auth/magic-link/verify con { "token": "..." }
# y recibe access_token y user.

# 2. Request Autenticada
curl -X GET https://api.pesquerapp.es/v2/orders \
  -H "Authorization: Bearer 1|abc123..." \
  -H "X-Tenant: empresa1"

# 3. Logout
curl -X POST https://api.pesquerapp.es/v2/logout \
  -H "Authorization: Bearer 1|abc123..." \
  -H "X-Tenant: empresa1"
```

### Verificar Rol en Código

```php
// En un controller
public function index(Request $request)
{
    $user = $request->user();
    
    if ($user->hasRole('tecnico')) {
        // Acceso completo
        return User::all();
    } elseif ($user->hasRole('administrador')) {
        // Acceso limitado
        return User::where('store_id', $user->assigned_store_id)->get();
    }
    
    abort(403);
}
```

---

## 🚨 Errores Comunes

### 401 Unauthorized

**Causa**: Token inválido, expirado o ausente

**Solución**: Verificar que el token esté en el header `Authorization: Bearer {token}` y que no haya expirado.

### 403 Forbidden

**Causa**: Usuario no tiene el rol requerido

**Solución**: Verificar el rol con `$user->role` o asignar el rol apropiado al usuario.

### 400 Bad Request - Tenant not specified

**Causa**: Falta cabecera `X-Tenant`

**Solución**: Incluir `X-Tenant: subdominio` en todas las requests.

---

## Observaciones Críticas y Mejoras Recomendadas

### ⚠️ Manejo de Errores Genérico

1. **Mensaje de Error Genérico en Login** (`app/Http/Controllers/v2/AuthController.php:28-31`)
   - "Las credenciales proporcionadas son inválidas" no diferencia entre usuario inexistente y contraseña incorrecta
   - **Líneas**: 28-31
   - **Problema**: Puede facilitar enumeración de usuarios
   - **Recomendación**: 
     - Mantener mensaje genérico por seguridad (correcto actualmente)
     - O agregar rate limiting por IP para prevenir ataques de fuerza bruta

### ⚠️ Falta de Rate Limiting

2. **No Hay Rate Limiting en Login** (`app/Http/Controllers/v2/AuthController.php:15-51`)
   - No limita intentos de login por IP o email
   - **Líneas**: 15-51
   - **Problema**: Vulnerable a ataques de fuerza bruta
   - **Recomendación**: Agregar middleware `throttle` a ruta de login:
     ```php
     Route::post('login', [AuthController::class, 'login'])
         ->middleware('throttle:5,1'); // 5 intentos por minuto
     ```

### ⚠️ Tokens Sin Revocación Selectiva

3. **Logout Elimina Todos los Tokens** (`app/Http/Controllers/v2/AuthController.php:54-59`)
   - `logout()` elimina TODOS los tokens del usuario
   - **Líneas**: 56
   - **Problema**: Cierra todas las sesiones (web, móvil, etc.) cuando solo se quiere cerrar una
   - **Recomendación**: 
     - Implementar logout selectivo: `$request->user()->currentAccessToken()->delete()`
     - O agregar endpoint para listar y revocar tokens específicos

### ⚠️ Falta Validación de Estado del Usuario

4. **No Valida Usuario Activo** (`app/Http/Controllers/v2/AuthController.php:24-31`)
   - No verifica si el usuario está activo antes de autenticar
   - **Líneas**: 24-31
   - **Problema**: Usuarios desactivados pueden autenticarse
   - **Recomendación**: Agregar campo `active` a usuarios y validar en login

### ⚠️ Información del Usuario Expuesta

5. **Respuesta de Login Expone Datos Sensibles** (`app/Http/Controllers/v2/AuthController.php:38-50`)
   - Incluye `assignedStoreId`, `companyName`, etc. en respuesta de login
   - **Líneas**: 38-50
   - **Problema**: Puede exponer información innecesaria
   - **Recomendación**: 
     - Usar API Resource para controlar qué se expone
     - O crear método `toPublicArray()` en User model

### ⚠️ Expiración de Tokens Fija

6. **Expiración Hardcodeada** (`config/sanctum.php:47`)
   - 30 días fijos para todos los tokens
   - **Líneas**: 47
   - **Problema**: No permite diferentes tiempos para diferentes tipos de usuarios
   - **Recomendación**: 
     - Permitir configuración por tipo de token
     - O crear tokens con diferentes expiraciones según necesidad

### ⚠️ Falta de Refresh Tokens

7. **No Hay Sistema de Refresh Tokens** (`app/Http/Controllers/v2/AuthController.php`)
    - Solo hay access tokens, no refresh tokens
    - **Problema**: Usuario debe hacer login cada 30 días
    - **Recomendación**: Implementar sistema de refresh tokens si es necesario para UX

---

**Última actualización**: Documentación actualizada tras migración a roles como enum (user.role).

