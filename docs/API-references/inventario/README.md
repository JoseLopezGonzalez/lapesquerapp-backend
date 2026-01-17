# Inventario

Documentación de endpoints para gestión de almacenes, palets y cajas.

## Índice

- [Almacenes (Stores)](#almacenes-stores)
- [Palets](#palets)
- [Cajas (Boxes)](#cajas-boxes)

---

## Almacenes (Stores)

### Listar Almacenes

```http
GET /api/v2/stores
```

#### Headers
```http
X-Tenant: {subdomain}
Authorization: Bearer {access_token}
```

#### Response Exitosa (200)

```json
{
  "data": [
    {
      "id": 1,
      "name": "Almacén Principal",
      "code": "ALM-001",
      "created_at": "2024-01-15T10:00:00.000000Z",
      "updated_at": "2024-01-15T10:00:00.000000Z"
    }
  ]
}
```

---

### Crear Almacén

```http
POST /api/v2/stores
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
  "name": "Almacén Principal",
  "code": "ALM-001"
}
```

#### Response Exitosa (201)

```json
{
  "data": {
    "id": 1,
    "name": "Almacén Principal",
    "code": "ALM-001",
    "created_at": "2024-01-15T10:00:00.000000Z"
  }
}
```

---

### Mostrar Almacén

```http
GET /api/v2/stores/{id}
```

#### Response Exitosa (200)

```json
{
  "data": {
    "id": 1,
    "name": "Almacén Principal",
    "code": "ALM-001"
  }
}
```

---

### Actualizar Almacén

```http
PUT /api/v2/stores/{id}
```

#### Request Body

```json
{
  "name": "Almacén Actualizado",
  "code": "ALM-001-UPD"
}
```

---

### Eliminar Almacén

```http
DELETE /api/v2/stores/{id}
```

#### Response Exitosa (200)

```json
{
  "message": "Almacén eliminado correctamente."
}
```

---

### Eliminar Múltiples Almacenes

```http
DELETE /api/v2/stores
```

#### Request Body

```json
{
  "ids": [1, 2, 3]
}
```

**Nota:** Requiere roles `superuser`, `manager` o `admin`.

---

### Opciones de Almacenes

```http
GET /api/v2/stores/options
```

#### Response Exitosa (200)

```json
[
  {
    "id": 1,
    "name": "Almacén Principal"
  }
]
```

---

### Stock Total por Productos

```http
GET /api/v2/stores/total-stock-by-products
```

#### Response Exitosa (200)

```json
{
  "data": [
    {
      "product_id": 1,
      "product_name": "Producto A",
      "total_quantity": 1500.50,
      "total_boxes": 100
    }
  ]
}
```

---

## Palets

### Listar Palets

```http
GET /api/v2/pallets
```

#### Query Parameters (Opcionales)

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| status | string | Estado del palet (`stored`, `shipping`, `shipped`) |
| store_id | integer | ID del almacén |
| order_id | integer | ID del pedido |
| lot | string | Número de lote |
| perPage | integer | Elementos por página |

#### Response Exitosa (200)

```json
{
  "data": [
    {
      "id": 1,
      "lot": "LOT-001",
      "status": "stored",
      "store": {
        "id": 1,
        "name": "Almacén Principal"
      },
      "created_at": "2024-01-15T10:00:00.000000Z"
    }
  ]
}
```

---

### Crear Palet

```http
POST /api/v2/pallets
```

#### Request Body

```json
{
  "lot": "LOT-001",
  "store_id": 1,
  "status": "stored"
}
```

---

### Mostrar Palet

```http
GET /api/v2/pallets/{id}
```

---

### Actualizar Palet

```http
PUT /api/v2/pallets/{id}
```

---

### Eliminar Palet

```http
DELETE /api/v2/pallets/{id}
```

---

### Eliminar Múltiples Palets

```http
DELETE /api/v2/pallets
```

#### Request Body

```json
{
  "ids": [1, 2, 3]
}
```

**Nota:** Requiere roles `superuser`, `manager` o `admin`.

---

### Palets Registrados

```http
GET /api/v2/pallets/registered
```

#### Headers
```http
X-Tenant: {subdomain}
Authorization: Bearer {access_token}
```

#### Response Exitosa (200)

```json
{
  "id": null,
  "name": "Palets Registrados",
  "temperature": null,
  "capacity": null,
  "netWeightPallets": 1500.50,
  "totalNetWeight": 1500.50,
  "content": {
    "pallets": [
      {
        "id": 1,
        "status": 1,
        "boxes": [...],
        "netWeight": 500.00
      }
    ],
    "boxes": [],
    "bigBoxes": []
  },
  "map": null
}
```

**Descripción:** Devuelve todos los palets con estado `registered` (registrados) con sus relaciones cargadas. El formato es similar a `StoreDetailsResource` para mantener consistencia en el frontend.

---

### Buscar por Lote

```http
GET /api/v2/pallets/search-by-lot?lot=LOT-001
```

#### Headers
```http
X-Tenant: {subdomain}
Authorization: Bearer {access_token}
```

#### Query Parameters (Requeridos)

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| lot | string | Número de lote a buscar |

#### Response Exitosa (200)

```json
{
  "data": {
    "pallets": [
      {
        "id": 1,
        "status": 1,
        "boxes": [
          {
            "id": 10,
            "lot": "LOT-001",
            "product": {...},
            "net_weight": 20.00,
            "isAvailable": true
          }
        ],
        "netWeight": 500.00
      }
    ],
    "total": 1,
    "totalBoxes": 5
  }
}
```

**Descripción:** Busca palets registrados que tengan cajas con el lote especificado y que estén disponibles (sin `productionInputs`). Solo retorna las cajas que coinciden con el lote.

#### Response Errónea (400) - Parámetro Faltante

```json
{
  "message": "El parámetro lot es requerido.",
  "userMessage": "Debe proporcionar el número de lote para buscar."
}
```

---

### Palets Disponibles para Pedido

```http
GET /api/v2/pallets/available-for-order
```

#### Headers
```http
X-Tenant: {subdomain}
Authorization: Bearer {access_token}
```

#### Query Parameters (Opcionales)

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| orderId | integer | ID del pedido (si se proporciona, incluye palets sin pedido O del mismo pedido) |
| id | string | Búsqueda por ID con coincidencias parciales |
| perPage | integer | Elementos por página (default: 20, max: 100) |

#### Response Exitosa (200)

```json
{
  "data": [
    {
      "id": 1,
      "status": 2,
      "boxes": [...],
      "storedPallet": {
        "store_id": 1,
        "position": "A-1"
      },
      "order_id": null
    }
  ],
  "current_page": 1,
  "last_page": 2,
  "per_page": 20,
  "total": 35
}
```

**Descripción:** Lista palets disponibles para vincular a un pedido. Solo incluye palets con estado `registered` o `stored`. Excluye palets vinculados a otros pedidos (a menos que se proporcione `orderId`, en cuyo caso permite incluir palets del mismo pedido).

---

### Asignar Palet a Posición

```http
POST /api/v2/pallets/assign-to-position
```

#### Request Body

```json
{
  "palet_id": 1,
  "position": "A-01-01"
}
```

---

### Mover Palet a Almacén

```http
POST /api/v2/pallets/move-to-store
```

#### Request Body

```json
{
  "palet_id": 1,
  "store_id": 2
}
```

---

### Mover Múltiples Palets a Almacén

```http
POST /api/v2/pallets/move-multiple-to-store
```

#### Request Body

```json
{
  "palet_ids": [1, 2, 3],
  "store_id": 2
}
```

---

### Desasignar Posición

```http
POST /api/v2/pallets/{id}/unassign-position
```

---

### Vincular Palet con Pedido

```http
POST /api/v2/pallets/{id}/link-order
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
| id | integer | ID del palet |

#### Request Body

```json
{
  "orderId": 1
}
```

#### Campos Requeridos

| Campo | Tipo | Descripción |
|-------|------|-------------|
| orderId | integer | ID del pedido |

#### Response Exitosa (200)

```json
{
  "message": "Palet vinculado correctamente al pedido",
  "pallet_id": 1,
  "order_id": 1,
  "pallet": {
    "id": 1,
    "status": 2,
    "order_id": 1
  }
}
```

#### Response Errónea (400) - Ya Vinculado a Otro Pedido

```json
{
  "error": "El palet #1 ya está vinculado al pedido #5. Debe desvincularlo primero."
}
```

---

### Vincular Múltiples Palets con Pedidos

```http
POST /api/v2/pallets/link-orders
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
  "pallets": [
    {
      "id": 1,
      "orderId": 1
    },
    {
      "id": 2,
      "orderId": 1
    }
  ]
}
```

#### Campos Requeridos

| Campo | Tipo | Descripción |
|-------|------|-------------|
| pallets | array | Array de objetos con `id` (palet) y `orderId` (pedido) |

#### Response Exitosa (200) - Sin Errores

```json
{
  "message": "Proceso de vinculación completado",
  "linked": 2,
  "already_linked": 0,
  "errors": 0,
  "results": [
    {
      "pallet_id": 1,
      "order_id": 1,
      "status": "linked",
      "message": "Palet vinculado correctamente"
    }
  ]
}
```

#### Response Parcial (207) - Con Errores

```json
{
  "message": "Proceso de vinculación completado",
  "linked": 1,
  "errors": 1,
  "errors_details": [
    {
      "pallet_id": 2,
      "order_id": 1,
      "error": "El palet #2 ya está vinculado al pedido #5"
    }
  ]
}
```

---

### Desvincular Palet de Pedido

```http
POST /api/v2/pallets/{id}/unlink-order
```

#### Headers
```http
X-Tenant: {subdomain}
Authorization: Bearer {access_token}
```

#### Path Parameters

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| id | integer | ID del palet |

#### Response Exitosa (200)

```json
{
  "message": "Palet desvinculado correctamente del pedido",
  "pallet_id": 1,
  "previous_order_id": 5,
  "pallet": {
    "id": 1,
    "status": 1,
    "order_id": null
  }
}
```

**Descripción:** Desvincula un palet de su pedido. Si el palet estaba `stored` o `shipped`, cambia a `registered` y elimina el almacenamiento.

---

### Desvincular Múltiples Palets de Pedidos

```http
POST /api/v2/pallets/unlink-orders
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
  "pallet_ids": [1, 2, 3]
}
```

#### Campos Requeridos

| Campo | Tipo | Descripción |
|-------|------|-------------|
| pallet_ids | array | Array de IDs de palets (mínimo: 1) |

#### Response Exitosa (200)

```json
{
  "message": "Proceso de desvinculación completado",
  "unlinked": 2,
  "already_unlinked": 1,
  "errors": 0,
  "results": [
    {
      "pallet_id": 1,
      "order_id": 5,
      "status": "unlinked",
      "message": "Palet desvinculado correctamente"
    }
  ]
}
```

**Descripción:** Desvincula múltiples palets de sus pedidos. Devuelve 207 si hay errores, 200 si todos fueron exitosos.

---

### Actualizar Estado Masivo de Palets

```http
POST /api/v2/pallets/update-state
```

**Nota:** Requiere roles `superuser`, `manager` o `admin`.

#### Request Body

```json
{
  "ids": [1, 2, 3],
  "status": "shipped"
}
```

---

## Cajas (Boxes)

### Listar Cajas

```http
GET /api/v2/boxes
```

---

### Crear Caja

```http
POST /api/v2/boxes
```

---

### Mostrar Caja

```http
GET /api/v2/boxes/{id}
```

---

### Actualizar Caja

```http
PUT /api/v2/boxes/{id}
```

---

### Eliminar Caja

```http
DELETE /api/v2/boxes/{id}
```

---

### Eliminar Múltiples Cajas

```http
DELETE /api/v2/boxes
```

#### Request Body

```json
{
  "ids": [1, 2, 3]
}
```

---

### Exportar Reporte de Cajas

```http
GET /api/v2/boxes/xlsx
```

**Nota:** Retorna un archivo Excel para descarga.

---

## Respuestas Erróneas Comunes

### Error de Validación (422)

```json
{
  "message": "Error de validación.",
  "userMessage": "El campo name es obligatorio.",
  "errors": {
    "name": ["The name field is required."]
  }
}
```

### Error de Solicitud Incorrecta (400)

```json
{
  "message": "No se han proporcionado IDs válidos.",
  "userMessage": "Debe proporcionar al menos un ID válido para eliminar."
}
```

### Error de Autenticación (401)

```json
{
  "message": "No autenticado."
}
```

### Error de Permisos (403)

```json
{
  "message": "No tiene permisos para realizar esta acción."
}
```

### Recurso No Encontrado (404)

```json
{
  "message": "Recurso no encontrado.",
  "userMessage": "El recurso especificado no existe."
}
```

