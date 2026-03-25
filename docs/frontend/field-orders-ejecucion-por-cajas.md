# Frontend — Ejecución de pedidos operativos por cajas (Field / Repartidor)

## Objetivo

Este documento define el **contrato e interacción** entre frontend y backend para el rol **`repartidor_autoventa`** en el perímetro `field/*`, específicamente para:

- **Ejecutar un pedido prefijado** guardando **cajas/palets** (ejecución real).
- **Añadir productos extra** no contemplados en el pedido (creando nuevas líneas planificadas).
- **Ajustar** (solo) **precio** e **IVA** de líneas planificadas existentes.

La ejecución se gestiona como **estado completo**: el frontend envía “la foto final” de las cajas del pedido y el backend sincroniza.

## Principios de negocio (muy importantes)

- **No se cambia el estado del pedido desde campo**: el endpoint operativo **NO admite** `status`.
- **No se reescriben las líneas planificadas “por ejecución”**: el endpoint operativo **NO admite** `plannedProducts` (contrato legacy).
- La ejecución real se guarda **en cajas/palets** (tablas `pallets`, `boxes`, `pallet_boxes`), no en planned.
- **Solo se permite:**
  - crear/editar/eliminar cajas de ejecución del pedido (por sync)
  - crear nuevas líneas planned *solo* para extras (sin borrar las existentes)
  - ajustar precio/IVA de líneas planned existentes (sin tocar cantidades)

## Perímetro y headers

- **Base path**: `/api/v2/field/*`
- **Headers**:
  - `X-Tenant: {subdomain}`
  - `Authorization: Bearer {token}`
  - `Accept: application/json`

## Endpoints implicados

### 1) IVA options (para extras)

- `GET /api/v2/field/taxes/options`
- Respuesta: array de impuestos `{ id, name, rate }`

Frontend debe usar este endpoint (NO `GET /api/v2/taxes/options`).

### 2) Pedido operativo — detalle (incluye ejecución con ids)

- `GET /api/v2/field/orders/{orderId}`
- Respuesta incluye:
  - `plannedProductDetails` (planificado)
  - `pallets[]` con `boxes[]` (ejecución) y, crucialmente, **`boxes[].id`**

El frontend usa los `id` de caja para sincronizar en el `PUT`.

### 3) Pedido operativo — guardar ejecución (sync estado completo)

- `PUT /api/v2/field/orders/{orderId}`

Payload admite:
- `boxes` (estado completo de ejecución)
- `plannedExtras` (opcional)
- `plannedAdjustments` (opcional)
- `items` (opcional; solo UI/resumen)

Campos prohibidos (si se envían → 422):
- `status`
- `plannedProducts`

## Respuesta de `GET /field/orders/{orderId}`

### Shape relevante (resumen)

```json
{
  "data": {
    "id": 123,
    "orderType": "standard",
    "status": "pending",
    "customer": { "id": 1, "name": "Cliente" },
    "plannedProductDetails": [
      {
        "id": 555,
        "productId": 10,
        "taxId": 2,
        "quantity": 12.5,
        "boxes": 3,
        "unitPrice": 8.25
      }
    ],
    "pallets": [
      {
        "id": 900,
        "state": { "id": 3, "name": "shipped" },
        "boxes": [
          {
            "id": 7001,
            "palletId": null,
            "product": { "id": 10, "name": "Producto" },
            "lot": "L-001",
            "gs1128": "GS1-...",
            "grossWeight": 7.4,
            "netWeight": 7.1,
            "createdAt": "2026-03-25"
          }
        ]
      }
    ]
  }
}
```

Notas:
- En `boxes[]`, el backend expone `id` (boxId) y `gs1128` (mapea a `boxes.gs1_128`).
- `palletId` puede venir `null` en `Box::toArrayAssocV2()` en algunos paths; para sync **no es necesario**.

## Contrato de `PUT /field/orders/{orderId}` (sync)

### Reglas de sincronización (estado completo)

El backend interpreta `boxes[]` como el **conjunto final** de cajas asociadas al pedido:

- **Update**: si una caja viene con `id` → se actualizan sus campos (lot/pesos/product/gs1128) **validando** que pertenece a ese pedido.
- **Create**: si una caja viene **sin `id`** → se crea una nueva caja + vínculo.
- **Delete**: toda caja existente del pedido cuyo `id` **no aparezca** en el payload se considera eliminada → se borra.

### 1) `boxes[]`

Cada caja puede ser:

- **Existente** (update):
  - `id` (obligatorio)
  - `productId` (obligatorio)
  - `lot` (opcional)
  - `netWeight` (obligatorio)
  - `grossWeight` (opcional)
  - `gs1128` (opcional)

- **Nueva** (create):
  - sin `id`
  - resto igual

Ejemplo (mezcla create + update, y delete implícito):

```json
{
  "boxes": [
    {
      "id": 7001,
      "productId": 10,
      "lot": "L-001",
      "netWeight": 7.20,
      "grossWeight": 7.45,
      "gs1128": "GS1-..."
    },
    {
      "productId": 99,
      "lot": "EXTRA-LOT-1",
      "netWeight": 2.50,
      "grossWeight": 2.60,
      "gs1128": "EXTRA-GS1-1"
    }
  ]
}
```

Si antes el pedido tenía otra caja `id=7002` y ahora no viene, el backend la eliminará.

### 2) `plannedExtras[]` (productos extra no prefijados)

Usar cuando el escaneo detecta productos que **no estaban** en `plannedProductDetails`.

```json
{
  "plannedExtras": [
    { "productId": 99, "unitPrice": 12.0, "taxId": 1 }
  ]
}
```

Reglas:
- `productId` debe existir.
- `unitPrice` y `taxId` son obligatorios.
- Backend crea una nueva línea en `order_planned_product_details` para ese producto (si ya existe, devuelve 422).
- `quantity` y `boxes` de esa línea se derivan del `boxes[]` actual agrupado por `productId`:
  - `boxes` = número de cajas en `boxes[]` con ese `productId`
  - `quantity` = suma de `netWeight` de esas cajas

### 3) `plannedAdjustments[]` (solo precio + IVA en líneas existentes)

Cuando el repartidor modifica precio o IVA de una línea prefijada.

```json
{
  "plannedAdjustments": [
    { "plannedProductDetailId": 555, "unitPrice": 8.25, "taxId": 2 }
  ]
}
```

Reglas:
- `plannedProductDetailId` debe pertenecer al pedido.
- Solo se actualizan `unit_price` y `tax_id` (no cantidad ni cajas).

### 4) `items[]` (opcional)

El frontend puede enviar `items[]` como resumen UI (como autoventa), pero:
- no es obligatorio
- no es la fuente de verdad; la ejecución real es `boxes[]`

## Interacción recomendada en Frontend (paso a paso)

### A) Cargar pantalla de ejecución

1. `GET /api/v2/field/orders/{orderId}`
2. Guardar en estado local:
   - `plannedProductDetails[]` (para comparar y mostrar)
   - `pallets[].boxes[]` (para reconstruir el listado de cajas con `id`)

### B) Escanear / crear cajas en UI

- Si el escaneo devuelve `gs1128`, mapearlo a `boxes[].gs1128` (string).
- Si no hay `gs1128`, se puede dejar vacío (backend generará), pero **recomendado** conservar un identificador local para UX (no contractual).

### C) Detectar “extras”

1. Construir set `plannedProductIds` desde `plannedProductDetails[].productId`.
2. Agrupar cajas por `productId`.
3. Los `productId` presentes en cajas pero no en planned ⇒ candidatos a `plannedExtras`.
4. Para cada extra, pedir al usuario:
   - `unitPrice`
   - `taxId` (seleccionado desde `GET /api/v2/field/taxes/options`)

### D) Guardar (sync)

Enviar `PUT /api/v2/field/orders/{orderId}` con:
- `boxes[]` como **estado completo**:
  - cajas ya existentes deben incluir su `id`
  - cajas nuevas no incluyen `id`
- opcionalmente:
  - `plannedExtras[]`
  - `plannedAdjustments[]`

### E) Tras guardar

- Usar la respuesta para refrescar UI (el backend devuelve `FieldOrderResource` en update; si se necesita detalle con cajas, volver a pedir `GET /field/orders/{orderId}`).
- Recomendación de UX: tras `PUT`, hacer `GET` para rehidratar ids de cajas nuevas y quedar consistente.

## Errores esperables

### 422 — contrato legacy
- Si se envía `status` o `plannedProducts`.

### 422 — integridad de cajas
- Si una caja con `id` no pertenece a ese pedido.
- Si se intenta eliminar una caja usada en producción (invariante): el backend bloqueará el borrado para mantener trazabilidad.

### 403
- Si el usuario no tiene `FieldOperator` o el pedido no está asignado al actor operativo.

## Migración desde el comportamiento anterior (IMPORTANTE)

Antes, el frontend convertía cajas → `plannedProducts` y llamaba al `PUT /field/orders/{order}` con `plannedProducts`.

Ahora:
- dejar de enviar `plannedProducts`
- guardar ejecución enviando `boxes[]` (con `id` para existentes)
- extras se envían en `plannedExtras[]`
- ajustes en `plannedAdjustments[]`

