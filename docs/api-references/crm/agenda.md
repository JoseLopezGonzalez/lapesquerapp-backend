# Agenda Actions CRM

## Endpoints

- `GET /api/v2/crm/agenda?startDate=YYYY-MM-DD&endDate=YYYY-MM-DD&targetType=prospect|customer&status[]=pending|reprogrammed|done|cancelled`
- `GET /api/v2/crm/agenda/summary?limitNext=10`
- `GET /api/v2/crm/agenda/pending?targetType=prospect|customer&targetId={id}`
- `POST /api/v2/crm/agenda`
- `POST /api/v2/crm/agenda/resolve-next-action`
- `POST /api/v2/crm/agenda/{id}/reschedule`
- `POST /api/v2/crm/agenda/{id}/cancel`

## GET /crm/agenda (calendario)

Respuesta:

```json
{
  "data": {
    "events": [
      {
        "agendaActionId": 123,
        "scheduledAt": "2026-03-20",
        "description": "Enviar oferta",
        "status": "pending",
        "reason": null,
        "target": { "type": "prospect", "id": 10 },
        "label": "Acme Seafood"
      }
    ]
  }
}
```

## GET /crm/agenda/summary (compacto)

Respuesta:

```json
{
  "data": {
    "overdue": [],
    "today": [],
    "next": []
  }
}
```

Los items de `overdue/today/next` usan el mismo shape que `events`.

## POST /crm/agenda (crear pending)

Payload:

```json
{
  "targetType": "prospect",
  "targetId": 10,
  "nextActionAt": "2026-03-25",
  "nextActionNote": "Enviar condiciones",
  "sourceInteractionId": null,
  "previousActionId": null
}
```

## POST /crm/agenda/{id}/reschedule

Payload:

```json
{
  "nextActionAt": "2026-03-27",
  "sourceInteractionId": null
}
```

## POST /crm/agenda/{id}/cancel

Payload:

```json
{
  "reason": "Cliente pospone compra"
}
```

## GET /crm/agenda/pending (preflight)

Respuesta sin pending:

```json
{
  "data": null
}
```

Respuesta con pending:

```json
{
  "data": {
    "agendaActionId": 124,
    "targetType": "prospect",
    "targetId": 10,
    "scheduledAt": "2026-03-20",
    "description": "Enviar propuesta",
    "status": "pending",
    "reason": null,
    "previousActionId": 123,
    "isOverdue": false,
    "daysOverdue": 0
  }
}
```

## POST /crm/agenda/resolve-next-action

Payload base:

```json
{
  "targetType": "prospect",
  "targetId": 10,
  "strategy": "keep|reschedule|reschedule_with_description|override|create_if_none",
  "nextActionAt": "2026-03-25",
  "description": "Enviar condiciones",
  "reason": "Cambio de contexto",
  "sourceInteractionId": null,
  "expectedPendingId": 124
}
```

Validación por strategy:

- `keep`: no admite `nextActionAt`, `description`, `reason`, `sourceInteractionId`
- `reschedule`: requiere `nextActionAt`, no admite `description` ni `reason`
- `reschedule_with_description`: requiere `nextActionAt` y `description`, no admite `reason`
- `override`: requiere `nextActionAt` y `reason`, `description` opcional
- `create_if_none`: requiere `nextActionAt`, `description` opcional, no admite `reason`

Respuesta:

```json
{
  "message": "Próxima acción actualizada correctamente.",
  "data": {
    "strategy": "override",
    "changed": true,
    "previousPending": {},
    "currentPending": {}
  }
}
```

Errores de dominio comunes (`422`):

- `PENDING_EXISTS`
- `NO_PENDING_TO_UPDATE`
- `STALE_PENDING`

