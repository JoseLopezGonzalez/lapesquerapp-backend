# Frontend: Cambios en Nodos de Venta y Stock - Versión 2

## 📋 Resumen Ejecutivo

**Cambio importante**: La estructura de nodos de venta y stock ha sido simplificada. En lugar de crear múltiples nodos (uno por cada pedido/almacén), ahora se crea **UN SOLO nodo por producto** con un desglose interno.

**Fecha del Cambio**: 2025-01-27  
**Versión Anterior**: v1 (múltiples nodos)  
**Versión Actual**: v2 (nodo único con desglose)

---

## 🔄 Cambio Principal

### ❌ Versión Anterior (v1) - MULTIPLES NODOS

**Antes**: Se creaba un nodo de venta por cada combinación de `producto + pedido` y un nodo de stock por cada combinación de `producto + almacén`.

**Estructura anterior**:
```json
{
  "processNodes": [
    {
      "id": 2,
      "isFinal": true,
      "children": [
        {
          "type": "sales",
          "id": "sales-5-123",  // 👈 Un nodo por pedido
          "parentRecordId": 2,
          "product": {"id": 5, "name": "Filetes de Atún"},
          "order": {
            "id": 123,
            "formattedId": "#00123",
            "customer": {...}
          },
          "pallets": [...],
          "totalBoxes": 10,
          "totalNetWeight": 95.0
        },
        {
          "type": "sales",
          "id": "sales-5-124",  // 👈 Otro nodo para otro pedido
          "parentRecordId": 2,
          "product": {"id": 5, "name": "Filetes de Atún"},
          "order": {
            "id": 124,
            "formattedId": "#00124",
            "customer": {...}
          },
          "pallets": [...],
          "totalBoxes": 8,
          "totalNetWeight": 76.0
        },
        {
          "type": "stock",
          "id": "stock-5-3",  // 👈 Un nodo por almacén
          "parentRecordId": 2,
          "product": {"id": 5, "name": "Filetes de Atún"},
          "store": {
            "id": 3,
            "name": "Almacén Central",
            "temperature": -18.00
          },
          "pallets": [...],
          "totalBoxes": 15,
          "totalNetWeight": 142.5
        },
        {
          "type": "stock",
          "id": "stock-5-4",  // 👈 Otro nodo para otro almacén
          "parentRecordId": 2,
          "product": {"id": 5, "name": "Filetes de Atún"},
          "store": {
            "id": 4,
            "name": "Almacén Norte",
            "temperature": -20.00
          },
          "pallets": [...],
          "totalBoxes": 10,
          "totalNetWeight": 95.0
        }
      ]
    }
  ]
}
```

**Problemas de la versión anterior**:
- ❌ Múltiples nodos para el mismo producto
- ❌ Difícil de visualizar el total por producto
- ❌ Más nodos en el árbol (puede ser confuso)

---

### ✅ Versión Nueva (v2) - NODO ÚNICO CON DESGLOSE

**Ahora**: Se crea **UN SOLO nodo de venta por producto** (con desglose de todos los pedidos) y **UN SOLO nodo de stock por producto** (con desglose de todos los almacenes).

**Nueva estructura**:
```json
{
  "processNodes": [
    {
      "id": 2,
      "isFinal": true,
      "children": [
        {
          "type": "sales",
          "id": "sales-5",  // 👈 UN SOLO nodo para el producto 5
          "parentRecordId": 2,
          "productionId": 1,
          "product": {
            "id": 5,
            "name": "Filetes de Atún"
          },
          "orders": [  // 👈 Array con desglose de pedidos
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
              "pallets": [
                {
                  "id": 789,
                  "availableBoxesCount": 10,
                  "totalAvailableWeight": 95.0
                }
              ],
              "totalBoxes": 10,
              "totalNetWeight": 95.0,
              "summary": {
                "palletsCount": 1,
                "boxesCount": 10,
                "netWeight": 95.0
              }
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
              "pallets": [...],
              "totalBoxes": 8,
              "totalNetWeight": 76.0,
              "summary": {...}
            }
          ],
          "totalBoxes": 18,  // 👈 Total de TODOS los pedidos
          "totalNetWeight": 171.0,
          "summary": {
            "ordersCount": 2,  // 👈 Número de pedidos
            "palletsCount": 2,
            "boxesCount": 18,
            "netWeight": 171.0
          },
          "children": []
        },
        {
          "type": "stock",
          "id": "stock-5",  // 👈 UN SOLO nodo para el producto 5
          "parentRecordId": 2,
          "productionId": 1,
          "product": {
            "id": 5,
            "name": "Filetes de Atún"
          },
          "stores": [  // 👈 Array con desglose de almacenes
            {
              "store": {
                "id": 3,
                "name": "Almacén Central",
                "temperature": -18.00
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
              "totalNetWeight": 142.5,
              "summary": {
                "palletsCount": 1,
                "boxesCount": 15,
                "netWeight": 142.5
              }
            },
            {
              "store": {
                "id": 4,
                "name": "Almacén Norte",
                "temperature": -20.00
              },
              "pallets": [...],
              "totalBoxes": 10,
              "totalNetWeight": 95.0,
              "summary": {...}
            }
          ],
          "totalBoxes": 25,  // 👈 Total de TODOS los almacenes
          "totalNetWeight": 237.5,
          "summary": {
            "storesCount": 2,  // 👈 Número de almacenes
            "palletsCount": 2,
            "boxesCount": 25,
            "netWeight": 237.5
          },
          "children": []
        }
      ]
    }
  ]
}
```

**Ventajas de la nueva versión**:
- ✅ Un solo nodo por producto (más limpio)
- ✅ Totales agregados por producto fáciles de ver
- ✅ Desglose interno cuando se necesita detalle
- ✅ Menos nodos en el árbol

---

## 📊 Comparación Detallada

### Estructura del Nodo de Venta

| Aspecto | Versión Anterior (v1) | Versión Nueva (v2) |
|---------|----------------------|-------------------|
| **Cantidad de nodos** | 1 nodo por pedido | 1 nodo por producto |
| **ID del nodo** | `sales-{productId}-{orderId}` | `sales-{productId}` |
| **Información del pedido** | Campo `order` (objeto único) | Array `orders` (múltiples) |
| **Palets** | Campo `pallets` (del pedido) | Dentro de cada elemento de `orders` |
| **Totales** | Solo del pedido específico | Totales agregados de todos los pedidos |

### Estructura del Nodo de Stock

| Aspecto | Versión Anterior (v1) | Versión Nueva (v2) |
|---------|----------------------|-------------------|
| **Cantidad de nodos** | 1 nodo por almacén | 1 nodo por producto |
| **ID del nodo** | `stock-{productId}-{storeId}` | `stock-{productId}` |
| **Información del almacén** | Campo `store` (objeto único) | Array `stores` (múltiples) |
| **Palets** | Campo `pallets` (del almacén) | Dentro de cada elemento de `stores` |
| **Totales** | Solo del almacén específico | Totales agregados de todos los almacenes |

---

## 🔧 Cambios Necesarios en el Frontend

### 1. Actualizar Tipos/Interfaces TypeScript

#### ❌ Versión Anterior (v1)

```typescript
interface SalesNode {
    type: 'sales';
    id: string;  // "sales-5-123"
    parentRecordId: number | null;
    productionId: number;
    product: {
        id: number;
        name: string;
    };
    order: {  // 👈 Un solo objeto
        id: number;
        formattedId: string;
        customer: {
            id: number;
            name: string;
        } | null;
        loadDate: string | null;
        status: string;
    };
    pallets: Array<{
        id: number;
        availableBoxesCount: number;
        totalAvailableWeight: number;
    }>;
    totalBoxes: number;
    totalNetWeight: number;
    summary: {
        palletsCount: number;
        boxesCount: number;
        netWeight: number;
    };
    children: [];
}

interface StockNode {
    type: 'stock';
    id: string;  // "stock-5-3"
    parentRecordId: number | null;
    productionId: number;
    product: {
        id: number;
        name: string;
    };
    store: {  // 👈 Un solo objeto
        id: number;
        name: string;
        temperature: number;
    };
    pallets: Array<{
        id: number;
        availableBoxesCount: number;
        totalAvailableWeight: number;
        position: string | null;
    }>;
    totalBoxes: number;
    totalNetWeight: number;
    summary: {
        palletsCount: number;
        boxesCount: number;
        netWeight: number;
    };
    children: [];
}
```

#### ✅ Versión Nueva (v2)

```typescript
interface SalesNode {
    type: 'sales';
    id: string;  // "sales-5" (sin orderId)
    parentRecordId: number | null;
    productionId: number;
    product: {
        id: number;
        name: string;
    };
    orders: Array<{  // 👈 Array de pedidos
        order: {
            id: number;
            formattedId: string;
            customer: {
                id: number;
                name: string;
            } | null;
            loadDate: string | null;
            status: string;
        };
        pallets: Array<{
            id: number;
            availableBoxesCount: number;
            totalAvailableWeight: number;
        }>;
        totalBoxes: number;
        totalNetWeight: number;
        summary: {
            palletsCount: number;
            boxesCount: number;
            netWeight: number;
        };
    }>;
    totalBoxes: number;  // Total de TODOS los pedidos
    totalNetWeight: number;
    summary: {
        ordersCount: number;  // 👈 Nuevo campo
        palletsCount: number;
        boxesCount: number;
        netWeight: number;
    };
    children: [];
}

interface StockNode {
    type: 'stock';
    id: string;  // "stock-5" (sin storeId)
    parentRecordId: number | null;
    productionId: number;
    product: {
        id: number;
        name: string;
    };
    stores: Array<{  // 👈 Array de almacenes
        store: {
            id: number;
            name: string;
            temperature: number;
        };
        pallets: Array<{
            id: number;
            availableBoxesCount: number;
            totalAvailableWeight: number;
            position: string | null;
        }>;
        totalBoxes: number;
        totalNetWeight: number;
        summary: {
            palletsCount: number;
            boxesCount: number;
            netWeight: number;
        };
    }>;
    totalBoxes: number;  // Total de TODOS los almacenes
    totalNetWeight: number;
    summary: {
        storesCount: number;  // 👈 Nuevo campo
        palletsCount: number;
        boxesCount: number;
        netWeight: number;
    };
    children: [];
}
```

---

### 2. Actualizar Componentes de Renderizado

#### ❌ Versión Anterior (v1) - Componente de Venta

```typescript
function SalesNodeComponent({ node }: { node: SalesNode }) {
    return (
        <div className="sales-node">
            <div className="node-header">
                <span>🛒 VENTA: {node.product.name}</span>
            </div>
            
            <div className="node-content">
                {/* Un solo pedido */}
                <div className="order-info">
                    <strong>Pedido:</strong> {node.order.formattedId}
                </div>
                {node.order.customer && (
                    <div className="customer-info">
                        <strong>Cliente:</strong> {node.order.customer.name}
                    </div>
                )}
                
                <div className="metrics">
                    <div>Cajas: {node.totalBoxes}</div>
                    <div>Peso: {node.totalNetWeight} kg</div>
                    <div>Palets: {node.summary.palletsCount}</div>
                </div>
                
                {/* Lista de palets */}
                <ul>
                    {node.pallets.map(pallet => (
                        <li key={pallet.id}>
                            Palet #{pallet.id}: {pallet.availableBoxesCount} cajas
                        </li>
                    ))}
                </ul>
            </div>
        </div>
    );
}
```

#### ✅ Versión Nueva (v2) - Componente de Venta

```typescript
function SalesNodeComponent({ node }: { node: SalesNode }) {
    return (
        <div className="sales-node">
            <div className="node-header">
                <span>🛒 VENTA: {node.product.name}</span>
            </div>
            
            <div className="node-content">
                {/* Totales agregados */}
                <div className="total-metrics">
                    <div><strong>Total Cajas:</strong> {node.totalBoxes}</div>
                    <div><strong>Total Peso:</strong> {node.totalNetWeight} kg</div>
                    <div><strong>Total Palets:</strong> {node.summary.palletsCount}</div>
                    <div><strong>Número de Pedidos:</strong> {node.summary.ordersCount}</div>
                </div>
                
                {/* Desglose por pedido */}
                <div className="orders-breakdown">
                    <h4>Desglose por Pedido:</h4>
                    {node.orders.map((orderData, index) => (
                        <details key={index} className="order-details">
                            <summary>
                                Pedido {orderData.order.formattedId}
                                {orderData.order.customer && (
                                    <> - {orderData.order.customer.name}</>
                                )}
                                <span className="order-summary">
                                    ({orderData.totalBoxes} cajas, {orderData.totalNetWeight} kg)
                                </span>
                            </summary>
                            
                            <div className="order-info">
                                <div>
                                    <strong>Cliente:</strong> {
                                        orderData.order.customer?.name || 'Sin cliente'
                                    }
                                </div>
                                {orderData.order.loadDate && (
                                    <div>
                                        <strong>Fecha de Carga:</strong> {
                                            new Date(orderData.order.loadDate).toLocaleDateString()
                                        }
                                    </div>
                                )}
                                <div>
                                    <strong>Estado:</strong> {orderData.order.status}
                                </div>
                            </div>
                            
                            {/* Palets de este pedido */}
                            <div className="pallets-list">
                                <strong>Palets ({orderData.summary.palletsCount}):</strong>
                                <ul>
                                    {orderData.pallets.map(pallet => (
                                        <li key={pallet.id}>
                                            Palet #{pallet.id}: {pallet.availableBoxesCount} cajas, 
                                            {pallet.totalAvailableWeight} kg
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        </details>
                    ))}
                </div>
            </div>
        </div>
    );
}
```

#### ✅ Versión Nueva (v2) - Componente de Stock

```typescript
function StockNodeComponent({ node }: { node: StockNode }) {
    return (
        <div className="stock-node">
            <div className="node-header">
                <span>📦 STOCK: {node.product.name}</span>
            </div>
            
            <div className="node-content">
                {/* Totales agregados */}
                <div className="total-metrics">
                    <div><strong>Total Cajas:</strong> {node.totalBoxes}</div>
                    <div><strong>Total Peso:</strong> {node.totalNetWeight} kg</div>
                    <div><strong>Total Palets:</strong> {node.summary.palletsCount}</div>
                    <div><strong>Número de Almacenes:</strong> {node.summary.storesCount}</div>
                </div>
                
                {/* Desglose por almacén */}
                <div className="stores-breakdown">
                    <h4>Desglose por Almacén:</h4>
                    {node.stores.map((storeData, index) => (
                        <details key={index} className="store-details">
                            <summary>
                                {storeData.store.name}
                                <span className="store-summary">
                                    ({storeData.totalBoxes} cajas, {storeData.totalNetWeight} kg)
                                </span>
                            </summary>
                            
                            <div className="store-info">
                                <div>
                                    <strong>Temperatura:</strong> {storeData.store.temperature}°C
                                </div>
                            </div>
                            
                            {/* Palets de este almacén */}
                            <div className="pallets-list">
                                <strong>Palets ({storeData.summary.palletsCount}):</strong>
                                <ul>
                                    {storeData.pallets.map(pallet => (
                                        <li key={pallet.id}>
                                            Palet #{pallet.id}: {pallet.availableBoxesCount} cajas, 
                                            {pallet.totalAvailableWeight} kg
                                            {pallet.position && (
                                                <> - Posición: {pallet.position}</>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        </details>
                    ))}
                </div>
            </div>
        </div>
    );
}
```

---

### 3. Actualizar Lógica de Identificación de Nodos

#### ❌ Versión Anterior (v1)

```typescript
// Los IDs incluían el pedido/almacén
if (node.id.startsWith('sales-')) {
    const [, productId, orderId] = node.id.split('-');
    // productId = "5", orderId = "123"
}

if (node.id.startsWith('stock-')) {
    const [, productId, storeId] = node.id.split('-');
    // productId = "5", storeId = "3"
}
```

#### ✅ Versión Nueva (v2)

```typescript
// Los IDs solo incluyen el producto
if (node.id.startsWith('sales-')) {
    const [, productId] = node.id.split('-');
    // productId = "5"
    // Para obtener pedidos, usar node.orders
}

if (node.id.startsWith('stock-')) {
    const [, productId] = node.id.split('-');
    // productId = "5"
    // Para obtener almacenes, usar node.stores
}
```

---

### 4. Actualizar Funciones de Búsqueda/Filtrado

Si tienes funciones que buscan nodos por pedido o almacén, necesitarás actualizarlas:

#### ❌ Versión Anterior (v1)

```typescript
// Buscar nodo de venta por pedido
function findSalesNodeByOrder(orderId: number, nodes: TreeNode[]): SalesNode | null {
    for (const node of nodes) {
        if (node.type === 'sales' && node.id === `sales-${productId}-${orderId}`) {
            return node as SalesNode;
        }
        if (node.children) {
            const found = findSalesNodeByOrder(orderId, node.children);
            if (found) return found;
        }
    }
    return null;
}
```

#### ✅ Versión Nueva (v2)

```typescript
// Buscar nodo de venta por producto y luego buscar el pedido dentro
function findSalesNodeByOrder(orderId: number, productId: number, nodes: TreeNode[]): SalesNode | null {
    for (const node of nodes) {
        if (node.type === 'sales' && node.id === `sales-${productId}`) {
            const salesNode = node as SalesNode;
            // Buscar el pedido dentro del array orders
            const orderData = salesNode.orders.find(o => o.order.id === orderId);
            if (orderData) {
                return salesNode;
            }
        }
        if (node.children) {
            const found = findSalesNodeByOrder(orderId, productId, node.children);
            if (found) return found;
        }
    }
    return null;
}
```

---

## 📋 Checklist de Migración

### Fase 1: Actualizar Tipos
- [ ] Actualizar `SalesNode` interface para usar `orders: Array<...>`
- [ ] Actualizar `StockNode` interface para usar `stores: Array<...>`
- [ ] Eliminar campos `order` y `store` directos
- [ ] Añadir campos `ordersCount` y `storesCount` en summary

### Fase 2: Actualizar Componentes
- [ ] Modificar `SalesNodeComponent` para iterar sobre `orders`
- [ ] Modificar `StockNodeComponent` para iterar sobre `stores`
- [ ] Añadir visualización de totales agregados
- [ ] Implementar desglose colapsable/expandible

### Fase 3: Actualizar Lógica
- [ ] Actualizar funciones de búsqueda/filtrado
- [ ] Actualizar funciones que acceden a `node.order` o `node.store`
- [ ] Actualizar funciones que usan el ID del nodo (ahora sin orderId/storeId)

### Fase 4: Testing
- [ ] Probar con producción que tiene múltiples pedidos
- [ ] Probar con producción que tiene múltiples almacenes
- [ ] Verificar que los totales sean correctos
- [ ] Verificar que el desglose muestre todos los pedidos/almacenes

---

## 🎯 Resumen de Cambios Clave

### Campos que Cambiaron

| Campo Antiguo (v1) | Campo Nuevo (v2) | Cambio |
|-------------------|------------------|--------|
| `order` (objeto) | `orders` (array) | Ahora es un array con múltiples pedidos |
| `store` (objeto) | `stores` (array) | Ahora es un array con múltiples almacenes |
| `id: "sales-5-123"` | `id: "sales-5"` | ID ya no incluye orderId |
| `id: "stock-5-3"` | `id: "stock-5"` | ID ya no incluye storeId |
| - | `summary.ordersCount` | Nuevo campo |
| - | `summary.storesCount` | Nuevo campo |

### Comportamiento que Cambió

1. **Antes**: Si había 3 pedidos para el producto 5, se creaban 3 nodos de venta
   **Ahora**: Se crea 1 nodo de venta con 3 elementos en `orders`

2. **Antes**: Si había 2 almacenes para el producto 5, se creaban 2 nodos de stock
   **Ahora**: Se crea 1 nodo de stock con 2 elementos en `stores`

3. **Antes**: Los totales eran por pedido/almacén
   **Ahora**: Los totales son agregados de todos los pedidos/almacenes

---

## ⚠️ Consideraciones Importantes

### 1. Compatibilidad hacia atrás
**NO hay compatibilidad hacia atrás**. La estructura cambió completamente. Si tienes código que usa la versión anterior, deberás actualizarlo.

### 2. Múltiples nodos de venta/stock
Ahora puede haber múltiples nodos de venta/stock solo si son para **productos diferentes**:
- `sales-5` (producto 5)
- `sales-6` (producto 6)

Pero NO habrá múltiples nodos para el mismo producto.

### 3. Nodos huérfanos
Los nodos huérfanos (sin padre) también siguen la nueva estructura:
- `parentRecordId: null`
- Pero tienen `orders` o `stores` con el desglose completo

---

## 📚 Ejemplos Completos

### Ejemplo 1: Producto con 2 Pedidos y 1 Almacén

**Estructura**:
```json
{
  "id": 2,
  "isFinal": true,
  "children": [
    {
      "type": "sales",
      "id": "sales-5",
      "orders": [
        {"order": {...}, "totalBoxes": 10, ...},
        {"order": {...}, "totalBoxes": 8, ...}
      ],
      "totalBoxes": 18,
      "summary": {"ordersCount": 2, ...}
    },
    {
      "type": "stock",
      "id": "stock-5",
      "stores": [
        {"store": {...}, "totalBoxes": 15, ...}
      ],
      "totalBoxes": 15,
      "summary": {"storesCount": 1, ...}
    }
  ]
}
```

### Ejemplo 2: Producto Huérfano (sin nodo final)

**Estructura**:
```json
{
  "processNodes": [
    // ... otros nodos de proceso ...
    {
      "type": "sales",
      "id": "sales-6",
      "parentRecordId": null,  // 👈 Huérfano
      "orders": [...],
      "totalBoxes": 25,
      "children": []
    }
  ]
}
```

---

## ✅ Conclusión

La nueva estructura simplifica significativamente el árbol de nodos:
- ✅ Menos nodos en el árbol
- ✅ Totales más claros por producto
- ✅ Desglose disponible cuando se necesita
- ✅ Mejor organización de la información

**Siguiente paso**: Actualizar los componentes del frontend siguiendo el checklist de migración.

---

**Fin del Documento**

