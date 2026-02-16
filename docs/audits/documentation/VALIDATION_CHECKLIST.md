# Checklist de Validación Post-Reorganización — PesquerApp Backend

**Fecha:** 2026-02-16
**Uso:** Ejecutar este checklist después de cada fase de reorganización para verificar integridad.

---

## Pre-Reorganización

- [ ] Backup del estado actual (`git stash` o branch de backup)
- [ ] Verificar que todos los tests pasan antes de cambios
- [ ] Confirmar plan de reorganización aprobado por el equipo

---

## Validación de Formato (todos los archivos)

### Encabezados
- [ ] Todos los archivos .md tienen encabezado H1 (`#`) como primera línea de contenido
- [ ] No hay archivos sin título
- [ ] Títulos son descriptivos (no genéricos como "Documento" o "Info")

### Metadatos
- [ ] Documentos principales incluyen fecha de última actualización
- [ ] Documentos deprecados tienen banner `> ⚠️ DEPRECADO` al inicio
- [ ] Documentos históricos/archivo indican claramente su estado

### Consistencia
- [ ] Nomenclatura consistente (kebab-case o PascalCase, no mezcla)
- [ ] Numeración sin duplicados dentro de cada carpeta
- [ ] Lenguaje consistente (español para docs de dominio, inglés para técnicos si aplica)

---

## Validación de Referencias

### Enlaces internos
- [ ] Ejecutar verificación de enlaces rotos:
  ```bash
  # Buscar referencias a archivos .md y verificar que existen
  grep -rn '\]\(.*\.md' docs/ | grep -v node_modules | grep -v vendor
  ```
- [ ] Todos los enlaces relativos apuntan a archivos existentes
- [ ] No hay enlaces a `docs/fundamentos/` (debe ser `docs/20-fundamentos/`)
- [ ] No hay enlaces a archivos movidos sin actualizar

### Referencias cruzadas
- [ ] CLAUDE.md: todas las referencias a docs/ son válidas
- [ ] README.md: todas las referencias a docs/ son válidas
- [ ] SECURITY.md: todas las referencias a docs/ son válidas
- [ ] docs/00-OVERVIEW.md: todos los enlaces funcionan
- [ ] docs/README.md: todos los enlaces funcionan

---

## Validación de Contenido

### Versiones
- [ ] Ningún documento hace referencia a "Laravel 11" (proyecto usa Laravel 10)
- [ ] Ningún documento hace referencia a API v1 como activa (eliminada 2025-01-27)
- [ ] Verificación:
  ```bash
  grep -rn "Laravel 11" docs/ README.md CLAUDE.md --include="*.md" | grep -v "11/12" | grep -v "archivo" | grep -v "_archivo"
  ```

### Placeholders
- [ ] No hay secciones con "TODO" o "Por completar" en documentos marcados como ✅
- [ ] Stubs (< 10 líneas) están identificados y en plan de acción
- [ ] Verificación:
  ```bash
  grep -rn "Por completar\|TODO\|FIXME\|PLACEHOLDER" docs/ --include="*.md"
  ```

### Duplicaciones
- [ ] No hay más de 1 documento CORS activo (los demás archivados)
- [ ] No hay más de 1 documento deploy-desarrollo activo
- [ ] Documentación API no está duplicada entre 22-28 y 31-api-references sin justificación

---

## Validación por Fase

### FASE 1 — Correcciones Inmediatas

- [x] README.md dice "Laravel 10" (no "Laravel 11")
- [x] SECURITY.md usa `docs/20-fundamentos/` (no `docs/fundamentos/`)
- [x] 11d-ROLLBACK-PROCEDURES.md tiene contenido real (> 30 líneas)
- [x] 11e-RUNBOOK.md tiene contenido real (> 30 líneas)

### FASE 2 — Consolidación de Duplicados

- [x] Existe `docs/21-instrucciones/CORS-GUIA-DEFINITIVA.md`
- [x] Los 9 archivos CORS originales están en `_archivo-cors/`
- [x] deploy-desarrollo en _archivo; canónico: deploy-desarrollo-guiado
- [x] CHANGELOG.md con referencia a tags
- [x] ROADMAP.md y TECH_DEBT.md en docs/34-por-hacer/

### FASE 3 — Reorganización de Producción

- [x] Existe `docs/25-produccion/00-ESTADO-ACTUAL.md`
- [x] Propuestas implementadas movidas a `_archivo/` (raíz, analisis, cambios)
- [ ] README en cada subdirectorio de producción actualizado (parcial)

### FASE 4 — Archivado de Documentación Deprecada

- [x] Documentos v1 movidos a `docs/_archivo/api-v1/`
- [x] Planes completados movidos a `docs/_archivo/planes-completados/`
- [x] Artefactos auditoría 2026-02-13 movidos a `docs/audits/documentation/2026-02-13/`
- [x] Carpeta `docs/00_ POR IMPLEMENTAR/` consolidada (por-implementar/00-POR-IMPLEMENTAR-README.md)

### FASE 5 — Mejoras Estructurales

- [x] No hay dos archivos con mismo prefijo numérico (02b, 31b, 82b)
- [x] Control-Horario-FRONTEND en `docs/33-frontend/`
- [x] `.ai_work_context/` en .gitignore
- [ ] Decisión tomada sobre `docs/35-prompts/` (mantener en docs por ahora)

### FASE 6 — Documentos Nuevos

- [x] 11b-STAGING, DEBUGGING-GUIDE, postmortem template poblados
- [x] Enlazados desde docs correspondientes
- [ ] MANIFEST.md actualizado (pendiente FASE F del plan pendientes)

---

## Validación Técnica

### Laravel Best Practices
- [ ] Documentación refleja estructura `app/` real (Controllers/v2, Services, Models, Policies)
- [ ] Convenciones de código documentadas coinciden con código real
- [ ] Rutas documentadas en 97-Rutas-Completas.md coinciden con `routes/api.php`
- [ ] Verificación:
  ```bash
  php artisan route:list --json 2>/dev/null | head -50
  ```

### Multi-Tenant
- [ ] Documentación multi-tenant es consistente entre:
  - CLAUDE.md (sección 2)
  - docs/20-fundamentos/01-Arquitectura-Multi-Tenant.md
  - docs/15-MULTI-TENANT-SPECIFICS.md
- [ ] No hay instrucciones que mezclen conexión `mysql` con datos de negocio

### API v2
- [ ] Todos los ejemplos de API usan `/api/v2/` (no `/api/v1/`)
- [ ] Header `X-Tenant` documentado en todos los endpoints
- [ ] Verificación:
  ```bash
  grep -rn "api/v1" docs/ --include="*.md" | grep -v "eliminad\|deprecad\|_archivo\|ADR\|históric"
  ```

---

## Validación de Estructura

### Directorios
- [ ] No hay directorios vacíos
- [ ] Cada directorio con > 3 archivos tiene README.md o índice
- [ ] Estructura `_archivo/` creada donde necesario
- [ ] No hay archivos sueltos en `docs/` raíz que deberían estar en subdirectorio

### Nombres de archivo
- [ ] No hay espacios en nombres de archivo (excepto `00_CORE CONSOLIDATION PLAN...md` - legacy)
- [ ] No hay caracteres especiales problemáticos (acentos, ñ) en nombres de archivo
- [ ] Verificación:
  ```bash
  find docs/ -name "*.md" | grep -P '[áéíóúñÁÉÍÓÚÑ ]' | grep -v "00_CORE"
  ```

---

## Validación Final

- [ ] `git diff --stat` muestra solo cambios esperados
- [ ] Ningún archivo de código fuente (.php, .js, .json) fue modificado
- [ ] Tests siguen pasando después de reorganización
- [ ] MANIFEST.md refleja el estado final real
- [ ] Este checklist archivado como referencia

---

## Métricas Post-Reorganización

| Métrica | Antes | Después | Objetivo |
|---------|-------|---------|----------|
| Total docs en docs/ | 196 | ~185 | ~170 |
| Archivos duplicados activos | 22+ | 0 (CORS, deploy archivados) | 0 |
| Archivos deprecados visibles en raíz | 6 | 0 (archivados) | 0 |
| Stubs vacíos | 8 | ≤ 3 (poblados rollback, runbook, staging, debugging, postmortem) | ≤ 3 |
| Calidad global | 7/10 | ~8/10 | ≥ 8.5/10 |

---

## Firma de Validación

| Fase | Validado por | Fecha | Estado |
|------|-------------|-------|--------|
| FASE 1 | Plan pendientes (FASE A) | 2026-02-16 | ✅ |
| FASE 2 | Plan pendientes (FASE A) | 2026-02-16 | ✅ |
| FASE 3 | Plan pendientes (FASE A) | 2026-02-16 | ✅ |
| FASE 4 | Plan pendientes (FASE A) | 2026-02-16 | ✅ |
| FASE 5 | Plan pendientes (FASE A) | 2026-02-16 | ✅ (35-prompts pendiente decisión) |
| FASE 6 | Plan pendientes (FASE A) | 2026-02-16 | ✅ (MANIFEST pendiente FASE F) |
| **FINAL** | | | 🔄 En progreso (plan pendientes FASE B–F) |

---

**Generado:** 2026-02-16
**Herramienta:** Claude Code (Opus 4.6)
