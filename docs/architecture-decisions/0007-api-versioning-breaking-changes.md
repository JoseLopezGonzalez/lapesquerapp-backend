---
title: ADR — Versionado de API y tratamiento de breaking changes del contrato
date: 2026-08-02
status: accepted
deciders: Equipo PesquerApp (formalizado en docs/api-contract-master-plan.md)
---

# 7. Versionado de API y tratamiento de breaking changes del contrato

## Contexto

ADR-0001 ya estableció `v2` como única versión activa de la API (eliminación completa de `v1`).
No existe negociación de contenido ni versionado por header — `v2` es simplemente un prefijo de
URL fijo. Con la introducción de un contrato OpenAPI generado y verificable (ADR-0003), surge la
pregunta de cómo gestionar cambios de forma en el contrato a lo largo del tiempo sin recurrir a
crear una `v3` cada vez que un endpoint cambia.

## Decisión

- Mantener `v2` como único prefijo de API — no se introduce versionado adicional por esta
  decisión (reafirma ADR-0001, no la sustituye).
- Los cambios de contrato se gestionan con el mecanismo ya implementado: `OpenApiContractDiffer`
  (`app/Services/OpenApi/OpenApiContractDiffer.php`) compara el YAML commiteado contra el
  generado desde el código actual, clasificando cada diferencia como `BREAKING`, `COMPATIBLE` o
  `INFO`.
- CI (`composer contract:verify` con `--fail-on-any` en `.github/workflows/api-contract.yml`)
  bloquea cualquier PR con cambios `BREAKING` no reconocidos explícitamente.
- Un `BREAKING` intencional se reconoce con `contract:check --allow-breaking` y **debe** ir
  acompañado de una entrada en `docs/frontend-integration/backend-api-changes.md` — no se permite
  silenciarlo sin documentación (ya vigente como regla en `CLAUDE.md` §19 regla 3).

## Consecuencias

### Positivas
- Evita la sobrecarga operativa de mantener múltiples versiones de API en paralelo (la misma
  razón que motivó ADR-0001 al eliminar `v1`).
- Un breaking change queda siempre documentado con motivación y ejemplo, no solo mencionado en un
  mensaje de commit que se pierde con el tiempo.

### Negativas / Trade-offs
- `OpenApiContractDiffer` compara solo nombres/tipos/presencia de propiedades de **primer nivel**,
  no un diff semántico profundo de JSON Schema (decisión deliberada para evitar falsos positivos
  por reordenación de claves — ver `docs/api-contract.md` §6) — un cambio anidado a más de un
  nivel de profundidad puede no detectarse automáticamente (deuda `API-CONTRACT-014`).
- Un breaking change mal coordinado (mergeado con `--allow-breaking` sin avisar realmente al
  frontend) puede romper producción igualmente — el mecanismo técnico no sustituye la
  coordinación humana/entre agentes.

### Neutras
- Si en el futuro aparece una razón de negocio real para un contrato divergente de forma
  permanente (por ejemplo, si la app móvil necesitara una API estructuralmente distinta a la web —
  ver decisión D13, pendiente, en `docs/api-contract-master-plan.md` §3), esta ADR debe reabrirse
  explícitamente, no superarse de forma incremental sin discutirlo.

## Alternativas consideradas

- **Versionado por header o negociación de contenido**: descartada — complejidad no justificada
  por ninguna necesidad de negocio detectada hoy.
- **Crear una nueva versión (`v3`) ante cualquier breaking change**: descartada — repetiría el
  mismo problema de mantenimiento duplicado que llevó a eliminar `v1` en ADR-0001; el mecanismo de
  `contract:verify` + changelog ya resuelve la necesidad real (comunicar el cambio) sin el coste
  de una versión nueva.

## Referencias

- ADR-0001 (API v2 como única versión activa).
- `app/Services/OpenApi/OpenApiContractDiffer.php`, `app/Console/Commands/CheckOpenApiContract.php`.
- `docs/api-contract.md` §6 (tabla de tipos de cambio y bloqueo de CI).
- `docs/frontend-integration/backend-api-changes.md`.
- `docs/api-contract-master-plan.md` §3 (decisiones D10/D11), §4 (`API-CONTRACT-014`).
