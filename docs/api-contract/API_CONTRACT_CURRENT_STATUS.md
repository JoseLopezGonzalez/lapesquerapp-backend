---
title: Estado actual del contrato API — resumen de 1 minuto
updated: 2026-08-02
---

# Estado actual del contrato API

**Estado general**: Infraestructura del contrato (Scribe, dos configs, CI, comandos
`contract:publish/check`) ya implementada y verificada por lectura de código. No verificada por
ejecución real en esta sesión (entorno sin `vendor/`, sin MySQL). Deuda de negocio conocida (39
modelos con `toArrayAssoc()`, paginación no determinista en `OrderListService`, CRM/Estadísticas
sin Resources) sigue sin resolver, tal como se documentó al implementar el pipeline.

**Fase actual**: Fase 0 — Activación real (aún no iniciada).

**Último trabajo realizado**: 2026-08-02 — creación del plan maestro
(`docs/api-contract/API_CONTRACT_MASTER_PLAN.md`), verificación de la deuda contractual contra el
código actual, hallazgo nuevo de un bug de casing en `IncotermResource` (API-CONTRACT-007) dentro
del propio módulo piloto recomendado (Catálogos).

**Bloqueos**: Ninguno de negocio. Bloqueo de *entorno* para el siguiente paso: se necesita un
entorno con `composer install` + MySQL para ejecutar `composer contract:test/update/verify` y
confirmar que el pipeline funciona de extremo a extremo (no se pudo hacer en la sesión de
planificación). D13 (estrategia de app móvil) está pendiente de decisión de negocio y bloquea el
detalle de la Fase 8.

**Próxima acción**: Ejecutar Fase 0 (§6 y "Próxima acción recomendada" del plan maestro) — instalar
dependencias, generar el contrato en un entorno real, y confirmar `composer contract:test/update/
verify` en verde.

**Plan maestro**: [`docs/api-contract/API_CONTRACT_MASTER_PLAN.md`](./API_CONTRACT_MASTER_PLAN.md)
