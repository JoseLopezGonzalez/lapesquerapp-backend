# FASE E — Nombres y estructura: resultados

**Fecha:** 2026-02-16  
**Plan:** PLAN-PENDIENTES-DOCUMENTACION.md

---

## E.1 Referencia de buenas prácticas

Creado **docs/audits/CONVENCIONES-DOCUMENTACION.md**: estructura 00–15 y 20–35, nomenclatura (kebab-case, sin espacios salvo excepción), uso de _archivo, referencias cruzadas, idiomas.

---

## E.2 Inventario de desviaciones

### Nombres con espacios o caracteres especiales

| Ruta | Observación | Prioridad |
|------|-------------|-----------|
| docs/core-consolidation-plan-erp-saas.md | Renombrado a kebab-case (estándar) | ✅ |
| docs/prompts/07_Laravel docs audit refactor .md | Espacios en nombre | P2 (opcional) |
| docs/prompts/06_CORS en Laravel.md | Espacios, "en" | P2 |
| docs/prompts/Pesquerapp seeder agent prompt.md | Espacios | P2 |
| docs/prompts/Cursor backup extractor prompt .md | Espacios | P2 |
| docs/prompts/04_Prompt agente autorizacion laravel.md | Espacios | P2 |
| docs/prompts/Rendimiento Auditoría Performance Laravel.md | Espacios, mayúsculas, acento en "Auditoría" | P2 |
| docs/prompts/02_Next.js UI Blocks Discovery Prompt.md | Espacios | P2 |
| docs/prompts/01_Laravel incremental evolution prompt.md | Espacios | P2 |
| docs/prompts/03_Prompt scribe implementation.md | Espacios | P2 |
| docs/prompts/00_Laravel Backend Deep Audit.md | Espacios | P2 |

**Decisión:** No renombrar prompts en esta pasada (evitar romper referencias en prompts; F.1 decidió mantener en docs/prompts). Quedan registrados como desviación; en futura iteración se puede unificar a kebab-case si se desea.

### Mezcla kebab-case / PascalCase / UPPER

- En docs/ dominan NN-nombre.md o Nombre-Descriptivo.md; api-references usa subcarpetas en minúsculas (pedidos, inventario). Aceptable; no se impone cambio masivo.

### Carpetas

- `por-implementar` y `por-hacer` unificados: todo el contenido en **`por-hacer/`**; carpeta `por-implementar` eliminada; `00_ POR IMPLEMENTAR` eliminada.
- No existe ya `00_ POR IMPLEMENTAR` (consolidado).

---

## E.3 Plan de unificación

- **Prioridad 1 (nombres problemáticos):** Ningún archivo fuera de prompts tiene espacios en el nombre (plan CORE renombrado a core-consolidation-plan-erp-saas.md); no hay acción P1 obligatoria.
- **Prioridad 2 (opcional):** Renombrar archivos en prompts a kebab-case en futura iteración si se quiere estricta adherencia.
- **Prioridad 3:** Reubicar archivos sueltos en docs/ raíz: no detectados como obligatorios; MANIFEST/STRUCTURE se actualizan en F.2.

**Entregable:** CONVENCIONES-DOCUMENTACION.md; este informe con inventario de desviaciones; P1 sin acciones pendientes, P2/P3 opcionales.
