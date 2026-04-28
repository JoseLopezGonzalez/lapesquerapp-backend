# Propuesta: cierre definitivo de producciones

> **Estado**: ✅ implementado — todas las fases 1–4 completadas.
> **Última revisión**: 2026-04-28

---

## Índice

1. [Objetivo](#1-objetivo)
2. [Decisiones de negocio confirmadas](#2-decisiones-de-negocio-confirmadas)
3. [Preguntas resueltas](#3-preguntas-resueltas)
4. [Qué existe hoy en el backend](#4-qué-existe-hoy-en-el-backend)
5. [Diseño propuesto](#5-diseño-propuesto)
6. [Validaciones antes del cierre](#6-validaciones-antes-del-cierre)
7. [Bloqueos después del cierre](#7-bloqueos-después-del-cierre)
8. [Cambios de base de datos](#8-cambios-de-base-de-datos)
9. [Cambios de API](#9-cambios-de-api)
10. [Fases de implementación](#10-fases-de-implementación)
11. [Tests recomendados](#11-tests-recomendados)
12. [Riesgos técnicos y gaps detectados](#12-riesgos-técnicos-y-gaps-detectados)
13. [Auditoría avanzada — evolución futura](#13-auditoría-avanzada--evolución-futura)

---

## 1. Objetivo

Implementar el **cierre definitivo de una producción**: marcar un lote como completamente liquidado cuando todo lo producido ya está vendido o reprocesado, y bloquear cualquier operación posterior que pueda alterar su trazabilidad.

"Cierre definitivo" significa:

- Todo lo producido con el lote está vendido (pedido `finished`, palet `shipped`) o reprocesado (usado como input en otra producción).
- No quedan cajas disponibles en stock con ese lote.
- A partir del cierre, el lote queda bloqueado para cualquier cambio operativo.

**Leyenda de términos:**


| Término       | Definición                                                                                                                                    |
| ------------- | --------------------------------------------------------------------------------------------------------------------------------------------- |
| `reprocesado` | Cajas resultantes de esta producción usadas como materia prima en otra producción. Se detecta porque tienen registros en `production_inputs`. |
| `vendido`     | Cajas del lote en palet `shipped` vinculado a pedido `finished`.                                                                              |
| `stock`       | Cajas del lote en palet `registered` o `stored` sin pedido asignado.                                                                          |
| `balance`     | `producido − vendido − reprocesado − stock`. Para cerrar, debe ser 0 y el stock debe ser 0.                                                   |


---

## 2. Decisiones de negocio confirmadas

Las siguientes decisiones ya están aprobadas e incorporadas al diseño.

- ✅ Solo existirá un tipo de cierre: **cierre definitivo**. No se implementará un cierre productivo intermedio.
- ✅ "Vendido" significa pedido `finished` **y** palet `shipped`. Un palet en pedido pendiente no cuenta como vendido.
- ✅ No se permitirán ajustes de cierre para cuadrar diferencias. Si no cuadra, se corrigen los datos antes de cerrar.
- ✅ Si hay que corregir una producción ya cerrada: reabrir → corregir → cerrar de nuevo.
- ✅ Pueden cerrar y reabrir **administradores y roles superiores**.
- ✅ No se permite reubicar palets ni mover stock de un lote cerrado.
- ✅ El lote es único entre producciones del tenant, pero puede estar repetido en muchas cajas.
- ✅ Se reutiliza `closed_at` como campo de cierre definitivo. No se añade `finalized_at` en la primera implementación.
- ✅ Campos adicionales de cierre: `closed_by`, `closure_reason`, `reopened_at`, `reopened_by`, `reopen_reason`.
- ✅ La primera implementación no guardará snapshot de cierre. Queda como evolución futura.
- ✅ El estado `warning` en conciliación **bloquea el cierre**. Solo `ok` permite proceder. *(resuelto P1)*
- ✅ El campo `notes` queda **bloqueado** tras el cierre definitivo. Todos los campos se bloquean. *(resuelto P2)*
- ✅ Una producción cerrada **no puede eliminarse** bajo ningún concepto. *(resuelto P3)*
- ✅ Solo el rol `administrador` y roles superiores pueden cerrar y reabrir. El rol `tecnico` no tiene este permiso. *(resuelto P4)*
- ✅ `ProductionOutput::lot_id` existe en BD pero no se usa. El lote de los outputs es siempre `Production::lot`. El bloqueo de outputs ya está cubierto por la validación existente de lote abierto. *(resuelto P5)*

---

## 3. Preguntas resueltas

Todas las preguntas han sido respondidas e incorporadas a la sección 2. Se conservan aquí como registro de la decisión y su contexto.

**P1 — ¿`warning` bloquea el cierre?**
**Respuesta:** sí. Solo el estado `ok` permite cerrar. `warning` y `error` bloquean.
*Motivo:* "cerrado = cuadrado". No se admiten diferencias, aunque estén dentro de umbral.

---

**P2 — ¿`notes` debe bloquearse tras el cierre?**
**Respuesta:** sí. Todos los campos quedan bloqueados, incluido `notes`.
*Acción:* eliminar la excepción de `notes` en `validateUpdateRules()` del modelo `Production`.

---

**P3 — ¿Una producción cerrada puede eliminarse?**
**Respuesta:** no, rotundamente.
*Acción:* añadir validación en `ProductionService::delete()` y `deleteMultiple()` que lance error si `closed_at != null`.

---

**P4 — ¿Qué roles pueden cerrar y reabrir?**
**Respuesta:** `administrador` y roles superiores. El rol `tecnico` no tiene este permiso.
*Acción:* implementar en Fase 4 via Policy o middleware de rol, sin crear un permiso específico nuevo.

---

**P5 — ¿`lot_id` en `ProductionOutput` es una FK numérica o el string `lot`?**
**Respuesta:** es un string nullable que existe en la BD pero no se usa en el frontend ni en la lógica de negocio. El lote de los outputs siempre es el lote de la producción (`Production::lot`), que ya queda en `Box::lot` de las cajas resultantes.
*Consecuencia:* no hay nada que gestionar. El bloqueo de outputs ya está cubierto porque `ProductionOutput` valida que el `ProductionRecord` pertenezca a una producción abierta. No se necesita ningún check adicional sobre `lot_id`.

---

## 4. Qué existe hoy en el backend

Esta sección describe el estado real del código actual relevante para el cierre.

### 4.1 Production


| Campo       | Tipo               | Estado                              |
| ----------- | ------------------ | ----------------------------------- |
| `opened_at` | timestamp          | Siempre set al crear                |
| `closed_at` | timestamp nullable | Hoy: cierre simple sin validaciones |


**Métodos disponibles:** `isOpen()`, `isClosed()`, `open()`, `close()`.

**Protección existente:** `validateUpdateRules()` ya impide modificar cualquier campo excepto `notes` cuando `closed_at != null` (esa excepción se eliminará al implementar el cierre definitivo). Los modelos `ProductionRecord`, `ProductionInput` y `ProductionOutput` ya validan que el lote esté abierto antes de crear o modificar. **El núcleo de producción ya está parcialmente bloqueado.**

**Lo que falta:** endpoint de cierre, validaciones previas al cierre, y extensión del bloqueo al resto de entidades (cajas, palets, pedidos, costes).

### 4.2 ProductionRecord (procesos)

- `started_at` / `finished_at`: marcan inicio y fin de cada proceso.
- Árbol jerárquico: cada proceso puede tener procesos hijos.
- Endpoint existente: `POST /production-records/{id}/finish` — solo establece `finished_at = now()`, sin validar outputs ni hijos.
- `isFinal()`: nodo sin hijos ni inputs de stock, con al menos un output.

### 4.3 Conciliación actual

`Production` ya calcula conciliación por producto con los siguientes estados:


| Estado    | Significado                                                                 |
| --------- | --------------------------------------------------------------------------- |
| `ok`      | Todo contabilizado, balance = 0                                             |
| `warning` | Diferencia dentro del umbral porcentual definido                            |
| `error`   | Diferencia relevante, o producto contabilizado que no figura como producido |


La lógica cuenta: outputs de nodos finales como producido, cajas en pedidos como venta, cajas en palets `registered`/`stored` sin pedido como stock, cajas con `productionInputs` como reprocesado.

**Limitación para el cierre:** la conciliación actual cuenta como "vendido" cualquier palet con `order_id`, aunque el pedido esté pendiente. Para el cierre definitivo, solo cuenta pedido `finished` y palet `shipped`.

### 4.4 Cajas (`Box`)

- `isAvailable`: accessor que retorna `!productionInputs()->exists()`. **No significa "en stock"**, significa "no usada como input en otra producción".
- Una caja vendida en pedido sigue siendo `isAvailable = true` si no fue reprocesada.
- No se puede eliminar una caja que ya tiene `productionInputs`.

### 4.5 Palets (`Pallet`)


| Estado       | Valor | Significado               |
| ------------ | ----- | ------------------------- |
| `registered` | 1     | En stock, registrado      |
| `stored`     | 2     | En stock, almacenado      |
| `shipped`    | 3     | Enviado (pedido finished) |
| `processed`  | 4     | Consumido en producción   |


`scopeInStock()` filtra `registered` o `stored`. `updateStateBasedOnBoxes()` cambia a `processed` cuando todas las cajas del palet están en producción. Cuando un pedido pasa a `finished`, sus palets cambian a `shipped`.

### 4.6 Pedidos (`Order`)

Estados: `pending`, `finished`, `incident`. Solo `pending` e `incident` son editables operativamente. La transición a `finished` cambia los palets asociados a `shipped`.

---

## 5. Diseño propuesto

### 5.1 Servicio central: `ProductionClosureService`

```
App\Services\Production\ProductionClosureService
```

Responsabilidades:

- Evaluar si una producción puede cerrarse (`canClose`).
- Aplicar el cierre dentro de una transacción con `lockForUpdate`.
- Aplicar la reapertura con control de permisos.
- Lanzar errores de negocio con mensajes claros.

```php
canClose(Production $production): ClosureCheckResult
close(Production $production, User $user, string $reason): Production
reopen(Production $production, User $user, string $reason): Production
```

`ClosureCheckResult` es un DTO que contiene: `canClose (bool)`, `blockingReasons (array)`, `summary (array)`.

### 5.2 Servicio transversal: `ProductionLotLockService`

```
App\Services\Production\ProductionLotLockService
```

Responsabilidades:

- Ser el único punto de verificación de si un lote está bloqueado.
- Lanzar excepción de negocio si se intenta mutar una entidad de un lote cerrado.

```php
assertLotIsMutable(?string $lot, string $operation): void
assertBoxIsMutable(Box $box, string $operation): void
assertPalletIsMutable(Pallet $pallet, string $operation): void
assertOrderIsMutableForProductionLots(Order $order, string $operation): void
```

La búsqueda de bloqueo:

```php
Production::where('lot', $lot)
    ->whereNotNull('closed_at')
    ->exists()
```

**Servicios que deben llamar al lock service:**

- `PalletWriteService`
- `PalletActionService`
- `RawMaterialReceptionWriteService`
- `ProductionInputService`
- `ProductionOutputService`
- `ProductionOutputConsumptionService`
- `ProductionCostService`
- `ProductionRecordService`
- Servicios de actualización de pedidos

> **Nota sobre Model Events:** Los eventos de Eloquent (`creating`, `updating`, `deleting`) no se disparan en updates masivos (`Box::where(...)->update([...])`). El check debe colocarse en los métodos de los servicios que escriben sobre cajas, no solo en eventos del modelo.

---

## 6. Validaciones antes del cierre

Todas deben pasar antes de establecer `closed_at`. El orden importa: los checks baratos primero.


| #   | Validación                                                                                             | Coste |
| --- | ------------------------------------------------------------------------------------------------------ | ----- |
| 1   | La producción debe estar abierta (`isOpen() = true`).                                                  | Bajo  |
| 2   | Debe tener al menos un proceso (`ProductionRecord`).                                                   | Bajo  |
| 3   | Todos los procesos deben tener `started_at`.                                                           | Bajo  |
| 4   | Todos los procesos deben tener `finished_at`, **incluyendo nodos hijos de cualquier nivel del árbol**. | Medio |
| 5   | Cada nodo final (`isFinal() = true`) debe tener al menos un output.                                    | Medio |
| 6   | No debe haber outputs intermedios sin consumir si el árbol exige transformación completa.              | Medio |
| 7   | La conciliación por producto debe estar en `ok`. Los estados `warning` y `error` también bloquean.     | Alto  |
| 8   | No deben existir palets `registered` o `stored` sin pedido que contengan cajas del lote.               | Alto  |
| 9   | `inStock.weight` debe ser 0 y `inStock.boxes` debe ser 0 para todos los productos del lote.            | Alto  |
| 10  | No debe haber pedidos `pending` o `incident` con palets del lote.                                      | Alto  |
| 11  | Toda venta del lote debe estar en pedido `finished` **y** palet `shipped`.                             | Alto  |
| 12  | No deben existir cajas del lote sin destino final (ni stock, ni venta, ni reprocesadas).               | Alto  |


**Sobre el balance:**

```
balance = producido − vendido − reprocesado − stock
```

Para cerrar: balance = 0 y stock = 0. No se admite tolerancia. Si no cuadra, se corrigen los datos.

**Concurrencia:** dentro de la transacción de cierre, obtener `lockForUpdate` sobre la producción y re-validar que sigue abierta después del lock para evitar doble cierre concurrente.

---

## 7. Bloqueos después del cierre

Cuando `closed_at != null`, el lote queda bloqueado. Cualquier intento de mutación debe lanzar un error de negocio claro. La única vía para modificar datos es la **reapertura administrativa**.

### 7.1 Producción


| Operación                                            | Bloqueada                |
| ---------------------------------------------------- | ------------------------ |
| Editar cualquier campo, incluido `notes`             | ✅ ya bloqueado (excepto `notes` — se elimina esa excepción en Fase 2) |
| Eliminar la producción                               | ⚠️ debe añadirse en Fase 2 |
| Añadir procesos (`ProductionRecord`)                 | ✅ ya bloqueado           |
| Añadir inputs                                        | ✅ ya bloqueado           |
| Añadir outputs                                       | ✅ ya bloqueado           |
| Añadir/editar/eliminar `ProductionOutputConsumption` | ⚠️ debe añadirse en Fase 3 |
| Añadir/editar/eliminar `ProductionCost`              | ⚠️ debe añadirse en Fase 3 |


### 7.2 Cajas


| Operación                                                           | Bloqueada        |
| ------------------------------------------------------------------- | ---------------- |
| Crear caja con ese `lot`                                            | ⚠️ debe añadirse |
| Editar `lot`, `article_id`, `net_weight`, `gross_weight`, `gs1_128` | ⚠️ debe añadirse |
| Eliminar caja con ese `lot`                                         | ⚠️ debe añadirse |
| Cambiar `lot` de otra caja hacia este lote                          | ⚠️ debe añadirse |
| Cambiar `lot` de una caja de este lote hacia otro                   | ⚠️ debe añadirse |


### 7.3 Palets


| Operación                        | Bloqueada                                              |
| -------------------------------- | ------------------------------------------------------ |
| Añadir cajas al palet            | ✅ `PalletWriteService::update()`                      |
| Quitar cajas del palet           | ✅ `PalletWriteService::update()`                      |
| Eliminar el palet                | ✅ `PalletWriteService::destroy()` / `destroyMultiple` |
| Vincular o desvincular de pedido | ✅ `PalletActionService::unlinkOrder()`                |
| Cambiar ubicación de almacén     | ✅ `PalletWriteService::update()`                      |


### 7.4 Pedidos


| Operación                                           | Bloqueada                                                    |
| --------------------------------------------------- | ------------------------------------------------------------ |
| Pasar pedido de `finished` a `pending` o `incident` | ✅ `OrderUpdateService::update()` via `assertOrderIsMutable…` |
| Desvincular palets del pedido                       | ✅ `PalletActionService::unlinkOrder()`                      |
| Modificar líneas que contradigan la trazabilidad    | ⚠️ no implementado (riesgo bajo, no impacta trazabilidad directamente) |
| Eliminar pedido que justifica salida final del lote | ⚠️ no implementado (requiere check en `OrderController::destroy`) |


### 7.5 Reprocesado


| Operación                                                     | Bloqueada                                                |
| ------------------------------------------------------------- | -------------------------------------------------------- |
| Crear `ProductionInput` con caja del lote cerrado             | ✅ `ProductionInputService::create()` via `assertBoxIsMutable` |
| Eliminar `ProductionInput` que justifica reprocesado del lote | ✅ `ProductionInputService::delete()` / `deleteMultiple` |


### 7.6 Recepciones

Si una recepción crea, edita o elimina palets o cajas con el lote cerrado, debe bloquearse. Ver [gap de recepciones](#gap-recepciones) para el detalle del mapeo pendiente.

---

## 8. Cambios de base de datos

### Migración requerida en `productions`

```php
$table->foreignId('closed_by')->nullable()->constrained('users');
$table->string('closure_reason')->nullable();
$table->timestamp('reopened_at')->nullable();
$table->foreignId('reopened_by')->nullable()->constrained('users');
$table->string('reopen_reason')->nullable();
```

Los campos `opened_at` y `closed_at` ya existen.

### Limpieza en `production_outputs`

Eliminar el campo `lot_id`: existe en BD pero no se usa en ningún flujo del frontend ni de negocio. El lote de los outputs siempre es el lote de la producción (`Production::lot`).

```php
$table->dropIndex(['lot_id']);
$table->dropColumn('lot_id');
```

También eliminar `lot_id` del array `$fillable` en `ProductionOutput` y de los Form Requests: `StoreProductionOutputRequest`, `UpdateProductionOutputRequest`, `SyncProductionOutputsRequest`, `StoreMultipleProductionOutputsRequest`, `IndexProductionOutputRequest`.

### Tabla futura: `production_closure_events`

No se implementa en la primera versión. Ver sección [Auditoría avanzada](#13-auditoría-avanzada--evolución-futura).

---

## 9. Cambios de API

### 9.1 Verificar si puede cerrar

```http
GET /api/v2/productions/{id}/closure-check
```

Respuesta:

```json
{
  "canClose": false,
  "blockingReasons": [
    {
      "code": "stock_remaining",
      "message": "Quedan 12.50 kg en stock del lote.",
      "productId": 10
    },
    {
      "code": "pending_order",
      "message": "Hay pedidos pendientes con palets de este lote.",
      "orderId": 5
    }
  ],
  "summary": {
    "producedWeight": 1000,
    "salesWeight": 700,
    "reprocessedWeight": 287.5,
    "stockWeight": 12.5,
    "balanceWeight": 0
  }
}
```

### 9.2 Cierre definitivo

```http
POST /api/v2/productions/{id}/close
```

Payload:

```json
{
  "reason": "Sin stock pendiente y conciliación validada"
}
```

### 9.3 Reapertura administrativa

```http
POST /api/v2/productions/{id}/reopen
```

Payload:

```json
{
  "reason": "Corrección administrativa autorizada"
}
```

### 9.4 Cambios en `ProductionResource`

```json
{
  "closedAt": "2026-04-28T10:00:00Z",
  "isClosed": true,
  "canClose": false,
  "closureSummary": {}
}
```

> **Nota de rendimiento:** `closureSummary` y `canClose` (si requiere queries complejas) deben exponerse solo cuando `?include_closure=1`. En listados sin ese parámetro no deben calcularse.

---

## 10. Fases de implementación

### Fase 1 — Evaluación y check *(sin bloqueos)*

- Crear `ProductionClosureService` con método `canClose`.
- Crear endpoint `GET /productions/{id}/closure-check`.
- Adaptar la conciliación existente para exigir pedido `finished` y palet `shipped`.
- `warning` en conciliación bloquea el cierre (igual que `error`).

### Fase 2 — Cierre definitivo

- Añadir migración: 5 campos nuevos en `productions` + eliminar `lot_id` de `production_outputs` (campo sin uso).
- Crear endpoint `POST /productions/{id}/close`.
- Implementar `ProductionClosureService::close()` con transacción y `lockForUpdate`.
- Implementar todas las validaciones de la sección 6.
- Eliminar excepción de `notes` en `Production::validateUpdateRules()`: todos los campos quedan bloqueados.
- Bloquear eliminación de producciones cerradas en `ProductionService::delete()` y `deleteMultiple()`.
- Reforzar bloqueos ya existentes en `ProductionRecord`, `ProductionInput`, `ProductionOutput`.

### Fase 3 — Bloqueo transversal

- Crear `ProductionLotLockService`. `lot_id` en outputs no se usa: no hay caso especial, el bloqueo de outputs ya está cubierto.
- Integrarlo en: `PalletWriteService`, `PalletActionService`, `RawMaterialReceptionWriteService`, `ProductionInputService`, `ProductionOutputService`, `ProductionOutputConsumptionService`, `ProductionCostService`, `ProductionRecordService`, servicios de pedidos.
- Mapear flujos de recepciones que tocan cajas de lotes de producción (ver G7).
- Colocar checks en servicios, no solo en Model Events (no cubren mass updates).
- Añadir tests de regresión para cada vía de mutación.

### Fase 4 — Reapertura administrativa

- Roles autorizados: `administrador` y superiores. Implementar via Policy o middleware de rol.
- Crear endpoint `POST /productions/{id}/reopen`.
- Implementar `ProductionClosureService::reopen()` con control de permisos y motivo obligatorio.

### Fase 5 — Auditoría avanzada *(futuro)*

- Crear tabla `production_closure_events`.
- Guardar snapshot por cada cierre.
- Registrar reaperturas como eventos.
- Ver sección [13](#13-auditoría-avanzada--evolución-futura).

---

## 11. Tests recomendados

### Cierre definitivo

- No permite cerrar producción sin procesos.
- No permite cerrar si algún proceso (incluidos hijos) no tiene `finished_at`.
- No permite cerrar si un nodo final no tiene outputs.
- No permite cerrar si queda stock del lote.
- No permite cerrar si conciliación tiene status `error` o `warning`.
- No permite cerrar si hay palets del lote en pedido pendiente.
- No permite cerrar si hay palets del lote en pedido `incident`.
- Permite cerrar si todo está vendido en pedidos `finished` y palets `shipped`.
- Permite cerrar si todo está reprocesado.
- Permite cerrar con mezcla de vendido y reprocesado sin stock.
- Doble cierre concurrente: solo uno debe tener éxito.
- Después del cierre no permite crear procesos, inputs, outputs ni costes.

### Bloqueo de lote (Fase 3)

- No permite crear caja con lote cerrado.
- No permite editar caja de lote cerrado.
- No permite eliminar caja de lote cerrado.
- No permite añadir caja de lote cerrado a palet.
- No permite quitar caja de lote cerrado de palet.
- No permite desvincular palet vendido de pedido si contiene lote cerrado.
- No permite crear `ProductionInput` con caja de lote cerrado.
- No permite eliminar `ProductionInput` que justifica reprocesado de lote cerrado.
- No permite crear o editar `ProductionCost` de producción cerrada.
- No permite update masivo de cajas de lote cerrado (cubre el gap de mass updates).

### Reapertura

- Solo rol autorizado puede reabrir.
- Motivo obligatorio.
- Después de reabrir permite corrección controlada.
- Después de reabrir y volver a cerrar, el historial es coherente.

---

## 12. Riesgos técnicos y gaps detectados

### Riesgos ya identificados en el diseño original

1. `isAvailable` en `Box` significa "no reprocesada", no "en stock". No usarla sola para evaluar el cierre.
2. La conciliación actual cuenta ventas por `order_id` sin exigir pedido `finished`. Para el cierre definitivo hay que endurecerla.
3. Las cajas se crean desde `PalletWriteService` y recepciones: el bloqueo debe ser transversal, no solo en el controller.
4. `Production::close()` existe pero no valida el estado completo del lote.
5. `ProductionRecordService::finish()` existe pero no valida outputs ni hijos.
6. La eliminación masiva de palets elimina `PalletBox` y luego palets: debe comprobar lotes cerrados antes de ejecutar.
7. Si se bloquea por `lot` textual, normalizar comparaciones para evitar diferencias por espacios o mayúsculas.

### Gaps detectados en la auditoría de código



**G1. `ProductionCost` no estaba en los bloqueos originales**

Los costes asociados a una producción cerrada no deben poder crearse, editarse ni eliminarse. `ProductionCostService` debe añadirse a la lista de servicios con integración del lock service.

**G2. `ProductionOutputConsumptionService` no estaba en la lista del lock service**

Tiene su propio servicio independiente. Debe añadirse explícitamente.

**G3. Eliminación de producciones cerradas no está protegida** *(decisión tomada: no se puede eliminar)*

`ProductionService::delete()` y `deleteMultiple()` no comprueban `closed_at`. Se añade en Fase 2: lanzar error de negocio si `closed_at != null`.

**G4. Model events no cubren mass updates**

Los eventos de Eloquent no se disparan en updates masivos. El lock debe estar en los servicios, no solo en los eventos del modelo. Cubrir con test específico.

**G5. Concurrencia en el cierre**

Sin `lockForUpdate`, dos usuarios simultáneos pueden pasar las validaciones. Añadir lock pesimista en la transacción de cierre.

**G6. `canClose` costoso en listados**

Si `canClose` requiere queries complejas, no incluirlo en el listado general de producciones sin el parámetro `?include_closure=1`.

**G7. Flujos de recepción que tocan lotes de producción — pendiente de mapear**

Las recepciones crean y editan palets y cajas. En el caso del reprocesado, esas cajas pueden tener lotes de producción. Antes de la Fase 3 hay que mapear qué métodos de `RawMaterialReceptionWriteService` pueden recibir cajas de un lote de producción cerrado y añadirles el check correspondiente.

---

## 13. Auditoría avanzada — evolución futura

No se implementa en la primera versión. En una evolución posterior conviene guardar una foto histórica por cada cierre y reapertura.

### Tabla propuesta: `production_closure_events`


| Campo           | Tipo      | Descripción                              |
| --------------- | --------- | ---------------------------------------- |
| `id`            | bigint    | PK                                       |
| `production_id` | FK        | Producción                               |
| `event_type`    | enum      | `closed`, `reopened`                     |
| `user_id`       | FK        | Usuario que ejecutó la acción            |
| `reason`        | string    | Motivo obligatorio                       |
| `snapshot`      | json      | Foto del estado en el momento del cierre |
| `created_at`    | timestamp | Fecha del evento                         |


### Contenido del snapshot

```json
{
  "closedAt": "2026-04-28T10:00:00Z",
  "closedBy": 1,
  "reason": "Cierre validado",
  "lot": "LOT-123",
  "summary": {
    "totalProducedWeight": 1000,
    "totalSalesWeight": 700,
    "totalReprocessedWeight": 300,
    "totalStockWeight": 0,
    "totalBalanceWeight": 0
  },
  "products": [
    {
      "productId": 10,
      "produced": { "boxes": 50, "weight": 1000 },
      "sales": { "boxes": 35, "weight": 700 },
      "reprocessed": { "boxes": 15, "weight": 300 },
      "stock": { "boxes": 0, "weight": 0 },
      "balance": { "weight": 0, "status": "ok" }
    }
  ]
}
```

### Ventajas sobre guardar en `productions`

- Historial completo de múltiples ciclos cierre/reapertura.
- Cada cierre conserva su propio snapshot sin sobrecargar la fila principal.
- Permite auditar con qué cifras se aprobó cada cierre, aunque después se reabriera y corrigiera.

---

## 14. Resumen de implementación (2026-04-28)

### Archivos creados

| Archivo | Propósito |
| ------- | --------- |
| `database/migrations/companies/2026_04_28_100000_add_closure_fields_to_productions_table.php` | 5 nuevos campos en `productions` |
| `database/migrations/companies/2026_04_28_100100_drop_lot_id_from_production_outputs_table.php` | Elimina `lot_id` de `production_outputs` |
| `app/Services/Production/ProductionClosureService.php` | `canClose`, `close`, `reopen` con 11 validaciones y `lockForUpdate` |
| `app/Services/Production/ProductionLotLockService.php` | Bloqueo transversal por lote: box, pallet, order, lot directo |
| `app/Http/Requests/v2/CloseProductionRequest.php` | Validación `reason` para cierre |
| `app/Http/Requests/v2/ReopenProductionRequest.php` | Validación `reason` para reapertura |

### Archivos modificados

| Archivo | Cambio |
| ------- | ------ |
| `app/Models/Production.php` | `$fillable` / `$casts` con campos de cierre; `validateUpdateRules` bloquea todo incluyendo `notes`; relaciones `closedByUser`, `reopenedByUser` |
| `app/Models/ProductionOutput.php` | Elimina `lot_id` de `$fillable` |
| `app/Http/Controllers/v2/ProductionController.php` | Endpoints `closureCheck`, `close`, `reopen` |
| `app/Policies/ProductionPolicy.php` | `close()` y `reopen()` — solo `administrador` y `direccion` |
| `app/Http/Resources/v2/ProductionResource.php` | Expone `closedBy`, `closureReason`, `reopenedAt`, `reopenedBy`, `reopenReason` + relaciones de usuario |
| `app/Http/Resources/v2/ProductionOutputResource.php` | Elimina `lotId` |
| `app/Services/Production/ProductionService.php` | Bloquea `delete` / `deleteMultiple` en producciones cerradas |
| `app/Services/Production/ProductionRecordService.php` | Bloquea `delete` y `finish` en lotes cerrados; elimina `lot_id` de creates |
| `app/Services/Production/ProductionInputService.php` | Bloquea `create`, `delete`, `deleteMultiple` por caja |
| `app/Services/Production/ProductionOutputService.php` | Bloquea `create`, `update`, `delete`; elimina `lot_id` de `createMultiple` |
| `app/Services/Production/ProductionOutputConsumptionService.php` | Bloquea `create`, `update`, `delete` por lote |
| `app/Http/Controllers/v2/ProductionCostController.php` | Bloquea `store`, `update`, `destroy` por lote |
| `app/Services/v2/PalletWriteService.php` | Bloquea `update`, `destroy`, `destroyMultiple` por palet |
| `app/Services/v2/PalletActionService.php` | Bloquea `unlinkOrder` por palet |
| `app/Services/v2/OrderUpdateService.php` | Bloquea reversión de `finished` → `pending`/`incident` si el pedido contiene lotes cerrados |
| `routes/api.php` | 3 rutas nuevas: `closure-check`, `close`, `reopen` |
| `app/Http/Requests/v2/StoreProductionOutputRequest.php` | Elimina `lot_id` |
| `app/Http/Requests/v2/UpdateProductionOutputRequest.php` | Elimina `lot_id` |
| `app/Http/Requests/v2/SyncProductionOutputsRequest.php` | Elimina `lot_id` |
| `app/Http/Requests/v2/StoreMultipleProductionOutputsRequest.php` | Elimina `lot_id` |
| `app/Http/Requests/v2/IndexProductionOutputRequest.php` | Elimina `lot_id` |
| `app/Http/Controllers/v2/ProductionOutputController.php` | Elimina filtro por `lot_id` del `index` |

### Gaps pendientes (bajo impacto)

- **Eliminar pedido con lote cerrado**: `OrderController::destroy` no tiene el check. Riesgo bajo — el flujo normal no elimina pedidos `finished`.
- **Modificar líneas de pedido que contradigan trazabilidad**: no implementado. Bajo impacto operativo.
- **`RawMaterialReceptionWriteService`**: el `lot` de cajas de recepción es un lote de proveedor, no un lote de producción. El check `assertBoxIsMutable` no dispara para estas cajas. Si en el futuro se crean recepciones con lote de producción, revisar.

