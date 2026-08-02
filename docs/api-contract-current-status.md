---
title: Estado actual del contrato API — resumen de 1 minuto
updated: 2026-08-02
---

# Estado actual del contrato API

**Estado general**: Infraestructura del contrato (Scribe, dos configs, CI, comandos
`contract:publish/check`) implementada y **verificada en CI real de extremo a extremo**. El
bloqueo que quedaba pendiente — API-CONTRACT-001, el job `api-contract` fallando de forma no
reproducible en `contract:check --fail-on-any` — se investigó a fondo y se resolvió: la causa real
no era `OrderListService` ni el parámetro `active` (esos siguen siendo deuda documentada, pero
deterministas), sino que el fixture usado para generar el contrato (`contract:seed-fixture`) creaba
un único pedido/cliente de ejemplo con varias relaciones FK nullable (`fieldOperator`,
`externalProcessor`, `incoterm` en Order; `fieldOperator`, notas, `a3erpCode`/`facilcomCode` en
Customer) asignadas al azar vía `faker->optional()` en las factories compartidas. Con una sola fila
de muestra, cada regeneración del contrato (base de datos efímera nueva) tenía una probabilidad
real de capturar un tipo distinto (objeto vs `null`) para esos campos — de ahí el "23 BREAKING,
todos en `GET /v2/orders`" que aparecía de forma no reproducible. Corregido fijando esas relaciones
a valores conocidos en `app/Console/Commands/SeedContractFixtureTenant.php` (solo la generación del
contrato; no se tocó `OrderListService` ni ningún Resource). Verificado empíricamente contra 4 bases
de datos efímeras independientes (`migrate` + `contract:seed-fixture` desde cero cada vez): el
schema capturado para `GET /v2/orders` es ahora idéntico en tipo en las 4 (solo difieren valores de
ejemplo/timestamps, no estructura). `contract:check --fail-on-any` pasa limpio contra una base de
datos efímera nueva.

**Fase actual**: Fase 0 — Activación real (**✅ Completada**, 2026-08-02). Los 3 comandos
(`test`/`update`/`verify`) terminan en verde contra un entorno real y reproducible; el contrato
publicado (`public/openapi/frontend.yaml`) refleja la forma estable. Deuda de negocio conocida
sigue abierta y fuera de alcance de Fase 0 (no bloquea CI): 39 modelos con `toArrayAssoc()`,
CRM/Estadísticas sin Resources, `perPage`/`per_page` sin unificar — ver `docs/api-contract-master-plan.md` §4.

**Último trabajo realizado**: 2026-08-02 (tercera sesión). Resumen cronológico:

1. Se confirmó el estado del repo (`main` alineado con `origin/main`, sin cambios sin commitear) y
   se leyó la "Próxima acción recomendada" del plan maestro: decidir el tratamiento de
   API-CONTRACT-001. El usuario eligió "fijar la captura de Scribe para `GET /v2/orders`" (frente a
   adelantar Fase 7 o aceptar el ruido documentándolo).
2. Investigación empírica con bases de datos efímeras frescas (`migrate` + `contract:seed-fixture`
   + `scribe:generate`) para aislar la causa real, evitando reutilizar fixtures de sesiones
   anteriores (que resultaron generar falsos positivos por caché de Redis de `TenantMiddleware`
   entre bases de datos distintas bajo el mismo subdominio `demo-tenant` — artefacto de la propia
   metodología de prueba, no un bug real; se documenta como aviso para quien retome esto).
3. Identificada la causa real: `faker->optional()` en `OrderFactory`/`CustomerFactory`/
   `ExternalProcessorFactory` sobre relaciones/campos que solo tienen 1 fila de muestra en el
   fixture. No es lo que originalmente se sospechaba (el parámetro `active` en sí es y era
   determinista — su ejemplo está fijado en el docblock de `IndexOrderRequest`).
4. Corregido `app/Console/Commands/SeedContractFixtureTenant.php`: las relaciones/campos
   opcionales de los 2 clientes y 2 pedidos de ejemplo ahora se fijan a valores conocidos, sin
   tocar las factories compartidas (siguen aleatorias para tests reales) ni `OrderListService`.
5. Verificado contra 4 bases de datos efímeras independientes adicionales: `GET /v2/orders` produce
   el mismo tipo de schema en las 4. `contract:check --fail-on-any` pasa limpio.
6. Republicado `public/openapi/frontend.yaml`/`meta.json` (`composer contract:update`) con la nueva
   forma estable. El diff resultante frente al contrato anterior: 8 cambios "BREAKING" (solo en el
   spec, no en el comportamiento real de la API — `fieldOperator`/`externalProcessor`/`incoterm`
   pasan de documentarse como `string` a documentarse correctamente como objeto/entero anidado) +
   18 cambios compatibles (nuevos campos en `GET /v2/external-processors/{id}`, antes sin ejemplo
   capturable). Documentado en `docs/frontend-integration/backend-api-changes.md` Sprint 3.
7. `composer contract:test` (`ApiDocumentationTest`, 8 tests) en verde contra el fixture corregido.

Detalle completo en `docs/audits/laravel-evolution-log.md` → entrada
"[2026-08-02] API Contract — Fase 0: cierre, causa real de API-CONTRACT-001 identificada y
corregida en el fixture".

**Bloqueos restantes** (ninguno bloquea ya el cierre de Fase 0):
- De negocio: D13 (estrategia de app móvil) sigue pendiente — bloquea el detalle de Fase 8, no
  Fase 0-7.
- Fuera de este repositorio: sigue sin confirmarse si la credencial que tenía `.env.example`
  (`94.143.137.84:3308`, saneada en la sesión anterior) era real y, si lo era, si ya se roto en el
  servidor — no verificable ni accionable desde este agente.
- Fuera de alcance: API-CONTRACT-016 (tests preexistentes fallando, ajenos al contrato) sigue
  abierto, sin tocar; requiere tratamiento por bloque vía `evolution-workflow`/`/task-workflow`.
- Deuda transversal conocida (39 `toArrayAssoc()`, CRM/Estadísticas sin Resources, `perPage`/
  `per_page`) sigue abierta — es exactamente lo que las Fases 1-7 abordan a partir de ahora.

**Próxima acción**: Iniciar **Fase 1 — Piloto de catálogos** (`docs/api-contract-master-plan.md`
§6): corregir `IncotermResource` (API-CONTRACT-007, snake_case) y generar los primeros tipos
TypeScript reales desde `frontend.yaml` para el bloque Catálogos.

**Plan maestro**: [`docs/api-contract-master-plan.md`](./api-contract-master-plan.md)
**Decisiones arquitectónicas**: [`docs/architecture-decisions/`](./architecture-decisions/readme.md) (ADRs 0003-0008)
