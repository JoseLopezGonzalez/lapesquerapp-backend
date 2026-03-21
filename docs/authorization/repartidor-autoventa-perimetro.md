# Autorización - Perímetro de `repartidor_autoventa`

## Objetivo

Recoger el perímetro real de acceso del actor `repartidor_autoventa` para evitar regresiones y usos incorrectos desde frontend o backend.

## Identidad requerida

No basta con:

- `User.role = repartidor_autoventa`

También hace falta:

- identidad operativa real `FieldOperator`

Si el usuario tiene el rol pero no tiene `FieldOperator`, los endpoints operativos deben responder `403`.

## Accesos permitidos

### Endpoints `field/*`

Permitidos:

- `GET /api/v2/field/customers/options`
- `GET /api/v2/field/products/options`
- `GET /api/v2/field/orders`
- `GET /api/v2/field/orders/{order}`
- `PUT /api/v2/field/orders/{order}`
- `POST /api/v2/field/autoventas`
- `GET /api/v2/field/routes`
- `GET /api/v2/field/routes/{route}`
- `PUT /api/v2/field/routes/{route}/stops/{routeStop}`

### Reglas de scope

- clientes: solo `customers.field_operator_id = current_field_operator.id`
- pedidos: solo `orders.field_operator_id = current_field_operator.id`
- rutas: solo `routes.field_operator_id = current_field_operator.id`

## Accesos bloqueados

### CRM

Bloqueados:

- `/api/v2/prospects`
- `/api/v2/offers`
- `/api/v2/commercial-interactions`
- `/api/v2/crm/dashboard`

### Catálogos y backoffice general

Bloqueados:

- `/api/v2/customers`
- `/api/v2/products/options`
- `/api/v2/stores/options`
- `/api/v2/pallets/*`
- gestión de usuarios
- gestión general de comerciales

### Documentos y reporting general

Bloqueados:

- `/api/v2/orders_report`
- `/api/v2/orders/{orderId}/pdf/order-sheet`
- exports generales de pedidos
- PDFs generales de pedidos

## Policies relevantes

El perímetro actual depende, al menos, de:

- `CustomerPolicy`
- `OrderPolicy`
- `DeliveryRoutePolicy`
- `ProductPolicy`
- `StorePolicy`
- `PalletPolicy`

## Reglas funcionales clave

### Cliente

El actor operativo:

- puede usar clientes con `field_operator_id` propio
- no tiene ficha de cliente
- no puede acceder al CRUD general de clientes

### Pedido

El actor operativo:

- puede ver y actualizar operativamente sus pedidos
- no puede modificar cliente ni condiciones comerciales

### Autoventa

Puede:

- vender sobre cliente operativo propio
- crear cliente nuevo y vender en la misma transacción

No puede:

- vender sobre cliente existente no operativo para él

### Ruta

Puede:

- ver rutas asignadas
- cerrar sus paradas

No puede:

- operar rutas ajenas
- convertir una parada con `targetType = prospect` en acceso al CRM del prospecto

## Respuesta esperada en errores

### `403`

Debe usarse cuando:

- no existe `FieldOperator`
- el recurso queda fuera del perímetro operativo
- el endpoint está fuera de su dominio

### `404`

Debe usarse cuando:

- el `routeStop` no pertenece a la `route`
- el recurso no existe

## Referencias

- [25-comercial-reparto-autoventa-operativa.md](/home/jose/lapesquerapp-backend/docs/pedidos/25-comercial-reparto-autoventa-operativa.md)
- [comercial-reparto-autoventa-integracion-frontend.md](/home/jose/lapesquerapp-backend/docs/frontend/comercial-reparto-autoventa-integracion-frontend.md)
