# Frontend: Nodos de Re-procesados y Faltantes

## 📋 Resumen Ejecutivo

Se han añadido **DOS nuevos tipos de nodos** al árbol de procesos que cuelgan de los nodos finales, completando la trazabilidad del 100% de los productos producidos:

1. **Nodo de Re-procesados** (`type: "reprocessed"`): Cajas usadas como materia prima en otro proceso
2. **Nodo de Balance** (`type: "balance"`): Balance completo (faltantes y sobras) de productos producidos

**Fecha**: 2025-01-27  
**Endpoint**: `GET /v2/productions/{id}/process-tree`

---

## 🎯 Objetivo

Completar la trazabilidad completa de los productos producidos en un nodo final:

```
Nodo Final
├── sales (productos en venta)
├── stock (productos almacenados)
├── reprocessed (productos re-procesados) ✨ NUEVO
└── balance (balance de productos: faltantes y sobras) ✨ NUEVO
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

## 📦 Nodo de Re-procesados

### Descripción

Representa productos del lote que fueron **usados como materia prima** en otro proceso de producción. Estas cajas tienen un destino claro: fueron transformadas en otro proceso.

### Identificación

- **Tipo**: `"reprocessed"`
- **ID**: `"reprocessed-{finalNodeId}"` (donde `finalNodeId` es el ID del nodo final padre)
- **Parent**: Siempre cuelga de un nodo final (o puede ser huérfano si hay ambigüedad)

### Estructura del Nodo

```json
{
  "type": "reprocessed",
  "id": "reprocessed-2",
  "parentRecordId": 2,  // ID del nodo final
  "productionId": 1,
  "processes": [
    {
      "process": {
        "id": 8,
        "name": "Enlatado",
        "type": "processing"
      },
      "productionRecord": {
        "id": 15,
        "productionId": 2,
        "startedAt": "2024-02-10T08:00:00Z",
        "finishedAt": "2024-02-10T12:00:00Z"
      },
      "production": {
        "id": 2,
        "lot": "LOT-2024-002"
      },
      "products": [
        {
          "product": {
            "id": 5,
            "name": "Filetes de Atún"
          },
          "boxes": [
            {
              "id": 1234,
              "netWeight": 5.0,
              "gs1_128": "1234567890123"
            },
            {
              "id": 1235,
              "netWeight": 5.0,
              "gs1_128": "1234567890124"
            }
          ],
          "totalBoxes": 2,
          "totalNetWeight": 10.0
        },
        {
          "product": {
            "id": 6,
            "name": "Atún en Aceite"
          },
          "boxes": [
            {
              "id": 1236,
              "netWeight": 10.0,
              "gs1_128": "1234567890125"
            }
          ],
          "totalBoxes": 1,
          "totalNetWeight": 10.0
        }
      ],
      "totalBoxes": 3,  // Total del proceso (suma de todos los productos)
      "totalNetWeight": 20.0
    },
    {
      "process": {
        "id": 9,
        "name": "Conservación",
        "type": "processing"
      },
      "productionRecord": {
        "id": 16,
        "productionId": 3,
        "startedAt": "2024-02-12T09:00:00Z",
        "finishedAt": null
      },
      "products": [
        {
          "product": {
            "id": 5,
            "name": "Filetes de Atún"
          },
          "boxes": [
            {
              "id": 1237,
              "netWeight": 5.0,
              "gs1_128": "1234567890126"
            }
          ],
          "totalBoxes": 1,
          "totalNetWeight": 5.0
        }
      ],
      "totalBoxes": 1,
      "totalNetWeight": 5.0
    }
  ],
  "totalBoxes": 4,  // Total de TODOS los procesos
  "totalNetWeight": 25.0,
  "summary": {
    "processesCount": 2,  // Número de procesos diferentes donde se usaron
    "productsCount": 2,  // Número de productos diferentes del nodo final
    "boxesCount": 4,
    "netWeight": 25.0
  },
  "children": []
}
```

### Campos Importantes

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `type` | string | Siempre `"reprocessed"` |
| `id` | string | `"reprocessed-{finalNodeId}"` |
| `parentRecordId` | number | ID del nodo final padre |
| `processes` | array | Array de procesos donde se usaron los productos |
| `processes[].process` | object | Información del proceso (id, name, type) |
| `processes[].productionRecord` | object | Información del registro de producción donde se usó |
| `processes[].production` | object | Información de la producción destino (id, lot) ✨ NUEVO |
| `processes[].products` | array | **Array de productos** del nodo final usados en este proceso |
| `processes[].products[].boxes` | array | Lista de cajas individuales usadas |
| `summary.processesCount` | number | Número de procesos diferentes |
| `summary.productsCount` | number | Número de productos diferentes del nodo final |

---

## ⚠️ Nodo de Faltantes

### Descripción

Representa productos del lote que **realmente faltan** o no están contabilizados. Estas cajas están disponibles pero:
- NO están en venta (sin pedido)
- NO están en stock (sin almacén)
- NO fueron consumidas (no se usaron en otro proceso)

### Identificación

- **Tipo**: `"balance"`
- **ID**: `"balance-{finalNodeId}"` (donde `finalNodeId` es el ID del nodo final padre)
- **Parent**: Siempre cuelga de un nodo final (o puede ser huérfano si hay ambigüedad)

### Estructura del Nodo

```json
{
  "type": "balance",
  "id": "balance-2",
  "parentRecordId": 2,  // ID del nodo final
  "productionId": 1,
  "products": [
    {
      "product": {
        "id": 5,
        "name": "Filetes de Atún"
      },
      "produced": {
        "boxes": 10,
        "weight": 50.0
      },
      "inSales": {
        "boxes": 8,
        "weight": 40.0
      },
      "inStock": {
        "boxes": 1,
        "weight": 5.0
      },
      "reprocessed": {
        "boxes": 2,
        "weight": 10.0
      },
      "balance": {
        "boxes": 0,
        "weight": 0.0,
        "percentage": 0.0
      },
      "boxes": []  // No hay cajas faltantes
    },
    {
      "product": {
        "id": 6,
        "name": "Atún en Aceite"
      },
      "produced": {
        "boxes": 5,
        "weight": 50.0
      },
      "inSales": {
        "boxes": 3,
        "weight": 30.0
      },
      "inStock": {
        "boxes": 0,
        "weight": 0.0
      },
      "reprocessed": {
        "boxes": 0,
        "weight": 0.0
      },
      "balance": {
        "boxes": 2,
        "weight": 20.0,
        "percentage": 40.0  // 40% del producto producido falta
      },
      "boxes": [
        {
          "id": 5678,
          "netWeight": 10.0,
          "gs1_128": "9876543210987",
          "location": null  // Sin ubicación asignada
        },
        {
          "id": 5679,
          "netWeight": 10.0,
          "gs1_128": "9876543210988",
          "location": null
        }
      ]
    }
  ],
  "summary": {
    "productsCount": 2,
    "totalBalanceBoxes": 2,
    "totalBalanceWeight": 20.0
  },
  "children": []
}
```

### Campos Importantes

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `type` | string | Siempre `"balance"` |
| `id` | string | `"balance-{finalNodeId}"` |
| `parentRecordId` | number | ID del nodo final padre |
| `products` | array | Array de productos con su balance |
| `products[].produced` | object | Total producido (boxes, weight) |
| `products[].inSales` | object | Total en venta (boxes, weight) |
| `products[].inStock` | object | Total en stock (boxes, weight) |
| `products[].reprocessed` | object | Total re-procesado (boxes, weight) |
| `products[].balance` | object | **Balance calculado** (boxes, weight, percentage). Positivo = faltante, Negativo = sobrante |
| `products[].boxes` | array | Lista de cajas individuales que faltan |
| `summary.totalBalanceBoxes` | number | Total de cajas (balance) |
| `summary.totalBalanceWeight` | number | Peso total (balance, puede ser negativo) |

### Cálculo de Balance

Para cada producto:
```
Balance = Producido - En Venta - En Stock - Re-procesado

Ejemplo 1 (Faltante):
  Producido: 50kg (5 cajas)
  - En Venta: 30kg (3 cajas)
  - En Stock: 0kg (0 cajas)
  - Re-procesado: 0kg (0 cajas)
  = Balance: 20kg (2 cajas) = 40% faltante

Ejemplo 2 (Sobrante):
  Producido: 50kg (5 cajas)
  - En Venta: 60kg (6 cajas)
  - En Stock: 10kg (1 caja)
  - Re-procesado: 0kg (0 cajas)
  = Balance: -20kg (sobrante, posible error de datos)
```

---

## 🔗 Estructura Completa del Árbol

### Visualización

```
Nodo Final "Fileteado" (ID: 2)
├── Produce: Producto 5 (10 cajas), Producto 6 (5 cajas)
│
├── sales-2 (productos en venta)
│   └── orders[]
│       └── products[] (Producto 5, Producto 6)
│
├── stock-2 (productos almacenados)
│   └── stores[]
│       └── products[] (Producto 5)
│
├── reprocessed-2 (productos re-procesados) ✨ NUEVO
│   └── processes[]
│       └── products[] (Producto 5, Producto 6)
│
└── balance-2 (balance de productos: faltantes y sobras) ✨ NUEVO
    └── products[]
        └── Cálculo completo + cajas individuales
```

### Orden de los Nodos

Los nodos hijos del nodo final aparecen en este orden (si existen):

1. `sales` - Productos en venta
2. `stock` - Productos almacenados
3. `reprocessed` - Productos re-procesados ✨
4. `missing` - Productos faltantes ✨

---

## 📊 Tipos TypeScript

### Interface ReprocessedNode

```typescript
interface ReprocessedNode {
    type: 'reprocessed';
    id: string;  // "reprocessed-{finalNodeId}"
    parentRecordId: number;  // ID del nodo final
    productionId: number;
    processes: Array<{
        process: {
            id: number;
            name: string;
            type: string;
        };
        productionRecord: {
            id: number;
            productionId: number;
            startedAt: string | null;
            finishedAt: string | null;
        };
        products: Array<{
            product: {
                id: number;
                name: string;
            };
            boxes: Array<{
                id: number;
                netWeight: number;
                gs1_128: string | null;
            }>;
            totalBoxes: number;
            totalNetWeight: number;
        }>;
        totalBoxes: number;
        totalNetWeight: number;
    }>;
    totalBoxes: number;
    totalNetWeight: number;
    summary: {
        processesCount: number;
        productsCount: number;
        boxesCount: number;
        netWeight: number;
    };
    children: [];
}
```

### Interface BalanceNode

```typescript
interface BalanceNode {
    type: 'balance';
    id: string;  // "balance-{finalNodeId}"
    parentRecordId: number;  // ID del nodo final
    productionId: number;
    products: Array<{
        product: {
            id: number;
            name: string;
        };
        produced: {
            boxes: number;
            weight: number;
        };
        inSales: {
            boxes: number;
            weight: number;
        };
        inStock: {
            boxes: number;
            weight: number;
        };
        reprocessed: {
            boxes: number;
            weight: number;
        };
        balance: {
            boxes: number;
            weight: number;  // Positivo = faltante, Negativo = sobrante
            percentage: number;  // Porcentaje del total producido (solo si es positivo)
        };
        boxes: Array<{
            id: number;
            netWeight: number;
            gs1_128: string | null;
            location: null;  // Siempre null (sin ubicación)
        }>;
    }>;
    summary: {
        productsCount: number;
        totalBalanceBoxes: number;
        totalBalanceWeight: number;  // Puede ser negativo (sobrante)
    };
    children: [];
}
```

### Union Type para Todos los Nodos

```typescript
type ProcessTreeNode = 
    | ProcessNode  // Nodo de proceso normal
    | SalesNode    // Nodo de venta
    | StockNode    // Nodo de stock
    | ReprocessedNode  // ✨ NUEVO
    | BalanceNode;     // ✨ NUEVO (antes MissingNode)
```

---

## 🎯 Casos de Uso

### Caso 1: Todo Contabilizado

```json
{
  "product": {
    "id": 5,
    "name": "Filetes de Atún"
  },
  "produced": { "boxes": 10, "weight": 50.0 },
  "inSales": { "boxes": 6, "weight": 30.0 },
  "inStock": { "boxes": 4, "weight": 20.0 },
  "reprocessed": { "boxes": 0, "weight": 0.0 },
  "balance": { "boxes": 0, "weight": 0.0, "percentage": 0.0 }
}
```

**Resultado**: No se mostraría el nodo de balance (no hay desbalance).

### Caso 2: Producto Re-procesado

```json
{
  "type": "reprocessed",
  "processes": [
    {
      "process": { "name": "Enlatado" },
      "products": [
        {
          "product": { "name": "Filetes de Atún" },
          "totalBoxes": 5,
          "totalNetWeight": 25.0
        }
      ]
    }
  ]
}
```

**Información útil**: Ver en qué proceso se reutilizaron los productos.

### Caso 3: Productos con Balance (Faltantes o Sobras)

```json
{
  "type": "balance",
  "products": [
    {
      "product": { "name": "Atún en Aceite" },
      "produced": { "boxes": 5, "weight": 50.0 },
      "inSales": { "boxes": 3, "weight": 30.0 },
      "inStock": { "boxes": 0, "weight": 0.0 },
      "reprocessed": { "boxes": 0, "weight": 0.0 },
      "balance": { "boxes": 2, "weight": 20.0, "percentage": 40.0 },
      "boxes": [
        { "id": 5678, "netWeight": 10.0, "gs1_128": "..." },
        { "id": 5679, "netWeight": 10.0, "gs1_128": "..." }
      ]
    }
  ]
}
```

**Información útil**: Identificar productos perdidos o no registrados.

---

## 📋 Cambios desde Versión Anterior

### Nuevos Nodos

| Tipo | ID | Descripción |
|------|-----|-------------|
| `reprocessed` | `reprocessed-{finalNodeId}` | Productos re-procesados |
| `balance` | `balance-{finalNodeId}` | Balance de productos (faltantes y sobras) |

### Estructura Mantenida

- `sales` - Sin cambios
- `stock` - Sin cambios
- Ambos siguen agrupando por nodo final (no por producto)

---

## 🔍 Ejemplo Completo

Ver archivo: [`../../ejemplos/EJEMPLO-RESPUESTA-process-tree-v4-completo.json`](../../32-ejemplos/EJEMPLO-RESPUESTA-process-tree-v4-completo.json)

Este archivo contiene un ejemplo completo con:
- Nodo final con 2 productos
- Nodo de venta con 2 pedidos
- Nodo de stock con 1 almacén
- Nodo de re-procesados con 1 proceso
- Nodo de balance con cálculo completo (faltantes y sobras)

---

## ⚠️ Notas Importantes

1. **Nodos opcionales**: Cada nodo solo aparece si tiene datos
   - Si no hay productos re-procesados → No aparece nodo `reprocessed`
   - Si no hay productos con desbalance → No aparece nodo `balance`

2. **Agrupación por nodo final**: 
   - **UN SOLO nodo** de cada tipo por nodo final
   - Agrupa **TODOS los productos** del nodo final

3. **Cálculo de faltantes**:
   - Se calcula automáticamente
   - Puede ser 0 si todo está contabilizado
   - El porcentaje indica qué % del total producido falta

4. **Nodos huérfanos**:
   - Si un producto no tiene nodo final o tiene ambigüedad
   - Los nodos se crean con ID `"reprocessed-orphan-{productId}"` o `"balance-orphan-{productId}"`
   - `parentRecordId: null`

---

## ✅ Checklist de Implementación Frontend

- [ ] Actualizar tipos TypeScript para incluir `ReprocessedNode` y `BalanceNode`
- [ ] Actualizar renderizado del árbol para mostrar los nuevos nodos
- [ ] Implementar visualización del nodo de re-procesados
- [ ] Implementar visualización del nodo de faltantes
- [ ] Mostrar cálculo completo en nodo de faltantes (producido - venta - stock - re-procesado)
- [ ] Manejar casos donde no hay datos (no mostrar nodo)
- [ ] Mostrar porcentaje de faltantes cuando sea relevante
- [ ] Visualizar procesos destino en nodo de re-procesados
- [ ] Visualizar lista de cajas faltantes

---

**Documento creado**: 2025-01-27  
**Versión**: v4 (con nodos re-procesados y faltantes)

