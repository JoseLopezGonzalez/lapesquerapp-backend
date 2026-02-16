---
title: Overview - Documentación PesquerApp Backend
description: Punto de entrada estándar a la documentación técnica del backend Laravel (API v2).
updated: 2026-02-13
audience: Backend Engineers, DevOps, Architects
---

# Overview — Documentación Técnica

**Propósito:** Índice único de la documentación del backend PesquerApp (Laravel, API v2, multi-tenant). Esta vista sigue la estructura estándar 00–15; el contenido detallado permanece en las carpetas por dominio (fundamentos, instrucciones, pedidos, inventario, etc.).

**Audiencia:** Desarrolladores backend, DevOps, arquitectos.

---

## ⚠️ API v2 únicamente

- **API v1**: Eliminada (2025-01-27). No existe en el código.
- **API v2**: Única versión activa. Toda la documentación hace referencia a `/v2/*`.

---

## Documentación por número (estructura estándar)

| Número | Tema | Documento | Contenido equivalente existente |
|--------|------|-----------|----------------------------------|
| 01 | Setup local | [01-setup-local.md](./01-setup-local.md) | [instrucciones/](./instrucciones/), [fundamentos/03-Configuracion-Entorno](./fundamentos/03-Configuracion-Entorno.md) |
| 02 | Variables de entorno | [02-environment-variables.md](./02-environment-variables.md) | [fundamentos/03-Configuracion-Entorno](./fundamentos/03-Configuracion-Entorno.md) |
| 03 | Arquitectura | [03-architecture-overview.md](./03-architecture-overview.md) | [fundamentos/00-Introduccion](./fundamentos/00-Introduccion.md), [01-Arquitectura-Multi-Tenant](./fundamentos/01-Arquitectura-Multi-Tenant.md) |
| 04 | Base de datos | [04-database-overview.md](./04-database-overview.md) | [referencia/95-Modelos-Referencia](./referencia/95-Modelos-Referencia.md), migraciones |
| 05 | Colas y jobs | [05-queues-jobs.md](./05-queues-jobs.md) | Por completar |
| 06 | Scheduler / Cron | [06-scheduler-cron.md](./06-scheduler-cron.md) | Por completar |
| 07 | Storage y archivos | [07-storage-files.md](./07-storage-files.md) | [utilidades/](./utilidades/) |
| 08 | API REST | [08-api-rest-overview.md](./08-api-rest-overview.md) | [api-references/](./api-references/), [referencia/97-Rutas-Completas](./referencia/97-Rutas-Completas.md) |
| 09 | Testing | [09-testing.md](./09-testing.md) | Por completar |
| 10 | Observabilidad | [10-observability-monitoring.md](./10-observability-monitoring.md) | Por completar |
| 11 | Deployment | [deployment/](./deployment/) | [instrucciones/](./instrucciones/) |
| 12 | Troubleshooting | [troubleshooting/](./troubleshooting/) | [referencia/98-Errores-Comunes](./referencia/98-Errores-Comunes.md), [PROBLEMAS-CRITICOS](./audits/PROBLEMAS-CRITICOS.md) |
| 13 | Postmortems | [postmortems/](./postmortems/) | Carpeta para informes de incidentes (por documentar) |
| 14 | Architecture Decisions | [architecture-decisions/](./architecture-decisions/) | ADR (por documentar) |
| 15 | Multi-tenant | [15-multi-tenant-specs.md](./15-multi-tenant-specs.md) | [fundamentos/01-Arquitectura-Multi-Tenant](./fundamentos/01-Arquitectura-Multi-Tenant.md) |

---

## Documentación por dominio

Carpetas por tema (minúsculas, sin numeración):

- **[fundamentos](./fundamentos/)** — Introducción, arquitectura multi-tenant, autenticación, configuración.
- **[instrucciones](./instrucciones/)** — Deploy con Docker Sail, WSL, validación.
- **[frontend](./frontend/)** — Guías de integración (auth, roles, email).
- **[api-references](./api-references/)** — Referencia por módulo (auth, catálogos, pedidos, inventario, producción, etc.).
- **[producción](./produccion/)** — Módulo producción (general, lotes, procesos, entradas/salidas, frontend, análisis, cambios).
- **[pedidos](./pedidos/)** — Pedidos, detalles, documentos, incidentes, estadísticas.
- **[inventario](./inventario/)** — Almacenes, palets, cajas, estadísticas.
- **[catalogos](./catalogos/)** — Productos, categorías, especies, clientes, proveedores, etc.
- **[recepciones-despachos](./recepciones-despachos/)** — Recepciones MP, despachos cebo, liquidación.
- **[etiquetas](./etiquetas/)** — Sistema de etiquetas.
- **[sistema](./sistema/)** — Usuarios, roles, sesiones, logs, configuración, control horario, auth.
- **[utilidades](./utilidades/)** — PDF, Excel, extracción IA.
- **[referencia](./referencia/)** — Modelos, recursos API, rutas, errores, glosario.
- **[ejemplos](./ejemplos/)** — Ejemplos de respuestas JSON.
- **[por-hacer](./por-hacer/)** — Backlog y tareas pendientes.
- **[prompts](./prompts/)** — Prompts para agentes IA (auditoría, seeders).

---

## Problemas críticos

**[PROBLEMAS-CRITICOS.md](./audits/PROBLEMAS-CRITICOS.md)** — Resumen ejecutivo de los 25 problemas más críticos.  
Detalle ampliado: [referencia/98-Errores-Comunes.md](./referencia/98-Errores-Comunes.md).

---

## Auditoría de documentación

Artefactos de auditoría previa (2026-02-13) y actual: [audits/documentation/](./audits/documentation/) (incl. 2026-02-13/, AUDIT_REPORT.md, MANIFEST.md, REORGANIZATION_PLAN.md).

---

## Véase también

- [00-docs-index.md](./00-docs-index.md) — Índice detallado por carpetas (vista por dominio).
- **Agentes IA (Cursor)** — Sistema de memoria de trabajo: **`.ai_standards/`** en la raíz del proyecto.
