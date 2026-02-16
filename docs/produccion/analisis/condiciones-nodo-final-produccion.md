# Condiciones para Nodo Final en Producción

## 📋 Resumen

Este documento explica las **condiciones exactas** que deben cumplirse para que un `ProductionRecord` (proceso) sea considerado un **nodo final** (`isFinal = true`).

---

## ✅ Condiciones Actuales (Implementadas)

**Ubicación**: `app/Models/ProductionRecord.php:95-101`

**Método**: `isFinal()`

### Condiciones que DEBEN cumplirse TODAS:

```php
public function isFinal()
{
    return $this->inputs()->count() === 0      // Condición 1: No tiene inputs de stock
        && $this->children()->count() === 0     // Condición 2: No tiene procesos hijos
        && $this->outputs()->count() > 0;      // Condición 3: Tiene al menos un output
}
```

**⚠️ IMPORTANTE**: Los nodos finales **SÍ pueden consumir outputs del padre**. Solo los nodos iniciales (raíz) no consumen del padre porque no tienen padre.

---

## 🔍 Explicación Detallada de Cada Condición

### Condición 1: No tiene Inputs de Stock
```php
$this->inputs()->count() === 0
```

**Significado**: El proceso **NO** tiene cajas del stock asignadas directamente.

**Relación**: `ProductionInput` (cajas consumidas desde stock/palets)

**Ejemplo**:
- ❌ **NO es final**: Si tiene cajas asignadas desde palets
- ✅ **Puede ser final**: Si no tiene cajas asignadas directamente

---

### Condición 2: No tiene Procesos Hijos
```php
$this->children()->count() === 0
```

**Significado**: El proceso **NO** tiene procesos hijos (subprocesos).

**Relación**: `ProductionRecord` con `parent_record_id = this.id`

**Ejemplo**:
- ❌ **NO es final**: Si tiene procesos hijos que transforman sus outputs
- ✅ **Puede ser final**: Si no tiene procesos hijos

**Importante**: Esta es la condición más crítica. Un proceso con hijos **NUNCA** puede ser final, porque sus outputs serán transformados por los hijos.

---

### ⚠️ Nota sobre Consumos del Padre

**Los nodos finales SÍ pueden consumir outputs del padre**. Esta condición fue **ELIMINADA** porque:

- Los nodos iniciales (raíz) no tienen padre, por lo que no pueden consumir del padre
- Los nodos finales (no raíz) **deben** consumir outputs del padre para obtener su materia prima
- Un nodo puede ser final aunque consuma del padre, siempre que no tenga inputs de stock ni procesos hijos

---

### Condición 3: Tiene al menos un Output
```php
$this->outputs()->count() > 0
```

**Significado**: El proceso **SÍ** produce al menos un output (producto).

**Relación**: `ProductionOutput` (productos producidos)

**Ejemplo**:
- ❌ **NO es final**: Si no tiene outputs (no produce nada)
- ✅ **Puede ser final**: Si tiene al menos un output

---

## 📊 Tabla de Combinaciones

| Inputs Stock | Consumos Padre | Procesos Hijos | Outputs | ¿Es Final? | Explicación |
|--------------|---------------|----------------|---------|------------|-------------|
| 0 | 0 | 0 | > 0 | ✅ **SÍ** | Nodo final válido (raíz) |
| 0 | > 0 | 0 | > 0 | ✅ **SÍ** | Nodo final válido (consume del padre) |
| > 0 | 0 | 0 | > 0 | ❌ **NO** | Tiene inputs de stock |
| > 0 | > 0 | 0 | > 0 | ❌ **NO** | Tiene inputs de stock |
| 0 | 0 | > 0 | > 0 | ❌ **NO** | Tiene procesos hijos |
| 0 | > 0 | > 0 | > 0 | ❌ **NO** | Tiene procesos hijos |
| 0 | 0 | 0 | 0 | ❌ **NO** | No produce outputs |
| 0 | > 0 | 0 | 0 | ❌ **NO** | No produce outputs |

---

## 🎯 Casos de Uso Reales

### Caso 1: Proceso Final Válido (Raíz) ✅
```
Proceso: "Producción Directa" (raíz)
- Inputs de stock: 0
- Consumos del padre: 0 (es raíz, no tiene padre)
- Procesos hijos: 0
- Outputs: 1 (Producto final)
Resultado: isFinal = true, isRoot = true
```

### Caso 1b: Proceso Final Válido (Consume del Padre) ✅
```
Proceso: "Envasado Final" (hijo)
- Inputs de stock: 0
- Consumos del padre: 1 (consume output de "Fileteado")
- Procesos hijos: 0
- Outputs: 1 (Producto envasado)
Resultado: isFinal = true, isRoot = false
```

### Caso 2: Proceso con Inputs de Stock ❌
```
Proceso: "Fileteado"
- Inputs de stock: 5 cajas
- Consumos del padre: 0
- Procesos hijos: 0
- Outputs: 1
Resultado: isFinal = false (tiene inputs de stock)
```

### Caso 3: Proceso que Consume del Padre y es Final ✅
```
Proceso: "Envasado al Vacío"
- Inputs de stock: 0
- Consumos del padre: 1 (consume output de "Fileteado")
- Procesos hijos: 0
- Outputs: 1
Resultado: isFinal = true (puede consumir del padre y ser final)
```

### Caso 4: Proceso con Hijos ❌
```
Proceso: "Fileteado"
- Inputs de stock: 0
- Consumos del padre: 0
- Procesos hijos: 2 ("Envasado A", "Envasado B")
- Outputs: 1
Resultado: isFinal = false (tiene procesos hijos)
```

### Caso 5: Proceso Intermedio (No es Final) ❌
```
Proceso: "Fileteado"
- Inputs de stock: 0
- Consumos del padre: 1 (consume output del padre)
- Procesos hijos: 2 ("Envasado A", "Envasado B")
- Outputs: 1
Resultado: isFinal = false (tiene procesos hijos)
```

---

## ⚠️ Cambios Recientes

### Antes (Versión Anterior)
La documentación mencionaba que `isFinal()` solo verificaba:
```php
return $this->inputs()->count() === 0 && $this->outputs()->count() > 0;
```

### Ahora (Versión Actual - CORREGIDA)
**Condiciones actuales** (3 condiciones):
1. ✅ No tiene inputs de stock
2. ✅ No tiene procesos hijos
3. ✅ Tiene al menos un output

**Cambio importante**: Se **ELIMINÓ** la condición de `parentOutputConsumptions()` porque:
- Los nodos finales **SÍ pueden consumir del padre** (excepto si son raíz)
- Solo los nodos iniciales (raíz) no consumen del padre porque no tienen padre
- Un nodo puede ser final aunque consuma outputs del padre, siempre que no tenga inputs de stock ni procesos hijos

---

## 🔄 Relación con Otros Conceptos

### `isRoot` vs `isFinal`

| Concepto | Condición | Significado |
|----------|-----------|-------------|
| `isRoot` | `parent_record_id === null` | Es el inicio del flujo |
| `isFinal` | 3 condiciones (ver arriba) | Es el final del flujo |

**Pueden combinarse**:
- `isRoot = true, isFinal = true`: Proceso que inicia y termina el flujo (solo produce, no consume)
- `isRoot = true, isFinal = false`: Proceso raíz que consume y produce
- `isRoot = false, isFinal = true`: Proceso hijo que es final
- `isRoot = false, isFinal = false`: Proceso intermedio

---

## 📝 Ejemplos de Código

### Verificar si un Proceso es Final

```php
$productionRecord = ProductionRecord::find($id);

if ($productionRecord->isFinal()) {
    echo "Este proceso es final";
    echo "Inputs: " . $productionRecord->inputs()->count();
    echo "Consumos padre: " . $productionRecord->parentOutputConsumptions()->count();
    echo "Hijos: " . $productionRecord->children()->count();
    echo "Outputs: " . $productionRecord->outputs()->count();
}
```

### Filtrar Solo Procesos Finales

```php
$finalRecords = ProductionRecord::whereHas('outputs')
    ->whereDoesntHave('inputs')
    ->whereDoesntHave('children')
    ->get();
```

**Nota**: No se filtra por `parentOutputConsumptions` porque los nodos finales pueden consumir del padre.

---

## 🚨 Consideraciones Importantes

### 1. Orden de Evaluación
Las condiciones se evalúan con `&&` (AND lógico), por lo que **TODAS** deben cumplirse.

### 2. Rendimiento
Cada condición ejecuta una query a la base de datos. Si necesitas verificar múltiples procesos, usa eager loading:

```php
$records = ProductionRecord::with([
    'inputs',
    'parentOutputConsumptions',
    'children',
    'outputs'
])->get();

foreach ($records as $record) {
    if ($record->isFinal()) {
        // ...
    }
}
```

### 3. Estados Temporales
Un proceso puede cambiar de `isFinal = false` a `isFinal = true` cuando:
- Se eliminan todos sus inputs de stock
- Se eliminan todos sus procesos hijos
- Se agrega al menos un output

**Nota**: Los consumos del padre NO afectan si un proceso es final o no.

---

## 📚 Referencias

- Modelo: `app/Models/ProductionRecord.php`
- Método: `isFinal()` (líneas 95-101)
- Documentación: `docs/produccion/12-Produccion-Procesos.md`
- Resource: `app/Http/Resources/v2/ProductionRecordResource.php`

---

**Última actualización**: 2025-01-27  
**Versión del código**: Implementación actual con 3 condiciones (se eliminó la condición de `parentOutputConsumptions`)

