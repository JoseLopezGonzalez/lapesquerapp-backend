# Timeline de Palet — Ideas de mejora

Documento abierto para recoger mejoras deseadas en el sistema de historial del palet. Incluye detalles técnicos de estructura y ejemplos; sin plan de implementación paso a paso. Se irá completando con más aspectos antes de cerrar.

---

## 1. Un solo evento por acción de guardado

### Problema

Cuando el usuario guarda cambios en un palet (un único "Guardar"), el backend genera **varias entradas** en el historial: una por cada caja añadida, una por cada caja eliminada, una por cada caja modificada, más una por cambio de pedido, otra por observaciones, etc. Ejemplo: 4 cajas nuevas + cambio de pedido + observaciones → 6 eventos. El usuario percibe una sola acción pero el timeline se llena de ítems que corresponden a un mismo guardado.

### Idea

Una sola acción de guardado (un PUT al palet, un "Guardar" en formulario) debe generar **un único evento** en el timeline. Ese evento agrupa todos los cambios de esa guardada en un solo `type` con un `action` resumen y un `details` que describe cada cambio.

### Alcance

- **Incluye**: actualización del palet desde el formulario de palet (`PalletWriteService::update`) y edición de palets desde la pantalla de recepción (un evento por palet por guardado de recepción).
- **No incluye**: acciones puntuales (mover a almacén, asignar posición, link/unlink order, bulk state, etc.) siguen siendo un evento por acción porque ya representan un solo paso del usuario. Creación de palet y cambios automáticos (estado por producción, enviado por pedido finalizado) tampoco cambian.

### Nuevo tipo de evento: `pallet_updated`

- **Cuándo**: Al guardar cambios en un palet (PUT del formulario) o al guardar la recepción habiendo modificado palets existentes. Solo se registra si **hubo al menos un cambio**; si no hubo cambios, no se escribe ningún evento.
- **Variante desde recepción**: Puede usarse el mismo `type` `pallet_updated` y en `action` (o en `details`) indicar el origen, p. ej. texto con coletilla `" (desde recepción)"` y/o un campo `details.fromReception = true` y `details.receptionId` para trazabilidad.

### Estructura técnica de `details`

Solo se incluyen en `details` las claves que realmente cambiaron. Si algo no cambió, no se incluye. Así el frontend puede iterar solo sobre lo relevante.

| Clave | Tipo | Descripción |
|-------|------|-------------|
| `observations` | `{ "from": string \| null, "to": string \| null }` | Solo si cambiaron las observaciones. |
| `state` | `{ "fromId": number, "from": string, "toId": number, "to": string }` | Solo si cambió el estado (mismos nombres que en el resto del API: `registered`, `stored`, `shipped`, `processed`). |
| `store` | Ver abajo | Solo si cambió la asignación de almacén. |
| `order` | Ver abajo | Solo si cambió la vinculación al pedido. |
| `boxesAdded` | `array` de objetos caja | Lista de cajas añadidas en esta guardada. |
| `boxesRemoved` | `array` de objetos caja | Lista de cajas eliminadas en esta guardada. |
| `boxesUpdated` | `array` de objetos con `changes` | Lista de cajas modificadas (solo las que tuvieron cambios en producto, lote o pesos). |
| `fromReception` | `boolean` | *(Opcional)* `true` si el cambio se hizo desde la edición de una recepción. |
| `receptionId` | `number` | *(Opcional)* ID de la recepción; solo si `fromReception === true`. |

**Forma de `store`** (una u otra, no ambas):

- Si se **asignó** almacén:  
  `"store": { "assigned": { "storeId": number, "storeName": string | null, "previousStoreId": number | null, "previousStoreName": string | null } }`
- Si se **retiró** del almacén:  
  `"store": { "removed": { "previousStoreId": number, "previousStoreName": string | null } }`

**Forma de `order`** (una u otra):

- Si se **vinculó** a un pedido:  
  `"order": { "linked": { "orderId": number, "orderReference": string } }`
- Si se **desvinculó**:  
  `"order": { "unlinked": { "orderId": number, "orderReference": string } }`

**Objeto “caja” en `boxesAdded` y `boxesRemoved`** (cada elemento del array):

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `boxId` | number | ID de la caja. |
| `productId` | number | ID del producto. |
| `productName` | string \| null | Nombre del producto. |
| `lot` | string | Lote. |
| `gs1128` | string \| null | Código GS1-128. |
| `netWeight` | number | Peso neto (kg). |
| `grossWeight` | number \| null | Peso bruto (kg). |
| `newBoxesCount` | number | *(Opcional)* Número total de cajas del palet **después** de esta guardada (puede ir una sola vez a nivel de resumen si se prefiere). |
| `newTotalNetWeight` | number | *(Opcional)* Peso neto total del palet después (kg). |

**Objeto en `boxesUpdated`** (cada elemento):

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `boxId` | number | ID de la caja. |
| `productId` | number | ID del producto (estado final). |
| `productName` | string \| null | Nombre del producto. |
| `lot` | string | Lote (estado final). |
| `changes` | object | Solo los campos que cambiaron; cada clave es el nombre del campo y el valor es `{ "from": valorAnterior, "to": valorNuevo }`. Claves posibles: `netWeight`, `grossWeight`, `lot`, `productId` (y si se expone nombre: `productName`). |

### Texto de `action`

El campo `action` es un **título breve** (una línea), no una descripción. El detalle completo está en `details`; el frontend puede mostrar el título en lista y el contenido de `details` al expandir.

- Formulario de palet: `"Palet actualizado"`.
- Desde edición de recepción: `"Palet actualizado (desde recepción)"`.

### Ejemplo completo de entrada `pallet_updated`

```json
{
  "timestamp": "2026-02-27T11:30:00.000000Z",
  "userId": 3,
  "userName": "María García",
  "type": "pallet_updated",
  "action": "Palet actualizado",
  "details": {
    "observations": {
      "from": null,
      "to": "Revisar antes de enviar"
    },
    "order": {
      "linked": {
        "orderId": 142,
        "orderReference": "#142"
      }
    },
    "boxesAdded": [
      {
        "boxId": 501,
        "productId": 10,
        "productName": "Merluza",
        "lot": "L-2025-01",
        "gs1128": null,
        "netWeight": 4.2,
        "grossWeight": 4.5,
        "newBoxesCount": 7,
        "newTotalNetWeight": 28.4
      },
      {
        "boxId": 502,
        "productId": 10,
        "productName": "Merluza",
        "lot": "L-2025-01",
        "gs1128": null,
        "netWeight": 4.0,
        "grossWeight": null,
        "newBoxesCount": 7,
        "newTotalNetWeight": 28.4
      }
    ]
  }
}
```

*(En el ejemplo solo se muestran 2 cajas en `boxesAdded` por brevedad; en un caso real serían las 4. Los totales `newBoxesCount` y `newTotalNetWeight` pueden repetirse en cada caja o enviarse una vez a nivel raíz de `details`, p. ej. `summary: { boxesCount: 7, totalNetWeight: 28.4 }`.)*

### Compatibilidad con eventos ya guardados

Los eventos **ya existentes** en el timeline conservan sus tipos actuales (`box_added`, `box_removed`, `order_linked`, `observations_updated`, etc.). El frontend debe seguir reconociendo esos tipos para mostrar el historial antiguo. A partir del cambio, en **nuevas guardadas** solo se emitirá `pallet_updated` (o la variante desde recepción) para agrupar todos los cambios de esa guardada. No es necesario migrar datos antiguos.

---

## 2. Borrar historial del palet (solo roles altos)

**Idea**: Poder borrar todo el historial (timeline) de un palet desde la API, restringido a los roles con más privilegios (administrador y técnico), **no** al operario ni al resto de roles que solo usan el sistema de forma operativa.

**Detalles**:

- **Endpoint**: `DELETE /api/v2/pallets/{id}/timeline`. Misma ruta base que el GET del timeline; el método HTTP distingue entre ver (GET) y borrar (DELETE).
- **Autorización**: Solo usuarios con rol **administrador** o **técnico** pueden llamar a este endpoint. Cualquier otro rol (operario, comercial, dirección, administración) debe recibir 403.
- **Comportamiento**: Se vacía la columna `timeline` del palet (array JSON pasa a `[]`). El palet sigue existiendo; solo se elimina el historial de eventos. No se registra ningún evento por el propio borrado.
- **Respuesta exitosa**: 200 con cuerpo p. ej. `{ "message": "Historial del palet borrado correctamente" }`.
- **Errores**: 401 no autenticado; 403 sin permiso (rol no autorizado o policy); 404 palet no encontrado.

**Implementación sugerida (referencia)**:

- En la policy del palet, una capacidad nueva (p. ej. `clearTimeline`) que compruebe que el usuario tiene uno de los roles permitidos (administrador, técnico), al estilo de otras políticas que restringen acciones destructivas (p. ej. `UserPolicy::delete`).
- En el controlador, una acción que cargue el palet, haga `authorize('clearTimeline', $pallet)` y llame a un método del servicio de timeline que actualice la columna `timeline` a array vacío (sin disparar eventos del modelo, igual que al escribir entradas).
- Registrar la ruta `DELETE` para `pallets/{id}/timeline` apuntando a esa acción.

---

## 3. (Reservado para más ideas)

*Se irán añadiendo aquí otras mejoras a medida que se definan.*

---

*Última actualización: 2026-02-27.*
