# Sistema de Categorías y Familias de Productos

## 📋 Descripción General

Este documento describe la implementación completa del sistema de categorías y familias de productos en la aplicación PesquerApp. El sistema permite clasificar productos en dos niveles: **categorías** (estado/proceso) y **familias** (presentación/elaboración).

## 🏗️ Arquitectura del Sistema

### Modelo de Datos

```
ProductCategory (1) ←→ (N) ProductFamily (1) ←→ (N) Product
     ↑                                                    ↑
     └─────────────── (1) ←→ (N) ────────────────────────┘
```

### Estructura Jerárquica

```
Categorías (Estado/Proceso)
├── Fresco
│   ├── Fresco entero
│   ├── Fresco eviscerado
│   └── Fresco fileteado
└── Congelado
    ├── Congelado entero
    ├── Congelado eviscerado
    ├── Congelado fileteado
    ├── Elaborado congelado
    └── Elaborado en bandeja
```

## 📊 Estructura de Base de Datos

### Tabla: `product_categories`

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | BIGINT UNSIGNED | Clave primaria |
| `name` | VARCHAR(255) | Nombre de la categoría |
| `description` | TEXT | Descripción opcional |
| `active` | BOOLEAN | Estado activo/inactivo |
| `created_at` | TIMESTAMP | Fecha de creación |
| `updated_at` | TIMESTAMP | Fecha de actualización |

### Tabla: `product_families`

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | BIGINT UNSIGNED | Clave primaria |
| `name` | VARCHAR(255) | Nombre de la familia |
| `description` | TEXT | Descripción opcional |
| `category_id` | BIGINT UNSIGNED | FK a product_categories |
| `active` | BOOLEAN | Estado activo/inactivo |
| `created_at` | TIMESTAMP | Fecha de creación |
| `updated_at` | TIMESTAMP | Fecha de actualización |

### Tabla: `products` (Campos Agregados)

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `category_id` | BIGINT UNSIGNED NULL | FK a product_categories |
| `family_id` | BIGINT UNSIGNED NULL | FK a product_families |

## 🔗 Relaciones Eloquent

### ProductCategory

```php
class ProductCategory extends Model
{
    use UsesTenantConnection;
    
    protected $fillable = [
        'name',
        'description', 
        'active'
    ];
    
    protected $casts = [
        'active' => 'boolean'
    ];
    
    // Relaciones
    public function families()
    {
        return $this->hasMany(ProductFamily::class, 'category_id');
    }
    
    public function products()
    {
        return $this->hasMany(Product::class, 'category_id');
    }
}
```

### ProductFamily

```php
class ProductFamily extends Model
{
    use UsesTenantConnection;
    
    protected $fillable = [
        'name',
        'description',
        'category_id',
        'active'
    ];
    
    protected $casts = [
        'active' => 'boolean'
    ];
    
    // Relaciones
    public function category()
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }
    
    public function products()
    {
        return $this->hasMany(Product::class, 'family_id');
    }
}
```

### Product (Actualizado)

```php
class Product extends Model
{
    // Campos agregados al fillable
    protected $fillable = [
        // ... campos existentes
        'category_id',
        'family_id'
    ];
    
    // Nuevas relaciones
    public function category()
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }
    
    public function family()
    {
        return $this->belongsTo(ProductFamily::class, 'family_id');
    }
    
    // Método toArrayAssoc actualizado
    public function toArrayAssoc()
    {
        return array_merge(
            optional($this->article)->toArrayAssoc() ?? [],
            [
                // ... campos existentes
                'category' => optional($this->category)->toArrayAssoc() ?? [],
                'family' => optional($this->family)->toArrayAssoc() ?? [],
            ]
        );
    }
}
```

## 🚀 API Endpoints

### ProductCategories

#### Listar Categorías
```http
GET /api/v2/product-categories
```

**Parámetros de Query:**
- `id` - Filtrar por ID específico
- `ids` - Filtrar por múltiples IDs
- `name` - Búsqueda por nombre (LIKE)
- `active` - Filtrar por estado activo
- `perPage` - Elementos por página (default: 12)

**Respuesta:**
```json
{
  "data": [
    {
      "id": 1,
      "name": "Fresco",
      "description": "Productos frescos sin procesar",
      "active": true,
      "createdAt": "2025-08-08T08:08:05.000000Z",
      "updatedAt": "2025-08-08T08:08:05.000000Z"
    }
  ],
  "links": {...},
  "meta": {...}
}
```

#### Crear Categoría
```http
POST /api/v2/product-categories
```

**Body:**
```json
{
  "name": "Nueva Categoría",
  "description": "Descripción opcional",
  "active": true
}
```

#### Ver Categoría
```http
GET /api/v2/product-categories/{id}
```

#### Actualizar Categoría
```http
PUT /api/v2/product-categories/{id}
```

#### Eliminar Categoría
```http
DELETE /api/v2/product-categories/{id}
```

#### Eliminar Múltiples Categorías
```http
DELETE /api/v2/product-categories
```

**Body:**
```json
{
  "ids": [1, 2, 3]
}
```

#### Obtener Opciones de Categorías
```http
GET /api/v2/product-categories/options
```

### ProductFamilies

#### Listar Familias
```http
GET /api/v2/product-families
```

**Parámetros de Query:**
- `id` - Filtrar por ID específico
- `ids` - Filtrar por múltiples IDs
- `name` - Búsqueda por nombre (LIKE)
- `categoryId` - Filtrar por categoría
- `active` - Filtrar por estado activo
- `perPage` - Elementos por página (default: 12)

**Respuesta:**
```json
{
  "data": [
    {
      "id": 1,
      "name": "Fresco entero",
      "description": "Productos frescos enteros sin procesar",
      "categoryId": 1,
      "category": {
        "id": 1,
        "name": "Fresco",
        "description": "Productos frescos sin procesar",
        "active": true,
        "createdAt": "2025-08-08T08:08:05.000000Z",
        "updatedAt": "2025-08-08T08:08:05.000000Z"
      },
      "active": true,
      "createdAt": "2025-08-08T08:08:05.000000Z",
      "updatedAt": "2025-08-08T08:08:05.000000Z"
    }
  ],
  "links": {...},
  "meta": {...}
}
```

#### Crear Familia
```http
POST /api/v2/product-families
```

**Body:**
```json
{
  "name": "Nueva Familia",
  "description": "Descripción opcional",
  "categoryId": 1,
  "active": true
}
```

#### Ver Familia
```http
GET /api/v2/product-families/{id}
```

#### Actualizar Familia
```http
PUT /api/v2/product-families/{id}
```

#### Eliminar Familia
```http
DELETE /api/v2/product-families/{id}
```

#### Eliminar Múltiples Familias
```http
DELETE /api/v2/product-families
```

**Body:**
```json
{
  "ids": [1, 2, 3]
}
```

#### Obtener Opciones de Familias
```http
GET /api/v2/product-families/options
```

### Products (Actualizado)

#### Listar Productos con Filtros
```http
GET /api/v2/products?categories=1,2&families=1,3,5
```

**Nuevos Parámetros de Query:**
- `categories` - Filtrar por categorías (IDs separados por coma)
- `families` - Filtrar por familias (IDs separados por coma)

#### Crear Producto con Categoría y Familia
```http
POST /api/v2/products
```

**Body:**
```json
{
  "name": "Pulpo Fresco Entero",
  "speciesId": 1,
  "captureZoneId": 1,
  "categoryId": 1,
  "familyId": 1,
  "articleGtin": "1234567890123",
  "boxGtin": "1234567890124",
  "palletGtin": "1234567890125"
}
```

## 🔧 Validaciones

### ProductCategory

```php
'name' => 'required|string|min:3|max:255',
'description' => 'nullable|string|max:1000',
'active' => 'boolean'
```

### ProductFamily

```php
'name' => 'required|string|min:3|max:255',
'description' => 'nullable|string|max:1000',
'categoryId' => 'required|exists:tenant.product_categories,id',
'active' => 'boolean'
```

### Product (Nuevos Campos)

```php
'categoryId' => 'nullable|exists:tenant.product_categories,id',
'familyId' => 'nullable|exists:tenant.product_families,id'
```

## 🌱 Datos Iniciales

### Categorías Creadas

| ID | Nombre | Descripción |
|----|--------|-------------|
| 1 | Fresco | Productos frescos sin procesar |
| 2 | Congelado | Productos congelados |

### Familias Creadas

| ID | Nombre | Categoría ID | Descripción |
|----|--------|--------------|-------------|
| 1 | Fresco entero | 1 | Productos frescos enteros sin procesar |
| 2 | Fresco eviscerado | 1 | Productos frescos eviscerados |
| 3 | Fresco fileteado | 1 | Productos frescos fileteados |
| 4 | Congelado entero | 2 | Productos congelados enteros |
| 5 | Congelado eviscerado | 2 | Productos congelados eviscerados |
| 6 | Congelado fileteado | 2 | Productos congelados fileteados |
| 7 | Elaborado congelado | 2 | Productos elaborados y congelados |
| 8 | Elaborado en bandeja | 2 | Productos elaborados y presentados en bandeja |

## 🛡️ Protecciones de Seguridad

### Eliminación Segura

#### ProductCategory
- No se puede eliminar si tiene familias asociadas
- Retorna error 400 con mensaje descriptivo

#### ProductFamily
- No se puede eliminar si tiene productos asociados
- Retorna error 400 con mensaje descriptivo

### Validaciones de Existencia

- Todas las relaciones verifican existencia en la base de datos
- Uso de `exists:tenant.table_name,id` para validaciones
- Manejo de errores con respuestas JSON estructuradas

## 🔄 Comandos Artisan

### Migraciones

```bash
# Ejecutar migraciones en todos los tenants
php artisan tenants:migrate

# Ejecutar migraciones específicas
php artisan migrate --path=database/migrations/companies/2025_08_08_080244_create_product_categories_table.php
```

### Seeders

```bash
# Ejecutar seeders en todos los tenants
php artisan tenants:seed --class=ProductCategorySeeder
php artisan tenants:seed --class=ProductFamilySeeder

# Ejecutar seeders específicos
php artisan db:seed --database=tenant --class=ProductCategorySeeder
```

## 📁 Estructura de Archivos

```
app/
├── Models/
│   ├── ProductCategory.php          # Nuevo modelo
│   ├── ProductFamily.php            # Nuevo modelo
│   └── Product.php                  # Actualizado
├── Http/
│   ├── Controllers/v2/
│   │   ├── ProductCategoryController.php  # Nuevo controlador
│   │   ├── ProductFamilyController.php    # Nuevo controlador
│   │   └── ProductController.php          # Actualizado
│   └── Resources/v2/
│       ├── ProductCategoryResource.php    # Nuevo resource
│       ├── ProductFamilyResource.php      # Nuevo resource
│       └── ProductResource.php            # Actualizado
├── Console/Commands/
│   └── SeedTenants.php              # Nuevo comando
└── Traits/
    └── UsesTenantConnection.php     # Existente

database/
├── migrations/companies/
│   ├── 2025_08_08_080244_create_product_categories_table.php
│   ├── 2025_08_08_080252_create_product_families_table.php
│   ├── 2025_08_08_080401_add_category_and_family_to_products_table.php
│   └── README.md                    # Documentación de migraciones
└── seeders/
    ├── ProductCategorySeeder.php
    └── ProductFamilySeeder.php

routes/
└── api.php                          # Rutas actualizadas
```

## 🔍 Casos de Uso

### 1. Crear un Producto Completo

```php
// Crear categoría
$category = ProductCategory::create([
    'name' => 'Ahumado',
    'description' => 'Productos ahumados',
    'active' => true
]);

// Crear familia
$family = ProductFamily::create([
    'name' => 'Ahumado en frío',
    'description' => 'Productos ahumados en frío',
    'category_id' => $category->id,
    'active' => true
]);

// Crear producto
$product = Product::create([
    'id' => $article->id,
    'species_id' => 1,
    'capture_zone_id' => 1,
    'category_id' => $category->id,
    'family_id' => $family->id,
    'article_gtin' => '1234567890123'
]);
```

### 2. Filtrar Productos por Categoría

```php
// Obtener todos los productos frescos
$frescoProducts = Product::whereHas('category', function($query) {
    $query->where('name', 'Fresco');
})->get();

// Obtener productos de categorías específicas
$products = Product::whereIn('category_id', [1, 2])->get();
```

### 3. Obtener Familias de una Categoría

```php
$frescoCategory = ProductCategory::where('name', 'Fresco')->first();
$frescoFamilies = $frescoCategory->families;
```

### 4. Estadísticas por Categoría

```php
$stats = Product::select('category_id')
    ->with('category')
    ->selectRaw('COUNT(*) as total_products')
    ->groupBy('category_id')
    ->get();
```

## 🧪 Testing

### Tests Unitarios Sugeridos

```php
class ProductCategoryTest extends TestCase
{
    public function test_can_create_category()
    {
        $category = ProductCategory::factory()->create();
        $this->assertDatabaseHas('product_categories', [
            'id' => $category->id
        ]);
    }
    
    public function test_cannot_delete_category_with_families()
    {
        $category = ProductCategory::factory()->create();
        ProductFamily::factory()->create(['category_id' => $category->id]);
        
        $this->expectException(Exception::class);
        $category->delete();
    }
}
```

### Tests de API Sugeridos

```php
class ProductCategoryApiTest extends TestCase
{
    public function test_can_list_categories()
    {
        $response = $this->getJson('/api/v2/product-categories');
        $response->assertStatus(200);
    }
    
    public function test_can_create_category()
    {
        $data = [
            'name' => 'Test Category',
            'description' => 'Test Description',
            'active' => true
        ];
        
        $response = $this->postJson('/api/v2/product-categories', $data);
        $response->assertStatus(201);
    }
}
```

## 🚀 Próximos Pasos

### 1. Migración de Datos Existentes

```php
// Script para asignar categorías por defecto a productos existentes
$products = Product::whereNull('category_id')->get();

foreach ($products as $product) {
    // Lógica para determinar categoría basada en especie o GTIN
    $category = $this->determineCategory($product);
    $product->update(['category_id' => $category->id]);
}
```

### 2. Reportes Avanzados

- Reportes por categoría y familia
- Análisis de ventas por clasificación
- Estadísticas de inventario por familia

### 3. Validaciones de Negocio

- Validar que familia pertenece a categoría correcta
- Reglas de negocio específicas por categoría
- Validaciones de GTIN por familia

### 4. Interfaz de Usuario

- Selectores jerárquicos (categoría → familia)
- Filtros avanzados en listados
- Gestión visual de categorías y familias

## 📚 Referencias

- [Laravel Eloquent Relationships](https://laravel.com/docs/eloquent-relationships)
- [Laravel API Resources](https://laravel.com/docs/eloquent-resources)
- [Laravel Validation](https://laravel.com/docs/validation)
- [Multi-Tenant Architecture](https://laravel.com/docs/multi-tenancy)

---

**Versión**: 1.0  
**Fecha**: Agosto 2025  
**Autor**: Sistema de Categorías y Familias de Productos  
**Estado**: Implementado y Documentado
