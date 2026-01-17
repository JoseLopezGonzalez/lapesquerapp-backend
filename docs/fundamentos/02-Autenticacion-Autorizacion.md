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

1. **Login**: Usuario envía credenciales → Recibe token
2. **Requests**: Cliente incluye token en header `Authorization`
3. **Validación**: Sanctum valida token y autentica usuario
4. **Logout**: Token se invalida

---

## 📡 Controlador de Autenticación

**Archivo**: `app/Http/Controllers/v2/AuthController.php`

### `login(Request $request)` - Iniciar Sesión

**Ruta**: `POST /v2/login`

**Request**:
```http
POST /v2/login
Content-Type: application/json
X-Tenant: empresa1

{
    "email": "usuario@empresa.com",
    "password": "contraseña"
}
```

**Validación**:
```php
[
    'email' => 'required|email',
    'password' => 'required',
]
```

**Proceso**:
1. Busca usuario por email en la base tenant
2. Verifica contraseña con `Hash::check()`
3. Crea token con `$user->createToken('auth_token')`
4. Retorna token y datos del usuario

**Respuesta exitosa** (200):
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
        "roles": ["manager"]
    }
}
```

**Respuesta error** (401):
```json
{
    "message": "Las credenciales proporcionadas son inválidas."
}
```

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
2. Elimina todos los tokens: `$user->tokens()->delete()`

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
    // ... todos los campos del usuario
}
```

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

### Modelo Role

**Archivo**: `app/Models/Role.php`

**Tabla**: `roles` (en base tenant)

**Campos**:
- `id`: ID único
- `name`: Nombre del rol (string, único)
- `description`: Descripción del rol (opcional)

**Relación**:
```php
public function users()
{
    return $this->belongsToMany(User::class, 'role_user');
}
```

### Roles Disponibles

1. **`superuser`**: Acceso total, gestión técnica
2. **`manager`**: Gestión y administración
3. **`admin`**: Administración de datos
4. **`store_operator`**: Operador de almacén (acceso limitado)

### Relación Usuario-Rol

**Tabla pivote**: `role_user`
- `user_id`: FK a `users`
- `role_id`: FK a `roles`

**Relación en User**:
```php
public function roles()
{
    return $this->belongsToMany(Role::class, 'role_user');
}
```

Un usuario puede tener **múltiples roles**.

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
Route::middleware(['role:superuser'])->group(function () {
    // Solo superuser
});

Route::middleware(['role:superuser,manager,admin'])->group(function () {
    // Cualquiera de estos roles
});
```

### Métodos en User Model

**Archivo**: `app/Models/User.php`

#### `hasRole($role)` - Verificar Rol Específico
```php
public function hasRole($role)
{
    if (is_array($role)) {
        return $this->roles->whereIn('name', $role)->isNotEmpty();
    }
    return $this->roles->where('name', $role)->isNotEmpty();
}
```

**Uso**:
```php
if ($user->hasRole('superuser')) {
    // ...
}

if ($user->hasRole(['manager', 'admin'])) {
    // ...
}
```

#### `hasAnyRole(array $roles)` - Verificar Cualquier Rol
```php
public function hasAnyRole(array $roles)
{
    return $this->roles()->whereIn('name', $roles)->exists();
}
```

**Uso**:
```php
if ($user->hasAnyRole(['superuser', 'manager'])) {
    // ...
}
```

#### `assignRole($roleName)` - Asignar Rol
```php
public function assignRole($roleName)
{
    $role = Role::where('name', $roleName)->first();
    
    if ($role && !$this->hasRole($roleName)) {
        $this->roles()->attach($role);
    }
}
```

#### `removeRole($roleName)` - Eliminar Rol
```php
public function removeRole($roleName)
{
    $role = Role::where('name', $roleName)->first();
    
    if ($role && $this->hasRole($roleName)) {
        $this->roles()->detach($role);
    }
}
```

---

## 📍 Aplicación en Rutas

### Estructura de Protección

En `routes/api.php`, las rutas v2 están organizadas por roles:

```php
Route::group(['prefix' => 'v2', 'middleware' => ['tenant']], function () {
    // Rutas públicas
    Route::post('login', [AuthController::class, 'login']);
    
    // Rutas protegidas
    Route::middleware(['auth:sanctum'])->group(function () {
        // Solo superuser
        Route::middleware(['role:superuser'])->group(function () {
            Route::apiResource('users', UserController::class);
            Route::apiResource('roles', RoleController::class);
        });
        
        // Múltiples roles
        Route::middleware(['role:superuser,manager,admin,store_operator'])->group(function () {
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

**Ruta**: `GET /v2/sessions` (requiere `role:superuser`)

Retorna todos los tokens activos del sistema (todos los usuarios).

### Eliminar Sesión

**Ruta**: `DELETE /v2/sessions/{id}` (requiere `role:superuser`)

Elimina un token específico, cerrando la sesión del usuario.

---

## 🔐 Seguridad

### Validación de Contraseñas

Las contraseñas se almacenan hasheadas usando `bcrypt`:
```php
'password' => Hash::make($password)
```

La verificación usa `Hash::check()`:
```php
Hash::check($plainPassword, $hashedPassword)
```

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
# 1. Login
curl -X POST https://api.pesquerapp.es/v2/login \
  -H "Content-Type: application/json" \
  -H "X-Tenant: empresa1" \
  -d '{"email":"admin@empresa.com","password":"secret"}'

# Respuesta:
# {
#   "access_token": "1|abc123...",
#   "token_type": "Bearer",
#   "user": {...}
# }

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
    
    if ($user->hasRole('superuser')) {
        // Acceso completo
        return User::all();
    } elseif ($user->hasRole('manager')) {
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

**Solución**: Verificar roles del usuario con `$user->roles` o asignar rol apropiado.

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

### ⚠️ hasAnyRole Usa Query

6. **hasAnyRole Hace Query Directo** (`app/Models/User.php:108-111`)
   - `hasAnyRole()` usa `exists()` que ejecuta query
   - **Líneas**: 110
   - **Problema**: Si roles ya están cargados, hace query innecesaria
   - **Recomendación**: Optimizar para usar colección si está cargada:
     ```php
     if ($this->relationLoaded('roles')) {
         return $this->roles->whereIn('name', $roles)->isNotEmpty();
     }
     return $this->roles()->whereIn('name', $roles)->exists();
     ```

### ⚠️ RoleMiddleware No Carga Relación

7. **RoleMiddleware No Eager Load Roles** (`app/Http/Middleware/RoleMiddleware.php:23-26`)
   - `$request->user()` puede no tener roles cargados
   - **Líneas**: 23-26
   - **Problema**: Puede causar N+1 queries si se accede múltiples veces
   - **Recomendación**: Cargar roles en middleware o en `Authenticate` middleware

### ⚠️ Falta de Validación en assignRole

8. **assignRole No Valida Rol Válido** (`app/Models/User.php:84-91`)
   - Si el rol no existe, simplemente no asigna (silencioso)
   - **Líneas**: 84-91
   - **Problema**: Puede ser confuso si se intenta asignar rol inexistente
   - **Recomendación**: 
     - Lanzar excepción si rol no existe
     - O retornar boolean indicando éxito/fallo

### ⚠️ Expiración de Tokens Fija

9. **Expiración Hardcodeada** (`config/sanctum.php:47`)
   - 30 días fijos para todos los tokens
   - **Líneas**: 47
   - **Problema**: No permite diferentes tiempos para diferentes tipos de usuarios
   - **Recomendación**: 
     - Permitir configuración por tipo de token
     - O crear tokens con diferentes expiraciones según necesidad

### ⚠️ Falta de Refresh Tokens

10. **No Hay Sistema de Refresh Tokens** (`app/Http/Controllers/v2/AuthController.php`)
    - Solo hay access tokens, no refresh tokens
    - **Problema**: Usuario debe hacer login cada 30 días
    - **Recomendación**: Implementar sistema de refresh tokens si es necesario para UX

---

**Última actualización**: Documentación generada desde código fuente en fecha de generación.

