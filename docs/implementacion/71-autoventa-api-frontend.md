# Autoventa — API para Frontend

**Estado**: Referencia para integración  
**Última actualización**: 2026-02-18

Documento de referencia para el equipo frontend: endpoints, request/response (éxito y error) y validaciones del flujo **Autoventa** (rol comercial).

---

## 1. Introducción y convenciones

### Base URL

Todas las rutas son relativas a la base de la API: **`/api/v2`** (el host lo define el frontend).

### Headers obligatorios

En **todas** las peticiones:

| Header | Valor | Descripción |
|--------|--------|-------------|
| `Authorization` | `Bearer {access_token}` | Token Sanctum del usuario logueado |
| `X-Tenant` | `{subdomain}` | Subdominio del tenant (empresa) |
| `Content-Type` | `application/json` | Solo en peticiones con body (POST, PUT, PATCH) |

### Rol y permisos

- Solo usuarios con rol **comercial** (y con un Salesperson asociado) pueden **crear** autoventas.
- El listado de pedidos y de clientes ya filtra por comercial en backend: el usuario solo ve sus pedidos y sus clientes.
- Si un usuario sin rol comercial intenta crear una autoventa, el backend responde **422** con un mensaje en `errors.orderType`.

### Códigos QR (GS1-128)

- **No existe endpoint de escaneo** en el backend.
- El frontend debe **parsear** el código GS1-128 y extraer: producto (GTIN/identificador), peso neto y lote.
- Con esos datos se construyen los arrays `items` (agregado por producto) y `boxes` (una entrada por caja física) que se envían al crear la autoventa.

---

## 2. Endpoints del flujo Autoventa

| Método | Ruta | Uso |
|--------|------|-----|
| GET | `/api/v2/customers/options` | Selector de cliente (solo clientes del comercial) |
| POST | `/api/v2/customers` | Creación rápida de cliente (solo nombre; comercial asignado por backend) |
| POST | `/api/v2/orders` | Crear autoventa (body con `orderType: 'autoventa'`, `items`, `boxes`, etc.) |
| GET | `/api/v2/orders?orderType=autoventa` | Listar solo autoventas del comercial (paginado) |
| GET | `/api/v2/orders/{id}` | Ver detalle de una autoventa (resumen, ticket, reimpresión) |

---

## 3. Crear autoventa

### Request

```http
POST /api/v2/orders
```

**Headers:** `Authorization: Bearer {token}`, `X-Tenant: {subdomain}`, `Content-Type: application/json`

**Body (campos para autoventa):**

| Campo | Tipo | Obligatorio | Descripción |
|-------|------|-------------|-------------|
| `orderType` | string | Sí (para autoventa) | Debe ser `"autoventa"` |
| `customer` | number | Sí | ID del cliente (tenant) |
| `entryDate` | string | Sí | Fecha de entrada en formato `Y-m-d` (ej. `"2026-02-18"`) |
| `loadDate` | string | Sí | Fecha de carga en formato `Y-m-d`; debe ser ≥ `entryDate` |
| `invoiceRequired` | boolean | Sí | `true` = con factura, `false` = solo recibo |
| `observations` | string | No | Texto libre; máximo 1000 caracteres |
| `items` | array | Sí | Líneas agregadas por producto (al menos una) |
| `items[].productId` | number | Sí | ID del producto (tenant) |
| `items[].boxesCount` | number | Sí | Número de cajas de ese producto (entero ≥ 1) |
| `items[].totalWeight` | number | Sí | Peso total de la línea (kg) |
| `items[].unitPrice` | number | Sí | Precio unitario (≥ 0) |
| `items[].subtotal` | number | No | Solo informativo; el backend no lo usa para validar |
| `items[].tax` | number | No | ID del impuesto (tenant); si no se envía, el backend usa el primero del tenant |
| `boxes` | array | Sí | Una entrada por caja física (al menos una) |
| `boxes[].productId` | number | Sí | ID del producto (tenant) |
| `boxes[].lot` | string | No | Lote; si no se envía, el backend genera uno (ej. `AUTOVENTA-{orderId}-{index}`) |
| `boxes[].netWeight` | number | Sí | Peso neto de la caja (kg); debe ser > 0 |
| `boxes[].gs1128` | string | No | Código GS1-128 escaneado (opcional) |
| `boxes[].grossWeight` | number | No | Peso bruto; si no se envía, se usa el mismo que `netWeight` |

**Ejemplo de body (request):**

```json
{
  "orderType": "autoventa",
  "customer": 10,
  "entryDate": "2026-02-18",
  "loadDate": "2026-02-18",
  "invoiceRequired": true,
  "observations": "Entrega en puerto",
  "items": [
    {
      "productId": 45,
      "boxesCount": 3,
      "totalWeight": 37.5,
      "unitPrice": 15.00,
      "subtotal": 562.50,
      "tax": 1
    }
  ],
  "boxes": [
    {
      "productId": 45,
      "lot": "LOT-2024-001",
      "netWeight": 12.5,
      "gs1128": "011234567890123131000012510LOT-2024-001",
      "grossWeight": 12.6
    },
    {
      "productId": 45,
      "lot": "LOT-2024-001",
      "netWeight": 12.5
    }
  ]
}
```

### Respuesta éxito (201 Created)

El cuerpo incluye `message` y `data` con el pedido creado en formato detalle (OrderDetailsResource).

```json
{
  "message": "Pedido creado correctamente.",
  "data": {
    "id": 42,
    "orderType": "autoventa",
    "buyerReference": null,
    "customer": {
      "id": 10,
      "name": "Cliente Ejemplo",
      "alias": "Cliente Nº 10",
      "vatNumber": null,
      "paymentTerm": null,
      "billingAddress": null,
      "shippingAddress": null,
      "transportationNotes": null,
      "productionNotes": null,
      "accountingNotes": null,
      "salesperson": { "id": 2, "name": "Comercial 1", "emails": [], "ccEmails": [], "createdAt": "...", "updatedAt": "..." },
      "emails": [],
      "ccEmails": [],
      "contactInfo": null,
      "country": null,
      "transport": null,
      "createdAt": "...",
      "updatedAt": "..."
    },
    "paymentTerm": null,
    "billingAddress": null,
    "shippingAddress": null,
    "transportationNotes": null,
    "productionNotes": null,
    "accountingNotes": "Con factura\nEntrega en puerto",
    "salesperson": { "id": 2, "name": "Comercial 1", "emails": [], "ccEmails": [], "createdAt": "...", "updatedAt": "..." },
    "transport": null,
    "entryDate": "2026-02-18",
    "loadDate": "2026-02-18",
    "status": "pending",
    "pallets": [
      {
        "id": 101,
        "observations": null,
        "state": { "id": 3, "name": "shipped" },
        "boxes": [
          {
            "id": 501,
            "palletId": 101,
            "product": { "id": 45, "name": "Merluza", "species": [], "captureZone": [], "category": null, "family": null, "articleGtin": null, "boxGtin": null, "palletGtin": null, "a3erpCode": null, "facilcomCode": null },
            "lot": "LOT-2024-001",
            "gs1128": "011234567890123131000012510LOT-2024-001",
            "grossWeight": 12.6,
            "netWeight": 12.5,
            "createdAt": "..."
          }
        ],
        "netWeight": 37.5,
        "productsNames": ["Merluza"],
        "lots": ["LOT-2024-001"],
        "numberOfBoxes": 3
      }
    ],
    "incoterm": null,
    "totalNetWeight": 37.5,
    "numberOfPallets": 1,
    "totalBoxes": 3,
    "createdAt": "2026-02-18T12:00:00.000000Z",
    "updatedAt": "2026-02-18T12:00:00.000000Z",
    "plannedProductDetails": [
      {
        "id": 88,
        "orderId": 42,
        "product": { "id": 45, "name": "Merluza", "species": [], "captureZone": [], "category": null, "family": null, "articleGtin": null, "boxGtin": null, "palletGtin": null, "a3erpCode": null, "facilcomCode": null },
        "tax": { "id": 1, "name": "IVA 10%", "rate": 10, "createdAt": "...", "updatedAt": "..." },
        "quantity": 37.5,
        "boxes": 3,
        "unitPrice": 15
      }
    ],
    "productionProductDetails": null,
    "productDetails": null,
    "subTotalAmount": 562.5,
    "totalAmount": 562.5,
    "emails": [],
    "ccEmails": [],
    "truckPlate": null,
    "trailerPlate": null,
    "temperature": null,
    "incident": null
  }
}
```

En autoventa, `orderType` será siempre `"autoventa"` y se crea un único palet con `state.name === "shipped"`.

### Respuestas error

El frontend debe mostrar al usuario el contenido de `userMessage` (o los mensajes en `errors` por campo). No exponer el contenido de `error` en respuestas 500.

**401 Unauthorized** (token ausente o inválido):

```json
{
  "message": "No autenticado.",
  "userMessage": "Debes iniciar sesión para acceder a este recurso."
}
```

**422 Unprocessable Entity (validación):**

En todas las respuestas 422 el cuerpo incluye `message`, `userMessage` (resumen legible para el usuario) y `errors` (detalle por campo).

- Rol no comercial al intentar crear autoventa:

```json
{
  "message": "Error de validación.",
  "userMessage": "Solo los usuarios con rol comercial pueden crear autoventas.",
  "errors": {
    "orderType": ["Solo los usuarios con rol comercial pueden crear autoventas."]
  }
}
```

- Cliente no asignado al comercial:

```json
{
  "message": "Error de validación.",
  "userMessage": "Solo puede crear pedidos para clientes asignados a usted.",
  "errors": {
    "customer": ["Solo puede crear pedidos para clientes asignados a usted."]
  }
}
```

- Sin impuestos configurados en el tenant:

```json
{
  "message": "Error de validación.",
  "userMessage": "No hay ningún impuesto configurado. Cree al menos uno en Catálogos para poder registrar autoventas.",
  "errors": {
    "general": ["No hay ningún impuesto configurado. Cree al menos uno en Catálogos para poder registrar autoventas."]
  }
}
```

- Items o boxes vacíos / inválidos (ejemplo):

```json
{
  "message": "Error de validación.",
  "userMessage": "Debe incluir al menos una línea de producto en la autoventa. y Debe incluir al menos una caja en la autoventa.",
  "errors": {
    "items": ["Debe incluir al menos una línea de producto en la autoventa."],
    "boxes": ["Debe incluir al menos una caja en la autoventa."],
    "invoiceRequired": ["Debe indicar si la autoventa lleva factura."],
    "items.0.productId": ["Uno o más productos no existen."],
    "boxes.0.netWeight": ["El peso neto debe ser mayor que 0."]
  }
}
```

**500 Internal Server Error:**

```json
{
  "message": "Error al crear el pedido",
  "userMessage": "Ocurrió un error al crear el pedido. Por favor, intente de nuevo.",
  "error": "Mensaje técnico del servidor (no mostrar tal cual al usuario)."
}
```

---

## 4. Listar autoventas

### Request

```http
GET /api/v2/orders?orderType=autoventa&perPage=20&page=1
```

**Headers:** `Authorization: Bearer {token}`, `X-Tenant: {subdomain}`

**Query params (opcionales):**

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `orderType` | string | `autoventa` para listar solo autoventas |
| `perPage` | number | Elementos por página (p. ej. 10–100) |
| `page` | number | Página (por defecto 1) |
| `status` | string | Filtrar por estado: `pending`, `finished`, `incident` |
| `loadDate` | object | Rango: `{ "start": "2026-01-01", "end": "2026-02-18" }` |

### Respuesta éxito (200 OK)

Respuesta paginada estándar Laravel. Cada elemento del array `data` sigue el formato OrderResource (listado).

```json
{
  "data": [
    {
      "id": 42,
      "orderType": "autoventa",
      "customer": {
        "id": 10,
        "name": "Cliente Ejemplo",
        "alias": "Cliente Nº 10",
        "vatNumber": null,
        "paymentTerm": null,
        "billingAddress": null,
        "shippingAddress": null,
        "transportationNotes": null,
        "productionNotes": null,
        "accountingNotes": null,
        "salesperson": { "id": 2, "name": "Comercial 1", "emails": [], "ccEmails": [], "createdAt": "...", "updatedAt": "..." },
        "emails": [],
        "ccEmails": [],
        "contactInfo": null,
        "country": null,
        "transport": null,
        "createdAt": "...",
        "updatedAt": "..."
      },
      "buyerReference": null,
      "status": "pending",
      "loadDate": "2026-02-18",
      "salesperson": { "id": 2, "name": "Comercial 1", "emails": [], "ccEmails": [], "createdAt": "...", "updatedAt": "..." },
      "transport": null,
      "pallets": 1,
      "totalBoxes": 3,
      "incoterm": null,
      "totalNetWeight": 37.5,
      "subtotalAmount": 562.5,
      "totalAmount": 562.5
    }
  ],
  "links": {
    "first": "...",
    "last": "...",
    "prev": null,
    "next": null
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 1,
    "path": "...",
    "per_page": 20,
    "to": 1,
    "total": 1
  }
}
```

### Respuestas error

**401:** Igual que en crear autoventa.

**403:** Si el usuario no tiene permiso para listar pedidos (p. ej. sin policy `viewAny`).

---

## 5. Ver detalle de una autoventa

### Request

```http
GET /api/v2/orders/{id}
```

**Headers:** `Authorization: Bearer {token}`, `X-Tenant: {subdomain}`

### Respuesta éxito (200 OK)

Mismo formato que el objeto `data` del **201** de crear autoventa (OrderDetailsResource): `id`, `orderType`, `customer`, `entryDate`, `loadDate`, `status`, `accountingNotes`, `plannedProductDetails`, `pallets` (con `boxes` y `state`), `totalNetWeight`, `totalBoxes`, `subTotalAmount`, `totalAmount`, etc.

```json
{
  "data": {
    "id": 42,
    "orderType": "autoventa",
    "buyerReference": null,
    "customer": { "id": 10, "name": "Cliente Ejemplo", "alias": "Cliente Nº 10", ... },
    "entryDate": "2026-02-18",
    "loadDate": "2026-02-18",
    "status": "pending",
    "accountingNotes": "Con factura\nEntrega en puerto",
    "plannedProductDetails": [ ... ],
    "pallets": [ { "id": 101, "state": { "id": 3, "name": "shipped" }, "boxes": [ ... ], "netWeight": 37.5, "numberOfBoxes": 3 } ],
    "totalNetWeight": 37.5,
    "totalBoxes": 3,
    "subTotalAmount": 562.5,
    "totalAmount": 562.5,
    ...
  }
}
```

### Respuestas error

**401:** No autenticado.  
**403:** No autorizado (el pedido no es del comercial).  
**404:** Pedido no encontrado.

---

## 6. Clientes: selector y creación rápida

### 6.1 Opciones de clientes (selector)

**Request**

```http
GET /api/v2/customers/options
```

**Headers:** `Authorization: Bearer {token}`, `X-Tenant: {subdomain}`

**Respuesta éxito (200 OK)**

Array de objetos con `id` y `name`. El backend ya filtra por comercial (solo sus clientes).

```json
[
  { "id": 10, "name": "Cliente Ejemplo" },
  { "id": 11, "name": "Otro Cliente" }
]
```

### 6.2 Crear cliente (creación rápida en autoventa)

Para el flujo “Nuevo cliente” en el paso 1 de autoventa, se puede enviar solo el nombre. El backend asigna el comercial del usuario.

**Request**

```http
POST /api/v2/customers
```

**Headers:** `Authorization: Bearer {token}`, `X-Tenant: {subdomain}`, `Content-Type: application/json`

**Body mínimo (creación rápida):**

```json
{
  "name": "Nombre del nuevo cliente"
}
```

Otros campos (`vatNumber`, `billing_address`, `emails`, etc.) son opcionales; para no ralentizar el flujo, el frontend puede enviar solo `name`.

**Respuesta éxito (201 Created)**

```json
{
  "message": "Cliente creado correctamente.",
  "data": {
    "id": 12,
    "name": "Nombre del nuevo cliente",
    "alias": "Cliente Nº 12",
    "vatNumber": null,
    "paymentTerm": null,
    "billingAddress": null,
    "shippingAddress": null,
    "transportationNotes": null,
    "productionNotes": null,
    "accountingNotes": null,
    "salesperson": { "id": 2, "name": "Comercial 1", ... },
    "emails": [],
    "ccEmails": [],
    "contactInfo": null,
    "country": null,
    "transport": null,
    "createdAt": "...",
    "updatedAt": "..."
  }
}
```

Tras crear, el frontend puede usar `data.id` como `customer` en el body de la autoventa.

**Respuesta error 422 (validación):**

```json
{
  "message": "Error de validación.",
  "userMessage": "El nombre del cliente es obligatorio.",
  "errors": {
    "name": ["El nombre del cliente es obligatorio."]
  }
}
```

---

## 7. Resumen de códigos HTTP y formato de error

| Código | Significado | Formato típico del cuerpo |
|--------|-------------|----------------------------|
| **200** | OK (listado, detalle, options) | `data` o array según endpoint |
| **201** | Created (crear pedido, crear cliente) | `message` + `data` |
| **401** | No autenticado | `{ "message": "No autenticado.", "userMessage": "..." }` |
| **403** | No autorizado | `{ "message": "...", "userMessage": "..." }` (o mensaje de policy) |
| **404** | Recurso no encontrado | `{ "message": "..." }` |
| **422** | Validación fallida | `{ "message": "Error de validación.", "userMessage": "...", "errors": { "campo": ["mensaje1", "mensaje2"] } }` |
| **500** | Error interno | `{ "message": "Error al crear el pedido", "userMessage": "...", "error": "detalle técnico" }` |

Para **422** y **500**, el frontend debe mostrar al usuario el contenido de `userMessage` (o los mensajes en `errors` por campo). No exponer el contenido de `error` de un **500** como mensaje principal al usuario.

---

## 8. Restricciones actualizadas (autoventa)

Resumen de qué campos **no** son obligatorios en el flujo autoventa para que el frontend no exija datos que el backend no usa.

### 8.1 Crear autoventa (POST /api/v2/orders con orderType: "autoventa")

Para crear una autoventa, el backend **no exige** ni utiliza:

- Condición de pago (`payment_term_id`)
- Transporte (`transport_id`)
- Dirección de facturación / envío
- Emails del pedido

El frontend no debe enviar estos campos en el body de autoventa (o puede omitirlos). Las columnas correspondientes en la tabla `orders` son nullable; no se introducen nuevos requisitos para el frontend.

### 8.2 Crear cliente en autoventa (POST /api/v2/customers — cliente in situ)

Para el flujo “Nuevo cliente” en el paso 1 de autoventa (registro in situ), el backend **solo exige el nombre** del cliente. El comercial se asigna automáticamente al usuario logueado.

Los siguientes campos son **opcionales** y pueden omitirse al crear el cliente desde autoventa:

- NIF/CIF (`vatNumber`)
- Dirección de facturación y de envío
- Emails y información de contacto
- País (`country_id`)
- Condición de pago (`payment_term_id`)
- Transporte (`transport_id`)

El frontend puede enviar solo `name` (y opcionalmente el resto si el usuario los rellena). Las columnas correspondientes en la tabla `customers` son nullable para reflejar este uso.

Ningún otro endpoint del flujo autoventa (listado, detalle, options) cambia; solo se aclara el contrato de creación de pedido y de cliente.

---

## 9. Referencias

- **Implementación backend**: [70-autoventa-backend-implementacion.md](70-autoventa-backend-implementacion.md)
- **Especificación funcional y flujo UI**: [69-autoventa-rol-comercial-especificacion.md](../por-hacer/69-autoventa-rol-comercial-especificacion.md)
