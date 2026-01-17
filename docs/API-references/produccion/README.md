# Producción

Documentación de endpoints para gestión de producción: lotes, registros, entradas, salidas y costos.

## Índice

- [Lotes de Producción](#lotes-de-producción)
- [Registros de Producción](#registros-de-producción)
- [Entradas de Producción](#entradas-de-producción)
- [Salidas de Producción](#salidas-de-producción)
- [Consumos de Salidas](#consumos-de-salidas)
- [Procesos](#procesos)

---

## Lotes de Producción

### Listar Lotes

```http
GET /api/v2/productions
```

#### Headers
```http
X-Tenant: {subdomain}
Authorization: Bearer {access_token}
```

#### Query Parameters (Opcionales)

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| lot | string | Número de lote |
| species_id | integer | ID de especie |
| status | string | Estado del lote |
| perPage | integer | Elementos por página (default: 15) |

#### Response Exitosa (200)

```json
{
  "data": [
    {
      "id": 1,
      "lot": "LOT-001",
      "species_id": 1,
      "status": "in_progress",
      "created_at": "2024-01-15T10:00:00.000000Z"
    }
  ],
  "current_page": 1,
  "last_page": 3,
  "per_page": 15,
  "total": 45
}
```

---

### Crear Lote

```http
POST /api/v2/productions
```

#### Request Body

```json
{
  "lot": "LOT-001",
  "species_id": 1,
  "description": "Lote de producción principal"
}
```

#### Response Exitosa (201)

```json
{
  "message": "Producción creada correctamente.",
  "data": {
    "id": 1,
    "lot": "LOT-001",
    "species_id": 1,
    "status": "pending",
    "created_at": "2024-01-15T10:00:00.000000Z"
  }
}
```

---

### Mostrar Lote

```http
GET /api/v2/productions/{id}
```

#### Response Exitosa (200)

```json
{
  "message": "Producción obtenida correctamente.",
  "data": {
    "id": 1,
    "lot": "LOT-001",
    "species": {
      "id": 1,
      "name": "Especie A"
    },
    "status": "in_progress",
    "reconciliation": {
      "total_input": 1000.00,
      "total_output": 950.00,
      "difference": 50.00
    },
    "created_at": "2024-01-15T10:00:00.000000Z"
  }
}
```

---

### Actualizar Lote

```http
PUT /api/v2/productions/{id}
```

---

### Eliminar Lote

```http
DELETE /api/v2/productions/{id}
```

---

### Eliminar Múltiples Lotes

```http
DELETE /api/v2/productions
```

#### Request Body

```json
{
  "ids": [1, 2, 3]
}
```

---

### Obtener Diagrama de Producción

```http
GET /api/v2/productions/{id}/diagram
```

#### Response Exitosa (200)

```json
{
  "data": {
    "nodes": [...],
    "edges": [...]
  }
}
```

---

### Obtener Árbol de Procesos

```http
GET /api/v2/productions/{id}/process-tree
```

---

### Obtener Totales

```http
GET /api/v2/productions/{id}/totals
```

---

### Obtener Reconciliación

```http
GET /api/v2/productions/{id}/reconciliation
```

---

### Obtener Productos Disponibles para Salidas

```http
GET /api/v2/productions/{id}/available-products-for-outputs
```

---

## Registros de Producción

### Listar Registros

```http
GET /api/v2/production-records
```

---

### Crear Registro

```http
POST /api/v2/production-records
```

#### Request Body

```json
{
  "production_id": 1,
  "process_id": 1,
  "start_date": "2024-01-15",
  "notes": "Registro de producción"
}
```

---

### Mostrar Registro

```http
GET /api/v2/production-records/{id}
```

---

### Actualizar Registro

```http
PUT /api/v2/production-records/{id}
```

---

### Eliminar Registro

```http
DELETE /api/v2/production-records/{id}
```

---

### Obtener Opciones de Registros

```http
GET /api/v2/production-records/options
```

#### Headers
```http
X-Tenant: {subdomain}
Authorization: Bearer {access_token}
```

#### Query Parameters (Opcionales)

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| production_id | integer | Filtrar por lote de producción |
| exclude_id | integer | Excluir registro por ID |

#### Response Exitosa (200)

```json
{
  "message": "Opciones de nodos padres obtenidas correctamente.",
  "data": [
    {
      "value": 1,
      "label": "Proceso A - 15/01/2024 10:00 (Raíz)",
      "processName": "Proceso A",
      "processId": 1,
      "productionId": 1,
      "productionLot": "LOT-001",
      "isRoot": true,
      "isFinal": false,
      "isCompleted": false,
      "startedAt": "2024-01-15T10:00:00.000000Z",
      "finishedAt": null
    }
  ]
}
```

---

### Obtener Datos de Fuentes

```http
GET /api/v2/production-records/{id}/sources-data
```

#### Headers
```http
X-Tenant: {subdomain}
Authorization: Bearer {access_token}
```

#### Path Parameters

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| id | integer | ID del registro de producción |

#### Response Exitosa (200)

```json
{
  "message": "Datos de sources obtenidos correctamente.",
  "data": {
    "productionRecord": {
      "id": 1,
      "processId": 1,
      "processName": "Proceso A",
      "productionId": 1,
      "productionLot": "LOT-001",
      "totalInputWeight": 1500.00,
      "totalInputCost": 7500.00
    },
    "stockBoxes": [
      {
        "productionInputId": 1,
        "boxId": 1,
        "product": {
          "id": 1,
          "name": "Producto A"
        },
        "lot": "LOT-001",
        "netWeight": 20.00,
        "grossWeight": 25.00,
        "costPerKg": 5.00,
        "totalCost": 100.00,
        "gs1128": "1234567890123",
        "palletId": 1
      }
    ],
    "parentOutputs": [
      {
        "productionOutputConsumptionId": 1,
        "productionOutputId": 1,
        "product": {
          "id": 2,
          "name": "Producto B"
        },
        "lotId": 1,
        "consumedWeightKg": 50.00,
        "consumedBoxes": 2,
        "outputTotalWeight": 100.00,
        "outputTotalBoxes": 4,
        "outputAvailableWeight": 50.00,
        "outputAvailableBoxes": 2,
        "costPerKg": 10.00,
        "totalCost": 500.00,
        "parentProcess": {
          "id": 2,
          "name": "Proceso Padre",
          "processId": 2
        }
      }
    ],
    "totals": {
      "stock": {
        "count": 10,
        "totalWeight": 1000.00,
        "totalCost": 5000.00,
        "averageCostPerKg": 5.00
      },
      "parent": {
        "count": 2,
        "totalWeight": 500.00,
        "totalCost": 2500.00,
        "averageCostPerKg": 5.00
      },
      "combined": {
        "totalWeight": 1500.00,
        "totalCost": 7500.00,
        "averageCostPerKg": 5.00
      }
    }
  }
}
```

**Descripción:** Este endpoint devuelve toda la información necesaria para crear sources (fuentes) de outputs, incluyendo:
- Inputs de stock (cajas) disponibles con sus costes
- Consumos de outputs del padre disponibles con sus costes
- Información del proceso actual
- Totales y pesos disponibles

---

### Obtener Árbol del Registro

```http
GET /api/v2/production-records/{id}/tree
```

#### Headers
```http
X-Tenant: {subdomain}
Authorization: Bearer {access_token}
```

#### Path Parameters

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| id | integer | ID del registro de producción |

#### Response Exitosa (200)

```json
{
  "message": "Árbol de procesos obtenido correctamente.",
  "data": {
    "id": 1,
    "production": {...},
    "process": {...},
    "children": [
      {
        "id": 2,
        "children": [...]
      }
    ],
    "inputs": [...],
    "outputs": [...]
  }
}
```

**Descripción:** Devuelve el árbol completo del registro con todos sus hijos recursivamente.

---

### Finalizar Registro

```http
POST /api/v2/production-records/{id}/finish
```

#### Response Exitosa (200)

```json
{
  "message": "Registro de producción finalizado correctamente.",
  "data": {
    "id": 1,
    "status": "finished",
    "finished_at": "2024-01-15T18:00:00.000000Z"
  }
}
```

---

### Sincronizar Salidas

```http
PUT /api/v2/production-records/{id}/outputs
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
| id | integer | ID del registro de producción |

#### Request Body

```json
{
  "outputs": [
    {
      "id": 1,
      "product": {
        "id": 1
      },
      "weight_kg": 100.00,
      "boxes": 5,
      "lot_id": 1
    }
  ]
}
```

#### Response Exitosa (200)

```json
{
  "message": "Salidas sincronizadas correctamente.",
  "data": {
    "id": 1,
    "outputs": [...]
  },
  "summary": {
    "created": 2,
    "updated": 1,
    "deleted": 0
  }
}
```

**Descripción:** Crea nuevas salidas, actualiza existentes y elimina las que no están en el array.

---

### Sincronizar Consumos

```http
PUT /api/v2/production-records/{id}/parent-output-consumptions
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
| id | integer | ID del registro de producción |

#### Request Body

```json
{
  "consumptions": [
    {
      "id": 1,
      "production_output_id": 1,
      "consumed_weight_kg": 50.00,
      "consumed_boxes": 2
    }
  ]
}
```

#### Response Exitosa (200)

```json
{
  "message": "Consumos sincronizados correctamente.",
  "data": {
    "id": 1,
    "parent_output_consumptions": [...]
  },
  "summary": {
    "created": 2,
    "updated": 1,
    "deleted": 0
  }
}
```

**Descripción:** Sincroniza los consumos de outputs del padre. Crea nuevos, actualiza existentes y elimina los que no están en el array.

---

## Entradas de Producción

### Listar Entradas

```http
GET /api/v2/production-inputs
```

---

### Crear Entrada

```http
POST /api/v2/production-inputs
```

#### Request Body

```json
{
  "production_record_id": 1,
  "product_id": 1,
  "quantity": 100.50,
  "unit": "kg"
}
```

---

### Crear Múltiples Entradas

```http
POST /api/v2/production-inputs/multiple
```

#### Request Body

```json
{
  "inputs": [
    {
      "production_record_id": 1,
      "product_id": 1,
      "quantity": 100.50,
      "unit": "kg"
    },
    {
      "production_record_id": 1,
      "product_id": 2,
      "quantity": 50.25,
      "unit": "kg"
    }
  ]
}
```

---

### Mostrar Entrada

```http
GET /api/v2/production-inputs/{id}
```

---

### Actualizar Entrada

```http
PUT /api/v2/production-inputs/{id}
```

---

### Eliminar Entrada

```http
DELETE /api/v2/production-inputs/{id}
```

---

### Eliminar Múltiples Entradas

```http
DELETE /api/v2/production-inputs/multiple
```

#### Request Body

```json
{
  "ids": [1, 2, 3]
}
```

---

## Salidas de Producción

### Listar Salidas

```http
GET /api/v2/production-outputs
```

---

### Crear Salida

```http
POST /api/v2/production-outputs
```

#### Request Body

```json
{
  "production_record_id": 1,
  "product_id": 1,
  "quantity": 95.00,
  "unit": "kg"
}
```

---

### Crear Múltiples Salidas

```http
POST /api/v2/production-outputs/multiple
```

---

### Mostrar Salida

```http
GET /api/v2/production-outputs/{id}
```

---

### Actualizar Salida

```http
PUT /api/v2/production-outputs/{id}
```

---

### Eliminar Salida

```http
DELETE /api/v2/production-outputs/{id}
```

---

### Obtener Desglose de Costos

```http
GET /api/v2/production-outputs/{id}/cost-breakdown
```

#### Response Exitosa (200)

```json
{
  "data": {
    "output_id": 1,
    "total_cost": 1500.00,
    "cost_per_kg": 15.79,
    "breakdown": [
      {
        "type": "input",
        "product": "Materia Prima A",
        "quantity": 100.00,
        "cost": 1000.00
      },
      {
        "type": "labor",
        "name": "Mano de Obra",
        "cost": 300.00
      },
      {
        "type": "operational",
        "name": "Electricidad",
        "cost": 200.00
      }
    ]
  }
}
```

---

## Consumos de Salidas

### Listar Consumos

```http
GET /api/v2/production-output-consumptions
```

---

### Crear Consumo

```http
POST /api/v2/production-output-consumptions
```

#### Request Body

```json
{
  "parent_output_id": 1,
  "consumed_output_id": 2,
  "quantity": 50.00
}
```

---

### Crear Múltiples Consumos

```http
POST /api/v2/production-output-consumptions/multiple
```

---

### Mostrar Consumo

```http
GET /api/v2/production-output-consumptions/{id}
```

---

### Actualizar Consumo

```http
PUT /api/v2/production-output-consumptions/{id}
```

---

### Eliminar Consumo

```http
DELETE /api/v2/production-output-consumptions/{id}
```

---

### Obtener Salidas Disponibles

```http
GET /api/v2/production-output-consumptions/available-outputs/{productionRecordId}
```

#### Headers
```http
X-Tenant: {subdomain}
Authorization: Bearer {access_token}
```

#### Path Parameters

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| productionRecordId | integer | ID del registro de producción |

#### Response Exitosa (200)

```json
{
  "data": [
    {
      "id": 1,
      "product": {
        "id": 1,
        "name": "Producto A"
      },
      "weight_kg": 100.00,
      "boxes": 5,
      "available_weight_kg": 50.00,
      "available_boxes": 2,
      "cost_per_kg": 5.00,
      "total_cost": 500.00,
      "production_record": {
        "id": 2,
        "process": {
          "id": 1,
          "name": "Proceso Padre"
        }
      }
    }
  ]
}
```

**Descripción:** Devuelve los outputs de producción disponibles para ser consumidos por el proceso hijo especificado.

---

## Procesos

### Listar Procesos

```http
GET /api/v2/processes
```

---

### Crear Proceso

```http
POST /api/v2/processes
```

#### Request Body

```json
{
  "name": "Proceso A",
  "description": "Descripción del proceso"
}
```

---

### Mostrar Proceso

```http
GET /api/v2/processes/{id}
```

---

### Actualizar Proceso

```http
PUT /api/v2/processes/{id}
```

---

### Eliminar Proceso

```http
DELETE /api/v2/processes/{id}
```

---

### Opciones de Procesos

```http
GET /api/v2/processes/options
```

---

## Respuestas Erróneas

### Error de Validación (422)

```json
{
  "message": "Error de validación.",
  "userMessage": "El campo production_record_id es obligatorio.",
  "errors": {
    "production_record_id": ["The production record id field is required."]
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
  "message": "Producción no encontrada."
}
```

