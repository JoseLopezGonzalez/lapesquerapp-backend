# API Documentation: Product Categories & Families

## 📋 Overview

This document provides comprehensive API documentation for the Product Categories and Families endpoints in PesquerApp v2.

**Base URL**: `https://api.pesquerapp.es/api/v2`  
**Authentication**: Bearer Token (Sanctum)  
**Content-Type**: `application/json`

## 🔐 Authentication

All endpoints require authentication via Bearer token in the header:

```http
Authorization: Bearer {your-token}
X-Tenant: {tenant-subdomain}
```

## 📊 Product Categories

### List Product Categories

Retrieves a paginated list of product categories.

```http
GET /product-categories
```

#### Query Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `id` | integer | No | Filter by specific category ID |
| `ids` | array | No | Filter by multiple category IDs |
| `name` | string | No | Search by name (LIKE query) |
| `active` | boolean | No | Filter by active status |
| `perPage` | integer | No | Items per page (default: 12) |

#### Example Request

```bash
curl -X GET "https://api.pesquerapp.es/api/v2/product-categories?name=fresco&active=true" \
  -H "Authorization: Bearer {token}" \
  -H "X-Tenant: brisamar"
```

#### Response

```json
{
  "data": [
    {
      "id": 1,
      "name": "Fresco",
      "description": "Productos frescos sin procesar",
      "active": true,
      "createdAt": "2025-08-08T08:08:05.000000Z",
      "updatedAt": "2025-08-08T08:08:05.000000Z"
    }
  ],
  "links": {
    "first": "https://api.pesquerapp.es/api/v2/product-categories?page=1",
    "last": "https://api.pesquerapp.es/api/v2/product-categories?page=1",
    "prev": null,
    "next": null
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 1,
    "per_page": 12,
    "to": 2,
    "total": 2
  }
}
```

### Get Product Category

Retrieves a specific product category by ID.

```http
GET /product-categories/{id}
```

#### Path Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `id` | integer | Yes | Category ID |

#### Example Request

```bash
curl -X GET "https://api.pesquerapp.es/api/v2/product-categories/1" \
  -H "Authorization: Bearer {token}" \
  -H "X-Tenant: brisamar"
```

#### Example Request

```bash
curl -X GET "https://api.pesquerapp.es/api/v2/product-categories/options" \
  -H "Authorization: Bearer {token}" \
  -H "X-Tenant: brisamar"
```

#### Response

```json
[
  {
    "id": 1,
    "name": "Fresco",
    "description": "Productos frescos sin procesar"
  },
  {
    "id": 2,
    "name": "Congelado",
    "description": "Productos congelados"
  }
]
```

#### Response

```json
{
  "message": "Categoría de producto obtenida con éxito",
  "data": {
    "id": 1,
    "name": "Fresco",
    "description": "Productos frescos sin procesar",
    "active": true,
    "createdAt": "2025-08-08T08:08:05.000000Z",
    "updatedAt": "2025-08-08T08:08:05.000000Z"
  }
}
```

### Create Product Category

Creates a new product category.

```http
POST /product-categories
```

#### Request Body

| Field | Type | Required | Validation | Description |
|-------|------|----------|------------|-------------|
| `name` | string | Yes | min:3, max:255 | Category name |
| `description` | string | No | max:1000 | Category description |
| `active` | boolean | No | - | Active status (default: true) |

#### Example Request

```bash
curl -X POST "https://api.pesquerapp.es/api/v2/product-categories" \
  -H "Authorization: Bearer {token}" \
  -H "X-Tenant: brisamar" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Ahumado",
    "description": "Productos ahumados",
    "active": true
  }'
```

#### Response

```json
{
  "message": "Categoría de producto creada con éxito",
  "data": {
    "id": 3,
    "name": "Ahumado",
    "description": "Productos ahumados",
    "active": true,
    "createdAt": "2025-08-08T10:30:00.000000Z",
    "updatedAt": "2025-08-08T10:30:00.000000Z"
  }
}
```

### Update Product Category

Updates an existing product category.

```http
PUT /product-categories/{id}
```

#### Path Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `id` | integer | Yes | Category ID |

#### Request Body

| Field | Type | Required | Validation | Description |
|-------|------|----------|------------|-------------|
| `name` | string | No | min:3, max:255 | Category name |
| `description` | string | No | max:1000 | Category description |
| `active` | boolean | No | - | Active status |

#### Example Request

```bash
curl -X PUT "https://api.pesquerapp.es/api/v2/product-categories/1" \
  -H "Authorization: Bearer {token}" \
  -H "X-Tenant: brisamar" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Fresco Actualizado",
    "description": "Descripción actualizada"
  }'
```

#### Response

```json
{
  "message": "Categoría de producto actualizada con éxito",
  "data": {
    "id": 1,
    "name": "Fresco Actualizado",
    "description": "Descripción actualizada",
    "active": true,
    "createdAt": "2025-08-08T08:08:05.000000Z",
    "updatedAt": "2025-08-08T10:35:00.000000Z"
  }
}
```

### Delete Product Category

Deletes a product category (only if no families are associated).

```http
DELETE /product-categories/{id}
```

### Delete Multiple Product Categories

Deletes multiple product categories (only if no families are associated).

```http
DELETE /product-categories
```

#### Request Body

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `ids` | array | Yes | Array of category IDs to delete |

#### Example Request

```bash
curl -X DELETE "https://api.pesquerapp.es/api/v2/product-categories" \
  -H "Authorization: Bearer {token}" \
  -H "X-Tenant: brisamar" \
  -H "Content-Type: application/json" \
  -d '{"ids": [1, 2, 3]}'
```

#### Response

```json
{
  "message": "Se eliminaron 2 categorías con éxito. Errores: Categoría 'Fresco' no se puede eliminar porque tiene familias asociadas",
  "deletedCount": 2,
  "errors": [
    "Categoría 'Fresco' no se puede eliminar porque tiene familias asociadas"
  ]
}
```

### Get Product Categories Options

Retrieves all active product categories for select boxes.

```http
GET /product-categories/options
```

#### Path Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `id` | integer | Yes | Category ID |

#### Example Request

```bash
curl -X DELETE "https://api.pesquerapp.es/api/v2/product-categories/3" \
  -H "Authorization: Bearer {token}" \
  -H "X-Tenant: brisamar"
```

#### Success Response

```json
{
  "message": "Categoría de producto eliminada con éxito"
}
```

#### Error Response (if families exist)

```json
{
  "message": "No se puede eliminar la categoría porque tiene familias asociadas"
}
```

## 👨‍👩‍👧‍👦 Product Families

### List Product Families

Retrieves a paginated list of product families.

```http
GET /product-families
```

#### Query Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `id` | integer | No | Filter by specific family ID |
| `ids` | array | No | Filter by multiple family IDs |
| `name` | string | No | Search by name (LIKE query) |
| `categoryId` | integer | No | Filter by category ID |
| `active` | boolean | No | Filter by active status |
| `perPage` | integer | No | Items per page (default: 12) |

#### Example Request

```bash
curl -X GET "https://api.pesquerapp.es/api/v2/product-families?categoryId=1&active=true" \
  -H "Authorization: Bearer {token}" \
  -H "X-Tenant: brisamar"
```

#### Response

```json
{
  "data": [
    {
      "id": 1,
      "name": "Fresco entero",
      "description": "Productos frescos enteros sin procesar",
      "categoryId": 1,
      "category": {
        "id": 1,
        "name": "Fresco",
        "description": "Productos frescos sin procesar",
        "active": true,
        "createdAt": "2025-08-08T08:08:05.000000Z",
        "updatedAt": "2025-08-08T08:08:05.000000Z"
      },
      "active": true,
      "createdAt": "2025-08-08T08:08:05.000000Z",
      "updatedAt": "2025-08-08T08:08:05.000000Z"
    }
  ],
  "links": {...},
  "meta": {...}
}
```

### Get Product Family

Retrieves a specific product family by ID.

```http
GET /product-families/{id}
```

#### Path Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `id` | integer | Yes | Family ID |

#### Example Request

```bash
curl -X GET "https://api.pesquerapp.es/api/v2/product-families/1" \
  -H "Authorization: Bearer {token}" \
  -H "X-Tenant: brisamar"
```

#### Example Request

```bash
curl -X GET "https://api.pesquerapp.es/api/v2/product-families/options" \
  -H "Authorization: Bearer {token}" \
  -H "X-Tenant: brisamar"
```

#### Response

```json
[
  {
    "id": 1,
    "name": "Fresco entero",
    "description": "Productos frescos enteros sin procesar",
    "categoryId": 1,
    "categoryName": "Fresco"
  },
  {
    "id": 2,
    "name": "Fresco eviscerado",
    "description": "Productos frescos eviscerados",
    "categoryId": 1,
    "categoryName": "Fresco"
  },
  {
    "id": 4,
    "name": "Congelado entero",
    "description": "Productos congelados enteros",
    "categoryId": 2,
    "categoryName": "Congelado"
  }
]
```

#### Response

```json
{
  "message": "Familia de producto obtenida con éxito",
  "data": {
    "id": 1,
    "name": "Fresco entero",
    "description": "Productos frescos enteros sin procesar",
    "categoryId": 1,
    "category": {
      "id": 1,
      "name": "Fresco",
      "description": "Productos frescos sin procesar",
      "active": true,
      "createdAt": "2025-08-08T08:08:05.000000Z",
      "updatedAt": "2025-08-08T08:08:05.000000Z"
    },
    "active": true,
    "createdAt": "2025-08-08T08:08:05.000000Z",
    "updatedAt": "2025-08-08T08:08:05.000000Z"
  }
}
```

### Create Product Family

Creates a new product family.

```http
POST /product-families
```

#### Request Body

| Field | Type | Required | Validation | Description |
|-------|------|----------|------------|-------------|
| `name` | string | Yes | min:3, max:255 | Family name |
| `description` | string | No | max:1000 | Family description |
| `categoryId` | integer | Yes | exists:tenant.product_categories,id | Category ID |
| `active` | boolean | No | - | Active status (default: true) |

#### Example Request

```bash
curl -X POST "https://api.pesquerapp.es/api/v2/product-families" \
  -H "Authorization: Bearer {token}" \
  -H "X-Tenant: brisamar" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Ahumado en frío",
    "description": "Productos ahumados en frío",
    "categoryId": 3,
    "active": true
  }'
```

#### Response

```json
{
  "message": "Familia de producto creada con éxito",
  "data": {
    "id": 9,
    "name": "Ahumado en frío",
    "description": "Productos ahumados en frío",
    "categoryId": 3,
    "category": {
      "id": 3,
      "name": "Ahumado",
      "description": "Productos ahumados",
      "active": true,
      "createdAt": "2025-08-08T10:30:00.000000Z",
      "updatedAt": "2025-08-08T10:30:00.000000Z"
    },
    "active": true,
    "createdAt": "2025-08-08T10:40:00.000000Z",
    "updatedAt": "2025-08-08T10:40:00.000000Z"
  }
}
```

### Update Product Family

Updates an existing product family.

```http
PUT /product-families/{id}
```

#### Path Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `id` | integer | Yes | Family ID |

#### Request Body

| Field | Type | Required | Validation | Description |
|-------|------|----------|------------|-------------|
| `name` | string | No | min:3, max:255 | Family name |
| `description` | string | No | max:1000 | Family description |
| `categoryId` | integer | No | exists:tenant.product_categories,id | Category ID |
| `active` | boolean | No | - | Active status |

#### Example Request

```bash
curl -X PUT "https://api.pesquerapp.es/api/v2/product-families/1" \
  -H "Authorization: Bearer {token}" \
  -H "X-Tenant: brisamar" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Fresco entero actualizado",
    "description": "Descripción actualizada"
  }'
```

#### Response

```json
{
  "message": "Familia de producto actualizada con éxito",
  "data": {
    "id": 1,
    "name": "Fresco entero actualizado",
    "description": "Descripción actualizada",
    "categoryId": 1,
    "category": {
      "id": 1,
      "name": "Fresco",
      "description": "Productos frescos sin procesar",
      "active": true,
      "createdAt": "2025-08-08T08:08:05.000000Z",
      "updatedAt": "2025-08-08T08:08:05.000000Z"
    },
    "active": true,
    "createdAt": "2025-08-08T08:08:05.000000Z",
    "updatedAt": "2025-08-08T10:45:00.000000Z"
  }
}
```

### Delete Product Family

Deletes a product family (only if no products are associated).

```http
DELETE /product-families/{id}
```

### Delete Multiple Product Families

Deletes multiple product families (only if no products are associated).

```http
DELETE /product-families
```

#### Request Body

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `ids` | array | Yes | Array of family IDs to delete |

#### Example Request

```bash
curl -X DELETE "https://api.pesquerapp.es/api/v2/product-families" \
  -H "Authorization: Bearer {token}" \
  -H "X-Tenant: brisamar" \
  -H "Content-Type: application/json" \
  -d '{"ids": [1, 2, 3]}'
```

#### Response

```json
{
  "message": "Se eliminaron 2 familias con éxito. Errores: Familia 'Fresco entero' no se puede eliminar porque tiene productos asociados",
  "deletedCount": 2,
  "errors": [
    "Familia 'Fresco entero' no se puede eliminar porque tiene productos asociados"
  ]
}
```

### Get Product Families Options

Retrieves all active product families for select boxes.

```http
GET /product-families/options
```

#### Path Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `id` | integer | Yes | Family ID |

#### Example Request

```bash
curl -X DELETE "https://api.pesquerapp.es/api/v2/product-families/9" \
  -H "Authorization: Bearer {token}" \
  -H "X-Tenant: brisamar"
```

#### Success Response

```json
{
  "message": "Familia de producto eliminada con éxito"
}
```

#### Error Response (if products exist)

```json
{
  "message": "No se puede eliminar la familia porque tiene productos asociados"
}
```

## 📦 Products (Updated)

### List Products with Category/Family Filters

The products endpoint now supports filtering by categories and families.

```http
GET /products
```

#### New Query Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `categories` | array | No | Filter by category IDs (comma-separated) |
| `families` | array | No | Filter by family IDs (comma-separated) |

#### Example Request

```bash
curl -X GET "https://api.pesquerapp.es/api/v2/products?categories=1,2&families=1,3,5" \
  -H "Authorization: Bearer {token}" \
  -H "X-Tenant: brisamar"
```

### Create Product with Category and Family

Products can now be created with category and family associations.

```http
POST /products
```

#### New Request Body Fields

| Field | Type | Required | Validation | Description |
|-------|------|----------|------------|-------------|
| `categoryId` | integer | No | exists:tenant.product_categories,id | Category ID |
| `familyId` | integer | No | exists:tenant.product_families,id | Family ID |

#### Example Request

```bash
curl -X POST "https://api.pesquerapp.es/api/v2/products" \
  -H "Authorization: Bearer {token}" \
  -H "X-Tenant: brisamar" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Pulpo Fresco Entero",
    "speciesId": 1,
    "captureZoneId": 1,
    "categoryId": 1,
    "familyId": 1,
    "articleGtin": "1234567890123",
    "boxGtin": "1234567890124",
    "palletGtin": "1234567890125"
  }'
```

#### Response

```json
{
  "message": "Producto creado con éxito",
  "data": {
    "id": 123,
    "name": "Pulpo Fresco Entero",
    "species": {
      "id": 1,
      "name": "Pulpo",
      "scientificName": "Octopus vulgaris",
      "fao": "OCT",
      "image": "pulpo.jpg"
    },
    "captureZone": {
      "id": 1,
      "name": "Atlántico Norte"
    },
    "category": {
      "id": 1,
      "name": "Fresco",
      "description": "Productos frescos sin procesar",
      "active": true
    },
    "family": {
      "id": 1,
      "name": "Fresco entero",
      "description": "Productos frescos enteros sin procesar",
      "categoryId": 1,
      "active": true
    },
    "articleGtin": "1234567890123",
    "boxGtin": "1234567890124",
    "palletGtin": "1234567890125"
  }
}
```

## 🚨 Error Responses

### Validation Errors

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "name": [
      "The name field is required."
    ],
    "categoryId": [
      "The selected category id is invalid."
    ]
  }
}
```

### Not Found Error

```json
{
  "message": "No query results for model [App\\Models\\ProductCategory] 999"
}
```

### Unauthorized Error

```json
{
  "message": "Unauthenticated."
}
```

### Tenant Not Found Error

```json
{
  "error": "Tenant not found or inactive"
}
```

## 📊 Status Codes

| Code | Description |
|------|-------------|
| 200 | Success |
| 201 | Created |
| 400 | Bad Request (Validation/Constraint errors) |
| 401 | Unauthorized |
| 404 | Not Found |
| 422 | Validation Error |
| 500 | Internal Server Error |

## 🔄 Rate Limiting

All endpoints are subject to rate limiting:
- **Authenticated users**: 60 requests per minute
- **Unauthenticated users**: 30 requests per minute

## 📝 Examples

### Complete Workflow

1. **Create Category**
```bash
curl -X POST "https://api.pesquerapp.es/api/v2/product-categories" \
  -H "Authorization: Bearer {token}" \
  -H "X-Tenant: brisamar" \
  -H "Content-Type: application/json" \
  -d '{"name": "En Conserva", "description": "Productos en conserva"}'
```

2. **Create Family**
```bash
curl -X POST "https://api.pesquerapp.es/api/v2/product-families" \
  -H "Authorization: Bearer {token}" \
  -H "X-Tenant: brisamar" \
  -H "Content-Type: application/json" \
  -d '{"name": "En aceite", "description": "Productos en aceite", "categoryId": 4}'
```

3. **Create Product**
```bash
curl -X POST "https://api.pesquerapp.es/api/v2/products" \
  -H "Authorization: Bearer {token}" \
  -H "X-Tenant: brisamar" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Atún en aceite",
    "speciesId": 2,
    "captureZoneId": 1,
    "categoryId": 4,
    "familyId": 10,
    "articleGtin": "9876543210987"
  }'
```

4. **Filter Products**
```bash
curl -X GET "https://api.pesquerapp.es/api/v2/products?categories=4&families=10" \
  -H "Authorization: Bearer {token}" \
  -H "X-Tenant: brisamar"
```

---

**API Version**: v2  
**Last Updated**: Agosto 2025  
**Documentation Version**: 1.0
