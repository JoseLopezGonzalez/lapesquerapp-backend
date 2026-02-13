# Protocolo de memoria de trabajo — Instrucciones para el agente

**Si estás leyendo este documento:** debes aplicar la metodología siguiente en esta sesión. El usuario te dará después la tarea concreta; tú la ejecutas siguiendo estas reglas.

---

## 1. Al iniciar esta sesión

1. Crea la carpeta de sesión: **`.ai_work_context/[TIMESTAMP]/`** (ej. `20260213_1430`).
2. Dentro, crea estas subcarpetas y archivos:

```
.ai_work_context/[TIMESTAMP]/
├── 00_working/            ← Temporal (BORRAR al finalizar)
│   ├── active_task.md     ← Tarea actual, fase, próximo paso
│   ├── context_stack.md
│   ├── decisions_pending.md
│   └── session_notes.md
├── 01_analysis/           ← Análisis que evoluciona (esquemas, patrones, requisitos)
├── 02_planning/           ← Plan de ejecución, matrices de validación
├── 03_execution/          ← Log de implementación, checklist, resultados
├── 04_logs/               ← execution_timeline.md, errors_and_solutions.md
└── 05_outputs/            ← Entregables finales + FINAL_REPORT.md
```

3. Escribe en `00_working/active_task.md` la tarea que te indique el usuario (o "Pendiente de indicación") y confirma en el chat que la estructura está creada y que esperas la tarea.

---

## 2. Durante la ejecución

- **Documenta** en cada fase: análisis en `01_analysis/`, plan en `02_planning/`, lo que hagas en `03_execution/`, y en `04_logs/execution_timeline.md` después de cada bloque de trabajo.
- **Actualiza** `00_working/active_task.md` con la fase actual y el próximo paso.
- **No dupliques** información: una sola fuente por tema; en otros sitios solo referencias.

### Decisiones

| Tipo | Qué hacer |
|------|-----------|
| **Automáticas** | Análisis técnico, código según estándares, validaciones documentadas, estructura de archivos. **Ejecutar sin preguntar.** |
| **Críticas** | Ambigüedad en lo que pide el usuario, conflicto entre requisitos, negocio no documentado, seguridad/datos sensibles, elección entre opciones con trade-offs. **PAUSAR:** escribe en `00_working/decisions_pending.md`, presenta opciones (A/B/C) con pros/contras, da tu recomendación y espera la respuesta del usuario antes de seguir. |

### Logging

Tras cada sección completada, añade en `04_logs/execution_timeline.md` una entrada tipo:

```markdown
## 🕐 [HH:MM] - [Nombre sección]
**Status**: ✅ Completado
**Documentos creados**: [listar]
**Próximo**: [automático / crítica]
```

Si hay errores: documéntalos en `04_logs/errors_and_solutions.md` (descripción, causa, solución, estado).

---

## 3. Al finalizar

1. **Consolida** la documentación (sin duplicar).
2. **Borra** la carpeta `00_working/` (solo esa; el resto se entrega).
3. **Genera** `05_outputs/FINAL_REPORT.md` con:
   - Resumen ejecutivo (qué se logró)
   - Objetivos cumplidos
   - Deliverables (archivos y docs en 01_/02_/03_/04_/05_)
   - Críticas resueltas (si las hubo)
   - Validaciones realizadas
   - Advertencias (si las hay)
   - Próximos pasos sugeridos
4. **Indica** al usuario la ruta de la carpeta `.ai_work_context/[TIMESTAMP]/` y que el reporte está en `05_outputs/FINAL_REPORT.md`.

---

## 4. Resumen de reglas

- Una sola fuente de verdad por concepto; el resto referencias.
- Máximo 3–5 documentos “vivos” en uso a la vez en `00_working/`; si hace falta más, consolidar o pasar a 01_/02_/03_.
- Documentos importantes: incluir **Estado**, **Última actualización** y, si aplica, tabla de **Histórico de cambios**.

---

**Fin del protocolo.** Aplica lo anterior y ejecuta la tarea que el usuario indique en este chat (en el mismo mensaje o en los siguientes).
