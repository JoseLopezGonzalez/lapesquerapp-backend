# Costos de Producción

Documentación de endpoints para gestión de catálogo de costos y costos de producción.

## Índice

- [Catálogo de Costos](#catálogo-de-costos)
- [Costos de Producción](#costos-de-producción)

---

## Catálogo de Costos

### Listar Catálogo de Costos

```http
GET /api/v2/cost-catalog
```

#### Headers
```http
X-Tenant: {subdomain}
Authorization: Bearer {access_token}
```

#### Query Parameters (Opcionales)

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| cost_type | string | Tipo de coste: `production`, `labor`, `operational`, `packaging` |
| active_only | boolean | Solo costes activos (default: true) |
| perPage | integer | Elementos por página (default: 15) |

#### Response Exitosa (200)

```json
{
  "message": "Catálogo de costes obtenido correctamente.",
  "data": {
    "data": [
      {
        "id": 1,
        "name": "Mano de Obra",
        "cost_type": "labor",
        "description": "Coste de mano de obra",
        "default_unit": "total",
        "is_active": true,
        "created_at": "2024-01-15T10:00:00.000000Z"
      }
    ],
    "current_page": 1,
    "last_page": 2,
    "per_page": 15,
    "total": 20
  }
}
```

---

### Crear Coste en Catálogo

```http
POST /api/v2/cost-catalog
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
  "name": "Mano de Obra",
  "cost_type": "labor",
  "description": "Coste de mano de obra",
  "default_unit": "total",
  "is_active": true
}
```

#### Campos Requeridos

| Campo | Tipo | Descripción |
|-------|------|-------------|
| name | string | Nombre del coste (único, máximo 255 caracteres) |
| cost_type | string | Tipo de coste: `production`, `labor`, `operational`, `packaging` |

#### Campos Opcionales

| Campo | Tipo | Descripción |
|-------|------|-------------|
| description | string | Descripción del coste |
| default_unit | string | Unidad por defecto: `total` o `per_kg` |
| is_active | boolean | Si el coste está activo (default: true) |

#### Response Exitosa (201)

```json
{
  "message": "Coste agregado al catálogo correctamente.",
  "data": {
    "id": 1,
    "name": "Mano de Obra",
    "cost_type": "labor",
    "description": "Coste de mano de obra",
    "default_unit": "total",
    "is_active": true,
    "created_at": "2024-01-15T10:00:00.000000Z"
  }
}
```

---

### Mostrar Coste del Catálogo

```http
GET /api/v2/cost-catalog/{id}
```

#### Response Exitosa (200)

```json
{
  "message": "Coste del catálogo obtenido correctamente.",
  "data": {
    "id": 1,
    "name": "Mano de Obra",
    "cost_type": "labor",
    "description": "Coste de mano de obra",
    "default_unit": "total",
    "is_active": true
  }
}
```

---

### Actualizar Coste del Catálogo

```http
PUT /api/v2/cost-catalog/{id}
```

#### Request Body

```json
{
  "name": "Mano de Obra Actualizada",
  "cost_type": "labor",
  "description": "Descripción actualizada",
  "is_active": false
}
```

#### Response Exitosa (200)

```json
{
  "message": "Coste del catálogo actualizado correctamente.",
  "data": {
    "id": 1,
    "name": "Mano de Obra Actualizada",
    "updated_at": "2024-01-15T11:00:00.000000Z"
  }
}
```

---

### Eliminar Coste del Catálogo

```http
DELETE /api/v2/cost-catalog/{id}
```

#### Response Exitosa (200)

```json
{
  "message": "Coste del catálogo eliminado correctamente."
}
```

---

## Costos de Producción

### Listar Costos de Producción

```http
GET /api/v2/production-costs
```

#### Headers
```http
X-Tenant: {subdomain}
Authorization: Bearer {access_token}
```

#### Query Parameters (Opcionales)

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| production_record_id | integer | Filtrar por registro de producción |
| production_id | integer | Filtrar por lote de producción |
| cost_type | string | Tipo de coste: `production`, `labor`, `operational`, `packaging` |
| perPage | integer | Elementos por página (default: 15) |

#### Response Exitosa (200)

```json
{
  "message": "Costes obtenidos correctamente.",
  "data": {
    "data": [
      {
        "id": 1,
        "production_record_id": 1,
        "production_id": null,
        "cost_catalog_id": 1,
        "cost_type": "labor",
        "name": "Mano de Obra",
        "description": "Coste de mano de obra",
        "total_cost": 500.00,
        "cost_per_kg": null,
        "distribution_unit": "total",
        "cost_date": "2024-01-15",
        "created_at": "2024-01-15T10:00:00.000000Z"
      }
    ],
    "current_page": 1,
    "last_page": 2,
    "per_page": 15,
    "total": 20
  }
}
```

---

### Crear Coste de Producción

```http
POST /api/v2/production-costs
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
  "production_record_id": 1,
  "cost_catalog_id": 1,
  "total_cost": 500.00,
  "cost_date": "2024-01-15",
  "description": "Coste de mano de obra"
}
```

**O alternativamente:**

```json
{
  "production_id": 1,
  "cost_type": "labor",
  "name": "Mano de Obra",
  "cost_per_kg": 2.50,
  "cost_date": "2024-01-15"
}
```

#### Campos Requeridos

**Debe especificarse uno de:**
- `production_record_id` (integer) - ID del registro de producción
- `production_id` (integer) - ID del lote de producción

**Y uno de:**
- `cost_catalog_id` (integer) - ID del coste del catálogo
- `cost_type` (string) + `name` (string) - Tipo y nombre del coste

**Y uno de:**
- `total_cost` (numeric) - Coste total
- `cost_per_kg` (numeric) - Coste por kilogramo

#### Campos Opcionales

| Campo | Tipo | Descripción |
|-------|------|-------------|
| description | string | Descripción del coste |
| distribution_unit | string | Unidad de distribución |
| cost_date | date | Fecha del coste |

#### Response Exitosa (201)

```json
{
  "message": "Coste creado correctamente.",
  "data": {
    "id": 1,
    "production_record_id": 1,
    "cost_catalog_id": 1,
    "total_cost": 500.00,
    "cost_date": "2024-01-15",
    "created_at": "2024-01-15T10:00:00.000000Z"
  }
}
```

#### Response Errónea (422) - Validación

```json
{
  "message": "Debe especificarse production_record_id o production_id.",
  "userMessage": "Debe especificarse production_record_id o production_id."
}
```

```json
{
  "message": "Se debe especificar O bien total_cost O bien cost_per_kg.",
  "userMessage": "Se debe especificar O bien total_cost O bien cost_per_kg."
}
```

---

### Mostrar Coste de Producción

```http
GET /api/v2/production-costs/{id}
```

#### Response Exitosa (200)

```json
{
  "message": "Coste obtenido correctamente.",
  "data": {
    "id": 1,
    "production_record": {
      "id": 1,
      "process": {
        "id": 1,
        "name": "Proceso A"
      }
    },
    "cost_catalog": {
      "id": 1,
      "name": "Mano de Obra"
    },
    },
    "total_cost": 500.00,
    "cost_date": "2024-01-15"
  }
}
```

---

### Actualizar Coste de Producción

```http
PUT /api/v2/production-costs/{id}
```

#### Request Body

```json
{
  "total_cost": 600.00,
  "description": "Coste actualizado"
}
```

#### Response Exitosa (200)

```json
{
  "message": "Coste actualizado correctamente.",
  "data": {
    "id": 1,
    "total_cost": 600.00,
    "updated_at": "2024-01-15T11:00:00.000000Z"
  }
}
```

---

### Eliminar Coste de Producción

```http
DELETE /api/v2/production-costs/{id}
```

#### Response Exitosa (200)

```json
{
  "message": "Coste eliminado correctamente."
}
```

---

## Respuestas Erróneas

### Error de Validación (422)

```json
{
  "message": "Error de validación.",
  "userMessage": "El campo name es obligatorio.",
  "errors": {
    "name": ["The name field is required."],
    "cost_type": ["The selected cost type is invalid."]
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
  "message": "Coste no encontrado.",
  "userMessage": "El coste especificado no existe."
}
```

