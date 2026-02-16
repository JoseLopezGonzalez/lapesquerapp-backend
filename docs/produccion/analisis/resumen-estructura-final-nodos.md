# Resumen: Estructura Final de Nodos de Venta y Stock

## ✅ Confirmación de la Estructura Final

### Respuesta Corta
**SÍ**. Con la nueva implementación (v3), habrá:
- **UN SOLO nodo de venta** por cada nodo final (si tiene productos en venta)
- **UN SOLO nodo de stock** por cada nodo final (si tiene productos en stock)

---

## 📊 Estructura Final Confirmada

### Por Cada Nodo Final:

```
Nodo Final (ID: 2)
├── Produce: Producto 5, Producto 6, Producto 7
│
├── [OPCIONAL] Nodo de Venta (sales-2)
│   └── Agrupa TODOS los productos del nodo final que están en venta
│       └── Desglose por pedido → productos dentro de cada pedido
│
└── [OPCIONAL] Nodo de Stock (stock-2)
    └── Agrupa TODOS los productos del nodo final que están en stock
        └── Desglose por almacén → productos dentro de cada almacén
```

### Reglas:

1. **Si un nodo final NO tiene productos en venta** → NO se crea nodo de venta
2. **Si un nodo final NO tiene productos en stock** → NO se crea nodo de stock
3. **Si un nodo final tiene productos en venta** → Se crea UN SOLO nodo de venta con todos
4. **Si un nodo final tiene productos en stock** → Se crea UN SOLO nodo de stock con todos

---

## 🔢 Ejemplos de Cantidad de Nodos

### Ejemplo 1: Nodo Final con 1 Producto

**Nodo Final (ID: 2)** produce:
- Producto 5: "Filetes de Atún"

**Resultado**:
- ✅ **1 nodo de venta** (`sales-2`) con el producto 5
- ✅ **1 nodo de stock** (`stock-2`) con el producto 5

**Total**: 2 nodos (1 venta + 1 stock)

---

### Ejemplo 2: Nodo Final con 3 Productos

**Nodo Final (ID: 2)** produce:
- Producto 5: "Filetes de Atún"
- Producto 6: "Atún en Aceite"
- Producto 7: "Atún en Lata"

**Resultado**:
- ✅ **1 nodo de venta** (`sales-2`) con los productos 5, 6, 7
- ✅ **1 nodo de stock** (`stock-2`) con los productos 5, 6, 7

**Total**: 2 nodos (1 venta + 1 stock)

---

### Ejemplo 3: Nodo Final sin Venta

**Nodo Final (ID: 2)** produce:
- Producto 5: "Filetes de Atún" (solo en stock, no en venta)

**Resultado**:
- ❌ **0 nodos de venta** (no hay productos en venta)
- ✅ **1 nodo de stock** (`stock-2`) con el producto 5

**Total**: 1 nodo (solo stock)

---

### Ejemplo 4: Nodo Final sin Stock

**Nodo Final (ID: 2)** produce:
- Producto 5: "Filetes de Atún" (solo en venta, no en stock)

**Resultado**:
- ✅ **1 nodo de venta** (`sales-2`) con el producto 5
- ❌ **0 nodos de stock** (no hay productos en stock)

**Total**: 1 nodo (solo venta)

---

### Ejemplo 5: Múltiples Nodos Finales

**Nodo Final A (ID: 2)** produce:
- Producto 5: "Filetes de Atún"
- Producto 6: "Atún en Aceite"

**Nodo Final B (ID: 3)** produce:
- Producto 7: "Atún en Lata"
- Producto 8: "Atún en Conserva"

**Resultado**:
- ✅ **1 nodo de venta** para nodo final A (`sales-2`) con productos 5, 6
- ✅ **1 nodo de stock** para nodo final A (`stock-2`) con productos 5, 6
- ✅ **1 nodo de venta** para nodo final B (`sales-3`) con productos 7, 8
- ✅ **1 nodo de stock** para nodo final B (`stock-3`) con productos 7, 8

**Total**: 4 nodos (2 venta + 2 stock)

---

## 📐 Fórmula General

Para **N nodos finales**:
- **Máximo de nodos de venta**: N (uno por cada nodo final que tenga productos en venta)
- **Máximo de nodos de stock**: N (uno por cada nodo final que tenga productos en stock)
- **Máximo total**: 2N nodos (N venta + N stock)

**Mínimo total**: 0 nodos (si ningún nodo final tiene productos en venta o stock)

---

## 🎯 Estructura del Nodo

### Nodo de Venta por Nodo Final

```json
{
  "type": "sales",
  "id": "sales-{finalNodeId}",  // ID del nodo final
  "parentRecordId": {finalNodeId},
  "orders": [
    {
      "order": {...},
      "products": [  // Todos los productos del nodo final en este pedido
        {"product": {...}, "pallets": [...]},
        {"product": {...}, "pallets": [...]}
      ]
    }
  ],
  "summary": {
    "productsCount": 3  // Número de productos diferentes del nodo final
  }
}
```

### Nodo de Stock por Nodo Final

```json
{
  "type": "stock",
  "id": "stock-{finalNodeId}",  // ID del nodo final
  "parentRecordId": {finalNodeId},
  "stores": [
    {
      "store": {...},
      "products": [  // Todos los productos del nodo final en este almacén
        {"product": {...}, "pallets": [...]},
        {"product": {...}, "pallets": [...]}
      ]
    }
  ],
  "summary": {
    "productsCount": 3  // Número de productos diferentes del nodo final
  }
}
```

---

## ✅ Confirmación Final

**Pregunta**: ¿Solo habrá un nodo de venta y un nodo de stock por cada nodo final?

**Respuesta**: **SÍ**, exactamente:

1. **Un nodo de venta por nodo final** (si tiene productos en venta)
2. **Un nodo de stock por nodo final** (si tiene productos en stock)

**NO habrá**:
- ❌ Múltiples nodos de venta para el mismo nodo final
- ❌ Múltiples nodos de stock para el mismo nodo final
- ❌ Nodos separados por producto

**Cada nodo agrupa TODOS los productos** que produce ese nodo final.

---

**Fin del Documento**

