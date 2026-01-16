# Pedidos (Orders)

Documentación de endpoints para gestión de pedidos.

## Índice

- [Listar Pedidos](#listar-pedidos)
- [Crear Pedido](#crear-pedido)
- [Mostrar Pedido](#mostrar-pedido)
- [Actualizar Pedido](#actualizar-pedido)
- [Eliminar Pedido](#eliminar-pedido)
- [Eliminar Múltiples Pedidos](#eliminar-múltiples-pedidos)
- [Actualizar Estado de Pedido](#actualizar-estado-de-pedido)
- [Pedidos Activos](#pedidos-activos)
- [Opciones de Pedidos](#opciones-de-pedidos)
- [Opciones de Pedidos Activos](#opciones-de-pedidos-activos)
- [Productos Planificados](#productos-planificados)
- [Incidencias de Pedidos](#incidencias-de-pedidos)

---

## Listar Pedidos

Obtener lista de pedidos con filtros opcionales.

### Request

```http
GET /api/v2/orders
```

#### Headers
```http
X-Tenant: {subdomain}
Authorization: Bearer {access_token}
```

#### Query Parameters (Opcionales)

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| active | boolean | Filtrar por pedidos activos (`true`) o finalizados (`false`) |
| customers | array | Array de IDs de clientes |
| ids | array | Array de IDs de pedidos |
| id | string | Búsqueda parcial por ID |
| buyerReference | string | Búsqueda parcial por referencia del comprador |
| status | string | Estado del pedido (`pending`, `finished`) |
| loadDate | object | Filtro por fecha de carga: `{start: "2024-01-01", end: "2024-12-31"}` |
| entryDate | object | Filtro por fecha de entrada: `{start: "2024-01-01", end: "2024-12-31"}` |
| transports | array | Array de IDs de transportes |
| salespeople | array | Array de IDs de vendedores |
| palletsState | string | Estado de palets (`stored`, `shipping`) |
| products | array | Array de IDs de productos |
| species | array | Array de IDs de especies |
| incoterm | integer | ID de incoterm |
| perPage | integer | Elementos por página (default: 10) |

#### Ejemplo de Request

```http
GET /api/v2/orders?status=pending&customers[]=1&customers[]=2&perPage=20
```

### Response Exitosa (200)

```json
{
  "data": [
    {
      "id": 1,
      "customer": {
        "id": 1,
        "name": "Cliente A"
      },
      "entry_date": "2024-01-15",
      "load_date": "2024-01-20",
      "status": "pending",
      "buyer_reference": "REF-001",
      "total_net_weight": 1500.50,
      "total_amount": 45000.00,
      "created_at": "2024-01-15T10:00:00.000000Z",
      "updated_at": "2024-01-15T10:00:00.000000Z"
    }
  ],
  "current_page": 1,
  "last_page": 5,
  "per_page": 10,
  "total": 50
}
```

### Response Errónea (401)

```json
{
  "message": "No autenticado."
}
```

---

## Crear Pedido

Crear un nuevo pedido.

### Request

```http
POST /api/v2/orders
```

#### Headers
```http
X-Tenant: {subdomain}
Authorization: Bearer {access_token}
Content-Type: application/json
```

#### Request Body

```json
{
  "customer": 1,
  "entryDate": "2024-01-15",
  "loadDate": "2024-01-20",
  "salesperson": 1,
  "payment": 1,
  "incoterm": 1,
  "buyerReference": "REF-001",
  "transport": 1,
  "truckPlate": "ABC-1234",
  "trailerPlate": "XYZ-5678",
  "temperature": "-18°C",
  "billingAddress": "Calle Principal 123",
  "shippingAddress": "Calle Secundaria 456",
  "transportationNotes": "Manejar con cuidado",
  "productionNotes": "Notas de producción",
  "accountingNotes": "Notas contables",
  "emails": ["cliente@example.com"],
  "ccEmails": ["copia@example.com"],
  "plannedProducts": [
    {
      "product": 1,
      "quantity": 100.5,
      "boxes": 10,
      "unitPrice": 15.50,
      "tax": 1
    }
  ]
}
```

#### Campos Requeridos

| Campo | Tipo | Descripción |
|-------|------|-------------|
| customer | integer | ID del cliente (debe existir) |
| entryDate | date | Fecha de entrada (formato: YYYY-MM-DD) |
| loadDate | date | Fecha de carga (formato: YYYY-MM-DD) |

#### Campos Opcionales

| Campo | Tipo | Descripción |
|-------|------|-------------|
| salesperson | integer | ID del vendedor |
| payment | integer | ID del término de pago |
| incoterm | integer | ID del incoterm |
| buyerReference | string | Referencia del comprador |
| transport | integer | ID del transporte |
| truckPlate | string | Matrícula del camión |
| trailerPlate | string | Matrícula del remolque |
| temperature | string | Temperatura requerida |
| billingAddress | string | Dirección de facturación |
| shippingAddress | string | Dirección de envío |
| transportationNotes | string | Notas de transporte |
| productionNotes | string | Notas de producción |
| accountingNotes | string | Notas contables |
| emails | array | Array de emails |
| ccEmails | array | Array de emails en copia |
| plannedProducts | array | Array de productos planificados |

#### Campos de plannedProducts

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| product | integer | Sí | ID del producto |
| quantity | numeric | Sí | Cantidad |
| boxes | integer | Sí | Número de cajas |
| unitPrice | numeric | Sí | Precio unitario |
| tax | integer | Sí | ID del impuesto |

### Response Exitosa (201)

```json
{
  "message": "Pedido creado correctamente.",
  "data": {
    "id": 1,
    "customer_id": 1,
    "entry_date": "2024-01-15",
    "load_date": "2024-01-20",
    "status": "pending",
    "buyer_reference": "REF-001",
    "created_at": "2024-01-15T10:00:00.000000Z",
    "updated_at": "2024-01-15T10:00:00.000000Z"
  }
}
```

### Response Errónea (422) - Error de Validación

```json
{
  "message": "Error de validación.",
  "userMessage": "El campo customer es obligatorio.",
  "errors": {
    "customer": ["The customer field is required."],
    "entryDate": ["The entry date field is required."],
    "loadDate": ["The load date field is required."]
  }
}
```

### Response Errónea (401)

```json
{
  "message": "No autenticado."
}
```

---

## Mostrar Pedido

Obtener información detallada de un pedido específico.

### Request

```http
GET /api/v2/orders/{id}
```

#### Headers
```http
X-Tenant: {subdomain}
Authorization: Bearer {access_token}
```

#### Path Parameters

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| id | integer | ID del pedido |

### Response Exitosa (200)

```json
{
  "id": 1,
  "customer": {
    "id": 1,
    "name": "Cliente A",
    "code": "CLI001"
  },
  "salesperson": {
    "id": 1,
    "name": "Vendedor A"
  },
  "transport": {
    "id": 1,
    "name": "Transporte A"
  },
  "entry_date": "2024-01-15",
  "load_date": "2024-01-20",
  "status": "pending",
  "buyer_reference": "REF-001",
  "truck_plate": "ABC-1234",
  "trailer_plate": "XYZ-5678",
  "temperature": "-18°C",
  "total_net_weight": 1500.50,
  "total_amount": 45000.00,
  "planned_products": [
    {
      "id": 1,
      "product": {
        "id": 1,
        "name": "Producto A"
      },
      "quantity": 100.5,
      "boxes": 10,
      "unit_price": 15.50
    }
  ],
  "created_at": "2024-01-15T10:00:00.000000Z",
  "updated_at": "2024-01-15T10:00:00.000000Z"
}
```

### Response Errónea (404)

```json
{
  "message": "Pedido no encontrado.",
  "userMessage": "El pedido especificado no existe."
}
```

---

## Actualizar Pedido

Actualizar un pedido existente.

### Request

```http
PUT /api/v2/orders/{id}
```

#### Headers
```http
X-Tenant: {subdomain}
Authorization: Bearer {access_token}
Content-Type: application/json
```

#### Path Parameters

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| id | integer | ID del pedido |

#### Request Body

Mismos campos que [Crear Pedido](#crear-pedido), todos opcionales.

### Response Exitosa (200)

```json
{
  "message": "Pedido actualizado correctamente.",
  "data": {
    "id": 1,
    "customer_id": 1,
    "entry_date": "2024-01-15",
    "load_date": "2024-01-20",
    "status": "pending",
    "updated_at": "2024-01-15T11:00:00.000000Z"
  }
}
```

### Response Errónea (404)

```json
{
  "message": "Pedido no encontrado.",
  "userMessage": "El pedido especificado no existe."
}
```

### Response Errónea (422) - Error de Validación

```json
{
  "message": "Error de validación.",
  "userMessage": "El campo customer debe ser un número entero válido.",
  "errors": {
    "customer": ["The customer must be a valid integer."]
  }
}
```

---

## Eliminar Pedido

Eliminar un pedido.

### Request

```http
DELETE /api/v2/orders/{id}
```

#### Headers
```http
X-Tenant: {subdomain}
Authorization: Bearer {access_token}
```

#### Path Parameters

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| id | integer | ID del pedido |

### Response Exitosa (200)

```json
{
  "message": "Pedido eliminado correctamente."
}
```

### Response Errónea (404)

```json
{
  "message": "Pedido no encontrado.",
  "userMessage": "El pedido especificado no existe."
}
```

---

## Eliminar Múltiples Pedidos

Eliminar varios pedidos a la vez.

### Request

```http
DELETE /api/v2/orders
```

#### Headers
```http
X-Tenant: {subdomain}
Authorization: Bearer {access_token}
Content-Type: application/json
```

#### Request Body

```json
{
  "ids": [1, 2, 3, 4, 5]
}
```

#### Campos Requeridos

| Campo | Tipo | Descripción |
|-------|------|-------------|
| ids | array | Array de IDs de pedidos a eliminar |

### Response Exitosa (200)

```json
{
  "message": "Pedidos eliminados correctamente.",
  "deleted_count": 5
}
```

### Response Errónea (400) - IDs Inválidos

```json
{
  "message": "No se proporcionaron IDs válidos.",
  "userMessage": "Debe proporcionar al menos un ID válido para eliminar."
}
```

### Response Errónea (422) - Error de Validación

```json
{
  "message": "Error de validación.",
  "userMessage": "El campo ids es obligatorio.",
  "errors": {
    "ids": ["The ids field is required."]
  }
}
```

---

## Actualizar Estado de Pedido

Actualizar el estado de un pedido.

### Request

```http
PUT /api/v2/orders/{order}/status
```

#### Headers
```http
X-Tenant: {subdomain}
Authorization: Bearer {access_token}
Content-Type: application/json
```

#### Path Parameters

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| order | integer | ID del pedido |

#### Request Body

```json
{
  "status": "finished"
}
```

#### Campos Requeridos

| Campo | Tipo | Descripción |
|-------|------|-------------|
| status | string | Estado del pedido (`pending`, `finished`) |

### Response Exitosa (200)

```json
{
  "message": "Estado del pedido actualizado correctamente.",
  "data": {
    "id": 1,
    "status": "finished",
    "updated_at": "2024-01-15T12:00:00.000000Z"
  }
}
```

### Response Errónea (404)

```json
{
  "message": "Pedido no encontrado.",
  "userMessage": "El pedido especificado no existe."
}
```

### Response Errónea (422) - Error de Validación

```json
{
  "message": "Error de validación.",
  "userMessage": "El estado proporcionado no es válido.",
  "errors": {
    "status": ["The selected status is invalid."]
  }
}
```

---

## Pedidos Activos

Obtener lista de pedidos activos.

### Request

```http
GET /api/v2/orders/active
```

#### Headers
```http
X-Tenant: {subdomain}
Authorization: Bearer {access_token}
```

### Response Exitosa (200)

```json
{
  "data": [
    {
      "id": 1,
      "customer": {
        "id": 1,
        "name": "Cliente A"
      },
      "entry_date": "2024-01-15",
      "load_date": "2024-01-20",
      "status": "pending",
      "total_net_weight": 1500.50,
      "total_amount": 45000.00
    }
  ]
}
```

---

## Opciones de Pedidos

Obtener lista de pedidos en formato opciones (para dropdowns).

### Request

```http
GET /api/v2/orders/options
```

#### Headers
```http
X-Tenant: {subdomain}
Authorization: Bearer {access_token}
```

### Response Exitosa (200)

```json
[
  {
    "id": 1,
    "name": "ORD-001 - Cliente A"
  },
  {
    "id": 2,
    "name": "ORD-002 - Cliente B"
  }
]
```

---

## Opciones de Pedidos Activos

Obtener lista de pedidos activos en formato opciones (para dropdowns).

### Request

```http
GET /api/v2/active-orders/options
```

#### Headers
```http
X-Tenant: {subdomain}
Authorization: Bearer {access_token}
```

### Response Exitosa (200)

```json
[
  {
    "id": 1,
    "name": 1,
    "load_date": "2024-01-20"
  },
  {
    "id": 2,
    "name": 2,
    "load_date": "2024-01-21"
  }
]
```

**Nota:** Los pedidos activos son aquellos con estado `pending` o con `load_date` mayor o igual a la fecha actual.

---

## Productos Planificados

### Listar Productos Planificados

```http
GET /api/v2/order-planned-product-details
```

#### Headers
```http
X-Tenant: {subdomain}
Authorization: Bearer {access_token}
```

#### Query Parameters (Opcionales)

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| orderId | integer | Filtrar por ID de pedido |
| productId | integer | Filtrar por ID de producto |
| perPage | integer | Elementos por página (default: 15) |

#### Response Exitosa (200)

```json
{
  "data": [
    {
      "id": 1,
      "order_id": 1,
      "product": {
        "id": 1,
        "name": "Producto A"
      },
      "quantity": 100.50,
      "boxes": 10,
      "unit_price": 15.50,
      "tax": {
        "id": 1,
        "name": "IVA 21%"
      },
      "line_base": 1557.75,
      "line_total": 1557.75,
      "created_at": "2024-01-15T10:00:00.000000Z"
    }
  ],
  "current_page": 1,
  "last_page": 1,
  "per_page": 15,
  "total": 1
}
```

---

### Crear Producto Planificado

```http
POST /api/v2/order-planned-product-details
```

#### Headers
```http
X-Tenant: {subdomain}
Authorization: Bearer {access_token}
Content-Type: application/json
```

#### Request Body

```json
{
  "orderId": 1,
  "product": {
    "id": 1
  },
  "quantity": 100.50,
  "boxes": 10,
  "unitPrice": 15.50,
  "tax": {
    "id": 1
  }
}
```

#### Campos Requeridos

| Campo | Tipo | Descripción |
|-------|------|-------------|
| orderId | integer | ID del pedido (debe existir) |
| product.id | integer | ID del producto (debe existir) |
| quantity | numeric | Cantidad del producto |
| boxes | integer | Número de cajas |
| unitPrice | numeric | Precio unitario |
| tax.id | integer | ID del impuesto (debe existir) |

#### Response Exitosa (201)

```json
{
  "id": 1,
  "order_id": 1,
  "product": {
    "id": 1,
    "name": "Producto A"
  },
  "quantity": 100.50,
  "boxes": 10,
  "unit_price": 15.50,
  "tax": {
    "id": 1,
    "name": "IVA 21%"
  },
  "line_base": 1557.75,
  "line_total": 1557.75,
  "created_at": "2024-01-15T10:00:00.000000Z"
}
```

---

### Mostrar Producto Planificado

```http
GET /api/v2/order-planned-product-details/{id}
```

#### Response Exitosa (200)

Misma estructura que [Crear Producto Planificado](#crear-producto-planificado).

---

### Actualizar Producto Planificado

```http
PUT /api/v2/order-planned-product-details/{id}
```

#### Headers
```http
X-Tenant: {subdomain}
Authorization: Bearer {access_token}
Content-Type: application/json
```

#### Request Body

```json
{
  "product": {
    "id": 1
  },
  "quantity": 120.00,
  "boxes": 12,
  "unitPrice": 16.00,
  "tax": {
    "id": 1
  }
}
```

**Nota:** El campo `orderId` no se puede actualizar.

#### Response Exitosa (200)

```json
{
  "id": 1,
  "order_id": 1,
  "product": {
    "id": 1,
    "name": "Producto A"
  },
  "quantity": 120.00,
  "boxes": 12,
  "unit_price": 16.00,
  "line_base": 1920.00,
  "line_total": 1920.00,
  "updated_at": "2024-01-15T11:00:00.000000Z"
}
```

---

### Eliminar Producto Planificado

```http
DELETE /api/v2/order-planned-product-details/{id}
```

#### Headers
```http
X-Tenant: {subdomain}
Authorization: Bearer {access_token}
```

#### Response Exitosa (200)

```json
{
  "message": "Linea eliminada correctamente"
}
```

---

## Incidencias de Pedidos

### Mostrar Incidencia

```http
GET /api/v2/orders/{orderId}/incident
```

#### Headers
```http
X-Tenant: {subdomain}
Authorization: Bearer {access_token}
```

#### Path Parameters

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| orderId | integer | ID del pedido |

#### Response Exitosa (200)

```json
{
  "id": 1,
  "order_id": 1,
  "description": "Producto dañado durante el transporte",
  "status": "open",
  "resolution_type": null,
  "resolution_notes": null,
  "resolved_at": null,
  "created_at": "2024-01-15T10:00:00.000000Z",
  "updated_at": "2024-01-15T10:00:00.000000Z"
}
```

#### Response Errónea (404)

```json
{
  "message": "Incidencia no encontrada.",
  "userMessage": "No se encontró incidencia para este pedido."
}
```

---

### Crear Incidencia

```http
POST /api/v2/orders/{orderId}/incident
```

#### Headers
```http
X-Tenant: {subdomain}
Authorization: Bearer {access_token}
Content-Type: application/json
```

#### Path Parameters

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| orderId | integer | ID del pedido |

#### Request Body

```json
{
  "description": "Producto dañado durante el transporte"
}
```

#### Campos Requeridos

| Campo | Tipo | Descripción |
|-------|------|-------------|
| description | string | Descripción de la incidencia |

#### Response Exitosa (201)

```json
{
  "id": 1,
  "order_id": 1,
  "description": "Producto dañado durante el transporte",
  "status": "open",
  "created_at": "2024-01-15T10:00:00.000000Z"
}
```

#### Response Errónea (400) - Incidencia Ya Existe

```json
{
  "message": "La incidencia ya existe.",
  "userMessage": "Este pedido ya tiene una incidencia registrada."
}
```

---

### Actualizar Incidencia

```http
PUT /api/v2/orders/{orderId}/incident
```

#### Headers
```http
X-Tenant: {subdomain}
Authorization: Bearer {access_token}
Content-Type: application/json
```

#### Path Parameters

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| orderId | integer | ID del pedido |

#### Request Body

```json
{
  "resolution_type": "returned",
  "resolution_notes": "Producto devuelto al almacén"
}
```

#### Campos Requeridos

| Campo | Tipo | Descripción |
|-------|------|-------------|
| resolution_type | string | Tipo de resolución: `returned`, `partially_returned`, `compensated` |

#### Campos Opcionales

| Campo | Tipo | Descripción |
|-------|------|-------------|
| resolution_notes | string | Notas sobre la resolución |

#### Response Exitosa (200)

```json
{
  "id": 1,
  "order_id": 1,
  "description": "Producto dañado durante el transporte",
  "status": "resolved",
  "resolution_type": "returned",
  "resolution_notes": "Producto devuelto al almacén",
  "resolved_at": "2024-01-15T12:00:00.000000Z",
  "updated_at": "2024-01-15T12:00:00.000000Z"
}
```

#### Response Errónea (404)

```json
{
  "message": "Incidencia no encontrada.",
  "userMessage": "No se encontró incidencia para este pedido."
}
```

---

### Eliminar Incidencia

```http
DELETE /api/v2/orders/{orderId}/incident
```

#### Headers
```http
X-Tenant: {subdomain}
Authorization: Bearer {access_token}
```

#### Path Parameters

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| orderId | integer | ID del pedido |

#### Response Exitosa (200)

```json
{
  "message": "Incident deleted"
}
```

**Nota:** Al eliminar una incidencia, el pedido se finaliza automáticamente y todos los palets asociados se marcan como enviados.

#### Response Errónea (404)

```json
{
  "message": "Incidencia no encontrada.",
  "userMessage": "No se encontró incidencia para este pedido."
}
```

