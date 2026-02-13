---
title: Overview - Documentación PesquerApp Backend
description: Punto de entrada estándar a la documentación técnica del backend Laravel (API v2).
updated: 2026-02-13
audience: Backend Engineers, DevOps, Architects
---

# Overview — Documentación Técnica

**Propósito:** Índice único de la documentación del backend PesquerApp (Laravel, API v2, multi-tenant). Esta vista sigue la estructura estándar 00–15; el contenido detallado permanece en las carpetas por dominio (fundamentos, instrucciones, módulos, etc.).

**Audiencia:** Desarrolladores backend, DevOps, arquitectos.

---

## ⚠️ API v2 únicamente

- **API v1**: Eliminada (2025-01-27). No existe en el código.
- **API v2**: Única versión activa. Toda la documentación hace referencia a `/v2/*`.

---

## Documentación por número (estructura estándar)

| Número | Tema | Documento | Contenido equivalente existente |
|--------|------|-----------|----------------------------------|
| 01 | Setup local | [01-SETUP-LOCAL.md](./01-SETUP-LOCAL.md) | [instrucciones/](./21-instrucciones/), [fundamentos/03-Configuracion-Entorno](./20-fundamentos/03-Configuracion-Entorno.md) |
| 02 | Variables de entorno | [02-ENVIRONMENT-VARIABLES.md](./02-ENVIRONMENT-VARIABLES.md) | [fundamentos/03-Configuracion-Entorno](./20-fundamentos/03-Configuracion-Entorno.md) |
| 03 | Arquitectura | [03-ARCHITECTURE.md](./03-ARCHITECTURE.md) | [fundamentos/00-Introduccion](./20-fundamentos/00-Introduccion.md), [01-Arquitectura-Multi-Tenant](./20-fundamentos/01-Arquitectura-Multi-Tenant.md) |
| 04 | Base de datos | [04-DATABASE.md](./04-DATABASE.md) | [referencia/95-Modelos-Referencia](./30-referencia/95-Modelos-Referencia.md), migraciones |
| 05 | Colas y jobs | [05-QUEUES-JOBS.md](./05-QUEUES-JOBS.md) | Por completar |
| 06 | Scheduler / Cron | [06-SCHEDULER-CRON.md](./06-SCHEDULER-CRON.md) | Por completar |
| 07 | Storage y archivos | [07-STORAGE-FILES.md](./07-STORAGE-FILES.md) | [utilidades/](./29-utilidades/) |
| 08 | API REST | [08-API-REST.md](./08-API-REST.md) | [API-references/](./31-api-references/), [referencia/97-Rutas-Completas](./30-referencia/97-Rutas-Completas.md) |
| 09 | Testing | [09-TESTING.md](./09-TESTING.md) | Por completar |
| 10 | Observabilidad | [10-OBSERVABILITY-MONITORING.md](./10-OBSERVABILITY-MONITORING.md) | Por completar |
| 11 | Deployment | [11-DEPLOYMENT/](./11-DEPLOYMENT/) | [instrucciones/](./21-instrucciones/) |
| 12 | Troubleshooting | [12-TROUBLESHOOTING/](./12-TROUBLESHOOTING/) | [referencia/98-Errores-Comunes](./30-referencia/98-Errores-Comunes.md), [PROBLEMAS-CRITICOS](./PROBLEMAS-CRITICOS.md) |
| 13 | Postmortems | [13-POSTMORTEMS/](./13-POSTMORTEMS/) | Carpeta para informes de incidentes (por documentar) |
| 14 | Architecture Decisions | [14-ARCHITECTURE-DECISIONS/](./14-ARCHITECTURE-DECISIONS/) | ADR (por documentar) |
| 15 | Multi-tenant | [15-MULTI-TENANT-SPECIFICS.md](./15-MULTI-TENANT-SPECIFICS.md) | [fundamentos/01-Arquitectura-Multi-Tenant](./20-fundamentos/01-Arquitectura-Multi-Tenant.md) |

---

## Documentación por dominio (carpetas 20–35)

Las carpetas de contenido tienen prefijo numérico para un orden claro al listar `docs/`:

- **[20-fundamentos](./20-fundamentos/)** — Introducción, arquitectura multi-tenant, autenticación, configuración.
- **[Instrucciones](./21-instrucciones/)** — Deploy con Docker Sail, WSL, validación.
- **[Frontend](./33-frontend/)** — Guías de integración (auth, roles, email).
- **[API References](./31-api-references/)** — Referencia por módulo (auth, catálogos, pedidos, inventario, producción, etc.).
- **[Producción](./25-produccion/)** — Módulo producción (general, lotes, procesos, entradas/salidas, frontend, análisis, cambios).
- **[Pedidos](./22-pedidos/)** — Pedidos, detalles, documentos, incidentes, estadísticas.
- **[Inventario](./23-inventario/)** — Almacenes, palets, cajas, estadísticas.
- **[Catálogos](./24-catalogos/)** — Productos, categorías, especies, clientes, proveedores, etc.
- **[Recepciones y Despachos](./26-recepciones-despachos/)** — Recepciones MP, despachos cebo, liquidación.
- **[Etiquetas](./27-etiquetas/)** — Sistema de etiquetas.
- **[Sistema](./28-sistema/)** — Usuarios, roles, sesiones, logs, configuración, control horario, auth.
- **[Utilidades](./29-utilidades/)** — PDF, Excel, extracción IA.
- **[Referencia](./30-referencia/)** — Modelos, recursos API, rutas, errores, glosario.
- **[Ejemplos](./32-ejemplos/)** — Ejemplos de respuestas JSON.
- **[34-por-hacer](./34-por-hacer/)** — Backlog y tareas pendientes.
- **[35-prompts](./35-prompts/)** — Prompts para agentes IA (auditoría, seeders).

---

## Problemas críticos

**[PROBLEMAS-CRITICOS.md](./PROBLEMAS-CRITICOS.md)** — Resumen ejecutivo de los 25 problemas más críticos.  
Detalle ampliado: [referencia/98-Errores-Comunes.md](./30-referencia/98-Errores-Comunes.md).

---

## Auditoría de documentación

- [CURRENT_STATE_SNAPSHOT.md](./CURRENT_STATE_SNAPSHOT.md) — Snapshot estado actual (FASE 0).
- [INVENTORY.md](./INVENTORY.md) — Inventario (FASE 1).
- [CLASSIFICATION_MATRIX.md](./CLASSIFICATION_MATRIX.md) — Matriz de clasificación y hallazgos (FASE 2).
- [DOCUMENTATION_MAPPING_MATRIX.md](./DOCUMENTATION_MAPPING_MATRIX.md) — Mapeo docs → estructura objetivo (FASE 3).
- [DOCUMENTATION_ORPHANS_AND_CATEGORIES.md](./DOCUMENTATION_ORPHANS_AND_CATEGORIES.md) — Huérfanos y nuevas categorías (FASE 4).
- [DOCUMENTATION_RESTRUCTURING_CHECKLIST.md](./DOCUMENTATION_RESTRUCTURING_CHECKLIST.md) — Plan de reestructuración (FASE 5).
- [DOCUMENTATION_AUDIT_REPORT.md](./DOCUMENTATION_AUDIT_REPORT.md) — Informe de auditoría (FASE 7).

---

## Véase también

- [README.md](./README.md) — Índice detallado por carpetas (misma información, vista por dominio).
- **Agentes IA (Cursor)** — Sistema de memoria de trabajo: **`.ai_standards/`** en la raíz del proyecto.
