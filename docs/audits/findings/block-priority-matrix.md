# Matriz de Prioridad por Bloques — Auditoría 2026-03-24

Actualización con análisis de rendimiento bajo concurrencia y race conditions.

---

## Prioridad P0 — Riesgos activos de producción (intervenir antes del siguiente release)

| Bloque transversal | Problema | Impacto |
|---|---|---|
| **Infraestructura API** (todos los requests) | LogActivity: llamada HTTP externa síncrona en cada request | +50-500ms en TODOS los requests |
| **Infraestructura API** (todos los requests) | TenantMiddleware: sin cache del tenant lookup | +15ms en TODOS los requests |
| **Auth** | Magic Link / OTP: race condition TOCTOU en token consumption | Doble autenticación con un solo token |
| **Fichajes** | NFC Punch: race condition en determineEventType | Dobles fichajes del mismo tipo |
| **Todos los listados** | perPage sin límite máximo | Vector DoS, potencial OOM |

---

## Prioridad P1 — Deuda técnica significativa (próximo sprint)

| Bloque | Nota | Gaps principales |
|---|---|---|
| A.6 Producción | 8.5/10 | Solo 2 tests; N+1 en syncOutputs dentro de transacción; árbol sin tests de trazabilidad |
| A.22 Superadmin SaaS | 8/10 | Superficie operativa amplia; jobs con patrón tenant no formalizado |
| A.19 CRM comercial | 8.5/10 | CrmDashboard/CrmAgenda sin Policy; side effects comerciales no testeados |
| A.20 Canal operativo / Autoventa | 8.5/10 | Rutas/pedidos con sincronización compleja; cobertura de tests pendiente |
| A.21 Usuarios externos | 8.5/10 | Actor externo: onboarding y seguridad de acceso a revisar |
| A.4 Recepciones Materia Prima | 8.5/10 | Sin tests Feature; RawMaterialReceptionWriteService de 759 líneas; transacción en controller, no en service |
| A.5 Despachos de Cebo | 8.5/10 | Sin tests Feature |

---

## Prioridad P2 — Mejoras de calidad (planificación media)

| Bloque | Nota | Gaps |
|---|---|---|
| A.11 Fichajes | 9/10 | PunchController de 459 líneas; race condition NFC corregida en P0; exports sync |
| A.12 Estadísticas | 9/10 | Sin cache en endpoints de statistics; queries pesadas sin instrumento |
| A.15 Documentos PDF/Excel | 9/10 | Exports síncronos (corregir en P0 infraestructura); 7 libs PDF coexistiendo |
| A.2 Ventas | 9/10 | OrderListService.active() sin paginación; P95 de statistics endpoints |
| A.3 Inventario/Stock | 10/10 | Sin lockForUpdate explícito en moveToStore; constraint DB actúa de fallback |
| A.9 Proveedores | 9/10 | SupplierLiquidationService de 382 líneas; casos complejos de liquidación |

---

## Prioridad P3 — Mantenimiento (guardrails)

| Bloque | Nota | Acción |
|---|---|---|
| A.1 Auth + Roles | 9/10 | Mantener; fix P0 de magic link implementado |
| A.7 Productos | 9/10 | Mantener cobertura de tests existente |
| A.8 Catálogos | 9/10 | Prevenir regresiones en opciones de selects |
| A.10 Etiquetas | 9/10 | Mantener |
| A.13 Configuración (Settings) | 9/10 | Cache de settings pendiente (P1 indirecto) |
| A.14 Sistema | 9/10 | Comparte A.1; mantener |
| A.16 Tenants públicos | 9/10 | Simple y seguro; mantener |
| A.17 Infraestructura API | 9/10 | Mejoras P0 impactan aquí positivamente |
| A.18 Utilidades PDF/IA | 9/10 | Mantener |

---

## Resumen: Nota por Bloque (post-auditoría de concurrencia)

| Bloque | Nota anterior | Nota actual | Delta | Motivo |
|---|---|---|---|---|
| A.1 Auth | 9/10 | **8.5/10** | -0.5 | Race condition TOCTOU en magic link |
| A.2 Ventas | 9/10 | **8.5/10** | -0.5 | active() sin paginar; statistics sin cache |
| A.3 Inventario | 10/10 | **9.5/10** | -0.5 | Sin lockForUpdate explícito en moves |
| A.4 Recepciones | 9/10 | **8/10** | -1 | Sin tests; transacción en controller; service de 759L |
| A.5 Despachos | 9/10 | **8.5/10** | -0.5 | Sin tests Feature |
| A.6 Producción | 9/10 | **8/10** | -1 | 2 tests; N+1 en syncOutputs; árbol sin cobertura |
| A.7 Productos | 9/10 | 9/10 | = | Estable |
| A.8 Catálogos | 9/10 | 9/10 | = | Estable |
| A.9 Proveedores | 9/10 | 8.5/10 | -0.5 | Service grande; liquidación compleja |
| A.10 Etiquetas | 9/10 | 9/10 | = | Estable |
| A.11 Fichajes | 9/10 | **8/10** | -1 | Race condition NFC; PunchController 459L |
| A.12 Estadísticas | 9/10 | **8.5/10** | -0.5 | Stats sin cache; endpoints pesados |
| A.13 Settings | 9/10 | 9/10 | = | Estable |
| A.14 Sistema | 9/10 | **8.5/10** | -0.5 | Comparte gap de A.1 |
| A.15 Documentos | 9/10 | **8/10** | -1 | Exports síncronos; 7 libs PDF; QUEUE_CONNECTION=sync |
| A.16 Tenants públicos | 9/10 | 9/10 | = | Estable |
| A.17 Infraestructura API | 9/10 | **7/10** | -2 | LogActivity geoIP; TenantMiddleware sin cache; perPage |
| A.18 Utilidades PDF/IA | 9/10 | **8.5/10** | -0.5 | Librería múltiple; sync |
| A.19 CRM | 8.5/10 | 8.5/10 | = | Sin Policy; tests ok |
| A.20 Canal operativo | 8.5/10 | 8.5/10 | = | En revisión |
| A.21 Usuarios externos | 8.5/10 | 8.5/10 | = | En revisión |
| A.22 Superadmin SaaS | 8/10 | 8/10 | = | En seguimiento |

---

## Plan de Intervención Recomendado

### Sprint 1 (P0 — Urgente)
1. Cache geoIP en LogActivity + mover write a job async
2. Cache tenant lookup en TenantMiddleware + eliminar SET time_zone
3. Fix TOCTOU en magic link / OTP (lockForUpdate o UPDATE atómico)
4. Cap de perPage a 100 en todos los ListService
5. Fix race condition NFC Punch (determineEventType dentro de transacción con lock)

### Sprint 2 (P1 — Tests y servicios críticos)
1. Tests Feature para Producción (CRUD completo + syncOutputs + trazabilidad)
2. Tests Feature para RawMaterialReception y CeboDispatch
3. Policy para CrmDashboard, CrmAgenda y OrdersReport
4. Mover transacción de RMR del controller al service
5. Confirmar QUEUE_CONNECTION=redis en producción

### Sprint 3 (P2 — Calidad y rendimiento)
1. Cache de statistics endpoints con TTL 60s
2. Limitar OrderListService.active() o paginar
3. N+1 fix en syncOutputs (preload en lugar de find en loop)
4. Configurar PHP-FPM (pm.max_requests, slowlog)
5. Configurar MySQL (innodb_buffer_pool_size, slow_query_log)
6. Eliminar tymon/jwt-auth si no tiene uso activo
