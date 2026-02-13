# Arquitectura Multi-Tenant

## ⚠️ Estado de la API
- **v1**: Obsoleta (no documentada)
- **v2**: Versión activa (este documento)

---

## 📋 Visión General

PesquerApp utiliza una arquitectura **multi-tenant** donde una sola instancia de la aplicación sirve a múltiples empresas clientes (tenants), cada una con su propia base de datos aislada.

### Principio Fundamental

**Un tenant = Una empresa = Una base de datos**

Cada empresa cliente tiene:
- Su propia base de datos completamente aislada
- Sus propios usuarios y datos de negocio
- Configuración independiente

---

## 🏗️ Estructura de Bases de Datos

### Base Central (mysql)

La base de datos central contiene solo información administrativa:

**Tabla: `tenants`**
- Catálogo de todas las empresas registradas
- Información de configuración por tenant
- **NO contiene datos de negocio**

**Ubicación**: Base de datos configurada como `mysql` en `config/database.php`

### Bases de Tenants (tenant)

Cada empresa tiene su propia base de datos:

```
Base Central
├── db_empresa1 (tenant)
├── db_empresa2 (tenant)
├── db_empresa3 (tenant)
└── ... (más tenants)
```

**Contenido**: Todas las tablas de negocio (orders, products, users, productions, etc.)

**Ubicación**: Configurada dinámicamente en tiempo de ejecución

---

## 🔄 Flujo de Conexión Dinámica

### 1. Request HTTP

Cada request a la API v2 incluye la cabecera:
```http
X-Tenant: empresa1
```

### 2. Middleware TenantMiddleware

**Archivo**: `app/Http/Middleware/TenantMiddleware.php`

El middleware ejecuta antes de cualquier lógica de negocio:

1. **Extrae el subdominio** de la cabecera `X-Tenant`
2. **Busca el tenant** en la base central:
   ```php
   $tenant = Tenant::where('subdomain', $subdomain)
       ->where('active', true)
       ->first();
   ```
3. **Valida** que el tenant exista y esté activo
4. **Configura la conexión dinámica**:
   ```php
   config(['database.connections.tenant.database' => $tenant->database]);
   DB::purge('tenant');
   DB::reconnect('tenant');
   ```
5. **Almacena el tenant actual** globalmente:
   ```php
   app()->instance('currentTenant', $subdomain);
   ```

### 3. Modelos Usan Conexión Tenant

Todos los modelos de negocio usan el trait `UsesTenantConnection`:

**Archivo**: `app/Traits/UsesTenantConnection.php`
```php
trait UsesTenantConnection
{
    public function initializeUsesTenantConnection()
    {
        $this->setConnection('tenant');
    }
}
```

**Uso en modelos**:
```php
class Production extends Model
{
    use UsesTenantConnection;
    // ...
}
```

Esto asegura que **todos** los queries Eloquent usen la conexión `tenant` configurada dinámicamente.

---

## 📍 Aplicación del Middleware

### Ubicación en Kernel

El middleware `TenantMiddleware` está aplicado en **múltiples lugares** para garantizar ejecución antes de Sanctum:

**Archivo**: `app/Http/Kernel.php`

1. **Middleware global** (línea 48):
   ```php
   protected $middleware = [
       // ...
       \App\Http\Middleware\TenantMiddleware::class,
   ];
   ```

2. **Grupo API** (línea 73):
   ```php
   'api' => [
       // ...
       \App\Http\Middleware\TenantMiddleware::class,
   ],
   ```

3. **En rutas** (`routes/api.php:262`):
   ```php
   Route::group(['prefix' => 'v2', 'middleware' => ['tenant']], function () {
       // ...
   });
   ```

### Prioridad de Ejecución

El middleware tiene prioridad alta en `middlewarePriority` (línea 23):
```php
protected $middlewarePriority = [
    \App\Http\Middleware\TenantMiddleware::class,  // Primero
    \Illuminate\Auth\Middleware\Authenticate::class,
    // ...
];
```

**Razón**: Debe ejecutarse **antes** que Sanctum para que la conexión esté configurada cuando Sanctum intente autenticar usuarios desde la base tenant.

---

## 🗄️ Modelo Tenant

**Archivo**: `app/Models/Tenant.php`

**Tabla**: `tenants` (en base central)

**Campos**:
- `id`: ID único
- `name`: Nombre completo de la empresa
- `subdomain`: Subdominio único (usado en `X-Tenant`)
- `database`: Nombre de la base de datos del tenant
- `active`: Boolean - Si el tenant está activo
- `branding_image_url`: URL de logo/imagen (opcional)
- `created_at`, `updated_at`: Timestamps

**Nota importante**: Este modelo **NO usa** `UsesTenantConnection` porque pertenece a la base central.

---

## 🔧 Configuración de Conexiones

### Configuración Base

**Archivo**: `config/database.php`

**Conexión `mysql` (base central)**:
```php
'mysql' => [
    'driver' => 'mysql',
    'host' => env('DB_HOST', '127.0.0.1'),
    'database' => env('DB_DATABASE', 'forge'),
    // ...
],
```

**Conexión `tenant` (dinámica)**:
```php
'tenant' => [
    'driver' => 'mysql',
    'host' => env('DB_HOST', '127.0.0.1'),
    'database' => '', // Se rellena dinámicamente
    'username' => env('DB_USERNAME', 'forge'),
    'password' => env('DB_PASSWORD', ''),
    // ...
],
```

El campo `database` se completa en tiempo de ejecución por `TenantMiddleware`.

---

## 🚀 Comandos de Migración

### Migrar Todos los Tenants

**Comando**: `php artisan tenants:migrate`

**Archivo**: `app/Console/Commands/MigrateTenants.php`

**Funcionamiento**:
1. Obtiene todos los tenants activos desde la base central
2. Para cada tenant:
   - Configura la conexión `tenant` con su base de datos
   - Ejecuta migraciones del directorio `database/migrations/companies/`
   - Opcionalmente ejecuta seeders

**Opciones**:
- `--fresh`: Elimina y recrea todas las tablas
- `--seed`: Ejecuta seeders después de migrar

**Ejemplo**:
```bash
php artisan tenants:migrate --fresh --seed
```

### Migrar un Tenant Específico

```bash
php artisan tinker
>>> config(['database.connections.tenant.database' => 'db_empresa1']);
>>> DB::purge('tenant');
>>> DB::reconnect('tenant');
>>> exit

php artisan migrate --path=database/migrations/companies --database=tenant
```

---

## 🌱 Comandos de Seeders

### Seedear Todos los Tenants

**Comando**: `php artisan tenants:seed`

**Archivo**: `app/Console/Commands/SeedTenants.php`

**Funcionamiento**: Similar a migraciones, ejecuta seeders en todos los tenants activos.

**Opciones**:
- `--class=SeederClass`: Ejecuta un seeder específico

**Ejemplo**:
```bash
php artisan tenants:seed --class=ProductCategorySeeder
```

---

## 📁 Estructura de Migraciones

### Migraciones de Base Central

**Ubicación**: `database/migrations/`

**Contenido**: Solo tablas administrativas (ej: `tenants`)

**Ejecutar**:
```bash
php artisan migrate
```

### Migraciones de Tenants

**Ubicación**: `database/migrations/companies/`

**Contenido**: Todas las tablas de negocio que deben existir en cada tenant

**Ejecutar**:
```bash
php artisan tenants:migrate
```

### Protección de Migraciones Tenant

Las migraciones en `companies/` deben protegerse para no ejecutarse en la base central:

```php
public function up(): void
{
    if (config('database.default') !== 'tenant') {
        return; // No ejecutar en base central
    }
    
    Schema::create('products', function (Blueprint $table) {
        // ...
    });
}
```

---

## 🔐 Rutas Públicas Excluidas

Algunas rutas no requieren tenant porque son públicas o de configuración:

**En TenantMiddleware** (línea 18-26):
```php
$excluded = [
    'api/v2/public/*',
];

foreach ($excluded as $route) {
    if ($request->is($route)) {
        return $next($request); // Saltar middleware
    }
}
```

**Ejemplo de ruta pública**:
```php
Route::get('v2/public/tenant/{subdomain}', [TenantController::class, 'showBySubdomain']);
```

---

## 🎯 Patrones de Uso

### Crear un Modelo Multi-Tenant

Todos los modelos de negocio deben:
1. Usar el trait `UsesTenantConnection`
2. Estar en migraciones de `companies/`

```php
namespace App\Models;

use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use UsesTenantConnection;
    use HasFactory;
    
    // ...
}
```

### Queries Manuales

Si necesitas hacer queries directos con `DB`:

```php
use Illuminate\Support\Facades\DB;

// Usa automáticamente la conexión tenant configurada
DB::table('products')->get();

// O especifica explícitamente
DB::connection('tenant')->table('products')->get();
```

### Acceder al Tenant Actual

El middleware almacena el tenant actual:

```php
$currentSubdomain = app('currentTenant');

// O desde el modelo Tenant
$tenant = Tenant::where('subdomain', app('currentTenant'))->first();
```

---

## ⚠️ Consideraciones Importantes

### Aislamiento de Datos

- **Nunca** hacer queries que crucen tenants
- **Siempre** usar el trait `UsesTenantConnection` en modelos de negocio
- **Validar** que las migraciones tenant no se ejecuten en base central

### Performance

- Cada request configura la conexión dinámicamente
- El `DB::purge()` y `reconnect()` son necesarios pero tienen costo
- Considerar caching de configuración si hay problemas de performance

### Seguridad

- **Siempre validar** que el tenant esté activo
- **Nunca confiar** en datos del cliente para seleccionar tenant
- El subdominio en `X-Tenant` debe validarse contra la base central

---

## 🔍 Debugging

### Verificar Conexión Actual

```php
// En un controller o tinker
dd(config('database.connections.tenant.database'));
```

### Logs del Middleware

El middleware registra cada cambio de conexión:

```php
Log::info('🔁 Conexión cambiada dinámicamente a tenant', [
    'subdomain' => $subdomain,
    'database' => $tenant->database,
]);
```

Revisar logs en `storage/logs/laravel.log`.

---

## Observaciones Críticas y Mejoras Recomendadas

### ⚠️ Middleware Aplicado Múltiples Veces

1. **Middleware en Múltiples Ubicaciones** (`app/Http/Kernel.php`)
   - `TenantMiddleware` está en: middleware global, grupo API, y en rutas
   - **Líneas**: 48, 73, y en `routes/api.php:262`
   - **Problema**: Puede ejecutarse múltiples veces en el mismo request
   - **Recomendación**: 
     - Documentar claramente por qué está en múltiples lugares
     - O consolidar en un solo lugar si es posible
     - O agregar guard para evitar ejecución duplicada

### ⚠️ Performance: Purge y Reconnect en Cada Request

2. **DB::purge() y reconnect() en Cada Request** (`app/Http/Middleware/TenantMiddleware.php:50-51`)
   - Se ejecutan en cada request HTTP
   - **Líneas**: 50-51
   - **Problema**: Overhead de conexión en cada request
   - **Recomendación**: 
     - Verificar si realmente es necesario purgar
     - O cachear la conexión si el tenant no ha cambiado
     - Medir impacto en producción antes de optimizar

### ⚠️ Falta de Validación de Formato de Subdomain

3. **No Valida Formato de Subdomain** (`app/Http/Middleware/TenantMiddleware.php:29`)
   - Solo verifica que exista, no valida formato
   - **Líneas**: 29-33
   - **Problema**: Pueden pasarse valores maliciosos o inválidos
   - **Recomendación**: Agregar validación de formato (solo alfanuméricos, guiones, etc.)

### ⚠️ Manejo de Errores Genérico

4. **Mensajes de Error Poco Específicos** (`app/Http/Middleware/TenantMiddleware.php:32,39`)
   - "Tenant not specified" y "Tenant not found or inactive" son genéricos
   - **Líneas**: 32, 39
   - **Problema**: No ayuda a debuggear en producción
   - **Recomendación**: 
     - Logs más detallados antes de retornar error
     - O mensajes más informativos (sin exponer seguridad)

### ⚠️ Falta de Rate Limiting por Tenant

5. **No Hay Rate Limiting por Tenant** (`app/Http/Middleware/TenantMiddleware.php`)
   - No limita requests por tenant
   - **Problema**: Un tenant puede sobrecargar el sistema
   - **Recomendación**: Considerar rate limiting por tenant si es necesario

### ⚠️ Tenant Model Sin UsesTenantConnection (Correcto pero Documentar)

6. **Tenant Model No Usa Trait** (`app/Models/Tenant.php`)
   - No usa `UsesTenantConnection` (correcto, pertenece a base central)
   - **Estado**: Correcto, pero importante documentar
   - **Recomendación**: Ya está documentado, mantener así

### ⚠️ Falta de Validación en Comandos

7. **Comandos No Validan Existencia de Bases** (`app/Console/Commands/MigrateTenants.php`)
   - No verifica que la base de datos exista antes de migrar
   - **Líneas**: 24-28
   - **Problema**: Puede fallar silenciosamente o con error poco claro
   - **Recomendación**: Validar existencia de base antes de configurar conexión

### ⚠️ Seeders Sin Validación de Tenant

8. **Seeders No Validan Configuración** (`app/Console/Commands/SeedTenants.php`)
   - Similar a migraciones, no valida que todo esté configurado
   - **Líneas**: 24-31
   - **Recomendación**: Agregar validaciones defensivas

---

**Última actualización**: Documentación generada desde código fuente en fecha de generación.

