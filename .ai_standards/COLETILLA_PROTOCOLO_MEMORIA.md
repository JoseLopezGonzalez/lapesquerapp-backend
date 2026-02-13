# COLETILLA ESTÁNDAR - PROTOCOLO DE MEMORIA PARA TODOS LOS PROMPTS

> **Copia y pega esta sección al FINAL de CUALQUIER prompt que crees para agentes IA**

---

## 🧠 PROTOCOLO DE MEMORIA DE TRABAJO ESTÁNDAR

### ⚡ REQUISITO PREVIO
Antes de procesar esta tarea, **CARGA Y REVISA**:
```
Archivo: .ai_standards/AGENT_MEMORY_SYSTEM.md
Propósito: Sistema estandarizado de gestión de contexto y memoria
```

Si el archivo NO existe, el agente debe crear la estructura desde cero.

---

### 📁 ESTRUCTURA AUTOMÁTICA

El agente **SIEMPRE** creará esta estructura:

```
.ai_work_context/
└── [TIMESTAMP_SESION]/        ← Carpeta única por sesión
    ├── 00_working/            ← Temporal (se BORRA al finalizar)
    │   ├── active_task.md
    │   ├── context_stack.md
    │   ├── decisions_pending.md
    │   └── session_notes.md
    │
    ├── 01_analysis/           ← Se ENTREGA al usuario
    ├── 02_planning/
    ├── 03_execution/
    ├── 04_logs/
    └── 05_outputs/            ← Archivos finales entregables
```

---

### ✅ PROTOCOLO DE DECISIONES

| Tipo | Acción | Documentación |
|------|--------|---------------|
| **Automáticas** | Ejecutar sin preguntar | `04_logs/execution_timeline.md` |
| **Críticas** | PAUSAR y preguntar | `00_working/decisions_pending.md` |

**CRÍTICAS** = Ambigüedad de especificación, contexto de negocio, datos sensibles, trade-offs

---

### 📝 LOGGING OBLIGATORIO

Después de CADA SECCIÓN completada, actualiza:

```markdown
## 🕐 [HH:MM:SS] - [Nombre Sección]
**Status**: ✅ Completado
**Documentos creados**: [listar]
**Próximo**: [automático/crítica]
```

---

### 🎯 CHECKLIST DE FINALIZACIÓN

✅ Completar ANTES de entregar:

```
- [ ] Documentación en 01_/02_/03_/04_/ (sin duplicación)
- [ ] 00_working/ está BORRADO (solo temporal)
- [ ] FINAL_REPORT.md creado en 05_outputs/
- [ ] Todos los documentos tienen ESTADO y TIMESTAMP
- [ ] Log de ejecución completo en 04_logs/
- [ ] Errores documentados (si los hay)
```

---

### 🚀 SECUENCIA INICIAL

El agente comienza con:

```
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🤖 AGENTE INICIADO - PROTOCOLO DE MEMORIA
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

✅ CARGAS COMPLETADAS:
   ✓ AGENT_MEMORY_SYSTEM.md
   ✓ Contexto del proyecto
   ✓ Estándares aplicables

📁 ESTRUCTURA CREADA:
   .ai_work_context/[SESSION_ID]/
   ├── 00_working/ (notas temporales)
   ├── 01_/02_/03_/04_/05_ (documentación)

🎯 CONFIGURACIÓN:
   • Decisiones técnicas: AUTOMÁTICAS
   • Ambigüedades: CRÍTICAS (preguntar)
   • Max contexto activo: 3-5 documentos
   • Logs: Actualizar cada 15-30 min

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Listo para procesar la tarea.
¿Cuál es tu request?
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
```

---

### 🔴 CUANDO ENCUENTRA UNA CRÍTICA

Guarda en `00_working/decisions_pending.md` y presenta así:

```markdown
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🔴 CRÍTICA - Decisión Requerida
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

**Contexto**: [Explicar brevemente]

**Opciones disponibles**:
  A) [Opción 1] → Beneficio: X | Desventaja: Y
  B) [Opción 2] → Beneficio: X | Desventaja: Y
  C) [Opción 3] → Beneficio: X | Desventaja: Y

**Mi recomendación**: [Opción + justificación breve]

**Próximo paso**: Espero tu selección (A / B / C)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
```

---

### 📊 REPORTE FINAL OBLIGATORIO

Al terminar, **SIEMPRE** generar: `05_outputs/FINAL_REPORT.md` (resumen, deliverables, validaciones, documentación de trabajo).

---

**Referencia rápida**: Cargue `.ai_standards/AGENT_MEMORY_SYSTEM.md` | Estructura `.ai_work_context/[TIMESTAMP]/` | Outputs `05_outputs/`
