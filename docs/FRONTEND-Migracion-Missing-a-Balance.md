# 🚀 Migración Frontend: Missing → Balance

**Fecha**: 2025-01-27  
**Breaking Change**: ⚠️ **SÍ** - Requiere actualización del código frontend

---

## 📋 Resumen del Cambio

El nodo `missing` ha sido renombrado a `balance` para reflejar que puede contener tanto **faltantes** (valores positivos) como **sobras** (valores negativos).

### Cambios Principales

| Antes | Ahora |
|-------|-------|
| `type: "missing"` | `type: "balance"` |
| `id: "missing-{finalNodeId}"` | `id: "balance-{finalNodeId}"` |
| `product.missing` | `product.balance` |
| `summary.totalMissingBoxes` | `summary.totalBalanceBoxes` |
| `summary.totalMissingWeight` | `summary.totalBalanceWeight` |

---

## 🔧 Cambios en el Código

### 1. Actualizar Tipos TypeScript

#### Antes
```typescript
interface MissingNode {
    type: 'missing';
    id: string;  // "missing-{finalNodeId}"
    products: Array<{
        product: { id: number; name: string };
        produced: { boxes: number; weight: number };
        inSales: { boxes: number; weight: number };
        inStock: { boxes: number; weight: number };
        reprocessed: { boxes: number; weight: number };
        missing: {  // ❌ Cambiar a balance
            boxes: number;
            weight: number;
            percentage: number;
        };
        boxes: Array<{ id: number; netWeight: number; gs1_128: string | null }>;
    }>;
    summary: {
        productsCount: number;
        totalMissingBoxes: number;  // ❌ Cambiar
        totalMissingWeight: number;  // ❌ Cambiar
    };
    children: [];
}

type ProcessTreeNode = 
    | ProcessNode
    | SalesNode
    | StockNode
    | ReprocessedNode
    | MissingNode;  // ❌ Cambiar a BalanceNode
```

#### Ahora
```typescript
interface BalanceNode {
    type: 'balance';  // ✨ Cambiado
    id: string;  // "balance-{finalNodeId}"  ✨ Cambiado
    products: Array<{
        product: { id: number; name: string };
        produced: { boxes: number; weight: number };
        inSales: { boxes: number; weight: number };
        inStock: { boxes: number; weight: number };
        reprocessed: { boxes: number; weight: number };
        balance: {  // ✨ Cambiado de "missing"
            boxes: number;
            weight: number;  // Positivo = faltante, Negativo = sobrante
            percentage: number;  // Solo si es positivo
        };
        boxes: Array<{ id: number; netWeight: number; gs1_128: string | null }>;
    }>;
    summary: {
        productsCount: number;
        totalBalanceBoxes: number;  // ✨ Cambiado
        totalBalanceWeight: number;  // ✨ Cambiado (puede ser negativo)
    };
    children: [];
}

type ProcessTreeNode = 
    | ProcessNode
    | SalesNode
    | StockNode
    | ReprocessedNode
    | BalanceNode;  // ✨ Cambiado de MissingNode
```

---

### 2. Actualizar Identificación de Nodos

#### Antes
```typescript
function getNodeType(node: ProcessTreeNode): string {
    if ('type' in node) {
        return node.type; // 'sales', 'stock', 'reprocessed', 'missing'
    }
    return 'process';
}

// Renderizado
switch (node.type) {
    case 'sales':
        return renderSalesNode(node);
    case 'stock':
        return renderStockNode(node);
    case 'reprocessed':
        return renderReprocessedNode(node);
    case 'missing':  // ❌ Cambiar
        return renderMissingNode(node);
    default:
        return renderProcessNode(node);
}
```

#### Ahora
```typescript
function getNodeType(node: ProcessTreeNode): string {
    if ('type' in node) {
        return node.type; // 'sales', 'stock', 'reprocessed', 'balance'  ✨
    }
    return 'process';
}

// Renderizado
switch (node.type) {
    case 'sales':
        return renderSalesNode(node);
    case 'stock':
        return renderStockNode(node);
    case 'reprocessed':
        return renderReprocessedNode(node);
    case 'balance':  // ✨ Cambiado
        return renderBalanceNode(node);
    default:
        return renderProcessNode(node);
}
```

---

### 3. Actualizar Renderizado del Nodo

#### Antes
```typescript
function renderMissingNode(node: MissingNode) {
    return node.products.map(product => (
        <div key={product.product.id}>
            <h3>{product.product.name}</h3>
            <div>Producido: {product.produced.weight}kg</div>
            <div>En Venta: {product.inSales.weight}kg</div>
            <div>En Stock: {product.inStock.weight}kg</div>
            <div>Re-procesado: {product.reprocessed.weight}kg</div>
            
            {/* ❌ Usa product.missing */}
            <div className="alert-warning">
                ⚠️ Faltante: {product.missing.weight}kg ({product.missing.percentage}%)
            </div>
            
            {product.boxes.length > 0 && (
                <div>Cajas faltantes: {product.boxes.map(b => b.id).join(', ')}</div>
            )}
        </div>
    ));
}
```

#### Ahora
```typescript
function renderBalanceNode(node: BalanceNode) {  // ✨ Cambiado nombre
    return node.products.map(product => {
        const balance = product.balance.weight;  // ✨ Cambiado de product.missing.weight
        const isShortage = balance > 0;  // Faltante
        const isExcess = balance < 0;    // Sobrante
        
        return (
            <div key={product.product.id}>
                <h3>{product.product.name}</h3>
                <div>Producido: {product.produced.weight}kg</div>
                <div>En Venta: {product.inSales.weight}kg</div>
                <div>En Stock: {product.inStock.weight}kg</div>
                <div>Re-procesado: {product.reprocessed.weight}kg</div>
                
                {/* ✨ Manejar faltantes y sobras */}
                {isShortage && (
                    <div className="alert-warning">
                        ⚠️ Faltante: {balance}kg ({product.balance.percentage}%)
                    </div>
                )}
                
                {isExcess && (
                    <div className="alert-error">
                        ❌ Sobrante: {Math.abs(balance)}kg (más contabilizado que producido)
                    </div>
                )}
                
                {product.boxes.length > 0 && (
                    <div>Cajas faltantes: {product.boxes.map(b => b.id).join(', ')}</div>
                )}
            </div>
        );
    });
}
```

---

### 4. Actualizar Acceso a Campos del Summary

#### Antes
```typescript
if (node.type === 'missing') {
    const missingNode = node as MissingNode;
    console.log(`Total faltantes: ${missingNode.summary.totalMissingBoxes} cajas`);
    console.log(`Peso faltante: ${missingNode.summary.totalMissingWeight}kg`);
}
```

#### Ahora
```typescript
if (node.type === 'balance') {
    const balanceNode = node as BalanceNode;
    console.log(`Total balance: ${balanceNode.summary.totalBalanceBoxes} cajas`);
    console.log(`Peso balance: ${balanceNode.summary.totalBalanceWeight}kg`);
    
    // ✨ El peso puede ser negativo (sobrante)
    if (balanceNode.summary.totalBalanceWeight < 0) {
        console.warn('⚠️ Hay sobrantes (más contabilizado que producido)');
    }
}
```

---

### 5. Actualizar Filtros y Búsquedas

#### Antes
```typescript
// Buscar nodos de faltantes
const missingNodes = nodes.filter(node => node.type === 'missing');

// Buscar por ID
const missingNode = nodes.find(node => node.id?.startsWith('missing-'));
```

#### Ahora
```typescript
// Buscar nodos de balance
const balanceNodes = nodes.filter(node => node.type === 'balance');  // ✨

// Buscar por ID
const balanceNode = nodes.find(node => node.id?.startsWith('balance-'));  // ✨
```

---

## 📊 Interpretación de Valores

### Balance Positivo (Faltante)
```typescript
if (product.balance.weight > 0) {
    // Hay productos producidos que no están contabilizados
    // Ejemplo: Producido 100kg, contabilizado 80kg → Faltante: 20kg
    showWarning(`Faltante: ${product.balance.weight}kg`);
}
```

### Balance Negativo (Sobrante)
```typescript
if (product.balance.weight < 0) {
    // Hay más contabilizado que producido (posible error de datos)
    // Ejemplo: Producido 100kg, contabilizado 120kg → Sobrante: -20kg
    showError(`Sobrante: ${Math.abs(product.balance.weight)}kg`);
    showError('⚠️ Posible error: más contabilizado que producido');
}
```

### Balance Cero (Balanceado)
```typescript
if (product.balance.weight === 0) {
    // Todo está contabilizado correctamente
    showSuccess('✅ Todo contabilizado');
}
```

---

## 🎨 Ejemplo de Componente Completo

```typescript
import React from 'react';

interface BalanceNode {
    type: 'balance';
    id: string;
    products: Array<{
        product: { id: number; name: string };
        produced: { boxes: number; weight: number };
        inSales: { boxes: number; weight: number };
        inStock: { boxes: number; weight: number };
        reprocessed: { boxes: number; weight: number };
        balance: {
            boxes: number;
            weight: number;
            percentage: number;
        };
        boxes: Array<{ id: number; netWeight: number; gs1_128: string | null }>;
    }>;
    summary: {
        productsCount: number;
        totalBalanceBoxes: number;
        totalBalanceWeight: number;
    };
}

const BalanceNodeComponent: React.FC<{ node: BalanceNode }> = ({ node }) => {
    return (
        <div className="balance-node">
            <h2>Balance de Productos</h2>
            
            {node.products.map(product => {
                const balance = product.balance.weight;
                const isShortage = balance > 0;
                const isExcess = balance < 0;
                
                return (
                    <div key={product.product.id} className="product-balance">
                        <h3>{product.product.name}</h3>
                        
                        <div className="balance-details">
                            <div>Producido: {product.produced.weight}kg</div>
                            <div>En Venta: {product.inSales.weight}kg</div>
                            <div>En Stock: {product.inStock.weight}kg</div>
                            <div>Re-procesado: {product.reprocessed.weight}kg</div>
                        </div>
                        
                        {isShortage && (
                            <div className="alert alert-warning">
                                ⚠️ Faltante: {balance}kg 
                                {product.balance.percentage > 0 && (
                                    <span> ({product.balance.percentage}%)</span>
                                )}
                            </div>
                        )}
                        
                        {isExcess && (
                            <div className="alert alert-error">
                                ❌ Sobrante: {Math.abs(balance)}kg
                                <br />
                                <small>Más contabilizado que producido (posible error)</small>
                            </div>
                        )}
                        
                        {product.boxes.length > 0 && (
                            <div className="missing-boxes">
                                <strong>Cajas faltantes:</strong>
                                <ul>
                                    {product.boxes.map(box => (
                                        <li key={box.id}>
                                            Caja {box.id} - {box.netWeight}kg
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}
                    </div>
                );
            })}
            
            <div className="summary">
                <h4>Resumen</h4>
                <div>Productos: {node.summary.productsCount}</div>
                <div>Cajas: {node.summary.totalBalanceBoxes}</div>
                <div>
                    Peso: {node.summary.totalBalanceWeight}kg
                    {node.summary.totalBalanceWeight < 0 && (
                        <span className="text-danger"> (Sobrante)</span>
                    )}
                </div>
            </div>
        </div>
    );
};

export default BalanceNodeComponent;
```

---

## ✅ Checklist de Migración

- [ ] Actualizar tipos TypeScript (`MissingNode` → `BalanceNode`)
- [ ] Cambiar `type: 'missing'` → `type: 'balance'` en switch/case
- [ ] Cambiar `product.missing` → `product.balance` en todo el código
- [ ] Cambiar `summary.totalMissingBoxes` → `summary.totalBalanceBoxes`
- [ ] Cambiar `summary.totalMissingWeight` → `summary.totalBalanceWeight`
- [ ] Actualizar búsquedas/filtros de `'missing'` → `'balance'`
- [ ] Actualizar IDs de `'missing-'` → `'balance-'`
- [ ] Implementar lógica para mostrar faltantes (positivos) y sobras (negativos)
- [ ] Actualizar tests unitarios
- [ ] Actualizar documentación interna del frontend
- [ ] Probar con datos reales del backend

---

## 🔄 Compatibilidad Temporal (Opcional)

Si necesitas mantener compatibilidad temporal con el backend antiguo:

```typescript
function getBalanceNode(node: ProcessTreeNode): BalanceNode | null {
    // Intentar nuevo formato primero
    if (node.type === 'balance') {
        return node as BalanceNode;
    }
    
    // Fallback al formato antiguo (temporal)
    if (node.type === 'missing') {
        const oldNode = node as any; // MissingNode antiguo
        return {
            ...oldNode,
            type: 'balance',
            id: oldNode.id.replace('missing-', 'balance-'),
            products: oldNode.products.map((p: any) => ({
                ...p,
                balance: p.missing || p.balance, // Usar balance si existe, sino missing
            })),
            summary: {
                ...oldNode.summary,
                totalBalanceBoxes: oldNode.summary.totalMissingBoxes || oldNode.summary.totalBalanceBoxes,
                totalBalanceWeight: oldNode.summary.totalMissingWeight || oldNode.summary.totalBalanceWeight,
            },
        } as BalanceNode;
    }
    
    return null;
}
```

**Nota**: Esta compatibilidad temporal solo debería usarse durante la transición. Elimínala una vez que el backend esté completamente actualizado.

---

## 📚 Referencias

- **Documento del cambio**: `CAMBIO-Nodo-Missing-a-Balance.md`
- **Documentación frontend completa**: `FRONTEND-Nodos-Re-procesados-y-Faltantes.md`
- **Guía rápida**: `FRONTEND-Guia-Rapida-Nodos-Completos.md`

---

**Fecha de migración recomendada**: Inmediata (breaking change)

