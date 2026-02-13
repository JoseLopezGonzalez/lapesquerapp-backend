# Estándares IA – PesquerApp Backend

Esta carpeta contiene el **sistema de memoria de trabajo** para agentes IA (Cursor, etc.). Los archivos aquí son la **referencia permanente** que el agente debe cargar y seguir en tareas complejas.

## Contenido

| Archivo | Uso |
|--------|-----|
| **PROTOCOLO_PARA_CHAT.md** | **Para el chat:** Incluye este archivo en el chat y escribe después tu tarea. El agente aplica toda la metodología solo con leerlo. |
| **AGENT_MEMORY_SYSTEM.md** | Documento maestro completo (tres capas, ciclo de vida, formato de docs). Referencia detallada. |
| **COLETILLA_PROTOCOLO_MEMORIA.md** | Texto para copiar al final de prompts; obliga al agente a crear `.ai_work_context/`, documentar y generar reporte final. |
| **QUICK_START_GUIDE.md** | Guía rápida de uso en Cursor y comandos útiles. |

## Uso en Cursor (recomendado)

1. En el chat, incluye: **`@.ai_standards/PROTOCOLO_PARA_CHAT.md`**
2. Escribe a continuación tu tarea (qué quieres que haga el agente).
3. El agente aplica la metodología y ejecuta tu tarea.

Alternativa (si prefieres el documento largo):
- **Tareas complejas o multi-paso**: Di algo como *“Aplica el protocolo de .ai_standards/AGENT_MEMORY_SYSTEM.md”* o referencia `@.ai_standards/AGENT_MEMORY_SYSTEM.md`.
- **Prompts propios**: Añade al final la sección “PROTOCOLO DE MEMORIA DE TRABAJO” de `COLETILLA_PROTOCOLO_MEMORIA.md`.
- La regla en `.cursor/rules/` recuerda al agente usar este sistema cuando sea apropiado.

## Dónde trabaja el agente

- **Memoria de sesión**: `.ai_work_context/[TIMESTAMP]/` (creada por el agente en cada sesión).
- **Entregables y reporte**: `.ai_work_context/[TIMESTAMP]/05_outputs/`, incluyendo `FINAL_REPORT.md`.

En la documentación del proyecto: `docs/README.md` y `docs/00-OVERVIEW.md` enlazan aquí.
