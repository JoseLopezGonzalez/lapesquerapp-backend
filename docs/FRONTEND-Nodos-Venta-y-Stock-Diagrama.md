# Frontend: Actualización del Diagrama con Nodos de Venta y Stock

## 📋 Resumen Ejecutivo

El endpoint `GET /v2/productions/{id}/process-tree` ahora incluye **nuevos tipos de nodos** que deben renderizarse en el diagrama:
- **Nodos de Venta** (`type: "sales"`): Productos asignados a pedidos
- **Nodos de Stock** (`type: "stock"`): Productos almacenados en almacenes

Estos nodos aparecen **como hijos de los nodos finales** del árbol de procesos o como nodos independientes si no hay nodo final para ese producto.

**Fecha de Cambio**: 2025-01-27  
**Endpoint Afectado**: `GET /v2/productions/{id}/process-tree`  
**Prioridad**: Alta - Cambio en estructura de datos

---

## 🎯 Objetivo del Cambio

Extender el diagrama de producción para mostrar:
1. Qué productos del lote están siendo **vendidos** (asignados a pedidos)
2. Qué productos del lote están **almacenados** (en almacenes)

Esto completa el flujo visual: **Producción → Venta/Stock**

---

## 🔄 Cambios en la Estructura de Datos

### Estructura Actual (Sin Cambios en Nodos de Proceso)

Los nodos de proceso mantienen su estructura existente:
- `id`: ID numérico del proceso
- `process`: Información del proceso
- `isFinal`: Boolean indicando si es nodo final
- `children`: Array de hijos (ahora puede incluir nodos de venta/stock)
- Etc.

### Nuevos Tipos de Nodos

El endpoint ahora puede retornar **tres tipos de nodos**:

1. **Nodo de Proceso** (existente): `type` ausente o `null`
   - Representa un proceso de producción
   - Estructura actual sin cambios

2. **Nodo de Venta** (nuevo): `type: "sales"`
   - Representa productos en pedidos

3. **Nodo de Stock** (nuevo): `type: "stock"`
   - Representa productos en almacenes

### Identificación de Tipos de Nodos

```javascript
// Identificar tipo de nodo
function getNodeType(node) {
    if (node.type === 'sales') {
        return 'SALES';
    }
    if (node.type === 'stock') {
        return 'STOCK';
    }
    // Si no tiene type o es null, es un nodo de proceso
    return 'PROCESS';
}
```

---

## 📦 Estructura de Nodos de Venta

### Campos del Nodo

```typescript
interface SalesNode {
    type: "sales";
    id: string;  // Formato: "sales-{productId}-{orderId}"
    parentRecordId: number | null;  // ID del nodo final padre (null si no tiene padre)
    productionId: number;
    product: {
        id: number;
        name: string;
    };
    order: {
        id: number;
        formattedId: string;  // Ejemplo: "#00123"
        customer: {
            id: number;
            name: string;
        } | null;
        loadDate: string | null;  // ISO 8601
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
    children: [];  // Siempre vacío, los nodos de venta no tienen hijos
}
```

### Ejemplo Real

```json
{
    "type": "sales",
    "id": "sales-5-123",
    "parentRecordId": 2,
    "productionId": 1,
    "product": {
        "id": 5,
        "name": "Filetes de Atún"
    },
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
        },
        {
            "id": 790,
            "availableBoxesCount": 8,
            "totalAvailableWeight": 76.0
        }
    ],
    "totalBoxes": 18,
    "totalNetWeight": 171.0,
    "summary": {
        "palletsCount": 2,
        "boxesCount": 18,
        "netWeight": 171.0
    },
    "children": []
}
```

### Información para Renderizar

**Título del nodo**: `"Venta: {product.name}"`  
**Subtítulo**: `"Pedido {order.formattedId} - {order.customer?.name || 'Sin cliente'}"`  
**Información principal**:
- Total de cajas: `totalBoxes`
- Peso neto total: `totalNetWeight` kg
- Número de palets: `summary.palletsCount`

**Color/Icono sugerido**: 🟢 Verde o icono de venta/carrito

---

## 📦 Estructura de Nodos de Stock

### Campos del Nodo

```typescript
interface StockNode {
    type: "stock";
    id: string;  // Formato: "stock-{productId}-{storeId}"
    parentRecordId: number | null;  // ID del nodo final padre (null si no tiene padre)
    productionId: number;
    product: {
        id: number;
        name: string;
    };
    store: {
        id: number;
        name: string;
        temperature: number;  // Temperatura del almacén
    };
    pallets: Array<{
        id: number;
        availableBoxesCount: number;
        totalAvailableWeight: number;
        position: string | null;  // Posición en el almacén (ej: "A-12")
    }>;
    totalBoxes: number;
    totalNetWeight: number;
    summary: {
        palletsCount: number;
        boxesCount: number;
        netWeight: number;
    };
    children: [];  // Siempre vacío, los nodos de stock no tienen hijos
}
```

### Ejemplo Real

```json
{
    "type": "stock",
    "id": "stock-5-3",
    "parentRecordId": 2,
    "productionId": 1,
    "product": {
        "id": 5,
        "name": "Filetes de Atún"
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
            "totalAvailableWeight": 142.5,
            "position": "A-12"
        },
        {
            "id": 457,
            "availableBoxesCount": 20,
            "totalAvailableWeight": 190.0,
            "position": "A-13"
        }
    ],
    "totalBoxes": 35,
    "totalNetWeight": 332.5,
    "summary": {
        "palletsCount": 2,
        "boxesCount": 35,
        "netWeight": 332.5
    },
    "children": []
}
```

### Información para Renderizar

**Título del nodo**: `"Stock: {product.name}"`  
**Subtítulo**: `"{store.name} ({store.temperature}°C)"`  
**Información principal**:
- Total de cajas: `totalBoxes`
- Peso neto total: `totalNetWeight` kg
- Número de palets: `summary.palletsCount`
- Posiciones: Lista de posiciones de los palets (si hay)

**Color/Icono sugerido**: 🔵 Azul o icono de almacén/almacenamiento

---

## 🔗 Lógica de Vinculación Jerárquica

### Reglas de Posicionamiento

1. **Nodos con Padre** (`parentRecordId !== null`):
   - Son hijos directos de un nodo final específico
   - Se posicionan **debajo** del nodo final que los produce
   - El nodo final tiene `isFinal: true`

2. **Nodos sin Padre** (`parentRecordId === null`):
   - Son nodos independientes (orphan nodes)
   - Se posicionan al **mismo nivel** que los nodos raíz
   - Aparecen cuando:
     - No hay nodo final para ese producto, O
     - Hay múltiples nodos finales para ese producto (ambigüedad)

### Ejemplo Visual del Árbol

```
┌─────────────────────┐
│  Proceso: Fileteado │ (isFinal: true)
│  Output: Producto 5 │
└──────────┬──────────┘
           │
     ┌─────┴─────┐
     │           │
┌────▼────┐ ┌───▼─────┐
│ VENTA   │ │ STOCK   │
│ Pedido  │ │ Almacén │
│ #00123  │ │ Central │
└─────────┘ └─────────┘
```

### Caso Especial: Nodos Huérfanos

```
┌─────────────────────┐
│  Proceso A Final    │ (isFinal: true, producto 5)
└─────────────────────┘

┌─────────────────────┐
│  Proceso B Final    │ (isFinal: true, producto 5)
└─────────────────────┘

┌─────────────────────┐
│  VENTA              │ (parentRecordId: null)
│  Producto 5         │ (Sin padre por ambigüedad)
│  Pedido #00123      │
└─────────────────────┘
```

---

## 📊 Nuevos Campos en Totales

El objeto `totals` ahora incluye métricas de venta y stock:

```typescript
interface Totals {
    // Campos existentes
    totalInputWeight: number;
    totalOutputWeight: number;
    totalWaste: number;
    totalWastePercentage: number;
    totalYield: number;
    totalYieldPercentage: number;
    totalInputBoxes: number;
    totalOutputBoxes: number;
    
    // ✨ Nuevos campos
    totalSalesWeight: number;      // Peso total en venta
    totalSalesBoxes: number;       // Cajas totales en venta
    totalSalesPallets: number;     // Palets totales en venta
    totalStockWeight: number;      // Peso total en stock
    totalStockBoxes: number;       // Cajas totales en stock
    totalStockPallets: number;     // Palets totales en stock
}
```

### Ejemplo

```json
{
    "totals": {
        "totalInputWeight": 150.50,
        "totalOutputWeight": 215.30,
        "totalWaste": 0,
        "totalWastePercentage": 0,
        "totalYield": 64.80,
        "totalYieldPercentage": 43.06,
        "totalInputBoxes": 5,
        "totalOutputBoxes": 18,
        "totalSalesWeight": 196.0,
        "totalSalesBoxes": 23,
        "totalSalesPallets": 3,
        "totalStockWeight": 142.5,
        "totalStockBoxes": 15,
        "totalStockPallets": 1
    }
}
```

---

## 🎨 Recomendaciones de Diseño Visual

### Diferenciación Visual

| Tipo de Nodo | Color Sugerido | Icono Sugerido | Estilo |
|--------------|----------------|----------------|--------|
| **Proceso** | Gris/Azul estándar | ⚙️ Proceso | Rectángulo con borde |
| **Venta** | 🟢 Verde | 🛒 Carrito/Venta | Rectángulo verde |
| **Stock** | 🔵 Azul | 📦 Almacén | Rectángulo azul |

### Información a Mostrar en Cada Nodo

#### Nodo de Venta:
```
┌──────────────────────────────┐
│ 🛒 VENTA                     │
│ Filetes de Atún              │
│                              │
│ Pedido: #00123               │
│ Cliente: Supermercado Central│
│                              │
│ 📦 18 cajas                  │
│ ⚖️ 171.0 kg                  │
│ 🏷️ 2 palets                  │
└──────────────────────────────┘
```

#### Nodo de Stock:
```
┌──────────────────────────────┐
│ 📦 STOCK                     │
│ Filetes de Atún              │
│                              │
│ Almacén: Central             │
│ Temp: -18°C                  │
│                              │
│ 📦 35 cajas                  │
│ ⚖️ 332.5 kg                  │
│ 🏷️ 2 palets                  │
│ 📍 A-12, A-13                │
└──────────────────────────────┘
```

### Conexiones en el Diagrama

- **Nodos de proceso → Nodos finales**: Conexión estándar existente
- **Nodos finales → Nodos de venta/stock**: Nueva conexión (línea punteada o diferente color)
- **Nodos huérfanos**: Sin conexión padre, aparecen al nivel raíz

---

## 💻 Cambios Necesarios en el Código Frontend

### 1. Actualizar Tipos/Interfaces TypeScript

```typescript
// types.ts o interfaces.ts

type NodeType = 'PROCESS' | 'SALES' | 'STOCK';

interface BaseNode {
    id: string | number;
    parentRecordId: number | null;
    productionId: number;
    children: TreeNode[];
}

interface ProcessNode extends BaseNode {
    type?: undefined | null;
    process: {
        id: number;
        name: string;
        type: string;
    };
    isFinal: boolean;
    isRoot: boolean;
    // ... otros campos de proceso
}

interface SalesNode extends BaseNode {
    type: 'sales';
    product: {
        id: number;
        name: string;
    };
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
}

interface StockNode extends BaseNode {
    type: 'stock';
    product: {
        id: number;
        name: string;
    };
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
}

type TreeNode = ProcessNode | SalesNode | StockNode;
```

### 2. Función de Identificación de Tipo

```typescript
function getNodeType(node: TreeNode): NodeType {
    if (node.type === 'sales') {
        return 'SALES';
    }
    if (node.type === 'stock') {
        return 'STOCK';
    }
    return 'PROCESS';
}

function isProcessNode(node: TreeNode): node is ProcessNode {
    return !node.type || node.type === null || node.type === undefined;
}

function isSalesNode(node: TreeNode): node is SalesNode {
    return node.type === 'sales';
}

function isStockNode(node: TreeNode): node is StockNode {
    return node.type === 'stock';
}
```

### 3. Componente de Renderizado

```typescript
// DiagramNode.tsx o similar

function DiagramNode({ node, level = 0 }: { node: TreeNode; level?: number }) {
    const nodeType = getNodeType(node);
    
    switch (nodeType) {
        case 'PROCESS':
            return <ProcessNodeComponent node={node as ProcessNode} level={level} />;
        case 'SALES':
            return <SalesNodeComponent node={node as SalesNode} level={level} />;
        case 'STOCK':
            return <StockNodeComponent node={node as StockNode} level={level} />;
        default:
            return null;
    }
}
```

### 4. Componente de Nodo de Venta

```typescript
// SalesNodeComponent.tsx

function SalesNodeComponent({ node, level }: { node: SalesNode; level: number }) {
    return (
        <div className="sales-node" style={{ marginLeft: `${level * 40}px` }}>
            <div className="node-header sales-header">
                <span className="icon">🛒</span>
                <span className="title">VENTA: {node.product.name}</span>
            </div>
            
            <div className="node-content">
                <div className="order-info">
                    <strong>Pedido:</strong> {node.order.formattedId}
                </div>
                {node.order.customer && (
                    <div className="customer-info">
                        <strong>Cliente:</strong> {node.order.customer.name}
                    </div>
                )}
                
                <div className="metrics">
                    <div className="metric">
                        <span className="label">Cajas:</span>
                        <span className="value">{node.totalBoxes}</span>
                    </div>
                    <div className="metric">
                        <span className="label">Peso:</span>
                        <span className="value">{node.totalNetWeight} kg</span>
                    </div>
                    <div className="metric">
                        <span className="label">Palets:</span>
                        <span className="value">{node.summary.palletsCount}</span>
                    </div>
                </div>
                
                {/* Opcional: Mostrar lista de palets */}
                {node.pallets.length > 0 && (
                    <details>
                        <summary>Ver palets ({node.pallets.length})</summary>
                        <ul>
                            {node.pallets.map(pallet => (
                                <li key={pallet.id}>
                                    Palet #{pallet.id}: {pallet.availableBoxesCount} cajas, {pallet.totalAvailableWeight} kg
                                </li>
                            ))}
                        </ul>
                    </details>
                )}
            </div>
        </div>
    );
}
```

### 5. Componente de Nodo de Stock

```typescript
// StockNodeComponent.tsx

function StockNodeComponent({ node, level }: { node: StockNode; level: number }) {
    const positions = node.pallets
        .map(p => p.position)
        .filter(p => p !== null)
        .join(', ');
    
    return (
        <div className="stock-node" style={{ marginLeft: `${level * 40}px` }}>
            <div className="node-header stock-header">
                <span className="icon">📦</span>
                <span className="title">STOCK: {node.product.name}</span>
            </div>
            
            <div className="node-content">
                <div className="store-info">
                    <strong>Almacén:</strong> {node.store.name}
                </div>
                <div className="temperature-info">
                    <strong>Temperatura:</strong> {node.store.temperature}°C
                </div>
                
                {positions && (
                    <div className="positions-info">
                        <strong>Posiciones:</strong> {positions}
                    </div>
                )}
                
                <div className="metrics">
                    <div className="metric">
                        <span className="label">Cajas:</span>
                        <span className="value">{node.totalBoxes}</span>
                    </div>
                    <div className="metric">
                        <span className="label">Peso:</span>
                        <span className="value">{node.totalNetWeight} kg</span>
                    </div>
                    <div className="metric">
                        <span className="label">Palets:</span>
                        <span className="value">{node.summary.palletsCount}</span>
                    </div>
                </div>
                
                {/* Opcional: Mostrar lista de palets */}
                {node.pallets.length > 0 && (
                    <details>
                        <summary>Ver palets ({node.pallets.length})</summary>
                        <ul>
                            {node.pallets.map(pallet => (
                                <li key={pallet.id}>
                                    Palet #{pallet.id}: {pallet.availableBoxesCount} cajas, {pallet.totalAvailableWeight} kg
                                    {pallet.position && ` - Posición: ${pallet.position}`}
                                </li>
                            ))}
                        </ul>
                    </details>
                )}
            </div>
        </div>
    );
}
```

### 6. Actualizar Función de Recorrido del Árbol

```typescript
// Asegurar que la función recursiva maneja los nuevos tipos

function renderTree(nodes: TreeNode[], level = 0) {
    return nodes.map(node => (
        <div key={node.id} className="tree-level">
            <DiagramNode node={node} level={level} />
            
            {/* Renderizar hijos recursivamente */}
            {node.children && node.children.length > 0 && (
                <div className="children-container">
                    {renderTree(node.children, level + 1)}
                </div>
            )}
        </div>
    ));
}
```

### 7. Actualizar Totales en UI

```typescript
// TotalsComponent.tsx

function TotalsComponent({ totals }: { totals: Totals }) {
    return (
        <div className="totals-panel">
            <h3>Totales Globales</h3>
            
            {/* Totales existentes */}
            <div className="totals-section">
                <h4>Producción</h4>
                <div>Peso Entrada: {totals.totalInputWeight} kg</div>
                <div>Peso Salida: {totals.totalOutputWeight} kg</div>
                <div>Cajas Entrada: {totals.totalInputBoxes}</div>
                <div>Cajas Salida: {totals.totalOutputBoxes}</div>
            </div>
            
            {/* ✨ Nuevos totales de venta */}
            {totals.totalSalesWeight > 0 && (
                <div className="totals-section sales-totals">
                    <h4>🛒 Venta</h4>
                    <div>Peso: {totals.totalSalesWeight} kg</div>
                    <div>Cajas: {totals.totalSalesBoxes}</div>
                    <div>Palets: {totals.totalSalesPallets}</div>
                </div>
            )}
            
            {/* ✨ Nuevos totales de stock */}
            {totals.totalStockWeight > 0 && (
                <div className="totals-section stock-totals">
                    <h4>📦 Stock</h4>
                    <div>Peso: {totals.totalStockWeight} kg</div>
                    <div>Cajas: {totals.totalStockBoxes}</div>
                    <div>Palets: {totals.totalStockPallets}</div>
                </div>
            )}
        </div>
    );
}
```

---

## 📝 Checklist de Implementación Frontend

### Fase 1: Preparación
- [ ] Actualizar tipos/interfaces TypeScript para incluir `SalesNode` y `StockNode`
- [ ] Crear función `getNodeType()` para identificar tipo de nodo
- [ ] Crear funciones type guards (`isProcessNode`, `isSalesNode`, `isStockNode`)

### Fase 2: Componentes de Renderizado
- [ ] Crear componente `SalesNodeComponent` para renderizar nodos de venta
- [ ] Crear componente `StockNodeComponent` para renderizar nodos de stock
- [ ] Actualizar componente `DiagramNode` para manejar los nuevos tipos
- [ ] Asegurar que la función recursiva de renderizado procesa todos los tipos

### Fase 3: Estilos y UX
- [ ] Definir estilos CSS para nodos de venta (color verde, iconos)
- [ ] Definir estilos CSS para nodos de stock (color azul, iconos)
- [ ] Ajustar espaciado y conexiones entre nodos
- [ ] Añadir animaciones/transiciones si aplica

### Fase 4: Información Detallada
- [ ] Mostrar información del pedido en nodos de venta
- [ ] Mostrar información del almacén en nodos de stock
- [ ] Mostrar lista de palets (colapsable/expandible)
- [ ] Mostrar posiciones en almacén si aplica

### Fase 5: Totales
- [ ] Actualizar componente de totales para mostrar `totalSalesWeight`, etc.
- [ ] Añadir secciones separadas para totales de venta y stock
- [ ] Verificar que los totales se actualicen correctamente

### Fase 6: Testing
- [ ] Probar con producción que tiene nodos de venta
- [ ] Probar con producción que tiene nodos de stock
- [ ] Probar con producción que tiene nodos huérfanos
- [ ] Probar con producción que tiene múltiples nodos finales
- [ ] Verificar renderizado correcto del árbol completo

---

## 🎯 Ejemplo Completo de Respuesta del Endpoint

```json
{
    "message": "Árbol de procesos obtenido correctamente.",
    "data": {
        "processNodes": [
            {
                "id": 1,
                "isRoot": true,
                "isFinal": false,
                "process": {
                    "id": 1,
                    "name": "Eviscerado"
                },
                "children": [
                    {
                        "id": 2,
                        "isFinal": true,
                        "process": {
                            "id": 2,
                            "name": "Fileteado Final"
                        },
                        "outputs": [
                            {
                                "productId": 5,
                                "product": {
                                    "id": 5,
                                    "name": "Filetes de Atún"
                                }
                            }
                        ],
                        "children": [
                            {
                                "type": "sales",
                                "id": "sales-5-123",
                                "parentRecordId": 2,
                                "productionId": 1,
                                "product": {
                                    "id": 5,
                                    "name": "Filetes de Atún"
                                },
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
                                },
                                "children": []
                            },
                            {
                                "type": "stock",
                                "id": "stock-5-3",
                                "parentRecordId": 2,
                                "productionId": 1,
                                "product": {
                                    "id": 5,
                                    "name": "Filetes de Atún"
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
            "totalSalesWeight": 95.0,
            "totalSalesBoxes": 10,
            "totalSalesPallets": 1,
            "totalStockWeight": 142.5,
            "totalStockBoxes": 15,
            "totalStockPallets": 1
        }
    }
}
```

---

## ⚠️ Consideraciones Importantes

### 1. Compatibilidad Hacia Atrás

- Los nodos de proceso **NO cambian** su estructura
- Si un nodo no tiene `type`, es un nodo de proceso (comportamiento existente)
- Solo se añaden nuevos tipos, no se modifican los existentes

### 2. Nodos Huérfanos

- Los nodos con `parentRecordId: null` deben renderizarse al **nivel raíz**
- No tienen conexión con ningún nodo padre
- Aparecen cuando hay ambigüedad o no hay nodo final

### 3. Rendimiento

- Los nodos de venta/stock pueden aparecer múltiples veces si hay muchos productos/pedidos/almacenes
- Considerar virtualización del árbol si hay muchos nodos
- Los nodos de venta/stock **siempre tienen `children: []`** (vacío), no hay que procesarlos recursivamente

### 4. Validación de Datos

- Verificar que `node.type` existe antes de acceder a campos específicos
- Validar que `order` o `store` existen según el tipo de nodo
- Manejar casos donde `customer` puede ser `null`

---

## 🔍 Casos de Uso para Testing

### Caso 1: Producción Simple con Venta y Stock
- 1 nodo final
- 1 nodo de venta como hijo
- 1 nodo de stock como hijo

### Caso 2: Múltiples Nodos Finales
- 2 nodos finales con el mismo producto
- Nodos de venta/stock deben aparecer como huérfanos

### Caso 3: Múltiples Productos en Pedido
- 1 nodo final con 2 productos
- 2 nodos de venta diferentes (mismo pedido, productos distintos)

### Caso 4: Múltiples Palets
- 1 nodo de venta con 5 palets agrupados
- Verificar que se muestre correctamente el total y la lista

### Caso 5: Sin Venta ni Stock
- Producción sin nodos de venta/stock
- Debe funcionar igual que antes (compatibilidad)

---

## 📚 Referencias

- **Documentación Backend Completa**: `docs/produccion/DISENO-Nodos-Venta-y-Stock-Production-Tree.md`
- **Documentación del Endpoint**: `docs/produccion/11-Produccion-Lotes.md`
- **Endpoint**: `GET /api/v2/productions/{id}/process-tree`

---

## ✅ Resumen de Cambios para el Frontend

1. **Nuevos tipos de nodos**: `type: "sales"` y `type: "stock"`
2. **Estructura jerárquica**: Nodos de venta/stock como hijos de nodos finales
3. **Nodos huérfanos**: Pueden aparecer sin padre al nivel raíz
4. **Nuevos totales**: Campos de venta y stock en objeto `totals`
5. **Renderizado**: Crear componentes específicos para cada tipo de nodo
6. **Compatibilidad**: Los nodos de proceso no cambian, solo se añaden nuevos tipos

**Estado**: ✅ Backend implementado y documentado - Listo para integración frontend

---

**Fin del Documento**

