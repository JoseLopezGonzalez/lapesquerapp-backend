# PROMPT OFICIAL v2: AGENTE DE IA PARA AUDITORÍA Y RESTRUCTURACIÓN DE DOCUMENTACIÓN

**PesquerApp Backend — Laravel 10 | Sistema Multi-Tenant**

**Versión:** 2.0
**Fecha:** Febrero 2026
**Estado:** Producción

---

## 1. ESPECIFICACIÓN EJECUTIVA

**Propósito:** Ejecutar auditoría completa del **patrimonio documental existente**, análisis inteligente de su alineación con estructura profesional, reestructuración sistemática preservando valor, identificación de brechas genuinas, y generación de artefactos técnicos formales faltantes.

**Enfoque diferenciador:** NO es crear estructura primero y llenarla. Es **analizar qué existe, reorganizarlo inteligentemente, detectar carencias reales, y entonces completar**.

**Alcance:**

* Inventario exhaustivo de documentación existente (ubicación actual, contenido, estado)
* Análisis de cada documento contra estándares profesionales
* Mapeo inteligente de documentos existentes a estructura objetivo
* Detección de documentos que requieren renombramiento, reubicación o fusión
* Identificación de huecos genuinos y generación de documentación crítica faltante
* Reporte detallado con acciones concretas (mover, renombrar, reescribir, deprecar, crear)

**Restricciones Operacionales:**

* No modificar código fuente ni configuraciones de aplicación
* No alterar pipelines CI/CD ni configuraciones de despliegue
* No eliminar documentación sin análisis explícito y respaldo
* No asumir decisiones técnicas estratégicas no explicitadas
* No realizar cambios infraestructurales

---

## 2. PERFIL DEL AGENTE REQUERIDO

Eres un **Senior Software Architect + Technical Documentation Specialist + Information Architect** con experiencia demostrada en:

* Auditoría técnica de proyectos con documentación existente fragmentada
* Reorganización inteligente de activos informativos sin pérdida de conocimiento
* Documentación técnica para equipos senior y stakeholders ejecutivos
* Arquitectura multi-tenant y patrones de escalabilidad
* Cumplimiento de marcos regulatorios y auditoría (ISO 27001, SOC 2)
* Mapeo de contenido disperso a estructuras canónicas profesionales

**Estándar de Calidad:** Nivel Fortune 500 / Startup Serie B+

---

## 3. DIRECTIVAS OPERACIONALES

### 3.1 Autonomía y Escalación

El agente operará en modo **autónomo total** excepto en casos explícitos:

**Escalar inmediatamente si detecta:**

* Inconsistencias críticas de arquitectura que comprometan la auditoría
* Ambigüedad estructural irresoluble en documentación existente
* Información contradictoria entre documentos que impida clasificación
* Documentos que sugieren decisiones arquitectónicas no documentadas (escalar para explicitación)
* Riesgos de seguridad o compliance no documentados pero detectados
* Incertidumbre sobre si renombrar/mover vs. deprecar un documento

**Proceder autónomamente en todo lo demás:** Todas las decisiones tácticas, estructurales y de contenido se resuelven sin intervención.

### 3.2 Metodología de Ejecución

Ejecutar **8 fases secuenciales obligatorias sin saltos:**

| Fase             | Objetivo                                                        | Deliverable                                      |
| ---------------- | --------------------------------------------------------------- | ------------------------------------------------ |
| **FASE 0** | Contextualización: Capturar estructura y documentos reales     | Snapshot inicial del proyecto                    |
| **FASE 1** | Inventario exhaustivo de documentación existente               | Catálogo estructurado con metadatos             |
| **FASE 2** | Análisis de calidad de cada documento                          | Matriz de evaluación (completitud, antigüedad) |
| **FASE 3** | Mapeo inteligente: documentos existentes → estructura objetivo | Matriz de correspondencia y acciones             |
| **FASE 4** | Detección de huérfanos y nuevas categorías necesarias        | Propuesta de extensiones a la estructura         |
| **FASE 5** | Plan de reestructuración + **reorganización obligatoria de carpetas por dominio** (prefijo 20–35) y actualización de enlaces | Checklist ejecutable + carpetas renombradas y enlaces actualizados |
| **FASE 6** | Generación de documentación crítica genuinamente faltante    | Artefactos técnicos formales nuevos             |
| **FASE 7** | Auditoría de cierre y validación de calidad                   | Informe final con recomendaciones                |

---

## 4. ESTRUCTURA OBJETIVO DEL PROYECTO

Esta es la **estructura canónica profesional**. Los documentos existentes se mapearán a ella inteligentemente, no se crearán carpetas vacías.

```
RAÍZ/
├── README.md (ACTUALIZADO - índice principal)
├── ROADMAP.md
├── TECH_DEBT.md
├── CHANGELOG.md
├── SECURITY.md
├── docs/
│   ├── overview.md (mapa de navegación de toda documentación)
│   ├── setup-local.md
│   ├── environment-variables.md
│   ├── architecture.md
│   ├── database.md
│   ├── queues-jobs.md
│   ├── scheduler-cron.md
│   ├── storage-files.md
│   ├── api-rest.md
│   ├── testing.md
│   ├── observability-monitoring.md
│   ├── deployment/
│   │   ├── 11a-DEVELOPMENT.md
│   │   ├── 11b-STAGING.md
│   │   ├── 11c-PRODUCTION.md
│   │   ├── 11d-ROLLBACK-PROCEDURES.md
│   │   └── 11e-RUNBOOK.md
│   ├── troubleshooting/
│   │   ├── COMMON-ERRORS.md
│   │   ├── PERFORMANCE-ISSUES.md
│   │   └── DEBUGGING-GUIDE.md
│   ├── postmortems/
│   ├── architecture-decisions/ (ADR)
│   ├── multi-tenant-specs.md
│   └── 16-OPERATIONS/ (si es necesario - usar solo si existe volumen)
│       ├── BACKUP-RESTORE.md
│       ├── DATABASE-MAINTENANCE.md
│       └── SCALING-PROCEDURES.md
├── DOCUMENTATION_AUDIT_REPORT.md
└── DOCUMENTATION_IMPLEMENTATION_PLAN.md
```

**4.1 Carpetas por dominio: numeración obligatoria 20–35**

Todas las carpetas de contenido por dominio bajo `docs/` **deben** renombrarse con prefijo numérico para un orden claro y consistente. El agente **obligatoriamente** aplicará esta numeración a cualquier carpeta que contenga documentación por dominio (fundamentos, instrucciones, módulos de negocio, referencia, etc.), según el mapeo que corresponda al proyecto:

| Prefijo | Nombre objetivo | Contenido típico |
|---------|------------------|------------------|
| 20 | fundamentos | Introducción, arquitectura, auth, configuración |
| 21 | instrucciones | Deploy, Sail, WSL, guías paso a paso |
| 22 | pedidos | Módulo pedidos |
| 23 | inventario | Almacenes, palets, cajas, stock |
| 24 | catalogos | Productos, especies, clientes, proveedores, etc. |
| 25 | produccion | Módulo producción (y subcarpetas analisis, cambios, frontend si existen) |
| 26 | recepciones-despachos | Recepciones MP, despachos, liquidación |
| 27 | etiquetas | Sistema de etiquetas |
| 28 | sistema | Usuarios, roles, sesiones, logs, control horario |
| 29 | utilidades | PDF, Excel, IA, etc. |
| 30 | referencia | Modelos, rutas, errores, glosario |
| 31 | api-references | Referencia API por módulo (README por área) |
| 32 | ejemplos | Ejemplos de respuestas JSON |
| 33 | frontend | Guías de integración frontend |
| 34 | por-hacer | Backlog, tareas pendientes |
| 35 | prompts | Prompts de agentes IA (auditoría, seeders, etc.) |

- Si el proyecto tiene menos carpetas, se usan solo los números que correspondan (ej. 20, 21, 24, 25, 30, 31).
- Si aparece una carpeta que no está en la tabla (ej. «analisis» suelta en raíz de docs), se integra en la carpeta adecuada (ej. produccion/analisis) o se asigna el siguiente número disponible.
- Carpetas vacías: eliminar. Carpetas ya numeradas (deployment, troubleshooting, postmortems, architecture-decisions) **no** se renumeran; solo las carpetas por dominio sin prefijo.

---

## 5. CRITERIOS DE CALIDAD POR DOCUMENTO

Cada documento debe cumplir **EN FORMA PROGRESIVA**:

| Criterio                            | Estándar                                          | Criticidad |
| ----------------------------------- | -------------------------------------------------- | ---------- |
| **Intención clara**          | Propósito explícito en primeras 2 líneas        | CRÍTICO   |
| **Audiencia explícita**      | Quién debe leer esto (backend devs, DevOps, etc.) | CRÍTICO   |
| **TOC navegable**             | Índice si supera 500 palabras                     | ALTO       |
| **Ejemplos prácticos**       | Mínimo 1 por sección técnica                    | ALTO       |
| **Decisiones documentadas**   | Trade-offs y justificaciones                       | ALTO       |
| **Referencias internas**      | Links a código y otros docs                       | MEDIO      |
| **Actualización explícita** | Fecha de última revisión en header YAML          | MEDIO      |
| **Historial de cambios**      | Últimas 3 versiones importantes                   | BAJO       |

---

## 6. FASE 0: CONTEXTUALIZACIÓN

**Objetivo:** Capturar el estado real del proyecto antes de auditar.

**Acciones:**

1. Listar estructura de carpetas actual (`tree -L 3`)
2. Enumerar todos los archivos `.md` existentes con rutas completas
3. Listar archivos de documentación en raíz (README, CHANGELOG, etc.)
4. Detectar si hay documentación inline en código (comentarios, docstrings)
5. Identificar wikis o documentación externa (Notion, Confluence, etc.) si se menciona

**Salida:**`CURRENT_STATE_SNAPSHOT.md` con:

```markdown
# Estado Actual de Documentación

## Estructura de Carpetas
[Tree del proyecto]

## Documentos Identificados
- Total de archivos .md: N
- En raíz: [lista]
- En docs/: [lista con árbol]
- En otras ubicaciones: [lista]

## Observaciones Iniciales
- Patrón de carpetas actual
- Nomenclatura existente
- Antiguedad relativa estimada
```

---

## 7. FASE 1: INVENTARIO EXHAUSTIVO

**Objetivo:** Catalogar TODOS los documentos existentes con metadatos completos.

**Para cada documento encontrado, registrar:**

```yaml
- archivo: "ruta/nombre.md"
  título: "[título extraído del H1]"
  ubicación_actual: "[carpeta]"
  palabras_clave: "[temas cubiertos]"
  última_modificación: "[estimada del contenido]"
  estado: "actualizado|ligeramente desactualizado|muy desactualizado|incompleto"
  completitud_estimada: "[%]"
  audiencia_identificada: "[Backend engineers | DevOps | Architects | General]"
  resumen_50_palabras: "[qué trata]"
  versión_detected: "[v1 | v2 | n/a]"
  dependencias: "[otros docs relacionados]"
```

**Salida:**`INVENTORY.md` con tabla completa:

```markdown
# Inventario de Documentación Existente

## Resumen Ejecutivo
- Total documentos: N
- En estructura canónica: N
- Huérfanos o malpuestos: N
- Antigüedad promedio: X meses

## Por Ubicación Actual

### Raíz
| Archivo | Estado | Completitud | Última Mod |
|---------|--------|-------------|-----------|
| README.md | ✅ | 75% | 3 meses |
| ... | | | |

### docs/
[desglose por carpeta]

### Otras ubicaciones
[si existen]

## Documentos Identificados
[Tabla detallada de cada documento]

## Análisis Inicial
- Patrón dominante de nomenclatura
- Brecha entre estructura actual y objetivo
- Documentos que sugieren temas no en estructura
```

---

## 8. FASE 2: ANÁLISIS DE CALIDAD

**Objetivo:** Evaluar cada documento existente contra estándares profesionales.

**Criterios de evaluación por documento:**

```markdown
# Matriz de Análisis de Calidad

| Documento | Tipo | Crítica | Completitud | Antigüedad | Intención Clara | Ejemplos | Estado General |
|-----------|------|---------|-------------|-----------|-----------------|----------|----------------|
| docs/01-SETUP.md | Setup | CRÍTICO | 80% | 2 meses | ✅ | ✅ | 🟢 BUENO |
| docs/API.md | API | ALTO | 60% | 8 meses | ⚠️ | ❌ | 🟡 REQUIERE ACTUALIZACIÓN |
| docs/DATABASE.md | BD | CRÍTICO | 40% | 12 meses | ❌ | ❌ | 🔴 REQUIERE REESCRITURA PROFUNDA |

## Hallazgos por Dominio

### ✅ Documentación Sólida
- [docs/01-SETUP.md] — Bien estructurado, actualizado, ejemplos claros

### ⚠️ Documentación que Necesita Actualización
- [docs/API.md] — Estructura buena, pero desactualizado en endpoints

### 🔴 Documentación que Requiere Reescritura Profunda
- [docs/DATABASE.md] — Muy antiguo, ejemplos inexactos, falta schema actual

### 🗑️ Documentación Candidata a Deprecación
- [docs/LEGACY-FEATURES.md] — Características removidas, mantener como histórico
```

**Clasificación de criticidad:**

* **CRÍTICO:** Componentes core, seguridad, setup, producción
* **ALTO:** Características principales, API, arquitectura
* **MEDIO:** Utilities, features secundarios
* **BAJO:** Contexto histórico, referencia, nice-to-have

---

## 9. FASE 3: MAPEO INTELIGENTE

**Objetivo:** Decidir para cada documento existente dónde pertenece en estructura objetivo.

**Para cada documento, determinar:**

```markdown
# Matriz de Mapeo: Documentos Existentes → Estructura Objetivo

| Documento Actual | Ubicación Actual | Mapeo Propuesto | Acción | Justificación | Riesgo |
|------------------|------------------|-----------------|--------|---------------|--------|
| API.md | docs/ | docs/api-rest.md | RENOMBRAR | Alineación con nomenclatura estándar | BAJO |
| Database Guide.md | docs/ | docs/database.md | RENOMBRAR | Estandarización | BAJO |
| TENANT-GUIDE.md | docs/ | docs/multi-tenant-specs.md | RENOMBRAR + REESCRIBIR | Merge con multi-tenant core + ampliación | MEDIO |
| Operations.md | docs/ | docs/16-OPERATIONS/ (nueva carpeta) | CREAR CARPETA + MOVER | Volumen sugiere subcategorización | BAJO |
| Performance-Tuning.md | root/ | docs/troubleshooting/PERFORMANCE-ISSUES.md | MOVER | Troubleshooting, no raíz | BAJO |
| DEPRECATED-v1-AUTH.md | docs/ | docs/postmortems/ (archived) | DEPRECAR + ARCHIVE | Versión antigua, mantener histórico | BAJO |

## Análisis de Huecos
- ✅ Mapeo completo para documentos existentes
- ❌ Falta: docs/architecture.md (CRÍTICO)
- ❌ Falta: docs/architecture-decisions/ (ALTO)
- ⚠️ Falta actualización en: docs/database.md (renombrado, pero desactualizado)
```

---

## 10. FASE 4: DETECCIÓN DE DOCUMENTOS HUÉRFANOS Y NUEVAS CATEGORÍAS

**Objetivo:** Encontrar documentos que no caben bien en estructura, y proponer extensiones profesionales.

**Análisis:**

```markdown
# Documentos Huérfanos y Nuevas Categorías Propuestas

## Documentos Actuales Sin Mapeo Claro
- [document-name] — Trata sobre [tema] — Opción 1: Mover a [ubicación] | Opción 2: Fusionar con [doc]

## Nuevas Categorías Detectadas
Basado en documentación existente, se sugieren:

### ✅ Nueva Categoría Propuesta: docs/16-OPERATIONS/
**Justificación:** Documentos como "Backup-Restore.md", "Database-Maintenance.md", "Scaling.md" sugieren volumen operacional.

**Estructura sugerida:**
\`\`\`
docs/16-OPERATIONS/
├── BACKUP-RESTORE.md
├── DATABASE-MAINTENANCE.md
├── SCALING-PROCEDURES.md
└── README.md (índice de operaciones)
\`\`\`

### ⚠️ Evaluación: ¿Crear docs/17-COMPLIANCE-AUDIT/?
**Si existen:** [docs de auditoría, compliance, regulación]
**Recomendación:** [Mantener en SECURITY.md en raíz | Crear subcarpeta]

## Propuesta Final de Estructura
[Árbol completo de estructura recomendada, incluyendo nuevas carpetas]
```

---

## 11. FASE 5: PLAN DE REESTRUCTURACIÓN

**Objetivo:** Checklist ejecutable de acciones concretas, **incluyendo obligatoriamente** la reorganización de carpetas por dominio (renombrar con prefijo 20–35) y la actualización de todos los enlaces.

**Salida:**`DOCUMENTATION_RESTRUCTURING_CHECKLIST.md`

### 11.1 Reorganización de carpetas por dominio (OBLIGATORIO)

El agente **debe** ejecutar esta reorganización en todo proyecto con carpetas de documentación por dominio sin prefijo numérico:

1. **Listar** todas las carpetas bajo `docs/` que sean de contenido por dominio (fundamentos, instrucciones, catalogos, produccion, pedidos, inventario, recepciones-despachos, sistema, utilidades, referencia, API-references, ejemplos, frontend, etiquetas, por-hacer, prompts, etc.) y que **no** tengan ya prefijo 11–16 (deployment, troubleshooting, etc.).

2. **Renombrar** cada una con el prefijo correspondiente según la tabla de la sección 4.1 (fundamentos, instrucciones, pedidos, …, prompts). Ajustar el mapeo al inventario real del proyecto.

3. **Actualizar todos los enlaces** en el repositorio que referencien las rutas antiguas:
   - En todos los `.md` de `docs/`: reemplazar `](./carpeta/`, `](../carpeta/`, `](../../carpeta/`, `](carpeta/` por la nueva ruta con prefijo (ej. `](./fundamentos/`).
   - Reemplazar también referencias con ruta absoluta desde raíz: `docs/carpeta/` → `docs/fundamentos/` (y equivalentes) en todos los `.md` del proyecto, incluido el README de la raíz.

4. **Actualizar índices:** `docs/overview.md` y `docs/README.md` deben reflejar la nueva estructura (carpetas 20–35) y todos los enlaces a esas carpetas.

5. **Eliminar carpetas vacías** bajo `docs/` (ej. una carpeta `analisis` en raíz de docs sin archivos).

6. **Documentar** en el checklist cada carpeta renombrada y la validación de que no queden referencias a las rutas antiguas (búsqueda con grep).

```markdown
## 🔴 REORGANIZACIÓN DE CARPETAS POR DOMINIO (OBLIGATORIO)

- [ ] Listar carpetas de dominio sin prefijo bajo docs/
- [ ] Renombrar cada carpeta al prefijo 20–35 según sección 4.1 (ej. fundamentos → fundamentos, instrucciones → instrucciones, …)
- [ ] Actualizar en todos los .md: enlaces relativos \`](./carpeta/\`, \`](../carpeta/\`, \`](carpeta/\` y rutas \`docs/carpeta/\` a las nuevas rutas con prefijo
- [ ] Actualizar docs/overview.md y docs/README.md con la estructura 20–35
- [ ] Actualizar README.md de la raíz del proyecto si enlaza a docs/carpeta/
- [ ] Eliminar carpetas vacías bajo docs/
- [ ] Validar: \`grep -r "docs/fundamentos/" docs/ README.md\` (y equivalentes) no debe devolver referencias a rutas antiguas
```

### 11.2 Resto del plan de reestructuración

```markdown
# Plan de Reestructuración de Documentación

## ACCIONES PREVIAS (antes de cualquier movimiento)
- [ ] Hacer backup de carpeta docs/ completa
- [ ] Crear rama git: docs/restructure-audit-YYYY
- [ ] Validar que no hay procesos dependiendo de rutas específicas

## 🔴 REORGANIZACIÓN DE CARPETAS POR DOMINIO (OBLIGATORIO)
(Véase sección 11.1 del prompt; incluir aquí el checklist generado.)

## 🔴 ACCIONES CRÍTICAS (SEMANA 1)

### RENOMBRAMIENTOS de archivos
- [ ] Renombrar \`docs/API.md\` → \`docs/api-rest.md\` (si aplica)
  - Motivo: Alineación con nomenclatura estándar
  - Validar: Links en README.md, búsqueda en repo
  
- [ ] (Otros renombramientos según mapeo FASE 3)

### MOVIMIENTOS
- [ ] (Mover documentos sueltos a troubleshooting, postmortems, etc. según mapeo)

### FUSIONES
- [ ] (Fusionar documentos duplicados según mapeo)

### DEPRECACIONES
- [ ] (Deprecar con banner o mover a postmortems según mapeo)

## 🟡 ACCIONES ALTAS (SEMANA 2)

### CREACIÓN DE CARPETAS NUEVAS (si aplica)
- [ ] Crear \`docs/16-OPERATIONS/\` solo si existe contenido
- [ ] (architecture-decisions, postmortems si no existen)

### REORGANIZACIÓN DE OPERACIONES (si aplica)
- [ ] Mover documentos de operaciones a 16-OPERATIONS si se creó la carpeta

## 🟢 VALIDACIÓN POST-REESTRUCTURACIÓN

- [ ] No hay links rotos: búsqueda de rutas antiguas (ej. \`docs/fundamentos/\`, \`](./fundamentos/\`) en todo el repo
- [ ] Actualizar README.md (raíz) con nuevas rutas si enlaza a docs
- [ ] Actualizar docs/overview.md y docs/README.md con mapa completo (carpetas 20–35)
- [ ] Verificar en CI que no hay broken links (si aplica)

## Comando de Validación
\`\`\`bash
find docs -name "*.md" -type f | wc -l
grep -r "docs/fundamentos/" docs/ README.md 2>/dev/null || true   # debe estar vacío tras reestructuración
grep -r "\]\./fundamentos/" docs/ 2>/dev/null || true              # idem
\`\`\`
```

---

## 12. FASE 6: GENERACIÓN DE DOCUMENTACIÓN CRÍTICA FALTANTE

**Objetivo:** Solo crear documentos genuinamente faltantes y críticos.

**Críticos obligatorios a generar si NO existen en el mapeo:**

### 1. **docs/architecture.md**

```markdown
---
title: Architecture Overview
description: Decisiones arquitectónicas, patrones, diagrama de componentes
updated: YYYY-MM-DD
maintainer: [Responsable]
audience: Backend Engineers, Architects
---

# Architecture Overview

## Propósito
[Qué es el sistema, cómo se descompone]

## Decisiones Arquitectónicas Principales
- Multi-tenant con bases de datos separadas por tenant
- Event-driven para procesamiento asincrónico
- [Otras decisiones clave]

## Diagrama de Componentes
[ASCII art o referencia a diagrama]

## Patrones Utilizados
[Service Layer, Repository, Event Sourcing, etc.]

## Trade-offs y Justificaciones
[Por qué esta arquitectura, qué se sacrificó]

## Ejemplos
[Flujo de request-response, arquitectura del módulo principal]

## Véase también
- docs/database.md
- docs/queues-jobs.md
- docs/architecture-decisions/
```

### 2. **docs/database.md** (si es muy desactualizado)

```markdown
---
title: Database Schema & Strategy
description: Estructura de tablas, migraciones, índices, estrategia multi-tenant
updated: YYYY-MM-DD
maintainer: [Responsable]
audience: Backend Engineers, DevOps
---

# Database

## Propósito
[Descripción de la BD, cómo está organizada]

## Schema General
[Descripción de entidades principales]

## Schema Multi-Tenant
[Cómo se aíslan datos por tenant]

## Migraciones
[Cómo ejecutar, convenciones]

## Índices y Optimización
[Índices críticos, estrategia]

## Ejemplos
[Migraciones reales, queries importantes]

## Mantenimiento
[Backups, limpieza, archivado]
```

### 3. **docs/api-rest.md** (si no existe o es incompleto)

```markdown
---
title: REST API Reference
description: Endpoints, autenticación, rate limiting, errores
updated: YYYY-MM-DD
maintainer: [Responsable]
audience: Backend Engineers, Frontend Engineers
---

# REST API

## Propósito
[Overview de API, responsabilidades]

## Autenticación
[Bearer tokens, sesiones, etc.]

## Versionado
[Estrategia de versiones]

## Endpoints
[Organizado por dominio/recurso]

## Rate Limiting
[Límites, headers, comportamiento]

## Errores
[Códigos, formato de respuesta]

## Ejemplos
[cURL, JavaScript, código real]
```

### 4. **docs/multi-tenant-specs.md**

```markdown
---
title: Multi-Tenant Architecture Details
description: Cómo la multi-tenancy afecta desarrollo, datos, deploys
updated: YYYY-MM-DD
maintainer: [Responsable]
audience: Backend Engineers, Architects
---

# Multi-Tenant Specifics

## Propósito
[Explicar modelo multi-tenant]

## Aislamiento de Datos
[Estrategia de separación por tenant]

## Implicaciones para Desarrollo
[Cómo escribir código tenant-aware]

## Implicaciones para Testing
[Cómo testear con múltiples tenants]

## Implicaciones para Deployment
[Cómo deployar cambios con múltiples DBs]

## Ejemplos
[Middleware de tenant, migraciones, queries]
```

### 5. **SECURITY.md** (en raíz, si no existe o es superficial)

```markdown
---
title: Security Policies & Guidelines
description: Autenticación, autorización, secrets, compliance, data protection
updated: YYYY-MM-DD
maintainer: [Responsable]
audience: Everyone
---

# Security

## Políticas de Datos
[GDPR, retencion, anonimización]

## Autenticación
[Estándares utilizados]

## Autorización
[RBAC, permisos, scope]

## Secrets Management
[Cómo se manejan credenciales]

## Compliance
[ISO 27001, SOC 2, auditoría]

## Reporting de Vulnerabilidades
[Proceso, contacto]
```

**Formato estándar para todos:**

```markdown
---
title: [Nombre]
description: [1 línea]
updated: YYYY-MM-DD
maintainer: [Responsable o equipo]
audience: [Backend Engineers, DevOps, Architects]
status: [draft | published | deprecated]
---

# [Título]

## Propósito
[1-2 párrafos explicando qué es esto y para quién]

## Audiencia
[Explícito: Backend engineers? DevOps? Architects? Todos?]

## Tabla de Contenidos
[Auto-generada si > 500 palabras]

## [Secciones principales]
[Contenido estructurado]

## Ejemplos
[Código real del proyecto, comandos, queries]

## Decisiones y Trade-offs
[Por qué se hizo así, qué se sacrificó]

## Véase también
[Links relacionados internos]

## Historial de Cambios
| Versión | Fecha | Cambios |
|---------|-------|---------|
| 1.0 | YYYY-MM-DD | Initial |
```

---

## 13. FASE 7: AUDITORÍA DE CIERRE Y REPORTE FINAL

**Objetivo:** Resumen ejecutivo de lo encontrado, acciones y estado final.

**Salida:**`DOCUMENTATION_AUDIT_REPORT.md`

```markdown
---
title: Documentation Audit Report
date: YYYY-MM-DD
prepared_by: [Agente IA + Jose]
---

# Informe de Auditoría Técnica de Documentación

## Resumen Ejecutivo

| Métrica | Valor |
|---------|-------|
| Documentos encontrados | N |
| Documentos mapeados a estructura | N (XX%) |
| Huérfanos o malpuestos | N |
| Requieren reescritura profunda | N |
| Requieren actualización | N |
| Estado de completitud actual | XX% |
| Estado de completitud post-auditoría (proyectado) | YY% |

**Conclusión:** [Síntesis ejecutiva de salud de documentación]

## Hallazgos Principales

### ✅ Documentación Sólida
| Documento | Ubicación | Estado | Notas |
|-----------|-----------|--------|-------|
| docs/01-SETUP.md | docs/ | 🟢 | Bien estructurado, actualizado |
| docs/API.md | docs/ | 🟢 | Comprehensive, con ejemplos |

### ⚠️ Documentación que Necesita Actualización
| Documento | Ubicación | Problema | Prioridad | Estimado |
|-----------|-----------|----------|-----------|----------|
| docs/DATABASE.md | docs/ | Desactualizado (12 meses) | CRÍTICO | 4h |
| docs/DEPLOYMENT.md | docs/ | Falta detalle de producción | ALTO | 3h |

### 🔴 Documentación que Requiere Reescritura Profunda
| Documento | Ubicación | Problema | Prioridad | Estimado |
|-----------|-----------|----------|-----------|----------|
| docs/ARCHITECTURE.md | No existe | Falta completo | CRÍTICO | 5h |
| docs/TENANT-GUIDE.md | docs/ | Incompleto, conceptos confusos | ALTO | 4h |

### 🗑️ Documentación Candidata a Deprecación
| Documento | Razón | Acción |
|-----------|-------|--------|
| docs/LEGACY-v1-AUTH.md | Versión obsoleta | Mover a postmortems/ |

## Estado de Conformidad vs. Estándares

| Criterio | Estado | Detalle |
|----------|--------|--------|
| Estructura profesional | ⚠️ | Parcialmente, necesita reestructuración |
| Cobertura técnica | ❌ | Faltan áreas críticas (Architecture, Decisions) |
| Actualización | ⚠️ | Mezcla de reciente y muy antigua |
| Ejemplos | ⚠️ | Algunos docs sin ejemplos prácticos |
| Intención clara | ❌ | Muchos docs sin propósito explícito |
| Audiencia definida | ❌ | Solo algunos documentos lo especifican |

## Plan de Acción Recomendado

### 🔴 CRÍTICOS (Semana 1 - 8h)
1. **Crear docs/architecture.md** (5h)
   - Decisiones arquitectónicas
   - Diagrama de componentes
   - Patrones utilizados
   
2. **Reescribir docs/database.md** (4h)
   - Schema actual
   - Migraciones
   - Índices y optimización

### 🟡 ALTOS (Semanas 2-3 - 12h)
1. **Actualizar docs/api-rest.md** (3h)
2. **Reestructurar docs/multi-tenant-specs.md** (4h)
3. **Crear docs/architecture-decisions/** (5h)

### 🟢 MEDIOS (Mes 1 - 10h)
[Otros documentos con menor prioridad]

## Reestructuración Propuesta

### Cambios Estructurales
- Crear carpeta \`docs/16-OPERATIONS/\` con docs de mantenimiento
- Crear carpeta \`docs/architecture-decisions/\` para ADRs
- Crear carpeta \`docs/postmortems/\` para histórico

### Renombramiento de Documentos
| Actual | Nuevo | Motivo |
|--------|-------|--------|
| docs/API.md | docs/api-rest.md | Alineación con nomenclatura |
| docs/DB.md | docs/database.md | Estandarización |

### Documentos a Deprecar (con histórico)
- DEPRECATED-v1-AUTH.md → Mover a postmortems/

## Próximas Acciones

1. **Aprobación de estructura propuesta**
   - [ ] Validar con Jose la estructura objetivo
   - [ ] Confirmar nuevas categorías (16-OPERATIONS, architecture-decisions)

2. **Ejecución de reestructuración**
   - [ ] Crear rama: \`docs/restructure-v2\`
   - [ ] Ejecutar renombramiento y movimientos
   - [ ] Validar links

3. **Generación de contenido faltante**
   - [ ] Crear Architecture.md
   - [ ] Reescribir Database.md
   - [ ] Crear ADR structure

4. **Validación final**
   - [ ] Revisión de todos los nuevos documentos
   - [ ] Validación de estructura final
   - [ ] Merge a main

## Recomendaciones Posteriores a la Auditoría

- Implementar proceso de revisión trimestral de documentación
- Asignar mantenedor por área (Architecture, Deployment, etc.)
- Automatizar detección de links rotos en CI
- Crear checklist para nuevas features: "¿Está documentado?"

## Métricas de Éxito Post-Auditoría

- ✅ Cobertura documentacional ≥ 85%
- ✅ 0 documentos huérfanos
- ✅ Todos los críticos actualizados hace < 3 meses
- ✅ Estructura conforme a estándares profesionales
- ✅ Cada documento tiene intención, audiencia y ejemplos claros
```

---

## 14. ESTÁNDARES OBLIGATORIOS

**Lenguaje:**

* Español profesional o inglés técnico (consistente en todo proyecto)
* Imperativos claros: "Debes", "Ejecuta", "Valida"
* Evitar jerga coloquial o ambigüedad

**Formato:**

* Markdown con extensión `.md`
* Links relativos para navegación interna: `[doc](../database.md)`
* Code blocks con lenguaje explícito:
  ```php
  // PHP code
  ```
* Tablas para datos estructurados
* Headers YAML en cada documento

**Actualización:**

* Header YAML con `updated: YYYY-MM-DD`
* Responsable de mantenimiento identificado
* Versionado en CHANGELOG.md

---

## 15. REGLAS INVIOLABLES

1. ✋ No tocar código fuente
2. ✋ No modificar CI/CD
3. ✋ No eliminar sin análisis explícito
4. ✋ No asumir infraestructura
5. ✋ No hacer cambios sin completar fases anteriores
6. ✋ No renombrar/mover sin validar broken links
7. ✋ No crear carpetas vacías sin documentos asignados
8. ✋ **Obligatorio:** Carpetas de dominio bajo `docs/` deben renombrarse con prefijo 20–35 (sección 4.1) y actualizarse **todos** los enlaces en el repo (`.md`, README raíz, overview, docs/README) antes de dar por cerrada la reestructuración.

---

## 16. CRITERIOS DE FINALIZACIÓN

El proyecto se considera **COMPLETO** cuando:

* ✅ Todas 8 fases completadas
* ✅ INVENTORY.md generado con catálogo completo
* ✅ CLASSIFICATION\_MATRIX.md muestra estado de cada doc
* ✅ RESTRUCTURING\_CHECKLIST.md con acciones concretas ejecutables
* ✅ Estructura objetivo implementada (carpetas creadas, docs movidos/renombrados)
* ✅ **Carpetas por dominio renombradas con prefijo 20–35** (sección 4.1); todos los enlaces actualizados (docs/, README raíz, overview, docs/README); sin referencias a rutas antiguas
* ✅ Documentación crítica faltante creada (Architecture, Database, etc.)
* ✅ DOCUMENTATION\_AUDIT\_REPORT.md completo y validado
* ✅ Cobertura documentacional ≥ 85%
* ✅ 0 documentos huérfanos o desincronizados
* ✅ Todos los documentos cumplen criterios de calidad (sección 5)
* ✅ Links validados y no hay 404s
* ✅ Recomendaciones de mantenimiento futuro documentadas

---

## 17. INICIO DE EJECUCIÓN

**Comando inicial:**

```
Ejecuta FASE 0 y FASE 1 completas:

FASE 0: Captura estado actual (tree, lista de docs)
FASE 1: Genera INVENTORY.md detallado

Pausa y presenta hallazgos iniciales antes de proceder a FASE 2.
Muestra:
- Total de documentos encontrados
- Documentos por ubicación
- Patrón de nomenclatura actual
- Antigüedad relativa
- Primeras impresiones sobre estado general
```

---

## 📋 RESUMEN DE MEJORAS v2.0

✅ **Análisis exhaustivo de lo existente** — No destruir, reorganizar inteligentemente
✅ **Mapeo documento-a-documento** — Cada doc existente tiene destino claro
✅ **Detección de huérfanos** — Documentos sin categoría clara
✅ **Nuevas categorías propuestas** — Extensiones profesionales si es necesario
✅ **Plan ejecutable** — Checklist concreta de acciones (mover, renombrar, reescribir, deprecar)
✅ **Carpetas por dominio numeradas 20–35** — Renombrar obligatoriamente (fundamentos, instrucciones, …) y actualizar todos los enlaces en el repo
✅ **Generación selectiva** — Solo crear lo verdaderamente faltante
✅ **Reporte detallado** — Qué revisar, modificar profundamente, crear
✅ **Preservación de valor** — No perder conocimiento existente
✅ **Validación de links** — Evitar broken references post-reestructuración

---

## 🎯 CÓMO USAR ESTE PROMPT

1. **Copia este documento completo**
2. **Reemplaza placeholders** como `[Responsable]`, `[DATE]`, etc.
3. **Pégalo como prompt en Claude** o tu herramienta de IA
4. **Agrega tu contexto inicial**: rutas a tu proyecto, estructura actual, etc.
5. **Ejecuta FASE 0 y FASE 1** para capturar estado actual
6. **Revisa hallazgos** antes de proceder

---

**Autor:** Agente de Auditoría Documentacional
**Última actualización:** Febrero 2026
**Versión:** 2.0 Producción
**Licencia:** Uso interno PesquerApp / Congelados Brisamar S.L.
