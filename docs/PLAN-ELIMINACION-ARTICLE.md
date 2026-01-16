# Plan de Eliminación de Article y Migración a Product

## 📋 Resumen Ejecutivo

Este documento detalla el plan completo para **eliminar la entidad `Article`** y consolidar toda su funcionalidad en `Product`. El objetivo es simplificar la arquitectura eliminando una abstracción innecesaria que solo se usa para productos.

**Fecha de creación**: 2026-01-16  
**Estado**: Plan de implementación  
**Prioridad**: Media (mejora arquitectónica, no crítica)

---

## 🎯 Objetivos

1. **Preservar TODOS los datos existentes** - Garantía de que ningún dato se pierde
2. **Eliminar la tabla `articles`** y su modelo asociado (solo después de migrar datos)
3. **Mover el campo `name`** de `Article` a `Product` (migración segura con validación)
4. **Eliminar la relación 1:1** entre `Product` y `Article` (ID compartido)
5. **Actualizar todas las referencias** a `Article` en el código
6. **Mantener compatibilidad** con el código existente durante la transición
7. **Eliminar `ArticleCategory`** si no se usa en otros lugares (verificar `Store`)

## 🛡️ Garantías de Preservación de Datos

**IMPORTANTE**: Este plan garantiza que **NINGÚN dato se perderá** mediante:

1. ✅ **Backup completo** antes de cualquier cambio
2. ✅ **Migración de datos ANTES de eliminar código** (primero mover datos, luego actualizar código)
3. ✅ **Validaciones exhaustivas** en cada paso
4. ✅ **Operaciones reversibles** con rollback plan
5. ✅ **Verificación de integridad** antes y después de cada paso
6. ✅ **Transacciones de base de datos** para garantizar atomicidad
7. ✅ **No eliminar `articles` hasta verificar que todos los datos fueron migrados**

---

## 📊 Análisis de Impacto

### Campos a Migrar

**De `articles` a `products`:**
- `name` (string) → Agregar a `products.name`
- `category_id` (bigint, FK a `article_categories`) → **NO migrar** (solo se usa para discriminar tipo, no necesario)

### Dependencias Identificadas

#### 1. Modelos que usan Article
- ✅ `Product` - Relación `belongsTo(Article::class, 'id', 'id')`
- ✅ `ArticleCategory` - Relación `hasMany(Article::class)` - **Verificar si se usa en Store**

#### 2. Controladores que usan Article
- ✅ `ProductController` - Crea/actualiza/elimina Article
- ✅ `StoreController` - Accede a `$product->article->name`

#### 3. Modelos que acceden a Article a través de Product
- ✅ `Order` - Usa `$product->article->name` en `getSummaryAttribute()`
- ✅ `Box` - Usa `article_id` pero apunta a `products` (ya correcto)
- ✅ `OrderPlannedProductDetail` - No usa Article directamente
- ✅ `ProductionOutput` - No usa Article directamente
- ✅ `RawMaterialReceptionProduct` - No usa Article directamente
- ✅ `CeboDispatchProduct` - No usa Article directamente

#### 4. Exports que usan Article
- ✅ `BoxesReportExport` - Accede a `$product->article->name`
- ✅ `CeboDispatchA3erpExport` - Accede a `$product->product->article->name`
- ✅ `CeboDispatchA3erp2Export` - Accede a `$product->product->article->name`
- ✅ `CeboDispatchFacilcomExport` - Accede a `$product->product->article->name`
- ✅ `RawMaterialReceptionA3erpExport` - Accede a `$product->product->article->name`
- ✅ `RawMaterialReceptionFacilcomExport` - Accede a `$product->product->article->name`

#### 5. Servicios que usan Article
- ✅ `OrderStatisticsService` - Hace JOIN con `articles` para ordenar por nombre

#### 6. Queries que hacen JOIN con articles
- ✅ `ProductController::index()` - Ordena por `Article::select('name')`
- ✅ `ProductController::options()` - JOIN con articles para obtener name
- ✅ `Order::scopeJoinBoxesAndArticles()` - JOIN con articles
- ✅ `Order::scopeWhereBoxArticleSpecies()` - JOIN con articles (pero usa `articles.species_id` que no existe)
- ✅ `OrderStatisticsService::calculateAmountDetails()` - JOIN con articles
- ✅ `OrderStatisticsService::calculateQuantityDetails()` - JOIN con articles
- ✅ `Pallet::scopeJoinProducts()` - JOIN con products (ya correcto)

---

## 🔍 Análisis Detallado por Componente

### 1. Base de Datos

#### Tabla `articles`
- **Campos**: `id`, `name`, `category_id`, `created_at`, `updated_at`
- **Foreign Keys**: 
  - `category_id` → `article_categories.id`
- **Datos a migrar**: Todos los `name` de `articles` a `products.name` donde `articles.id = products.id`

#### Tabla `products`
- **Cambios necesarios**:
  - Agregar columna `name` (string, NOT NULL)
  - Migrar datos desde `articles.name` donde `articles.id = products.id`
  - Eliminar campo `article_id` del fillable (no existe en BD, solo en código)

#### Tabla `article_categories`
- **Análisis**: 
  - Se usa en `Article` (a eliminar)
  - Se usa en `Store` (pero `Store.category_id` NO existe en la migración, solo en fillable)
  - **Decisión**: Eliminar si `Store` realmente no lo usa en BD

#### Tabla `stores`
- **Análisis**: 
  - `fillable` incluye `category_id`
  - Migración NO incluye `category_id`
  - Relación `categoria()` apunta a `ArticleCategory`
  - **Decisión**: Verificar si realmente existe en BD. Si no, eliminar del modelo.

---

## 📝 Plan de Implementación

### Fase 1: Preparación y Análisis de Datos

#### 1.1 Verificar datos existentes
```sql
-- Verificar que todos los products tienen Article correspondiente
SELECT COUNT(*) FROM products p
LEFT JOIN articles a ON p.id = a.id
WHERE a.id IS NULL;

-- Verificar que todos los Articles tienen Product correspondiente
SELECT COUNT(*) FROM articles a
LEFT JOIN products p ON a.id = p.id
WHERE p.id IS NULL;

-- Verificar si hay Articles sin Product (no debería haber)
SELECT * FROM articles a
LEFT JOIN products p ON a.id = p.id
WHERE p.id IS NULL;
```

#### 1.2 Verificar Store.category_id
```sql
-- Verificar si stores tiene category_id en BD
SHOW COLUMNS FROM stores LIKE 'category_id';

-- Si existe, verificar uso
SELECT COUNT(*) FROM stores WHERE category_id IS NOT NULL;
```

#### 1.3 Backup de datos
**CRÍTICO**: Hacer backup completo antes de proceder

```sql
-- Backup completo de ambas tablas
CREATE TABLE articles_backup AS SELECT * FROM articles;
CREATE TABLE products_backup AS SELECT * FROM products;

-- Verificar que el backup se creó correctamente
SELECT COUNT(*) as articles_count FROM articles;
SELECT COUNT(*) as articles_backup_count FROM articles_backup;
SELECT COUNT(*) as products_count FROM products;
SELECT COUNT(*) as products_backup_count FROM products_backup;

-- Deben ser iguales
-- Si no coinciden, NO PROCEDER hasta resolver
```

#### 1.4 Verificar integridad referencial
```sql
-- Verificar que TODOS los products tienen Article (debe ser 0)
SELECT COUNT(*) as products_sin_article
FROM products p
LEFT JOIN articles a ON p.id = a.id
WHERE a.id IS NULL;

-- Verificar que TODOS los Articles tienen Product (debe ser 0)
SELECT COUNT(*) as articles_sin_product
FROM articles a
LEFT JOIN products p ON a.id = p.id
WHERE p.id IS NULL;

-- Verificar que todos los Articles tienen name (debe ser 0)
SELECT COUNT(*) as articles_sin_name
FROM articles
WHERE name IS NULL OR name = '';

-- Verificar duplicados de name en articles (información)
SELECT name, COUNT(*) as cantidad
FROM articles
GROUP BY name
HAVING cantidad > 1;
```

**Si alguno de estos queries retorna datos inesperados, NO PROCEDER hasta resolver.**

---

### Fase 2: Migración de Datos

**⚠️ CRÍTICO**: Esta fase debe ejecutarse ANTES de actualizar el código. Los datos deben estar migrados completamente antes de cambiar cualquier línea de código.

#### 2.1 Crear migración para agregar `name` a `products`
```php
// database/migrations/companies/YYYY_MM_DD_HHMMSS_add_name_to_products_table.php

Schema::table('products', function (Blueprint $table) {
    if (!Schema::hasColumn('products', 'name')) {
        $table->string('name')->nullable()->after('id');
    }
});
```

**Ejecutar migración**: `php artisan tenants:migrate`

#### 2.2 Verificar antes de migrar datos
```sql
-- Verificar cuántos products ya tienen name (debe ser 0 o bajo)
SELECT COUNT(*) as products_con_name
FROM products
WHERE name IS NOT NULL AND name != '';

-- Verificar cuántos products necesitan migración
SELECT COUNT(*) as products_necesitan_migracion
FROM products p
INNER JOIN articles a ON p.id = a.id
WHERE p.name IS NULL OR p.name = '';
```

#### 2.3 Migrar datos de `articles.name` a `products.name` (CON TRANSACCIÓN)
```sql
-- INICIAR TRANSACCIÓN (en caso de error, hacer ROLLBACK)
START TRANSACTION;

-- Migrar los datos
UPDATE products p
INNER JOIN articles a ON p.id = a.id
SET p.name = a.name
WHERE p.name IS NULL OR p.name = '';

-- VERIFICAR que la migración fue exitosa
-- Debe retornar 0 (todos los products tienen name ahora)
SELECT COUNT(*) as products_sin_name_despues_migracion
FROM products p
INNER JOIN articles a ON p.id = a.id
WHERE p.name IS NULL OR p.name = '';

-- Verificar que todos los names coinciden
SELECT COUNT(*) as nombres_no_coinciden
FROM products p
INNER JOIN articles a ON p.id = a.id
WHERE p.name != a.name;

-- Si ambos queries retornan 0, la migración fue exitosa
-- Si no, hacer ROLLBACK:
-- ROLLBACK;

-- Si todo está bien, confirmar:
COMMIT;
```

#### 2.4 Verificar integridad después de migrar
```sql
-- Verificar que TODOS los products tienen name (debe ser 0)
SELECT COUNT(*) as products_sin_name
FROM products
WHERE name IS NULL OR name = '';

-- Verificar que todos los names fueron migrados correctamente
-- Comparar products.name con articles.name (deben coincidir)
SELECT p.id, p.name as product_name, a.name as article_name
FROM products p
INNER JOIN articles a ON p.id = a.id
WHERE p.name != a.name OR p.name IS NULL OR a.name IS NULL;

-- Si este query retorna filas, hay un problema. Revisar antes de continuar.
```

#### 2.5 Hacer `name` NOT NULL después de migrar (solo si la verificación fue exitosa)
```php
// database/migrations/companies/YYYY_MM_DD_HHMMSS_make_products_name_not_null.php

// PRIMERO verificar que no hay NULLs
// Si hay, NO ejecutar esta migración

Schema::table('products', function (Blueprint $table) {
    // Solo ejecutar si 2.4 verificó que todos tienen name
    $table->string('name')->nullable(false)->change();
});
```

#### 2.6 Agregar índice único para `name` (si es necesario)
```php
// database/migrations/companies/YYYY_MM_DD_HHMMSS_add_unique_index_to_products_name.php

// ANTES de agregar índice único, verificar duplicados:
// SELECT name, COUNT(*) FROM products GROUP BY name HAVING COUNT(*) > 1;
// Si hay duplicados, resolver primero

Schema::table('products', function (Blueprint $table) {
    // Solo agregar si no hay duplicados
    $table->unique('name'); // O unique(['name', 'tenant_id']) si multi-tenant
});
```

**⚠️ IMPORTANTE**: NO eliminar la tabla `articles` hasta completar TODA la Fase 4 (actualización de código) y verificar que todo funciona correctamente.

---

### Fase 3: Actualización de Modelos

#### 3.1 Actualizar `Product` Model

**Cambios en `app/Models/Product.php`:**

1. **Agregar `name` al fillable** (ya está, pero verificar)
2. **Eliminar relación `article()`**
3. **Eliminar accessor `getNameAttribute()`** (ya no necesario, `name` es campo real)
4. **Actualizar `toArrayAssoc()`** - Eliminar `$this->article->toArrayAssoc()`
5. **Actualizar validaciones en `boot()`**:
   - Eliminar validación `if ($product->article && empty($product->article->name))`
   - Agregar validación directa de `name`
   - Agregar validación de `name` único
6. **Eliminar import de `Article`**

**Código específico a cambiar:**

```php
// ANTES:
protected $fillable = [
    'id',
    'article_id', // ❌ Eliminar (no existe en BD)
    'name', // Ya está pero es accessor
    // ...
];

public function getNameAttribute() {
    return $this->article ? $this->article->name : null; // ❌ Eliminar
}

public function article() {
    return $this->belongsTo(Article::class, 'id', 'id'); // ❌ Eliminar
}

public function toArrayAssoc() {
    return array_merge(
        $this->article ? ($this->article->toArrayAssoc() ?? []) : [], // ❌ Eliminar
        [
            'name' => $this->name, // Ya está pero viene de accessor
            // ...
        ]
    );
}

// DESPUÉS:
protected $fillable = [
    'id',
    'name', // ✅ Campo real ahora
    'family_id',
    'species_id',
    // ... resto igual
];

// ✅ Eliminar getNameAttribute() - name es campo directo
// ✅ Eliminar article() - relación eliminada
// ✅ Eliminar import use App\Models\Article;

public function toArrayAssoc() {
    return [
        'id' => $this->id,
        'name' => $this->name, // ✅ Campo directo
        'species' => $this->species ? ($this->species->toArrayAssoc() ?? []) : [],
        // ... resto igual
    ];
}

// ✅ Actualizar boot() para validar name directamente
static::saving(function ($product) {
    // Validar name no vacío
    if (empty($product->name)) {
        throw ValidationException::withMessages([
            'name' => 'El nombre del producto no puede estar vacío.',
        ]);
    }
    
    // Validar name único
    $existing = self::where('name', $product->name)
        ->where('id', '!=', $product->id ?? 0)
        ->first();
    
    if ($existing) {
        throw ValidationException::withMessages([
            'name' => 'Ya existe un producto con este nombre.',
        ]);
    }
    
    // ... resto de validaciones igual
});
```

#### 3.2 Eliminar `Article` Model
- **Archivo**: `app/Models/Article.php` → **ELIMINAR**

#### 3.3 Actualizar `ArticleCategory` Model (si se mantiene para Store)

**Análisis de `Store`:**
- `Store.fillable` incluye `category_id`
- `Store.categoria()` apunta a `ArticleCategory`
- **PERO**: La migración de `stores` NO tiene `category_id`

**Decisión**:
- Si `Store.category_id` NO existe en BD → Eliminar del modelo y eliminar `ArticleCategory`
- Si `Store.category_id` SÍ existe en BD → Mantener `ArticleCategory` solo para Store

**Si se elimina `ArticleCategory`:**
- Eliminar `app/Models/ArticleCategory.php`
- Eliminar relación en `Store::categoria()`
- Eliminar `category_id` del fillable de `Store`

---

### Fase 4: Actualización de Controladores

#### 4.1 `ProductController`

**Cambios en `app/Http/Controllers/v2/ProductController.php`:**

1. **`index()` - Eliminar eager loading de `article`**
   ```php
   // ANTES:
   $query->with(['article', 'family.category', 'family']);
   
   // DESPUÉS:
   $query->with(['family.category', 'family']); // ✅ Eliminar 'article'
   ```

2. **`index()` - Cambiar filtro por nombre**
   ```php
   // ANTES:
   if ($request->has('name')) {
       $query->whereHas('article', function ($query) use ($request) {
           $query->where('name', 'like', '%' . $request->name . '%');
       });
   }
   
   // DESPUÉS:
   if ($request->has('name')) {
       $query->where('name', 'like', '%' . $request->name . '%'); // ✅ Directo
   }
   ```

3. **`index()` - Cambiar ordenamiento**
   ```php
   // ANTES:
   $query->orderBy(
       Article::select('name')
           ->whereColumn('articles.id', 'products.id'),
       'asc'
   );
   
   // DESPUÉS:
   $query->orderBy('name', 'asc'); // ✅ Directo
   ```

4. **`store()` - Eliminar creación de Article**
   ```php
   // ANTES:
   DB::transaction(function () use (&$articleId, $validated) {
       $article = Article::create([
           'name' => $validated['name'],
           'category_id' => 1,
       ]);
       $articleId = $article->id;
       
       Product::create([
           'id' => $articleId,
           // ...
       ]);
   });
   
   // DESPUÉS:
   DB::transaction(function () use (&$productId, $validated) {
       $product = Product::create([
           'name' => $validated['name'], // ✅ Agregar name directamente
           'species_id' => $validated['speciesId'],
           // ... resto igual
       ]);
       $productId = $product->id;
   });
   ```

5. **`update()` - Eliminar actualización de Article**
   ```php
   // ANTES:
   $product = Product::findOrFail($id);
   $article = Article::findOrFail($id);
   
   DB::transaction(function () use ($article, $product, $validated) {
       $article->update(['name' => $validated['name']]);
       $product->update([/* ... */]);
   });
   
   // DESPUÉS:
   $product = Product::findOrFail($id);
   
   DB::transaction(function () use ($product, $validated) {
       $product->update([
           'name' => $validated['name'], // ✅ Agregar name
           // ... resto igual
       ]);
   });
   ```

6. **`destroy()` y `destroyMultiple()` - Eliminar eliminación de Article**
   ```php
   // ANTES:
   DB::transaction(function () use ($id) {
       $product->delete();
       Article::where('id', $id)->delete();
   });
   
   // DESPUÉS:
   $product->delete(); // ✅ Solo eliminar Product
   ```

7. **`options()` - Eliminar JOIN con articles**
   ```php
   // ANTES:
   $products = Product::join('articles', 'products.id', '=', 'articles.id')
       ->select('products.id', 'articles.name', 'products.box_gtin as boxGtin')
       ->orderBy('articles.name', 'asc')
       ->get();
   
   // DESPUÉS:
   $products = Product::select('id', 'name', 'box_gtin as boxGtin')
       ->orderBy('name', 'asc') // ✅ Directo
       ->get();
   ```

8. **Eliminar import de `Article`**
   ```php
   // ANTES:
   use App\Models\Article;
   use App\Models\Product;
   
   // DESPUÉS:
   use App\Models\Product; // ✅ Eliminar Article
   ```

#### 4.2 `StoreController`

**Cambios en `app/Http/Controllers/v2/StoreController.php`:**

1. **`inventory()` - Cambiar acceso a name**
   ```php
   // ANTES:
   'name' => $product->article->name,
   
   // DESPUÉS:
   'name' => $product->name, // ✅ Directo
   ```

2. **Eliminar eager loading de `article`** (si existe)
   ```php
   // Si hay:
   $products = Product::with('article')->get();
   
   // Cambiar a:
   $products = Product::all(); // ✅ Ya no necesario
   ```

---

### Fase 5: Actualización de Modelos Relacionados

#### 5.1 `Order` Model

**Cambios en `app/Models/Order.php`:**

1. **`getSummaryAttribute()` - Cambiar acceso a name**
   ```php
   // ANTES:
   'product' => [
       'article' => [
           'id' => $product->article->id,
           'name' => $product->article->name,
       ],
       // ...
   ],
   
   // DESPUÉS:
   'product' => [
       'id' => $product->id,
       'name' => $product->name, // ✅ Directo
       // ...
   ],
   ```

2. **`scopeJoinBoxesAndArticles()` - Eliminar JOIN con articles**
   ```php
   // ANTES:
   return $query
       ->join('pallets', 'pallets.order_id', '=', 'orders.id')
       ->join('pallet_boxes', 'pallet_boxes.pallet_id', '=', 'pallets.id')
       ->join('boxes', 'boxes.id', '=', 'pallet_boxes.box_id')
       ->join('articles', 'articles.id', '=', 'boxes.article_id');
   
   // DESPUÉS:
   return $query
       ->join('pallets', 'pallets.order_id', '=', 'orders.id')
       ->join('pallet_boxes', 'pallet_boxes.pallet_id', '=', 'pallets.id')
       ->join('boxes', 'boxes.id', '=', 'pallet_boxes.box_id')
       ->join('products', 'products.id', '=', 'boxes.article_id'); // ✅ Cambiar a products
   ```

3. **`scopeWhereBoxArticleSpecies()` - Corregir JOIN**
   ```php
   // ANTES:
   public function scopeWhereBoxArticleSpecies($query, $speciesId) {
       if ($speciesId) {
           $query->where('articles.species_id', $speciesId); // ❌ articles no tiene species_id
       }
       return $query;
   }
   
   // DESPUÉS:
   public function scopeWhereBoxArticleSpecies($query, $speciesId) {
       if ($speciesId) {
           $query->join('products', 'products.id', '=', 'boxes.article_id')
                 ->where('products.species_id', $speciesId); // ✅ Correcto
       }
       return $query;
   }
   ```

#### 5.2 `Box` Model

**Cambios en `app/Models/Box.php`:**

1. **`validateBoxRules()` - Ya correcto, solo verificar**
   ```php
   // Ya usa Product::find($this->article_id) ✅ Correcto
   // No necesita cambios
   ```

2. **`article()` y `product()` - Ya apuntan a Product ✅**
   ```php
   // Ya están correctos:
   public function article() {
       return $this->belongsTo(Product::class, 'article_id'); // ✅ Correcto
   }
   
   public function product() {
       return $this->belongsTo(Product::class, 'article_id'); // ✅ Correcto
   }
   ```

**Nota**: El campo se llama `article_id` pero apunta a `products`. Podríamos renombrarlo a `product_id` en el futuro, pero no es crítico ahora.

---

### Fase 6: Actualización de Exports

#### 6.1 `BoxesReportExport`

**Cambios en `app/Exports/v2/BoxesReportExport.php`:**

1. **`map()` - Cambiar acceso a name**
   ```php
   // ANTES:
   $product = $box->product;
   $article = $product ? $product->article : null;
   // ...
   $article ? $article->name : '-',
   
   // DESPUÉS:
   $product = $box->product;
   // ...
   $product ? $product->name : '-', // ✅ Directo
   ```

2. **Eliminar eager loading de `article`** (si existe en query)

#### 6.2 `CeboDispatchA3erpExport`

**Cambios en `app/Exports/v2/CeboDispatchA3erpExport.php`:**

1. **`collection()` - Eliminar eager loading de `article`**
   ```php
   // ANTES:
   return $query->with([
       'supplier',
       'products.product.article' // ❌ Eliminar .article
   ])->get();
   
   // DESPUÉS:
   return $query->with([
       'supplier',
       'products.product' // ✅ Solo product
   ])->get();
   ```

2. **`map()` - Cambiar acceso a name**
   ```php
   // ANTES:
   $productModel = $product->product;
   $article = $productModel ? $productModel->article : null;
   // ...
   $article ? $article->name : '-',
   
   // DESPUÉS:
   $productModel = $product->product;
   // ...
   $productModel ? $productModel->name : '-', // ✅ Directo
   ```

#### 6.3 `CeboDispatchA3erp2Export`
- **Mismos cambios** que `CeboDispatchA3erpExport`

#### 6.4 `CeboDispatchFacilcomExport`
- **Mismos cambios** que `CeboDispatchA3erpExport`

#### 6.5 `RawMaterialReceptionA3erpExport`
- **Mismos cambios** que `CeboDispatchA3erpExport`

#### 6.6 `RawMaterialReceptionFacilcomExport`
- **Mismos cambios** que `CeboDispatchA3erpExport`

---

### Fase 7: Actualización de Servicios

#### 7.1 `OrderStatisticsService`

**Cambios en `app/Services/v2/OrderStatisticsService.php`:**

1. **`calculateAmountDetails()` - Eliminar JOIN con articles**
   ```php
   // ANTES:
   if ($groupBy === 'product') {
       $query->join('articles', 'products.id', '=', 'articles.id');
   }
   // ...
   $groupByField = match ($groupBy) {
       'product' => 'articles.name', // ❌
   };
   
   // DESPUÉS:
   // ✅ Eliminar JOIN con articles
   // ...
   $groupByField = match ($groupBy) {
       'product' => 'products.name', // ✅ Directo
   };
   ```

2. **`calculateQuantityDetails()` - Eliminar JOIN con articles**
   ```php
   // ANTES:
   $query = Order::query()
       ->join('pallets', 'pallets.order_id', '=', 'orders.id')
       ->join('pallet_boxes', 'pallet_boxes.pallet_id', '=', 'pallets.id')
       ->join('boxes', 'boxes.id', '=', 'pallet_boxes.box_id')
       ->join('articles', 'articles.id', '=', 'boxes.article_id')
       ->join('products', 'products.id', '=', 'articles.id'); // ❌ Redundante
   
   // DESPUÉS:
   $query = Order::query()
       ->join('pallets', 'pallets.order_id', '=', 'orders.id')
       ->join('pallet_boxes', 'pallet_boxes.pallet_id', '=', 'pallets.id')
       ->join('boxes', 'boxes.id', '=', 'pallet_boxes.box_id')
       ->join('products', 'products.id', '=', 'boxes.article_id'); // ✅ Directo
   ```

---

### Fase 8: Actualización de Migraciones

#### 8.1 Crear migración para agregar `name` a `products`
- Ver Fase 2.1

#### 8.2 Crear migración para eliminar tabla `articles`
```php
// database/migrations/companies/YYYY_MM_DD_HHMMSS_drop_articles_table.php

Schema::dropIfExists('articles');
```

#### 8.3 Crear migración para eliminar tabla `article_categories` (si no se usa en Store)
```php
// database/migrations/companies/YYYY_MM_DD_HHMMSS_drop_article_categories_table.php

// Primero eliminar FK de stores si existe
Schema::table('stores', function (Blueprint $table) {
    if (Schema::hasColumn('stores', 'category_id')) {
        $table->dropForeign(['category_id']);
        $table->dropColumn('category_id');
    }
});

Schema::dropIfExists('article_categories');
```

---

### Fase 9: Limpieza de Código

#### 9.1 Eliminar archivos
- ✅ `app/Models/Article.php`
- ✅ `app/Models/ArticleCategory.php` (si no se usa en Store)
- ✅ `database/factories/ArticleFactory.php`
- ✅ Migraciones de `articles` y `article_categories` (marcar como obsoletas, no eliminar por historial)

#### 9.2 Eliminar imports
- Buscar y eliminar todos los `use App\Models\Article;`
- Buscar y eliminar todos los `use App\Models\ArticleCategory;` (si se elimina)

#### 9.3 Limpiar fillable de Product
- Eliminar `'article_id'` del fillable (no existe en BD)

---

### Fase 10: Actualización de Documentación

#### 10.1 Documentación de código
- Actualizar `docs/catalogos/40-Productos.md`
- Actualizar `docs/referencia/95-Modelos-Referencia.md`
- Eliminar referencias a Article

#### 10.2 Actualizar PROBLEMAS-CRITICOS.md
- Marcar problema 23 como resuelto

---

## ⚠️ Puntos Críticos y Consideraciones

### 1. Integridad de Datos ⚠️ CRÍTICO
- **Riesgo**: Si hay Products sin Article correspondiente, perderán el nombre
- **Mitigación**: 
  - ✅ Verificar en Fase 1.1 y 1.4 que todos los Products tienen Article
  - ✅ NO proceder si hay Products sin Article
  - ✅ Backup completo antes de cualquier cambio
  - ✅ Migrar datos con transacciones (ROLLBACK disponible)

### 2. Pérdida de datos durante migración ⚠️ CRÍTICO
- **Riesgo**: Si la migración falla parcialmente, algunos products pueden quedar sin name
- **Mitigación**:
  - ✅ Usar transacciones SQL (START TRANSACTION / COMMIT / ROLLBACK)
  - ✅ Verificar después de cada paso de migración
  - ✅ NO hacer `name` NOT NULL hasta verificar que todos tienen name
  - ✅ Mantener tabla `articles` intacta hasta verificar que todo funciona

### 3. Store.category_id
- **Riesgo**: Si Store realmente usa ArticleCategory, no podemos eliminarlo
- **Mitigación**: Verificar en Fase 1.2 si existe en BD

### 4. Validación de nombre único
- **Riesgo**: Article validaba nombre único, Product debe hacerlo también
- **Mitigación**: Agregar validación en `Product::boot()` (ver Fase 3.1)

### 5. Ordenamiento por nombre
- **Riesgo**: Queries que ordenan por `articles.name` fallarán después de eliminar tabla
- **Mitigación**: 
  - ✅ Actualizar todos los queries ANTES de eliminar tabla `articles`
  - ✅ NO eliminar tabla hasta que todo el código esté actualizado

### 6. Eager Loading
- **Riesgo**: Código que hace `->with('article')` fallará después de eliminar modelo
- **Mitigación**: 
  - ✅ Eliminar todos los eager loadings ANTES de eliminar modelo
  - ✅ Actualizar código en Fase 4, 5, 6 antes de Fase 8

### 7. Accessors
- **Riesgo**: Código que usa `$product->name` (accessor) puede fallar durante transición
- **Mitigación**: 
  - ✅ Eliminar accessor DESPUÉS de migrar datos a BD
  - ✅ El campo `name` estará en BD, no necesitará accessor

### 8. Orden de ejecución ⚠️ CRÍTICO
- **Riesgo**: Si se elimina código antes de migrar datos, se puede perder acceso a datos
- **Mitigación**: 
  - ✅ **SIEMPRE migrar datos PRIMERO** (Fase 2)
  - ✅ **DESPUÉS actualizar código** (Fases 3-7)
  - ✅ **FINALMENTE eliminar tablas** (Fase 8)

---

## 📋 Checklist de Implementación

### Pre-implementación
- [ ] Backup completo de BD
- [ ] Verificar que todos los Products tienen Article (Fase 1.1)
- [ ] Verificar Store.category_id (Fase 1.2)
- [ ] Crear branch de git para la refactorización

### Migración de Datos
- [ ] Crear migración para agregar `name` a `products`
- [ ] Ejecutar migración de datos (UPDATE products SET name = ...)
- [ ] Verificar que todos los products tienen name
- [ ] Hacer `name` NOT NULL

### Actualización de Código
- [ ] Actualizar `Product` model (Fase 3.1)
- [ ] Actualizar `ProductController` (Fase 4.1)
- [ ] Actualizar `StoreController` (Fase 4.2)
- [ ] Actualizar `Order` model (Fase 5.1)
- [ ] Actualizar todos los Exports (Fase 6)
- [ ] Actualizar `OrderStatisticsService` (Fase 7.1)

### Eliminación
- [ ] Eliminar `Article` model
- [ ] Eliminar `ArticleCategory` model (si aplica)
- [ ] Eliminar `ArticleFactory`
- [ ] Crear migración para eliminar tabla `articles`
- [ ] Crear migración para eliminar tabla `article_categories` (si aplica)

### Limpieza
- [ ] Eliminar imports de Article
- [ ] Eliminar `article_id` del fillable de Product
- [ ] Buscar y eliminar código comentado relacionado con Article

### Testing
- [ ] Probar creación de Product
- [ ] Probar actualización de Product
- [ ] Probar eliminación de Product
- [ ] Probar listado de Products (filtros, ordenamiento)
- [ ] Probar exports (todos los tipos)
- [ ] Probar estadísticas de pedidos
- [ ] Probar inventario de almacenes

### Documentación
- [ ] Actualizar documentación de Product
- [ ] Actualizar PROBLEMAS-CRITICOS.md
- [ ] Actualizar referencias en otros documentos

---

## 🔄 Orden de Ejecución Recomendado (GARANTIZA NO PERDER DATOS)

**⚠️ IMPORTANTE**: Este orden es CRÍTICO para garantizar que no se pierda ningún dato.

### Fase A: Preparación (NO TOCA DATOS)
1. **Fase 1**: Preparación y análisis (verificar datos)
   - Backup completo
   - Verificaciones de integridad
   - **NO hacer cambios todavía**

### Fase B: Migración de Datos (PRIMERO LOS DATOS)
2. **Fase 2**: Migración de datos (agregar `name` a `products` y migrar datos)
   - ✅ Agregar columna `name` a `products`
   - ✅ Migrar datos de `articles.name` a `products.name` (con transacción)
   - ✅ Verificar que TODOS los datos fueron migrados
   - ✅ Hacer `name` NOT NULL solo después de verificar
   - **⚠️ TABLA `articles` SIGUE EXISTIENDO** (backup de seguridad)

### Fase C: Actualización de Código (AHORA SÍ CAMBIAR CÓDIGO)
3. **Fase 3**: Actualizar modelos (Product, eliminar Article)
4. **Fase 4**: Actualizar controladores
5. **Fase 5**: Actualizar modelos relacionados (Order, Box)
6. **Fase 6**: Actualizar exports
7. **Fase 7**: Actualizar servicios
   - **Durante estas fases, los datos ya están migrados**
   - **Si hay error en código, los datos están seguros en BD**

### Fase D: Testing (VERIFICAR TODO FUNCIONA)
8. **Testing exhaustivo**:
   - ✅ Probar que todos los products muestran su name correctamente
   - ✅ Probar que no hay errores relacionados con Article
   - ✅ Verificar que exports funcionan
   - ✅ Verificar que estadísticas funcionan

### Fase E: Limpieza (SOLO SI TODO FUNCIONA)
9. **Fase 8**: Crear migraciones de eliminación (solo si testing pasó)
   - **AHORA SÍ** eliminar tabla `articles` (los datos ya están en `products`)
10. **Fase 9**: Limpieza de código
11. **Fase 10**: Actualizar documentación

**📌 Resumen del orden seguro**:
1. Backup → 2. Migrar datos → 3. Verificar datos → 4. Actualizar código → 5. Testing → 6. Eliminar tablas

---

## 📊 Estimación de Impacto

### Archivos a Modificar
- **Modelos**: 2-3 archivos (Product, eliminar Article, posiblemente ArticleCategory)
- **Controladores**: 2 archivos (ProductController, StoreController)
- **Modelos relacionados**: 1 archivo (Order)
- **Exports**: 6 archivos
- **Servicios**: 1 archivo (OrderStatisticsService)
- **Migraciones**: 3-4 nuevas migraciones
- **Total**: ~15-18 archivos

### Complejidad
- **Media-Alta**: Requiere cambios en múltiples capas
- **Riesgo**: Medio (si se hace correctamente con backup y verificación)

### Tiempo Estimado
- **Análisis y planificación**: 1-2 horas
- **Implementación**: 4-6 horas
- **Testing**: 2-3 horas
- **Total**: 7-11 horas

---

## ✅ Criterios de Éxito

1. ✅ Todos los Products tienen `name` en la tabla `products`
2. ✅ No hay referencias a `Article` en el código (excepto comentarios/documentación)
3. ✅ Todas las funcionalidades existentes siguen funcionando
4. ✅ Los exports funcionan correctamente
5. ✅ Las estadísticas funcionan correctamente
6. ✅ La tabla `articles` ha sido eliminada
7. ✅ La documentación está actualizada

---

## 🚨 Rollback Plan (Garantía de Recuperación)

Si algo sale mal en cualquier momento:

### Durante Fase 2 (Migración de Datos)
- **Si la migración falla parcialmente**:
  ```sql
  -- Rollback de la transacción
  ROLLBACK;
  
  -- Restaurar desde backup si es necesario
  DELETE FROM products WHERE name IS NOT NULL;
  UPDATE products p 
  INNER JOIN articles_backup a ON p.id = a.id 
  SET p.name = NULL;
  ```

### Después de Fase 2 pero antes de Fase 8
- **Si hay problemas en el código pero los datos están OK**:
  - Los datos ya están migrados en `products.name`
  - La tabla `articles` todavía existe (backup)
  - Simplemente revertir cambios de código (git)
  - Los datos están seguros

### Después de eliminar tabla articles (Fase 8)
- **Si se necesita recuperar**:
  ```sql
  -- Restaurar desde backup de BD completo
  -- O restaurar desde articles_backup si existe
  CREATE TABLE articles AS SELECT * FROM articles_backup;
  ```

### Plan de Rollback General
1. **Restaurar backup completo de BD** (si se hizo antes de empezar)
2. **Revertir commits de git** (si se usó control de versiones)
3. **Verificar que todo funciona como antes**
4. **NO perder datos**: Si los datos están en `products.name`, están seguros aunque falle el código

### Puntos de No Retorno
- ⚠️ **ANTES de Fase 8**: Todo es reversible, tabla `articles` existe
- ⚠️ **DESPUÉS de Fase 8**: Necesitas backup para restaurar `articles`

---

---

## 📋 Garantías de Preservación de Datos - Resumen Ejecutivo

### ¿Cómo garantizamos que NO se pierda ningún dato?

1. **✅ Backup completo antes de empezar**
   - Se crean tablas de backup (`articles_backup`, `products_backup`)
   - Si algo falla, siempre podemos restaurar

2. **✅ Migración de datos ANTES de cambiar código**
   - Primero copiamos `articles.name` → `products.name`
   - Los datos quedan en AMBAS tablas durante la transición
   - Solo después de verificar, actualizamos el código

3. **✅ Transacciones SQL con rollback**
   - La migración usa `START TRANSACTION`
   - Si algo falla, hacemos `ROLLBACK`
   - Nada se pierde si hay error

4. **✅ Validaciones exhaustivas**
   - Verificamos que todos los products tienen name antes de continuar
   - Verificamos que los nombres coinciden
   - NO procedemos si algo no cuadra

5. **✅ La tabla `articles` NO se elimina hasta el final**
   - Se mantiene como backup durante toda la transición
   - Solo se elimina cuando TODO está funcionando
   - Si necesitas recuperar, los datos siguen ahí

6. **✅ Orden seguro de ejecución**
   - Fase 1: Backup y verificación (no toca datos)
   - Fase 2: Migrar datos (copia, no elimina)
   - Fases 3-7: Actualizar código (datos ya migrados)
   - Fase 8: Eliminar tabla (solo si todo funciona)

### Flujo de Datos Seguro

```
INICIO:
  articles (con name) ← Datos originales
  products (sin name)

FASE 2 (Migración):
  articles (con name) ← Se mantiene (backup)
  products (con name) ← Se copia desde articles
  
  ✅ Ambos tienen los datos durante la transición

FASE 3-7 (Actualización de código):
  articles (con name) ← Se mantiene (backup de seguridad)
  products (con name) ← Código usa este ahora
  
  ✅ Si el código falla, los datos siguen en articles

FASE 8 (Limpieza - SOLO si todo funciona):
  articles (ELIMINADO) ← Solo después de verificar
  products (con name) ← Única fuente de verdad
  
  ✅ Datos migrados y verificados antes de eliminar
```

### Preguntas Frecuentes

**P: ¿Qué pasa si falla la migración de datos?**
R: Se hace ROLLBACK y los datos vuelven a como estaban. No se pierde nada.

**P: ¿Qué pasa si el código nuevo tiene errores?**
R: Los datos ya están en `products.name`. Puedes revertir el código y los datos siguen ahí. La tabla `articles` todavía existe como backup.

**P: ¿Cuándo se pierden los datos de `articles`?**
R: Solo en la Fase 8, y SOLO si:
   - ✅ La migración fue exitosa (verificado)
   - ✅ El código funciona correctamente (probado)
   - ✅ Todos los tests pasan

**P: ¿Puedo recuperar si algo sale mal después de eliminar `articles`?**
R: Sí, desde el backup completo que se hizo en la Fase 1.

---

**Última actualización**: 2026-01-16  
**Autor**: Análisis automático del código base

