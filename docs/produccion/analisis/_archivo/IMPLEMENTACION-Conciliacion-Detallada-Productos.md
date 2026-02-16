# Implementación: Conciliación Detallada por Productos

**Fecha**: 2025-01-27  
**Estado**: ✅ Implementado

---

## 📋 Resumen

Se ha implementado un método que genera una conciliación detallada por producto para cada producción, mostrando minuciosamente:
- Cuánto se produjo de cada producto
- Dónde está contabilizado (venta, stock, re-procesado)
- Cuánto falta o sobra
- Estado de la conciliación (ok/warning/error)

---

## 🔧 Cambios Realizados

### 1. Nuevo Método en `Production.php`

**Método**: `getDetailedReconciliationByProduct()`

**Ubicación**: `app/Models/Production.php` (después de `calculateGlobalTotals()`)

**Funcionalidad**:
- Obtiene todos los productos producidos (de todos los nodos finales)
- Para cada producto, calcula:
  - Producido (peso y cajas)
  - En venta (peso y cajas)
  - En stock (peso y cajas)
  - Re-procesado (peso y cajas)
  - Balance (peso y porcentaje)
  - Estado (ok/warning/error)
- Genera un resumen general con estado global

---

### 2. Integración en el Endpoint

**Endpoint**: `GET /v2/productions/{id}/process-tree`

**Cambio**: Se agregó el campo `reconciliation` a la respuesta:

```php
'data' => [
    'processNodes' => $processNodes,
    'totals' => $production->calculateGlobalTotals(),
    'reconciliation' => $production->getDetailedReconciliationByProduct(), // ✨ NUEVO
],
```

---

## 📊 Estructura de la Respuesta

### Campo `reconciliation`

```json
{
  "reconciliation": {
    "products": [
      {
        "product": {
          "id": 104,
          "name": "Pulpo Fresco Rizado"
        },
        "produced": {
          "weight": 700.0,
          "boxes": 0
        },
        "inSales": {
          "weight": 725.0,
          "boxes": 145
        },
        "inStock": {
          "weight": 200.0,
          "boxes": 40
        },
        "reprocessed": {
          "weight": 10.0,
          "boxes": 2
        },
        "balance": {
          "weight": -235.0,
          "percentage": -33.57
        },
        "status": "error",
        "message": "Hay 235.0kg más contabilizado (33.57%)"
      },
      {
        "product": {
          "id": 205,
          "name": "Alacha congelada mediana"
        },
        "produced": {
          "weight": 400.0,
          "boxes": 0
        },
        "inSales": {
          "weight": 0.0,
          "boxes": 0
        },
        "inStock": {
          "weight": 0.0,
          "boxes": 0
        },
        "reprocessed": {
          "weight": 0.0,
          "boxes": 0
        },
        "balance": {
          "weight": 400.0,
          "percentage": 100.0
        },
        "status": "error",
        "message": "Faltan 400.0kg (100.0%)"
      }
    ],
    "summary": {
      "totalProducts": 3,
      "productsOk": 0,
      "productsWarning": 0,
      "productsError": 2,
      "totalProducedWeight": 1300.0,
      "totalContabilizedWeight": 1435.0,
      "totalBalanceWeight": -135.0,
      "overallStatus": "error"
    }
  }
}
```

---

## 📐 Cálculo del Balance

Para cada producto:

```
Balance = Producido - (Venta + Stock + Re-procesado)

Si Balance > 0: Faltante (hay menos contabilizado que producido)
Si Balance < 0: Exceso (hay más contabilizado que producido) ⚠️
Si Balance = 0: OK ✅
```

---

## 🎯 Estados de Conciliación

### Por Producto

| Estado | Condición | Significado |
|--------|-----------|-------------|
| `ok` | `|Balance| ≤ 0.01kg` | Todo contabilizado correctamente |
| `warning` | `0.01kg < |Balance| ≤ 5%` | Pequeña discrepancia, revisar |
| `error` | `|Balance| > 5%` | Discrepancia significativa, acción requerida |

### Estado General

El estado general (`overallStatus`) se determina con los mismos umbrales aplicados al balance total de todos los productos.

---

## 📝 Campos Detallados

### Por Producto

- **product**: Información del producto (id, name)
- **produced**: Peso y cajas producidas
- **inSales**: Peso y cajas en venta (asignadas a pedidos)
- **inStock**: Peso y cajas en stock (almacenadas)
- **reprocessed**: Peso y cajas re-procesadas (usadas en otros procesos)
- **balance**: Diferencia calculada (peso y porcentaje)
- **status**: Estado de la conciliación (ok/warning/error)
- **message**: Mensaje descriptivo del estado

### Resumen

- **totalProducts**: Número total de productos producidos
- **productsOk**: Productos con conciliación correcta
- **productsWarning**: Productos con advertencia
- **productsError**: Productos con error
- **totalProducedWeight**: Peso total producido
- **totalContabilizedWeight**: Peso total contabilizado
- **totalBalanceWeight**: Balance total (puede ser negativo)
- **overallStatus**: Estado general (ok/warning/error)

---

## 🔍 Ejemplo de Uso

### Endpoint

```
GET /api/v2/productions/291/process-tree
```

### Respuesta

```json
{
  "message": "Árbol de procesos obtenido correctamente.",
  "data": {
    "processNodes": [...],
    "totals": {...},
    "reconciliation": {
      "products": [
        {
          "product": {"id": 104, "name": "Pulpo Fresco Rizado"},
          "produced": {"weight": 700.0, "boxes": 0},
          "inSales": {"weight": 725.0, "boxes": 145},
          "inStock": {"weight": 200.0, "boxes": 40},
          "reprocessed": {"weight": 10.0, "boxes": 2},
          "balance": {"weight": -235.0, "percentage": -33.57},
          "status": "error",
          "message": "Hay 235.0kg más contabilizado (33.57%)"
        }
      ],
      "summary": {
        "totalProducts": 1,
        "productsOk": 0,
        "productsWarning": 0,
        "productsError": 1,
        "totalProducedWeight": 700.0,
        "totalContabilizedWeight": 935.0,
        "totalBalanceWeight": -235.0,
        "overallStatus": "error"
      }
    }
  }
}
```

---

## ✅ Validaciones

1. **Productos sin producción**: Solo se incluyen productos que realmente fueron producidos
2. **Productos sin datos**: Si un producto no tiene venta/stock/re-procesado, los valores son 0
3. **Valores negativos**: Permite valores negativos en balance para detectar errores (más contabilizado que producido)

---

## 🎯 Casos de Uso

### 1. Detección de Errores

Si `balance.weight < 0`, significa que hay más contabilizado que producido:
- Cajas con lote incorrecto
- Productos asignados incorrectamente
- Errores en el registro de producción

### 2. Detección de Faltantes

Si `balance.weight > 0`, significa que falta contabilizar:
- Productos no registrados
- Cajas sin asignar a palets
- Errores en el flujo de datos

### 3. Validación de Cierre

Antes de cerrar una producción, verificar:
- `overallStatus` debe ser `ok` o `warning`
- Si es `error`, revisar los productos individuales

---

## 📚 Archivos Modificados

1. **`app/Models/Production.php`**
   - Nuevo método: `getDetailedReconciliationByProduct()`

2. **`app/Http/Controllers/v2/ProductionController.php`**
   - Modificado método: `getProcessTree()`
   - Agregado campo `reconciliation` a la respuesta

---

## 🔄 Relación con Otros Métodos

### `calculateGlobalTotals()`

Calcula totales globales de venta y stock, pero no por producto.

### `getDetailedReconciliationByProduct()`

Calcula la conciliación detallada **por producto**, mostrando el balance completo de cada uno.

### `reconcile()`

Método legacy que compara producción declarada vs stock real (solo stock, no incluye venta ni re-procesados).

---

**Implementación completada**: 2025-01-27  
**Estado**: ✅ Listo para usar

