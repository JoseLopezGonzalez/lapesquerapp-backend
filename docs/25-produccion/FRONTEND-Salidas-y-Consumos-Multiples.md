# Frontend - Endpoints para Múltiples Salidas y Consumos

## 🎯 Resumen

Se han agregado nuevos endpoints que permiten crear y editar múltiples salidas de producto y consumos de outputs del padre **en una sola petición**, optimizando el flujo de trabajo del frontend.

**Fecha de implementación**: 2025-01-XX

---

## 📋 Nuevos Endpoints

### 1. Crear Múltiples Salidas de Producción

**Endpoint**: `POST /v2/production-outputs/multiple`

Permite crear múltiples salidas de producto para un proceso en una sola petición.

#### Request

```http
POST /v2/production-outputs/multiple
Content-Type: application/json
Authorization: Bearer {token}
X-Tenant: {tenant}
```

```json
{
    "production_record_id": 123,
    "outputs": [
        {
            "product_id": 10,
            "lot_id": "LOT-001",
            "boxes": 20,
            "weight_kg": 300.00
        },
        {
            "product_id": 11,
            "lot_id": "LOT-002",
            "boxes": 15,
            "weight_kg": 225.00
        },
        {
            "product_id": 12,
            "boxes": 10,
            "weight_kg": 150.00
        }
    ]
}
```

#### Validación

- `production_record_id`: Requerido, debe existir
- `outputs`: Array requerido con al menos 1 elemento
- `outputs.*.product_id`: Requerido, debe existir
- `outputs.*.lot_id`: Opcional, string
- `outputs.*.boxes`: Requerido, integer >= 0
- `outputs.*.weight_kg`: Requerido, numeric >= 0

#### Response (201)

```json
{
    "message": "3 salida(s) creada(s) correctamente.",
    "data": [
        {
            "id": 456,
            "productionRecordId": 123,
            "productId": 10,
            "product": {
                "id": 10,
                "name": "Filetes de atún"
            },
            "lotId": "LOT-001",
            "boxes": 20,
            "weightKg": 300.00,
            "createdAt": "2025-01-15T12:00:00Z",
            "updatedAt": "2025-01-15T12:00:00Z"
        },
        {
            "id": 457,
            "productionRecordId": 123,
            "productId": 11,
            "product": {
                "id": 11,
                "name": "Lomos de atún"
            },
            "lotId": "LOT-002",
            "boxes": 15,
            "weightKg": 225.00,
            "createdAt": "2025-01-15T12:00:00Z",
            "updatedAt": "2025-01-15T12:00:00Z"
        },
        {
            "id": 458,
            "productionRecordId": 123,
            "productId": 12,
            "product": {
                "id": 12,
                "name": "Ventresca"
            },
            "lotId": null,
            "boxes": 10,
            "weightKg": 150.00,
            "createdAt": "2025-01-15T12:00:00Z",
            "updatedAt": "2025-01-15T12:00:00Z"
        }
    ],
    "errors": []
}
```

#### Errores

Si alguna salida falla, se retornan los errores pero las que se crearon exitosamente se mantienen (transacción parcial):

```json
{
    "message": "2 salida(s) creada(s) correctamente.",
    "data": [...],
    "errors": [
        "Error en la salida #2: El producto no existe."
    ]
}
```

---

### 2. Sincronizar Todas las Salidas de un Proceso

**Endpoint**: `PUT /v2/production-records/{id}/outputs`

Permite crear, actualizar y eliminar salidas de un proceso en una sola petición. **Este es el endpoint recomendado para editar todas las salidas de una vez.**

#### Request

```http
PUT /v2/production-records/123/outputs
Content-Type: application/json
Authorization: Bearer {token}
X-Tenant: {tenant}
```

```json
{
    "outputs": [
        {
            "id": 456,
            "product_id": 10,
            "lot_id": "LOT-001-UPDATED",
            "boxes": 25,
            "weight_kg": 375.00
        },
        {
            "product_id": 12,
            "lot_id": "LOT-003",
            "boxes": 10,
            "weight_kg": 150.00
        }
    ]
}
```

**Comportamiento**:
- Si `outputs.*.id` existe y pertenece al proceso → **actualiza** la salida existente
- Si `outputs.*.id` no existe → **crea** una nueva salida
- Las salidas que no están en el array → **se eliminan** (si no tienen consumos asociados)

#### Validación

- `outputs`: Array requerido
- `outputs.*.id`: Opcional, si existe debe pertenecer al proceso
- `outputs.*.product_id`: Requerido, debe existir
- `outputs.*.lot_id`: Opcional, string
- `outputs.*.boxes`: Requerido, integer >= 0
- `outputs.*.weight_kg`: Requerido, numeric >= 0

#### Response (200)

```json
{
    "message": "Salidas sincronizadas correctamente.",
    "data": {
        "id": 123,
        "production": {...},
        "process": {...},
        "outputs": [
            {
                "id": 456,
                "productId": 10,
                "boxes": 25,
                "weightKg": 375.00,
                ...
            },
            {
                "id": 459,
                "productId": 12,
                "boxes": 10,
                "weightKg": 150.00,
                ...
            }
        ],
        ...
    },
    "summary": {
        "created": 1,
        "updated": 1,
        "deleted": 1
    }
}
```

#### Errores

**422 - No se puede eliminar output con consumos**:
```json
{
    "message": "No se puede eliminar el output #456 porque tiene consumos asociados.",
    "output_id": 456
}
```

**422 - Output no pertenece al proceso**:
```json
{
    "message": "El output #999 no pertenece a este proceso."
}
```

---

### 3. Crear Múltiples Consumos de Outputs del Padre

**Endpoint**: `POST /v2/production-output-consumptions/multiple`

Permite crear múltiples consumos de outputs del proceso padre en una sola petición.

#### Request

```http
POST /v2/production-output-consumptions/multiple
Content-Type: application/json
Authorization: Bearer {token}
X-Tenant: {tenant}
```

```json
{
    "production_record_id": 124,
    "consumptions": [
        {
            "production_output_id": 456,
            "consumed_weight_kg": 150.00,
            "consumed_boxes": 10,
            "notes": "Consumo parcial para envasado"
        },
        {
            "production_output_id": 457,
            "consumed_weight_kg": 200.00,
            "consumed_boxes": 15
        }
    ]
}
```

#### Validación

- `production_record_id`: Requerido, debe existir y tener un proceso padre
- `consumptions`: Array requerido con al menos 1 elemento
- `consumptions.*.production_output_id`: Requerido, debe existir y pertenecer al proceso padre
- `consumptions.*.consumed_weight_kg`: Requerido, numeric >= 0
- `consumptions.*.consumed_boxes`: Opcional, integer >= 0
- `consumptions.*.notes`: Opcional, string

**Validaciones adicionales**:
- El proceso debe tener un padre (`parent_record_id`)
- Cada output debe pertenecer al proceso padre directo
- No puede haber consumos duplicados del mismo output
- El consumo total no debe exceder el output disponible

#### Response (201)

```json
{
    "message": "2 consumo(s) creado(s) correctamente.",
    "data": [
        {
            "id": 789,
            "productionRecordId": 124,
            "productionOutputId": 456,
            "consumedWeightKg": 150.00,
            "consumedBoxes": 10,
            "notes": "Consumo parcial para envasado",
            "productionOutput": {
                "id": 456,
                "productId": 10,
                "weightKg": 300.00,
                "boxes": 20
            },
            ...
        },
        {
            "id": 790,
            "productionRecordId": 124,
            "productionOutputId": 457,
            "consumedWeightKg": 200.00,
            "consumedBoxes": 15,
            "notes": null,
            ...
        }
    ],
    "errors": []
}
```

#### Errores

**422 - Proceso sin padre**:
```json
{
    "message": "El proceso no tiene un proceso padre. Solo los procesos hijos pueden consumir outputs de procesos padre."
}
```

**422 - Output no pertenece al padre**:
```json
{
    "message": "El output #456 no pertenece al proceso padre directo."
}
```

**422 - Insuficiente disponibilidad**:
```json
{
    "message": "Error de validación al crear los consumos.",
    "errors": [
        "Output #456: No hay suficiente peso disponible. Disponible: 100.00kg, solicitado: 150.00kg"
    ]
}
```

**422 - Consumo duplicado**:
```json
{
    "message": "2 consumo(s) creado(s) correctamente.",
    "data": [...],
    "errors": [
        "Consumo #1: Ya existe un consumo para el output #456."
    ]
}
```

---

### 4. Sincronizar Todos los Consumos de un Proceso

**Endpoint**: `PUT /v2/production-records/{id}/parent-output-consumptions`

Permite crear, actualizar y eliminar consumos de outputs del padre en una sola petición. **Este es el endpoint recomendado para editar todos los consumos de una vez.**

#### Request

```http
PUT /v2/production-records/124/parent-output-consumptions
Content-Type: application/json
Authorization: Bearer {token}
X-Tenant: {tenant}
```

```json
{
    "consumptions": [
        {
            "id": 789,
            "production_output_id": 456,
            "consumed_weight_kg": 175.00,
            "consumed_boxes": 12,
            "notes": "Consumo actualizado"
        },
        {
            "production_output_id": 458,
            "consumed_weight_kg": 100.00,
            "consumed_boxes": 8
        }
    ]
}
```

**Comportamiento**:
- Si `consumptions.*.id` existe y pertenece al proceso → **actualiza** el consumo existente
- Si `consumptions.*.id` no existe → **crea** un nuevo consumo
- Los consumos que no están en el array → **se eliminan**

#### Validación

- `consumptions`: Array requerido
- `consumptions.*.id`: Opcional, si existe debe pertenecer al proceso
- `consumptions.*.production_output_id`: Requerido, debe existir y pertenecer al proceso padre
- `consumptions.*.consumed_weight_kg`: Requerido, numeric >= 0
- `consumptions.*.consumed_boxes`: Opcional, integer >= 0
- `consumptions.*.notes`: Opcional, string

**Validaciones adicionales**:
- El proceso debe tener un padre
- Cada output debe pertenecer al proceso padre directo
- No puede haber consumos duplicados del mismo output
- El consumo total no debe exceder el output disponible (considerando otros consumos)

#### Response (200)

```json
{
    "message": "Consumos sincronizados correctamente.",
    "data": {
        "id": 124,
        "production": {...},
        "parent": {...},
        "process": {...},
        "parentOutputConsumptions": [
            {
                "id": 789,
                "productionOutputId": 456,
                "consumedWeightKg": 175.00,
                "consumedBoxes": 12,
                ...
            },
            {
                "id": 791,
                "productionOutputId": 458,
                "consumedWeightKg": 100.00,
                "consumedBoxes": 8,
                ...
            }
        ],
        ...
    },
    "summary": {
        "created": 1,
        "updated": 1,
        "deleted": 1
    }
}
```

#### Errores

**422 - Proceso sin padre**:
```json
{
    "message": "El proceso no tiene un proceso padre. Solo los procesos hijos pueden consumir outputs de procesos padre."
}
```

**422 - Insuficiente disponibilidad**:
```json
{
    "message": "No hay suficiente peso disponible en el output #456. Disponible: 100.00kg, solicitado: 175.00kg",
    "output_id": 456,
    "available_weight": 100.00,
    "requested_weight": 175.00
}
```

**422 - Consumo duplicado**:
```json
{
    "message": "Ya existe un consumo para el output #456. Use el ID del consumo existente para actualizarlo.",
    "existing_consumption_id": 789
}
```

---

## 🔄 Migración desde Endpoints Individuales

### Antes (Múltiples peticiones)

```typescript
// ❌ Antes: Múltiples peticiones
async function saveOutputs(outputs: Output[]) {
    for (const output of outputs) {
        await fetch('/v2/production-outputs', {
            method: 'POST',
            body: JSON.stringify({
                production_record_id: 123,
                ...output
            })
        });
    }
}
```

### Después (Una sola petición)

```typescript
// ✅ Después: Una sola petición
async function saveOutputs(outputs: Output[]) {
    const response = await fetch('/v2/production-outputs/multiple', {
        method: 'POST',
        body: JSON.stringify({
            production_record_id: 123,
            outputs: outputs
        })
    });
    return response.json();
}
```

### Para Editar (Sincronización)

```typescript
// ✅ Editar todas las salidas de una vez
async function syncOutputs(productionRecordId: number, outputs: Output[]) {
    const response = await fetch(`/v2/production-records/${productionRecordId}/outputs`, {
        method: 'PUT',
        body: JSON.stringify({
            outputs: outputs.map(output => ({
                id: output.id, // Si tiene ID, se actualiza; si no, se crea
                product_id: output.product_id,
                lot_id: output.lot_id,
                boxes: output.boxes,
                weight_kg: output.weight_kg
            }))
        })
    });
    return response.json();
}
```

---

## 📝 Ejemplos de Uso en el Frontend

### Ejemplo 1: Formulario de Salidas

```typescript
interface OutputForm {
    product_id: number;
    lot_id?: string;
    boxes: number;
    weight_kg: number;
}

async function handleSaveOutputs(productionRecordId: number, outputs: OutputForm[]) {
    try {
        // Si es la primera vez (crear)
        if (outputs.every(o => !o.id)) {
            const response = await fetch('/v2/production-outputs/multiple', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${token}`,
                    'X-Tenant': tenant
                },
                body: JSON.stringify({
                    production_record_id: productionRecordId,
                    outputs: outputs
                })
            });
            
            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.message);
            }
            
            return await response.json();
        } else {
            // Si hay IDs (editar/sincronizar)
            const response = await fetch(`/v2/production-records/${productionRecordId}/outputs`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${token}`,
                    'X-Tenant': tenant
                },
                body: JSON.stringify({
                    outputs: outputs.map(o => ({
                        id: o.id,
                        product_id: o.product_id,
                        lot_id: o.lot_id,
                        boxes: o.boxes,
                        weight_kg: o.weight_kg
                    }))
                })
            });
            
            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.message);
            }
            
            return await response.json();
        }
    } catch (error) {
        console.error('Error al guardar salidas:', error);
        throw error;
    }
}
```

### Ejemplo 2: Formulario de Consumos

```typescript
interface ConsumptionForm {
    production_output_id: number;
    consumed_weight_kg: number;
    consumed_boxes?: number;
    notes?: string;
}

async function handleSaveConsumptions(productionRecordId: number, consumptions: ConsumptionForm[]) {
    try {
        // Verificar si hay IDs (editar) o no (crear)
        const hasIds = consumptions.some(c => c.id);
        
        if (!hasIds) {
            // Crear múltiples
            const response = await fetch('/v2/production-output-consumptions/multiple', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${token}`,
                    'X-Tenant': tenant
                },
                body: JSON.stringify({
                    production_record_id: productionRecordId,
                    consumptions: consumptions
                })
            });
            
            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.message);
            }
            
            return await response.json();
        } else {
            // Sincronizar (crear/actualizar/eliminar)
            const response = await fetch(`/v2/production-records/${productionRecordId}/parent-output-consumptions`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${token}`,
                    'X-Tenant': tenant
                },
                body: JSON.stringify({
                    consumptions: consumptions.map(c => ({
                        id: c.id,
                        production_output_id: c.production_output_id,
                        consumed_weight_kg: c.consumed_weight_kg,
                        consumed_boxes: c.consumed_boxes,
                        notes: c.notes
                    }))
                })
            });
            
            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.message);
            }
            
            return await response.json();
        }
    } catch (error) {
        console.error('Error al guardar consumos:', error);
        throw error;
    }
}
```

---

## ⚠️ Consideraciones Importantes

### 1. Transacciones

- Los endpoints de **sincronización** (`syncOutputs`, `syncConsumptions`) usan transacciones: si algo falla, se revierte todo.
- Los endpoints de **creación múltiple** (`storeMultiple`) también usan transacciones, pero pueden retornar errores parciales.

### 2. Eliminación de Salidas

- **No se pueden eliminar** salidas que tienen consumos asociados.
- Si intentas eliminar una salida con consumos, recibirás un error 422.

### 3. Validación de Disponibilidad

- Los consumos validan la disponibilidad **antes** de crear/actualizar.
- Si el consumo total excede lo disponible, toda la operación falla.

### 4. IDs en Sincronización

- Si envías un `id` que existe → se actualiza
- Si no envías `id` → se crea nuevo
- Si una salida/consumo existente no está en el array → se elimina

### 5. Consumos Duplicados

- No puedes tener dos consumos del mismo output en el mismo proceso.
- Si intentas crear un duplicado, recibirás un error indicando el ID del consumo existente.

---

## 🔗 Endpoints Relacionados

- `GET /v2/production-outputs` - Listar salidas
- `GET /v2/production-output-consumptions` - Listar consumos
- `GET /v2/production-output-consumptions/available-outputs/{productionRecordId}` - Obtener outputs disponibles
- `GET /v2/production-records/{id}` - Obtener proceso con sus salidas y consumos

---

## 📅 Changelog

- **2025-01-XX**: Agregados endpoints para múltiples salidas y consumos
  - `POST /v2/production-outputs/multiple`
  - `PUT /v2/production-records/{id}/outputs`
  - `POST /v2/production-output-consumptions/multiple`
  - `PUT /v2/production-records/{id}/parent-output-consumptions`

