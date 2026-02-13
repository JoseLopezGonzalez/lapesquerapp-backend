# Módulo de Producción - Visión General

## ⚠️ Estado de la API
- **v1**: Eliminada (2025-01-27) - Usaba JSON único (`diagram_data`) para almacenar toda la estructura. Ya no existe en el código base.
- **v2**: Versión activa (este documento) - Usa entidades relacionales (`production_records`, `production_inputs`, `production_outputs`). Única versión disponible.

---

## 📋 Visión General

El módulo de producción es el sistema más complejo y crítico del backend de PesquerApp. Gestiona el ciclo completo de producción pesquera, desde la entrada de materia prima hasta la salida de productos terminados, manteniendo trazabilidad total a nivel de caja individual.

### Contexto del Negocio

En la industria pesquera, cada lote de producción representa una captura o recepción de materia prima que pasa por una serie de procesos (ej: eviscerado, fileteado, envasado) hasta convertirse en productos finales. El sistema debe:

1. **Rastrear cada caja** individual desde su entrada hasta su transformación
2. **Construir árboles de procesos** que muestren cómo la materia prima se transforma
3. **Calcular mermas** y rendimientos en cada etapa
4. **Conciliar producción declarada** con stock real en almacenes

---

## 🏗️ Arquitectura del Módulo

### Evolución del Sistema

El módulo migró de una arquitectura basada en JSON a una arquitectura relacional completa:

#### Sistema Antiguo (v1 - Eliminado)
- **Almacenamiento**: Todo el diagrama de producción se guardaba en un campo JSON único (`productions.diagram_data`)
- **Ventajas**: Simplicidad de almacenamiento, fácil de visualizar
- **Desventajas**: 
  - Imposible consultar datos específicos sin parsear JSON
  - Difícil mantener integridad referencial
  - No permite trazabilidad real de cajas individuales
  - Cálculos complejos y poco eficientes

#### Sistema Nuevo (v2 - En desarrollo)
- **Almacenamiento**: Estructura relacional normalizada con 4 tablas principales:
  - `productions`: Cabecera del lote
  - `production_records`: Procesos individuales (árbol jerárquico)
  - `production_inputs`: Entradas (cajas consumidas)
  - `production_outputs`: Salidas (productos producidos)
- **Ventajas**:
  - Trazabilidad total a nivel de caja
  - Consultas eficientes con SQL
  - Integridad referencial garantizada
  - Diagrama calculado dinámicamente desde datos relacionales
- **Estado**: Implementación completa y activa. v1 fue eliminada completamente (2025-01-27)

---

## 📊 Entidades Principales

### 1. Production (Lote de Producción)
**Archivo**: `app/Models/Production.php`

Cabecera que representa un lote completo de producción. Agrupa todos los procesos relacionados.

**Campos principales**:
- `lot`: Identificador del lote (string, nullable)
- `species_id`: Especie pesquera (nullable en v2, required en v1)
- `capture_zone_id`: Zona de captura (legacy, nullable)
- `notes`: Notas adicionales
- `opened_at`: Timestamp cuando se abre el lote
- `closed_at`: Timestamp cuando se cierra el lote
- `diagram_data`: JSON legacy (mantenido para compatibilidad)

**Estados**:
- **Abierto** (`opened_at` != null, `closed_at` == null): Permite agregar procesos
- **Cerrado** (`closed_at` != null): Lote finalizado, sin modificaciones

### 2. ProductionRecord (Proceso de Producción)
**Archivo**: `app/Models/ProductionRecord.php`

Representa un proceso individual dentro del lote (ej: "Eviscerado", "Fileteado"). Los procesos se organizan en árboles jerárquicos donde un proceso puede tener procesos hijos.

**Estructura de árbol**:
- **Raíz**: Proceso sin `parent_record_id` (ej: proceso inicial de recepción)
- **Hijo**: Proceso con `parent_record_id` apuntando a otro proceso
- **Final**: Proceso que solo produce outputs, no consume inputs

**Campos principales**:
- `production_id`: FK a Production
- `parent_record_id`: FK a ProductionRecord (nullable, para construir árbol)
- `process_id`: FK a Process (tipo de proceso maestro)
- `started_at`: Inicio del proceso
- `finished_at`: Finalización del proceso

### 3. ProductionInput (Entrada de Producción)
**Archivo**: `app/Models/ProductionInput.php`

Representa una caja individual que se consume en un proceso. Cada entrada vincula una caja (`Box`) a un proceso (`ProductionRecord`).

**Características**:
- Una caja solo puede estar asignada **una vez** al mismo proceso (unique constraint)
- Una caja puede ser consumida en múltiples procesos diferentes
- El peso, producto y lote se obtienen automáticamente desde la `Box`

**Campos principales**:
- `production_record_id`: FK a ProductionRecord
- `box_id`: FK a Box

### 4. ProductionOutput (Salida de Producción)
**Archivo**: `app/Models/ProductionOutput.php`

Representa un producto producido en un proceso. No vincula cajas individuales (eso se hace luego en stock), sino que declara "cuánto se produjo".

**Características**:
- Declara cantidad de cajas y peso total producido
- Puede haber múltiples outputs del mismo producto en un proceso
- El `lot_id` es opcional y puede diferir del lote del Production
- Puede ser consumido por procesos hijos (a través de `ProductionOutputConsumption`)

**Campos principales**:
- `production_record_id`: FK a ProductionRecord
- `product_id`: FK a Product (producto producido)
- `lot_id`: String opcional para identificar el lote del producto
- `boxes`: Cantidad de cajas producidas (integer)
- `weight_kg`: Peso total en kilogramos (decimal)

### 5. ProductionOutputConsumption (Consumo de Output del Padre)
**Archivo**: `app/Models/ProductionOutputConsumption.php`

Representa el consumo de una salida de producción del proceso padre por parte de un proceso hijo. Permite que los procesos hijos consuman tanto cajas del stock como salidas del proceso padre.

**Características**:
- Permite que procesos hijos consuman outputs del proceso padre
- Permite consumo parcial o total del output
- Valida que no se exceda el output disponible
- Complementa `ProductionInput` (que solo consume cajas del stock)

**Campos principales**:
- `production_record_id`: FK a ProductionRecord (proceso hijo que consume)
- `production_output_id`: FK a ProductionOutput (output del padre consumido)
- `consumed_weight_kg`: Peso consumido en kilogramos
- `consumed_boxes`: Cantidad de cajas consumidas

---

## 🔄 Flujo de Trabajo

### Crear un Lote de Producción

1. **Crear Production**: Se crea el lote y automáticamente se abre (`opened_at` = now)
   ```
   POST /v2/productions
   ```

2. **Crear Procesos**: Se crean ProductionRecord para cada etapa
   ```
   POST /v2/production-records
   ```

3. **Asignar Entradas**: Se asignan cajas a procesos
   ```
   POST /v2/production-inputs
   POST /v2/production-inputs/multiple (para múltiples cajas)
   ```

4. **Registrar Salidas**: Se declaran productos producidos
   ```
   POST /v2/production-outputs
   ```

5. **Consumir Outputs del Padre** (para procesos hijos): Los procesos hijos pueden consumir outputs del proceso padre
   ```
   POST /v2/production-output-consumptions
   ```
   ```
   POST /v2/production-outputs
   ```

5. **Finalizar Procesos**: Se marca cada proceso como completado
   ```
   POST /v2/production-records/{id}/finish
   ```

6. **Cerrar Lote** (futuro): Cuando todos los procesos estén finalizados, se cierra el lote

### Consultar Diagrama

El diagrama se calcula dinámicamente desde los datos relacionales:
```
GET /v2/productions/{id}/diagram
```

Este endpoint:
- Si hay `diagram_data` legacy y no hay procesos nuevos, retorna el JSON antiguo
- Si hay procesos nuevos, calcula dinámicamente el diagrama desde los árboles de procesos

---

## 🔗 Relaciones entre Entidades

```
Production (1) ←→ (N) ProductionRecord
ProductionRecord (1) ←→ (N) ProductionInput
ProductionRecord (1) ←→ (N) ProductionOutput
ProductionRecord (1) ←→ (N) ProductionOutputConsumption
ProductionInput (N) ←→ (1) Box
ProductionOutput (N) ←→ (1) Product
ProductionOutput (1) ←→ (N) ProductionOutputConsumption
ProductionRecord (N) ←→ (1) Process
ProductionRecord (N) ←→ (1) ProductionRecord (parent)
```

---

## 📍 Rutas API Principales

Todas las rutas están bajo `/v2` y requieren autenticación (`auth:sanctum`) y roles (`superuser,manager,admin,store_operator`).

### Production (Lotes)
- `GET /v2/productions` - Listar producciones
- `POST /v2/productions` - Crear producción
- `GET /v2/productions/{id}` - Mostrar producción
- `PUT /v2/productions/{id}` - Actualizar producción
- `DELETE /v2/productions/{id}` - Eliminar producción
- `GET /v2/productions/{id}/diagram` - Obtener diagrama calculado
- `GET /v2/productions/{id}/process-tree` - Obtener árbol de procesos
- `GET /v2/productions/{id}/totals` - Obtener totales globales
- `GET /v2/productions/{id}/reconciliation` - Obtener conciliación

### Production Records (Procesos)
- `GET /v2/production-records` - Listar procesos
- `POST /v2/production-records` - Crear proceso
- `GET /v2/production-records/{id}` - Mostrar proceso
- `PUT /v2/production-records/{id}` - Actualizar proceso
- `DELETE /v2/production-records/{id}` - Eliminar proceso
- `GET /v2/production-records/{id}/tree` - Obtener árbol del proceso
- `POST /v2/production-records/{id}/finish` - Finalizar proceso

### Production Inputs (Entradas)
- `GET /v2/production-inputs` - Listar entradas
- `POST /v2/production-inputs` - Crear entrada
- `POST /v2/production-inputs/multiple` - Crear múltiples entradas
- `GET /v2/production-inputs/{id}` - Mostrar entrada
- `DELETE /v2/production-inputs/{id}` - Eliminar entrada

### Production Outputs (Salidas)
- `GET /v2/production-outputs` - Listar salidas
- `POST /v2/production-outputs` - Crear salida
- `GET /v2/production-outputs/{id}` - Mostrar salida
- `PUT /v2/production-outputs/{id}` - Actualizar salida
- `DELETE /v2/production-outputs/{id}` - Eliminar salida

### Production Output Consumptions (Consumos de Outputs del Padre)
- `GET /v2/production-output-consumptions` - Listar consumos
- `POST /v2/production-output-consumptions` - Crear consumo
- `GET /v2/production-output-consumptions/{id}` - Mostrar consumo
- `PUT /v2/production-output-consumptions/{id}` - Actualizar consumo
- `DELETE /v2/production-output-consumptions/{id}` - Eliminar consumo
- `GET /v2/production-output-consumptions/available-outputs/{productionRecordId}` - Obtener outputs disponibles

---

## 🔍 Conceptos Clave

### Trazabilidad por Caja

Cada `Box` tiene un atributo `isAvailable` que indica si ya ha sido usada en producción. Una vez asignada a un `ProductionInput`, se marca como no disponible para evitar duplicados.

### Construcción de Árboles

Los procesos se organizan en árboles mediante `parent_record_id`. Para construir el árbol completo:
1. Se obtienen los procesos raíz (`parent_record_id IS NULL`)
2. Recursivamente se cargan los hijos de cada proceso
3. Se calculan totales agregados en cada nodo

### Cálculo de Totales

Los totales se calculan dinámicamente:
- **Peso de entrada**: Suma de `net_weight` de todas las cajas en inputs + suma de `consumed_weight_kg` de consumos de outputs del padre
- **Peso de salida**: Suma de `weight_kg` de todos los outputs
- **Merma**: Peso entrada - Peso salida
- **Porcentaje de merma**: (Merma / Peso entrada) * 100

**Nota**: Los procesos hijos pueden tener dos tipos de inputs:
- **Inputs desde stock** (`ProductionInput`): Cajas físicas del stock
- **Consumos de outputs del padre** (`ProductionOutputConsumption`): Salidas del proceso padre

### Conciliación con Stock

El sistema compara:
- **Producción declarada**: Suma de outputs de todos los procesos del lote
- **Stock real**: Cajas en almacenes (`Box` con `lot` coincidente que están en `Pallet`)

La conciliación retorna diferencias y un estado (green/yellow/red) según los umbrales configurados.

---

## 📚 Documentación Específica

Para detalles completos de cada entidad, consultar:
- [11-Produccion-Lotes.md](./11-Produccion-Lotes.md) - Modelo Production
- [12-Produccion-Procesos.md](./12-Produccion-Procesos.md) - Modelo ProductionRecord
- [13-Produccion-Entradas.md](./13-Produccion-Entradas.md) - Modelo ProductionInput
- [14-Produccion-Salidas.md](./14-Produccion-Salidas.md) - Modelo ProductionOutput
- [15-Produccion-Consumos-Outputs-Padre.md](./15-Produccion-Consumos-Outputs-Padre.md) - Modelo ProductionOutputConsumption

---

## Observaciones Críticas y Mejoras Recomendadas

### ⚠️ Estado de Migración Incompleto

1. **Dualidad de Sistemas** (`app/Models/Production.php:278-310`)
   - El modelo `Production` mantiene compatibilidad con ambos sistemas
   - El método `getDiagramData()` retorna datos antiguos si no hay procesos nuevos
   - **Problema**: Esto puede llevar a inconsistencias si hay datos en ambos formatos
   - **Recomendación**: Definir una estrategia clara de migración y fecha límite para deprecar `diagram_data`

2. **Campos Legacy Mantenidos** (`database/migrations/companies/2025_11_23_135210_update_productions_table_for_new_structure.php`)
   - Los campos `diagram_data`, `capture_zone_id`, `date` se mantienen "para facilitar migración gradual"
   - **Problema**: No hay documentación clara de cuándo se eliminarán
   - **Recomendación**: Definir fase 4 de migración con fecha específica

3. **Species_id Nullable** (`database/migrations/companies/2025_11_23_135210_update_productions_table_for_new_structure.php:20`)
   - En v1 era required, en v2 es nullable
   - **Problema**: Puede haber inconsistencias si el frontend asume que siempre existe
   - **Recomendación**: Validar en frontend o hacer required si es necesario

### ⚠️ Validaciones Faltantes

4. **Validación de Estructura del Árbol** (`app/Http/Controllers/v2/ProductionRecordController.php:61-81`)
   - No se valida si `parent_record_id` crea ciclos
   - No se valida si `parent_record_id` pertenece al mismo `production_id`
   - **Problema**: Puede crear estructuras inválidas
   - **Recomendación**: Agregar validación en `store()` y `update()`

5. **Validación de Cajas Disponibles** (`app/Http/Controllers/v2/ProductionInputController.php:46-73`)
   - Solo valida duplicados dentro del mismo proceso
   - No valida si la caja ya fue usada en otro proceso del mismo lote
   - **Problema**: Una caja podría consumirse múltiples veces en el mismo lote
   - **Recomendación**: Validar disponibilidad a nivel de lote, no solo proceso

6. **Validación de Conciliación** (`app/Models/Production.php:426-464`)
   - No hay validación automática antes de cerrar un lote
   - **Problema**: Pueden cerrarse lotes con grandes discrepancias
   - **Recomendación**: Agregar método `canClose()` que valide conciliación

### ⚠️ Métodos Incompletos o con Lógica Problemática

7. **Métodos Legacy en Production** (`app/Models/Production.php:65-141`)
   - `getProcessNodes()` y `getFinalNodes()` parsean `diagram_data` antiguo
   - Estos métodos solo funcionan con datos legacy
   - **Problema**: No hay equivalentes para la nueva estructura
   - **Recomendación**: Deprecar estos métodos o crear versiones para v2

8. **Cálculo de Totales con Queries Subóptimas** (`app/Models/Production.php:338-367`)
   - `getTotalInputWeightAttribute()` hace un join y sum en cada acceso
   - `getTotalOutputWeightAttribute()` hace un sum en cada acceso
   - **Problema**: Si se accede múltiples veces, se ejecutan múltiples queries
   - **Recomendación**: Cachear resultados o usar eager loading

9. **Conciliación con Umbrales Hardcodeados** (`app/Models/Production.php:440-445`)
   - Los umbrales (5% para red, 1% para yellow) están hardcodeados
   - **Problema**: No son configurables por tenant o usuario
   - **Recomendación**: Mover a configuración o tabla de settings

### ⚠️ Falta de Control de Transacciones

10. **Creación de Procesos sin Validación de Estado del Lote** (`app/Http/Controllers/v2/ProductionRecordController.php:61-81`)
    - No valida si el lote está cerrado antes de crear procesos
    - **Problema**: Pueden crearse procesos en lotes cerrados
    - **Recomendación**: Agregar validación `$production->isOpen()`

11. **Eliminación sin Validaciones de Integridad** (`app/Http/Controllers/v2/ProductionRecordController.php:133-141`)
    - No valida si el proceso tiene inputs/outputs antes de eliminar
    - **Problema**: Puede dejar datos huérfanos o inconsistencia en cálculos
    - **Recomendación**: Validar antes de eliminar o usar soft deletes

### ⚠️ Inconsistencias en Nombres y Tipos

12. **lot_id en ProductionOutput** (`app/Models/ProductionOutput.php:17`)
    - Es un `string` opcional, pero conceptualmente debería relacionarse con `Production.lot`
    - **Problema**: No hay validación de consistencia entre lotes
    - **Recomendación**: Agregar validación o relación explícita

13. **Falta de Relación Inversa** (`app/Models/Box.php:41-90`)
    - `Box` tiene método `getIsAvailableAttribute()` pero no relación directa
    - La relación `productionInputs()` existe pero el método `isAvailable` no la usa siempre eficientemente
    - **Problema**: Puede haber N+1 queries si no se carga eager loading
    - **Recomendación**: Optimizar o documentar requerimiento de eager loading

### ⚠️ Código Duplicado y Dead Code

14. **Métodos de Cálculo Duplicados** (`app/Models/Production.php` vs `app/Models/ProductionRecord.php`)
    - Ambos tienen métodos similares para calcular totales, mermas, etc.
    - **Problema**: Mantenimiento duplicado, posible inconsistencia
    - **Recomendación**: Extraer a traits o servicios compartidos

15. **Campos No Utilizados** (`app/Models/Production.php:18-19`)
    - `date` y `capture_zone_id` están en fillable pero pueden no usarse en v2
    - **Problema**: Confusión sobre qué campos son legacy
    - **Recomendación**: Documentar claramente o eliminar de fillable si no se usan

### ⚠️ Manejo de Errores

16. **Falta de Manejo de Errores en Cálculos** (`app/Models/Production.php:315-333`)
    - `calculateGlobalTotals()` no maneja división por cero explícitamente en todos los casos
    - **Problema**: Puede generar errores si hay datos inconsistentes
    - **Recomendación**: Agregar validaciones defensivas

17. **Mensajes de Error Genéricos** (`app/Http/Controllers/v2/ProductionInputController.php:58-62`)
    - Mensaje de error genérico "La caja ya está asignada" no indica cuál caja
    - **Problema**: Difícil debuggear en producción
    - **Recomendación**: Incluir ID de caja en mensaje de error

---

**Última actualización**: Documentación generada desde código fuente en fecha de generación.

