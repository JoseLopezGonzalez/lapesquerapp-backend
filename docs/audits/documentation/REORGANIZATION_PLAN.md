# Plan de Reorganización de Documentación — PesquerApp Backend

**Fecha:** 2026-02-16
**Estado:** Propuesta (pendiente de aprobación)

---

## Principios

1. **No romper enlaces**: Cada movimiento incluye actualización de referencias
2. **Archivar, no eliminar**: Documentos históricos se mueven a `_archivo/`, no se borran
3. **Una fuente de verdad**: Eliminar duplicaciones, consolidar en documento único
4. **Progresivo**: Ejecutar por fases, validar en cada paso

---

## FASE 1 — Correcciones Inmediatas (P0)

### 1.1 Corregir versión en README.md

| Acción | Detalle |
|--------|---------|
| Archivo | `README.md` (raíz) |
| Cambio | Línea 22: "Laravel 11" → "Laravel 10" |
| Impacto | Ningún enlace afectado |

### 1.2 Corregir path en SECURITY.md

| Acción | Detalle |
|--------|---------|
| Archivo | `SECURITY.md` (raíz) |
| Cambio | `docs/fundamentos/` → `docs/20-fundamentos/` en todas las referencias |

### 1.3 Completar stubs operativos críticos

| Archivo | Acción |
|---------|--------|
| `docs/11-DEPLOYMENT/11d-ROLLBACK-PROCEDURES.md` | Poblar con procedimientos de rollback multi-tenant |
| `docs/11-DEPLOYMENT/11e-RUNBOOK.md` | Poblar con runbook operativo básico |

---

## FASE 2 — Consolidación de Duplicados (P1)

### 2.1 Consolidar 9 archivos CORS

**Crear:** `docs/21-instrucciones/CORS-GUIA-DEFINITIVA.md`

Contenido consolidado de:
- Sección 1: Diagnóstico (de CORS-DIAGNOSTICO-Y-OPCIONES.md + CORS-PRODUCCION-TROUBLESHOOTING.md)
- Sección 2: Solución Laravel (de CORS-SOLUCION-COMPLETA.md)
- Sección 3: Solución Apache (de APACHE-CORS-INSTRUCCIONES.md)
- Sección 4: Solución Coolify/Traefik (de CORS-COOLIFY-TRAEFIK-SOLUCION.md)
- Sección 5: Validación (de CORS-VALIDACION-Y-TROUBLESHOOTING.md)
- Apéndice: Análisis commit 8097331 (de CORS-ANALISIS-*.md)

**Mover a archivo:**
```
docs/21-instrucciones/_archivo-cors/
├── APACHE-CORS-INSTRUCCIONES.md
├── CORS-ANALISIS-8097331-PROFUNDO.md
├── CORS-ANALISIS-COMMIT-8097331.md
├── CORS-COOLIFY-TRAEFIK-SOLUCION.md
├── CORS-DIAGNOSTICO-Y-OPCIONES.md
├── CORS-PRODUCCION-TROUBLESHOOTING.md
├── CORS-SOLUCION-COMPLETA.md
├── CORS-VALIDACION-Y-TROUBLESHOOTING.md
└── CORS-proxy-Origin.md
```

### 2.2 Consolidar documentos de deploy desarrollo

**Mantener:** `docs/21-instrucciones/deploy-desarrollo-guiado.md` (más completo, 205 líneas)
**Archivar:** `docs/21-instrucciones/_archivo/deploy-desarrollo.md`

O bien fusionar ambos en un único `deploy-desarrollo.md` con secciones "primera vez" y "re-deploy".

### 2.3 Consolidar stubs raíz

| Archivo actual | Acción | Destino |
|----------------|--------|---------|
| CHANGELOG.md (7 líneas) | Poblar desde git tags o eliminar | Mantener si se puebla; eliminar si se usa `git log` |
| ROADMAP.md (7 líneas) | Fusionar con docs/34-por-hacer/ o eliminar | `docs/34-por-hacer/ROADMAP.md` |
| TECH_DEBT.md (7 líneas) | Fusionar con docs/PROBLEMAS-CRITICOS.md o eliminar | `docs/PROBLEMAS-CRITICOS.md` ya cubre esto |

---

## FASE 3 — Reorganización de Producción (P1)

### 3.1 Crear documento de estado actual

**Crear:** `docs/25-produccion/00-ESTADO-ACTUAL.md`

Resumen ejecutivo del módulo de producción con:
- Estructura actual de entidades (Production, ProductionRecord, inputs, outputs, consumptions)
- Estado del production tree (versión vigente: v5 con conciliación)
- Estado de costes y trazabilidad
- Referencias a documentos de referencia vigentes

### 3.2 Archivar propuestas y análisis implementados

**Mover a:** `docs/25-produccion/_archivo/`

| Archivo | Razón |
|---------|-------|
| PROPUESTA-Trazabilidad-Costes-Producciones.md | Propuesta ya implementada |
| INVESTIGACION-Salidas-y-Consumos.md | Investigación completada |
| REFACTORIZACION-PRODUCCIONES-V2.md | Refactorización completada |
| RESUMEN-Implementacion-Multiples.md | Resumen de implementación pasada |
| ANALISIS-ERRORES-IMPLEMENTACION-COSTES.md | Errores ya corregidos |

**Mover a:** `docs/25-produccion/analisis/_archivo/`

| Archivo | Razón |
|---------|-------|
| ACTUALIZACION-ESTRUCTURA-FINAL-v3.md | Versión anterior |
| CONFIRMACION-Estructura-Final.md | Decisión tomada |
| RESUMEN-Decision-Dos-Nodos.md | Decisión tomada |
| Todos los DISENO-*.md | Diseños ya implementados |
| Todos los IMPLEMENTACION-*.md | Implementaciones completadas |

**Mover a:** `docs/25-produccion/cambios/_archivo/`

| Archivo | Razón |
|---------|-------|
| FRONTEND-Cambios-Nodos-Venta-Stock-v2.md | Supersedido por v3 |
| CAMBIO-Nodo-Missing-a-Balance.md | Cambio completado |
| Todos los FIX-*.md | Fixes completados |

### 3.3 Mover documentos frontend a ubicación centralizada

**Considerar mover a `docs/33-frontend/produccion/`:**
- DOCUMENTACION-FRONTEND-Trazabilidad-Costes.md
- FRONTEND-Consumos-Outputs-Padre.md
- FRONTEND-Salidas-y-Consumos-Multiples.md
- docs/25-produccion/frontend/* (todo el subdirectorio)

*Alternativa: mantener en 25-produccion pero con referencia cruzada clara desde 33-frontend.*

---

## FASE 4 — Archivado de Documentación Deprecada (P1-P2)

### 4.1 Documentación API v1

**Crear:** `docs/_archivo/api-v1/`

| Origen | Destino |
|--------|---------|
| docs/26-recepciones-despachos/67-Guia-Backend-v1-*.md | docs/_archivo/api-v1/ |
| docs/26-recepciones-despachos/68-Analisis-Cambios-API-v1-*.md | docs/_archivo/api-v1/ |

### 4.2 Planes ya completados

**Crear:** `docs/_archivo/planes-completados/`

| Origen | Destino | Razón |
|--------|---------|-------|
| docs/30-referencia/PLAN-ELIMINACION-ARTICLE.md | _archivo/ | Plan completado (Article → Product) |
| docs/26-recepciones-despachos/62-Plan-Implementacion-*.md | _archivo/ | Plan completado |
| docs/28-sistema/87-Plan-Auth-Magic-Link-OTP.md | _archivo/ | Plan implementado |

### 4.3 Artefactos de auditoría previa

**Mover a:** `docs/audits/documentation/2026-02-13/`

| Origen |
|--------|
| docs/DOCUMENTATION_AUDIT_REPORT.md |
| docs/INVENTORY.md |
| docs/CLASSIFICATION_MATRIX.md |
| docs/CURRENT_STATE_SNAPSHOT.md |
| docs/DOCUMENTATION_MAPPING_MATRIX.md |
| docs/DOCUMENTATION_ORPHANS_AND_CATEGORIES.md |
| docs/DOCUMENTATION_RESTRUCTURING_CHECKLIST.md |
| docs/DOCUMENTATION_TODO_FLOW.md |
| docs/GAPS_ANALYSIS.md |
| docs/API_DOCUMENTATION_GUIDE.md |

---

## FASE 5 — Mejoras Estructurales (P2)

### 5.1 Renombrar archivos con numeración inconsistente

| Actual | Propuesto | Razón |
|--------|-----------|-------|
| docs/20-fundamentos/02-Convencion-Tenant-Jobs.md | docs/20-fundamentos/02b-Convencion-Tenant-Jobs.md | Dos archivos con prefijo 02- |
| docs/23-inventario/31-Palets-Estados-Fijos.md | docs/23-inventario/31b-Palets-Estados-Fijos.md | Dos archivos con prefijo 31- |
| docs/28-sistema/82-Roles-Pasos-2-y-3-Pendientes.md | docs/28-sistema/82b-Roles-Pasos-Pendientes.md | Dos archivos con prefijo 82- |

### 5.2 Mover documentos mal ubicados

| Archivo | Ubicación actual | Ubicación propuesta | Razón |
|---------|------------------|---------------------|-------|
| docs/28-sistema/86-Control-Horario-FRONTEND.md | 28-sistema | docs/33-frontend/Control-Horario-FRONTEND.md | Es documentación frontend |
| docs/00_ POR IMPLEMENTAR/README.md | docs/00_ | docs/por-implementar/ | Ya existe la carpeta destino |
| docs/PROBLEMAS-CRITICOS.md | docs/ raíz | docs/audits/PROBLEMAS-CRITICOS.md | Encaja mejor con auditorías |

### 5.3 Mover prompts de IA

| Origen | Destino | Razón |
|--------|---------|-------|
| docs/35-prompts/*.md | .agents/prompts/*.md | Separar prompts IA de documentación del proyecto |

*Alternativa: mantener en docs/35-prompts si se quiere que los prompts estén versionados junto al código.*

### 5.4 Política para `.ai_work_context/`

**Opciones:**
1. **Añadir a `.gitignore`**: Son archivos efímeros de sesiones de trabajo IA (67 archivos, 2.435 líneas)
2. **Mantener pero con limpieza periódica**: Borrar sesiones > 30 días
3. **Mantener como está**: Si se quiere historial de decisiones IA

**Recomendación:** Opción 1 (`.gitignore`) ya que los informes finales relevantes deberían integrarse en la documentación principal.

---

## FASE 6 — Documentos Nuevos a Crear (P2-P3)

| Documento | Ubicación | Prioridad | Descripción |
|-----------|-----------|-----------|-------------|
| CORS-GUIA-DEFINITIVA.md | docs/21-instrucciones/ | P1 | Consolidación de 9 documentos CORS |
| 00-ESTADO-ACTUAL.md | docs/25-produccion/ | P1 | Resumen estado actual del módulo producción |
| 11b-STAGING.md (contenido) | docs/11-DEPLOYMENT/ | P2 | Procedimientos staging |
| DEBUGGING-GUIDE.md (contenido) | docs/12-TROUBLESHOOTING/ | P3 | Guía de depuración |
| Postmortem template | docs/13-POSTMORTEMS/ | P3 | Plantilla para postmortems |

---

## Resumen de Impacto

| Métrica | Antes | Después (estimado) |
|---------|-------|---------------------|
| Total archivos en docs/ | 196 | ~170 (-26 archivados) |
| Archivos duplicados | 22+ | 0 |
| Archivos deprecados visibles | 6 | 0 (archivados) |
| Archivos stub vacíos | 8 | 3 (5 poblados) |
| Líneas en docs/ | 80.788 | ~75.000 (-5.800 archivadas) |
| Documentos CORS | 9 | 1 + 9 archivados |
| Calidad global | 7/10 | 8.5/10 (estimado) |

---

## Orden de Ejecución

```
FASE 1 (inmediato, ~30 min)
  └── 1.1 Corregir README.md
  └── 1.2 Corregir SECURITY.md
  └── 1.3 Poblar rollback + runbook

FASE 2 (esta semana, ~4h)
  └── 2.1 Consolidar CORS
  └── 2.2 Consolidar deploy
  └── 2.3 Decidir stubs raíz

FASE 3 (esta semana, ~3h)
  └── 3.1 Estado actual producción
  └── 3.2 Archivar propuestas
  └── 3.3 Evaluar frontend docs

FASE 4 (este mes, ~2h)
  └── 4.1 Archivar docs v1
  └── 4.2 Archivar planes completados
  └── 4.3 Mover artefactos auditoría

FASE 5 (este mes, ~2h)
  └── 5.1 Renombrar numeración
  └── 5.2 Reubicar docs
  └── 5.3 Evaluar prompts
  └── 5.4 Decidir .ai_work_context

FASE 6 (cuando convenga, ~6h)
  └── Crear documentos nuevos
```

---

**Generado:** 2026-02-16
