# 📊 Análisis: Nodos de Re-procesados y Faltantes

**Fecha**: 2025-01-27  
**Propuesta**: Crear **DOS nodos adicionales** que muestren:
1. **Nodo de Re-procesados**: Cajas usadas como materia prima en otro proceso
2. **Nodo de Faltantes**: Cajas que realmente faltan (no están en venta, stock, ni fueron consumidas)  
**Estado**: ✅ **DECISIÓN TOMADA** - Separar en dos nodos

---

## 💡 Concepto Propuesto - DOS NODOS SEPARADOS

### Nodo 1: Re-procesados / Consumidos

Cajas que fueron usadas como **materia prima** en otro proceso de producción:
- Tienen `isAvailable = false` (no están disponibles porque fueron consumidas)
- Tienen un registro en `production_inputs` (fueron usadas en otro proceso)
- **Tienen un destino claro**: otro proceso de producción

### Nodo 2: Faltantes / No Contabilizados

Cajas que **realmente faltan** o no están contabilizadas:
- Tienen `isAvailable = true` (están disponibles)
- NO están en venta (sin pedido)
- NO están en stock (sin almacén)
- NO fueron consumidas (sin `production_inputs`)
- **Estado desconocido**: perdidas, error de registro, etc.

---

## 🎯 Objetivo

Completar la **trazabilidad del 100%** de los productos producidos:

```
Producto Producido (en nodo final)
  - Producto en Venta
  - Producto en Stock
  - Producto Re-procesado (usado en otro proceso)
  - Producto Faltante (no contabilizado)
  = Trazabilidad Completa
```

---

## 🎯 Objetivo

Identificar **discrepancias** o **productos faltantes** entre:
- Lo que se registró como producido (`ProductionOutput`)
- Lo que realmente está contabilizado (en venta o stock)

---

## ✅ Ventajas de Implementar Este Nodo

1. **🔍 Trazabilidad Completa**
   - Permite ver el 100% del flujo del producto
   - Identifica dónde hay "huecos" en el inventario

2. **⚠️ Detección de Problemas**
   - Productos perdidos o no registrados
   - Errores de contabilización
   - Cajas en tránsito sin ubicación

3. **📊 Visibilidad del Estado Real**
   - Muestra la diferencia entre producción teórica y física
   - Ayuda a identificar problemas operativos

4. **🎯 Consistencia con la Estructura Actual**
   - Mismo patrón que nodos de venta/stock
   - Se integra naturalmente en el árbol

---

## ⚠️ Desafíos y Consideraciones

### 1. **¿Qué significa "no contabilizado"?**

Hay varios escenarios posibles:

| Escenario | Descripción | ¿Incluir en "no contabilizado"? |
|-----------|-------------|--------------------------------|
| **A. Cajas consumidas** | Usadas en otro proceso de producción | ❓ ¿Sí o No? |
| **B. Cajas en tránsito** | Sin palet ni ubicación asignada | ✅ Probablemente SÍ |
| **C. Cajas desperdiciadas** | Perdidas o destruidas | ✅ Probablemente SÍ |
| **D. Error de registro** | Producción teórica vs realidad | ✅ Sí, para detectar |
| **E. Cajas de otro lote** | Mismo producto, lote diferente | ❓ ¿Cómo tratarlo? |

### 2. **Cálculo de lo Producido**

**Pregunta clave**: ¿Qué datos usar como base?

- ✅ **Opción A**: `ProductionOutput.weight_kg` y `ProductionOutput.boxes` (producción teórica registrada)
- ❓ **Opción B**: Suma de todas las cajas físicas del lote (producción real)
- ❓ **Opción C**: Solo cajas disponibles (`isAvailable = true`)

**Recomendación**: Empezar con **Opción A** (producción teórica) porque es lo que está en el nodo final.

### 3. **Filtrado por Lote**

**Pregunta**: ¿Solo contar cajas del mismo lote?

- ✅ **Sí**: Solo cajas con `Box.lot = ProductionOutput.lot_id`
- ❌ **No**: Todas las cajas del producto (más complejo, menos preciso)

**Recomendación**: **Sí, filtrar por lote** (igual que en nodos de venta/stock).

### 4. **Cajas Consumidas en Otros Procesos**

**Situación**: Las cajas pueden ser usadas como input en otro proceso de producción.

- Si se incluyen: El nodo mostrará cajas que fueron transformadas (esperado)
- Si se excluyen: Solo mostrará cajas "perdidas" o sin ubicación

**Recomendación**: **Opcional** - Permitir configurar si incluir o no cajas consumidas.

### 5. **Estructura del Nodo**

**Pregunta**: ¿Cómo estructurar la información?

**Opción 1: Simple (diferencia total)**
```json
{
  "type": "unaccounted",
  "id": "unaccounted-2",
  "products": [
    {
      "product": {...},
      "produced": { "boxes": 100, "weight": 1000 },
      "inSales": { "boxes": 50, "weight": 500 },
      "inStock": { "boxes": 30, "weight": 300 },
      "missing": { "boxes": 20, "weight": 200 }
    }
  ]
}
```

**Opción 2: Detallado (con estados)**
```json
{
  "type": "unaccounted",
  "id": "unaccounted-2",
  "products": [
    {
      "product": {...},
      "produced": {...},
      "inSales": {...},
      "inStock": {...},
      "missing": {...},
      "states": [
        { "state": "in_transit", "boxes": 10, "weight": 100 },
        { "state": "consumed", "boxes": 5, "weight": 50 },
        { "state": "unknown", "boxes": 5, "weight": 50 }
      ]
    }
  ]
}
```

**Recomendación**: Empezar con **Opción 1** (simple) y luego extender si es necesario.

---

## 🤔 Preguntas para Resolver

### 1. **¿Qué nombre usar para el nodo?**

- `unaccounted` - No contabilizado
- `missing` - Faltante
- `pending` - Pendiente
- `discrepancy` - Discrepancia
- `unregistered` - No registrado

**Recomendación**: `unaccounted` (descriptivo y claro).

### 2. **¿Cuándo mostrar el nodo?**

- ¿Solo si hay diferencia? (si `missing > 0`)
- ¿Siempre? (para mostrar balance completo)
- ¿Solo si diferencia > X%? (para evitar ruido)

**Recomendación**: **Solo si hay diferencia** (`missing > 0`).

### 3. **¿Qué hacer con cajas consumidas?**

- **Opción A**: Incluirlas en "no contabilizado" (están "faltando" del stock)
- **Opción B**: Excluirlas (tienen un destino claro: otro proceso)
- **Opción C**: Mostrarlas separadas (en `states`)

**Recomendación**: **Opción C** - Mostrarlas como un estado separado para trazabilidad completa.

### 4. **¿Qué hacer con cajas sin ubicación?**

Cajas del lote que:
- Están disponibles (`isAvailable = true`)
- NO están en un palet
- NO tienen pedido ni almacén

**Recomendación**: Incluirlas en "no contabilizado" como estado "in_transit" o "without_location".

---

## 📐 Estructura Propuesta

### Nodo de "No Contabilizado" por Nodo Final

```json
{
  "type": "unaccounted",
  "id": "unaccounted-{finalNodeId}",
  "parentRecordId": {finalNodeId},
  "productionId": 1,
  "products": [
    {
      "product": {
        "id": 5,
        "name": "Filetes de Atún"
      },
      "produced": {
        "boxes": 100,
        "weight": 1000.0
      },
      "inSales": {
        "boxes": 50,
        "weight": 500.0
      },
      "inStock": {
        "boxes": 30,
        "weight": 300.0
      },
      "unaccounted": {
        "boxes": 20,
        "weight": 200.0,
        "percentage": 20.0  // % del total producido
      },
      "states": [
        {
          "state": "consumed",  // Usadas en otros procesos
          "boxes": 10,
          "weight": 100.0
        },
        {
          "state": "without_location",  // Sin palet/ubicación
          "boxes": 5,
          "weight": 50.0
        },
        {
          "state": "unknown",  // No encontradas
          "boxes": 5,
          "weight": 50.0
        }
      ]
    }
  ],
  "summary": {
    "productsCount": 1,
    "totalUnaccountedBoxes": 20,
    "totalUnaccountedWeight": 200.0
  },
  "children": []
}
```

---

## 🔍 Casos de Uso

### Caso 1: Todo Contabilizado
```
Producción: 100 cajas
- En venta: 60 cajas
- En stock: 40 cajas
- No contabilizado: 0 cajas
```
**Resultado**: No se mostraría el nodo (diferencia = 0).

### Caso 2: Cajas Perdidas
```
Producción: 100 cajas
- En venta: 50 cajas
- En stock: 30 cajas
- No contabilizado: 20 cajas (10 consumidas, 10 sin ubicación)
```
**Resultado**: Se mostraría el nodo con las 20 cajas faltantes.

### Caso 3: Error de Registro
```
Producción registrada: 100 cajas
- En venta: 55 cajas
- En stock: 50 cajas
- Total encontrado: 105 cajas
- Diferencia: +5 cajas (más de lo esperado)
```
**Resultado**: ¿Mostrar como "diferencia negativa" o solo mostrar cuando falten?

---

## 💭 Mi Opinión

### ✅ **SÍ, me parece una excelente idea** por estas razones:

1. **Complementa perfectamente** los nodos de venta/stock
2. **Añade visibilidad** al 100% del flujo de productos
3. **Ayuda a detectar problemas** operativos o de registro
4. **Sigue el mismo patrón** que los nodos existentes

### ⚠️ **Pero necesitamos definir**:

1. **¿Qué significa exactamente "no contabilizado"?**
   - ¿Solo cajas perdidas?
   - ¿Incluye cajas consumidas?
   - ¿Incluye cajas en tránsito?

2. **¿Qué hacer cuando hay más cajas de las esperadas?**
   - ¿Mostrar diferencia negativa?
   - ¿Ignorar?
   - ¿Generar alerta?

3. **¿Cómo manejar múltiples estados?**
   - ¿Un solo valor de "faltante"?
   - ¿Desglose por estado (consumido, tránsito, perdido)?

### 🎯 **Recomendación de Implementación**:

**Fase 1 (MVP - Mínimo Viable)**:
- Nodo simple que muestre diferencia total
- Solo cajas del mismo lote
- Solo cuando haya diferencia positiva (faltantes)
- Excluir cajas consumidas (tienen destino claro)

**Fase 2 (Avanzado)**:
- Desglose por estados (consumido, tránsito, desconocido)
- Incluir opción para mostrar cajas consumidas
- Manejar diferencias negativas (más de lo esperado)
- Alertas cuando diferencia > X%

---

## 📋 Checklist para Implementación

- [ ] Definir qué significa "no contabilizado"
- [ ] Decidir si incluir cajas consumidas
- [ ] Definir cómo manejar diferencias negativas
- [ ] Elegir nombre del nodo (`unaccounted`, `missing`, etc.)
- [ ] Definir estructura del nodo (simple vs detallada)
- [ ] Decidir cuándo mostrar el nodo (siempre vs solo si hay diferencia)
- [ ] Definir cómo calcular lo producido (teórico vs real)
- [ ] Decidir filtros de lote y disponibilidad
- [ ] Crear documentación de diseño
- [ ] Implementar en backend
- [ ] Actualizar documentación frontend
- [ ] Probar con datos reales

---

## 🤝 ¿Qué Opinas?

1. **¿Qué nombre prefieres para el nodo?**
2. **¿Qué debería mostrar exactamente?**
3. **¿Qué hacer con cajas consumidas?**
4. **¿Empezamos simple o directamente detallado?**

---

**Estado**: 💡 **EN ANÁLISIS** - Esperando feedback y decisiones

