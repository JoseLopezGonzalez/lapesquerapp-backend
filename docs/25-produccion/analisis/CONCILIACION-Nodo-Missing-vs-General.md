# Conciliación: Nodo Missing vs Conciliación General

**Fecha**: 2025-01-27  
**Concepto**: Relación entre el nodo `missing` (por nodo final) y la conciliación general de la producción

---

## 📊 Conceptos

### 1. Nodo Missing (Conciliación Parcial)

**Alcance**: Por nodo final (proceso final de producción)

**Balance calculado**:
```
Para cada producto del nodo final:
  Faltante = Producido - Venta - Stock - Re-procesado
```

**Propósito**: Verificar que todo lo producido en un proceso final esté contabilizado

**Ubicación**: Como nodo hijo de cada nodo final en el árbol de procesos

---

### 2. Conciliación General (Balance Total)

**Alcance**: Toda la producción (lote completo)

**Balance calculado**:
```
Total Producido = Suma de todos los outputs de todos los nodos finales
Total Contabilizado = Venta + Stock + Re-procesado
Faltante Total = Total Producido - Total Contabilizado
```

**Propósito**: Verificar que toda la producción del lote esté contabilizada a nivel global

**Ubicación**: En los totales globales de la producción (actualmente no implementado)

---

## 🔄 Relación entre Ambos

### Conciliación Parcial (Nodo Missing)
```
Nodo Final 1
├── Produce: Producto A (100kg), Producto B (50kg)
├── sales: Producto A (80kg), Producto B (30kg)
├── stock: Producto A (10kg)
├── reprocessed: Producto A (5kg)
└── missing: Producto A (5kg faltante), Producto B (20kg faltante)
```

### Conciliación General (Balance Total)
```
Producción Completa (Lote "LOT-001")
├── Total Producido: 150kg
├── Total en Venta: 110kg
├── Total en Stock: 10kg
├── Total Re-procesado: 5kg
└── Total Faltante: 25kg
```

---

## ✅ Estado Actual

### Implementado

1. **Nodo Missing por Nodo Final** ✅
   - Calcula balance: Producido - Venta - Stock - Re-procesado
   - Muestra faltantes por producto
   - Se muestra como hijo de cada nodo final

2. **Totales Globales** (`calculateGlobalTotals()`) ✅
   - `totalSalesWeight`: Total en venta
   - `totalSalesBoxes`: Total de cajas en venta
   - `totalStockWeight`: Total en stock
   - `totalStockBoxes`: Total de cajas en stock
   - **Falta**: Total re-procesado y total faltante

3. **Método `reconcile()`** ✅
   - Compara Producción Declarada vs Stock Real
   - Solo compara outputs declarados con cajas en stock
   - No incluye venta, re-procesados, ni faltantes

---

## 🎯 Lo que Falta: Conciliación General Completa

### Totales Globales Completos

Actualmente en `calculateGlobalTotals()` falta:

```php
// ✅ Ya existe
'totalSalesWeight' => ...
'totalStockWeight' => ...

// ❌ Falta agregar
'totalReprocessedWeight' => ...  // Total re-procesado
'totalReprocessedBoxes' => ...
'totalMissingWeight' => ...      // Total faltante
'totalMissingBoxes' => ...
```

### Balance Total de Conciliación

Debería existir un método que calcule:

```
Balance Total = Total Producido - Total Venta - Total Stock - Total Re-procesado

Si Balance Total > 0: Hay faltantes
Si Balance Total < 0: Hay error (más contabilizado que producido)
Si Balance Total = 0: Todo contabilizado ✅
```

---

## 📋 Propuesta: Ampliar `calculateGlobalTotals()`

### Agregar Totales de Re-procesados y Faltantes

```php
public function calculateGlobalTotals()
{
    // ... código existente ...
    
    // Calcular totales de re-procesados
    $reprocessedData = $this->getReprocessedDataByProduct($lot);
    $totalReprocessedWeight = 0;
    $totalReprocessedBoxes = 0;
    
    foreach ($reprocessedData as $productId => $processes) {
        foreach ($processes as $processData) {
            $boxes = collect($processData['boxes']);
            $totalReprocessedBoxes += $boxes->count();
            $totalReprocessedWeight += $boxes->sum('net_weight');
        }
    }
    
    // Calcular totales de faltantes
    $totalOutputWeight = $this->total_output_weight; // Total producido
    $totalMissingWeight = $totalOutputWeight 
        - $totalSalesWeight 
        - $totalStockWeight 
        - $totalReprocessedWeight;
    
    $totals['totalReprocessedWeight'] = round($totalReprocessedWeight, 2);
    $totals['totalReprocessedBoxes'] = $totalReprocessedBoxes;
    $totals['totalMissingWeight'] = round($totalMissingWeight, 2);
    
    return $totals;
}
```

---

## 🎯 Estructura de Conciliación Completa

### En el Response del Endpoint `/v2/productions/{id}/process-tree`

```json
{
  "data": {
    "processNodes": [...],
    "totals": {
      "totalInputWeight": 730.0,
      "totalOutputWeight": 1300.0,
      "totalSalesWeight": 725.0,
      "totalStockWeight": 700.0,
      "totalReprocessedWeight": 10.0,  // ✨ NUEVO
      "totalMissingWeight": -135.0,    // ✨ NUEVO (negativo = error)
      
      "totalSalesBoxes": 145,
      "totalStockBoxes": 41,
      "totalReprocessedBoxes": 2,      // ✨ NUEVO
      "totalMissingBoxes": 0,          // ✨ NUEVO
      
      "conciliation": {                 // ✨ NUEVO
        "status": "error",              // ok | warning | error
        "message": "Hay más contabilizado que producido",
        "balance": -135.0
      }
    }
  }
}
```

---

## 📊 Comparación

| Aspecto | Nodo Missing (Parcial) | Conciliación General |
|---------|----------------------|---------------------|
| **Alcance** | Por nodo final | Toda la producción |
| **Granularidad** | Por producto | Total agregado |
| **Propósito** | Verificar proceso final | Verificar lote completo |
| **Ubicación** | Hijo de nodo final | Totales globales |
| **Estado** | ✅ Implementado | ⚠️ Parcialmente implementado |

---

## ✅ Recomendación

1. **Ampliar `calculateGlobalTotals()`** para incluir:
   - Total re-procesado (peso y cajas)
   - Total faltante (peso y cajas)
   - Status de conciliación (ok/warning/error)

2. **Mantener el nodo `missing` por nodo final** para el detalle granular

3. **Agregar conciliación general** en los totales para el resumen ejecutivo

---

**Conclusión**: Sí, el nodo `missing` a nivel general es la conciliación. Actualmente está implementado parcialmente (solo por nodo final), pero falta agregarlo a nivel de totales globales.

---

**Documento creado**: 2025-01-27

