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

### Opciones de Palets

```http
GET /api/v2/pallets/options
```

---

### Palets Almacenados

```http
GET /api/v2/pallets/stored-options
```

---

### Palets Enviados

```http
GET /api/v2/pallets/shipped-options
```

---

### Palets Registrados

```http
GET /api/v2/pallets/registered
```

---

### Buscar por Lote

```http
GET /api/v2/pallets/search-by-lot?lot=LOT-001
```

---

### Palets Disponibles para Pedido

```http
GET /api/v2/pallets/available-for-order
```

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

#### Request Body

```json
{
  "order_id": 1
}
```

---

### Vincular Múltiples Palets con Pedidos

```http
POST /api/v2/pallets/link-orders
```

#### Request Body

```json
{
  "palet_ids": [1, 2, 3],
  "order_id": 1
}
```

---

### Desvincular Palet de Pedido

```http
POST /api/v2/pallets/{id}/unlink-order
```

---

### Desvincular Múltiples Palets de Pedidos

```http
POST /api/v2/pallets/unlink-orders
```

#### Request Body

```json
{
  "palet_ids": [1, 2, 3],
  "order_id": 1
}
```

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

### Cajas Disponibles

```http
GET /api/v2/boxes/available
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

