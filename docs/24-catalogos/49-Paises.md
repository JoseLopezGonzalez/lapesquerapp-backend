# Catálogos - Países (Countries)

## ⚠️ Estado de la API
- **v1**: Obsoleta (no documentada)
- **v2**: Versión activa (este documento)

---

## 📋 Visión General

El modelo `Country` representa un **país**. Los países se asignan a clientes para identificar su ubicación geográfica. Es un catálogo simple que contiene solo el nombre del país.

**Archivo del modelo**: `app/Models/Country.php`

---

## 🗄️ Estructura de Base de Datos

### Tabla: `countries`

**Migración**: `database/migrations/companies/2023_12_19_151259_create_countries_table.php`

**Campos**:

| Campo | Tipo | Nullable | Descripción |
|-------|------|----------|-------------|
| `id` | bigint | NO | ID único del país |
| `name` | string | NO | Nombre del país |
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

### `customers()` - Clientes
```php
public function customers()
{
    return $this->hasMany(Customer::class);
}
```
- Relación uno-a-muchos con `Customer`
- Clientes de este país

---

## 📡 Controlador

**Archivo**: `app/Http/Controllers/v2/CountryController.php`

### Métodos del Controlador

#### `index(Request $request)` - Listar Países
```php
GET /v2/countries
```

**Filtros disponibles** (query parameters):
- `id`: Filtrar por ID
- `ids`: Filtrar por múltiples IDs (array)
- `name`: Buscar por nombre (LIKE)

**Orden**: Por nombre ascendente

**Query parameters**: `perPage` (default: 12)

**Respuesta**: Collection paginada de `CountryResource`

#### `store(Request $request)` - Crear País
```php
POST /v2/countries
```

**Validación**:
```php
[
    'name' => 'required|string|min:2|max:255',
]
```

**Request body**:
```json
{
    "name": "España"
}
```

**Respuesta** (201): `CountryResource`

#### `show(string $id)` - Mostrar País
```php
GET /v2/countries/{id}
```

#### `update(Request $request, string $id)` - Actualizar País
```php
PUT /v2/countries/{id}
```

**Validación**: Igual que `store()`

#### `destroy(string $id)` - Eliminar País
```php
DELETE /v2/countries/{id}
```

**Advertencia**: ⚠️ No valida si el país está en uso (clientes)

#### `destroyMultiple(Request $request)` - Eliminar Múltiples Países
```php
DELETE /v2/countries
```

#### `options()` - Opciones para Select
```php
GET /v2/countries/options
```

**Respuesta**: Array simple con `id` y `name`

---

## 📄 API Resource

**Archivo**: `app/Http/Resources/v2/CountryResource.php`

Usa el método `toArrayAssoc()` del modelo:
```json
{
    "id": 1,
    "name": "España",
    "createdAt": "2025-01-15T10:00:00",
    "updatedAt": "2025-01-15T10:00:00"
}
```

---

## 🔐 Permisos y Autenticación

**Middleware requerido**:
- `auth:sanctum`: Autenticación requerida
- `role:superuser,manager,admin,store_operator`: Roles permitidos

**Rutas**: Todas bajo `/v2/countries/*`

---

## Observaciones Críticas y Mejoras Recomendadas

### ⚠️ Eliminación Sin Validaciones

1. **No Valida Uso Antes de Eliminar** (`app/Http/Controllers/v2/CountryController.php:108-114`)
   - No valida si el país está en uso (clientes)
   - **Líneas**: 108-114
   - **Problema**: Puede eliminar países en uso
   - **Recomendación**: Validar relaciones antes de eliminar

### ⚠️ Sin Validación de Nombre Único

2. **No Valida Unicidad de Nombre** (`app/Http/Controllers/v2/CountryController.php`)
   - No valida que el nombre sea único
   - **Problema**: Pueden crearse países duplicados
   - **Recomendación**: 
     - Agregar unique constraint en BD
     - O validar en controlador

### ⚠️ Sin Validación de Formato ISO

3. **No Usa Código ISO** (`app/Models/Country.php`)
   - Solo almacena nombre, no código ISO (ej: "ES", "FR")
   - **Estado**: Puede ser intencional si no se necesita
   - **Recomendación**: Considerar agregar código ISO si se requiere estandarización

---

**Última actualización**: Documentación generada desde código fuente en fecha de generación.

