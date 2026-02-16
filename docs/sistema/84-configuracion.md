# Sistema - Configuración (Settings)

## ⚠️ Estado de la API
- **v1**: Obsoleta (no documentada)
- **v2**: Versión activa (este documento)

---

## 📋 Visión General

El sistema de configuración permite almacenar **configuraciones específicas de cada tenant** en la base de datos. A diferencia de otros módulos, **no existe un modelo Eloquent**; se utiliza directamente Query Builder sobre la tabla `settings`.

**Controlador**: `app/Http/Controllers/v2/SettingController.php`

**Helper function**: `app/Support/helpers.php` - `tenantSetting()`

**Configuración inicial**: `config/company.php` se seedea automáticamente en `settings` al inicializar un tenant.

---

## 🗄️ Estructura de Base de Datos

### Tabla: `settings`

**Migración**: `database/migrations/companies/2025_07_21_154922_create_settings_table.php`

**Campos**:

| Campo | Tipo | Nullable | Descripción |
|-------|------|----------|-------------|
| `id` | bigint | NO | ID único de la configuración |
| `key` | string | NO | Clave de la configuración - **UNIQUE** |
| `value` | longText | YES | Valor de la configuración (JSON, texto, etc.) |
| `type` | string | YES | Tipo de configuración (no se usa en el código) |
| `description` | text | YES | Descripción de la configuración (no se usa en el código) |
| `created_at` | timestamp | NO | Fecha de creación |
| `updated_at` | timestamp | NO | Fecha de última actualización |

**Índices**:
- `id` (primary key)
- `key` (unique)

**Formato de keys**: Normalmente usan prefijo `company.` (ej: `company.name`, `company.cif`)

---

## 📦 Modelo Eloquent

**⚠️ No existe modelo Eloquent**. Se usa directamente Query Builder:

```php
DB::connection('tenant')->table('settings')
```

---

## 🔧 Helper Function: `tenantSetting()`

**Archivo**: `app/Support/helpers.php`

### Funcionalidad

Obtiene un valor de configuración del tenant con fallback a `config()`.

### Signatura

```php
tenantSetting(string $key, mixed $default = null): mixed
```

### Comportamiento

1. **Cache local por petición**: Cache estático en memoria para evitar múltiples queries
2. **Normalización de clave**: Añade prefijo `company.` si no lo tiene
3. **Lectura de BD**: Busca en tabla `settings` del tenant
4. **Fallback**: Si no existe en BD, busca en `config('company.xxx')`
5. **Default**: Si no existe en ningún lado, retorna `$default`

### Ejemplos de Uso

```php
// Busca "company.name" en BD o config
$name = tenantSetting('name');
$name = tenantSetting('company.name'); // Equivalente

// Con default
$logo = tenantSetting('logo_url', 'https://default.com/logo.png');

// Acceso a valores anidados (desde config)
$address = tenantSetting('address.street');
```

---

## 📡 Controlador

**Archivo**: `app/Http/Controllers/v2/SettingController.php`

**Permisos requeridos**: `role:superuser,manager,admin` (según rutas)

### Métodos del Controlador

#### `index()` - Obtener Todas las Configuraciones
```php
GET /v2/settings
```

**Comportamiento**:
- Obtiene todas las configuraciones de la tabla
- Retorna como objeto JSON con `key` como índice

**Respuesta**:
```json
{
    "company.name": "Congelados Brisamar S.L.",
    "company.cif": "B21573282",
    "company.address.street": "C/Dieciocho de Julio de 1922 Nº2",
    ...
}
```

**Nota**: ⚠️ Retorna todas las configuraciones sin paginación. Puede ser problemático si hay muchas.

#### `update(Request $request)` - Actualizar Configuraciones
```php
PUT /v2/settings
```

**Request body**:
```json
{
    "company.name": "Nueva Empresa S.L.",
    "company.cif": "B12345678",
    "company.bcc_email": "nuevo@email.com"
}
```

**Comportamiento**:
- Itera sobre todas las claves enviadas
- Usa `updateOrInsert()` para crear o actualizar
- No valida campos ni estructura

**Respuesta**:
```json
{
    "message": "Settings updated"
}
```

**⚠️ Problemas**:
- No valida claves ni valores
- Permite cualquier clave (no solo `company.*`)
- No valida estructura JSON si el valor es complejo

---

## 📝 Configuración Inicial (config/company.php)

**Archivo**: `config/company.php`

**Propósito**: Configuración por defecto que se seedea en `settings` al crear un tenant.

**Estructura**:

```php
[
    'name' => 'Congelados Brisamar S.L.',
    'cif' => 'B21573282',
    'sanitary_number' => 'ES 12.021462/H CE',
    'address' => [...],
    'website_url' => '...',
    'logo_url_small' => '...',
    'loading_place' => '...',
    'signature_location' => '...',
    'bcc_email' => '...',
    'contact' => [...],
    'legal' => [...],
]
```

**Seeding**: `database/seeders/TenantDatabaseSeeder.php` (líneas 19-28)
- Lee `config('company')`
- Aplana el array con `Arr::dot()` (convierte arrays anidados en `clave.subclave`)
- Crea registros en `settings` con prefijo `company.`

**Ejemplo**: `address.street` se guarda como key `company.address.street`

---

## 🔐 Permisos y Autenticación

**Middleware requerido**:
- `auth:sanctum`: Autenticación requerida
- `role:superuser,manager,admin`: Roles permitidos (según rutas)

**Rutas**: Todas bajo `/v2/settings`

**Rutas definidas**:
- `GET /v2/settings` - Listar todas las configuraciones
- `PUT /v2/settings` - Actualizar configuraciones

---

## 💡 Uso en el Código

### Helper Function

El helper `tenantSetting()` se usa en toda la aplicación para acceder a configuraciones:

```php
// En controladores
$companyName = tenantSetting('name');
$bccEmail = tenantSetting('bcc_email');

// En vistas Blade (PDFs)
{{ tenantSetting('name') }}
```

### Acceso Directo a BD

El controlador accede directamente:

```php
DB::connection('tenant')->table('settings')
    ->where('key', 'company.name')
    ->value('value');
```

---

## Observaciones Críticas y Mejoras Recomendadas

### ⚠️ No Hay Validación en Update

1. **Sin Validación de Campos** (`app/Http/Controllers/v2/SettingController.php:17-29`)
   - No valida que las claves sean válidas
   - No valida estructura de valores
   - **Líneas**: 17-29
   - **Problema**: Pueden guardarse configuraciones inválidas
   - **Recomendación**: Agregar validación de estructura esperada

### ⚠️ Sin Paginación en Index

2. **Retorna Todas las Configuraciones** (`app/Http/Controllers/v2/SettingController.php:11-15`)
   - No hay paginación
   - **Líneas**: 11-15
   - **Problema**: Puede retornar muchas configuraciones
   - **Recomendación**: Agregar paginación o filtrado si se necesitan muchas

### ⚠️ Sin Modelo Eloquent

3. **No Hay Modelo Setting** 
   - Se usa Query Builder directamente
   - **Problema**: No hay validaciones, casts, ni relaciones
   - **Recomendación**: 
     - Considerar crear modelo si se necesita funcionalidad avanzada
     - O mantener Query Builder si es intencional (simplicidad)

### ⚠️ Campos type y description No Se Usan

4. **Campos No Utilizados** (`database/migrations/companies/2025_07_21_154922_create_settings_table.php:14-15`)
   - Campos `type` y `description` existen pero no se usan
   - **Problema**: Información no disponible para validación/documentación
   - **Recomendación**: 
     - Usar para validación de tipos
     - O eliminar si no se necesitan

### ⚠️ Valor Como longText

5. **value Como longText** (`database/migrations/companies/2025_07_21_154922_create_settings_table.php:13`)
   - Campo `value` es `longText` (puede almacenar JSON, texto, etc.)
   - **Problema**: No hay validación de formato
   - **Recomendación**: 
     - Validar formato si se espera JSON
     - O usar campo JSON si Laravel lo soporta

### ⚠️ Sin Método show() o get() Individual

6. **No Se Puede Obtener Una Configuración Específica** (`app/Http/Controllers/v2/SettingController.php`)
   - Solo `index()` (todas) y `update()` (masivo)
   - **Problema**: No hay endpoint para obtener una key específica
   - **Recomendación**: 
     - Agregar método si se necesita
     - O usar helper `tenantSetting()` desde el código

### ⚠️ Update Actualiza Todas las Claves

7. **Update Masivo Sin Selectividad** (`app/Http/Controllers/v2/SettingController.php:19-26`)
   - Actualiza todas las claves enviadas
   - **Problema**: No se puede actualizar una sola clave fácilmente
   - **Recomendación**: 
     - Agregar método para actualizar una key
     - O documentar que update es masivo

### ⚠️ Sin Validación de Prefijo company.

8. **Permite Cualquier Prefijo** (`app/Http/Controllers/v2/SettingController.php:22`)
   - No valida que las keys tengan prefijo `company.`
   - **Problema**: Pueden crearse configuraciones con otros prefijos
   - **Recomendación**: 
     - Validar prefijo si solo se permiten configs de empresa
     - O permitir cualquier key si es intencional

### ⚠️ Cache Solo Por Petición

9. **Cache No Persiste Entre Requests** (`app/Support/helpers.php:10`)
   - Cache estático solo funciona en una petición
   - **Problema**: Cada request hace query si no está en cache
   - **Recomendación**: 
     - Considerar cache persistente si se lee mucho
     - O mantener cache por petición si es suficiente

### ⚠️ Fallback a config() Puede Confundir

10. **Fallback Silencioso** (`app/Support/helpers.php:29-32`)
    - Si no existe en BD, busca en `config('company.xxx')`
    - **Problema**: Puede ser confuso si se espera valor de BD
    - **Recomendación**: Documentar comportamiento o hacer más explícito

---

**Última actualización**: Documentación generada desde código fuente en fecha de generación.

