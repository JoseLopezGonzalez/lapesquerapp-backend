# Interacciones Comerciales

## Endpoints

- `GET /api/v2/commercial-interactions`
- `POST /api/v2/commercial-interactions`
- `GET /api/v2/commercial-interactions/{id}`

## Reglas

- Una interacción pertenece a un prospecto o a un cliente, nunca a ambos.
- En V1 las interacciones no se editan.
- Flujo vigente: **2 pasos**.
  - Paso 1 (`POST /commercial-interactions`): registrar interacción.
  - Paso 2 (`POST /crm/agenda/resolve-next-action`): gestionar próxima acción.
- En Paso 1, si NO llega `agendaActionId`:
  - la interacción se guarda (agenda no se toca),
  - y si llegan campos de próxima acción (`nextActionAt`/`nextActionNote`) devuelve `422` (fail-fast).
- Compat legacy mantenida:
  - `agendaActionId` sin `nextActionAt` => `completed` (marca `done`).
  - `agendaActionId + nextActionAt` => `completed_and_created` (marca `done` y crea nueva `pending`).

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
  "result": "pending"
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

### Cierre + nueva próxima acción

Para marcar la acción actual como `done` y crear la siguiente en la misma interacción:

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

### Interacción libre + gestión de próxima acción (flujo recomendado)

1) Guardar interacción:

```json
{
  "prospectId": 10,
  "type": "call",
  "occurredAt": "2026-03-17T12:10:00Z",
  "summary": "Llamada entrante imprevista",
  "result": "interested"
}
```

2) Resolver próxima acción:

```json
{
  "targetType": "prospect",
  "targetId": 10,
  "strategy": "override",
  "nextActionAt": "2026-03-20",
  "description": "Enviar propuesta final",
  "reason": "Cambio de contexto"
}
```

Respuesta `201`:

```json
{
  "message": "Interacción registrada correctamente.",
  "data": {
    "id": 501,
    "prospectId": 10,
    "customerId": null,
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
- `422` si `agendaActionId` no existe, no está `pending` o no corresponde al target de la interacción
- `422` si envías campos de próxima acción en Paso 1 sin `agendaActionId` (fail-fast)
- `403` si intenta leer una interacción de otro comercial
