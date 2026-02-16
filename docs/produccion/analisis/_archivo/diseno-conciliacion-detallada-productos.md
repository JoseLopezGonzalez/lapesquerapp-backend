# Diseño: Conciliación Detallada por Productos

**Fecha**: 2025-01-27  
**Objetivo**: Generar una conciliación minuciosa por producto que muestre exactamente qué hay en cada estado (venta, stock, re-procesado, faltante).

---

## 📊 Estructura de la Conciliación Detallada

Para cada producto producido en la producción, se mostrará:

```
Producto X:
├── Producido: 700kg (X cajas)
├── En Venta: 725kg (Y cajas)
├── En Stock: 200kg (Z cajas)
├── Re-procesado: 10kg (W cajas)
├── Balance: -235kg (más contabilizado que producido) ⚠️
└── Estado: error
```

---

## 🎯 Método: `getDetailedReconciliationByProduct()`

### Propósito

Calcular la conciliación detallada para **cada producto** producido en la producción, mostrando:
- Cuánto se produjo
- Dónde está (venta, stock, re-procesado)
- Cuánto falta o sobra
- Estado de la conciliación (ok/warning/error)

### Retorno

```php
[
    'products' => [
        [
            'product' => ['id' => 104, 'name' => 'Pulpo Fresco Rizado'],
            'produced' => ['weight' => 700.0, 'boxes' => 0],
            'inSales' => ['weight' => 725.0, 'boxes' => 145],
            'inStock' => ['weight' => 200.0, 'boxes' => 40],
            'reprocessed' => ['weight' => 10.0, 'boxes' => 2],
            'balance' => ['weight' => -235.0, 'percentage' => -33.57],
            'status' => 'error',  // ok | warning | error
            'message' => 'Hay más contabilizado que producido'
        ],
        // ... más productos
    ],
    'summary' => [
        'totalProducts' => 3,
        'productsOk' => 1,
        'productsWarning' => 0,
        'productsError' => 2,
        'totalProducedWeight' => 1300.0,
        'totalContabilizedWeight' => 1435.0,
        'totalBalanceWeight' => -135.0,
        'overallStatus' => 'error'
    ]
]
```

---

## 📋 Cálculo por Producto

### Paso 1: Obtener todos los productos producidos

Recorrer todos los nodos finales y recopilar todos los productos únicos con sus cantidades producidas.

```php
foreach ($finalNodes as $finalNode) {
    foreach ($finalNode->outputs as $output) {
        $productId = $output->product_id;
        $produced[$productId] = ($produced[$productId] ?? 0) + $output->weight_kg;
    }
}
```

### Paso 2: Calcular contabilizado por producto

Para cada producto, calcular:
- **En venta**: Sumar peso de cajas del lote en palets con pedido
- **En stock**: Sumar peso de cajas del lote en palets almacenados
- **Re-procesado**: Sumar peso de cajas del lote usadas en otros procesos

### Paso 3: Calcular balance

```
Balance = Producido - (Venta + Stock + Re-procesado)
```

Si Balance > 0: **Faltante**  
Si Balance < 0: **Exceso** (error)  
Si Balance = 0: **OK**

### Paso 4: Determinar estado

- **ok**: Balance = 0 (con tolerancia de 0.01kg)
- **warning**: |Balance| <= 5% del producido
- **error**: |Balance| > 5% del producido

---

## 🔧 Implementación

### Método en `Production.php`

```php
public function getDetailedReconciliationByProduct(): array
{
    $lot = $this->lot;
    
    // 1. Obtener todos los productos producidos (de nodos finales)
    $allFinalNodes = $this->getFinalNodes();
    $producedByProduct = [];
    
    foreach ($allFinalNodes as $finalNode) {
        foreach ($finalNode->outputs as $output) {
            $productId = $output->product_id;
            if (!isset($producedByProduct[$productId])) {
                $producedByProduct[$productId] = [
                    'product' => $output->product,
                    'weight' => 0,
                    'boxes' => 0,
                ];
            }
            $producedByProduct[$productId]['weight'] += $output->weight_kg;
            $producedByProduct[$productId]['boxes'] += $output->boxes;
        }
    }
    
    // 2. Obtener datos de venta, stock y re-procesados
    $salesData = $this->getSalesDataByProduct($lot);
    $stockData = $this->getStockDataByProduct($lot);
    $reprocessedData = $this->getReprocessedDataByProduct($lot);
    
    // 3. Calcular conciliación por producto
    $productsData = [];
    $summary = [
        'totalProducts' => 0,
        'productsOk' => 0,
        'productsWarning' => 0,
        'productsError' => 0,
        'totalProducedWeight' => 0,
        'totalContabilizedWeight' => 0,
        'totalBalanceWeight' => 0,
        'overallStatus' => 'ok',
    ];
    
    foreach ($producedByProduct as $productId => $producedInfo) {
        $product = $producedInfo['product'];
        $producedWeight = $producedInfo['weight'];
        $producedBoxes = $producedInfo['boxes'];
        
        // Calcular en venta
        $inSalesWeight = 0;
        $inSalesBoxes = 0;
        if (isset($salesData[$productId])) {
            foreach ($salesData[$productId] as $orderData) {
                foreach ($orderData['pallets'] as $palletData) {
                    $boxes = collect($palletData['boxes']);
                    $inSalesBoxes += $boxes->count();
                    $inSalesWeight += $boxes->sum('net_weight');
                }
            }
        }
        
        // Calcular en stock
        $inStockWeight = 0;
        $inStockBoxes = 0;
        if (isset($stockData[$productId])) {
            foreach ($stockData[$productId] as $storeData) {
                foreach ($storeData['pallets'] as $palletData) {
                    $boxes = collect($palletData['boxes']);
                    $inStockBoxes += $boxes->count();
                    $inStockWeight += $boxes->sum('net_weight');
                }
            }
        }
        
        // Calcular re-procesado
        $reprocessedWeight = 0;
        $reprocessedBoxes = 0;
        if (isset($reprocessedData[$productId])) {
            foreach ($reprocessedData[$productId] as $processData) {
                $boxes = collect($processData['boxes']);
                $reprocessedBoxes += $boxes->count();
                $reprocessedWeight += $boxes->sum('net_weight');
            }
        }
        
        // Calcular balance
        $contabilizedWeight = $inSalesWeight + $inStockWeight + $reprocessedWeight;
        $balanceWeight = $producedWeight - $contabilizedWeight;
        $balancePercentage = $producedWeight > 0 
            ? ($balanceWeight / $producedWeight) * 100 
            : 0;
        
        // Determinar estado
        $status = 'ok';
        $message = 'Todo contabilizado correctamente';
        
        if (abs($balanceWeight) > 0.01) {
            $absPercentage = abs($balancePercentage);
            if ($absPercentage > 5) {
                $status = 'error';
                if ($balanceWeight > 0) {
                    $message = "Faltan {$balanceWeight}kg ({$absPercentage}%)";
                } else {
                    $message = "Hay {$abs( $balanceWeight)}kg más contabilizado ({$absPercentage}%)";
                }
            } else {
                $status = 'warning';
                if ($balanceWeight > 0) {
                    $message = "Faltan {$balanceWeight}kg ({$absPercentage}%)";
                } else {
                    $message = "Hay {$abs( $balanceWeight)}kg más contabilizado ({$absPercentage}%)";
                }
            }
        }
        
        $productsData[] = [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
            ],
            'produced' => [
                'weight' => round($producedWeight, 2),
                'boxes' => $producedBoxes,
            ],
            'inSales' => [
                'weight' => round($inSalesWeight, 2),
                'boxes' => $inSalesBoxes,
            ],
            'inStock' => [
                'weight' => round($inStockWeight, 2),
                'boxes' => $inStockBoxes,
            ],
            'reprocessed' => [
                'weight' => round($reprocessedWeight, 2),
                'boxes' => $reprocessedBoxes,
            ],
            'balance' => [
                'weight' => round($balanceWeight, 2),
                'percentage' => round($balancePercentage, 2),
            ],
            'status' => $status,
            'message' => $message,
        ];
        
        // Actualizar resumen
        $summary['totalProducts']++;
        if ($status === 'ok') $summary['productsOk']++;
        elseif ($status === 'warning') $summary['productsWarning']++;
        else $summary['productsError']++;
        
        $summary['totalProducedWeight'] += $producedWeight;
        $summary['totalContabilizedWeight'] += $contabilizedWeight;
        $summary['totalBalanceWeight'] += $balanceWeight;
    }
    
    // Determinar estado general
    $totalBalanceAbs = abs($summary['totalBalanceWeight']);
    $totalBalancePercentage = $summary['totalProducedWeight'] > 0
        ? ($totalBalanceAbs / $summary['totalProducedWeight']) * 100
        : 0;
    
    if ($totalBalanceAbs > 0.01) {
        if ($totalBalancePercentage > 5) {
            $summary['overallStatus'] = 'error';
        } else {
            $summary['overallStatus'] = 'warning';
        }
    }
    
    $summary['totalProducedWeight'] = round($summary['totalProducedWeight'], 2);
    $summary['totalContabilizedWeight'] = round($summary['totalContabilizedWeight'], 2);
    $summary['totalBalanceWeight'] = round($summary['totalBalanceWeight'], 2);
    
    return [
        'products' => $productsData,
        'summary' => $summary,
    ];
}
```

---

## 📡 Integración en el Endpoint

### Agregar al endpoint `getProcessTree()`

```php
public function getProcessTree(string $id)
{
    $production = Production::findOrFail($id);
    
    $tree = $production->buildProcessTree();
    $processNodes = $tree->map(function ($record) {
        return $record->getNodeData();
    })->toArray();
    
    $processNodes = $production->attachSalesAndStockNodes($processNodes);
    
    return response()->json([
        'message' => 'Árbol de procesos obtenido correctamente.',
        'data' => [
            'processNodes' => $processNodes,
            'totals' => $production->calculateGlobalTotals(),
            'reconciliation' => $production->getDetailedReconciliationByProduct(), // ✨ NUEVO
        ],
    ]);
}
```

---

## 📊 Ejemplo de Respuesta

```json
{
  "message": "Árbol de procesos obtenido correctamente.",
  "data": {
    "processNodes": [...],
    "totals": {...},
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
}
```

---

## ✅ Estados

| Estado | Condición | Significado |
|--------|-----------|-------------|
| `ok` | Balance = 0 (±0.01kg) | Todo contabilizado correctamente |
| `warning` | 0 < |Balance| ≤ 5% | Pequeña discrepancia, revisar |
| `error` | |Balance| > 5% | Discrepancia significativa, acción requerida |

---

**Diseño completado**: 2025-01-27

