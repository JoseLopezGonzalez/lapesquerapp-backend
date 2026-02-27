# API Timeline de Palet (F-01) — Especificación para Frontend

Documento de contrato entre backend y frontend: qué devuelve el API, cuándo y con qué estructura. Sin criterios de implementación en UI.

**Resumen rápido**: Ver [pallet-timeline-resumen-eventos.md](pallet-timeline-resumen-eventos.md) para tabla de tipos por flujo y endpoints. **Sistema actual**: un guardado (formulario de palet o recepción) genera un solo evento `pallet_updated` con todos los cambios; las acciones puntuales (mover a almacén, asignar posición, link/unlink order, etc.) siguen generando un evento cada una. El historial puede contener además eventos en formato legacy (datos antiguos).

---

## Cambios recientes (para frontend)

| Fecha | Cambio | Impacto en frontend |
|-------|--------|---------------------|
| 2026-02 | **Totales del palet en `afterEvent`** | En eventos que tocan cajas (`pallet_updated` con boxesAdded/boxesRemoved/boxesUpdated, y tipos legacy `box_added`/`box_removed`/`box_updated`), los totales del palet **ya no** van en cada ítem de caja. Pasan a un único objeto **`details.afterEvent`** con forma `{ "boxesCount": number, "totalNetWeight": number }`. Los ítems de `boxesAdded` y `boxesRemoved` solo llevan datos de la caja (boxId, productId, productName, lot, gs1128, netWeight, grossWeight). **Compatibilidad**: si existe `details.afterEvent`, usarlo para mostrar totales; si no (datos antiguos), se puede seguir leyendo `newBoxesCount`/`newTotalNetWeight` en el ítem o en details. |
| 2026-02 | **Pedido anterior en `order.linked`** | En `pallet_updated` y en `order_linked`, al vincular un pedido nuevo, si antes había otro asignado se incluye **`previousOrderId`** dentro de `details.order.linked`. No se envían cadenas de referencia; el frontend obtiene la referencia del pedido por id si la necesita. |

---

## 1. Endpoints

| Método | Ruta | Descripción |
|--------|------|-------------|
| `GET` | `/api/v2/pallets/{id}/timeline` | Obtiene el historial de modificaciones del palet `id` |
| `DELETE` | `/api/v2/pallets/{id}/timeline` | Borra todo el historial del palet (solo administrador y técnico) |

### GET timeline — Cuándo llamarlo

- Cuando el usuario entra en el **detalle de un palet** y se quiere mostrar su historial.
- El backend no envía el timeline en `GET /api/v2/pallets/{id}`; hay que pedirlo explícitamente con este endpoint.

### DELETE timeline — Cuándo llamarlo

- Cuando un usuario con rol **administrador** o **técnico** quiere vaciar el historial del palet (p. ej. desde un botón "Borrar historial" en la pantalla de detalle). Cualquier otro rol recibe **403**.

### Headers requeridos

- `Authorization: Bearer {token}` (Sanctum)
- `X-Tenant: {subdomain}` (tenant activo)
- `Accept: application/json`

### Respuesta exitosa (200)

Cuerpo JSON:

```json
{
  "timeline": [ ... ]
}
```

- `timeline`: array de entradas. **Orden**: el backend devuelve **más reciente primero** (ya invertido respecto al orden cronológico de escritura).
- Si el palet no tiene ningún evento, `timeline` es `[]`.
- Si el palet no existe o no se tiene permiso: 404 o 403 según corresponda.

### Errores (GET)

- **401**: No autenticado.
- **403**: Sin permiso para ver el palet (misma política que `GET /api/v2/pallets/{id}`).
- **404**: Palet no encontrado.

### DELETE timeline — Respuesta exitosa (200)

Cuerpo JSON: `{ "message": "Historial del palet borrado correctamente" }`. El palet sigue existiendo; solo se vacía el array de eventos.

### Errores (DELETE)

- **401**: No autenticado.
- **403**: Sin permiso para borrar el historial (solo administrador y técnico pueden hacer DELETE).
- **404**: Palet no encontrado.

---

## 2. Estructura de cada entrada del timeline

Todas las entradas comparten estos campos a nivel raíz:

| Campo | Tipo | Siempre presente | Descripción |
|-------|------|------------------|-------------|
| `timestamp` | string | Sí | Fecha/hora en ISO 8601 (ej. `2026-02-25T10:30:00.000000Z`) |
| `userId` | number \| null | Sí | ID del usuario que realizó la acción; `null` si la acción es automática (sistema) |
| `userName` | string | Sí | Nombre del usuario; si no hay usuario, será `"Sistema"` |
| `type` | string | Sí | Identificador del tipo de evento (ver sección 3) |
| `action` | string | Sí | **Título breve** del evento (ej. "Palet actualizado", "Movido a almacén"). El detalle completo está en `details`. |
| `details` | object | Sí | Objeto con datos específicos del tipo; estructura depende de `type` (puede ser `{}`) |

---

## 3. Tipos de evento y estructura de `details`

A continuación se listan **todos** los valores posibles de `type` y, para cada uno, **cuándo se registra** y la forma exacta de `details`.

**Compatibilidad**: El array `timeline` puede contener a la vez eventos en **formato nuevo** (tipo `pallet_updated`, un evento por guardado) y eventos en **formato legacy** (tipos granulares como `box_added`, `observations_updated`, etc.) en palets con historial antiguo. El frontend debe reconocer y poder mostrar ambos. Para tipos desconocidos, mostrar al menos `timestamp`, `userName` y `action`.

---

### 3.1 `pallet_created`

**Cuándo**: El usuario crea un palet manualmente con `POST /api/v2/pallets`, o se crea un palet en una **autoventa** (flujo autoventa).

**`details`**:

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `boxesCount` | number | Número de cajas con las que se creó el palet |
| `totalNetWeight` | number | Peso neto total (kg) de esas cajas |
| `initialState` | string | Estado inicial: `"registered"`, `"stored"` o `"shipped"` (autoventa) |
| `storeId` | number \| null | ID del almacén si se asignó al crear; si no, `null` |
| `storeName` | string \| null | Nombre del almacén; `null` si no hay almacén |
| `orderId` | number \| null | ID del pedido si se vinculó al crear (o pedido autoventa); si no, `null` |
| `fromAutoventa` | boolean | *(Opcional)* `true` si el palet se creó en un flujo de autoventa |

**`action`**: `"Palet creado"` | `"Palet creado (autoventa)"` (título breve; cajas y peso en `details`).

---

### 3.2 `pallet_created_from_reception`

**Cuándo**: El palet se crea desde una recepción de materia prima (creación/actualización de recepción en modo palets o en modo líneas que genera un palet).

**`details`**:

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `receptionId` | number | ID de la recepción de materia prima |
| `boxesCount` | number | Número de cajas del palet |
| `totalNetWeight` | number | Peso neto total (kg) |

**`action`**: `"Palet creado desde recepción"` (título breve; receptionId, cajas y peso en `details`).

---

### 3.3 `pallet_updated`

**Cuándo**: Al guardar cambios en un palet (un único "Guardar" en el formulario de palet o al guardar la recepción habiendo modificado palets existentes). El backend emite **un solo evento** por guardado que agrupa todos los cambios (estado, almacén, pedido, observaciones, cajas añadidas/eliminadas/modificadas). Solo se registra si hubo al menos un cambio.

**`details`**: Objeto en el que **solo aparecen las claves que realmente cambiaron**. Posibles claves:

| Clave | Tipo | Descripción |
|-------|------|-------------|
| `observations` | `{ "from": string \| null, "to": string \| null }` | Solo si cambiaron las observaciones. |
| `state` | `{ "fromId", "from", "toId", "to" }` | Solo si cambió el estado (nombres: `registered`, `stored`, `shipped`, `processed`). |
| `store` | Ver abajo | Solo si cambió la asignación de almacén. |
| `order` | Ver abajo | Solo si cambió la vinculación al pedido. |
| `boxesAdded` | array | Lista de cajas añadidas en esta guardada. Cada elemento solo tiene datos de la caja: boxId, productId, productName, lot, gs1128, netWeight, grossWeight (no incluye totales del palet). |
| `boxesRemoved` | array | Lista de cajas eliminadas (misma estructura: solo datos de cada caja). |
| `boxesUpdated` | array | Lista de cajas modificadas; cada elemento tiene boxId, productId, productName, lot, `changes` (objeto con solo los campos que cambiaron: netWeight, grossWeight, lot, productId, con `from`/`to`). |
| `afterEvent` | object | Presente cuando el evento incluye cambios de cajas (boxesAdded, boxesRemoved o boxesUpdated). Forma: `{ "boxesCount": number, "totalNetWeight": number }` — estado del palet tras el evento. |
| `fromReception` | boolean | `true` si el cambio se hizo desde la edición de una recepción. |
| `receptionId` | number | ID de la recepción; solo si `fromReception === true`. |

**Forma de `store`** (una u otra):  
- Asignado: `"store": { "assigned": { "storeId", "storeName", "previousStoreId", "previousStoreName" } }`  
- Retirado: `"store": { "removed": { "previousStoreId", "previousStoreName" } }`

**Forma de `order`** (una u otra):  
- Vinculado: `"order": { "linked": { "orderId" [, "previousOrderId" ] } }`. Si antes había otro pedido asignado, se incluye `previousOrderId`.  
- Desvinculado: `"order": { "unlinked": { "orderId" } }`

**`action`**: `"Palet actualizado"` | `"Palet actualizado (desde recepción)"` (título breve; el detalle de cambios está en `details`).

---

### 3.4 `state_changed`

**Cuándo**: Cambio de estado del palet hecho por el usuario (actualización del palet, mover a almacén que pasa a almacenado, cambio masivo de estado, desvincular pedido que pasa a registrado, o marcar pedido como finalizado y el palet pasa a enviado).

**`details`**:

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `fromId` | number | ID del estado anterior (1=registered, 2=stored, 3=shipped, 4=processed) |
| `from` | string | Nombre del estado anterior: `"registered"`, `"stored"`, `"shipped"`, `"processed"` |
| `toId` | number | ID del estado nuevo |
| `to` | string | Nombre del estado nuevo |

**`action`**: `"Estado cambiado"` (from/to en `details`).

---

### 3.5 `state_changed_auto`

**Cuándo**: El estado del palet cambia automáticamente por la lógica de producción (todas las cajas usadas en producción → procesado; todas disponibles de nuevo o parcialmente liberadas → registrado).

**`details`**:

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `fromId` | number | ID del estado anterior |
| `from` | string | Nombre del estado anterior |
| `toId` | number | ID del estado nuevo |
| `to` | string | Nombre del estado nuevo |
| `reason` | string | Causa: `"all_boxes_in_production"` \| `"boxes_released_from_production"` \| `"partial_boxes_released"` |
| `usedBoxesCount` | number | Número de cajas usadas en producción en ese momento |
| `totalBoxesCount` | number | Número total de cajas del palet en ese momento |

**`action`**: `"Estado actualizado (automático)"` (reason y conteos en `details`).

---

### 3.6 `store_assigned`

**Cuándo**: El palet se asigna a un almacén (mover a almacén o editar palet y poner almacén).

**`details`**:

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `storeId` | number | ID del almacén asignado |
| `storeName` | string \| null | Nombre del almacén (puede ser null si no se resolvió) |
| `previousStoreId` | number \| null | ID del almacén anterior; `null` si no estaba en ninguno |
| `previousStoreName` | string \| null | Nombre del almacén anterior; `null` si no había |

**`action`**: `"Movido a almacén"` (storeId/storeName en `details`).

---

### 3.7 `store_removed`

**Cuándo**: El palet deja de estar en un almacén (se quita almacén en la edición o al cambiar estado a no almacenado).

**`details`**:

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `previousStoreId` | number | ID del almacén del que se retiró |
| `previousStoreName` | string \| null | Nombre de ese almacén |

**`action`**: `"Retirado del almacén"` (previousStoreId/Name en `details`).

---

### 3.8 `position_assigned`

**Cuándo**: Se asigna posición al palet con `POST /api/v2/pallets/assign-to-position`.

**`details`**:

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `positionId` | number | ID/valor de la posición asignada |
| `positionName` | string | Representación en string de la posición (p. ej. el mismo número) |
| `storeId` | number \| null | ID del almacén donde está el palet |
| `storeName` | string \| null | Nombre del almacén |

**`action`**: `"Posición asignada"` (positionId, storeId/storeName en `details`).

---

### 3.9 `position_unassigned`

**Cuándo**: Se quita la posición del palet con `POST /api/v2/pallets/{id}/unassign-position`.

**`details`**:

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `previousPositionId` | number | Valor de la posición que tenía |
| `previousPositionName` | string | Mismo valor en string |

**`action`**: `"Posición eliminada"` (previousPositionId/Name en `details`).

---

### 3.10 `order_linked`

**Cuándo**: El palet se vincula a un pedido (edición del palet con `orderId`, o `POST /api/v2/pallets/{id}/link-order`).

**`details`**:

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `orderId` | number | ID del pedido vinculado |
| `previousOrderId` | number | *(Opcional)* ID del pedido que estaba asignado antes, si lo había |

**`action`**: `"Vinculado a pedido"` (orderId en `details`; el frontend puede resolver la referencia del pedido por id si la necesita).

---

### 3.11 `order_unlinked`

**Cuándo**: El palet se desvincula del pedido (edición con `orderId` null o `POST /api/v2/pallets/{id}/unlink-order`).

**`details`**:

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `orderId` | number | ID del pedido del que se desvinculó |

**`action`**: `"Desvinculado de pedido"` (orderId en `details`).

---

### 3.12 `box_added`

**Cuándo**: *(Solo datos antiguos/legacy.)* En una actualización del palet se añadía al menos una caja; cada caja generaba una entrada de tipo `box_added`.

**`details`**: Datos de la caja añadida. Los totales del palet tras el evento van en **`afterEvent`** (formato actual y datos de ejemplo):

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `boxId` | number | ID de la caja añadida |
| `productId` | number | ID del producto (artículo) |
| `productName` | string \| null | Nombre del producto (`null` si no se resolvió) |
| `lot` | string | Lote de la caja |
| `gs1128` | string \| null | Código GS1-128 |
| `netWeight` | number | Peso neto (kg) |
| `grossWeight` | number \| null | Peso bruto (kg) |
| `afterEvent` | object | *(Formato actual)* `{ "boxesCount": number, "totalNetWeight": number }` — estado del palet tras añadir la caja. |

**Compatibilidad**: En datos legacy puede aparecer `newBoxesCount` y `newTotalNetWeight` en el propio objeto en lugar de `afterEvent`. El frontend puede priorizar `details.afterEvent` si existe; si no, usar esos campos para no romper historiales antiguos.

---

### 3.13 `box_removed`

**Cuándo**: *(Solo datos antiguos/legacy.)* En una actualización del palet se eliminaba una caja; cada eliminación generaba una entrada de tipo `box_removed`.

**`details`**: Datos de la caja eliminada. Los totales del palet tras el evento van en **`afterEvent`** (formato actual):

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `boxId` | number | ID de la caja eliminada |
| `productId` | number | ID del producto |
| `productName` | string \| null | Nombre del producto |
| `lot` | string | Lote |
| `gs1128` | string \| null | GS1-128 |
| `netWeight` | number | Peso neto de esa caja |
| `grossWeight` | number \| null | Peso bruto |
| `afterEvent` | object | *(Formato actual)* `{ "boxesCount": number, "totalNetWeight": number }` — estado del palet tras eliminar la caja. |

**Compatibilidad**: En datos legacy puede aparecer `newBoxesCount` y `newTotalNetWeight` en el propio objeto. Priorizar `details.afterEvent` si existe.

---

### 3.14 `box_updated`

**Cuándo**: *(Solo datos antiguos/legacy.)* En una actualización del palet, una caja existente cambiaba en al menos uno de: producto, lote, peso neto, peso bruto.

**`details`**:

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `boxId` | number | ID de la caja modificada |
| `productId` | number | ID del producto después del cambio |
| `productName` | string \| null | Nombre del producto (estado final) |
| `lot` | string | Lote después del cambio |
| `changes` | object | Solo los campos que cambiaron; cada clave es el nombre del campo y el valor es `{ "from": valorAnterior, "to": valorNuevo }` |
| `afterEvent` | object | *(Opcional)* Para consistencia en eventos que tocan cajas: `{ "boxesCount": number, "totalNetWeight": number }`. |

Claves posibles dentro de `changes`:

- `netWeight`: `{ "from": number, "to": number }`
- `grossWeight`: `{ "from": number, "to": number }`
- `lot`: `{ "from": string, "to": string }`
- `productId`: `{ "from": number, "to": number }`

**Ejemplo de `action`**: `"Caja #88 modificada — Merluza, Lote L-2024-05"`

**Ejemplo de `details.changes`**:
```json
{
  "netWeight": { "from": 4, "to": 4.2 },
  "lot": { "from": "L-2024-04", "to": "L-2024-05" }
}
```

---

### 3.15 `observations_updated`

**Cuándo**: En una actualización del palet (`PUT /api/v2/pallets/{id}`) el campo de observaciones cambia.

**`details`**:

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `from` | string \| null | Observaciones anteriores (puede ser `null` o vacío) |
| `to` | string \| null | Observaciones nuevas |

**Ejemplos de `action`**: `"Observaciones actualizadas"` | `"Observaciones actualizadas (desde recepción)"`

**Nota (legacy)**: Los tipos `box_added`, `box_removed`, `box_updated`, `observations_updated`, y en algunos casos `state_changed`, `store_assigned`, `store_removed`, `order_linked`, `order_unlinked` pueden aparecer en **datos antiguos** (historial guardado antes de la introducción de `pallet_updated`). En guardados nuevos el backend solo emite `pallet_updated` para agrupar todos los cambios de una guardada. El frontend debe seguir soportando estos tipos para mostrar el historial completo.

---

## 4. Resumen de tipos

| `type` | Descripción breve | Emitido actualmente |
|--------|-------------------|----------------------|
| `pallet_created` | Palet creado manualmente | Sí |
| `pallet_created_from_reception` | Palet creado desde recepción | Sí |
| `pallet_updated` | Guardado con cambios agrupados (formulario o recepción) | Sí |
| `state_changed` | Cambio de estado por usuario (acciones puntuales) | Sí |
| `state_changed_auto` | Cambio de estado automático (producción) | Sí |
| `store_assigned` | Asignado a un almacén (acción "mover a almacén") | Sí |
| `store_removed` | Retirado del almacén (solo dentro de `pallet_updated` o legacy) | En details de pallet_updated |
| `position_assigned` | Posición asignada | Sí |
| `position_unassigned` | Posición quitada | Sí |
| `order_linked` | Vinculado a pedido (acción link-order) | Sí |
| `order_unlinked` | Desvinculado de pedido (acción unlink-order) | Sí |
| `box_added` | Caja añadida (legacy: una entrada por caja) | No, solo en datos antiguos |
| `box_removed` | Caja eliminada (legacy) | No, solo en datos antiguos |
| `box_updated` | Caja modificada (legacy) | No, solo en datos antiguos |
| `observations_updated` | Observaciones modificadas (legacy) | No, solo en datos antiguos |

---

## 5. Estados del palet (referencia)

Para interpretar `from` / `to` en eventos de estado:

| ID | Nombre (`from`/`to`) | Significado |
|----|----------------------|-------------|
| 1 | `registered` | Registrado (sin almacén) |
| 2 | `stored` | Almacenado |
| 3 | `shipped` | Enviado (pedido finalizado) |
| 4 | `processed` | Procesado (todas las cajas consumidas en producción) |

---

## 6. Consideraciones para el frontend

- **Orden**: El array `timeline` viene ya con el **evento más reciente en la primera posición**; se puede mostrar en orden sin invertir.
- **`action` como título**: El campo `action` es un **título breve** (una línea), no una descripción larga. El detalle (almacén, pedido, cajas, etc.) está en `details`; el frontend puede mostrar el título en lista y el detalle al expandir o en vista detalle.
- **`userId` null**: Indica acción automática (p. ej. `state_changed_auto`); `userName` será `"Sistema"`.
- **Campos opcionales**: En `details`, campos como `storeName`, `productName`, `gs1128`, `grossWeight` pueden ser `null`; conviene tratarlos como opcionales al mostrar.
- **Idioma**: Los textos en `action` están en español.
- **Formato numérico**: En `details` los valores numéricos usan punto decimal (ej. `14.5`). En datos legacy, `action` podía incluir números con coma.
- **Compatibilidad**: Soportar tanto el tipo `pallet_updated` (un evento por guardado con `details` agregados) como los tipos legacy (`box_added`, `box_removed`, `box_updated`, `observations_updated`, etc.) para historiales antiguos. Si aparece un `type` no reconocido, mostrar al menos `timestamp`, `userName` y `action`.
- **Totales del palet tras el evento (cajas)**: En eventos que implican cambios de cajas, los totales (número de cajas y peso neto total) van en **`details.afterEvent`** con forma `{ "boxesCount": number, "totalNetWeight": number }`. Los ítems de `boxesAdded`/`boxesRemoved` ya no repiten esos valores. En datos legacy puede seguir existiendo `newBoxesCount`/`newTotalNetWeight` en cada ítem o en details; el frontend puede priorizar `details.afterEvent` si existe y, si no, usar esos campos para no romper historiales antiguos.
- **Borrar historial**: Mostrar el botón "Borrar historial" solo si el usuario tiene rol administrador o técnico; llamar a `DELETE /api/v2/pallets/{id}/timeline`. Si el backend devuelve 403, ocultar o deshabilitar el botón.
