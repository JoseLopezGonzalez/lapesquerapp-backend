# Dashboard CRM

## Endpoint

- `GET /api/v2/crm/dashboard`

## Respuesta

```json
{
  "data": {
    "reminders_today": [
      {
        "type": "prospect",
        "id": 10,
        "label": "Acme Seafood",
        "nextActionAt": "2026-03-17",
        "daysOverdue": 0,
        "prospectId": 10,
        "customerId": null
      }
    ],
    "overdue_actions": [],
    "inactive_customers": [
      {
        "id": 44,
        "name": "Cliente Dormido",
        "country": { "id": 1, "name": "Espana" },
        "daysSinceLastOrder": 42,
        "lastOrderAt": "2026-02-04 00:00:00"
      }
    ],
    "prospects_without_activity": [
      {
        "id": 12,
        "companyName": "Prospecto Antiguo",
        "country": { "id": 1, "name": "Espana" },
        "daysWithoutActivity": 10,
        "lastContactAt": "2026-03-07T10:00:00.000000Z"
      }
    ],
    "counters": {
      "remindersToday": 1,
      "overdueActions": 2,
      "inactiveCustomers": 1,
      "prospectsWithoutActivity": 1
    }
  }
}
```

## Reglas de negocio

- `reminders_today`: prospectos e interacciones con `next_action_at = hoy`
- `overdue_actions`: combinación de
  - prospectos con `next_action_at < hoy`
  - interacciones con `next_action_at < hoy`
- `inactive_customers`: clientes sin pedido en más de 30 días
- `prospects_without_activity`: prospectos sin interacción en más de 7 días

## Resolución de agenda desde frontend

Hay dos caminos soportados:

1. Registrar interacción nueva en `POST /api/v2/commercial-interactions`
   - si `nextActionAt` llega vacío, el backend limpia la acción pendiente del prospecto
2. Reprogramar o limpiar directamente sobre el prospecto
   - `POST /api/v2/prospects/{id}/schedule-action`
   - `DELETE /api/v2/prospects/{id}/next-action`

No existe calendario separado ni motor adicional de tareas.
