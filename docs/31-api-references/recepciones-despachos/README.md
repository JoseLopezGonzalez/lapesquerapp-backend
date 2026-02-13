# Recepciones y Despachos

Documentación de endpoints para gestión de recepciones de materia prima y despachos de cebo.

## Índice

- [Recepciones de Materia Prima](#recepciones-de-materia-prima)
- [Despachos de Cebo](#despachos-de-cebo)
- [Liquidaciones de Proveedores](#liquidaciones-de-proveedores)

---

## Recepciones de Materia Prima

### Listar Recepciones

```http
GET /api/v2/raw-material-receptions
```

#### Headers
```http
X-Tenant: {subdomain}
Authorization: Bearer {access_token}
```

#### Query Parameters (Opcionales)

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| id | integer | ID de recepción |
| ids | array | Array de IDs de recepciones |
| suppliers | array | Array de IDs de proveedores |
| dates | object | Filtro por fecha: `{start: "2024-01-01", end: "2024-12-31"}` |
| species | array | Array de IDs de especies |
| products | array | Array de IDs de productos |
| notes | string | Búsqueda parcial en notas |
| perPage | integer | Elementos por página (default: 12) |

#### Response Exitosa (200)

```json
{
  "data": [
    {
      "id": 1,
      "supplier": {
        "id": 1,
        "name": "Proveedor A"
      },
      "date": "2024-01-15",
      "notes": "Recepción de materia prima",
      "declared_total_amount": 5000.00,
      "declared_total_net_weight": 1000.00,
      "created_at": "2024-01-15T10:00:00.000000Z"
    }
  ],
  "current_page": 1,
  "last_page": 5,
  "per_page": 12,
  "total": 60
}
```

---

### Crear Recepción

```http
POST /api/v2/raw-material-receptions
```

#### Headers
```http
X-Tenant: {subdomain}
Authorization: Bearer {access_token}
Content-Type: application/json
```

#### Request Body - Modo Líneas (Automático)

```json
{
  "supplier": {
    "id": 1
  },
  "date": "2024-01-15",
  "notes": "Recepción de materia prima",
  "declaredTotalAmount": 5000.00,
  "declaredTotalNetWeight": 1000.00,
  "details": [
    {
      "product": {
        "id": 1
      },
      "netWeight": 500.00,
      "price": 5.00,
      "lot": "LOT-001",
      "boxes": 10
    }
  ]
}
```

#### Request Body - Modo Palets (Manual)

```json
{
  "supplier": {
    "id": 1
  },
  "date": "2024-01-15",
  "notes": "Recepción de materia prima",
  "declaredTotalAmount": 5000.00,
  "declaredTotalNetWeight": 1000.00,
  "pallets": [
    {
      "observations": "Palet en buen estado",
      "store": {
        "id": 1
      },
      "boxes": [
        {
          "product": {
            "id": 1
          },
          "lot": "LOT-001",
          "gs1128": "1234567890123",
          "grossWeight": 25.50,
          "netWeight": 20.00
        }
      ]
    }
  ],
  "prices": [
    {
      "product": {
        "id": 1
      },
      "lot": "LOT-001",
      "price": 5.00
    }
  ]
}
```

#### Campos Requeridos

| Campo | Tipo | Descripción |
|-------|------|-------------|
| supplier.id | integer | ID del proveedor |
| date | date | Fecha de recepción |

**Modo Líneas:**
- `details` (array) - Array de detalles de productos

**Modo Palets:**
- `pallets` (array) - Array de palets con cajas
- `prices` (array) - Array de precios por producto/lote

#### Response Exitosa (201)

```json
{
  "message": "Recepción de materia prima creada correctamente.",
  "data": {
    "id": 1,
    "supplier": {
      "id": 1,
      "name": "Proveedor A"
    },
    "date": "2024-01-15",
    "notes": "Recepción de materia prima",
    "creation_mode": "lines",
    "products": [...],
    "pallets": [...],
    "created_at": "2024-01-15T10:00:00.000000Z"
  }
}
```

---

### Mostrar Recepción

```http
GET /api/v2/raw-material-receptions/{id}
```

#### Response Exitosa (200)

```json
{
  "data": {
    "id": 1,
    "supplier": {
      "id": 1,
      "name": "Proveedor A"
    },
    "date": "2024-01-15",
    "notes": "Recepción de materia prima",
    "creation_mode": "lines",
    "products": [...],
    "pallets": [...],
    "created_at": "2024-01-15T10:00:00.000000Z"
  }
}
```

---

### Actualizar Recepción

```http
PUT /api/v2/raw-material-receptions/{id}
```

**Nota:** El formato del request body depende del `creation_mode` de la recepción:
- Si es `lines`: usar formato de líneas
- Si es `pallets`: usar formato de palets

#### Response Exitosa (200)

```json
{
  "message": "Recepción de materia prima actualizada correctamente.",
  "data": {
    "id": 1,
    "supplier": {
      "id": 1,
      "name": "Proveedor A"
    },
    "date": "2024-01-15",
    "notes": "Recepción de materia prima",
    "creation_mode": "lines",
    "products": [...],
    "pallets": [...],
    "created_at": "2024-01-15T10:00:00.000000Z",
    "updated_at": "2024-01-15T11:00:00.000000Z"
  }
}
```

---

### Eliminar Recepción

```http
DELETE /api/v2/raw-material-receptions/{id}
```

#### Response Exitosa (200)

```json
{
  "message": "Recepción eliminada correctamente."
}
```

---

### Eliminar Múltiples Recepciones

```http
DELETE /api/v2/raw-material-receptions
```

#### Request Body

```json
{
  "ids": [1, 2, 3]
}
```

#### Response Exitosa (200)

```json
{
  "message": "Recepciones eliminadas correctamente.",
  "deleted_count": 3
}
```

#### Response Errónea (400)

```json
{
  "message": "No se han proporcionado IDs válidos.",
  "userMessage": "Debe proporcionar al menos un ID válido para eliminar."
}
```

---

## Despachos de Cebo

### Listar Despachos

```http
GET /api/v2/cebo-dispatches
```

#### Headers
```http
X-Tenant: {subdomain}
Authorization: Bearer {access_token}
```

#### Query Parameters (Opcionales)

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| id | integer | ID de despacho |
| ids | array | Array de IDs de despachos |
| suppliers | array | Array de IDs de proveedores |
| dates | object | Filtro por fecha: `{start: "2024-01-01", end: "2024-12-31"}` |
| species | array | Array de IDs de especies |
| products | array | Array de IDs de productos |
| notes | string | Búsqueda parcial en notas |
| export_type | string | Tipo de exportación (`facilcom`, `a3erp`, `a3erp2`) |
| perPage | integer | Elementos por página (default: 12) |

#### Response Exitosa (200)

```json
{
  "data": [
    {
      "id": 1,
      "supplier": {
        "id": 1,
        "name": "Proveedor A"
      },
      "date": "2024-01-15",
      "notes": "Despacho de cebo",
      "export_type": "facilcom",
      "products": [
        {
          "id": 1,
          "product": {
            "id": 1,
            "name": "Producto A"
          },
          "net_weight": 500.00
        }
      ],
      "created_at": "2024-01-15T10:00:00.000000Z"
    }
  ],
  "current_page": 1,
  "last_page": 3,
  "per_page": 12,
  "total": 36
}
```

---

### Crear Despacho

```http
POST /api/v2/cebo-dispatches
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
  "supplier": {
    "id": 1
  },
  "date": "2024-01-15",
  "notes": "Despacho de cebo",
  "details": [
    {
      "product": {
        "id": 1
      },
      "netWeight": 500.00
    }
  ]
}
```

#### Campos Requeridos

| Campo | Tipo | Descripción |
|-------|------|-------------|
| supplier.id | integer | ID del proveedor |
| date | date | Fecha de despacho |
| details | array | Array de detalles de productos |

#### Campos de details

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| product.id | integer | Sí | ID del producto |
| netWeight | numeric | Sí | Peso neto |

#### Response Exitosa (201)

```json
{
  "message": "Despacho de cebo creado correctamente.",
  "data": {
    "id": 1,
    "supplier": {
      "id": 1,
      "name": "Proveedor A"
    },
    "date": "2024-01-15",
    "notes": "Despacho de cebo",
    "products": [
      {
        "id": 1,
        "product": {
          "id": 1,
          "name": "Producto A"
        },
        "net_weight": 500.00
      }
    ],
    "created_at": "2024-01-15T10:00:00.000000Z"
  }
}
```

---

### Mostrar Despacho

```http
GET /api/v2/cebo-dispatches/{id}
```

#### Response Exitosa (200)

```json
{
  "data": {
    "id": 1,
    "supplier": {
      "id": 1,
      "name": "Proveedor A"
    },
    "date": "2024-01-15",
    "notes": "Despacho de cebo",
    "products": [
      {
        "id": 1,
        "product": {
          "id": 1,
          "name": "Producto A"
        },
        "net_weight": 500.00
      }
    ],
    "created_at": "2024-01-15T10:00:00.000000Z"
  }
}
```

---

### Actualizar Despacho

```http
PUT /api/v2/cebo-dispatches/{id}
```

#### Request Body

```json
{
  "supplier": {
    "id": 1
  },
  "date": "2024-01-15",
  "notes": "Despacho actualizado",
  "details": [
    {
      "product": {
        "id": 1
      },
      "netWeight": 600.00
    }
  ]
}
```

**Nota:** Al actualizar, todos los productos existentes se eliminan y se recrean con los nuevos datos.

#### Response Exitosa (200)

```json
{
  "message": "Despacho de cebo actualizado correctamente.",
  "data": {
    "id": 1,
    "supplier": {
      "id": 1,
      "name": "Proveedor A"
    },
    "date": "2024-01-15",
    "notes": "Despacho actualizado",
    "products": [
      {
        "id": 1,
        "product": {
          "id": 1,
          "name": "Producto A"
        },
        "net_weight": 600.00
      }
    ],
    "created_at": "2024-01-15T10:00:00.000000Z",
    "updated_at": "2024-01-15T11:00:00.000000Z"
  }
}
```

---

### Eliminar Despacho

```http
DELETE /api/v2/cebo-dispatches/{id}
```

#### Response Exitosa (200)

```json
{
  "message": "Despacho de cebo eliminado correctamente"
}
```

#### Response Errónea (400) - Tiene Productos

```json
{
  "message": "No se puede eliminar el despacho porque tiene productos asociados",
  "details": "El despacho contiene productos que deben eliminarse primero"
}
```

---

### Eliminar Múltiples Despachos

```http
DELETE /api/v2/cebo-dispatches
```

#### Request Body

```json
{
  "ids": [1, 2, 3]
}
```

#### Response Exitosa (200)

```json
{
  "message": "Despachos de cebo eliminados correctamente"
}
```

#### Response Errónea (400) - Algunos Tienen Productos

```json
{
  "message": "No se pueden eliminar algunos despachos porque tienen productos asociados",
  "details": "Los despachos con IDs: 1, 2 tienen productos asociados"
}
```

---

## Liquidaciones de Proveedores

### Listar Proveedores con Actividad

```http
GET /api/v2/supplier-liquidations/suppliers
```

#### Headers
```http
X-Tenant: {subdomain}
Authorization: Bearer {access_token}
```

#### Query Parameters (Requeridos)

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| dates.start | date | Fecha de inicio (YYYY-MM-DD) |
| dates.end | date | Fecha de fin (YYYY-MM-DD) |

#### Response Exitosa (200)

```json
{
  "data": [
    {
      "id": 1,
      "name": "Proveedor A",
      "receptions_count": 5,
      "dispatches_count": 3,
      "total_receptions_weight": 5000.00,
      "total_dispatches_weight": 2000.00,
      "total_receptions_amount": 25000.00,
      "total_dispatches_amount": 10000.00
    }
  ]
}
```

---

### Obtener Liquidación Detallada

```http
GET /api/v2/supplier-liquidations/{supplierId}/details
```

#### Headers
```http
X-Tenant: {subdomain}
Authorization: Bearer {access_token}
```

#### Path Parameters

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| supplierId | integer | ID del proveedor |

#### Query Parameters (Requeridos)

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| dates.start | date | Fecha de inicio (YYYY-MM-DD) |
| dates.end | date | Fecha de fin (YYYY-MM-DD) |

#### Response Exitosa (200)

```json
{
  "supplier": {
    "id": 1,
    "name": "Proveedor A",
    "contact_person": "Juan Pérez",
    "phone": "123456789",
    "address": "Calle Principal 123"
  },
  "date_range": {
    "start": "2024-01-01",
    "end": "2024-01-31"
  },
  "receptions": [
    {
      "id": 1,
      "date": "2024-01-15",
      "notes": "Recepción",
      "products": [...],
      "calculated_total_net_weight": 1000.00,
      "calculated_total_amount": 5000.00,
      "declared_total_net_weight": 950.00,
      "declared_total_amount": 4750.00,
      "related_dispatches": [...]
    }
  ],
  "dispatches": [
    {
      "id": 1,
      "date": "2024-01-16",
      "export_type": "facilcom",
      "iva_rate": 0,
      "products": [...],
      "total_net_weight": 500.00,
      "base_amount": 2500.00,
      "iva_amount": 0.00,
      "total_amount": 2500.00
    }
  ],
  "summary": {
    "total_receptions": 5,
    "total_dispatches": 3,
    "total_receptions_weight": 5000.00,
    "total_receptions_amount": 25000.00,
    "total_dispatches_weight": 2000.00,
    "total_dispatches_base_amount": 10000.00,
    "total_dispatches_iva_amount": 0.00,
    "total_dispatches_amount": 10000.00,
    "total_declared_weight": 4750.00,
    "total_declared_amount": 23750.00,
    "total_declared_with_iva": 26125.00,
    "weight_difference": 250.00,
    "amount_difference": 1250.00,
    "percentage_not_declared": 5.00,
    "net_amount": 1250.00,
    "has_iva_in_dispatches": false
  }
}
```

---

### Generar PDF de Liquidación

```http
GET /api/v2/supplier-liquidations/{supplierId}/pdf
```

#### Headers
```http
X-Tenant: {subdomain}
Authorization: Bearer {access_token}
```

#### Path Parameters

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| supplierId | integer | ID del proveedor |

#### Query Parameters (Requeridos)

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| dates.start | date | Fecha de inicio (YYYY-MM-DD) |
| dates.end | date | Fecha de fin (YYYY-MM-DD) |

#### Query Parameters (Opcionales)

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| receptions | array | Array de IDs de recepciones a incluir |
| dispatches | array | Array de IDs de despachos a incluir |
| payment_method | string | Método de pago: `cash` o `transfer` |
| has_management_fee | boolean | Incluir gasto de gestión (2.5%) |
| show_transfer_payment | boolean | Mostrar información de transferencia (default: true) |

#### Response Exitosa (200)

**Content-Type:** `application/pdf`

**Content-Disposition:** `attachment; filename="Liquidacion_Proveedor_{nombre}_{fecha_inicio}_{fecha_fin}.pdf"`

Retorna un archivo PDF con la liquidación del proveedor.

---

## Respuestas Erróneas

### Error de Validación (422)

```json
{
  "message": "Error de validación.",
  "userMessage": "El campo supplier.id es obligatorio.",
  "errors": {
    "supplier.id": ["The supplier id field is required."],
    "date": ["The date field is required."]
  }
}
```

### Error de Autenticación (401)

```json
{
  "message": "No autenticado."
}
```

### Recurso No Encontrado (404)

```json
{
  "message": "Recepción no encontrada.",
  "userMessage": "La recepción especificada no existe."
}
```

