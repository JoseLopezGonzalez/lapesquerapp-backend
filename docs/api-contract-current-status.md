---
title: Estado actual del contrato API — resumen de 1 minuto
updated: 2026-08-02
---

# Estado actual del contrato API

**Estado general**: Infraestructura del contrato (Scribe, dos configs, CI, comandos
`contract:publish/check`) implementada y **verificada en CI real de extremo a extremo**, no solo en
local. Tras corregir un bug de env vars en el workflow (varios pasos no re-declaraban
`DB_CONNECTION`/`DB_PORT`/`DB_USERNAME`/`DB_PASSWORD` y heredaban valores incorrectos de
`.env.example`) y sanear ese mismo fichero (tenía un host/puerto/password que parecía una credencial
real, pendiente de confirmación/rotación por el usuario), el job `api-contract` de CI llegó vivo
por primera vez hasta el paso final (`contract:check --fail-on-any`), que falla por
API-CONTRACT-001 — ya no por infraestructura. Deuda de negocio conocida sigue sin resolver: 39
modelos con `toArrayAssoc()`, paginación no determinista en `OrderListService` (API-CONTRACT-001,
ahora confirmado dos veces —local y CI— como bloqueo real), CRM/Estadísticas sin Resources.

**Fase actual**: Fase 0 — Activación real (🔄 en progreso; mecanismo confirmado en verde en CI real,
pendiente solo una decisión de negocio sobre API-CONTRACT-001 para poder cerrarla).

**Último trabajo realizado**: 2026-08-02 (segunda sesión, continuación). Resumen cronológico:

1. Se confirmó que el push de los 3 commits de la sesión anterior ya había ocurrido.
2. El run de CI para ese commit falló: el job `api-contract` seguía `skipped` (dependía de `tests`,
   que falla por API-CONTRACT-016, deuda no relacionada). Con aprobación del usuario, se desacopló.
3. Al re-ejecutar, el job `api-contract` corrió por primera vez pero falló en 1s en "Central
   migrations" — se encontró que `.env.example` tenía un host/puerto/password que parecía una
   credencial real (no un placeholder), y que 4 pasos del workflow no declaraban todas las
   variables de conexión, heredando esos valores incorrectos. Se saneó `.env.example` y se hizo
   cada paso autocontenido.
4. Con ese fix pusheado, el run de CI llegó vivo hasta `contract:check --fail-on-any`, que falla ahí
   — consistente con la verificación local (23 `BREAKING` en `GET /api/v2/orders`, API-CONTRACT-001;
   API-CONTRACT-004 ya no reproduce, el fix del differ de la sesión anterior funciona).

Detalle completo en `docs/audits/laravel-evolution-log.md` (3 entradas nuevas de esta sesión, todas
tituladas "API Contract — Fase 0: ...").

**Bloqueos**:
- De negocio: D13 (estrategia de app móvil) sigue pendiente. API-CONTRACT-001 (no determinismo de
  `OrderListService`) es ahora el único bloqueo confirmado para que el job de contrato en CI quede
  en verde de forma reproducible.
- Fuera de este repositorio: confirmar si la credencial que tenía `.env.example`
  (`94.143.137.84:3308`) era real y, si lo era, rotarla — no verificable ni accionable desde este
  agente.
- Contractual: `public/openapi/frontend.yaml` sigue sin republicar — la generación limpia
  disponible ahora mismo solo introduciría el ruido de API-CONTRACT-001, no una mejora real.
- Fuera de alcance: API-CONTRACT-016 (70 tests preexistentes fallando, ajenos al contrato) sigue
  abierto, sin tocar; requiere tratamiento por bloque vía `evolution-workflow`/`/task-workflow`.

**Próxima acción**: Decidir con el usuario el tratamiento de API-CONTRACT-001 (adelantar su
resolución, fijar el parámetro de ejemplo que usa Scribe para `GET /v2/orders`, o aceptar el ruido
documentándolo) — es el único paso que falta para declarar Fase 0 completada.

**Plan maestro**: [`docs/api-contract-master-plan.md`](./api-contract-master-plan.md)
**Decisiones arquitectónicas**: [`docs/architecture-decisions/`](./architecture-decisions/readme.md) (ADRs 0003-0008)
