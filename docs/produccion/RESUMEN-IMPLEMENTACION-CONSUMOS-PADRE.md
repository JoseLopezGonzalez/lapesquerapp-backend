# Resumen de Implementación: Consumos de Outputs del Padre

## ✅ Cambios Completados

### 1. Base de Datos

**Nueva tabla**: `production_output_consumptions`

**Archivo**: `database/migrations/companies/2025_12_01_220536_create_production_output_consumptions_table.php`

**Campos principales**:
- `production_record_id`: Proceso hijo que consume
- `production_output_id`: Output del padre consumido
- `consumed_weight_kg`: Peso consumido
- `consumed_boxes`: Cajas consumidas
- `notes`: Notas opcionales

**Constraints**:
- Unique: Un proceso solo puede consumir un output una vez
- Foreign keys con cascade delete

---

### 2. Modelos

#### Nuevo Modelo: `ProductionOutputConsumption`

**Archivo**: `app/Models/ProductionOutputConsumption.php`

**Relaciones**:
- `productionRecord()` - Proceso que consume
- `productionOutput()` - Output consumido

**Métodos calculados**:
- `isComplete()` - Verificar si consume todo el output
- `isPartial()` - Verificar si es consumo parcial
- `getWeightConsumptionPercentageAttribute()` - Porcentaje consumido
- `getBoxesConsumptionPercentageAttribute()` - Porcentaje de cajas consumidas

#### Actualizado: `ProductionRecord`

**Cambios**:
- Nueva relación: `parentOutputConsumptions()`
- `getTotalInputWeightAttribute()` - Ahora incluye consumos del padre
- `getTotalInputBoxesAttribute()` - Ahora incluye consumos del padre
- `getNodeData()` - Incluye `parentOutputConsumptions` en la estructura
- `buildTree()` - Carga consumos del padre
- Nuevos métodos:
  - `getStockInputs()` - Inputs desde stock
  - `getParentOutputInputs()` - Consumos del padre
  - `getAvailableParentOutputs()` - Outputs disponibles

#### Actualizado: `ProductionOutput`

**Cambios**:
- Nueva relación: `consumptions()`
- Nuevos accessors:
  - `getAvailableWeightKgAttribute()` - Peso disponible
  - `getAvailableBoxesAttribute()` - Cajas disponibles
  - `isFullyConsumed()` - Verificar si está completamente consumido
  - `isPartiallyConsumed()` - Verificar si está parcialmente consumido
  - `getConsumedWeightPercentageAttribute()` - Porcentaje consumido

---

### 3. Controladores

#### Nuevo Controlador: `ProductionOutputConsumptionController`

**Archivo**: `app/Http/Controllers/v2/ProductionOutputConsumptionController.php`

**Endpoints**:
- `GET /v2/production-output-consumptions` - Listar consumos
- `POST /v2/production-output-consumptions` - Crear consumo
- `GET /v2/production-output-consumptions/{id}` - Mostrar consumo
- `PUT /v2/production-output-consumptions/{id}` - Actualizar consumo
- `DELETE /v2/production-output-consumptions/{id}` - Eliminar consumo
- `GET /v2/production-output-consumptions/available-outputs/{productionRecordId}` - Outputs disponibles

**Validaciones implementadas**:
- El proceso debe tener un padre
- El output debe pertenecer al proceso padre directo
- No puede haber consumo duplicado del mismo output
- El consumo no puede exceder el output disponible

---

### 4. Resources

#### Nuevo Resource: `ProductionOutputConsumptionResource`

**Archivo**: `app/Http/Resources/v2/ProductionOutputConsumptionResource.php`

**Información incluida**:
- Datos del consumo
- Información del output consumido
- Información del proceso que consume
- Información del proceso padre
- Valores calculados (porcentajes, disponibilidad)

---

### 5. Rutas

**Archivo**: `routes/api.php`

**Rutas agregadas**:
```php
Route::apiResource('production-output-consumptions', ProductionOutputConsumptionController::class);
Route::get('production-output-consumptions/available-outputs/{productionRecordId}', 
    [ProductionOutputConsumptionController::class, 'getAvailableOutputs']);
```

---

### 6. Documentación

#### Nuevos Documentos

1. **15-Produccion-Consumos-Outputs-Padre.md**
   - Documentación completa del modelo
   - Estructura de base de datos
   - Relaciones
   - Endpoints y ejemplos

2. **FRONTEND-Consumos-Outputs-Padre.md**
   - Guía de integración para frontend
   - Ejemplos de código
   - Recomendaciones de UI/UX
   - Checklist de implementación

3. **RESUMEN-IMPLEMENTACION-CONSUMOS-PADRE.md** (este documento)
   - Resumen ejecutivo de cambios

#### Documentos Actualizados

1. **10-Produccion-General.md**
   - Agregada sección sobre `ProductionOutputConsumption`
   - Actualizado flujo de trabajo
   - Actualizadas relaciones entre entidades
   - Agregadas rutas nuevas

---

## 📊 Impacto en Funcionalidad Existente

### Cálculos de Totales

**Antes**:
- `total_input_weight` = Solo sumaba cajas del stock

**Ahora**:
- `total_input_weight` = Cajas del stock + Consumos del padre

### Estructura de Datos

**Antes**:
```json
{
    "inputs": [...],  // Solo cajas del stock
    "totals": {
        "inputWeight": 100.00  // Solo stock
    }
}
```

**Ahora**:
```json
{
    "inputs": [...],  // Cajas del stock
    "parentOutputConsumptions": [...],  // Consumos del padre
    "totals": {
        "inputWeight": 250.00  // Stock + padre
    }
}
```

### Validaciones

Se agregaron validaciones para:
- Solo procesos hijos pueden consumir outputs del padre
- El output debe pertenecer al proceso padre directo
- No se puede exceder el output disponible
- Un proceso solo puede consumir un output una vez

---

## 🔄 Compatibilidad con Datos Existentes

✅ **Totalmente compatible**: Los procesos existentes sin consumos del padre seguirán funcionando normalmente. Los cálculos se adaptan automáticamente.

✅ **Sin migración de datos**: No se requieren cambios en datos existentes.

✅ **Backward compatible**: Todas las funcionalidades existentes siguen funcionando.

---

## 📝 Próximos Pasos

### Backend
- [x] Migración creada
- [x] Modelos creados/actualizados
- [x] Controlador creado
- [x] Resource creado
- [x] Rutas agregadas
- [x] Documentación actualizada

### Frontend (Por implementar)
- [ ] Agregar endpoints a la API client
- [ ] Crear componente para consumir outputs del padre
- [ ] Actualizar vista de proceso para mostrar dos tipos de inputs
- [ ] Agregar validaciones de disponibilidad
- [ ] Actualizar cálculos de totales
- [ ] Agregar indicadores visuales

### Testing
- [ ] Tests unitarios para el modelo
- [ ] Tests de integración para el controlador
- [ ] Tests de validaciones
- [ ] Tests de cálculos

---

## 🚀 Cómo Usar

### Ejemplo de Uso Completo

```php
// 1. Crear proceso padre
$parentRecord = ProductionRecord::create([...]);

// 2. Registrar output del padre
$output = ProductionOutput::create([
    'production_record_id' => $parentRecord->id,
    'product_id' => 10,
    'weight_kg' => 300.00,
    'boxes' => 20
]);

// 3. Crear proceso hijo
$childRecord = ProductionRecord::create([
    'parent_record_id' => $parentRecord->id,
    ...
]);

// 4. Consumir output del padre
$consumption = ProductionOutputConsumption::create([
    'production_record_id' => $childRecord->id,
    'production_output_id' => $output->id,
    'consumed_weight_kg' => 150.50,
    'consumed_boxes' => 10
]);

// 5. Los cálculos ahora incluyen el consumo
$childRecord->total_input_weight; // Incluye 150.50kg del padre
```

---

## 📚 Archivos Creados/Modificados

### Nuevos Archivos

1. `database/migrations/companies/2025_12_01_220536_create_production_output_consumptions_table.php`
2. `app/Models/ProductionOutputConsumption.php`
3. `app/Http/Controllers/v2/ProductionOutputConsumptionController.php`
4. `app/Http/Resources/v2/ProductionOutputConsumptionResource.php`
5. `docs/produccion/15-Produccion-Consumos-Outputs-Padre.md`
6. `docs/produccion/FRONTEND-Consumos-Outputs-Padre.md`
7. `docs/produccion/RESUMEN-IMPLEMENTACION-CONSUMOS-PADRE.md`

### Archivos Modificados

1. `app/Models/ProductionRecord.php`
2. `app/Models/ProductionOutput.php`
3. `routes/api.php`
4. `docs/produccion/10-Produccion-General.md`

---

## ✅ Checklist de Verificación

- [x] Migración creada y probada
- [x] Modelo `ProductionOutputConsumption` creado
- [x] Modelo `ProductionRecord` actualizado
- [x] Modelo `ProductionOutput` actualizado
- [x] Controlador creado con todas las validaciones
- [x] Resource creado
- [x] Rutas agregadas
- [x] Documentación técnica creada
- [x] Documentación para frontend creada
- [x] Sin errores de linting
- [x] Compatibilidad con datos existentes garantizada

---

**Fecha de implementación**: 2025-12-01
**Estado**: ✅ Implementación completa

