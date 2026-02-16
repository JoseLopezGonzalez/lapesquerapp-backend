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

- [ ] README.md dice "Laravel 10" (no "Laravel 11")
- [ ] SECURITY.md usa `docs/20-fundamentos/` (no `docs/fundamentos/`)
- [ ] 11d-ROLLBACK-PROCEDURES.md tiene contenido real (> 30 líneas)
- [ ] 11e-RUNBOOK.md tiene contenido real (> 30 líneas)

### FASE 2 — Consolidación de Duplicados

- [ ] Existe `docs/21-instrucciones/CORS-GUIA-DEFINITIVA.md`
- [ ] Los 9 archivos CORS originales están en `_archivo-cors/`
- [ ] Existe un único `deploy-desarrollo.md` consolidado (o se decidió mantener ambos)
- [ ] CHANGELOG.md poblado o eliminado
- [ ] ROADMAP.md poblado o eliminado
- [ ] TECH_DEBT.md poblado o eliminado

### FASE 3 — Reorganización de Producción

- [ ] Existe `docs/25-produccion/00-ESTADO-ACTUAL.md`
- [ ] Propuestas implementadas movidas a `_archivo/`
- [ ] README en cada subdirectorio de producción actualizado

### FASE 4 — Archivado de Documentación Deprecada

- [ ] Documentos v1 movidos a `docs/_archivo/api-v1/`
- [ ] Planes completados movidos a `docs/_archivo/planes-completados/`
- [ ] Artefactos auditoría 2026-02-13 movidos a `docs/audits/documentation/2026-02-13/`
- [ ] Carpeta `docs/00_ POR IMPLEMENTAR/` eliminada o consolidada

### FASE 5 — Mejoras Estructurales

- [ ] No hay dos archivos con mismo prefijo numérico en una carpeta
- [ ] `86-Control-Horario-FRONTEND.md` en `docs/33-frontend/` (si se decidió mover)
- [ ] Decisión tomada sobre `.ai_work_context/` (.gitignore o mantener)
- [ ] Decisión tomada sobre `docs/35-prompts/` (mover a .agents/ o mantener)

### FASE 6 — Documentos Nuevos

- [ ] Todos los documentos nuevos siguen la plantilla/convención existente
- [ ] Nuevos documentos enlazados desde índices correspondientes (00-OVERVIEW, README)
- [ ] MANIFEST.md actualizado con nuevos documentos

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
| Total docs en docs/ | 196 | ___ | ~170 |
| Archivos duplicados | 22+ | ___ | 0 |
| Archivos deprecados visibles | 6 | ___ | 0 |
| Stubs vacíos | 8 | ___ | ≤ 3 |
| Calidad global | 7/10 | ___ | ≥ 8.5/10 |

---

## Firma de Validación

| Fase | Validado por | Fecha | Estado |
|------|-------------|-------|--------|
| FASE 1 | | | ⬜ Pendiente |
| FASE 2 | | | ⬜ Pendiente |
| FASE 3 | | | ⬜ Pendiente |
| FASE 4 | | | ⬜ Pendiente |
| FASE 5 | | | ⬜ Pendiente |
| FASE 6 | | | ⬜ Pendiente |
| **FINAL** | | | ⬜ Pendiente |

---

**Generado:** 2026-02-16
**Herramienta:** Claude Code (Opus 4.6)
