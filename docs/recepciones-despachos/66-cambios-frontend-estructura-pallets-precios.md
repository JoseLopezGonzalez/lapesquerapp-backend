# Cambios Frontend: Nueva Estructura de Pallets y Precios

## 📋 Resumen

Este documento describe los **cambios en la estructura del request** para crear y editar recepciones en modo PALLETS. Estos cambios permiten mayor flexibilidad: cada caja puede tener su propio producto y lote, y los precios se especifican por producto+lote.

**Fecha de implementación**: Diciembre 2025

---

## ⚠️ CAMBIOS IMPORTANTES

### ❌ Campos Eliminados (ya no se usan)

- `pallets[].product.id` - **ELIMINADO** (ahora cada caja tiene su producto)
- `pallets[].price` - **ELIMINADO** (ahora se usa el array `prices` en la raíz)
- `pallets[].lot` - **ELIMINADO** (ahora cada caja tiene su lote)
- `pallets[].prices` - **ELIMINADO** (ahora está en la raíz de la recepción)

### ✅ Campos Nuevos/Modificados

- `pallets[].boxes[].product.id` - **NUEVO** (requerido en cada caja)
- `pallets[].boxes[].lot` - **MODIFICADO** (ahora es por caja, no por palet)
- `prices` - **NUEVO** (array de precios en la raíz de la recepción, compartido por todos los palets)

---

## 📦 Nueva Estructura del Request

### Crear Recepción (POST `/api/v2/raw-material-receptions`)

**Antes** (estructura antigua - ❌ NO VÁLIDA):
```json
{
  "supplier": { "id": 1 },
  "date": "2025-01-15",
  "pallets": [
    {
      "product": { "id": 5 },        // ❌ ELIMINADO
      "price": 12.50,                // ❌ ELIMINADO
      "lot": "LOT-2025-001",         // ❌ ELIMINADO
      "boxes": [
        {
          "gs1128": "GS1-001",
          "grossWeight": 25.5,
          "netWeight": 25.0
        }
      ]
    }
  ]
}
```

**Ahora** (estructura nueva - ✅ VÁLIDA):
```json
{
  "supplier": { "id": 1 },
  "date": "2025-01-15",
  "notes": "Recepción de prueba",
  "prices": [                         // ✅ REQUERIDO: Array de precios en la RAÍZ (compartido por todos los palets)
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
      "store": { "id": 1 },          // Opcional: si se proporciona, el palet se crea como almacenado
      "boxes": [
        {
          "product": { "id": 5 },     // ✅ REQUERIDO: Producto de la caja
          "lot": "LOT-A",             // ✅ Opcional: Si no se proporciona, se genera automáticamente
          "gs1128": "GS1-001",
          "grossWeight": 25.5,
          "netWeight": 25.0
        },
        {
          "product": { "id": 5 },     // ✅ Mismo producto, diferente lote
          "lot": "LOT-B",
          "gs1128": "GS1-002",
          "grossWeight": 25.5,
          "netWeight": 25.0
        },
        {
          "product": { "id": 6 },     // ✅ Diferente producto en el mismo palet
          "lot": "LOT-C",
          "gs1128": "GS1-003",
          "grossWeight": 30.0,
          "netWeight": 29.5
        }
      ]
    },
    {
      "observations": "Palet 2",
      "boxes": [
        {
          "product": { "id": 5 },     // ✅ Mismo producto+lote que Palet 1
          "lot": "LOT-A",
          "gs1128": "GS1-004",
          "grossWeight": 25.5,
          "netWeight": 25.0
        }
      ]
    }
  ]
}
```

---

## 📝 Validación de Campos

### Campos Requeridos

- `prices` - Array de precios en la raíz de la recepción (requerido si hay palets)
- `prices[].product.id` - ID del producto (requerido)
- `prices[].lot` - Lote (requerido)
- `prices[].price` - Precio por kg (requerido, ≥ 0)
- `pallets[].boxes` - Array con al menos 1 caja
- `pallets[].boxes[].product.id` - ID del producto (requerido en cada caja)
- `pallets[].boxes[].gs1128` - Código GS1-128 (requerido)
- `pallets[].boxes[].grossWeight` - Peso bruto (requerido, numérico)
- `pallets[].boxes[].netWeight` - Peso neto (requerido, numérico)

### Campos Opcionales

- `pallets[].observations` - Observaciones del palet
- `pallets[].store.id` - ID del almacén (si se proporciona, el palet se crea como almacenado)
- `pallets[].boxes[].lot` - Lote de la caja (si no se proporciona, se genera automáticamente)

---

## 🔄 Actualizar Recepción (PUT `/api/v2/raw-material-receptions/{id}`)

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
      "id": 10,                       // ID del palet existente (opcional, si no existe se crea uno nuevo)
      "observations": "Palet 1",
      "store": { "id": 1 },
      "boxes": [
        {
          "id": 100,                  // ID de la caja existente (opcional, si no existe se crea una nueva)
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

## 💡 Ventajas de la Nueva Estructura

### 1. **Máxima Flexibilidad**
- Un palet puede contener múltiples productos
- Un palet puede contener múltiples lotes del mismo producto
- Cada caja puede tener su propio producto y lote

### 2. **Precios Granulares y Compartidos**
- Precios diferentes para el mismo producto con diferentes lotes
- Precios diferentes para diferentes productos
- El precio se especifica **una vez por combinación producto+lote** en el array `prices` en la raíz
- **Ventaja**: Si dos palets comparten el mismo producto+lote, solo se especifica el precio una vez
- Los precios son compartidos por todos los palets de la recepción

### 3. **Consistencia**
- El lote se toma directamente de las cajas (no se inventa)
- Los costes se calculan correctamente por producto+lote
- Las líneas de recepción reflejan exactamente los lotes de las cajas

---

## 🎯 Ejemplos de Uso

### Ejemplo 1: Palet con un solo producto y un solo lote

```json
{
  "prices": [
    {
      "product": { "id": 5 },
      "lot": "LOT-001",
      "price": 12.50
    }
  ],
  "pallets": [
    {
      "observations": "Palet simple",
      "boxes": [
        {
          "product": { "id": 5 },
          "lot": "LOT-001",
          "gs1128": "GS1-001",
          "grossWeight": 25.5,
          "netWeight": 25.0
        },
        {
          "product": { "id": 5 },
          "lot": "LOT-001",
          "gs1128": "GS1-002",
          "grossWeight": 25.5,
          "netWeight": 25.0
        }
      ]
    }
  ]
}
```

### Ejemplo 2: Palet con múltiples lotes del mismo producto

```json
{
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
    }
  ],
  "pallets": [
    {
      "observations": "Palet con múltiples lotes",
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
    }
  ]
}
```

### Ejemplo 3: Múltiples palets compartiendo productos y lotes

```json
{
  "prices": [
    {
      "product": { "id": 5 },
      "lot": "LOT-A",
      "price": 12.50
    },
    {
      "product": { "id": 6 },
      "lot": "LOT-B",
      "price": 15.00
    }
  ],
  "pallets": [
    {
      "observations": "Palet 1",
      "boxes": [
        {
          "product": { "id": 5 },
          "lot": "LOT-A",
          "gs1128": "GS1-001",
          "grossWeight": 25.5,
          "netWeight": 25.0
        },
        {
          "product": { "id": 6 },
          "lot": "LOT-B",
          "gs1128": "GS1-002",
          "grossWeight": 30.0,
          "netWeight": 29.5
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
        }
      ]
    }
  ]
}
```

**Ventaja**: El precio para producto 5 + LOT-A solo se especifica una vez en `prices`, aunque aparezca en múltiples palets.

---

## ⚠️ Notas Importantes

### 1. **Validación de Precios**
- Todas las combinaciones producto+lote en **todas las cajas de todos los palets** deben tener su precio correspondiente en `prices` (en la raíz)
- Si falta un precio para una combinación, el backend intentará buscarlo del histórico
- Si no se encuentra en el histórico, el precio será `null` y no se calcularán costes
- **Importante**: El array `prices` debe contener todas las combinaciones únicas de producto+lote que aparecen en cualquier palet

### 2. **Generación Automática de Lotes**
- Si una caja no tiene `lot`, se genera automáticamente con el formato: `YYYYMMDD-{reception_id}-{product_id}`
- Es recomendable siempre proporcionar el lote explícitamente

### 3. **Almacenamiento**
- Si se proporciona `store.id`, el palet se crea con estado "almacenado"
- Si no se proporciona, el palet se crea con estado "registrado"

### 4. **Líneas de Recepción**
- Se crean automáticamente agrupando por producto+lote
- Cada línea de recepción tiene el `lot` correspondiente
- El peso neto es la suma de todas las cajas con el mismo producto+lote

---

## 🔧 Migración desde la Estructura Antigua

Si tienes código que usa la estructura antigua, necesitas:

1. **Mover `product.id` de palet a cada caja**:
   ```javascript
   // Antes
   pallet.product.id
   
   // Ahora
   box.product.id
   ```

2. **Mover `lot` de palet a cada caja**:
   ```javascript
   // Antes
   pallet.lot
   
   // Ahora
   box.lot
   ```

3. **Mover `prices` a la raíz de la recepción**:
   ```javascript
   // Antes (dentro de cada palet)
   pallet.prices = [...]
   
   // Ahora (en la raíz)
   reception.prices = [
     {
       product: { id: productId },
       lot: lot,
       price: price
     }
   ]
   ```

4. **Agrupar precios únicos por producto+lote**:
   - Recorrer todos los palets y todas sus cajas
   - Extraer todas las combinaciones únicas de producto+lote
   - Crear un solo array `prices` en la raíz con todas las combinaciones únicas
   - Si dos palets comparten el mismo producto+lote, solo aparece una vez en `prices`

---

## 📚 Referencias

- [Guía Frontend Completa](./63-Guia-Frontend-Recepciones-Palets.md)
- [Documentación de Recepciones](./60-Recepciones-Materia-Prima.md)
- [Guía Backend de Edición](./65-Guia-Backend-Edicion-Recepciones.md)

---

**Última actualización**: Diciembre 2025

