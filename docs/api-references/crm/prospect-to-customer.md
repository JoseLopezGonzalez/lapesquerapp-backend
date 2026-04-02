# CRM — Conversión de Prospecto a Cliente

Última actualización: 2026-04-02. Refleja el estado real del código. Sin pendientes abiertos.

---

## Endpoint de conversión

| Campo | Valor |
|-------|-------|
| Método | `POST` |
| Ruta | `/api/v2/prospects/{id}/convert-to-customer` |
| Auth | `auth:sanctum` + `external.active` |
| Tenant | Header `X-Tenant` obligatorio |
| Autorización | `ProspectPolicy@update` (el comercial solo puede convertir sus prospectos) |

Referencias en código:
- Ruta: `routes/api.php` → grupo `v2`
- Controller: `app/Http/Controllers/v2/ProspectController.php` → `convertToCustomer()`
- Service: `app/Services/v2/ProspectService.php` → `convertToCustomer()`
- Request: `app/Http/Requests/v2/ConvertToCustomerRequest.php`

---

## Request

### Path param

| Param | Tipo | Descripción |
|-------|------|-------------|
| `id` | integer | ID del prospecto a convertir |

### Body (JSON, todos opcionales)

El frontend **muestra estos campos en la pantalla de conversión** antes de confirmar. El body puede ir vacío si el comercial no los rellena.

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `vatNumber` | string\|null | NIF/CIF del cliente |
| `billingAddress` | string\|null | Dirección de facturación. Si se omite, se usa la del prospecto |
| `shippingAddress` | string\|null | Dirección de envío. Si se omite, se usa la del prospecto |
| `transportId` | integer\|null | ID de transporte (`tenant.transports`) |
| `paymentTermId` | integer\|null | Condición de pago. Tiene **prioridad** sobre la oferta aceptada |
| `a3erpCode` | string\|null | Código en A3ERP |
| `facilcomCode` | string\|null | Código en Facilcom |
| `transportationNotes` | string\|null | Notas de transporte/logística |
| `productionNotes` | string\|null | Notas de producción |
| `accountingNotes` | string\|null | Notas contables |

> **Decisión UX (cerrada):** la pantalla de conversión muestra como mínimo `billingAddress` y `shippingAddress` para confirmar o ajustar. El resto de campos se rellenan opcionalmente en ese momento o después desde la ficha del cliente.

---

## Respuestas

### 200 OK — conversión exitosa

```json
{
  "message": "Prospecto convertido a cliente correctamente.",
  "data": {
    "id": 42,
    "name": "Empresa Ejemplo S.L.",
    "alias": "Cliente Nº 42",
    "vatNumber": "ES12345678A",
    "billingAddress": "Polígono Industrial Norte, nave 12",
    "shippingAddress": "Polígono Industrial Norte, nave 12",
    "emails": ["ana@empresa.com", "carlos@empresa.com"],
    "ccEmails": [],
    "contactInfo": "Ana García | Cargo: Gerente | Tel: 600000001 | Email: ana@empresa.com\nCarlos Ruiz | Cargo: Compras | Tel: 600000002 | Email: carlos@empresa.com",
    "paymentTerm": { "id": 5, "name": "30 días" },
    "salesperson": { "...": "..." },
    "country": { "...": "..." },
    "transport": null,
    "a3erpCode": null,
    "facilcomCode": null,
    "transportationNotes": null,
    "productionNotes": null,
    "accountingNotes": null,
    "operationalStatus": null,
    "createdAt": "2026-04-02T10:00:00.000000Z",
    "updatedAt": "2026-04-02T10:00:00.000000Z"
  }
}
```

`data` se serializa vía `Customer::toArrayAssoc()`. `emails` y `ccEmails` son arrays (el modelo los parsea desde el string interno separado por `;`).

### Errores esperables

| HTTP | Cuándo | Campo en `errors` |
|------|--------|-------------------|
| 404 | Prospecto no existe | — |
| 403 | Sin permiso sobre el prospecto | — |
| 422 | Ya convertido con cliente activo | `errors.status[0]` |
| 422 | Sin contacto primario | `errors.primaryContact[0]` |
| 422 | Contacto primario sin teléfono ni email | `errors.primaryContact[0]` |
| 422 | Campo del body inválido (p. ej. `transportId` inexistente) | campo correspondiente |

---

## Lógica de negocio (implementada)

### 1. Estados permitidos

La conversión acepta el prospecto en **cualquier estado** (`new`, `following`, `offer_sent`, `discarded`).

### 2. Idempotencia — ya convertido

| Situación | Comportamiento |
|-----------|----------------|
| `status = customer` + `customer_id` apunta a cliente existente | 422 — bloquear |
| `status = customer` + `customer_id` null o cliente eliminado | Permitir reconversión |
| Cualquier otro estado | Permitir conversión |

El check usa `lockForUpdate()` para prevenir condiciones de carrera.

### 3. Precondiciones (siempre validadas)

1. Debe existir contacto primario (`is_primary = true`).
2. El contacto primario debe tener al menos teléfono o email.

### 4. Mapeo Prospect → Customer

| Campo `Customer` | Origen |
|------------------|--------|
| `name` | `Prospect.company_name` |
| `alias` | Auto: `"Cliente Nº {id}"` |
| `country_id` | `Prospect.country_id` |
| `salesperson_id` | `Prospect.salesperson_id` |
| `billing_address` | `payload.billingAddress` ?? `Prospect.address` |
| `shipping_address` | `payload.shippingAddress` ?? `Prospect.address` |
| `emails` | Todos los emails de contactos consolidados (ver §5) |
| `contact_info` | Todos los contactos formateados (ver §6) |
| `payment_term_id` | `payload.paymentTermId` ?? oferta aceptada más reciente |
| `vat_number` | `payload.vatNumber` |
| `transport_id` | `payload.transportId` |
| `a3erp_code` | `payload.a3erpCode` |
| `facilcom_code` | `payload.facilcomCode` |
| `transportation_notes` | `payload.transportationNotes` |
| `production_notes` | `payload.productionNotes` |
| `accounting_notes` | `payload.accountingNotes` |

Campos no cubiertos en la conversión (`field_operator_id`, etc.) quedan `null` y se editan desde la ficha de cliente.

### 5. Consolidación de emails (`Customer.emails`)

- Se recogen todos los contactos del prospecto con email no vacío.
- Se deduplicar por email normalizado (lowercase).
- Formato interno en BD: `"email1@x.com;email2@x.com;"`.
- Sin prefijo `CC:`.
- La API serializa esto como array en `data.emails`.

### 6. Consolidación de contactos (`Customer.contact_info`)

Texto multilinea, una línea por contacto.

**Formato por línea** (solo se incluyen partes con valor):
```
{Nombre} | Cargo: {Rol} | Tel: {Telefono} | Email: {Email}
```

**Orden:** contacto primario primero; resto alfabético por nombre.

**Deduplicación:** por combinación `(name, email, phone)` normalizada.

### 7. Transferencia de entidades CRM

| Entidad | Comportamiento |
|---------|----------------|
| **Ofertas** (`Offer`) | Todas se reasignan al cliente: `prospect_id → null`, `customer_id → {nuevo}` |
| **Agenda** (`AgendaAction`) | Solo la acción `pending` activa se transfiere al cliente. Si el cliente ya tenía una pending, se cancela. El histórico de agenda queda en el prospecto. |
| **Interacciones** (`CommercialInteraction`) | No se migran. Históricas permanecen en el prospecto; nuevas se crean sobre el cliente. Vista unificada disponible vía endpoint dedicado (ver §8). |

### 8. Estado final del prospecto

No se elimina. Se conserva como histórico:
- `status` ← `customer`
- `customer_id` ← ID del cliente creado

Debe quedar excluido de listados operativos por defecto (filtrar `status != customer`).

---

## Endpoint de interacciones unificado

Una vez convertido el prospecto, la ficha de cliente expone un endpoint que agrega las interacciones propias del cliente y las históricas del prospecto convertido.

| Campo | Valor |
|-------|-------|
| Método | `GET` |
| Ruta | `/api/v2/customers/{id}/interactions` |
| Auth | `auth:sanctum` + `external.active` |
| Autorización | `CustomerPolicy@view` |

Referencias en código:
- `app/Http/Controllers/v2/CustomerController.php` → `interactions()`

### Respuesta 200

```json
{
  "data": [
    {
      "id": 10,
      "customerId": 42,
      "prospectId": null,
      "salesperson": { "...": "..." },
      "type": "call",
      "occurredAt": "2026-04-01T10:00:00.000000Z",
      "summary": "Llamada de seguimiento post-venta",
      "result": "interested",
      "nextActionNote": null,
      "nextActionAt": null,
      "isFromProspect": false
    },
    {
      "id": 3,
      "customerId": null,
      "prospectId": 17,
      "salesperson": { "...": "..." },
      "type": "email",
      "occurredAt": "2025-12-10T09:30:00.000000Z",
      "summary": "Primer contacto vía email",
      "result": "interested",
      "nextActionNote": null,
      "nextActionAt": null,
      "isFromProspect": true
    }
  ]
}
```

- Ordenadas por `occurredAt` DESC.
- `isFromProspect: true` identifica las interacciones históricas del prospecto.
- Si el cliente no proviene de un prospecto convertido, solo devuelve sus propias interacciones.

---

## Flujo `POST /offers/{id}/create-order` (impacto)

Antes de esta revisión, el endpoint creaba automáticamente el cliente si la oferta seguía vinculada a un prospecto. **Este comportamiento fue eliminado.**

El flujo ahora es:

| Situación de la oferta | Comportamiento |
|------------------------|----------------|
| `customer_id` informado | Crear pedido normalmente |
| `prospect_id` informado, prospecto ya convertido con cliente activo | 422 — "El prospecto ya fue convertido. Usa el cliente asociado." |
| `prospect_id` informado, prospecto no convertido | 422 — "Convierte el prospecto antes de crear el pedido." |
| Sin `customer_id` ni `prospect_id` | 422 — "La oferta no tiene cliente asociado." |

> Nota: tras la conversión, todas las ofertas del prospecto quedan con `customer_id` asignado y `prospect_id = null`. El caso de "prospecto no convertido" solo puede darse en ofertas creadas directamente sobre el prospecto sin haberlo convertido todavía.

---

## Guía de uso para el frontend

### Flujo de conversión (pantalla de conversión)

1. El frontend carga los datos del prospecto y pre-rellena el formulario con:
   - `billingAddress` ← `prospect.address`
   - `shippingAddress` ← `prospect.address`
2. El comercial ajusta las direcciones si aplica y añade datos opcionales (VAT, transport, ERP codes).
3. El frontend envía `POST /api/v2/prospects/{id}/convert-to-customer` con el payload.
4. Con la respuesta `200`, redirigir a `/clientes/{data.id}`.

### Detección de "ya convertido" (422)

```json
{
  "errors": {
    "status": ["Este prospecto ya ha sido convertido a cliente."]
  }
}
```

El frontend puede capturar este error y redirigir a la ficha del cliente (`prospect.customerId`).

### Carga de interacciones en ficha cliente

```http
GET /api/v2/customers/{id}/interactions
```

Usar `isFromProspect` para renderizar visualmente el origen de cada interacción (p. ej. badge "Histórico de prospecto").

### Creación de pedido desde oferta

Con el prospecto convertido, las ofertas ya tienen `customer_id`. El flujo `POST /offers/{id}/create-order` funciona sin cambios desde el frontend. Si por cualquier motivo la oferta aún apunta a un prospecto no convertido, el backend devuelve un 422 claro con instrucciones.
