# Backend API Changes — Frontend Integration Reference

Documento acumulativo de cambios en el backend que afectan la integración con el frontend Next.js.

Cada sección indica el sprint de origen, los endpoints afectados, y el impacto esperado en el cliente.

---

## Sprint 1 — 2026-03-24 (Quick Wins P0)

### Resumen de cambios visibles al frontend

| Cambio | Tipo | Endpoints afectados | Acción requerida |
|---|---|---|---|
| perPage cap a 100 | Comportamiento | Todos los listados | Verificar que frontend no envíe `perPage > 100` |
| Resto de cambios | Interno | — | Ninguna |

---

### 1. Límite máximo de paginación: `perPage = 100`

**Fecha**: 2026-03-24
**Motivación**: Protección contra peticiones abusivas (`perPage=999999`) que podían causar OOM en el servidor.

**Comportamiento anterior**: El parámetro `perPage` no tenía límite superior. El frontend podía enviar cualquier valor.

**Comportamiento nuevo**: Todos los endpoints de listado tienen un límite máximo de **100 ítems por página**. Si se envía `perPage=200`, la respuesta contiene máximo 100 ítems.

**Endpoints afectados** (todos los listados paginados de la API v2):

| Endpoint | perPage default | perPage máximo |
|---|---|---|
| `GET /api/v2/orders` | 10 | 100 |
| `GET /api/v2/pallets` | 10 | 100 |
| `GET /api/v2/suppliers` | 12 | 100 |
| `GET /api/v2/raw-material-receptions` | 12 | 100 |
| `GET /api/v2/customers` | 10 | 100 |
| `GET /api/v2/field-operators` | 10 | 100 |
| `GET /api/v2/prospects` | 10 | 100 |
| `GET /api/v2/product-categories` | 12 | 100 |
| `GET /api/v2/commercial-interactions` | 10 | 100 |
| `GET /api/v2/product-families` | 12 | 100 |
| `GET /api/v2/cebo-dispatches` | 12 | 100 |
| `GET /api/v2/offers` | 10 | 100 |
| `GET /api/v2/products` | 14 | 100 |
| `GET /api/v2/users` | 10 | 100 |
| `GET /api/v2/salespeople` | 10 | 100 |

**Respuesta de ejemplo** con `?perPage=200` (antes devolvía 200, ahora devuelve 100):
```json
{
  "data": [ ...100 items... ],
  "links": { ... },
  "meta": {
    "current_page": 1,
    "per_page": 100,
    "total": 350,
    "last_page": 4
  }
}
```

**Acción requerida por el frontend**:
- Si alguna pantalla enviaba `perPage > 100` para cargar "todo", debe adaptarse a paginar correctamente.
- Para selects/dropdowns que necesiten todos los ítems, usar los endpoints `/options` o `/op` que devuelven listados completos sin paginación (estos no se ven afectados).

---

### 2. Autenticación magic link y OTP (sin cambio de contrato)

**Fecha**: 2026-03-24
**Cambio interno**: Los endpoints `POST /api/v2/auth/verify-magic-link` y `POST /api/v2/auth/verify-otp` tienen ahora protección contra doble uso del mismo token (fix de race condition TOCTOU).

**Impacto en frontend**: Ninguno. Las respuestas JSON son idénticas. El único cambio de comportamiento es que si dos requests simultáneos usan el mismo token, solo uno tendrá éxito (el correcto). El frontend no debería estar enviando el mismo token dos veces.

---

### 3. Mejoras de rendimiento internas (sin cambio de contrato)

**Fecha**: 2026-03-24

Los siguientes cambios son transparentes al frontend:

- **LogActivity**: El registro de actividad de usuario ahora usa cache de geolocalización (24h por IP) y escritura asíncrona. Los logs siguen creándose, solo más eficientemente.
- **TenantMiddleware**: El lookup de tenant ahora usa cache (5 min). No hay cambio en el comportamiento observable.
- **NFC Punch**: La determinación del tipo de fichaje (entrada/salida) es ahora atómica. El comportamiento visible es idéntico — siempre devuelve el tipo correcto.

---

## Sprint 2 — 2026-08-02 (Contrato OpenAPI)

### Resumen de cambios visibles al frontend

| Cambio | Tipo | Endpoints afectados | Acción requerida |
|---|---|---|---|
| `GET /v2/orders/{orderId}/incident` cambia de forma | **Breaking** | 1 endpoint | Actualizar el parseo: ya no es el modelo Eloquent crudo |

### 1. `GET /v2/orders/{orderId}/incident` ahora devuelve la misma forma que `POST`/`PUT` (breaking)

**Fecha**: 2026-08-02
**Motivación**: el mismo recurso `Incident` se serializaba de forma distinta según el verbo —
`GET` devolvía el modelo Eloquent crudo (columnas de BD en `snake_case`: `order_id`,
`resolution_type`, `created_at`, ...), mientras que `POST`/`PUT` devolvían `Incident::toArrayAssoc()`
(`camelCase`: `resolutionType`, `resolutionNotes`, ...). Detectado en `API_CONTRACT_AUDIT.md` §6-7
como bloqueador para generar un contrato OpenAPI fiable.

**Comportamiento anterior** (`GET`):
```json
{
  "id": 1,
  "order_id": 42,
  "description": "...",
  "status": "open",
  "resolution_type": null,
  "resolution_notes": null,
  "resolved_at": null,
  "created_at": "2026-08-02T10:00:00.000000Z",
  "updated_at": "2026-08-02T10:00:00.000000Z"
}
```

**Comportamiento nuevo** (`GET`, ahora idéntico a `POST`/`PUT`):
```json
{
  "id": 1,
  "description": "...",
  "status": "open",
  "resolutionType": null,
  "resolutionNotes": null,
  "resolvedAt": null,
  "createdAt": "2026-08-02T10:00:00+00:00",
  "updatedAt": "2026-08-02T10:00:00+00:00"
}
```

Nótese además: `order_id` ya no se expone (redundante, viene de la propia URL) y las fechas se
serializan vía `toIso8601String()` en vez del formato por defecto de Eloquent.

**Acción requerida por el frontend**: si algún componente lee `order_id`, `resolution_type`,
`resolution_notes`, `resolved_at`, `created_at` o `updated_at` de la respuesta de `GET .../incident`,
actualizarlo a los nombres camelCase (`resolutionType`, `resolutionNotes`, `resolvedAt`,
`createdAt`, `updatedAt`) — igual que ya se hacía para las respuestas de `POST`/`PUT` de este
mismo endpoint.

### 2. Endpoints internos/administrativos ya no son alcanzables desde el contrato publicado

**Fecha**: 2026-08-02
**Cambio**: no es un cambio de comportamiento de la API (las rutas siguen funcionando igual),
sino de qué aparece documentado en `public/openapi/frontend.yaml`. `v2/superadmin/*` y
`v2/public/impersonation/*` nunca deben usarse desde el frontend de negocio ni la app móvil — si
algo los está consumiendo hoy, es una integración fuera de contrato que debería revisarse.

**Impacto en frontend**: Ninguno si el frontend de negocio nunca llamó a esas rutas (lo esperado).

---

## Sprint 3 — 2026-08-02 (Contrato API — Fase 0, estabilización de `GET /v2/orders`)

### Resumen de cambios visibles al frontend

| Cambio | Tipo | Endpoints afectados | Acción requerida |
|---|---|---|---|
| `fieldOperator`/`externalProcessor`/`incoterm` (y sus `*Id`) documentados correctamente como objeto/entero anidado en vez de `string` | **Breaking** (solo en el spec; el comportamiento en producción no cambió) | `GET /v2/orders`, `GET /v2/orders/{id}`, `GET /v2/customers/{id}` | Si generaste tipos TS desde una versión anterior de `frontend.yaml`, regenéralos |

### 1. Tipos de `fieldOperator`, `externalProcessor`, `incoterm` corregidos en el spec (breaking solo documental)

**Fecha**: 2026-08-02
**Motivación**: no es un cambio de comportamiento de la API — `OrderResource`/`CustomerResource`
siempre devolvieron estos campos como objeto anidado (o `null` si la relación no aplica). El
`public/openapi/frontend.yaml` anterior los documentaba incorrectamente como `type: string` porque
el fixture usado por `php artisan contract:seed-fixture` (base de datos desechable que Scribe usa
para capturar ejemplos reales) creaba un único pedido/cliente con estas relaciones FK asignadas al
azar (`faker->optional()`), y con una sola fila de muestra la probabilidad de que el ejemplo
capturado saliera `null` era alta — un valor `null` sin contexto adicional se documentaba con un
tipo poco informativo. Esto también hacía que el job `api-contract` de CI (`contract:check
--fail-on-any`) fallara de forma no reproducible en cada ejecución (base de datos efímera nueva en
cada run ⇒ nueva probabilidad al azar), ver `API-CONTRACT-001` en
`docs/api-contract-master-plan.md`. Corregido fijando estas relaciones a valores conocidos en
`app/Console/Commands/SeedContractFixtureTenant.php` (solo afecta a la generación del contrato, no
a `OrderListService` ni a ningún Resource).

**Documentado antes** (`GET /v2/orders`, ejemplo):
```json
{ "fieldOperator": "algún string", "fieldOperatorId": "algún string", "externalProcessor": "...", "incoterm": "..." }
```

**Documentado ahora** (refleja lo que la API siempre devolvió):
```json
{
  "fieldOperator": { "id": 1, "name": "...", "emails": [...] },
  "fieldOperatorId": 1,
  "externalProcessor": { "id": 1, "name": "...", "legalName": "...", "...": "..." },
  "externalProcessorId": 1,
  "incoterm": { "id": 1, "code": "...", "description": "..." }
}
```

**Acción requerida por el frontend**: si ya generaste tipos TypeScript desde una versión anterior
de `frontend.yaml` para `GET /v2/orders`, `GET /v2/orders/{id}` o `GET /v2/customers/{id}`,
regenéralos — el runtime real de la API no cambia, solo la precisión del tipo documentado.

---

## Sprint 4 — 2026-08-03 (Rentabilidad: fix 500 en rangos grandes + flujo asíncrono)

### Resumen de cambios visibles al frontend

| Cambio | Tipo | Endpoints afectados | Acción requerida |
|---|---|---|---|
| Límite de 60 días en la consulta síncrona | **Breaking** (nueva validación) | `GET .../profitability-summary`, `GET .../profitability-products` | Sí — ver §1 y §3 |
| Nuevos endpoints asíncronos (dispatch + polling) | Nuevo | `POST/GET .../profitability-summary/jobs`, `POST/GET .../profitability-products/jobs` | Sí — implementar el flujo para rangos > 60 días |
| Fix de rendimiento interno (N+1 en coste de trazabilidad) | Interno | Los mismos 4 endpoints de arriba | Ninguna, pero explica por qué antes tardaba/fallaba |

### Contexto — por qué cambia esto

`GET /api/v2/statistics/orders/profitability-summary` y `GET /api/v2/statistics/orders/profitability-products`
estaban devolviendo **500 Internal Server Error** cuando se consultaban con rangos de fechas amplios
(ej. `dateFrom=2026-01-01&dateTo=2026-08-03`, ~7 meses). Causa: por cada caja del pedido se lanzaba
una consulta SQL adicional para resolver su coste de trazabilidad (N+1), y con rangos grandes el
volumen de queries agotaba el `memory_limit`/`max_execution_time` por defecto de PHP-FPM antes de
poder responder.

Se aplicaron dos cambios:

1. **Fix del N+1** (interno, transparente): la respuesta ahora es mucho más rápida para el mismo volumen de datos. No requiere cambios en el frontend.
2. **Límite de rango + flujo asíncrono** (requiere cambios en el frontend): en vez de dejar que una consulta grande siga arriesgándose a agotar los límites de una petición HTTP síncrona, la API ahora **rechaza rangos > 60 días** en los endpoints síncronos y ofrece un **flujo asíncrono equivalente al que ya existe para la exportación Excel** (`POST .../export-jobs` + polling), sin límite de rango, porque corre en un worker de cola en background con límites ampliados (memoria 2048M, hasta 30 min de ejecución).

No se usó un límite de días "adivinado" como solución definitiva: el límite de 60 días es solo la
frontera entre "resuélvelo al instante" y "resuélvelo en background". El dato real que importa es
el volumen de pedidos/cajas del tenant en ese rango, no los días en sí — por eso el flujo asíncrono
no tiene ningún tope y es la vía recomendada para cualquier consulta que no sea "los últimos 1-2 meses".

---

### 1. `GET /api/v2/statistics/orders/profitability-summary` — ahora limitado a 60 días (breaking)

**Sin cambios**: query params (`dateFrom`, `dateTo`, `productIds[]` opcional), headers, forma de la respuesta 200.

**Nuevo**: si `dateTo - dateFrom > 60 días`, la API responde **422** en vez de intentar resolver la consulta.

**Respuesta 422** (nueva):
```json
{
  "message": "Error de validación.",
  "userMessage": "El rango de fechas no puede superar 60 días. Para periodos más amplios, usa la consulta asíncrona.",
  "code": "VALIDATION_ERROR",
  "errors": {
    "dateTo": [
      "El rango de fechas no puede superar 60 días. Para periodos más amplios, usa la consulta asíncrona."
    ]
  }
}
```

**Respuesta 200** (sin cambios de forma, para referencia):
```json
{
  "period": { "from": "2026-03-01", "to": "2026-03-31" },
  "ordersCount": 1,
  "totalRevenue": 75,
  "totalCost": 30,
  "grossMargin": 45,
  "marginPercentage": 60,
  "coveredBoxes": 1,
  "uncoveredBoxes": 1,
  "costCoverageBoxesPct": 50,
  "salePriceAlert": {
    "active": false,
    "boxesWithoutSalePrice": 0,
    "hint": null
  }
}
```

---

### 2. `GET /api/v2/statistics/orders/profitability-products` — mismo límite de 60 días (breaking)

Idéntico al anterior: mismos query params (`dateFrom`, `dateTo` — **sin** `productIds`, este endpoint nunca lo soportó), mismo 422 si el rango supera 60 días (mismo texto de mensaje).

**Respuesta 200** (sin cambios de forma, para referencia):
```json
{
  "period": { "from": "2026-03-01", "to": "2026-03-31" },
  "products": [
    {
      "product": { "id": 8, "name": "Merluza fresca" },
      "totalWeightKg": 7.52,
      "totalRevenue": 99.41,
      "totalCost": 30.08,
      "grossMargin": 69.33,
      "marginPercentage": 69.74,
      "revenuePerKg": 13.2194,
      "costPerKg": 4.0,
      "marginPerKg": 9.2194,
      "ordersCount": 1
    }
  ]
}
```

---

### 3. Flujo asíncrono — nuevo, para rangos > 60 días

Cuatro endpoints nuevos, dos pares `POST` (crear job) + `GET` (consultar estado/resultado), uno
por cada consulta. **Mismo patrón que ya usáis para `POST .../profitability-summary/export-jobs`**
(descarga de Excel): si ya tenéis ese polling implementado, es el mismo código con otra URL y sin
paso de descarga de fichero (el resultado es JSON, no un Excel).

| Acción | Método + ruta |
|---|---|
| Crear job de resumen | `POST /api/v2/statistics/orders/profitability-summary/jobs` |
| Consultar job de resumen | `GET /api/v2/statistics/orders/profitability-summary/jobs/{id}` |
| Crear job de desglose por producto | `POST /api/v2/statistics/orders/profitability-products/jobs` |
| Consultar job de desglose por producto | `GET /api/v2/statistics/orders/profitability-products/jobs/{id}` |

**Headers**: los mismos de siempre (`X-Tenant`, `Authorization: Bearer {token}`, `Accept: application/json`).

#### 3.1 Crear el job

`POST /api/v2/statistics/orders/profitability-summary/jobs`

Body (idéntico a los query params del endpoint síncrono, pero como JSON body):
```json
{
  "dateFrom": "2026-01-01",
  "dateTo": "2026-08-03",
  "productIds": []
}
```
`productIds` es opcional y solo aplica al job de **summary**. El job de **products** solo acepta `dateFrom`/`dateTo` (igual que su equivalente síncrono).

`POST /api/v2/statistics/orders/profitability-products/jobs`:
```json
{
  "dateFrom": "2026-01-01",
  "dateTo": "2026-08-03"
}
```

**No hay límite de rango en estos dos endpoints** — a diferencia de los síncronos, aceptan cualquier `dateFrom`/`dateTo` válido (`dateTo >= dateFrom`).

**Respuesta 202** (ambos endpoints, misma forma — el campo `type` indica cuál es):
```json
{
  "id": "5648482e-d2bb-4dd9-a559-b072883195fe",
  "type": "summary",
  "status": "pending",
  "filters": { "dateFrom": "2026-01-01", "dateTo": "2026-08-03", "productIds": [] },
  "result": null,
  "errorMessage": null,
  "createdAt": "2026-08-03T09:18:43+02:00",
  "startedAt": null,
  "finishedAt": null
}
```

`id` es el identificador a usar para el polling (es un UUID, no el `id` autoincremental interno).

#### 3.2 Consultar el estado / obtener el resultado (polling)

`GET /api/v2/statistics/orders/profitability-summary/jobs/{id}` (o el equivalente de `profitability-products`).

`status` puede ser: `pending` → `processing` → `finished` (éxito) o `failed` (error).

**Mientras está en proceso**:
```json
{
  "id": "5648482e-d2bb-4dd9-a559-b072883195fe",
  "type": "summary",
  "status": "processing",
  "filters": { "dateFrom": "2026-01-01", "dateTo": "2026-08-03", "productIds": [] },
  "result": null,
  "errorMessage": null,
  "createdAt": "2026-08-03T09:18:43+02:00",
  "startedAt": "2026-08-03T09:18:44+02:00",
  "finishedAt": null
}
```

**Cuando termina bien** (`status: "finished"`) — `result` contiene **exactamente la misma forma que
la respuesta 200 del endpoint síncrono equivalente** (§1 para `summary`, §2 para `products`):
```json
{
  "id": "5648482e-d2bb-4dd9-a559-b072883195fe",
  "type": "summary",
  "status": "finished",
  "filters": { "dateFrom": "2026-01-01", "dateTo": "2026-08-03", "productIds": [] },
  "result": {
    "period": { "from": "2026-01-01", "to": "2026-08-03" },
    "ordersCount": 6,
    "totalRevenue": 122.35,
    "totalCost": null,
    "grossMargin": null,
    "marginPercentage": null,
    "coveredBoxes": 0,
    "uncoveredBoxes": 8,
    "costCoverageBoxesPct": 0,
    "salePriceAlert": {
      "active": true,
      "boxesWithoutSalePrice": 6,
      "hint": "6 cajas sin precio unitario (€/kg) en la previsión del pedido."
    }
  },
  "errorMessage": null,
  "createdAt": "2026-08-03T09:18:43+02:00",
  "startedAt": "2026-08-03T09:18:44+02:00",
  "finishedAt": "2026-08-03T09:18:44+02:00"
}
```

**Si falla** (`status: "failed"`) — `result` es `null` y `errorMessage` trae el detalle (mensaje técnico, no pensado para mostrar tal cual al usuario; mostrar un mensaje genérico tipo "No se pudo calcular la rentabilidad, inténtalo de nuevo"):
```json
{
  "status": "failed",
  "result": null,
  "errorMessage": "SQLSTATE[...]: ...",
  "finishedAt": "2026-08-03T09:18:44+02:00"
}
```

**404**: si el `id` no existe, o si se consulta un UUID de un job de `summary` contra la ruta de `products` (o viceversa) — cada ruta de consulta solo devuelve jobs de su propio tipo.

#### 3.3 Recomendación de implementación

- **Decidir el flujo en el cliente antes de llamar a la API**, sin depender de que el síncrono
  devuelva 422: calculad la diferencia en días entre `dateFrom`/`dateTo` al cambiar el selector de
  fechas del dashboard. Si es `≤ 60` días, llamad al endpoint síncrono existente (`GET
  .../profitability-summary` / `GET .../profitability-products`) tal cual ya lo hacéis hoy — misma
  respuesta, más rápido que antes gracias al fix del N+1. Si es `> 60` días, usad el flujo async.
- **Manejar igualmente el 422** como red de seguridad (por si hay algún desajuste de reloj/timezone
  entre cliente y servidor en el cálculo de días), haciendo fallback automático al flujo async si
  llega un 422 con `errors.dateTo` en la respuesta.
- **Intervalo de polling recomendado**: 1.5–2s. En la práctica, los jobs de summary/products
  terminan en 1-3 segundos incluso con varios meses de datos (el fix del N+1 los hace muy rápidos);
  el límite duro del worker es de 30 minutos, pero un tiempo de espera razonable en el cliente antes
  de mostrar "esto está tardando más de lo normal" es de **20-30 segundos** (10-15 intentos de
  polling).
- **UI**: mostrar un estado de carga mientras `status` sea `pending`/`processing` (idéntico a como
  ya se maneja para la exportación Excel), y el resultado o el error cuando `status` sea
  `finished`/`failed`.

**Pseudocódigo del flujo completo**:
```ts
async function fetchProfitabilitySummary(dateFrom: string, dateTo: string, productIds: number[] = []) {
  const days = daysBetween(dateFrom, dateTo);

  if (days <= 60) {
    const res = await api.get('/statistics/orders/profitability-summary', {
      params: { dateFrom, dateTo, productIds },
    });
    if (res.status === 200) return res.data;
    if (res.status !== 422) throw res; // 422 -> cae al flujo async de abajo
  }

  const { id } = await api.post('/statistics/orders/profitability-summary/jobs', {
    dateFrom, dateTo, productIds,
  }).then(r => r.data);

  return pollUntilFinished(`/statistics/orders/profitability-summary/jobs/${id}`);
}

async function pollUntilFinished(url: string, { intervalMs = 1800, maxAttempts = 15 } = {}) {
  for (let attempt = 0; attempt < maxAttempts; attempt++) {
    const job = await api.get(url).then(r => r.data);

    if (job.status === 'finished') return job.result;
    if (job.status === 'failed') throw new Error(job.errorMessage ?? 'No se pudo calcular la rentabilidad');

    await sleep(intervalMs);
  }

  throw new Error('El cálculo está tardando más de lo esperado. Inténtalo de nuevo en unos minutos.');
}
```

`profitability-products` sigue el mismo patrón, cambiando la URL y sin el parámetro `productIds`.

---

### 4. Sin cambios (para evitar confusión)

- **`POST/GET .../profitability-summary/export-jobs/*`** (descarga de Excel de auditoría): sin
  cambios de comportamiento visibles. Internamente ya no hereda por error el límite de 60 días (un
  bug introducido y corregido durante el mismo desarrollo, nunca llegó a desplegarse) — sigue
  aceptando cualquier rango, como siempre.
- **`GET .../profitability-summary/export`** (descarga síncrona de Excel): sin cambios.

---

## Notas generales de integración

### Parámetros de paginación

El backend usa el parámetro `perPage` (camelCase) en los endpoints de la API v2. Excepción: algunos endpoints de Superadmin y de sesiones usan `per_page` (snake_case). No mezclar.

### Headers requeridos

Todos los requests a `/api/v2/*` (salvo rutas públicas) requieren:
- `X-Tenant: {subdomain}` — identificador del tenant
- `Authorization: Bearer {token}` — token Sanctum

### Formato de errores

```json
{
  "message": "Mensaje técnico",
  "userMessage": "Mensaje legible para el usuario (cuando aplica)",
  "errors": { "campo": ["error 1", "error 2"] }
}
```

### Códigos de estado comunes

| Código | Situación |
|---|---|
| 400 | Token inválido / datos incorrectos |
| 401 | No autenticado |
| 403 | Sin permisos / cuenta suspendida |
| 404 | Recurso o tenant no encontrado |
| 422 | Error de validación (con `errors`) |
| 429 | Rate limit superado (auth endpoints) |
