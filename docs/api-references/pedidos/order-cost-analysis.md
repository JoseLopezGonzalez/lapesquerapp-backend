# Análisis de Costes y Márgenes de Pedido

Documentación de los campos de coste/margen disponibles en el detalle de pedido y del endpoint dedicado de análisis económico.

---

## Campos nuevos en el detalle de pedido

Los endpoints `GET /api/v2/orders/{id}`, `POST /api/v2/orders` y `PUT /api/v2/orders/{id}` devuelven ahora tres campos adicionales en su respuesta (`OrderDetailsResource`):

```json
{
  "totalCost": 1240.50,
  "grossMargin": 380.00,
  "marginPercentage": 23.45
}
```

| Campo | Tipo | Descripción |
|---|---|---|
| `totalCost` | `number \| null` | Suma del coste total de todas las cajas de todos los palets del pedido. `null` si ninguna caja tiene coste calculable. |
| `grossMargin` | `number \| null` | `totalAmount - totalCost`. `null` si `totalCost` es `null`. |
| `marginPercentage` | `number \| null` | `(grossMargin / totalAmount) × 100`, redondeado a 2 decimales. `null` si `totalCost` es `null` o `totalAmount` es 0. |

> `totalAmount` ya existía en la respuesta y representa el importe total de venta con IVA incluido.

---

## Endpoint de análisis detallado

```http
GET /api/v2/orders/{order}/cost-analysis
Authorization: Bearer {token}
X-Tenant: {tenant}
```

### Permisos

Requiere el mismo permiso que `GET /api/v2/orders/{id}` (`view` sobre el pedido).

### Respuesta exitosa `200 OK`

```json
{
  "summary": {
    "totalRevenue": 1620.50,
    "totalCost": 1240.50,
    "grossMargin": 380.00,
    "marginPercentage": 23.45
  },
  "byProductLine": [
    {
      "product": {
        "id": 12,
        "name": "Merluza 400-600 IQF"
      },
      "unitPrice": 5.80,
      "taxRate": 4.0,
      "lineWeightKg": 125.400,
      "lineRevenue": 727.32,
      "lineRevenueWithTax": 756.41,
      "lineCost": 540.00,
      "lineMargin": 187.32,
      "lineMarginPct": 25.76
    }
  ],
  "byPallet": [
    {
      "palletId": 7,
      "totalWeightKg": 200.000,
      "totalCost": 640.00,
      "costPerKg": 3.2000,
      "products": ["Merluza 400-600 IQF", "Merluza 600-800 IQF"]
    }
  ]
}
```

---

### Estructura detallada

#### `summary`

Resumen económico global del pedido.

| Campo | Tipo | Descripción |
|---|---|---|
| `totalRevenue` | `number` | Importe total de venta sin IVA (base imponible). |
| `totalCost` | `number \| null` | Coste total de las cajas disponibles del pedido. `null` si ninguna tiene coste calculable. |
| `grossMargin` | `number \| null` | `totalRevenue - totalCost`. |
| `marginPercentage` | `number \| null` | Porcentaje de margen sobre revenue, 2 decimales. |

---

#### `byProductLine`

Array con una entrada por cada línea de `plannedProductDetails` del pedido. El coste de cada línea se calcula sumando el coste de las cajas disponibles en los palets del pedido cuyo `article_id` coincide con el `product_id` de la línea.

| Campo | Tipo | Descripción |
|---|---|---|
| `product.id` | `number` | ID del producto. |
| `product.name` | `string` | Nombre del producto. |
| `unitPrice` | `number` | Precio de venta por kg (de `plannedProductDetails`). |
| `taxRate` | `number` | Tipo de IVA en porcentaje (de `plannedProductDetails`). |
| `lineWeightKg` | `number` | Peso neto total de las cajas disponibles para este producto en este pedido, en kg (3 decimales). |
| `lineRevenue` | `number` | `unitPrice × lineWeightKg`, sin IVA. |
| `lineRevenueWithTax` | `number` | `lineRevenue × (1 + taxRate / 100)`. |
| `lineCost` | `number \| null` | Suma de costes de todas las cajas disponibles de este producto. `null` si ninguna tiene coste. |
| `lineMargin` | `number \| null` | `lineRevenue - lineCost`. |
| `lineMarginPct` | `number \| null` | `(lineMargin / lineRevenue) × 100`, 2 decimales. |

> Las líneas planificadas sin cajas reales asignadas tendrán `lineWeightKg: 0`, `lineRevenue: 0` y `lineCost: null`.

---

#### `byPallet`

Array con una entrada por cada palet vinculado al pedido. Solo se consideran las cajas marcadas como disponibles (`isAvailable = true`).

| Campo | Tipo | Descripción |
|---|---|---|
| `palletId` | `number` | ID del palet. |
| `totalWeightKg` | `number` | Peso neto de las cajas disponibles del palet, en kg (3 decimales). |
| `totalCost` | `number \| null` | Suma de costes de las cajas disponibles del palet. `null` si ninguna tiene coste. |
| `costPerKg` | `number \| null` | `totalCost / totalWeightKg`, 4 decimales. `null` si no hay coste o peso es 0. |
| `products` | `string[]` | Nombres únicos de los productos presentes en las cajas disponibles del palet. |

---

### Valores `null` en costes

Un valor de coste será `null` cuando ninguna caja del pedido (o de esa línea/palet) tiene un coste calculable. Esto ocurre si:

- El palet no proviene de una recepción con precio definido.
- Las cajas no están asociadas a una producción con costes registrados.

Los campos de margen (`grossMargin`, `lineMargin`, etc.) serán `null` siempre que su coste base sea `null`.
