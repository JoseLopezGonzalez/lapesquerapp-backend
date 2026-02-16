# Fix: Conciliación Incluye Productos No Producidos

**Fecha**: 2025-01-XX  
**Problema Identificado**: La conciliación no mostraba productos con el lote que existían en venta/stock/reprocesados pero no estaban registrados como producidos.

---

## 🐛 Problema

El método `getDetailedReconciliationByProduct()` solo iteraba sobre los productos que estaban registrados como **producidos** en la producción. Esto significaba que:

- ❌ Si un producto tenía el lote en venta pero no estaba registrado como producido → **No aparecía en la conciliación**
- ❌ Si un producto tenía el lote en stock pero no estaba registrado como producido → **No aparecía en la conciliación**
- ❌ Si un producto tenía el lote re-procesado pero no estaba registrado como producido → **No aparecía en la conciliación**

**Ejemplo del problema:**
```
Lote: LOT-2024-001
- Producto A: Producido 100kg, en venta 50kg → ✅ Aparece en conciliación
- Producto B: NO producido, en venta 30kg → ❌ NO aparece en conciliación (PROBLEMA)
```

---

## ✅ Solución Implementada

Se modificó el método `getDetailedReconciliationByProduct()` para:

1. **Obtener TODOS los productos únicos** que tienen ese lote, incluyendo:
   - Productos producidos (de outputs de nodos finales)
   - Productos en venta
   - Productos en stock
   - Productos re-procesados

2. **Para cada producto**, calcular:
   - **Producido**: Puede ser 0 si no está registrado
   - **En venta**: Suma de cajas del lote en pedidos
   - **En stock**: Suma de cajas del lote en almacenes
   - **Re-procesado**: Suma de cajas del lote usadas en otros procesos
   - **Balance**: `Producido - (Venta + Stock + Re-procesado)`
   - **Estado**: 
     - `error` si no está producido pero está contabilizado
     - `error` si el balance es > 5%
     - `warning` si el balance es <= 5%
     - `ok` si el balance es 0

3. **Mensaje especial** para productos no producidos:
   ```
   "Producto no registrado como producido pero existe en venta/stock/reprocesado (X kg)"
   ```

---

## 📊 Cambios en el Código

### Antes

```php
// Solo iteraba sobre productos producidos
foreach ($producedByProduct as $productId => $producedInfo) {
    // ...
}
```

### Después

```php
// Obtener TODOS los productos únicos con ese lote
$allProductIds = collect();
$allProductIds = $allProductIds->merge(array_keys($producedByProduct));
$allProductIds = $allProductIds->merge(array_keys($salesData));
$allProductIds = $allProductIds->merge(array_keys($stockData));
$allProductIds = $allProductIds->merge(array_keys($reprocessedData));
$allProductIds = $allProductIds->unique()->values();

// Iterar sobre TODOS los productos
foreach ($allProductIds as $productId) {
    // Obtener producido (puede ser 0)
    $producedWeight = $producedByProduct[$productId]['weight'] ?? 0;
    // ...
}
```

---

## 🎯 Ejemplo de Respuesta

### Antes (Producto no producido no aparecía)

```json
{
  "products": [
    {
      "product": {"id": 1, "name": "Producto A"},
      "produced": {"weight": 100, "boxes": 20},
      "inSales": {"weight": 50, "boxes": 10},
      "inStock": {"weight": 50, "boxes": 10},
      "balance": {"weight": 0, "percentage": 0},
      "status": "ok"
    }
    // Producto B no aparece aunque tenga el lote en venta
  ]
}
```

### Después (Producto no producido aparece con error)

```json
{
  "products": [
    {
      "product": {"id": 1, "name": "Producto A"},
      "produced": {"weight": 100, "boxes": 20},
      "inSales": {"weight": 50, "boxes": 10},
      "inStock": {"weight": 50, "boxes": 10},
      "balance": {"weight": 0, "percentage": 0},
      "status": "ok"
    },
    {
      "product": {"id": 2, "name": "Producto B"},
      "produced": {"weight": 0, "boxes": 0},  // ✨ No producido
      "inSales": {"weight": 30, "boxes": 6},   // ✨ Pero está en venta
      "inStock": {"weight": 0, "boxes": 0},
      "reprocessed": {"weight": 0, "boxes": 0},
      "balance": {"weight": -30, "percentage": -100},
      "status": "error",  // ✨ Marcado como error
      "message": "Producto no registrado como producido pero existe en venta/stock/reprocesado (30kg)"
    }
  ]
}
```

---

## 🔍 Casos de Uso

### Caso 1: Producto en venta pero no producido
- **Producido**: 0kg
- **En venta**: 50kg
- **Balance**: -50kg
- **Estado**: `error`
- **Mensaje**: "Producto no registrado como producido pero existe en venta/stock/reprocesado (50kg)"

### Caso 2: Producto en stock pero no producido
- **Producido**: 0kg
- **En stock**: 30kg
- **Balance**: -30kg
- **Estado**: `error`
- **Mensaje**: "Producto no registrado como producido pero existe en venta/stock/reprocesado (30kg)"

### Caso 3: Producto re-procesado pero no producido
- **Producido**: 0kg
- **Re-procesado**: 20kg
- **Balance**: -20kg
- **Estado**: `error`
- **Mensaje**: "Producto no registrado como producido pero existe en venta/stock/reprocesado (20kg)"

### Caso 4: Producto producido correctamente
- **Producido**: 100kg
- **En venta**: 50kg
- **En stock**: 50kg
- **Balance**: 0kg
- **Estado**: `ok`

---

## ⚠️ Impacto

### Positivo
- ✅ **Detección de inconsistencias**: Ahora se detectan productos con el lote que no están registrados como producidos
- ✅ **Conciliación completa**: La conciliación muestra TODOS los productos con ese lote
- ✅ **Mejor trazabilidad**: Se puede identificar productos "huérfanos" que tienen el lote pero no están en la producción

### Consideraciones
- ⚠️ **Rendimiento**: Se hacen más queries para obtener todos los productos (impacto mínimo)
- ⚠️ **Resumen**: El resumen ahora puede incluir productos con balance negativo (no producidos)

---

## 📝 Notas Técnicas

1. **Búsqueda de productos**: Si un producto no está en ninguna de las fuentes (producido, venta, stock, reprocesado), se busca en la BD usando `Product::find($productId)`

2. **Cálculo de porcentaje**: 
   - Si hay producido: `(balance / producido) * 100`
   - Si no hay producido pero hay contabilizado: `-100%` (todo es exceso)

3. **Estado general**: Si hay productos contabilizados pero no producidos, el estado general se marca como `error`

---

## ✅ Testing Recomendado

1. **Test 1**: Producto con lote en venta pero no producido
   - Debe aparecer en conciliación
   - Estado debe ser `error`
   - Balance debe ser negativo

2. **Test 2**: Producto con lote en stock pero no producido
   - Debe aparecer en conciliación
   - Estado debe ser `error`

3. **Test 3**: Producto producido correctamente
   - Debe aparecer normalmente
   - Balance debe ser correcto

4. **Test 4**: Mezcla de productos producidos y no producidos
   - Todos deben aparecer
   - Estado general debe ser `error` si hay no producidos

---

**Autor**: Corrección de bug  
**Fecha**: 2025-01-XX  
**Versión**: 1.0

