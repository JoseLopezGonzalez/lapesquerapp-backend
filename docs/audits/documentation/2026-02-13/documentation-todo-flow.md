# Roadmap de Documentación

**FASE 6 — Auditoría de documentación PesquerApp Backend**  
**Fecha:** 2026-02-13

Este documento prioriza el trabajo pendiente de documentación tras la auditoría (FASES 1–5). Los ítems ya completados en FASE 5 se marcan como hechos; el resto son tareas abiertas con estimación orientativa.

---

## Por criticidad

### 🔴 CRÍTICOS (Semana 1)

- [x] **testing.md** — Estrategia de pruebas, PHPUnit, Sail, ejemplos. — **Completado FASE 5.**
- [x] **11c-PRODUCTION.md** — Procedimientos de despliegue, migraciones, monitoreo. — **Completado FASE 5.**
- [x] **SECURITY.md** (raíz) — Políticas de datos, auth, secretos, reporting de vulnerabilidades. — **Completado FASE 5.**
- [ ] **observability-monitoring.md** — Logs, métricas, health checks, procedimientos de monitoreo. — Estimado 2–3 h.
- [ ] **11e-RUNBOOK.md** — Runbook operativo: health checks, reinicio de servicios, respuesta a incidentes. — Estimado 2 h.

### 🟡 ALTOS (Semanas 2–3)

- [x] **queues-jobs.md** — Configuración colas, drivers, comandos. — **Completado FASE 5.**
- [x] **scheduler-cron.md** — Tareas programadas (Kernel.php), cron en producción. — **Completado FASE 5.**
- [ ] **multi-tenant-specs.md** — Añadir sección operativa: impacto en despliegue, operación y troubleshooting por tenant. — Estimado 1,5 h.
- [ ] **11b-STAGING.md** — Procedimientos de despliegue en staging cuando exista el entorno. — Estimado 1 h.
- [ ] **11d-ROLLBACK-PROCEDURES.md** — Pasos de rollback de aplicación y migraciones. — Estimado 1,5 h.
- [ ] **database.md** — Ampliar con estrategia operativa de migraciones (orden, central vs tenant, rollback). — Estimado 2 h.
- [ ] **Troubleshooting instalación** — Sección en setup-local o troubleshooting con errores típicos de Sail/entorno. — Estimado 1 h.

### 🟢 MEDIOS (Mes 1–2)

- [ ] **postmortems/** — Plantilla de postmortem y primer ejemplo cuando ocurra un incidente. — Estimado 1 h.
- [ ] **architecture-decisions/** — Plantilla ADR y primer ADR (p. ej. multi-tenant, auth sin contraseña). — Estimado 2 h.
- [ ] **architecture / fundamentos** — Diagrama de componentes o arquitectura (Mermaid o imagen). — Estimado 2 h.
- [ ] **ROADMAP.md** (raíz) — Completar con hitos y planificación real del producto/backend. — Estimado 1 h.
- [ ] **TECH_DEBT.md** (raíz) — Poblar con ítems priorizados desde PROBLEMAS-CRITICOS y 98-Errores. — Estimado 1,5 h.
- [ ] **CHANGELOG.md** (raíz) — Estructura y primeras entradas (formato Keep a Changelog). — Estimado 1 h.

### ⚪ BAJOS (Backlog)

- [ ] **database/migrations/companies/README.md** — Revisar vigencia y actualizar (última mod. 2025-08). — Estimado 0,5 h.
- [ ] **00_ POR IMPLEMENTAR** — Integrar guía entorno desarrollo en instrucciones o marcar como obsoleta. — Estimado 1 h.
- [ ] **Ejemplos en testing** — Añadir ejemplo de test de endpoint API v2 con tenant/auth. — Estimado 1 h.
- [ ] **Diagramas en troubleshooting** — Flujo de diagnóstico para errores comunes si aporta valor. — Estimado 1 h.

---

## Dependencias

- **11e-RUNBOOK** depende de tener definidos health checks y métricas → conviene completar **observability-monitoring** antes o en paralelo.
- **11c-PRODUCTION** ya referencia 11e y 10; al completar 10 y 11e, revisar que 11c enlace correctamente.
- **11d-ROLLBACK** debe ser coherente con 11c (pasos de deploy que se revierten).
- **database** (estrategia migraciones) debe alinearse con 11c (orden de ejecución en producción) y con `tenants:migrate`.
- **architecture-decisions**: el primer ADR puede basarse en contenido ya existente en fundamentos y multi-tenant-specs.

---

## Flujo recomendado

1. **Semana 1:** Cerrar **observability-monitoring** y **11e-RUNBOOK** (críticos pendientes). Validar con equipo que testing, 11c y SECURITY cubren las necesidades mínimas.
2. **Semanas 2–3:** Completar altos: **multi-tenant-specs** (sección operativa), **11b**, **11d**, **database** (operativo), troubleshooting de instalación.
3. **Mes 1–2:** Medios: plantillas **postmortems** y **14-ADR**, diagrama en **architecture**, **ROADMAP**, **TECH_DEBT**, **CHANGELOG** con contenido real.
4. **Backlog:** Ir cerrando bajos según prioridad del equipo (migraciones/companies, 00_ POR IMPLEMENTAR, ejemplos de tests, diagramas de troubleshooting).

---

## Resumen de estado post–FASE 5

| Prioridad | Total ítems | Completados | Pendientes |
|-----------|-------------|-------------|------------|
| 🔴 Críticos | 5 | 3 | 2 |
| 🟡 Altos | 7 | 2 | 5 |
| 🟢 Medios | 6 | 0 | 6 |
| ⚪ Bajos | 4 | 0 | 4 |

---

## Véase también

- [GAPS_ANALYSIS.md](./GAPS_ANALYSIS.md) — Análisis de brechas (FASE 4).
- [CLASSIFICATION_MATRIX.md](./CLASSIFICATION_MATRIX.md) — Clasificación de documentos.
- [overview.md](./overview.md) — Estructura estándar de documentación.

**Siguiente paso:** FASE 7 — Informe de auditoría de cierre (DOCUMENTATION_AUDIT_REPORT.md).
