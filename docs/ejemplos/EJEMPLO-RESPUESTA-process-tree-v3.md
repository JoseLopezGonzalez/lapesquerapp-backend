# Ejemplo de Respuesta: GET /v2/productions/{id}/process-tree

## 📋 Resumen

Este documento contiene un ejemplo completo de respuesta del endpoint `GET /v2/productions/{id}/process-tree` con la nueva estructura de nodos de venta y stock (v3).

**Características del ejemplo**:
- ✅ Un nodo final que produce 2 productos diferentes (Filetes de Atún, Atún en Aceite)
- ✅ UN SOLO nodo de venta que agrupa ambos productos con desglose por pedido
- ✅ UN SOLO nodo de stock que agrupa ambos productos con desglose por almacén
- ✅ Múltiples pedidos y almacenes para demostrar la agrupación

---

## 🎯 Estructura del Ejemplo

```
Nodo Raíz (Eviscerado)
  └── Nodo Final (Fileteado Final) - Produce: Producto 5 y 6
      ├── Nodo Venta (sales-2)
      │   └── Pedido #00123
      │       ├── Producto 5: Filetes de Atún (10 cajas)
      │       └── Producto 6: Atún en Aceite (5 cajas)
      │   └── Pedido #00124
      │       └── Producto 5: Filetes de Atún (8 cajas)
      └── Nodo Stock (stock-2)
          └── Almacén Central
              ├── Producto 5: Filetes de Atún (27 cajas)
              └── Producto 6: Atún en Aceite (8 cajas)
          └── Almacén Norte
              └── Producto 5: Filetes de Atún (5 cajas)
```

---

## 📄 Ejemplo Completo de Respuesta

```json
{
  "message": "Árbol de procesos obtenido correctamente.",
  "data": {
    "processNodes": [
      {
        "id": 1,
        "productionId": 1,
        "production": {
          "id": 1,
          "lot": "LOT-2024-001",
          "openedAt": "2024-01-15T10:30:00Z",
          "closedAt": null
        },
        "parentRecordId": null,
        "parent": null,
        "processId": 3,
        "process": {
          "id": 3,
          "name": "Eviscerado",
          "type": "processing"
        },
        "startedAt": "2024-01-15T10:35:00Z",
        "finishedAt": "2024-01-15T12:00:00Z",
        "notes": "Proceso de eviscerado manual",
        "isRoot": true,
        "isFinal": false,
        "isCompleted": true,
        "totalInputWeight": 150.50,
        "totalOutputWeight": 120.30,
        "totalInputBoxes": 5,
        "totalOutputBoxes": 8,
        "waste": 30.20,
        "wastePercentage": 20.07,
        "yield": 0,
        "yieldPercentage": 0,
        "inputs": [...],
        "parentOutputConsumptions": [],
        "outputs": [...],
        "children": [
          {
            "id": 2,
            "productionId": 1,
            "parentRecordId": 1,
            "processId": 4,
            "process": {
              "id": 4,
              "name": "Fileteado Final",
              "type": "processing"
            },
            "isRoot": false,
            "isFinal": true,
            "isCompleted": true,
            "outputs": [
              {
                "id": 2,
                "productId": 5,
                "product": {
                  "id": 5,
                  "name": "Filetes de Atún"
                },
                "boxes": 10,
                "weightKg": 95.0
              },
              {
                "id": 3,
                "productId": 6,
                "product": {
                  "id": 6,
                  "name": "Atún en Aceite"
                },
                "boxes": 5,
                "weightKg": 95.0
              }
            ],
            "children": [
              {
                "type": "sales",
                "id": "sales-2",
                "parentRecordId": 2,
                "productionId": 1,
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
                    "products": [
                      {
                        "product": {
                          "id": 5,
                          "name": "Filetes de Atún"
                        },
                        "pallets": [
                          {
                            "id": 789,
                            "availableBoxesCount": 10,
                            "totalAvailableWeight": 95.0
                          }
                        ],
                        "totalBoxes": 10,
                        "totalNetWeight": 95.0
                      },
                      {
                        "product": {
                          "id": 6,
                          "name": "Atún en Aceite"
                        },
                        "pallets": [
                          {
                            "id": 790,
                            "availableBoxesCount": 5,
                            "totalAvailableWeight": 47.5
                          }
                        ],
                        "totalBoxes": 5,
                        "totalNetWeight": 47.5
                      }
                    ],
                    "totalBoxes": 15,
                    "totalNetWeight": 142.5
                  },
                  {
                    "order": {
                      "id": 124,
                      "formattedId": "#00124",
                      "customer": {
                        "id": 46,
                        "name": "Pescadería Marina"
                      },
                      "loadDate": "2024-02-20T00:00:00Z",
                      "status": "pending"
                    },
                    "products": [
                      {
                        "product": {
                          "id": 5,
                          "name": "Filetes de Atún"
                        },
                        "pallets": [
                          {
                            "id": 791,
                            "availableBoxesCount": 8,
                            "totalAvailableWeight": 76.0
                          }
                        ],
                        "totalBoxes": 8,
                        "totalNetWeight": 76.0
                      }
                    ],
                    "totalBoxes": 8,
                    "totalNetWeight": 76.0
                  }
                ],
                "totalBoxes": 23,
                "totalNetWeight": 218.5,
                "summary": {
                  "ordersCount": 2,
                  "productsCount": 2,
                  "palletsCount": 3,
                  "boxesCount": 23,
                  "netWeight": 218.5
                },
                "children": []
              },
              {
                "type": "stock",
                "id": "stock-2",
                "parentRecordId": 2,
                "productionId": 1,
                "stores": [
                  {
                    "store": {
                      "id": 3,
                      "name": "Almacén Central",
                      "temperature": -18.0
                    },
                    "products": [
                      {
                        "product": {
                          "id": 5,
                          "name": "Filetes de Atún"
                        },
                        "pallets": [
                          {
                            "id": 456,
                            "availableBoxesCount": 15,
                            "totalAvailableWeight": 142.5,
                            "position": "A-12"
                          },
                          {
                            "id": 457,
                            "availableBoxesCount": 12,
                            "totalAvailableWeight": 114.0,
                            "position": "A-13"
                          }
                        ],
                        "totalBoxes": 27,
                        "totalNetWeight": 256.5
                      },
                      {
                        "product": {
                          "id": 6,
                          "name": "Atún en Aceite"
                        },
                        "pallets": [
                          {
                            "id": 458,
                            "availableBoxesCount": 8,
                            "totalAvailableWeight": 152.0,
                            "position": "B-05"
                          }
                        ],
                        "totalBoxes": 8,
                        "totalNetWeight": 152.0
                      }
                    ],
                    "totalBoxes": 35,
                    "totalNetWeight": 408.5
                  },
                  {
                    "store": {
                      "id": 4,
                      "name": "Almacén Norte",
                      "temperature": -20.0
                    },
                    "products": [
                      {
                        "product": {
                          "id": 5,
                          "name": "Filetes de Atún"
                        },
                        "pallets": [
                          {
                            "id": 459,
                            "availableBoxesCount": 5,
                            "totalAvailableWeight": 47.5,
                            "position": "C-08"
                          }
                        ],
                        "totalBoxes": 5,
                        "totalNetWeight": 47.5
                      }
                    ],
                    "totalBoxes": 5,
                    "totalNetWeight": 47.5
                  }
                ],
                "totalBoxes": 40,
                "totalNetWeight": 456.0,
                "summary": {
                  "storesCount": 2,
                  "productsCount": 2,
                  "palletsCount": 4,
                  "boxesCount": 40,
                  "netWeight": 456.0
                },
                "children": []
              }
            ]
          }
        ]
      }
    ],
    "totals": {
      "totalInputWeight": 150.50,
      "totalOutputWeight": 215.30,
      "totalWaste": 0,
      "totalWastePercentage": 0,
      "totalYield": 64.80,
      "totalYieldPercentage": 43.06,
      "totalInputBoxes": 5,
      "totalOutputBoxes": 18,
      "totalSalesWeight": 218.5,
      "totalSalesBoxes": 23,
      "totalSalesPallets": 3,
      "totalStockWeight": 456.0,
      "totalStockBoxes": 40,
      "totalStockPallets": 4
    }
  }
}
```

---

## 🔍 Puntos Clave del Ejemplo

### 1. Nodo Final Produce Múltiples Productos

El nodo final (ID: 2) produce **2 productos**:
- Producto 5: "Filetes de Atún"
- Producto 6: "Atún en Aceite"

### 2. UN SOLO Nodo de Venta

**ID**: `sales-2` (corresponde al nodo final ID: 2)

**Contiene**:
- **2 pedidos** diferentes (#00123 y #00124)
- **Pedido #00123**: Tiene 2 productos (Producto 5 y 6)
- **Pedido #00124**: Tiene 1 producto (Producto 5)
- **Totales agregados**: 23 cajas, 218.5 kg

### 3. UN SOLO Nodo de Stock

**ID**: `stock-2` (corresponde al nodo final ID: 2)

**Contiene**:
- **2 almacenes** diferentes (Central y Norte)
- **Almacén Central**: Tiene 2 productos (Producto 5 y 6)
- **Almacén Norte**: Tiene 1 producto (Producto 5)
- **Totales agregados**: 40 cajas, 456.0 kg

### 4. Estructura de Agrupación

**Nodo de Venta**:
```
sales-2
└── orders[] (2 pedidos)
    ├── order #00123
    │   └── products[] (2 productos)
    │       ├── Producto 5: 10 cajas
    │       └── Producto 6: 5 cajas
    └── order #00124
        └── products[] (1 producto)
            └── Producto 5: 8 cajas
```

**Nodo de Stock**:
```
stock-2
└── stores[] (2 almacenes)
    ├── Almacén Central
    │   └── products[] (2 productos)
    │       ├── Producto 5: 27 cajas
    │       └── Producto 6: 8 cajas
    └── Almacén Norte
        └── products[] (1 producto)
            └── Producto 5: 5 cajas
```

---

## 📊 Campos Importantes

### Nodo de Venta (`type: "sales"`)

| Campo | Tipo | Descripción | Ejemplo |
|-------|------|-------------|---------|
| `id` | string | ID del nodo (formato: `sales-{finalNodeId}`) | `"sales-2"` |
| `parentRecordId` | number | ID del nodo final padre | `2` |
| `orders` | array | Array de pedidos con sus productos | Ver estructura abajo |
| `totalBoxes` | number | Total de cajas en TODOS los pedidos | `23` |
| `totalNetWeight` | number | Peso total en TODOS los pedidos | `218.5` |
| `summary.ordersCount` | number | Número de pedidos | `2` |
| `summary.productsCount` | number | Número de productos diferentes | `2` |

### Estructura de `orders[]`

Cada elemento del array `orders` tiene:

```json
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
  "products": [
    {
      "product": {
        "id": 5,
        "name": "Filetes de Atún"
      },
      "pallets": [
        {
          "id": 789,
          "availableBoxesCount": 10,
          "totalAvailableWeight": 95.0
        }
      ],
      "totalBoxes": 10,
      "totalNetWeight": 95.0
    }
  ],
  "totalBoxes": 15,
  "totalNetWeight": 142.5
}
```

### Nodo de Stock (`type: "stock"`)

| Campo | Tipo | Descripción | Ejemplo |
|-------|------|-------------|---------|
| `id` | string | ID del nodo (formato: `stock-{finalNodeId}`) | `"stock-2"` |
| `parentRecordId` | number | ID del nodo final padre | `2` |
| `stores` | array | Array de almacenes con sus productos | Ver estructura abajo |
| `totalBoxes` | number | Total de cajas en TODOS los almacenes | `40` |
| `totalNetWeight` | number | Peso total en TODOS los almacenes | `456.0` |
| `summary.storesCount` | number | Número de almacenes | `2` |
| `summary.productsCount` | number | Número de productos diferentes | `2` |

### Estructura de `stores[]`

Cada elemento del array `stores` tiene:

```json
{
  "store": {
    "id": 3,
    "name": "Almacén Central",
    "temperature": -18.0
  },
  "products": [
    {
      "product": {
        "id": 5,
        "name": "Filetes de Atún"
      },
      "pallets": [
        {
          "id": 456,
          "availableBoxesCount": 15,
          "totalAvailableWeight": 142.5,
          "position": "A-12"
        }
      ],
      "totalBoxes": 15,
      "totalNetWeight": 142.5
    }
  ],
  "totalBoxes": 35,
  "totalNetWeight": 408.5
}
```

---

## 🎯 Cómo Interpretar el Ejemplo

### Escenario del Ejemplo

1. **Producción**: Lote "LOT-2024-001"
2. **Nodo Final**: "Fileteado Final" (ID: 2)
   - Produce: Filetes de Atún (Producto 5) y Atún en Aceite (Producto 6)

3. **Venta**:
   - Pedido #00123: Recibe ambos productos (15 cajas total)
   - Pedido #00124: Recibe solo Filetes de Atún (8 cajas)

4. **Stock**:
   - Almacén Central: Tiene ambos productos (35 cajas total)
   - Almacén Norte: Tiene solo Filetes de Atún (5 cajas)

### Totalización

**Nodo de Venta (sales-2)**:
- Pedido #00123: 15 cajas (10 + 5)
- Pedido #00124: 8 cajas
- **Total**: 23 cajas, 218.5 kg

**Nodo de Stock (stock-2)**:
- Almacén Central: 35 cajas (27 + 8)
- Almacén Norte: 5 cajas
- **Total**: 40 cajas, 456.0 kg

---

## 📁 Archivo JSON Completo

El archivo JSON completo está disponible en:
```
docs/EJEMPLO-RESPUESTA-process-tree-v3.json
```

---

## ✅ Resumen para el Frontend

1. **Un nodo final** puede producir múltiples productos
2. **Un solo nodo de venta** (`sales-{finalNodeId}`) agrupa todos los productos del nodo final
3. **Un solo nodo de stock** (`stock-{finalNodeId}`) agrupa todos los productos del nodo final
4. Cada pedido/almacén contiene un array de productos
5. Cada producto tiene sus palets y totales

**Estructura de acceso**:
```
node.orders[0].products[0].product.name  // Nombre del producto
node.orders[0].products[0].pallets[0].id  // ID del palet
node.stores[0].products[0].product.name   // Nombre del producto
node.stores[0].products[0].pallets[0].position  // Posición en almacén
```

---

**Fin del Documento**

