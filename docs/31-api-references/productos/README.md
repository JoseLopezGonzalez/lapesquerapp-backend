# Productos (Products)

Documentación de endpoints para gestión de productos, categorías y familias.

## Índice

### Productos
- [Listar Productos](#listar-productos)
- [Crear Producto](#crear-producto)
- [Mostrar Producto](#mostrar-producto)
- [Actualizar Producto](#actualizar-producto)
- [Eliminar Producto](#eliminar-producto)
- [Eliminar Múltiples Productos](#eliminar-múltiples-productos)
- [Opciones de Productos](#opciones-de-productos)

### Categorías de Productos
- [Listar Categorías](#listar-categorías)
- [Crear Categoría](#crear-categoría)
- [Actualizar Categoría](#actualizar-categoría)
- [Eliminar Categoría](#eliminar-categoría)
- [Eliminar Múltiples Categorías](#eliminar-múltiples-categorías)
- [Opciones de Categorías](#opciones-de-categorías)

### Familias de Productos
- [Listar Familias](#listar-familias)
- [Crear Familia](#crear-familia)
- [Actualizar Familia](#actualizar-familia)
- [Eliminar Familia](#eliminar-familia)
- [Eliminar Múltiples Familias](#eliminar-múltiples-familias)
- [Opciones de Familias](#opciones-de-familias)

---

## Listar Productos

Obtener lista de productos con filtros opcionales.

### Request

```http
GET /api/v2/products
```

#### Headers
```http
X-Tenant: {subdomain}
Authorization: Bearer {access_token}
```

#### Query Parameters (Opcionales)

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| name | string | Búsqueda parcial por nombre |
| speciesId | integer | ID de especie |
| captureZoneId | integer | ID de zona de captura |
| familyId | integer | ID de familia |
| articleGtin | string | Código GTIN del artículo |
| boxGtin | string | Código GTIN de la caja |
| palletGtin | string | Código GTIN del palet |
| perPage | integer | Elementos por página (default: 14) |

### Response Exitosa (200)

```json
{
  "data": [
    {
      "id": 1,
      "name": "Producto A",
      "species": {
        "id": 1,
        "name": "Especie A"
      },
      "capture_zone": {
        "id": 1,
        "name": "Zona A"
      },
      "family": {
        "id": 1,
        "name": "Familia A",
        "category": {
          "id": 1,
          "name": "Categoría A"
        }
      },
      "article_gtin": "1234567890123",
      "box_gtin": "1234567890124",
      "pallet_gtin": "1234567890125",
      "a3erp_code": "A3ERP-001",
      "facil_com_code": "FACIL-001",
      "created_at": "2024-01-15T10:00:00.000000Z",
      "updated_at": "2024-01-15T10:00:00.000000Z"
    }
  ],
  "current_page": 1,
  "last_page": 5,
  "per_page": 14,
  "total": 70
}
```

---

## Crear Producto

Crear un nuevo producto.

### Request

```http
POST /api/v2/products
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
  "name": "Producto A",
  "speciesId": 1,
  "captureZoneId": 1,
  "familyId": 1,
  "articleGtin": "1234567890123",
  "boxGtin": "1234567890124",
  "palletGtin": "1234567890125",
  "a3erp_code": "A3ERP-001",
  "facil_com_code": "FACIL-001"
}
```

#### Campos Requeridos

| Campo | Tipo | Descripción |
|-------|------|-------------|
| name | string | Nombre del producto (mínimo 3 caracteres, máximo 255) |
| speciesId | integer | ID de la especie (debe existir) |
| captureZoneId | integer | ID de la zona de captura (debe existir) |

#### Campos Opcionales

| Campo | Tipo | Descripción |
|-------|------|-------------|
| familyId | integer | ID de la familia |
| articleGtin | string | Código GTIN del artículo (8-14 dígitos) |
| boxGtin | string | Código GTIN de la caja (8-14 dígitos) |
| palletGtin | string | Código GTIN del palet (8-14 dígitos) |
| a3erp_code | string | Código A3ERP (máximo 255 caracteres) |
| facil_com_code | string | Código Facilcom (máximo 255 caracteres) |

### Response Exitosa (201)

```json
{
  "message": "Producto creado con éxito",
  "data": {
    "id": 1,
    "name": "Producto A",
    "species": {
      "id": 1,
      "name": "Especie A"
    },
    "capture_zone": {
      "id": 1,
      "name": "Zona A"
    },
    "family": {
      "id": 1,
      "name": "Familia A"
    },
    "article_gtin": "1234567890123",
    "box_gtin": "1234567890124",
    "pallet_gtin": "1234567890125",
    "created_at": "2024-01-15T10:00:00.000000Z",
    "updated_at": "2024-01-15T10:00:00.000000Z"
  }
}
```

### Response Errónea (422) - Error de Validación

```json
{
  "message": "Error de validación.",
  "errors": {
    "name": ["The name field is required."],
    "speciesId": ["The species id field is required."],
    "captureZoneId": ["The capture zone id field is required."],
    "articleGtin": ["The article gtin must be 8 to 14 digits."]
  }
}
```

---

## Mostrar Producto

Obtener información detallada de un producto específico.

### Request

```http
GET /api/v2/products/{id}
```

#### Headers
```http
X-Tenant: {subdomain}
Authorization: Bearer {access_token}
```

### Response Exitosa (200)

```json
{
  "message": "Producto obtenido con éxito",
  "data": {
    "id": 1,
    "name": "Producto A",
    "species": {
      "id": 1,
      "name": "Especie A"
    },
    "capture_zone": {
      "id": 1,
      "name": "Zona A"
    },
    "family": {
      "id": 1,
      "name": "Familia A",
      "category": {
        "id": 1,
        "name": "Categoría A"
      }
    },
    "article_gtin": "1234567890123",
    "box_gtin": "1234567890124",
    "pallet_gtin": "1234567890125",
    "created_at": "2024-01-15T10:00:00.000000Z",
    "updated_at": "2024-01-15T10:00:00.000000Z"
  }
}
```

---

## Actualizar Producto

Actualizar un producto existente.

### Request

```http
PUT /api/v2/products/{id}
```

#### Headers
```http
X-Tenant: {subdomain}
Authorization: Bearer {access_token}
Content-Type: application/json
```

#### Request Body

Mismos campos que [Crear Producto](#crear-producto), todos requeridos.

### Response Exitosa (200)

```json
{
  "message": "Producto actualizado con éxito",
  "data": {
    "id": 1,
    "name": "Producto Actualizado",
    "updated_at": "2024-01-15T11:00:00.000000Z"
  }
}
```

---

## Eliminar Producto

Eliminar un producto.

### Request

```http
DELETE /api/v2/products/{id}
```

#### Headers
```http
X-Tenant: {subdomain}
Authorization: Bearer {access_token}
```

### Response Exitosa (200)

```json
{
  "message": "Producto eliminado correctamente."
}
```

---

## Eliminar Múltiples Productos

Eliminar varios productos a la vez.

### Request

```http
DELETE /api/v2/products
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
  "ids": [1, 2, 3]
}
```

### Response Exitosa (200)

```json
{
  "message": "Productos eliminados correctamente.",
  "deleted_count": 3
}
```

---

## Opciones de Productos

Obtener lista de productos en formato opciones (para dropdowns).

### Request

```http
GET /api/v2/products/options
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
    "name": "Producto A"
  },
  {
    "id": 2,
    "name": "Producto B"
  }
]
```

---

## Listar Categorías

### Request

```http
GET /api/v2/product-categories
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
      "name": "Categoría A",
      "created_at": "2024-01-15T10:00:00.000000Z",
      "updated_at": "2024-01-15T10:00:00.000000Z"
    }
  ]
}
```

---

## Crear Categoría

### Request

```http
POST /api/v2/product-categories
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
  "name": "Categoría A"
}
```

#### Campos Requeridos

| Campo | Tipo | Descripción |
|-------|------|-------------|
| name | string | Nombre de la categoría |

### Response Exitosa (201)

```json
{
  "data": {
    "id": 1,
    "name": "Categoría A",
    "created_at": "2024-01-15T10:00:00.000000Z",
    "updated_at": "2024-01-15T10:00:00.000000Z"
  }
}
```

---

## Actualizar Categoría

### Request

```http
PUT /api/v2/product-categories/{id}
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
  "name": "Categoría Actualizada"
}
```

### Response Exitosa (200)

```json
{
  "data": {
    "id": 1,
    "name": "Categoría Actualizada",
    "updated_at": "2024-01-15T11:00:00.000000Z"
  }
}
```

---

## Eliminar Categoría

### Request

```http
DELETE /api/v2/product-categories/{id}
```

#### Headers
```http
X-Tenant: {subdomain}
Authorization: Bearer {access_token}
```

### Response Exitosa (200)

```json
{
  "message": "Categoría eliminada correctamente."
}
```

---

## Eliminar Múltiples Categorías

### Request

```http
DELETE /api/v2/product-categories
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
  "ids": [1, 2, 3]
}
```

---

## Opciones de Categorías

### Request

```http
GET /api/v2/product-categories/options
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
    "name": "Categoría A"
  }
]
```

---

## Listar Familias

### Request

```http
GET /api/v2/product-families
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
      "name": "Familia A",
      "category": {
        "id": 1,
        "name": "Categoría A"
      },
      "created_at": "2024-01-15T10:00:00.000000Z",
      "updated_at": "2024-01-15T10:00:00.000000Z"
    }
  ]
}
```

---

## Crear Familia

### Request

```http
POST /api/v2/product-families
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
  "name": "Familia A",
  "category_id": 1
}
```

#### Campos Requeridos

| Campo | Tipo | Descripción |
|-------|------|-------------|
| name | string | Nombre de la familia |
| category_id | integer | ID de la categoría |

### Response Exitosa (201)

```json
{
  "data": {
    "id": 1,
    "name": "Familia A",
    "category_id": 1,
    "created_at": "2024-01-15T10:00:00.000000Z",
    "updated_at": "2024-01-15T10:00:00.000000Z"
  }
}
```

---

## Actualizar Familia

### Request

```http
PUT /api/v2/product-families/{id}
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
  "name": "Familia Actualizada",
  "category_id": 1
}
```

### Response Exitosa (200)

```json
{
  "data": {
    "id": 1,
    "name": "Familia Actualizada",
    "category_id": 1,
    "updated_at": "2024-01-15T11:00:00.000000Z"
  }
}
```

---

## Eliminar Familia

### Request

```http
DELETE /api/v2/product-families/{id}
```

#### Headers
```http
X-Tenant: {subdomain}
Authorization: Bearer {access_token}
```

### Response Exitosa (200)

```json
{
  "message": "Familia eliminada correctamente."
}
```

---

## Eliminar Múltiples Familias

### Request

```http
DELETE /api/v2/product-families
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
  "ids": [1, 2, 3]
}
```

---

## Opciones de Familias

### Request

```http
GET /api/v2/product-families/options
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
    "name": "Familia A"
  }
]
```

