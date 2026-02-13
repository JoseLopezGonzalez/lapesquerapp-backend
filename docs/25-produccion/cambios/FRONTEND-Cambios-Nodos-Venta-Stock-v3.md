# Frontend: Cambios Finales en Nodos de Venta y Stock - Versión 3

## 📋 Resumen Ejecutivo

**Cambio crítico**: Los nodos de venta y stock ahora se agrupan **por nodo final**, no por producto. Cada nodo final tiene **UN SOLO nodo de venta** y **UN SOLO nodo de stock** que agrupan **TODOS los productos** que produce ese nodo final.

**Fecha del Cambio**: 2025-01-27  
**Versión Anterior**: v2 (un nodo por producto)  
**Versión Actual**: v3 (un nodo por nodo final con todos los productos)

---

## 🔄 Cambio Principal

### ❌ Versión Anterior (v2) - UN NODO POR PRODUCTO

**Antes**: Se creaba un nodo de venta y un nodo de stock por cada producto.

Si un nodo final producía 3 productos diferentes, se creaban 6 nodos:
- `sales-5` (producto 5)
- `sales-6` (producto 6)  
- `sales-7` (producto 7)
- `stock-5` (producto 5)
- `stock-6` (producto 6)
- `stock-7` (producto 7)

### ✅ Versión Nueva (v3) - UN NODO POR NODO FINAL

**Ahora**: Se crea **UN SOLO nodo de venta** y **UN SOLO nodo de stock** por cada nodo final, agrupando **TODOS los productos** que produce ese nodo final.

Si un nodo final produce 3 productos diferentes, se crean solo 2 nodos:
- `sales-{finalNodeId}` (todos los productos agrupados)
- `stock-{finalNodeId}` (todos los productos agrupados)

---

## 📊 Nueva Estructura

### Nodo de Venta por Nodo Final

```json
{
  "type": "sales",
  "id": "sales-2",  // 👈 ID del nodo final, NO del producto
  "parentRecordId": 2,  // ID del nodo final
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
      "products": [  // 👈 Array de productos en este pedido
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
          "pallets": [...],
          "totalBoxes": 5,
          "totalNetWeight": 50.0
        }
      ],
      "totalBoxes": 15,  // Total del pedido (suma de todos los productos)
      "totalNetWeight": 145.0
    }
  ],
  "totalBoxes": 15,  // Total de TODOS los pedidos
  "totalNetWeight": 145.0,
  "summary": {
    "ordersCount": 1,
    "productsCount": 2,  // 👈 Número de productos diferentes
    "palletsCount": 2,
    "boxesCount": 15,
    "netWeight": 145.0
  },
  "children": []
}
```

### Nodo de Stock por Nodo Final

```json
{
  "type": "stock",
  "id": "stock-2",  // 👈 ID del nodo final, NO del producto
  "parentRecordId": 2,
  "productionId": 1,
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
        },
        {
          "product": {
            "id": 6,
            "name": "Atún en Aceite"
          },
          "pallets": [...],
          "totalBoxes": 8,
          "totalNetWeight": 76.0
        }
      ],
      "totalBoxes": 23,  // Total del almacén (suma de todos los productos)
      "totalNetWeight": 218.5
    }
  ],
  "totalBoxes": 23,  // Total de TODOS los almacenes
  "totalNetWeight": 218.5,
  "summary": {
    "storesCount": 1,
    "productsCount": 2,  // 👈 Número de productos diferentes
    "palletsCount": 3,
    "boxesCount": 23,
    "netWeight": 218.5
  },
  "children": []
}
```

---

## 🔧 Cambios en Tipos TypeScript

### ✅ Nueva Estructura

```typescript
interface SalesNode {
    type: 'sales';
    id: string;  // "sales-{finalNodeId}" - NO incluye productId
    parentRecordId: number;  // ID del nodo final
    productionId: number;
    orders: Array<{
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
        products: Array<{  // 👈 Array de productos en este pedido
            product: {
                id: number;
                name: string;
            };
            pallets: Array<{
                id: number;
                availableBoxesCount: number;
                totalAvailableWeight: number;
            }>;
            totalBoxes: number;
            totalNetWeight: number;
        }>;
        totalBoxes: number;  // Total del pedido
        totalNetWeight: number;
    }>;
    totalBoxes: number;
    totalNetWeight: number;
    summary: {
        ordersCount: number;
        productsCount: number;  // 👈 Nuevo: número de productos diferentes
        palletsCount: number;
        boxesCount: number;
        netWeight: number;
    };
    children: [];
}

interface StockNode {
    type: 'stock';
    id: string;  // "stock-{finalNodeId}" - NO incluye productId
    parentRecordId: number;
    productionId: number;
    stores: Array<{
        store: {
            id: number;
            name: string;
            temperature: number;
        };
        products: Array<{  // 👈 Array de productos en este almacén
            product: {
                id: number;
                name: string;
            };
            pallets: Array<{
                id: number;
                availableBoxesCount: number;
                totalAvailableWeight: number;
                position: string | null;
            }>;
            totalBoxes: number;
            totalNetWeight: number;
        }>;
        totalBoxes: number;  // Total del almacén
        totalNetWeight: number;
    }>;
    totalBoxes: number;
    totalNetWeight: number;
    summary: {
        storesCount: number;
        productsCount: number;  // 👈 Nuevo: número de productos diferentes
        palletsCount: number;
        boxesCount: number;
        netWeight: number;
    };
    children: [];
}
```

---

## 📋 Comparación de Cambios

| Aspecto | v2 (Anterior) | v3 (Actual) |
|---------|---------------|-------------|
| **Agrupación** | Por producto | Por nodo final |
| **ID del nodo** | `sales-{productId}` | `sales-{finalNodeId}` |
| **Cantidad de nodos** | 1 nodo por producto | 1 nodo por nodo final |
| **Estructura** | `orders` por producto | `orders` con `products` dentro |
| **Múltiples productos** | Múltiples nodos | Un solo nodo con array de productos |

---

## 🎯 Ejemplo Completo

### Caso: Nodo Final con 3 Productos

**Nodo Final** (id: 2) produce:
- Producto 5: "Filetes de Atún"
- Producto 6: "Atún en Aceite"  
- Producto 7: "Atún en Lata"

**Resultado**:

```json
{
  "id": 2,
  "isFinal": true,
  "outputs": [
    {"productId": 5, "product": {"id": 5, "name": "Filetes de Atún"}},
    {"productId": 6, "product": {"id": 6, "name": "Atún en Aceite"}},
    {"productId": 7, "product": {"id": 7, "name": "Atún en Lata"}}
  ],
  "children": [
    {
      "type": "sales",
      "id": "sales-2",  // 👈 Un solo nodo para todos los productos
      "parentRecordId": 2,
      "orders": [
        {
          "order": {
            "id": 123,
            "formattedId": "#00123"
          },
          "products": [
            {
              "product": {"id": 5, "name": "Filetes de Atún"},
              "pallets": [...],
              "totalBoxes": 10,
              "totalNetWeight": 95.0
            },
            {
              "product": {"id": 6, "name": "Atún en Aceite"},
              "pallets": [...],
              "totalBoxes": 5,
              "totalNetWeight": 50.0
            }
            // Producto 7 no está en este pedido
          ],
          "totalBoxes": 15,
          "totalNetWeight": 145.0
        }
      ],
      "summary": {
        "ordersCount": 1,
        "productsCount": 3,  // Todos los productos del nodo final
        "palletsCount": 2,
        "boxesCount": 15,
        "netWeight": 145.0
      }
    },
    {
      "type": "stock",
      "id": "stock-2",  // 👈 Un solo nodo para todos los productos
      "parentRecordId": 2,
      "stores": [
        {
          "store": {
            "id": 3,
            "name": "Almacén Central"
          },
          "products": [
            {
              "product": {"id": 5, "name": "Filetes de Atún"},
              "pallets": [...],
              "totalBoxes": 15,
              "totalNetWeight": 142.5
            },
            {
              "product": {"id": 7, "name": "Atún en Lata"},
              "pallets": [...],
              "totalBoxes": 20,
              "totalNetWeight": 190.0
            }
            // Producto 6 no está en este almacén
          ],
          "totalBoxes": 35,
          "totalNetWeight": 332.5
        }
      ],
      "summary": {
        "storesCount": 1,
        "productsCount": 3,  // Todos los productos del nodo final
        "palletsCount": 3,
        "boxesCount": 35,
        "netWeight": 332.5
      }
    }
  ]
}
```

---

## 💻 Cambios en Componentes

### Componente de Venta Actualizado

```typescript
function SalesNodeComponent({ node }: { node: SalesNode }) {
    return (
        <div className="sales-node">
            <div className="node-header">
                <span>🛒 VENTA</span>
            </div>
            
            <div className="node-content">
                {/* Totales agregados */}
                <div className="total-metrics">
                    <div><strong>Total Cajas:</strong> {node.totalBoxes}</div>
                    <div><strong>Total Peso:</strong> {node.totalNetWeight} kg</div>
                    <div><strong>Pedidos:</strong> {node.summary.ordersCount}</div>
                    <div><strong>Productos:</strong> {node.summary.productsCount}</div>
                </div>
                
                {/* Desglose por pedido */}
                {node.orders.map((orderData, orderIndex) => (
                    <details key={orderIndex} className="order-details">
                        <summary>
                            Pedido {orderData.order.formattedId}
                            {orderData.order.customer && (
                                <> - {orderData.order.customer.name}</>
                            )}
                            <span className="order-summary">
                                ({orderData.totalBoxes} cajas, {orderData.totalNetWeight} kg)
                            </span>
                        </summary>
                        
                        {/* Productos en este pedido */}
                        {orderData.products.map((productData, productIndex) => (
                            <div key={productIndex} className="product-in-order">
                                <h5>{productData.product.name}</h5>
                                <div>
                                    {productData.totalBoxes} cajas, {productData.totalNetWeight} kg
                                </div>
                                {/* Palets de este producto */}
                                <ul>
                                    {productData.pallets.map(pallet => (
                                        <li key={pallet.id}>
                                            Palet #{pallet.id}: {pallet.availableBoxesCount} cajas
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        ))}
                    </details>
                ))}
            </div>
        </div>
    );
}
```

### Componente de Stock Actualizado

```typescript
function StockNodeComponent({ node }: { node: StockNode }) {
    return (
        <div className="stock-node">
            <div className="node-header">
                <span>📦 STOCK</span>
            </div>
            
            <div className="node-content">
                {/* Totales agregados */}
                <div className="total-metrics">
                    <div><strong>Total Cajas:</strong> {node.totalBoxes}</div>
                    <div><strong>Total Peso:</strong> {node.totalNetWeight} kg</div>
                    <div><strong>Almacenes:</strong> {node.summary.storesCount}</div>
                    <div><strong>Productos:</strong> {node.summary.productsCount}</div>
                </div>
                
                {/* Desglose por almacén */}
                {node.stores.map((storeData, storeIndex) => (
                    <details key={storeIndex} className="store-details">
                        <summary>
                            {storeData.store.name} ({storeData.store.temperature}°C)
                            <span className="store-summary">
                                ({storeData.totalBoxes} cajas, {storeData.totalNetWeight} kg)
                            </span>
                        </summary>
                        
                        {/* Productos en este almacén */}
                        {storeData.products.map((productData, productIndex) => (
                            <div key={productIndex} className="product-in-store">
                                <h5>{productData.product.name}</h5>
                                <div>
                                    {productData.totalBoxes} cajas, {productData.totalNetWeight} kg
                                </div>
                                {/* Palets de este producto */}
                                <ul>
                                    {productData.pallets.map(pallet => (
                                        <li key={pallet.id}>
                                            Palet #{pallet.id}: {pallet.availableBoxesCount} cajas
                                            {pallet.position && (
                                                <> - Posición: {pallet.position}</>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        ))}
                    </details>
                ))}
            </div>
        </div>
    );
}
```

---

## ⚠️ Cambios Críticos

### 1. ID del Nodo

**Antes**: `sales-5` (producto 5)  
**Ahora**: `sales-2` (nodo final 2)

```typescript
// ❌ Antes
if (node.id.startsWith('sales-')) {
    const [, productId] = node.id.split('-');
    // productId = "5"
}

// ✅ Ahora
if (node.id.startsWith('sales-')) {
    const [, finalNodeId] = node.id.split('-');
    // finalNodeId = "2" (ID del nodo final)
}
```

### 2. Acceso a Productos

**Antes**: Cada nodo tenía un solo producto  
**Ahora**: Cada nodo puede tener múltiples productos dentro de cada pedido/almacén

```typescript
// ❌ Antes
const product = node.product;  // Un solo producto

// ✅ Ahora
node.orders.forEach(orderData => {
    orderData.products.forEach(productData => {
        const product = productData.product;  // Múltiples productos
    });
});
```

### 3. Búsqueda de Nodos

**Antes**: Buscar por producto  
**Ahora**: Buscar por nodo final

```typescript
// ❌ Antes
function findSalesNodeByProduct(productId: number): SalesNode | null {
    // Buscar nodo con id "sales-{productId}"
}

// ✅ Ahora
function findSalesNodeByFinalNode(finalNodeId: number): SalesNode | null {
    // Buscar nodo con id "sales-{finalNodeId}"
}
```

---

## 📋 Checklist de Migración desde v2

### Fase 1: Actualizar Tipos
- [ ] Cambiar `id` de `sales-{productId}` a `sales-{finalNodeId}`
- [ ] Cambiar estructura de `orders` para incluir array `products`
- [ ] Añadir `productsCount` en `summary`
- [ ] Actualizar `StockNode` de la misma manera

### Fase 2: Actualizar Componentes
- [ ] Modificar `SalesNodeComponent` para iterar sobre `order.products`
- [ ] Modificar `StockNodeComponent` para iterar sobre `store.products`
- [ ] Mostrar lista de productos dentro de cada pedido/almacén

### Fase 3: Actualizar Lógica
- [ ] Actualizar funciones que usan el ID del nodo
- [ ] Actualizar búsquedas/filtrados por producto
- [ ] Actualizar funciones que acceden a `node.product` (ahora no existe directamente)

### Fase 4: Testing
- [ ] Probar con nodo final que produce un solo producto
- [ ] Probar con nodo final que produce múltiples productos
- [ ] Verificar que todos los productos aparecen correctamente
- [ ] Verificar que los totales son correctos

---

## ✅ Resumen

**Cambio principal**: Agrupación por **nodo final** en lugar de por producto.

**Estructura**:
- UN nodo de venta por nodo final → contiene todos los productos
- UN nodo de stock por nodo final → contiene todos los productos
- Cada pedido/almacén tiene un array de productos
- Cada producto tiene sus palets

**Ventajas**:
- ✅ Menos nodos en el árbol
- ✅ Vista más clara de qué viene de cada nodo final
- ✅ Todos los productos del nodo final juntos

---

**Fin del Documento**

