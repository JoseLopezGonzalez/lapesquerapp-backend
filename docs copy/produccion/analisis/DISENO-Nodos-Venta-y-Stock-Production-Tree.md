# Diseño: Nodos de Venta y Stock en Production Tree

## 📋 Resumen Ejecutivo

Este documento detalla el diseño para añadir nuevos nodos al endpoint `GET /v2/productions/{id}/process-tree` que representen:
- **Nodos de Venta**: Palets asignados a pedidos con cajas disponibles
- **Nodos de Stock**: Palets almacenados en almacenes con cajas disponibles

**⚠️ IMPORTANTE**: Los nodos de venta y stock se añaden **como hijos de los nodos finales** del árbol de procesos. Se crea **UN SOLO nodo de venta y UN SOLO nodo de stock por cada nodo final** (no por producto), agrupando todos los productos que produce ese nodo final.

**Fecha de Diseño**: 2025-01-27  
**Fecha de Actualización**: 2025-01-27 (v3 - Estructura Final)  
**Estado**: ✅ **Implementado - Estructura Final**

### Decisiones Finales Aprobadas (v3 - Estructura Final)

- ✅ **Opción B** para palets en pedidos/almacén: Si tiene pedido → venta, si no tiene pedido pero está almacenado → stock
- ✅ **Agrupación FINAL**: **UN SOLO nodo de venta por nodo final** (agrupa todos los productos del nodo final)
- ✅ **Agrupación FINAL**: **UN SOLO nodo de stock por nodo final** (agrupa todos los productos del nodo final)
- ✅ **Estructura interna**: Dentro de cada pedido/almacén hay un array de productos con sus palets
- ✅ **Solo cajas disponibles**: Filtrar por `isAvailable = true`
- ✅ **Filtro por lote**: Obligatorio en todas las queries (`Box.lot = Production.lot`)

---

## 🎯 Objetivo

Extender el árbol de procesos de producción (`production tree`) para incluir información sobre:
1. **Venta de productos**: Qué productos del lote están siendo vendidos (asignados a pedidos)
2. **Stock disponible**: Qué productos del lote están almacenados en almacenes

Esto permitirá visualizar el flujo completo desde producción hasta venta/almacenamiento.

---

## 📊 Estructura Actual del Árbol

El endpoint actual `/v2/productions/{id}/process-tree` retorna:

```json
{
  "message": "Árbol de procesos obtenido correctamente.",
  "data": {
    "processNodes": [
      {
        "id": 1,
        "productionId": 1,
        "process": { "id": 1, "name": "Proceso X" },
        "inputs": [...],
        "outputs": [...],
        "children": [...],
        "totalInputWeight": 100.50,
        "totalOutputWeight": 95.30,
        ...
      }
    ],
    "totals": {
      "totalInputWeight": 100.50,
      "totalOutputWeight": 95.30,
      "totalWaste": 5.20,
      ...
    }
  }
}
```

---

## 🔄 Estructura Propuesta

### Nueva Estructura del Response - Jerárquica

Los nodos de venta y stock se añaden **como hijos de los nodos finales** del árbol de procesos, o como nodos independientes si no hay nodo final para ese producto.

```json
{
  "message": "Árbol de procesos obtenido correctamente.",
  "data": {
    "processNodes": [
      {
        "id": 1,
        "isFinal": true,
        "outputs": [
          { "productId": 5, "product": { "id": 5, "name": "Atún en Lata" } }
        ],
        "children": [
          // ✨ NUEVO: Nodos de venta/stock como hijos del nodo final
          {
            "type": "sales",
            "id": "sales-5-123",
            "parentRecordId": 1,  // ID del nodo final padre
            ...
          },
          {
            "type": "stock",
            "id": "stock-5-3",
            "parentRecordId": 1,  // ID del nodo final padre
            ...
          }
        ]
      },
      {
        "id": 2,
        "isFinal": false,
        "children": [...]
      }
      // Si no hay nodo final para un producto, se crea nodo sin padre
      {
        "type": "sales",
        "id": "sales-6-124",
        "parentRecordId": null,  // Sin padre
        ...
      }
    ],
    "totals": {
      "totalInputWeight": 100.50,
      "totalOutputWeight": 95.30,
      "totalWaste": 5.20,
      "totalSalesWeight": 50.20,      // ✨ NUEVO
      "totalStockWeight": 45.10,      // ✨ NUEVO
      ...
    }
  }
}
```

### ⚠️ Cambio Importante en la Lógica

**Antes**: Los nodos de venta/stock se añadían como arrays separados.

**Ahora**: Los nodos de venta/stock se añaden **dentro del árbol jerárquico**:
- Si existe un **nodo final** que produce el mismo producto → Los nodos de venta/stock son **hijos** de ese nodo final
- Si **NO existe** un nodo final para ese producto → Los nodos de venta/stock se crean como nodos independientes (sin padre) pero dentro de `processNodes`

---

## 📦 Nodos de Venta (Sales Nodes)

### Descripción

Representan productos del lote que están siendo vendidos (asignados a pedidos). Se agrupan por:
- **Producto**
- **Pedido** (Order)

### Criterios de Inclusión

Un palet se incluye en los nodos de venta si:
1. ✅ Tiene `order_id` no nulo (asignado a un pedido)
2. ✅ Tiene cajas disponibles (`isAvailable = true`)
3. ✅ **Las cajas pertenecen al lote de la producción** (`Box.lot = Production.lot`) ⚠️ **CRÍTICO**

**Nota**: Solo se buscan palets y cajas que tengan el mismo lote que la producción. Esto asegura que solo mostramos venta/stock del lote específico que estamos visualizando.

### Estructura de un Nodo de Venta

**✨ IMPORTANTE (v3 - Estructura Final)**: Se crea **UN SOLO nodo de venta por nodo final** con todos los productos que produce ese nodo final. Dentro de cada pedido hay un array de productos.

```json
{
  "type": "sales",
  "id": "sales-{finalNodeId}",  // 👈 ID del nodo final, NO del producto
  "parentRecordId": 2,  // ✨ ID del nodo final padre (null si no tiene padre)
  "productionId": 1,    // ID de la producción
  "orders": [
    {
      "order": {
        "id": 123,
        "formattedId": "#00123",
        "customer": {
          "id": 45,
          "name": "Supermercado Central"
        },
        "loadDate": "2024-02-15T00:00:00Z",
        "status": "pending"
      },
      "products": [  // 👈 Array de productos en este pedido
        {
          "product": {
            "id": 5,
            "name": "Atún en Lata 200g"
          },
          "pallets": [
            {
              "id": 789,
              "availableBoxesCount": 10,
              "totalAvailableWeight": 25.50
            }
          ],
          "totalBoxes": 10,
          "totalNetWeight": 25.50
        },
        {
          "product": {
            "id": 6,
            "name": "Atún en Aceite"
          },
          "pallets": [...],
          "totalBoxes": 5,
          "totalNetWeight": 12.50
        }
      ],
      "totalBoxes": 15,  // Total del pedido (suma de todos los productos)
      "totalNetWeight": 38.0
    },
    {
      "order": {
        "id": 124,
        "formattedId": "#00124",
        "customer": {
          "id": 46,
          "name": "Otro Cliente"
        },
        "loadDate": "2024-02-20T00:00:00Z",
        "status": "pending"
      },
      "products": [...],
      "totalBoxes": 8,
      "totalNetWeight": 20.0
    }
  ],
  "totalBoxes": 23,  // Total de TODOS los pedidos
  "totalNetWeight": 58.0,  // Total de TODOS los pedidos
  "summary": {
    "ordersCount": 2,  // Número de pedidos
    "productsCount": 2,  // 👈 Número de productos diferentes del nodo final
    "palletsCount": 4,  // Total de palets
    "boxesCount": 23,  // Total de cajas
    "netWeight": 58.0  // Peso total
  },
  "children": []  // Los nodos de venta/stock no tienen hijos
}
```

### Relación con Nodos Finales

**Lógica de matching**:
1. Obtener todos los nodos finales del árbol (`isFinal = true`)
2. Para cada nodo final, obtener sus outputs (productos producidos)
3. Para cada producto en venta/stock, buscar si existe un nodo final que produzca ese producto (`ProductionOutput.product_id`)
4. Si hay match → El nodo de venta/stock se añade como hijo del nodo final
5. Si NO hay match → El nodo de venta/stock se crea sin padre (`parentRecordId: null`)

### Agrupación (v3 - Estructura Final)

**✨ FINAL**: Se crea **UN SOLO nodo de venta por nodo final**:
- **Un nodo de venta** agrupa **TODOS los productos** que produce el nodo final
- Contiene **todos los pedidos** donde están esos productos (desglose en array `orders`)
- Dentro de cada pedido, hay un array de `products` con todos los productos del nodo final que están en ese pedido
- Cada producto dentro de un pedido tiene sus propios palets y totales
- Los totales del nodo (`totalBoxes`, `totalNetWeight`) son la suma de todos los pedidos y todos los productos

---

## 📦 Nodos de Stock (Stock Nodes)

### Descripción

Representan productos del lote que están almacenados en almacenes. Se agrupan por:
- **Producto**
- **Almacén** (Store)

### Criterios de Inclusión

Un palet se incluye en los nodos de stock si:
1. ✅ Está almacenado (`state_id = 2` - stored)
2. ✅ Tiene una relación en `stored_pallets` (está en un almacén)
3. ✅ Tiene cajas disponibles (`isAvailable = true`)
4. ✅ **Las cajas pertenecen al lote de la producción** (`Box.lot = Production.lot`) ⚠️ **CRÍTICO**
5. ✅ NO está asignado a un pedido (`order_id IS NULL`)

**Nota**: Solo se buscan palets y cajas que tengan el mismo lote que la producción. Si un palet tiene pedido, va a nodos de venta, no a stock.

### Estructura de un Nodo de Stock

**✨ IMPORTANTE (v3 - Estructura Final)**: Se crea **UN SOLO nodo de stock por nodo final** con todos los productos que produce ese nodo final. Dentro de cada almacén hay un array de productos.

```json
{
  "type": "stock",
  "id": "stock-{finalNodeId}",  // 👈 ID del nodo final, NO del producto
  "parentRecordId": 2,  // ✨ ID del nodo final padre (null si no tiene padre)
  "productionId": 1,    // ID de la producción
  "stores": [
    {
      "store": {
        "id": 3,
        "name": "Almacén Central",
        "temperature": -18.00
      },
      "products": [  // 👈 Array de productos en este almacén
        {
          "product": {
            "id": 5,
            "name": "Atún en Lata 200g"
          },
          "pallets": [
            {
              "id": 456,
              "availableBoxesCount": 15,
              "totalAvailableWeight": 38.25,
              "position": "A-12"
            }
          ],
          "totalBoxes": 15,
          "totalNetWeight": 38.25
        },
        {
          "product": {
            "id": 6,
            "name": "Atún en Aceite"
          },
          "pallets": [...],
          "totalBoxes": 8,
          "totalNetWeight": 20.0
        }
      ],
      "totalBoxes": 23,  // Total del almacén (suma de todos los productos)
      "totalNetWeight": 58.25
    },
    {
      "store": {
        "id": 4,
        "name": "Almacén Norte",
        "temperature": -20.00
      },
      "products": [...],
      "totalBoxes": 10,
      "totalNetWeight": 25.0
    }
  ],
  "totalBoxes": 33,  // Total de TODOS los almacenes
  "totalNetWeight": 83.25,  // Total de TODOS los almacenes
  "summary": {
    "storesCount": 2,  // Número de almacenes
    "productsCount": 2,  // 👈 Número de productos diferentes del nodo final
    "palletsCount": 4,  // Total de palets
    "boxesCount": 33,  // Total de cajas
    "netWeight": 83.25  // Peso total
  },
  "children": []  // Los nodos de stock no tienen hijos
}
```

### Agrupación (v3 - Estructura Final)

**✨ FINAL**: Se crea **UN SOLO nodo de stock por nodo final**:
- **Un nodo de stock** agrupa **TODOS los productos** que produce el nodo final
- Contiene **todos los almacenes** donde están esos productos (desglose en array `stores`)
- Dentro de cada almacén, hay un array de `products` con todos los productos del nodo final que están en ese almacén
- Cada producto dentro de un almacén tiene sus propios palets y totales
- Los totales del nodo (`totalBoxes`, `totalNetWeight`) son la suma de todos los almacenes y todos los productos

---

## 🔗 Algoritmo de Matching y Vinculación

### Paso 1: Identificar Nodos Finales

Recorrer el árbol de procesos y encontrar todos los nodos donde `isFinal = true`:

```php
// Pseudocódigo
foreach ($processNodes as $node) {
    if ($node['isFinal'] === true) {
        // Extraer productos de los outputs
        foreach ($node['outputs'] as $output) {
            $finalNodesByProduct[$output['productId']][] = $node['id'];
        }
    }
    // Recursivamente buscar en hijos
    if (!empty($node['children'])) {
        // Buscar en children
    }
}
```

Resultado: `$finalNodesByProduct = ['productId' => [nodeId1, nodeId2, ...]]`

### Paso 2: Obtener Datos de Venta/Stock

Obtener todos los palets con cajas disponibles del lote y agruparlos por producto:

```php
// Datos de venta agrupados por producto
$salesData = [
    'productId' => [
        'orderId' => [
            'pallets' => [...],
            'totalBoxes' => ...,
            'totalNetWeight' => ...
        ]
    ]
];

// Datos de stock agrupados por producto
$stockData = [
    'productId' => [
        'storeId' => [
            'pallets' => [...],
            'totalBoxes' => ...,
            'totalNetWeight' => ...
        ]
    ]
];
```

### Paso 3: Vincular Nodos de Venta/Stock a Nodos Finales (v3 - Estructura Final)

**✨ NUEVA LÓGICA**: Agrupar por nodo final, no por producto.

Para cada nodo final:

1. **Obtener todos los productos que produce el nodo final**
   - Extraer productos de los `outputs` del nodo final
   
2. **Recopilar datos de venta/stock para TODOS esos productos**
   - Buscar en `$salesData` y `$stockData` todos los productos del nodo final
   
3. **Si hay datos de venta para alguno de los productos**:
   - Crear **UN SOLO nodo de venta** para el nodo final
   - Agrupar todos los productos del nodo final en ese nodo
   - Añadirlo como hijo del nodo final (`parentRecordId = finalNodeId`)
   
4. **Si hay datos de stock para alguno de los productos**:
   - Crear **UN SOLO nodo de stock** para el nodo final
   - Agrupar todos los productos del nodo final en ese nodo
   - Añadirlo como hijo del nodo final (`parentRecordId = finalNodeId`)

**Para productos sin nodo final o con ambigüedad** (múltiples nodos finales):
- Crear nodos huérfanos agrupados por producto (un nodo por producto)
- Añadirlos al nivel raíz de `processNodes` (`parentRecordId = null`)

### Paso 4: Casos Especiales

#### Caso 1: Múltiples Nodos Finales con el Mismo Producto

**Problema**: ¿Qué hacer cuando hay varios nodos finales que producen el mismo producto?

**Ejemplo**:
```
Nodo Final 1 (ID: 5) → Output: Producto A (10kg)
Nodo Final 2 (ID: 8) → Output: Producto A (15kg)
```

**Opciones de Solución**:

| Opción | Descripción | Pros | Contras |
|--------|-------------|------|---------|
| **A: Sin Padre (Orphan)** | Crear nodos de venta/stock sin `parentRecordId` | Evita ambigüedad, más claro | No muestra relación con producción |
| **B: Duplicar en Todos** | Añadir nodos de venta/stock a TODOS los nodos finales | Muestra todas las relaciones | Puede ser confuso, datos duplicados |
| **C: Primer Nodo** | Asignar solo al primer nodo final encontrado | Simple, una sola relación | Puede ser arbitrario |
| **D: Último Nodo** | Asignar solo al último nodo final (más reciente) | Lógica temporal | Puede no ser el correcto |
| **E: Distribuir Proporcionalmente** | Dividir según peso de cada nodo final | Más preciso | Complejo de calcular |

**✅ Recomendación: Opción A - Sin Padre (Orphan Nodes)**

**Justificación**:
- Evita ambigüedad sobre cuál nodo final es el "correcto"
- Los nodos de venta/stock representan el estado actual (venta/stock), no necesariamente la producción específica
- Si hay múltiples nodos finales, puede ser que el producto se haya producido en diferentes procesos, y no podemos determinar de cuál viene cada caja
- Es más seguro crear nodos sin padre cuando hay ambigüedad

**Implementación**:
```php
// Si hay múltiples nodos finales para un producto
if (count($finalNodesByProduct[$productId]) > 1) {
    // Crear nodos de venta/stock SIN PADRE (orphan)
    $orphanNodes = $this->createSalesNodesForProduct($productId, $data, null);
    // NO asignar a ningún nodo final
} else {
    // Solo un nodo final → asignar normalmente
    $parentNodeId = $finalNodesByProduct[$productId][0];
    $salesNodes = $this->createSalesNodesForProduct($productId, $data, $parentNodeId);
}
```

#### Caso 2: Múltiples Productos en un Nodo Final (v3 - Estructura Final)

**✨ NUEVO**: Si un nodo final produce varios productos, se crea **UN SOLO nodo de venta y UN SOLO nodo de stock** que agrupan **TODOS los productos** del nodo final.

- Un nodo final con 3 productos → **1 nodo de venta** (con los 3 productos) + **1 nodo de stock** (con los 3 productos)
- No hay múltiples nodos por producto, sino un solo nodo que agrupa todos los productos del nodo final
- Dentro de cada pedido/almacén, hay un array de productos con sus respectivos palets y totales

---

## 🔍 Lógica de Consulta

### Query para Nodos de Venta

```php
// Obtener palets con cajas disponibles del lote, asignados a pedidos
// ⚠️ IMPORTANTE: Solo cajas con el mismo lote que la producción
$salesPallets = Pallet::query()
    ->whereNotNull('order_id')
    ->whereHas('boxes.box', function ($query) use ($productionLot) {
        $query->where('lot', $productionLot)  // ⚠️ Filtrar por lote de la producción
              ->whereDoesntHave('productionInputs');  // Solo cajas disponibles
    })
    ->with([
        'order.customer',
        'boxes.box' => function ($query) use ($productionLot) {
            $query->where('lot', $productionLot)  // ⚠️ Filtrar por lote
                  ->whereDoesntHave('productionInputs')
                  ->with('product');
        }
    ])
    ->get();
```

**⚠️ Nota Crítica**: El filtro por lote (`Box.lot = Production.lot`) es **obligatorio** en todas las queries. Solo mostramos palets y cajas del lote específico de la producción.

### Query para Nodos de Stock

```php
// Obtener palets almacenados con cajas disponibles del lote
// ⚠️ IMPORTANTE: Solo cajas con el mismo lote que la producción
$stockPallets = Pallet::query()
    ->stored()  // state_id = 2
    ->whereHas('storedPallet')  // Tiene relación en stored_pallets
    ->whereNull('order_id')  // Solo palets SIN pedido (si tiene pedido, va a venta)
    ->whereHas('boxes.box', function ($query) use ($productionLot) {
        $query->where('lot', $productionLot)  // ⚠️ Filtrar por lote de la producción
              ->whereDoesntHave('productionInputs');  // Solo cajas disponibles
    })
    ->with([
        'storedPallet.store',
        'boxes.box' => function ($query) use ($productionLot) {
            $query->where('lot', $productionLot)  // ⚠️ Filtrar por lote
                  ->whereDoesntHave('productionInputs')
                  ->with('product');
        }
    ])
    ->get();
```

**⚠️ Nota Crítica**: El filtro por lote (`Box.lot = Production.lot`) es **obligatorio** en todas las queries. Solo mostramos palets y cajas del lote específico de la producción.

---

## 🏗️ Implementación Propuesta

### ⚠️ Cambio Fundamental en el Enfoque

En lugar de añadir nodos de venta/stock como arrays separados, debemos:
1. **Identificar nodos finales** en el árbol existente
2. **Hacer match** entre productos de nodos finales y productos en venta/stock
3. **Añadir nodos de venta/stock como hijos** de los nodos finales correspondientes
4. Si no hay match, crear nodos independientes (sin padre) dentro de `processNodes`

### 1. Nuevo Método en Modelo `Production` - Calcular Nodos de Venta/Stock y Vincularlos

```php
// app/Models/Production.php

/**
 * Calcular nodos de venta para el árbol de procesos
 */
public function calculateSalesNodes()
{
    $lot = $this->lot;
    
    // Obtener palets asignados a pedidos con cajas del lote disponibles
    $salesPallets = Pallet::query()
        ->whereNotNull('order_id')
        ->whereHas('boxes.box', function ($query) use ($lot) {
            $query->where('lot', $lot)
                  ->whereDoesntHave('productionInputs');
        })
        ->with([
            'order.customer',
            'boxes.box' => function ($query) use ($lot) {
                $query->where('lot', $lot)
                      ->whereDoesntHave('productionInputs')
                      ->with('product');
            }
        ])
        ->get();
    
    // Agrupar por producto y pedido
    $grouped = $salesPallets->flatMap(function ($pallet) {
        return $pallet->boxes->map(function ($palletBox) use ($pallet) {
            $box = $palletBox->box;
            if (!$box || !$box->isAvailable || !$box->product) {
                return null;
            }
            
            return [
                'product' => $box->product,
                'order' => $pallet->order,
                'pallet' => $pallet,
                'box' => $box,
            ];
        });
    })->filter()->groupBy(function ($item) {
        return $item['product']->id . '-' . $item['order']->id;
    });
    
    // Construir nodos
    return $grouped->map(function ($items, $key) {
        $first = $items->first();
        $product = $first['product'];
        $order = $first['order'];
        
        $pallets = $items->groupBy(function ($item) {
            return $item['pallet']->id;
        })->map(function ($palletBoxes, $palletId) {
            $pallet = $palletBoxes->first()['pallet'];
            $boxes = $palletBoxes->pluck('box');
            
            return [
                'id' => $pallet->id,
                'availableBoxesCount' => $boxes->count(),
                'totalAvailableWeight' => $boxes->sum('net_weight'),
            ];
        })->values();
        
        return [
            'type' => 'sales',
            'id' => "sales-{$product->id}-{$order->id}",
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
            ],
            'order' => [
                'id' => $order->id,
                'formattedId' => $order->formatted_id,
                'customer' => $order->customer ? [
                    'id' => $order->customer->id,
                    'name' => $order->customer->name,
                ] : null,
                'loadDate' => $order->load_date?->toIso8601String(),
                'status' => $order->status,
            ],
            'pallets' => $pallets->toArray(),
            'totalBoxes' => $pallets->sum('availableBoxesCount'),
            'totalNetWeight' => round($pallets->sum('totalAvailableWeight'), 2),
            'summary' => [
                'palletsCount' => $pallets->count(),
                'boxesCount' => $pallets->sum('availableBoxesCount'),
                'netWeight' => round($pallets->sum('totalAvailableWeight'), 2),
            ],
        ];
    })->values()->toArray();
}

/**
 * Calcular nodos de stock para el árbol de procesos
 */
public function calculateStockNodes()
{
    $lot = $this->lot;
    
    // Obtener palets almacenados con cajas del lote disponibles
    $stockPallets = Pallet::query()
        ->stored()
        ->whereHas('storedPallet')
        ->whereNull('order_id')  // Solo sin pedido (¿o permitir ambos?)
        ->whereHas('boxes.box', function ($query) use ($lot) {
            $query->where('lot', $lot)
                  ->whereDoesntHave('productionInputs');
        })
        ->with([
            'storedPallet.store',
            'boxes.box' => function ($query) use ($lot) {
                $query->where('lot', $lot)
                      ->whereDoesntHave('productionInputs')
                      ->with('product');
            }
        ])
        ->get();
    
    // Agrupar por producto y almacén
    $grouped = $stockPallets->flatMap(function ($pallet) {
        return $pallet->boxes->map(function ($palletBox) use ($pallet) {
            $box = $palletBox->box;
            if (!$box || !$box->isAvailable || !$box->product || !$pallet->storedPallet) {
                return null;
            }
            
            return [
                'product' => $box->product,
                'store' => $pallet->storedPallet->store,
                'pallet' => $pallet,
                'storedPallet' => $pallet->storedPallet,
                'box' => $box,
            ];
        });
    })->filter()->groupBy(function ($item) {
        return $item['product']->id . '-' . $item['store']->id;
    });
    
    // Construir nodos
    return $grouped->map(function ($items, $key) {
        $first = $items->first();
        $product = $first['product'];
        $store = $first['store'];
        
        $pallets = $items->groupBy(function ($item) {
            return $item['pallet']->id;
        })->map(function ($palletBoxes, $palletId) {
            $pallet = $palletBoxes->first()['pallet'];
            $storedPallet = $palletBoxes->first()['storedPallet'];
            $boxes = $palletBoxes->pluck('box');
            
            return [
                'id' => $pallet->id,
                'availableBoxesCount' => $boxes->count(),
                'totalAvailableWeight' => $boxes->sum('net_weight'),
                'position' => $storedPallet->position,
            ];
        })->values();
        
        return [
            'type' => 'stock',
            'id' => "stock-{$product->id}-{$store->id}",
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
            ],
            'store' => [
                'id' => $store->id,
                'name' => $store->name,
                'temperature' => $store->temperature,
            ],
            'pallets' => $pallets->toArray(),
            'totalBoxes' => $pallets->sum('availableBoxesCount'),
            'totalNetWeight' => round($pallets->sum('totalAvailableWeight'), 2),
            'summary' => [
                'palletsCount' => $pallets->count(),
                'boxesCount' => $pallets->sum('availableBoxesCount'),
                'netWeight' => round($pallets->sum('totalAvailableWeight'), 2),
            ],
        ];
    })->values()->toArray();
}
```

### 2. Modificar Método `calculateGlobalTotals()`

```php
// app/Models/Production.php

/**
 * Calcular totales globales incluyendo venta y stock
 */
public function calculateGlobalTotals()
{
    $totals = [
        'totalInputWeight' => round($this->total_input_weight, 2),
        'totalOutputWeight' => round($this->total_output_weight, 2),
        'totalWaste' => round($this->total_waste, 2),
        'totalWastePercentage' => round($this->waste_percentage, 2),
        'totalYield' => 0,
        'totalYieldPercentage' => 0,
        'totalInputBoxes' => $this->total_input_boxes,
        'totalOutputBoxes' => $this->total_output_boxes,
    ];
    
    // Calcular totales de venta
    $salesNodes = $this->calculateSalesNodes();
    $totals['totalSalesWeight'] = round(collect($salesNodes)->sum('totalNetWeight'), 2);
    $totals['totalSalesBoxes'] = collect($salesNodes)->sum('totalBoxes');
    $totals['totalSalesPallets'] = collect($salesNodes)->sum('summary.palletsCount');
    
    // Calcular totales de stock
    $stockNodes = $this->calculateStockNodes();
    $totals['totalStockWeight'] = round(collect($stockNodes)->sum('totalNetWeight'), 2);
    $totals['totalStockBoxes'] = collect($stockNodes)->sum('totalBoxes');
    $totals['totalStockPallets'] = collect($stockNodes)->sum('summary.palletsCount');
    
    return $totals;
}
```

### 3. Nuevo Método para Vincular Nodos de Venta/Stock con Nodos Finales

```php
// app/Models/Production.php

/**
 * Añadir nodos de venta y stock como hijos de nodos finales o como nodos independientes
 */
public function attachSalesAndStockNodes(array $processNodes)
{
    $lot = $this->lot;
    
    // 1. Identificar todos los nodos finales y sus productos
    $finalNodesByProduct = [];
    $this->collectFinalNodesAndProducts($processNodes, $finalNodesByProduct);
    
    // 2. Obtener datos de venta y stock (solo del lote de la producción)
    $salesData = $this->getSalesDataByProduct($lot);
    $stockData = $this->getStockDataByProduct($lot);
    
    // 3. Añadir nodos de venta como hijos de nodos finales
    // ⚠️ Si hay múltiples nodos finales para un producto, se crean sin padre
    $this->attachSalesNodesToFinalNodes($processNodes, $finalNodesByProduct, $salesData);
    
    // 4. Añadir nodos de stock como hijos de nodos finales
    // ⚠️ Si hay múltiples nodos finales para un producto, se crean sin padre
    $this->attachStockNodesToFinalNodes($processNodes, $finalNodesByProduct, $stockData);
    
    // 5. Añadir nodos de venta/stock sin padre:
    //    - Sin nodo final correspondiente
    //    - Con múltiples nodos finales (ambigüedad)
    $orphanSalesNodes = $this->createOrphanSalesNodes($salesData, $finalNodesByProduct);
    $orphanStockNodes = $this->createOrphanStockNodes($stockData, $finalNodesByProduct);
    
    // Añadir nodos huérfanos al final de processNodes
    return array_merge($processNodes, $orphanSalesNodes, $orphanStockNodes);
}

/**
 * Recursivamente identificar nodos finales y sus productos
 */
private function collectFinalNodesAndProducts(array $nodes, array &$finalNodesByProduct)
{
    foreach ($nodes as $node) {
        if (isset($node['isFinal']) && $node['isFinal'] === true) {
            // Extraer productos de los outputs
            foreach ($node['outputs'] ?? [] as $output) {
                $productId = $output['productId'] ?? null;
                if ($productId) {
                    if (!isset($finalNodesByProduct[$productId])) {
                        $finalNodesByProduct[$productId] = [];
                    }
                    $finalNodesByProduct[$productId][] = $node['id'];
                }
            }
        }
        
        // Recursivamente procesar hijos
        if (!empty($node['children'])) {
            $this->collectFinalNodesAndProducts($node['children'], $finalNodesByProduct);
        }
    }
}

/**
 * Obtener datos de venta agrupados por producto
 */
private function getSalesDataByProduct(string $lot)
{
    // Query similar a calculateSalesNodes() pero retornando datos agrupados por producto
    // Retorna: ['productId' => [...datos de venta...]]
}

/**
 * Obtener datos de stock agrupados por producto
 */
private function getStockDataByProduct(string $lot)
{
    // Query similar a calculateStockNodes() pero retornando datos agrupados por producto
    // Retorna: ['productId' => [...datos de stock...]]
}

/**
 * Añadir nodos de venta como hijos de nodos finales
 * ⚠️ Solo si hay UN SOLO nodo final para el producto (sin ambigüedad)
 */
private function attachSalesNodesToFinalNodes(array &$nodes, array $finalNodesByProduct, array $salesData)
{
    foreach ($nodes as &$node) {
        if (!empty($node['children'])) {
            $this->attachSalesNodesToFinalNodes($node['children'], $finalNodesByProduct, $salesData);
        }
        
        if (isset($node['isFinal']) && $node['isFinal'] === true) {
            // Buscar productos en outputs de este nodo final
            foreach ($node['outputs'] ?? [] as $output) {
                $productId = $output['productId'] ?? null;
                if ($productId && isset($salesData[$productId])) {
                    // ⚠️ Solo asignar si hay UN SOLO nodo final para este producto
                    // Si hay múltiples, se crearán como orphan nodes
                    if (isset($finalNodesByProduct[$productId]) && count($finalNodesByProduct[$productId]) === 1) {
                        // Verificar que este es el único nodo final
                        if ($finalNodesByProduct[$productId][0] === $node['id']) {
                            // Crear nodos de venta para este producto
                            $salesNodes = $this->createSalesNodesForProduct($productId, $salesData[$productId], $node['id']);
                            
                            // Añadir como hijos
                            if (!isset($node['children'])) {
                                $node['children'] = [];
                            }
                            $node['children'] = array_merge($node['children'], $salesNodes);
                        }
                    }
                }
            }
        }
    }
}

/**
 * Añadir nodos de stock como hijos de nodos finales
 * ⚠️ Solo si hay UN SOLO nodo final para el producto (sin ambigüedad)
 */
private function attachStockNodesToFinalNodes(array &$nodes, array $finalNodesByProduct, array $stockData)
{
    foreach ($nodes as &$node) {
        if (!empty($node['children'])) {
            $this->attachStockNodesToFinalNodes($node['children'], $finalNodesByProduct, $stockData);
        }
        
        if (isset($node['isFinal']) && $node['isFinal'] === true) {
            // Buscar productos en outputs de este nodo final
            foreach ($node['outputs'] ?? [] as $output) {
                $productId = $output['productId'] ?? null;
                if ($productId && isset($stockData[$productId])) {
                    // ⚠️ Solo asignar si hay UN SOLO nodo final para este producto
                    // Si hay múltiples, se crearán como orphan nodes
                    if (isset($finalNodesByProduct[$productId]) && count($finalNodesByProduct[$productId]) === 1) {
                        // Verificar que este es el único nodo final
                        if ($finalNodesByProduct[$productId][0] === $node['id']) {
                            // Crear nodos de stock para este producto
                            $stockNodes = $this->createStockNodesForProduct($productId, $stockData[$productId], $node['id']);
                            
                            // Añadir como hijos
                            if (!isset($node['children'])) {
                                $node['children'] = [];
                            }
                            $node['children'] = array_merge($node['children'], $stockNodes);
                        }
                    }
                }
            }
        }
    }
}

/**
 * Crear nodos de venta sin padre:
 * - Producto no tiene nodo final
 * - Producto tiene múltiples nodos finales (ambigüedad)
 */
private function createOrphanSalesNodes(array $salesData, array $finalNodesByProduct)
{
    $orphanNodes = [];
    
    foreach ($salesData as $productId => $data) {
        // Caso 1: No hay nodo final para este producto
        // Caso 2: Hay múltiples nodos finales (ambigüedad)
        if (!isset($finalNodesByProduct[$productId]) || count($finalNodesByProduct[$productId]) > 1) {
            $orphanNodes = array_merge($orphanNodes, $this->createSalesNodesForProduct($productId, $data, null));
        }
    }
    
    return $orphanNodes;
}

/**
 * Crear nodos de stock sin padre:
 * - Producto no tiene nodo final
 * - Producto tiene múltiples nodos finales (ambigüedad)
 */
private function createOrphanStockNodes(array $stockData, array $finalNodesByProduct)
{
    $orphanNodes = [];
    
    foreach ($stockData as $productId => $data) {
        // Caso 1: No hay nodo final para este producto
        // Caso 2: Hay múltiples nodos finales (ambigüedad)
        if (!isset($finalNodesByProduct[$productId]) || count($finalNodesByProduct[$productId]) > 1) {
            $orphanNodes = array_merge($orphanNodes, $this->createStockNodesForProduct($productId, $data, null));
        }
    }
    
    return $orphanNodes;
}
```

### 4. Modificar Controlador `ProductionController::getProcessTree()`

```php
// app/Http/Controllers/v2/ProductionController.php

public function getProcessTree(string $id)
{
    $production = Production::findOrFail($id);

    $tree = $production->buildProcessTree();

    // Convertir a estructura del diagrama
    $processNodes = $tree->map(function ($record) {
        return $record->getNodeData();
    })->toArray();

    // ✨ Añadir nodos de venta y stock como hijos de nodos finales
    $processNodes = $production->attachSalesAndStockNodes($processNodes);

    return response()->json([
        'message' => 'Árbol de procesos obtenido correctamente.',
        'data' => [
            'processNodes' => $processNodes,  // Ahora incluye nodos de venta/stock dentro del árbol
            'totals' => $production->calculateGlobalTotals(),
        ],
    ]);
}
```

---

## ✅ Decisiones Finales Aprobadas

### Resumen de Decisiones

| # | Pregunta | Decisión | Estado |
|---|----------|----------|--------|
| 1 | Palets en pedidos y almacenados | **Opción B**: Si tiene pedido → venta, si no tiene pedido pero está almacenado → stock | ✅ Aprobado |
| 2 | Agrupación de nodos | Agrupar por `producto + pedido/almacén` (independiente de cantidad de palets) | ✅ Aprobado |
| 3 | Filtro por lote | **OBLIGATORIO** - Solo cajas con `Box.lot = Production.lot` | ✅ Aprobado |
| 4 | Cajas disponibles vs totales | **Solo disponibles** - `isAvailable = true` | ✅ Aprobado |
| 5 | Estructura jerárquica | Como hijos de nodos finales | ✅ Aprobado |
| 6 | Múltiples nodos finales | Crear nodos sin padre (orphan) cuando hay ambigüedad | ✅ Aprobado |

---

## 🤔 Puntos de Decisión (Histórico - Ya Decididos)

### 1. **Palets en Pedidos Y Almacenados**

**Pregunta**: ¿Un palet puede estar asignado a un pedido Y almacenado al mismo tiempo?

**Opciones**:
- **A**: Solo mostrar en venta si tiene pedido (ignorar almacén)
- **B**: Solo mostrar en stock si NO tiene pedido
- **C**: Mostrar en ambos (puede estar en almacén esperando despacho del pedido)

**✅ DECISIÓN FINAL: Opción B** - Si tiene pedido, va a venta. Si NO tiene pedido pero está almacenado, va a stock.

**Implementación**:
- Palets con `order_id IS NOT NULL` → Van a nodos de venta
- Palets con `order_id IS NULL` pero almacenados (`state_id = 2` y tiene `storedPallet`) → Van a nodos de stock

---

### 2. **Agrupación de Nodos**

**Pregunta**: ¿Cómo agrupar cuando hay múltiples palets del mismo producto en el mismo pedido/almacén?

**✅ DECISIÓN FINAL**: 
- **Venta**: Agrupar por `producto + pedido` (independientemente de cuántos palets haya)
- **Stock**: Agrupar por `producto + almacén` (independientemente de cuántos palets haya)
- Dentro del nodo, incluir lista de **todos los palets** con sus detalles

**Significado**:
- Si hay 3 palets del mismo producto en el mismo pedido → **Un solo nodo de venta** con los 3 palets dentro
- Si hay 5 palets del mismo producto en el mismo almacén → **Un solo nodo de stock** con los 5 palets dentro
- La agrupación es por pedido/almacén, no por palet individual

**Ejemplo**:
```json
{
  "type": "sales",
  "id": "sales-5-123",
  "product": { "id": 5, "name": "Atún" },
  "order": { "id": 123 },
  "pallets": [
    { "id": 1, "availableBoxesCount": 10, ... },
    { "id": 2, "availableBoxesCount": 8, ... },
    { "id": 3, "availableBoxesCount": 12, ... }
  ],
  "totalBoxes": 30,  // Suma de todas las cajas de los 3 palets
  "totalNetWeight": 75.50
}
```

---

### 3. **Filtro por Lote**

**Pregunta**: ¿Solo mostrar cajas del mismo lote que la producción?

**Respuesta**: ✅ **SÍ - OBLIGATORIO** - Solo cajas donde `Box.lot = Production.lot`

**Confirmado**: ✅ Buscaremos palets y cajas con el lote de la producción. Este filtro es crítico y debe aplicarse en todas las queries.

---

### 4. **Cajas Disponibles vs Totales**

**Pregunta**: ¿Mostrar solo cajas disponibles o todas?

**✅ DECISIÓN FINAL**: **Solo disponibles** - Solo cajas con `isAvailable = true` (no usadas en producción)

**Implementación**: 
- Filtrar por `whereDoesntHave('productionInputs')` en todas las queries
- Solo contar y sumar peso de cajas disponibles
- Los totales en los nodos reflejan solo cajas disponibles

---

### 6. **Estructura Jerárquica - Nodos como Hijos**

**Pregunta**: ¿Cómo integrar los nodos de venta/stock en el árbol?

**Respuesta**: ✅ **Como hijos de nodos finales**
- Los nodos de venta/stock deben ser **hijos** de los nodos finales que producen el mismo producto
- Si no hay nodo final para un producto, los nodos se crean **sin padre** pero dentro de `processNodes`
- El matching se hace por `product_id` entre `ProductionOutput.product_id` y el producto de las cajas

**Aprobado**: ✅ Esta es la nueva estructura jerárquica.

---

### 7. **Múltiples Nodos Finales con el Mismo Producto** ⚠️ CASO ESPECIAL

**Pregunta**: ¿Qué hacer cuando hay dos o más nodos finales que producen el mismo producto?

**Problema**: No podemos determinar de cuál nodo final provienen las cajas en venta/stock.

**Solución Aprobada**: ✅ **Crear nodos de venta/stock SIN PADRE (orphan nodes)**

**Justificación**:
- Evita ambigüedad sobre cuál nodo final es el "correcto"
- Si hay múltiples nodos finales, puede ser que el producto se haya producido en diferentes procesos
- No podemos determinar de cuál proceso viene cada caja en venta/stock
- Es más seguro crear nodos sin padre cuando hay ambigüedad

**Implementación**:
```php
// Si hay múltiples nodos finales para un producto
if (count($finalNodesByProduct[$productId]) > 1) {
    // Crear nodos SIN PADRE (orphan)
    $orphanNodes = $this->createSalesNodesForProduct($productId, $data, null);
} else {
    // Solo un nodo final → asignar normalmente
    $parentNodeId = $finalNodesByProduct[$productId][0];
    $salesNodes = $this->createSalesNodesForProduct($productId, $data, $parentNodeId);
}
```

**Aprobado**: ✅ Opción A - Sin Padre cuando hay múltiples nodos finales.

---

### 5. **Rendimiento de Queries**

**Preocupación**: Las queries pueden ser costosas con muchos palets/cajas.

**Optimización propuesta**:
- Usar eager loading eficiente
- Filtrar a nivel de query (no en memoria)
- Considerar índices en `boxes.lot` y `pallets.order_id`

---

## 📝 Resumen de Campos en Totales

```json
{
  "totals": {
    // Existentes
    "totalInputWeight": 100.50,
    "totalOutputWeight": 95.30,
    "totalWaste": 5.20,
    "totalWastePercentage": 5.17,
    "totalYield": 0,
    "totalYieldPercentage": 0,
    "totalInputBoxes": 10,
    "totalOutputBoxes": 8,
    
    // Nuevos - Venta
    "totalSalesWeight": 50.20,
    "totalSalesBoxes": 5,
    "totalSalesPallets": 2,
    
    // Nuevos - Stock
    "totalStockWeight": 45.10,
    "totalStockBoxes": 4,
    "totalStockPallets": 1
  }
}
```

---

## ✅ Checklist de Implementación

### Fase 1: Modelo Production
- [ ] Implementar método para identificar nodos finales y sus productos
- [ ] Implementar métodos para obtener datos de venta/stock agrupados por producto
- [ ] Implementar `attachSalesAndStockNodes()` para vincular nodos al árbol
- [ ] Modificar `calculateGlobalTotals()` para incluir totales de venta/stock
- [ ] Añadir tests unitarios

### Fase 2: Controlador
- [ ] Modificar `getProcessTree()` para incluir nodos de venta/stock
- [ ] Verificar formato de respuesta

### Fase 3: Optimización
- [ ] Revisar queries y añadir eager loading eficiente
- [ ] Verificar índices en BD
- [ ] Optimizar agrupación en memoria

### Fase 4: Documentación
- [ ] Actualizar documentación del endpoint
- [ ] Añadir ejemplos de respuesta
- [ ] Documentar estructura de nodos

### Fase 5: Testing
- [ ] Crear tests de integración
- [ ] Probar con diferentes escenarios (con/sin pedidos, con/sin stock)
- [ ] Verificar rendimiento con datos grandes

---

## 🔄 Ejemplo de Respuesta Completa - Estructura Jerárquica

```json
{
  "message": "Árbol de procesos obtenido correctamente.",
  "data": {
    "processNodes": [
      {
        "id": 1,
        "process": { "id": 1, "name": "Fileteado" },
        "isFinal": false,
        "totalInputWeight": 100.50,
        "totalOutputWeight": 95.30,
        "children": [
          {
            "id": 2,
            "process": { "id": 5, "name": "Envasado Final" },
            "isFinal": true,
            "outputs": [
              {
                "id": 10,
                "productId": 5,
                "product": {
                  "id": 5,
                  "name": "Atún en Lata 200g"
                },
                "weightKg": 95.30
              }
            ],
            "children": [
              // ✨ Nodo de venta como hijo del nodo final
              {
                "type": "sales",
                "id": "sales-5-123",
                "parentRecordId": 2,
                "productionId": 1,
                "product": {
                  "id": 5,
                  "name": "Atún en Lata 200g"
                },
                "order": {
                  "id": 123,
                  "formattedId": "#00123",
                  "customer": {
                    "id": 45,
                    "name": "Supermercado Central"
                  }
                },
                "pallets": [
                  {
                    "id": 789,
                    "availableBoxesCount": 10,
                    "totalAvailableWeight": 25.50
                  }
                ],
                "totalBoxes": 10,
                "totalNetWeight": 25.50,
                "children": []
              },
              // ✨ Nodo de stock como hijo del nodo final
              {
                "type": "stock",
                "id": "stock-5-3",
                "parentRecordId": 2,
                "productionId": 1,
                "product": {
                  "id": 5,
                  "name": "Atún en Lata 200g"
                },
                "store": {
                  "id": 3,
                  "name": "Almacén Central",
                  "temperature": -18.00
                },
                "pallets": [
                  {
                    "id": 456,
                    "availableBoxesCount": 15,
                    "totalAvailableWeight": 38.25,
                    "position": "A-12"
                  }
                ],
                "totalBoxes": 15,
                "totalNetWeight": 38.25,
                "children": []
              }
            ]
          }
        ]
      },
      // ✨ Ejemplo de nodo de venta sin padre (no hay nodo final para ese producto)
      {
        "type": "sales",
        "id": "sales-6-124",
        "parentRecordId": null,
        "productionId": 1,
        "product": {
          "id": 6,
          "name": "Atún en Aceite"
        },
        "order": {
          "id": 124,
          "formattedId": "#00124",
          "customer": {
            "id": 46,
            "name": "Otro Cliente"
          }
        },
        "pallets": [
          {
            "id": 790,
            "availableBoxesCount": 5,
            "totalAvailableWeight": 12.75
          }
        ],
        "totalBoxes": 5,
        "totalNetWeight": 12.75,
        "children": []
      }
    ],
    "totals": {
      "totalInputWeight": 100.50,
      "totalOutputWeight": 95.30,
      "totalWaste": 5.20,
      "totalSalesWeight": 38.25,
      "totalSalesBoxes": 15,
      "totalSalesPallets": 2,
      "totalStockWeight": 38.25,
      "totalStockBoxes": 15,
      "totalStockPallets": 1
    }
  }
}
```

### 📝 Notas sobre la Estructura

1. **Nodos de venta/stock como hijos**: Los nodos de venta y stock se añaden dentro de `children` de los nodos finales correspondientes
2. **Nodos huérfanos**: Si un producto no tiene nodo final, el nodo de venta/stock se crea sin padre (`parentRecordId: null`) pero dentro de `processNodes`
3. **Campo `type`**: Distingue entre nodos de proceso (`null` o ausente), `sales`, y `stock`
4. **Campo `parentRecordId`**: Indica el ID del nodo final padre, o `null` si no tiene padre

---

## 🎯 Próximos Pasos

1. ✅ **Decisiones confirmadas** - Todas las decisiones finales han sido aprobadas
2. **Implementar** siguiendo el checklist
3. **Probar** con datos reales
4. **Documentar** cambios en API

---

## 📋 Resumen Ejecutivo Final

### Decisiones Clave Aprobadas

1. **Criterios de Inclusión**:
   - Venta: Palets con `order_id IS NOT NULL` y cajas disponibles del lote
   - Stock: Palets almacenados (`state_id = 2`) con `order_id IS NULL` y cajas disponibles del lote

2. **Agrupación**:
   - Por `producto + pedido` para venta (todos los palets agrupados en un nodo)
   - Por `producto + almacén` para stock (todos los palets agrupados en un nodo)

3. **Filtros Obligatorios**:
   - `Box.lot = Production.lot` (lote de la producción)
   - `isAvailable = true` (solo cajas disponibles)

4. **Estructura Jerárquica**:
   - Nodos de venta/stock como hijos de nodos finales (cuando hay un solo nodo final para el producto)
   - Nodos sin padre (orphan) cuando hay múltiples nodos finales o no hay nodo final

**Estado**: ✅ **Listo para Implementación**

---

**Fin del Documento de Diseño**

