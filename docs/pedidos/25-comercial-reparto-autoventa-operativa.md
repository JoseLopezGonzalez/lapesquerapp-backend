# Comercial / Reparto / Autoventa Operativa

## Estado

Implementado en backend y validado en Sail a fecha `2026-03-21`.

## Objetivo

Documentar la lógica real ya implementada para separar:

- `comercial CRM`
- `repartidor_autoventa`

sin romper:

- CRM
- autoventa legacy
- `Order` como entidad transaccional central

## Modelo vigente

### Actores

#### Comercial CRM

Sigue usando:

- `User.role = comercial`
- identidad de negocio `Salesperson`

Responsabilidad:

- ownership comercial
- CRM
- cartera
- ofertas
- pedidos desde la óptica comercial

#### Repartidor / autoventa

Usa:

- `User.role = repartidor_autoventa`
- identidad de negocio `FieldOperator`

Responsabilidad:

- ejecución operativa
- rutas
- pedidos prefijados asignados
- autoventa
- alta operativa de clientes

## Entidades reales

### `FieldOperator`

Identidad operativa interna 1:1 con `User`.

Permite:

- asignar clientes operativos
- asignar pedidos operativos
- asignar rutas
- crear autoventas operativas

Restricciones:

- el `user_id` enlazado debe tener rol `repartidor_autoventa`
- no puede enlazarse a un `User` que ya tenga `Salesperson`

### `Customer`

Campos relevantes ya implementados:

- `salesperson_id` nullable
- `field_operator_id` nullable
- `operational_status`

Significado:

- `salesperson_id`: ownership comercial
- `field_operator_id`: actor operativo actual
- `operational_status`: estado operativo del cliente

Valores de `operational_status`:

- `normal`
- `alta_operativa`

Caso especial:

Un cliente creado desde autoventa operativa nace con:

- `salesperson_id = null`
- `field_operator_id = FieldOperator actual`
- `operational_status = alta_operativa`

### `Order`

Campos relevantes ya implementados:

- `salesperson_id`
- `field_operator_id`
- `created_by_user_id`
- `route_id`
- `route_stop_id`
- `order_type`

Significado:

- `salesperson_id`: ownership / contexto comercial
- `field_operator_id`: ejecutor operativo
- `created_by_user_id`: trazabilidad de creación
- `route_id` y `route_stop_id`: vínculo opcional con ejecución en ruta

Valores relevantes:

- `order_type = standard`
- `order_type = autoventa`

### `RouteTemplate`, `RouteTemplateStop`, `DeliveryRoute`, `RouteStop`

Implementan la capa operativa de planificación y ejecución.

La ruta es:

- una guía operativa
- no un contenedor obligatorio

Una autoventa o un pedido operativo:

- pueden vincularse a ruta/parada
- pero no dependen obligatoriamente de ello

## Flujos reales implementados

### 1. Pedido prefijado asignado a repartidor

Un pedido puede quedar asignado a un `FieldOperator` mediante `orders.field_operator_id`.

El actor operativo puede:

- verlo en `/api/v2/field/orders`
- abrirlo en `/api/v2/field/orders/{order}`
- actualizar solo su parte operativa en `PUT /api/v2/field/orders/{order}`

Puede:

- registrar **ejecución real** (cajas/palets) del pedido con payload de `boxes[]` (mismo shape que autoventa)
- añadir **productos extra** no prefijados (crea nuevas líneas planificadas sin borrar las existentes)
- ajustar **solo** `unit_price` e `iva` de líneas planificadas existentes (sin tocar cantidades)

Notas de integridad:

- La ejecución operativa se sincroniza como **estado completo**: si una caja existente no viene en el payload, backend la interpreta como **eliminada**.
- Invariante asumida: una caja vinculada a un pedido operativo no debe consumirse en producción. Si se detectase el caso, el borrado debe bloquearse para mantener trazabilidad.

No puede cambiar:

- cliente
- fechas
- condiciones comerciales

### 2. Autoventa sobre cliente existente

El actor operativo puede crear autoventa si:

- el cliente existe
- `customers.field_operator_id = current_field_operator.id`

Si no se cumple, backend rechaza la operación.

### 3. Autoventa con alta de cliente

El actor operativo puede crear autoventa enviando `newCustomerName`.

Backend crea en la misma transacción:

- el cliente
- el pedido de autoventa
- la parte física/logística asociada

### 4. Ejecución de ruta

El actor operativo puede:

- listar sus rutas
- abrir una ruta
- registrar resultado de una parada

No puede:

- replanificar por API el conjunto completo de rutas
- acceder a rutas ajenas
- usar una parada sobre `prospect` como acceso al CRM; el prospecto en ruta es solo referencia operativa

## Contratos backend relevantes

### Endpoints operativos

- `GET /api/v2/field/customers/options`
- `GET /api/v2/field/products/options`
- `GET /api/v2/field/orders`
- `GET /api/v2/field/orders/{order}`
- `PUT /api/v2/field/orders/{order}`
- `POST /api/v2/field/autoventas`
- `GET /api/v2/field/routes`
- `GET /api/v2/field/routes/{route}`
- `PUT /api/v2/field/routes/{route}/stops/{routeStop}`

### Endpoints administrativos relacionados

- `GET /api/v2/field-operators`
- `POST /api/v2/field-operators`
- `GET /api/v2/field-operators/{field_operator}`
- `PUT /api/v2/field-operators/{field_operator}`
- `DELETE /api/v2/field-operators/{field_operator}`
- `GET /api/v2/field-operators/options`
- `PUT /api/v2/customers/{customer}/assignment`
- `GET /api/v2/route-templates`
- `POST /api/v2/route-templates`
- `GET /api/v2/routes`
- `POST /api/v2/routes`

Consideración útil para backoffice:

- `DELETE /api/v2/field-operators/{field_operator}` devuelve `400` si el actor operativo tiene clientes, pedidos o rutas asociadas

## Autorización

El actor `repartidor_autoventa` tiene perímetro explícito.

Puede:

- usar endpoints `field/*`
- operar sus pedidos asignados
- operar sus rutas asignadas
- usar sus clientes operativos

No puede:

- acceder al CRM
- usar `products/options` general
- usar `stores/options`
- usar exports generales
- usar PDFs generales
- entrar en CRUD general de clientes

## Integridad de datos

### Migraciones relevantes

Ya están aplicadas migraciones tenant para:

- `field_operators`
- campos operativos en `customers`
- campos operativos en `orders`
- `route_templates`
- `route_template_stops`
- `routes`
- `route_stops`

### Endurecimiento adicional

Existe migración correctiva que:

- sanea `orders.route_id`
- sanea `orders.route_stop_id`
- corrige incoherencias entre `route_id` y `route_stop_id`
- añade foreign keys con `nullOnDelete()`

También existe migración correctiva que sustituye:

- `route_stops.result`

por:

- `route_stops.result_type`
- `route_stops.result_notes`

## Estados y enums relevantes

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

### Resultado de parada

- `delivery`
- `autoventa`
- `no_contact`
- `incident`
- `visit`

## Lo que no cambia

- CRM sigue siendo dominio exclusivo del comercial
- `salesperson_id` sigue significando ownership comercial
- autoventa legacy de comercial sigue funcionando
- `Order` sigue siendo la entidad central
- el sistema sigue siendo multi-tenant

## Validación realizada

Validado en Sail con:

- migración central
- `tenants:migrate`
- batería focalizada de tests de actor operativo, autorización, clientes, pedidos y rutas

Último resultado documentado:

- `40` tests
- `132` assertions
- `1` warning no bloqueante fuera de este bloque funcional

## Referencias

- [73-modelo-logico-comercial-reparto-autoventa.md](/home/jose/lapesquerapp-backend/docs/por-hacer/73-modelo-logico-comercial-reparto-autoventa.md)
- [comercial-reparto-autoventa-integracion-frontend.md](/home/jose/lapesquerapp-backend/docs/frontend/comercial-reparto-autoventa-integracion-frontend.md)
- [repartidor-autoventa-perimetro.md](/home/jose/lapesquerapp-backend/docs/authorization/repartidor-autoventa-perimetro.md)
