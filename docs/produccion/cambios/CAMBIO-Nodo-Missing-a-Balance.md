# Cambio: Nodo "missing" → "balance"

**Fecha**: 2025-01-27  
**Motivo**: El nodo ahora maneja tanto faltantes (positivos) como sobras (negativos), por lo que el nombre "missing" ya no es semánticamente correcto.

---

## 🔄 Cambio Realizado

### Antes
- **Tipo de nodo**: `"missing"`
- **ID del nodo**: `"missing-{finalNodeId}"`
- **Campos en summary**: `totalMissingBoxes`, `totalMissingWeight`
- **Campo en producto**: `missing` (con `weight`, `boxes`, `percentage`)
- **Semántica**: Solo indicaba faltantes

### Ahora
- **Tipo de nodo**: `"balance"` ✨
- **ID del nodo**: `"balance-{finalNodeId}"` ✨
- **Campos en summary**: `totalBalanceBoxes`, `totalBalanceWeight` ✨
- **Campo en producto**: `balance` (con `weight`, `boxes`, `percentage`) ✨
- **Semántica**: Indica balance completo (faltantes y sobras)

---

## 📊 ¿Qué Significa el Balance?

El nodo `balance` calcula para cada producto:

```
Balance = Producido - Venta - Stock - Re-procesado
```

### Valores Positivos (Faltantes)
- **Balance > 0**: Hay productos producidos que no están contabilizados
- **Ejemplo**: Producido 100kg, contabilizado 80kg → **Faltante: 20kg**

### Valores Negativos (Sobras)
- **Balance < 0**: Hay más contabilizado que producido (posible error de datos)
- **Ejemplo**: Producido 100kg, contabilizado 120kg → **Sobrante: -20kg**
- **Causas posibles**:
  - Cajas con lote incorrecto
  - Productos asignados a pedidos/almacenes de otro lote
  - Errores en el registro de producción

---

## 📋 Estructura del Nodo Actualizada

```json
{
  "type": "balance",  // ✨ Cambiado de "missing"
  "id": "balance-8",   // ✨ Cambiado de "missing-8"
  "parentRecordId": 8,
  "productionId": 1,
  "products": [
    {
      "product": { "id": 104, "name": "Pulpo Fresco Rizado" },
      "produced": { "boxes": 0, "weight": 700.0 },
      "inSales": { "boxes": 145, "weight": 725.0 },
      "inStock": { "boxes": 40, "weight": 200.0 },
      "reprocessed": { "boxes": 0, "weight": 0.0 },
      "balance": {  // ✨ Cambiado de "missing"
        "boxes": 0,
        "weight": -225.0,  // ⚠️ Negativo = sobrante
        "percentage": 0.0
      },
      "boxes": []
    },
    {
      "product": { "id": 205, "name": "Alacha congelada mediana" },
      "produced": { "boxes": 0, "weight": 400.0 },
      "inSales": { "boxes": 0, "weight": 0.0 },
      "inStock": { "boxes": 0, "weight": 0.0 },
      "reprocessed": { "boxes": 0, "weight": 0.0 },
      "balance": {
        "boxes": 0,
        "weight": 400.0,  // ✅ Positivo = faltante
        "percentage": 100.0
      },
      "boxes": []
    }
  ],
  "summary": {
    "productsCount": 2,
    "totalBalanceBoxes": 0,      // ✨ Cambiado de totalMissingBoxes
    "totalBalanceWeight": 175.0  // ✨ Cambiado de totalMissingWeight (puede ser negativo)
  },
  "children": []
}
```

---

## ✨ Campo "balance" Dentro del Producto

**El campo `balance` dentro de cada producto** contiene:
- `weight`: Balance calculado (positivo = faltante, negativo = sobrante)
- `boxes`: Número de cajas físicas faltantes (solo si hay cajas físicas)
- `percentage`: Porcentaje de faltantes sobre lo producido (solo si es positivo)

**El frontend debe interpretar**:
- `balance.weight > 0` → Mostrar como "Faltante"
- `balance.weight < 0` → Mostrar como "Sobrante" o "Error de datos"

---

## 🔧 Cambios en el Código

### Archivo: `app/Models/Production.php`

1. **Tipo de nodo**: `'type' => 'balance'` (antes `'missing'`)
2. **ID del nodo**: `"balance-{$finalNodeId}"` (antes `"missing-{$finalNodeId}"`)
3. **ID de nodo huérfano**: `"balance-orphan-{$productId}"` (antes `"missing-orphan-{$productId}"`)
4. **Campos en summary**:
   - `totalBalanceBoxes` (antes `totalMissingBoxes`)
   - `totalBalanceWeight` (antes `totalMissingWeight`)
5. **Campo en producto**: `balance` (antes `missing`)
6. **Comentarios actualizados** para reflejar que maneja faltantes y sobras

---

## 📝 Cambios para el Frontend

### 1. Actualizar Tipos TypeScript

```typescript
// Antes
interface MissingNode {
    type: 'missing';
    // ...
}

// Ahora
interface BalanceNode {
    type: 'balance';  // ✨ Cambiado
    id: string;  // "balance-{finalNodeId}"
    products: Array<{
        product: { id: number; name: string };
        produced: { boxes: number; weight: number };
        inSales: { boxes: number; weight: number };
        inStock: { boxes: number; weight: number };
        reprocessed: { boxes: number; weight: number };
        balance: {  // ✨ Cambiado de "missing"
            boxes: number;
            weight: number;  // Positivo = faltante, Negativo = sobrante
            percentage: number;
        };
        boxes: Array<{ id: number; netWeight: number; gs1_128: string | null; location: null }>;
    }>;
    summary: {
        productsCount: number;
        totalBalanceBoxes: number;   // ✨ Cambiado
        totalBalanceWeight: number;   // ✨ Cambiado (puede ser negativo)
    };
    children: [];
}
```

### 2. Actualizar Renderizado

```typescript
// Antes
case 'missing':
    return renderMissingNode(node);

// Ahora
case 'balance':
    return renderBalanceNode(node);
```

### 3. Mostrar Faltantes y Sobras

```typescript
function renderBalanceNode(node: BalanceNode) {
    return node.products.map(product => {
        const balance = product.balance.weight;  // ✨ Cambiado de product.missing.weight
        const isShortage = balance > 0;
        const isExcess = balance < 0;
        
        return (
            <div>
                <h3>{product.product.name}</h3>
                <div>Producido: {product.produced.weight}kg</div>
                <div>En Venta: {product.inSales.weight}kg</div>
                <div>En Stock: {product.inStock.weight}kg</div>
                <div>Re-procesado: {product.reprocessed.weight}kg</div>
                
                {isShortage && (
                    <div className="alert-warning">
                        ⚠️ Faltante: {balance}kg ({product.balance.percentage}%)  {/* ✨ Cambiado */}
                    </div>
                )}
                
                {isExcess && (
                    <div className="alert-error">
                        ❌ Sobrante: {Math.abs(balance)}kg (más contabilizado que producido)
                    </div>
                )}
            </div>
        );
    });
}
```

---

## ✅ Ventajas del Cambio

1. **Semánticamente correcto**: El nombre "balance" refleja mejor que puede ser positivo o negativo
2. **Un solo nodo**: No necesitamos crear nodos separados para faltantes y sobras
3. **Más claro**: El frontend puede interpretar el signo para mostrar el mensaje apropiado
4. **Menos confusión**: Evita que el usuario se pregunte "¿por qué hay un nodo 'missing' con valores negativos?"

---

## 🔄 Compatibilidad

**Breaking Change**: Sí, el frontend debe actualizarse para:
- Buscar nodos con `type: 'balance'` en lugar de `type: 'missing'`
- Usar `totalBalanceBoxes` y `totalBalanceWeight` en lugar de `totalMissingBoxes` y `totalMissingWeight`
- Usar `product.balance` en lugar de `product.missing`
- Interpretar valores negativos como sobras

---

**Cambio completado**: 2025-01-27

