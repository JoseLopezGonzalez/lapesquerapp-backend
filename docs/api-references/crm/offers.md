# Ofertas

## Endpoints

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

## Estados válidos

- `draft`
- `sent`
- `accepted`
- `rejected`
- `expired`

## Canales de envío

- `email`
- `pdf`
- `whatsapp_text`

## Crear oferta

Debe indicar exactamente uno entre `prospectId` y `customerId`.

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

## Reglas de edición

- Solo se puede editar o borrar una oferta en `draft`
- Al marcarla como enviada:
  - `status = sent`
  - `send_channel` se rellena
  - `sent_at` se rellena
  - si la oferta pertenece a un prospecto, ese prospecto pasa a `offer_sent` y se actualiza `last_offer_at`
- Una oferta aceptada no se puede expirar
- Una oferta aceptada no se puede rechazar

## Enviar oferta

`POST /api/v2/offers/{id}/send`

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

## Texto para WhatsApp

`GET /api/v2/offers/{id}/whatsapp-text`

Respuesta:

```json
{
  "data": {
    "text": "Oferta para Acme Seafood\n\n- Langostino 2kg | 10.000 kg x 12.5000 EUR\n\nValidez: 2026-03-31"
  }
}
```

## Crear pedido desde oferta

`POST /api/v2/offers/{id}/create-order`

Precondiciones:

- La oferta debe estar en `accepted`
- La oferta no puede tener ya `order_id`
- Las líneas de oferta deben tener `productId`, `taxId` y `boxes`

Payload:

```json
{
  "entryDate": "2026-03-17",
  "loadDate": "2026-03-18",
  "transport": 2,
  "buyerReference": "CRM-REF-1",
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

Comportamiento:

- Si la oferta está ligada a prospecto y aún no existe cliente, el backend convierte el prospecto antes de crear el pedido
- Las líneas de la oferta se convierten a `plannedProducts`
- `plannedProducts` del payload se añaden como líneas extra
- El pedido real se crea usando internamente el flujo actual de `POST /api/v2/orders`
- Se guarda `offers.order_id`

## Detección de pedido desde oferta

Hay dos maneras válidas:

- Leer `offerId` en `GET /api/v2/orders` y `GET /api/v2/orders/{id}`
- O consultar `GET /api/v2/offers?orderId={orderId}`

## Errores típicos

- `422` si la oferta no está en `accepted`
- `422` si falta `boxes` en una línea de oferta
- `422` si la oferta ya tiene pedido
- `403` si el comercial intenta operar sobre una oferta ajena
