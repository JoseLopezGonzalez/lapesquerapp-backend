# Estadísticas de Rentabilidad de Pedidos

Tres endpoints independientes para alimentar el dashboard de rentabilidad: KPIs globales, evolución temporal y desglose por producto.

> **Nota de rendimiento**: los costes se calculan en tiempo real caja a caja. Para rangos amplios (>60 días con alto volumen de pedidos) la respuesta puede tardar varios segundos. Se documentará e implementará una tabla materializada en una fase posterior ([ADR-0002](../../architecture-decisions/0002-materialized-order-costs.md)).

---

## Autenticación y cabeceras comunes

```http
Authorization: Bearer {token}
X-Tenant: {tenant}
```

Todos los endpoints requieren el permiso `viewAny` sobre `Order`.

---

## 1. KPIs globales del período

```http
GET /api/v2/statistics/orders/profitability-summary
```

### Parámetros

| Parámetro | Tipo | Requerido | Descripción |
|---|---|---|---|
| `dateFrom` | `string` (YYYY-MM-DD) | Sí | Inicio del rango (sobre `load_date` del pedido). |
| `dateTo` | `string` (YYYY-MM-DD) | Sí | Fin del rango. |
| `productIds[]` | `integer[]` | No | Filtra para contar solo revenue y coste de esos productos dentro de cada pedido del rango. |

### Respuesta `200 OK`

```json
{
  "period": {
    "from": "2026-01-01",
    "to": "2026-03-31"
  },
  "ordersCount": 34,
  "totalRevenue": 48200.00,
  "totalCost": 36800.00,
  "grossMargin": 11400.00,
  "marginPercentage": 23.65
}
```

| Campo | Tipo | Descripción |
|---|---|---|
| `period.from` / `period.to` | `string` | Rango solicitado. |
| `ordersCount` | `integer` | Número de pedidos con `load_date` en el rango. |
| `totalRevenue` | `number` | Suma de `precio_unitario × kg_neto` de las cajas expedidas (sin IVA). |
| `totalCost` | `number \| null` | Suma del coste de todas las cajas expedidas. `null` si ninguna tiene coste calculable. |
| `grossMargin` | `number \| null` | `totalRevenue − totalCost`. |
| `marginPercentage` | `number \| null` | `(grossMargin / totalRevenue) × 100`, 2 decimales. |

---

## 2. Evolución temporal

```http
GET /api/v2/statistics/orders/profitability-timeline
```

### Parámetros

| Parámetro | Tipo | Requerido | Descripción |
|---|---|---|---|
| `dateFrom` | `string` (YYYY-MM-DD) | Sí | Inicio del rango. |
| `dateTo` | `string` (YYYY-MM-DD) | Sí | Fin del rango. |
| `granularity` | `day \| week \| month` | No | Agrupación temporal. Default: `month`. |
| `productIds[]` | `integer[]` | No | Igual que en summary: filtra por productos. |

### Respuesta `200 OK`

```json
{
  "granularity": "month",
  "series": [
    {
      "period": "2026-01",
      "periodLabel": "Enero 2026",
      "ordersCount": 11,
      "totalRevenue": 16200.00,
      "totalCost": 12400.00,
      "grossMargin": 3800.00,
      "marginPercentage": 23.46
    },
    {
      "period": "2026-02",
      "periodLabel": "Febrero 2026",
      "ordersCount": 0,
      "totalRevenue": 0.00,
      "totalCost": null,
      "grossMargin": null,
      "marginPercentage": null
    }
  ]
}
```

| Campo | Tipo | Descripción |
|---|---|---|
| `granularity` | `string` | Granularidad aplicada. |
| `series` | `array` | Un objeto por cada período del rango, **incluyendo períodos sin pedidos** (con valores en cero). |
| `series[].period` | `string` | Clave del período: `YYYY-MM-DD` (día), `YYYY-WW` (semana ISO), `YYYY-MM` (mes). |
| `series[].periodLabel` | `string` | Etiqueta legible en español: `"21/04/2026"`, `"Sem. 17 2026"`, `"Abril 2026"`. |
| `series[].ordersCount` | `integer` | Pedidos en ese período. |
| `series[].totalRevenue` | `number` | Revenue ex-IVA del período. `0.00` si no hay pedidos. |
| `series[].totalCost` | `number \| null` | Coste del período. `null` si no hay pedidos o ninguna caja tiene coste. |
| `series[].grossMargin` | `number \| null` | Margen bruto del período. |
| `series[].marginPercentage` | `number \| null` | Porcentaje de margen del período. |

> La serie **siempre cubre todos los períodos** entre `dateFrom` y `dateTo`. Los períodos vacíos tienen `totalRevenue: 0.00` y `totalCost: null`.

---

## 3. Desglose por producto

```http
GET /api/v2/statistics/orders/profitability-products
```

### Parámetros

| Parámetro | Tipo | Requerido | Descripción |
|---|---|---|---|
| `dateFrom` | `string` (YYYY-MM-DD) | Sí | Inicio del rango. |
| `dateTo` | `string` (YYYY-MM-DD) | Sí | Fin del rango. |

### Respuesta `200 OK`

```json
{
  "period": {
    "from": "2026-01-01",
    "to": "2026-03-31"
  },
  "products": [
    {
      "product": {
        "id": 12,
        "name": "Merluza 400-600 IQF"
      },
      "totalWeightKg": 4520.300,
      "totalRevenue": 26217.74,
      "totalCost": 19584.50,
      "grossMargin": 6633.24,
      "marginPercentage": 25.30,
      "revenuePerKg": 5.8000,
      "costPerKg": 4.3326,
      "marginPerKg": 1.4674,
      "ordersCount": 18
    },
    {
      "product": {
        "id": 7,
        "name": "Merluza 600-800 IQF"
      },
      "totalWeightKg": 2100.000,
      "totalRevenue": 11550.00,
      "totalCost": null,
      "grossMargin": null,
      "marginPercentage": null,
      "revenuePerKg": 5.5000,
      "costPerKg": null,
      "marginPerKg": null,
      "ordersCount": 9
    }
  ]
}
```

| Campo | Tipo | Descripción |
|---|---|---|
| `period` | `object` | Rango solicitado. |
| `products` | `array` | Un objeto por cada producto que tiene cajas expedidas en el rango. **Ordenado por `totalRevenue` descendente.** Solo se incluyen productos con al menos una caja disponible expedida en el período. |
| `products[].product.id` | `integer` | ID del producto. |
| `products[].product.name` | `string` | Nombre del producto. |
| `products[].totalWeightKg` | `number` | Kg netos totales expedidos de ese producto en el período (3 decimales). |
| `products[].totalRevenue` | `number` | Revenue ex-IVA (`precio_unitario × kg_netos`). |
| `products[].totalCost` | `number \| null` | Coste total del producto en el período. `null` si ninguna caja tiene coste calculable. |
| `products[].grossMargin` | `number \| null` | `totalRevenue − totalCost`. |
| `products[].marginPercentage` | `number \| null` | `(grossMargin / totalRevenue) × 100`, 2 decimales. |
| `products[].revenuePerKg` | `number \| null` | `totalRevenue / totalWeightKg`, 4 decimales. |
| `products[].costPerKg` | `number \| null` | `totalCost / totalWeightKg`, 4 decimales. `null` si no hay coste o kg. |
| `products[].marginPerKg` | `number \| null` | `grossMargin / totalWeightKg`, 4 decimales. `null` si no hay margen o kg. |
| `products[].ordersCount` | `integer` | Número de pedidos distintos en los que aparece el producto. |

---

## Notas generales

### Qué se entiende por "cajas expedidas"

Los tres endpoints cuentan únicamente cajas marcadas como **disponibles** (`isAvailable = true`), es decir, cajas que no han sido consumidas como input de producción.

### Cálculo de revenue

```
revenue = precio_unitario_planificado (€/kg) × peso_neto_caja (kg)
```

El precio proviene de `plannedProductDetails.unit_price`. Si una caja no tiene línea planificada correspondiente, su revenue es 0 pero sí contribuye al coste.

### Valores `null` en coste

Un coste `null` indica que no existe información de coste para esas cajas: ni precio en la recepción de materia prima ni costes de producción registrados. No es un error, es un dato ausente.

### Filtro `productIds[]` (summary y timeline)

Cuando se envía `productIds[]`, el endpoint **no filtra qué pedidos se incluyen**, sino qué cajas se cuentan dentro de cada pedido. Un pedido con 3 productos distintos, del que solo se pide 1 en el filtro, contribuirá al total únicamente con la parte proporcional de ese producto.

### Formato de fechas

Todas las fechas de entrada deben enviarse en formato `YYYY-MM-DD`. El filtro aplica sobre el campo `load_date` del pedido (fecha de carga/expedición), no sobre `entry_date` ni `created_at`.
