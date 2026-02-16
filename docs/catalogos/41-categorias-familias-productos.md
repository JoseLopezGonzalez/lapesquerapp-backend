# Catálogos - Categorías y Familias de Productos

## ⚠️ Estado de la API
- **v1**: Obsoleta (no documentada)
- **v2**: Versión activa (este documento)

---

## 📋 Visión General

El sistema de clasificación de productos utiliza una **jerarquía de dos niveles**:

1. **ProductCategory** (Categorías): Nivel superior - Estado/proceso (ej: "Fresco", "Congelado")
2. **ProductFamily** (Familias): Nivel inferior - Presentación/elaboración (ej: "Fresco entero", "Congelado fileteado")

**Concepto clave**: Las categorías agrupan familias, y las familias agrupan productos. Un producto pertenece a una familia, y una familia pertenece a una categoría.

**Estructura jerárquica**:
```
ProductCategory (1) ←→ (N) ProductFamily (1) ←→ (N) Product
```

---

## 🗄️ Estructura de Base de Datos

### Tabla: `product_categories`

**Migración**: `database/migrations/companies/2025_08_08_080244_create_product_categories_table.php`

**Campos**:

| Campo | Tipo | Nullable | Descripción |
|-------|------|----------|-------------|
| `id` | bigint | NO | ID único de la categoría |
| `name` | string | NO | Nombre de la categoría |
| `description` | text | YES | Descripción de la categoría |
| `active` | boolean | NO | Estado activo/inactivo (default: true) |
| `created_at` | timestamp | NO | Fecha de creación |
| `updated_at` | timestamp | NO | Fecha de última actualización |

**Índices**:
- `id` (primary key)

### Tabla: `product_families`

**Migración**: `database/migrations/companies/2025_08_08_080252_create_product_families_table.php`

**Campos**:

| Campo | Tipo | Nullable | Descripción |
|-------|------|----------|-------------|
| `id` | bigint | NO | ID único de la familia |
| `name` | string | NO | Nombre de la familia |
| `description` | text | YES | Descripción de la familia |
| `category_id` | bigint | NO | FK a `product_categories` - Categoría a la que pertenece |
| `active` | boolean | NO | Estado activo/inactivo (default: true) |
| `created_at` | timestamp | NO | Fecha de creación |
| `updated_at` | timestamp | NO | Fecha de última actualización |

**Índices**:
- `id` (primary key)
- Foreign key a `product_categories`

**Constraints**:
- `category_id` → `product_categories.id` (onDelete: cascade)

---

## 📦 Modelo Eloquent: ProductCategory

**Archivo**: `app/Models/ProductCategory.php`

### Fillable Attributes

```php
protected $fillable = [
    'name',
    'description',
    'active',
];
```

### Traits

- `UsesTenantConnection`: Usa conexión tenant (multi-tenant)
- `HasFactory`: Para testing y seeders

### Casts

```php
protected $casts = [
    'active' => 'boolean',
];
```

### Relaciones

#### `families()` - Familias de la Categoría
```php
public function families()
{
    return $this->hasMany(ProductFamily::class, 'category_id');
}
```

#### `products()` - Productos de la Categoría (Through)
```php
public function products()
{
    return $this->hasManyThrough(Product::class, ProductFamily::class, 'category_id', 'family_id');
}
```
- Relación a través de `ProductFamily`
- Obtiene todos los productos de todas las familias de la categoría

---

## 📦 Modelo Eloquent: ProductFamily

**Archivo**: `app/Models/ProductFamily.php`

### Fillable Attributes

```php
protected $fillable = [
    'name',
    'description',
    'category_id',
    'active',
];
```

### Traits

- `UsesTenantConnection`: Usa conexión tenant (multi-tenant)
- `HasFactory`: Para testing y seeders

### Casts

```php
protected $casts = [
    'active' => 'boolean',
];
```

### Relaciones

#### `category()` - Categoría de la Familia
```php
public function category()
{
    return $this->belongsTo(ProductCategory::class, 'category_id');
}
```

#### `products()` - Productos de la Familia
```php
public function products()
{
    return $this->hasMany(Product::class, 'family_id');
}
```

---

## 📡 Controladores

### ProductCategoryController

**Archivo**: `app/Http/Controllers/v2/ProductCategoryController.php`

#### Métodos

##### `index(Request $request)` - Listar Categorías
```php
GET /v2/product-categories
```

**Filtros**:
- `id`: Filtrar por ID
- `ids`: Filtrar por múltiples IDs
- `name`: Buscar por nombre (LIKE)
- `active`: Filtrar por estado activo (boolean)

**Orden**: Por nombre ascendente

**Query parameters**: `perPage` (default: 12)

##### `store(Request $request)` - Crear Categoría
```php
POST /v2/product-categories
```

**Validación**:
```php
[
    'name' => 'required|string|min:3|max:255',
    'description' => 'nullable|string|max:1000',
    'active' => 'boolean',
]
```

##### `show(string $id)` - Mostrar Categoría
```php
GET /v2/product-categories/{id}
```

**Eager loading**: Carga `families`

##### `update(Request $request, string $id)` - Actualizar Categoría
```php
PUT /v2/product-categories/{id}
```

##### `destroy(string $id)` - Eliminar Categoría
```php
DELETE /v2/product-categories/{id}
```

**Validación**: Verifica que no tenga familias asociadas antes de eliminar

##### `destroyMultiple(Request $request)` - Eliminar Múltiples Categorías
```php
DELETE /v2/product-categories
```

**Comportamiento**: Valida cada categoría individualmente y reporta errores

##### `options()` - Opciones para Select
```php
GET /v2/product-categories/options
```

**Respuesta**: Solo categorías activas con `id`, `name`, `description`

### ProductFamilyController

**Archivo**: `app/Http/Controllers/v2/ProductFamilyController.php`

#### Métodos

##### `index(Request $request)` - Listar Familias
```php
GET /v2/product-families
```

**Filtros**:
- `id`: Filtrar por ID
- `ids`: Filtrar por múltiples IDs
- `name`: Buscar por nombre (LIKE)
- `categoryId`: Filtrar por categoría
- `active`: Filtrar por estado activo

**Eager loading**: Carga `category`

**Orden**: Por nombre ascendente

##### `store(Request $request)` - Crear Familia
```php
POST /v2/product-families
```

**Validación**:
```php
[
    'name' => 'required|string|min:3|max:255',
    'description' => 'nullable|string|max:1000',
    'categoryId' => 'required|exists:tenant.product_categories,id',
    'active' => 'boolean',
]
```

**Comportamiento**: `active` default a `true` si no se proporciona

##### `show(string $id)` - Mostrar Familia
```php
GET /v2/product-families/{id}
```

**Eager loading**: Carga `category` y `products`

##### `update(Request $request, string $id)` - Actualizar Familia
```php
PUT /v2/product-families/{id}
```

**Nota**: Usa valores por defecto del modelo si no se proporcionan

##### `destroy(string $id)` - Eliminar Familia
```php
DELETE /v2/product-families/{id}
```

**Validación**: Verifica que no tenga productos asociados

##### `destroyMultiple(Request $request)` - Eliminar Múltiples Familias
```php
DELETE /v2/product-families
```

##### `options()` - Opciones para Select
```php
GET /v2/product-families/options
```

**Respuesta**: Solo familias activas con `id`, `name`, `description`, `categoryId`, `categoryName`

---

## 📄 API Resources

### ProductCategoryResource

**Archivo**: `app/Http/Resources/v2/ProductCategoryResource.php`

Usa `toArrayAssoc()` del modelo:
```json
{
    "id": 1,
    "name": "Congelado",
    "description": "Productos congelados",
    "active": true
}
```

### ProductFamilyResource

**Archivo**: `app/Http/Resources/v2/ProductFamilyResource.php`

Usa `toArrayAssoc()` del modelo:
```json
{
    "id": 1,
    "name": "Fileteado",
    "description": "Productos fileteados",
    "category": {
        "id": 1,
        "name": "Congelado"
    },
    "active": true
}
```

---

## 🔐 Permisos y Autenticación

**Middleware requerido**:
- `auth:sanctum`: Autenticación requerida
- `role:superuser,manager,admin,store_operator`: Roles permitidos

**Rutas**: 
- `/v2/product-categories/*`
- `/v2/product-families/*`

---

## 📝 Ejemplos de Uso

### Crear Categoría
```http
POST /v2/product-categories
Content-Type: application/json
Authorization: Bearer {token}
X-Tenant: empresa1

{
    "name": "Congelado",
    "description": "Productos congelados",
    "active": true
}
```

### Crear Familia
```http
POST /v2/product-families
Content-Type: application/json
Authorization: Bearer {token}

{
    "name": "Fileteado",
    "description": "Productos fileteados",
    "categoryId": 1,
    "active": true
}
```

### Listar Familias por Categoría
```http
GET /v2/product-families?categoryId=1&active=true
Authorization: Bearer {token}
```

---

## Observaciones Críticas y Mejoras Recomendadas

### ⚠️ Eliminación en Cascade

1. **Cascade en category_id** (`database/migrations/companies/2025_08_08_080252_create_product_families_table.php:22`)
   - `onDelete('cascade')` en `category_id`
   - **Líneas**: 22
   - **Problema**: Si se elimina una categoría, se eliminan todas sus familias (aunque valida antes)
   - **Estado**: La validación previene eliminación si hay familias, pero el cascade es redundante
   - **Recomendación**: Cambiar a `onDelete('restrict')` o mantener validación

### ⚠️ Validación Antes de Eliminar

2. **Validación en destroy() de Categoría** (`app/Http/Controllers/v2/ProductCategoryController.php:102-106`)
   - Valida que no tenga familias antes de eliminar
   - **Líneas**: 102-106
   - **Estado**: ✅ Correcto, pero el cascade en BD lo hace redundante

3. **Validación en destroy() de Familia** (`app/Http/Controllers/v2/ProductFamilyController.php:127-131`)
   - Valida que no tenga productos antes de eliminar
   - **Líneas**: 127-131
   - **Estado**: ✅ Correcto

### ⚠️ Valores por Defecto en Update

4. **Update Usa Valores del Modelo** (`app/Http/Controllers/v2/ProductFamilyController.php:100-104`)
   - Si no se proporciona un campo, usa el valor actual del modelo
   - **Líneas**: 100-104
   - **Problema**: Podría no actualizar si se envía `null` explícitamente
   - **Recomendación**: Usar `$validated` directamente o manejar `null` explícitamente

### ⚠️ Query N+1 Potencial

5. **Eager Loading Inconsistente** (`app/Http/Controllers/v2/ProductCategoryController.php:65`)
   - `show()` carga `families`, pero `index()` no
   - **Líneas**: 65
   - **Problema**: Si se accede a categorías con familias desde index, hay N+1
   - **Recomendación**: Agregar eager loading opcional o siempre cargar

### ⚠️ Options Filtra Solo Activos

6. **options() Solo Retorna Activos** (`app/Http/Controllers/v2/ProductCategoryController.php:160-165`)
   - Filtra por `active = true` en options
   - **Líneas**: 160-165
   - **Estado**: ✅ Correcto para select boxes, pero podría ser opcional

### ⚠️ Sin Validación de Nombre Único

7. **No Valida Unicidad de Nombres** (`app/Http/Controllers/v2/ProductCategoryController.php`, `ProductFamilyController.php`)
   - No valida que nombres sean únicos
   - **Problema**: Pueden crearse categorías/familias duplicadas
   - **Recomendación**: 
     - Agregar unique constraints en BD
     - O validar en controlador

### ⚠️ Mensajes de Error en destroyMultiple

8. **Mensajes Concatena Errores** (`app/Http/Controllers/v2/ProductCategoryController.php:143-146`)
   - Concatena todos los errores en un solo mensaje
   - **Líneas**: 143-146
   - **Estado**: Funciona, pero podría ser más claro separando errores

### ⚠️ hasManyThrough Puede Ser Lento

9. **products() Usa hasManyThrough** (`app/Models/ProductCategory.php:29-32`)
   - Relación `hasManyThrough` puede ser lenta con muchos productos
   - **Líneas**: 29-32
   - **Problema**: Queries complejas si se accede frecuentemente
   - **Recomendación**: Cachear o optimizar si se usa frecuentemente

### ⚠️ active Sin Default en Migración

10. **active No Tiene Default** (`database/migrations/companies/2025_08_08_080244_create_product_categories_table.php`)
    - Campo `active` no tiene default en migración
    - **Problema**: Puede causar errores si no se especifica
    - **Recomendación**: Agregar `->default(true)` en migración

---

**Última actualización**: Documentación generada desde código fuente en fecha de generación.

