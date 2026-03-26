# CRM Comercial — Guía Única de Integración Frontend

## Objetivo

Este documento reúne en un único lugar toda la información que necesita el frontend para integrarse con el backend del CRM comercial implementado en este repo.

Es el documento canónico para frontend dentro de `docs/api-references/crm/`.

Incluye:

- endpoints
- payloads
- respuestas
- estados y transiciones
- permisos por rol
- flujos de uso
- relación con endpoints existentes de clientes y pedidos

No define UI ni componentes. Solo contratos y comportamiento backend.

---

## Base de integración

### Base path

- `/api/v2`

### Requisitos comunes

- Header `X-Tenant`
- Autenticación Sanctum
- Respuestas JSON salvo descargas PDF

### Convención de respuestas

En escrituras, el backend usa normalmente:

```json
{
  "message": "Texto descriptivo",
  "data": {},
  "warnings": []
}
```

Notas:

- `warnings` solo aparece cuando hay avisos no bloqueantes, como duplicados.
- En listados paginados se usa `Resource::collection(...)`, así que la respuesta sigue el patrón Laravel con `data`, `links` y `meta`.

---

## Roles y permisos

### Rol `comercial`

- Solo ve sus propios prospectos
- Solo ve sus propias interacciones
- Solo ve sus propias ofertas
- El dashboard CRM devuelve solo sus datos
- Puede convertir sus prospectos a cliente
- Puede crear pedido desde sus ofertas aceptadas

### Roles `administrador`, `tecnico`, `direccion`

- Acceso global a los registros CRM

### Consideración importante

El backend no solo protege por policy de lectura/escritura del recurso.  
También valida ownership en servicios cuando el target viene en payload, para evitar operar sobre prospectos o clientes ajenos.

---

## Recursos principales

### 1. Prospectos

#### Endpoints

- `GET /api/v2/prospects`
- `POST /api/v2/prospects`
- `GET /api/v2/prospects/{id}`
- `PUT /api/v2/prospects/{id}`
- `DELETE /api/v2/prospects/{id}`
- `GET /api/v2/prospects/{id}/contacts`
- `POST /api/v2/prospects/{id}/contacts`
- `PUT /api/v2/prospects/{id}/contacts/{contactId}`
- `DELETE /api/v2/prospects/{id}/contacts/{contactId}`
- `POST /api/v2/prospects/{id}/convert-to-customer`
- `POST /api/v2/prospects/{id}/schedule-action`
- `DELETE /api/v2/prospects/{id}/next-action`

#### Estados válidos

- `new`
- `following`
- `offer_sent`
- `customer`
- `discarded`

#### Orígenes válidos

- `conxemar`
- `direct`
- `referral`
- `web`
- `other`

#### Filtros de listado

- `search`
- `status[]`
- `origin[]`
- `countries[]`
- `salespeople[]`
- `perPage`

#### Orden real del listado

1. prospectos con `next_action_at`
2. `next_action_at` ascendente
3. `company_name`

#### Payload de create/update

```json
{
  "companyName": "Acme Seafood",
  "countryId": 1,
  "speciesInterest": ["langostino", "pulpo"],
  "origin": "direct",
  "status": "new",
  "notes": "Interés inicial",
  "commercialInterestNotes": "Formato 2kg, mercado horeca",
  "nextActionAt": "2026-03-20",
  "nextActionNote": "Llamar para enviar oferta",
  "lostReason": null,
  "salespersonId": 3,
  "primaryContact": {
    "name": "Ana Compras",
    "role": "Compras",
    "phone": "600111222",
    "email": "ana@acme.test"
  }
}
```

#### Respuesta típica

```json
{
  "message": "Prospecto creado correctamente.",
  "data": {
    "id": 10,
    "companyName": "Acme Seafood",
    "country": {
      "id": 1,
      "name": "Espana"
    },
    "speciesInterest": ["langostino", "pulpo"],
    "origin": "direct",
    "status": "new",
    "salesperson": {
      "id": 3,
      "name": "Comercial Uno"
    },
    "customer": null,
    "nextActionAt": "2026-03-20",
    "nextActionNote": "Llamar para enviar oferta",
    "notes": "Interés inicial",
    "commercialInterestNotes": "Formato 2kg, mercado horeca",
    "lastContactAt": null,
    "lastOfferAt": null,
    "lostReason": null,
    "primaryContact": {
      "id": 14,
      "prospectId": 10,
      "name": "Ana Compras",
      "role": "Compras",
      "phone": "600111222",
      "email": "ana@acme.test",
      "isPrimary": true
    },
    "latestInteraction": null,
    "contacts": [],
    "interactions": [],
    "offers": [],
    "offersSummary": {
      "count": 0,
      "latestStatus": null
    }
  },
  "warnings": []
}
```

#### Duplicados

El backend no bloquea por duplicados. Devuelve `warnings` si detecta:

- mismo `companyName` en prospectos o clientes
- mismo email en contactos de prospectos o en `customers.emails`
- mismo teléfono en contactos de prospectos o en `customers.contact_info`

Ejemplo de warning:

```json
{
  "type": "company_name",
  "message": "Ya existe una empresa con el mismo nombre.",
  "matches": {
    "prospectIds": [4],
    "customerIds": [8]
  }
}
```

#### Contactos de prospecto

Payload:

```json
{
  "name": "Ana Compras",
  "role": "Compras",
  "phone": "600111222",
  "email": "ana@acme.test",
  "isPrimary": true
}
```

Regla importante:

- si se marca un contacto como primario, el backend desmarca el anterior

#### Agenda del prospecto

Reprogramar:

```json
POST /api/v2/prospects/{id}/schedule-action
{
  "nextActionAt": "2026-03-25",
  "nextActionNote": "Enviar condiciones actualizadas"
}
```

`nextActionNote` es opcional. Limpiar:

- `DELETE /api/v2/prospects/{id}/next-action`

#### Convertir prospecto a cliente

Endpoint:

- `POST /api/v2/prospects/{id}/convert-to-customer`

Precondiciones:

- `status` debe ser `offer_sent`
- debe existir contacto primario
- el contacto primario debe tener teléfono o email

Efectos:

- crea `Customer`
- actualiza `prospect.status = customer`
- rellena `prospect.customer_id`
- actualiza ofertas del prospecto sin `customer_id`

Errores típicos:

- `403` si no puede operar ese prospecto
- `422` si el estado no es `offer_sent`
- `422` si falta contacto primario útil

---

### 2. Interacciones comerciales

#### Endpoints

- `GET /api/v2/commercial-interactions`
- `POST /api/v2/commercial-interactions`
- `GET /api/v2/commercial-interactions/{id}`

#### Tipos válidos

- `call`
- `email`
- `whatsapp`
- `visit`
- `other`

#### Resultados válidos

- `interested`
- `no_response`
- `not_interested`
- `pending`

#### Filtros

- `prospectId`
- `customerId`
- `result[]`
- `type[]`
- `dateFrom`
- `dateTo`
- `perPage`

#### Orden del listado

- `occurred_at desc`

#### Payload de alta sobre prospecto

```json
{
  "prospectId": 10,
  "type": "call",
  "occurredAt": "2026-03-17T10:30:00Z",
  "summary": "Llamada de seguimiento",
  "result": "pending",
  "nextActionNote": "Enviar oferta",
  "nextActionAt": "2026-03-20"
}
```

#### Payload de alta sobre cliente

```json
{
  "customerId": 44,
  "type": "email",
  "occurredAt": "2026-03-17T11:00:00Z",
  "summary": "Se envían condiciones actualizadas",
  "result": "interested"
}
```

#### Payload para cerrar y programar la siguiente en una sola interacción

```json
{
  "prospectId": 10,
  "type": "visit",
  "occurredAt": "2026-03-17T12:10:00Z",
  "summary": "Visita realizada y siguiente paso",
  "result": "interested",
  "agendaActionId": 123,
  "nextActionAt": "2026-03-20",
  "nextActionNote": "Enviar propuesta final"
}
```

#### Reglas backend

- Debe venir exactamente uno entre `prospectId` y `customerId`
- En V1 las interacciones no se editan
- Si la interacción es sobre prospecto:
  - se actualiza `last_contact_at`
  - en interacción libre (sin agendaActionId) no toca `next_action_*`
  - en flujo legacy con `agendaActionId + nextActionAt`, replica a `prospects.next_action_*`
- Semántica de agenda actual en `POST /api/v2/commercial-interactions`:
  - sin `agendaActionId` y sin campos de próxima acción: guarda interacción, `agenda.mode = null`
  - sin `agendaActionId` y con `nextActionAt/nextActionNote`: `422` fail-fast
  - `agendaActionId` sin `nextActionAt`: `completed`
  - `agendaActionId + nextActionAt`: `completed_and_created` (compat)

#### Endpoint nuevo de resolución de próxima acción (Paso 2)

- `POST /api/v2/crm/agenda/resolve-next-action`
- `GET /api/v2/crm/agenda/pending` (preflight)

Regla de flujo recomendada:

1. Guardar interacción (`POST /commercial-interactions`)
2. Resolver próxima acción (`POST /crm/agenda/resolve-next-action`)

#### Respuesta de escritura relevante

Además de `data` con la interacción, el backend devuelve un bloque raíz `agenda`:

```json
{
  "message": "Interacción registrada correctamente.",
  "data": {
    "id": 501,
    "prospectId": 10,
    "type": "visit",
    "occurredAt": "2026-03-17T12:10:00Z",
    "summary": "Visita realizada y siguiente paso",
    "result": "interested",
    "nextActionNote": "Enviar propuesta final",
    "nextActionAt": "2026-03-20"
  },
  "agenda": {
    "mode": "completed_and_created",
    "completedAction": {
      "agendaActionId": 123,
      "targetType": "prospect",
      "targetId": 10,
      "scheduledAt": "2026-03-18",
      "description": "Llamada previa",
      "status": "done",
      "previousActionId": null
    },
    "createdAction": {
      "agendaActionId": 124,
      "targetType": "prospect",
      "targetId": 10,
      "scheduledAt": "2026-03-20",
      "description": "Enviar propuesta final",
      "status": "pending",
      "previousActionId": 123
    }
  }
}
```

#### Implicación importante para frontend

- Para interacción libre (nueva UX 2 pasos): no enviar `nextActionAt/nextActionNote` en Paso 1.
- Para “Marcar hecha” sin siguiente acción: enviar `agendaActionId` y no enviar `nextActionAt`.
- Para “Marcar hecha y programar siguiente” (compat): enviar `agendaActionId + nextActionAt + nextActionNote?`.
- Usar `POST /api/v2/crm/agenda/{id}/reschedule` solo cuando la intención sea reprogramar una pendiente existente sin marcarla `done`

Errores típicos:

- `422` si faltan ambos targets o vienen ambos
- `422` si el comercial apunta a un target ajeno
- `422` si `agendaActionId` no existe, no está `pending` o pertenece a otro target
- `422` fail-fast si se intenta gestionar próxima acción en Paso 1 sin `agendaActionId`
- `422` con `code` de dominio en Paso 2 (`PENDING_EXISTS`, `NO_PENDING_TO_UPDATE`, `STALE_PENDING`)
- `403` si intenta leer una interacción fuera de su scope

---

### 3. Ofertas

#### Endpoints

- `GET /api/v2/offers`
- `POST /api/v2/offers`
- `GET /api/v2/offers/{id}`
- `PUT /api/v2/offers/{id}`
- `DELETE /api/v2/offers/{id}`
- `POST /api/v2/offers/{id}/send`
- `POST /api/v2/offers/{id}/accept`
- `POST /api/v2/offers/{id}/reject`
- `POST /api/v2/offers/{id}/expire`
- `GET /api/v2/offers/{id}/pdf`
- `GET /api/v2/offers/{id}/whatsapp-text`
- `POST /api/v2/offers/{id}/email`
- `POST /api/v2/offers/{id}/create-order`

#### Estados válidos

- `draft`
- `sent`
- `accepted`
- `rejected`
- `expired`

#### Canales de envío

- `email`
- `pdf`
- `whatsapp_text`

#### Filtros

- `search`
- `status[]`
- `prospectId`
- `customerId`
- `orderId`
- `salespeople[]`
- `perPage`

#### Crear oferta

Debe indicarse exactamente uno entre `prospectId` y `customerId`.

```json
{
  "prospectId": 10,
  "validUntil": "2026-03-31",
  "incotermId": 2,
  "paymentTermId": 3,
  "currency": "EUR",
  "notes": "Oferta para campaña primavera",
  "lines": [
    {
      "productId": 12,
      "description": "Langostino 2kg",
      "quantity": 10,
      "unit": "kg",
      "unitPrice": 12.5,
      "taxId": 1,
      "boxes": 4,
      "currency": "EUR"
    }
  ]
}
```

#### Estructura de línea

- `productId` puede ser nullable
- `description` es obligatorio
- `quantity` obligatorio
- `unit` obligatorio
- `unitPrice` obligatorio
- `taxId` nullable
- `boxes` nullable
- `currency` optional

#### Reglas de edición

- solo se puede editar o borrar en `draft`
- el bloqueo fuerte está en servicio, no en policy

#### Transiciones reales implementadas

- `draft -> sent`
- `sent -> accepted`
- `sent -> rejected`
- `draft|sent|rejected -> expired`
- `accepted` no puede expirar

Decisión cerrada:

- una oferta aceptada no puede rechazarse

#### Enviar oferta

Endpoint:

- `POST /api/v2/offers/{id}/send`

Payload mínimo:

```json
{
  "channel": "pdf"
}
```

Para email:

```json
{
  "channel": "email",
  "email": "compras@cliente.test",
  "subject": "Oferta marzo 2026"
}
```

Efectos al enviar:

- `status = sent`
- `send_channel` se rellena
- `sent_at` se rellena
- si la oferta pertenece a un prospecto:
  - el prospecto pasa a `offer_sent`
  - se actualiza `last_offer_at`

#### Aceptar oferta

- `POST /api/v2/offers/{id}/accept`

Requiere:

- `status = sent`

#### Rechazar oferta

- `POST /api/v2/offers/{id}/reject`

Payload:

```json
{
  "reason": "Cliente pospone compra"
}
```

#### Expirar oferta

- `POST /api/v2/offers/{id}/expire`

#### PDF de oferta

- `GET /api/v2/offers/{id}/pdf`

Respuesta:

- descarga PDF

#### Email de oferta

- `POST /api/v2/offers/{id}/email`

Payload:

```json
{
  "channel": "email",
  "email": "compras@cliente.test",
  "subject": "Oferta marzo 2026"
}
```

Notas:

- adjunta el PDF generado
- si el frontend quiere usar el mail directo en lugar de `send`, este endpoint lo cubre

#### Texto para WhatsApp

- `GET /api/v2/offers/{id}/whatsapp-text`

Respuesta:

```json
{
  "data": {
    "text": "Oferta para Acme Seafood\n\n- Langostino 2kg | 10.000 kg x 12.5000 EUR\n\nValidez: 2026-03-31"
  }
}
```

#### Crear pedido desde oferta aceptada

- `POST /api/v2/offers/{id}/create-order`

Precondiciones:

- la oferta debe estar en `accepted`
- la oferta no puede tener ya `order_id`
- las líneas de la oferta deben tener `productId`, `taxId` y `boxes`

Payload:

```json
{
  "entryDate": "2026-03-17",
  "loadDate": "2026-03-18",
  "transport": 2,
  "buyerReference": "CRM-REF-1",
  "billingAddress": "Dir facturación",
  "shippingAddress": "Dir envío",
  "transportationNotes": "Cargar por la tarde",
  "productionNotes": null,
  "accountingNotes": null,
  "emails": ["compras@cliente.test"],
  "ccEmails": [],
  "plannedProducts": [
    {
      "product": 99,
      "quantity": 2,
      "boxes": 1,
      "unitPrice": 8.5,
      "tax": 1
    }
  ]
}
```

Comportamiento real:

- si la oferta está ligada a prospecto sin cliente:
  - el backend convierte el prospecto a cliente antes de crear el pedido
- las líneas de oferta se convierten a `plannedProducts`
- `plannedProducts` del payload se añaden como extras
- se usa internamente `OrderStoreService::store()`
- se guarda `offers.order_id`

Errores típicos:

- `422` si la oferta no está aceptada
- `422` si ya tiene pedido
- `422` si falta `boxes`, `productId` o `taxId` en líneas base

---

### 4. Dashboard CRM

#### Endpoint

- `GET /api/v2/crm/dashboard`

#### Respuesta

```json
{
  "data": {
    "reminders_today": [
      {
        "type": "prospect",
        "id": 10,
        "label": "Acme Seafood",
        "nextActionAt": "2026-03-17",
        "nextActionNote": "Enviar oferta",
        "daysOverdue": 0,
        "prospectId": 10,
        "customerId": null
      }
    ],
    "overdue_actions": [],
    "inactive_customers": [],
    "prospects_without_activity": [],
    "counters": {
      "remindersToday": 1,
      "overdueActions": 0,
      "inactiveCustomers": 0,
      "prospectsWithoutActivity": 0
    }
  }
}
```

#### Lógica real

`reminders_today`:

- prospectos con `next_action_at = hoy`
- interacciones con `next_action_at = hoy`

`overdue_actions`:

- prospectos con `next_action_at < hoy`
- interacciones con `next_action_at < hoy`

`inactive_customers`:

- clientes sin pedido o con último pedido de hace más de 30 días

`prospects_without_activity`:

- prospectos sin `last_contact_at`
- o con `last_contact_at < hoy - 7 días`

#### Importante para frontend

La agenda diaria ya mezcla prospectos e interacciones programadas para hoy.

---

## Relación con endpoints existentes del ERP

### Clientes

Se siguen usando:

- `GET /api/v2/customers`
- `GET /api/v2/customers/{id}`
- `GET /api/v2/customers/{id}/order-history`
- `GET /api/v2/customers/options`

Uso esperado desde CRM:

- detalle simplificado de cliente
- historial de pedidos
- opciones para selects

### Pedidos

Se siguen usando:

- `GET /api/v2/orders`
- `GET /api/v2/orders/{id}`
- `POST /api/v2/orders`

Integraciones nuevas:

- `Order` ahora expone `offerId`
- el frontend puede detectar si un pedido viene de oferta

Importante:

- este backend deja listo el dato para pedidos del comercial
- pero el frontend seguirá necesitando un consumo dedicado para `/comercial/pedidos`
- no debe asumirse que `useOrders.js` existente ya resuelve ese caso por sí solo
- lo correcto es usar un hook específico para pedidos del comercial que consuma `GET /api/v2/orders` con el scope de sesión del usuario

### Detección de pedido creado desde oferta

Opciones válidas:

- leer `offerId` en `GET /api/v2/orders` o `GET /api/v2/orders/{id}`
- consultar `GET /api/v2/offers?orderId={orderId}`

### Catálogos y options útiles

- `/countries/options`
- `/products/options`
- `/taxes/options`
- `/incoterms/options`
- `/payment-terms/options`
- `/salespeople/options`

---

## Shapes relevantes para frontend

### Prospect resource

Campos relevantes:

- `id`
- `companyName`
- `country`
- `speciesInterest`
- `origin`
- `status`
- `salesperson`
- `customer`
- `nextActionAt`
- `nextActionNote`
- `notes`
- `commercialInterestNotes`
- `lastContactAt`
- `lastOfferAt`
- `lostReason`
- `primaryContact`
- `latestInteraction`
- `contacts`
- `interactions`
- `offers`
- `offersSummary`

### CommercialInteraction resource

Campos relevantes:

- `id`
- `prospectId`
- `customerId`
- `salesperson`
- `type`
- `occurredAt`
- `summary`
- `result`
- `nextActionNote`
- `nextActionAt`
- `createdAt`

### Offer resource

Campos relevantes:

- `id`
- `prospectId`
- `customerId`
- `prospect`
- `customer`
- `salesperson`
- `status`
- `sendChannel`
- `sentAt`
- `validUntil`
- `incoterm`
- `paymentTerm`
- `currency`
- `notes`
- `acceptedAt`
- `rejectedAt`
- `rejectionReason`
- `orderId`
- `lines`
- `createdAt`
- `updatedAt`

### Order resource modificado

Campos añadidos:

- `offerId`

Esto aplica en:

- listados de pedidos
- detalle de pedido

---

## Flujos frontend soportados

### Flujo 1. Crear prospecto

1. `POST /api/v2/prospects`
2. leer `warnings`
3. si hace falta más de un contacto, usar CRUD de contactos

### Flujo 2. Registrar seguimiento

1. `POST /api/v2/commercial-interactions`
2. si es sobre prospecto:
   - se actualiza `last_contact_at`
3. si corresponde agenda:
   - ejecutar `POST /api/v2/crm/agenda/resolve-next-action`

### Flujo 3. Resolver una acción pendiente

Opción A (recomendada): interacción + resolución en 2 pasos

- Paso 1: `POST /api/v2/commercial-interactions`
- Paso 2: `POST /api/v2/crm/agenda/resolve-next-action`

Opción B (compat): cerrar y crear siguiente en una sola interacción

- `POST /api/v2/commercial-interactions` con `agendaActionId + nextActionAt`

Opción C: operaciones legacy de agenda

- `POST /api/v2/crm/agenda/{id}/reschedule`
- `POST /api/v2/crm/agenda/{id}/cancel`

### Flujo 4. Enviar oferta desde prospecto

1. crear oferta en `draft`
2. enviarla con `/offers/{id}/send`
3. el prospecto pasa a `offer_sent`

### Flujo 5. Aceptar oferta y crear pedido

1. `POST /api/v2/offers/{id}/accept`
2. `POST /api/v2/offers/{id}/create-order`
3. leer `orderId` en la oferta o `offerId` en el pedido

### Flujo 6. Mostrar pedido “desde oferta”

Usar:

- `offerId` dentro del resource de pedido

---

## Compatibilidad con el modelo legacy

### Customer

La conversión desde prospecto no crea tabla de contactos de cliente.  
Se usa el modelo legacy actual:

- `name`
- `country_id`
- `salesperson_id`
- `emails` separado por `;`
- `contact_info` como texto libre

### Pedido

La creación desde oferta no inventa un segundo flujo de pedidos.  
Reutiliza el flujo actual de `orders`.

### Multi-tenant

Las tablas CRM nuevas:

- no llevan `tenant_id`
- viven en migraciones `companies`
- se apoyan en `UsesTenantConnection`

---

## Limitaciones actuales que frontend debe conocer

### 1. Interacciones no editables

No existe update/delete de interacciones en V1.

### 2. Agenda diaria combinada

`reminders_today` ya incluye interacciones de hoy.

### 3. Contacto primario único garantizado por servicio

No hay constraint SQL dedicado para garantizar un único primario por prospecto.

### 4. PDF/email de oferta funcional pero simple

Existe backend para ello, pero las plantillas son mínimas.

### 5. Rechazo de oferta aceptada

Hoy el backend no lo permite. `accepted` queda como estado terminal frente a rechazo.

---

## Qué debería revisar el frontend al integrar

1. Si necesita agenda diaria combinada de prospectos e interacciones.
2. Si necesita más datos embebidos en ciertos resources o con este shape ya basta.
3. Si quiere usar `send` genérico o separar UI entre `send`, `email`, `pdf`, `whatsapp-text`.
4. Si el flujo de creación de pedido desde oferta necesita forzar captura de campos extra antes de llamar al endpoint.
5. Si el tratamiento de warnings de duplicados debe ser modal, banner o confirmación explícita.
