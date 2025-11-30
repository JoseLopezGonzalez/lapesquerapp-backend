# Catálogos - Productos (Products)

## ⚠️ Estado de la API
- **v1**: Obsoleta (no documentada)
- **v2**: Versión activa (este documento)

---

## 📋 Visión General

El modelo `Product` representa un **producto** del catálogo. Los productos tienen una relación especial 1:1 con `Article` donde comparten el mismo ID (misma clave primaria). `Article` almacena información básica (nombre, categoría), mientras que `Product` extiende con información específica (especies, zonas de captura, GTINs, códigos externos).

**Concepto clave**: `Product` y `Article` comparten la misma PK (relación 1:1 con mismo ID). `Article` es el catálogo base y `Product` es la especialización para productos pesqueros.

**Archivo del modelo**: `app/Models/Product.php`

---

## 🗄️ Estructura de Base de Datos

### Tabla: `products`

**Migración base**: `database/migrations/companies/2023_08_09_145449_create_products_table.php`

**Migraciones adicionales**:
- `2025_08_08_080401_add_category_and_family_to_products_table.php` - Agrega `category_id` y `family_id`
- `2025_08_08_110842_remove_category_id_from_products_table.php` - Elimina `category_id` (solo queda `family_id`)
- `2025_03_14_092724_add_a3erp_code_to_products_table.php` - Agrega `a3erp_code`

**Campos**:

| Campo | Tipo | Nullable | Descripción |
|-------|------|----------|-------------|
| `id` | bigint | NO | ID único (comparte con `articles.id`) |
| `family_id` | bigint | YES | FK a `product_families` - Familia del producto |
| `species_id` | bigint | NO | FK a `species` - Especie pesquera |
| `capture_zone_id` | bigint | NO | FK a `capture_zones` - Zona de captura |
| `article_gtin` | string | NO | GTIN del artículo |
| `box_gtin` | string | NO | GTIN de la caja |
| `pallet_gtin` | string | NO | GTIN del palet |
| `fixed_weight` | decimal(6,2) | NO | Peso fijo del producto |
| `a3erp_code` | string | YES | Código para sistema A3ERP |
| `facil_com_code` | string | YES | Código para sistema Facilcom |
| `created_at` | timestamp | NO | Fecha de creación |
| `updated_at` | timestamp | NO | Fecha de última actualización |

**Índices**:
- `id` (primary key, compartido con `articles`)
- Foreign keys a `species`, `capture_zones`, `product_families`

**Constraints**:
- `species_id` → `species.id`
- `capture_zone_id` → `capture_zones.id`
- `family_id` → `product_families.id` (onDelete: set null)

**Nota**: El campo `category_id` fue agregado y luego eliminado. La categoría se obtiene ahora a través de `family->category`.

---

## 📦 Modelo Eloquent

### Fillable Attributes

```php
protected $fillable = [
    'id',
    'article_id',  // ⚠️ Campo que no existe en BD
    'family_id',
    'species_id',
    'capture_zone_id',
    'article_gtin',
    'box_gtin',
    'pallet_gtin',
    'fixed_weight',
    'name',  // ⚠️ No existe en BD, es accessor
    'a3erp_code',
    'facil_com_code',
];
```

**Notas**:
- `id` está en fillable para permitir asignación manual (compartido con Article)
- `article_id` está en fillable pero no existe en BD
- `name` está en fillable pero es un accessor que viene de `Article`

### Traits

- `UsesTenantConnection`: Usa conexión tenant (multi-tenant)
- `HasFactory`: Para testing y seeders

---

## 🔗 Relaciones

### 1. `article()` - Artículo Base (1:1 con mismo ID)
```php
public function article()
{
    return $this->belongsTo(Article::class, 'id', 'id');
}
```
- Relación 1:1 donde ambos modelos comparten la misma clave primaria
- `Product.id === Article.id`
- `Article` contiene: `name`, `category_id` (categoría de artículo)
- **Acceso al nombre**: `$product->name` (accessor que llama a `$product->article->name`)

### 2. `species()` - Especie Pesquera
```php
public function species()
{
    return $this->belongsTo(Species::class, 'species_id');
}
```
- Relación muchos-a-uno con `Species`
- Especie pesquera del producto

### 3. `captureZone()` - Zona de Captura
```php
public function captureZone()
{
    return $this->belongsTo(CaptureZone::class, 'capture_zone_id');
}
```
- Relación muchos-a-uno con `CaptureZone`
- Zona FAO de captura

### 4. `family()` - Familia de Producto
```php
public function family()
{
    return $this->belongsTo(ProductFamily::class, 'family_id');
}
```
- Relación muchos-a-uno con `ProductFamily`
- Familia de producto (ej: "Congelado fileteado")
- La familia tiene una categoría: `family->category`

### 5. `productionNodes()` - Nodos de Producción (Legacy)
```php
public function productionNodes()
{
    return $this->belongsToMany(ProductionNode::class, 'production_node_product')->withPivot('quantity');
}
```
- **Estado**: ⚠️ Relación legacy, posiblemente no usada en v2

### 6. `rawMaterials()` - Materias Primas
```php
public function rawMaterials()
{
    return $this->has(RawMaterial::class, 'id');
}
```
- **Estado**: ⚠️ Relación poco clara, verificar uso

---

## 🔢 Accessors (Atributos Calculados)

### `getNameAttribute()`
```php
public function getNameAttribute()
{
    return $this->article->name;
}
```
- Obtiene el nombre desde `Article`
- **Problema**: Si `article` no está cargado, causa query N+1

---

## 📡 Controlador

**Archivo**: `app/Http/Controllers/v2/ProductController.php`

### Métodos del Controlador

#### `index(Request $request)` - Listar Productos
```php
GET /v2/products
```

**Filtros disponibles** (query parameters):
- `id`: Filtrar por ID
- `ids`: Filtrar por múltiples IDs (array)
- `name`: Buscar por nombre (LIKE en `article.name`)
- `species`: Array de IDs de especies
- `captureZones`: Array de IDs de zonas de captura
- `categories`: Array de IDs de categorías (filtra por `family.category_id`)
- `families`: Array de IDs de familias
- `articleGtin`: Filtrar por GTIN de artículo
- `boxGtin`: Filtrar por GTIN de caja
- `palletGtin`: Filtrar por GTIN de palet

**Query parameters**:
- `perPage`: Elementos por página (default: 14)

**Orden**: Por nombre del artículo ascendente (subquery)

**Eager loading**: Carga `['article', 'family.category', 'family']`

**Respuesta**: Collection paginada de `ProductResource`

#### `store(Request $request)` - Crear Producto
```php
POST /v2/products
```

**Request body**:
```json
{
    "name": "Filetes de atún",
    "speciesId": 1,
    "captureZoneId": 2,
    "familyId": 3,
    "articleGtin": "1234567890123",
    "boxGtin": "1234567890124",
    "palletGtin": "1234567890125",
    "a3erp_code": "ATU001",
    "facil_com_code": "FAC001"
}
```

**Validación**:
- `name`: Requerido, string 3-255 caracteres
- `speciesId`: Requerido, existe en `species`
- `captureZoneId`: Requerido, existe en `capture_zones`
- `familyId`: Opcional, existe en `product_families`
- `articleGtin`, `boxGtin`, `palletGtin`: Opcional, regex `^[0-9]{8,14}$`
- `a3erp_code`, `facil_com_code`: Opcional, string max 255

**Comportamiento**:
1. Crea `Article` primero con `name` y `category_id = 1` (hardcodeado)
2. Usa el `id` del artículo creado para crear `Product`
3. Ambos comparten el mismo ID

**Transacción**: Usa `DB::transaction()` para garantizar consistencia

**Respuesta** (201): `ProductResource`

#### `show(string $id)` - Mostrar Producto
```php
GET /v2/products/{id}
```

**Eager loading**: Carga `['article', 'species', 'captureZone', 'family.category', 'family']`

**Respuesta**: `ProductResource`

#### `update(Request $request, string $id)` - Actualizar Producto
```php
PUT /v2/products/{id}
```

**Validación**: Igual que `store()` pero todos los campos opcionales con `sometimes`

**Comportamiento**:
1. Actualiza `Article.name`
2. Actualiza campos de `Product`

**Transacción**: Usa `DB::transaction()`

**Respuesta**: `ProductResource`

#### `destroy(string $id)` - Eliminar Producto
```php
DELETE /v2/products/{id}
```

**Comportamiento**:
1. Elimina `Product`
2. Elimina `Article` con mismo ID

**Transacción**: Usa `DB::transaction()`

**Advertencia**: ⚠️ No valida si el producto está en uso (cajas, pedidos, producción)

#### `destroyMultiple(Request $request)` - Eliminar Múltiples Productos
```php
DELETE /v2/products
```

**Request body**:
```json
{
    "ids": [1, 2, 3]
}
```

#### `options()` - Opciones para Select
```php
GET /v2/products/options
```

**Respuesta**: Array con `id`, `name` (desde article), `boxGtin`
```json
[
    {
        "id": 1,
        "name": "Filetes de atún",
        "boxGtin": "1234567890124"
    },
    ...
]
```

**Ordenado**: Por nombre ascendente

---

## 📄 API Resource

**Archivo**: `app/Http/Resources/v2/ProductResource.php`

Usa el método `toArrayAssoc()` del modelo.

**Campos expuestos** (desde `toArrayAssoc()`):
```json
{
    "id": 1,
    "name": "Filetes de atún",
    "species": {
        "id": 1,
        "name": "Atún rojo"
    },
    "captureZone": {
        "id": 2,
        "name": "Fao 27 IX.a"
    },
    "category": {
        "id": 1,
        "name": "Congelado"
    },
    "family": {
        "id": 3,
        "name": "Fileteado"
    },
    "articleGtin": "1234567890123",
    "boxGtin": "1234567890124",
    "palletGtin": "1234567890125",
    "fixedWeight": 5.50,
    "a3erpCode": "ATU001",
    "facilcomCode": "FAC001"
}
```

**Nota**: `toArrayAssoc()` mergea datos de `Article` y `Product`.

---

## 🔐 Permisos y Autenticación

**Middleware requerido**:
- `auth:sanctum`: Autenticación requerida
- `role:superuser,manager,admin,store_operator`: Roles permitidos

**Rutas**: Todas bajo `/v2/products/*`

---

## 📝 Ejemplos de Uso

### Crear un Producto
```http
POST /v2/products
Content-Type: application/json
Authorization: Bearer {token}
X-Tenant: empresa1

{
    "name": "Filetes de atún congelados",
    "speciesId": 1,
    "captureZoneId": 2,
    "familyId": 3,
    "articleGtin": "1234567890123",
    "boxGtin": "1234567890124",
    "palletGtin": "1234567890125"
}
```

### Listar Productos con Filtros
```http
GET /v2/products?species[]=1&families[]=3&name=atún&perPage=20
Authorization: Bearer {token}
X-Tenant: empresa1
```

---

## 🔄 Relación Product-Article

### Patrón de Diseño

**Single Table Inheritance (STI) o Table Per Type**:
- `Article`: Tabla base para todos los tipos de artículos
- `Product`: Extensión específica para productos pesqueros
- Ambos comparten la misma PK

### Creación

1. Se crea `Article` primero
2. Se obtiene su `id`
3. Se crea `Product` con ese mismo `id`

### Actualización

- `Article`: Solo se actualiza `name`
- `Product`: Se actualizan campos específicos

### Eliminación

- Se eliminan ambos en la misma transacción
- Primero `Product`, luego `Article`

---

## Observaciones Críticas y Mejoras Recomendadas

### ⚠️ Campos en Fillable Que No Existen

1. **article_id en Fillable** (`app/Models/Product.php:25`)
   - Campo `article_id` está en fillable pero no existe en BD
   - **Líneas**: 25
   - **Problema**: No tiene efecto, confunde
   - **Recomendación**: Eliminar del fillable

2. **name en Fillable** (`app/Models/Product.php:33`)
   - Campo `name` está en fillable pero es un accessor
   - **Líneas**: 33
   - **Problema**: No se puede asignar directamente
   - **Recomendación**: Eliminar del fillable

### ⚠️ Relación Product-Article Compleja

3. **Relación 1:1 con Mismo ID** (`app/Models/Product.php:99-102`)
   - Usa `belongsTo(Article::class, 'id', 'id')` (misma PK)
   - **Líneas**: 99-102
   - **Estado**: Patrón no estándar, puede confundir
   - **Recomendación**: Documentar claramente este patrón

4. **category_id Hardcodeado en Store** (`app/Http/Controllers/v2/ProductController.php:111`)
   - Al crear Article, usa `category_id = 1` hardcodeado
   - **Líneas**: 111
   - **Problema**: No es configurable
   - **Recomendación**: 
     - Permitir especificar `category_id` en request
     - O usar categoría por defecto desde configuración

### ⚠️ Eliminación Sin Validaciones

5. **No Valida Uso Antes de Eliminar** (`app/Http/Controllers/v2/ProductController.php:207-217`)
   - No valida si el producto está en uso (cajas, pedidos, producción)
   - **Líneas**: 207-217
   - **Problema**: Puede eliminar productos en uso, rompiendo relaciones
   - **Recomendación**: 
     - Validar relaciones antes de eliminar
     - O usar soft deletes

### ⚠️ Query N+1 en getNameAttribute()

6. **Accessor Sin Eager Loading** (`app/Models/Product.php:83-86`)
   - `getNameAttribute()` accede a `$this->article->name` sin validar carga
   - **Líneas**: 83-86
   - **Problema**: Query N+1 si se accede desde colección sin eager loading
   - **Recomendación**: 
     - Siempre usar eager loading en controlador (ya se hace)
     - O validar que esté cargado

### ⚠️ Ordenamiento con Subquery

7. **Ordenamiento Ineficiente** (`app/Http/Controllers/v2/ProductController.php:76-81`)
   - Usa subquery en `orderBy()` para ordenar por `article.name`
   - **Líneas**: 76-81
   - **Problema**: Puede ser lento con muchos registros
   - **Recomendación**: 
     - Usar JOIN en lugar de subquery
     - O ordenar después de cargar con eager loading

### ⚠️ Filtro de boxGtin Incorrecto

8. **Filtro boxGtin Usa articleGtin** (`app/Http/Controllers/v2/ProductController.php:67`)
   - Filtro de `boxGtin` usa `$request->articleGtin` en lugar de `$request->boxGtin`
   - **Líneas**: 67
   - **Problema**: Filtro no funciona correctamente
   - **Recomendación**: Corregir a `$request->boxGtin`

### ⚠️ Validación de GTIN

9. **Regex de GTIN Permite 8-14 Dígitos** (`app/Http/Controllers/v2/ProductController.php:99-101`)
   - Valida que GTIN tenga 8-14 dígitos
   - **Líneas**: 99-101
   - **Estado**: Correcto para EAN-8 y EAN-13, pero puede no validar formato completo
   - **Recomendación**: Documentar formato esperado

### ⚠️ Código Legacy

10. **Relación productionNodes Legacy** (`app/Models/Product.php:88-91`)
    - Relación con `ProductionNode` posiblemente no usada en v2
    - **Líneas**: 88-91
    - **Problema**: Código muerto si no se usa
    - **Recomendación**: Verificar uso y eliminar si no se necesita

11. **Método rawMaterials Confuso** (`app/Models/Product.php:93-96`)
    - Usa `has()` en lugar de relación estándar
    - **Líneas**: 93-96
    - **Problema**: Sintaxis poco clara
    - **Recomendación**: Aclarar relación o corregir sintaxis

### ⚠️ Comentarios Confusos

12. **Comentarios en Relaciones** (`app/Models/Product.php:45, 50`)
    - Comentarios dicen "No se bien porque no indica que el id es el que relaciona"
    - **Líneas**: 45, 50
    - **Problema**: Comentarios obsoletos que confunden
    - **Recomendación**: Eliminar o actualizar comentarios

### ⚠️ Código Comentado

13. **Relación article() Comentada** (`app/Models/Product.php:38-41`)
    - Hay código comentado de una relación anterior
    - **Líneas**: 38-41
    - **Problema**: Código muerto
    - **Recomendación**: Eliminar código comentado

### ⚠️ toArrayAssoc() Complejo

14. **Método toArrayAssoc() Complejo** (`app/Models/Product.php:60-79`)
    - Mergea datos de Article y Product con múltiples `optional()`
    - **Líneas**: 60-79
    - **Problema**: Difícil de mantener y debuggear
    - **Recomendación**: Simplificar o usar Resource class

### ⚠️ Sin Validación de GTINs Únicos

15. **No Valida Unicidad de GTINs** (`app/Http/Controllers/v2/ProductController.php`)
    - No valida que GTINs sean únicos en BD
    - **Problema**: Pueden haber productos duplicados con mismo GTIN
    - **Recomendación**: 
      - Agregar unique constraints en BD
      - O validar en controlador

---

**Última actualización**: Documentación generada desde código fuente en fecha de generación.

