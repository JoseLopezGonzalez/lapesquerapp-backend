Optimiza el prompt que te proporcione para reducir el número de tokens sin perder semántica ni precisión.

Proceso:
1. Analiza el prompt original e identifica: redundancias, instrucciones duplicadas, ejemplos innecesarios, verbosidad.
2. Produce una versión comprimida que preserve exactamente la intención.
3. Muestra la comparación:
   - Tokens estimados original: N
   - Tokens estimados optimizado: M
   - Reducción: X%
4. Lista los cambios realizados (qué eliminaste o comprimiste y por qué).

Reglas:
- No elimines restricciones de seguridad ni contexto de dominio crítico.
- Prefiere sustantivos precisos sobre frases descriptivas largas.
- Elimina fórmulas de cortesía y relleno ("Por favor, podrías...", "Sería genial que...").
- Convierte listas largas de ejemplos en uno representativo + "etc."
- Mantén instrucciones negativas explícitas (son difíciles de recuperar de forma implícita).

Los prompts de auditoría del proyecto están en `.claude/` (archivos 12-14). Puedes optimizarlos si el usuario los pega.

Entrada: $ARGUMENTS (si está vacío, pide al usuario que pegue el prompt a optimizar)
