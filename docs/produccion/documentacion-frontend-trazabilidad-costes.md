# 📚 Documentación Frontend - Trazabilidad de Costes en Producciones

**Versión**: 1.0  
**Fecha**: 2025-01-XX  
**Estado**: Implementación completa

---

## 📋 Índice

1. [Resumen de Cambios](#resumen-de-cambios)
2. [Nuevos Endpoints](#nuevos-endpoints)
   - [Catálogo de Costes](#1-catálogo-de-costes-v2cost-catalog)
   - [Costes de Producción](#2-costes-de-producción-v2production-costs)
   - [Obtener Datos de Sources](#3-obtener-datos-de-sources-para-crear-outputs-v2production-recordsidsources-data)
   - [Actualización de Endpoints Existentes](#4-actualización-de-endpoints-existentes)
3. [Estructuras de Datos](#estructuras-de-datos)
4. [Flujos de Trabajo](#flujos-de-trabajo)
5. [Ejemplos de Uso](#ejemplos-de-uso)
6. [Consideraciones de UI/UX](#consideraciones-de-uiux)

---

## 🎯 Resumen de Cambios

### Nuevas Funcionalidades

1. **Trazabilidad de Costes**: Cada `ProductionOutput` ahora tiene trazabilidad completa de costes desde materias primas hasta producto final
2. **Catálogo de Costes**: Sistema de catálogo para costes comunes (producción, personal, operativos, envases)
3. **Costes Adicionales**: Posibilidad de agregar costes a nivel de proceso o a nivel de producción (lote)
4. **Desglose de Costes**: Desglose detallado de costes por tipo y origen

### Cambios en Endpoints Existentes

- `GET /v2/production-outputs/{id}`: Ahora incluye campos de coste (`costPerKg`, `totalCost`, `sources`)
- `POST /v2/production-outputs`: Ahora acepta campo `sources` para especificar proveniencia
- `PUT /v2/production-outputs/{id}`: Permite actualizar `sources`

---

## 🔌 Nuevos Endpoints

### 1. Catálogo de Costes (`/v2/cost-catalog`)

#### `GET /v2/cost-catalog`

Lista el catálogo de costes disponibles.

**Query Parameters**:
- `cost_type` (opcional): Filtrar por tipo (`production`, `labor`, `operational`, `packaging`)
- `active_only` (opcional, default: `true`): Solo costes activos
- `perPage` (opcional, default: `15`): Elementos por página

**Response**:
```json
{
  "message": "Catálogo de costes obtenido correctamente.",
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 1,
        "name": "Mantenimiento máquina",
        "costType": "production",
        "description": "Mantenimiento preventivo de maquinaria",
        "defaultUnit": "total",
        "isActive": true,
        "createdAt": "2025-01-15T10:00:00Z",
        "updatedAt": "2025-01-15T10:00:00Z"
      },
      {
        "id": 2,
        "name": "Energía eléctrica",
        "costType": "operational",
        "description": "Consumo eléctrico del proceso",
        "defaultUnit": "per_kg",
        "isActive": true,
        "createdAt": "2025-01-15T10:00:00Z",
        "updatedAt": "2025-01-15T10:00:00Z"
      }
    ],
    "total": 10,
    "per_page": 15
  }
}
```

#### `POST /v2/cost-catalog`

Crea un nuevo coste en el catálogo.

**Request Body**:
```json
{
  "name": "Agua industrial",
  "costType": "operational",
  "description": "Consumo de agua industrial",
  "defaultUnit": "per_kg",
  "isActive": true
}
```

**Response** (201):
```json
{
  "message": "Coste agregado al catálogo correctamente.",
  "data": {
    "id": 3,
    "name": "Agua industrial",
    "costType": "operational",
    "description": "Consumo de agua industrial",
    "defaultUnit": "per_kg",
    "isActive": true,
    "createdAt": "2025-01-15T10:00:00Z",
    "updatedAt": "2025-01-15T10:00:00Z"
  }
}
```

#### `GET /v2/cost-catalog/{id}`

Obtiene un coste específico del catálogo.

#### `PUT /v2/cost-catalog/{id}`

Actualiza un coste del catálogo.

**Request Body**:
```json
{
  "name": "Energía eléctrica - Actualizado",
  "description": "Nueva descripción",
  "isActive": false
}
```

#### `DELETE /v2/cost-catalog/{id}`

Elimina un coste del catálogo (solo si no está siendo usado).

---

### 2. Costes de Producción (`/v2/production-costs`)

#### `GET /v2/production-costs`

Lista costes de producción.

**Query Parameters**:
- `production_record_id` (opcional): Filtrar por proceso
- `production_id` (opcional): Filtrar por lote
- `cost_type` (opcional): Filtrar por tipo
- `perPage` (opcional, default: `15`)

**Response**:
```json
{
  "message": "Costes obtenidos correctamente.",
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 1,
        "productionRecordId": 5,
        "productionId": null,
        "costCatalogId": 1,
        "costCatalog": null,
        "costType": "production",
        "name": "Mantenimiento máquina",
        "description": "Mantenimiento preventivo mensual",
        "totalCost": 500.00,
        "costPerKg": null,
        "distributionUnit": null,
        "costDate": "2025-01-15",
        "effectiveTotalCost": 500.00,
        "createdAt": "2025-01-15T10:00:00Z",
        "updatedAt": "2025-01-15T10:00:00Z"
      }
    ]
  }
}
```

#### `POST /v2/production-costs`

Crea un nuevo coste de producción.

**Request Body - Coste desde catálogo (Recomendado)**:
```json
{
  "production_record_id": 5,
  "production_id": null,
  "cost_catalog_id": 1,
  "name": null,
  "cost_type": null,
  "description": "Mantenimiento preventivo mensual",
  "total_cost": 500.00,
  "cost_per_kg": null,
  "cost_date": "2025-01-15"
}
```

**Request Body - Coste desde catálogo con nombre personalizado**:
```json
{
  "production_record_id": 5,
  "production_id": null,
  "cost_catalog_id": 1,
  "name": "Mantenimiento máquina fileteado - Especial",
  "cost_type": null,
  "description": "Mantenimiento preventivo mensual",
  "total_cost": 500.00,
  "cost_per_kg": null,
  "cost_date": "2025-01-15"
}
```

**Request Body - Coste ad-hoc (no está en catálogo)**:
```json
{
  "production_record_id": 5,
  "production_id": null,
  "cost_catalog_id": null,
  "name": "Servicio especial de limpieza",
  "cost_type": "operational",
  "description": "Limpieza especial por inspección",
  "total_cost": 200.00,
  "cost_per_kg": null,
  "cost_date": "2025-01-15"
}
```

**Request Body - Coste por kg**:
```json
{
  "production_record_id": 5,
  "production_id": null,
  "cost_catalog_id": 2,
  "name": null,
  "cost_type": null,
  "description": "Consumo eléctrico del proceso",
  "total_cost": null,
  "cost_per_kg": 0.50,
  "cost_date": "2025-01-15"
}
```

**Request Body - Coste a nivel de producción (lote)**:
```json
{
  "production_record_id": null,
  "production_id": 10,
  "cost_catalog_id": 7,
  "name": null,
  "cost_type": null,
  "description": "Personal de supervisión dedicado al lote completo",
  "total_cost": 1500.00,
  "cost_per_kg": null,
  "cost_date": "2025-01-15"
}
```

**⚠️ Reglas de Validación**:
- Debe especificarse **O bien** `production_record_id` **O bien** `production_id` (no ambos, no ninguno)
- Debe especificarse **O bien** `total_cost` **O bien** `cost_per_kg` (no ambos, no ninguno)
- Si `cost_catalog_id` está presente, `name` y `cost_type` se obtienen automáticamente (pero se pueden sobrescribir)
- Si `cost_catalog_id` es null, `name` y `cost_type` son obligatorios

#### `GET /v2/production-costs/{id}`

Obtiene un coste específico.

#### `PUT /v2/production-costs/{id}`

Actualiza un coste de producción.

#### `DELETE /v2/production-costs/{id}`

Elimina un coste de producción.

---

### 3. Obtener Datos de Sources para Crear Outputs (`/v2/production-records/{id}/sources-data`)

#### `GET /v2/production-records/{id}/sources-data`

**✨ NUEVO**: Endpoint específico para obtener toda la información necesaria para crear sources al crear un `ProductionOutput`.

Este endpoint devuelve:
- **Stock Products**: Todos los productos consumidos desde stock en el proceso, agrupados por `product_id`
- **Parent Outputs**: Todos los consumos de outputs del proceso padre con sus costes
- **Totales**: Resumen de pesos, costes y promedios

**Use Case**: Cuando el frontend necesita crear un nuevo `ProductionOutput` y debe permitir al usuario seleccionar qué fuentes (`stock_product` o `parent_output`) contribuyen al output y en qué proporción.

**Response**:
```json
{
  "message": "Datos de sources obtenidos correctamente.",
  "data": {
    "productionRecord": {
      "id": 5,
      "processId": 3,
      "processName": "Fileteado",
      "productionId": 10,
      "productionLot": "LOT-2025-001",
      "totalInputWeight": 150.5,
      "totalInputCost": 1279.25
    },
    "stockProducts": [
      {
        "productId": 5,
        "product": {
          "id": 5,
          "name": "Atún rojo"
        },
        "inputCount": 2,
        "totalWeight": 53.5,
        "costPerKg": 8.50,
        "totalCost": 454.75
      }
    ],
    "parentOutputs": [
      {
        "productionOutputConsumptionId": 5,
        "productionOutputId": 20,
        "product": {
          "id": 8,
          "name": "Filetes de atún"
        },
        "lotId": "LOT-2025-001-FIL",
        "consumedWeightKg": 40.0,
        "consumedBoxes": 4,
        "outputTotalWeight": 100.0,
        "outputTotalBoxes": 10,
        "outputAvailableWeight": 60.0,
        "outputAvailableBoxes": 6,
        "costPerKg": 12.30,
        "totalCost": 492.00,
        "parentProcess": {
          "id": 4,
          "name": "Limpieza",
          "processId": 2
        }
      }
    ],
    "totals": {
      "stock": {
        "count": 2,
        "totalWeight": 53.5,
        "totalCost": 454.75,
        "averageCostPerKg": 8.50
      },
      "parent": {
        "count": 1,
        "totalWeight": 40.0,
        "totalCost": 492.00,
        "averageCostPerKg": 12.30
      },
      "combined": {
        "totalWeight": 93.5,
        "totalCost": 946.75,
        "averageCostPerKg": 10.12
      }
    }
  }
}
```

**Estructura de Datos**:

- **`productionRecord`**: Información básica del proceso actual
  - `id`: ID del registro de producción
  - `processId`: ID del proceso
  - `processName`: Nombre del proceso
  - `productionId`: ID de la producción (lote)
  - `productionLot`: Lote de la producción
  - `totalInputWeight`: Peso total de todas las entradas
  - `totalInputCost`: Coste total de todas las entradas

- **`stockProducts`**: Array de productos consumidos desde stock, agrupados por `product_id`
  - `productId`: ID del producto (usar en `sources[].product_id`)
  - `product`: Información del producto
  - `inputCount`: Número de inputs/cajas consumidos de ese producto
  - `totalWeight`: Peso total consumido de ese producto
  - `costPerKg`: Coste medio ponderado por kg de ese producto en el proceso
  - `totalCost`: Coste total agregado del producto consumido

- **`parentOutputs`**: Array de consumos de outputs del proceso padre
  - `productionOutputConsumptionId`: ID del `ProductionOutputConsumption` (usar en `sources[].production_output_consumption_id`)
  - `productionOutputId`: ID del `ProductionOutput` consumido
  - `product`: Información del producto del output
  - `lotId`: Lote del output
  - `consumedWeightKg`: Peso consumido de este output
  - `consumedBoxes`: Cajas consumidas
  - `outputTotalWeight`: Peso total del output original
  - `outputTotalBoxes`: Cajas totales del output original
  - `outputAvailableWeight`: Peso disponible restante del output
  - `outputAvailableBoxes`: Cajas disponibles restantes
  - `costPerKg`: Coste por kg del output (calculado recursivamente)
  - `totalCost`: Coste total del output
  - `parentProcess`: Información del proceso padre

- **`totals`**: Resumen de totales
- `stock`: Totales de productos consumidos desde stock
  - `parent`: Totales de outputs del padre
  - `combined`: Totales combinados

**Ejemplo de Uso en Frontend**:

```javascript
// 1. Obtener datos de sources antes de crear el output
const response = await fetch(`/api/v2/production-records/${recordId}/sources-data`);
const { data } = await response.json();

// 2. Mostrar al usuario las fuentes disponibles
// data.stockProducts - productos consumidos desde stock
// data.parentOutputs - outputs del padre

// 3. Usuario selecciona fuentes y especifica contribución
const sources = [
  {
    source_type: 'stock_product',
    product_id: data.stockProducts[0].productId,
    contributed_weight_kg: 53.5 // o contribution_percentage: 57.22
  },
  {
    source_type: 'parent_output',
    production_output_consumption_id: data.parentOutputs[0].productionOutputConsumptionId,
    contributed_weight_kg: 40.0, // o contribution_percentage: 47
    contributed_boxes: 4
  }
];

// 4. Crear output con sources
const outputResponse = await fetch('/api/v2/production-outputs', {
  method: 'POST',
  body: JSON.stringify({
    production_record_id: recordId,
    product_id: 8,
    lot_id: 'LOT-2025-001-FIL',
    boxes: 10,
    weight_kg: 85.5,
    sources: sources
  })
});
```

**Notas Importantes**:
- Si no se proporcionan `sources` al crear un `ProductionOutput`, se crearán automáticamente de forma proporcional
- Cada source debe tener O bien `contributed_weight_kg` O bien `contribution_percentage`
- La suma de `contribution_percentage` debe ser aproximadamente 100%
- Los costes se calculan automáticamente desde las fuentes
- `stock_product` se agrupa solo por `product_id`, ignorando lote, pallet y caja
- El backend rechazará una source `stock_product` si ese `product_id` no existe entre los inputs del proceso
- El coste de `stock_product` se calcula como media ponderada por kg de todas las cajas consumidas de ese producto en el proceso

---

### 4. Actualización de Endpoints Existentes

#### `GET /v2/production-outputs/{id}`

**Nuevos campos en la respuesta**:
```json
{
  "id": 1,
  "productionRecordId": 5,
  "productId": 12,
  "product": { ... },
  "lotId": "LOT-2025-001-FIL",
  "boxes": 10,
  "weightKg": 95.0,
  "averageWeightPerBox": 9.5,
  
  // ✨ NUEVOS CAMPOS
  "costPerKg": 12.86,
  "totalCost": 1221.70,
  "sources": [
    {
      "id": 1,
      "productionOutputId": 1,
      "sourceType": "stock_product",
      "productId": 10,
      "product": {
        "id": 10,
        "name": "Lomo de atún"
      },
      "contributedWeightKg": 55.0,
      "contributionPercentage": 57.89,
      "sourceCostPerKg": 10.00,
      "sourceTotalCost": 550.00
    },
    {
      "id": 2,
      "productionOutputId": 1,
      "sourceType": "parent_output",
      "productionOutputConsumptionId": 3,
      "contributedWeightKg": 40.0,
      "contributionPercentage": 42.10,
      "sourceCostPerKg": 15.00,
      "sourceTotalCost": 600.00
    }
  ],
  
  "createdAt": "2025-01-15T10:00:00Z",
  "updatedAt": "2025-01-15T10:00:00Z"
}
```

**Query Parameters adicionales**:
- `include_cost_breakdown` (opcional): Incluye desglose detallado de costes

#### `GET /v2/production-outputs/{id}/cost-breakdown`

**Nuevo endpoint**: Obtiene el desglose completo de costes de un output.

**Response**:
```json
{
  "message": "Desglose de costes obtenido correctamente.",
  "data": {
    "output": { ... },
    "cost_breakdown": {
      "materials": {
        "total_cost": 900.00,
        "cost_per_kg": 9.47,
        "sources": [
          {
            "source_type": "stock_product",
            "contributed_weight_kg": 55.0,
            "contribution_percentage": 57.89,
            "source_cost_per_kg": 10.00,
            "source_total_cost": 550.00
          }
        ]
      },
      "process_costs": {
        "production": {
          "total_cost": 300.00,
          "cost_per_kg": 3.16,
          "breakdown": [
            {
              "name": "Mantenimiento máquina envasado",
              "total_cost": 300.00,
              "cost_per_kg": 3.16
            }
          ]
        },
        "labor": {
          "total_cost": 200.00,
          "cost_per_kg": 2.11,
          "breakdown": [ ... ]
        },
        "operational": {
          "total_cost": 0.00,
          "cost_per_kg": 0.00,
          "breakdown": []
        },
        "packaging": {
          "total_cost": 150.00,
          "cost_per_kg": 1.58,
          "breakdown": [ ... ]
        },
        "total": {
          "total_cost": 650.00,
          "cost_per_kg": 6.84
        }
      },
      "production_costs": {
        "production": {
          "total_cost": 2000.00,
          "cost_per_kg": 21.05,
          "breakdown": [ ... ]
        },
        "labor": {
          "total_cost": 1500.00,
          "cost_per_kg": 15.79,
          "breakdown": [ ... ]
        },
        "operational": {
          "total_cost": 18.00,
          "cost_per_kg": 0.19,
          "breakdown": [ ... ]
        },
        "packaging": {
          "total_cost": 0.00,
          "cost_per_kg": 0.00,
          "breakdown": []
        },
        "total": {
          "total_cost": 3518.00,
          "cost_per_kg": 37.03
        }
      },
      "total": {
        "total_cost": 5068.00,
        "cost_per_kg": 53.35
      }
    }
  }
}
```

#### `POST /v2/production-outputs`

**Nuevo campo en el request**: `sources`

**Request Body**:
```json
{
  "production_record_id": 5,
  "product_id": 12,
  "lot_id": "LOT-2025-001-FIL",
  "boxes": 10,
  "weight_kg": 95.0,
  "sources": [
    {
      "source_type": "stock_product",
      "product_id": 10,
      "contributed_weight_kg": 55
    },
    {
      "source_type": "parent_output",
      "production_output_consumption_id": 3,
      "contributed_weight_kg": 40
    }
  ]
}
```

**⚠️ Reglas de Validación**:
- Se debe especificar **O bien** `contributed_weight_kg` **O bien** `contribution_percentage` (no ambos, no ninguno)
- Si se especifica uno, el otro se calcula automáticamente
- **IMPORTANTE**: Los sources reflejan el **CONSUMO REAL** (peso de inputs), no el output final
- Si `source_type = stock_product`, se debe enviar `product_id`
- Si `source_type = parent_output`, se debe enviar `production_output_consumption_id`
- `production_input_id` ya no forma parte del contrato operativo
- El `product_id` de `stock_product` debe existir entre los inputs consumidos por el proceso
- La suma de `contribution_percentage` debe ser ≈ 100% del **consumo real** (con tolerancia de 0.01%)
- La suma de `contributed_weight_kg` debe ser aproximadamente igual al **consumo real** del proceso
- Si `sources` no se proporciona, se calcula automáticamente de forma proporcional basándose en el consumo real

**📊 Ejemplo con Merma**:
```
Consumo real (inputs): 100kg
Output final: 90kg
Merma: 10kg

Sources (deben sumar 100kg, no 90kg):
- Source 1: contributed_weight_kg = 50kg (50% del consumo)
- Source 2: contributed_weight_kg = 50kg (50% del consumo)
Total sources: 100kg ✅

Output: weight_kg = 90kg

Merma calculable: 100kg (sources) - 90kg (output) = 10kg ✅
```

#### `PUT /v2/production-outputs/{id}`

Permite actualizar `sources` del output.

---

## 📊 Estructuras de Datos

### ProductionOutput (Actualizado)

```typescript
interface ProductionOutput {
  id: number;
  productionRecordId: number;
  productId: number;
  product?: Product;
  lotId: string;
  boxes: number;
  weightKg: number;
  averageWeightPerBox: number;
  
  // ✨ NUEVOS CAMPOS
  costPerKg?: number;
  totalCost?: number;
  costBreakdown?: CostBreakdown;
  sources?: ProductionOutputSource[];
  
  createdAt: string;
  updatedAt: string;
}
```

### ProductionOutputSource

```typescript
interface ProductionOutputSource {
  id: number;
  productionOutputId: number;
  sourceType: 'stock_product' | 'parent_output';
  productId?: number;
  product?: Product;
  productionOutputConsumptionId?: number;
  productionOutputConsumption?: ProductionOutputConsumption;
  contributedWeightKg?: number; // ⚠️ Peso REAL consumido (no el output final)
  contributedBoxes: number;
  contributionPercentage?: number; // ⚠️ Porcentaje del CONSUMO REAL (no del output)
  sourceCostPerKg?: number;
  sourceTotalCost?: number;
  createdAt: string;
  updatedAt: string;
}
```

**⚠️ IMPORTANTE - Sources y Merma**:
- Los `contributedWeightKg` reflejan el **CONSUMO REAL** (peso de inputs), no el output final
- La suma de todos los `contributedWeightKg` debe ser igual al consumo total del proceso
- El `contributionPercentage` se calcula sobre el **consumo real**, no sobre el output
- Esto permite calcular la merma como: `sum(sources) - output.weightKg`
- El coste se calcula sobre el **consumo real**, incluyendo la merma
- En `stock_product`, el coste por kg es una media ponderada de todas las cajas consumidas de ese producto en el proceso

**Ejemplo con Merma**:
```
Consumo real (inputs): 100kg
Output final: 90kg
Merma: 10kg

Sources (deben sumar 100kg, no 90kg):
- Source 1: contributedWeightKg = 50kg (50% del consumo)
- Source 2: contributedWeightKg = 50kg (50% del consumo)
Total sources: 100kg ✅

Output: weightKg = 90kg

Merma calculable: 100kg (sources) - 90kg (output) = 10kg ✅
```

### CostCatalog

```typescript
interface CostCatalog {
  id: number;
  name: string;
  costType: 'production' | 'labor' | 'operational' | 'packaging';
  description?: string;
  defaultUnit: 'total' | 'per_kg';
  isActive: boolean;
  createdAt: string;
  updatedAt: string;
}
```

### ProductionCost

```typescript
interface ProductionCost {
  id: number;
  productionRecordId?: number;
  productionId?: number;
  costCatalogId?: number;
  costCatalog?: CostCatalog;
  costType: 'production' | 'labor' | 'operational' | 'packaging';
  name: string;
  description?: string;
  totalCost?: number;
  costPerKg?: number;
  distributionUnit?: string;
  costDate?: string;
  effectiveTotalCost?: number;
  createdAt: string;
  updatedAt: string;
}
```

### CostBreakdown

```typescript
interface CostBreakdown {
  materials: {
    total_cost: number;
    cost_per_kg: number;
    sources: Array<{
      source_type: string;
      contributed_weight_kg: number;
      contribution_percentage: number;
      source_cost_per_kg: number;
      source_total_cost: number;
    }>;
  };
  process_costs: {
    production: CostTypeBreakdown;
    labor: CostTypeBreakdown;
    operational: CostTypeBreakdown;
    packaging: CostTypeBreakdown;
    total: {
      total_cost: number;
      cost_per_kg: number;
    };
  };
  production_costs: {
    production: CostTypeBreakdown;
    labor: CostTypeBreakdown;
    operational: CostTypeBreakdown;
    packaging: CostTypeBreakdown;
    total: {
      total_cost: number;
      cost_per_kg: number;
    };
  };
  total: {
    total_cost: number;
    cost_per_kg: number;
  };
}

interface CostTypeBreakdown {
  total_cost: number;
  cost_per_kg: number;
  breakdown: Array<{
    name: string;
    total_cost: number;
    cost_per_kg: number;
  }>;
}
```

---

## 🔄 Flujos de Trabajo

### 1. Crear Output con Trazabilidad de Costes

**Paso 1**: Obtener productos de stock agrupados del proceso
```typescript
// GET /v2/production-records/{id}/sources-data
const sourcesData = await getSourcesData(productionRecordId);
```

**Paso 2**: Crear output con sources
```typescript
const output = await createProductionOutput({
  production_record_id: 5,
  product_id: 12,
  lot_id: "LOT-2025-001-FIL",
  boxes: 10,
  weight_kg: 95.0,
  sources: [
    {
      source_type: "stock_product",
      product_id: sourcesData.stockProducts[0].productId,
      contributed_weight_kg: 55
    }
  ]
});
```

**Paso 3**: Verificar costes calculados
```typescript
// El output ya incluye costPerKg y totalCost
console.log(output.costPerKg); // 12.86
console.log(output.totalCost); // 1221.70
```

### 2. Agregar Costes Adicionales a un Proceso

**Paso 1**: Obtener catálogo de costes
```typescript
// GET /v2/cost-catalog?cost_type=production&active_only=true
const catalog = await getCostCatalog({ cost_type: 'production' });
```

**Paso 2**: Crear coste desde catálogo
```typescript
const cost = await createProductionCost({
  production_record_id: 5,
  production_id: null,
  cost_catalog_id: catalog.data[0].id, // "Mantenimiento máquina"
  total_cost: 500.00,
  cost_date: "2025-01-15"
});
```

**Paso 3**: O crear coste ad-hoc
```typescript
const cost = await createProductionCost({
  production_record_id: 5,
  production_id: null,
  cost_catalog_id: null,
  name: "Servicio especial",
  cost_type: "operational",
  total_cost: 200.00,
  cost_date: "2025-01-15"
});
```

### 3. Agregar Costes a Nivel de Producción (Lote)

```typescript
const cost = await createProductionCost({
  production_record_id: null,
  production_id: 10,
  cost_catalog_id: 7, // "Supervisión"
  total_cost: 1500.00,
  cost_date: "2025-01-15"
});
```

### 4. Ver Desglose Completo de Costes

```typescript
// GET /v2/production-outputs/{id}/cost-breakdown
const breakdown = await getCostBreakdown(outputId);

console.log(breakdown.data.cost_breakdown.materials.total_cost);
console.log(breakdown.data.cost_breakdown.process_costs.total.total_cost);
console.log(breakdown.data.cost_breakdown.production_costs.total.total_cost);
console.log(breakdown.data.cost_breakdown.total.total_cost);
```

---

## 💡 Ejemplos de Uso

### Ejemplo 1: Mostrar Coste en Lista de Outputs

```typescript
const outputs = await getProductionOutputs({ production_record_id: 5 });

outputs.data.forEach(output => {
  console.log(`${output.product.name}: ${output.costPerKg}€/kg (Total: ${output.totalCost}€)`);
});
```

### Ejemplo 2: Formulario para Agregar Coste

```typescript
// Componente React/Vue
const [costCatalog, setCostCatalog] = useState([]);
const [selectedCatalogId, setSelectedCatalogId] = useState(null);
const [costType, setCostType] = useState('total'); // 'total' o 'per_kg'
const [costValue, setCostValue] = useState(0);

// Cargar catálogo
useEffect(() => {
  getCostCatalog().then(res => setCostCatalog(res.data.data));
}, []);

// Al seleccionar del catálogo
const handleCatalogSelect = (catalogId) => {
  const catalogItem = costCatalog.find(c => c.id === catalogId);
  setSelectedCatalogId(catalogId);
  setCostType(catalogItem.defaultUnit);
};

// Enviar
const handleSubmit = async () => {
  await createProductionCost({
    production_record_id: productionRecordId,
    production_id: null,
    cost_catalog_id: selectedCatalogId,
    total_cost: costType === 'total' ? costValue : null,
    cost_per_kg: costType === 'per_kg' ? costValue : null,
    cost_date: new Date().toISOString().split('T')[0]
  });
};
```

### Ejemplo 3: Mostrar Desglose de Costes

```typescript
const breakdown = await getCostBreakdown(outputId);
const { cost_breakdown } = breakdown.data;

// Mostrar en tabla
<table>
  <tr>
    <td>Materias primas</td>
    <td>{cost_breakdown.materials.total_cost}€</td>
    <td>{cost_breakdown.materials.cost_per_kg}€/kg</td>
  </tr>
  <tr>
    <td>Costes del proceso</td>
    <td>{cost_breakdown.process_costs.total.total_cost}€</td>
    <td>{cost_breakdown.process_costs.total.cost_per_kg}€/kg</td>
  </tr>
  <tr>
    <td>Costes del lote</td>
    <td>{cost_breakdown.production_costs.total.total_cost}€</td>
    <td>{cost_breakdown.production_costs.total.cost_per_kg}€/kg</td>
  </tr>
  <tr>
    <td><strong>Total</strong></td>
    <td><strong>{cost_breakdown.total.total_cost}€</strong></td>
    <td><strong>{cost_breakdown.total.cost_per_kg}€/kg</strong></td>
  </tr>
</table>
```

---

## 🎨 Consideraciones de UI/UX

### 1. Visualización de Costes

- **Mostrar coste por kg** en listas de outputs
- **Mostrar coste total** en detalles
- **Indicador visual** si el coste está calculado o pendiente
- **Color coding** según tipo de coste (materias primas, proceso, lote)

### 2. Formulario de Sources

- **Autocompletado** de sources basado en inputs del proceso
- **Validación en tiempo real** de porcentajes (debe sumar 100%)
- **Opción de especificar por kg o porcentaje**
- **Cálculo automático** del otro valor

### 3. Formulario de Costes

- **Selector de catálogo** con búsqueda
- **Opción de crear coste ad-hoc** si no está en catálogo
- **Toggle entre coste total y coste por kg**
- **Sugerencia de unidad** basada en el catálogo

### 4. Desglose de Costes

- **Vista expandible** por tipo de coste
- **Gráficos** para visualizar distribución
- **Exportación** a PDF/Excel
- **Filtros** por tipo de coste

---

## ⚠️ Notas Importantes

1. **Costes se calculan dinámicamente**: Los costes no se almacenan en la BD, se calculan en tiempo real mediante accessors
2. **Solo outputs finales reciben costes del lote**: Los outputs intermedios solo reciben costes de materias primas y proceso
3. **Validaciones estrictas**: El backend valida que se especifique O bien total_cost O bien cost_per_kg
4. **Catálogo recomendado**: Usar catálogo para costes comunes, ad-hoc solo para casos especiales
5. **Sources opcionales**: Si no se especifican sources, se calculan automáticamente de forma proporcional

---

**Última actualización**: 2025-01-XX  
**Mantenido por**: Equipo Backend
