# Frontend - Integración de Consumos de Outputs del Padre

## 📋 Resumen de Cambios

Se ha implementado la funcionalidad para que los procesos hijos puedan consumir outputs del proceso padre. Esto complementa la funcionalidad existente de consumo de cajas del stock.

### Nuevas Funcionalidades

1. **Consumir outputs del proceso padre**: Los procesos hijos ahora pueden consumir parte o toda la salida del proceso padre
2. **Visualización de disponibilidad**: Ver qué outputs del padre están disponibles para consumo
3. **Validación en tiempo real**: Validación de disponibilidad antes de crear consumos

---

## 🆕 Nuevos Endpoints

### 1. Crear Consumo de Output del Padre

```http
POST /v2/production-output-consumptions
Content-Type: application/json
Authorization: Bearer {token}
X-Tenant: {tenant}

{
    "production_record_id": 123,
    "production_output_id": 456,
    "consumed_weight_kg": 150.50,
    "consumed_boxes": 10,
    "notes": "Consumo parcial para envasado"
}
```

**Respuesta exitosa (201)**:
```json
{
    "message": "Consumo de output creado correctamente.",
    "data": {
        "id": 1,
        "productionRecordId": 123,
        "productionOutputId": 456,
        "consumedWeightKg": 150.50,
        "consumedBoxes": 10,
        "notes": "Consumo parcial para envasado",
        "productionOutput": {
            "id": 456,
            "productId": 10,
            "product": {
                "id": 10,
                "name": "Filetes de atún"
            },
            "weightKg": 300.00,
            "boxes": 20
        },
        "isComplete": false,
        "isPartial": true,
        "weightConsumptionPercentage": 50.17,
        "boxesConsumptionPercentage": 50.00,
        "outputTotalWeight": 300.00,
        "outputTotalBoxes": 20,
        "outputAvailableWeight": 149.50,
        "outputAvailableBoxes": 10,
        "createdAt": "2024-01-15T12:00:00Z",
        "updatedAt": "2024-01-15T12:00:00Z"
    }
}
```

**Errores posibles**:
- `422`: El proceso no tiene padre / Output no pertenece al padre / Ya existe consumo / No hay suficiente disponibilidad

---

### 2. Listar Consumos

```http
GET /v2/production-output-consumptions?production_record_id=123
```

**Parámetros de query**:
- `production_record_id`: Filtrar por proceso que consume
- `production_output_id`: Filtrar por output consumido
- `production_id`: Filtrar por lote de producción
- `parent_record_id`: Filtrar por procesos hijos de un proceso específico
- `perPage`: Cantidad por página (default: 15)

**Respuesta**:
```json
{
    "data": [
        {
            "id": 1,
            "productionRecordId": 123,
            "productionOutputId": 456,
            "consumedWeightKg": 150.50,
            "consumedBoxes": 10,
            "productionOutput": { ... },
            ...
        }
    ],
    "links": { ... },
    "meta": { ... }
}
```

---

### 3. Obtener Outputs Disponibles

```http
GET /v2/production-output-consumptions/available-outputs/{productionRecordId}
```

**Respuesta**:
```json
{
    "message": "Outputs disponibles obtenidos correctamente.",
    "data": [
        {
            "output": {
                "id": 456,
                "productionRecordId": 100,
                "productId": 10,
                "product": {
                    "id": 10,
                    "name": "Filetes de atún"
                },
                "lotId": "LOT-123",
                "boxes": 20,
                "weightKg": 300.00,
                "averageWeightPerBox": 15.00,
                "createdAt": "2024-01-15T10:30:00+00:00",
                "updatedAt": "2024-01-15T10:30:00+00:00"
            },
            "totalWeight": 300.00,
            "totalBoxes": 20,
            "consumedWeight": 150.50,
            "consumedBoxes": 10,
            "availableWeight": 149.50,
            "availableBoxes": 10,
            "hasExistingConsumption": false
        }
    ]
}
```

**Nota**: El objeto `product` con `id` y `name` está incluido en la respuesta. Solo se devuelven outputs con disponibilidad (`availableWeight > 0` o `availableBoxes > 0`).

Este endpoint es especialmente útil para mostrar al usuario qué outputs del padre están disponibles antes de crear un consumo.

---

### 4. Actualizar Consumo

```http
PUT /v2/production-output-consumptions/{id}
Content-Type: application/json

{
    "consumed_weight_kg": 200.00,
    "consumed_boxes": 15,
    "notes": "Consumo actualizado"
}
```

**Nota**: Solo se pueden actualizar `consumed_weight_kg`, `consumed_boxes` y `notes`. No se puede cambiar el proceso o el output.

---

### 5. Eliminar Consumo

```http
DELETE /v2/production-output-consumptions/{id}
```

---

## 🔄 Cambios en Datos Existentes

### ProductionRecord (Proceso)

El objeto `ProductionRecord` ahora incluye información sobre consumos de outputs del padre:

```json
{
    "id": 123,
    "process": { ... },
    "inputs": [
        {
            "id": 1,
            "type": "stock_box",
            "box_id": 789,
            "box": { ... },
            "weight": 50.00
        }
    ],
    "parentOutputConsumptions": [
        {
            "id": 1,
            "type": "parent_output",
            "production_output_id": 456,
            "consumed_weight_kg": 150.50,
            "consumed_boxes": 10,
            "weight": 150.50
        }
    ],
    "outputs": [ ... ],
    "totals": {
        "inputWeight": 200.50,  // 50 (stock) + 150.50 (padre)
        "outputWeight": 180.00,
        "waste": 20.50,
        "wastePercentage": 10.22
    }
}
```

**Cambios importantes**:
- `inputs`: Ahora incluye solo inputs desde stock (tipo `stock_box`)
- `parentOutputConsumptions`: Nuevo array con consumos del padre (tipo `parent_output`)
- `totals.inputWeight`: Ahora incluye ambos tipos de inputs

---

## 🎨 Recomendaciones de UI/UX

### 1. Vista de Proceso Hijo

Cuando se visualiza un proceso hijo, mostrar dos secciones:

**Sección 1: Inputs desde Stock**
```
┌─────────────────────────────────────┐
│ Inputs desde Stock                  │
├─────────────────────────────────────┤
│ - Caja #123 - 50kg                  │
│ - Caja #124 - 45kg                  │
│ Total: 95kg                         │
└─────────────────────────────────────┘
```

**Sección 2: Consumos del Padre**
```
┌─────────────────────────────────────┐
│ Consumos del Proceso Padre          │
├─────────────────────────────────────┤
│ - Filetes de atún: 150.50kg (10 cajas)│
│ Total: 150.50kg                     │
└─────────────────────────────────────┘
```

**Total Input**: 245.50kg (95 + 150.50)

---

### 2. Formulario para Consumir Output del Padre

**Paso 1**: Obtener outputs disponibles
```javascript
const availableOutputs = await fetch(
    `/v2/production-output-consumptions/available-outputs/${productionRecordId}`
).then(r => r.json());
```

**Paso 2**: Mostrar selector de output
```html
<select name="production_output_id">
    {availableOutputs.data.map(output => (
        <option value={output.output.id}>
            {output.output.product.name} - 
            Disponible: {output.availableWeight}kg / {output.output.weightKg}kg
        </option>
    ))}
</select>
```

**Paso 3**: Campos de consumo
```html
<input 
    type="number" 
    name="consumed_weight_kg" 
    min="0" 
    max={selectedOutput.availableWeight}
    step="0.01"
/>
<input 
    type="number" 
    name="consumed_boxes" 
    min="0" 
    max={selectedOutput.availableBoxes}
/>
<textarea name="notes" placeholder="Notas opcionales" />
```

**Paso 4**: Validación antes de enviar
- Verificar que `consumed_weight_kg <= availableWeight`
- Verificar que `consumed_boxes <= availableBoxes`
- Mostrar advertencia si el proceso ya tiene un consumo de ese output (debe actualizarse en lugar de crear uno nuevo)

---

### 3. Visualización de Disponibilidad

Mostrar barra de progreso o indicador visual:

```
Output: Filetes de atún - 300kg total
[████████████████░░░░░░░░] 150.50kg consumido (50.17%)
Disponible: 149.50kg
```

---

### 4. Edición de Consumo

Si el proceso ya tiene un consumo de un output, mostrar:
- Opción para editar el consumo existente (no crear uno nuevo)
- Mostrar consumo actual y disponibilidad restante

---

## 📊 Ejemplo Completo de Flujo

### Escenario: Proceso Hijo que Consume del Padre

1. **Crear proceso padre "Fileteado"**
   ```javascript
   const parentRecord = await createProductionRecord({
       production_id: 1,
       process_id: 5, // Fileteado
       parent_record_id: null
   });
   ```

2. **Asignar cajas del stock al padre**
   ```javascript
   await createProductionInput({
       production_record_id: parentRecord.id,
       box_id: 789
   });
   ```

3. **Registrar output del padre**
   ```javascript
   await createProductionOutput({
       production_record_id: parentRecord.id,
       product_id: 10, // Filetes
       weight_kg: 300.00,
       boxes: 20
   });
   ```

4. **Crear proceso hijo "Envasado"**
   ```javascript
   const childRecord = await createProductionRecord({
       production_id: 1,
       process_id: 6, // Envasado
       parent_record_id: parentRecord.id  // ← Tiene padre
   });
   ```

5. **Obtener outputs disponibles del padre**
   ```javascript
   const available = await fetch(
       `/v2/production-output-consumptions/available-outputs/${childRecord.id}`
   ).then(r => r.json());
   
   // available.data[0] = {
   //   output: { id: 456, weightKg: 300, ... },
   //   availableWeight: 300.00,
   //   availableBoxes: 20
   // }
   ```

6. **Consumir output del padre**
   ```javascript
   await createOutputConsumption({
       production_record_id: childRecord.id,
       production_output_id: 456,
       consumed_weight_kg: 150.50,
       consumed_boxes: 10,
       notes: "Consumo parcial para envasado"
   });
   ```

7. **Opcionalmente, también consumir cajas del stock**
   ```javascript
   await createProductionInput({
       production_record_id: childRecord.id,
       box_id: 790  // Otra caja
   });
   ```

8. **Registrar output del proceso hijo**
   ```javascript
   await createProductionOutput({
       production_record_id: childRecord.id,
       product_id: 11, // Filetes envasados
       weight_kg: 140.00,
       boxes: 15
   });
   ```

**Resultado**:
- Proceso hijo tiene inputs totales: 150.50kg (padre) + peso de caja 790 (stock)
- Proceso hijo produce: 140.00kg
- Merma calculada correctamente incluyendo ambos tipos de inputs

---

## ⚠️ Validaciones Importantes

### 1. Solo Procesos Hijos Pueden Consumir

```javascript
// Verificar antes de mostrar el formulario
if (!productionRecord.parent_record_id) {
    // No mostrar opción de consumir outputs del padre
    return;
}
```

### 2. Un Solo Consumo por Output

Si el proceso ya tiene un consumo de un output:
- **NO** permitir crear otro consumo
- **SÍ** mostrar opción de editar el consumo existente

```javascript
const existingConsumption = consumptions.find(
    c => c.productionOutputId === outputId
);

if (existingConsumption) {
    // Mostrar formulario de edición
    // No mostrar formulario de creación
}
```

### 3. Validar Disponibilidad Antes de Enviar

```javascript
const output = availableOutputs.find(
    o => o.output.id === formData.production_output_id
);

if (formData.consumed_weight_kg > output.availableWeight) {
    showError(`Solo hay ${output.availableWeight}kg disponible`);
    return;
}
```

---

## 🔄 Migración de Datos Existentes

No se requieren cambios en datos existentes. Los procesos existentes que no tienen consumos de outputs del padre seguirán funcionando normalmente. Solo se agregará la nueva funcionalidad.

---

## 📝 Checklist de Implementación Frontend

- [ ] Agregar endpoint para crear consumo de output del padre
- [ ] Agregar endpoint para obtener outputs disponibles
- [ ] Agregar endpoint para listar consumos de un proceso
- [ ] Agregar endpoint para actualizar consumo
- [ ] Agregar endpoint para eliminar consumo
- [ ] Actualizar vista de proceso para mostrar dos tipos de inputs
- [ ] Crear formulario para consumir output del padre
- [ ] Agregar validaciones de disponibilidad
- [ ] Actualizar cálculos de totales para incluir consumos del padre
- [ ] Agregar indicadores visuales de disponibilidad
- [ ] Actualizar diagrama de producción para mostrar consumos del padre

---

## 🎯 Ejemplos de Código

### React/TypeScript Example

```typescript
interface ProductionOutputConsumption {
    id: number;
    productionRecordId: number;
    productionOutputId: number;
    consumedWeightKg: number;
    consumedBoxes: number;
    notes?: string;
    productionOutput: {
        id: number;
        product: {
            id: number;
            name: string;
        };
        weightKg: number;
        boxes: number;
    };
    outputAvailableWeight: number;
    outputAvailableBoxes: number;
}

// Obtener outputs disponibles
async function getAvailableOutputs(productionRecordId: number) {
    const response = await fetch(
        `/v2/production-output-consumptions/available-outputs/${productionRecordId}`,
        {
            headers: {
                'Authorization': `Bearer ${token}`,
                'X-Tenant': tenant
            }
        }
    );
    return response.json();
}

// Crear consumo
async function createOutputConsumption(data: {
    production_record_id: number;
    production_output_id: number;
    consumed_weight_kg: number;
    consumed_boxes?: number;
    notes?: string;
}) {
    const response = await fetch('/v2/production-output-consumptions', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${token}`,
            'X-Tenant': tenant
        },
        body: JSON.stringify(data)
    });
    
    if (!response.ok) {
        const error = await response.json();
        throw new Error(error.message || 'Error al crear consumo');
    }
    
    return response.json();
}

// Componente de ejemplo
function ConsumeParentOutput({ productionRecordId }: { productionRecordId: number }) {
    const [availableOutputs, setAvailableOutputs] = useState([]);
    const [selectedOutput, setSelectedOutput] = useState(null);
    const [consumedWeight, setConsumedWeight] = useState(0);
    
    useEffect(() => {
        getAvailableOutputs(productionRecordId).then(result => {
            setAvailableOutputs(result.data);
        });
    }, [productionRecordId]);
    
    const handleSubmit = async (e) => {
        e.preventDefault();
        
        const output = availableOutputs.find(
            o => o.output.id === selectedOutput
        );
        
        if (consumedWeight > output.availableWeight) {
            alert(`Solo hay ${output.availableWeight}kg disponible`);
            return;
        }
        
        try {
            await createOutputConsumption({
                production_record_id: productionRecordId,
                production_output_id: selectedOutput,
                consumed_weight_kg: consumedWeight
            });
            alert('Consumo creado correctamente');
            // Refrescar datos
        } catch (error) {
            alert(error.message);
        }
    };
    
    return (
        <form onSubmit={handleSubmit}>
            <select 
                value={selectedOutput || ''} 
                onChange={e => setSelectedOutput(Number(e.target.value))}
            >
                <option value="">Seleccionar output</option>
                {availableOutputs.map(available => (
                    <option key={available.output.id} value={available.output.id}>
                        {available.output.product.name} - 
                        Disponible: {available.availableWeight}kg / {available.totalWeight}kg
                    </option>
                ))}
            </select>
            
            {selectedOutput && (
                <>
                    <input
                        type="number"
                        value={consumedWeight}
                        onChange={e => setConsumedWeight(Number(e.target.value))}
                        min={0}
                        max={
                            availableOutputs.find(o => o.output.id === selectedOutput)
                                ?.availableWeight || 0
                        }
                        step="0.01"
                        placeholder="Peso a consumir (kg)"
                    />
                    <button type="submit">Consumir</button>
                </>
            )}
        </form>
    );
}
```

---

## 📚 Referencias

- [15-Produccion-Consumos-Outputs-Padre.md](./15-Produccion-Consumos-Outputs-Padre.md) - Documentación completa del modelo
- [10-Produccion-General.md](./10-Produccion-General.md) - Visión general del módulo de producción
- [12-Produccion-Procesos.md](./12-Produccion-Procesos.md) - Documentación de procesos

---

**Última actualización**: Documentación creada para frontend.

