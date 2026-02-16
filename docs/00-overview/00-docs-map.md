# Mapa de Documentación — PesquerApp Backend

**Objetivo:** Vista única de toda la documentación del backend (API v2, Laravel, multi-tenant).

**Última actualización:** 2026-02-16

---

## Puntos de entrada

| Documento | Propósito |
|-----------|-----------|
| [../00-docs-index.md](../00-docs-index.md) | Índice por dominio (fundamentos, instrucciones, módulos) |
| [../00-overview.md](../00-overview.md) | Índice por número (estructura estándar 00–15) |
| [../README.md](../README.md) | Redirect al índice principal |

---

## Estructura estándar (00–15)

| NN | Tema | Documento |
|----|------|-----------|
| 01 | Setup local | [01-setup-local.md](../01-setup-local.md) |
| 02 | Variables de entorno | [02-environment-variables.md](../02-environment-variables.md) |
| 03 | Arquitectura | [03-architecture-overview.md](../03-architecture-overview.md) |
| 04 | Base de datos | [04-database-overview.md](../04-database-overview.md) |
| 05 | Colas y jobs | [05-queues-jobs.md](../05-queues-jobs.md) |
| 06 | Scheduler / Cron | [06-scheduler-cron.md](../06-scheduler-cron.md) |
| 07 | Storage y archivos | [07-storage-files.md](../07-storage-files.md) |
| 08 | API REST | [08-api-rest-overview.md](../08-api-rest-overview.md) |
| 09 | Testing | [09-testing.md](../09-testing.md) |
| 10 | Observabilidad | [10-observability-monitoring.md](../10-observability-monitoring.md) |
| 11 | Deployment | [deployment/](../deployment/) (11a–11e) |
| 12 | Troubleshooting | [troubleshooting/](../troubleshooting/) |
| 15 | Multi-tenant | [15-multi-tenant-specs.md](../15-multi-tenant-specs.md) |

---

## Carpetas por dominio

- **fundamentos/** — Arquitectura, auth, configuración
- **instrucciones/** — Deploy Sail, WSL, CORS, validación
- **deployment/** — Development, staging, production, runbook
- **api-references/** — Referencia API por módulo
- **produccion/**, **pedidos/**, **inventario/**, **catalogos/**
- **recepciones-despachos/**, **etiquetas/**, **sistema/**, **utilidades/**
- **referencia/** — Modelos, rutas, errores, glosario
- **audits/** — Auditorías, evolution log, problemas críticos
- **_archivo/** — Documentación archivada (API v1, planes completados)
- **_worklog/** — CHANGES.md, VERIFY.md, REMAINING_RENAMES.md

---

## Relacionado

- [CHANGES.md](../_worklog/CHANGES.md) — Log de cambios Fase 1
- [PROBLEMAS-CRITICOS.md](../audits/PROBLEMAS-CRITICOS.md) — Problemas prioritarios
