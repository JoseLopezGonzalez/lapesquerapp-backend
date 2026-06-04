Dado lo que el usuario quiere hacer, recomienda qué skill usar de las disponibles en este proyecto.

Skills disponibles en `.claude/commands/`:

| Skill | Cuándo usarla |
|-------|---------------|
| `/caveman` | Entender código o arquitectura compleja en lenguaje simple |
| `/humanizer` | Convertir texto técnico (logs, errores, docs) a lenguaje para no-técnicos |
| `/napkin` | Generar un diagrama rápido de flujo, estados, entidades o secuencia |
| `/token-optimizer` | Reducir el tamaño de un prompt sin perder su significado |
| `/find-skills` | Esta misma — encontrar qué skill usar |
| `/skill-creator` | Crear una nueva skill para el proyecto |
| `/task-workflow` | Ejecutar el flujo completo de evolución de un bloque del ERP (análisis → plan → implementación → log) |

Proceso:
1. Lee la descripción de la tarea en $ARGUMENTS.
2. Identifica la skill más adecuada (puede ser más de una, en orden de relevancia).
3. Explica en 1-2 frases por qué esa skill encaja con la tarea.
4. Si ninguna encaja perfectamente, sugiere `/skill-creator` para crear una nueva.

Entrada: $ARGUMENTS
