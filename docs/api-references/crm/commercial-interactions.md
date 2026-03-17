# Interacciones Comerciales

## Endpoints

- `GET /api/v2/commercial-interactions`
- `POST /api/v2/commercial-interactions`
- `GET /api/v2/commercial-interactions/{id}`

## Reglas

- Una interacción pertenece a un prospecto o a un cliente, nunca a ambos.
- En V1 las interacciones no se editan.
- Al crear una interacción sobre un prospecto, el backend actualiza:
  - `prospects.last_contact_at`
  - `prospects.next_action_at` si llega `nextActionAt`
- Si llega `nextActionAt = null`, el backend limpia la próxima acción del prospecto.

## Tipos válidos

- `call`
- `email`
- `whatsapp`
- `visit`
- `other`

## Resultados válidos

- `interested`
- `no_response`
- `not_interested`
- `pending`

## Crear interacción

Payload sobre prospecto:

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

Payload sobre cliente:

```json
{
  "customerId": 44,
  "type": "email",
  "occurredAt": "2026-03-17T11:00:00Z",
  "summary": "Se envían condiciones actualizadas",
  "result": "interested"
}
```

## Listado

Filtros soportados:

- `prospectId`
- `customerId`
- `result[]`
- `type[]`
- `dateFrom`
- `dateTo`
- `perPage`

Orden por defecto:

- `occurred_at desc`

## Errores típicos

- `422` si faltan ambos targets o llegan ambos a la vez
- `422` si un comercial intenta registrar una interacción sobre un prospecto/cliente ajeno
- `403` si intenta leer una interacción de otro comercial
