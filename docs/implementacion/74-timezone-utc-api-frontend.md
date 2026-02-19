# Timezone UTC — Contrato de API y guía para Frontend

**Estado**: Referencia para integración  
**Última actualización**: 2026-02-19

El backend unifica el manejo de fechas y horas: **todos los instantes (datetime) se almacenan en UTC** y se devuelven en **ISO 8601 con zona**. Las fechas solo “día” (sin hora) se interpretan en la zona de negocio (Europe/Madrid) y se devuelven en formato `Y-m-d`. Este documento describe el contrato de la API y los cambios necesarios en el frontend para adaptarse.

---

## 1. Resumen

- **Almacenamiento:** Todos los instantes (timestamp, opened_at, closed_at, resolved_at, etc.) se guardan en UTC en la base de datos.
- **Respuesta API:** Los campos **datetime** se devuelven en **ISO 8601 con zona** (p. ej. `2024-01-15T09:30:00.000000Z` o `2024-01-15T10:30:00+01:00`). El frontend debe interpretarlos siempre como instante y formatearlos en la zona local del usuario para mostrar.
- **Fechas solo “día”:** Los campos `entryDate`, `loadDate`, `date` (recepciones, despachos), `cost_date`, etc. representan el **día natural en la zona de negocio** (Europe/Madrid). Se aceptan en escritura como `YYYY-MM-DD` o ISO datetime; el backend normaliza a ese día. En lectura se devuelven como `YYYY-MM-DD` o equivalente.

---

## 2. Contrato de escritura (POST / PUT)

### 2.1 Fichajes (punches)

- **Endpoint:** POST `/api/v2/punches` (fichaje manual), POST `/api/v2/punches/bulk` (masivo).
- **Campo:** `timestamp` (y en bulk `punches[].timestamp`).
- **Formato:** Enviar en **ISO 8601 con zona**. Recomendado: UTC con sufijo `Z`, p. ej. `new Date().toISOString()` en JavaScript.
- **Ejemplos válidos:**
  - `"2024-01-15T09:30:00.000Z"` (UTC)
  - `"2024-01-15T10:30:00+01:00"` (hora local España invierno)
- El backend convierte a UTC y guarda. No enviar fechas sin zona si se quiere evitar ambigüedad.

### 2.2 Pedidos (Orders)

- **Campos:** `entryDate`, `loadDate`.
- **Formato:** `YYYY-MM-DD` o ISO date/datetime (con o sin zona). El backend normaliza al día en la zona de negocio.

### 2.3 Recepción de materia prima (RawMaterialReception)

- **Campo:** `date`.
- **Formato:** `YYYY-MM-DD` o ISO date/datetime. El backend normaliza a fecha en la zona de negocio.

### 2.4 Despachos de cebo (CeboDispatch)

- **Campo:** `date`.
- **Formato:** `YYYY-MM-DD` o ISO date/datetime. Misma normalización que arriba.

### 2.5 Producción (Production)

- **Campo:** `date` (cuando se envía).
- **Formato:** `YYYY-MM-DD` o ISO date/datetime. Normalización a día en zona de negocio.

### 2.6 Costes de producción (ProductionCost)

- **Campo:** `cost_date`.
- **Formato:** `YYYY-MM-DD` o ISO date/datetime. Misma normalización.

### 2.7 Registros de producción (ProductionRecord) e incidencias (Incident)

- Si el backend acepta `started_at`, `finished_at` o `resolved_at` desde el front: enviar en **ISO 8601 con zona**. El backend convertirá a UTC antes de guardar.

---

## 3. Contrato de lectura (GET / listados / detalle)

### 3.1 Campos datetime (siempre ISO 8601 con zona)

Los siguientes campos vendrán en formato ISO 8601 **con zona** (p. ej. `Z` o `+01:00`). El frontend debe tratarlos como **instante** (p. ej. `new Date(value)` en JS) y formatear en la zona local del usuario para la UI.

| Recurso / contexto | Campo(s) |
|--------------------|----------|
| **Punches (fichajes)** | `timestamp` (listado, detalle, dashboard, calendario, estadísticas, respuesta NFC, bulk). |
| **Employees** | `lastPunchEvent.timestamp` |
| **Productions** | `openedAt`, `closedAt`, `date` (solo fecha, ver abajo) |
| **ProductionRecords** | `startedAt`, `finishedAt` |
| **Incidents** | `resolvedAt`, `createdAt`, `updatedAt` (en `toArrayAssoc`) |
| **Users** | `created_at`, `updated_at` (UserResource) |
| **Sessions** | `last_used_at`, `created_at`, `expires_at` |

No asumir que las fechas vienen en “hora local” o sin zona. Parsear siempre como instante y formatear a local para mostrar.

### 3.2 Campos solo fecha (YYYY-MM-DD)

Representan el **día en la zona de negocio** (Europe/Madrid). No incluyen hora; no hay desfase de zona.

| Recurso | Campo(s) |
|---------|----------|
| **Orders** | `entryDate`, `loadDate` |
| **RawMaterialReceptions** | `date` |
| **CeboDispatches** | `date` |
| **Production** | `date` (cuando se expone como fecha) |
| **ProductionCost** | `costDate` |

---

## 4. Listado de entidades y campos afectados

| Entidad | Campo API | Formato en respuesta |
|---------|-----------|----------------------|
| Punches | `timestamp` | ISO 8601 con zona |
| Employees | `lastPunchEvent.timestamp` | ISO 8601 con zona |
| Orders | `entryDate`, `loadDate` | Y-m-d (solo fecha) |
| Productions | `openedAt`, `closedAt` | ISO 8601 con zona |
| Productions | `date` | Y-m-d (solo fecha) |
| ProductionRecords | `startedAt`, `finishedAt` | ISO 8601 con zona |
| Incidents | `resolvedAt`, `createdAt`, `updatedAt` | ISO 8601 con zona |
| Users | `created_at`, `updated_at` | ISO 8601 con zona |
| Sessions | `last_used_at`, `created_at`, `expires_at` | ISO 8601 con zona |
| RawMaterialReceptions | `date` | Y-m-d (solo fecha) |
| CeboDispatches | `date` | Y-m-d (solo fecha) |
| ProductionCosts | `costDate` | Y-m-d (solo fecha) |

---

## 5. Cambios necesarios en el frontend

1. **Interpretar fechas datetime como instante**  
   Para cualquier campo que venga en ISO 8601 con zona, usar el tipo `Date` (o equivalente) y formatear en la zona local del usuario. No tratar el string como “hora local” ni asumir zona por defecto.

2. **Fichajes (manual, bulk, Excel)**  
   Seguir enviando `timestamp` en ISO 8601 con zona (recomendado UTC, p. ej. `toISOString()`). No enviar solo “YYYY-MM-DD HH:mm” sin zona.

3. **Filtros por fecha**  
   Parámetros como `loadDate.start`, `loadDate.end`, `entryDate.start`, `entryDate.end`, `date_start`, `date_end`, `date_from`, `date_to`, etc.: enviar en el formato que el backend ya acepta (p. ej. `YYYY-MM-DD` o ISO). El backend aplica la zona de negocio para los rangos.

4. **Compatibilidad con respuestas antiguas**  
   Si en algún momento el backend devolvía `Y-m-d H:i:s` sin zona para algunos datetime, ese formato **ya no se usa** para los campos listados en la tabla anterior. El frontend debe esperar ISO con zona para esos campos y dejar de depender de “hora local” implícita.

---

## 6. Referencias

- **Plan de implementación backend:** Unificación de timezone (UTC) — fases 1–7 en el plan de Cursor.
- **Archivos relevantes:**  
  - `app/Casts/DateTimeUtcCast.php`  
  - `app/Services/PunchEventWriteService.php`  
  - `config/app.php` (`business_timezone`)  
  - Resources v2 que exponen `timestamp`, `openedAt`, `closedAt`, `startedAt`, `finishedAt`, `resolvedAt`, etc.  
- **Nullable en Orders/Customers:** [72-nullables-orders-customers-frontend.md](72-nullables-orders-customers-frontend.md)  
- **Autoventa API:** [71-autoventa-api-frontend.md](71-autoventa-api-frontend.md)
