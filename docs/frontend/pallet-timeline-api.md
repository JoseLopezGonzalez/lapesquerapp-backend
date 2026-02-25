# API Timeline de Palet (F-01) — Especificación para Frontend

Documento de contrato entre backend y frontend: qué devuelve el API, cuándo y con qué estructura. Sin criterios de implementación en UI.

---

## 1. Endpoint

| Método | Ruta | Descripción |
|--------|------|-------------|
| `GET` | `/api/v2/pallets/{id}/timeline` | Obtiene el historial de modificaciones del palet `id` |

### Cuándo llamarlo

- Cuando el usuario entra en el **detalle de un palet** y se quiere mostrar su historial.
- El backend no envía el timeline en `GET /api/v2/pallets/{id}`; hay que pedirlo explícitamente con este endpoint.

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

### Errores

- **401**: No autenticado.
- **403**: Sin permiso para ver el palet (misma política que `GET /api/v2/pallets/{id}`).
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
| `action` | string | Sí | Texto en lenguaje natural listo para mostrar (ej. "Movido al almacén Frigorífico A") |
| `details` | object | Sí | Objeto con datos específicos del tipo; estructura depende de `type` (puede ser `{}`) |

---

## 3. Tipos de evento y estructura de `details`

A continuación se listan **todos** los valores posibles de `type` y, para cada uno, **cuándo se registra** y la forma exacta de `details`.

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

**Ejemplos de `action`**: `"Palet creado con 3 cajas (14.50 kg)"` | `"Palet creado en autoventa con 2 cajas (8.00 kg)"`

---

### 3.2 `pallet_created_from_reception`

**Cuándo**: El palet se crea desde una recepción de materia prima (creación/actualización de recepción en modo palets o en modo líneas que genera un palet).

**`details`**:

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `receptionId` | number | ID de la recepción de materia prima |
| `boxesCount` | number | Número de cajas del palet |
| `totalNetWeight` | number | Peso neto total (kg) |

**Ejemplo de `action`**: `"Palet creado desde la recepción #42 con 5 cajas (22.30 kg)"`

---

### 3.3 `state_changed`

**Cuándo**: Cambio de estado del palet hecho por el usuario (actualización del palet, mover a almacén que pasa a almacenado, cambio masivo de estado, desvincular pedido que pasa a registrado, o marcar pedido como finalizado y el palet pasa a enviado).

**`details`**:

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `fromId` | number | ID del estado anterior (1=registered, 2=stored, 3=shipped, 4=processed) |
| `from` | string | Nombre del estado anterior: `"registered"`, `"stored"`, `"shipped"`, `"processed"` |
| `toId` | number | ID del estado nuevo |
| `to` | string | Nombre del estado nuevo |

**Ejemplos de `action`**:
- `"Estado cambiado de Registrado a Almacenado"`
- `"Estado cambiado a Enviado (desde pedido finalizado)"`

---

### 3.4 `state_changed_auto`

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

**Ejemplos de `action`**:
- `"Estado actualizado automáticamente a Procesado (todas las cajas en producción)"`
- `"Estado actualizado automáticamente a Registrado (todas las cajas disponibles)"`
- `"Estado actualizado automáticamente a Registrado (cajas liberadas de producción)"`

---

### 3.5 `store_assigned`

**Cuándo**: El palet se asigna a un almacén (mover a almacén o editar palet y poner almacén).

**`details`**:

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `storeId` | number | ID del almacén asignado |
| `storeName` | string \| null | Nombre del almacén (puede ser null si no se resolvió) |
| `previousStoreId` | number \| null | ID del almacén anterior; `null` si no estaba en ninguno |
| `previousStoreName` | string \| null | Nombre del almacén anterior; `null` si no había |

**Ejemplo de `action`**: `"Movido al almacén Frigorífico A"` o `"Movido al almacén #3"` si no hay nombre.

---

### 3.6 `store_removed`

**Cuándo**: El palet deja de estar en un almacén (se quita almacén en la edición o al cambiar estado a no almacenado).

**`details`**:

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `previousStoreId` | number | ID del almacén del que se retiró |
| `previousStoreName` | string \| null | Nombre de ese almacén |

**Ejemplo de `action`**: `"Retirado del almacén Frigorífico A"`

---

### 3.7 `position_assigned`

**Cuándo**: Se asigna posición al palet con `POST /api/v2/pallets/assign-to-position`.

**`details`**:

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `positionId` | number | ID/valor de la posición asignada |
| `positionName` | string | Representación en string de la posición (p. ej. el mismo número) |
| `storeId` | number \| null | ID del almacén donde está el palet |
| `storeName` | string \| null | Nombre del almacén |

**Ejemplo de `action`**: `"Posición asignada: 12 (Frigorífico A)"` o `"Posición asignada: 12"` si no hay nombre de almacén.

---

### 3.8 `position_unassigned`

**Cuándo**: Se quita la posición del palet con `POST /api/v2/pallets/{id}/unassign-position`.

**`details`**:

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `previousPositionId` | number | Valor de la posición que tenía |
| `previousPositionName` | string | Mismo valor en string |

**Ejemplo de `action`**: `"Posición 12 eliminada (Frigorífico A)"` o `"Posición 12 eliminada"`

---

### 3.9 `order_linked`

**Cuándo**: El palet se vincula a un pedido (edición del palet con `orderId`, o `POST /api/v2/pallets/{id}/link-order`).

**`details`**:

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `orderId` | number | ID del pedido vinculado |
| `orderReference` | string | Referencia para mostrar (p. ej. `"#142"` o el campo referencia del pedido si existe) |

**Ejemplo de `action`**: `"Vinculado al pedido #142"` o `"Vinculado al pedido PED-2026-142"`

---

### 3.10 `order_unlinked`

**Cuándo**: El palet se desvincula del pedido (edición con `orderId` null o `POST /api/v2/pallets/{id}/unlink-order`).

**`details`**:

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `orderId` | number | ID del pedido del que se desvinculó |
| `orderReference` | string | Referencia para mostrar (mismo criterio que en `order_linked`) |

**Ejemplo de `action`**: `"Desvinculado del pedido #142"`

---

### 3.11 `box_added`

**Cuándo**: En una actualización del palet (`PUT /api/v2/pallets/{id}`) se añade al menos una caja nueva al array de cajas.

**`details`** (por cada caja nueva se genera una entrada; los datos son los de la caja añadida y los totales del palet tras el cambio):

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `boxId` | number | ID de la caja añadida |
| `productId` | number | ID del producto (artículo) |
| `productName` | string \| null | Nombre del producto (`null` si no se resolvió) |
| `lot` | string | Lote de la caja |
| `gs1128` | string \| null | Código GS1-128 |
| `netWeight` | number | Peso neto (kg) |
| `grossWeight` | number \| null | Peso bruto (kg) |
| `newBoxesCount` | number | Número total de cajas del palet después de añadir |
| `newTotalNetWeight` | number | Peso neto total del palet después (kg) |

**Ejemplo de `action`**: `"Caja añadida — Merluza, Lote L-2024-05, 4,20 kg. Total: 4 cajas / 16,70 kg"` (el formato decimal puede usar coma según locale del backend).

---

### 3.12 `box_removed`

**Cuándo**: En una actualización del palet se elimina una caja (la caja existente no viene en el array de cajas enviado).

**`details`**: Misma estructura que `box_added` (datos de la caja eliminada más totales actuales del palet).

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `boxId` | number | ID de la caja eliminada |
| `productId` | number | ID del producto |
| `productName` | string \| null | Nombre del producto |
| `lot` | string | Lote |
| `gs1128` | string \| null | GS1-128 |
| `netWeight` | number | Peso neto de esa caja |
| `grossWeight` | number \| null | Peso bruto |
| `newBoxesCount` | number | Número de cajas del palet después de eliminar |
| `newTotalNetWeight` | number | Peso neto total del palet después (kg) |

**Ejemplo de `action`**: `"Caja eliminada — Merluza, Lote L-2024-05, 4,20 kg. Total: 3 cajas / 12,50 kg"`

---

### 3.13 `box_updated`

**Cuándo**: En una actualización del palet, una caja existente cambia en al menos uno de: producto, lote, peso neto, peso bruto.

**`details`**:

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `boxId` | number | ID de la caja modificada |
| `productId` | number | ID del producto después del cambio |
| `productName` | string \| null | Nombre del producto (estado final) |
| `lot` | string | Lote después del cambio |
| `changes` | object | Solo los campos que cambiaron; cada clave es el nombre del campo y el valor es `{ "from": valorAnterior, "to": valorNuevo }` |

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

### 3.14 `observations_updated`

**Cuándo**: En una actualización del palet (`PUT /api/v2/pallets/{id}`) el campo de observaciones cambia.

**`details`**:

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `from` | string \| null | Observaciones anteriores (puede ser `null` o vacío) |
| `to` | string \| null | Observaciones nuevas |

**Ejemplos de `action`**: `"Observaciones actualizadas"` | `"Observaciones actualizadas (desde recepción)"`

**Nota**: Los mismos tipos `state_changed`, `store_assigned`, `store_removed`, `box_added`, `box_removed`, `box_updated` pueden generarse también al **editar una recepción** (modificar palets existentes desde la pantalla de recepción); en ese caso el texto de `action` incluye la coletilla `"(desde recepción)"`.

---

## 4. Resumen de tipos

| `type` | Descripción breve |
|--------|-------------------|
| `pallet_created` | Palet creado manualmente |
| `pallet_created_from_reception` | Palet creado desde recepción |
| `state_changed` | Cambio de estado por usuario |
| `state_changed_auto` | Cambio de estado automático (producción) |
| `store_assigned` | Asignado a un almacén |
| `store_removed` | Retirado del almacén |
| `position_assigned` | Posición asignada |
| `position_unassigned` | Posición quitada |
| `order_linked` | Vinculado a un pedido |
| `order_unlinked` | Desvinculado de un pedido |
| `box_added` | Caja añadida al palet |
| `box_removed` | Caja eliminada del palet |
| `box_updated` | Caja existente modificada |
| `observations_updated` | Observaciones del palet modificadas |

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
- **`userId` null**: Indica acción automática (p. ej. `state_changed_auto`); `userName` será `"Sistema"`.
- **Campos opcionales**: En `details`, campos como `storeName`, `productName`, `orderReference`, `gs1128`, `grossWeight` pueden ser `null`; conviene tratarlos como opcionales al mostrar.
- **Idioma**: Los textos en `action` están en español.
- **Formato numérico**: En `action` los números pueden usar coma como separador decimal (ej. `14,50`); en `details` los valores son numéricos (ej. `14.5`).
