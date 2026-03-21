# Frontend - Integración con Comercial / Reparto / Autoventa

## Objetivo

Documentar qué necesita saber frontend para integrarse con el backend real de:

- `comercial CRM`
- `repartidor_autoventa`
- clientes operativos
- pedidos operativos
- autoventa operativa
- rutas y paradas

Este documento no define UI ni arquitectura frontend. Solo contratos e interacción con backend.

## Headers y convenciones base

Aplican las convenciones generales de la API v2:

- `X-Tenant` obligatorio
- `Authorization: Bearer {token}` en rutas protegidas
- `Accept: application/json` recomendado

Referencia general:

- [api-conventions.md](/home/jose/lapesquerapp-backend/docs/frontend/api-conventions.md)

## Bootstrap de sesión

### Endpoint base

- `GET /api/v2/me`

### Campos relevantes

- `role`
- `actorType`
- `salespersonId`
- `fieldOperatorId`
- `isFieldOperator`
- `allowedStoreIds`
- `features`

### Regla de lectura

- si `role = repartidor_autoventa`, frontend debe trabajar con el perímetro `field/*`
- si `fieldOperatorId = null`, el usuario no tiene identidad operativa activa y los endpoints `field/*` devolverán `403`

## Perímetros por actor

### Comercial / backoffice

Puede consumir:

- endpoints generales
- CRUD de clientes
- CRM
- gestión de `FieldOperator`
- plantillas y rutas
- asignación de cliente

### Repartidor / autoventa

Debe consumir:

- `GET /api/v2/field/customers/options`
- `GET /api/v2/field/products/options`
- `GET /api/v2/field/orders`
- `GET /api/v2/field/orders/{order}`
- `PUT /api/v2/field/orders/{order}`
- `POST /api/v2/field/autoventas`
- `GET /api/v2/field/routes`
- `GET /api/v2/field/routes/{route}`
- `PUT /api/v2/field/routes/{route}/stops/{routeStop}`

No debe consumir:

- `/api/v2/customers`
- `/api/v2/products/options`
- `/api/v2/stores/options`
- `/api/v2/prospects`
- `/api/v2/offers`
- `/api/v2/commercial-interactions`
- `/api/v2/crm/dashboard`
- `/api/v2/orders_report`
- `/api/v2/orders/{orderId}/pdf/order-sheet`

## Shapes de respuesta

### Listados paginados

Esperar:

- `data`
- `links`
- `meta`

### Detalle

Esperar:

- `data`

### Acción mutadora

Esperar:

- `message`
- `data`

### `options`

Esperar:

- array simple

## Clientes operativos

### Selector operativo

- `GET /api/v2/field/customers/options`

Respuesta por elemento:

- `id`
- `name`
- `operationalStatus`

Reglas:

- solo devuelve clientes con acceso operativo del actor actual
- no existe endpoint de ficha de cliente para el repartidor

### Asignación desde backoffice

- `PUT /api/v2/customers/{customer}/assignment`

Payload admitido:

- `salesperson_id`
- `field_operator_id`
- `operational_status`

Valores válidos de `operational_status`:

- `normal`
- `alta_operativa`

## Productos operativos

### Catálogo para autoventa

- `GET /api/v2/field/products/options`

Respuesta por elemento:

- `id`
- `name`
- `species`
  - `id`
  - `name`

Regla:

- el actor operativo no debe usar `GET /api/v2/products/options`

## Pedidos operativos

### Listado

- `GET /api/v2/field/orders`

Filtros admitidos:

- `status`
- `orderType`
- `routeId`
- `perPage`

Valores:

- `status`: `pending`, `finished`, `incident`
- `orderType`: `standard`, `autoventa`

### Recurso devuelto

`FieldOrderResource`:

- `id`
- `orderType`
- `status`
- `entryDate`
- `loadDate`
- `buyerReference`
- `customer`
  - `id`
  - `name`
- `fieldOperatorId`
- `routeId`
- `routeStopId`
- `plannedProductDetails`
- `totalBoxes`
- `totalNetWeight`
- `createdAt`
- `updatedAt`

### Detalle

- `GET /api/v2/field/orders/{order}`

### Actualización operativa

- `PUT /api/v2/field/orders/{order}`

Payload admitido:

- `status`
- `plannedProducts`

Cada línea de `plannedProducts`:

- `product`
- `quantity`
- `boxes`
- `unitPrice`
- `tax`

Reglas:

- debe enviarse `status`, `plannedProducts` o ambos
- `plannedProducts` se interpreta como conjunto operativo completo de líneas
- este endpoint no cambia cliente ni condiciones comerciales

## Autoventa operativa

### Endpoint

- `POST /api/v2/field/autoventas`

### Payload

- `customer`
- `newCustomerName`
- `entryDate`
- `loadDate`
- `invoiceRequired`
- `observations`
- `items`
- `boxes`
- `routeId`
- `routeStopId`

### Reglas

- debe enviarse `customer` o `newCustomerName`
- `entryDate <= loadDate`
- si se usa `customer`, debe pertenecer al actor operativo
- si se usa `newCustomerName`, backend crea cliente en la misma transacción
- si se usa `routeId`, la ruta debe pertenecer al actor
- si se usa `routeStopId`, la parada debe pertenecer a la ruta y al actor

### `items`

- `productId`
- `boxesCount`
- `totalWeight`
- `unitPrice`
- `subtotal`
- `tax`

### `boxes`

- `productId`
- `lot`
- `netWeight`
- `grossWeight`
- `gs1128`

### Respuesta

`201` con:

- `message`
- `data` con `FieldOrderResource`

## Rutas operativas

### Listado

- `GET /api/v2/field/routes`

Parámetros:

- `perPage`

### Detalle

- `GET /api/v2/field/routes/{route}`

### Recurso devuelto

`DeliveryRouteResource`:

- `id`
- `routeTemplateId`
- `name`
- `description`
- `routeDate`
- `status`
- `salesperson`
- `fieldOperator`
- `createdByUserId`
- `stops`
- `createdAt`
- `updatedAt`

Cada parada:

- `id`
- `position`
- `stopType`
- `targetType`
- `customerId`
- `prospectId`
- `label`
- `address`
- `notes`
- `status`
- `resultType`
- `resultNotes`
- `completedAt`

Regla importante:

- si una parada viene con `targetType = prospect`, eso **no** habilita acceso CRM al prospecto; frontend debe tratarlo solo como referencia operativa dentro de la ruta

### Cierre de parada

- `PUT /api/v2/field/routes/{route}/stops/{routeStop}`

Payload admitido:

- `status`
- `result_type`
- `result_notes`

Reglas:

- `status` obligatorio
- `result_type` obligatorio si `status = completed`

Valores válidos de `result_type`:

- `delivery`
- `autoventa`
- `no_contact`
- `incident`
- `visit`

## Backoffice operativo

### `FieldOperator`

Endpoints:

- `GET /api/v2/field-operators`
- `POST /api/v2/field-operators`
- `GET /api/v2/field-operators/{field_operator}`
- `PUT /api/v2/field-operators/{field_operator}`
- `DELETE /api/v2/field-operators/{field_operator}`
- `GET /api/v2/field-operators/options`

Restricciones:

- el usuario vinculado debe tener rol `repartidor_autoventa`
- no puede tener ya `Salesperson`
- si el `FieldOperator` tiene clientes, pedidos o rutas asociadas, el borrado devuelve `400`

### `RouteTemplate`

Endpoints:

- `GET /api/v2/route-templates`
- `POST /api/v2/route-templates`
- `GET /api/v2/route-templates/{route_template}`
- `PUT /api/v2/route-templates/{route_template}`
- `DELETE /api/v2/route-templates/{route_template}`

Payload:

- `name`
- `description`
- `salespersonId`
- `fieldOperatorId`
- `isActive`
- `stops`

### `Route`

Endpoints:

- `GET /api/v2/routes`
- `POST /api/v2/routes`
- `GET /api/v2/routes/{route}`
- `PUT /api/v2/routes/{route}`
- `DELETE /api/v2/routes/{route}`

Payload:

- `routeTemplateId`
- `name`
- `description`
- `routeDate`
- `status`
- `salespersonId`
- `fieldOperatorId`
- `stops`

Reglas:

- si se envía `routeTemplateId` sin `stops`, backend instancia paradas desde plantilla
- si se envía `stops` en update, backend reemplaza todas las paradas actuales

## Errores que frontend debe considerar normales

### `403`

- fuera de perímetro
- sin `FieldOperator`
- recurso no asignado al actor actual

### `404`

- recurso inexistente
- parada que no pertenece a la ruta indicada

### `422`

- payload inválido
- fechas incoherentes
- cliente no operativo para el actor
- `result_type` ausente cuando es obligatorio

## Enums que frontend debe tratar como contrato

### Pedido operativo

- `pending`
- `finished`
- `incident`

### Tipo de pedido

- `standard`
- `autoventa`

### Estado operativo de cliente

- `normal`
- `alta_operativa`

### Tipos de target de parada

- `customer`
- `prospect`
- `location`

### Resultado de parada

- `delivery`
- `autoventa`
- `no_contact`
- `incident`
- `visit`

## Regla final de integración

Si para el actor operativo existe endpoint `field/*`, ese es el contrato correcto.

Si falta información en ese perímetro, la solución correcta es ampliar backend operativo, no reutilizar un endpoint general bloqueado por diseño.

## Referencias

- [25-comercial-reparto-autoventa-operativa.md](/home/jose/lapesquerapp-backend/docs/pedidos/25-comercial-reparto-autoventa-operativa.md)
- [repartidor-autoventa-perimetro.md](/home/jose/lapesquerapp-backend/docs/authorization/repartidor-autoventa-perimetro.md)
- [73-modelo-logico-comercial-reparto-autoventa.md](/home/jose/lapesquerapp-backend/docs/por-hacer/73-modelo-logico-comercial-reparto-autoventa.md)
