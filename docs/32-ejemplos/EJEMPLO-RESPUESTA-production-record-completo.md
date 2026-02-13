# Ejemplo Completo: GET Production Record con Consumos

**Endpoint**: `GET /v2/production-records/{id}`  
**Fecha**: 2025-01-27  
**Estado**: ✅ Actualizado - Ahora incluye consumos

---

## 📋 Resumen

Este ejemplo muestra la respuesta completa del endpoint `GET /v2/production-records/{id}` **después de la actualización** que incluye los consumos (`parentOutputConsumptions`).

---

## 🎯 Estructura de la Respuesta

### Datos Básicos del Record

```json
{
  "id": 5,
  "productionId": 1,
  "parentRecordId": 2,
  "processId": 4,
  "startedAt": "2025-01-27T10:00:00Z",
  "finishedAt": "2025-01-27T12:30:00Z",
  "notes": "Proceso de envasado con control de calidad estricto",
  "isRoot": false,
  "isFinal": true,
  "isCompleted": true
}
```

### Relaciones Cargadas

- **production**: Información del lote de producción
- **parent**: Proceso padre (si tiene)
- **process**: Tipo de proceso
- **inputs**: Cajas del stock asignadas al proceso
- **parentOutputConsumptions**: ✨ **NUEVO** - Consumos de outputs del padre
- **outputs**: Productos producidos por el proceso
- **children**: Procesos hijos (vacío en este ejemplo)

---

## 📊 Secciones Detalladas

### 1. Inputs (Entradas desde Stock)

**Tipo**: Cajas directamente asignadas desde el almacén

```json
"inputs": [
  {
    "id": 10,
    "productionRecordId": 5,
    "boxId": 101,
    "box": {
      "id": 101,
      "lot": "LOTE-2025-001",
      "netWeight": 25.5,
      "grossWeight": 27.0,
      "product": {
        "id": 15,
        "name": "Filete de Merluza"
      }
    },
    "product": {
      "id": 15,
      "name": "Filete de Merluza"
    },
    "lot": "LOTE-2025-001",
    "weight": 25.5
  }
]
```

**Características**:
- Cada input representa una caja física del stock
- Incluye información completa de la caja (`box`)
- Incluye el producto de la caja
- El peso viene del `netWeight` de la caja

---

### 2. Parent Output Consumptions ✨ NUEVO

**Tipo**: Consumos de outputs producidos por el proceso padre

```json
"parentOutputConsumptions": [
  {
    "id": 1,
    "productionRecordId": 5,
    "productionOutputId": 8,
    "consumedWeightKg": 200.0,
    "consumedBoxes": 8,
    "notes": "Consumo parcial del output del proceso padre",
    "productionOutput": {
      "id": 8,
      "productionRecordId": 2,
      "productId": 15,
      "product": {
        "id": 15,
        "name": "Filete de Merluza"
      },
      "lotId": "LOTE-2025-001",
      "boxes": 20,
      "weightKg": 500.0,
      "averageWeightPerBox": 25.0
    },
    "product": {
      "id": 15,
      "name": "Filete de Merluza"
    },
    "parentRecord": {
      "id": 2,
      "process": {
        "id": 3,
        "name": "Fileteado"
      }
    },
    "isComplete": false,
    "isPartial": true,
    "weightConsumptionPercentage": 40.0,
    "boxesConsumptionPercentage": 40.0,
    "outputTotalWeight": 500.0,
    "outputTotalBoxes": 20,
    "outputAvailableWeight": 300.0,
    "outputAvailableBoxes": 12
  }
]
```

**Características**:
- Muestra qué outputs del proceso padre se consumieron
- Incluye información completa del output consumido
- Indica si el consumo es completo o parcial
- Muestra porcentajes de consumo
- Muestra disponibilidad restante del output

**Campos importantes**:
- `consumedWeightKg`: Peso consumido del output del padre
- `consumedBoxes`: Cajas consumidas del output del padre
- `productionOutput`: Información completa del output consumido
- `parentRecord`: Información del proceso padre que produjo el output
- `isComplete`: Si se consumió el 100% del output
- `isPartial`: Si se consumió parcialmente
- `outputAvailableWeight`: Peso disponible restante del output

---

### 3. Outputs (Salidas - Productos Producidos)

**Tipo**: Productos producidos por este proceso

```json
"outputs": [
  {
    "id": 20,
    "productionRecordId": 5,
    "productId": 25,
    "product": {
      "id": 25,
      "name": "Filete de Merluza Envasado al Vacío"
    },
    "lotId": "LOTE-2025-001",
    "boxes": 10,
    "weightKg": 240.0,
    "averageWeightPerBox": 24.0
  }
]
```

**Características**:
- Productos finales producidos por el proceso
- Incluye información del producto
- Incluye cantidad de cajas y peso total

---

### 4. Totales Calculados

```json
{
  "totalInputWeight": 500.0,      // Suma de: inputs de stock + consumos del padre
  "totalOutputWeight": 480.0,     // Suma de todos los outputs
  "totalInputBoxes": 20,          // Suma de: cajas de stock + cajas consumidas
  "totalOutputBoxes": 16,         // Suma de todas las cajas de outputs
  "waste": 20.0,                  // Pérdida: input - output
  "wastePercentage": 4.0,         // Porcentaje de merma
  "yield": 0,                     // Ganancia (0 si hay pérdida)
  "yieldPercentage": 0            // Porcentaje de rendimiento
}
```

**Cálculo de totales**:
- `totalInputWeight` = Suma de pesos de `inputs` + Suma de `consumedWeightKg` de `parentOutputConsumptions`
- `totalInputBoxes` = Cantidad de `inputs` + Suma de `consumedBoxes` de `parentOutputConsumptions`
- `totalOutputWeight` = Suma de `weightKg` de `outputs`
- `totalOutputBoxes` = Suma de `boxes` de `outputs`

---

## 🔍 Casos de Uso

### Caso 1: Proceso con Solo Inputs de Stock

Un proceso que solo consume cajas del almacén (no consume del padre):

```json
{
  "inputs": [...],                    // ✅ Tiene inputs
  "parentOutputConsumptions": [],     // ❌ No tiene consumos
  "outputs": [...]
}
```

### Caso 2: Proceso que Solo Consume del Padre

Un proceso hijo que solo consume outputs del proceso padre (nodo final):

```json
{
  "inputs": [],                       // ❌ No tiene inputs de stock
  "parentOutputConsumptions": [...],  // ✅ Tiene consumos del padre
  "outputs": [...],
  "isFinal": true
}
```

### Caso 3: Proceso con Ambos Tipos de Inputs

Un proceso que consume tanto del stock como del padre:

```json
{
  "inputs": [...],                    // ✅ Inputs de stock
  "parentOutputConsumptions": [...],  // ✅ Consumos del padre
  "outputs": [...]
}
```

---

## 📝 Notas Importantes

1. **Los consumos ahora se devuelven**: Antes solo se usaban en los cálculos, ahora se devuelven como array completo

2. **Totales incluyen consumos**: Los totales (`totalInputWeight`, `totalInputBoxes`) incluyen tanto inputs de stock como consumos del padre

3. **Consumos opcionales**: Si el proceso no consume del padre, `parentOutputConsumptions` será un array vacío `[]`

4. **Información completa**: Cada consumo incluye información del output consumido y del proceso padre

5. **Disponibilidad**: Los consumos muestran cuánto queda disponible del output del padre

---

## 🔗 Referencias

- **Controlador**: `app/Http/Controllers/v2/ProductionRecordController.php`
- **Resource**: `app/Http/Resources/v2/ProductionRecordResource.php`
- **Consumption Resource**: `app/Http/Resources/v2/ProductionOutputConsumptionResource.php`
- **Documentación**: `docs/25-produccion/12-Produccion-Procesos-ENDPOINT-GET.md`

---

**Última actualización**: 2025-01-27

