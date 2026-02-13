# Frontend: Relaciones Padre-Hijo en el Árbol de Producción

## 📋 Resumen

Este documento explica cómo el frontend identifica las relaciones padre-hijo entre nodos en el árbol de producción, tanto para nodos de proceso como para los nuevos nodos de venta y stock.

---

## 🔗 Sistema de Identificación de Relaciones

### Para Nodos de Proceso (Existentes)

Los nodos de proceso usan el campo `parentRecordId` para identificar su padre:

```json
{
  "id": 2,
  "parentRecordId": 1,  // 👈 ID del nodo padre (o null si es raíz)
  "process": {
    "id": 4,
    "name": "Fileteado"
  },
  "children": [
    // Nodos hijos aquí
  ]
}
```

**Lógica**:
- Si `parentRecordId === null` → Es un nodo **raíz** (no tiene padre)
- Si `parentRecordId !== null` → Es un nodo **hijo** (el valor es el `id` del nodo padre)
- El árbol se construye recursivamente usando el array `children`

### Para Nodos de Venta y Stock (Nuevos)

Los nodos de venta y stock también usan `parentRecordId` de la misma manera:

```json
{
  "type": "sales",
  "id": "sales-5",
  "parentRecordId": 2,  // 👈 ID del nodo final padre (o null si es huérfano)
  "productionId": 1,
  "product": {...},
  "orders": [...],
  "children": []  // Siempre vacío
}
```

**Lógica**:
- Si `parentRecordId === null` → Es un nodo **huérfano** (orphan node), se coloca al nivel raíz
- Si `parentRecordId !== null` → Es hijo del nodo final con ese `id`
- **Los nodos de venta/stock siempre tienen `children: []`** (no tienen hijos propios)

---

## 🎯 Cómo el Frontend Construye las Conexiones

### Opción 1: Estructura Jerárquica Recursiva (Recomendada)

El backend ya construye el árbol jerárquico completo. El frontend solo necesita recorrerlo recursivamente:

```typescript
// El árbol ya viene estructurado desde el backend
// processNodes es un array plano o jerárquico

interface TreeNode {
    id: string | number;
    parentRecordId: number | null;
    children: TreeNode[];
    // ... otros campos
}

function renderTree(nodes: TreeNode[], level = 0) {
    return nodes.map(node => (
        <div key={node.id} className="tree-level" style={{ marginLeft: `${level * 40}px` }}>
            <NodeComponent node={node} />
            
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

**Ventajas**:
- ✅ Simple y directo
- ✅ El backend ya hace el trabajo pesado
- ✅ No necesita construir relaciones manualmente

### Opción 2: Construcción Manual usando parentRecordId

Si prefieres construir el árbol desde un array plano, puedes usar `parentRecordId`:

```typescript
interface FlatNode {
    id: string | number;
    parentRecordId: number | null;
    // ... otros campos
}

interface TreeNode extends FlatNode {
    children: TreeNode[];
}

function buildTree(flatNodes: FlatNode[]): TreeNode[] {
    // Crear mapa de nodos por ID
    const nodeMap = new Map<string | number, TreeNode>();
    
    // Inicializar todos los nodos con children vacío
    flatNodes.forEach(node => {
        nodeMap.set(node.id, {
            ...node,
            children: []
        });
    });
    
    // Construir el árbol
    const rootNodes: TreeNode[] = [];
    
    flatNodes.forEach(node => {
        const treeNode = nodeMap.get(node.id)!;
        
        if (node.parentRecordId === null) {
            // Es un nodo raíz
            rootNodes.push(treeNode);
        } else {
            // Es un nodo hijo, añadirlo al padre
            const parent = nodeMap.get(node.parentRecordId);
            if (parent) {
                parent.children.push(treeNode);
            } else {
                // Padre no encontrado (no debería pasar), añadir como raíz
                rootNodes.push(treeNode);
            }
        }
    });
    
    return rootNodes;
}
```

---

## 📊 Estructura Completa del Árbol

### Ejemplo Real del Backend

```json
{
  "processNodes": [
    {
      "id": 1,
      "parentRecordId": null,  // 👈 Nodo raíz
      "isRoot": true,
      "isFinal": false,
      "process": {
        "name": "Eviscerado"
      },
      "children": [
        {
          "id": 2,
          "parentRecordId": 1,  // 👈 Hijo del nodo 1
          "isFinal": true,
          "process": {
            "name": "Fileteado"
          },
          "children": [
            {
              "type": "sales",
              "id": "sales-5",
              "parentRecordId": 2,  // 👈 Hijo del nodo 2 (final)
              "children": []
            },
            {
              "type": "stock",
              "id": "stock-5",
              "parentRecordId": 2,  // 👈 Hijo del nodo 2 (final)
              "children": []
            }
          ]
        }
      ]
    },
    {
      "type": "sales",
      "id": "sales-6",
      "parentRecordId": null,  // 👈 Nodo huérfano (sin padre)
      "children": []
    }
  ]
}
```

### Visualización del Árbol

```
processNodes (array nivel raíz)
│
├── Nodo Proceso 1 (id: 1, parentRecordId: null) [RAÍZ]
│   │
│   └── children: [
│       │
│       └── Nodo Proceso 2 (id: 2, parentRecordId: 1) [FINAL]
│           │
│           └── children: [
│               │
│               ├── Nodo Venta (id: "sales-5", parentRecordId: 2)
│               │   └── children: []
│               │
│               └── Nodo Stock (id: "stock-5", parentRecordId: 2)
│                   └── children: []
│
└── Nodo Venta (id: "sales-6", parentRecordId: null) [HUÉRFANO]
    └── children: []
```

---

## 🔍 Identificación de Tipos de Nodos

### Para Determinar el Tipo de Relación

```typescript
interface Node {
    id: string | number;
    parentRecordId: number | null;
    type?: 'sales' | 'stock';
    isRoot?: boolean;
    isFinal?: boolean;
}

function getNodeRelationType(node: Node): 'ROOT' | 'CHILD' | 'ORPHAN' {
    // Nodos de proceso
    if (!node.type) {
        if (node.parentRecordId === null) {
            return 'ROOT';
        }
        return 'CHILD';
    }
    
    // Nodos de venta/stock
    if (node.type === 'sales' || node.type === 'stock') {
        if (node.parentRecordId === null) {
            return 'ORPHAN';  // Nodo huérfano
        }
        return 'CHILD';  // Hijo de un nodo final
    }
    
    return 'CHILD';
}
```

### Para Encontrar el Nodo Padre

```typescript
function findParentNode(nodeId: number, allNodes: TreeNode[]): TreeNode | null {
    function search(nodes: TreeNode[]): TreeNode | null {
        for (const node of nodes) {
            if (node.id === nodeId) {
                return node;
            }
            if (node.children && node.children.length > 0) {
                const found = search(node.children);
                if (found) {
                    return found;
                }
            }
        }
        return null;
    }
    
    return search(allNodes);
}

// Uso:
const node = { id: "sales-5", parentRecordId: 2 };
const parent = findParentNode(node.parentRecordId, processNodes);
// parent ahora contiene el nodo con id: 2
```

---

## 🎨 Renderizado Visual con Conexiones

### Ejemplo de Componente con Conexiones

```typescript
function ProductionTreeDiagram({ processNodes }: { processNodes: TreeNode[] }) {
    return (
        <div className="production-tree">
            {renderTree(processNodes)}
        </div>
    );
}

function NodeComponent({ node, level = 0 }: { node: TreeNode; level?: number }) {
    const nodeType = getNodeType(node);
    const relationType = getNodeRelationType(node);
    
    return (
        <div className="node-container" style={{ marginLeft: `${level * 40}px` }}>
            {/* Línea de conexión si tiene padre */}
            {relationType === 'CHILD' && level > 0 && (
                <div className="connection-line" />
            )}
            
            {/* Nodo */}
            <div className={`node node-${nodeType} relation-${relationType}`}>
                {nodeType === 'PROCESS' && <ProcessNode node={node} />}
                {nodeType === 'SALES' && <SalesNode node={node} />}
                {nodeType === 'STOCK' && <StockNode node={node} />}
            </div>
            
            {/* Renderizar hijos */}
            {node.children && node.children.length > 0 && (
                <div className="children">
                    {node.children.map(child => (
                        <NodeComponent key={child.id} node={child} level={level + 1} />
                    ))}
                </div>
            )}
        </div>
    );
}
```

### Estilos CSS para Conexiones

```css
.production-tree {
    position: relative;
}

.node-container {
    position: relative;
}

.connection-line {
    position: absolute;
    left: -20px;
    top: 50%;
    width: 20px;
    height: 2px;
    background-color: #ccc;
}

.connection-line::before {
    content: '';
    position: absolute;
    left: -10px;
    top: -1px;
    width: 2px;
    height: 100%;
    background-color: #ccc;
}

.children {
    margin-left: 40px;
    border-left: 2px solid #eee;
    padding-left: 20px;
}
```

---

## ⚠️ Casos Especiales

### 1. Nodos Huérfanos (Orphan Nodes)

Los nodos de venta/stock pueden aparecer sin padre cuando:
- No hay nodo final que produzca ese producto
- Hay múltiples nodos finales para el mismo producto (ambigüedad)

```typescript
// Identificar nodos huérfanos
const orphanNodes = processNodes.filter(node => 
    (node.type === 'sales' || node.type === 'stock') && 
    node.parentRecordId === null
);

// Renderizar al nivel raíz
<div className="root-level">
    {processNodes.map(node => (
        <NodeComponent key={node.id} node={node} />
    ))}
</div>
```

### 2. Múltiples Nodos Finales para el Mismo Producto

Cuando hay ambigüedad, los nodos de venta/stock se crean como huérfanos:

```json
[
  {
    "id": 1,
    "isFinal": true,
    "outputs": [{"productId": 5}]
  },
  {
    "id": 2,
    "isFinal": true,
    "outputs": [{"productId": 5}]
  },
  {
    "type": "sales",
    "id": "sales-5",
    "parentRecordId": null,  // 👈 Huérfano por ambigüedad
    "product": {"id": 5}
  }
]
```

---

## ✅ Checklist para el Frontend

- [ ] Identificar tipo de nodo usando `node.type` o ausencia del campo
- [ ] Verificar `parentRecordId` para determinar si es raíz, hijo o huérfano
- [ ] Recorrer recursivamente el array `children` para construir el árbol
- [ ] Renderizar conexiones visuales usando el nivel de anidación
- [ ] Manejar nodos huérfanos al nivel raíz
- [ ] Asegurar que los nodos de venta/stock no tengan hijos (verificar `children: []`)

---

## 📚 Resumen

1. **Todos los nodos** (proceso, venta, stock) usan `parentRecordId` para identificar el padre
2. **El árbol ya viene estructurado** desde el backend en el array `children`
3. **El frontend solo necesita recorrer** recursivamente los `children`
4. **Los nodos huérfanos** tienen `parentRecordId: null` y se colocan al nivel raíz
5. **Los nodos de venta/stock** siempre tienen `children: []` (vacío)

**El sistema es consistente**: Los nodos de venta/stock funcionan exactamente igual que los nodos de proceso en términos de relaciones padre-hijo.

---

**Fin del Documento**

