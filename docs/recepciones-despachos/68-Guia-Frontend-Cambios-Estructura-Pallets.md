# Guía Frontend: Cambios en Estructura de Pallets y Precios

## 📋 Resumen Ejecutivo

Este documento describe los **cambios críticos** en la estructura del request para crear y editar recepciones en modo PALLETS. Estos cambios permiten mayor flexibilidad y evitan duplicación de precios cuando múltiples palets comparten productos y lotes.

**Fecha de implementación**: Diciembre 2025  
**Versión API**: v2  
**Endpoints afectados**: 
- `POST /api/v2/raw-material-receptions`
- `PUT /api/v2/raw-material-receptions/{id}`

---

## ⚠️ CAMBIOS CRÍTICOS

### 1. Campos Eliminados

❌ **Ya NO se usan** (causarán error de validación):
- `pallets[].product.id` 
- `pallets[].price`
- `pallets[].lot`
- `pallets[].prices` (movido a la raíz)

### 2. Campos Nuevos/Modificados

✅ **Nuevos requerimientos**:
- `prices` - Array en la **raíz de la recepción** (no dentro de cada palet)
- `pallets[].boxes[].product.id` - Cada caja debe tener su producto
- `pallets[].boxes[].lot` - Cada caja puede tener su lote (opcional)

---

## 📦 Estructura del Request

### Estructura ANTES (❌ NO VÁLIDA)

```json
{
  "supplier": { "id": 1 },
  "date": "2025-01-15",
  "pallets": [
    {
      "product": { "id": 5 },        // ❌ ELIMINADO
      "price": 12.50,                // ❌ ELIMINADO
      "lot": "LOT-001",              // ❌ ELIMINADO
      "boxes": [
        {
          "gs1128": "GS1-001",
          "grossWeight": 25.5,
          "netWeight": 25.0
        }
      ],
      "prices": [                    // ❌ ELIMINADO (estaba dentro del palet)
        {
          "product": { "id": 5 },
          "lot": "LOT-001",
          "price": 12.50
        }
      ]
    }
  ]
}
```

### Estructura AHORA (✅ VÁLIDA)

```json
{
  "supplier": { "id": 1 },
  "date": "2025-01-15",
  "notes": "Recepción de prueba",
  "prices": [                         // ✅ EN LA RAÍZ (compartido por todos los palets)
    {
      "product": { "id": 5 },
      "lot": "LOT-A",
      "price": 12.50
    },
    {
      "product": { "id": 5 },
      "lot": "LOT-B",
      "price": 13.00
    },
    {
      "product": { "id": 6 },
      "lot": "LOT-C",
      "price": 15.00
    }
  ],
  "pallets": [
    {
      "observations": "Palet 1",
      "store": { "id": 1 },          // Opcional
      "boxes": [
        {
          "product": { "id": 5 },     // ✅ REQUERIDO: Producto de la caja
          "lot": "LOT-A",             // ✅ Opcional: Si no se proporciona, se genera automáticamente
          "gs1128": "GS1-001",
          "grossWeight": 25.5,
          "netWeight": 25.0
        },
        {
          "product": { "id": 5 },
          "lot": "LOT-B",
          "gs1128": "GS1-002",
          "grossWeight": 25.5,
          "netWeight": 25.0
        }
      ]
    },
    {
      "observations": "Palet 2",
      "boxes": [
        {
          "product": { "id": 5 },
          "lot": "LOT-A",             // ← Mismo producto+lote que Palet 1
          "gs1128": "GS1-003",
          "grossWeight": 25.5,
          "netWeight": 25.0
        },
        {
          "product": { "id": 6 },
          "lot": "LOT-C",
          "gs1128": "GS1-004",
          "grossWeight": 30.0,
          "netWeight": 29.5
        }
      ]
    }
  ]
}
```

**Nota importante**: El precio para producto 5 + LOT-A solo aparece **una vez** en `prices`, aunque aparezca en múltiples palets.

---

## 📝 Validación de Campos

### Campos Requeridos

| Campo | Ubicación | Descripción |
|-------|-----------|-------------|
| `prices` | Raíz | Array de precios (compartido por todos los palets) |
| `prices[].product.id` | Raíz | ID del producto |
| `prices[].lot` | Raíz | Lote |
| `prices[].price` | Raíz | Precio por kg (≥ 0) |
| `pallets[].boxes` | Palet | Array con al menos 1 caja |
| `pallets[].boxes[].product.id` | Caja | ID del producto (requerido en cada caja) |
| `pallets[].boxes[].gs1128` | Caja | Código GS1-128 |
| `pallets[].boxes[].grossWeight` | Caja | Peso bruto (numérico) |
| `pallets[].boxes[].netWeight` | Caja | Peso neto (numérico) |

### Campos Opcionales

| Campo | Ubicación | Descripción |
|-------|-----------|-------------|
| `pallets[].observations` | Palet | Observaciones del palet |
| `pallets[].store.id` | Palet | ID del almacén (si se proporciona, el palet se crea como almacenado) |
| `pallets[].boxes[].lot` | Caja | Lote de la caja (si no se proporciona, se genera automáticamente) |

---

## 🔄 Actualizar Recepción

La estructura es **idéntica** a la de creación, pero con campos adicionales para identificar elementos existentes:

```json
{
  "supplier": { "id": 1 },
  "date": "2025-01-15",
  "prices": [
    {
      "product": { "id": 5 },
      "lot": "LOT-A",
      "price": 12.50
    }
  ],
  "pallets": [
    {
      "id": 10,                       // ID del palet existente (opcional)
      "observations": "Palet 1",
      "store": { "id": 1 },
      "boxes": [
        {
          "id": 100,                  // ID de la caja existente (opcional)
          "product": { "id": 5 },
          "lot": "LOT-A",
          "gs1128": "GS1-001",
          "grossWeight": 25.5,
          "netWeight": 25.0
        }
      ]
    }
  ]
}
```

**Comportamiento**:
- Si un palet tiene `id` y existe → se actualiza
- Si un palet no tiene `id` o no existe → se crea uno nuevo
- Si una caja tiene `id` y existe → se actualiza
- Si una caja no tiene `id` o no existe → se crea una nueva
- Si un palet/caja existente no está en el request → se elimina

---

## 💻 Implementación en Frontend

### Paso 1: Recolectar Todas las Cajas

```javascript
// Recorrer todos los palets y todas sus cajas
const allBoxes = [];
pallets.forEach(pallet => {
  pallet.boxes.forEach(box => {
    allBoxes.push({
      productId: box.product.id,
      lot: box.lot || null
    });
  });
});
```

### Paso 2: Extraer Combinaciones Únicas de Producto+Lote

```javascript
// Crear un Set con combinaciones únicas
const uniqueProductLots = new Set();
allBoxes.forEach(box => {
  const key = `${box.productId}_${box.lot || 'AUTO'}`;
  uniqueProductLots.add(key);
});

// Convertir a array de objetos
const prices = Array.from(uniqueProductLots).map(key => {
  const [productId, lot] = key.split('_');
  return {
    product: { id: parseInt(productId) },
    lot: lot === 'AUTO' ? null : lot,
    price: null // Se debe obtener del formulario o histórico
  };
});
```

### Paso 3: Construir el Request

```javascript
const request = {
  supplier: { id: supplierId },
  date: date,
  notes: notes,
  prices: prices,  // ← En la raíz
  pallets: pallets.map(pallet => ({
    observations: pallet.observations,
    store: pallet.store,
    boxes: pallet.boxes.map(box => ({
      product: { id: box.product.id },
      lot: box.lot,
      gs1128: box.gs1128,
      grossWeight: box.grossWeight,
      netWeight: box.netWeight
    }))
  }))
};
```

### Ejemplo Completo (React/Vue)

```javascript
// Función para construir el request
function buildReceptionRequest(formData) {
  const { supplier, date, notes, pallets } = formData;
  
  // 1. Extraer todas las combinaciones únicas de producto+lote
  const productLotMap = new Map();
  
  pallets.forEach(pallet => {
    pallet.boxes.forEach(box => {
      const productId = box.product.id;
      const lot = box.lot || 'AUTO';
      const key = `${productId}_${lot}`;
      
      if (!productLotMap.has(key)) {
        productLotMap.set(key, {
          product: { id: productId },
          lot: lot === 'AUTO' ? null : lot,
          price: box.price || null  // Obtener del formulario
        });
      }
    });
  });
  
  // 2. Convertir Map a Array
  const prices = Array.from(productLotMap.values());
  
  // 3. Construir palets sin prices
  const palletsData = pallets.map(pallet => ({
    observations: pallet.observations,
    store: pallet.store,
    boxes: pallet.boxes.map(box => ({
      product: { id: box.product.id },
      lot: box.lot,
      gs1128: box.gs1128,
      grossWeight: box.grossWeight,
      netWeight: box.netWeight
    }))
  }));
  
  // 4. Construir request final
  return {
    supplier: { id: supplier.id },
    date: date,
    notes: notes,
    prices: prices,  // ← En la raíz
    pallets: palletsData
  };
}
```

---

## 🎯 Casos de Uso

### Caso 1: Un solo palet con un producto y lote

```json
{
  "prices": [
    { "product": { "id": 5 }, "lot": "LOT-001", "price": 12.50 }
  ],
  "pallets": [
    {
      "boxes": [
        { "product": { "id": 5 }, "lot": "LOT-001", ... }
      ]
    }
  ]
}
```

### Caso 2: Múltiples palets compartiendo producto+lote

```json
{
  "prices": [
    { "product": { "id": 5 }, "lot": "LOT-A", "price": 12.50 }  // ← Solo una vez
  ],
  "pallets": [
    {
      "boxes": [
        { "product": { "id": 5 }, "lot": "LOT-A", ... }  // Palet 1
      ]
    },
    {
      "boxes": [
        { "product": { "id": 5 }, "lot": "LOT-A", ... }  // Palet 2 - mismo producto+lote
      ]
    }
  ]
}
```

### Caso 3: Palet con múltiples productos y lotes

```json
{
  "prices": [
    { "product": { "id": 5 }, "lot": "LOT-A", "price": 12.50 },
    { "product": { "id": 5 }, "lot": "LOT-B", "price": 13.00 },
    { "product": { "id": 6 }, "lot": "LOT-C", "price": 15.00 }
  ],
  "pallets": [
    {
      "boxes": [
        { "product": { "id": 5 }, "lot": "LOT-A", ... },
        { "product": { "id": 5 }, "lot": "LOT-B", ... },
        { "product": { "id": 6 }, "lot": "LOT-C", ... }
      ]
    }
  ]
}
```

---

## ⚠️ Validaciones Importantes

### 1. Validación de Precios

**El frontend debe validar**:
- Todas las combinaciones producto+lote de todas las cajas deben tener su precio en `prices`
- No debe haber duplicados en `prices` (misma combinación producto+lote)
- Si falta un precio, el backend intentará buscarlo del histórico, pero es mejor proporcionarlo

**Ejemplo de validación**:
```javascript
function validatePrices(prices, pallets) {
  const requiredKeys = new Set();
  
  // Extraer todas las combinaciones requeridas
  pallets.forEach(pallet => {
    pallet.boxes.forEach(box => {
      const key = `${box.product.id}_${box.lot || 'AUTO'}`;
      requiredKeys.add(key);
    });
  });
  
  // Verificar que todas tengan precio
  const priceKeys = new Set();
  prices.forEach(price => {
    const key = `${price.product.id}_${price.lot || 'AUTO'}`;
    priceKeys.add(key);
  });
  
  // Verificar que no falten
  const missing = Array.from(requiredKeys).filter(key => !priceKeys.has(key));
  if (missing.length > 0) {
    throw new Error(`Faltan precios para: ${missing.join(', ')}`);
  }
  
  // Verificar duplicados
  if (priceKeys.size !== prices.length) {
    throw new Error('Hay precios duplicados en el array prices');
  }
}
```

### 2. Generación de Lotes

- Si una caja no tiene `lot`, el backend lo genera automáticamente
- Es recomendable siempre proporcionar el lote explícitamente
- El formato generado es: `YYYYMMDD-{reception_id}-{product_id}`

---

## 🔧 Migración desde Código Antiguo

### Cambio 1: Mover product.id de palet a caja

```javascript
// ❌ Antes
pallet.product.id

// ✅ Ahora
box.product.id
```

### Cambio 2: Mover lot de palet a caja

```javascript
// ❌ Antes
pallet.lot

// ✅ Ahora
box.lot
```

### Cambio 3: Mover prices a la raíz

```javascript
// ❌ Antes
pallet.prices = [
  { product: { id: 5 }, lot: "LOT-A", price: 12.50 }
]

// ✅ Ahora
reception.prices = [
  { product: { id: 5 }, lot: "LOT-A", price: 12.50 }
]

// Y eliminar prices de cada palet
pallet.prices = undefined;  // o simplemente no incluirlo
```

### Cambio 4: Agrupar precios únicos

```javascript
// ✅ Función helper
function extractUniquePrices(pallets) {
  const priceMap = new Map();
  
  pallets.forEach(pallet => {
    pallet.boxes.forEach(box => {
      const key = `${box.product.id}_${box.lot || 'AUTO'}`;
      if (!priceMap.has(key)) {
        priceMap.set(key, {
          product: { id: box.product.id },
          lot: box.lot,
          price: box.price  // Obtener del formulario
        });
      }
    });
  });
  
  return Array.from(priceMap.values());
}

// Uso
const prices = extractUniquePrices(pallets);
const request = {
  ...otherFields,
  prices: prices,  // ← En la raíz
  pallets: pallets.map(p => ({
    ...p,
    prices: undefined  // ← Eliminar de cada palet
  }))
};
```

---

## 📊 Ejemplo Completo de Request

### Escenario: 2 palets, 3 productos diferentes, 2 lotes compartidos

```json
{
  "supplier": { "id": 1 },
  "date": "2025-01-15",
  "notes": "Recepción con múltiples palets",
  "prices": [
    {
      "product": { "id": 5 },
      "lot": "LOT-A",
      "price": 12.50
    },
    {
      "product": { "id": 5 },
      "lot": "LOT-B",
      "price": 13.00
    },
    {
      "product": { "id": 6 },
      "lot": "LOT-C",
      "price": 15.00
    }
  ],
  "pallets": [
    {
      "observations": "Palet 1",
      "store": { "id": 1 },
      "boxes": [
        {
          "product": { "id": 5 },
          "lot": "LOT-A",
          "gs1128": "GS1-001",
          "grossWeight": 25.5,
          "netWeight": 25.0
        },
        {
          "product": { "id": 5 },
          "lot": "LOT-B",
          "gs1128": "GS1-002",
          "grossWeight": 25.5,
          "netWeight": 25.0
        }
      ]
    },
    {
      "observations": "Palet 2",
      "boxes": [
        {
          "product": { "id": 5 },
          "lot": "LOT-A",  // ← Mismo producto+lote que Palet 1
          "gs1128": "GS1-003",
          "grossWeight": 25.5,
          "netWeight": 25.0
        },
        {
          "product": { "id": 6 },
          "lot": "LOT-C",
          "gs1128": "GS1-004",
          "grossWeight": 30.0,
          "netWeight": 29.5
        }
      ]
    }
  ]
}
```

**Nota**: El precio para producto 5 + LOT-A aparece **una sola vez** en `prices`, aunque aparezca en ambos palets.

---

## ✅ Checklist de Implementación

- [ ] Eliminar `pallets[].product.id` del código
- [ ] Eliminar `pallets[].price` del código
- [ ] Eliminar `pallets[].lot` del código
- [ ] Eliminar `pallets[].prices` del código
- [ ] Agregar `pallets[].boxes[].product.id` en cada caja
- [ ] Agregar `pallets[].boxes[].lot` en cada caja (opcional)
- [ ] Crear función para extraer combinaciones únicas de producto+lote
- [ ] Mover `prices` a la raíz de la recepción
- [ ] Validar que todas las combinaciones producto+lote tengan precio
- [ ] Actualizar formularios para capturar precios por producto+lote
- [ ] Actualizar lógica de edición para mantener la misma estructura
- [ ] Probar con múltiples palets compartiendo productos y lotes

---

## 🐛 Errores Comunes

### Error 1: Precio duplicado en múltiples palets

```json
// ❌ INCORRECTO
{
  "pallets": [
    {
      "prices": [{ "product": { "id": 5 }, "lot": "LOT-A", "price": 12.50 }]
    },
    {
      "prices": [{ "product": { "id": 5 }, "lot": "LOT-A", "price": 12.50 }]  // ← Duplicado
    }
  ]
}

// ✅ CORRECTO
{
  "prices": [{ "product": { "id": 5 }, "lot": "LOT-A", "price": 12.50 }],  // ← Una sola vez
  "pallets": [...]
}
```

### Error 2: Precio faltante para una combinación

```json
// ❌ INCORRECTO - Falta precio para producto 6 + LOT-C
{
  "prices": [
    { "product": { "id": 5 }, "lot": "LOT-A", "price": 12.50 }
  ],
  "pallets": [
    {
      "boxes": [
        { "product": { "id": 5 }, "lot": "LOT-A", ... },
        { "product": { "id": 6 }, "lot": "LOT-C", ... }  // ← Sin precio
      ]
    }
  ]
}
```

**Solución**: El backend intentará buscar el precio del histórico, pero es mejor proporcionarlo.

---

## 📚 Referencias

- [Guía Frontend Completa](./63-Guia-Frontend-Recepciones-Palets.md)
- [Documentación de Recepciones](./60-Recepciones-Materia-Prima.md)
- [Guía Backend de Edición](./65-Guia-Backend-Edicion-Recepciones.md)

---

## 📞 Soporte

Si tienes dudas sobre la implementación:
1. Revisa los ejemplos en este documento
2. Consulta la documentación completa de la API
3. Verifica que todas las combinaciones producto+lote tengan precio

---

**Última actualización**: Diciembre 2025  
**Versión**: 2.0

