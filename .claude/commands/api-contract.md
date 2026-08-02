Retoma o ejecuta una fase del plan maestro del contrato API (`docs/api-contract-master-plan.md`),
siguiendo su protocolo para agentes (§10). Esta skill es un atajo operativo — el detalle real de
cada fase (objetivo, alcance, pasos, criterios de aceptación) vive en el plan, no aquí; no lo
dupliques.

Fase objetivo: $ARGUMENTS (vacío = continuar desde "Próxima acción recomendada" del plan; un
número/nombre de fase solo si el usuario pide explícitamente retomar o adelantar una fase distinta)

---

## Antes de empezar

1. Lee `docs/api-contract-master-plan.md` completo — no solo la fase objetivo. El estado (§2), la
   deuda (§4) y la clasificación de módulos (§5) condicionan cómo ejecutarla.
2. Revisa `git status`. Si el árbol de trabajo no coincide con lo que dice el registro de
   ejecución (plan §8 + la entrada correspondiente en `docs/audits/laravel-evolution-log.md`),
   detente y reconcilia antes de continuar — probablemente alguien avanzó sin actualizar el
   registro, o al revés.
3. Si $ARGUMENTS está vacío, la fase a ejecutar es la de "Próxima acción recomendada" (última
   sección del plan). Si $ARGUMENTS pide otra fase, confírmalo explícitamente con el usuario antes
   de continuar (STEP 4 del protocolo: no adelantar fases sin necesidad real).
4. Lee las ADRs que esa fase referencia como dependencia (`docs/architecture-decisions/0003-*.md`
   a `0008-*.md`, o posteriores) — no reinventes una decisión que ya está tomada ahí.

## Ejecución

- Trabaja solo en el alcance de la fase indicada (§6 del plan: Objetivo, Alcance, Fuera de
  alcance, Pasos de implementación, Riesgos). No adelantes trabajo de fases posteriores "ya que
  estás".
- Respeta las convenciones ya vigentes del proyecto (`CLAUDE.md` §5-7, agente `laravel-expert`):
  Controllers thin, Services, Resources explícitas, multi-tenant seguro, transacciones donde
  corresponda.
- Si el trabajo de esta fase coincide con un bloque del CORE v1.0 (`CLAUDE.md` §8) que también
  está bajo el workflow de evolución de bloques: coordina ambos seguimientos. El rating Laravel
  del bloque vive en `docs/audits/laravel-evolution-log.md` (vía `/task-workflow` o el agente
  `evolution-workflow`); el estado de su deuda contractual vive en el inventario de este plan
  (§4). Actualiza los dos si tocas ambos, no solo uno.
- Antes de dar por terminada la fase, ejecuta lo que aplique: `composer contract:test`,
  `composer contract:update`, `composer contract:verify`, y la suite Feature del módulo tocado.
  No marques una fase como `Completada` sin evidencia verificable de estos comandos.

## Al terminar

Actualiza, en este orden:

1. `docs/api-contract-master-plan.md`: `Estado` de la fase en §6, filas de §4/§5 que hayan
   cambiado, y reescribe "Próxima acción recomendada" (última sección) para la siguiente fase.
2. `docs/api-contract-current-status.md`: fase actual, último trabajo, bloqueos.
3. `docs/audits/laravel-evolution-log.md`: nueva entrada (formato usado el 2026-08-02, título
   `API Contract — {resumen}`), con una fila de índice nueva enlazada en el plan §8.
4. Si surge una decisión arquitectónica durable nueva (no solo de secuenciación u orden de
   ejecución): crea la siguiente ADR (`docs/architecture-decisions/0009-*.md` en adelante)
   siguiendo `0000-adr-template.md`, y referénciala desde el plan §3.
5. Si algún cambio afecta al frontend (breaking o no): `docs/frontend-integration/backend-api-changes.md`
   y, si aplica, `FRONTEND_OPENAPI_HANDOFF.md`.

No borres deuda pendiente de §4 para simplificar el documento — cambia su `Estado`, no la
elimines. Ver `docs/api-contract-master-plan.md` §10 para el protocolo completo de 12 reglas; esta
skill no lo sustituye, solo lo pone en marcha.
