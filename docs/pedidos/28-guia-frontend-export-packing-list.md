# Guía Frontend: Export Packing List (Exportación Marítima)

## 📋 Resumen

Esta guía documenta la implementación **ya realizada en backend** de todo lo necesario para generar el **Export Packing List** de un pedido de exportación marítima (`order_type = maritime_export`), construida sobre la base ya existente de `OrderMaritimeShippingDetail` / `OrderMaritimeContainer` (ver evolution log, entrada de exportación marítima).

Piezas nuevas:
- **`CustomsBroker`** — catálogo de agentes de aduanas (Intermediate Consignee), reutilizable entre pedidos.
- **Campos nuevos en `OrderMaritimeShippingDetail`**: `customsBrokerId` (link al agente) y `ultimateConsigneeName`/`ultimateConsigneeAddress` (override opcional del consignatario final; si no se rellenan, el documento usa los datos del `Customer` del pedido).
- **`hsCode`** en `Product` — código arancelario (HTSUS) por producto.
- **Asignación de palets a contenedores** — un pedido puede tener varios contenedores (`OrderMaritimeContainer`, ya existía); ahora cada `Pallet` puede quedar vinculado a **uno** de esos contenedores, individualmente o en bloque, para repartir la mercancía real entre contenedores físicos.
- **Endpoint de PDF** — `Export Packing List`, uno por contenedor, con el mismo lenguaje visual que el resto de documentos del pedido.

Todo lo relativo a buque, viaje, puertos, SWB, nº de contenedor y precinto **ya existía** (feature previo) y no cambia — se documenta aquí también para tener el cuadro completo.

---

## 1. Modelo de Datos

### `CustomsBroker` (catálogo nuevo)

| Campo | Tipo | Notas |
|-------|------|-------|
| `id` | number | |
| `name` | string | |
| `address` | string\|null | Texto libre, puede tener saltos de línea |
| `phone` | string\|null | |
| `email` | string\|null | |
| `createdAt` / `updatedAt` | string | |

### `OrderMaritimeShippingDetail` (campos nuevos resaltados)

| Campo | Tipo | Notas |
|-------|------|-------|
| `id`, `orderId` | number | Sin cambios |
| `vesselName`, `voyageNumber`, `exportInvoiceNumber`, `swbNumber`, `loadingPort`, `dischargePort` | string\|null | Sin cambios (feature previo) |
| **`customsBrokerId`** | number\|null | **Nuevo**. FK a `CustomsBroker` |
| **`customsBroker`** | object\|null | **Nuevo**. Objeto `CustomsBroker` completo, presente cuando `customsBrokerId` no es null |
| **`ultimateConsigneeName`** | string\|null | **Nuevo**. Si es null, el documento usa `order.customer.name` |
| **`ultimateConsigneeAddress`** | string\|null | **Nuevo**. Si es null, el documento usa `order.shippingAddress` |

### `Product` (campo nuevo)

| Campo | Tipo | Notas |
|-------|------|-------|
| `hsCode` | string\|null | **Nuevo**. Código arancelario (HTSUS), ej. `"0307520000"` |

### `Pallet` (campo nuevo)

| Campo | Tipo | Notas |
|-------|------|-------|
| `orderMaritimeContainerId` | number\|null | **Nuevo**. Contenedor al que está asignado el palet, si alguno |

### `OrderMaritimeContainer` (campo nuevo)

| Campo | Tipo | Notas |
|-------|------|-------|
| `id`, `orderId`, `containerNumber`, `sealNumber` | — | Sin cambios |
| **`palletIds`** | number[]\|null | **Nuevo**. Solo presente cuando la relación `pallets` viene cargada (p. ej. en el detalle del pedido); `null` si no se cargó |

> ⚠️ Un palet solo puede estar asignado a **un** contenedor a la vez. Asignarlo a un contenedor nuevo desvincula automáticamente cualquier asignación previa (no hay que desasignar primero).

---

## 2. Catálogo de Agentes de Aduanas — `/api/v2/customs-brokers`

Mismo patrón que otros catálogos simples del proyecto (`transports`, `payment-terms`).

### Listado (sin paginar)

```
GET /api/v2/customs-brokers
```
```json
{
  "data": [
    { "id": 1, "name": "NDC Customs Brokers", "address": "372 Av. Escorial, San Juan, 00920", "phone": "+1 787-781-0000", "email": "ndc@ndcpr.com", "createdAt": "...", "updatedAt": "..." }
  ]
}
```
No disponible para rol `comercial` (403 en `viewAny`/`view`).

### Opciones para selects

```
GET /api/v2/customs-brokers/options
```
Array plano `[{ "id": 1, "name": "NDC Customs Brokers" }]`, disponible para **cualquier rol autenticado** (incluido `comercial`), útil para el selector dentro del formulario de datos de envío marítimo aunque el usuario no tenga acceso a la pantalla de mantenimiento del catálogo.

### Crear

```
POST /api/v2/customs-brokers
{ "name": "NDC Customs Brokers", "address": "372 Av. Escorial, San Juan, 00920", "phone": "+1 787-781-0000", "email": "ndc@ndcpr.com" }
```
- `name` requerido, mín. 3, máx. 255.
- `address`, `phone`, `email` opcionales.

`201` con `{ "message": "...", "data": {...CustomsBroker...} }`.

### Actualizar

```
PATCH /api/v2/customs-brokers/{id}
{ "phone": "+1 787-000-0000" }
```
Campos `sometimes` (parciales). `200` con el mismo shape.

### Eliminar

```
DELETE /api/v2/customs-brokers/{id}
```
- `200` si no está en uso.
- **400** si algún pedido tiene datos de envío marítimo apuntando a este agente:
```json
{
  "message": "No se puede eliminar el agente de aduanas porque está en uso",
  "userMessage": "No se puede eliminar el agente de aduanas porque está siendo utilizado en uno o más pedidos."
}
```
No hay borrado múltiple para este catálogo (a diferencia de `transports`/`auxiliary-products`) — no se consideró necesario dado el volumen esperado de agentes distintos.

---

## 3. Datos de Envío Marítimo — Campos Nuevos

El endpoint **ya existente** gana campos nuevos, sin cambiar su forma de uso (upsert, reemplazo completo):

```
PUT /api/v2/orders/{order}/maritime-shipping-details
{
  "vesselName": "Mando 631S",
  "voyageNumber": "V-2026-045",
  "exportInvoiceNumber": "BR26/377",
  "swbNumber": null,
  "loadingPort": "Vigo",
  "dischargePort": "San Juan",
  "customsBrokerId": 1,
  "ultimateConsigneeName": "Jose Santiago, Inc",
  "ultimateConsigneeAddress": "P.O. BOX 191795\nSan Juan, Puerto Rico 00919-1795"
}
```
- `customsBrokerId` opcional, debe existir en `customs_brokers` si se envía.
- `ultimateConsigneeName`/`ultimateConsigneeAddress` opcionales, texto libre. Si se dejan en blanco, el Export Packing List usará automáticamente `order.customer.name` / `order.shippingAddress` — **no hace falta rellenarlos si el consignatario es el mismo cliente del pedido**, solo cuando difiere (caso típico: el comprador es un importador pero la mercancía física la recibe otro almacén).

`GET /api/v2/orders/{order}/maritime-shipping-details` devuelve el mismo shape ampliado, con `customsBroker` ya resuelto (objeto completo, no solo el id).

---

## 4. Asignación de Palets a Contenedores

Un pedido de exportación marítima puede tener varios `OrderMaritimeContainer`. Los palets reales del pedido (los mismos que ya ves en el detalle, `order.pallets`) se reparten entre esos contenedores para poder generar un Export Packing List correcto por contenedor.

### 4.1 Asignación individual

```
PATCH /api/v2/orders/{order}/pallets/{pallet}/maritime-container
{ "containerId": 7 }
```
- `containerId` puede ser `null` para **desasignar** el palet (queda sin contenedor).
- Si `containerId` no es null, debe corresponder a un contenedor del **mismo pedido** (404 si no).
- El palet debe pertenecer al pedido de la URL (404 si no).

Respuesta `200`:
```json
{
  "message": "Palet asignado al contenedor correctamente.",
  "data": { "id": 12, "orderMaritimeContainerId": 7, "...": "resto del shape habitual de Pallet (toArrayAssocV2)" }
}
```

Usa este endpoint para un checkbox/select "contenedor" en la fila de un palet individual dentro del listado de palets del pedido.

### 4.2 Asignación en bloque

```
POST /api/v2/orders/{order}/maritime-containers/{container}/pallets
{ "palletIds": [12, 13, 14] }
```
Asigna **todos** los palets indicados a ese contenedor de una sola vez (sobrescribe cualquier asignación previa que tuvieran a otro contenedor del mismo pedido).

- **422** si alguno de los `palletIds` no pertenece a este pedido:
```json
{
  "message": "Uno o más palets no pertenecen a este pedido.",
  "userMessage": "Uno o más palets seleccionados no pertenecen a este pedido: 99"
}
```
Ningún palet se asigna si hay algún id inválido (operación todo-o-nada).

Respuesta `200`:
```json
{ "message": "Palets asignados al contenedor correctamente.", "data": { "id": 7, "containerNumber": "MSKU1234567", "sealNumber": "SL987654", "palletIds": [12, 13, 14], "...": "..." } }
```

### 4.3 Desasignación en bloque

```
DELETE /api/v2/orders/{order}/maritime-containers/{container}/pallets
{ "palletIds": [12, 13] }
```
Desasigna (pone `orderMaritimeContainerId = null`) los palets indicados, **solo si** actualmente pertenecen a ese contenedor (los que no, se ignoran silenciosamente — no da error).

Usa 4.2/4.3 para un flujo de selección múltiple ("selecciona palets → botón 'Asignar a contenedor X'" / "botón 'Quitar de este contenedor'").

### 4.4 Ver qué palets tiene un contenedor

```
GET /api/v2/orders/{order}/maritime-containers/{container}/pallets
```
```json
{ "data": [ { "id": 12, "orderMaritimeContainerId": 7, "boxes": [...], "netWeight": 120.5, "numberOfBoxes": 8, "...": "shape completo de Pallet" } ] }
```
Útil para mostrar, antes de generar el PDF, un resumen de qué hay cargado en cada contenedor (cajas, peso, productos).

---

## 5. Export Packing List (PDF)

Un documento por contenedor — si el pedido tiene 2 contenedores, se generan 2 PDFs distintos, cada uno con solo la mercancía (palets) asignada a ese contenedor.

```
GET /api/v2/orders/{order}/maritime-containers/{container}/pdf/export-packing-list
```
Descarga directa (`Content-Type: application/pdf`, `Content-Disposition: attachment`), igual que el resto de endpoints `pdf/*` del pedido. No disponible para rol `comercial` (403), igual que `packing-list`/`CMR`/`loading-note`.

Contenido del documento (fijo, no configurable desde frontend):
- Cabecera estándar (empresa, título, nº de pedido, fecha de carga, QR) — igual que el resto de PDFs.
- Bloque de 3 columnas: **Shipper/Exporter** (datos del tenant), **Intermediate Consignee** (`customsBroker` de los datos de envío, o aviso "sin agente asignado" si no hay), **Ultimate Consignee** (override o `customer` del pedido).
- Metadatos: nº de factura comercial, buque, nº de viaje, nº de contenedor, nº de precinto, SWB, país de origen (del tenant), país de destino (del cliente), incoterm.
- Tabla de mercancía agrupada por **especie** (nombre + nombre científico + código FAO) y desglosada por **producto/calibre** dentro de cada especie: cajas, peso neto y bruto en **kg y lb** (conversión automática, 1 kg = 2.20462 lb), y el código HTSUS del producto si está informado (`Product.hsCode`).
- Fila `TOTAL` con sumas de todo el contenedor.

Si un producto no tiene `hsCode` informado, simplemente no se muestra esa línea (no bloquea la generación del documento). Si no hay agente de aduanas asignado, la columna correspondiente muestra un aviso en vez de quedar vacía.

> Nota: a diferencia de otros documentos del pedido, este **no** está (todavía) integrado en el flujo de envío por email (`config/order_documents.php` / botón "enviar documentos") porque ese flujo asume un documento por pedido, no por contenedor. Por ahora es solo descarga directa. Si se necesita enviarlo por email, avisar para extender ese flujo.

---

## 6. Integración en el Detalle del Pedido

`GET /api/v2/orders/{id}` (detalle) — sin cambios de forma respecto al feature previo, pero con más datos dentro de las mismas claves:

```json
{
  "id": 42,
  "orderType": "maritime_export",
  "maritimeShippingDetail": {
    "id": 5, "orderId": 42,
    "vesselName": "Mando 631S", "voyageNumber": "V-2026-045",
    "customsBrokerId": 1,
    "customsBroker": { "id": 1, "name": "NDC Customs Brokers", "...": "..." },
    "ultimateConsigneeName": "Jose Santiago, Inc",
    "ultimateConsigneeAddress": "P.O. BOX 191795...",
    "...": "resto sin cambios"
  },
  "maritimeContainers": [
    { "id": 7, "orderId": 42, "containerNumber": "MSKU1234567", "sealNumber": "SL987654", "palletIds": [12, 13, 14] }
  ]
}
```
`maritimeShippingDetail` es `null` si el pedido no tiene datos de envío guardados todavía (comportamiento sin cambios). `maritimeContainers` sigue siendo siempre un array (`[]` si no hay ninguno).

---

## 7. Manejo de Errores (formato estándar del backend)

Igual que el resto de la API — usa siempre `userMessage` para mostrar al usuario:

| Código | Caso | Shape |
|--------|------|-------|
| 422 | Validación (Form Request) o palets que no pertenecen al pedido en asignación en bloque | `{ message, userMessage, errors? }` |
| 403 | Policy deniega la acción (rol `comercial` en catálogo o en PDF) | `{ message, userMessage, error }` |
| 400 | Regla de negocio (ej. borrar agente de aduanas en uso) | `{ message, userMessage }` |
| 404 | Contenedor/palet no existe o no pertenece al pedido de la URL | `{ message }` |

---

## 8. Checklist de Implementación Frontend

- [ ] Pantalla de mantenimiento de catálogo `CustomsBroker` (listado + CRUD), visible solo para roles ≠ `comercial`.
- [ ] Selector de agente de aduanas en el formulario de datos de envío marítimo, usando `/customs-brokers/options`.
- [ ] Campos `ultimateConsigneeName`/`ultimateConsigneeAddress` en ese mismo formulario, con placeholder tipo "dejar en blanco para usar los datos del cliente del pedido".
- [ ] Campo `hsCode` en el formulario de producto (catálogo de productos), opcional.
- [ ] En el listado de palets del pedido (para pedidos `maritime_export`): columna/selector de contenedor por palet (endpoint 4.1) + selección múltiple con acción "asignar a contenedor" (endpoint 4.2) y "quitar de este contenedor" (endpoint 4.3).
- [ ] Vista previa por contenedor antes de descargar el PDF (endpoint 4.4): nº de palets, cajas y peso total asignado.
- [ ] Botón "Descargar Export Packing List" por cada contenedor del pedido (no uno solo por pedido) — deshabilitarlo o avisar si el contenedor no tiene ningún palet asignado todavía.
- [ ] Manejo de errores: distinguir 422 (validación / palets de otro pedido) de 400 (agente de aduanas en uso) y 403 (rol sin permiso).
