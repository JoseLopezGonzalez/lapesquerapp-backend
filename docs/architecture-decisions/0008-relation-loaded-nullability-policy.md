---
title: ADR — Política de nulabilidad en relaciones condicionalmente cargadas (relationLoaded())
date: 2026-08-02
status: accepted
deciders: Equipo PesquerApp (formalizado en docs/api-contract-master-plan.md)
---

# 8. Política de nulabilidad en relaciones condicionalmente cargadas (`relationLoaded()`)

## Contexto

Varias Resources (`OrderResource`, `OrderDetailsResource`, `CustomerResource`, `PalletResource`,
`SpeciesResource`) usan el patrón `$this->relationLoaded('x') ? ... : null` para exponer una
relación solo si el controlador la precargó, evitando así una consulta N+1 accidental. El efecto
colateral es que un campo puede ser `null` por dos motivos indistinguibles desde el JSON: "no
aplica a este registro" (regla de negocio) o "esta vista concreta no cargó la relación" (decisión
de rendimiento del endpoint). Un cliente TypeScript generado marca el campo como `nullable` sin
poder capturar esa diferencia semántica — riesgo detectado en `API_CONTRACT_AUDIT.md` §6-7.

## Decisión

Se adopta la **opción A** como política por defecto:

- **No eliminar `relationLoaded()`** como patrón — es una mitigación consciente y correcta de N+1,
  no un error.
- **Documentar explícitamente en cada Resource**, mediante comentario, qué controladores/acciones
  garantizan la relación cargada y cuáles no (por ejemplo: `// Cargada en: OrderController::index,
  OrderController::show; NO cargada en: OrderController::indexLite`).
- Forzar eager-loading permanente de una relación (**opción B**) se evalúa caso por caso, solo
  cuando un endpoint concreto lo justifique (por ejemplo, si la ambigüedad ya ha causado un bug de
  negocio real), nunca como regla general aplicada a todas las Resources.

## Consecuencias

### Positivas
- No introduce riesgo de N+1 nuevo — la política no cambia el comportamiento de carga, solo exige
  documentarlo.
- Es de bajo coste de implementación: añadir un comentario no requiere tocar lógica ni tests
  existentes.

### Negativas / Trade-offs
- El spec OpenAPI seguirá sin poder expresar la diferencia de forma automática — sigue siendo
  `nullable` sin más matiz. Esta es una limitación de la herramienta (OpenAPI/JSON Schema no tiene
  un concepto nativo de "null por no cargado"), no algo que esta política resuelva por sí sola.
- Sin *enforcement* automático (no hay lint que obligue al comentario), depende de disciplina de
  revisión de código — un Resource nuevo puede introducir el patrón sin documentarlo si nadie lo
  revisa.

### Neutras
- Un consumidor (frontend/app móvil) sigue necesitando la documentación humana (el comentario en
  el Resource, o el equivalente en `docs/frontend-integration/backend-api-changes.md` si afecta a
  un endpoint ya en producción) para interpretar correctamente un campo `null` — el contrato
  OpenAPI por sí solo no es suficiente para este caso, y no se espera que lo sea.

## Alternativas consideradas

- **Opción B generalizada** (forzar `with()` de todas las relaciones opcionales en todos los
  controladores): descartada como regla general — el coste de rendimiento (N+1 o sobre-carga de
  datos no usados por la vista) no está justificado para la mayoría de los ~8 campos afectados en
  `OrderResource` y equivalentes.
- **Exponer un campo booleano adicional junto a cada relación opcional** (p. ej.
  `customerLoaded: true/false`): descartada — añadiría ruido a todas las respuestas para un
  problema que hoy solo afecta a un subconjunto acotado de Resources, y complicaría el contrato
  para todos los consumidores, no solo los que necesitan la distinción.

## Referencias

- `API_CONTRACT_AUDIT.md` §6-7 (hallazgo original del problema).
- `app/Http/Resources/v2/OrderResource.php:20-29` (ejemplo del patrón).
- `docs/api-contract.md` §3 ("Al modificar un endpoint existente" ya recomendaba documentar en el
  propio Resource qué controladores cargan la relación — esta ADR formaliza esa recomendación como
  decisión y añade la opción B como alternativa evaluada explícitamente).
- `docs/api-contract-master-plan.md` §3 (decisión D7), §4 (`API-CONTRACT-004`).
