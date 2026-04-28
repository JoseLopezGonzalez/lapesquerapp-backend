# Frontend — Cierre definitivo de producciones

> **Versión de API**: v2  
> **Roles que pueden cerrar/reabrir**: `administrador`, `direccion`  
> **Todos los roles**: pueden consultar el estado de cierre y ver el resultado de `closure-check`

---

## 1. Nuevos campos en `Production`

Todos los endpoints que devuelven una producción (`GET /productions/{id}`, `POST /close`, `POST /reopen`) incluyen ahora estos campos adicionales:

```jsonc
{
  "id": 12,
  "lot": "LOT-2024-003",
  "isOpen": false,       // true = producción del día abierta
  "isClosed": true,      // true = cerrada DEFINITIVAMENTE (lote bloqueado)
  "closedAt": "2026-04-28T10:00:00Z",   // null si no está cerrada
  "closedBy": 3,                         // id del usuario que cerró
  "closureReason": "Lote liquidado completamente.",
  "closedByUser": { "id": 3, "name": "Admin" },  // null si no cerrada
  "reopenedAt": null,    // fecha de la última reapertura (si la hay)
  "reopenedBy": null,
  "reopenReason": null,
  "reopenedByUser": null
  // ... resto de campos habituales
}
```

**`isOpen` vs `isClosed`**: son estados independientes. Una producción puede estar `isOpen: false` (jornada cerrada) sin estar `isClosed: true` (cierre definitivo). El cierre definitivo bloquea el lote.

---

## 2. Flujo de UI recomendado

```
[Detalle de producción]
  ↓
  Botón "Verificar cierre" → GET /closure-check
  ↓
  Si canClose: true  → mostrar resumen + Botón "Cerrar definitivamente"
  Si canClose: false → mostrar lista de bloqueos + guiar al usuario
  ↓
  Usuario confirma + escribe motivo → POST /close
  ↓
  Producción queda bloqueada (isClosed: true)
  ↓ (solo admin/dirección)
  Botón "Reabrir" + motivo → POST /reopen
```

---

## 3. Endpoints

### 3.1 Verificar si se puede cerrar

```
GET /api/v2/productions/{id}/closure-check
```

No requiere body. Requiere permiso `view` sobre la producción.

**Respuesta exitosa (200):**

```json
{
  "message": "Evaluación de cierre obtenida correctamente.",
  "data": {
    "canClose": false,
    "blockingReasons": [
      {
        "code": "stock_remaining",
        "message": "El palet #45 tiene cajas del lote en stock.",
        "palletId": 45
      },
      {
        "code": "pending_order",
        "message": "El pedido #12 está en estado 'pending' y contiene palets de este lote.",
        "orderId": 12
      }
    ],
    "summary": {
      "producedWeight": 1000.00,
      "producedBoxes": 50,
      "salesWeight": 700.00,
      "reprocessedWeight": 200.00,
      "stockWeight": 100.00,
      "balanceWeight": 0.00
    }
  }
}
```

Cuando `canClose: true`, `blockingReasons` es `[]` y `summary` muestra el desglose final.

#### Códigos de bloqueo posibles

| `code` | Qué significa | Qué debe hacer el usuario |
|---|---|---|
| `already_closed` | El lote ya está cerrado | — |
| `not_open` | La producción no está en estado abierto | Abrir la producción primero |
| `no_processes` | No hay ningún proceso registrado | Añadir procesos |
| `process_not_started` | Un proceso no tiene `started_at` | Completar el proceso (`recordId` indica cuál) |
| `process_not_finished` | Un proceso no tiene `finished_at` | Completar el proceso (`recordId` indica cuál) |
| `final_node_no_outputs` | Un proceso final no tiene outputs | Registrar outputs (`recordId` indica cuál) |
| `pending_order` | Hay un pedido `pending` o `incident` con palets del lote | Finalizar o resolver el pedido (`orderId` indica cuál) |
| `pallet_not_shipped` | Un palet asignado a pedido no está en estado `shipped` | Marcar el palet como expedido (`palletId` indica cuál) |
| `stock_remaining` | Hay palets del lote en stock sin pedido | Asignar a pedido o procesar (`palletId` indica cuál) |
| `orphan_box` | Hay cajas del lote sin palet ni reproceso | Ubicar la caja (`boxId` indica cuál) |
| `reconciliation_not_ok` | La conciliación está en `warning` o `error` | Revisar la conciliación del lote (`reconciliationStatus` indica el estado) |

---

### 3.2 Cerrar definitivamente

```
POST /api/v2/productions/{id}/close
Content-Type: application/json

{
  "reason": "Lote liquidado. Toda la producción vendida o reprocesada."
}
```

- `reason`: obligatorio, string, máximo 500 caracteres.
- Requiere rol `administrador` o `direccion`.

**Respuesta exitosa (200):**

```json
{
  "message": "Producción cerrada definitivamente.",
  "data": { /* ProductionResource completo con isClosed: true */ }
}
```

**Error si no puede cerrarse (500 / RuntimeException):**

```json
{
  "message": "No se puede cerrar la producción: El palet #45 tiene cajas del lote en stock. | ..."
}
```

Llama siempre a `closure-check` antes de mostrar el botón de cierre para dar feedback específico por ítem, en lugar de mostrar el mensaje de error concatenado.

---

### 3.3 Reabrir

```
POST /api/v2/productions/{id}/reopen
Content-Type: application/json

{
  "reason": "Corrección de error en outputs del proceso 2."
}
```

- `reason`: obligatorio, string, máximo 500 caracteres.
- Requiere rol `administrador` o `direccion`.

**Respuesta exitosa (200):**

```json
{
  "message": "Producción reabierta correctamente.",
  "data": { /* ProductionResource completo con isClosed: false, reopenedAt: "..." */ }
}
```

---

## 4. Errores de lote bloqueado

Cuando se intenta mutar cualquier entidad asociada a un lote cerrado (palet, caja, input, output, coste, proceso, pedido...), el backend responde con un **500** cuyo `message` sigue este patrón:

```
No se puede ejecutar 'OPERACIÓN': el lote 'LOT-2024-003' pertenece a una producción cerrada definitivamente. Reabre la producción antes de realizar cambios.
```

O para palets con varias cajas de lotes cerrados:

```
No se puede ejecutar 'OPERACIÓN': el palet #45 contiene cajas de lotes cerrados (LOT-2024-003, LOT-2024-004). Reabre las producciones antes de realizar cambios.
```

**Recomendación**: captura estos errores globalmente en tu cliente API y muestra el `message` del servidor directamente — ya viene en español y es legible por el usuario.

---

## 5. Qué queda bloqueado tras el cierre

### Producción

| Acción | Resultado |
|---|---|
| Editar cualquier campo (incluyendo `notes`) | ❌ Error 403 / 500 |
| Eliminar la producción | ❌ Error 500 |

### Procesos (`ProductionRecord`)

| Acción | Resultado |
|---|---|
| Eliminar un proceso | ❌ Bloqueado por lote |
| Marcar proceso como finalizado | ❌ Bloqueado por lote |

### Inputs / Outputs / Costes

| Acción | Resultado |
|---|---|
| Crear, editar o eliminar `ProductionInput` | ❌ Bloqueado por caja/lote |
| Crear, editar o eliminar `ProductionOutput` | ❌ Bloqueado por lote |
| Crear, editar o eliminar `ProductionOutputConsumption` | ❌ Bloqueado por lote |
| Crear, editar o eliminar `ProductionCost` | ❌ Bloqueado por lote |

### Palets y pedidos

| Acción | Resultado |
|---|---|
| Editar o eliminar un palet con cajas del lote | ❌ Bloqueado |
| Desvincular un palet del pedido | ❌ Bloqueado |
| Revertir pedido de `finished` a `pending` o `incident` | ❌ Bloqueado si contiene palets del lote |

---

## 6. Cuándo mostrar el botón de cierre

```ts
const canShowCloseButton =
  production.isOpen &&          // la jornada está abierta
  !production.isClosed &&       // no está ya cerrada
  userHasRole(['administrador', 'direccion']);
```

Llama a `closure-check` al entrar en el detalle de producción (o solo al hacer clic en "Verificar") para no añadir latencia innecesaria al listado.

---

## 7. Cuándo mostrar el botón de reapertura

```ts
const canShowReopenButton =
  production.isClosed &&
  userHasRole(['administrador', 'direccion']);
```

Siempre pedir motivo mediante modal de confirmación antes de enviar.

---

## 8. Eliminación de `lotId` en outputs

El campo `lotId` ha sido eliminado de `ProductionOutput` — **elimínalo de cualquier formulario o tabla donde lo tengas**. El lote de un output siempre es el lote de la producción (`production.lot`).

Endpoints afectados: `POST /production-outputs`, `PUT /production-outputs/{id}`, `POST /production-records/{id}/sync-outputs`, `POST /production-outputs/bulk`.
