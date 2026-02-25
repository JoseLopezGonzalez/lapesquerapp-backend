# Timeline Palet (F-01) — Resumen esquemático de eventos

Chequeo rápido: eventos contemplados y qué se guarda en cada uno.

---

## Estructura común de toda entrada

| Campo      | Descripción breve                    |
|-----------|--------------------------------------|
| `timestamp` | ISO 8601                             |
| `userId`    | ID usuario o `null` (Sistema)        |
| `userName`  | Nombre usuario o `"Sistema"`         |
| `type`      | Tipo de evento (tabla abajo)         |
| `action`    | Texto en lenguaje natural           |
| `details`   | Objeto; campos según tipo (abajo)    |

---

## Tabla resumen: tipo → cuándo → qué guardamos en `details`

| Tipo | Cuándo se dispara | Detalles guardados (resumen) |
|------|-------------------|------------------------------|
| **pallet_created** | Crear palet manual (`POST /pallets`) | `boxesCount`, `totalNetWeight`, `initialState`, `storeId`, `storeName`, `orderId` |
| **pallet_created_from_reception** | Palet creado desde recepción (modo palets o líneas) | `receptionId`, `boxesCount`, `totalNetWeight` |
| **state_changed** | Cambio de estado por usuario (edición, mover a almacén, bulk state, desvincular, pedido finalizado) | `fromId`, `from`, `toId`, `to` |
| **state_changed_auto** | Cambio automático por producción (todas cajas usadas → processed; liberadas → registered) | `fromId`, `from`, `toId`, `to`, `reason` (`all_boxes_in_production` \| `boxes_released_from_production` \| `partial_boxes_released`), `usedBoxesCount`, `totalBoxesCount` |
| **store_assigned** | Palet asignado a almacén (mover o editar con almacén) | `storeId`, `storeName`, `previousStoreId`, `previousStoreName` |
| **store_removed** | Palet retirado del almacén | `previousStoreId`, `previousStoreName` |
| **position_assigned** | Asignar posición (`POST /pallets/assign-to-position`) | `positionId`, `positionName`, `storeId`, `storeName` |
| **position_unassigned** | Quitar posición (`POST /pallets/{id}/unassign-position`) | `previousPositionId`, `previousPositionName` |
| **order_linked** | Vincular a pedido (edición o `POST /pallets/{id}/link-order`) | `orderId`, `orderReference` |
| **order_unlinked** | Desvincular de pedido (edición o `POST /pallets/{id}/unlink-order`) | `orderId`, `orderReference` |
| **box_added** | Añadir caja(s) en edición del palet | `boxId`, `productId`, `productName`, `lot`, `gs1128`, `netWeight`, `grossWeight`, `newBoxesCount`, `newTotalNetWeight` |
| **box_removed** | Quitar caja en edición del palet | Igual que box_added + totales tras eliminar |
| **box_updated** | Modificar caja existente (producto, lote, pesos) en edición | `boxId`, `productId`, `productName`, `lot`, `changes` (solo campos cambiados: `netWeight`, `grossWeight`, `lot`, `productId` con `from`/`to`) |
| **observations_updated** | Cambiar observaciones en edición del palet | `from`, `to` |

---

## Origen por flujo (dónde se registra cada tipo)

| Flujo / Lugar | Tipos que puede generar |
|---------------|-------------------------|
| **PalletWriteService::store()** | pallet_created |
| **PalletWriteService::update()** | state_changed, store_assigned, store_removed, order_linked, order_unlinked, box_added, box_removed, box_updated, observations_updated |
| **PalletActionService::moveToStore()** | state_changed (si pasa a stored), store_assigned |
| **PalletActionService::assignToPosition()** | position_assigned |
| **PalletActionService::unassignPosition()** | position_unassigned |
| **PalletActionService::bulkUpdateState()** | state_changed (por cada palet) |
| **PalletActionService::linkOrder()** | order_linked |
| **PalletActionService::unlinkOrder()** | order_unlinked |
| **Pallet::changeToShipped()** | state_changed (a shipped) |
| **Pallet::updateStateBasedOnBoxes()** | state_changed_auto (processed o registered) |
| **RawMaterialReceptionWriteService** (crear/actualizar recepción con palets) | pallet_created_from_reception |

---

## Estados del palet (referencia para from/to)

| ID | Nombre |
|----|--------|
| 1 | registered |
| 2 | stored |
| 3 | shipped |
| 4 | processed |

---

## Comprobación rápida: ¿está todo?

- [x] Creación palet (manual y desde recepción)
- [x] Cambio de estado (manual y automático)
- [x] Asignación / retirada de almacén
- [x] Asignación / retirada de posición
- [x] Vinculación / desvinculación de pedido
- [x] Alta / baja / modificación de cajas
- [x] Cambio de observaciones

**Total: 14 tipos de evento.**
