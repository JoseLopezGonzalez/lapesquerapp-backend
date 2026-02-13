# Matriz de Mapeo: Documentos Existentes → Estructura Objetivo

**FASE 3 — Auditoría de documentación PesquerApp Backend**  
**Fecha:** 2026-02-13

**Estructura objetivo de referencia:** Sección 4 del [PESQUERAPP_DOCUMENTATION_AUDIT_PROMPT.md](./35-prompts/PESQUERAPP_DOCUMENTATION_AUDIT_PROMPT.md) (RAÍZ + docs/00–16).

---

## Resumen

- Los documentos **ya en la estructura canónica** (00–15, 11-DEPLOYMENT, 12-TROUBLESHOOTING, 13-POSTMORTEMS, 14-ARCHITECTURE-DECISIONS) se **mantienen**; muchos actúan como *hubs* que enlazan al contenido detallado en carpetas por dominio.
- Las **carpetas por dominio** (fundamentos, instrucciones, API-references, produccion, pedidos, inventario, catalogos, recepciones-despachos, sistema, utilidades, referencia, ejemplos, frontend, etiquetas, por-hacer) se **mantienen** como documentación detallada; no se mueve masivamente contenido a 01–15 para no duplicar ni romper enlaces.
- **Acciones concretas** se limitan a: integrar 00_ POR IMPLEMENTAR, revisar candidatos a deprecación (v1), y opcionalmente crear 16-OPERATIONS si se aporta contenido.

---

## Documentos ya en estructura objetivo → MANTENER

| Documento actual | Ubicación actual | Mapeo propuesto | Acción | Justificación | Riesgo |
|------------------|------------------|-----------------|--------|---------------|--------|
| README.md | raíz | README.md | MANTENER | Índice principal | — |
| CHANGELOG.md | raíz | CHANGELOG.md | MANTENER | Historial cambios | — |
| ROADMAP.md | raíz | ROADMAP.md | MANTENER | Hoja de ruta | — |
| TECH_DEBT.md | raíz | TECH_DEBT.md | MANTENER | Deuda técnica | — |
| SECURITY.md | raíz | SECURITY.md | MANTENER | Seguridad | — |
| docs/00-OVERVIEW.md | docs/ | docs/00-OVERVIEW.md | MANTENER | Mapa navegación | — |
| docs/01-SETUP-LOCAL.md … 15-MULTI-TENANT-SPECIFICS.md | docs/ | docs/01–15 | MANTENER | Hubs + contenido breve | — |
| docs/11-DEPLOYMENT/ (11a–11e) | docs/ | docs/11-DEPLOYMENT/ | MANTENER | Despliegue estándar | — |
| docs/12-TROUBLESHOOTING/ (COMMON-ERRORS, etc.) | docs/ | docs/12-TROUBLESHOOTING/ | MANTENER | Troubleshooting | — |
| docs/13-POSTMORTEMS/ | docs/ | docs/13-POSTMORTEMS/ | MANTENER | Carpeta para postmortems | — |
| docs/14-ARCHITECTURE-DECISIONS/ | docs/ | docs/14-ARCHITECTURE-DECISIONS/ | MANTENER | ADR | — |

---

## Contenido por dominio → MANTENER (enlaces desde estructura estándar)

| Ubicación actual | Equivalente en estructura objetivo | Acción | Justificación |
|------------------|------------------------------------|--------|----------------|
| docs/20-fundamentos/ | 01-SETUP, 02-ENV, 03-ARCHITECTURE, 15-MULTI-TENANT (contenido) | MANTENER | 01, 02, 03, 15 enlazan aquí; no duplicar. |
| docs/21-instrucciones/ | 01-SETUP-LOCAL, 11-DEPLOYMENT (contenido) | MANTENER | 01-SETUP y 11a enlazan aquí. |
| docs/31-api-references/ | 08-API-REST (contenido) | MANTENER | 08-API-REST enlaza aquí. |
| docs/30-referencia/ | 04-DATABASE, 08-API-REST, 12-TROUBLESHOOTING (contenido) | MANTENER | 04, 08, 12 y 00-OVERVIEW enlazan aquí. |
| docs/25-produccion/, pedidos/, inventario/, catalogos/, recepciones-despachos/, sistema/, utilidades/, etiquetas/, frontend/ | Contenido de referencia por módulo | MANTENER | Documentación detallada; 00-OVERVIEW y README enlazan. |
| docs/32-ejemplos/ | 08-API-REST, producción (ejemplos) | MANTENER | Referencia de ejemplos JSON. |
| docs/34-por-hacer/ | Backlog / tareas | MANTENER | No forma parte del árbol 00–16; mantener como backlog. |

---

## Acciones propuestas (renombrar / mover / fusionar / deprecar)

| Documento actual | Ubicación actual | Mapeo propuesto | Acción | Justificación | Riesgo |
|------------------|------------------|-----------------|--------|---------------|--------|
| docs/00_ POR IMPLEMENTAR/guia-entorno-desarrollo-pesquerapp.md | docs/00_ POR IMPLEMENTAR/ | docs/01-SETUP-LOCAL.md (ampliar) o docs/21-instrucciones/ | FUSIONAR o MOVER | Evitar carpeta “por implementar”; integrar en setup o instrucciones. | MEDIO (enlaces) |
| docs/00_ POR IMPLEMENTAR/IMPORTANTE/resumen-problema-solucion-productos-variantes.md | docs/00_ POR IMPLEMENTAR/ | docs/24-catalogos/ o docs/34-por-hacer/ | MOVER | Contenido de productos/variantes; catalogos o backlog. | BAJO |
| docs/26-recepciones-despachos/67-Guia-Backend-v1-Recepcion-Lineas-Palet-Automatico.md | docs/26-recepciones-despachos/ | docs/13-POSTMORTEMS/ (si v1 obsoleto) o añadir banner | DEPRECAR / REVISAR | Mención API v1; si v1 no existe, archivar o marcar deprecado. | BAJO |
| database/migrations/companies/README.md | database/ | MANTENER (actualizar) | ACTUALIZAR | Contenido vigente; última mod. 2025-08. | BAJO |

---

## Análisis de huecos

| Hueco | Severidad | Observación |
|-------|-----------|-------------|
| **docs/16-OPERATIONS/** | Opcional | No existe. Estructura objetivo la prevé solo “si existe volumen”. No hay hoy BACKUP-RESTORE.md, DATABASE-MAINTENANCE.md, SCALING-PROCEDURES.md en el repo; si se crean más adelante, ubicarlos en 16-OPERATIONS/. |
| **docs/13-POSTMORTEMS/** | Medio | Carpeta existe con solo README; sin informes de incidentes. Mantener estructura para futuros postmortems. |
| **docs/14-ARCHITECTURE-DECISIONS/** | Alto | Carpeta existe con solo README; sin ADRs. Recomendación: añadir al menos un ADR índice o plantilla y migrar decisiones clave desde fundamentos/referencia. |
| **docs/09-TESTING.md, 10-OBSERVABILITY-MONITORING.md** | Medio | Contenido por completar; enlaces o placeholders ya presentes. |
| **Contenido detallado 11-DEPLOYMENT (11b STAGING, 11c PRODUCTION, 11d ROLLBACK, 11e RUNBOOK)** | Medio | 11a y 11c tienen algo de contenido; 11b, 11d, 11e pueden ampliarse desde docs/instrucciones y operaciones. |

---

## Resumen de acciones FASE 3

| Acción | Cantidad | Ejemplos |
|--------|----------|----------|
| MANTENER (ya en sitio) | ~35 (estructura 00–15, 11–14) | 00-OVERVIEW, 01–15, 11-DEPLOYMENT, 12-TROUBLESHOOTING |
| MANTENER (dominio) | ~165 | fundamentos, instrucciones, API-references, módulos, referencia, ejemplos |
| FUSIONAR / MOVER | 2 | 00_ POR IMPLEMENTAR → integrar en 01-SETUP o instrucciones |
| DEPRECAR / REVISAR | 1 | 67-Guia-Backend-v1 (si v1 obsoleto) |
| ACTUALIZAR (sin mover) | 1 | database/migrations/companies/README.md |
| Crear (opcional) | 16-OPERATIONS, ADRs, postmortems | Cuando exista contenido |

---

**Siguiente paso:** FASE 4 — Detección de huérfanos y nuevas categorías; FASE 5 — Plan de reestructuración (checklist ejecutable).
