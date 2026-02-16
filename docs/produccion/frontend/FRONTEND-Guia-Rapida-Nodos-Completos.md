# 🚀 Guía Rápida Frontend: Nodos Completos del Production Tree

**Endpoint**: `GET /v2/productions/{id}/process-tree`  
**Fecha**: 2025-01-27  
**Versión**: v4 (con nodos re-procesados y faltantes)

---

## 📊 Estructura Completa

Un nodo final puede tener hasta **4 tipos de nodos hijos**:

```
Nodo Final (ID: 2)
│
├── sales-2          → Productos en venta
├── stock-2          → Productos almacenados
├── reprocessed-2    → Productos re-procesados ✨ NUEVO
└── balance-2        → Balance de productos (faltantes y sobras) ✨ NUEVO
```

---

## 🎯 Nodos por Tipo

### 1. Sales Node (`type: "sales"`)

**Estructura**:
```
sales-{finalNodeId}
└── orders[]
    └── products[]  ← Array de productos en cada pedido
```

### 2. Stock Node (`type: "stock"`)

**Estructura**:
```
stock-{finalNodeId}
└── stores[]
    └── products[]  ← Array de productos en cada almacén
```

### 3. Reprocessed Node (`type: "reprocessed"`) ✨ NUEVO

**Estructura**:
```
reprocessed-{finalNodeId}
└── processes[]     ← Procesos donde se usaron los productos
    └── products[]  ← Array de productos usados en cada proceso
```

### 4. Balance Node (`type: "balance"`) ✨ NUEVO

**Estructura**:
```
balance-{finalNodeId}
└── products[]      ← Productos con cálculo completo
    ├── produced    ← Total producido
    ├── inSales     ← Total en venta
    ├── inStock     ← Total en stock
    ├── reprocessed ← Total re-procesado
    ├── balance     ← Diferencia (positivo = faltante, negativo = sobrante)
    └── boxes[]     ← Lista de cajas faltantes
```

---

## 📝 Ejemplo Completo Simplificado

### Nodo Final con 2 Productos

```json
{
  "id": 2,
  "isFinal": true,
  "outputs": [
    { "productId": 5, "product": { "name": "Filetes de Atún" } },
    { "productId": 6, "product": { "name": "Atún en Aceite" } }
  ],
  "children": [
    {
      "type": "sales",
      "id": "sales-2",
      "orders": [
        {
          "order": { "id": 123, "formattedId": "#00123" },
          "products": [
            { "product": { "id": 5, "name": "Filetes de Atún" }, "totalBoxes": 5 },
            { "product": { "id": 6, "name": "Atún en Aceite" }, "totalBoxes": 3 }
          ]
        }
      ]
    },
    {
      "type": "stock",
      "id": "stock-2",
      "stores": [
        {
          "store": { "id": 3, "name": "Almacén Central" },
          "products": [
            { "product": { "id": 5, "name": "Filetes de Atún" }, "totalBoxes": 1 }
          ]
        }
      ]
    },
    {
      "type": "reprocessed",
      "id": "reprocessed-2",
      "processes": [
        {
          "process": { "id": 8, "name": "Enlatado" },
          "products": [
            { "product": { "id": 5, "name": "Filetes de Atún" }, "totalBoxes": 2 }
          ]
        }
      ]
    },
    {
      "type": "balance",
      "id": "balance-2",
      "products": [
        {
          "product": { "id": 5, "name": "Filetes de Atún" },
          "produced": { "boxes": 10, "weight": 50.0 },
          "inSales": { "boxes": 5, "weight": 25.0 },
          "inStock": { "boxes": 1, "weight": 5.0 },
          "reprocessed": { "boxes": 2, "weight": 10.0 },
          "balance": { "boxes": 2, "weight": 10.0, "percentage": 20.0 },
          "boxes": [
            { "id": 5678, "netWeight": 5.0, "gs1_128": "1234567890123" },
            { "id": 5679, "netWeight": 5.0, "gs1_128": "1234567890124" }
          ]
        },
        {
          "product": { "id": 6, "name": "Atún en Aceite" },
          "produced": { "boxes": 5, "weight": 50.0 },
          "inSales": { "boxes": 3, "weight": 30.0 },
          "inStock": { "boxes": 0, "weight": 0.0 },
          "reprocessed": { "boxes": 0, "weight": 0.0 },
          "missing": { "boxes": 2, "weight": 20.0, "percentage": 40.0 },
          "boxes": [
            { "id": 5680, "netWeight": 10.0, "gs1_128": "9876543210987" },
            { "id": 5681, "netWeight": 10.0, "gs1_128": "9876543210988" }
          ]
        }
      ]
    }
  ]
}
```

---

## 🔑 Diferencias Clave

### Sales vs Stock vs Reprocessed vs Missing

| Aspecto | Sales | Stock | Reprocessed | Missing |
|---------|-------|-------|-------------|---------|
| **Agrupa por** | Pedidos | Almacenes | Procesos | Productos |
| **Estructura interna** | `orders[] → products[]` | `stores[] → products[]` | `processes[] → products[]` | `products[]` directo |
| **Información extra** | Cliente, fecha carga | Temperatura, posición | Proceso destino, fechas | Cálculo completo, cajas |

### Ejemplo Visual

**Sales Node**:
```
orders[] (agrupa por pedido)
  └── Pedido #00123
      └── products[] (todos los productos del nodo final en este pedido)
```

**Stock Node**:
```
stores[] (agrupa por almacén)
  └── Almacén Central
      └── products[] (todos los productos del nodo final en este almacén)
```

**Reprocessed Node**:
```
processes[] (agrupa por proceso)
  └── Proceso "Enlatado"
      └── products[] (todos los productos del nodo final usados en este proceso)
```

**Missing Node**:
```
products[] (directo, no agrupa)
  └── Producto 5
      ├── Balance completo (producido - venta - stock - re-procesado)
      └── boxes[] (lista de cajas faltantes)
```

---

## 💡 Tips para el Frontend

### 1. Identificar el Tipo de Nodo

```typescript
function getNodeType(node: ProcessTreeNode): string {
  if ('type' in node) {
    return node.type; // 'sales', 'stock', 'reprocessed', 'balance'
  }
  return 'process'; // Nodo de proceso normal
}
```

### 2. Renderizar según el Tipo

```typescript
function renderNode(node: ProcessTreeNode) {
  switch (node.type) {
    case 'sales':
      return renderSalesNode(node);
    case 'stock':
      return renderStockNode(node);
    case 'reprocessed':
      return renderReprocessedNode(node); // ✨ NUEVO
    case 'balance':
      return renderBalanceNode(node);     // ✨ NUEVO
    default:
      return renderProcessNode(node);
  }
}
```

### 3. Mostrar Balance en Balance Node

```typescript
function renderBalanceNode(node: BalanceNode) {
  return node.products.map(product => (
    <div>
      <h3>{product.product.name}</h3>
      <div>Producido: {product.produced.boxes} cajas</div>
      <div>En Venta: {product.inSales.boxes} cajas</div>
      <div>En Stock: {product.inStock.boxes} cajas</div>
      <div>Re-procesado: {product.reprocessed.boxes} cajas</div>
      <div className="alert">
        {product.balance.weight > 0 ? (
          <>⚠️ Faltante: {product.balance.boxes} cajas ({product.balance.percentage}%)</>
        ) : (
          <>❌ Sobrante: {Math.abs(product.balance.weight)}kg</>
        )}
      </div>
      {product.boxes.length > 0 && (
        <div>Cajas faltantes: {product.boxes.map(b => b.id).join(', ')}</div>
      )}
    </div>
  ));
}
```

---

## 📚 Archivos de Referencia

- **Ejemplo JSON completo**: [`../../ejemplos/EJEMPLO-RESPUESTA-process-tree-v4-completo.json`](../../ejemplos/EJEMPLO-RESPUESTA-process-tree-v4-completo.json)
- **Documentación detallada**: [`FRONTEND-Nodos-Re-procesados-y-Faltantes.md`](./FRONTEND-Nodos-Re-procesados-y-Faltantes.md)
- **Documentación venta/stock**: [`../cambios/FRONTEND-Cambios-Nodos-Venta-Stock-v3.md`](../cambios/FRONTEND-Cambios-Nodos-Venta-Stock-v3.md)

---

**Versión**: v4 - Nodos Completos

