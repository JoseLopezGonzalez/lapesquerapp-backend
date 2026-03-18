# Resumen de cambios: descripción de próxima acción (nextActionNote)

**Fecha:** 2026-03-18

Este documento describe los cambios de contrato en la API v2 CRM para que el frontend pueda adaptar su integración. No incluye instrucciones de implementación en frontend.

---

## 1. Prospect (recurso)

- **Campo nuevo en la respuesta** de cualquier endpoint que devuelve un prospecto (`GET /api/v2/prospects`, `GET /api/v2/prospects/{id}`, `POST`, `PUT`, respuestas embebidas, etc.):
  - **`nextActionNote`** (string | null): descripción de la próxima acción pendiente del prospecto. Máximo 255 caracteres.

- **Payload de creación y actualización** (`POST /api/v2/prospects`, `PUT /api/v2/prospects/{id}`):
  - **`nextActionNote`** (opcional): string, máximo 255 caracteres. Permite fijar o actualizar la descripción de la próxima acción junto con `nextActionAt`.

---

## 2. Dashboard CRM

- **Endpoint:** `GET /api/v2/crm/dashboard`

- **Cambio en la estructura de `reminders_today` y `overdue_actions`:**
  - Cada elemento de estas dos listas incluye ahora el campo **`nextActionNote`** (string | null).
  - Aplica tanto a ítems de tipo `prospect` como a ítems de tipo `interaction`.
  - El frontend puede mostrar descripción + fecha en la agenda diaria y en acciones atrasadas.

---

## 3. Reprogramar próxima acción (schedule-action)

- **Endpoint:** `POST /api/v2/prospects/{id}/schedule-action`

- **Body:**
  - **`nextActionAt`** (obligatorio): fecha en formato `Y-m-d`.
  - **`nextActionNote`** (opcional): string, máximo 255 caracteres. Si no se envía, el backend deja la descripción en null.

---

## 4. Limpiar próxima acción

- **Endpoint:** `DELETE /api/v2/prospects/{id}/next-action`

- **Comportamiento:** Sin cambios de contrato. El backend limpia `next_action_at` y `next_action_note` del prospecto.

---

## 5. Interacciones comerciales

- **Endpoint:** `POST /api/v2/commercial-interactions`

- **Comportamiento cuando la interacción es sobre un prospecto** (`prospectId` presente):
  - Si se envía **`nextActionAt`**: el backend actualiza en el prospecto la fecha y, si se envía, **`nextActionNote`** (descripción de la próxima acción).
  - Si **no** se envía `nextActionAt` (o se envía null): el backend limpia en el prospecto tanto la fecha como la descripción de la próxima acción.
  - El payload de creación de interacción ya admitía `nextActionNote` y `nextActionAt`; ahora ese `nextActionNote` se refleja también en el prospecto para que el dashboard y el detalle del prospecto muestren la misma descripción.

---

## Resumen de campos nuevos o modificados

| Dónde | Cambio |
|-------|--------|
| Prospect resource (todas las respuestas) | Nuevo campo `nextActionNote` |
| POST/PUT prospects | Body acepta `nextActionNote` opcional |
| POST prospects/{id}/schedule-action | Body acepta `nextActionNote` opcional |
| GET crm/dashboard → reminders_today, overdue_actions | Cada ítem incluye `nextActionNote` |
| POST commercial-interactions (sobre prospecto) | `nextActionNote` del body se sincroniza al prospecto |
