# Investigación: Endpoints para Salidas y Consumos de Producción

## 📋 Resumen Ejecutivo

Este documento investiga en profundidad los endpoints y funcionalidades para:
1. **Agregar salidas de producto** (ProductionOutput)
2. **Consumos de productos de procesos padres** (ProductionOutputConsumption)

**Problema identificado**: El frontend necesita enviar/editar todas las líneas de una vez, pero actualmente solo existen endpoints para crear/editar una línea a la vez.

---

## 🔍 Situación Actual

### 1. ProductionOutput (Salidas de Producto)

#### Endpoints Disponibles

| Método | Ruta | Descripción | Estado |
|--------|------|-------------|--------|
| `GET` | `/v2/production-outputs` | Listar salidas | ✅ Funcional |
| `POST` | `/v2/production-outputs` | **Crear UNA salida** | ✅ Funcional |
| `GET` | `/v2/production-outputs/{id}` | Mostrar salida | ✅ Funcional |
| `PUT` | `/v2/production-outputs/{id}` | **Actualizar UNA salida** | ✅ Funcional |
| `DELETE` | `/v2/production-outputs/{id}` | Eliminar salida | ✅ Funcional |

#### Estructura del Modelo

```php
ProductionOutput {
    production_record_id: FK a ProductionRecord
    product_id: FK a Product
    lot_id: string (opcional)
    boxes: integer (cantidad de cajas)
    weight_kg: decimal (peso en kilogramos)
}
```

#### Validaciones Actuales

```php
// POST /v2/production-outputs
[
    'production_record_id' => 'required|exists:tenant.production_records,id',
    'product_id' => 'required|exists:tenant.products,id',
    'lot_id' => 'nullable|string',
    'boxes' => 'required|integer|min:0',
    'weight_kg' => 'required|numeric|min:0',
]
```

#### Limitación Identificada

❌ **NO existe endpoint para crear múltiples salidas a la vez**

El frontend necesita enviar un array de salidas, pero actualmente debe hacer múltiples llamadas POST individuales.

---

### 2. ProductionOutputConsumption (Consumos de Outputs del Padre)

#### Endpoints Disponibles

| Método | Ruta | Descripción | Estado |
|--------|------|-------------|--------|
| `GET` | `/v2/production-output-consumptions` | Listar consumos | ✅ Funcional |
| `POST` | `/v2/production-output-consumptions` | **Crear UN consumo** | ✅ Funcional |
| `GET` | `/v2/production-output-consumptions/{id}` | Mostrar consumo | ✅ Funcional |
| `PUT` | `/v2/production-output-consumptions/{id}` | **Actualizar UN consumo** | ✅ Funcional |
| `DELETE` | `/v2/production-output-consumptions/{id}` | Eliminar consumo | ✅ Funcional |
| `GET` | `/v2/production-output-consumptions/available-outputs/{id}` | Outputs disponibles | ✅ Funcional |

#### Estructura del Modelo

```php
ProductionOutputConsumption {
    production_record_id: FK a ProductionRecord (proceso hijo)
    production_output_id: FK a ProductionOutput (output del padre)
    consumed_weight_kg: decimal (peso consumido)
    consumed_boxes: integer (cajas consumidas)
    notes: string (opcional)
}
```

#### Validaciones Actuales

```php
// POST /v2/production-output-consumptions
[
    'production_record_id' => 'required|exists:tenant.production_records,id',
    'production_output_id' => 'required|exists:tenant.production_outputs,id',
    'consumed_weight_kg' => 'required|numeric|min:0',
    'consumed_boxes' => 'nullable|integer|min:0',
    'notes' => 'nullable|string',
]
```

**Validaciones adicionales**:
- ✅ El proceso debe tener un padre (`parent_record_id`)
- ✅ El output debe pertenecer al proceso padre directo
- ✅ No puede haber un consumo duplicado (unique constraint)
- ✅ El consumo no puede exceder el output disponible

#### Limitación Identificada

❌ **NO existe endpoint para crear múltiples consumos a la vez**

El frontend necesita enviar un array de consumos, pero actualmente debe hacer múltiples llamadas POST individuales.

---

## 📊 Comparación con ProductionInput

### ProductionInput tiene `storeMultiple`

```php
// POST /v2/production-inputs/multiple
[
    'production_record_id' => 'required|exists:tenant.production_records,id',
    'box_ids' => 'required|array',
    'box_ids.*' => 'required|exists:tenant.boxes,id',
]
```

**Características**:
- ✅ Crea múltiples entradas en una transacción
- ✅ Ignora cajas que ya están asignadas (no falla toda la operación)
- ✅ Retorna array de creadas y errores
- ✅ Usa `DB::beginTransaction()` para atomicidad

**Ejemplo de respuesta**:
```json
{
    "message": "3 entradas creadas correctamente.",
    "data": [...],
    "errors": ["La caja 5 ya está asignada a este proceso."]
}
```

---

## 🎯 Necesidad del Frontend

El usuario indica que **en el frontend mete o edita todas las líneas de una vez, no línea por línea**.

Esto significa que necesita:

1. **Para Salidas (Outputs)**:
   - Endpoint para crear múltiples salidas en una sola petición
   - Endpoint para actualizar/reemplazar todas las salidas de un proceso

2. **Para Consumos (OutputConsumptions)**:
   - Endpoint para crear múltiples consumos en una sola petición
   - Endpoint para actualizar/reemplazar todos los consumos de un proceso

---

## 💡 Propuesta de Solución

### 1. Endpoint para Crear Múltiples Salidas

```php
// POST /v2/production-outputs/multiple
[
    'production_record_id' => 'required|exists:tenant.production_records,id',
    'outputs' => 'required|array|min:1',
    'outputs.*.product_id' => 'required|exists:tenant.products,id',
    'outputs.*.lot_id' => 'nullable|string',
    'outputs.*.boxes' => 'required|integer|min:0',
    'outputs.*.weight_kg' => 'required|numeric|min:0',
]
```

**Comportamiento**:
- Crea todas las salidas en una transacción
- Retorna array de salidas creadas
- Si alguna falla, hace rollback de todas

### 2. Endpoint para Actualizar/Reemplazar Todas las Salidas

```php
// PUT /v2/production-records/{id}/outputs
[
    'outputs' => 'required|array',
    'outputs.*.id' => 'sometimes|nullable|integer|exists:tenant.production_outputs,id',
    'outputs.*.product_id' => 'required|exists:tenant.products,id',
    'outputs.*.lot_id' => 'nullable|string',
    'outputs.*.boxes' => 'required|integer|min:0',
    'outputs.*.weight_kg' => 'required|numeric|min:0',
]
```

**Comportamiento**:
- Si `outputs.*.id` existe → actualiza la salida existente
- Si `outputs.*.id` no existe → crea una nueva salida
- Elimina las salidas que no están en el array
- Todo en una transacción

### 3. Endpoint para Crear Múltiples Consumos

```php
// POST /v2/production-output-consumptions/multiple
[
    'production_record_id' => 'required|exists:tenant.production_records,id',
    'consumptions' => 'required|array|min:1',
    'consumptions.*.production_output_id' => 'required|exists:tenant.production_outputs,id',
    'consumptions.*.consumed_weight_kg' => 'required|numeric|min:0',
    'consumptions.*.consumed_boxes' => 'nullable|integer|min:0',
    'consumptions.*.notes' => 'nullable|string',
]
```

**Comportamiento**:
- Valida todas las reglas de negocio para cada consumo
- Crea todos los consumos en una transacción
- Retorna array de creados y errores (similar a `storeMultiple` de inputs)

### 4. Endpoint para Actualizar/Reemplazar Todos los Consumos

```php
// PUT /v2/production-records/{id}/parent-output-consumptions
[
    'consumptions' => 'required|array',
    'consumptions.*.id' => 'sometimes|nullable|integer|exists:tenant.production_output_consumptions,id',
    'consumptions.*.production_output_id' => 'required|exists:tenant.production_outputs,id',
    'consumptions.*.consumed_weight_kg' => 'required|numeric|min:0',
    'consumptions.*.consumed_boxes' => 'nullable|integer|min:0',
    'consumptions.*.notes' => 'nullable|string',
]
```

**Comportamiento**:
- Si `consumptions.*.id` existe → actualiza el consumo existente
- Si `consumptions.*.id` no existe → crea un nuevo consumo
- Elimina los consumos que no están en el array
- Valida disponibilidad de outputs antes de hacer cambios
- Todo en una transacción

---

## 🔄 Flujo de Trabajo Propuesto

### Escenario 1: Crear Salidas por Primera Vez

```javascript
// Frontend envía todas las salidas de una vez
POST /v2/production-outputs/multiple
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
        }
    ]
}
```

### Escenario 2: Editar Todas las Salidas

```javascript
// Frontend envía todas las salidas (existentes y nuevas)
PUT /v2/production-records/123/outputs
{
    "outputs": [
        {
            "id": 456,  // Existente - se actualiza
            "product_id": 10,
            "lot_id": "LOT-001-UPDATED",
            "boxes": 25,
            "weight_kg": 375.00
        },
        {
            // Sin ID - se crea nueva
            "product_id": 12,
            "lot_id": "LOT-003",
            "boxes": 10,
            "weight_kg": 150.00
        }
        // La salida con id 457 se elimina (no está en el array)
    ]
}
```

### Escenario 3: Crear Consumos por Primera Vez

```javascript
// Frontend envía todos los consumos de una vez
POST /v2/production-output-consumptions/multiple
{
    "production_record_id": 124,
    "consumptions": [
        {
            "production_output_id": 456,
            "consumed_weight_kg": 150.00,
            "consumed_boxes": 10,
            "notes": "Consumo parcial"
        },
        {
            "production_output_id": 457,
            "consumed_weight_kg": 200.00,
            "consumed_boxes": 15
        }
    ]
}
```

### Escenario 4: Editar Todos los Consumos

```javascript
// Frontend envía todos los consumos (existentes y nuevos)
PUT /v2/production-records/124/parent-output-consumptions
{
    "consumptions": [
        {
            "id": 789,  // Existente - se actualiza
            "production_output_id": 456,
            "consumed_weight_kg": 175.00,
            "consumed_boxes": 12
        },
        {
            // Sin ID - se crea nuevo
            "production_output_id": 458,
            "consumed_weight_kg": 100.00,
            "consumed_boxes": 8
        }
        // El consumo con id 790 se elimina (no está en el array)
    ]
}
```

---

## ⚠️ Consideraciones Importantes

### Validaciones para Múltiples Salidas

1. ✅ Todas las salidas deben pertenecer al mismo `production_record_id`
2. ✅ Validar que los productos existan
3. ✅ Validar que el proceso exista

### Validaciones para Múltiples Consumos

1. ✅ Todos los consumos deben pertenecer al mismo `production_record_id`
2. ✅ El proceso debe tener un padre
3. ✅ Cada output debe pertenecer al proceso padre directo
4. ✅ No puede haber consumos duplicados del mismo output
5. ✅ **Validar disponibilidad total**: La suma de todos los consumos no debe exceder el output disponible
6. ✅ Si hay consumos existentes, considerar su consumo actual al validar

### Validaciones para Reemplazar Salidas/Consumos

1. ⚠️ **Eliminar salidas/consumos existentes**: Si una salida/consumo no está en el array, se elimina
2. ⚠️ **Validar dependencias**: Antes de eliminar una salida, verificar que no tenga consumos asociados
3. ⚠️ **Validar consumos antes de eliminar outputs**: Si se elimina un output que tiene consumos, esos consumos también deben eliminarse o rechazarse

---

## 📝 Implementación Sugerida

### Archivos a Modificar/Crear

1. **`app/Http/Controllers/v2/ProductionOutputController.php`**
   - Agregar método `storeMultiple()`
   - Agregar método `updateAll()` o `syncOutputs()`

2. **`app/Http/Controllers/v2/ProductionOutputConsumptionController.php`**
   - Agregar método `storeMultiple()`
   - Agregar método `updateAll()` o `syncConsumptions()`

3. **`routes/api.php`**
   - Agregar ruta `POST /v2/production-outputs/multiple`
   - Agregar ruta `PUT /v2/production-records/{id}/outputs`
   - Agregar ruta `POST /v2/production-output-consumptions/multiple`
   - Agregar ruta `PUT /v2/production-records/{id}/parent-output-consumptions`

### Patrón de Implementación

Seguir el mismo patrón que `ProductionInputController::storeMultiple()`:
- Usar transacciones
- Validar cada elemento
- Retornar creados y errores
- No fallar toda la operación si un elemento falla (o sí, según requerimiento)

---

## ✅ Checklist de Implementación

### Para ProductionOutput

- [ ] Crear método `storeMultiple()` en `ProductionOutputController`
- [ ] Agregar validación para array de outputs
- [ ] Implementar transacción
- [ ] Agregar ruta `POST /v2/production-outputs/multiple`
- [ ] Crear método `syncOutputs()` o `updateAll()` en `ProductionOutputController`
- [ ] Implementar lógica de crear/actualizar/eliminar
- [ ] Validar dependencias antes de eliminar
- [ ] Agregar ruta `PUT /v2/production-records/{id}/outputs`
- [ ] Documentar endpoints
- [ ] Probar con casos edge

### Para ProductionOutputConsumption

- [ ] Crear método `storeMultiple()` en `ProductionOutputConsumptionController`
- [ ] Agregar validación para array de consumptions
- [ ] Implementar validaciones de negocio para cada consumo
- [ ] Validar disponibilidad total de outputs
- [ ] Implementar transacción
- [ ] Agregar ruta `POST /v2/production-output-consumptions/multiple`
- [ ] Crear método `syncConsumptions()` o `updateAll()` en `ProductionOutputConsumptionController`
- [ ] Implementar lógica de crear/actualizar/eliminar
- [ ] Validar disponibilidad antes de cambios
- [ ] Agregar ruta `PUT /v2/production-records/{id}/parent-output-consumptions`
- [ ] Documentar endpoints
- [ ] Probar con casos edge

---

## 🔗 Referencias

- `app/Http/Controllers/v2/ProductionInputController.php` - Ejemplo de `storeMultiple()`
- `app/Http/Controllers/v2/ProductionOutputController.php` - Controlador actual
- `app/Http/Controllers/v2/ProductionOutputConsumptionController.php` - Controlador actual
- `docs/25-produccion/13-Produccion-Entradas.md` - Documentación de inputs
- `docs/25-produccion/14-Produccion-Salidas.md` - Documentación de outputs
- `docs/25-produccion/15-Produccion-Consumos-Outputs-Padre.md` - Documentación de consumos

---

## 📅 Próximos Pasos

1. Revisar esta investigación con el equipo
2. Decidir si se implementan todos los endpoints o solo algunos
3. Definir comportamiento exacto de validaciones y errores
4. Implementar endpoints siguiendo el patrón establecido
5. Actualizar documentación del frontend

