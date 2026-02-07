# Plan de mejoras: GET orders/{id}

Este documento describe el análisis de la implementación actual del endpoint **GET** `/api/v2/orders/{id}` y el plan para implementar las mejoras **2, 3 y 5** del análisis de rendimiento (select explícito, N+1/profundidad, índices).

**Estado:** Mejoras 2, 3 y 5 implementadas. **Pendiente de probar** en entorno real/staging; si falta algún campo en la respuesta, añadirlo al `select()` en `OrderController::show()` y documentar en §8.

**Revisión post-pull:** Tras contrastar con la versión actual del código (origin/main), se ajustó el `show()`: (1) **Customer** usa columna `facilcom_code` (no `facil_com_code`). (2) **Product** ya no tiene relación `article` ni modelo Article; se eliminaron `product.article` y `product.article.categoria` del `with()` y se quitaron `article_id` y `fixed_weight` del select de Product (la tabla `products` ya no tiene `fixed_weight` por migración `remove_fixed_weight_from_products_table`). (3) **Box::toArrayAssoc()** usa `$this->product`, no `$this->article`; se sigue cargando `pallets.boxes.box.product` correctamente.

**Segunda revisión (exhaustiva):** (4) **pallet_boxes:** La tabla solo tiene `id`, `pallet_id`, `box_id`, `created_at`, `updated_at` (migración `2023_08_10_093037_pallet_boxes`); no tiene `lot`, `net_weight`, `article_id`. Se corrigió el select a `['id', 'pallet_id', 'box_id', 'created_at', 'updated_at']` para evitar error SQL. (5) **role_user:** Se reañadió la comprobación `Schema::hasTable('role_user')` antes de `Schema::create` para que la migración sea idempotente en todos los tenants.

---

## 1. Implementación actual

### 1.1 Controlador (v2)

**Archivo:** `app/Http/Controllers/v2/OrderController.php` → método `show(string $id)`.

```php
$order = Order::with([
    'pallets.boxes.box.productionInputs',
    'pallets.boxes.box.product',
])->findOrFail($id);
return new OrderDetailsResource($order);
```

- **Una sola consulta** con eager loading para `pallets` → `boxes` (PalletBox) → `box` (Box) → `productionInputs` y `product`.
- **No se cargan** las relaciones que el resource usa a nivel de Order: `customer`, `payment_term`, `salesperson`, `transport`, `incoterm`, `plannedProductDetails`, `incident`.

### 1.2 Recurso y modelos involucrados

**OrderDetailsResource** (`app/Http/Resources/v2/OrderDetailsResource.php`) accede a:

| Campo en respuesta | Origen | Relaciones / accessors usados |
|--------------------|--------|--------------------------------|
| customer | `$this->customer->toArrayAssoc()` | customer → payment_term, salesperson, country, transport |
| paymentTerm | `$this->payment_term` | — |
| salesperson | `$this->salesperson` | — |
| transport | `$this->transport` | — |
| incoterm | `$this->incoterm` | — |
| pallets | `$this->pallets->map(... toArrayAssoc())` | pallets → boxes → box → product (article) |
| totalNetWeight, totalBoxes, numberOfPallets | Accessors en Order | pallets, boxes, box, productionInputs, isAvailable |
| productionProductDetails, productDetails, subTotalAmount, totalAmount | Accessors en Order | pallets, boxes, box, product, plannedProductDetails (product, tax) |
| plannedProductDetails | `$this->plannedProductDetails` | product, tax |
| incident | `$this->incident` | — |
| customerHistory | `getCustomerHistory()` | Nueva query: Order::where(...)->with(...)->get() |

- **Pallet::toArrayAssoc()** usa: `boxes` → cada **PalletBox::toArrayAssoc()** → **Box::toArrayAssoc()**.
- **Box::toArrayAssoc()** usa: `article` (relación a Product) y atributos de Box.
- **Product::toArrayAssoc()** usa: `article`, `species`, `captureZone`, `family`, `family.category`.

Conclusión: al serializar un solo pedido se disparan **varias cargas diferidas (lazy)** para customer, payment_term, salesperson, transport, incoterm, plannedProductDetails, incident y, dentro de cada caja, para product → article, species, captureZone, family, family.category. Eso implica **N+1** (y variantes) en la serialización.

### 1.3 Cadena de datos realmente necesaria

Para que la respuesta del `show` no provoque N+1 ni cargue de más:

1. **Order:** todas las columnas que usa el resource (o al menos las necesarias).
2. **Relaciones directas del Order:** customer, payment_term, salesperson, transport, incoterm, plannedProductDetails (con product, tax), incident.
3. **Customer anidado:** payment_term, salesperson, country, transport (por Customer::toArrayAssoc()).
4. **Pallets:** pallets → boxes (PalletBox) → box (Box) → productionInputs, product.
5. **Product (por caja):** article, species, captureZone, family, family.category (por Product::toArrayAssoc()).
6. **OrderPlannedProductDetail:** product, tax.
7. **ProductionInput:** solo hace falta saber si existe (para `isAvailable`); no es necesario serializar sus columnas en la respuesta del order show.

---

## 2. Mejora 3: Profundidad real y N+1 en serialización

**Objetivo:** Eager loading completo de todo lo que toca el resource al serializar el pedido, sin cargas diferidas.

### 2.1 Relaciones que debe cargar el `show()`

En `OrderController::show()` (v2) se debe usar un `with()` que incluya al menos:

```text
customer.payment_term
customer.salesperson
customer.country
customer.transport
payment_term
salesperson
transport
incoterm
plannedProductDetails.product
plannedProductDetails.tax
incident
pallets.boxes.box.productionInputs
pallets.boxes.box.product.article
pallets.boxes.box.product.species
pallets.boxes.box.product.captureZone
pallets.boxes.box.product.family
pallets.boxes.box.product.family.category
```

Nota: si `Product::toArrayAssoc()` usa otra relación (por ejemplo `product.article` como modelo distinto de Product), hay que incluirla también. En el código actual, `box.product` es Product y Product tiene `article` (Article); hay que confirmar en `Article::toArrayAssoc()` si pide más relaciones y añadirlas al `with()`.

### 2.2 getCustomerHistory()

- **Problema:** Hace una query nueva `Order::where(...)->with('plannedProductDetails.product','pallets.boxes.box.product')->get()` y itera sobre muchos pedidos. No es N+1 del “show” del pedido actual, pero es coste extra y puede ser pesado si el cliente tiene muchos pedidos.
- **Para esta mejora (punto 3):** El foco es el pedido actual. Se puede dejar getCustomerHistory() como está y, en un paso posterior, valorar:
  - Límite de pedidos (ej. últimos 10) y/o paginación.
  - Eager load explícito de solo las relaciones que usa (product, etc.) y, si se implementa la mejora 2, select explícito también ahí.

### 2.3 Tareas concretas (mejora 3)

1. ~~**Listar relaciones exactas** que usan `OrderDetailsResource`, `Pallet::toArrayAssoc()`, `PalletBox`, `Box::toArrayAssoc()`, `Product::toArrayAssoc()`, `Customer::toArrayAssoc()`, y los modelos anidados (Article, Tax, etc.).~~
2. ~~**Ampliar el `with()`** en `OrderController::show()` con la lista anterior (y las que falten según el listado).~~ **Hecho:** `app/Http/Controllers/v2/OrderController.php` método `show()`.
3. **Comprobar** que ningún accessor usado en la serialización hace `->load()` o accede a relaciones no cargadas (ej. `Order::getTotalNetWeightAttribute` ya intenta cargar `boxes.box.productionInputs` si no están cargados; con el with() completo no debería hacer falta).
4. **Opcional:** Añadir un test que cargue un order con pallets/boxes y verifique número de queries (p. ej. 1–2 para el order show, sin crecer con el número de pallets/cajas).

---

## 3. Mejora 2: Select explícito de columnas

**Objetivo:** Reducir datos transferidos desde BD y memoria PHP cargando solo las columnas que realmente se usan en la respuesta.

### 3.1 Reglas al usar `select()` en relaciones

- Incluir siempre la **clave primaria** (`id`) y las **claves foráneas** usadas en la cadena (ej. `order_id`, `customer_id`, `pallet_id`, `box_id`, `article_id`). Si falta una FK, Eloquent no puede montar bien la relación.
- En `Order::with([...])` se puede usar forma de closure, por ejemplo:
  - `'customer' => fn($q) => $q->select(['id','...'])`
  - y análogo para cada relación anidada que se quiera restringir.

### 3.2 Columnas por modelo (referencia)

Hay que revisar cada `toArrayAssoc()` / resource y anotar columnas usadas. Resumen de partida:

| Modelo | Columnas típicas a incluir (ajustar según toArrayAssoc real) |
|--------|-------------------------------------------------------------|
| Order | id, buyer_reference, customer_id, payment_term_id, billing_address, shipping_address, transportation_notes, production_notes, accounting_notes, salesperson_id, emails, transport_id, entry_date, load_date, status, incoterm_id, created_at, updated_at, truck_plate, trailer_plate, temperature |
| Customer | id, name, alias, vat_number, payment_term_id, billing_address, shipping_address, ..., country_id, transport_id, ... (todas las usadas en toArrayAssoc) |
| Pallet | id, observations, status, order_id, ... (y reception_id si se usa en toArrayAssoc) |
| PalletBox | id, pallet_id, box_id, created_at, updated_at (tabla sin lot, net_weight, article_id) |
| Box | id, article_id, lot, gs1_128, gross_weight, net_weight, created_at (+ pallet_id si es accessor/atributo) |
| ProductionInput | id, box_id (solo para exists/isEmpty; a veces con select mínimo) |
| Product | id, article_id, family_id, species_id, capture_zone_id, name, a3erp_code, facil_com_code, article_gtin, box_gtin, pallet_gtin, fixed_weight, ... |
| OrderPlannedProductDetail | id, order_id, product_id, tax_id, quantity, boxes, unit_price, ... |
| Tax, PaymentTerm, Salesperson, Transport, Incoterm, Incident, Article, Species, CaptureZone, ProductFamily, ProductCategory | Según lo que exponga cada toArrayAssoc(). |

### 3.3 Tareas concretas (mejora 2)

1. ~~**Auditar cada modelo** usado en la respuesta del show (Order, Customer, Pallet, PalletBox, Box, Product, OrderPlannedProductDetail, Tax, etc.) y listar en este doc o en un comentario las columnas que realmente usa el resource / toArrayAssoc().~~
2. ~~**Introducir `select()`** en el `with()` del show usando closures.~~ **Hecho:** `OrderController::show()` aplica `Order::select([...])` y en cada relación del `with()` un closure con `select()` (customer, payment_term, salesperson, transport, incoterm, plannedProductDetails + product/tax, incident, pallets, pallet_boxes, boxes, production_inputs, product + article.categoria, species, captureZone, family.category). Siempre se incluyen `id` y FKs necesarias.
3. **Comprobar** que la respuesta JSON del endpoint no pierde campos y que los tests pasan (recomendado probar GET orders/{id} en al menos un pedido con pallets/cajas).
4. Si en algún modelo se usa `$model->someRelation` sin acceder a columnas concretas (solo para exists/count), se puede mantener ese modelo con select mínimo (id + FK) o sin select si es pequeño.

---

## 4. Mejora 5: Índices en FKs usadas en el eager loading

**Objetivo:** Asegurar que todas las columnas usadas en joins y filtros del árbol Order → Pallets → Boxes → ProductionInputs / Product tengan índice donde corresponda.

### 4.1 Rutas de carga del show

- `orders.id` (PK).
- `pallets.order_id` → filtro por order.
- `pallet_boxes.pallet_id` → join pallets–pallet_boxes.
- `pallet_boxes.box_id` → join pallet_boxes–boxes.
- `boxes.article_id` (product) → join boxes–products.
- `production_inputs.box_id` → join boxes–production_inputs (para isAvailable).
- `orders.customer_id`, `payment_term_id`, `salesperson_id`, `transport_id`, `incoterm_id` → carga de relaciones del Order.
- `order_planned_product_details.order_id`, `incidents.order_id`.

En Laravel, `foreignId()` y `foreign()` suelen crear índice sobre la columna. Aun así, conviene verificar en BD que existan índices en:

- `pallets.order_id`
- `pallet_boxes.pallet_id`, `pallet_boxes.box_id`
- `boxes.article_id`
- `production_inputs.box_id` (ya existe en migración)
- `order_planned_product_details.order_id`
- `incidents.order_id` (si existe tabla incidents)

### 4.2 Tareas concretas (mejora 5)

1. ~~**Revisar migraciones** en `database/migrations/companies/` para orders, pallets, pallet_boxes, boxes, production_inputs, order_planned_product_details, incidents y anotar qué índices existen (FK suelen llevar índice implícito).~~
2. ~~**Ejecutar en BD** (o con un comando artisan que use Schema::getIndexListing() u otro método) un listado de índices de esas tablas y documentarlo.~~ (Las FK en Laravel/MySQL crean índice; production_inputs ya tiene índices explícitos.)
3. ~~**Añadir migraciones** solo para índices que falten.~~ **Hecho:** `database/migrations/companies/2025_12_12_100000_ensure_indexes_for_orders_show_path.php`. La migración comprueba por columna si ya existe algún índice (p. ej. el de la FK) y solo añade `index(column)` cuando falta, para las tablas/columnas del path: pallets.order_id, pallet_boxes.pallet_id|box_id, boxes.article_id, production_inputs.box_id, order_planned_product_details.order_id, incidents.order_id.
4. **Documentar:** El path del GET orders/{id} queda cubierto por esta migración (índices existentes o añadidos).

---

## 5. Orden de implementación recomendado

1. **Primero mejora 3 (N+1 y profundidad):** Añadir el `with()` completo en `show()`. Es el cambio que más impacto tiene en número de queries y es independiente de los selects.
2. **Después mejora 5 (índices):** Verificar y completar índices. Así la consulta pesada que ya hace el show (y la que hará con más relaciones) será más rápida.
3. **Por último mejora 2 (select explícito):** Una vez estable el árbol de relaciones, añadir `select()` por modelos para reducir ancho de banda y memoria.

---

## 6. Archivos a tocar (resumen)

| Mejora | Archivos principales |
|--------|------------------------|
| 3 | `app/Http/Controllers/v2/OrderController.php` (método show), posiblemente `app/Http/Resources/v2/OrderDetailsResource.php` si se condiciona algo |
| 2 | `app/Http/Controllers/v2/OrderController.php` (mismo show con closures y select en with) |
| 5 | `database/migrations/companies/` (nuevas migraciones solo si faltan índices) |

---

## 7. Criterios de aceptación

- **Mejora 3:** GET orders/{id} no dispara consultas adicionales al serializar (número de queries estable y bajo, p. ej. 1–2, independiente del número de pallets/cajas del pedido).
- **Mejora 2:** La respuesta JSON del show sigue siendo la misma (mismos campos y valores); solo se reduce carga en BD y memoria.
- **Mejora 5:** Todas las FKs del path Order → Pallets → Boxes → ProductionInputs/Product (y relaciones del Order) tienen índice verificado o añadido por migración.

---

## 8. Revisión post-implementación y pendiente de probar

**Estado:** Las mejoras 2, 3 y 5 están implementadas. Queda **pendiente de probar en entorno real o staging** para validar que la respuesta del endpoint no pierde ningún campo.

### 8.1 Comprobaciones recomendadas

- [ ] Llamar a **GET /api/v2/orders/{id}** con un pedido que tenga varios pallets y cajas.
- [ ] Comparar la respuesta JSON con la de antes de los cambios (o con un pedido de referencia): mismos campos en `customer`, `paymentTerm`, `salesperson`, `transport`, `incoterm`, `pallets`, `plannedProductDetails`, `productDetails`, `incident`, etc.
- [ ] Verificar que no aparezcan atributos `null` donde antes había valor (p. ej. en objetos anidados de cajas o productos).
- [ ] Si falta algún campo: añadirlo al `select()` correspondiente en `OrderController::show()` (en la relación que carga ese modelo) y documentar aquí el cambio.

### 8.2 Posibles ajustes conocidos

- **Box.palletId:** En `Box::toArrayAssoc()` se expone `palletId` como `$this->pallet_id`. La tabla `boxes` no tiene columna `pallet_id`; ese valor puede ser `null` si no se rellena desde otro sitio. Si el frontend necesita el pallet id en cada caja, se puede obtener del `PalletBox` padre (p. ej. en el resource o en un accessor en el modelo que use la relación `palletBox`).
- **getCustomerHistory():** Sigue haciendo una query aparte con `Order::where(...)->with(...)->get()`. No se ha aplicado select explícito ni límite de pedidos; se puede optimizar en un paso posterior si hace falta.
- **Código actual (Product/Article):** En la rama actual no existe el modelo `Article`; `Product::toArrayAssoc()` no usa `article`. Las relaciones `product.article` y `product.article.categoria` se eliminaron del `show()` para evitar errores. Si en el futuro se reintroduce Article u otra estructura, revisar de nuevo el `with()` y los selects.

### 8.3 Resumen de lo implementado (revisión)

| Ámbito | Comprobado |
|--------|------------|
| Order | select con id, buyer_reference, todas las FKs, direcciones, notas, emails, fechas, status, truck_plate, trailer_plate, temperature. |
| customer + customer.payment_term, salesperson, country, transport | select con columnas usadas en toArrayAssoc(). |
| payment_term, salesperson, transport, incoterm | select con columnas usadas (incl. emails donde aplica). |
| plannedProductDetails + product + tax | select con id, FKs, quantity, boxes, unit_price; product (sin article_id ni fixed_weight; Product ya no tiene relación article); product.species, captureZone, product.family, family.category; tax con id, name, rate. |
| incident | select con todas las columnas de toArrayAssoc(). |
| pallets | select id, observations, status, order_id. |
| pallet_boxes | select id, pallet_id, box_id, created_at, updated_at. |
| boxes | select id, article_id, lot, gs1_128, gross_weight, net_weight, created_at. |
| production_inputs | select id, box_id (solo para isEmpty / isAvailable). |
| product (en pallets) + species, captureZone, family, family.category | select con columnas usadas en Product::toArrayAssoc() (sin article; Product actual no tiene relación article). |

Si tras las pruebas se detecta algún campo faltante, añadirlo al `select()` de la relación correspondiente en `OrderController::show()` y anotar el cambio en este apartado.

---

## 9. Tercera revisión final (seguridad)

Revisión exhaustiva de columnas, relaciones y flujo sin modificar código adicional.

### 9.1 Checklist de verificación

| Verificación | Resultado |
|--------------|-----------|
| **Order** select vs. migraciones (orders, add_plates, add_temperature) | Todas las columnas existen. |
| **Customer** select vs. create_customers + add a3erp_code + add facilcom_code | Incl. `facilcom_code`; columnas correctas. |
| **Customer::toArrayAssoc()** | Usa `? -> : null` en payment_term, salesperson, country, transport; seguro con null. |
| **PaymentTerm, Tax, Incoterm, Country** | Selects alineados con create_* y toArrayAssoc(). |
| **Salesperson, Transport** | create_* + add_emails (salespeople); columnas correctas. |
| **OrderPlannedProductDetail** | Tabla order_planned_product_details; select sin columnas inexistentes. |
| **Product** | Sin article_id ni fixed_weight; columnas actuales (name, family_id, species_id, capture_zone_id, a3erp_code, facil_com_code, article_gtin, box_gtin, pallet_gtin) existen. |
| **Incident, Pallet, Box, ProductionInput** | Selects coherentes con create_* y toArrayAssoc(). |
| **Species, CaptureZone, ProductFamily, ProductCategory** | Columnas usadas en toArrayAssoc() incluidas en select. |
| **Relaciones** | Nombres correctos: pallets.boxes (PalletBox), .box (Box), .product, .species, .captureZone, .family.category; plannedProductDetails.product, .tax. |
| **FK en selects** | order_id, customer_id, payment_term_id, product_id, tax_id, pallet_id, box_id, article_id, etc. incluidos donde corresponde para que Eloquent monte las relaciones. |
| **PalletBox** | Select solo id, pallet_id, box_id, created_at, updated_at (tabla sin lot, net_weight, article_id). |
| **Migración role_user** | Incluye `Schema::hasTable('role_user')` antes de create; idempotente. |
| **Migración índices** | Tablas y columnas existentes; lógica “solo si no hay índice” correcta. |

### 9.2 Riesgo de relaciones null (mitigado)

- Tras `fix_orders_foreign_keys_on_delete`, algunos pedidos pueden tener `customer_id`, `payment_term_id`, `salesperson_id`, `transport_id` o `incoterm_id` en NULL; el resource podría devolver 500 al llamar `->toArrayAssoc()` sobre null.
- **Aplicado en tercera revisión:** en `OrderDetailsResource::toArray()` se usó acceso condicional para esas relaciones: `$this->customer?->toArrayAssoc()`, `$this->payment_term?->toArrayAssoc()`, `$this->salesperson?->toArrayAssoc()`, `$this->transport?->toArrayAssoc()`, `$this->incoterm?->toArrayAssoc()`. Así el endpoint devuelve `null` en el campo correspondiente en lugar de fallar.

### 9.3 Conclusión

En la tercera revisión se verificó la checklist (§9.1), se documentó el riesgo de FKs null y se aplicó la mitigación en el resource (§9.2). No hubo más cambios en el controlador ni en migraciones. Pendiente solo la prueba en entorno real/staging (§8.1).
