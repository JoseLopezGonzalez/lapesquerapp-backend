# Guía Frontend: Edición de Cajas Disponibles en Recepciones

## 📋 Resumen Ejecutivo

Este documento describe los cambios en el backend que permiten **editar recepciones en modo PALLETS cuando hay cajas siendo utilizadas en producción**. El frontend debe actualizar su implementación para soportar esta nueva funcionalidad.

**Fecha de implementación**: 2025-01-XX  
**Versión API**: v2  
**Endpoint afectado**: `PUT /api/v2/raw-material-receptions/{id}`  
**Alcance**: Solo recepciones con `creation_mode = 'pallets'`

---

## 🎯 ¿Qué cambió?

### Antes

- ❌ **No se podía editar** una recepción si alguna caja estaba siendo usada en producción
- ❌ El endpoint retornaba error si había cajas usadas

### Ahora

- ✅ **Se puede editar parcialmente** una recepción cuando hay cajas usadas
- ✅ Solo se pueden modificar las **cajas disponibles** (no usadas en producción)
- ✅ Los **totales por producto** deben mantenerse exactamente iguales
- ✅ El backend ajusta automáticamente diferencias pequeñas por redondeos (≤ 0.01 kg)

---

## 🔍 Información que el Frontend debe conocer

### 1. Estado de Edición de Recepciones

El atributo `can_edit` ahora puede ser `true` incluso cuando hay cajas usadas en producción.

**Antes**:
```json
{
  "id": 1,
  "can_edit": false,
  "cannot_edit_reason": "La caja #42 está siendo usada en producción"
}
```

**Ahora** (con cajas usadas):
```json
{
  "id": 1,
  "can_edit": true,
  "cannot_edit_reason": null
}
```

**Nota**: `can_edit` solo será `false` si algún palet está vinculado a un pedido.

---

### 2. Identificación de Cajas Disponibles vs Usadas

Cada caja en la respuesta del API incluye información sobre su disponibilidad:

```json
{
  "id": 42,
  "netWeight": 25.5,
  "isAvailable": true,  // ← Indica si está disponible
  "production": null    // ← null si está disponible, o info de producción si está usada
}
```

**Caja disponible**:
```json
{
  "id": 42,
  "isAvailable": true,
  "production": null
}
```

**Caja usada**:
```json
{
  "id": 43,
  "isAvailable": false,
  "production": {
    "id": 10,
    "lot": "LOT-001"
  }
}
```

---

## ✅ Qué se permite hacer

### 1. Modificar todos los campos de cajas disponibles

**Permitido**: Cambiar cualquier campo de cajas que tienen `isAvailable: true`:
- `product.id` (producto)
- `lot` (lote)
- `netWeight` (peso neto)
- `grossWeight` (peso bruto)
- `gs1128` (código GS1-128)

```json
{
  "pallets": [
    {
      "id": 15,
      "boxes": [
        { 
          "id": 1, 
          "product": { "id": 5 },      // ← Caja disponible, se puede modificar
          "lot": "LOT-NEW-001",          // ← Caja disponible, se puede modificar
          "netWeight": 30.0,             // ← Caja disponible, se puede modificar
          "grossWeight": 32.0,           // ← Caja disponible, se puede modificar
          "gs1128": "GS1-NEW-CODE-001"  // ← Caja disponible, se puede modificar
        },
        { 
          "id": 2, 
          "product": { "id": 6 },       // ← Caja disponible, se puede modificar
          "lot": "LOT-NEW-002",         // ← Caja disponible, se puede modificar
          "netWeight": 25.0,             // ← Caja disponible, se puede modificar
          "grossWeight": 27.0,           // ← Caja disponible, se puede modificar
          "gs1128": "GS1-NEW-CODE-002"  // ← Caja disponible, se puede modificar
        }
      ]
    }
  ]
}
```

**Nota**: Todos los campos son modificables siempre que la caja no esté siendo usada en producción (`isAvailable: true`).

### 2. Reorganizar pesos entre cajas disponibles

**Permitido**: Redistribuir peso entre cajas disponibles del mismo producto, siempre que el total se mantenga igual.

**Ejemplo**: Si tienes 3 cajas disponibles con total 100 kg:
- Caja 1: 30 kg → 35 kg (+5)
- Caja 2: 35 kg → 30 kg (-5)
- Caja 3: 35 kg → 35 kg (sin cambio)
- **Total**: 100 kg (se mantiene igual) ✅

### 3. Incluir cajas usadas en el request (sin modificar)

**Permitido**: Incluir cajas usadas en el request con el mismo peso que tienen actualmente.

```json
{
  "pallets": [
    {
      "id": 15,
      "boxes": [
        { "id": 1, "netWeight": 10.0 },  // ← Caja usada, mismo peso (OK)
        { "id": 2, "netWeight": 30.0 }    // ← Caja disponible, modificada
      ]
    }
  ]
}
```

**Nota**: Si intentas cambiar el peso de una caja usada, el backend retornará error.

---

## ❌ Qué NO se permite hacer

### 1. Modificar cajas usadas

**No permitido**: Cambiar cualquier campo de una caja que tiene `isAvailable: false`

**Error esperado**:
```json
{
  "message": "No se puede modificar la caja #43: está siendo usada en producción"
}
```

### 2. Modificar campos de cajas usadas en producción

**No permitido**: Cambiar **cualquier campo** de una caja que tiene `isAvailable: false` (está siendo usada en producción)

**Errores esperados** (solo para cajas usadas):
- `"No se puede modificar el producto de la caja #42: está siendo usada en producción"`
- `"No se puede modificar el lote de la caja #42: está siendo usada en producción"`
- `"No se puede modificar el peso neto de la caja #42: está siendo usada en producción"`
- `"No se puede modificar el peso bruto de la caja #42: está siendo usada en producción"`
- `"No se puede modificar el GS1-128 de la caja #42: está siendo usada en producción"`

**Nota**: Si la caja está disponible (`isAvailable: true`), **todos los campos son modificables**.

### 3. Crear nuevas cajas cuando hay cajas usadas

**No permitido**: Agregar nuevas cajas (sin `id`) cuando hay cajas usadas en algún palet

**Error esperado**:
```json
{
  "message": "No se pueden crear nuevas cajas cuando hay cajas siendo usadas en producción"
}
```

### 4. Eliminar cajas usadas

**No permitido**: Omitir cajas usadas del request (intentar eliminarlas)

**Error esperado**:
```json
{
  "message": "No se puede eliminar la caja #43: está siendo usada en producción"
}
```

### 5. Eliminar palets con cajas usadas

**No permitido**: Omitir palets que tienen cajas usadas del request

**Error esperado**:
```json
{
  "message": "No se puede eliminar el palet #15: tiene cajas siendo usadas en producción"
}
```

### 6. Cambiar totales por producto

**No permitido**: Modificar pesos de manera que el total por producto cambie más de 0.01 kg

**Error esperado**:
```json
{
  "message": "El total del producto 5 con lote LOT-001 ha cambiado. Original: 100.0 kg, Nuevo: 95.0 kg, Diferencia: 5.0 kg"
}
```

### 7. Agregar nuevos productos cuando hay cajas usadas

**No permitido**: Agregar cajas de productos que no existían antes en la recepción

**Error esperado**:
```json
{
  "message": "Se ha agregado un nuevo producto 6 con lote LOT-002. No se pueden agregar nuevos productos cuando hay cajas usadas."
}
```

### 8. Eliminar todos los productos cuando hay cajas usadas

**No permitido**: Eliminar todos los productos de un tipo cuando hay cajas usadas de ese producto

**Error esperado**:
```json
{
  "message": "El producto 5 con lote LOT-001 ya no tiene cajas. No se pueden eliminar todos los productos cuando hay cajas usadas."
}
```

### 9. Modificar precios cuando hay cajas usadas

**No permitido**: Cambiar precios en el array `prices` cuando hay cajas usadas

**Nota**: El backend ignora los precios nuevos y mantiene los originales cuando hay cajas usadas.

---

## 📊 Validaciones que el Backend realiza

### 1. Validación de cajas usadas

El backend verifica que:
- Si una caja tiene `id` y está en el request, no puede tener `productionInputs`
- Si una caja usada está en el request, su peso debe ser exactamente igual al original

### 2. Validación de campos modificables

El backend verifica que:
- **Si la caja está disponible** (`isAvailable: true`): Se pueden modificar todos los campos (`product.id`, `lot`, `netWeight`, `grossWeight`, `gs1128`)
- **Si la caja está usada** (`isAvailable: false`): No se puede modificar ningún campo. Todos los valores deben ser exactamente iguales a los originales

### 3. Validación de totales

El backend calcula:
1. Totales originales por producto+lote (desde `RawMaterialReceptionProduct`)
2. Totales nuevos por producto+lote (sumando todas las cajas: disponibles modificadas + usadas)
3. Compara ambos totales con tolerancia de 0.01 kg

**Si la diferencia es > 0.01 kg**: Error
**Si la diferencia es ≤ 0.01 kg**: Ajuste automático en la última caja disponible

### 4. Validación de creación/eliminación

El backend verifica:
- No se pueden crear nuevas cajas (sin `id`) si hay cajas usadas
- No se pueden eliminar cajas usadas (no están en el request)
- No se pueden eliminar palets con cajas usadas

---

## 🎨 Consideraciones de UI/UX

### 1. Indicadores visuales

**Recomendación**: Mostrar claramente qué cajas están disponibles y cuáles están usadas:

```
Palet #15
├─ Caja #1: 10.0 kg [🔒 USADA EN PRODUCCIÓN - No editable]
├─ Caja #2: 10.0 kg [🔒 USADA EN PRODUCCIÓN - No editable]
├─ Caja #3: 10.0 kg [🔒 USADA EN PRODUCCIÓN - No editable]
├─ Caja #4: 25.0 kg [✏️ DISPONIBLE - Editable]
├─ Caja #5: 30.0 kg [✏️ DISPONIBLE - Editable]
└─ ...

Total: 250.0 kg
  - Usadas: 30.0 kg (no editable)
  - Disponibles: 220.0 kg (editable)
```

### 2. Campos bloqueados

**Recomendación**: Mostrar campos de cajas usadas como read-only o deshabilitados:

- `netWeight`: Read-only
- `product`: Read-only
- `lot`: Read-only
- `gs1128`: Read-only
- `grossWeight`: Read-only

**Para cajas disponibles** (`isAvailable: true`):
- `netWeight`: Editable ✅
- `gs1128`: Editable ✅
- `product`: Editable ✅
- `lot`: Editable ✅
- `grossWeight`: Editable ✅

### 3. Validación en tiempo real

**Recomendación**: Validar que los totales coincidan mientras el usuario edita:

```
Total original: 250.0 kg
Total actual: 248.5 kg
Diferencia: -1.5 kg ❌

[Mensaje]: "Los totales deben mantenerse iguales. Diferencia: -1.5 kg"
```

### 4. Mensajes de error claros

**Recomendación**: Mostrar mensajes de error específicos y accionables:

```
❌ Error: "No se puede modificar la caja #43: está siendo usada en producción"
   → Explicar: "Esta caja está siendo utilizada en el proceso de producción #10 (Lote: LOT-001)"
```

### 5. Confirmación antes de guardar

**Recomendación**: Mostrar resumen de cambios antes de guardar:

```
Resumen de cambios:
- Caja #4: 25.0 kg → 30.0 kg (+5.0 kg)
- Caja #5: 30.0 kg → 25.0 kg (-5.0 kg)
- Cajas usadas: Sin cambios (3 cajas, 30.0 kg total)

Total: 250.0 kg (sin cambios) ✅

¿Confirmar cambios?
```

---

## 📝 Ejemplos de Casos de Uso

### Caso 1: Reorganizar pesos para cuadrar cantidad específica

**Escenario**: Necesitas gastar exactamente 100 kg en producción, pero las cajas disponibles suman 105 kg.

**Solución**: Reorganizar los pesos de las cajas disponibles para que sumen exactamente 100 kg.

**Request**:
```json
{
  "supplier": { "id": 1 },
  "date": "2025-01-15",
  "pallets": [
    {
      "id": 15,
      "boxes": [
        { "id": 1, "netWeight": 10.0 },   // ← Usada (no modificar)
        { "id": 2, "netWeight": 10.0 },   // ← Usada (no modificar)
        { "id": 3, "netWeight": 10.0 },   // ← Usada (no modificar)
        { "id": 4, "netWeight": 30.0 },   // ← Disponible (modificada: era 35.0)
        { "id": 5, "netWeight": 30.0 },   // ← Disponible (modificada: era 35.0)
        { "id": 6, "netWeight": 40.0 }    // ← Disponible (modificada: era 35.0)
      ]
    }
  ],
  "prices": [
    {
      "product": { "id": 5 },
      "lot": "LOT-001",
      "price": 12.50
    }
  ]
}
```

**Validación**:
- Total original: 30 (usadas) + 105 (disponibles) = 135 kg
- Total nuevo: 30 (usadas) + 100 (disponibles) = 130 kg
- ❌ **Error**: Diferencia de 5 kg

**Request corregido**:
```json
{
  "pallets": [
    {
      "id": 15,
      "boxes": [
        { "id": 1, "netWeight": 10.0 },   // ← Usada
        { "id": 2, "netWeight": 10.0 },   // ← Usada
        { "id": 3, "netWeight": 10.0 },   // ← Usada
        { "id": 4, "netWeight": 33.33 },  // ← Disponible (reorganizada)
        { "id": 5, "netWeight": 33.33 },  // ← Disponible (reorganizada)
        { "id": 6, "netWeight": 33.34 }   // ← Disponible (reorganizada, ajuste por redondeo)
      ]
    }
  ]
}
```

**Validación**:
- Total original: 30 + 105 = 135 kg
- Total nuevo: 30 + 100 = 130 kg
- ❌ **Error**: Diferencia de 5 kg

**Solución correcta**: Mantener el total de 135 kg, reorganizar solo las disponibles:

```json
{
  "pallets": [
    {
      "id": 15,
      "boxes": [
        { "id": 1, "netWeight": 10.0 },   // ← Usada
        { "id": 2, "netWeight": 10.0 },   // ← Usada
        { "id": 3, "netWeight": 10.0 },   // ← Usada
        { "id": 4, "netWeight": 35.0 },   // ← Disponible (sin cambio)
        { "id": 5, "netWeight": 35.0 },   // ← Disponible (sin cambio)
        { "id": 6, "netWeight": 35.0 }    // ← Disponible (sin cambio)
      ]
    }
  ]
}
```

---

### Caso 2: Intentar modificar caja usada

**Request**:
```json
{
  "pallets": [
    {
      "id": 15,
      "boxes": [
        { "id": 1, "netWeight": 15.0 }  // ← Caja usada, intento de modificación
      ]
    }
  ]
}
```

**Respuesta del backend**:
```json
{
  "message": "No se puede modificar la caja #1: está siendo usada en producción"
}
```

---

### Caso 3: Intentar crear nueva caja cuando hay usadas

**Request**:
```json
{
  "pallets": [
    {
      "id": 15,
      "boxes": [
        { "id": 1, "netWeight": 10.0 },  // ← Caja usada
        { "netWeight": 25.0 }              // ← Nueva caja (sin id)
      ]
    }
  ]
}
```

**Respuesta del backend**:
```json
{
  "message": "No se pueden crear nuevas cajas cuando hay cajas siendo usadas en producción"
}
```

---

### Caso 4: Ajuste automático de redondeos

**Escenario**: Reorganizas pesos y hay una diferencia pequeña por redondeos.

**Request**:
```json
{
  "pallets": [
    {
      "id": 15,
      "boxes": [
        { "id": 1, "netWeight": 10.0 },   // ← Usada
        { "id": 4, "netWeight": 33.33 },  // ← Disponible
        { "id": 5, "netWeight": 33.33 },  // ← Disponible
        { "id": 6, "netWeight": 33.33 }   // ← Disponible
      ]
    }
  ]
}
```

**Validación**:
- Total original: 10 + 100 = 110 kg
- Total nuevo: 10 + 99.99 = 109.99 kg
- Diferencia: 0.01 kg ✅

**Backend**: Ajusta automáticamente la última caja disponible:
- Caja #6: 33.33 → 33.34 kg

**Resultado**: Total = 110 kg (exacto)

---

## 🔄 Flujo Recomendado

### 1. Cargar recepción

```javascript
GET /api/v2/raw-material-receptions/{id}
```

### 2. Identificar cajas disponibles vs usadas

```javascript
const availableBoxes = reception.pallets
  .flatMap(p => p.boxes)
  .filter(b => b.isAvailable);

const usedBoxes = reception.pallets
  .flatMap(p => p.boxes)
  .filter(b => !b.isAvailable);
```

### 3. Permitir edición solo de cajas disponibles

```javascript
// Mostrar campos editables solo para cajas disponibles
boxes.forEach(box => {
  if (box.isAvailable) {
    // Habilitar edición de netWeight
    enableEdit(box, 'netWeight');
  } else {
    // Mostrar como read-only
    disableEdit(box);
  }
});
```

### 4. Validar totales en tiempo real

```javascript
function validateTotals(originalTotals, currentBoxes) {
  const currentTotals = calculateTotals(currentBoxes);
  
  for (const [key, original] of Object.entries(originalTotals)) {
    const current = currentTotals[key];
    const difference = Math.abs(original - current);
    
    if (difference > 0.01) {
      return {
        valid: false,
        message: `El total del producto ${key} ha cambiado. Diferencia: ${difference} kg`
      };
    }
  }
  
  return { valid: true };
}
```

### 5. Enviar request

```javascript
PUT /api/v2/raw-material-receptions/{id}
{
  "supplier": { "id": 1 },
  "date": "2025-01-15",
  "pallets": [...],
  "prices": [...]
}
```

### 6. Manejar respuesta

```javascript
// Éxito
if (response.status === 200) {
  showSuccess("Recepciones actualizada correctamente");
}

// Error
if (response.status === 422 || response.status === 400) {
  showError(response.data.message);
}
```

---

## ⚠️ Mensajes de Error Comunes

| Error | Causa | Solución |
|-------|-------|----------|
| `"No se puede modificar la caja #X: está siendo usada en producción"` | Intentaste modificar una caja usada | No modificar cajas con `isAvailable: false` |
| `"No se pueden crear nuevas cajas cuando hay cajas siendo usadas en producción"` | Intentaste crear una caja nueva (sin `id`) | Solo modificar cajas existentes disponibles |
| `"No se puede eliminar la caja #X: está siendo usada en producción"` | Omitiste una caja usada del request | Incluir todas las cajas usadas en el request |
| `"El total del producto X con lote Y ha cambiado. Diferencia: Z kg"` | Los totales no coinciden | Ajustar pesos para mantener totales iguales |
| `"No se puede modificar el producto de la caja #X: está siendo usada en producción"` | Intentaste cambiar el producto de una caja usada | Solo se puede modificar el producto de cajas disponibles (`isAvailable: true`) |
| `"No se puede modificar el lote de la caja #X: está siendo usada en producción"` | Intentaste cambiar el lote de una caja usada | Solo se puede modificar el lote de cajas disponibles |
| `"No se puede modificar el peso bruto de la caja #X: está siendo usada en producción"` | Intentaste cambiar el peso bruto de una caja usada | Solo se puede modificar el peso bruto de cajas disponibles |
| `"No se puede eliminar el palet #X: tiene cajas siendo usadas en producción"` | Intentaste eliminar un palet con cajas usadas | Incluir el palet en el request |

---

## 📚 Referencias

- [Diseño Backend](./69-Diseno-Edicion-Cajas-Disponibles-Recepciones.md)
- [Guía Backend Edición Recepciones](./65-Guia-Backend-Edicion-Recepciones.md)
- [Guía Frontend Edición Recepciones](./64-Guia-Frontend-Edicion-Recepciones.md)

---

**Última actualización**: 2025-01-XX

