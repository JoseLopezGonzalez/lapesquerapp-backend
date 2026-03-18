# Agenda Actions CRM

## Endpoints

- `GET /api/v2/crm/agenda?startDate=YYYY-MM-DD&endDate=YYYY-MM-DD&targetType=prospect|customer&status[]=pending|done|cancelled`
- `GET /api/v2/crm/agenda/summary?limitNext=10`
- `POST /api/v2/crm/agenda`
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
  "nextActionNote": "Nota reprogramada",
  "sourceInteractionId": null
}
```

## POST /crm/agenda/{id}/cancel

No body requerido.

