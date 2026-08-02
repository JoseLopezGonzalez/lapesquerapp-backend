---
title: ADR — Migración progresiva de toArrayAssoc(); prohibido en código nuevo
date: 2026-08-02
status: accepted
deciders: Equipo PesquerApp (formalizado en docs/api-contract-master-plan.md)
---

# 6. Migración progresiva de `toArrayAssoc()`; prohibido en código nuevo

## Contexto

39 modelos Eloquent implementan un método `toArrayAssoc()` que serializa manualmente el modelo y
sus relaciones anidadas, en lugar de usar API Resources. Es invisible para cualquier análisis
estático (Scramble, PHPStan) y solo parcialmente capturado por Scribe vía `ResponseCalls` — y
únicamente para rutas `GET` (ver ADR-0003). 14 API Resources delegan el 100% de su serialización
en el `toArrayAssoc()` del modelo subyacente. Migrar los 39 usos de una sola vez es un refactor
grande que tocaría prácticamente todos los módulos de negocio simultáneamente — desproporcionado
para una sola intervención y con alto riesgo de introducir regresiones cruzadas.

## Decisión

- **Código nuevo**: ningún modelo nuevo debe implementar `toArrayAssoc()` para servir a la API;
  usar API Resources anidadas reales desde el principio (ya es la regla general en `CLAUDE.md`
  §19 regla 1; esta ADR la hace explícita para este caso concreto).
- **Código existente**: los 39 usos se migran de forma progresiva, priorizados por tráfico y por
  rating del bloque (`CLAUDE.md` §8), no todos a la vez. El orden concreto y las fases de
  ejecución viven en `docs/api-contract-master-plan.md` §6 (Fases 3 a 7: empezar por modelos de
  bajo radio de impacto — `FishingGear`, `PaymentTerm`, `Country` — antes de tocar `Customer`,
  `Product` u `Order`-relacionados).
- Mientras un modelo siga usando `toArrayAssoc()`, se acepta que su forma solo esté verificada
  contra ejecución real para `GET` (vía `ResponseCalls`); las respuestas de éxito de escritura se
  infieren del código, no se validan automáticamente (deuda `API-CONTRACT-013`).

## Consecuencias

### Positivas
- No bloquea el trabajo de negocio en curso con un refactor previo masivo — el contrato ya es
  parcialmente fiable hoy (Scribe + `ResponseCalls`) mientras dura la migración.
- El orden de migración por radio de impacto (fases 3-7) reduce el riesgo de romper varios módulos
  a la vez, al validar el patrón de migración primero en modelos de bajo impacto.

### Negativas / Trade-offs
- Mientras dure la migración (potencialmente varias fases/meses de calendario), las respuestas de
  escritura de los modelos no migrados siguen sin verificación automática real.
- Requiere disciplina sostenida entre varias intervenciones/agentes — sin este ADR y sin el
  seguimiento del plan maestro, es fácil que la migración se abandone a medias o que se añadan
  usos nuevos de `toArrayAssoc()` por conveniencia puntual.

### Neutras
- No cambia el comportamiento observable de la API mientras la migración no toque la forma real de
  ningún campo (el objetivo es hacer el código introspectable, no cambiar lo que ya devuelve, salvo
  que se identifique un bug de paso, como ocurrió con `IncotermResource`).

## Alternativas consideradas

- **Refactor completo inmediato de los 39 usos**: descartada por alcance desproporcionado para una
  sola intervención y por el riesgo de tocar todos los módulos de negocio a la vez.
- **No migrar nunca, depender solo de `ResponseCalls`**: descartada — dejaría permanentemente sin
  verificación real las respuestas de escritura, y perpetuaría un patrón opaco para cualquier
  herramienta de análisis estático futura.

## Referencias

- `docs/api-contract-master-plan.md` §3 (decisión D9), §4 (`API-CONTRACT-002`, `API-CONTRACT-003`,
  `API-CONTRACT-013`), §6 (Fases 3-7).
- `app/Http/Resources/v2/CustomerResource.php` (ejemplo ya corregido del anti-patrón magic
  `__call`, aunque la delegación en `toArrayAssoc()` en sí sigue pendiente de migrar).
- ADR-0003 (Scribe y `ResponseCalls` como mitigación parcial).
