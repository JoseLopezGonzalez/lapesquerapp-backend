# Pedidos - Detalles Planificados de Productos (OrderPlannedProductDetail)

## ⚠️ Estado de la API
- **v1**: Obsoleta (no documentada)
- **v2**: Versión activa (este documento)

---

## 📋 Visión General

El modelo `OrderPlannedProductDetail` representa un **producto planificado** dentro de un pedido. Estos detalles definen qué productos se esperan en el pedido, con qué cantidades, precios unitarios e impuestos.

**Concepto clave**: Los productos planificados son la "hoja de ruta" del pedido. Definen lo que se espera entregar antes de que se asignen los palets reales con las cajas físicas.

**Archivo del modelo**: `app/Models/OrderPlannedProductDetail.php`

---

## 🗄️ Estructura de Base de Datos

### Tabla: `order_planned_product_details`

**Migración**: Buscar en `database/migrations/companies/` (fecha aproximada: 2025-03-09 según referencia en código)

**Campos**:

| Campo | Tipo | Nullable | Descripción |
|-------|------|----------|-------------|
| `id` | bigint | NO | ID único del detalle |
| `order_id` | bigint | NO | FK a `orders` - Pedido al que pertenece |
| `product_id` | bigint | NO | FK a `products` - Producto planificado |
| `tax_id` | bigint | NO | FK a `taxes` - Impuesto aplicado |
| `quantity` | decimal | NO | Cantidad planificada (en kg) |
| `boxes` | integer | NO | Cantidad de cajas planificadas |
| `unit_price` | decimal | NO | Precio unitario por kilogramo |
| `created_at` | timestamp | NO | Fecha de creación |
| `updated_at` | timestamp | NO | Fecha de última actualización |

**Campos comentados** (posiblemente eliminados o no usados):
- `line_base`: Base de la línea (probablemente calculado)
- `line_total`: Total de la línea (probablemente calculado)
- `pallets`: Cantidad de palets (no usado)
- `discount_type`, `discount_value`: Descuentos (no implementados)

**Índices**:
- `id` (primary key)
- Foreign keys a `orders`, `products`, `taxes`

**Constraints**:
- `order_id` → `orders.id` (onDelete: cascade)
- `product_id` → `products.id` (onDelete: cascade)
- `tax_id` → `taxes.id` (onDelete: cascade)

---

## 📦 Modelo Eloquent

### Fillable Attributes

```php
protected $fillable = [
    'order_id',
    'product_id',
    'tax_id',
    'quantity',
    'boxes',
    'unit_price',
    // Campos comentados:
    // 'line_base',
    // 'line_total',
    // 'pallets',
    // 'discount_type',
    // 'discount_value',
];
```

**Nota**: Los campos `line_base` y `line_total` están comentados en fillable pero se usan en algunos métodos del controlador.

### Traits

- `UsesTenantConnection`: Usa conexión tenant (multi-tenant)
- `HasFactory`: Para testing y seeders

---

## 🔗 Relaciones

### 1. `order()` - Pedido
```php
public function order()
{
    return $this->belongsTo(Order::class);
}
```
- Relación muchos-a-uno con `Order`
- Cada detalle pertenece a un pedido

### 2. `product()` - Producto
```php
public function product()
{
    return $this->belongsTo(Product::class);
}
```
- Relación muchos-a-uno con `Product`
- El producto planificado

### 3. `tax()` - Impuesto
```php
public function tax()
{
    return $this->belongsTo(Tax::class);
}
```
- Relación muchos-a-uno con `Tax`
- El impuesto aplicado a este producto

---

## 📡 Controlador

**Archivo**: `app/Http/Controllers/v2/OrderPlannedProductDetailController.php`

### Métodos del Controlador

#### `index(Request $request)` - Listar Detalles
```php
GET /v2/order-planned-product-details
```

**Estado**: **VACÍO** - No implementado (ver observaciones críticas)

#### `store(Request $request)` - Crear Detalle Planificado
```php
POST /v2/order-planned-product-details
```

**Validación**:
```php
[
    "orderId" => 'required|integer|exists:tenant.orders,id',
    "boxes" => 'required|integer',
    "product.id" => 'required|integer|exists:tenant.products,id',
    "quantity" => 'required|numeric',
    "tax.id" => 'required|integer|exists:tenant.taxes,id',
    'unitPrice' => 'required|numeric',
]
```

**Comportamiento**:
- Crea el detalle planificado
- Calcula `line_base` y `line_total` como `unitPrice * quantity`
- **Nota**: Estos campos están comentados en fillable pero se usan aquí

**Respuesta**: Retorna `OrderPlannedProductDetailResource`

#### `update(Request $request, string $id)` - Actualizar Detalle
```php
PUT /v2/order-planned-product-details/{id}
```

**Validación**: Similar a `store()` pero sin `orderId`

**Comportamiento**: Actualiza el detalle y recalcula `line_base` y `line_total`

#### `destroy(string $id)` - Eliminar Detalle
```php
DELETE /v2/order-planned-product-details/{id}
```

**Comportamiento**: Elimina el detalle planificado

---

## 📄 API Resource

**Archivo**: `app/Http/Resources/v2/OrderPlannedProductDetailResource.php`

**Campos expuestos**:
```json
{
    "id": 1,
    "orderId": 5,
    "product": {
        "id": 10,
        "name": "Filetes de atún"
    },
    "tax": {
        "id": 2,
        "rate": 10
    },
    "quantity": 100.50,
    "boxes": 20,
    "unitPrice": 15.75
}
```

---

## 🔐 Permisos y Autenticación

**Middleware requerido**:
- `auth:sanctum`: Autenticación requerida
- `role:superuser,manager,admin,store_operator`: Roles permitidos

**Rutas**: Todas bajo `/v2/order-planned-product-details/*`

---

## 🔍 Uso en Order Model

Los detalles planificados se usan en `Order` para:

1. **Cálculo de `productDetails`**: Compara productos planificados con productos reales (desde palets)
2. **Cálculo de totales**: Suma de subtotales y totales con impuestos
3. **Documentos PDF**: Incluye productos planificados en hojas de pedido

**Método en Order**:
```php
public function productDetails()
{
    // Combina productionProductDetails (reales) con plannedProductDetails
    // Calcula precios y totales
}
```

---

## 📝 Ejemplos de Uso

### Crear un Producto Planificado
```http
POST /v2/order-planned-product-details
Content-Type: application/json
X-Tenant: empresa1

{
    "orderId": 5,
    "product": {
        "id": 10
    },
    "tax": {
        "id": 2
    },
    "quantity": 100.50,
    "boxes": 20,
    "unitPrice": 15.75
}
```

### Actualizar un Producto Planificado
```http
PUT /v2/order-planned-product-details/1
Content-Type: application/json

{
    "quantity": 120.00,
    "boxes": 24,
    "unitPrice": 16.00
}
```

---

## Observaciones Críticas y Mejoras Recomendadas

### ⚠️ Métodos No Implementados

1. **`index()` Vacío** (`app/Http/Controllers/v2/OrderPlannedProductDetailController.php:15-17`)
   - Método está definido pero sin implementación
   - **Líneas**: 15-17
   - **Problema**: Endpoint retorna vacío, puede confundir
   - **Recomendación**: Implementar o eliminar la ruta

2. **`show()` Vacío** (`app/Http/Controllers/v2/OrderPlannedProductDetailController.php:60-63`)
   - Método está definido pero sin implementación
   - **Líneas**: 60-63
   - **Problema**: No se puede obtener un detalle individual
   - **Recomendación**: Implementar retorno de recurso

3. **`create()` y `edit()` Vacíos** (`app/Http/Controllers/v2/OrderPlannedProductDetailController.php:22-26, 68-71`)
   - Métodos de formularios no usados en API REST
   - **Líneas**: 22-26, 68-71
   - **Estado**: Normal en APIs REST, pueden eliminarse

### ⚠️ Inconsistencia en Campos

4. **Campos Comentados en Fillable Pero Usados** (`app/Models/OrderPlannedProductDetail.php:22-23, 49-50`)
   - `line_base` y `line_total` están comentados en fillable
   - Pero se usan en `store()` y `update()` del controlador
   - **Líneas**: 22-23 (fillable), 49-50, 94-95 (uso)
   - **Problema**: Puede causar errores al crear/actualizar
   - **Recomendación**: 
     - Descomentar en fillable si se usan
     - O calcular como attributes en lugar de guardar

5. **Validación de Product Anidado** (`app/Http/Controllers/v2/OrderPlannedProductDetailController.php:36`)
   - Valida `"product.id"` (estructura anidada)
   - Pero en update valida `"product.id"` también
   - **Líneas**: 36, 81
   - **Problema**: Formato inconsistente con otros endpoints
   - **Recomendación**: Usar `product_id` plano como en otros lugares

### ⚠️ Falta de Validaciones

6. **No Validar Cantidades Positivas** (`app/Http/Controllers/v2/OrderPlannedProductDetailController.php:37-40`)
   - Valida que sean numéricos pero no que sean > 0
   - **Líneas**: 37, 39, 40
   - **Problema**: Pueden crearse detalles con cantidades negativas o cero
   - **Recomendación**: Agregar validación `min:0` o `min:0.01`

7. **No Validar Order Pertenece al Tenant** (`app/Http/Controllers/v2/OrderPlannedProductDetailController.php:34`)
   - Valida existencia pero no explícitamente tenant
   - **Líneas**: 34
   - **Estado**: Implícito por middleware tenant, pero podría ser más explícito

### ⚠️ Cálculo de Totales

8. **Cálculo Simplificado** (`app/Http/Controllers/v2/OrderPlannedProductDetailController.php:49-50, 94-95`)
   - `line_base` y `line_total` se calculan igual: `unitPrice * quantity`
   - **Líneas**: 49-50, 94-95
   - **Problema**: No aplica impuesto en `line_total` (debería ser base + impuesto)
   - **Recomendación**: 
     - Calcular correctamente: `line_base = unitPrice * quantity`
     - `line_total = line_base + (line_base * tax.rate / 100)`

9. **No Se Usa Tax en Cálculo** (`app/Http/Controllers/v2/OrderPlannedProductDetailController.php`)
   - Aunque se valida y guarda `tax_id`, no se usa para calcular totales
   - **Problema**: Los totales pueden estar incorrectos
   - **Recomendación**: Aplicar impuesto en cálculo de `line_total`

### ⚠️ Relación con Order

10. **No Validar Estado del Order** (`app/Http/Controllers/v2/OrderPlannedProductDetailController.php:31-55`)
    - No valida si el pedido está finalizado antes de crear detalles
    - **Líneas**: 31-55
    - **Problema**: Pueden agregarse detalles a pedidos finalizados
    - **Recomendación**: Validar `$order->status !== 'finished'`

11. **Permite Cambiar Order en Update** (`app/Http/Controllers/v2/OrderPlannedProductDetailController.php:76-99`)
    - No valida que `order_id` no pueda cambiarse
    - **Problema**: Aunque no está en validación, podría permitirse accidentalmente
    - **Recomendación**: Explícitamente no permitir cambiar `order_id` en update

### ⚠️ Falta de Unicidad

12. **No Previene Duplicados** (`database/migrations/`)
    - No hay unique constraint en `['order_id', 'product_id']`
    - **Problema**: Pueden crearse múltiples detalles del mismo producto en un pedido
    - **Recomendación**: 
      - Agregar unique constraint si no debe haber duplicados
      - O validar en controlador antes de crear

---

**Última actualización**: Documentación generada desde código fuente en fecha de generación.

