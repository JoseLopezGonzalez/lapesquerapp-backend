# Guía de Implementación: Categorías y Familias de Productos

## 📋 Resumen Ejecutivo

Esta guía documenta el proceso completo de implementación del sistema de categorías y familias de productos en PesquerApp. La implementación se realizó siguiendo las mejores prácticas de Laravel y la arquitectura multi-tenant existente.

## 🎯 Objetivos de la Implementación

1. **Agregar clasificación jerárquica** a los productos (Categorías → Familias)
2. **Mantener compatibilidad** con el sistema multi-tenant existente
3. **Proporcionar API completa** para gestión de categorías y familias
4. **Implementar validaciones** y protecciones de integridad
5. **Documentar todo el proceso** para futuras referencias

## 🏗️ Arquitectura Implementada

### Modelo de Datos
```
ProductCategory (1) ←→ (N) ProductFamily (1) ←→ (N) Product
```

### Estructura de Archivos
```
app/
├── Models/
│   ├── ProductCategory.php          # Nuevo
│   ├── ProductFamily.php            # Nuevo
│   └── Product.php                  # Modificado
├── Http/
│   ├── Controllers/v2/
│   │   ├── ProductCategoryController.php  # Nuevo
│   │   ├── ProductFamilyController.php    # Nuevo
│   │   └── ProductController.php          # Modificado
│   └── Resources/v2/
│       ├── ProductCategoryResource.php    # Nuevo
│       ├── ProductFamilyResource.php      # Nuevo
│       └── ProductResource.php            # Modificado
├── Console/Commands/
│   └── SeedTenants.php              # Nuevo
└── Traits/
    └── UsesTenantConnection.php     # Existente

database/
├── migrations/companies/
│   ├── 2025_08_08_080244_create_product_categories_table.php
│   ├── 2025_08_08_080252_create_product_families_table.php
│   ├── 2025_08_08_080401_add_category_and_family_to_products_table.php
│   └── README.md                    # Documentación
└── seeders/
    ├── ProductCategorySeeder.php
    └── ProductFamilySeeder.php

routes/
└── api.php                          # Modificado
```

## 🚀 Pasos de Implementación

### Paso 1: Crear Migraciones

#### 1.1 Migración para ProductCategories
```bash
php artisan make:migration create_product_categories_table --path=database/migrations/companies
```

**Estructura de la migración:**
```php
Schema::create('product_categories', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->text('description')->nullable();
    $table->boolean('active')->default(true);
    $table->timestamps();
});
```

#### 1.2 Migración para ProductFamilies
```bash
php artisan make:migration create_product_families_table --path=database/migrations/companies
```

**Estructura de la migración:**
```php
Schema::create('product_families', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->text('description')->nullable();
    $table->unsignedBigInteger('category_id');
    $table->boolean('active')->default(true);
    $table->timestamps();
    
    $table->foreign('category_id')->references('id')->on('product_categories')->onDelete('cascade');
});
```

#### 1.3 Migración para agregar campos a Products
```bash
php artisan make:migration add_category_and_family_to_products_table --path=database/migrations/companies
```

**Estructura de la migración:**
```php
Schema::table('products', function (Blueprint $table) {
    $table->unsignedBigInteger('category_id')->nullable()->after('id');
    $table->unsignedBigInteger('family_id')->nullable()->after('category_id');
    
    $table->foreign('category_id')->references('id')->on('product_categories')->onDelete('set null');
    $table->foreign('family_id')->references('id')->on('product_families')->onDelete('set null');
});
```

### Paso 2: Crear Modelos

#### 2.1 ProductCategory Model
```bash
php artisan make:model ProductCategory
```

**Características implementadas:**
- Trait `UsesTenantConnection` para multi-tenant
- Relaciones `families()` y `products()`
- Método `toArrayAssoc()` para serialización
- Validaciones y casts apropiados

#### 2.2 ProductFamily Model
```bash
php artisan make:model ProductFamily
```

**Características implementadas:**
- Trait `UsesTenantConnection` para multi-tenant
- Relaciones `category()` y `products()`
- Método `toArrayAssoc()` para serialización
- Validaciones y casts apropiados

#### 2.3 Actualizar Product Model
**Modificaciones realizadas:**
- Agregar campos `category_id` y `family_id` al fillable
- Agregar relaciones `category()` y `family()`
- Actualizar método `toArrayAssoc()` para incluir nuevas relaciones

### Paso 3: Crear Seeders

#### 3.1 ProductCategorySeeder
```bash
php artisan make:seeder ProductCategorySeeder
```

**Datos iniciales creados:**
- Fresco (Productos frescos sin procesar)
- Congelado (Productos congelados)

#### 3.2 ProductFamilySeeder
```bash
php artisan make:seeder ProductFamilySeeder
```

**Datos iniciales creados:**
- 8 familias distribuidas entre las 2 categorías
- Incluye familias como "Fresco entero", "Congelado eviscerado", etc.

### Paso 4: Crear Comando Personalizado

#### 4.1 SeedTenants Command
```bash
php artisan make:command SeedTenants
```

**Funcionalidades implementadas:**
- Ejecutar seeders en todos los tenants activos
- Manejo correcto de conexiones multi-tenant
- Soporte para seeders específicos o múltiples

### Paso 5: Crear Controladores

#### 5.1 ProductCategoryController
```bash
php artisan make:controller v2/ProductCategoryController
```

**Métodos implementados:**
- `index()` - Listar con filtros
- `store()` - Crear con validaciones
- `show()` - Ver con relaciones
- `update()` - Actualizar con validaciones
- `destroy()` - Eliminar con protecciones

#### 5.2 ProductFamilyController
```bash
php artisan make:controller v2/ProductFamilyController
```

**Métodos implementados:**
- `index()` - Listar con filtros y relaciones
- `store()` - Crear con validaciones
- `show()` - Ver con relaciones
- `update()` - Actualizar con validaciones
- `destroy()` - Eliminar con protecciones

#### 5.3 Actualizar ProductController
**Modificaciones realizadas:**
- Agregar filtros por `categories` y `families`
- Agregar validaciones para `categoryId` y `familyId`
- Cargar relaciones en respuestas
- Actualizar creación y actualización de productos

### Paso 6: Crear Resources

#### 6.1 ProductCategoryResource
```bash
php artisan make:resource v2/ProductCategoryResource
```

**Estructura de datos:**
```php
return [
    'id' => $this->id,
    'name' => $this->name,
    'description' => $this->description,
    'active' => $this->active,
    'createdAt' => $this->created_at,
    'updatedAt' => $this->updated_at,
];
```

#### 6.2 ProductFamilyResource
```bash
php artisan make:resource v2/ProductFamilyResource
```

**Estructura de datos:**
```php
return [
    'id' => $this->id,
    'name' => $this->name,
    'description' => $this->description,
    'categoryId' => $this->category_id,
    'category' => $this->whenLoaded('category', function () {
        return new ProductCategoryResource($this->category);
    }),
    'active' => $this->active,
    'createdAt' => $this->created_at,
    'updatedAt' => $this->updated_at,
];
```

### Paso 7: Configurar Rutas

#### 7.1 Agregar Rutas en api.php
```php
Route::apiResource('product-categories', ProductCategoryController::class);
Route::delete('product-categories', [ProductCategoryController::class, 'destroyMultiple']);
Route::apiResource('product-families', ProductFamilyController::class);
Route::delete('product-families', [ProductFamilyController::class, 'destroyMultiple']);
```

#### 7.2 Agregar Imports
```php
use App\Http\Controllers\v2\ProductCategoryController;
use App\Http\Controllers\v2\ProductFamilyController;
```

### Paso 8: Ejecutar Migraciones y Seeders

#### 8.1 Ejecutar Migraciones
```bash
php artisan tenants:migrate
```

#### 8.2 Ejecutar Seeders
```bash
php artisan tenants:seed --class=ProductCategorySeeder
php artisan tenants:seed --class=ProductFamilySeeder
```

## 🔧 Configuraciones Específicas

### Multi-Tenant Configuration

#### Trait UsesTenantConnection
Todos los modelos nuevos implementan:
```php
use App\Traits\UsesTenantConnection;

class ProductCategory extends Model
{
    use UsesTenantConnection;
    // ...
}
```

#### Comando SeedTenants
Manejo correcto de conexiones multi-tenant:
```php
config(['database.connections.tenant.database' => $tenant->database]);
DB::purge('tenant');
DB::reconnect('tenant');
config(['database.default' => 'tenant']);
```

### Validaciones Implementadas

#### ProductCategory
```php
'name' => 'required|string|min:3|max:255',
'description' => 'nullable|string|max:1000',
'active' => 'boolean'
```

#### ProductFamily
```php
'name' => 'required|string|min:3|max:255',
'description' => 'nullable|string|max:1000',
'categoryId' => 'required|exists:tenant.product_categories,id',
'active' => 'boolean'
```

#### Product (Nuevos Campos)
```php
'categoryId' => 'nullable|exists:tenant.product_categories,id',
'familyId' => 'nullable|exists:tenant.product_families,id'
```

### Protecciones de Seguridad

#### Eliminación Segura
- **ProductCategory**: No se puede eliminar si tiene familias asociadas
- **ProductFamily**: No se puede eliminar si tiene productos asociados

#### Validaciones de Existencia
- Todas las relaciones verifican existencia en la base de datos
- Uso de `exists:tenant.table_name,id` para validaciones

## 🧪 Testing y Verificación

### Verificar Migraciones
```bash
# Verificar que las migraciones se ejecutaron correctamente
php artisan tenants:migrate:status
```

### Verificar Seeders
```bash
# Verificar que los datos se crearon correctamente
php artisan tinker
>>> App\Models\ProductCategory::count()
>>> App\Models\ProductFamily::count()
```

### Probar API Endpoints
```bash
# Probar listado de categorías
curl -X GET "https://api.pesquerapp.es/api/v2/product-categories" \
  -H "Authorization: Bearer {token}" \
  -H "X-Tenant: brisamar"

# Probar creación de categoría
curl -X POST "https://api.pesquerapp.es/api/v2/product-categories" \
  -H "Authorization: Bearer {token}" \
  -H "X-Tenant: brisamar" \
  -H "Content-Type: application/json" \
  -d '{"name": "Test Category", "description": "Test Description"}'
```

## 📊 Datos Iniciales Creados

### Categorías
| ID | Nombre | Descripción |
|----|--------|-------------|
| 1 | Fresco | Productos frescos sin procesar |
| 2 | Congelado | Productos congelados |

### Familias
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

## 🚨 Consideraciones Importantes

### Multi-Tenant
- Todas las operaciones respetan la arquitectura multi-tenant
- Los datos son específicos por tenant
- Las migraciones se ejecutan en todas las bases de datos de tenants

### Integridad de Datos
- Las claves foráneas están configuradas correctamente
- Las eliminaciones en cascada están configuradas apropiadamente
- Las validaciones previenen datos inconsistentes

### Performance
- Las relaciones se cargan eficientemente con `with()`
- Los filtros están optimizados para consultas rápidas
- La paginación está implementada en todos los endpoints

## 🔄 Mantenimiento

### Agregar Nuevas Categorías
```bash
# Crear seeder para nuevas categorías
php artisan make:seeder NewCategorySeeder

# Ejecutar en todos los tenants
php artisan tenants:seed --class=NewCategorySeeder
```

### Agregar Nuevas Familias
```bash
# Crear seeder para nuevas familias
php artisan make:seeder NewFamilySeeder

# Ejecutar en todos los tenants
php artisan tenants:seed --class=NewFamilySeeder
```

### Actualizar Productos Existentes
```php
// Script para asignar categorías por defecto
$products = Product::whereNull('category_id')->get();
foreach ($products as $product) {
    $category = $this->determineCategory($product);
    $product->update(['category_id' => $category->id]);
}
```

## 📚 Documentación Relacionada

- [README de Migraciones Multi-Tenant](database/migrations/companies/README.md)
- [Documentación Técnica Completa](docs/PRODUCT_CATEGORIES_AND_FAMILIES.md)
- [Documentación de API](docs/API_PRODUCT_CATEGORIES_FAMILIES.md)

## 🎯 Próximos Pasos Sugeridos

1. **Testing**: Crear tests unitarios y de integración
2. **Frontend**: Actualizar interfaces de usuario
3. **Reportes**: Crear reportes basados en categorías y familias
4. **Validaciones**: Agregar reglas de negocio específicas
5. **Performance**: Optimizar consultas complejas

---

**Fecha de Implementación**: Agosto 2025  
**Versión**: 1.0  
**Estado**: Completado y Documentado  
**Autor**: Sistema de Categorías y Familias de Productos
