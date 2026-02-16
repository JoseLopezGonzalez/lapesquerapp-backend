# Convenciones de documentación — PesquerApp Backend

**Fecha:** 2026-02-16  
**Uso:** Criterio para nombres, estructura y archivado. Nuevos documentos y refactors deberían seguir estas convenciones.

---

## 1. Estructura de carpetas

- **docs/ raíz:** Archivos de índice en minúsculas y sin numeración: overview.md, setup-local.md, environment-variables.md, architecture.md, database.md, queues-jobs.md, scheduler-cron.md, storage-files.md, api-rest.md, testing.md, observability-monitoring.md, multi-tenant-specs.md. Carpetas de tema: deployment/, troubleshooting/, postmortems/, architecture-decisions/.
- **Carpetas por dominio (docs/):** Sin numeración, minúsculas (kebab-case): fundamentos, instrucciones, pedidos, inventario, catalogos, produccion, recepciones-despachos, etiquetas, sistema, utilidades, referencia, api-references, ejemplos, frontend, por-hacer, prompts.
- **audits/:** Auditorías, hallazgos, planes y documentación de documentación (MANIFEST, REORGANIZATION_PLAN, etc.).
- **_archivo/:** Contenido histórico o deprecado; no se elimina (ver docs/audits/POLITICA-DEPRECADOS.md). Subcarpetas cuando aplique (_archivo-cors, _archivo/api-v1, etc.).

---

## 2. Nomenclatura de archivos

- **Preferido:** `NN-nombre-del-dema.md` (número opcional + kebab-case) o `nombre-descriptivo.md` en kebab-case.
- **Evitar:** Espacios en el nombre, caracteres especiales (acentos, ñ) en el nombre del archivo.
- **Carpetas:** minúsculas, guiones (kebab-case); sin espacios ni prefijos numéricos. Ej.: `instrucciones`, `por-hacer`. Una sola carpeta para backlog: `por-hacer`.

---

## 3. Contenido de documentos

- **Primera línea:** H1 (`# Título`) como título del documento.
- **Documentos principales:** Incluir "Última actualización: YYYY-MM-DD" cuando sea útil.
- **Deprecados/archivados:** Banner al inicio, p. ej. `> **Archivado:** YYYY-MM-DD. Contenido consolidado en [doc canónico](ruta).` o `> **DEPRECADO** — ...`.

---

## 4. Referencias cruzadas

- Usar rutas relativas al documento actual (ej. `../audits/PROBLEMAS-CRITICOS.md`, `./pedidos/README.md`).
- Tras mover un archivo, actualizar enlaces en README, overview y documentos que lo referencien (ver VALIDATION_CHECKLIST).

---

## 5. Idiomas

- Documentación de dominio y usuario: español.
- Nombres técnicos (endpoints, variables, código): inglés cuando sea el estándar del proyecto.

---

**Referencia:** PLAN-PENDIENTES-DOCUMENTACION.md (FASE E); VALIDATION_CHECKLIST.md.
