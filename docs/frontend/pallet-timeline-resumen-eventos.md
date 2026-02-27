# Timeline Palet (F-01) — Resumen esquemático de eventos

Chequeo rápido: eventos contemplados, qué se guarda en cada uno y endpoints. A partir de la mejora "un evento por guardado", el backend emite **un solo** evento `pallet_updated` por cada guardado (formulario de palet o edición de recepción); los tipos granulares (`box_added`, `box_removed`, etc.) solo aparecen en **datos antiguos**.

---

## Endpoints

| Método | Ruta | Descripción |
|--------|------|-------------|
| `GET` | `/api/v2/pallets/{id}/timeline` | Obtener historial del palet. |
| `DELETE` | `/api/v2/pallets/{id}/timeline` | Borrar todo el historial. **Solo administrador y técnico** (403 para el resto). |

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
| **pallet_created** | Crear palet manual (`POST /pallets`) o en autoventa | `boxesCount`, `totalNetWeight`, `initialState`, `storeId`, `storeName`, `orderId`; si autoventa: `fromAutoventa: true`, `initialState`: `shipped` |
| **pallet_created_from_reception** | Palet creado desde recepción (modo palets o líneas) | `receptionId`, `boxesCount`, `totalNetWeight` |
| **pallet_updated** | **Un único evento por guardado**: formulario de palet (`PUT /pallets/{id}`) o guardar recepción con palets modificados. Agrupa todos los cambios. | Solo claves que cambiaron: `observations`, `state`, `store` (assigned/removed), `order` (linked/unlinked), `boxesAdded`, `boxesRemoved`, `boxesUpdated`; si desde recepción: `fromReception`, `receptionId` |
| **state_changed** | Cambio de estado por usuario en **acción puntual**: mover a almacén, bulk state, desvincular pedido, pedido finalizado | `fromId`, `from`, `toId`, `to` |
| **state_changed_auto** | Cambio automático por producción (todas cajas usadas → processed; liberadas → registered) | `fromId`, `from`, `toId`, `to`, `reason`, `usedBoxesCount`, `totalBoxesCount` |
| **store_assigned** | Palet asignado a almacén con **acción** "mover a almacén" (no desde edición) | `storeId`, `storeName`, `previousStoreId`, `previousStoreName` |
| **store_removed** | Solo dentro de `pallet_updated` o en datos legacy | `previousStoreId`, `previousStoreName` |
| **position_assigned** | Asignar posición (`POST /pallets/assign-to-position`) | `positionId`, `positionName`, `storeId`, `storeName` |
| **position_unassigned** | Quitar posición (`POST /pallets/{id}/unassign-position`) | `previousPositionId`, `previousPositionName` |
| **order_linked** | Vincular a pedido con **acción** link-order (no desde edición) | `orderId`, `orderReference` |
| **order_unlinked** | Desvincular con **acción** unlink-order (no desde edición) | `orderId`, `orderReference` |
| **box_added** | *(Solo datos antiguos)* Antes: una entrada por caja añadida | `boxId`, `productId`, `productName`, `lot`, `gs1128`, `netWeight`, `grossWeight`, `newBoxesCount`, `newTotalNetWeight` |
| **box_removed** | *(Solo datos antiguos)* | Igual que box_added + totales tras eliminar |
| **box_updated** | *(Solo datos antiguos)* | `boxId`, `productId`, `productName`, `lot`, `changes` |
| **observations_updated** | *(Solo datos antiguos)* | `from`, `to` |

---

## Origen por flujo (dónde se registra cada tipo)

| Flujo / Lugar | Tipos que puede generar |
|---------------|-------------------------|
| **PalletWriteService::store()** | pallet_created |
| **PalletWriteService::update()** | **pallet_updated** (un evento por guardado con todos los cambios) |
| **AutoventaStoreService** (crear autoventa) | pallet_created (con fromAutoventa) |
| **RawMaterialReceptionWriteService::updatePalletsFromRequest()** (editar recepción, palets existentes modificados) | **pallet_updated** (un evento por palet, con fromReception/receptionId) |
| **PalletActionService::moveToStore()** | state_changed (si pasa a stored), store_assigned |
| **PalletActionService::assignToPosition()** | position_assigned |
| **PalletActionService::unassignPosition()** | position_unassigned |
| **PalletActionService::bulkUpdateState()** | state_changed (por cada palet) |
| **PalletActionService::linkOrder()** | order_linked |
| **PalletActionService::unlinkOrder()** | order_unlinked |
| **Pallet::changeToShipped()** | state_changed (a shipped) |
| **Pallet::updateStateBasedOnBoxes()** | state_changed_auto (processed o registered) |
| **RawMaterialReceptionWriteService** (crear/actualizar recepción con palets nuevos) | pallet_created_from_reception |
| **DELETE /pallets/{id}/timeline** | No registra evento; vacía el historial. Solo admin y técnico. |

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
- [x] Guardado con cambios agrupados (pallet_updated)
- [x] Cambio de estado (manual y automático)
- [x] Asignación / retirada de almacén
- [x] Asignación / retirada de posición
- [x] Vinculación / desvinculación de pedido
- [x] Alta / baja / modificación de cajas (dentro de pallet_updated o legacy)
- [x] Cambio de observaciones (dentro de pallet_updated o legacy)
- [x] Borrar historial (DELETE, solo admin y técnico)

**Total: 15 tipos de evento.** El frontend debe soportar todos para compatibilidad con historiales antiguos; los nuevos guardados emiten sobre todo `pallet_updated`.
