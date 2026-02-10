# Restricciones entre Entidades - Análisis Completo

## ⚠️ Estado del Documento

- **Versión**: 1.0
- **Fecha**: 2025-01-XX
- **Propósito**: Documentar todas las restricciones que deberían implementarse entre entidades del sistema para garantizar integridad de datos y coherencia del negocio.

---

## 📋 Índice

1. [Sistema de Clasificación de Peligrosidad](#sistema-de-clasificación-de-peligrosidad)
2. [Módulo: Producción](#módulo-producción)
3. [Módulo: Inventario y Almacén](#módulo-inventario-y-almacén)
4. [Módulo: Pedidos](#módulo-pedidos)
5. [Módulo: Catálogos y Maestros](#módulo-catálogos-y-maestros)
6. [Módulo: Sistema y Autenticación](#módulo-sistema-y-autenticación)
7. [Módulo: Recepciones y Despachos](#módulo-recepciones-y-despachos)
8. [Restricciones Transversales](#restricciones-transversales)

---

## 🔴 Sistema de Clasificación de Peligrosidad

### Niveles de Peligrosidad

- **🔴 CRÍTICO**: Corrupción de datos, pérdida de trazabilidad, inconsistencias graves. Debe implementarse inmediatamente.
- **🟠 ALTO**: Puede causar errores en reportes, cálculos incorrectos, problemas de negocio. Implementar en corto plazo.
- **🟡 MEDIO**: Mejora la calidad de datos, previene errores menores. Implementar según prioridad.
- **🟢 BAJO**: Mejora la experiencia de usuario, validaciones opcionales. Implementar cuando sea posible.

---

## 🏭 Módulo: Producción

### 1. Production (Lote de Producción)

#### 1.1. Restricciones de Integridad Referencial

| Restricción                                | Estado          | Peligrosidad | Descripción                                                                                                                                                                                                                                                                                                                                                       | Solución/Idea |
| ------------------------------------------- | --------------- | ------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | -------------- |
| `species_id` → `species.id`            | ✅ Implementada | 🟠 ALTO      | **Comportamiento actual**: Si se elimina una especie, se eliminan todas las producciones asociadas (`onDelete('cascade')`). **Problema**: Esto destruye la trazabilidad histórica. **Solución**: Cambiar a `onDelete('restrict')` para impedir eliminar especies con producciones, o `onDelete('set null')` si se permite nullable.      | restrict       |
| `capture_zone_id` → `capture_zones.id` | ✅ Implementada | 🟠 ALTO      | **Comportamiento actual**: Si se elimina una zona de captura, se eliminan todas las producciones asociadas (`onDelete('cascade')`). **Problema**: Esto destruye la trazabilidad histórica. **Solución**: Cambiar a `onDelete('restrict')` para impedir eliminar zonas con producciones, o `onDelete('set null')` si se permite nullable. | restrict       |

**✅ SOLUCIÓN IMPLEMENTADA**: Migración `2025_12_05_210315_fix_productions_foreign_keys_on_delete.php` creada para cambiar `onDelete('cascade')` a `onDelete('restrict')` en ambas foreign keys. Esto protege la trazabilidad histórica impidiendo eliminar especies o zonas de captura que tienen producciones asociadas.

#### 1.2. Restricciones de Negocio

| Restricción                               | Estado             | Peligrosidad | Descripción                                                                                                                                                                                                                                                                                                                                           | Solución/Idea |
| ------------------------------------------ | ------------------ | ------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | -------------- |
| `opened_at` ≤ `closed_at`             | ✅ Implementada | 🔴 CRÍTICO  | **Validar**: Si ambos campos tienen valor, `closed_at` debe ser mayor o igual a `opened_at`. **Impacto sin validar**: Permite cerrar un lote antes de abrirlo, generando inconsistencias temporales. **Validación**: Al establecer `closed_at`, verificar que `opened_at` existe y que `closed_at >= opened_at`.          | ok             |
| `closed_at` solo si `opened_at` existe | ✅ Implementada | 🔴 CRÍTICO  | **Validar**: No se puede establecer `closed_at` si `opened_at` es `null`. **Impacto sin validar**: Permite cerrar lotes que nunca se abrieron, generando estados inconsistentes. **Validación**: Al establecer `closed_at`, verificar que `opened_at` no sea `null`.                                                    | ok             |
| `lot` único por tenant                  | ❌ No implementada | 🟠 ALTO      | **Validar**: El campo `lot` debe ser único dentro del mismo tenant. **Impacto sin validar**: Permite crear múltiples producciones con el mismo número de lote, causando confusión y errores en reportes. **Nota**: Puede ser opcional si el negocio permite múltiples producciones con el mismo lote (ej: diferentes fechas). | ok             |
| `date` debe ser válida                  | ✅ Implementada | 🟡 MEDIO     | **Validar**: El campo `date` debe ser una fecha válida en formato correcto y dentro de un rango razonable (ej: no fechas futuras muy lejanas, no fechas anteriores a 1900). **Impacto sin validar**: Permite fechas inválidas que pueden causar errores en cálculos y reportes.                                                       | ok             |

#### 1.3. Restricciones de Estado

| Restricción                              | Estado             | Peligrosidad | Descripción                                                                                                                                                                                                                                                                                                                                                                                          | Solución/Idea |
| ----------------------------------------- | ------------------ | ------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------- |
| No modificar cuando `closed_at` != null | ✅ Implementada | 🔴 CRÍTICO  | **Validar**: Si `closed_at` tiene valor, no se pueden modificar campos del lote (excepto posiblemente `notes` o campos de solo lectura). **Impacto sin validar**: Permite modificar datos históricos de lotes cerrados, destruyendo la integridad de los registros de producción. **Validación**: Antes de cualquier `update()`, verificar que `closed_at` sea `null`. | ok             |
| No agregar procesos cuando cerrado        | ✅ Implementada | 🔴 CRÍTICO  | **Validar**: Si `closed_at` tiene valor, no se pueden crear nuevos `ProductionRecord` asociados a este lote. **Impacto sin validar**: Permite agregar procesos a lotes ya cerrados, generando inconsistencias en la trazabilidad. **Validación**: Al crear un `ProductionRecord`, verificar que `production.closed_at` sea `null`.                                       | ok             |

---

### 2. ProductionRecord (Proceso de Producción)

#### 2.1. Restricciones de Integridad Referencial

| Restricción                                      | Estado          | Peligrosidad | Descripción                                                                                                                                                                                                                                                                                                                                                                                                        | Solución/Idea                                           |
| ------------------------------------------------- | --------------- | ------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------- |
| `production_id` → `productions.id`           | ✅ Implementada | 🔴 CRÍTICO  | **Comportamiento**: Si se elimina un `Production`, se eliminan todos sus `ProductionRecord` (`onDelete('cascade')`). **Correcto**: Al eliminar un lote, tiene sentido eliminar todos sus procesos.                                                                                                                                                                                                | Correcto                                                 |
| `parent_record_id` → `production_records.id` | ✅ Implementada | 🔴 CRÍTICO  | **Comportamiento actual**: Si se elimina un `ProductionRecord` padre, se eliminan todos sus hijos (`onDelete('cascade')`). **Problema**: Esto puede eliminar procesos hijos que deberían mantenerse para trazabilidad. **Solución**: Cambiar a `onDelete('set null')` para que los hijos se conviertan en raíz, o `onDelete('restrict')` si no se permite eliminar procesos con hijos. | No, deberia de mantenerse todos los hijos pero sin padre |

**✅ SOLUCIÓN IMPLEMENTADA**: Migración `2025_12_05_210335_fix_production_records_parent_on_delete.php` creada para cambiar `onDelete('cascade')` a `onDelete('set null')` en `parent_record_id`. Esto permite que los procesos hijos se conviertan en raíz cuando se elimina el padre, manteniendo la trazabilidad.
| `process_id` → `processes.id`                | ✅ Implementada | 🟠 ALTO      | **Comportamiento**: Si se intenta eliminar un `Process` que tiene `ProductionRecord` asociados, se impide la eliminación (`onDelete('restrict')`). **Correcto**: Los procesos son catálogos maestros y no deben eliminarse si están en uso.                                                                                                                                                    | Correcto                                                 |

#### 2.2. Restricciones de Negocio

| Restricción                                                    | Estado             | Peligrosidad | Descripción                                                                                                                                                                                                                                                                                                                                                                                                                | Solución/Idea |
| --------------------------------------------------------------- | ------------------ | ------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------- |
| `parent_record_id` no puede ser el mismo `id`               | ✅ Implementada | 🔴 CRÍTICO  | **Validar**: Al establecer `parent_record_id`, verificar que sea diferente de `id`. **Impacto sin validar**: Permite que un proceso sea su propio padre, creando un ciclo directo que rompe la estructura del árbol. **Validación**: `parent_record_id != id` (si `parent_record_id` no es `null`).                                                                                           | ok             |
| `parent_record_id` debe pertenecer al mismo `production_id` | ✅ Implementada | 🔴 CRÍTICO  | **Validar**: Si `parent_record_id` tiene valor, el proceso padre debe tener el mismo `production_id` que el proceso hijo. **Impacto sin validar**: Permite que un proceso de un lote tenga como padre un proceso de otro lote, rompiendo la coherencia del árbol. **Validación**: Si `parent_record_id` existe, verificar que `parent.production_id == production_id`.                          | ok             |
| No ciclos en el árbol (validación recursiva)                  | ✅ Implementada | 🔴 CRÍTICO  | **Validar**: Al establecer `parent_record_id`, verificar recursivamente que no se cree un ciclo (el padre no puede tener como ancestro al proceso hijo). **Impacto sin validar**: Permite referencias circulares (A → B → C → A), causando loops infinitos en consultas y cálculos. **Validación**: Recorrer la cadena de padres hasta encontrar `null` o detectar el `id` del proceso actual. | ok             |
| `started_at` ≤ `finished_at`                               | ✅ Implementada | 🟠 ALTO      | **Validar**: Si ambos `started_at` y `finished_at` tienen valor, `finished_at` debe ser mayor o igual a `started_at`. **Impacto sin validar**: Permite procesos que terminan antes de iniciar, generando datos temporales inconsistentes. **Validación**: Si ambos existen, verificar `finished_at >= started_at`.                                                                             | ok             |
| `started_at` solo si `production` está abierto             | ✅ Implementada | 🟠 ALTO      | **Validar**: No se puede establecer `started_at` si el lote padre (`production`) tiene `closed_at` con valor. **Impacto sin validar**: Permite iniciar procesos en lotes ya cerrados, generando inconsistencias en el estado del lote. **Validación**: Al establecer `started_at`, verificar que `production.closed_at` sea `null`.                                                          | ok             |
| `finished_at` solo si `started_at` existe                   | ✅ Implementada | 🟠 ALTO      | **Validar**: No se puede establecer `finished_at` si `started_at` es `null`. **Impacto sin validar**: Permite finalizar procesos que nunca se iniciaron, generando estados inconsistentes. **Validación**: Al establecer `finished_at`, verificar que `started_at` no sea `null`.                                                                                                            | ok             |

#### 2.3. Restricciones de Unicidad

| Restricción                                    | Estado             | Peligrosidad | Descripción                                                                                                                                                                                                                                                                                                                                                                                    | Solución/Idea                                                                |
| ----------------------------------------------- | ------------------ | ------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------- |
| Un proceso raíz por tipo de proceso en un lote | ❌ No implementada | 🟡 MEDIO     | **Validar (opcional)**: Solo permitir un proceso raíz (`parent_record_id = null`) de cada tipo (`process_id`) por lote. **Impacto sin validar**: Permite múltiples procesos raíz del mismo tipo, lo cual puede ser válido si se procesan en diferentes fechas/horarios. **Nota**: Esta restricción es opcional y depende del negocio. Si se permite, no implementar. | No, puede haber diferentes fechas para los mismos procesos y que sean raices. |

---

### 3. ProductionInput (Entrada de Producción)

#### 3.1. Restricciones de Integridad Referencial

| Restricción                                          | Estado          | Peligrosidad | Descripción                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                           | Solución/Idea                                                                                                                                                                                                                                                                                                             |
| ----------------------------------------------------- | --------------- | ------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `production_record_id` → `production_records.id` | ✅ Implementada | 🔴 CRÍTICO  | **Comportamiento**: Si se elimina un `ProductionRecord`, se eliminan todos sus `ProductionInput` (`onDelete('cascade')`). **Correcto**: Al eliminar un proceso, tiene sentido eliminar sus entradas.                                                                                                                                                                                                                                                                                                                                 |                                                                                                                                                                                                                                                                                                                            |
| `box_id` → `boxes.id`                            | ✅ Implementada | 🔴 CRÍTICO  | **Comportamiento actual**: Si se elimina un `ProductionInput`, se elimina la `Box` asociada (`onDelete('cascade')`). **Problema**: Esto elimina la caja física cuando se elimina el registro de entrada, destruyendo la trazabilidad. **Solución**: Cambiar a `onDelete('restrict')` para impedir eliminar inputs si la caja debe mantenerse, o implementar soft delete. **Nota**: La relación debería ser que si se elimina una caja del stock, NO se elimina el input de producción (la caja ya fue consumida). | No se debe permitir que se elimine una caja de un palet si esta usandose en un input de un record.<br />Por otro lado si se elimina el input del record que afecta a una caja de un palet este palet y caja nodeben sufrir cambios ni ser eliminados.<br />Lo que se elimina es el registro del uso de la caja no la caja. |

**✅ SOLUCIÓN IMPLEMENTADA**: Migración `2025_12_05_210340_fix_production_inputs_box_on_delete.php` creada para cambiar `onDelete('cascade')` a `onDelete('restrict')` en `box_id`. Esto impide eliminar cajas que están siendo usadas en producción, protegiendo la trazabilidad. La caja se mantiene incluso si se elimina el registro de input.

#### 3.2. Restricciones de Negocio

| Restricción                                            | Estado             | Peligrosidad | Descripción                                                                                                                                                                                                                                                                                                                                                                                                                         | Solución/Idea                                                                                                                                                                                                  |
| ------------------------------------------------------- | ------------------ | ------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `box_id` único por `production_record_id`          | ✅ Implementada    | 🔴 CRÍTICO  | **Validar**: Una misma caja (`box_id`) no puede estar asociada dos veces al mismo proceso (`production_record_id`). **Ya implementado**: Existe constraint único `['production_record_id', 'box_id']`. **Correcto**: Previene duplicados en el mismo proceso.                                                                                                                                               | Una caja no puede estar asignada a más de una input de cualquier producción.<br />Solo se pueden gastar las cajas una vez para producir. <br />No tiene sentido que la gasten varios procesos o producciones |
| `box` debe estar disponible (`isAvailable = true`)  | ✅ Implementada | 🔴 CRÍTICO  | **Validar**: Al crear un `ProductionInput`, la caja asociada debe tener `isAvailable = true` (no debe tener `productionInputs` existentes). **Impacto sin validar**: Permite usar la misma caja en múltiples procesos diferentes, generando doble contabilización y pérdida de trazabilidad. **Validación**: Antes de crear `ProductionInput`, verificar que `box.productionInputs()->count() == 0`. | Efectivamente , cambiar eso                                                                                                                                                                                     |
| `box` debe existir y no estar eliminada               | ✅ Implementada | 🔴 CRÍTICO  | **Validar**: Al crear un `ProductionInput`, la caja (`box_id`) debe existir en la base de datos y no estar eliminada (si usa soft deletes). **Impacto sin validar**: Permite crear inputs con cajas inexistentes, generando referencias rotas. **Validación**: Verificar que `Box::find($box_id)` existe y no está eliminado.                                                                              | Efectivamente                                                                                                                                                                                                   |
| `production_record` debe pertenecer a un lote abierto | ✅ Implementada | 🔴 CRÍTICO  | **Validar**: Al crear un `ProductionInput`, el proceso padre (`production_record`) debe pertenecer a un lote (`production`) que tenga `closed_at = null`. **Impacto sin validar**: Permite agregar entradas a procesos de lotes ya cerrados, generando inconsistencias. **Validación**: Verificar que `productionRecord.production.closed_at` sea `null`.                                             | Corregir                                                                                                                                                                                                        |

#### 3.3. Restricciones de Unicidad

| Restricción                                  | Estado          | Peligrosidad | Descripción                                                                                                                                                                                                                                 | Solución/Idea |
| --------------------------------------------- | --------------- | ------------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------- |
| `['production_record_id', 'box_id']` único | ✅ Implementada | 🔴 CRÍTICO  | **Validar**: La combinación de `production_record_id` y `box_id` debe ser única. **Ya implementado**: Existe constraint único en la tabla. **Correcto**: Previene que una caja esté dos veces en el mismo proceso. | ok             |

---

### 4. ProductionOutput (Salida de Producción)

#### 4.1. Restricciones de Integridad Referencial

| Restricción                                          | Estado          | Peligrosidad | Descripción                                                                                                                                                                                                                                                                                                                                                                                                                                                 | Solución/Idea                                                                                                                                                                        |
| ----------------------------------------------------- | --------------- | ------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `production_record_id` → `production_records.id` | ✅ Implementada | 🔴 CRÍTICO  | **Comportamiento**: Si se elimina un `ProductionRecord`, se eliminan todos sus `ProductionOutput` (`onDelete('cascade')`). **Correcto**: Al eliminar un proceso, tiene sentido eliminar sus salidas.                                                                                                                                                                                                                                       | ok                                                                                                                                                                                    |
| `product_id` → `products.id`                     | ✅ Implementada | 🟠 ALTO      | **Comportamiento actual**: Si se elimina un `ProductionOutput`, se elimina el `Product` asociado (`onDelete('cascade')`). **Problema**: Los productos son catálogos maestros y no deben eliminarse cuando se elimina un output. **Solución**: Cambiar a `onDelete('restrict')` para impedir eliminar outputs si el producto está en uso, o mejor aún, cambiar la relación para que eliminar un output no elimine el producto. | Un producto no se puede eliminar si se esta usando en algun sitio.<br />Y obviamente si se elimina el sitio donde se esta usando no se debe bajo ningun concepto eliminar el producto |

**✅ SOLUCIÓN IMPLEMENTADA**: Migración `2025_12_05_210344_fix_production_outputs_product_on_delete.php` creada para cambiar `onDelete('cascade')` a `onDelete('restrict')` en `product_id`. Esto impide eliminar productos que son catálogos maestros cuando se elimina un output.

#### 4.2. Restricciones de Negocio

| Restricción                                                                        | Estado             | Peligrosidad | Descripción                                                                                                                                                                                                                                                                                                                                                                                                                         | Solución/Idea                                                                                                            |
| ----------------------------------------------------------------------------------- | ------------------ | ------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------- |
| `boxes` > 0                                                                       | ❌ No implementada | 🟠 ALTO      | **Validar**: El campo `boxes` debe ser mayor que 0. **Impacto sin validar**: Permite crear outputs con 0 cajas, generando datos inválidos. **Validación**: `boxes > 0` (tipo integer positivo).                                                                                                                                                                                                              | Correcto, las cajas no son obligatorias , solo los pesos                                                                  |
| `weight_kg` > 0                                                                   | ✅ Implementada | 🟠 ALTO      | **Validar**: El campo `weight_kg` debe ser mayor que 0. **Impacto sin validar**: Permite crear outputs con peso 0 o negativo, generando datos inválidos. **Validación**: `weight_kg > 0` (tipo decimal positivo).                                                                                                                                                                                            | Debe ser mayor que 0 exacto                                                                                               |
| `weight_kg` / `boxes` razonable                                                 | ✅ No aplica | 🟡 MEDIO     | **Validar**: El peso promedio por caja (`weight_kg / boxes`) debe estar en un rango razonable (ej: entre 0.5 y 50 kg por caja). **Impacto sin validar**: Permite pesos promedio anómalos que pueden indicar errores de captura. **Validación**: `(weight_kg / boxes) >= 0.5 AND (weight_kg / boxes) <= 50` (valores configurables). **Nota**: Según solución del usuario, no se limita el promedio.                                                                          | Es indiferente el promedio, no limitar                                                                                    |
| `lot_id` debe ser válido                                                         | ✅ No aplica | 🟠 ALTO      | **Validar**: Si el campo `lot_id` tiene valor, debe referenciar un lote existente y válido. **Impacto sin validar**: Permite referencias a lotes inexistentes. **Nota**: Este campo puede no usarse si el lote se obtiene desde `production_record.production.lot`. **Validación**: Si `lot_id` no es `null`, verificar que existe. **Nota**: Según solución del usuario, este campo no debe usarse.                                                                | No debe usarse, los lotes de las salidas tiran del lote de producción. Es un campo que podemos eliminar si es que existe |
| `production_record` debe pertenecer a un lote abierto                             | ✅ Implementada | 🔴 CRÍTICO  | **Validar**: Al crear un `ProductionOutput`, el proceso padre (`production_record`) debe pertenecer a un lote (`production`) que tenga `closed_at = null`. **Impacto sin validar**: Permite agregar salidas a procesos de lotes ya cerrados, generando inconsistencias. **Validación**: Verificar que `productionRecord.production.closed_at` sea `null`.                                             | ok                                                                                                                        |
| `product` debe tener `species_id` y `capture_zone_id` compatibles con el lote | ❌ No implementada | 🟠 ALTO      | **Validar**: El producto asociado debe tener `species_id` y `capture_zone_id` compatibles con el lote (`production.species_id` y `production.capture_zone_id`). **Impacto sin validar**: Permite producir productos de especies/zonas diferentes al lote, generando inconsistencias. **Validación**: Si el lote tiene `species_id` y `capture_zone_id`, verificar que coincidan con los del producto. | No limitar por lo pronto, pero tener en cuenta                                                                            |

#### 4.3. Restricciones de Consumo

| Restricción                            | Estado             | Peligrosidad | Descripción                                                                                                                                                                                                                                                                                                                                                                                                                                                              | Solución/Idea                                               |
| --------------------------------------- | ------------------ | ------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------ |
| `consumed_weight_kg` ≤ `weight_kg` | ✅ Implementada | 🔴 CRÍTICO  | **Validar**: La suma de todos los `consumed_weight_kg` de los `ProductionOutputConsumption` asociados a un `ProductionOutput` no debe exceder el `weight_kg` del output. **Impacto sin validar**: Permite consumir más peso del producido, generando inconsistencias en los cálculos. **Validación**: Al crear/actualizar un consumo, verificar que `sum(consumptions.consumed_weight_kg) + nuevo_consumed_weight_kg <= output.weight_kg`. **Nota**: Esta validación está implementada en el modelo `ProductionOutputConsumption` (ver sección 5.2). | ok                                                           |
| `consumed_boxes` ≤ `boxes`         | ✅ Implementada | 🔴 CRÍTICO  | **Validar**: La suma de todos los `consumed_boxes` de los `ProductionOutputConsumption` asociados a un `ProductionOutput` no debe exceder el `boxes` del output. **Impacto sin validar**: Permite consumir más cajas de las producidas, generando inconsistencias. **Validación**: Al crear/actualizar un consumo, verificar que `sum(consumptions.consumed_boxes) + nuevo_consumed_boxes <= output.boxes`. **Nota**: Esta validación está implementada en el modelo `ProductionOutputConsumption` (ver sección 5.2).                                 | ok , las cajas no deben ser un campo obligatorio de registar |

---

### 5. ProductionOutputConsumption (Consumo de Outputs)

#### 5.1. Restricciones de Integridad Referencial

| Restricción                                          | Estado          | Peligrosidad | Descripción                       | Solución/Idea |
| ----------------------------------------------------- | --------------- | ------------ | ---------------------------------- | -------------- |
| `production_record_id` → `production_records.id` | ✅ Implementada | 🔴 CRÍTICO  | `onDelete('cascade')` - Correcto |                |
| `production_output_id` → `production_outputs.id` | ✅ Implementada | 🔴 CRÍTICO  | `onDelete('cascade')` - Correcto |                |

#### 5.2. Restricciones de Negocio

| Restricción                                                                  | Estado             | Peligrosidad | Descripción                                                                                                                                                                                                                                                                                                                                                                                                                       | Solución/Idea                                    |
| ----------------------------------------------------------------------------- | ------------------ | ------------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------- |
| `production_output` debe pertenecer al `parent` del `production_record` | ✅ Implementada | 🔴 CRÍTICO  | **Validar**: Al crear un `ProductionOutputConsumption`, el `production_output` debe pertenecer al proceso padre (`production_record.parent`) del proceso que consume. **Impacto sin validar**: Permite consumir outputs de procesos que no son el padre, rompiendo la estructura del árbol. **Validación**: Verificar que `productionOutput.productionRecord.id == productionRecord.parent_record_id`. | ok                                                |
| `consumed_weight_kg` > 0                                                    | ✅ Implementada | 🟠 ALTO      | **Validar**: El campo `consumed_weight_kg` debe ser mayor que 0. **Impacto sin validar**: Permite crear consumos con peso 0, generando registros sin sentido. **Validación**: `consumed_weight_kg > 0` (tipo decimal positivo).                                                                                                                                                                             | ok                                                |
| `consumed_boxes` > 0                                                        | ✅ No aplica | 🟠 ALTO      | **Validar**: El campo `consumed_boxes` debe ser mayor que 0. **Impacto sin validar**: Permite crear consumos con 0 cajas, generando registros sin sentido. **Validación**: `consumed_boxes > 0` (tipo integer positivo). **Nota**: Según solución del usuario, las cajas no son obligatorias.                                                                                                                                                                                    | No limitar, las cajas no son obligatorias indicar |
| `consumed_weight_kg` ≤ `available_weight_kg` del output                  | ✅ Implementada | 🔴 CRÍTICO  | **Validar**: Al crear/actualizar un consumo, el `consumed_weight_kg` no debe exceder el peso disponible del output (`output.weight_kg - sum(otros_consumos.consumed_weight_kg)`). **Impacto sin validar**: Permite consumir más peso del disponible, generando inconsistencias. **Validación**: `consumed_weight_kg <= output.available_weight_kg`.                                                      | ok                                                |
| `consumed_boxes` ≤ `available_boxes` del output                          | ✅ Implementada | 🔴 CRÍTICO  | **Validar**: Al crear/actualizar un consumo, el `consumed_boxes` no debe exceder las cajas disponibles del output (`output.boxes - sum(otros_consumos.consumed_boxes)`). **Impacto sin validar**: Permite consumir más cajas de las disponibles. **Validación**: `consumed_boxes <= output.available_boxes`.                                                                                             | ok                                                |
| `['production_record_id', 'production_output_id']` único                   | ✅ Implementada    | 🔴 CRÍTICO  | **Validar**: La combinación de `production_record_id` y `production_output_id` debe ser única. **Ya implementado**: Existe constraint único en la tabla. **Correcto**: Previene que un proceso consuma el mismo output múltiples veces (si se necesita consumir más, actualizar el registro existente).                                                                                                 | ok                                                |

#### 5.3. Restricciones de Coherencia

| Restricción                                                                        | Estado             | Peligrosidad | Descripción                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            | Solución/Idea                      |
| ----------------------------------------------------------------------------------- | ------------------ | ------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------- |
| `consumed_weight_kg` / `consumed_boxes` ≈ `weight_kg` / `boxes` del output | ❌ No implementada | 🟡 MEDIO     | **Validar (opcional)**: El peso promedio por caja consumido (`consumed_weight_kg / consumed_boxes`) debe ser similar al peso promedio del output original (`weight_kg / boxes`), con una tolerancia razonable (ej: ±10%). **Impacto sin validar**: Permite consumos con proporciones anómalas que pueden indicar errores. **Nota**: Esta validación es opcional y puede ser flexible según el negocio. **Validación**: `abs((consumed_weight_kg / consumed_boxes) - (output.weight_kg / output.boxes)) / (output.weight_kg / output.boxes) <= 0.10`. | No limitar, es un campo informativo |

---

## 📦 Módulo: Inventario y Almacén

### 6. Box (Caja)

#### 6.1. Restricciones de Integridad Referencial

| Restricción                      | Estado          | Peligrosidad | Descripción                                                                                                                                                                                                                                                                                                                                             | Solución/Idea |
| --------------------------------- | --------------- | ------------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------- |
| `article_id` → `products.id` | ✅ Implementada | 🔴 CRÍTICO  | **Comportamiento actual**: No hay `onDelete` especificado en la migración. **Problema**: Si se elimina un producto, las cajas asociadas quedan con referencias rotas. **Solución**: Implementar `onDelete('restrict')` para impedir eliminar productos que tienen cajas asociadas, ya que los productos son catálogos maestros. | ok             |

**✅ SOLUCIÓN IMPLEMENTADA**: Migración `2025_12_05_212325_fix_boxes_article_id_foreign_key_on_delete.php` creada para agregar `onDelete('restrict')` en `boxes.article_id`. Esto impide eliminar productos que tienen cajas asociadas.

#### 6.2. Restricciones de Negocio

| Restricción                       | Estado             | Peligrosidad | Descripción                                                                                                                                                                                                                                                                                                                                                                        | Solución/Idea                                                                                  |
| ---------------------------------- | ------------------ | ------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------- |
| `net_weight` > 0                 | ✅ Implementada | 🟠 ALTO      | **Validar**: El campo `net_weight` debe ser mayor que 0. **Impacto sin validar**: Permite crear cajas con peso 0 o negativo, generando datos inválidos. **Validación**: `net_weight > 0` (tipo decimal positivo).                                                                                                                                           | corregir                                                                                        |
| `gross_weight` ≥ `net_weight` | ✅ Implementada | 🟠 ALTO      | **Validar**: Si ambos `gross_weight` y `net_weight` tienen valor, `gross_weight` debe ser mayor o igual a `net_weight` (el peso bruto incluye el neto más el embalaje). **Impacto sin validar**: Permite pesos brutos menores que netos, lo cual es físicamente imposible. **Validación**: Si ambos existen, verificar `gross_weight >= net_weight`. | ok, aunque gross_weight por lo pronto esta deprecado, creo que lo utilizare en un futuro        |
| `lot` no vacío                  | ✅ Implementada | 🟠 ALTO      | **Validar**: El campo `lot` debe tener un valor no vacío (no `null` ni string vacío). **Impacto sin validar**: Permite cajas sin lote, dificultando la trazabilidad. **Nota**: Puede ser opcional si se permite cajas sin lote asignado. **Validación**: `lot IS NOT NULL AND lot != ''`.                                                          | corregir                                                                                        |
| `gs1_128` único (si se usa)     | ✅ No aplica | 🟡 MEDIO     | **Validar**: Si el campo `gs1_128` tiene valor, debe ser único dentro del tenant. **Impacto sin validar**: Permite códigos GS1-128 duplicados, causando confusión en escaneo y trazabilidad. **Nota**: Solo aplicar si el sistema usa códigos GS1-128. **Validación**: Si `gs1_128` no es `null`, debe ser único por tenant. **Nota**: Según solución del usuario, no se limita.                    | Pueden existir cajas identicas con el mismo codigo . No limitar                                 |
| `article_id` debe existir        | ✅ Implementada | 🔴 CRÍTICO  | **Validar**: Al crear una caja, el `article_id` (que referencia a `products.id`) debe existir en la base de datos. **Impacto sin validar**: Permite crear cajas con productos inexistentes, generando referencias rotas. **Validación**: Verificar que `Product::find($article_id)` existe.                                                                | ok, dejar constancia que mas adelante mejoraremos el problema que hay con articulos y productos |

#### 6.3. Restricciones de Estado

| Restricción                              | Estado             | Peligrosidad | Descripción                                                                                                                                                                                                                                                                                                                                                                                                                                                  | Solución/Idea                                                                                  |
| ----------------------------------------- | ------------------ | ------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------- |
| No eliminar si tiene `productionInputs` | ✅ Implementada | 🔴 CRÍTICO  | **Validar**: No se puede eliminar una caja si tiene `ProductionInput` asociados (fue usada en producción). **Impacto sin validar**: Permite eliminar cajas que fueron consumidas en producción, destruyendo la trazabilidad histórica. **Solución**: Implementar `onDelete('restrict')` en la relación o soft delete en `Box`. **Validación**: Antes de eliminar, verificar que `box.productionInputs()->count() == 0`. | Claro                                                                                           |
| No eliminar si está en un `pallet`     | ❌ No implementada | 🟠 ALTO      | **Validar**: No se puede eliminar una caja si está asociada a un palet (tiene `PalletBox`). **Impacto sin validar**: Permite eliminar cajas que están en palets, generando palets con referencias rotas. **Solución**: Implementar `onDelete('restrict')` en `PalletBox.box_id` o eliminar primero la relación `PalletBox`. **Validación**: Antes de eliminar, verificar que `box.palletBox` sea `null`.             | No hay problemas , se puede eliminar una caja que este en un palet y desaparecera de el tambien |

---

### 7. Pallet (Palet)

#### 7.1. Restricciones de Integridad Referencial

| Restricción                        | Estado          | Peligrosidad | Descripción                                                                                                                                                                                                                                                                                                                                                                     | Solución/Idea                                                                                                                                                                                                                                       |
| ----------------------------------- | --------------- | ------------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `order_id` → `orders.id`       | ⚠️ Parcial    | 🟠 ALTO      | **Problema**: No existe foreign key explícita para `order_id` en la migración. **Impacto**: Permite referencias a pedidos inexistentes y no garantiza integridad referencial. **Solución**: Implementar FK con `onDelete('set null')` (si se permite que un palet quede sin pedido) o `onDelete('restrict')` (si un palet siempre debe tener pedido). | Pueden estar vinculados a un pedido o no, si se elimina el pedido no se debe eliminar el palet simplemente perder su vinculacion<br />Si se elimina un palet tampoco se debe eliminar un pedido simplemente el pedido tiene un palet menos vinculado |
| `status` valores válidos (1-4) | ✅ Implementada | 🟠 ALTO      | **Validar**: El campo `status` solo puede tener valores 1 (registrado), 2 (almacenado), 3 (enviado), o 4 (procesado). **Ya implementado**: Validación mediante constantes en el modelo. **Nota**: La columna fue renombrada de `state_id` a `status` para evitar resolución automática de relaciones. **Correcto**: Previene estados inválidos.                                                                                                                        | ok                                                                                                                                                                                                                                                   |

**⚠️ Problema Detectado**: No hay foreign key para `order_id`. Debe implementarse con `onDelete('set null')` o `onDelete('restrict')`.

#### 7.2. Restricciones de Negocio

| Restricción                                                                      | Estado             | Peligrosidad | Descripción                                                                                                                                                                                                                                                                                                                                                                                                           | Solución/Idea                                                              |
| --------------------------------------------------------------------------------- | ------------------ | ------------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------- |
| `status` ∈ {1, 2, 3, 4}                                                      | ✅ Implementada    | 🟠 ALTO      | **Validar**: El campo `status` solo puede tener valores 1, 2, 3 o 4. **Ya implementado**: Validación mediante constantes `STATE_REGISTERED`, `STATE_STORED`, `STATE_SHIPPED`, `STATE_PROCESSED`. **Nota**: La columna fue renombrada de `state_id` a `status`. **Correcto**: Previene estados inválidos.                                                                                                                                             | ok                                                                          |
| Si `status = 2` (almacenado), debe tener `storedPallet`                     | ❌ No implementada | 🔴 CRÍTICO  | **Validar**: Si `status = 2`, debe existir un registro `StoredPallet` asociado. **Impacto sin validar**: Permite palets marcados como almacenados sin estar realmente almacenados, generando inconsistencias. **Validación**: Si `status = 2`, verificar que `storedPallet` existe.                                                                                                     | Lo trataremos mas adelante                                                  |
| Si `status = 3` (enviado), debe tener `order_id`                            | ❌ No implementada | 🟠 ALTO      | **Validar**: Si `status = 3`, el campo `order_id` debe tener un valor no nulo. **Impacto sin validar**: Permite palets marcados como enviados sin estar asignados a un pedido, generando inconsistencias. **Validación**: Si `status = 3`, verificar que `order_id IS NOT NULL`.                                                                                                        | Lo trataremos mas adelante                                                  |
| Si `status = 4` (procesado), todas las cajas deben tener `productionInputs` | ❌ No implementada | 🟠 ALTO      | **Validar**: Si `status = 4`, todas las cajas del palet deben tener al menos un `ProductionInput` asociado (fueron consumidas en producción). **Impacto sin validar**: Permite marcar palets como procesados cuando aún tienen cajas disponibles, generando inconsistencias. **Validación**: Si `status = 4`, verificar que todas las cajas tienen `productionInputs()->count() > 0`. | Lo trateremos mas adelante                                                  |
| No puede tener cajas vacías                                                      | ❌ No implementada | 🟠 ALTO      | **Validar**: Un palet debe tener al menos una caja asociada (`PalletBox`). **Impacto sin validar**: Permite crear palets vacíos, generando datos inválidos. **Validación**: Al crear/actualizar, verificar que `palletBoxes()->count() > 0` o impedir eliminar la última caja.                                                                                                               | Lo trateremos mas adelante                                                  |
| No puede tener cajas con productos diferentes (opcional)                          | ❌ No implementada | 🟡 MEDIO     | **Validar (opcional)**: Todas las cajas de un palet deben tener el mismo producto (`article_id`). **Impacto sin validar**: Permite palets con productos mezclados, lo cual puede ser válido según el negocio. **Nota**: Esta validación es opcional y depende de las reglas de negocio. **Validación**: Si se implementa, verificar que todas las cajas tienen el mismo `article_id`.  | No limitar, pueden existir palets mezclados con muchos productos diferentes |

#### 7.3. Restricciones de Estado Transicional

| Restricción                                              | Estado             | Peligrosidad | Descripción                                                                                                                                                                                                                                                                                                                                                                                                                                            | Solución/Idea        |
| --------------------------------------------------------- | ------------------ | ------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------- |
| No cambiar de `status = 4` a otro estado              | ✅ Implementada | 🔴 CRÍTICO  | **Validar**: Si `status = 4` (procesado), no se puede cambiar a ningún otro estado (1, 2 o 3). **Impacto sin validar**: Permite revertir palets procesados, generando inconsistencias en la trazabilidad. **Validación**: Al cambiar `status`, si el valor actual es 4, impedir el cambio.                                                                                                                                  | ok                    |
| No cambiar de `status = 3` a `status = 1` o `2` | ✅ Implementada | 🟠 ALTO      | **Validar**: Si `status = 3` (enviado), no se puede cambiar a `status = 1` (registrado) o `status = 2` (almacenado). **Impacto sin validar**: Permite que palets enviados vuelvan a almacén, generando inconsistencias en el flujo de negocio. **Nota**: Puede permitirse cambiar de 3 a 4 (procesado) si se requiere. **Validación**: Al cambiar `status`, si el valor actual es 3, impedir cambiar a 1 o 2. | Lo vemos mas adelante |

---

### 8. PalletBox (Relación Palet-Caja)

#### 8.1. Restricciones de Integridad Referencial

| Restricción                    | Estado          | Peligrosidad | Descripción                                                                                                                                                                                                                                                                                                                                                                                                      | Solución/Idea                                                                                    |
| ------------------------------- | --------------- | ------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------- |
| `pallet_id` → `pallets.id` | ✅ Implementada | 🔴 CRÍTICO  | **Comportamiento actual**: Si se elimina un `Pallet`, se eliminan todos sus `PalletBox` (`onDelete('cascade')`). **Correcto**: Al eliminar un palet, tiene sentido eliminar las relaciones con cajas.                                                                                                                                                                                           | Si se elimina un palet se deben eliminar las relaciones con las cajas y las cajas                 |
| `box_id` → `boxes.id`      | ✅ Implementada | 🔴 CRÍTICO  | **Comportamiento actual**: Si se elimina un `PalletBox`, se elimina la `Box` asociada (`onDelete('cascade')`). **Problema**: Esto elimina la caja física cuando se quita del palet, lo cual puede ser incorrecto si la caja debe mantenerse. **Solución**: Cambiar a `onDelete('restrict')` para impedir eliminar la relación si la caja debe mantenerse, o implementar soft delete. | Esta bien:<br />Si se elimina un palet se deben eliminar las relaciones con las cajas y las cajas |

**⚠️ Problema Detectado**: `onDelete('cascade')` eliminará la caja cuando se elimine el palet. Esto puede ser incorrecto si se quiere mantener la caja.

**Recomendación**: Cambiar a `onDelete('restrict')` o implementar soft delete.

#### 8.2. Restricciones de Negocio

| Restricción                                              | Estado             | Peligrosidad | Descripción                                                                                                                                                                                                                                                                                                                                                                                                                                  | Solución/Idea                                                                                                                                                                                                                                       |
| --------------------------------------------------------- | ------------------ | ------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `box_id` único (una caja solo puede estar en un palet) | ✅ Implementada | 🔴 CRÍTICO  | **Validar**: Una misma caja (`box_id`) solo puede estar asociada a un palet a la vez. **Impacto sin validar**: Permite que una caja esté en múltiples palets simultáneamente, generando duplicación y confusión. **Solución**: Implementar constraint único en `box_id` o validación a nivel de aplicación. **Validación**: Antes de crear `PalletBox`, verificar que `box.palletBox` sea `null`. | correcto , solo en un palet a la vez                                                                                                                                                                                                                 |

**✅ SOLUCIÓN IMPLEMENTADA**: Migración `2025_12_05_210352_add_pallet_boxes_unique_constraints.php` creada para agregar constraint único en `box_id` y constraint único compuesto en `['pallet_id', 'box_id']`. Esto asegura que una caja solo puede estar en un palet a la vez.
| `box` no debe tener `productionInputs` al agregar     | ❌ No implementada | 🔴 CRÍTICO  | **Validar**: Al agregar una caja a un palet, la caja no debe tener `ProductionInput` asociados (no debe haber sido consumida en producción). **Impacto sin validar**: Permite agregar cajas ya consumidas a palets, generando inconsistencias en el inventario. **Validación**: Antes de crear `PalletBox`, verificar que `box.productionInputs()->count() == 0`.                                                   | No se hasta que punto afecta porque no tengo ningun sistema implementado para mover cajas entre palets.<br />Pero no creo que implique nada porque automaticamente cuando entre en el palet va a estar no disponible por pertenecer a una produccion |
| `pallet` y `box` deben pertenecer al mismo tenant     | ⚠️ Parcial       | 🔴 CRÍTICO  | **Validar**: Al crear un `PalletBox`, el palet y la caja deben pertenecer al mismo tenant. **Impacto sin validar**: Permite cruzar datos entre tenants, violando el aislamiento multi-tenant. **Validación**: Verificar que `pallet` y `box` pertenecen al mismo tenant (mediante el trait `UsesTenantConnection`).                                                                                                | Correcto, corregir                                                                                                                                                                                                                                   |

#### 8.3. Restricciones de Unicidad

| Restricción                       | Estado             | Peligrosidad | Descripción                                                                                                                                                                                                                                                                                                                                                                                                       | Solución/Idea |
| ---------------------------------- | ------------------ | ------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | -------------- |
| `['pallet_id', 'box_id']` único | ✅ Implementada | 🔴 CRÍTICO  | **Validar**: La combinación de `pallet_id` y `box_id` debe ser única. **Impacto sin validar**: Permite duplicar la misma relación palet-caja, generando datos redundantes. **Solución**: Implementar constraint único `UNIQUE(pallet_id, box_id)` en la tabla. **Nota**: Implementado en la migración `2025_12_05_210352_add_pallet_boxes_unique_constraints.php`.                                                                                                                        | ok             |
| `box_id` único (global)         | ✅ Implementada | 🔴 CRÍTICO  | **Validar**: Una misma caja (`box_id`) solo puede estar en un palet a la vez (constraint único en `box_id`). **Impacto sin validar**: Permite que una caja esté en múltiples palets. **Solución**: Implementar constraint único en `box_id` (más restrictivo que el anterior). **Nota**: Esta restricción es más fuerte que la anterior y puede ser suficiente por sí sola. | ok             |

**✅ SOLUCIÓN IMPLEMENTADA**: Incluida en la migración `2025_12_05_210352_add_pallet_boxes_unique_constraints.php`.

---

### 9. StoredPallet (Almacenamiento de Palet)

#### 9.1. Restricciones de Integridad Referencial

| Restricción                    | Estado          | Peligrosidad | Descripción                                                                                                                                                                                                                            | Solución/Idea                                                                                    |
| ------------------------------- | --------------- | ------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------- |
| `pallet_id` → `pallets.id` | ✅ Implementada | 🔴 CRÍTICO  | **Comportamiento**: Si se elimina un `Pallet`, se elimina su `StoredPallet` asociado (`onDelete('cascade')`). **Correcto**: Al eliminar un palet, tiene sentido eliminar su almacenamiento.                           | ok                                                                                                |
| `store_id` → `stores.id`   | ✅ Implementada | 🔴 CRÍTICO  | **Comportamiento**: Si se elimina un `Store`, se eliminan todos sus `StoredPallet` asociados (`onDelete('cascade')`). **Correcto**: Al eliminar un almacén, tiene sentido eliminar las relaciones de almacenamiento. | ok.<br />Aqui a su vez habria que cambiar el estado de todos los palets que contenia a registrado |

#### 9.2. Restricciones de Negocio

| Restricción                                          | Estado             | Peligrosidad | Descripción                                                                                                                                                                                                                                                                                                                                                                                                               | Solución/Idea                                                                                    |
| ----------------------------------------------------- | ------------------ | ------------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------- |
| `pallet_id` único (un palet solo en un almacén)   | ✅ Implementada | 🔴 CRÍTICO  | **Validar**: Un mismo palet (`pallet_id`) solo puede estar almacenado en un almacén a la vez. **Impacto sin validar**: Permite que un palet esté en múltiples almacenes simultáneamente, generando inconsistencias. **Solución**: Implementar constraint único en `pallet_id` en la tabla `stored_pallets`.                                                                                  | corregir                                                                                          |

**✅ SOLUCIÓN IMPLEMENTADA**: Migración `2025_12_05_210354_add_stored_pallets_unique_constraints.php` creada para agregar constraint único en `pallet_id`. Esto asegura que un palet solo puede estar almacenado en un almacén a la vez.
| `pallet.status` debe ser `2` (almacenado)       | ❌ No implementada | 🔴 CRÍTICO  | **Validar**: Al crear un `StoredPallet`, el palet asociado debe tener `status = 2` (almacenado). **Impacto sin validar**: Permite almacenar palets que no están en estado "almacenado", generando inconsistencias. **Validación**: Antes de crear `StoredPallet`, verificar que `pallet.status == 2`.                                                                                      | lo vemos mas adelante                                                                             |
| `position` único por `store_id` (si se requiere) | ❌ No implementada | 🟡 MEDIO     | **Validar (opcional)**: Si el campo `position` tiene valor, debe ser único dentro del mismo almacén (`store_id`). **Impacto sin validar**: Permite que múltiples palets tengan la misma posición en un almacén, causando confusión. **Nota**: Solo aplicar si el sistema requiere posiciones únicas. **Validación**: Si `position` no es `null`, debe ser único por `store_id`. | No limitar, las posiciones pueden tener varios elementos dentro                                   |
| `pallet` no debe tener `order_id`                 | ❌ No implementada | 🟠 ALTO      | **Validar**: Al crear un `StoredPallet`, el palet asociado no debe tener `order_id` (no debe estar asignado a un pedido). **Impacto sin validar**: Permite almacenar palets que están asignados a pedidos, generando inconsistencias en el flujo. **Validación**: Antes de crear `StoredPallet`, verificar que `pallet.order_id IS NULL`.                                                      | No limitar, un palet puiede estar vinculado a uin pedido pero aun estar en almacen antes de salir |

#### 9.3. Restricciones de Unicidad

| Restricción         | Estado             | Peligrosidad | Descripción                                                                                                                                                                                                                                                                  | Solución/Idea |
| -------------------- | ------------------ | ------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------- |
| `pallet_id` único | ✅ Implementada | 🔴 CRÍTICO  | **Validar**: El campo `pallet_id` debe ser único en la tabla `stored_pallets`. **Impacto sin validar**: Permite que un palet esté almacenado en múltiples almacenes. **Solución**: Implementar constraint único `UNIQUE(pallet_id)` en la tabla. | ok             |

**✅ SOLUCIÓN IMPLEMENTADA**: Incluida en la migración `2025_12_05_210354_add_stored_pallets_unique_constraints.php`.

---

### 10. Store (Almacén)

#### 10.1. Restricciones de Negocio

| Restricción                          | Estado             | Peligrosidad | Descripción                                                                                                                                                                                                                                                                                                                                                                                                                                                         | Solución/Idea                                     |
| ------------------------------------- | ------------------ | ------------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------- |
| `name` único por tenant            | ❌ No implementada | 🟡 MEDIO     | **Validar**: El campo `name` debe ser único dentro del mismo tenant. **Impacto sin validar**: Permite crear múltiples almacenes con el mismo nombre, causando confusión. **Validación**: `UNIQUE(tenant_id, name)` o validación a nivel de aplicación.                                                                                                                                                                                   | ok                                                 |
| `capacity` ≥ peso total almacenado | ❌ No implementada | 🟡 MEDIO     | **Validar (opcional)**: Si el campo `capacity` tiene valor, debe ser mayor o igual al peso total de todos los palets almacenados en ese almacén. **Impacto sin validar**: Permite exceder la capacidad del almacén. **Nota**: Esta validación es opcional y puede no aplicarse si la capacidad es solo informativa. **Validación**: Si `capacity` no es `null`, verificar que `sum(storedPallets.pallet.netWeight) <= capacity`. | No limitar, por lo pronto es un campo informativo. |
| `temperature` valores válidos      | ❌ No implementada | 🟢 BAJO      | **Validar (opcional)**: El campo `temperature` debe tener un formato válido (ej: número con unidad, rango válido). **Impacto sin validar**: Permite valores de temperatura inválidos que pueden causar confusión. **Nota**: Esta validación es opcional y depende de cómo se almacene la temperatura.                                                                                                                                     | No limitar aun                                     |

---

## 📋 Módulo: Pedidos

### 11. Order (Pedido)

#### 11.1. Restricciones de Integridad Referencial

| Restricción                                | Estado          | Peligrosidad | Descripción                                                                                                                                                                                                                                                                                                               | Solución/Idea                                               |
| ------------------------------------------- | --------------- | ------------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------ |
| `customer_id` → `customers.id`         | ✅ Implementada | 🔴 CRÍTICO  | **Comportamiento actual**: No hay `onDelete` especificado. **Problema**: Si se elimina un cliente, los pedidos quedan con referencias rotas. **Solución**: Implementar `onDelete('restrict')` para impedir eliminar clientes con pedidos, ya que los pedidos son registros históricos importantes. | No se puede eliminar un cliente si tiene pedidos a su nombre |
| `payment_term_id` → `payment_terms.id` | ✅ Implementada | 🟠 ALTO      | **Comportamiento actual**: No hay `onDelete` especificado. **Problema**: Si se elimina un término de pago, los pedidos quedan con referencias rotas. **Solución**: Implementar `onDelete('restrict')` o `onDelete('set null')` según si se permite que pedidos queden sin término de pago.     | Igual                                                        |
| `salesperson_id` → `salespersons.id`   | ✅ Implementada | 🟠 ALTO      | **Comportamiento actual**: No hay `onDelete` especificado. **Problema**: Si se elimina un vendedor, los pedidos quedan con referencias rotas. **Solución**: Implementar `onDelete('restrict')` o `onDelete('set null')` según si se permite que pedidos queden sin vendedor.                     | Igual                                                        |
| `transport_id` → `transports.id`       | ✅ Implementada | 🟠 ALTO      | **Comportamiento actual**: No hay `onDelete` especificado. **Problema**: Si se elimina un transporte, los pedidos quedan con referencias rotas. **Solución**: Implementar `onDelete('restrict')` o `onDelete('set null')` según si se permite que pedidos queden sin transporte.                 | Igual                                                        |
| `incoterm_id` → `incoterms.id`         | ✅ Implementada | 🟠 ALTO      | **Comportamiento actual**: No hay `onDelete` especificado. **Problema**: Si se elimina un incoterm, los pedidos quedan con referencias rotas. **Solución**: Implementar `onDelete('restrict')` o `onDelete('set null')` según si se permite que pedidos queden sin incoterm.                     | Igual                                                        |

**✅ SOLUCIÓN IMPLEMENTADA**: Migración `2025_12_05_212339_fix_orders_foreign_keys_on_delete.php` creada para agregar `onDelete('restrict')` en todas las Foreign Keys de `orders`:
- `customer_id`: `onDelete('restrict')` - No eliminar clientes con pedidos
- `payment_term_id`: `onDelete('restrict')` - No eliminar términos de pago con pedidos
- `salesperson_id`: `onDelete('restrict')` - No eliminar vendedores con pedidos
- `transport_id`: `onDelete('restrict')` - No eliminar transportes con pedidos
- `incoterm_id`: `onDelete('restrict')` - No eliminar incoterms con pedidos

#### 11.2. Restricciones de Negocio

| Restricción                          | Estado             | Peligrosidad | Descripción                                                                                                                                                                                                                                                                                                                                                                                 | Solución/Idea                                                                 |
| ------------------------------------- | ------------------ | ------------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------ |
| `entry_date` ≤ `load_date`       | ✅ Implementada | 🟠 ALTO      | **Validar**: Si ambos `entry_date` y `load_date` tienen valor, `load_date` debe ser mayor o igual a `entry_date`. **Impacto sin validar**: Permite fechas de carga anteriores a la fecha de entrada, generando inconsistencias temporales. **Validación**: Si ambos existen, verificar `load_date >= entry_date`. **Implementado**: Validación en el modelo `Order` usando evento `saving`.                                               | ok                                                                             |
| `status` valores válidos           | ✅ Implementada | 🟠 ALTO      | **Validar**: El campo `status` solo puede tener valores permitidos ('pending', 'finished', 'incident'). **Impacto sin validar**: Permite estados inválidos que pueden causar errores en el flujo de negocio. **Validación**: Verificar que `status` está en una lista de valores permitidos (enum o validación de aplicación). **Implementado**: Validación en el modelo `Order` usando constantes y evento `saving`.                         | ok                                                                             |
| `emails` formato válido            | ❌ No implementada | 🟡 MEDIO     | **Validar**: El campo `emails` debe contener emails válidos separados por `;` (formato: `email1@domain.com;email2@domain.com;CC:email3@domain.com`). **Impacto sin validar**: Permite formatos inválidos que pueden causar errores al enviar notificaciones. **Validación**: Parsear el string y validar cada email con regex o función de validación de email. | dejemoslo para mas adelante                                                    |
| `buyer_reference` único (opcional) | ❌ No implementada | 🟡 MEDIO     | **Validar (opcional)**: Si el campo `buyer_reference` tiene valor, debe ser único dentro del tenant. **Impacto sin validar**: Permite referencias duplicadas que pueden causar confusión. **Nota**: Solo aplicar si el negocio requiere referencias únicas. **Validación**: Si `buyer_reference` no es `null`, debe ser único por tenant.                 | No tiene por que, algunos clientes pueden coincidir en numeros de referencias. |

#### 11.3. Restricciones de Estado

| Restricción                                                      | Estado             | Peligrosidad | Descripción                                                                                                                                                                                                                                                                                                                                                                                                                                        | Solución/Idea          |
| ----------------------------------------------------------------- | ------------------ | ------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------- |
| No modificar cuando `status = 'finished'` y `load_date` < now | ❌ No implementada | 🟠 ALTO      | **Validar**: Si `status = 'finished'` y `load_date < now()` (fecha de carga ya pasó), no se pueden modificar campos del pedido (excepto posiblemente campos de solo lectura como `notes`). **Impacto sin validar**: Permite modificar pedidos ya finalizados y enviados, generando inconsistencias en registros históricos. **Validación**: Antes de cualquier `update()`, verificar que no se cumpla esta condición. | Dejemoslo para adelante |

---

### 12. OrderPlannedProductDetail (Detalle Planificado de Pedido)

#### 12.1. Restricciones de Integridad Referencial

| Restricción                      | Estado          | Peligrosidad | Descripción                                                                                                                                                                                                                                                                                                                          | Solución/Idea                                               |
| --------------------------------- | --------------- | ------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------ |
| `order_id` → `orders.id`     | ✅ Implementada | 🔴 CRÍTICO  | **Comportamiento**: Si se elimina un `Order`, se eliminan todos sus `OrderPlannedProductDetail` (`cascadeOnDelete()`). **Correcto**: Al eliminar un pedido, tiene sentido eliminar sus detalles planificados.                                                                                                       | ok                                                           |
| `product_id` → `products.id` | ✅ Implementada | 🟠 ALTO      | **Comportamiento actual**: No hay `onDelete` especificado. **Problema**: Si se elimina un producto, los detalles de pedido quedan con referencias rotas. **Solución**: Implementar `onDelete('restrict')` para impedir eliminar productos que están en pedidos, ya que los productos son catálogos maestros. | claro                                                        |
| `tax_id` → `taxes.id`        | ✅ Implementada | 🟡 MEDIO     | **Comportamiento**: El campo `tax_id` es nullable, lo que permite detalles sin impuesto. **Correcto**: Permite flexibilidad en la configuración de impuestos.                                                                                                                                                          | Dejemoslo asi pero no deberia,  mas adelante lo comprobamos |

**✅ SOLUCIÓN IMPLEMENTADA**: Migración `2025_12_05_212346_fix_order_planned_product_details_product_id_on_delete.php` creada para agregar `onDelete('restrict')` en `order_planned_product_details.product_id`. Esto impide eliminar productos que están en pedidos.

#### 12.2. Restricciones de Negocio

| Restricción                                     | Estado             | Peligrosidad | Descripción                                                                                                                                                                                                                                                                                                                                                                                                                                                                   | Solución/Idea                                                                |
| ------------------------------------------------ | ------------------ | ------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | ----------------------------------------------------------------------------- |
| `boxes` > 0                                    | ❌ No implementada | 🟠 ALTO      | **Validar**: El campo `boxes` debe ser mayor que 0. **Impacto sin validar**: Permite crear detalles de pedido con 0 cajas, generando datos inválidos. **Validación**: `boxes > 0` (tipo integer positivo).                                                                                                                                                                                                                                             | puede ser que no se sepan las cajas exactas , date cuenta que son previsiones |
| `quantity` > 0 (si se usa)                     | ✅ Implementada | 🟠 ALTO      | **Validar**: Si el campo `quantity` se usa, debe ser mayor que 0. **Impacto sin validar**: Permite cantidades 0 o negativas, generando datos inválidos. **Validación**: Si `quantity` no es `null`, verificar `quantity > 0`. **Implementado**: Validación en el modelo `OrderPlannedProductDetail` usando evento `saving`.                                                                                                                                                                                                                      | Exacto                                                                        |
| `unit_price` ≥ 0                              | ✅ Implementada | 🟡 MEDIO     | **Validar**: El campo `unit_price` debe ser mayor o igual a 0 (permite precio 0 para productos gratuitos). **Impacto sin validar**: Permite precios negativos, generando cálculos incorrectos. **Validación**: `unit_price >= 0` (tipo decimal no negativo). **Implementado**: Validación en el modelo `OrderPlannedProductDetail` usando evento `saving`.                                                                                                                                                                                           | Exacto                                                                        |
| `['order_id', 'product_id']` único (opcional) | ❌ No implementada | 🟡 MEDIO     | **Validar (opcional)**: La combinación de `order_id` y `product_id` debe ser única (un producto solo puede aparecer una vez en un pedido). **Impacto sin validar**: Permite duplicar el mismo producto en un pedido, lo cual puede ser válido si se requiere separar por diferentes precios o notas. **Nota**: Solo aplicar si el negocio no permite productos duplicados. **Validación**: Si se implementa, `UNIQUE(order_id, product_id)`. | Exacto                                                                        |

---

### 13. Incident (Incidente de Pedido)

#### 13.1. Restricciones de Integridad Referencial

| Restricción                  | Estado          | Peligrosidad | Descripción                                                                                                                                                                                                    | Solución/Idea |
| ----------------------------- | --------------- | ------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------- |
| `order_id` → `orders.id` | ✅ Implementada | 🔴 CRÍTICO  | **Comportamiento**: Si se elimina un `Order`, se eliminan todos sus `Incident` asociados (`onDelete('cascade')`). **Correcto**: Al eliminar un pedido, tiene sentido eliminar sus incidentes. | ok             |

#### 13.2. Restricciones de Negocio

| Restricción                                     | Estado             | Peligrosidad | Descripción                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   | Solución/Idea               |
| ------------------------------------------------ | ------------------ | ------------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------- |
| `status` valores válidos                      | ✅ Implementada | 🟠 ALTO      | **Validar**: El campo `status` solo puede tener valores permitidos ('open', 'resolved'). **Impacto sin validar**: Permite estados inválidos que pueden causar errores en el flujo. **Validación**: Verificar que `status` está en una lista de valores permitidos. **Implementado**: Validación en el modelo `Incident` usando constantes y evento `saving`.                                                                                                                                                                                   | ok                           |
| `resolution_type` valores válidos (si existe) | ✅ Implementada | 🟡 MEDIO     | **Validar**: Si el campo `resolution_type` existe y tiene valor, debe estar en una lista de valores permitidos ('returned', 'partially_returned', 'compensated'). **Impacto sin validar**: Permite tipos de resolución inválidos. **Validación**: Si `resolution_type` no es `null`, verificar que está en valores permitidos. **Implementado**: Validación en el modelo `Incident` usando constantes y evento `saving`.                                                                                                                                                                                       | ok                           |
| Solo un incidente abierto por pedido             | ❌ No implementada | 🟡 MEDIO     | **Validar (opcional)**: Un pedido solo puede tener un incidente con `status = 'open'` a la vez. **Impacto sin validar**: Permite múltiples incidentes abiertos simultáneamente, lo cual puede ser válido según el negocio. **Nota**: Solo aplicar si el negocio requiere un solo incidente abierto. **Validación**: Si se implementa, al crear un incidente con `status = 'open'`, verificar que no exista otro con `status = 'open'` para el mismo pedido. | Dejemoslo asi por el momento |

---

## 🗂️ Módulo: Catálogos y Maestros

### 14. Product (Producto)

#### 14.1. Restricciones de Integridad Referencial

| Restricción                                | Estado          | Peligrosidad | Descripción                                                                                                                                                                                                                                                                                                                                                                                                                                                                | Solución/Idea                               |
| ------------------------------------------- | --------------- | ------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------- |
| `id` = `articles.id` (1:1)              | ⚠️ Parcial    | 🔴 CRÍTICO  | **Relación especial**: `Product` y `Article` comparten el mismo `id` (misma clave primaria). **Problema**: Si se crea un `Product` sin `Article` correspondiente, o viceversa, se rompe la coherencia. **Solución**: Asegurar que al crear un `Product` se cree el `Article` correspondiente con el mismo `id`, y viceversa. **Validación**: Verificar que para cada `Product.id` existe un `Article.id` con el mismo valor. | Esto es caso aparte, dejemoslo para adelante |
| `species_id` → `species.id`            | ✅ Implementada | 🟠 ALTO      | **Comportamiento actual**: No hay `onDelete` especificado. **Problema**: Si se elimina una especie, los productos quedan con referencias rotas. **Solución**: Implementar `onDelete('restrict')` para impedir eliminar especies que tienen productos asociados, ya que las especies son catálogos maestros.                                                                                                                                         | Corre3gir                                    |
| `capture_zone_id` → `capture_zones.id` | ✅ Implementada | 🟠 ALTO      | **Comportamiento actual**: No hay `onDelete` especificado. **Problema**: Si se elimina una zona de captura, los productos quedan con referencias rotas. **Solución**: Implementar `onDelete('restrict')` para impedir eliminar zonas que tienen productos asociados, ya que las zonas son catálogos maestros.                                                                                                                                       | Corregir                                     |
| `family_id` → `product_families.id`    | ✅ Implementada | 🟡 MEDIO     | **Comportamiento**: Si se elimina una `ProductFamily`, los productos asociados tienen `family_id = null` (`onDelete('set null')`). **Correcto**: Permite eliminar familias sin eliminar productos, pero los productos quedan sin familia.                                                                                                                                                                                                                 | Corregir                                     |

**✅ SOLUCIÓN IMPLEMENTADA**: Migración `2025_12_05_212345_fix_products_foreign_keys_on_delete.php` creada para agregar `onDelete('restrict')` en `products.species_id` y `products.capture_zone_id`. Esto impide eliminar especies o zonas de captura que tienen productos asociados.

#### 14.2. Restricciones de Negocio

| Restricción                                                              | Estado             | Peligrosidad | Descripción                                                                                                                                                                                                                                                                                                                                                                                                       | Solución/Idea           |
| ------------------------------------------------------------------------- | ------------------ | ------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | ------------------------ |
| `fixed_weight` > 0 (si se usa)                                          | ❌ No implementada | 🟡 MEDIO     | **Validar**: Si el campo `fixed_weight` tiene valor, debe ser mayor que 0. **Impacto sin validar**: Permite pesos fijos 0 o negativos, generando datos inválidos. **Validación**: Si `fixed_weight` no es `null`, verificar `fixed_weight > 0`.                                                                                                                                        | dejemoslo por el momento |
| `article_gtin`, `box_gtin`, `pallet_gtin` únicos (si se requieren) | ✅ Implementada | 🟡 MEDIO     | **Validar**: Los campos `article_gtin`, `box_gtin` y `pallet_gtin` son **opcionales** (pueden ser `null`). Si tienen valor, deben ser únicos dentro del tenant y cumplir el formato regex `^[0-9]{8,14}$`. **Impacto sin validar**: Permite GTINs duplicados, causando problemas en sistemas externos que usan estos códigos. **Validación**: Si cada campo no es `null` y no está vacío, debe ser único por tenant y cumplir el formato. **Implementado**: Validación en el modelo `Product` usando evento `saving` y en el controlador `ProductController` que normaliza strings vacíos a `null`. | corregir                 |
| `name` no vacío (desde Article)                                        | ✅ Implementada | 🟠 ALTO      | **Validar**: El campo `name` (obtenido desde `Article.name`) no debe estar vacío. **Impacto sin validar**: Permite productos sin nombre, generando datos inválidos. **Validación**: Verificar que `article.name IS NOT NULL AND article.name != ''`. **Implementado**: Validación en el modelo `Product` usando evento `saving`.                                                                                                                                    | Dejemoslo por lo pronto  |
| `species_id` y `capture_zone_id` requeridos                           | ✅ Implementada | 🟠 ALTO      | **Validar**: Los campos `species_id` y `capture_zone_id` deben tener valor (no pueden ser `null`). **Impacto sin validar**: Permite productos sin especie o zona de captura, generando datos incompletos. **Validación**: Verificar que ambos campos no son `null` y que las entidades referenciadas existen. **Implementado**: Validación en el modelo `Product` usando evento `saving`.                                                                           | Corregir                 |

#### 14.3. Restricciones de Unicidad

| Restricción                        | Estado             | Peligrosidad | Descripción                                                                                                                                                                                                                                                                                                                                                                                                                                                                                              | Solución/Idea                               |
| ----------------------------------- | ------------------ | ------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------- |
| `id` único (PK)                  | ✅ Implementada    | 🔴 CRÍTICO  | **Validar**: El campo `id` es clave primaria y por tanto único. **Ya implementado**: Constraint de PK en la base de datos. **Correcto**: Garantiza unicidad.                                                                                                                                                                                                                                                                                                                         |                                              |
| Sincronización con `articles.id` | ❌ No implementada | 🔴 CRÍTICO  | **Validar**: Para cada `Product.id` debe existir un `Article.id` con el mismo valor, y viceversa. **Impacto sin validar**: Permite crear productos sin artículo correspondiente o artículos sin producto, rompiendo la relación 1:1. **Solución**: Implementar triggers o validaciones a nivel de aplicación para mantener la sincronización. **Validación**: Verificar que `Product.id IN (SELECT id FROM articles)` y `Article.id IN (SELECT id FROM products)`. | Esto es caso aparte, dejemoslo para adelante |

---

### 15. Article (Artículo)

#### 15.1. Restricciones de Integridad Referencial

| Restricción                                 | Estado          | Peligrosidad | Descripción                                                                                                                                                                                                                                                                                                      | Solución/Idea |
| -------------------------------------------- | --------------- | ------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------- |
| `category_id` → `article_categories.id` | ✅ Implementada | 🟡 MEDIO     | **Comportamiento actual**: No hay `onDelete` especificado. **Problema**: Si se elimina una categoría, los artículos quedan con referencias rotas. **Solución**: Implementar `onDelete('restrict')` o `onDelete('set null')` según si se permite que artículos queden sin categoría. **Implementado**: Migración `2025_12_05_212350_fix_articles_category_id_foreign_key_on_delete.php` con `onDelete('restrict')`. | Corregir       |

#### 15.2. Restricciones de Negocio

| Restricción               | Estado             | Peligrosidad | Descripción                         | Solución/Idea |
| -------------------------- | ------------------ | ------------ | ------------------------------------ | -------------- |
| `name` único por tenant | ✅ Implementada | 🟡 MEDIO     | Evitar nombres duplicados (opcional) | Corregir       |
| `name` no vacío         | ✅ Implementada | 🟠 ALTO      | Validar nombre                       | Corregir       |

---

### 16. Customer (Cliente)

#### 16.1. Restricciones de Integridad Referencial

| Restricción                                | Estado          | Peligrosidad | Descripción                         | Solución/Idea |
| ------------------------------------------- | --------------- | ------------ | ------------------------------------ | -------------- |
| `payment_term_id` → `payment_terms.id` | ✅ Implementada | 🟠 ALTO      | Sin `onDelete` - **REVISAR** | restrict       |
| `salesperson_id` → `salespersons.id`   | ✅ Implementada | 🟠 ALTO      | Sin `onDelete` - **REVISAR** | restrict       |
| `country_id` → `countries.id`          | ✅ Implementada | 🟡 MEDIO     | Sin `onDelete` - **REVISAR** | restrict       |
| `transport_id` → `transports.id`       | ✅ Implementada | 🟠 ALTO      | Sin `onDelete` - **REVISAR** | restrict       |

**✅ SOLUCIÓN IMPLEMENTADA**: Migración `2025_12_05_212349_fix_customers_foreign_keys_on_delete.php` creada para agregar `onDelete('restrict')` en todas las Foreign Keys de `customers`:
- `payment_term_id`: `onDelete('restrict')`
- `salesperson_id`: `onDelete('restrict')`
- `country_id`: `onDelete('restrict')`
- `transport_id`: `onDelete('restrict')`

#### 16.2. Restricciones de Negocio

| Restricción                     | Estado             | Peligrosidad | Descripción              | Solución/Idea               |
| -------------------------------- | ------------------ | ------------ | ------------------------- | ---------------------------- |
| `name` único por tenant       | ✅ Implementada | 🟡 MEDIO     | Evitar nombres duplicados | ok                           |
| `vat_number` único por tenant | ❌ No implementada | 🟠 ALTO      | Evitar NIFs duplicados    | Ser flexibles pòr lo pronto |
| `emails` formato válido       | ❌ No implementada | 🟡 MEDIO     | Validar formato de emails | No limitar por el momento    |
| `name` no vacío               | ✅ Implementada | 🟠 ALTO      | Validar nombre            | ok                           |

---

## 🔐 Módulo: Sistema y Autenticación

### 17. User (Usuario)

#### 17.1. Restricciones de Integridad Referencial

| Restricción                           | Estado       | Peligrosidad | Descripción                                | Solución/Idea |
| -------------------------------------- | ------------ | ------------ | ------------------------------------------- | -------------- |
| `assigned_store_id` → `stores.id` | ✅ Implementada | 🟡 MEDIO     | No hay FK explícita -**IMPLEMENTAR** | implementar    |

**✅ SOLUCIÓN IMPLEMENTADA**: Migración `2025_12_05_210349_add_users_assigned_store_foreign_key.php` creada para agregar la FK con `onDelete('set null')`. Esto permite que un usuario quede sin almacén asignado si se elimina el almacén.

#### 17.2. Restricciones de Negocio

| Restricción                | Estado          | Peligrosidad | Descripción        | Solución/Idea      |
| --------------------------- | --------------- | ------------ | ------------------- | ------------------- |
| `email` único por tenant | ✅ Implementada | 🔴 CRÍTICO  | Correcto            |                     |
| `email` formato válido   | ⚠️ Parcial    | 🟠 ALTO      | Validar formato     | dejar por lo pronto |
| ~~`password` requerido~~  | N/A           | —           | Eliminado: acceso por magic link/OTP, sin contraseña | —                  |
| `name` no vacío          | ⚠️ Parcial    | 🟠 ALTO      | Validar nombre      | ok                  |

---

### 18. Role (Rol)

#### 18.1. Restricciones de Negocio

| Restricción               | Estado             | Peligrosidad | Descripción              | Solución/Idea |
| -------------------------- | ------------------ | ------------ | ------------------------- | -------------- |
| `name` único por tenant | ❌ No implementada | 🟠 ALTO      | Evitar nombres duplicados | ok             |
| `name` no vacío         | ⚠️ Parcial       | 🟠 ALTO      | Validar nombre            | ok             |

---

### 19. ActivityLog (Log de Actividad)

#### 19.1. Restricciones de Integridad Referencial

| Restricción                | Estado          | Peligrosidad | Descripción                                  | Solución/Idea |
| --------------------------- | --------------- | ------------ | --------------------------------------------- | -------------- |
| `user_id` → `users.id` | ✅ Implementada | 🟡 MEDIO     | `onDelete('cascade')` - Correcto (nullable) |                |

---

## 📥 Módulo: Recepciones y Despachos

### 20. RawMaterialReception (Recepción de Materia Prima)

#### 20.1. Restricciones de Integridad Referencial

| Restricción                        | Estado          | Peligrosidad | Descripción                                                       |
| ----------------------------------- | --------------- | ------------ | ------------------------------------------------------------------ |
| `supplier_id` → `suppliers.id` | ✅ Implementada | 🟠 ALTO      | `onDelete('cascade')` - **REVISAR - implementar restrict** |

**✅ SOLUCIÓN IMPLEMENTADA**: Migración `2025_12_05_213254_fix_raw_material_receptions_supplier_id_on_delete.php` creada para cambiar `onDelete('cascade')` a `onDelete('restrict')` en `raw_material_receptions.supplier_id`. Esto impide eliminar proveedores que tienen recepciones asociadas.

#### 20.2. Restricciones de Negocio

| Restricción     | Estado             | Peligrosidad | Descripción                          | Solución/Idea           |
| ---------------- | ------------------ | ------------ | ------------------------------------- | ------------------------ |
| `date` válida | ⚠️ Parcial       | 🟠 ALTO      | Validar formato de fecha              | ok                       |
| `date` ≤ hoy  | ❌ No implementada | 🟡 MEDIO     | No permitir fechas futuras (opcional) | no limitar por lo pronto |

---

### 21. RawMaterialReceptionProduct (Producto de Recepción)

#### 21.1. Restricciones de Integridad Referencial

| Restricción                                       | Estado          | Peligrosidad | Descripción                                | Solución/Idea |
| -------------------------------------------------- | --------------- | ------------ | ------------------------------------------- | -------------- |
| `reception_id` → `raw_material_receptions.id` | ✅ Implementada | 🔴 CRÍTICO  | `onDelete('cascade')` - Correcto          |                |
| `product_id` → `products.id`                  | ✅ Implementada | 🟠 ALTO      | `onDelete('cascade')` - **REVISAR** | restrict       |

**✅ SOLUCIÓN IMPLEMENTADA**: 
- Migración `2025_12_05_212352_fix_raw_material_reception_products_product_id_on_delete.php` creada para cambiar `onDelete('cascade')` a `onDelete('restrict')` en `raw_material_reception_products.product_id`.
- Migración `2025_12_05_213258_add_raw_material_reception_products_unique_constraint.php` creada para agregar constraint único compuesto en `['reception_id', 'product_id']`.

#### 21.2. Restricciones de Negocio

| Restricción                                         | Estado             | Peligrosidad | Descripción                | Solución/Idea |
| ---------------------------------------------------- | ------------------ | ------------ | --------------------------- | -------------- |
| `net_weight` > 0                                   | ✅ Implementada | 🟠 ALTO      | Peso positivo               | ok             |
| `price` ≥ 0                                       | ✅ Implementada | 🟡 MEDIO     | Precio no negativo          | ok             |
| `['reception_id', 'product_id']` único (opcional) | ✅ Implementada | 🟡 MEDIO     | Evitar productos duplicados | ok             |

---

### 22. CeboDispatch (Despacho de Cebo)

#### 22.1. Restricciones de Integridad Referencial

| Restricción                        | Estado          | Peligrosidad | Descripción                                                     |
| ----------------------------------- | --------------- | ------------ | ---------------------------------------------------------------- |
| `supplier_id` → `suppliers.id` | ✅ Implementada | 🟠 ALTO      | `onDelete('cascade')` - **REVISAR - corregir restrict** |

**✅ SOLUCIÓN IMPLEMENTADA**: Migración `2025_12_05_213256_fix_cebo_dispatches_supplier_id_on_delete.php` creada para cambiar `onDelete('cascade')` a `onDelete('restrict')` en `cebo_dispatches.supplier_id`. Esto impide eliminar proveedores que tienen despachos asociados.

---

### 23. CeboDispatchProduct (Producto de Despacho)

#### 23.1. Restricciones de Integridad Referencial

| Restricción                              | Estado          | Peligrosidad | Descripción                                | Solución/Idea |
| ----------------------------------------- | --------------- | ------------ | ------------------------------------------- | -------------- |
| `dispatch_id` → `cebo_dispatches.id` | ✅ Implementada | 🔴 CRÍTICO  | `onDelete('cascade')` - Correcto          |                |
| `product_id` → `products.id`         | ✅ Implementada | 🟠 ALTO      | `onDelete('cascade')` - **REVISAR** | restrict       |

**✅ SOLUCIÓN IMPLEMENTADA**: 
- Migración `2025_12_05_212353_fix_cebo_dispatch_products_product_id_on_delete.php` creada para cambiar `onDelete('cascade')` a `onDelete('restrict')` en `cebo_dispatch_products.product_id`.
- Migración `2025_12_05_213259_add_cebo_dispatch_products_unique_constraint.php` creada para agregar constraint único compuesto en `['dispatch_id', 'product_id']`.

#### 23.2. Restricciones de Negocio

| Restricción                                         | Estado             | Peligrosidad | Descripción                | Solución/Idea |
| ---------------------------------------------------- | ------------------ | ------------ | --------------------------- | -------------- |
| `net_weight` > 0                                   | ✅ Implementada | 🟠 ALTO      | Peso positivo               | ok             |
| `price` ≥ 0                                       | ✅ Implementada | 🟡 MEDIO     | Precio no negativo          | ok             |
| `['dispatch_id', 'product_id']` único (opcional) | ✅ Implementada | 🟡 MEDIO     | Evitar productos duplicados | ok             |

---

## 🔄 Restricciones Transversales

### 24. Multi-Tenancy

| Restricción                                         | Estado       | Peligrosidad | Descripción                           | Solución/Idea |
| ---------------------------------------------------- | ------------ | ------------ | -------------------------------------- | -------------- |
| Todas las entidades deben pertenecer al mismo tenant | ⚠️ Parcial | 🔴 CRÍTICO  | Validar tenant en todas las relaciones | si             |
| No cruzar datos entre tenants                        | ⚠️ Parcial | 🔴 CRÍTICO  | Asegurar aislamiento de datos          | ok             |

---

### 25. Soft Deletes

| Restricción                                           | Estado             | Peligrosidad | Descripción                                   | Solución/Idea                   |
| ------------------------------------------------------ | ------------------ | ------------ | ---------------------------------------------- | -------------------------------- |
| `Production` tiene soft deletes                      | ✅ Implementada    | 🟠 ALTO      | Correcto                                       |                                  |
| Otras entidades críticas deberían tener soft deletes | ❌ No implementada | 🟡 MEDIO     | Considerar para `Box`, `Pallet`, `Order` | Explicame que casos y valoramos |

---

### 26. Timestamps

| Restricción                                 | Estado          | Peligrosidad | Descripción                       | Solución/Idea |
| -------------------------------------------- | --------------- | ------------ | ---------------------------------- | -------------- |
| `created_at` ≤ `updated_at`             | ⚠️ Parcial    | 🟢 BAJO      | Validación automática de Laravel |                |
| `updated_at` se actualiza automáticamente | ✅ Implementada | 🟢 BAJO      | Correcto                           |                |

---

## 📊 Resumen Ejecutivo

### Restricciones Críticas (🔴) - Implementar Inmediatamente

1. **Producción**:

   - Validar que `opened_at` ≤ `closed_at`
   - Prevenir modificaciones en lotes cerrados
   - Validar que cajas estén disponibles antes de usar en producción
   - Validar que outputs no consuman más de lo disponible
   - Prevenir ciclos en árbol de procesos
2. **Inventario**:

   - Una caja solo puede estar en un palet
   - Un palet solo puede estar en un almacén
   - Validar coherencia de estados de palet
   - Prevenir eliminar cajas con trazabilidad
3. **Pedidos**:

   - Validar fechas (`entry_date` ≤ `load_date`)
   - Prevenir modificaciones en pedidos finalizados
4. **Catálogos**:

   - Mantener sincronización `Product` ↔ `Article` (1:1)
   - Validar que productos no se eliminen si están en uso

### Restricciones de Alto Impacto (🟠) - Implementar en Corto Plazo

1. Cambiar `onDelete('cascade')` a `onDelete('restrict')` en:

   - `Production.species_id`, `Production.capture_zone_id`
   - `ProductionOutput.product_id`
   - `Box.article_id`
   - `OrderPlannedProductDetail.product_id`
   - `RawMaterialReceptionProduct.product_id`
   - `CeboDispatchProduct.product_id`
2. Implementar foreign keys faltantes:

   - `Pallet.order_id` → `orders.id`
   - `User.assigned_store_id` → `stores.id`
3. Validaciones de negocio:

   - Pesos y cantidades positivas
   - Coherencia de estados
   - Validación de fechas

### Restricciones de Medio Impacto (🟡) - Implementar Según Prioridad

1. Unicidades:

   - `Customer.vat_number` único
   - `Product.GTINs` únicos
   - `Role.name` único
2. Validaciones de formato:

   - Emails
   - Fechas
   - Códigos externos

### Restricciones de Bajo Impacto (🟢) - Implementar Cuando Sea Posible

1. Validaciones opcionales:
   - Capacidad de almacenes
   - Temperaturas
   - Formatos de datos secundarios

---

## 🔧 Recomendaciones de Implementación

### Fase 1: Críticas (Inmediato)

1. Implementar validaciones de estado en `Production`
2. Implementar restricciones de unicidad en `PalletBox` y `StoredPallet`
3. Corregir `onDelete` en relaciones críticas
4. Implementar validaciones de negocio en producción

### Fase 2: Alto Impacto (1-2 semanas)

1. Corregir todas las relaciones con `onDelete` incorrecto
2. Implementar foreign keys faltantes
3. Validaciones de negocio en inventario y pedidos

### Fase 3: Medio Impacto (1 mes)

1. Unicidades y validaciones de formato
2. Mejoras en coherencia de datos

### Fase 4: Bajo Impacto (Ongoing)

1. Validaciones opcionales
2. Mejoras de UX

---

## 📝 Notas Finales

- Este documento debe actualizarse cuando se implementen nuevas restricciones
- Las restricciones marcadas como "Parcial" tienen alguna validación pero no completa
- Las restricciones marcadas como "No implementada" requieren implementación completa
- Todas las restricciones críticas deben tener tests unitarios asociados

---

## 📦 Implementaciones Realizadas

### Migraciones Creadas (2025-12-05)

Se han creado las siguientes migraciones para implementar las soluciones acordadas:

1. **`2025_12_05_210315_fix_productions_foreign_keys_on_delete.php`**
   - Cambia `onDelete('cascade')` a `onDelete('restrict')` en `productions.species_id` y `capture_zone_id`
   - Protege la trazabilidad histórica impidiendo eliminar especies/zonas con producciones

2. **`2025_12_05_210335_fix_production_records_parent_on_delete.php`**
   - Cambia `onDelete('cascade')` a `onDelete('set null')` en `production_records.parent_record_id`
   - Permite que los procesos hijos se conviertan en raíz cuando se elimina el padre

3. **`2025_12_05_210340_fix_production_inputs_box_on_delete.php`**
   - Cambia `onDelete('cascade')` a `onDelete('restrict')` en `production_inputs.box_id`
   - Impide eliminar cajas que están siendo usadas en producción

4. **`2025_12_05_210344_fix_production_outputs_product_on_delete.php`**
   - Cambia `onDelete('cascade')` a `onDelete('restrict')` en `production_outputs.product_id`
   - Protege los productos como catálogos maestros

5. **`2025_12_05_210346_add_pallets_order_foreign_key.php`**
   - Asegura que existe la FK `pallets.order_id` con `onDelete('set null')`
   - Corrige la migración original que no definía la columna antes de la FK

6. **`2025_12_05_210349_add_users_assigned_store_foreign_key.php`**
   - Agrega FK `users.assigned_store_id` con `onDelete('set null')`
   - Permite que usuarios queden sin almacén si se elimina el almacén

7. **`2025_12_05_210352_add_pallet_boxes_unique_constraints.php`**
   - Agrega constraint único en `box_id` (una caja solo en un palet)
   - Agrega constraint único compuesto en `['pallet_id', 'box_id']`

8. **`2025_12_05_210354_add_stored_pallets_unique_constraints.php`**
   - Agrega constraint único en `pallet_id` (un palet solo en un almacén)

9. **`2025_12_05_212325_fix_boxes_article_id_foreign_key_on_delete.php`**
   - Agrega `onDelete('restrict')` en `boxes.article_id` → `products.id`
   - Impide eliminar productos que tienen cajas asociadas

10. **`2025_12_05_212339_fix_orders_foreign_keys_on_delete.php`**
    - Agrega `onDelete('restrict')` en todas las Foreign Keys de `orders`:
      - `customer_id` → `customers.id`
      - `payment_term_id` → `payment_terms.id`
      - `salesperson_id` → `salespersons.id`
      - `transport_id` → `transports.id`
      - `incoterm_id` → `incoterms.id`

11. **`2025_12_05_212345_fix_products_foreign_keys_on_delete.php`**
    - Agrega `onDelete('restrict')` en `products.species_id` → `species.id`
    - Agrega `onDelete('restrict')` en `products.capture_zone_id` → `capture_zones.id`

12. **`2025_12_05_212346_fix_order_planned_product_details_product_id_on_delete.php`**
    - Agrega `onDelete('restrict')` en `order_planned_product_details.product_id` → `products.id`

13. **`2025_12_05_212349_fix_customers_foreign_keys_on_delete.php`**
    - Agrega `onDelete('restrict')` en todas las Foreign Keys de `customers`:
      - `payment_term_id` → `payment_terms.id`
      - `salesperson_id` → `salespersons.id`
      - `country_id` → `countries.id`
      - `transport_id` → `transports.id`

14. **`2025_12_05_212350_fix_articles_category_id_foreign_key_on_delete.php`**
    - Agrega `onDelete('restrict')` en `articles.category_id` → `article_categories.id`

15. **`2025_12_05_212352_fix_raw_material_reception_products_product_id_on_delete.php`**
    - Cambia `onDelete('cascade')` a `onDelete('restrict')` en `raw_material_reception_products.product_id` → `products.id`

16. **`2025_12_05_212353_fix_cebo_dispatch_products_product_id_on_delete.php`**
    - Cambia `onDelete('cascade')` a `onDelete('restrict')` en `cebo_dispatch_products.product_id` → `products.id`

17. **`2025_12_05_213254_fix_raw_material_receptions_supplier_id_on_delete.php`**
    - Cambia `onDelete('cascade')` a `onDelete('restrict')` en `raw_material_receptions.supplier_id` → `suppliers.id`

18. **`2025_12_05_213256_fix_cebo_dispatches_supplier_id_on_delete.php`**
    - Cambia `onDelete('cascade')` a `onDelete('restrict')` en `cebo_dispatches.supplier_id` → `suppliers.id`

19. **`2025_12_05_213258_add_raw_material_reception_products_unique_constraint.php`**
    - Agrega constraint único compuesto en `['reception_id', 'product_id']` para evitar productos duplicados en la misma recepción

20. **`2025_12_05_213259_add_cebo_dispatch_products_unique_constraint.php`**
    - Agrega constraint único compuesto en `['dispatch_id', 'product_id']` para evitar productos duplicados en el mismo despacho

### Correcciones en Migraciones Existentes

- **`2023_08_09_145908_create_pallets_table.php`**: Corregida para definir la columna `order_id` antes de crear la foreign key.

### Validaciones Implementadas en Modelos (2025-12-05)

Se han implementado todas las validaciones críticas a nivel de modelo usando eventos de Eloquent:

1. **Production Model**:
   - ✅ Validación de `opened_at ≤ closed_at`
   - ✅ Validación de `closed_at` solo si `opened_at` existe
   - ✅ Validación de `date` en rango válido (1900 - +10 años)
   - ✅ Bloqueo de modificaciones cuando `closed_at != null` (excepto `notes`)

2. **ProductionRecord Model**:
   - ✅ Validación de `parent_record_id != id` (evitar ciclos directos)
   - ✅ Validación de `parent_record_id` pertenece al mismo `production_id`
   - ✅ Validación recursiva de ciclos en el árbol
   - ✅ Validación de `started_at ≤ finished_at`
   - ✅ Validación de `started_at` solo si lote está abierto
   - ✅ Validación de `finished_at` solo si `started_at` existe
   - ✅ Bloqueo de creación de procesos en lotes cerrados

3. **ProductionInput Model**:
   - ✅ Validación de caja disponible (`isAvailable = true`)
   - ✅ Validación de caja existe y no eliminada
   - ✅ Validación de proceso pertenece a lote abierto

4. **ProductionOutput Model**:
   - ✅ Validación de `weight_kg > 0`
   - ✅ Validación de proceso pertenece a lote abierto

5. **ProductionOutputConsumption Model**:
   - ✅ Validación de `consumed_weight_kg > 0`
   - ✅ Validación de `consumed_weight_kg ≤ available_weight_kg`
   - ✅ Validación de `consumed_boxes ≤ available_boxes` (si `consumed_boxes > 0`)
   - ✅ Validación de `production_output` pertenece al `parent` del `production_record`

6. **Box Model**:
   - ✅ Validación de `net_weight > 0`
   - ✅ Validación de `gross_weight >= net_weight`
   - ✅ Validación de `lot` no vacío
   - ✅ Validación de `article_id` existe
   - ✅ Bloqueo de eliminación si tiene `productionInputs`

7. **Pallet Model**:
   - ✅ Validación de `status` válido
   - ✅ Bloqueo de cambio de `status = 4` (procesado) a otro estado
   - ✅ Bloqueo de cambio de `status = 3` (enviado) a `1` o `2`

8. **Order Model**:
   - ✅ Validación de `entry_date ≤ load_date`
   - ✅ Validación de `status` valores válidos ('pending', 'finished', 'incident')
   - ✅ Constantes para estados válidos (`STATUS_PENDING`, `STATUS_FINISHED`, `STATUS_INCIDENT`)

9. **Product Model**:
   - ✅ Validación de `species_id` requerido
   - ✅ Validación de `capture_zone_id` requerido
   - ✅ Validación de `name` no vacío (desde Article)

10. **Customer Model**:
    - ✅ Validación de `name` no vacío
    - ✅ Validación de `name` único por tenant

11. **Article Model**:
    - ✅ Validación de `name` no vacío
    - ✅ Validación de `name` único por tenant

12. **OrderPlannedProductDetail Model**:
    - ✅ Validación de `quantity > 0` (si se usa)
    - ✅ Validación de `unit_price ≥ 0`

### Actualizaciones en Request Classes

Se han actualizado las siguientes request classes para validar `weight_kg > 0`:

- `StoreProductionOutputRequest`: `weight_kg` ahora valida `gt:0` en lugar de `min:0`
- `UpdateProductionOutputRequest`: `weight_kg` ahora valida `gt:0` en lugar de `min:0`
- `StoreMultipleProductionOutputsRequest`: `weight_kg` ahora valida `gt:0` en lugar de `min:0`
- `StoreProductionOutputConsumptionRequest`: `consumed_weight_kg` ahora valida `gt:0` en lugar de `min:0`
- `UpdateProductionOutputConsumptionRequest`: `consumed_weight_kg` ahora valida `gt:0` en lugar de `min:0`

---

### Notas sobre Restricciones No Implementadas

Las siguientes restricciones están marcadas como "❌ No implementada" pero **NO se implementarán** según las decisiones del usuario:

1. **Restricciones que el usuario indicó "No limitar" o "No aplica"**:
   - `lot` único por tenant (puede haber múltiples producciones con el mismo lote)
   - `boxes > 0` en ProductionOutput (las cajas no son obligatorias, solo los pesos)
   - `weight_kg / boxes` razonable (no se limita el promedio)
   - `consumed_boxes > 0` (las cajas no son obligatorias)
   - `gs1_128` único (pueden existir cajas idénticas con el mismo código)
   - `consumed_weight_kg / consumed_boxes` ≈ `weight_kg / boxes` (no se limita)
   - No eliminar si está en un `palet` (se puede eliminar una caja que esté en un palet)
   - No puede tener cajas con productos diferentes (pueden existir palets mezclados)
   - `position` único por `store_id` (las posiciones pueden tener varios elementos)
   - `pallet.status` debe ser `2` al crear StoredPallet (un palet puede estar vinculado a un pedido pero aún estar en almacén)
   - `pallet` no debe tener `order_id` al crear StoredPallet (no se limita)

2. **Restricciones que el usuario indicó "Lo trataremos mas adelante"**:
   - Si `status = 2` (almacenado), debe tener `storedPallet`
   - Si `status = 3` (enviado), debe tener `order_id`
   - Si `status = 4` (procesado), todas las cajas deben tener `productionInputs`
   - No puede tener cajas vacías
   - `box` no debe tener `productionInputs` al agregar a palet
   - `pallet.status` debe ser `2` al crear StoredPallet

3. **Restricciones opcionales que no se implementarán**:
   - Un proceso raíz por tipo de proceso en un lote (puede haber diferentes fechas)
   - `product` debe tener `species_id` y `capture_zone_id` compatibles con el lote (no limitar por lo pronto)

### Restricciones Pendientes de Implementación

Las siguientes restricciones están marcadas como "❌ No implementada" y **SÍ requieren implementación** (aunque algunas pueden ser de baja prioridad):

- Validaciones de formato (emails, fechas, etc.) - algunas marcadas como "dejemoslo para mas adelante"
- Restricciones de estado más complejas (validar coherencia entre estados y relaciones) - marcadas como "Lo trataremos mas adelante"
- Validaciones de negocio específicas que requieren lógica adicional (marcadas como "Dejemoslo para adelante")
- `fixed_weight > 0` en Product (marcado como "dejemoslo por el momento")
- `['order_id', 'product_id']` único en OrderPlannedProductDetail (marcado como "Exacto" pero opcional)

---

**Última actualización**: 2025-12-05
**Mantenido por**: Equipo de Desarrollo
