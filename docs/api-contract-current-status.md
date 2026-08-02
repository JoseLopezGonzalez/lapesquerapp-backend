---
title: Estado actual del contrato API — resumen de 1 minuto
updated: 2026-08-02
---

# Estado actual del contrato API

**Estado general**: Infraestructura del contrato (Scribe, dos configs, CI, comandos
`contract:publish/check`) implementada y, por primera vez, **verificada por ejecución real** (no
solo por lectura de código). El pipeline en sí tenía 2 bugs que le impedían completar una ejecución
con éxito (ver "Último trabajo realizado"); ya corregidos. Deuda de negocio conocida (39 modelos con
`toArrayAssoc()`, paginación no determinista en `OrderListService`, CRM/Estadísticas sin Resources,
nulabilidad de relaciones sin política aplicada) sigue sin resolver.

**Fase actual**: Fase 0 — Activación real (🔄 en progreso; pipeline validado y corregido, contrato
aún sin republicar — ver bloqueos).

**Último trabajo realizado**: 2026-08-02 — ejecución de Fase 0. Se encontraron y corrigieron 3 bugs
que impedían validar el pipeline de extremo a extremo:

1. CI en rojo desde su activación por un fallo de Pint en ~80 ficheros nunca formateados (ajeno al
   contrato, pero bloqueaba que el job `api-contract` (`needs: tests`) llegara a ejecutarse).
2. `ProspectFactory::new()` colisionaba con el método estático de Eloquent `Factory::new()`,
   provocando fallos en cascada en la suite completa.
3. `OpenApiContractDiffer` no soportaba la sintaxis nullable de OpenAPI 3.1 (`type: [string, null]`)
   y fallaba con un fatal error en el primer campo nullable que comparaba — `composer
   contract:verify` no había completado una ejecución con éxito nunca hasta esta sesión.

Los 3 están corregidos y committeados (`9677ec0c`, `7b89ff0c`), **sin pushear todavía**. Detalle
completo: `docs/audits/laravel-evolution-log.md` → entrada "[2026-08-02] API Contract — Fase 0:
activación real, 3 bugs de infraestructura corregidos".

**Bloqueos**:
- De negocio: ninguno nuevo. D13 (estrategia de app móvil) sigue pendiente de decisión de negocio.
- Operativo: los commits de Fase 0 están en `main` local, pendientes de `git push` y de confirmar
  CI en verde en GitHub Actions (no verificable hasta que estén en el remoto).
- Contractual: una generación limpia de `composer contract:verify` (BD desechable, no la de
  desarrollo) produce 8 cambios BREAKING + 18 COMPATIBLE, casi todos por inestabilidad de
  nulabilidad de relaciones (`fieldOperator`, `externalProcessor`, `incoterm` — API-CONTRACT-004,
  ahora confirmado empíricamente). No se ha republicado `public/openapi/frontend.yaml` porque ese
  diff refleja ruido de fixture, no cambios de código deliberados.
- Nuevo hallazgo fuera de alcance: la suite completa de tests tiene ~66-68 fallos preexistentes, no
  relacionados con el contrato (Producción, CRM, Route Management, Order Statistics, Stock,
  Superadmin...). Documentado como API-CONTRACT-016; requiere tratamiento por bloque, no aquí.

**Próxima acción**: Confirmar con el usuario el `git push` de `9677ec0c`/`7b89ff0c` y verificar CI
en verde. Después, decidir el tratamiento de API-CONTRACT-004 (nulabilidad de relaciones, Fase 2)
antes de republicar el contrato con una generación limpia.

**Plan maestro**: [`docs/api-contract-master-plan.md`](./api-contract-master-plan.md)
**Decisiones arquitectónicas**: [`docs/architecture-decisions/`](./architecture-decisions/readme.md) (ADRs 0003-0008)
