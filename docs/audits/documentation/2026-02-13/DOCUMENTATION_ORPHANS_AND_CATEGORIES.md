# Documentos Huérfanos y Nuevas Categorías Propuestas

**FASE 4 — Auditoría de documentación PesquerApp Backend**  
**Fecha:** 2026-02-13

---

## Documentos actuales sin mapeo claro

| Documento / Carpeta | Tema | Opción 1 | Opción 2 |
|--------------------|------|----------|----------|
| docs/00_ POR IMPLEMENTAR/ | Guías y resúmenes pendientes de integrar | Mover contenido a **docs/21-instrucciones/** y **docs/01-SETUP-LOCAL** (ampliar) | Mantener carpeta pero renombrar a `docs/PENDING-INTEGRATION/` y añadir README que enlace a 01-SETUP e instrucciones |
| docs/34-por-hacer/ | Backlog, revisiones pendientes | **Mantener** como backlog (fuera árbol 00–16) | Mover 01-Revision-Validaciones a docs/22-pedidos/ o referencia/ si es referencia técnica |
| docs/35-prompts/ | Prompts de agentes IA (auditoría, seeder) | **Mantener** en docs/35-prompts/ (no es parte del árbol operativo 00–16) | Mover a .ai_standards/ si se unifica documentación para agentes |
| .ai_standards/ | Memoria de trabajo, protocolo IA | **Mantener** en raíz (estándares de trabajo con IA) | No incluir en estructura canónica de documentación técnica |
| docs/GAPS_ANALYSIS.md, DOCUMENTATION_TODO_FLOW.md, DOCUMENTATION_AUDIT_REPORT.md, CURRENT_STATE_SNAPSHOT.md | Artefactos de auditoría | **Mantener** en docs/; opcional subcarpeta docs/audit/ | Dejar en raíz docs/ para visibilidad |

---

## Nuevas categorías detectadas

### ✅ Nueva categoría opcional: docs/16-OPERATIONS/

**Justificación:** La estructura objetivo prevé 16-OPERATIONS “solo si existe volumen”. Hoy no hay documentos dedicados a backup, mantenimiento BD o escalado; si en el futuro se documentan procedimientos operativos (backup/restore, mantenimiento BD, escalado), crear:

```
docs/16-OPERATIONS/
├── BACKUP-RESTORE.md
├── DATABASE-MAINTENANCE.md
├── SCALING-PROCEDURES.md
└── README.md (índice)
```

**Recomendación:** No crear la carpeta vacía; crearla cuando exista al menos un documento de operaciones.

### ⚠️ Evaluación: docs/14-ARCHITECTURE-DECISIONS/ (ADR)

**Estado:** Carpeta existe con solo README; no hay ADRs.

**Recomendación:** Mantener carpeta. Añadir plantilla ADR y al menos un ADR de ejemplo (p. ej. multi-tenant, eliminación API v1) migrando decisiones ya descritas en fundamentos o referencia.

### No crear: docs/17-COMPLIANCE-AUDIT/

No hay volumen de documentación de compliance/auditoría regulatoria; SECURITY.md en raíz y referencias en documentación existente son suficientes. Si en el futuro se añade documentación ISO/SOC, valorar subcarpeta bajo docs/ o ampliar SECURITY.md.

---

## Propuesta final de estructura (resumen)

- **Raíz:** README, CHANGELOG, ROADMAP, TECH_DEBT, SECURITY (sin cambios).
- **docs/:** 00-OVERVIEW, 01–15, 11-DEPLOYMENT/, 12-TROUBLESHOOTING/, 13-POSTMORTEMS/, 14-ARCHITECTURE-DECISIONS/ (sin cambios estructurales).
- **docs/16-OPERATIONS/:** Crear solo cuando exista contenido (backup, mantenimiento, escalado).
- **Carpetas por dominio:** fundamentos, instrucciones, API-references, produccion, pedidos, inventario, catalogos, recepciones-despachos, sistema, utilidades, referencia, ejemplos, frontend, etiquetas, por-hacer — **mantener**; 00-OVERVIEW y 01–15 enlazan a ellas.
- **Integración:** Contenido de 00_ POR IMPLEMENTAR integrar en 01-SETUP e instrucciones (FASE 5 checklist).
- **Auditoría:** INVENTORY, CLASSIFICATION_MATRIX, DOCUMENTATION_MAPPING_MATRIX, GAPS_ANALYSIS, DOCUMENTATION_AUDIT_REPORT, CURRENT_STATE_SNAPSHOT — mantener en docs/.

---

**Siguiente paso:** FASE 5 — [DOCUMENTATION_RESTRUCTURING_CHECKLIST.md](./DOCUMENTATION_RESTRUCTURING_CHECKLIST.md).
