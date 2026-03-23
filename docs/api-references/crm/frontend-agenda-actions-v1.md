# CRM Frontend — Agenda Actions (V1)

## Objetivo del cambio
La agenda del comercial deja de construirse mezclando:
- `prospects.next_action_at/next_action_note`
- `commercial_interactions.next_action_at/next_action_note`

y pasa a tener una **fuente de verdad única**:
- `agenda_actions`

Esto permite:
- mostrar una lista/calendario consistente
- poder marcar “hecho” de forma determinista (ligado a un `agendaActionId`)
- soportar reprogramaciones con historial

---

## Conceptos clave (V1)

### 1) Target (a quién pertenece la acción)
Una `agenda_actions` siempre pertenece a un **target**:
- `prospect`
- `customer`

### 2) Statuses
En V1 los estados relevantes para UI son:
- `pending`: acción activa y pendiente
- `done`: acción ya realizada (se marca por interacción de cierre con ligadura)
- `reprogrammed`: acción antigua retirada por reprogramación (queda como historial)
- `cancelled`: acción retirada (cancelación explícita)

### 3) Regla V1 de cardinalidad: 1 pending principal por target
Para cada target (prospecto/cliente):
- puede existir **como máximo una** acción `pending` “principal” activa

Implicación para el front:
- si el usuario intenta crear/programar otra próxima acción para el mismo target mientras aún existe una `pending`, el backend responde `422`
- la UI debe tratar ese `422` como señal para usar el flujo de **reprogramar/cerrar** la pending existente antes

---

## Fuente de datos para la UI

### Dashboard (ya no mezcla orígenes)
`GET /api/v2/crm/dashboard` ahora devuelve:
- `data.reminders_today`: acciones `agenda_actions` con `status=pending` y `scheduledAt` = hoy
- `data.overdue_actions`: acciones `agenda_actions` con `status=pending` y `scheduledAt` < hoy

Nota importante:
- la UI ya no debe esperar items con `type="interaction"` dentro de `reminders_today/overdue_actions`

### Calendario (nuevo)
- `GET /api/v2/crm/agenda?startDate=YYYY-MM-DD&endDate=YYYY-MM-DD&targetType=prospect|customer&status[]=pending|reprogrammed|done|cancelled`
- `GET /api/v2/crm/agenda/summary?limitNext=10`

Ambos endpoints consumen `agenda_actions` (no legacy).

---

## Contratos de lectura: payloads del front

### 1) Dashboard: `GET /api/v2/crm/dashboard`
Cada item en:
- `data.reminders_today`
- `data.overdue_actions`

tiene este shape conceptual:
- `type`: `"prospect"` o `"customer"`
- `id`: **igual que `agendaActionId`**
- `agendaActionId`: id de la acción
- `label`: nombre del target (empresa/nombre cliente)
- `nextActionAt`: fecha (`YYYY-MM-DD`) de la acción
- `nextActionNote`: descripción (equivalente a `description` en agenda_actions)
- `daysOverdue`: entero (0 si no está atrasado)
- `prospectId`: id cuando `type="prospect"` (si no, `null`)
- `customerId`: id cuando `type="customer"` (si no, `null`)

Ejemplo (conceptual):
```json
{
  "type": "prospect",
  "id": 123,
  "agendaActionId": 123,
  "label": "Acme Seafood",
  "nextActionAt": "2026-03-20",
  "nextActionNote": "Enviar oferta",
  "daysOverdue": 0,
  "prospectId": 10,
  "customerId": null
}
```

### 2) Calendario: `GET /api/v2/crm/agenda`
La respuesta trae:
- `data.events`: array ordenado por fecha

Cada item del array:
- `agendaActionId`
- `scheduledAt` (`YYYY-MM-DD`)
- `description`
- `status` (`pending|reprogrammed|done|cancelled`)
- `target`: `{ "type": "prospect|customer", "id": <targetId> }`
- `label`

Ejemplo:
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

### 3) Summary: `GET /api/v2/crm/agenda/summary`
Devuelve:
- `data.overdue`
- `data.today`
- `data.next`

Todos los elementos usan el mismo shape que `events` (agendaActionId/scheduledAt/description/status/target/label).

---

## Contratos de escritura: cómo crear, cerrar, reprogramar, cancelar

### A) Creación de próxima acción desde una interacción
Endpoint:
- `POST /api/v2/commercial-interactions`

Regla:
1. Si en la interacción llega `nextActionAt` y NO llega `agendaActionId`:
   - el backend crea una `agenda_actions` con `status=pending` para el target indicado
   - la `description` proviene de `nextActionNote` (si viene, si no puede ser `null`/vacío según el caso)
2. Si el mismo target ya tiene una `pending` activa:
   - el backend responde `422` (regla V1 de 1 pending)

Payload relevante (prospect):
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

Payload relevante (customer):
```json
{
  "customerId": 44,
  "type": "email",
  "occurredAt": "2026-03-17T11:00:00Z",
  "summary": "Se envían condiciones actualizadas",
  "result": "interested"
  // (si incluyes nextActionAt, también crea pending en agenda_actions)
}
```

### B) Marcar una acción como “hecho” (done) en V1
Regla V1 (determinista):
- marcar “done” **no se hace** con heurísticas
- se hace **solo** cuando:
  - el front envía una interacción de cierre
  - esa interacción **NO** incluye `nextActionAt` (o llega `null`)
  - y además incluye `agendaActionId`

Endpoint:
- `POST /api/v2/commercial-interactions`

Requisito para cierre:
- Si `nextActionAt` no se envía (o es `null`), entonces `agendaActionId` es obligatorio

Ejemplo (prospect closure):
```json
{
  "prospectId": 10,
  "type": "call",
  "occurredAt": "2026-03-17T12:10:00Z",
  "summary": "Cierre de la tarea",
  "result": "pending",
  "agendaActionId": 123
  // sin nextActionAt
}
```

Consecuencias para la UI:
- la acción `pending` ligada a `agendaActionId` pasa a `done`
- desaparece como “pendiente activa” en el calendario/summary

Errores típicos:
- `422` si intentas cerrar sin `agendaActionId` (cuando `nextActionAt` no está presente)

### C) Marcar como hecho y crear la siguiente desde la misma interacción
Regla:
- si la interacción incluye `agendaActionId` y `nextActionAt`, el backend:
  - marca la acción actual como `done`
  - crea una nueva `agenda_actions.pending`
  - enlaza la nueva con `previousActionId = agendaActionId`
- `nextActionNote` es opcional; si no se envía, la nueva acción se crea con `description = null`

Ejemplo:
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

Respuesta relevante:
```json
{
  "agenda": {
    "mode": "completed_and_created",
    "completedAction": {
      "agendaActionId": 123,
      "status": "done"
    },
    "createdAction": {
      "agendaActionId": 124,
      "scheduledAt": "2026-03-20",
      "description": "Enviar propuesta final",
      "status": "pending",
      "previousActionId": 123
    }
  }
}
```

### D) Reprogramar desde la agenda
Endpoints:
- `POST /api/v2/crm/agenda/{id}/reschedule`

Efecto esperado:
- la `agenda_actions` original (el `id` reprogramado) pasa a `reprogrammed`
- se crea una nueva `agenda_actions` `pending` con:
  - nueva `scheduledAt`
  - `description` igual a la anterior (se hereda si el front no envía nota)
  - enlace histórico mediante `previous_action_id` (la UI puede ignorarlo si no muestra historial)

Entrada conceptual:
```json
{
  "nextActionAt": "2026-03-27",
  "sourceInteractionId": null
}
```

Importante:
- `reschedule` NO marca la acción anterior como `done`
- el combinado desde interacción sí hace `done + pending`

### E) Cancelar desde la agenda
Endpoint:
- `POST /api/v2/crm/agenda/{id}/cancel`

Efecto esperado:
- la `agenda_actions` `pending` del `id` pasa a `cancelled`
- desaparece de “pendientes activas” en el calendario/summary

---

## Compatibilidad legacy (mientras el front migra)

### Prospecto: wrappers legacy
Estos endpoints siguen existiendo y ahora operan sobre `agenda_actions` internamente:
- `POST /api/v2/prospects/{id}/schedule-action`
- `DELETE /api/v2/prospects/{id}/next-action`

Implicación:
- aunque el front use estos wrappers, el resultado visible para la UI será consistente con `agenda_actions`

### Prospecto -> Customer (transfer de pending)
Endpoint:
- `POST /api/v2/prospects/{id}/convert-to-customer`

Efecto:
- si existía una `agenda_actions.pending` para el prospecto, esa pending se **transfiere** al customer
- para el calendario, el item puede aparecer bajo `type="customer"` en lugar de `type="prospect"`

---

## Manejo de errores para UX

### 422: ya existe una pending activa
Cuándo ocurre:
- intentas programar `nextActionAt` para un target que ya tiene una `agenda_actions.pending`

Qué significa para el usuario:
- hay una acción activa; primero debe reprogramarse/cerrarse la actual

### 422: done sin agendaActionId
Cuándo ocurre:
- en una interacción de cierre no llega `nextActionAt` (o llega `null`)
- pero tampoco llega `agendaActionId`

Qué significa:
- para V1 el cierre siempre debe referenciar la acción concreta a marcar `done`

### 422: combinado inválido

Ocurre si:
- `agendaActionId` no existe
- `agendaActionId` no está en `pending`
- `agendaActionId` no corresponde al target de la interacción
- al cerrar la actual sigue existiendo otra `pending` activa para ese target y no se puede crear la nueva

---

## Resumen operativo (para alinear UI)

1. Para que una acción aparezca como `pending` en calendario:
   - o bien se crea por interacción incluyendo `nextActionAt`
   - o bien se usa `schedule-action` (wrapper legacy)

2. Para marcar “hecho”:
   - interacción de cierre sin `nextActionAt` + `agendaActionId`

3. Para mover una acción:
   - `reschedule` desde `/crm/agenda/{id}/reschedule`

4. Para cancelar:
   - `/crm/agenda/{id}/cancel`

5. Para leer la vista:
   - dashboard y agenda leen `agenda_actions`
