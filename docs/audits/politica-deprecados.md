# Política de documentación deprecada y archivada

**Fecha:** 2026-02-16  
**Contexto:** Plan pendientes documentación (FASE D).

---

## Decisión

**Política adoptada: A — No eliminar.**

Todo lo deprecado o histórico permanece en carpetas `_archivo/` (o `_archivo-cors/`, `docs/_archivo/`, etc.). No se elimina documentación archivada; el repositorio solo crece en contenido archivado.

**Motivo:** Trazabilidad, auditoría y posibilidad de consultar decisiones o versiones antiguas sin perder información. El coste de almacenamiento es bajo frente al valor de conservar historial.

---

## Ubicaciones de archivado

- `docs/instrucciones/_archivo-cors/` — Documentos CORS consolidados en CORS-GUIA-DEFINITIVA.
- `docs/instrucciones/_archivo/` — deploy-desarrollo (canónico: deploy-desarrollo-guiado).
- `docs/produccion/_archivo/`, `analisis/_archivo/`, `cambios/_archivo/` — Propuestas e implementaciones ya aplicadas.
- `docs/_archivo/api-v1/` — Documentación API v1 (eliminada).
- `docs/_archivo/planes-completados/` — Planes ya ejecutados (ARTICLE, recepciones, auth magic link).
- `docs/audits/documentation/2026-02-13/` — Artefactos de auditoría previa.

---

## Revisión futura

Si en el futuro se quisiera adoptar política B (eliminar artefactos de auditoría muy antiguos) o C (eliminar con criterios), se actualizará este documento y se registrará en el plan de documentación o en el evolution log.
