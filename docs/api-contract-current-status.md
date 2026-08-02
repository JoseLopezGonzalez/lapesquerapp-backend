---
title: Estado actual del contrato API — resumen de 1 minuto
updated: 2026-08-02
---

# Estado actual del contrato API

**Estado general**: Infraestructura del contrato (Scribe, dos configs, CI, comandos
`contract:publish/check`) implementada y **verificada por ejecución real** de extremo a extremo. Los
3 bugs de infraestructura de la primera sesión de Fase 0 (Pint, `ProspectFactory::new()`,
`OpenApiContractDiffer` sin soporte de nullable OpenAPI 3.1) ya están en `origin/main`. Una segunda
sesión confirmó que el fix del differ resuelve realmente el ruido de nulabilidad (API-CONTRACT-004
ya no reproduce) y que el job `api-contract` de CI nunca había llegado a ejecutarse (siempre
`skipped` por depender de la suite completa). Deuda de negocio conocida sigue sin resolver: 39
modelos con `toArrayAssoc()`, paginación no determinista en `OrderListService` (API-CONTRACT-001,
ahora confirmado como bloqueo real de CI), CRM/Estadísticas sin Resources.

**Fase actual**: Fase 0 — Activación real (🔄 en progreso; mecanismo del pipeline confirmado en
verde, pendiente cerrar el círculo de CI y decidir API-CONTRACT-001 — ver bloqueos).

**Último trabajo realizado**: 2026-08-02 (segunda sesión). Se confirmó que el push de los commits
de la sesión anterior ya había ocurrido (contrario a lo que este documento decía). El run de CI
para `8c128994` terminó en `failure`: el job "Tests + Pint" falla por API-CONTRACT-016 (70 tests
preexistentes, ajenos al contrato), y como `api-contract` dependía de él (`needs: tests`), nunca
llegó a ejecutarse — ni en este run ni en ninguno anterior de `main`. Con aprobación del usuario, se
desacopló `api-contract` de `tests` en `.github/workflows/api-contract.yml`. Además, se replicó el
job de contrato en local contra una BD desechable nueva (`contract_fixture_20260802`): el diff de
nulabilidad de API-CONTRACT-004 ya no aparece (el fix del differ funciona); el único diff restante
son 23 `BREAKING` concentrados en `GET /api/v2/orders`, causa raíz confirmada como API-CONTRACT-001.
Detalle completo: `docs/audits/laravel-evolution-log.md` → entrada "[2026-08-02] API Contract —
Fase 0: CI desacoplada de la suite completa, API-CONTRACT-004 resuelto en generación limpia".

**Bloqueos**:
- De negocio: D13 (estrategia de app móvil) sigue pendiente. API-CONTRACT-001 (no determinismo de
  `OrderListService`) ha pasado de "deuda documentada" a "bloqueo empírico confirmado" para que el
  job de contrato en CI quede en verde de forma reproducible (CI usa `--fail-on-any`).
- Operativo: el cambio de workflow (desacoplar `api-contract` de `tests`) está hecho en el árbol de
  trabajo, pendiente de confirmación del usuario para commitear y pushear. Sin ese push no se puede
  observar el resultado real del job ya desacoplado.
- Contractual: `public/openapi/frontend.yaml` sigue sin republicar — la generación limpia
  disponible ahora mismo solo introduciría el ruido de API-CONTRACT-001, no una mejora real.
- Fuera de alcance: API-CONTRACT-016 (70 tests preexistentes fallando, ajenos al contrato) sigue
  abierto, sin tocar; requiere tratamiento por bloque vía `evolution-workflow`/`/task-workflow`.

**Próxima acción**: Confirmar con el usuario el `git push` del cambio de workflow, verificar el
resultado real del job `api-contract` (es esperable que siga en rojo por API-CONTRACT-001, lo cual
sería correcto, no un fallo de esta sesión), y decidir explícitamente el tratamiento de
API-CONTRACT-001 antes de declarar Fase 0 completada.

**Plan maestro**: [`docs/api-contract-master-plan.md`](./api-contract-master-plan.md)
**Decisiones arquitectónicas**: [`docs/architecture-decisions/`](./architecture-decisions/readme.md) (ADRs 0003-0008)
