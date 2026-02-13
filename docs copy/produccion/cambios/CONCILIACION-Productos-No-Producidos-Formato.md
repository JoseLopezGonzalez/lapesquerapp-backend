# Formato de Respuesta: Productos No Producidos en Conciliación

**Fecha**: 2025-01-XX  
**Endpoint**: `GET /v2/productions/{id}` (campo `reconciliation`)

---

## 📋 Respuesta

Los productos que **NO están registrados como producidos** pero que **existen en el sistema** (en venta, stock o reprocesados) se devuelven **con el mismo formato** que los productos producidos, pero con características especiales:

---

## 🔍 Características de Productos No Producidos

### 1. Campo `produced`

```json
{
  "produced": {
    "weight": 0.0,  // ✨ Siempre 0
    "boxes": 0      // ✨ Siempre 0
  }
}
```

### 2. Campos `inSales`, `inStock`, `reprocessed`

Contienen los valores reales si el producto existe en esas fuentes:

```json
{
  "inSales": {
    "weight": 30.0,  // ✨ Valor real si existe en venta
    "boxes": 6
  },
  "inStock": {
    "weight": 0.0,   // 0 si no está en stock
    "boxes": 0
  },
  "reprocessed": {
    "weight": 0.0,   // 0 si no fue reprocesado
    "boxes": 0
  }
}
```

### 3. Campo `balance`

```json
{
  "balance": {
    "weight": -30.0,      // ✨ Siempre negativo (exceso)
    "percentage": -100.0  // ✨ Siempre -100% si no está producido
  }
}
```

**Fórmula**: `balance = produced - (inSales + inStock + reprocessed)`
- Si `produced = 0` y hay contabilizado → `balance = -contabilized` (negativo)

### 4. Campo `status`

```json
{
  "status": "error"  // ✨ Siempre "error" si no está producido pero está contabilizado
}
```

### 5. Campo `message`

```json
{
  "message": "Producto no registrado como producido pero existe en venta/stock/reprocesado (30kg)"
}
```

**Formato del mensaje**: 
```
"Producto no registrado como producido pero existe en venta/stock/reprocesado (Xkg)"
```

Donde `X` es el total contabilizado (suma de venta + stock + reprocesado).

---

## 📊 Ejemplo Completo

### Producto Producido (Normal)

```json
{
  "product": {
    "id": 104,
    "name": "Pulpo Fresco Rizado"
  },
  "produced": {
    "weight": 100.0,
    "boxes": 20
  },
  "inSales": {
    "weight": 50.0,
    "boxes": 10
  },
  "inStock": {
    "weight": 50.0,
    "boxes": 10
  },
  "reprocessed": {
    "weight": 0.0,
    "boxes": 0
  },
  "balance": {
    "weight": 0.0,
    "percentage": 0.0
  },
  "status": "ok",
  "message": "Todo contabilizado correctamente"
}
```

### Producto NO Producido (Especial)

```json
{
  "product": {
    "id": 105,
    "name": "Pulpo Fresco Entero"
  },
  "produced": {
    "weight": 0.0,  // ✨ 0 porque no está registrado
    "boxes": 0      // ✨ 0 porque no está registrado
  },
  "inSales": {
    "weight": 30.0,  // ✨ Pero existe en venta
    "boxes": 6
  },
  "inStock": {
    "weight": 0.0,
    "boxes": 0
  },
  "reprocessed": {
    "weight": 0.0,
    "boxes": 0
  },
  "balance": {
    "weight": -30.0,     // ✨ Negativo (exceso)
    "percentage": -100.0 // ✨ -100% porque no está producido
  },
  "status": "error",    // ✨ Siempre error
  "message": "Producto no registrado como producido pero existe en venta/stock/reprocesado (30kg)"
}
```

---

## 🎯 Identificación en Frontend

Para identificar productos no producidos en el frontend:

```javascript
// Opción 1: Por produced.weight === 0 y contabilizado > 0
const isNotProduced = product.produced.weight === 0 && 
  (product.inSales.weight > 0 || 
   product.inStock.weight > 0 || 
   product.reprocessed.weight > 0);

// Opción 2: Por status === 'error' y balance.percentage === -100
const isNotProduced = product.status === 'error' && 
  product.balance.percentage === -100;

// Opción 3: Por el mensaje
const isNotProduced = product.message.includes('no registrado como producido');
```

---

## ⚠️ Casos Especiales

### Caso 1: Producto en Múltiples Fuentes

```json
{
  "product": {
    "id": 106,
    "name": "Pulpo Cocido"
  },
  "produced": {
    "weight": 0.0,
    "boxes": 0
  },
  "inSales": {
    "weight": 20.0,  // En venta
    "boxes": 4
  },
  "inStock": {
    "weight": 15.0,  // Y en stock
    "boxes": 3
  },
  "reprocessed": {
    "weight": 5.0,   // Y reprocesado
    "boxes": 1
  },
  "balance": {
    "weight": -40.0,     // Total: -40kg
    "percentage": -100.0
  },
  "status": "error",
  "message": "Producto no registrado como producido pero existe en venta/stock/reprocesado (40kg)"
}
```

El mensaje muestra el **total contabilizado** (20 + 15 + 5 = 40kg).

### Caso 2: Producto Solo en Stock

```json
{
  "product": {
    "id": 107,
    "name": "Pulpo Solo Stock"
  },
  "produced": {
    "weight": 0.0,
    "boxes": 0
  },
  "inSales": {
    "weight": 0.0,
    "boxes": 0
  },
  "inStock": {
    "weight": 25.0,  // Solo en stock
    "boxes": 5
  },
  "reprocessed": {
    "weight": 0.0,
    "boxes": 0
  },
  "balance": {
    "weight": -25.0,
    "percentage": -100.0
  },
  "status": "error",
  "message": "Producto no registrado como producido pero existe en venta/stock/reprocesado (25kg)"
}
```

---

## 📝 Resumen

| Característica | Producto Producido | Producto NO Producido |
|----------------|-------------------|----------------------|
| `produced.weight` | > 0 | = 0 |
| `produced.boxes` | > 0 | = 0 |
| `balance.weight` | Puede ser positivo, negativo o 0 | Siempre negativo |
| `balance.percentage` | Variable | Siempre -100% |
| `status` | ok/warning/error | Siempre "error" |
| `message` | Varios mensajes | "Producto no registrado..." |

---

## ✅ Ventajas de Este Formato

1. **Consistencia**: Mismo formato para todos los productos
2. **Fácil identificación**: Se puede identificar por `produced.weight === 0` y `status === 'error'`
3. **Información completa**: Muestra todas las fuentes (venta, stock, reprocesado)
4. **Mensaje claro**: Indica explícitamente que no está registrado como producido

---

**Autor**: Documentación de formato  
**Fecha**: 2025-01-XX  
**Versión**: 1.0

