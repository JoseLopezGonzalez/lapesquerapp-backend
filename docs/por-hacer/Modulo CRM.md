# Módulo CRM — Gestión Comercial Simple

> **Objetivo:** que el comercial de Brisamar pueda gestionar sus clientes, prospectos e interacciones comerciales sin complejidad. Nada de embudos multicapa, puntuaciones de leads ni automatizaciones sofisticadas. Solo lo esencial para que una persona lleve bien su cartera.

> **Estado del documento:** actualizado con código real del backend **y del frontend** — Marzo 2026. Listo para planificar implementación.

---

## Lo que ya existe en el frontend (no hay que construir)

Estado actual del frontend para el rol comercial, cotejado contra el código real.

### Rutas actuales del rol comercial

`/comercial` — Dashboard principal (`ComercialLayoutClient.jsx` + `ComercialDashboard`)

`/comercial/autoventa` — Wizard de autoventa (8 pasos)

Son las **únicas dos rutas** definidas en `roleRoutesConfig.js` para el comercial. No existe aún ninguna ruta CRM.

### Dashboard comercial actual (`ComercialDashboard/index.js`)

Ya renderiza cuatro widgets de estadísticas generales:

* `TotalQuantitySoldCard` — total kg vendidos, año en curso
* `TotalAmountSoldCard` — importe total, año en curso
* `OrderRankingChart` — top 5 pedidos por importe
* `SalesBySalespersonPieChart` — ventas por comercial (pie chart)
* `TransportRadarChart` — radar de transportes

Usa `useOrdersStats.ts` (hooks: `useOrdersTotalNetWeightStats`, `useOrdersTotalAmountStats`, `useOrderRankingStats`, `useSalesBySalesperson`).

> **Problema real:** el `SalesBySalespersonPieChart` muestra todos los comerciales — para un usuario con rol `comercial` esto no tiene sentido (solo hay un slice: el suyo). Este widget debe adaptarse o sustituirse por métricas personalizadas.

### Autoventa wizard ya completo

8 pasos en `src/components/Comercial/Autoventa/`:

`Step1ClientSelection` → `Step2QRScan` → `Step3Pricing` → `Step4Invoice` → `Step5Observations` → `Step6Summary` → `Step7Confirmation` → `Step8PrintTicket`

Incluye `CreateCustomerQuickForm` — formulario rápido de cliente embebido en el wizard. Es un formulario de **un solo campo** (nombre del cliente). El `ProspectoForm` es mucho más complejo e independiente — comparten las mismas primitivas (`Input`, `Label`, `Select`, `Textarea`) pero no hay reutilización directa de componentes.

### Servicios y hooks ya disponibles reutilizables

| Servicio / Hook            | Archivo                                              | Útil para CRM                                                          |
| -------------------------- | ---------------------------------------------------- | ----------------------------------------------------------------------- |
| `customerService.ts`     | `src/services/domain/customers/customerService.ts` | Lista/ficha de clientes del comercial                                   |
| `useCustomerHistory.js`  | `src/hooks/useCustomerHistory.js`                  | Calcula `daysSinceLastOrder`, métricas, tendencias por producto      |
| `useOrders.js`           | `src/hooks/useOrders.js`                           | Lista de pedidos filtrada por `salespeople[]`                         |
| `useOrderFormOptions.js` | `src/hooks/useOrderFormOptions.js`                 | Opciones de productos, incoterms, formas de pago para el form de oferta |
| `useIncotermsList.ts`    | `src/hooks/useIncotermsList.ts`                    | Selector de incoterm en oferta                                          |
| `usePaymentTermsList.ts` | `src/hooks/usePaymentTermsList.ts`                 | Selector de forma de pago en oferta                                     |
| `useSalesBySalesperson`  | `src/hooks/useOrdersStats.ts`                      | Stats del propio comercial                                              |

### Navegación actual del rol comercial (`navgationConfig.js`)

Solo dos ítems activos:

```
{ name: 'Inicio',     href: '/comercial',          allowedRoles: ['comercial'] }
{ name: 'Autoventa',  href: '/comercial/autoventa', allowedRoles: ['comercial'] }
```

**No hay ningún ítem CRM** en la navegación para el comercial.

### Protección de rutas actual

`ComercialLayoutClient.jsx` valida `role === 'comercial'` y redirige al resto a `/admin/home`. El middleware usa `roleConfig.ts` donde `/comercial` está asignado solo a `['comercial']`.

---

## Principios de diseño

* **Mínimo fricción.** Crear un lead o registrar una interacción tiene que costar menos de 30 segundos.
* **Sin jerga CRM.** Nada de "pipeline", "opportunity stage" ni "conversion rate". El comercial ve clientes, prospectos y conversaciones.
* **Integrado en el área del rol comercial, no como módulo independiente fuera de `/comercial`.** Toda la funcionalidad CRM vive bajo rutas `/comercial/*` — prospectos, clientes, ofertas y pedidos son subrutas dentro del mismo área del comercial, con su propia navegación interna.
* **Un solo actor.** Diseñado para que lo use una persona, no un departamento.

---

## Lo que ya existe en el backend (no hay que construir)

Esta sección es nueva. Antes de planificar, es importante saber qué está hecho:

### Entidades ya operativas

| Entidad                       | Tabla                             | Estado                                              |
| ----------------------------- | --------------------------------- | --------------------------------------------------- |
| `Customer`                  | `customers`                     | ✅ CRUD completo — Policy + Service + Resource     |
| `Salesperson`               | `salespeople`                   | ✅ CRUD completo — Policy + Service + Resource     |
| `Order`                     | `orders`                        | ✅ CRUD completo — Policy + Service + Resource     |
| `OrderPlannedProductDetail` | `order_planned_product_details` | ✅ Líneas de pedido con producto, precio, impuesto |
| `Product`                   | `products`                      | ✅ Con especie, zona de captura, familia, GTINs     |

### Restricciones del rol Comercial ya implementadas

Las Policies de Laravel ya controlan exactamente lo que el documento de diseño describe. **No hay que construirlo.**

| Acción                     | Comercial                                         | Otros roles |
| --------------------------- | ------------------------------------------------- | ----------- |
| Ver lista de clientes       | Solo los suyos (`salesperson_id`)               | Todos       |
| Ver un cliente              | Solo el suyo                                      | Cualquiera  |
| Crear cliente               | ✅ (se le asigna automáticamente como comercial) | ✅          |
| Editar / eliminar cliente   | ❌ Denegado                                       | ✅          |
| Ver lista de pedidos        | Solo los suyos                                    | Todos       |
| Ver un pedido               | Solo el suyo                                      | Cualquiera  |
| Crear pedido                | ✅ (solo para sus clientes)                       | ✅          |
| Editar / eliminar pedido    | ❌ Denegado                                       | ✅          |
| Ver lista de comerciales    | ❌ Denegado                                       | ✅          |
| Ver opciones de comerciales | Solo el suyo (en `/options`)                    | Todos       |

### Endpoints ya disponibles relevantes para CRM

\`# Clientes GET    /api/v2/customers                        → lista paginada con filtros GET    /api/v2/customers/{id}                   → ficha de cliente GET    /api/v2/customers/{id}/order-history     → historial de pedidos del cliente GET    /api/v2/customers/op                     → opciones para select

# Pedidos

GET    /api/v2/orders                           → lista con filtros extensos POST   /api/v2/orders                           → crear pedido GET    /api/v2/orders/{id}                      → detalle de pedido GET    /api/v2/orders/active                    → pedidos activos (pending o fecha futura) PUT    /api/v2/orders/{id}/status               → cambio de estado GET    /api/v2/orders/sales-by-salesperson      → estadísticas por comercial

# Comerciales

GET    /api/v2/salespeople/options              → opciones (comercial solo ve el suyo)\`

### Filtros disponibles en listado de pedidos (para dashboard y listas)

`customers`, `status`, `orderType`, `loadDate {start, end}`, `entryDate {start, end}`, `salespeople`, `products`, `species`, `incoterm`, `transport`, `perPage`.

Con estos filtros el frontend ya puede construir la vista "clientes inactivos" y "pedidos pendientes" del dashboard sin nuevos endpoints de backend.

---

## Entidades nuevas a construir

### Prospecto (`prospects`)

Un contacto comercial que todavía no es cliente activo en el ERP.

**Campos mínimos:**

| Campo                | Tipo                               | Notas                                                                |
| -------------------- | ---------------------------------- | -------------------------------------------------------------------- |
| Empresa              | string                             | Razón social o nombre comercial                                     |
| País                | FK →`countries`(`country_id`) | Igual que en `Customer`existente — no texto libre                 |
| Especie de interés  | json                               | Array de strings — sin FK, libre                                    |
| Origen               | enum                               | `conxemar`,`direct`,`referral`,`web`,`other`               |
| Estado               | enum                               | Ver estados más abajo                                               |
| Comercial asignado   | FK →`salespeople`               | Nullable                                                             |
| Cliente vinculado    | FK →`customers`                 | Nullable — se rellena al convertir                                  |
| Próxima acción     | date                               | Para recordatorio en dashboard                                       |
| Notas                | text                               | Campo abierto                                                        |
| Interés comercial   | text                               | `commercial_interest_notes`— formato, calibre, mercado objetivo… |
| Última interacción | datetime                           | Auto-actualizado                                                     |
| Última oferta       | datetime                           | Auto-actualizado                                                     |
| Motivo descarte      | text                               | Solo si estado = discarded                                           |

**Estados del prospecto:**

`new → following → offer_sent → customer                              → discarded`

Solo `discarded` es irreversible. El resto se puede mover libremente.

**Transición especial:** cuando pasa a `customer` → se ejecuta transacción que crea el `Customer` y guarda `customer_id` en el prospecto.

---

### Contactos del prospecto (`prospect_contacts`)

Un prospecto puede tener varios contactos (compras, dirección, logística…). Solo uno es primario.

> **Nota de coherencia:** El modelo `Customer` existente almacena los emails en un campo de texto con separador `;` y el campo `contact_info` como texto libre. Al convertir el prospecto en cliente, el contacto primario de `prospect_contacts` debe mapearse a los campos `emails` y `contact_info` del `Customer`. No se crea una tabla relacional nueva en customers — se respeta la estructura existente.

| Campo        | Tipo                    |
| ------------ | ----------------------- |
| prospect\_id | FK → prospects         |
| name         | string                  |
| role         | string nullable (cargo) |
| phone        | string nullable         |
| email        | string nullable         |
| is\_primary  | boolean default false   |

---

### Interacción comercial (`commercial_interactions`)

Cualquier contacto registrado contra un prospecto o un cliente existente.

| Campo              | Tipo                     | Notas                                                         |
| ------------------ | ------------------------ | ------------------------------------------------------------- |
| prospect\_id       | FK → prospects nullable | Uno de los dos obligatorio                                    |
| customer\_id       | FK → customers nullable | Uno de los dos obligatorio                                    |
| salesperson\_id    | FK → salespeople        | Quién la registró                                           |
| type               | enum                     | `call`,`email`,`whatsapp`,`visit`,`other`           |
| occurred\_at       | datetime                 | Por defecto: ahora                                            |
| summary            | string max 500           | 1-2 líneas de qué pasó                                     |
| result             | enum                     | `interested`,`no_response`,`not_interested`,`pending` |
| next\_action\_note | string nullable          |                                                               |
| next\_action\_at   | date nullable            | Alimenta la agenda del dashboard                              |

**Constraint correcto:**

```
CHECK (prospect_id IS NOT NULL OR customer_id IS NOT NULL)   -- al menos uno relleno
CHECK (NOT (prospect_id IS NOT NULL AND customer_id IS NOT NULL))  -- nunca los dos a la vez
```

El primer check impide que ambos sean nulos. El segundo impide que ambos estén rellenos. Ambos son necesarios.

Cuando se crea una interacción sobre un prospecto, se actualiza automáticamente `prospects.last_contact_at`.

---

### Oferta (`offers` + `offer_lines`)

La oferta es una entidad propia que permite guardar qué productos y precios se propusieron, tener historial de versiones y conectar la oferta aceptada con el `Order` resultante.

**Tabla `offers`:**

| Campo             | Tipo                           | Notas                                                    |
| ----------------- | ------------------------------ | -------------------------------------------------------- |
| prospect\_id      | FK nullable                    | Uno de los dos                                           |
| customer\_id      | FK nullable                    | Uno de los dos                                           |
| salesperson\_id   | FK → salespeople              |                                                          |
| status            | enum                           | `draft`,`sent`,`accepted`,`rejected`,`expired` |
| send\_channel     | enum nullable                  | `email`,`pdf`,`whatsapp_text`                      |
| sent\_at          | datetime nullable              |                                                          |
| valid\_until      | date nullable                  |                                                          |
| incoterm\_id      | FK →`incoterms`nullable     | Consistente con modelo `Order`existente                |
| payment\_term\_id | FK →`payment_terms`nullable | Consistente con modelo `Order`existente                |
| currency          | enum                           | `EUR`,`USD`— default EUR                            |
| notes             | text nullable                  |                                                          |
| accepted\_at      | datetime nullable              |                                                          |
| rejected\_at      | datetime nullable              |                                                          |
| rejection\_reason | text nullable                  |                                                          |
| order\_id         | FK →`orders`nullable        | Al generar el pedido                                     |

> **Nota importante:**`incoterm_id` y `payment_term_id` usan FK reales (igual que en `Order`), no strings. Las tablas `incoterms` y `payment_terms` ya existen.

**Tabla `offer_lines`:**

| Campo       | Tipo                      | Notas                                                                                                                                                                                                                          |
| ----------- | ------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| offer\_id   | FK → offers              |                                                                                                                                                                                                                                |
| product\_id | FK →`products`nullable | Si hay producto del catálogo                                                                                                                                                                                                  |
| description | string                    | Texto libre si no hay product\_id                                                                                                                                                                                              |
| quantity    | decimal                   |                                                                                                                                                                                                                                |
| unit        | string                    | kg, caja, tina…                                                                                                                                                                                                               |
| unit\_price | decimal                   |                                                                                                                                                                                                                                |
| tax\_id     | FK →`taxes`nullable    | Consistente con `OrderPlannedProductDetail`                                                                                                                                                                                  |
| boxes       | integer nullable          | Nº de cajas estimado. Nullable en la oferta;**obligatorio**al crear el `Order`(`StoreOrderRequest`requiere `plannedProducts.*.boxes`). Se precarga si está relleno; el comercial lo confirma al crear el pedido. |
| currency    | string default EUR        |                                                                                                                                                                                                                                |

> **Nota de coherencia:**`OrderPlannedProductDetail.$fillable` usa `product_id`, `unit_price`, `tax_id` y `boxes`. Las líneas de oferta siguen exactamente el mismo patrón. El campo `boxes` es nullable en la oferta porque en fase de negociación puede no conocerse el encaje exacto de cajas; se confirma al crear el pedido.

**Reglas:**

* Una oferta puede originar como máximo **un pedido** (`order_id` unique cuando no es null).
* Al crear el pedido desde la oferta, las líneas se copian como snapshot en `order_planned_product_details` — no se recalculan.
* Una vez `accepted`, la oferta queda no editable.
* El pedido puede incluir líneas adicionales **solo en el momento de creación**. Una vez creado el `Order`, el comercial no puede editarlo (Policy ya implementada). Las líneas extra se añaden en el formulario de creación del pedido, no después.
* Al enviar la oferta → se actualiza `prospects.last_offer_at`.

---

## Modelo de datos — esquema final

> **Nota arquitectónica crítica:** PesquerApp es multi-tenant con **base de datos separada por tenant**. Las tablas nuevas **NO tienen columna `tenant_id`** — el aislamiento lo garantiza la conexión dinámica (trait `UsesTenantConnection`). Añadir `tenant_id` sería un error de diseño.

### Tabla `prospects`

`id                            bigint PK salesperson_id                bigint FK → salespeople (nullable) company_name                  string country_id                    bigint FK → countries (nullable) species_interest              json     -- array de strings origin                        enum: conxemar | direct | referral | web | other status                        enum: new | following | offer_sent | customer | discarded next_action_at                date nullable notes                         text nullable commercial_interest_notes     text nullable   -- formato, calibre, mercado objetivo… customer_id                   bigint FK → customers nullable  -- al convertir last_contact_at               datetime nullable               -- auto-actualizado last_offer_at                 datetime nullable               -- auto-actualizado lost_reason                   text nullable created_at updated_at`

> **Cambio respecto al borrador original:**`country` pasa a `country_id FK → countries` para ser consistente con el modelo `Customer` existente, que ya usa `country_id`.

### Tabla `prospect_contacts`

`id                  bigint PK prospect_id         bigint FK → prospects name                string role                string nullable phone               string nullable email               string nullable is_primary          boolean default false created_at updated_at`

### Tabla `commercial_interactions`

`id                  bigint PK prospect_id         bigint FK → prospects nullable customer_id         bigint FK → customers nullable salesperson_id      bigint FK → salespeople type                enum: call | email | whatsapp | visit | other occurred_at         datetime summary             string(500) result              enum: interested | no_response | not_interested | pending next_action_note    string nullable next_action_at      date nullable created_at -- sin updated_at: las interacciones no se editan`

### Tabla `offers`

`id                  bigint PK prospect_id         bigint FK nullable customer_id         bigint FK nullable salesperson_id      bigint FK → salespeople status              enum: draft | sent | accepted | rejected | expired send_channel        enum: email | pdf | whatsapp_text nullable sent_at             datetime nullable valid_until         date nullable incoterm_id         bigint FK → incoterms nullable payment_term_id     bigint FK → payment_terms nullable currency            enum: EUR | USD default EUR notes               text nullable accepted_at         datetime nullable rejected_at         datetime nullable rejection_reason    text nullable order_id            bigint FK → orders nullable unique created_at updated_at`

### Tabla `offer_lines`

`id                  bigint PK offer_id            bigint FK → offers product_id          bigint FK → products nullable description         string quantity            decimal(10,3) unit                string unit_price          decimal(10,4) tax_id              bigint FK → taxes nullable boxes               integer nullable   -- se precarga al crear el Order; StoreOrderRequest lo requiere currency            string(3) default 'EUR' created_at -- sin updated_at: las líneas se recrean si se reedita el draft`

---

## Conversión prospecto → cliente

### Validación previa

El sistema comprueba antes de ejecutar:

* `company_name` no vacío
* Al menos un `prospect_contact` con `is_primary = true`
* El contacto primario tiene teléfono o email

No se exigen datos fiscales (`vat_number`). El `Customer` puede crearse como incompleto.

> **Restricción de estado:** la conversión solo está disponible cuando el prospecto está en estado `offer_sent`. No se permite convertir un prospecto sin haber enviado previamente una oferta. Esto es coherente con la UI, donde el botón "Convertir a cliente" solo aparece en ese estado. El backend debe validar esta condición y devolver error 422 si se intenta convertir desde otro estado.

### Mapeo de campos (prospecto → Customer existente)

| Campo en `Prospect`                 | Campo en `Customer`                              |
| ------------------------------------- | -------------------------------------------------- |
| `company_name`                      | `name`                                           |
| `country_id`                        | `country_id`                                     |
| `salesperson_id`                    | `salesperson_id`                                 |
| Contacto primario → email            | `emails`(formato semicolon-separado del sistema) |
| Contacto primario → phone + name     | `contact_info`(texto libre)                      |
| Oferta aceptada →`payment_term_id` | `payment_term_id`(si existe en la oferta)        |

> **⚠️ Nota sobre `incoterm_id`:** el modelo `Customer`**no tiene** campo `incoterm_id` (verificado contra `$fillable` y migraciones reales). El incoterm es por pedido (`orders.incoterm_id`), no por cliente. Al crear el `Order` desde la oferta aceptada, el `incoterm_id` de la oferta se copia al `Order`. No se guarda en el `Customer`.

> Los campos `billing_address`, `shipping_address`, `vat_number`, `transport_id` quedan vacíos — el comercial o administración los completan después desde la ficha del cliente.

> **Sobre múltiples contactos en la conversión:**`Customer` no tiene tabla relacional de contactos — almacena `emails` (separados por `;`) y `contact_info` como texto libre. En V1, solo el contacto primario (`is_primary = true`) se mapea al Customer. Los contactos secundarios quedan en `prospect_contacts` como historial pero **no se migran al Customer**. Esto es una limitación conocida de V1, aceptada conscientemente para no rediseñar el modelo `Customer`.

### Transacción

\`DB::transaction:

1. Crear Customer con campos mapeados
2. Actualizar Prospect: status = 'customer', customer\_id = nuevo [Customer.id](http://Customer.id)
3. Actualizar Offer activa (si existe): customer\_id = nuevo Customer.id\`

Si falla cualquier paso → rollback completo.

---

## Control de duplicados

Al crear un prospecto, el sistema comprueba (case-insensitive):

* Mismo `company_name` en `prospects` o `customers` → aviso con enlace
* Mismo email en `prospect_contacts` o campo `customers.emails` → aviso
* Mismo teléfono en `prospect_contacts` o campo `customers.contact_info` → aviso

**No bloquea.** Solo informa. El comercial decide si es un caso legítimamente distinto.

---

## Visibilidad por rol

| Rol                           | Prospectos                          | Interacciones  | Ofertas        |
| ----------------------------- | ----------------------------------- | -------------- | -------------- |
| Comercial                     | Solo los suyos (`salesperson_id`) | Solo las suyas | Solo las suyas |
| Admin / Técnico / Dirección | Todos                               | Todas          | Todas          |

Implementación: misma estrategia que `CustomerPolicy` y `OrderPolicy` ya existentes — filtro por `salesperson_id` en el método `viewAny` de cada Policy nueva.

> **Sobre dirección operando como comercial:** si alguien de dirección necesita crear prospectos u ofertas, debe hacerlo desde un usuario con rol Comercial. No se añade lógica de excepción. Toda actividad queda vinculada a un `salesperson_id` real — garantiza coherencia en historial y estadísticas.

---

## Dashboard comercial

### Lo que ya existe (no construir)

* `GET /api/v2/orders/sales-by-salesperson` → estadísticas de ventas por comercial
* `GET /api/v2/orders?salespeople[]=X&status=pending` → pedidos pendientes del comercial
* `GET /api/v2/customers/{id}/order-history` → historial de pedidos de un cliente

Con estos endpoints el frontend puede mostrar ya:

* Pedidos activos del comercial
* Clientes con últimos pedidos (ordenar por fecha y calcular días de inactividad en frontend o con filtros de fecha en el endpoint de orders)

### Lo que hay que añadir (endpoints nuevos)

`GET /api/v2/crm/dashboard`

Respuesta sugerida:

`{   "reminders_today": [     { "type": "prospect|interaction", "id": 1, "label": "Llamar a X", "next_action_at": "2026-03-17" }   ],   "overdue_actions": [...],       // next_action_at < hoy, sin completar   "inactive_customers": [...],    // sin pedido en los últimos N días (configurable, default 30)   "prospects_without_activity": [...] // sin interacción en los últimos 7 días }`

El umbral de inactividad (30 días) puede hacerse configurable más adelante usando la tabla `settings` del tenant (ya existe `SettingService`).

---

## Agenda del comercial

No es un calendario propio. El campo `next_action_at` de interacciones y prospectos alimenta una **sección en el dashboard** existente.

**Regla:** si `next_action_at` definido → aparece en agenda. Si no → no aparece. Sin más lógica.

Las acciones vencidas (`next_action_at < hoy`) siguen apareciendo hasta que se resuelvan o reprogramen.

Sin integración con calendarios externos en esta versión.

---

## Envío de ofertas — tres canales

1. **Email directo** — PesquerApp envía al email del contacto con PDF adjunto (usar el sistema de emails existente en el proyecto — ya hay envío de emails en otros módulos).
2. **Descarga PDF** — genera y descarga para envío manual.
3. **Copiar texto WhatsApp** — genera texto formateado con saltos de línea, productos, precios y datos de contacto. Sin integración de API.

Las tres opciones coexisten.

---

## Vista del comercial en el Order Manager (fuera del CRM)

> Esto no es CRM pero se implementa en paralelo como parte de completar la vista del rol comercial.

El comercial puede **crear y visualizar** pedidos desde el Order Manager existente con estas restricciones:

* Solo ve sus propios pedidos → **ya implementado** en `OrderPolicy` y `OrderListService`
* Puede crear pedidos → **ya implementado**
* **No puede editar un pedido una vez creado** → **ya implementado** (Policy deniega update/delete a Comercial)

**Conclusión: el Order Manager para el rol comercial ya está implementado a nivel de backend.** Solo falta que el frontend oculte/deshabilite los controles de edición según el rol.

---

## Relación con entidades existentes del ERP

| Entidad CRM                   | Relación con ERP                                                          | Estado    |
| ----------------------------- | -------------------------------------------------------------------------- | --------- |
| Prospecto                     | Al convertirse crea `Customer`                                           | 🆕 Nuevo  |
| `prospect_contacts`         | Mapea a `customers.emails`+`contact_info`al convertir                  | 🆕 Nuevo  |
| Interacción en cliente       | Se registra contra `customers.id`(ya existe)                             | 🆕 Nuevo  |
| Oferta                        | Usa `products`,`incoterms`,`payment_terms`,`taxes`(todo existente) | 🆕 Nuevo  |
| Oferta aceptada               | Crea `Order`+`order_planned_product_details`(ya existen)               | 🆕 Nuevo  |
| Dashboard comercial           | Usa `Order`,`Salesperson`,`Customer`(ya existen)                     | Parcial   |
| Vista rol comercial en orders | `OrderPolicy`+`OrderListService`ya implementan restricciones           | ✅ Existe |

---

## Tareas para implementar

### Backend

**Migraciones**

* [ ] `prospects`
* [ ] `prospect_contacts`
* [ ] `commercial_interactions`
* [ ] `offers` + `offer_lines`

**Modelos + Policies**

* [ ] `Prospect` model con trait `UsesTenantConnection`
* [ ] `ProspectContact` model
* [ ] `CommercialInteraction` model
* [ ] `Offer` + `OfferLine` models
* [ ] `ProspectPolicy` (viewAny filtra por salesperson\_id para Comercial)
* [ ] `OfferPolicy`
* [ ] `CommercialInteractionPolicy`

**API — Prospectos**

* [ ] CRUD prospectos (`/api/v2/prospects`)
* [ ] CRUD contactos del prospecto (`/api/v2/prospects/{id}/contacts`)
* [ ] Endpoint conversión prospecto → customer (transacción atómica)
* [ ] Detección de duplicados (en store/update de prospectos)

**API — Interacciones**

* [ ] CRUD interacciones (`/api/v2/commercial-interactions`)
* [ ] Al crear interacción → actualizar `prospect.last_contact_at`

**API — Ofertas**

* [ ] CRUD ofertas + líneas (`/api/v2/offers`)
* [ ] Endpoint: generar PDF de oferta
* [ ] Endpoint: generar texto WhatsApp formateado
* [ ] Endpoint: envío email con PDF adjunto
* [ ] Endpoint: crear `Order` desde `Offer` aceptada (líneas → `order_planned_product_details`) — el pedido se crea vía el flujo existente `POST /api/v2/orders`, con las líneas precargadas. El comercial puede añadir más líneas en ese mismo acto de creación. El pedido resultante se gestiona desde `/comercial/pedidos` (reutiliza el Order Manager existente).
* [ ] El endpoint de conversión oferta → pedido debe especificar claramente que usa `POST /api/v2/orders` internamente y no crea un flujo paralelo de pedidos

**API — Dashboard CRM**

* [ ] `GET /api/v2/crm/dashboard` (recordatorios, acciones vencidas, clientes inactivos, prospectos sin actividad)

### Frontend

> **Decisión de navegación:** el CRM vive en subrutas bajo `/comercial` (opción B). El dashboard `/comercial` se amplía con widgets CRM, y las entidades tienen rutas propias (`/comercial/prospectos`, `/comercial/clientes`, etc.). Esto es más limpio que intentar embutir listados, fichas y formularios en el dashboard principal sin navegación.

#### Nuevas rutas bajo `/comercial`

* [ ] `/comercial/prospectos` — listado con filtros
* [ ] `/comercial/prospectos/[id]` — ficha de prospecto
* [ ] `/comercial/prospectos/create` — formulario de creación
* [ ] `/comercial/clientes` — listado de clientes propios del comercial
* [ ] `/comercial/clientes/[id]` — ficha de cliente con interacciones y ofertas
* [ ] `/comercial/ofertas` — listado de ofertas
* [ ] `/comercial/ofertas/[id]` — ficha de oferta
* [ ] `/comercial/pedidos` — pedidos propios (solo lectura — reutiliza `useOrders.js` con filtro `salespeople[]`)

#### Actualizar archivos de configuración existentes

* [ ] `navgationConfig.js` — añadir ítems para `comercial`: Mis Clientes, Prospectos, Ofertas, Mis Pedidos
* [ ] `roleRoutesConfig.js` — añadir rutas CRM al objeto `comercialRoutes`
* [ ] `roleConfig.ts` — añadir rutas nuevas al guard del middleware

#### Servicios nuevos (`src/services/domain/`)

* [ ] `prospects/prospectService.ts` — CRUD prospectos + contactos + conversión → cliente
* [ ] `commercial-interactions/commercialInteractionService.ts` — CRUD interacciones
* [ ] `offers/offerService.ts` — CRUD ofertas + líneas + generar PDF + texto WhatsApp + crear pedido
* [ ] `crm/crmDashboardService.ts` — `GET /api/v2/crm/dashboard`

#### Hooks nuevos (`src/hooks/`)

* [ ] `useProspects.ts` — lista de prospectos con filtros
* [ ] `useProspect.ts` — detalle de un prospecto
* [ ] `useCommercialInteractions.ts` — historial de interacciones
* [ ] `useOffers.ts` — lista de ofertas con filtros
* [ ] `useOffer.ts` — detalle de una oferta
* [ ] `useCrmDashboard.ts` — recordatorios, acciones vencidas, clientes inactivos

#### Componentes nuevos (`src/components/Comercial/`)

* [ ] `Prospectos/ProspectosList` — tabla/cards de prospectos con filtros de estado y búsqueda
* [ ] `Prospectos/ProspectoDetail` — ficha: datos + contactos + historial interacciones + ofertas
* [ ] `Prospectos/ProspectoForm` — formulario crear/editar prospecto
* [ ] `Prospectos/ProspectoContactsPanel` — CRUD de contactos del prospecto
* [ ] `Prospectos/ConvertirAClienteButton` — botón con validación previa + transacción
* [ ] `Interactions/QuickInteractionModal` — modal rápido (<30 seg) para nueva interacción
* [ ] `Interactions/InteractionsList` — historial de interacciones (prospecto o cliente)
* [ ] `Ofertas/OfertaForm` — formulario de oferta con líneas de producto
* [ ] `Ofertas/OfertaLineEditor` — editor de líneas (reutiliza patrón de `OrderPlannedProductDetail`)
* [ ] `Ofertas/OfertaSendButtons` — tres botones: email / descargar PDF / copiar texto WhatsApp
* [ ] `Ofertas/OfertasList` — tabla de ofertas con estados
* [ ] `CrmDashboard/RemindersWidget` — recordatorios del día y acciones vencidas
* [ ] `CrmDashboard/InactiveCustomersWidget` — clientes sin pedido en N días
* [ ] `CrmDashboard/ProspectsWithoutActivityWidget` — prospectos sin interacción en 7 días

#### Adaptar dashboard comercial existente

* [ ] `ComercialDashboard/index.js` — añadir sección CRM widgets (Reminders, Inactive, Prospects) usando `useCrmDashboard.ts`
* [ ] `SalesBySalespersonPieChart` — adaptar para rol `comercial`: mostrar métricas propias en lugar de pie chart comparativo entre todos los comerciales
* [ ] `TotalQuantitySoldCard` / `TotalAmountSoldCard` — verificar que el backend filtra automáticamente por `salesperson_id` cuando el token es de un comercial (si no, añadir filtro explícito)

#### Order Manager para rol comercial

* [ ] Crear hook `useComercialOrders.ts` que llame a `GET /api/v2/orders?salespeople[]=X` con el `salesperson_id` del usuario en sesión — `useOrders.js` existente no acepta filtros, usa `getActiveOrders()` sin parámetros
* [ ] Añadir ruta `/comercial/pedidos` que reutilice los componentes del Order Manager con `useComercialOrders.ts`
* [ ] Ocultar controles de edición condicionalmente por `session.user.role === 'comercial'` (el backend ya deniega vía Policy)

---

## Lo que NO tiene este módulo

* Sin puntuación de leads
* Sin embudo visual tipo Kanban (puede añadirse después)
* Sin automatización de emails
* Sin integración con calendarios externos
* Sin gestión de contratos ni documentos formales

---

> *Documento actualizado con código real del backend — Marzo 2026. Cotejado contra modelos, controladores, policies y migraciones existentes.*

---

Las correcciones más importantes que hice respecto al borrador original:

1. **Sin `tenant_id` en las tablas** — el sistema usa bases de datos separadas por tenant, añadir ese campo sería un error de arquitectura.
2. **`country` pasa a `country_id FK → countries`** — para ser coherente con `Customer` existente.
3. **`salespeople` (no `salespersons`)** — nombre real de la tabla.
4. **`incoterm_id` y `payment_term_id` usan FK** — coherentes con `Order`. **Corrección adicional (cotejado contra código real):**`Customer`**no tiene** `incoterm_id` en su `$fillable` ni en sus migraciones. El incoterm es por pedido, no por cliente. La tabla de mapeo se ha corregido: `incoterm_id` de la oferta → `Order`; solo `payment_term_id` → `Customer`.
5. **`tax_id` y `boxes` en `offer_lines`** — coherente con `OrderPlannedProductDetail.$fillable` (`product_id`, `unit_price`, `tax_id`, `boxes`). El campo `boxes` es nullable en la oferta pero obligatorio en `StoreOrderRequest` al crear el pedido — se precarga si está relleno en la línea de oferta.
6. **Las restricciones del Comercial ya están implementadas** — Policies y Services ya existen, no hay que construirlos.
7. **El Order Manager para el Comercial ya está implementado a nivel de backend (Policies + Service)** — el trabajo de frontend incluye ocultar controles de edición según rol y crear `useComercialOrders.ts` nuevo. `useOrders.js` actual no es reutilizable directamente porque usa `getActiveOrders()` sin parámetros de filtro — esto lo invalida para filtrar por `salesperson_id`. La fila en la tabla de servicios reutilizables que describe `useOrders.js` como “filtrado por `salespeople[]`” es optimista: ese filtrado requiere el hook nuevo.
8. **Mapeo preciso prospecto → Customer** — los campos reales del modelo existente.

[UI — Diseño de vistas del rol comercial](https://www.notion.so/UI-Dise-o-de-vistas-del-rol-comercial-326e28c879628103ae3cd81d4bdc7348?pvs=21)

[ ]

WYSIWYG <Alt+Ctrl+7>Instant Rendering <Alt+Ctrl+8>Split View <Alt+Ctrl+9>

Outline

DesktopTabletMobile/Wechat
