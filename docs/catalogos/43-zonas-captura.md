# Catálogos - Zonas de Captura (Capture Zones)

## ⚠️ Estado de la API
- **v1**: Obsoleta (no documentada)
- **v2**: Versión activa (este documento)

---

## 📋 Visión General

El modelo `CaptureZone` representa una **zona de captura FAO** donde se pesca el producto. Las zonas de captura identifican geográficamente el origen del pescado (por ejemplo, "FAO 27 IX.a").

**Concepto clave**: Las zonas de captura son fundamentales para la trazabilidad y certificación de productos pesqueros, especialmente para cumplir con normativas europeas e internacionales.

**Archivo del modelo**: `app/Models/CaptureZone.php`

---

## 🗄️ Estructura de Base de Datos

### Tabla: `capture_zones`

**Migración**: `database/migrations/companies/2023_08_09_145133_create_capture_zones_table.php`

**Campos**:

| Campo | Tipo | Nullable | Descripción |
|-------|------|----------|-------------|
| `id` | bigint | NO | ID único de la zona |
| `name` | string | NO | Nombre de la zona (ej: "FAO 27 IX.a") |
| `created_at` | timestamp | NO | Fecha de creación |
| `updated_at` | timestamp | NO | Fecha de última actualización |

**Índices**:
- `id` (primary key)

---

## 📦 Modelo Eloquent

### Fillable Attributes

```php
protected $fillable = ['name'];
```

### Traits

- `UsesTenantConnection`: Usa conexión tenant (multi-tenant)
- `HasFactory`: Para testing y seeders

---

## 🔗 Relaciones

### `productions()` - Producciones
```php
public function productions()
{
    return $this->hasMany(Production::class, 'capture_zone_id');
}
```
- Relación uno-a-muchos con `Production`
- Lotes de producción de esta zona

**Nota**: También está relacionada con `Product` (a través de `capture_zone_id`), pero no hay relación definida en el modelo.

---

## 📡 Controlador

**Archivo**: `app/Http/Controllers/v2/CaptureZoneController.php`

### Métodos del Controlador

#### `index(Request $request)` - Listar Zonas
```php
GET /v2/capture-zones
```

**Filtros disponibles** (query parameters):
- `id`: Filtrar por ID
- `ids`: Filtrar por múltiples IDs (array)
- `name`: Buscar por nombre (LIKE)

**Orden**: Por nombre ascendente

**Query parameters**: `perPage` (default: 12)

**Respuesta**: Collection paginada de `CaptureZoneResource`

#### `store(Request $request)` - Crear Zona
```php
POST /v2/capture-zones
```

**Validación**:
```php
[
    'name' => 'required|string|min:3|max:255',
]
```

**Request body**:
```json
{
    "name": "FAO 27 IX.a"
}
```

**Respuesta** (201):
```json
{
    "message": "Zona de captura creada con éxito",
    "data": { ... }
}
```

#### `show(string $id)` - Mostrar Zona
```php
GET /v2/capture-zones/{id}
```

**Respuesta**: `CaptureZoneResource`

#### `update(Request $request, string $id)` - Actualizar Zona
```php
PUT /v2/capture-zones/{id}
```

**Validación**: Igual que `store()`

#### `destroy(string $id)` - Eliminar Zona
```php
DELETE /v2/capture-zones/{id}
```

**Advertencia**: ⚠️ No valida si la zona está en uso (productos, producciones)

#### `destroyMultiple(Request $request)` - Eliminar Múltiples Zonas
```php
DELETE /v2/capture-zones
```

**Request body**:
```json
{
    "ids": [1, 2, 3]
}
```

#### `options()` - Opciones para Select
```php
GET /v2/capture-zones/options
```

**Respuesta**: Array simple con `id` y `name`

---

## 📄 API Resource

**Archivo**: `app/Http/Resources/v2/CaptureZoneResource.php` (o v1)

Usa el método `toArrayAssoc()` del modelo:
```json
{
    "id": 1,
    "name": "FAO 27 IX.a"
}
```

---

## 🔐 Permisos y Autenticación

**Middleware requerido**:
- `auth:sanctum`: Autenticación requerida
- `role:superuser,manager,admin,store_operator`: Roles permitidos

**Rutas**: Todas bajo `/v2/capture-zones/*`

---

## 📝 Ejemplos de Uso

### Crear una Zona
```http
POST /v2/capture-zones
Content-Type: application/json
Authorization: Bearer {token}
X-Tenant: empresa1

{
    "name": "FAO 27 IX.a"
}
```

### Buscar Zonas
```http
GET /v2/capture-zones?name=FAO
Authorization: Bearer {token}
X-Tenant: empresa1
```

---

## Observaciones Críticas y Mejoras Recomendadas

### ⚠️ Eliminación Sin Validaciones

1. **No Valida Uso Antes de Eliminar** (`app/Http/Controllers/v2/CaptureZoneController.php:110-116`)
   - No valida si la zona está en uso (productos, producciones)
   - **Líneas**: 110-116
   - **Problema**: Puede eliminar zonas en uso, rompiendo relaciones
   - **Recomendación**: 
     - Validar relaciones antes de eliminar
     - O usar soft deletes

### ⚠️ Sin Validación de Nombre Único

2. **No Valida Unicidad de Nombre** (`app/Http/Controllers/v2/CaptureZoneController.php`)
   - No valida que el nombre sea único
   - **Problema**: Pueden crearse zonas duplicadas
   - **Recomendación**: 
     - Agregar unique constraint en BD
     - O validar en controlador

### ⚠️ Relación con Product No Definida

3. **No Hay Relación products()** (`app/Models/CaptureZone.php`)
   - No hay relación definida con `Product` aunque existe FK en productos
   - **Problema**: No se puede acceder fácilmente a productos desde la zona
   - **Recomendación**: Agregar relación `products()`

### ⚠️ Métodos Vacíos

4. **create() y edit() Vacíos** (`app/Http/Controllers/v2/CaptureZoneController.php:43-46, 82-85`)
   - Métodos están vacíos (no implementados)
   - **Líneas**: 43-46, 82-85
   - **Estado**: No crítico, pero código muerto

### ⚠️ Sin Validación de Formato FAO

5. **No Valida Formato de Zona** (`app/Http/Controllers/v2/CaptureZoneController.php:53-55`)
   - No valida que el nombre siga formato FAO estándar
   - **Estado**: Puede ser intencional para flexibilidad
   - **Recomendación**: Considerar validación de formato si se requiere estandarización

---

**Última actualización**: Documentación generada desde código fuente en fecha de generación.

