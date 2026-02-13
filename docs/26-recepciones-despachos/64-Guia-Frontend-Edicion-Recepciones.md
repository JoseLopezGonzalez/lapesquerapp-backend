# Guía Frontend: Edición de Recepciones de Materia Prima

## 📋 Resumen

Esta guía explica la nueva funcionalidad de **edición de recepciones de materia prima** y cómo determinar si una recepción se puede editar. Está orientada específicamente al equipo de frontend para implementar correctamente la lógica de edición.

---

## 🎯 Cambios Principales

### 1. Indicadores de Edición

Todas las recepciones ahora incluyen dos campos nuevos en la respuesta:

- **`canEdit`** (boolean): Indica si la recepción se puede editar
- **`cannotEditReason`** (string | null): Razón por la que no se puede editar (solo presente si `canEdit = false`)

### 2. Modos de Edición

Las recepciones se editan según su modo de creación:

- **Recepciones creadas en modo `lines`**: Se editan enviando `details`
- **Recepciones creadas en modo `pallets`**: Se pueden editar de dos formas:
  - Enviando `pallets` al endpoint de recepciones
  - Editando palets individuales desde el endpoint de palets

### 3. Restricciones Comunes

Independientemente del modo de creación, una recepción **NO se puede editar** si:

- Algún palet está vinculado a un pedido (`order_id !== null`)
- Alguna caja está siendo usada en producción (`productionInputs()->exists()`)

---

## 📡 API Response

### Campos Nuevos en RawMaterialReceptionResource

```json
{
  "id": 1,
  "supplier": {...},
  "date": "2025-01-15",
  "notes": "...",
  "creationMode": "lines", // o "pallets"
  "netWeight": 1000.50,
  "species": {...},
  "details": [...],
  "pallets": [...],
  "totalAmount": 12500.00,
  "canEdit": true,           // ← NUEVO
  "cannotEditReason": null    // ← NUEVO
}
```

### Ejemplo: Recepción que NO se puede editar

```json
{
  "id": 2,
  "creationMode": "pallets",
  "canEdit": false,
  "cannotEditReason": "El palet #15 está vinculado a un pedido"
}
```

O:

```json
{
  "id": 3,
  "creationMode": "lines",
  "canEdit": false,
  "cannotEditReason": "La caja #42 está siendo usada en producción"
}
```

---

## 🔍 Lógica de Edición

### Paso 1: Verificar si se puede editar

```javascript
// Ejemplo en JavaScript/TypeScript
const reception = await fetchReception(id);

if (!reception.canEdit) {
  // Mostrar mensaje de error
  showError(reception.cannotEditReason);
  disableEditButton();
  return;
}

// Habilitar botón de edición
enableEditButton();
```

### Paso 2: Determinar modo de edición

```javascript
if (reception.creationMode === 'lines') {
  // Editar con details
  showEditFormWithDetails(reception);
} else if (reception.creationMode === 'pallets') {
  // Editar con pallets o permitir edición individual de palets
  showEditFormWithPallets(reception);
  enableIndividualPalletEditing(reception.pallets);
}
```

### Paso 3: Incluir IDs en el Request (Modo PALLETS)

**⚠️ IMPORTANTE**: En modo PALLETS, debes incluir los IDs de palets y cajas existentes para que se editen en lugar de recrearse:

```javascript
// Al preparar el request para editar
const requestBody = {
  supplier: { id: reception.supplier.id },
  date: reception.date,
  notes: reception.notes,
  pallets: reception.pallets.map(pallet => ({
    id: pallet.id,  // ← Incluir ID del palet
    product: { id: pallet.product.id },
    price: pallet.price,
    lot: pallet.lot,
    observations: pallet.observations,
    boxes: pallet.boxes.map(box => ({
      id: box.id,  // ← Incluir ID de la caja
      gs1128: box.gs1128,
      grossWeight: box.grossWeight,
      netWeight: box.netWeight
    }))
  }))
};
```

---

## ✏️ Editar Recepción

### Editar Recepción en Modo LINES

**Endpoint**: `PUT /api/v2/raw-material-receptions/{id}`

**Request Body**:
```json
{
  "supplier": {
    "id": 1
  },
  "date": "2025-01-15",
  "notes": "Notas actualizadas",
  "details": [
    {
      "product": {
        "id": 5
      },
      "netWeight": 500.00,
      "price": 12.50,
      "lot": "LOT-2025-001",
      "boxes": 20
    }
  ]
}
```

**Validaciones**:
- `creationMode` debe ser `'lines'` o `null` (recepciones antiguas)
- `canEdit` debe ser `true`
- El formato es idéntico al de creación en modo automático

### Editar Recepción en Modo PALLETS

**Endpoint**: `PUT /api/v2/raw-material-receptions/{id}`

**Request Body**:
```json
{
  "supplier": {
    "id": 1
  },
  "date": "2025-01-15",
  "notes": "Notas actualizadas",
  "pallets": [
    {
      "id": 15,  // ← ID del palet existente (opcional)
      "product": {
        "id": 5
      },
      "price": 12.50,
      "lot": "LOT-2025-001",
      "observations": "Palet 1",
      "boxes": [
        {
          "id": 42,  // ← ID de la caja existente (opcional)
          "gs1128": "GS1-001",
          "grossWeight": 25.5,
          "netWeight": 25.0
        },
        {
          "gs1128": "GS1-002",  // ← Sin ID = nueva caja
          "grossWeight": 25.5,
          "netWeight": 25.0
        }
      ]
    }
  ]
}
```

**Validaciones**:
- `creationMode` debe ser `'pallets'`
- `canEdit` debe ser `true`
- `pallets[].id` es opcional (si viene, edita el palet existente; si no, crea uno nuevo)
- `pallets[].boxes[].id` es opcional (si viene, edita la caja existente; si no, crea una nueva)

**Comportamiento**:
- Si `pallets[].id` existe → actualiza el palet existente
- Si `pallets[].id` no existe → crea un nuevo palet
- Si `boxes[].id` existe → actualiza la caja existente
- Si `boxes[].id` no existe → crea una nueva caja
- Elimina palets/cajas que no están en el request
- Regenera líneas de recepción automáticamente

**⚠️ RECOMENDACIÓN**: Siempre incluye los IDs de palets y cajas existentes para mantener los IDs originales y evitar recreaciones innecesarias.

---

## 📦 Editar Palets Individuales

### Cuándo se puede editar un palet individualmente

Un palet se puede editar individualmente **solo si**:

1. Pertenece a una recepción (`receptionId !== null`)
2. La recepción fue creada en modo `pallets` (`creationMode === 'pallets'`)
3. El palet no está vinculado a un pedido (`orderId === null`)
4. Ninguna caja del palet está en producción

### Endpoint

**PUT** `/api/v2/pallets/{id}`

**Request Body**: Formato estándar de edición de palet

```json
{
  "id": 10,
  "observations": "Observaciones actualizadas",
  "boxes": [
    {
      "id": 1,
      "product": { "id": 5 },
      "lot": "LOT-2025-001",
      "gs1128": "GS1-001",
      "grossWeight": 25.5,
      "netWeight": 25.0
    }
  ]
}
```

### Comportamiento Automático

Al editar un palet de recepción:

1. Se actualiza el palet y sus cajas
2. **Se regeneran automáticamente las líneas de recepción** basándose en todos los palets de la recepción
3. Se agrupan cajas por producto y lote
4. Se mantiene el precio existente de las líneas de recepción

**⚠️ Importante**: No es necesario editar la recepción después de editar un palet. Las líneas se actualizan automáticamente.

---

## 🚫 Errores Comunes

### Error: Modo de edición incorrecto

**Código**: 500 (Exception)

**Mensaje**: `"No se puede modificar una recepción creada por palets usando el método de líneas. Debe modificar los palets directamente."`

**Solución**: Verificar `creationMode` y usar el formato correcto.

### Error: Recepción no editable

**Código**: 500 (Exception)

**Mensajes posibles**:
- `"No se puede modificar la recepción: el palet #X está vinculado a un pedido"`
- `"No se puede modificar la recepción: la caja #X está siendo usada en producción"`

**Solución**: Verificar `canEdit` antes de permitir edición. Mostrar `cannotEditReason` al usuario.

### Error: Palet de recepción no editable

**Código**: 403

**Mensajes posibles**:
- `"No se puede modificar un palet que proviene de una recepción creada por líneas. Modifique desde la recepción."`
- `"No se puede modificar el palet: está vinculado a un pedido"`
- `"No se puede modificar el palet: la caja #X está siendo usada en producción"`

**Solución**: 
- Si `creationMode === 'lines'`, deshabilitar edición de palets individuales
- Verificar restricciones antes de permitir edición

---

## 💡 Ejemplos de Implementación

### Ejemplo 1: Componente de Lista de Recepciones

```javascript
function ReceptionList({ receptions }) {
  return receptions.map(reception => (
    <ReceptionCard key={reception.id}>
      <ReceptionInfo reception={reception} />
      
      {/* Botón de edición */}
      {reception.canEdit ? (
        <EditButton 
          onClick={() => handleEdit(reception)}
          disabled={false}
        />
      ) : (
        <Tooltip content={reception.cannotEditReason}>
          <EditButton disabled={true} />
        </Tooltip>
      )}
    </ReceptionCard>
  ));
}
```

### Ejemplo 2: Formulario de Edición

```javascript
function EditReceptionForm({ reception }) {
  // Verificar si se puede editar
  if (!reception.canEdit) {
    return <ErrorMessage message={reception.cannotEditReason} />;
  }

  // Determinar modo de edición
  const isLinesMode = reception.creationMode === 'lines';
  
  return (
    <Form onSubmit={handleSubmit}>
      <SupplierField />
      <DateField />
      <NotesField />
      
      {isLinesMode ? (
        <DetailsFields details={reception.details} />
      ) : (
        // IMPORTANTE: Incluir IDs de palets y cajas para que se editen
        <PalletsFields pallets={reception.pallets} includeIds={true} />
      )}
      
      <SubmitButton />
    </Form>
  );
}
```

**⚠️ Nota**: En modo PALLETS, asegúrate de incluir los `id` de palets y cajas en el formulario para que se editen en lugar de recrearse.

### Ejemplo 3: Lista de Palets con Edición Condicional

```javascript
function PalletList({ pallets, reception }) {
  const canEditIndividual = 
    reception?.creationMode === 'pallets' && 
    reception?.canEdit;

  return pallets.map(pallet => (
    <PalletCard key={pallet.id}>
      <PalletInfo pallet={pallet} />
      
      {pallet.isFromReception ? (
        canEditIndividual ? (
          <EditButton onClick={() => editPallet(pallet.id)} />
        ) : (
          <Tooltip content="Edite desde la recepción">
            <EditButton disabled={true} />
          </Tooltip>
        )
      ) : (
        <EditButton onClick={() => editPallet(pallet.id)} />
      )}
    </PalletCard>
  ));
}
```

### Ejemplo 4: Preparar Request con IDs

```javascript
function prepareUpdateRequest(reception, formData) {
  if (reception.creationMode === 'pallets') {
    return {
      supplier: { id: formData.supplierId },
      date: formData.date,
      notes: formData.notes,
      pallets: formData.pallets.map(palletForm => ({
        id: palletForm.id,  // ← ID del palet (si existe)
        product: { id: palletForm.productId },
        price: palletForm.price,
        lot: palletForm.lot,
        observations: palletForm.observations,
        boxes: palletForm.boxes.map(boxForm => ({
          id: boxForm.id,  // ← ID de la caja (si existe)
          gs1128: boxForm.gs1128,
          grossWeight: boxForm.grossWeight,
          netWeight: boxForm.netWeight
        }))
      }))
    };
  } else {
    return {
      supplier: { id: formData.supplierId },
      date: formData.date,
      notes: formData.notes,
      details: formData.details.map(detail => ({
        product: { id: detail.productId },
        netWeight: detail.netWeight,
        price: detail.price,
        lot: detail.lot,
        boxes: detail.boxes
      }))
    };
  }
}
```

---

## 🔄 Flujo Completo de Edición

### Flujo 1: Editar Recepción en Modo LINES

```
1. Usuario hace clic en "Editar recepción"
   ↓
2. Frontend verifica: reception.canEdit === true
   ↓
3. Frontend muestra formulario con campos:
   - supplier, date, notes
   - details[] (array de líneas)
   ↓
4. Usuario modifica datos y envía
   ↓
5. Frontend envía PUT /api/v2/raw-material-receptions/{id}
   Body: { supplier, date, notes, details }
   ↓
6. Backend valida y actualiza
   ↓
7. Frontend recarga recepción actualizada
```

### Flujo 2: Editar Recepción en Modo PALLETS (desde recepción)

```
1. Usuario hace clic en "Editar recepción"
   ↓
2. Frontend verifica: reception.canEdit === true
   ↓
3. Frontend muestra formulario con campos:
   - supplier, date, notes
   - pallets[] (array de palets con cajas)
   ↓
4. Usuario modifica datos y envía
   ↓
5. Frontend envía PUT /api/v2/raw-material-receptions/{id}
   Body: { supplier, date, notes, pallets }
   ↓
6. Backend valida y actualiza
   ↓
7. Frontend recarga recepción actualizada
```

### Flujo 3: Editar Palet Individual (modo PALLETS)

```
1. Usuario hace clic en "Editar palet" (solo visible si creationMode === 'pallets')
   ↓
2. Frontend verifica:
   - pallet.isFromReception === true
   - reception.creationMode === 'pallets'
   - reception.canEdit === true
   ↓
3. Frontend muestra formulario de edición de palet
   ↓
4. Usuario modifica datos y envía
   ↓
5. Frontend envía PUT /api/v2/pallets/{id}
   Body: { id, observations, boxes, ... }
   ↓
6. Backend valida, actualiza palet y regenera líneas de recepción
   ↓
7. Frontend recarga palet y recepción actualizados
```

---

## ✅ Checklist de Implementación

- [ ] Verificar `canEdit` antes de mostrar botón de edición
- [ ] Mostrar `cannotEditReason` cuando `canEdit === false`
- [ ] Determinar modo de edición según `creationMode`
- [ ] Mostrar formulario correcto según el modo (`details` o `pallets`)
- [ ] Permitir edición de palets individuales solo si `creationMode === 'pallets'`
- [ ] Deshabilitar edición de palets si `creationMode === 'lines'`
- [ ] Manejar errores de validación del backend
- [ ] Recargar datos después de editar exitosamente
- [ ] Mostrar indicadores visuales de restricciones

---

## 🔗 Referencias

- [Guía Completa de Recepciones y Palets](./63-Guia-Frontend-Recepciones-Palets.md)
- [Guía Backend de Edición](./65-Guia-Backend-Edicion-Recepciones.md)
- [Documentación Técnica de Recepciones](./60-Recepciones-Materia-Prima.md)
- [Documentación de Palets](../23-inventario/31-Palets.md)

---

**Última actualización**: 2025-01-XX

