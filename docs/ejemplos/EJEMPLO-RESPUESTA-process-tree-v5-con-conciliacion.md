# Ejemplo Completo: Production Tree v5 con Conciliación Detallada

**Endpoint**: `GET /v2/productions/{id}/process-tree`  
**Versión**: v5 - Con conciliación detallada por productos

---

## 📋 Resumen del Ejemplo

Este ejemplo muestra una producción completa con:

- ✅ **Árbol de procesos** con nodos finales
- ✅ **Nodos de venta** (sales)
- ✅ **Nodos de stock** (stock)
- ✅ **Nodos de re-procesados** (reprocessed) ✨
- ✅ **Nodos de faltantes** (missing) ✨
- ✅ **Totales globales**
- ✅ **Conciliación detallada por productos** ✨ NUEVO

---

## 🎯 Estructura Principal

```json
{
  "message": "...",
  "data": {
    "processNodes": [...],      // Árbol de procesos
    "totals": {...},            // Totales globales
    "reconciliation": {...}     // ✨ Conciliación detallada
  }
}
```

---

## 📊 Sección: `reconciliation`

### Estructura

```json
{
  "reconciliation": {
    "products": [
      {
        "product": {...},
        "produced": {...},
        "inSales": {...},
        "inStock": {...},
        "reprocessed": {...},
        "balance": {...},
        "status": "error",
        "message": "..."
      }
    ],
    "summary": {...}
  }
}
```

---

## 🔍 Detalle por Producto

### Producto 1: Pulpo Fresco Rizado

```json
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
}
```

**Análisis**:
- ✅ Producido: 700kg
- ⚠️ En Venta: 725kg (más que producido)
- ✅ En Stock: 200kg
- ✅ Re-procesado: 10kg
- ❌ **Balance: -235kg** (hay más contabilizado que producido)
- ❌ **Estado: error** (discrepancia > 5%)

---

### Producto 2: Breca

```json
{
  "product": {
    "id": 110,
    "name": "Breca"
  },
  "produced": {
    "weight": 200.0,
    "boxes": 0
  },
  "inSales": {
    "weight": 0.0,
    "boxes": 0
  },
  "inStock": {
    "weight": 500.0,
    "boxes": 1
  },
  "reprocessed": {
    "weight": 0.0,
    "boxes": 0
  },
  "balance": {
    "weight": -300.0,
    "percentage": -150.0
  },
  "status": "error",
  "message": "Hay 300.0kg más contabilizado (150.0%)"
}
```

**Análisis**:
- ✅ Producido: 200kg
- ✅ En Venta: 0kg
- ⚠️ En Stock: 500kg (más del doble que producido)
- ✅ Re-procesado: 0kg
- ❌ **Balance: -300kg** (hay 300kg más en stock que producido)
- ❌ **Estado: error** (discrepancia > 5%)

---

### Producto 3: Alacha congelada mediana

```json
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
```

**Análisis**:
- ✅ Producido: 400kg
- ✅ En Venta: 0kg
- ✅ En Stock: 0kg
- ✅ Re-procesado: 0kg
- ❌ **Balance: 400kg** (todo el producto producido falta)
- ❌ **Estado: error** (discrepancia > 5%)

---

## 📊 Resumen de Conciliación

```json
{
  "summary": {
    "totalProducts": 3,
    "productsOk": 0,
    "productsWarning": 0,
    "productsError": 3,
    "totalProducedWeight": 1300.0,
    "totalContabilizedWeight": 1435.0,
    "totalBalanceWeight": -135.0,
    "overallStatus": "error"
  }
}
```

**Análisis Global**:
- **Total producido**: 1300kg
- **Total contabilizado**: 1435kg (venta + stock + re-procesado)
- **Balance total**: -135kg (hay más contabilizado que producido)
- **Estado general**: `error` (hay problemas significativos)

---

## 🎯 Estados Posibles

### Por Producto

| Estado | Condición | Significado |
|--------|-----------|-------------|
| `ok` | `|balance.weight| ≤ 0.01kg` | Todo contabilizado correctamente ✅ |
| `warning` | `0.01kg < |balance.weight| ≤ 5%` | Pequeña discrepancia, revisar ⚠️ |
| `error` | `|balance.weight| > 5%` | Discrepancia significativa, acción requerida ❌ |

### Estado General

El `overallStatus` se calcula con los mismos umbrales aplicados al balance total.

---

## 💡 Interpretación de Valores Negativos

Si `balance.weight` es **negativo**, significa que hay **más contabilizado** que producido:

**Posibles causas**:
- Cajas con lote incorrecto asignadas a esta producción
- Productos de otro lote asignados incorrectamente
- Errores en el registro de producción
- Duplicación de datos

**Ejemplo**:
```json
{
  "produced": {"weight": 700.0},
  "inSales": {"weight": 725.0},  // Más que producido
  "balance": {"weight": -235.0}  // Error: hay más contabilizado
}
```

---

## 📚 Archivo Completo

Ver archivo: `EJEMPLO-RESPUESTA-process-tree-v5-con-conciliacion.json`

Este archivo contiene el ejemplo completo con todos los campos y estructuras.

---

**Ejemplo creado**: 2025-01-27

