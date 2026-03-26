# CRM (Comercial) — UX “Próxima acción” (Agenda-first) + Dependencias Backend

Documento vivo para discusión y aterrizaje técnico (backend) del flujo UX de **Próxima acción / Acción pendiente** en el rol comercial.

> **Última revisión de estado**: 2026-03-26 (implementación backend actualizada)
> **Nota canónica**: para contrato API / integración backend, la sección **“Dependencias Backend (spec para implementación)”** prevalece sobre secciones anteriores si hubiera discrepancias de naming o semántica.

---

## Resumen de integración frontend (estado real implementado)

Este bloque está escrito como referencia rápida “as-built” para el frontend.

### Flujo en producción (2 pasos + compat legacy)

1. **Paso 1** (`POST /api/v2/commercial-interactions`): registrar interacción.
2. **Paso 2** (`POST /api/v2/crm/agenda/resolve-next-action`): gestionar próxima acción.

Compatibilidad mantenida:

- Si llega `agendaActionId` + `nextActionAt` en Paso 1, se permite `completed_and_created` (flujo legado de “marcar hecha y crear siguiente”).

### Reglas efectivas de Paso 1 (`/commercial-interactions`)

- Sin `agendaActionId` y sin campos de próxima acción: **201**, guarda interacción y `agenda.mode = null`.
- Sin `agendaActionId` pero con `nextActionAt`/`nextActionNote`: **422 fail-fast** (forzar Paso 2).
- Con `agendaActionId` y sin `nextActionAt`: **201**, `agenda.mode = completed`.
- Con `agendaActionId` y con `nextActionAt` (`nextActionNote` opcional legacy): **201**, `agenda.mode = completed_and_created`.

### Reglas efectivas de Paso 2 (`/crm/agenda/resolve-next-action`)

Payload base:

```json
{
  "targetType": "prospect|customer",
  "targetId": 10,
  "strategy": "keep|update|reschedule|override|create_if_none",
  "nextActionAt": "2026-03-20",
  "description": "Enviar propuesta",
  "reason": "Sobrescrita por nueva acción",
  "sourceInteractionId": 501,
  "expectedPendingId": 123
}
```

Respuesta base:

```json
{
  "message": "Próxima acción actualizada correctamente.",
  "data": {
    "strategy": "override",
    "changed": true,
    "previousPending": { "...": "..." },
    "currentPending": { "...": "..." }
  }
}
```

### Preflight implementado

- `GET /api/v2/crm/agenda/pending?targetType=...&targetId=...`
- Devuelve:
  - `data: null` si no hay pending
  - o pending con `isOverdue` y `daysOverdue`

### Códigos de error disponibles

- Dominio (servicio): `PENDING_EXISTS`, `NO_PENDING_TO_UPDATE`, `STALE_PENDING`.
- Validación request: `VALIDATION_ERROR` (incluye casos de `INVALID_STRATEGY_FIELDS` en mensajes de detalle).

### Campos canónicos

- Paso 1 (`/commercial-interactions`): `nextActionNote` se mantiene por compat legado.
- Paso 2 (`/resolve-next-action`): campo canónico de texto de acción es `description`.
- Persistencia en agenda: `agenda_actions.description`.

---

## Validación rápida de lógica de negocio (confirmar)

Este bloque resume, en lenguaje de negocio, **qué cambia** y **qué reglas quedan fijas** para que puedas validar sin leer todo el detalle técnico.

1. Por cada cliente/prospecto solo puede existir **una única acción pendiente activa** (la “próxima acción”). ✅ confirmado
2. El flujo oficial pasa a ser siempre en **2 pasos**: primero se guarda la interacción y después se decide qué hacer con la próxima acción. ✅ confirmado
3. Registrar una interacción no debe perderse por conflictos de agenda: la interacción se guarda aunque luego haya que resolver la próxima acción. ✅ confirmado
4. En el flujo de 2 pasos, el Paso 1 (interacción) no admite campos de “próxima acción”; si llegan, el sistema responde **422** para forzar el flujo correcto y evitar estados ambiguos.
5. “Sobreescribir” significa siempre lo mismo: la pendiente actual pasa a `cancelled`, se crea una nueva `pending` y se guarda motivo. ✅ confirmado
6. “Reprogramar” también mantiene trazabilidad: la pendiente actual pasa a `reprogrammed` y se crea una nueva `pending` enlazada con `previous_action_id`. ✅ confirmado
7. “Actualizar” solo cambia el contenido (`description`) de la pendiente actual; no crea nueva acción. ✅ confirmado a medias(y fecha tambien opcionalmente)
8. Si hay pendiente vencida, el usuario no puede crear otra “sin decidir”: debe elegir explícitamente mantener, actualizar, reprogramar o sobreescribir.
9. Cuando se inicia “Nueva interacción” y ya existe pendiente activa, el sistema siempre muestra preflight para evitar que el comercial se salte compromisos por error. ✅ confirmado
10. Agenda es la fuente de verdad de próxima acción; en V1 se sigue replicando a campos legacy de prospecto solo por compatibilidad. (NO TE ENTIENDO)
11. El backend debe resolver cambios de próxima acción en operación atómica y con protección de concurrencia para mantener la regla de una sola `pending`. ✅ confirmado
12. Los errores de negocio se devuelven con códigos estables (`PENDING_EXISTS`, `STALE_PENDING`, etc.) para que frontend guíe al usuario sin parsear textos. ✅ confirmado
13. `agendaActionId` no “autoriza” crear próxima acción por sí mismo: solo identifica la acción que se está cerrando en el caso legacy de “marcar hecha”; fuera de ese caso, la gestión de próxima acción se hace solo por `resolve-next-action`.

---

## Estado de implementación


| Componente                                                               | Estado         | Icono | Notas                                                    |
| ------------------------------------------------------------------------ | -------------- | ----- | -------------------------------------------------------- |
| Fundación agenda: CRUD (crear/listar/cancelar/reprogramar)               | `IMPLEMENTADO` | ✅     | `CrmAgendaService`, `CrmAgendaController`                |
| `POST /commercial-interactions` (flujo actual acoplado)                  | `IMPLEMENTADO` | ✅     | Guarda interacción + sync agenda en una sola transacción |
| `POST /commercial-interactions` — Paso 1 desacoplado ("siempre guardar") | `IMPLEMENTADO` | ✅     | Guarda interacción sin agenda cuando no llega `agendaActionId` |
| Reprogramación con cadena (`previous_action_id`)                         | `IMPLEMENTADO` | ✅     | `CrmAgendaService::reschedule()`                         |
| Status `reprogrammed` en `agenda_actions`                                | `IMPLEMENTADO` | ✅     | Migración `2026_03_19`                                   |
| Campos `source_interaction_id` y `previous_action_id`                    | `IMPLEMENTADO` | ✅     | Modelo y migración `2026_03_18`                          |
| Campo `description` canónico en `agenda_actions`                         | `IMPLEMENTADO` | ✅     | En `fillable` y `toArrayAssoc()`                         |
| Desacoplar interacción de agenda (Paso 1 independiente)                  | `IMPLEMENTADO` | ✅     | Interacción libre ya no depende de sincronizar agenda    |
| `agendaActionId` opcional en `StoreCommercialInteractionRequest`         | `IMPLEMENTADO` | ✅     | Se admite ausencia de `agendaActionId` en Paso 1         |
| Endpoint `POST /crm/agenda/resolve-next-action` (Paso 2)                 | `IMPLEMENTADO` | ✅     | Ruta + request + controlador + servicio                  |
| Estrategias `keep/update/reschedule/override/create_if_none`             | `IMPLEMENTADO` | ✅     | Implementadas en `CrmAgendaService::resolveNextAction()` |
| Campo `reason`/`cancel_reason` en `agenda_actions`                       | `IMPLEMENTADO` | ✅     | Campo `reason` añadido en migración                      |
| Endpoint `GET /crm/agenda/pending` (preflight)                           | `IMPLEMENTADO` | ✅     | Devuelve pending + `isOverdue`/`daysOverdue`             |
| Códigos de error estables (`PENDING_EXISTS`, `STALE_PENDING`…)           | `IMPLEMENTADO` | ✅     | Soportado con `DomainValidationException` + `code`       |
| Contrato de respuesta enriquecido (`previousPending`/`currentPending`)   | `IMPLEMENTADO` | ✅     | Respuesta estándar de `resolve-next-action`              |


### Estado de ejecución del plan

Plan ejecutado en backend en 3 bloques:

1. **Paso 1 desacoplado**: interacción libre guardable + fail-fast de campos de próxima acción fuera de flujo.
2. **Paso 2 implementado**: `resolve-next-action` con estrategias, respuesta uniforme y errores de dominio.
3. **Preflight implementado**: endpoint de pending activa con derivados de vencimiento.

Pendiente de coordinación con frontend:

- Ajustar consumo de `code` y `errors` para UX final de mensajes.
- Validar secuencias reales de UI (wizard 2 pasos + panel contextual) con QA conjunto.

---

## Contexto

En el CRM del rol comercial existe el concepto de **acción pendiente / próxima acción** que guía el seguimiento por **target**:

- Target = `prospect` (prospecto) o `customer` (cliente)
- Concepto: “siguiente compromiso real”

Decisión base para este documento:

- **Una sola próxima acción activa por target**.

Esto implica que el sistema debe permitir:

- **Sobreescribir** la próxima acción existente (convertirla automáticamente en no-activa).
- **Modificar** (editar contenido) la próxima acción existente.
- **Reprogramar** (cambiar fecha) la próxima acción existente.

## Flujo ideal (lineal)

- El comercial abre agenda y revisa pendientes.
- Realiza la acción y registra una interacción.
- Normalmente crea una próxima acción.
- Si no puede hacerla, la reprograma.
- Si decide descartarla, la cancela.

## Problema UX real (dilema)

Hay escenarios habituales donde el flujo lineal se rompe:

### Caso A. “Cambio de tornas” tras cerrar una acción

- El comercial cierra una acción y crea una siguiente.
- Poco después cambia el contexto (llamada entrante, etc.).
- Necesita registrar otra interacción y ajustar la próxima acción.
- Puede ocurrir que la acción anterior:
  - ya no sirva, o
  - siga sirviendo junto con una nueva necesidad.

### Caso B. “Interacción inesperada” cuando la próxima acción no es hoy

- La próxima acción no era hoy.
- El target llama o hay una interacción imprevista.
- Dilema: registrar interacción + decidir qué pasa con la próxima acción existente.

## Estado actual del sistema (hoy)

### 1) Modelado (orientación frontend y backend)

El modelo actual está orientado a **una sola próxima acción por target**:

- `prospects.next_action_at` / `prospects.next_action_note` (legacy para prospectos)
- `commercial_interactions.next_action_at` / `commercial_interactions.next_action_note` (histórico por interacción)
- La agenda materializada vive en `agenda_actions` con `status`:
  - `pending`, `reprogrammed`, `done`, `cancelled`

**Campos ya existentes en `agenda_actions`** (migración `2026_03_18`):

- `target_type`, `target_id`, `scheduled_at`, `description`
- `status` (constraint: `pending|done|cancelled|reprogrammed`)
- `source_interaction_id` (FK a `commercial_interactions`, nullable)
- `completed_interaction_id` (FK a `commercial_interactions`, nullable)
- `previous_action_id` (FK self-referencial, para cadena reschedule/override)

**Lo que aún NO existe en DB**:

- Campo `reason`/`cancel_reason` para auditoría de overrides

### 2) Restricción backend reflejada en UI (422) — sigue activa

Cuando se intenta registrar una interacción creando una próxima acción y ya existe una acción `pending` activa para ese target, el backend responde **422** con un mensaje del estilo:

> “Ya existe una acción pendiente activa para este target. Reprograma o cierra la pendiente actual antes de crear otra.”

El código que impone esto está en `CrmAgendaService::createPendingFromInteraction()` (líneas 113–118).

Efecto UX actual: obliga a una ruta de más pasos:

- salir del contexto,
- reprogramar o cancelar manualmente,
- volver a intentar registrar interacción / próxima acción.

### 3) Lo que permite hoy la Agenda ✅

Desde Agenda:

- Reprogramar acción (`reschedule`) → `CrmAgendaService::reschedule()` — crea cadena (`reprogrammed` + nueva pending con `previous_action_id`)
- Cancelar acción (`cancel`) → `CrmAgendaService::cancel()`
- Marcar hecha (cierre) ⇒ flujo que registra interacción y opcionalmente programa siguiente acción → `CrmAgendaService::completeFromInteraction()`
- Obtener pending de un target (interno) → `CrmAgendaService::getPendingForTarget()` — no expuesto como endpoint público

### Aclaración clave

El dilema original (“quiero registrar interacción + próxima acción pero ya hay una pendiente”) **no nace del flujo de “marcar hecha”**:

- Cuando marcas una acción como hecha, consumes la única `pending` activa y luego puedes crear la siguiente sin conflicto.

El dilema real aparece cuando el comercial:

- registra una interacción **sin depender de una acción prevista** (llamada entrante, visita, etc.)
- y además quiere crear o ajustar la próxima acción
- existiendo ya una `pending` activa para ese target.

## Enfoque de producto: “Agenda primero”

Objetivo: la agenda es el “centro de decisión”, no un listado pasivo.

## Propuesta UX en Agenda (flujo guiado)

### 1) Clic en una acción ⇒ abrir panel contextual (modal no-dismissable)

Al pulsar una acción:

- dialog/panel que **no se cierra al pinchar fuera**
- contextualiza target

Contenido mínimo:

- Header: nombre target, tipo, estado, fecha, nota
- Contexto rápido (mini overview):
  - timeline últimas N interacciones
  - acceso pedidos/histórico o resumen
  - acceso a ficha completa

Acciones claras:

- Hacer (equivale a “marcar hecha” + registrar interacción)
- Reprogramar
- Cancelar

### 2) Registrar interacción desde Agenda (sin acción prevista)

La agenda debe permitir crear:

- Interacción + próxima acción **sin que la interacción cuelgue de una acción prevista**

Regla UX:

- No mostrar “Nueva interacción” dentro del panel de detalle de una acción.
- El CTA “Registrar interacción (sin cerrar ninguna tarea)” debe existir:
  - en el diálogo del día, y/o
  - en la agenda global (botón principal / flotante).

### 2.1) Preflight al iniciar “Nueva interacción” (si existe pending activa)

Si existe `pending` activa para el target, avisar y ofrecer:

- Retomar acción pendiente (abrir panel / hacer / reprogramar / cancelar)
- Continuar registrando interacción
- (Opcional) Ver detalle en solo lectura

Objetivo: evitar que el comercial se “salte” una acción por descuido.

### 2.2) Separar UI: “Registrar interacción” vs “Gestionar próxima acción”

Separar en 2 pasos claros:

- Paso 1: **Registrar interacción** (siempre guardable)
- Paso 2: **Gestionar próxima acción** (única)

En Paso 2, si ya existe `pending`, permitir:

- Editar la pendiente actual (contenido)
- Reprogramar la pendiente actual (fecha)
- Sobreescribir con la nueva (la nueva pasa a ser activa; la anterior deja de serlo)
- Mantener la anterior (no se crea nueva próxima acción)

## Reglas lógicas (para no dejar casos colgados)

Regla base:

- Siempre se puede registrar una interacción.
- La agenda debe quedar consistente: **1 sola pending activa por target**.
- Si existe una pendiente vencida, antes de permitir “crear nueva próxima acción” el sistema debe forzar una elección explícita: hacer / reprogramar / cancelar / mantener.

Micro-regla UX sugerida en Paso 2:

Si existe `pending` vencida, mostrar bloque fijo:

- “Acción pendiente actual (vencida): fecha + nota”
- “Antes de programar una nueva próxima acción, decide qué hacemos con esta”
  - Hacer ahora (ligar interacción y cerrarla)
  - Reprogramar
  - Cancelar
  - Mantener

Nota sobre “Mantener”:

- Mantener **no** debe permitir “crear otra sin decidir”.
- Mantener permite continuar solo si el usuario decide explícitamente:
  - Sobreescribir, o
  - Editar, o
  - Reprogramar la existente.

## Decisiones cerradas (según el documento de producto)

- **D1**: 1 sola próxima acción activa por target.
- **D2**: Sobreescribir = cancelar la anterior (`cancelled`) + crear nueva como única activa.
- **Motivo**: se guarda; por defecto texto predefinido y/o texto libre.
- **D3**: Panel contextual con tabs.
- **D4**: Pendiente vencida no se puede ignorar “sin decidir”.
- **D5**: Sobreescritura se implementa en backend (operación atómica).
- **D6**: CTA “Registrar interacción”: diálogo del día + botón flotante global.
- **D7**: Garantía de guardado: flujo en 2 pasos siempre (Interacción → Próxima acción).
- **D8**: Preflight siempre que haya pending activa.
- **D9**: Terminología:
  - “Acción pendiente” = próxima acción aún no hecha (la única activa)
  - “Próxima acción” = equivalente a acción pendiente (en agenda)

Pendiente:

- **D10**: definir si el panel contextual es copia adaptada de vistas existentes (cliente/prospecto) y qué recortes.

---

## Dependencias y fricciones técnicas detectadas en backend (hoy)

### A) El conflicto “ya existe pending” se traduce en 422 al crear próxima acción — ❌ SIN RESOLVER

En `CrmAgendaService::createPendingFromInteraction()` (líneas 113–118) se impone la regla de “1 pending por target” rechazando con `ValidationException` cuando ya existe una pending:

- error cae en `nextActionAt`
- esto es lo que hoy obliga a “salir y reprogramar/cancelar manualmente”

**Estado**: No cambia hasta que se implemente `resolve-next-action` (Paso 2).

### B) Hoy NO se puede “registrar interacción sin acción prevista” — ❌ SIN RESOLVER

El request `StoreCommercialInteractionRequest` impone (validación personalizada):

- si no viene `nextActionAt`, entonces `agendaActionId` es obligatorio

Esto bloquea el flujo “interacción inesperada” (llamada entrante) si no se quiere cerrar ninguna agendaAction en ese momento.

**Estado**: Pendiente de cambiar la validación en `StoreCommercialInteractionRequest` para hacer `agendaActionId` verdaderamente opcional.

### C) El guardado de interacción está acoplado a agenda dentro de una transacción — ❌ SIN RESOLVER

`CommercialInteractionService::store()` (líneas 64–141) guarda interacción y luego sincroniza agenda en la misma transacción:

- si la agenda falla (por ejemplo, por conflicto de pending), **se revierte también la interacción**
- esto contradice la regla de negocio/UX: “siempre registrar interacción”

**Estado**: Pendiente de refactorizar para separar el guardado de interacción del sync de agenda.

---

## Objetivo técnico (backend) para soportar el UX propuesto

1. **Permitir guardar interacciones siempre** (sin depender de agenda).
2. Mantener la invariantes:
  - **solo 1 pending activa por target**
3. Añadir una operación backend **atómica** para resolver “sobreescribir próxima acción”:
  - cancelar anterior + crear nueva pending + guardar motivo
4. Permitir “gestionar próxima acción” en Paso 2:
  - keep / update / reschedule / override
5. Añadir endpoint(s) para **preflight**:
  - consultar la pending actual de un target (si existe)

---

## Propuesta de implementación backend (mínimo viable y coherente con el repo)

> **Nota de avance**: La fundación de datos (modelo, migraciones, servicio de agenda) está implementada. Los cambios pendientes son los 3 puntos de la sección anterior (A, B, C) más los nuevos artefactos descritos abajo.

### Convención de nombres (backend vs frontend)

Para evitar confusión entre `nextActionNote` / “note” / `description`:


| Contexto                                               | Campo de texto       | Notas                                                                                                               |
| ------------------------------------------------------ | -------------------- | ------------------------------------------------------------------------------------------------------------------- |
| `POST /api/v2/commercial-interactions` (Paso 1)        | `**nextActionNote`** | Legacy; el backend lo mapea internamente a `agenda_actions.description`. No exponer `description` en este endpoint. |
| `POST /api/v2/crm/agenda/resolve-next-action` (Paso 2) | `**description**`    | 1:1 con el modelo `AgendaAction`.                                                                                   |
| Tabla `agenda_actions` (backend)                       | `**description**`    | Nombre canónico.                                                                                                    |


### 1) Contrato de `POST /api/v2/commercial-interactions` — ✅ IMPLEMENTADO

Cambios de contrato:

- `agendaActionId` debe ser **opcional**
- `nextActionAt` / `nextActionNote` deben ser **opcionales**

> **Naming**: ver tabla "Convención de nombres" arriba. Los únicos campos de texto relevantes en este endpoint son `nextActionAt` y `nextActionNote`.

Decisión para evitar ambigüedad (alineado con D7 “2 pasos siempre”):

- Este endpoint **NO gestiona** “próxima acción” salvo en el caso explícito de “cerrar una acción de agenda” (modo `done`/`completed_and_created`).
- La creación/modificación/reprogramación/sobreescritura de próxima acción ocurre **solo** vía `resolve-next-action` (Paso 2).
- Esta separación es por **responsabilidad de flujo** (Paso 1 = interacción, Paso 2 = próxima acción), no porque crear próxima acción “dependa” de `agendaActionId`.

Semántica cerrada:


| `agendaActionId` | `nextActionAt` | Comportamiento                                                                                                                                                            |
| ---------------- | -------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| presente         | ausente        | Marcar acción como `done`                                                                                                                                                 |
| presente         | presente       | `done` + crear nueva pending (`completed_and_created`) — compatibilidad con flujo lineal                                                                                  |
| ausente          | ausente        | Guardar interacción siempre. Agenda no se toca.                                                                                                                           |
| ausente          | presente       | **422 (fail fast)**: forzar al Paso 2 (`resolve-next-action`). Motivo: ignorarlo sería peor UX (el comercial cree que programó una próxima acción cuando en realidad no). |

Regla complementaria (para cerrar ambigüedad):

- Si `agendaActionId` está **ausente** y llega **cualquier** campo de próxima acción (`nextActionAt` o `nextActionNote`), la respuesta debe ser **422 fail fast** y la resolución pasa al Paso 2 (`resolve-next-action`).
- `nextActionNote` en `POST /commercial-interactions` queda solo por compatibilidad del caso `completed_and_created` (cuando sí hay `agendaActionId` + `nextActionAt`).
- El **422** aquí significa “estás intentando crear/editar próxima acción en el endpoint de interacción”, no “falta `agendaActionId` para poder crearla”.


Nota de diseño:

- El frontend, tras guardar la interacción, lanza el Paso 2 (gestión de próxima acción) llamando a `resolve-next-action`.

### 2) Endpoint atómico para Paso 2: “Gestionar próxima acción” por target — ✅ IMPLEMENTADO

> No existe ningún artefacto de este endpoint: no hay ruta, controlador ni request. El método interno `CrmAgendaService::getPendingForTarget()` existe pero no está expuesto públicamente.

Crear un endpoint dedicado, por ejemplo:

- `POST /api/v2/crm/agenda/resolve-next-action`

Opciones de diseño:

- **S1 (recomendado)**: un endpoint + `strategy` obligatorio, con validación estricta por estrategia.
- **S2**: endpoints explícitos (override/reschedule/update/keep/create). Menos flexible, más simple de validar.

Este documento asume **S1**.

Payload sugerido (S1):

```json
{
  "targetType": "prospect|customer",
  "targetId": 10,
  "strategy": "keep|update|reschedule|override|create_if_none",
  "nextActionAt": "2026-03-20",
  "description": "Enviar propuesta",
  "reason": "Sobrescrita por nueva acción",
  "sourceInteractionId": 501
}
```

Tabla de validación (S1, **estricta**):

- `keep`
  - **prohíbe**: `nextActionAt`, `description`, `reason`, `sourceInteractionId`
  - Nota: `keep` no cambia agenda; si se necesita auditoría/telemetría, se debe modelar explícitamente (p. ej. `agenda_events` o ActivityLog dedicado).
- `update` (editar contenido de la pending actual)
  - **requiere**: `description`
  - **prohíbe**: `nextActionAt`, `reason`
- `reschedule` (reprogramar como cadena)
  - **requiere**: `nextActionAt`
  - **permite**: `description` (si se quiere actualizar también el texto al reprogramar)
  - **prohíbe**: `reason`
- `override` (sobreescribir = cancelar + crear nueva)
  - **requiere**: `nextActionAt` y `reason`
  - **permite**: `description`
- `create_if_none`
  - **requiere**: `nextActionAt`
  - **permite**: `description`
  - **prohíbe**: `reason`

Semántica (consistente con modelo de cadena `previousActionId`):

- `keep`: no cambia nada en agenda.
- `update`: edita **solo** la `description` de la pending actual.
- `reschedule`: la acción anterior deja de ser `pending` (pasa a `reprogrammed`) y se crea una **nueva** `pending` con `previous_action_id` apuntando a la anterior.
- `override`: la acción anterior deja de ser `pending` (pasa a `cancelled`), se guarda `reason`, y se crea una **nueva** `pending` con `previous_action_id` apuntando a la anterior.
- `create_if_none`: si no existe pending, crea una pending nueva (equivalente a “programar próxima acción” normal).

Coherencia con datos legacy (`prospects.next_action_`*):

- Si el sistema sigue usando `prospects.next_action_at` / `prospects.next_action_note` como “summary rápido” en algunas vistas, hay que definir la política de sincronización:
  - `resolve-next-action` debe ser la **fuente de verdad** para “próxima acción”.
  - Cuando `targetType = prospect`, el backend debe decidir si:
    - **(decisión para V1)**: replicar a `prospects.next_action_`* el estado de la pending actual (fecha + description) para mantener compatibilidad con vistas/queries existentes.
    - (futuro) deprecar el uso de esos campos y consumir siempre `agenda_actions`.

Política de cadena (`previous_action_id`):

- En `reschedule` y `override`, la nueva acción debe apuntar a la **anterior inmediata** (aunque esa anterior ya tenga `previous_action_id`).
- El backend debe impedir “cadenas rotas” por concurrencia:
  - la acción que se transiciona debe seguir en `pending` al momento del update,
  - y debe pertenecer al `targetType/targetId` indicado.

Requisitos de atomicidad y concurrencia:

- La operación debe ser **atómica** (transacción) y segura ante carreras (dos pestañas o dos usuarios).
- Debe existir una protección a nivel DB o bloqueo:
  - Debe existir una garantía fuerte: o bien un mecanismo equivalente a “índice único parcial” (si se implementa, documentar el enfoque para MySQL), o bien lock transaccional por target (p. ej. `SELECT ... FOR UPDATE` de la pending actual + verificación antes de crear).
  - Nota: en MySQL, un “índice único parcial por status=pending” no es trivial; si se necesita garantía por constraint, puede requerir una estrategia alternativa (columna derivada, tabla auxiliar, o locking).
- Debe devolver error claro cuando detecte conflicto entre preflight y submit (concurrencia optimista).

### 2.1) Errores estables (códigos) para guiar el frontend

Para evitar parsear mensajes y permitir UX guiado, se recomienda estandarizar códigos (ejemplos):

- `PENDING_EXISTS`: ya existe pending activa y la estrategia no permite continuar sin resolverla.
- `NO_PENDING_TO_UPDATE`: `update/reschedule/override/keep` requieren pending pero no existe.
- `STALE_PENDING`: la pending cambió entre preflight y submit (concurrencia optimista).
- `INVALID_STRATEGY_FIELDS`: campos presentes/ausentes no cumplen la tabla de validación.
- `TARGET_MISMATCH`: la acción pending encontrada no corresponde al target indicado (seguridad/consistencia).
- `PENDING_NOT_ACTIVE`: la acción a transicionar ya no está en `pending`.

### 2.2) Contrato de respuesta (resolve-next-action)

Para refrescar UI sin reconsultas, `resolve-next-action` debe devolver:

- `previousPending`: la acción que era `pending` al inicio y que, tras aplicar la estrategia, queda en su **estado final** (p. ej. `cancelled` o `reprogrammed`). Si no existía pending, `null`.
- `currentPending`: pending activa después de aplicar la estrategia (o `null`)
- `changed`: `true|false`

Ejemplo (override):

```json
{
  "message": "Próxima acción actualizada correctamente.",
  "data": {
    "strategy": "override",
    "changed": true,
    "previousPending": {
      "agendaActionId": 100,
      "targetType": "prospect",
      "targetId": 10,
      "scheduledAt": "2026-03-18",
      "description": "Llamar para confirmar condiciones",
      "status": "cancelled",
      "previousActionId": null
    },
    "currentPending": {
      "agendaActionId": 101,
      "targetType": "prospect",
      "targetId": 10,
      "scheduledAt": "2026-03-20",
      "description": "Enviar muestra",
      "status": "pending",
      "previousActionId": 100
    }
  }
}
```

Ejemplo (keep):

```json
{
  "message": "Próxima acción mantenida.",
  "data": {
    "strategy": "keep",
    "changed": false,
    "previousPending": {
      "agendaActionId": 100,
      "targetType": "prospect",
      "targetId": 10,
      "scheduledAt": "2026-03-18",
      "description": "Llamar para confirmar condiciones",
      "status": "pending",
      "previousActionId": null
    },
    "currentPending": {
      "agendaActionId": 100,
      "targetType": "prospect",
      "targetId": 10,
      "scheduledAt": "2026-03-18",
      "description": "Llamar para confirmar condiciones",
      "status": "pending",
      "previousActionId": null
    }
  }
}
```

Ejemplo (reschedule):

```json
{
  "message": "Acción reprogramada correctamente.",
  "data": {
    "strategy": "reschedule",
    "changed": true,
    "previousPending": {
      "agendaActionId": 200,
      "targetType": "prospect",
      "targetId": 10,
      "scheduledAt": "2026-03-18",
      "description": "Llamar para confirmar condiciones",
      "status": "reprogrammed",
      "previousActionId": null
    },
    "currentPending": {
      "agendaActionId": 201,
      "targetType": "prospect",
      "targetId": 10,
      "scheduledAt": "2026-03-25",
      "description": "Llamar para confirmar condiciones",
      "status": "pending",
      "previousActionId": 200
    }
  }
}
```

Ejemplo (update):

```json
{
  "message": "Acción actualizada correctamente.",
  "data": {
    "strategy": "update",
    "changed": true,
    "previousPending": {
      "agendaActionId": 300,
      "targetType": "prospect",
      "targetId": 10,
      "scheduledAt": "2026-03-18",
      "description": "Llamar para confirmar condiciones",
      "status": "pending",
      "previousActionId": null
    },
    "currentPending": {
      "agendaActionId": 300,
      "targetType": "prospect",
      "targetId": 10,
      "scheduledAt": "2026-03-18",
      "description": "Llamar para confirmar condiciones (pide responder por WhatsApp)",
      "status": "pending",
      "previousActionId": null
    }
  }
}
```

### 2.3) Contrato de error (código estable)

Formato recomendado para `422` en este módulo (ejemplo):

```json
{
  "message": "Validation error",
  "userMessage": "No se pudo actualizar la próxima acción.",
  "code": "INVALID_STRATEGY_FIELDS",
  "errors": {
    "nextActionAt": ["nextActionAt es obligatorio para strategy=override."]
  }
}
```

Regla explícita (update sin pending):

- Si `strategy=update` y no existe pending activa para el target, el backend devuelve **422** con:
  - `code = NO_PENDING_TO_UPDATE`
  - `errors` apuntando al campo `strategy` y/o `target`

No-go (para evitar caminos alternativos):

- Si una interacción se registra **sin** `agendaActionId`, la **única** vía para crear/modificar/reprogramar/sobreescribir la próxima acción es `resolve-next-action`.

### 3) Persistencia del “motivo” (reason) del override — ✅ IMPLEMENTADO

`agenda_actions` ya incluye campo `reason` mediante migración incremental `2026_03_26_180000_add_reason_to_agenda_actions_table.php`.

- Campo implementado: `reason` (text nullable)

Mínimos recomendados de trazabilidad:

- `source_interaction_id` en la **nueva** acción (ya existe en modelo y debe poblarse siempre que aplique)
- `reason` / `cancel_reason` para overrides (texto predefinido por defecto y/o libre)

Opcionales (si se quiere auditoría fuerte y debugging fácil):

- `cancelled_at`, `reprogrammed_at`, `done_at` (o equivalente)
- `cancel_source_interaction_id` (si el override se decide en Paso 2 y no en el mismo POST de interacción)

### 4) Preflight: endpoint para obtener la pending actual de un target — ✅ IMPLEMENTADO

> El método `CrmAgendaService::getPendingForTarget()` se expone vía endpoint `GET /api/v2/crm/agenda/pending`.

Crear endpoint de lectura:

- `GET /api/v2/crm/agenda/pending?targetType=...&targetId=...`

Respuesta:

- `data: null` si no hay
- o `data` con shape similar a `AgendaAction::toArrayAssoc()`

Campos derivados recomendados (para UX y consistencia de timezone):

- `isOverdue` (bool)
- `daysOverdue` (int)
- opcional: `ageDays` (int) si se quiere distinguir “muy vencida” vs “poco vencida”

Nota de fechas y timezone (V1):

- `nextActionAt` se trata como **fecha de negocio** (`YYYY-MM-DD`, sin hora).
- `scheduled_at` en `agenda_actions` se interpreta en la misma base (date-only).
- El backend calcula `isOverdue/daysOverdue` usando `business_timezone` (config `app.business_timezone`, hoy `Europe/Madrid`) y comparando **por fecha**.

Esto alimenta:

- aviso “existe pending”
- “ver detalle”
- “retomar”

---

## Compatibilidad con el flujo actual

> **Prefijo de rutas**: todas las rutas usan el prefijo `/api/v2/`. En la tabla se omite por brevedad.

El backend ya soporta ✅:


| Operación                                     | Endpoint (sin prefijo `/api/v2`)                       | Servicio                                        |
| --------------------------------------------- | ------------------------------------------------------ | ----------------------------------------------- |
| Agenda calendar/summary                       | `GET /crm/agenda`, `GET /crm/agenda/summary`           | `CrmAgendaService::listCalendar()`, `summary()` |
| Crear acción pending directa                  | `POST /crm/agenda`                                     | `CrmAgendaService`                              |
| Crear acción pending desde interacción        | `POST /commercial-interactions` (con `nextActionAt`)   | `CommercialInteractionService::store()`         |
| Reschedule (crea cadena `previous_action_id`) | `POST /crm/agenda/{id}/reschedule`                     | `CrmAgendaService::reschedule()`                |
| Cancel                                        | `POST /crm/agenda/{id}/cancel`                         | `CrmAgendaService::cancel()`                    |
| Completar desde interacción                   | `POST /commercial-interactions` (con `agendaActionId`) | `CrmAgendaService::completeFromInteraction()`   |


La propuesta mantiene esos endpoints y añade/ajusta lo mínimo para:

- no bloquear interacción por conflictos de agenda (Paso 1 desacoplado)
- resolver conflictos de “ya existe pending” sin obligar a navegación manual (Paso 2 `resolve-next-action`)

---

## Referencias internas (repo)

### Artefactos existentes ✅

- **Servicios**
  - `app/Services/v2/CrmAgendaService.php` — 529 líneas; métodos clave: `createPendingFromInteraction`, `syncFromInteraction`, `reschedule`, `cancel`, `completeFromInteraction`, `getPendingForTarget`
  - `app/Services/v2/CommercialInteractionService.php` — `store()` (acoplado a agenda en transacción)
- **Requests**
  - `app/Http/Requests/v2/StoreCommercialInteractionRequest.php` — validación con `agendaActionId` obligatorio si no hay `nextActionAt`
  - `app/Http/Requests/v2/StoreCrmAgendaActionRequest.php` — incluye `previousActionId`
  - `app/Http/Requests/v2/RescheduleCrmAgendaActionRequest.php`
  - `app/Http/Requests/v2/CancelCrmAgendaActionRequest.php`
  - `app/Http/Requests/v2/IndexCrmAgendaRequest.php`
  - `app/Http/Requests/v2/IndexCrmAgendaSummaryRequest.php`
- **Controladores y rutas**
  - `app/Http/Controllers/v2/CrmAgendaController.php` — métodos: `calendar`, `summary`, `store`, `reschedule`, `cancel`
  - `app/Http/Controllers/v2/CommercialInteractionController.php` — métodos: `index`, `store`, `show`
  - `routes/api.php` — rutas activas (prefijo `/api/v2`): `GET/POST /crm/agenda`, `GET /crm/agenda/summary`, `POST /crm/agenda/{id}/reschedule`, `POST /crm/agenda/{id}/cancel`, `GET/POST /commercial-interactions`, `GET /commercial-interactions/{id}`
- **Modelo y migraciones**
  - `app/Models/AgendaAction.php` — `fillable`: `target_type`, `target_id`, `scheduled_at`, `description`, `status`, `source_interaction_id`, `completed_interaction_id`, `previous_action_id`
  - `database/migrations/companies/2026_03_18_130000_create_agenda_actions_table.php`
  - `database/migrations/companies/2026_03_19_000100_allow_reprogrammed_status_in_agenda_actions.php`

### Artefactos incorporados en esta iteración ✅

- `app/Http/Requests/v2/ResolveNextActionRequest.php`
- `app/Http/Requests/v2/ShowCrmAgendaPendingRequest.php`
- Migración: `database/migrations/companies/2026_03_26_180000_add_reason_to_agenda_actions_table.php`
- Ruta: `POST /api/v2/crm/agenda/resolve-next-action`
- Ruta: `GET /api/v2/crm/agenda/pending`

### Documentación canónica

- `docs/api-references/crm/frontend-integration.md` — guía de integración frontend (existente)
- `docs/api-references/crm/agenda.md` — referencia de agenda (existente)

---

## Checklist de validación frontend (post-implementación)

Usar este checklist para validar integración con el documento hermano del frontend:

1. `POST /commercial-interactions` sin `agendaActionId` y sin campos de próxima acción:
   - guarda interacción (`201`)
   - `agenda.mode = null`
2. `POST /commercial-interactions` sin `agendaActionId` pero con `nextActionAt/nextActionNote`:
   - responde `422`
   - `code = VALIDATION_ERROR`
3. `POST /commercial-interactions` con `agendaActionId`:
   - sin `nextActionAt` => `agenda.mode = completed`
   - con `nextActionAt` => `agenda.mode = completed_and_created`
4. `POST /crm/agenda/resolve-next-action`:
   - `keep` devuelve `changed=false`
   - `update` actualiza solo `description`
   - `reschedule` crea cadena `reprogrammed -> pending`
   - `override` crea cadena `cancelled(reason) -> pending`
   - `create_if_none` falla con `PENDING_EXISTS` si ya hay pending
5. `GET /crm/agenda/pending`:
   - devuelve `data=null` si no hay pending
   - devuelve `isOverdue` y `daysOverdue` si hay pending
6. Errores de dominio de Paso 2:
   - `PENDING_EXISTS`, `NO_PENDING_TO_UPDATE`, `STALE_PENDING` deben mostrarse como mensajes UX accionables.

