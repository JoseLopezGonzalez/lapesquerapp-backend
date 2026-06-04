Crea una nueva skill para este proyecto siguiendo el patrón de las skills existentes en `.claude/commands/`.

Proceso:
1. Si $ARGUMENTS contiene nombre y descripción, úsalos directamente. Si no, pregunta:
   - Nombre de la skill (será el slash command: `/nombre`)
   - Qué debe hacer exactamente
   - Qué input espera (texto libre, código, argumentos específicos)
   - Contexto del proyecto que debe conocer

2. Genera el contenido del archivo `.md` siguiendo este patrón:
   - Descripción de qué hace en 1 línea
   - Reglas o restricciones claras
   - Contexto del proyecto relevante (si aplica)
   - Proceso numerado (si tiene pasos)
   - Línea final: `Entrada: $ARGUMENTS`

3. Guarda el archivo en `.claude/commands/{nombre}.md` usando la herramienta Write.

4. Confirma al usuario:
   - Ruta del archivo creado
   - Cómo invocarla: `/{nombre}`
   - Resumen de lo que hace

Convenciones del proyecto:
- Skills en español (el equipo es hispanohablante)
- Sin emojis salvo que el usuario los pida
- Contexto del ERP pesquero cuando sea relevante

Entrada: $ARGUMENTS (nombre y descripción de la nueva skill, o vacío para modo interactivo)
