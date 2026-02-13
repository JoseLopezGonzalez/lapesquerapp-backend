# Inventario - Cajas (Boxes)

## ⚠️ Estado de la API
- **v1**: Obsoleta (no documentada)
- **v2**: Versión activa (este documento)

---

## 📋 Visión General

El modelo `Box` representa una **caja individual** de producto. Las cajas son la unidad mínima de trazabilidad en el sistema: cada caja tiene un lote, código GS1-128, producto, y pesos. Las cajas pueden estar en palets, almacenadas en almacenes, y ser consumidas en procesos de producción.

**Concepto clave**: Las cajas son la unidad atómica del sistema. Todo se rastrea a nivel de caja individual, desde el inventario hasta la producción.

**Archivo del modelo**: `app/Models/Box.php`

---

## 🗄️ Estructura de Base de Datos

### Tabla: `boxes`

**Migración**: `database/migrations/companies/2023_08_09_145949_create_boxes_table.php`

**Campos**:

| Campo | Tipo | Nullable | Descripción |
|-------|------|----------|-------------|
| `id` | bigint | NO | ID único de la caja |
| `article_id` | bigint | NO | FK a `products` - Producto de la caja |
| `lot` | string | NO | Lote de producción |
| `gs1_128` | string | NO | Código GS1-128 (código de barras) |
| `gross_weight` | decimal | NO | Peso bruto de la caja |
| `net_weight` | decimal | NO | Peso neto de la caja |
| `created_at` | timestamp | NO | Fecha de creación |
| `updated_at` | timestamp | NO | Fecha de última actualización |

**Índices**:
- `id` (primary key)
- Foreign key a `products` (via `article_id`)

**Constraints**:
- `article_id` → `products.id`

**Nota**: El campo se llama `article_id` pero realmente referencia a `products`. Esto es por razones históricas (ver observaciones).

---

## 📦 Modelo Eloquent

### Fillable Attributes

```php
protected $fillable = [
    'article_id',
    'lot',
    'gs1_128',
    'gross_weight',
    'net_weight',
];
```

**Nota**: `article_id` almacena el ID de `Product`, no de `Article`.

### Traits

- `UsesTenantConnection`: Usa conexión tenant (multi-tenant)
- `HasFactory`: Para testing y seeders

---

## 🔗 Relaciones

### 1. `product()` - Producto
```php
public function product()
{
    return $this->belongsTo(Product::class, 'article_id');
}
```
- Relación muchos-a-uno con `Product`
- **Nombre correcto**: Usa `article_id` pero referencia a `Product`

### 2. `article()` - Producto (Alias Legacy)
```php
public function article()
{
    return $this->belongsTo(Product::class, 'article_id');
}
```
- **Estado**: ⚠️ Método legacy que retorna `Product`, no `Article`
- Se mantiene por compatibilidad pero es semánticamente incorrecto

### 3. `palletBox()` - Relación con Palet
```php
public function palletBox()
{
    return $this->hasOne(PalletBox::class, 'box_id');
}
```
- Relación uno-a-uno con `PalletBox` (tabla intermedia)
- Una caja pertenece a un palet a través de `pallet_boxes`

### 4. `productionInputs()` - Entradas de Producción
```php
public function productionInputs()
{
    return $this->hasMany(ProductionInput::class, 'box_id');
}
```
- Relación uno-a-muchos con `ProductionInput`
- Indica en qué procesos de producción se ha usado esta caja
- **Importante**: Una caja puede estar en múltiples procesos de producción

---

## 🔢 Accessors (Atributos Calculados)

### `getPalletAttribute()`
Obtiene el palet donde está la caja (a través de `palletBox`).
```php
return $this->palletBox ? $this->palletBox->pallet : null;
```

### `getIsAvailableAttribute()`
Determina si la caja está disponible (no ha sido usada en producción).
```php
// Si la relación está cargada, usa isEmpty()
if ($this->relationLoaded('productionInputs')) {
    return $this->productionInputs->isEmpty();
}
// Si no, hace query directo
return !$this->productionInputs()->exists();
```

**Lógica**: Una caja está disponible si no tiene ningún `ProductionInput`.

### `getProductionAttribute()`
Obtiene la producción más reciente en la que se usó esta caja.
```php
// Obtiene el ProductionInput más reciente
// Y retorna su ProductionRecord->Production
```

**Retorna**: `Production` o `null` si nunca se usó en producción.

---

## 📡 Controlador

**Archivo**: `app/Http/Controllers/v2/BoxesController.php`

### Métodos del Controlador

#### `index(Request $request)` - Listar Cajas
```php
GET /v2/boxes
```

**Filtros disponibles** (query parameters):
- `id`: Filtrar por ID
- `ids`: Filtrar por múltiples IDs (array)
- `name`: Buscar por nombre de producto (LIKE en `product.article.name`)
- `species`: Array de IDs de especies (`product.species_id`)
- `lots`: Array de lotes
- `products`: Array de IDs de productos (`article_id`)
- `pallets`: Array de IDs de palets
- `gs1128`: Array de códigos GS1-128
- `createdAt.start` y `createdAt.end`: Rango de fechas de creación
- `palletState`: `'stored'` o `'shipped'` (estado del palet)
- `orderState`: `'pending'`, `'finished'`, `'without_order'` (estado del pedido del palet)
- `position`: `'located'` o `'unlocated'` (posición del palet)
- `stores`: Array de IDs de almacenes (donde está el palet)
- `orders`: Array de IDs de pedidos (del palet)
- `notes`: Buscar en observaciones del palet (LIKE)
- `orderIds`: Array de IDs de pedidos
- `orderDates.start` y `orderDates.end`: Fechas de pedidos
- `orderBuyerReference`: Referencia de compra (LIKE)

**Query parameters**:
- `perPage`: Elementos por página (default: 12)

**Orden**: Por ID descendente

**Respuesta**: Collection paginada de `BoxResource`

**Nota**: ⚠️ La mayoría de filtros dependen de relaciones anidadas (palet → order, palet → store), lo que puede hacer las queries lentas.

#### `destroy(string $id)` - Eliminar Caja
```php
DELETE /v2/boxes/{id}
```

**Comportamiento**: Elimina la caja

**Advertencia**: ⚠️ No valida si la caja está en uso (producción o palet).

#### `destroyMultiple(Request $request)` - Eliminar Múltiples Cajas
```php
DELETE /v2/boxes
```

**Request body**:
```json
{
    "ids": [1, 2, 3]
}
```

**Métodos no implementados**:
- `create()`: Vacío
- `store()`: Vacío
- `show()`: Vacío
- `edit()`: Vacío
- `update()`: Vacío

**Nota**: Las cajas generalmente se crean/actualizan a través de los palets.

---

## 📄 API Resource

**Archivo**: `app/Http/Resources/v2/BoxResource.php`

**Campos expuestos**:
```json
{
    "id": 1,
    "palletId": 5,
    "product": {
        "id": 1,
        "name": "Filetes de atún"
    },
    "lot": "LOT123",
    "gs1128": "1234567890123",
    "grossWeight": 10.5,
    "netWeight": 9.5,
    "createdAt": "2025-01-15",
    "isAvailable": true,
    "production": {
        "id": 10,
        "lot": "PROD123"
    }
}
```

**Nota**: `production` es `null` si la caja nunca se usó en producción.

---

## 🔍 Métodos de Transformación

### `toArrayAssoc()`
Array asociativo legacy (versión v1).
```php
[
    'id' => $this->id,
    'palletId' => $this->pallet_id,  // ⚠️ No existe este campo
    'article' => $this->article->toArrayAssoc(),
    // ...
]
```

### `toArrayAssocV2()`
Array asociativo v2 con información de producción.
```php
[
    'id' => $this->id,
    'palletId' => $this->pallet_id,  // ⚠️ Calculado desde palletBox
    'product' => $this->product->toArrayAssoc(),
    'isAvailable' => $this->isAvailable,
    'production' => [...],  // Producción más reciente
    // ...
]
```

---

## 🔐 Permisos y Autenticación

**Middleware requerido**:
- `auth:sanctum`: Autenticación requerida
- `role:superuser,manager,admin,store_operator`: Roles permitidos

**Rutas**: Todas bajo `/v2/boxes/*`

---

## 📝 Ejemplos de Uso

### Listar Cajas
```http
GET /v2/boxes?species[]=1&palletState=stored&perPage=20
Authorization: Bearer {token}
X-Tenant: empresa1
```

### Filtrar por Disponibilidad (indirectamente)
```http
GET /v2/boxes?products[]=1&createdAt[start]=2025-01-01
Authorization: Bearer {token}
```

**Nota**: No hay filtro directo `isAvailable`, pero se puede filtrar por cajas sin `productionInputs`.

---

## 🔄 Flujo de Vida de una Caja

1. **Creación**: Se crea junto con un palet o en producción
2. **Almacenamiento**: Se asigna a un palet que está en un almacén
3. **Uso en Producción**: Se consume en un proceso (crea `ProductionInput`)
4. **No disponible**: `isAvailable = false` después de ser consumida

---

## Observaciones Críticas y Mejoras Recomendadas

### ⚠️ Métodos No Implementados

1. **CRUD Incompleto** (`app/Http/Controllers/v2/BoxesController.php`)
   - `create()`, `store()`, `show()`, `edit()`, `update()` están vacíos
   - **Líneas**: 211-246
   - **Estado**: Las cajas se crean a través de palets, pero limita funcionalidad
   - **Recomendación**: Implementar si se necesita gestión directa de cajas

### ⚠️ Confusión Semántica: article_id vs product

2. **Campo article_id Referencia a Product** (`app/Models/Box.php:16, 19-27`)
   - El campo se llama `article_id` pero referencia a `Product`
   - Hay método `article()` que retorna `Product` (legacy)
   - **Líneas**: 16, 19-27
   - **Problema**: Confusión semántica, código legacy
   - **Recomendación**: 
     - Mantener por compatibilidad si hay mucho código legacy
     - O migrar a `product_id` con migración de datos

### ⚠️ Queries N+1 en Filtros

3. **Filtros con Relaciones Anidadas** (`app/Http/Controllers/v2/BoxesController.php:86-198`)
   - Muchos filtros usan `whereHas()` con múltiples niveles de anidación
   - **Líneas**: 86-198
   - **Problema**: Queries complejas y lentas con muchos datos
   - **Recomendación**: 
     - Optimizar con JOINs directos donde sea posible
     - Considerar índices en tablas relacionadas

### ⚠️ Eliminación Sin Validaciones

4. **delete() No Valida Uso** (`app/Http/Controllers/v2/BoxesController.php:251-257`)
   - No valida si la caja está en uso (producción o palet) antes de eliminar
   - **Líneas**: 251-257
   - **Problema**: Puede eliminar cajas que están en producción, rompiendo trazabilidad
   - **Recomendación**: 
     - Validar `productionInputs()->exists()` antes de eliminar
     - Validar `palletBox()` antes de eliminar

5. **delete() Sin Transacción** (`app/Http/Controllers/v2/BoxesController.php:251-257`)
   - Eliminación simple sin transacción
   - **Problema**: Si falla a mitad, puede dejar datos inconsistentes
   - **Recomendación**: Usar `DB::transaction()`

### ⚠️ Performance: getProductionAttribute()

6. **getProductionAttribute() Puede Ser Lento** (`app/Models/Box.php:66-90`)
   - Si no están cargadas las relaciones, hace query con `orderBy`
   - **Líneas**: 66-90
   - **Problema**: Puede ser lento si se accede desde una colección
   - **Recomendación**: Usar eager loading cuando se necesite

### ⚠️ Campo palletId en toArrayAssoc()

7. **palletId No Existe en Box** (`app/Models/Box.php:96`)
   - `toArrayAssoc()` incluye `palletId` pero no existe como campo
   - **Líneas**: 96, 113
   - **Problema**: Retorna `null` o valor incorrecto
   - **Recomendación**: 
     - Calcular desde `palletBox->pallet_id`
     - O eliminar si no se usa

### ⚠️ No Hay Validación de GS1-128 Único

8. **GS1-128 No Validado Como Único** (`app/Http/Controllers/v2/BoxesController.php`)
   - No hay validación ni constraint de unicidad para `gs1_128`
   - **Problema**: Pueden existir cajas duplicadas con mismo código
   - **Recomendación**: 
     - Agregar unique constraint en BD
     - O validar en creación/actualización

### ⚠️ No Valida netWeight <= grossWeight

9. **Validación de Pesos Faltante** (`app/Models/Box.php`)
   - No valida que `net_weight <= gross_weight`
   - **Problema**: Pueden crearse cajas con datos inválidos
   - **Recomendación**: Agregar validación en modelo o controlador

### ⚠️ Filtro de Name Complejo

10. **Filtro de Name con Múltiples whereHas** (`app/Http/Controllers/v2/BoxesController.php:32-38`)
    - Filtra por `product.article.name` con múltiples niveles
    - **Líneas**: 32-38
    - **Problema**: Query compleja, asume estructura específica de relaciones
    - **Recomendación**: Simplificar o documentar estructura esperada

### ⚠️ Filtro orderState Complejo

11. **Lógica Compleja en orderState** (`app/Http/Controllers/v2/BoxesController.php:100-130`)
    - Maneja múltiples estados incluyendo `without_order` con lógica OR
    - **Líneas**: 100-130
    - **Problema**: Lógica compleja difícil de mantener
    - **Recomendación**: Simplificar o extraer a método privado

### ⚠️ Sin Eager Loading por Defecto

12. **index() No Usa Eager Loading** (`app/Http/Controllers/v2/BoxesController.php:18-206`)
    - No carga relaciones necesarias (product, palletBox, productionInputs)
    - **Problema**: Queries N+1 si se acceden estos campos
    - **Recomendación**: Agregar `->with(['product', 'palletBox.pallet', 'productionInputs'])`

### ⚠️ Filtro orderIds Convierte String a Array

13. **orderIds Convierte String** (`app/Http/Controllers/v2/BoxesController.php:167-172`)
    - Si `orderIds` viene como string, lo convierte a array con `explode(',')`
    - **Líneas**: 167-172
    - **Problema**: Comportamiento no estándar, mejor usar array siempre
    - **Recomendación**: Validar que sea array o aceptar ambos formatos explícitamente

---

**Última actualización**: Documentación generada desde código fuente en fecha de generación.

