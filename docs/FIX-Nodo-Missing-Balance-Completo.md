# Fix: Nodo Missing - Balance Completo

**Fecha**: 2025-01-27  
**Problema**: El nodo `missing` no aparecía cuando había productos producidos que no estaban contabilizados.

---

## 🔍 Problema Identificado

El nodo `missing` solo se creaba si había **cajas físicas faltantes** (cajas del lote que no estaban en palets o en palets sin asignar). 

**Pero** el nodo debería calcular el balance completo para **TODOS los productos** del nodo final:

```
Faltante = Producido - Venta - Stock - Re-procesado
```

Incluso si no hay cajas físicas, debería mostrar cuando hay un desbalance.

---

## 📊 Ejemplo del Problema

**Nodo Final (ID: 8) - Producción:**
- Producto 104: 700kg
- Producto 110: 200kg  
- Producto 205: 400kg
- **Total**: 1300kg

**Contabilizado:**
- Venta: 725kg (producto 104)
- Stock: 700kg (producto 104: 200kg, producto 110: 500kg)
- **Total**: 1425kg

**Faltantes que deberían aparecer:**
- Producto 104: 700 - 725 - 200 = -225kg ⚠️ (error: más contabilizado que producido)
- Producto 110: 200 - 0 - 500 = -300kg ⚠️ (error: más contabilizado que producido)
- Producto 205: 400 - 0 - 0 = 400kg ⚠️ (faltante real)

**Pero el nodo `missing` no aparecía** porque no había cajas físicas con el lote `"211125OCC01003"`.

---

## ✅ Solución Implementada

### Cambios en `createMissingNodeForFinalNode()`

1. **Ahora calcula el balance para TODOS los productos del nodo final**, no solo los que tienen cajas físicas faltantes.

2. **Obtiene los productos directamente desde los outputs del nodo final**, no solo desde los datos de faltantes.

3. **Calcula el faltante teórico**:
   ```
   Faltante = Producido - Venta - Stock - Re-procesado
   ```

4. **Muestra el nodo si**:
   - Hay faltantes positivos (productos no contabilizados)
   - Hay errores negativos (más contabilizado que producido)

5. **Permite valores negativos** en `missing.weight` para detectar errores de datos.

---

## 🔧 Cambios Técnicos

### Archivo: `app/Models/Production.php`

**Método modificado**: `createMissingNodeForFinalNode()`

**Antes:**
```php
if (empty($missingDataByProduct)) {
    return null; // ❌ Solo creaba si había cajas físicas faltantes
}
```

**Ahora:**
```php
// ✨ Calcular balance completo para TODOS los productos del nodo final
// Obtener productos desde los outputs
foreach ($finalNodeOutputs as $output) {
    // Obtener producto...
}

// Calcular faltante teórico para cada producto
$calculatedMissing = $produced - $inSales - $inStock - $reprocessed;

// Mostrar si hay faltantes o errores
if ($hasPositiveMissing || $hasOverCount) {
    // Crear nodo...
}
```

---

## 📋 Estructura del Nodo Missing Actualizada

El nodo `missing` ahora incluye **TODOS los productos** del nodo final con desbalance:

```json
{
  "type": "missing",
  "id": "missing-8",
  "products": [
    {
      "product": { "id": 104, "name": "Pulpo Fresco Rizado" },
      "produced": { "boxes": 0, "weight": 700.0 },
      "inSales": { "boxes": 145, "weight": 725.0 },
      "inStock": { "boxes": 40, "weight": 200.0 },
      "reprocessed": { "boxes": 0, "weight": 0.0 },
      "missing": {
        "boxes": 0,
        "weight": -225.0,  // ⚠️ Negativo = error de datos
        "percentage": 0.0
      },
      "boxes": []
    },
    {
      "product": { "id": 205, "name": "Alacha congelada mediana" },
      "produced": { "boxes": 0, "weight": 400.0 },
      "inSales": { "boxes": 0, "weight": 0.0 },
      "inStock": { "boxes": 0, "weight": 0.0 },
      "reprocessed": { "boxes": 0, "weight": 0.0 },
      "missing": {
        "boxes": 0,
        "weight": 400.0,  // ✅ Faltante real
        "percentage": 100.0
      },
      "boxes": []
    }
  ]
}
```

---

## ⚠️ Valores Negativos

Si `missing.weight` es **negativo**, significa que hay **más contabilizado** (venta + stock + re-procesado) que **producido**. Esto indica un **error de datos**:

- Cajas con lote incorrecto
- Productos asignados a pedidos/almacenes de otro lote
- Errores en el registro de producción

**Ejemplo:**
- Producido: 700kg
- Venta: 725kg
- Stock: 200kg
- **Faltante**: -225kg ⚠️ (hay 925kg contabilizados pero solo 700kg producidos)

---

## ✅ Resultado Esperado

Ahora el nodo `missing` debería aparecer siempre que:

1. ✅ Hay productos del nodo final que no están completamente contabilizados
2. ✅ Hay errores donde hay más contabilizado que producido
3. ✅ Hay cajas físicas faltantes (comportamiento anterior mantenido)

---

**Fix completado**: 2025-01-27

