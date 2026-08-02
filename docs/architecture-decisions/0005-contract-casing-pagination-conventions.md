---
title: ADR — Convenciones objetivo del contrato — casing camelCase y paginación estándar
date: 2026-08-02
status: accepted
deciders: Equipo PesquerApp (formalizado en docs/api-contract-master-plan.md)
---

# 5. Convenciones objetivo del contrato — casing camelCase y paginación estándar

## Contexto

La API mezcla históricamente `camelCase` (mayoría de Resources y `toArrayAssoc()`) con
`snake_case` residual (`IncotermResource` devuelve `created_at`/`updated_at` pese a que sus
Resources hermanas de Catálogos usan `createdAt`/`updatedAt`; algunas rutas de sesiones/superadmin
usan `per_page`). De forma similar, la paginación usa mayoritariamente `perPage` (76 archivos)
pero conviven 19 archivos con `per_page`, y `GET /v2/orders` cambia de envelope de respuesta según
el parámetro `active` (ver ADR relacionado sobre `toArrayAssoc()`, y `API-CONTRACT-001/006/007`
en `docs/api-contract-master-plan.md` §4). Sin una convención formalizada, cada endpoint nuevo es
libre de elegir una u otra forma, perpetuando la inconsistencia que hace poco fiable un cliente
TypeScript generado automáticamente.

## Decisión

- **Casing**: `camelCase` obligatorio en el JSON de request/response para todo código nuevo
  (Resources, `toArrayAssoc()`, arrays manuales de respuesta). Los casos existentes que se desvían
  (`IncotermResource`, endpoints de sesiones/superadmin con `per_page`) son **deuda a corregir**,
  no un precedente válido a replicar.
- **Paginación**: los listados nuevos deben paginar siempre con el envelope estándar de Laravel
  (`{ data, links, meta }`) y usar el parámetro `perPage`. No se admite un endpoint de listado que
  devuelva forma distinta según un query param opcional (el caso de `OrderListService`/`active` es
  deuda heredada a resolver en `docs/api-contract-master-plan.md` Fase 7, no un patrón a repetir).
- La migración del código existente que se desvía de estas convenciones es trabajo de fases
  posteriores del plan maestro (Fase 1 para `IncotermResource`, Fase 2 para el resto de casos
  `per_page`, Fase 7 para `OrderListService`), no de esta ADR — esta ADR fija el objetivo, no
  ejecuta la migración.

## Consecuencias

### Positivas
- Un cliente TypeScript generado puede asumir una única convención de nombres y de forma de
  paginación para toda la superficie de negocio, en vez de tener que revisar endpoint a endpoint.
- Da un criterio objetivo para clasificar hallazgos futuros como "bug a corregir" en vez de
  "variante aceptable".

### Negativas / Trade-offs
- Corregir los casos existentes (`IncotermResource`, 19 archivos con `per_page`, el caso `active`
  de `OrderListService`) son, en distinto grado, cambios potencialmente `BREAKING` para cualquier
  consumidor que ya dependa de la forma actual — requieren coordinación explícita con el frontend
  vía `docs/frontend-integration/backend-api-changes.md` antes de ejecutarse, no se hacen de forma
  silenciosa.
- Formalizar la convención no la aplica automáticamente; sin disciplina de revisión (o, en el
  futuro, un linter/test que la haga cumplir), un endpoint nuevo puede seguir introduciendo
  `snake_case` por descuido.

### Neutras
- No cambia el formato de fechas, números ni booleanos (fuera de alcance de esta ADR; ver
  `API-CONTRACT-015` sobre enums y tipos categóricos en `docs/api-contract-master-plan.md` §4 para
  una deuda relacionada pero distinta).

## Alternativas consideradas

- **Aceptar ambas convenciones indefinidamente y documentarlas endpoint a endpoint**: descartada
  — es exactamente la situación actual, y es la fuente de la fricción que motiva esta ADR.
- **Migrar todo a `snake_case`**: descartada — `camelCase` ya es ampliamente mayoritario (Resources
  explícitas, `toArrayAssoc()`, `perPage`); migrar a `snake_case` movería más código que
  formalizar el patrón ya dominante.

## Referencias

- `docs/api-contract-master-plan.md` §3 (decisiones D5/D6), §4 (`API-CONTRACT-006`,
  `API-CONTRACT-007`), §6 (Fases 1, 2 y 7).
- `docs/frontend/api-conventions.md`.
- `docs/frontend-integration/backend-api-changes.md`.
