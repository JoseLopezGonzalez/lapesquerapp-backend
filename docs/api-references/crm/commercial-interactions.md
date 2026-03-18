# Interacciones Comerciales

## Endpoints

- `GET /api/v2/commercial-interactions`
- `POST /api/v2/commercial-interactions`
- `GET /api/v2/commercial-interactions/{id}`

## Reglas

- Una interacción pertenece a un prospecto o a un cliente, nunca a ambos.
- En V1 las interacciones no se editan.
- Si llega `nextActionAt`:
  - el backend crea una `agenda_actions.pending` para el target (prospecto/cliente) ligada a esa interacción
  - si el target es prospecto, también actualiza `prospects.last_contact_at` y `prospects.next_action_at/next_action_note` (legacy)
- Si NO llega `nextActionAt` (o llega `null`):
  - el backend exige `agendaActionId`
  - el backend marca esa `agenda_actions` como `status=done` y guarda el vínculo con la interacción que cierra
  - si el target es prospecto, limpia `prospects.next_action_at/next_action_note` (legacy)
- Regla V1: si ya existe una `agenda_actions` `pending` para el mismo target, crear otra pendiente con `nextActionAt` devuelve `422`.

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

### Cierre (done) sin próxima acción

Para marcar una acción como `done` (V1), la interacción debe venir **sin** `nextActionAt` y debe incluir `agendaActionId`.

```json
{
  "prospectId": 10,
  "type": "call",
  "occurredAt": "2026-03-17T12:10:00Z",
  "summary": "Cierre de la tarea",
  "result": "pending",
  "agendaActionId": 123
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
