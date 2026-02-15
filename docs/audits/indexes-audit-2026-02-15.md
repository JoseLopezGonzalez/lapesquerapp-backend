# Revisión de índices — Auditoría 2026-02-15

**Fecha:** 2026-02-15  
**Contexto:** Auditoría global backend Laravel; optimización de listados filtrados.  
**Fuente:** `docs/audits/laravel-backend-global-audit.md` — Top 5 mejoras: revisar índices en orders, punch_events, production_*.

---

## 1. Índices existentes (antes de la revisión)

| Tabla | Índices relevantes | Migración |
|-------|--------------------|-----------|
| orders | `(status, load_date)` | 2026_02_07_add_orders_status_load_date_index |
| punch_events | `(employee_id, timestamp)` | create_punch_events_table |
| production_records | `production_id`, `parent_record_id` | create_production_records_table |
| pallets | `order_id` | ensure_indexes_for_orders_show_path |
| pallet_boxes | `(pallet_id, box_id)` | ensure_indexes_for_orders_show_path |
| boxes | `article_id` | ensure_indexes_for_orders_show_path |
| production_inputs | `box_id` | ensure_indexes_for_orders_show_path |
| order_planned_product_details | `order_id` | ensure_indexes_for_orders_show_path |
| incidents | `order_id` | ensure_indexes_for_orders_show_path |

---

## 2. Cambios aplicados

### 2.1 Nueva migración: `2026_02_15_160000_add_indexes_for_audit_tables.php`

| Tabla | Columna | Propósito |
|-------|---------|-----------|
| punch_events | timestamp | Queries por rango de fechas (dashboard, calendar, statistics). El índice compuesto (employee_id, timestamp) solo cubre queries con employee_id; filtros globales por fecha usan timestamp. |
| productions | date | Listados filtrados por fecha. |

---

## 3. Criterios de decisión

- **punch_events.timestamp:** PunchEventListService filtra por `date_start`, `date_end`, `dates.start`, `dates.end`. Queries sin `employee_id` (listado global por fechas) no aprovechan (employee_id, timestamp); se añade índice en `timestamp`.
- **productions.date:** ProductionController/ProductionService filtran por fecha; la columna `date` no tenía índice propio.

---

## 4. Índices no añadidos (motivo)

| Tabla | Columna | Motivo |
|-------|---------|--------|
| orders | (varios) | Ya existe (status, load_date); índice por id implícito en PK. |
| productions | species_id, capture_zone_id | Ya cubiertos por FKs. |
| production_records | production_id | Ya existe índice. |
