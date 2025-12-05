# 📊 Diseño: Nodos de Re-procesados y Faltantes

**Fecha**: 2025-01-27  
**Estado**: ✅ **DECISIÓN TOMADA** - Separar en dos nodos  
**Versión**: v1.0

---

## 📋 Resumen Ejecutivo

Crear **DOS nodos adicionales** que cuelguen del nodo final (además de `sales` y `stock`) para completar la trazabilidad del 100% de los productos producidos:

1. **Nodo de Re-procesados**: Cajas usadas como materia prima en otro proceso
2. **Nodo de Faltantes**: Cajas que realmente faltan o no están contabilizadas

---

## 🎯 Objetivo

Completar la **trazabilidad completa** de los productos:

```
Nodo Final
├── sales (productos en venta)
├── stock (productos almacenados)
├── reprocessed (productos re-procesados) ✨ NUEVO
└── missing (productos faltantes) ✨ NUEVO
```

**Balance completo**:
```
Producto Producido
  = En Venta
  + En Stock
  + Re-procesado (usado en otro proceso)
  + Faltante (no contabilizado)
```

---

## 📦 Nodo 1: Re-procesados / Consumidos

### Descripción

Representa productos del lote que fueron **usados como materia prima** en otro proceso de producción.

### Criterios de Inclusión

Una caja se incluye en el nodo de re-procesados si:

1. ✅ **Pertenece al lote de la producción** (`Box.lot = Production.lot`)
2. ✅ **No está disponible** (`isAvailable = false`)
3. ✅ **Fue usada en otro proceso** (tiene registro en `production_inputs`)
4. ✅ **Pertenece a un producto producido en el nodo final**

### Cómo Identificarlas

```php
// Cajas del lote que fueron consumidas en otro proceso
Box::where('lot', $productionLot)
   ->whereHas('productionInputs')  // Tiene al menos un ProductionInput
   ->with(['productionInputs.productionRecord'])  // Cargar procesos donde se usó
```

### Estructura del Nodo

```json
{
  "type": "reprocessed",
  "id": "reprocessed-{finalNodeId}",
  "parentRecordId": {finalNodeId},
  "productionId": 1,
  "products": [
    {
      "product": {
        "id": 5,
        "name": "Filetes de Atún"
      },
      "processes": [
        {
          "process": {
            "id": 10,
            "name": "Enlatado",
            "type": "processing"
          },
          "productionRecord": {
            "id": 15,
            "productionId": 2,
            "startedAt": "2024-02-10T08:00:00Z"
          },
          "boxes": [
            {
              "id": 1234,
              "netWeight": 9.5,
              "usedAt": "2024-02-10T09:30:00Z"
            }
          ],
          "totalBoxes": 5,
          "totalWeight": 47.5
        }
      ],
      "totalBoxes": 5,
      "totalWeight": 47.5
    }
  ],
  "summary": {
    "productsCount": 1,
    "processesCount": 1,
    "totalBoxes": 5,
    "totalWeight": 47.5
  },
  "children": []
}
```

### Agrupación

- **UN SOLO nodo por nodo final** (igual que sales/stock)
- Agrupa **TODOS los productos** del nodo final que fueron re-procesados
- Dentro de cada producto, agrupa por **proceso de destino** (dónde se usaron)
- Cada proceso muestra las cajas individuales que se usaron

---

## 🔍 Nodo 2: Faltantes / No Contabilizados

### Descripción

Representa productos del lote que **realmente faltan** o no están contabilizados:
- Están disponibles (`isAvailable = true`)
- NO están en venta (sin pedido)
- NO están en stock (sin almacén)
- NO fueron consumidas (sin `production_inputs`)

### Criterios de Inclusión

Una caja se incluye en el nodo de faltantes si:

1. ✅ **Pertenece al lote de la producción** (`Box.lot = Production.lot`)
2. ✅ **Está disponible** (`isAvailable = true`)
3. ✅ **NO está en venta**: No tiene palet con `order_id`
4. ✅ **NO está en stock**: No tiene palet almacenado (`state_id != 2` o sin `stored_pallet`)
5. ✅ **NO fue consumida**: No tiene `production_inputs`
6. ✅ **Pertenece a un producto producido en el nodo final**

### Cómo Identificarlas

```php
// Cajas del lote que están disponibles pero no están contabilizadas
Box::where('lot', $productionLot)
   ->whereDoesntHave('productionInputs')  // No fueron consumidas
   ->whereDoesntHave('palletBox.pallet', function($query) {
       $query->whereNotNull('order_id');  // No están en pedidos
   })
   ->whereDoesntHave('palletBox.pallet.storedPallet')  // No están almacenadas
   ->with('product')
```

### Cálculo de Faltantes

Para cada producto del nodo final:

```
Faltantes = Producción (ProductionOutput)
  - En Venta
  - En Stock
  - Re-procesados (consumidos)
```

O directamente:

```
Faltantes = Cajas del lote disponibles
  - Cajas en palets con pedido (venta)
  - Cajas en palets almacenados (stock)
  - Cajas consumidas (re-procesados)
```

### Estructura del Nodo

```json
{
  "type": "balance",
  "id": "balance-{finalNodeId}",
  "parentRecordId": {finalNodeId},
  "productionId": 1,
  "products": [
    {
      "product": {
        "id": 5,
        "name": "Filetes de Atún"
      },
      "produced": {
        "boxes": 100,
        "weight": 1000.0
      },
      "inSales": {
        "boxes": 50,
        "weight": 500.0
      },
      "inStock": {
        "boxes": 30,
        "weight": 300.0
      },
      "reprocessed": {
        "boxes": 15,
        "weight": 150.0
      },
      "balance": {
        "boxes": 5,
        "weight": 50.0,
        "percentage": 5.0
      },
      "boxes": [
        {
          "id": 1234,
          "netWeight": 10.0,
          "gs1_128": "1234567890123",
          "location": null  // Sin ubicación asignada
        }
      ]
    }
  ],
  "summary": {
    "productsCount": 1,
    "totalBalanceBoxes": 5,
    "totalBalanceWeight": 50.0
  },
  "children": []
}
```

### Agrupación

- **UN SOLO nodo por nodo final** (igual que sales/stock)
- Agrupa **TODOS los productos** del nodo final que tienen faltantes
- Muestra el cálculo completo: producido - venta - stock - re-procesado = faltante
- Lista las cajas individuales que faltan

---

## 🔗 Relación con el Nodo Final

Ambos nodos se añaden **como hijos del nodo final** igual que los nodos de venta y stock:

```
Nodo Final (ID: 2)
├── Produce: Producto 5, Producto 6
│
├── sales-2 (productos en venta)
├── stock-2 (productos almacenados)
├── reprocessed-2 (productos re-procesados) ✨ NUEVO
└── balance-2 (balance de productos: faltantes y sobras) ✨ NUEVO
```

### Cuándo Mostrar Cada Nodo

- **sales**: Solo si hay productos en venta
- **stock**: Solo si hay productos en stock
- **reprocessed**: Solo si hay productos re-procesados
- **balance**: Solo si hay productos con desbalance (`balance.weight != 0`)

---

## 📊 Estructura Completa del Árbol

### Ejemplo Visual

```
Nodo Final "Envasado" (ID: 2)
├── Outputs: Producto 5 (100kg), Producto 6 (50kg)
│
├── sales-2
│   └── Producto 5: 50kg en venta
│   └── Producto 6: 25kg en venta
│
├── stock-2
│   └── Producto 5: 30kg en almacén
│   └── Producto 6: 15kg en almacén
│
├── reprocessed-2 ✨
│   └── Producto 5: 15kg usado en proceso "Enlatado"
│   └── Producto 6: 5kg usado en proceso "Conservación"
│
└── missing-2 ✨
    └── Producto 5: 5kg faltantes
    └── Producto 6: 5kg faltantes
```

### Balance Total

```
Producto 5:
  Producción: 100kg
  - Venta: 50kg
  - Stock: 30kg
  - Re-procesado: 15kg
  - Faltante: 5kg
  = Total contabilizado: 100kg ✅

Producto 6:
  Producción: 50kg
  - Venta: 25kg
  - Stock: 15kg
  - Re-procesado: 5kg
  - Faltante: 5kg
  = Total contabilizado: 50kg ✅
```

---

## 🔍 Lógica de Consulta

### Query para Re-procesados

```php
// Para cada producto del nodo final, buscar cajas consumidas
$reprocessedBoxes = Box::where('lot', $productionLot)
    ->where('article_id', $productId)  // Producto del nodo final
    ->whereHas('productionInputs')  // Fue consumida
    ->with([
        'product',
        'productionInputs.productionRecord.process',
        'productionInputs.productionRecord.production'
    ])
    ->get();
```

### Query para Faltantes

```php
// Para cada producto del nodo final, calcular faltantes
$produced = ProductionOutput::where('production_record_id', $finalNodeId)
    ->where('product_id', $productId)
    ->sum('weight_kg');

$inSales = /* Sumar peso en venta */;
$inStock = /* Sumar peso en stock */;
$reprocessed = /* Sumar peso re-procesado */;

$missing = $produced - $inSales - $inStock - $reprocessed;

// O buscar cajas directamente
$missingBoxes = Box::where('lot', $productionLot)
    ->where('article_id', $productId)
    ->whereDoesntHave('productionInputs')
    ->whereDoesntHave('palletBox.pallet', function($q) {
        $q->whereNotNull('order_id');
    })
    ->whereDoesntHave('palletBox.pallet.storedPallet')
    ->get();
```

---

## ✅ Ventajas de Separar en Dos Nodos

1. **🎯 Claridad Semántica**
   - Re-procesados: tienen un destino claro (otro proceso)
   - Faltantes: estado desconocido (problema a investigar)

2. **📊 Diferentes Casos de Uso**
   - Re-procesados: Seguimiento de flujo de materiales
   - Faltantes: Detección de problemas operativos

3. **🔍 Trazabilidad Completa**
   - Permite ver el 100% del flujo
   - Identifica exactamente dónde está cada producto

4. **⚠️ Detección de Problemas**
   - Faltantes alertan sobre errores o pérdidas
   - Re-procesados muestran el flujo normal de transformación

---

## 📋 Checklist de Implementación

- [ ] Definir estructura exacta de cada nodo
- [ ] Implementar query para nodo de re-procesados
- [ ] Implementar query para nodo de faltantes
- [ ] Crear método `getReprocessedDataByProduct()`
- [ ] Crear método `getMissingDataByProduct()`
- [ ] Crear método `createReprocessedNodeForFinalNode()`
- [ ] Crear método `createMissingNodeForFinalNode()`
- [ ] Integrar en `attachSalesAndStockNodes()`
- [ ] Actualizar documentación
- [ ] Crear ejemplos JSON
- [ ] Documentar para frontend

---

## 🤔 Preguntas Pendientes

1. **¿Mostrar información del proceso destino en re-procesados?**
   - Sí: Más información, más útil para trazabilidad
   - No: Más simple

2. **¿Qué hacer si faltantes son negativos?** (más cajas de las esperadas)
   - Mostrar como "discrepancia positiva"
   - Ignorar
   - Generar alerta

3. **¿Incluir fecha de cuando se re-procesó?**
   - Sí: Útil para análisis temporal
   - No: Más simple

4. **¿Agrupar re-procesados por proceso destino?**
   - Sí: Más organizado (un proceso = una entrada)
   - No: Lista plana de cajas

---

**Estado**: ✅ **Diseño Aprobado** - Listo para implementación

