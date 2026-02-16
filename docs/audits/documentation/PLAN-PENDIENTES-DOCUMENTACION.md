# Plan — Pendientes de documentación (post-reorganización)

**Fecha:** 2026-02-16  
**Estado:** Propuesta  
**Objetivo:** Ejecutar todo lo que no se hizo en la reorganización: validación, desactualizados, duplicados, política deprecados, nombres/estructura según buenas prácticas, y cierre del checklist.

---

## Resumen de lo que SÍ se hizo

- Reorganización y movimientos (CORS, deploy, producción, API v1, planes, auditoría, ROADMAP/TECH_DEBT, PROBLEMAS-CRITICOS, Control-Horario-FRONTEND).
- Renombrados 02b, 31b, 82b.
- Archivado (no eliminación) de deprecados y duplicados evidentes.
- Correcciones puntuales (Laravel 10, rutas 20-fundamentos/28-sistema).
- Stubs poblados (rollback, runbook, staging, debugging, postmortem).

## Resumen de lo que NO se hizo

1. **Validación sistemática** — No se ejecutó el VALIDATION_CHECKLIST de punta a punta (enlaces rotos, referencias incorrectas, métricas).
2. **Revisión de documentación desactualizada** — No se revisó documento a documento si el contenido sigue siendo válido (API, flujos, versiones).
3. **Auditoría de duplicados** — Solo se consolidó CORS y deploy; no se revisó solapamiento 22–28 vs 31-api-references ni otros pares.
4. **Política de eliminación de deprecados** — Se archivó todo; no se definió si algo se elimina nunca o en qué condiciones.
5. **Nombres y estructura según buenas prácticas** — No se evaluó ni unificó nomenclatura/estructura frente a referencias Laravel o propias.
6. **Cierre de detalles** — Carpeta `00_ POR IMPLEMENTAR`, decisión 35-prompts, actualización MANIFEST/STRUCTURE_DIAGRAM, métricas del checklist.

---

## FASE A — Validación (ejecutar checklist)

**Objetivo:** Comprobar integridad post-reorganización y corregir lo que falle.

### A.1 Enlaces y referencias

- [x] Extraer todos los enlaces `](...\.md)` en docs/, README, CLAUDE, SECURITY y comprobar que el archivo destino existe (y que la ruta es la correcta tras movimientos).
- [x] Buscar y corregir referencias restantes a: `docs/fundamentos/`, `docs/sistema/`, `docs/PROBLEMAS-CRITICOS.md` (debe ser `docs/audits/PROBLEMAS-CRITICOS.md`).
- [x] Buscar "Laravel 11" (excl. archivo/_archivo y menciones "11/12") y corregir a Laravel 10 donde corresponda.
- [x] Buscar referencias a API v1 como activa (excl. deprecado/archivo/ADR) y corregir o marcar como histórico.
- [x] Validar CLAUDE.md, README.md (raíz), SECURITY.md, docs/00-OVERVIEW.md, docs/README.md: cada enlace a docs/ debe resolver.

**Entregable:** Ver FASE-A-VALIDACION-RESULTADOS.md (2 enlaces rotos corregidos; referencias OK; Laravel 11 corregido en 2 docs).

### A.2 Formato y consistencia (muestreo)

- [x] Comprobar que documentos principales tienen H1 y, donde aplique, "Última actualización".
- [x] Listar archivos .md con "Por completar" / "TODO" / "FIXME" y clasificar: aceptable (stub conocido) vs debe completarse o archivarse.
- [x] Verificar que no queden dos documentos "activos" para el mismo tema (CORS, deploy desarrollo) fuera de _archivo.

**Entregable:** Ver FASE-A-VALIDACION-RESULTADOS.md (muestreo OK; lista exhaustiva de placeholders pendiente para FASE B si se desea).

### A.3 Checklist por fases y métricas

- [x] Rellenar la tabla "Validación por Fase" del VALIDATION_CHECKLIST con ✅/❌ según estado real.
- [x] Rellenar "Métricas Post-Reorganización" (total docs, duplicados, deprecados visibles, stubs).
- [x] Decidir y documentar: carpeta `docs/00_ POR IMPLEMENTAR` — consolidada (por-implementar/00-POR-IMPLEMENTAR-README.md).

**Entregable:** VALIDATION_CHECKLIST actualizado; FASE-A-VALIDACION-RESULTADOS.md.

---

## FASE B — Documentación desactualizada

**Objetivo:** Identificar documentos cuyo contenido ya no es válido y actuar (actualizar o archivar).

### B.1 Criterios de "desactualizado"

Considerar desactualizado cuando:

- Habla de API v1 como disponible o recomienda usarla.
- Indica Laravel 11 como versión del proyecto.
- Describe flujos, rutas o estructuras que ya no existen en el código (ej. rutas eliminadas, nombres de tablas/campos cambiados).
- Referencias a archivos o rutas que hemos movido/renombrado sin actualizar el texto.
- Fechas o hitos claramente superados sin que el doc se haya revisado.

### B.2 Proceso

1. **Inventario:** Listar documentos por carpeta (o por dominio 20–35) y marcar candidatos a revisión (por nombre, antigüedad, o búsqueda de "v1", "Laravel 11", rutas viejas).
2. **Revisión:** Por cada candidato: leer resumen/primeras secciones y comprobar contra código o docs canónicos (CLAUDE, 00-ESTADO-ACTUAL, rutas actuales). Decisión: actualizar / archivar / eliminar (si aplica política FASE D).
3. **Actualización o archivado:** Si se actualiza, corregir versiones y enlaces. Si se archiva, mover a _archivo correspondiente y añadir banner "Archivado — contenido desactualizado. Ver [doc canónico]."

**Entregable:** Lista de documentos revisados con decisión (actualizado / archivado / sin cambios); cambios aplicados.

---

## FASE C — Duplicados

**Objetivo:** Detectar contenido duplicado o muy solapado y dejar una sola fuente de verdad o enlaces claros.

### C.1 Ámbitos a revisar

- **22–28 (dominio) vs 31-api-references:** ¿Los README de 31-api-references repiten lo que ya está en 22–28? Si sí: decidir "canónico en 22–28, 31 solo índice y enlaces" o "canónico en 31, 22–28 resumen".
- **Ejemplos y referencias:** 32-ejemplos vs ejemplos embebidos en docs de producción/pedidos; duplicación de ejemplos de respuesta.
- **Otros pares obvios:** Por búsqueda o inspección (ej. dos guías de "cómo hacer X" en carpetas distintas).

### C.2 Proceso

1. **Mapeo:** Tabla "Doc A / Doc B / Tipo de solapamiento / Propuesta (canónico + enlace, o consolidar en uno)".
2. **Decisión:** Para cada par, decidir: (a) consolidar en un solo doc y archivar el otro, o (b) doc canónico + en el otro solo "Ver [canónico]".
3. **Aplicar:** Consolidaciones o redacciones de redirección; actualizar índices (00-OVERVIEW, README) si cambia el doc principal.

**Entregable:** Informe de duplicados con decisiones; cambios aplicados (consolidaciones o enlaces canónicos).

---

## FASE D — Política deprecados y eliminación

**Objetivo:** Definir si algo se elimina alguna vez y, en su caso, qué y cuándo.

### D.1 Opciones de política

- **A:** Nunca eliminar; todo lo deprecado permanece en _archivo (solo crece).
- **B:** Eliminar solo artefactos de auditoría muy antiguos (ej. > 2 años) si ya no se consultan y hay informe resumen.
- **C:** Eliminar cualquier doc en _archivo que lleve > X tiempo y esté marcado "obsoleto" tras revisión, con registro en CHANGELOG o audits.

### D.2 Decisión y registro

- [ ] Elegir política (A, B o C) y documentarla en este plan o en docs/audits/.
- [ ] Si B o C: definir criterios (antigüedad, tipo de doc) y lista de primeros candidatos a eliminar (o "ninguno por ahora").

**Entregable:** Sección "Política de deprecados" en documentación; si aplica, lista de candidatos a eliminación futura.

---

## FASE E — Nombres y estructura (buenas prácticas)

**Objetivo:** Alinear nombres de archivos/carpetas y estructura con un criterio definido (Laravel o propio).

### E.1 Referencia de buenas prácticas

- **Opción 1:** Documentar "Convenciones de documentación — PesquerApp" (1–2 páginas): estructura 00–15 + 20–35, nomenclatura (kebab-case para .md, prefijo numérico donde aplique), uso de _archivo, sin espacios ni caracteres especiales en nombres.
- **Opción 2:** Referencia externa (ej. Laravel documentation structure, o otro proyecto Laravel) y lista de "desviaciones aceptadas" en nuestro repo.

**Entregable:** Doc corto de convenciones (o enlace a referencia + desviaciones).

### E.2 Inventario de desviaciones

- [ ] Nombres con espacios o caracteres raros (excepto 00_CORE... si se mantiene por legacy).
- [ ] Mezcla kebab-case / PascalCase / UPPER en nombres de archivos.
- [ ] Carpetas con nombres inconsistentes (00_ POR IMPLEMENTAR, por-implementar vs 34-por-hacer).
- [ ] Archivos en raíz de docs/ que podrían estar en subcarpeta.

**Entregable:** Lista de desviaciones con ruta y propuesta (renombrar / mover / excepción).

### E.3 Plan de unificación (gradual)

- **Prioridad 1:** Corregir nombres que causan problemas (espacios, caracteres especiales).
- **Prioridad 2:** Unificar criterio en carpetas nuevas y en documentos que se toquen (ej. todo kebab o todo "NN-nombre-tema.md").
- **Prioridad 3:** Reubicar archivos sueltos en docs/ raíz si hay subcarpeta clara (sin romper enlaces; actualizar referencias).

No obligatorio hacer todo en una pasada; se puede ir aplicando en sucesivas iteraciones.

**Entregable:** Lista de acciones Prioridad 1 (y opcionalmente 2–3) con estado (hecho / pendiente).

---

## FASE F — Cierre y actualización de índices

**Objetivo:** Dejar la documentación coherente con el estado real y las decisiones tomadas.

### F.1 Decisiones pendientes

- [ ] **35-prompts:** ¿Mover a `.agents/prompts/` o mantener en `docs/35-prompts/`? Decidir y ejecutar o documentar "mantenemos en docs/".
- [ ] **00_ POR IMPLEMENTAR:** Ya cubierto en A.3; registrar decisión aquí si no está en el checklist.

### F.2 Actualización de MANIFEST y STRUCTURE_DIAGRAM

- [ ] **MANIFEST.md:** Actualizar rutas que cambiaron (PROBLEMAS-CRITICOS en audits/, ROADMAP y TECH_DEBT en 34-por-hacer/, documentos archivados, 33-frontend/Control-Horario-FRONTEND). Recontar archivos/líneas si hace falta.
- [ ] **STRUCTURE_DIAGRAM.md:** Reflejar estructura actual (sin 00_ en raíz si se eliminó, audits/PROBLEMAS-CRITICOS, _archivo, etc.).

### F.3 Índices y navegación

- [ ] Comprobar que docs/00-OVERVIEW.md y docs/README.md enlazan a los documentos canónicos correctos (audits/PROBLEMAS-CRITICOS, 34-por-hacer, 21-instrucciones deploy, etc.).
- [ ] Si se crearon o movieron docs en FASE B/C/E, añadirlos a índices correspondientes.

**Entregable:** MANIFEST y STRUCTURE_DIAGRAM actualizados; índices revisados; decisiones F.1 registradas.

---

## Orden de ejecución recomendado

| Orden | Fase | Dependencias | Esfuerzo estimado |
|-------|------|--------------|-------------------|
| 1 | A — Validación | Ninguna | 1–2 h |
| 2 | F.1 — Decisiones (35-prompts, 00_) | Ninguna | 15 min |
| 3 | B — Desactualizados | A (enlaces corregidos) | 2–3 h |
| 4 | C — Duplicados | A | 1–2 h |
| 5 | D — Política deprecados | Ninguna (decisión) | 30 min |
| 6 | E — Nombres y estructura | A, F.1 | 1–2 h |
| 7 | F.2, F.3 — MANIFEST, STRUCTURE, índices | A, B, C, E | 1 h |

**Total estimado:** 7–11 h (repartibles en varias sesiones).

---

## Criterios de "hecho"

- **FASE A:** Checklist ejecutado; enlaces rotos e referencias incorrectas corregidas; métricas rellenadas.
- **FASE B:** Lista de revisados con decisión; documentos desactualizados actualizados o archivados.
- **FASE C:** Informe duplicados con decisiones; consolidaciones o enlaces canónicos aplicados.
- **FASE D:** Política documentada; si aplica, lista de candidatos a eliminación.
- **FASE E:** Doc de convenciones + inventario desviaciones + al menos Prioridad 1 aplicada.
- **FASE F:** Decisiones 35-prompts y 00_ registradas; MANIFEST y STRUCTURE_DIAGRAM actualizados; índices coherentes.

---

## Historial

| Fecha | Cambio |
|-------|--------|
| 2026-02-16 | Plan creado (pendientes post-reorganización). |
| 2026-02-16 | FASE A ejecutada: enlaces corregidos, Laravel 11 corregido, checklist y métricas actualizados. Ver FASE-A-VALIDACION-RESULTADOS.md. |
