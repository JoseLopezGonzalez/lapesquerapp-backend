---
title: Plan Maestro del Contrato API — PesquerApp Backend
description: Fuente de seguimiento única del proyecto de consolidación del contrato OpenAPI. Léelo antes de tocar rutas, Form Requests, Resources o controladores de v2/*.
status: vivo — actualizar en cada intervención (ver §10)
created: 2026-08-02
last_updated: 2026-08-02
verified_against_commit: f58e1cb
audience: Agentes IA (Claude Code y otros), backend engineers, frontend engineers
---

# Plan Maestro del Contrato API — PesquerApp Backend

> Este documento asume que quien lo lee **no ha visto la conversación en la que se creó**. Todo lo
> necesario para continuar el trabajo está aquí o enlazado desde aquí. Si algo parece contradecir
> el código actual, el código manda — actualiza este documento, no al revés (ver §10, regla 12).

**Cómo llegar aquí**: `CLAUDE.md` §19 y `AGENTS.md` apuntan a este documento como fuente de
seguimiento del contrato API. Tres documentos complementarios, cada uno con un rol distinto —
ninguno duplica a los demás:

- **`docs/api-contract.md`** — documentación operativa (comandos, cómo generar/verificar,
  convenciones). Consúltalo para *cómo ejecutar* algo.
- **`docs/architecture-decisions/000{3-8}-*.md`** — las decisiones arquitectónicas durables del
  contrato (herramienta elegida, casing/paginación objetivo, política de `toArrayAssoc()`,
  versionado, nulabilidad), en el formato ADR estándar del proyecto (ver
  `docs/architecture-decisions/readme.md`). Este plan las **referencia**, no las repite enteras.
- **`docs/audits/laravel-evolution-log.md`** — el registro histórico único de todas las
  intervenciones del CORE v1.0, incluidas las de contrato API (etiquetadas "API Contract"). Este
  plan no mantiene un log paralelo — ver §8.

Este documento aporta lo que ninguno de los anteriores cubre: fases de ejecución secuenciadas,
inventario de deuda contractual con IDs estables, clasificación de módulos y la próxima acción
recomendada en cada momento.

---

## 1. Visión final

Cuando este proyecto esté terminado, el sistema debe funcionar así:

```
Laravel (rutas + Form Requests + API Resources)
   │  única fuente de verdad del contrato — no hay JSON Schema ni OpenAPI escrito a mano
   ▼
public/openapi/frontend.yaml  (generado, versionado en git, servido en /openapi/frontend.yaml)
   │  regenerado y verificado en cada PR que toque v2/* (CI: .github/workflows/api-contract.yml)
   ▼
Tipos TypeScript generados (frontend Next.js) + tipos equivalentes para la futura app móvil
   │  generados desde el YAML publicado, no escritos a mano, no inventados por un agente
   ▼
Adaptadores / ViewModels en el cliente
   │  la capa que traduce "lo que la API devuelve" a "lo que la pantalla necesita"
   ▼
UI
```

Y en paralelo, en cada Pull Request:

```
Cambio de código Laravel → composer contract:update → diff del YAML → composer contract:verify
   → CI bloquea si hay BREAKING no reconocido → si es intencional, --allow-breaking +
   entrada en docs/frontend-integration/backend-api-changes.md
```

**Laravel nunca deja de ser la fuente de verdad.** El YAML es una proyección; si el spec y el
comportamiento real divergen, se regenera el spec, nunca se edita el YAML a mano para que
"cuadre". Esto ya es una decisión tomada y en vigor (ver `docs/api-contract.md` §1).

### Qué queda automatizado cuando esto esté terminado

- Generación del contrato (Scribe, ya funciona).
- Detección de breaking changes en CI (`OpenApiContractDiffer`, ya funciona a nivel de
  propiedades de primer nivel — ver deuda API-CONTRACT-014 sobre su profundidad).
- Generación de tipos TypeScript a partir del YAML publicado (frontend, fuera de este repo).
- Exclusión de rutas sensibles (superadmin, impersonación) del contrato público.

### Qué sigue siendo manual, y debe seguir siéndolo

Esto **no es deuda a eliminar**, es diseño correcto — no automatizar lo que no debe automatizarse:

- **ViewModels/adaptadores en el frontend**: traducir el tipo generado a lo que la pantalla
  necesita (agrupar, formatear, combinar varias respuestas) es responsabilidad del frontend, no
  algo que Laravel deba anticipar.
- **Schemas y reglas de formulario en el cliente**: OpenAPI documenta la forma de la respuesta y
  las reglas de validación del backend, pero la validación *interactiva* de formularios (mensajes
  en tiempo real, dependencias entre campos en la UI) vive en el frontend.
- **Transformaciones visuales**: formateo de fechas/moneda para mostrar, agrupaciones para
  gráficos, etc. — no son parte del contrato.
- **Lógica de caché** (frontend: React Query/SWR o similar; backend: `Cache::remember` donde ya
  se usa, p. ej. `TenantMiddleware`) — cada lado cachea lo suyo, el contrato no la describe.
- **Casos de negocio específicos por rol** (p. ej. por qué `FieldOrderResource` omite campos
  comerciales para `repartidor_autoventa`, ver API-CONTRACT-005) — son decisiones de producto que
  el contrato documenta pero no decide.

Un agente futuro que proponga "generar automáticamente el ViewModel/formulario/caché desde el
OpenAPI" está resolviendo un problema que este plan no tiene — no lo hagas sin que el usuario lo
pida explícitamente.

---

## 2. Estado actual verificado

Todo lo listado aquí se ha comprobado contra el código en el commit `f58e1cb` (2026-08-02), no
copiado de auditorías anteriores sin contrastar. Donde no ha sido posible ejecutar algo (por
ejemplo, por falta de MySQL/`vendor/` en el entorno de esta sesión), se dice explícitamente.

### 2.1 Infraestructura que ya existe y está verificada por lectura de código

| Pieza | Estado verificado | Evidencia |
|---|---|---|
| Scribe `^5.9` instalado | Sí, en `composer.json` (require-dev) | `composer.json` |
| Dos configs (interna/pública) | Sí, `config/scribe.php` + `config/scribe_public.php`, la pública extiende la interna y excluye superadmin/impersonación/debug/internal/system | `config/scribe_public.php:29-46` |
| Contrato versionado en git | Sí, `public/openapi/frontend.yaml` (1.06 MB) + `public/openapi/meta.json` existen en el árbol de trabajo, no están en `.gitignore` | `ls public/openapi/` |
| Comandos `contract:publish` / `contract:check` | Sí, implementados como comandos Artisan reales (no wrappers vacíos); usan un proceso `Process` separado porque Scribe hace `exit()` directo en fallos de ruta individual | `app/Console/Commands/PublishOpenApiContract.php`, `app/Console/Commands/CheckOpenApiContract.php` |
| `composer contract:update/verify/test` | Sí, mapeados en `composer.json` → `scripts` | `composer.json` |
| Diff estructural de breaking changes | Sí, `OpenApiContractDiffer` compara nombre/tipo/requerido de propiedades de nivel superior | `app/Services/OpenApi/OpenApiContractDiffer.php` |
| CI (`.github/workflows/api-contract.yml`) | Sí, existe y corre en dos jobs: `tests` (Pint + PHPUnit) y `api-contract` (levanta MySQL efímero, migra, siembra tenant demo, ejecuta `contract:check --fail-on-any`) | `.github/workflows/api-contract.yml` |
| Test de exclusión de rutas sensibles | Sí, `test_public_openapi_spec_excludes_sensitive_routes` comprueba que el YAML público no contiene `/superadmin`, `/public/impersonation` ni `impersonate` | `tests/Feature/ApiDocumentationTest.php:95-102` |
| Fixture de generación (`contract:seed-fixture`) | Referenciada en CI y en `docs/api-contract.md`; no se ha inspeccionado el código del comando en esta sesión | `.github/workflows/api-contract.yml` (paso "Seed demo tenant + admin fixture") |

**Esto contradice una parte central de `API_CONTRACT_AUDIT.md`** (que decía "no existe CI/CD, no
hay `.github/`"): esa auditoría es anterior a la implementación (`b7e1466`, "feat: establish
OpenAPI contract pipeline for frontend consumption"). El propio `API_CONTRACT_AUDIT.md` describe
el punto de partida, no el estado actual — trátalo como archivo histórico, no como estado vigente.
`FRONTEND_OPENAPI_HANDOFF.md` y `docs/api-contract.md` sí describen el estado post-implementación
y coinciden con lo verificado aquí.

### 2.2 Qué funciona (verificado por lectura de código, no por ejecución en esta sesión)

- El pipeline descrito en `docs/api-contract.md` §2-3 (update → verify → commit) es coherente con
  el código real de los comandos.
- `IncidentController` ya sirve la misma forma (`toArrayAssoc()`) en `GET`/`POST`/`PUT` —
  el breaking change documentado en `docs/frontend-integration/backend-api-changes.md` Sprint 2 sí
  está aplicado en el código (`app/Http/Controllers/v2/IncidentController.php:27` usa
  `$order->incident->toArrayAssoc()`, ya no el modelo crudo).
- El patrón `parent::toArrayAssoc()` vía magic `__call` (el hallazgo más "opaco para herramientas"
  de la auditoría original) ya no existe: `CustomerResource::toArray()` llama explícitamente a
  `$this->resource->toArrayAssoc()`, con un comentario explicando por qué no usar `parent::`
  (`app/Http/Resources/v2/CustomerResource.php:15-19`).

### 2.3 Qué falta probar en un entorno real (no verificable en esta sesión)

Esta sesión se ejecutó en un contenedor sin `vendor/` instalado, sin `.env`, sin cliente `mysql` y
sin un servicio de base de datos accesible (`which mysql` falla; no hay `vendor/bin/pint`). Por
tanto, **nada de lo siguiente se ha ejecutado, solo leído**:

- Que `composer install` funcione limpio con PHP 8.2 (CI) — el `composer.json` exige `^8.1`
  (ver API-CONTRACT-011), no se ha comprobado si hay conflictos de versión reales.
- Que `composer contract:test`, `composer contract:update` y `composer contract:verify` se
  ejecuten sin error contra una base de datos real.
- Que el último run de CI en la rama `main`/`claude/api-contract-master-plan-myz6dz` esté en
  verde. No se ha consultado el estado de Actions en esta sesión (no había acceso a herramientas
  de GitHub Actions en el momento de escribir este documento).
- Que la URL pública `https://api.lapesquerapp.es/openapi/frontend.yaml` sirva realmente el
  archivo en producción (depende de despliegue, fuera del alcance de este repositorio).
- Que `contract:seed-fixture` cree correctamente el tenant demo en un entorno limpio.

Esto es exactamente el contenido de la **Fase 0** (§6): confirmar en un entorno real lo que aquí
solo se ha podido verificar por lectura de código.

### 2.4 Limitaciones que siguen presentes (confirmadas, no heredadas sin contrastar)

Ver el inventario completo en §4. Resumen de lo más importante, **re-verificado en esta sesión**
(no copiado de `API_CONTRACT_AUDIT.md` sin comprobar):

- **`toArrayAssoc()` sigue en 39 modelos** — se ha vuelto a ejecutar
  `grep -rl "function toArrayAssoc" app/Models | wc -l` y sigue dando 39 (la auditoría original
  también decía 39; la lista de archivos se ha vuelto a extraer completa el 2026-08-02 y se anexa
  en API-CONTRACT-002).
- **`perPage` vs `per_page` sigue sin unificar**: 76 vs 19 archivos, cifras idénticas a las de la
  auditoría original — no ha habido ningún cambio en este punto (API-CONTRACT-006).
- **`OrderListService::list()` sigue devolviendo `Collection|LengthAwarePaginator`** según el
  parámetro `active` — el código ahora **sí documenta el problema explícitamente** en un docblock
  (`app/Services/v2/OrderListService.php:29-45`, con referencia a `docs/api-contract.md` §5 y a la
  alternativa estable `GET /v2/orders/active`), pero el comportamiento no determinista **no se ha
  corregido**, solo documentado y mitigado con un endpoint alternativo (API-CONTRACT-001).
- **CRM y Estadísticas siguen sin Resources**: se ha vuelto a comprobar
  `CrmDashboardController`, `CrmAgendaController`, `OrderStatisticsController`,
  `CeboDispatchStatisticsController`, `RawMaterialReceptionStatisticsController`,
  `AuxiliaryLineStatisticsController`, `StockStatisticsController` — los 7 tienen 0 referencias a
  `Resource` (API-CONTRACT-009).
- **`FieldOrderResource` sigue divergiendo de `OrderResource`** de forma sustancial (omite
  `salesperson`, `transport`, `incoterm`, `subtotalAmount`, `totalAmount`; usa una forma reducida
  de `customer`) — comparación línea a línea repetida en esta sesión, coincide con la auditoría
  original (API-CONTRACT-005).

### 2.5 Un hallazgo nuevo, no presente en auditorías anteriores

Al verificar módulo por módulo para escribir §5 de este plan, se ha encontrado que
**`IncotermResource` — dentro del propio módulo "Catálogos", el recomendado como piloto — devuelve
`created_at`/`updated_at` en snake_case**, mientras que sus resources hermanas del mismo módulo
(`ProductCategoryResource`, `ProductFamilyResource`, `TransportResource`) devuelven
`createdAt`/`updatedAt` en camelCase. Ninguna auditoría previa lo menciona. Es un bug de una línea,
pero **bloquea la afirmación "Catálogos es el módulo más uniforme"** tal cual estaba escrita —
ahora es "el módulo más uniforme, con una excepción conocida y corregible" (API-CONTRACT-007,
detalle en §4 y primer paso de Fase 1).

### 2.6 Decisiones ya tomadas (antes de este plan, confirmadas vigentes)

- Scribe sobre Scramble (ver `API_CONTRACT_AUDIT.md` §11-13; sigue siendo válido, nada ha cambiado
  que favorezca a Scramble).
- Contrato público (`config/scribe_public.php`) separado del interno (`config/scribe.php`).
- El contrato público se versiona en git; el interno no.
- Exclusión de rutas sensibles por prefijo, con test de regresión.

### 2.7 Problemas corregidos desde la auditoría original

- Divergencia de forma de `Incident` entre `GET` y `POST`/`PUT` (unificado, ver §2.2).
- Magic `__call` opaco en `CustomerResource` (eliminado, ver §2.2).
- Ausencia de CI (añadido, ver §2.1).
- Contrato no versionado (corregido, ver §2.1).
- Exclusión de superadmin/impersonación del contrato público (implementado y con test).

### 2.8 Problemas aplazados deliberadamente (documentados como tal en el propio código o en `docs/api-contract.md`)

- `OrderListService` no determinista (mitigado con endpoint alternativo, no corregido).
- Los 39 usos de `toArrayAssoc()` (aceptados como mitigados parcialmente por `ResponseCalls`).
- `perPage`/`per_page`.
- `FieldOrderResource` vs `OrderResource`.
- Ver `docs/api-contract.md` §5 para el detalle tal como lo documentó la intervención anterior —
  coincide con lo verificado aquí.

---

## 3. Principios y decisiones arquitectónicas

Este proyecto ya tiene un sistema de ADR (`docs/architecture-decisions/`, formato en
`0000-adr-template.md`). Las decisiones **durables** del contrato (herramienta, convenciones,
políticas de migración, versionado) viven ahí como ADRs numeradas, no aquí — este plan las indexa
y añade las decisiones de alcance más operativo (secuenciación, qué se genera) que no justifican
una ADR propia. No dupliques el contenido completo de una ADR en este documento: si necesitas más
detalle que el resumen de una fila, abre la ADR enlazada.

### Índice de decisiones con ADR propia

| ID | Decisión | Estado | ADR |
|---|---|---|---|
| D1 | Herramienta OpenAPI: Scribe `^5.9` sobre Scramble | Aprobada (preexistente) | [ADR-0003](../architecture-decisions/0003-scribe-openapi-tooling.md) |
| D3+D4 | Contrato público vs interno; exclusión de rutas administrativas por prefijo + test de regresión | Aprobada (preexistente) | [ADR-0004](../architecture-decisions/0004-public-vs-internal-contract.md) |
| D5+D6 | Convenciones objetivo: casing camelCase y paginación estándar (`perPage`, envelope `{data,links,meta}`) | Aprobada como objetivo; migración del código existente es Propuesta (fases 1/2/7) | [ADR-0005](../architecture-decisions/0005-contract-casing-pagination-conventions.md) |
| D9 | Uso de `toArrayAssoc()`: permitido de forma transitoria, prohibido en código nuevo | Aprobada la política; migración existente es Propuesta (fases 3-7) | [ADR-0006](../architecture-decisions/0006-toarrayassoc-migration-policy.md) |
| D10+D11 | Versionado de API (mantener `v2` único) y tratamiento de breaking changes | Aprobada (ya implementado) | [ADR-0007](../architecture-decisions/0007-api-versioning-breaking-changes.md) |
| D7 | Política de nulabilidad en relaciones condicionalmente cargadas (`relationLoaded()`) | Aprobada (opción A por defecto) | [ADR-0008](../architecture-decisions/0008-relation-loaded-nullability-policy.md) |

### Decisiones de alcance operativo (sin ADR propia — demasiado ligadas a la ejecución de este plan concreto para justificar una ADR de arquitectura)

**D2 — Fuente de verdad: código Laravel, no el YAML.** Aprobada, preexistente. El YAML es un
artefacto derivado; si diverge del comportamiento real, el comportamiento real manda y el YAML se
regenera — nunca se edita a mano. Ya documentado en `docs/api-contract.md` §1.

**D8 — Uso de API Resources obligatorio para endpoints nuevos.** Aprobada, preexistente (ya en
`CLAUDE.md` §19 regla 1). Ningún controlador nuevo debe devolver un modelo Eloquent crudo ni un
array de forma variable.

**D12 — Estrategia frontend: catálogos primero, por tráfico/rating después.** Aprobada como orden
general (preexistente, `FRONTEND_OPENAPI_HANDOFF.md` §4-5); el orden fino de fases 4-7 es
específico de este plan (§6) y puede reordenarse si el negocio lo pide. No generar tipos para
CRM, Estadísticas o Field/Autoventa hasta que sus Resources existan (ver §5).

**D13 — Estrategia para la aplicación móvil.** **Pendiente** — requiere decisión de negocio, no
técnica. `API_CONTRACT_AUDIT.md` §19 ya dejaba esta pregunta abierta el 2026-08-02 y sigue sin
respuesta verificable en el código: ¿la futura app móvil reutiliza `v2/field/*` (hoy pensado para
`repartidor_autoventa`), o necesita un contrato nuevo? Condiciona si `FieldOrderResource` es "el
contrato móvil de referencia" o "un caso particular a discontinuar" (API-CONTRACT-005). Fase 8
(§6) no puede planificarse en detalle hasta que se responda. Deliberadamente **sin ADR todavía**:
una ADR documenta una decisión tomada, no una pregunta abierta — créala (`0009-*.md`) en el
momento en que D13 se resuelva, no antes.

**D14 — Qué se genera y qué no se genera.** Aprobada, preexistente. Se generan tipos TypeScript
para las respuestas JSON de negocio (`v2/*` no excluido). No se generan para
`PDFController`/`ExcelController` (documentar como blob) ni para las rutas ya excluidas por
ADR-0004. Ya documentado en `docs/api-contract.md` §4 y `FRONTEND_OPENAPI_HANDOFF.md` §6-7.

---

## 4. Inventario de deuda contractual

IDs estables. **Estado** usa: `Abierto` (no resuelto) · `Parcialmente mitigado` · `Mitigado`
(resuelto para el propósito del contrato, aunque el patrón subyacente siga existiendo) ·
`Resuelto`. Todas las filas se han verificado contra el código el 2026-08-02 salvo donde se indica
lo contrario explícitamente.

| ID | Problema | Módulo | Severidad | Estado | Dependencias | Evidencia |
|---|---|---|---|---|---|---|
| API-CONTRACT-001 | `GET /v2/orders?active=true\|false` devuelve `Collection` plana (sin `links`/`meta`); sin el parámetro, devuelve el envelope de paginación estándar. Mismo endpoint, dos formas. | Pedidos | Crítico | Parcialmente mitigado (documentado en código + alternativa estable `GET /v2/orders/active` con `ActiveOrderCardResource`) | Fase 7; ADR-0005 | `app/Services/v2/OrderListService.php:29-60` |
| API-CONTRACT-002 | 39 modelos serializan vía `toArrayAssoc()`/`toArrayAssocShort()`, opaco a análisis estático; solo capturado para `GET` vía `ResponseCalls` | Transversal | Alto | Abierto | Fases 3-7; ADR-0006 | `grep -rl "function toArrayAssoc" app/Models` → 39 archivos (lista completa reverificada 2026-08-02: AgendaAction, AuxiliaryProduct, Box, CaptureZone, Cebo, CommercialInteraction, Country, Customer, CustomsBroker, ExternalProcessor, ExternalUser, FieldOperator, FishingGear, Incident, Incoterm, Offer, OfferLine, OrderAuxiliaryLine, OrderMaritimeContainer, OrderMaritimeShippingDetail, OrderPallet, OrderPlannedProductDetail, Pallet, PalletBox, PaymentTerm, Product, ProductCategory, ProductFamily, Prospect, ProspectCategory, ProspectContact, RawMaterial, Salesperson, Species, Store, StoredPallet, Tax, Transport, User) |
| API-CONTRACT-003 | 14 Resources delegan el 100% de su serialización en `Model::toArrayAssoc()` (ya no vía magic `__call`, pero sigue opaco a introspección estática) | Transversal (Customer, Product, Store, Offer, Prospect, Country, PaymentTerm, FishingGear, CommercialInteraction, CustomsBroker, AuxiliaryProduct, OrderAuxiliaryLine, OrderMaritimeShippingDetail, OrderMaritimeContainer) | Medio | Parcialmente mitigado (magic `__call` eliminado en `CustomerResource`; el resto del patrón sigue) | Fase 3; ADR-0006 | `grep -rl "resource->toArrayAssoc()" app/Http/Resources/v2` → 14 archivos |
| API-CONTRACT-004 | `relationLoaded()` condicional: mismo campo puede ser `null` por "no aplica" o por "esta vista no cargó la relación", indistinguible en el spec | Transversal (`OrderResource`, `OrderDetailsResource`, `CustomerResource`, `PalletResource`, `SpeciesResource`) | Alto | Abierto (política definida en ADR-0008, no implementada) | Fase 2 (documentación por Resource); ADR-0008 | `app/Http/Resources/v2/OrderResource.php:20-29` |
| API-CONTRACT-005 | `FieldOrderResource` y `OrderResource` representan el mismo concepto de negocio ("pedido") con forma distinta, sin marcarlo como decisión de producto en el código | Autoventa/campo vs Pedidos | Medio | Abierto | Fase 7-8; D13 (bloqueada por decisión de negocio sobre app móvil) | Comparación `app/Http/Resources/v2/OrderResource.php` vs `FieldOrderResource.php`, reverificada 2026-08-02 |
| API-CONTRACT-006 | Parámetro de paginación inconsistente: `perPage` (76 archivos) vs `per_page` (19 archivos) | Transversal | Medio | Abierto (cifras idénticas a la auditoría original; sin cambios) | Fase 2; ADR-0005 | `grep -rl "'perPage'" app/Services app/Http` → 76; `grep -rl "'per_page'"` → 19 (reverificado 2026-08-02) |
| API-CONTRACT-007 | `IncotermResource` devuelve `created_at`/`updated_at` en snake_case mientras `ProductCategoryResource`, `ProductFamilyResource` y `TransportResource` (mismo módulo, Catálogos) devuelven `createdAt`/`updatedAt` camelCase | Catálogos | Alto (bloquea la afirmación "Catálogos es 100% uniforme"; bajo esfuerzo de fix) | Abierto — **hallazgo nuevo, no en auditorías previas** | Fase 1 (primer paso) | `app/Http/Resources/v2/IncotermResource.php:19-20` vs `app/Http/Resources/v2/ProductCategoryResource.php:22-23` |
| API-CONTRACT-008 | ~27 rutas `v2/superadmin/*` registradas en `routes/api.php` fuera del grupo `TenantMiddleware`, mismo archivo que el resto de la API de negocio | Superadmin | Bajo (para el contrato; el riesgo de exposición ya está mitigado) | Mitigado para el contrato frontend (excluidas explícitamente + test de regresión); nota arquitectónica general sin resolver | Ninguna para el contrato; posible limpieza de `routes/api.php` fuera de alcance de este plan | `routes/api.php:125-137`; `config/scribe_public.php:37-45`; `tests/Feature/ApiDocumentationTest.php:95-102` |
| API-CONTRACT-009 | `CrmDashboardController`, `CrmAgendaController`, `OrderStatisticsController`, `CeboDispatchStatisticsController`, `RawMaterialReceptionStatisticsController`, `AuxiliaryLineStatisticsController`, `StockStatisticsController` devuelven arrays manuales, 0 uso de `Resource` | CRM, Estadísticas | Alto | Abierto | Fase 4 | `grep -c Resource` = 0 en los 7 archivos (reverificado 2026-08-02) |
| API-CONTRACT-010 | Variantes de formato de error entre controladores (`{message,userMessage}` base vs `+details` vs `+error` ad hoc) | Transversal | Medio | **Heredado, no re-verificado exhaustivamente en esta sesión** | Fase 2 | `API_CONTRACT_AUDIT.md` §6 (`Handler.php` vs `OrderController.php` líneas históricas 78-83/130-141 — no releídas línea a línea el 2026-08-02) |
| API-CONTRACT-011 | `composer.json` exige PHP `^8.1`; CI y `CLAUDE.md` asumen 8.2 | Infraestructura | Bajo | Abierto, documental | Ninguna | `composer.json` (`require.php`) vs `.github/workflows/api-contract.yml` (`php-version: '8.2'`) |
| API-CONTRACT-012 | Reproducibilidad del spec entre generaciones locales repetidas contra la misma BD (`AUTO_INCREMENT` no transaccional en `ResponseCalls`) | Infraestructura del contrato | Bajo | Documentado, aceptado (no bloquea CI, que usa BD efímera) | Ninguna | `docs/api-contract.md` §5 |
| API-CONTRACT-013 | `ResponseCalls` de Scribe solo ejecuta rutas `GET`; las respuestas de éxito de `POST`/`PUT`/`PATCH` se infieren del código, no se verifican en ejecución real | Transversal | Medio | Limitación de herramienta, aceptada | Fase 3+ (relevante al decidir qué migrar primero) | `config/scribe.php` (`strategies.responses`, `ResponseCalls` con `only: ['GET *']`) |
| API-CONTRACT-014 | Los tests de contrato (`ApiDocumentationTest`) son un smoke test (genera sin error, contiene ciertas cadenas) — no comparan forma/tipos completos de payload; `OpenApiContractDiffer` solo compara propiedades de primer nivel | Testing | Medio | Abierto (diseño deliberado del differ, documentado en `docs/api-contract.md` §6, pero limita qué detecta CI) | Fase 2-3 (evaluar si merece un nivel de comparación más profundo) | `tests/Feature/ApiDocumentationTest.php`; `docs/api-contract.md` §6 |
| API-CONTRACT-015 | Solo existe un PHP enum (`Role`); campos categóricos (`status`, `order_type`) son strings validados por `in:`, sin tipo cerrado en el schema generado | Transversal | Bajo | Abierto, mejora recomendada no bloqueante | Ninguna (mejora incremental, cualquier fase) | `app/Enums/Role.php` (único archivo); `app/Models/Order.php:19-31` (constantes `STATUS_*`/`ORDER_TYPE_*`, candidatas a `enum` backed) |

**Regla para agentes futuros**: al cerrar o mitigar un ítem, cambia su `Estado` y añade la fecha y
el commit en una nota al final de la fila (o en el registro de ejecución, §8) — **no borres la
fila**. Al encontrar deuda nueva, añádela con el siguiente ID libre (`API-CONTRACT-016`, ...); no
reutilices IDs ni los renumeres.

---

## 5. Clasificación de módulos

Clasificaciones: **Listo para piloto** · **Necesita ajustes menores** · **Necesita saneamiento
medio** · **No migrable todavía** · **Contrato específico por contexto**.

| Módulo | Clasificación | Calidad actual del contrato | Riesgos | Dependencias | Tests existentes | Trabajo previo necesario | Orden recomendado |
|---|---|---|---|---|---|---|---|
| **Catálogos** (species, incoterms, payment-terms, countries, taxes, fishing-gears, capture-zones, product-categories, product-families) | Necesita ajustes menores (→ Listo para piloto tras fix) | Alta uniformidad, CRUD estándar, Resources explícitas en casi todos | Bajo | Ninguna | No se ha confirmado un Feature test dedicado por catálogo individual en esta sesión (revisar en Fase 1) | Corregir API-CONTRACT-007 (`IncotermResource` snake_case); confirmar que `SpeciesResource.fishingGear` (delega en `toArrayAssoc()`) no introduce inconsistencia | 1 (Fase 1) |
| **Producción** (productions, production-records, production-inputs/outputs, production-output-consumptions, cost-catalog, production-costs) | Necesita ajustes menores | Buena — Resources dedicados por entidad (`ProductionResource`, `ProductionRecordResource`, etc.); ningún modelo de este dominio está en la lista de 39 con `toArrayAssoc()` | Complejidad intrínseca del árbol de trazabilidad (`ProductionRecord`), no de serialización | Ninguna crítica para el contrato | `CLAUDE.md` §10 no lista un test Feature específico de Producción entre los "bloques con tests existentes" (Auth, Productos, Stock, Settings, Fichajes, Label) — confirmar antes de fiarse solo del contrato | Revisar Resources anidadas de escritura (no cubiertas por `ResponseCalls`, API-CONTRACT-013) | 4 (Fase 4) |
| **Proveedores** (suppliers, supplier-liquidations) | Necesita ajustes menores | `Supplier` no está en la lista de 39 con `toArrayAssoc()`; `SupplierLiquidation` mezcla Resource + descargas PDF | Bajo | Ninguna | No confirmado en esta sesión | Confirmar forma de `SupplierLiquidationResource` en escritura vs lectura | 4 (Fase 4) |
| **Usuarios** (users) | Necesita ajustes menores | `User` **sí** está en la lista de 39 con `toArrayAssoc()`; `UserController` usa Resources (5 referencias) | Medio — datos sensibles (roles, permisos); cualquier fuga de campo es más grave aquí que en catálogos | A.1 Auth (`AuthBlockApiTest`, según `CLAUDE.md` §10) | Confirmado: existe test de bloque Auth, no específico de `UserResource` | Verificar si `UserResource` delega en `User::toArrayAssoc()` (no confirmado en esta sesión) o serializa explícito | 4 (Fase 4) |
| **Productos** (products) | Necesita saneamiento medio | `Product` está en la lista de 39; `ProductResource` delega 100% en `toArrayAssoc()` (API-CONTRACT-003); uso muy extendido (Orders, Pallets, Production lo referencian) | Alto — tocar `Product::toArrayAssoc()` tiene radio de impacto sobre varios módulos a la vez | `ProductosBlockApiTest` (`CLAUDE.md` §10) | Confirmado, existe test de bloque | Planificar migración a Resource anidada real con cuidado de no romper los módulos que lo consumen (Orders, Pallets) | 5 (Fase 5) |
| **Clientes** (customers) | Necesita saneamiento medio | `Customer` está en la lista de 39; `CustomerResource` delega 100% (ya sin magic `__call`, API-CONTRACT-003) | Medio-alto — `customer` aparece anidado en `OrderResource`/`OrderDetailsResource` | Ninguna específica confirmada (revisar) | — | Migrar `CustomerResource` a campos explícitos + Resources anidadas para `paymentTerm`/`salesperson` | 5 (Fase 5) |
| **Palets** (pallets, adjuntos, timeline, expedición) | Necesita saneamiento medio | `Pallet`, `PalletBox`, `Box`, `StoredPallet` están en la lista de 39; `PalletController` usa 8 referencias a `Resource` (delega en Resources, no llama `toArrayAssoc()` directo desde el controlador); acciones bulk con `response()->json()` ad hoc según auditoría original (no re-verificado línea a línea) | Medio | Ninguna crítica confirmada | `StockBlockApiTest` cubre Stock/Store, no necesariamente Pallet en detalle (revisar) | Confirmar forma de acciones bulk (`destroyMultiple`, adjuntos) antes de generar tipos para ellas | 6 (Fase 6) |
| **Pedidos** (orders + incidencias + líneas auxiliares + exportación marítima) | Contrato específico por contexto / Necesita saneamiento medio | El módulo con más deuda concentrada: API-CONTRACT-001 (no determinismo), varios modelos anidados con `toArrayAssoc()` (`OrderPlannedProductDetail`, `OrderAuxiliaryLine`, `OrderMaritimeContainer`, `OrderMaritimeShippingDetail`, `OrderPallet`), pero también el módulo donde ya se corrigió un breaking change real (`Incident`, §2.2) | Alto — es el módulo de mayor tráfico según `CLAUDE.md` §8 (A.2 Ventas, 9/10) | Fase 3 (patrón de migración de serializadores ya probado en un módulo más simple primero) | `OrderApiTest`, `OrderMaritimeShippingApiTest` (10 tests, ver `docs/audits/laravel-evolution-log.md` entrada 2026-07-29) | Resolver o al menos decidir explícitamente qué hacer con API-CONTRACT-001 antes de generar tipos para `GET /v2/orders` sin parámetros especiales | 7 (Fase 7) |
| **Autoventa/campo** (`v2/field/*`) | Contrato específico por contexto (deliberado, pero no documentado como tal en código) | `FieldOrderResource`, `FieldOrderDetailsResource`, `FieldCustomerController`, etc. — contrato paralelo e intencionalmente reducido frente al interno | Medio — riesgo no es de calidad sino de que un futuro cliente (app móvil) asuma que "pedido" es un único tipo compartido con el panel interno | D13 (decisión de negocio pendiente sobre la app móvil) | No confirmado en esta sesión | Documentar explícitamente en el código (comentario en `FieldOrderResource`) que la divergencia es intencional, mientras D13 sigue abierta | 7-8 (coordinar con Fase 7 y Fase 8) |
| **Superadmin** (`v2/superadmin/*`) | No migrable todavía (por diseño, no por deuda) | Excluido explícitamente del contrato frontend, correcto y verificado con test de regresión (API-CONTRACT-008 mitigado) | Ninguno para el contrato frontend — el riesgo sería *incluirlo* por error | Ninguna (fuera de alcance por diseño) | `SuperadminFeatureSecurityTest` (mencionado en `API_CONTRACT_AUDIT.md`, no releído en esta sesión) | Ninguno — no debe entrar en el plan de generación de tipos salvo decisión de negocio explícita que lo revierta | Nunca (fuera de alcance permanente salvo decisión de negocio) |
| **CRM** (prospects, offers, crm/dashboard, crm/agenda) | No migrable todavía | 0 Resources en los controladores de dashboard/agenda (API-CONTRACT-009); `ProspectResource`/`OfferResource` sí existen y delegan en `toArrayAssoc()` (API-CONTRACT-003) para las entidades CRUD | Alto — es el bloque con más forma "ad hoc" de toda la API | Fase 4 requiere primero decidir el patrón de Resource para dashboards/estadísticas (aplica también a Estadísticas, ver abajo) | No confirmado en esta sesión | Diseñar Resources para `CrmDashboardController`/`CrmAgendaController` antes de intentar generar tipos | 4 (Fase 4, mayor esfuerzo dentro de la fase) |

**Nota sobre otros módulos no listados explícitamente en el encargo pero relevantes**: Almacenes
(`stores`), Fichajes (`punches`), Recepciones de materia prima, Despachos de cebo y Etiquetas
tienen, según `FRONTEND_OPENAPI_HANDOFF.md` §4 y esta verificación parcial, un contrato
razonablemente sólido (Resources reales, cubiertos por `ResponseCalls`). Se tratan como candidatos
naturales dentro de la **Fase 4** junto a Producción/Proveedores/Usuarios, sin fila propia en la
tabla para no duplicar alcance — confírmalos individualmente al planificar el detalle de esa fase.
**Estadísticas** (`statistics/*`) comparte clasificación con CRM (**No migrable todavía**, misma
causa: arrays manuales, API-CONTRACT-009) y se resuelve en la misma fase por tener el mismo
patrón de solución (diseñar Resources para respuestas agregadas).

---

## 6. Fases de ejecución

Cada fase es ejecutable de forma aislada y no depende circularmente de otra — las dependencias
son siempre "fase N depende de que la fase N-1 haya *cerrado sus criterios de aceptación*", nunca
al revés. Estimaciones relativas: **pequeña** (una sesión), **media** (varias sesiones, un
módulo), **grande** (varias semanas de calendario/varios módulos).

### Fase 0 — Activación real

- **Objetivo**: Confirmar en un entorno real (no solo por lectura de código) que el pipeline
  descrito en §2.1 funciona de extremo a extremo, y publicar el contrato de forma que el frontend
  pueda empezar a consumirlo.
- **Alcance**: `composer install` + entorno con MySQL; ejecutar `contract:test`, `contract:update`,
  `contract:verify`; confirmar que CI (`.github/workflows/api-contract.yml`) está en verde en la
  rama de trabajo; confirmar la URL pública de despliegue; primera adopción real desde el
  frontend (aunque sea leer el YAML una vez, no necesariamente generar tipos todavía — eso es
  Fase 1).
- **Fuera de alcance**: Corregir ninguna deuda de negocio (eso empieza en Fase 1). Generar tipos
  TypeScript (Fase 1).
- **Dependencias**: Ninguna — es la fase 0.
- **Archivos/módulos probables**: `.env`, `composer.json`, `.github/workflows/api-contract.yml`,
  despliegue (Coolify/IONOS, fuera de este repositorio).
- **Riesgos**: `composer install` puede fallar por el desajuste `^8.1` vs PHP 8.2 de CI
  (API-CONTRACT-011) — bajo pero real; la BD de generación puede no reflejar datos representativos
  (afecta a `ResponseCalls`, especialmente en módulos con `toArrayAssoc()`).
- **Pasos de implementación**:
  1. `composer install` en un entorno con PHP 8.1+ y MySQL accesible.
  2. `cp .env.example .env && php artisan key:generate`.
  3. `php artisan migrate` (BD central) + `php artisan contract:seed-fixture` (tenant demo).
  4. `composer contract:test` → confirmar verde.
  5. `composer contract:update` → confirmar que `public/openapi/frontend.yaml`/`meta.json` no
     cambian de forma inesperada respecto al commiteado (si cambian, decidir si es ruido de
     `AUTO_INCREMENT`, API-CONTRACT-012, o un cambio real sin commitear).
  6. `composer contract:verify` → confirmar sin `BREAKING` no reconocidos.
  7. Confirmar que el último run de `.github/workflows/api-contract.yml` en la rama de trabajo
     está en verde (vía la herramienta de CI disponible en ese momento).
  8. Confirmar con el equipo de despliegue la URL real de `public/openapi/frontend.yaml` en cada
     entorno (dev/staging/prod).
- **Tests**: `composer contract:test` (existente); ningún test nuevo necesario para esta fase.
- **Criterios de aceptación**: Los 3 comandos (`test`/`update`/`verify`) terminan en verde en un
  entorno real; CI en verde; alguien del lado frontend confirma que puede descargar
  `frontend.yaml` desde la URL acordada.
- **Entregable para frontend**: Confirmación de la URL estable + `meta.json` para detectar cambios
  de versión.
- **Estrategia de reversión**: Ninguna — esta fase no cambia código de negocio, solo verifica.
- **Estimación relativa**: Pequeña.
- **Estado**: Pendiente.

### Fase 1 — Piloto de catálogos

- **Objetivo**: Demostrar el flujo completo (Laravel → OpenAPI → tipos TS → cliente) en el módulo
  de menor riesgo, y dejarlo como plantilla replicable.
- **Alcance**: Corregir API-CONTRACT-007 (`IncotermResource`); revisar los ~9-11 endpoints de
  catálogos uno a uno contra el YAML generado; generar tipos TypeScript (en el repo frontend, no
  en este) para ese bloque; validar contra al menos dos endpoints reales
  (`GET /api/v2/species`, `GET /api/v2/incoterms`, siguiendo la recomendación ya escrita en
  `FRONTEND_OPENAPI_HANDOFF.md` §10).
- **Fuera de alcance**: Cualquier otro módulo. Migrar `toArrayAssoc()` de `Species.fishingGear`
  (eso es Fase 3, salvo que bloquee el piloto).
- **Dependencias**: Fase 0 completada (contrato accesible de forma fiable).
- **Archivos/módulos probables**: `app/Http/Resources/v2/IncotermResource.php`, el resto de
  `*Resource.php` de catálogos, `app/Http/Controllers/v2/{Species,Incoterm,PaymentTerm,Country,
  Tax,FishingGear,CaptureZone,ProductCategory,ProductFamily}Controller.php`.
- **Riesgos**: Bajo — es justamente el módulo elegido por tener el menor riesgo. El riesgo
  principal es de proceso: si el piloto no se ejecuta hasta el final (generación real de tipos +
  consumo real en una pantalla), no valida nada.
- **Pasos de implementación**:
  1. Corregir `IncotermResource::toArray()` para devolver `createdAt`/`updatedAt` camelCase
     (cambio de una línea, sin migración de BD).
  2. `composer contract:update` y revisar el diff — confirmar que el único cambio es el esperado.
  3. `composer contract:verify` — este cambio es técnicamente `BREAKING` (cambia el nombre de un
     campo de respuesta) si algún consumidor ya leía `created_at`/`updated_at` de `incoterms`; si
     el frontend aún no consume tipos generados de este endpoint, es seguro tratarlo como
     corrección de bug, no como breaking change de producto — confirmarlo antes de mergear.
  4. Revisar manualmente el resto de Resources de catálogos contra el YAML generado.
  5. (Lado frontend, fuera de este repo) Generar tipos TS desde `frontend.yaml` para el bloque
     Catálogos y validar contra `GET /api/v2/species`/`GET /api/v2/incoterms`.
  6. Migrar una pantalla real del frontend a los tipos generados.
- **Tests**: Añadir/confirmar un Feature test por catálogo si no existe ya (pendiente de
  confirmar en Fase 0/1, ver tabla de §5). Si `IncotermResource` no tiene test que cubra
  `created_at`/`updated_at`, añadir una aserción que fije el nombre correcto para evitar
  regresiones futuras.
- **Criterios de aceptación**: `composer contract:verify` sin `BREAKING` no reconocido tras el
  fix; el frontend confirma que al menos una pantalla real consume tipos generados desde
  `frontend.yaml` para un catálogo, sin necesidad de tipos escritos a mano para ese endpoint.
- **Entregable para frontend**: Tipos TS del bloque Catálogos + confirmación de que el flujo
  completo (fetch spec → generar tipos → consumir) funciona.
- **Estrategia de reversión**: Revertir el cambio de `IncotermResource` es trivial (una línea);
  no toca BD ni tiene efectos colaterales conocidos.
- **Estimación relativa**: Pequeña.
- **Estado**: Pendiente.

### Fase 2 — Normalización transversal mínima

- **Objetivo**: Reducir el ruido transversal que afecta a *todos* los módulos futuros antes de
  seguir extendiendo el piloto — no perseguir un rediseño completo, solo lo que bloquea contratos
  fiables módulo a módulo.
- **Alcance**: (a) Documentar en cada Resource relevante qué controladores garantizan la relación
  cargada (política D7, aplicado a `OrderResource`/`OrderDetailsResource`/`CustomerResource`/
  `PalletResource` como mínimo); (b) trazar un plan concreto (no ejecutarlo todo aquí) para
  unificar `perPage`/`per_page` en los 19 archivos que usan `per_page`; (c) confirmar/corregir el
  inventario de variantes de error (API-CONTRACT-010, pendiente de re-verificación); (d) revisar
  fechas/booleanos/IDs por consistencia de formato en los módulos que entren en Fase 4-7.
- **Fuera de alcance**: Migrar `toArrayAssoc()` (Fase 3). Tocar `OrderListService` (Fase 7, salvo
  que el trazado del plan de `perPage` lo incluya como ítem, sin ejecutarlo aquí).
- **Dependencias**: Fase 1 (el piloto debe haber confirmado el flujo antes de tocar convenciones
  transversales que afectan a todo lo demás).
- **Archivos/módulos probables**: `app/Http/Resources/v2/*.php` (comentarios), Form Requests de
  listado con `per_page` (a identificar con `grep -rl "'per_page'" app/Services app/Http`),
  `app/Exceptions/Handler.php`.
- **Riesgos**: Cambiar `per_page` → `perPage` en un endpoint ya consumido por el frontend es
  breaking; requiere coordinación (ver D11) antes de ejecutar, no solo en Fase 2 sino en la fase
  donde realmente se aplique el cambio.
- **Pasos de implementación**:
  1. Para cada Resource en la lista de API-CONTRACT-004, añadir un comentario explícito
     (`// Cargada en: OrderController::index, OrderController::show; NO cargada en: ...`).
  2. Generar la lista completa de los 19 archivos con `per_page` y clasificarlos: ¿son endpoints
     ya en producción y consumidos, o internos/superadmin/sesiones de bajo riesgo? (la propia
     `docs/frontend-integration/backend-api-changes.md` ya apunta a que sesiones/superadmin son
     los casos de `per_page` — confirmarlo).
  3. Re-ejecutar el grep de variantes de error de `API_CONTRACT_AUDIT.md` §6 contra el código
     actual y actualizar API-CONTRACT-010 con el resultado verificado.
  4. Documentar el resultado de 1-3 como un plan concreto de Fase 7 (para `perPage`) y de esta
     misma fase (para comentarios/errores), sin ejecutar la migración de `per_page` todavía si
     toca endpoints ya consumidos.
- **Tests**: Ninguno nuevo obligatorio (fase de documentación/planificación); si se corrige algún
  caso de error de bajo riesgo, añadir test de regresión puntual.
- **Criterios de aceptación**: Los Resources de Pedidos/Clientes/Palets tienen comentario
  explícito de carga de relaciones; existe una lista clasificada y priorizada de los 19 casos
  `per_page`; API-CONTRACT-010 queda con estado verificado (no "heredado sin confirmar").
  Aceptación se puede reevaluar con severidades: Alta prioridad si bloquea Fase 4-7, Baja si es
  documentación pura.
- **Entregable para frontend**: Ninguno directo (fase interna); indirectamente, mejor
  documentación de nullabilidad para los módulos que se migren después.
- **Estrategia de reversión**: Trivial (son comentarios y documentos, no cambios de
  comportamiento salvo que se decida ejecutar algún fix puntual de bajo riesgo).
- **Estimación relativa**: Pequeña-media.
- **Estado**: Pendiente.

### Fase 3 — Migración progresiva de serializadores (`toArrayAssoc()`)

- **Objetivo**: Establecer y validar el patrón de migración de `toArrayAssoc()` → Resource
  anidada real, en un caso acotado, antes de aplicarlo a los módulos de mayor tráfico.
- **Alcance**: Elegir 1-2 modelos de bajo radio de impacto de la lista de 39 (candidatos:
  `FishingGear`, usado solo desde `Species`; o `PaymentTerm`, `Country`) y migrarlos a Resources
  anidadas explícitas; escribir un test de Resource dedicado; documentar el patrón para que las
  fases 4-7 lo repliquen.
- **Fuera de alcance**: Migrar `Customer`, `Product`, `Order*` (alto radio de impacto — eso son
  las fases 5-7, una vez el patrón esté probado).
- **Dependencias**: Fase 2 (convenciones de nullabilidad/casing ya aplicadas al patrón nuevo).
- **Archivos/módulos probables**: `app/Models/FishingGear.php`, `app/Models/PaymentTerm.php`,
  `app/Models/Country.php`, sus Resources y `SpeciesResource.php` (consumidor de `FishingGear`).
- **Riesgos**: Bajo si se eligen modelos de bajo radio de impacto como se recomienda; el riesgo
  crece exponencialmente si se empieza por `Customer`/`Product` sin haber probado el patrón antes.
- **Pasos de implementación**:
  1. Crear `FishingGearResource` con campos explícitos (o confirmar que ya no delega, revisar
     estado actual — está en la lista de API-CONTRACT-003).
  2. Actualizar `SpeciesResource::toArray()` para usar `new FishingGearResource($this->fishingGear)`
     en vez de `$this->fishingGear?->toArrayAssoc()`.
  3. `composer contract:update` + `contract:verify` — confirmar que la forma no cambia (o, si
     cambia, que es un cambio deliberado y documentado).
  4. Repetir para `PaymentTerm`/`Country` si el primero valida bien el patrón.
  5. Documentar el patrón (antes/después, checklist) en este mismo documento (§8, registro de
     ejecución) para que las fases siguientes lo repliquen sin reinventar el enfoque.
- **Tests**: Un test unitario o Feature por Resource migrada que fije los campos esperados
  (evita regresión silenciosa si alguien vuelve a delegar en `toArrayAssoc()`).
- **Criterios de aceptación**: Al menos un modelo migrado de `toArrayAssoc()` a Resource anidada
  real, con `contract:verify` limpio (o breaking change reconocido y documentado) y test de
  regresión.
- **Entregable para frontend**: Ninguno directo si la forma no cambia; si cambia, entrada en
  `docs/frontend-integration/backend-api-changes.md`.
- **Estrategia de reversión**: `git revert` del commit de migración — no toca BD ni datos.
- **Estimación relativa**: Media.
- **Estado**: Pendiente.

### Fase 4 — Módulos intermedios (CRM, Proveedores, Usuarios, Producción)

- **Objetivo**: Llevar CRM/Estadísticas (los peores en cobertura de Resources) a un contrato
  serializable, y confirmar/pulir Proveedores/Usuarios/Producción (ya razonablemente sanos).
- **Alcance**: Diseñar e implementar Resources para `CrmDashboardController`, `CrmAgendaController`
  y los 5 controladores de `*StatisticsController` (API-CONTRACT-009); aplicar el patrón de Fase 3
  a los modelos de estos módulos que sigan en la lista de 39 (`CommercialInteraction`, `Offer`,
  `OfferLine`, `Prospect`, `ProspectCategory`, `ProspectContact`, `ExternalUser`, `FieldOperator`,
  `ExternalProcessor`, `CustomsBroker`, `Cebo`, `RawMaterial`, `Salesperson`, `User`).
- **Fuera de alcance**: Productos, Clientes, Palets, Pedidos (fases 5-7).
- **Dependencias**: Fase 3 (patrón de migración probado).
- **Archivos/módulos probables**: `app/Http/Controllers/v2/Crm*.php`,
  `app/Http/Controllers/v2/*StatisticsController.php`, Resources nuevas a crear para dashboards
  (no existen hoy).
- **Riesgos**: Alto en el sub-alcance de estadísticas — no hay Resource previa de la que partir,
  hay que diseñar la forma desde cero, con el riesgo de que el frontend actual dependa de la forma
  ad hoc actual de esos arrays. Confirmar con el frontend antes de cambiar cualquier forma ya en
  uso (no solo añadir Resource, sino verificar que la Resource nueva produce *la misma forma* que
  el array manual actual, salvo que se acuerde un breaking change).
- **Pasos de implementación**:
  1. Para cada controlador de estadísticas/CRM sin Resource: documentar la forma actual real
     (ejecutando el endpoint, no adivinando) antes de tocar nada.
  2. Crear una Resource (o varias, si el endpoint agrega datos heterogéneos) que reproduzca esa
     forma exactamente — el objetivo de esta fase es "hacerlo introspectable", no "rediseñarlo".
  3. `composer contract:update`/`verify` en cada paso.
  4. Migrar los modelos con `toArrayAssoc()` de este grupo siguiendo el patrón de Fase 3.
  5. Confirmar Proveedores/Usuarios/Producción contra el YAML generado; corregir cualquier
     desviación menor encontrada (equivalente a lo hecho con `IncotermResource` en Fase 1).
- **Tests**: Feature test por endpoint de estadísticas/CRM migrado (fijar forma+campos clave);
  reutilizar patrones de test ya existentes en el módulo CRM si los hay (confirmar en Fase 0/1).
- **Criterios de aceptación**: Los 7 controladores de API-CONTRACT-009 usan Resource; `contract:
  verify` limpio o breaking changes reconocidos y documentados; Proveedores/Usuarios/Producción
  confirmados sin desviaciones de casing/nullabilidad no documentadas.
- **Entregable para frontend**: Tipos TS para CRM/Estadísticas/Proveedores/Usuarios/Producción,
  con aviso explícito en `docs/frontend-integration/backend-api-changes.md` de cualquier forma que
  cambie respecto a la actual.
- **Estrategia de reversión**: `git revert` por endpoint/Resource; sin cambios de BD.
- **Estimación relativa**: Grande (es el sub-alcance con más superficie: 7 controladores sin
  Resource + ~14 modelos con `toArrayAssoc()`).
- **Estado**: Pendiente.

### Fase 5 — Productos y clientes

- **Objetivo**: Migrar los dos modelos de mayor radio de impacto transversal (`Product`,
  `Customer`) del patrón `toArrayAssoc()` a Resources anidadas reales.
- **Alcance**: `ProductResource`, `CustomerResource` y sus relaciones anidadas
  (`ProductCategory`, `ProductFamily` ya no delegan según lo verificado — confirmar en esta fase;
  `PaymentTerm`, `Salesperson` para `Customer`).
- **Fuera de alcance**: Palets, Pedidos (dependen de estos dos, se hacen después para no migrar
  todo a la vez).
- **Dependencias**: Fase 3 (patrón probado), Fase 4 (si `Salesperson`/`PaymentTerm` se migraron
  ahí, reutilizar).
- **Archivos/módulos probables**: `app/Models/Product.php`, `app/Models/Customer.php`,
  `app/Http/Resources/v2/ProductResource.php`, `app/Http/Resources/v2/CustomerResource.php`, y
  todo lo que los referencia anidado (`OrderResource`, `OrderPlannedProductDetail`,
  `PalletResource`, etc. — **sin migrarlos en esta fase**, solo actualizando la llamada anidada de
  `->toArrayAssoc()` a `new ProductResource(...)`/`new CustomerResource(...)` donde corresponda).
- **Riesgos**: Alto — radio de impacto real (Orders, Pallets, Production referencian estos
  modelos). Cualquier cambio de forma debe verificarse contra los tres consumidores principales,
  no solo contra el endpoint CRUD directo de Productos/Clientes.
- **Pasos de implementación**:
  1. Migrar `ProductResource` primero (menor complejidad relacional que `Customer`).
  2. `contract:update`/`verify`; revisar el diff en **todos** los endpoints que anidan `product`,
     no solo `GET /v2/products`.
  3. Repetir para `CustomerResource`.
  4. Actualizar los puntos de anidación conocidos (`OrderResource:20`, similares) para usar la
     Resource nueva en vez de `->toArrayAssoc()`.
- **Tests**: Tests de Resource dedicados + regresión en `ProductosBlockApiTest` y en `OrderApiTest`
  (para confirmar que el `product`/`customer` anidado en un pedido no cambia de forma
  inesperadamente).
- **Criterios de aceptación**: `ProductResource`/`CustomerResource` ya no delegan en
  `toArrayAssoc()`; `contract:verify` limpio o breaking reconocido; `OrderApiTest` y
  `ProductosBlockApiTest` en verde.
- **Entregable para frontend**: Tipos TS para Productos y Clientes; changelog si algún campo
  cambia de forma.
- **Estrategia de reversión**: `git revert`; sin cambios de BD (solo serialización).
- **Estimación relativa**: Grande.
- **Estado**: Pendiente.

### Fase 6 — Palets

- **Objetivo**: Consolidar el contrato de Palets (Store/Box/PalletBox/StoredPallet), incluidas
  las acciones bulk y de adjuntos que hoy usan `response()->json()` ad hoc.
- **Alcance**: `Pallet`, `PalletBox`, `Box`, `StoredPallet` (todos en la lista de 39); acciones
  bulk de `PalletController` (adjuntos, timeline, expedición).
- **Fuera de alcance**: Pedidos (Fase 7), aunque `Pallet` se referencia desde `Order` — solo se
  actualiza el punto de anidación, no se migra `Order` en esta fase.
- **Dependencias**: Fase 5 (si `Product` se anida dentro de una caja/palet, reutilizar la
  Resource ya migrada).
- **Archivos/módulos probables**: `app/Models/{Pallet,PalletBox,Box,StoredPallet}.php`,
  `app/Http/Resources/v2/PalletResource.php`, `app/Http/Controllers/v2/PalletController.php`
  (311 líneas, 8 referencias a Resource — confirmar cuáles de las 8 delegan y cuáles no).
- **Riesgos**: Medio — las acciones bulk/adjuntos no verificadas en detalle en esta sesión pueden
  tener formas de respuesta no estándar (ver hallazgo original de la auditoría sobre
  `response()->json()` ad hoc en bulk).
- **Pasos de implementación**: Análogos a Fase 5, aplicados a Palets; documentar explícitamente la
  forma real de cada acción bulk antes de tocarla (mismo principio que en Fase 4 paso 1).
- **Tests**: Reutilizar/ampliar `StockBlockApiTest` si cubre Pallet, o crear el test específico
  que falte.
- **Criterios de aceptación**: `Pallet`/`PalletBox`/`Box`/`StoredPallet` migrados o justificados
  como excepción documentada; acciones bulk con forma fija y documentada en el spec.
- **Entregable para frontend**: Tipos TS para Palets, incluidas acciones bulk.
- **Estrategia de reversión**: `git revert`; sin cambios de BD.
- **Estimación relativa**: Media.
- **Estado**: Pendiente.

### Fase 7 — Pedidos

- **Objetivo**: Resolver el módulo de mayor deuda concentrada y mayor tráfico: eliminar (o
  documentar como decisión de producto irreversible) el contrato no determinista de
  `OrderListService` (API-CONTRACT-001), migrar los modelos anidados de `Order` que siguen en
  `toArrayAssoc()`, y decidir explícitamente el estatus de `FieldOrderResource` frente a
  `OrderResource` (API-CONTRACT-005), en coordinación con D13.
- **Alcance**: `OrderListService`, `OrderResource`, `OrderDetailsResource`,
  `ActiveOrderCardResource`, `OrderPlannedProductDetail`, `OrderAuxiliaryLine`,
  `OrderMaritimeContainer`, `OrderMaritimeShippingDetail`, `OrderPallet`.
- **Fuera de alcance**: Cambiar `FieldOrderResource` en sí (eso depende de D13, tratado en Fase 8
  o antes si D13 se resuelve primero); tocar el canal `v2/field/*`.
- **Dependencias**: Fases 3-6 (los modelos anidados en un pedido — Product, Customer, Pallet — ya
  deben estar migrados o el trabajo se duplica).
- **Archivos/módulos probables**: `app/Services/v2/OrderListService.php`,
  `app/Http/Resources/v2/Order*.php`, `app/Http/Requests/v2/IndexOrderRequest.php`.
- **Riesgos**: Crítico — es el módulo de mayor tráfico (`CLAUDE.md` §8, A.2 Ventas 9/10) y el
  "Order Manager" del frontend ya depende del comportamiento actual de `?active=`. Cualquier
  cambio debe coordinarse explícitamente con el equipo/agente frontend antes de mergear, no
  después.
- **Pasos de implementación**:
  1. Confirmar con el frontend si el "Order Manager" puede migrarse a `GET /v2/orders/active`
     (ya disponible y estable) en vez de `GET /v2/orders?active=true`.
  2. Si la migración del consumidor es viable: deprecar formalmente (no eliminar todavía) el
     parámetro `active` en `IndexOrderRequest`/`OrderListService`, y planificar su eliminación en
     una fase posterior una vez confirmado que ningún consumidor lo usa.
  3. Si no es viable a corto plazo: dejar API-CONTRACT-001 como "Aceptado como deuda permanente,
     mitigado" con justificación explícita, en vez de "pendiente" indefinidamente.
  4. Migrar los 5 modelos anidados de `Order` siguiendo el patrón de Fase 3.
  5. Decidir y documentar en código (no solo en este plan) el estatus de `FieldOrderResource`.
- **Tests**: `OrderApiTest`, `OrderMaritimeShippingApiTest` en verde tras cada cambio; test nuevo
  que fije la forma estable de `GET /v2/orders/active` si no existe ya.
- **Criterios de aceptación**: API-CONTRACT-001 con una resolución explícita (migrado o aceptado
  como deuda permanente documentada, nunca "pendiente sin más"); los 5 modelos anidados migrados o
  justificados; `FieldOrderResource` con nota explícita de intencionalidad en el propio código.
- **Entregable para frontend**: Tipos TS para Pedidos; changelog explícito si `active` cambia de
  comportamiento.
- **Estrategia de reversión**: `git revert` por paso; el cambio de comportamiento de `active` (si
  se ejecuta) requiere coordinación de despliegue con el frontend, no solo revert de código.
- **Estimación relativa**: Grande.
- **Estado**: Pendiente. Bloqueada parcialmente por D13 en el sub-alcance de `FieldOrderResource`.

### Fase 8 — Aplicación móvil y contrato compartido

- **Objetivo**: Definir y ejecutar la estrategia de contrato para la futura app móvil, una vez
  resuelta D13.
- **Alcance**: Depende enteramente de la respuesta a D13 — no se puede detallar más sin esa
  decisión. Como orientación: si se reutiliza `v2/field/*`, el trabajo es documentar y estabilizar
  ese contrato como "el contrato móvil oficial"; si se necesita un contrato nuevo, esta fase
  empieza desde el STEP 0a del workflow de evolución (`CLAUDE.md` §18) como un bloque nuevo.
- **Fuera de alcance**: Nada puede ejecutarse en detalle hasta D13.
- **Dependencias**: Fase 7 (Pedidos estable, ya que la app móvil probablemente los consume);
  **D13 resuelta** (bloqueante real, no solo recomendado).
- **Archivos/módulos probables**: `app/Http/Resources/v2/Field*.php`, `routes/api.php` (bloque
  `v2/field/*`), o un nuevo prefijo si D13 decide un contrato nuevo.
- **Riesgos**: Sin acotar hasta D13.
- **Pasos de implementación**: No detallables hasta D13. Primer paso real: llevar D13 a decisión
  explícita del negocio (no de un agente) y volver a esta sección para expandirla.
- **Tests**: A definir tras D13.
- **Criterios de aceptación**: A definir tras D13.
- **Entregable para frontend/móvil**: A definir tras D13.
- **Estrategia de reversión**: A definir tras D13.
- **Estimación relativa**: Grande (probablemente subdividida en sub-fases una vez D13 se resuelva).
- **Estado**: Bloqueada (decisión de negocio pendiente, D13).

---

## 7. Hitos

| Hito | Criterio de cumplimiento | Fase |
|---|---|---|
| Contrato real desplegado y verificado en vivo | `composer contract:test/update/verify` en verde en un entorno real; CI en verde; URL pública confirmada | Fase 0 |
| Primer tipo generado | El frontend genera tipos TS reales desde `frontend.yaml` (no a mano) para al menos un endpoint | Fase 1 |
| Primer módulo consumiendo tipos generados | Una pantalla real del frontend usa los tipos generados de Catálogos | Fase 1 |
| Primera incompatibilidad detectada automáticamente | `contract:verify`/CI marca un `BREAKING` real en un PR (no un ejercicio de prueba) y se gestiona con el proceso ya definido (D11) | Cualquiera, orgánico |
| Catálogos migrados | Fase 1 con todos sus criterios de aceptación cumplidos | Fase 1 |
| Eliminación de un normalizador de casing en un módulo | API-CONTRACT-007 corregido y verificado | Fase 1 |
| Primer módulo complejo migrado | Fase 5 (Productos y Clientes) o Fase 7 (Pedidos) con criterios de aceptación cumplidos | Fase 5/7 |
| Pedidos migrados | Fase 7 con API-CONTRACT-001 resuelto explícitamente (migrado o aceptado documentado) | Fase 7 |

---

## 8. Registro de ejecución

El proyecto ya tiene un log histórico único y acumulativo para todo el CORE v1.0:
`docs/audits/laravel-evolution-log.md`. Las intervenciones sobre el contrato API se registran
**ahí**, como una entrada más (etiquetada `API Contract` en el título de la entrada), no en una
tabla paralela dentro de este plan — un futuro agente no debería tener que cruzar dos logs
distintos para reconstruir "qué pasó cuándo" en este repositorio.

Este plan mantiene solo un **índice corto** (fecha + fase + una línea + enlace a la entrada
completa). **No se borran filas.** Cada intervención añade una fila nueva; si solo corrige el
estado de una entrada previa, referencia la fecha original en vez de sobrescribirla.

| Fecha | Fase | Resumen (1 línea) | Entrada completa |
|---|---|---|---|
| 2026-08-02 | Planificación (previa a Fase 0) | Creación del plan maestro; verificación de deuda heredada + 1 hallazgo nuevo (`IncotermResource`, API-CONTRACT-007); extracción de decisiones durables a ADRs 0003-0008. | `docs/audits/laravel-evolution-log.md` → entrada "[2026-08-02] API Contract — Plan maestro y ADRs 0003-0008" |

**Al terminar cualquier intervención de este plan**: añade la entrada completa (formato ya
establecido en `docs/audits/laravel-evolution-log.md`: problemas abordados, cambios aplicados,
resultados de verificación, gap a 10/10, rollback plan) al evolution log, y una fila de una línea
aquí que enlace a ella. No dupliques el contenido completo en los dos sitios.

---

## 9. Estructura documental y cuándo crear sub-documentos

Este plan maestro y `docs/api-contract-current-status.md` son la única fuente obligatoria hoy,
como ficheros planos en `docs/` (no en una carpeta propia — ver nota más abajo). Los documentos
relacionados y cuándo crear cada uno:

| Documento | Cuándo se actualiza / crea |
|---|---|
| `docs/api-contract-master-plan.md` (este documento) | Siempre existe; se actualiza en cada intervención (§10). |
| `docs/api-contract-current-status.md` | Siempre existe; se actualiza en cada intervención (§10). |
| `docs/api-contract.md` | Documentación operativa (comandos). Se actualiza si cambia el *cómo* (nuevo comando, nuevo flag), no el estado del plan. |
| `docs/architecture-decisions/000X-*.md` | Una ADR nueva **solo** cuando surja una decisión arquitectónica durable no cubierta por las ADRs 0003-0008 ya existentes (p. ej. cuando D13 — estrategia app móvil — se resuelva, créala como `0009-*.md`). No crear una ADR por cada fila de la tabla de deuda (§4) ni por decisiones de secuenciación operativa (esas quedan en §3, sección "sin ADR propia"). |
| `docs/audits/laravel-evolution-log.md` | Una entrada nueva por cada intervención que ejecute (no solo planifique) una fase — ver §8. |
| `docs/frontend-integration/backend-api-changes.md` | Una entrada nueva por cada breaking change reconocido con `--allow-breaking` (regla ya vigente en `CLAUDE.md` §19 regla 3). |
| `FRONTEND_OPENAPI_HANDOFF.md` | Se actualiza cuando una fase cambia algo que ya afirma (módulos listos, convenciones, breaking changes) — sigue siendo el documento que un agente del repositorio frontend debe leer primero; este plan no lo sustituye. |
| Un doc de detalle de fase (p. ej. `docs/api-contract-phase-01-catalogs.md`) | **Solo** si el detalle de ejecución de una fase (comandos exactos, capturas de diffs, decisiones intermedias) no cabe razonablemente en su fila de §6 — no es obligatorio, es una válvula de escape. Si se crea, enlázalo desde la fila de la fase en §6 y mantén el resumen de §6 sincronizado, no lo reduzcas a un enlace vacío. |

**Nota sobre la ubicación**: la primera versión de este plan vivía en una carpeta dedicada
(`docs/api-contract/`), que colisionaba de nombre con el `docs/api-contract.md` ya existente y
rompía la convención de nomenclatura de `docs/` (minúsculas con guiones — ver
`docs/core-consolidation-plan-erp-saas.md` como referencia del patrón que sigue este plan). Se
aplanó a `docs/api-contract-master-plan.md` / `docs/api-contract-current-status.md` el mismo día
de su creación (2026-08-02, ver §8). No recrear la carpeta.

---

## 10. Protocolo para futuros agentes

Obligatorio para cualquier agente (IA o humano) que trabaje en algo relacionado con el contrato
API de este repositorio:

1. **Lee este plan maestro completo** antes de tocar rutas, Form Requests, Resources o
   controladores de `v2/*`. No empieces por `API_CONTRACT_AUDIT.md` ni por
   `FRONTEND_OPENAPI_HANDOFF.md` — son documentos de apoyo, este es el que manda sobre el estado
   y la secuencia de trabajo.
2. **Revisa `git status`** antes de asumir en qué estado está el árbol de trabajo — no confíes
   solo en lo que dice este documento sobre el último commit verificado.
3. **Confirma la próxima acción** en §11 (última sección) — si el estado del repositorio no
   coincide con lo que ahí se describe, detente y reconcilia antes de continuar (probablemente
   alguien ya avanzó y no se actualizó el registro, o viceversa).
4. **Trabaja solamente en la fase indicada** en §11, salvo que el usuario pida explícitamente otra
   cosa. No adelantes trabajo de una fase posterior "ya que estás".
5. **No adelantes fases sin necesidad real** — si detectas que una fase posterior sería más fácil
   de hacer ahora, propónlo explícitamente al usuario en vez de reordenar el plan unilateralmente.
6. **Ejecuta los tests relevantes** (`composer contract:test`, la suite Feature del módulo tocado,
   y `composer contract:verify`) antes de dar por terminada cualquier intervención que toque el
   contrato.
7. **Actualiza estados y registro**: cambia el `Estado` de la fase en §6, añade una fila nueva en
   §8 (nunca borres las anteriores), y actualiza `docs/api-contract-current-status.md`.
8. **Anota nuevas decisiones o problemas** encontrados en §3 (nueva decisión Dxx) o §4 (nuevo
   `API-CONTRACT-0xx`) — no los dejes solo en el mensaje de commit o en la conversación.
9. **Actualiza la próxima acción** (§11) antes de terminar tu intervención, aunque sea para decir
   "continuar el mismo paso, quedó a medias por X motivo".
10. **No marques una fase como `Completada`** sin evidencia verificable (tests en verde,
    `contract:verify` limpio, confirmación real de lo que pedía el criterio de aceptación) — "creo
    que funciona" no es evidencia.
11. **No borres deuda pendiente** de §4 para simplificar el documento, aunque parezca menor o
    "ya no importa" — cambia su `Estado`, no la elimines. Si de verdad ya no aplica (p. ej. el
    módulo entero se eliminó), márcala `Resuelto` con la razón, no borres la fila.
12. **No conviertas este plan en una lista de deseos desconectada del código** — cada afirmación
    nueva que añadas debe tener evidencia verificable (ruta de archivo, comando ejecutado, test
    que pasa), igual que se ha exigido en la creación de este documento. Si no puedes verificar
    algo en tu sesión (por ejemplo, por falta de entorno, como ocurrió en esta creación), dilo
    explícitamente en vez de darlo por bueno.

---

## Próxima acción recomendada

**Tarea**: Ejecutar la **Fase 0 — Activación real** (§6), empezando por sus pasos 1-6: levantar un
entorno con `composer install` + MySQL accesible, y confirmar que `composer contract:test`,
`composer contract:update` y `composer contract:verify` terminan en verde contra el código actual
(commit `f58e1cb` o el que esté en `HEAD` en ese momento).

**Por qué es el siguiente paso**: Todo lo demás en este plan (piloto de catálogos, normalización,
migración de serializadores) asume que el pipeline ya descrito en `docs/api-contract.md` funciona
de extremo a extremo en un entorno real. Esta sesión de planificación **no pudo verificarlo**: el
contenedor no tenía `vendor/`, `.env`, cliente `mysql` ni un servicio de base de datos accesible
(ver §2.3). Es la verificación de menor esfuerzo con mayor valor: si algo falla aquí, todo lo
demás en el plan está construido sobre una base no confirmada.

**Requisitos previos**: Acceso a un entorno con PHP 8.1+ (idealmente 8.2, como usa CI) y un
servicio MySQL 8.0 accesible (local vía Sail/Docker, o reutilizar el runner de CI). No requiere
tocar código de negocio.

**Comandos**:
```bash
composer install --no-interaction --prefer-dist
cp .env.example .env
php artisan key:generate --ansi
php artisan migrate --force
php artisan contract:seed-fixture
composer contract:test
composer contract:update
git diff --stat public/openapi/   # confirmar si cambia algo respecto al commiteado, y por qué
composer contract:verify
```

**Criterio de finalización**: Los tres comandos de contrato terminan en verde (`test`/`update`
sin errores; `verify` sin `BREAKING` no reconocido), y queda registrado si `contract:update`
produjo o no un diff respecto al `frontend.yaml` ya commiteado (si lo produjo, decidir si es ruido
de `AUTO_INCREMENT` — API-CONTRACT-012 — o un cambio real pendiente de commitear). Adicionalmente,
confirmar el estado real del workflow `.github/workflows/api-contract.yml` en la rama de trabajo
usando el acceso a CI disponible en ese momento (no estaba disponible en esta sesión de
planificación).

**Documento a actualizar al terminar**: Este mismo archivo — cambia el `Estado` de la Fase 0 en
§6, añade una fila nueva en §8 con lo realizado, y actualiza `docs/api-contract-current-status.md` con
la fase actual y el resultado.
