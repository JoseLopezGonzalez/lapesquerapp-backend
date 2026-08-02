---
title: ADR — Scribe como herramienta de generación OpenAPI
date: 2026-08-02
status: accepted
deciders: Equipo PesquerApp (formalizado en docs/api-contract-master-plan.md)
---

# 3. Scribe como herramienta de generación OpenAPI

## Contexto

El backend necesita un contrato OpenAPI fiable para que el frontend Next.js (y en el futuro una
app móvil) puedan generar clientes/tipos en vez de escribirlos a mano. La API tiene un patrón
extendido (`toArrayAssoc()` en 39 modelos, ver ADR-0006) que serializa relaciones anidadas fuera
de las API Resources estándar de Laravel, lo que dificulta cualquier generación puramente estática
del contrato. `API_CONTRACT_AUDIT.md` §11-13 comparó en detalle las dos opciones disponibles para
Laravel: Scramble (análisis estático de tipos, sin ejecutar la app) y Scribe (con la estrategia
`ResponseCalls`, que ejecuta las rutas `GET` reales contra una base de datos).

## Decisión

- Usar **Scribe `^5.9`** como única herramienta de generación OpenAPI (ya estaba instalada en el
  repositorio antes de esta decisión formal).
- Mantener **dos configuraciones**: `config/scribe.php` (spec interno completo, uso del equipo
  backend) y `config/scribe_public.php` (spec público, consumido por frontend/app móvil — ver
  ADR-0004 para el detalle de qué excluye y por qué).
- Aprovechar `ResponseCalls` (ejecuta rutas `GET *` reales) como mitigación parcial del problema
  de `toArrayAssoc()`: captura la forma real de la respuesta para lectura, aunque no para
  escritura (ver "Consecuencias").

## Consecuencias

### Positivas
- Es la única de las dos opciones evaluadas capaz de extraer una forma real (no vacía) de
  respuestas que dependen de `toArrayAssoc()`, sin necesidad de refactorizar antes de generar el
  primer contrato útil.
- Ya está instalada y configurada con conocimiento específico del dominio (header `X-Tenant`,
  autenticación Bearer, exclusión de `/api/health`), sin coste de introducir una segunda
  herramienta desde cero.
- Genera OpenAPI 3.1, compatible con generadores de tipos TypeScript estándar
  (`openapi-typescript` u otros).

### Negativas / Trade-offs
- Requiere una base de datos accesible para generar el contrato (no es análisis puramente
  estático) — implica que CI necesita un servicio MySQL efímero
  (`.github/workflows/api-contract.yml`), y que un agente sin entorno de BD no puede regenerar ni
  verificar el contrato localmente.
- `ResponseCalls` solo ejecuta rutas `GET` (`config/scribe.php` → `strategies.responses`, `only:
  ['GET *']`); las respuestas de éxito de `POST`/`PUT`/`PATCH` de Resources que dependen de
  `toArrayAssoc()` se infieren del código, no se verifican en ejecución real (deuda
  `API-CONTRACT-013`, ver `docs/api-contract-master-plan.md` §4).
- La reproducibilidad del spec entre generaciones locales repetidas contra la misma BD puede
  variar por el `AUTO_INCREMENT` no transaccional de MySQL (deuda `API-CONTRACT-012`, no bloquea
  CI porque usa BD efímera).

### Neutras
- Migrar los 39 usos de `toArrayAssoc()` a Resources anidadas reales (ADR-0006) sigue siendo
  deseable independientemente de la herramienta elegida — Scribe mitiga el síntoma para `GET`, no
  resuelve la causa.

## Alternativas consideradas

- **Scramble**: descartada. Al ser puramente estática (usa PHPStan por debajo, sin ejecutar la
  app), no puede resolver `toArrayAssoc()` ni el patrón de delegación magic `__call` que existía
  en `CustomerResource` antes de corregirse — habría rendido peor que Scribe con `ResponseCalls`
  en este código concreto, no mejor. Ver comparación completa en `API_CONTRACT_AUDIT.md` §11-13.
- **Escribir el OpenAPI a mano**: descartada. Alto coste de mantenimiento y alto riesgo de
  divergencia respecto al comportamiento real — contradice la decisión de que Laravel sea la
  fuente de verdad del contrato (ver `docs/api-contract.md` §1).

## Referencias

- `API_CONTRACT_AUDIT.md` §11-13 (comparación técnica completa Scramble vs Scribe).
- `config/scribe.php`, `config/scribe_public.php`.
- `docs/api-contract.md` (documentación operativa: comandos, flujo de trabajo).
- `docs/api-contract-master-plan.md` (plan de evolución, deuda contractual, decisión D1).
