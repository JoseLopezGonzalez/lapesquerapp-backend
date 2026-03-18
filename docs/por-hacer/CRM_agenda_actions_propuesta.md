# CRM Comercial - Propuesta "Agenda Actions" (veredicto para otra IA)

## Contexto y objetivo
Actualmente la agenda del comercial para prospectos se apoya en los campos de "proxima accion" dispersos entre:
- `prospects.next_action_at` (+ nota)
- `commercial_interactions.next_action_at` (+ nota)

Esta implementacion permite saber que hacer "para hoy" mediante el endpoint:
- `GET /api/v2/crm/dashboard`

Pero a nivel logico genera confusion: la proxima accion puede ser escrita desde una interaccion, y aun asi el dashboard mezcla origenes.

El objetivo de esta propuesta es introducir una entidad unica para la agenda:
**"agenda_actions"** (o nombre equivalente), que centralice:
- programacion de acciones por target (prospecto o cliente)
- historial de reprogramaciones/aplazamientos
- estado `pending` / `done` / `cancelled` (segun se decida)
- ligadura (trazabilidad) entre "siguientes pasos" definidos en una interaccion y la accion agenda resultante

## Resumen de la logica actual (para contrastar)

### Prospectos
- `prospects` tiene:
  - `next_action_at`
  - `next_action_note` (nota incluida en la evolucion reciente)

### Interacciones comerciales
- `commercial_interactions` tiene:
  - `next_action_at`
  - `next_action_note`

### Efecto al crear una interaccion sobre un prospecto
- Si en la interaccion se envia `nextActionAt`:
  - se actualiza el prospecto con `next_action_at` y la nota
- Si no se envia `nextActionAt` (o llega null):
  - se limpia `next_action_at` (y su nota) del prospecto

### Dashboard
`GET /api/v2/crm/dashboard` construye:
- `reminders_today`: prospectos e interacciones cuya fecha coincide con "hoy"
  - incluye `type: "prospect"` (por prospecto) y `type: "interaction"` (por interaccion)
- `overdue_actions`: mismos origenes, pero con fechas < hoy
- se devuelven ademas `inactive_customers` y `prospects_without_activity`

## Problema percibido
- La agenda es una construccion derivada de dos fuentes.
- El front ve "que hacer", pero la semantica de `type` no encaja bien con la idea de que la proxima accion viene definida por una interaccion.
- Falta un identificador unico de "accion pendiente" (id de la tarea) para que "marcar hecho" sea inequívoco y ligado de forma determinista.

## Propuesta: entidad unica "Accion de agenda" (agenda_actions)

### Que es
Crear una entidad (tabla y modelo) tipo `agenda_actions` que represente una "tarea de calendario" individual.

### Campos conceptuales minimos
- `id`
- `target_type`: `prospect` o `customer`
- `target_id`: id del prospecto/cliente
- `scheduled_at`: fecha/hora programada (para calendario)
- `description`: texto (equivalente a nextActionNote)
- `status`: `pending` / `done` / `cancelled` (o un conjunto equivalente)
- `source_interaction_id`: interaccion que programa la accion (cuando aplica)
- `completed_interaction_id`: interaccion que marca hecho (cuando aplica)
- `previous_action_id`: enlace opcional para reconstruir la cadena de reprogramaciones (cuando aplica)
- `created_at` / `updated_at`

### Regla de complejidad: solo 1 accion pendiente activa por target
Para mantener la V1 simple y evitar ambiguedades:
- Solo puede existir una accion principal en estado `pending` por cada target (prospecto/cliente).
- La idea es que esta sea una **restriccion pragmatica de V1**, y que la tabla/modelo no obligue a rehacer todo si mas adelante se quiere permitir mas de una accion activa.

Si el comercial intenta programar otra `nextActionAt` mediante una interaccion cuando ya hay una `pending` activa:
- **rechazar con 422**
- forzar el flujo de reprogramacion/cierre antes de crear una nueva pendiente

## Como se crea/actualiza (a nivel comportamiento)

### 1) Programar una proxima accion desde una interaccion
Cuando el front registra una interaccion con proxima accion (nota + fecha) sobre un target:
- si el target NO tiene `agenda_actions.pending`:
  - se crea una nueva fila `agenda_actions` en `pending`
  - se enlaza `source_interaction_id` con la interaccion que origina esa programacion
- si el target SI tiene `pending`:
  - se devuelve `422` (regla de una unica pendiente)

### 2) Marcar como "hecha" (constancia ligada)
El "hecho" debe quedar constatable sin heuristicas:
- la marca "hecha" se hace mediante una nueva interaccion relacionada
- el front debe ser capaz de indicar **exactamente** qué accion de agenda se marca como hecha
- idealmente via `agendaActionId` (ligadura explicita)
- el backend actualiza:
  - `agenda_actions.status = done`
  - `completed_interaction_id` con la interaccion de cierre

> Nota: el usuario NO quiere que el sistema infiera "la mas reciente" u otras reglas ambiguas.
> Solo se marca "hecho" cuando hay ligadura explicita desde la relacion (interaccion).

### 3) Reprogramacion / aplazamiento con historial
Al reprogramar:
- la accion pending anterior debe quedar:
  - `cancelled` (o `done` segun semantica), y/o
- cancelada
- se crea una nueva accion pending con nueva fecha y nota
- la nueva accion debe enlazar con la anterior mediante `previous_action_id`

Esto permite calendario hacia atras:
- mostrar que antes estuvo pendiente para otra fecha
- y que luego se movio/reprogramo

## Como se consume en el calendario

### Nuevo endpoint conceptual de calendario por rango
En vez de solo "hoy", habilitar rango (hacia delante/atras):
- `GET /api/v2/crm/agenda?startDate=YYYY-MM-DD&endDate=YYYY-MM-DD`

### Datos que debe devolver cada item (conceptual)
- `agendaActionId`
- target (prospect/customer) + label
- `scheduledAt`
- `description`
- `status`
- enlaces opcionales (para mostrar historial si se decide)

## Endpoints propuestos para calendario y dashboard

### 1) Calendario completo (vista “calendario” en UI)
El calendario necesita poder navegar hacia delante/atrás sin depender solo de “hoy”.

- **Endpoint conceptual:** `GET /api/v2/crm/agenda`
- **Filtros típicos (por rango):**
  - `startDate` (YYYY-MM-DD)
  - `endDate` (YYYY-MM-DD)
  - (opcional) `targetType` (`prospect`/`customer`) o filtros por comercial/cliente si aplica por permisos
  - (opcional) `status` si se quiere mostrar histórico en la misma vista

**Salida:** lista de eventos de agenda ordenados por fecha, donde cada evento incluye al menos:
- `agendaActionId`
- `scheduledAt`
- `description`
- `status`
- referencia al target (prospect/customer) para poder abrir el detalle

### 2) Resumen compacto para dashboard (cards pequeñas / lista)
Para que el dashboard no sea una agenda entera (tarjeta pequeña), necesitamos una respuesta compacta por bloques, pensada para renderizar “en plan lista”:

- **Endpoint conceptual:** `GET /api/v2/crm/agenda/summary`
- **Bloques que debería devolver:**
  - `overdue` (atrasados)
  - `today` (del día)
  - `next` (los próximos más cercanos)
    - típicamente limitado por un `limitNext` (opcional) para que la card no crezca

**Salida por bloque:** objetos con el mínimo para pintar en el UI:
- `agendaActionId`
- `scheduledAt`
- `description`
- `target` + `label`
- `status`

### 3) Endpoints V1 de agenda (operaciones sobre acciones)
Para que el calendario sea accionable desde UI y mantenga ligadura con interacciones, la V1 necesita endpoints de “crear/operar”:

1. Crear accion (programar)
- **Endpoint:** `POST /api/v2/crm/agenda`
- **Entrada (conceptual):**
  - `targetType`: `prospect` o `customer`
  - `targetId`: id del target
  - `scheduledAt`: fecha (Y-m-d)
  - `description`: texto
  - `sourceInteractionId` (opcional): si la accion viene de una interaccion
  - (opcional) `previous_action_id`: si se crea encadenada desde una reprogramacion
- **Reglas:** si ya existe una accion `pending` principal para ese target, se rechaza (422) en la V1.

2. Marcar como hecha (done) en V1
- **V1 oficial:** el marcado como `done` se hace **solo** creando una interacción de cierre.
- La interacción debe incluir el `agendaActionId` (ligadura explícita) para que el backend marque la acción exacta como `done` y guarde `completed_interaction_id`.
- En consecuencia, en V1 **no es imprescindible** disponer de un endpoint dedicado tipo `.../complete` (o se deja como opcional/derivado si el front lo necesitase).

3. Reprogramar
- **Endpoint:** `POST /api/v2/crm/agenda/{id}/reschedule`
- **Idea de contrato:** cancela la accion anterior y crea una nueva accion `pending` con nueva fecha/descripcion enlazada con `previous_action_id`.
- **Debe quedar constancia:** idealmente mediante una interaccion que capture el cambio (ligada con `sourceInteractionId`).

4. Cancelar
- **Endpoint:** `POST /api/v2/crm/agenda/{id}/cancel`
- **Idea de contrato:** marca la accion como `cancelled` (y opcionalmente enlaza historial).

## Preguntas para el veredicto de la otra IA
1. Arquitectura: ?es razonable introducir `agenda_actions` como fuente de verdad unica sin que crezca demasiado la complejidad?
2. Cardinalidad (V1): ?aceptas la restriccion pragmatica de max 1 `pending` principal por target, dejando el modelo listo para ampliar en el futuro?
3. Migracion: ?como minimizar desincronizacion evitando doble fuente de verdad demasiado tiempo? (recomendacion: migracion corta con copia y cambio de lectura a `agenda_actions` cuanto antes)
4. UX: ?solo mostrar `pending` en el calendario principal o permitir filtros para `done/cancelled` sin ensuciar?
5. Alcance V1: ?mantienes `reschedule/cancel` como endpoints, mientras que `done` se hace solo por interaccion ligada (`agendaActionId`)?

## Estrategia V1 de migración (recomendación)
Para evitar bugs por “doble fuente de verdad”, la transición recomendada es:
- Crear `agenda_actions` y
- **copiar** el contenido heredado de la logica actual (principalmente las próximas acciones de `prospects.next_action_*` y las de `commercial_interactions.next_action_*` relevantes) a filas de `agenda_actions`.
- Hacer que `GET /api/v2/crm/dashboard` y el nuevo calendario `GET /api/v2/crm/agenda` (UI) **lean solo desde `agenda_actions`**.
- Mantener los campos legacy solo como respaldo temporal y/o deprecarlos rápidamente (sin doble escritura bidireccional).

## Alcance deliberado (para no disparar complejidad)
- No convertir la agenda en un motor de workflows complejo.
- Mantener la accion de agenda centrada en "pendiente -> realizada" y reprogramacion con historial.
- Constancia solo cuando hay relacion explicita con una interaccion.

## Estado de implementación (V1)
Implementado conforme al plan:
- Fuente de verdad única: `agenda_actions` (tabla + modelo `AgendaAction` con `UsesTenantConnection`).
- Escritura desde `POST /api/v2/commercial-interactions`:
  - si llega `nextActionAt` se crea `agenda_actions.status=pending` para el target (rechaza 422 si ya existe una pending).
  - si NO llega `nextActionAt` se marca como `done` la acción indicada por `agendaActionId` (y se guarda `completed_interaction_id`).
- Reprogramación/cancel:
  - `POST /api/v2/crm/agenda/{id}/reschedule` cancela la pendiente anterior y crea una nueva con `previous_action_id`.
  - `POST /api/v2/crm/agenda/{id}/cancel` marca la pendiente como `cancelled`.
- Calendario + summary:
  - `GET /api/v2/crm/agenda` (calendario) devuelve `events` con `agendaActionId`, `scheduledAt`, `description`, `status` y referencia al target.
  - `GET /api/v2/crm/agenda/summary` y `GET /api/v2/crm/dashboard` consumen `agenda_actions` para `reminders_today` y `overdue_actions`.
- Compatibilidad legacy:
  - `POST /api/v2/prospects/{id}/schedule-action` y `DELETE /api/v2/prospects/{id}/next-action` actúan como wrappers sincronizando `agenda_actions` y legacy.
  - `POST /api/v2/prospects/{id}/convert-to-customer` transfiere la pending principal del prospect al customer.
- Backfill multi-tenant:
  - comando `crm:agenda-backfill` que construye `agenda_actions` desde `prospects.next_action_*` y las últimas interacciones legacy.

