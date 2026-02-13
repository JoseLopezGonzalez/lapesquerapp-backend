# SISTEMA PROFESIONAL DE MEMORIA DE TRABAJO PARA AGENTES IA
## v1.0 - Working Memory Management Framework

---

## 📋 DOCUMENTO REFERENCIA
Este documento debe estar **SIEMPRE DISPONIBLE** para consulta por el agente IA en Cursor cuando se pase cualquier prompt.

**Propósito**: Estandarizar cómo los agentes gestionan contexto, memoria y documentación sin perder coherencia en tareas complejas.

**Basado en**: Investigación 2025 de context engineering, AWS AgentCore Memory, mem0 framework, y mejores prácticas de OpenAI Agents SDK.

---

## 🧠 TRES CAPAS DE MEMORIA

### 1️⃣ MEMORIA CORTO PLAZO (Short-Term / Working Memory)
**Propósito**: Mantener contexto ACTIVO de la tarea en progreso

**Características**:
- Válida SOLO dentro de una sesión/ejecución
- Máximo 3-5 documentos vivos simultáneamente
- Se BORRA al final de la sesión (o cuando la tarea termina)
- Analogía: Notas pegadas en tu escritorio hoy

**Ubicación**: `.ai_work_context/00_working/`

**Contenido típico**:
```
00_working/
├── active_task.md          # Tarea actual EN PROGRESO
├── context_stack.md        # Pila de contextos (último = actual)
├── decisions_pending.md    # CRÍTICAS esperando respuesta
└── session_notes.md        # Notas rápidas de ejecución
```

---

### 2️⃣ MEMORIA MEDIO PLAZO (Mid-Term / Reference Memory)
**Propósito**: Documentación que EVOLUCIONA durante la tarea

**Características**:
- Persiste mientras se ejecuta la tarea general
- Se actualiza/refina con cada iteración
- Se ENTREGA al usuario como parte del output
- Analogía: Documentación del proyecto vivo

**Ubicación**: `.ai_work_context/01_analysis/`, `02_planning/`, `03_execution/`, etc.

**Contenido típico**:
```
01_analysis/
├── schema_mapping.md          # Mapeo inicial → actualizaciones
├── data_patterns.md           # Patrones detectados → validaciones
└── entity_relationships.md    # Relaciones → refinamientos

02_planning/
├── execution_plan.md          # Plan inicial → ajustes
└── validation_matrix.md       # Matriz de validación

03_execution/
├── implementation_log.md      # Log detallado de qué se hizo
├── implementation_checklist.md # Progreso ✓
└── quality_assurance.md       # Validaciones y resultados
```

---

### 3️⃣ MEMORIA LARGO PLAZO (Long-Term / Reference)
**Propósito**: Documentación REUTILIZABLE entre sesiones

**Características**:
- Persiste permanentemente en tu proyecto
- Se consulta pero NO se modifica durante ejecución
- Se actualiza SOLO después de validación manual
- Analogía: Manuales y estándares de la empresa

**Ubicación**: `.ai_standards/` (fuera del contexto de trabajo)

**Contenido típico**:
```
.ai_standards/
├── AGENT_MEMORY_SYSTEM.md          # ← ESTE DOCUMENTO
├── CURSOR_AGENT_BEST_PRACTICES.md  # Patrones probados
├── DOMAIN_RULES_PESQUERAPP.md      # Reglas de negocio PesquerApp
└── PROMPT_TEMPLATES/               # Templates para nuevos prompts
    ├── seeder_generation.md
    ├── feature_implementation.md
    └── bug_fixing.md
```

---

## 🎯 CICLO DE VIDA DE LA MEMORIA

```
INICIO DE SESIÓN
       ↓
1. Agente CARGA memoria largo plazo (.ai_standards/)
2. Agente CREA carpeta temporal: .ai_work_context/[TIMESTAMP_SESION]/
3. Agente INICIALIZA: 00_working/ + 01_analysis/
       ↓
DURANTE LA EJECUCIÓN
       ↓
4. Agente ESCRIBE en 00_working/ (actualiza cada paso)
5. Agente ACTUALIZA 01_/02_/03_/ (documentación que evoluciona)
6. Cuando encuentra CRÍTICA: PAUSA y pregunta (guarda en decisions_pending.md)
7. Usuario RESPONDE → Agente RESUELVE y continúa
       ↓
FINALIZACIÓN
       ↓
8. Agente CONSOLIDA documentación (02_/03_/)
9. Agente GENERA REPORTE FINAL
10. Agente BORRA 00_working/ (cosas rápidas/temporales)
11. Agente ENTREGA carpeta .ai_work_context/[TIMESTAMP]/ + OUTPUTS
12. Usuario VALIDA y ARCHIVA en .ai_standards/ si es reutilizable
```

---

## 📁 ESTRUCTURA DE CARPETAS ESTÁNDAR

```
proyecto_pesquerapp/
│
├── .ai_standards/                      ← MEMORIA LARGO PLAZO (reutilizable)
│   ├── AGENT_MEMORY_SYSTEM.md         # ← Este documento
│   ├── DOMAIN_RULES_PESQUERAPP.md     # Reglas de negocio
│   ├── CURSOR_BEST_PRACTICES.md       # Patrones de Cursor
│   └── PROMPT_TEMPLATES/               # Templates para nuevos prompts
│
├── .ai_work_context/                   ← MEMORIA CORTO/MEDIO PLAZO (temporal)
│   └── [TIMESTAMP_SESION_20250213_1430]/
│       │
│       ├── 00_working/                 ← BORRAR al finalizar
│       │   ├── active_task.md
│       │   ├── context_stack.md
│       │   ├── decisions_pending.md
│       │   └── session_notes.md
│       │
│       ├── 01_analysis/
│       ├── 02_planning/
│       ├── 03_execution/
│       ├── 04_logs/
│       └── 05_outputs/                 ← ENTREGABLES FINALES
│
└── [proyecto actual]/
```

---

## 🔄 PROTOCOLO DE DECISIÓN CRÍTICA vs AUTOMÁTICA

### ✅ AUTOMÁTICAS (Ejecutar sin intervención)
- Análisis técnico (sintaxis, estructura, patrones)
- Generación de código que sigue estándares establecidos
- Validaciones contra reglas documentadas
- Transformación de datos según patrón conocido
- Cálculos matemáticos/lógicos
- Creación de estructura de archivos/carpetas

### 🔴 CRÍTICAS (PAUSAR y preguntar)
Guardar en `00_working/decisions_pending.md` y preguntar:
- Ambigüedad en especificación del usuario
- Conflicto entre requisitos
- Necesidad de contexto de negocio no documentado
- Decisión con implicaciones de seguridad/datos sensibles
- Elección entre opciones válidas con trade-offs

**Formato de Pregunta Crítica**: ver `COLETILLA_PROTOCOLO_MEMORIA.md` en esta carpeta.

---

## 📝 FORMATO ESTÁNDAR DE DOCUMENTOS

Todos los documentos en carpeta de trabajo deben incluir:
- **Estado**: [🟢 Activo | 🟡 En Revisión | 🔴 Crítica | ✅ Completado]
- **Última actualización**: [AUTO TIMESTAMP]
- **Sesión**: [AUTO SESSION_ID]
- **Versión**: [AUTO]
- Tabla **Histórico de Cambios** al final

---

## ⏱️ PATRÓN DE LOGS

**Archivo**: `04_logs/execution_timeline.md` — actualizar después de cada sección completada.

---

## 🎯 CHECKLIST DE GESTIÓN DE MEMORIA

✅ Al INICIO: Cargar .ai_standards/, crear .ai_work_context/[TIMESTAMP]/, inicializar 00_working/ y 01_/02_/03_/04_/05_/, crear active_task.md.

✅ DURANTE: Actualizar active_task.md, documentar en execution_timeline.md, si CRÍTICA → decisions_pending.md.

✅ Al FINALIZAR: Consolidar documentación, borrar 00_working/, crear FINAL_REPORT.md en 05_outputs/, entregar carpeta.

---

## 💡 REGLAS ESTRICTAS

1. **NO Duplicación**: Cada información única en UN SOLO lugar; referenciar con links.
2. **Límite de contexto activo**: Máximo 3-5 documentos en 00_working/ simultáneamente.
3. **Temporal vs Permanente**: Borrar solo 00_working/ al final; el resto se entrega.
4. **Control de versión**: Tabla de cambios en documentos importantes.

---

## 🚨 MANEJO DE ERRORES

**Archivo**: `04_logs/errors_and_solutions.md` — documentar descripción, causa raíz, solución, documentos afectados, status.

---

## 📊 REPORTE FINAL

**SIEMPRE** al terminar: `05_outputs/FINAL_REPORT.md` con resumen ejecutivo, objetivos cumplidos, deliverables, críticas resueltas, validaciones, advertencias, próximos pasos.

---

## 🎬 PROTOCOLO DE INICIO (Para CADA prompt nuevo)

El agente SIEMPRE comienza con:
1. ✅ Cargar AGENT_MEMORY_SYSTEM.md (este documento)
2. ✅ Crear carpeta: .ai_work_context/[TIMESTAMP_SESION]/
3. ✅ Inicializar estructura de directorios
4. ✅ Listo para recibir la tarea. ¿Cuál es la tarea?

---

## 🔗 INTEGRACIÓN CON TUS PROMPTS

Añadir al final de prompts que usen este sistema:

```markdown
---
## 📌 PROTOCOLO DE MEMORIA DE TRABAJO
Antes de ejecutar, revisa: .ai_standards/AGENT_MEMORY_SYSTEM.md
✅ Crearás carpeta: .ai_work_context/[TIMESTAMP]/
✅ Documentarás en: 01_analysis/, 02_planning/, 03_execution/, etc.
✅ Críticas: Preguntarás con opciones claras
✅ Al finalizar: FINAL_REPORT.md y entregar documentación
---
```

---

**Última actualización**: Febrero 2026 | **Versión**: 1.0 | **Estado**: ✅ Listo para producción
