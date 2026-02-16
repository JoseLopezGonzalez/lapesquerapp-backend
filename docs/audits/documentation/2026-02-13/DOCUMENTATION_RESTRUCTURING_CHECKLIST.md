# Plan de Reestructuración de Documentación

**FASE 5 — Auditoría de documentación PesquerApp Backend**  
**Fecha:** 2026-02-13

Checklist ejecutable derivada de [DOCUMENTATION_MAPPING_MATRIX.md](./DOCUMENTATION_MAPPING_MATRIX.md) y [DOCUMENTATION_ORPHANS_AND_CATEGORIES.md](./DOCUMENTATION_ORPHANS_AND_CATEGORIES.md).

---

## ACCIONES PREVIAS (antes de cualquier movimiento)

- [x] Hacer backup de carpeta `docs/` completa — *hecho por el usuario*
- [ ] Crear rama git: `docs/restructure-audit-2026`
- [ ] Validar que no hay procesos/CI dependiendo de rutas concretas a archivos en `docs/00_ POR IMPLEMENTAR` o `docs/26-recepciones-despachos/67-*`

---

## 🔴 ACCIONES CRÍTICAS (SEMANA 1)

### Integración de contenido “por implementar”

- [x] **Revisar** `docs/00_ POR IMPLEMENTAR/guia-entorno-desarrollo-pesquerapp.md` — **Hecho:** Movido a `docs/21-instrucciones/guia-completa-entorno-sail-windows.md`; enlaces actualizados.

- [x] **Revisar** `docs/00_ POR IMPLEMENTAR/IMPORTANTE/resumen-problema-solucion-productos-variantes.md` — **Hecho:** Movido a `docs/24-catalogos/54-Productos-Variantes-GS1-Resumen.md`.

### Deprecación / revisión

- [x] **Confirmar** si API v1 sigue en uso. **Hecho:** Banner de deprecación añadido en 67-Guia-Backend-v1 (API v1 eliminada 2025-01-27).

### Actualización sin movimiento

- [x] **Actualizar** `database/migrations/companies/README.md` — **Hecho:** Última actualización a Febrero 2026.

---

## 🟡 ACCIONES ALTAS (SEMANA 2)

### Carpetas ya existentes (sin crear vacías)

- [ ] **docs/13-POSTMORTEMS/:** Mantener; cuando haya un incidente, añadir primer postmortem según plantilla.
- [ ] **docs/14-ARCHITECTURE-DECISIONS/:** Añadir al menos una plantilla ADR y un ADR de ejemplo (p. ej. multi-tenant o eliminación API v1) a partir de contenido en fundamentos/referencia.

### Contenido por completar (opcional)

- [ ] Ampliar **docs/09-TESTING.md** y **docs/10-OBSERVABILITY-MONITORING.md** (enlaces, ejemplos, comandos).
- [ ] Revisar **docs/11-DEPLOYMENT/** (11b STAGING, 11d ROLLBACK, 11e RUNBOOK) y completar desde `docs/21-instrucciones/` si aplica.

### Nueva categoría (solo si hay contenido)

- [ ] **docs/16-OPERATIONS/:** Crear carpeta **solo cuando** exista al menos un documento (p. ej. BACKUP-RESTORE, DATABASE-MAINTENANCE). No crear vacía.

---

## 🟢 VALIDACIÓN POST-REESTRUCTURACIÓN

- [ ] No hay enlaces rotos: `grep -r "00_ POR IMPLEMENTAR" docs/` (tras integración) y revisar referencias a archivos movidos.
- [ ] Actualizar **docs/00-OVERVIEW.md** si se han movido o renombrado documentos.
- [ ] Actualizar **docs/README.md** (índice por dominio) si cambian rutas.
- [ ] Si se ha creado **docs/16-OPERATIONS/**, añadir entrada en 00-OVERVIEW y en README.

### Comandos de validación

```bash
# Contar documentos
find docs -name "*.md" -type f | wc -l

# Buscar referencias a carpeta por implementar (tras mover)
grep -r "00_ POR IMPLEMENTAR" docs/ 2>/dev/null || echo "No references"

# Buscar referencias a 67-Guia-Backend-v1 (si se mueve a 13-POSTMORTEMS)
grep -r "67-Guia-Backend-v1" docs/ 2>/dev/null
```

---

## Resumen de prioridad

| Prioridad | Acción | Estimado |
|-----------|--------|----------|
| 🔴 | Integrar 00_ POR IMPLEMENTAR (2 docs) | 1–2 h |
| 🔴 | Revisar/deprecar 67-Guia-Backend-v1 (si v1 obsoleto) | 0,5 h |
| 🔴 | Actualizar database/migrations/companies/README | 0,5 h |
| 🟡 | Plantilla ADR + 1 ADR en 14-ARCHITECTURE-DECISIONS | 1–2 h |
| 🟡 | Completar 09-TESTING, 10-OBSERVABILITY, 11-DEPLOYMENT | 2–4 h |
| 🟢 | Validación enlaces y 00-OVERVIEW/README | 0,5 h |

---

**Siguiente paso:** FASE 6 — Generación de documentación crítica faltante (si aplica); FASE 7 — Informe final de auditoría.
