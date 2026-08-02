# Architecture Decision Records (ADR)

Carpeta para **Architecture Decision Records**: decisiones arquitectónicas importantes con contexto, decisión y consecuencias.

**Formato recomendado:** Ver [0000-adr-template.md](./0000-adr-template.md).

## ADRs existentes

| Número | Título | Estado | Fecha |
|--------|--------|--------|-------|
| [0000](./0000-adr-template.md) | Plantilla ADR | — | — |
| [0001](./0001-api-v2-only.md) | API v2 como única versión activa | Aceptado | 2025-01-27 |
| [0002](./0002-materialized-order-costs.md) | Costes de pedido: resolución dinámica vs. tabla materializada | Pendiente de implementar | 2026-04-21 |
| [0003](./0003-scribe-openapi-tooling.md) | Scribe como herramienta de generación OpenAPI | Aceptado | 2026-08-02 |
| [0004](./0004-public-vs-internal-contract.md) | Contrato público vs interno y exclusión de rutas administrativas | Aceptado | 2026-08-02 |
| [0005](./0005-contract-casing-pagination-conventions.md) | Convenciones objetivo — casing camelCase y paginación estándar | Aceptado | 2026-08-02 |
| [0006](./0006-toarrayassoc-migration-policy.md) | Migración progresiva de `toArrayAssoc()`; prohibido en código nuevo | Aceptado | 2026-08-02 |
| [0007](./0007-api-versioning-breaking-changes.md) | Versionado de API y tratamiento de breaking changes del contrato | Aceptado | 2026-08-02 |
| [0008](./0008-relation-loaded-nullability-policy.md) | Política de nulabilidad en relaciones condicionalmente cargadas | Aceptado | 2026-08-02 |

ADRs 0003-0008 forman parte del plan de evolución del contrato API — ver
[`docs/api-contract-master-plan.md`](../api-contract-master-plan.md) para el seguimiento de fases,
deuda contractual y próxima acción. No dupliques ahí el contenido completo de una ADR: referénciala
por número.

## Cuándo añadir un ADR

- Cambios de arquitectura (multi-tenant, autenticación, estrategia de datos).
- Eliminación o deprecación mayor de funcionalidad (p. ej. API v1).
- Decisiones de infraestructura o despliegue que afecten al diseño del sistema.
- Convenciones formales del contrato API (casing, paginación, versionado, política de
  serialización) — ver ADRs 0003-0008 como referencia de alcance y formato esperado.
