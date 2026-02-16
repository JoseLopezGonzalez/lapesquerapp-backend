# Estadísticas y Reportes

Documentación de endpoints para obtener estadísticas y reportes del sistema.

## Índice

- [Estadísticas de Pedidos](#estadísticas-de-pedidos)
- [Estadísticas de Stock](#estadísticas-de-stock)
- [Estadísticas de Recepciones](#estadísticas-de-recepciones)
- [Estadísticas de Despachos](#estadísticas-de-despachos)
- [Ventas por Vendedor](#ventas-por-vendedor)
- [Datos para Gráficos](#datos-para-gráficos)

---

## Estadísticas de Pedidos

### Peso Neto Total de Pedidos

```http
GET /api/v2/statistics/orders/total-net-weight
```

#### Headers
```http
X-Tenant: {subdomain}
Authorization: Bearer {access_token}
```

#### Query Parameters (Opcionales)

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| startDate | date | Fecha de inicio (YYYY-MM-DD) |
| endDate | date | Fecha de fin (YYYY-MM-DD) |
| status | string | Estado del pedido |
| customer_id | integer | ID del cliente |

#### Response Exitosa (200)

```json
{
  "data": {
    "total_net_weight": 15000.50,
    "count": 25,
    "period": {
      "start": "2024-01-01",
      "end": "2024-01-31"
    }
  }
}
```

---

### Monto Total de Pedidos

```http
GET /api/v2/statistics/orders/total-amount
```

#### Query Parameters (Opcionales)

Mismos que [Peso Neto Total](#peso-neto-total-de-pedidos).

#### Response Exitosa (200)

```json
{
  "data": {
    "total_amount": 450000.00,
    "count": 25,
    "average_amount": 18000.00,
    "period": {
      "start": "2024-01-01",
      "end": "2024-01-31"
    }
  }
}
```

---

### Ranking de Pedidos

```http
GET /api/v2/statistics/orders/ranking
```

#### Query Parameters (Opcionales)

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| limit | integer | Número de resultados (default: 10) |
| sortBy | string | Campo de ordenamiento (`amount`, `weight`, `count`) |
| order | string | Orden (`asc`, `desc`) |

#### Response Exitosa (200)

```json
{
  "data": [
    {
      "customer_id": 1,
      "customer_name": "Cliente A",
      "total_amount": 150000.00,
      "total_weight": 5000.00,
      "order_count": 10,
      "rank": 1
    },
    {
      "customer_id": 2,
      "customer_name": "Cliente B",
      "total_amount": 120000.00,
      "total_weight": 4000.00,
      "order_count": 8,
      "rank": 2
    }
  ]
}
```

---

### Ventas por Vendedor

```http
GET /api/v2/orders/sales-by-salesperson
```

#### Query Parameters (Opcionales)

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| startDate | date | Fecha de inicio |
| endDate | date | Fecha de fin |

#### Response Exitosa (200)

```json
{
  "data": [
    {
      "salesperson_id": 1,
      "salesperson_name": "Vendedor A",
      "total_amount": 200000.00,
      "total_weight": 6000.00,
      "order_count": 15
    }
  ]
}
```

---

### Datos para Gráfico de Ventas

```http
GET /api/v2/orders/sales-chart-data
```

#### Query Parameters (Opcionales)

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| startDate | date | Fecha de inicio |
| endDate | date | Fecha de fin |
| groupBy | string | Agrupación (`day`, `week`, `month`) |

#### Response Exitosa (200)

```json
{
  "data": [
    {
      "period": "2024-01-01",
      "amount": 15000.00,
      "weight": 500.00,
      "count": 5
    },
    {
      "period": "2024-01-02",
      "amount": 18000.00,
      "weight": 600.00,
      "count": 6
    }
  ],
  "total": {
    "amount": 33000.00,
    "weight": 1100.00,
    "count": 11
  }
}
```

---

### Datos para Gráfico de Transportes

```http
GET /api/v2/orders/transport-chart-data
```

#### Response Exitosa (200)

```json
{
  "data": [
    {
      "transport_id": 1,
      "transport_name": "Transporte A",
      "order_count": 20,
      "total_weight": 8000.00
    }
  ]
}
```

---

## Estadísticas de Stock

### Stock Total

```http
GET /api/v2/statistics/stock/total
```

#### Query Parameters (Opcionales)

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| store_id | integer | ID del almacén |
| species_id | integer | ID de especie |
| product_id | integer | ID del producto |

#### Response Exitosa (200)

```json
{
  "data": {
    "total_quantity": 50000.00,
    "total_boxes": 3500,
    "total_pallets": 500,
    "by_store": [
      {
        "store_id": 1,
        "store_name": "Almacén Principal",
        "quantity": 30000.00,
        "boxes": 2000,
        "pallets": 300
      }
    ]
  }
}
```

---

### Stock Total por Especie

```http
GET /api/v2/statistics/stock/total-by-species
```

#### Response Exitosa (200)

```json
{
  "data": [
    {
      "species_id": 1,
      "species_name": "Especie A",
      "total_quantity": 25000.00,
      "total_boxes": 1500,
      "total_pallets": 250
    },
    {
      "species_id": 2,
      "species_name": "Especie B",
      "total_quantity": 25000.00,
      "total_boxes": 2000,
      "total_pallets": 250
    }
  ]
}
```

---

### Stock Total por Productos

```http
GET /api/v2/stores/total-stock-by-products
```

#### Query Parameters (Opcionales)

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| store_id | integer | ID del almacén |
| species_id | integer | ID de especie |

#### Response Exitosa (200)

```json
{
  "data": [
    {
      "product_id": 1,
      "product_name": "Producto A",
      "total_quantity": 10000.00,
      "total_boxes": 500,
      "stores": [
        {
          "store_id": 1,
          "store_name": "Almacén Principal",
          "quantity": 7000.00,
          "boxes": 350
        }
      ]
    }
  ]
}
```

---

## Estadísticas de Recepciones

### Datos para Gráfico de Recepciones

```http
GET /api/v2/raw-material-receptions/reception-chart-data
```

#### Query Parameters (Opcionales)

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| startDate | date | Fecha de inicio |
| endDate | date | Fecha de fin |
| groupBy | string | Agrupación (`day`, `week`, `month`) |

#### Response Exitosa (200)

```json
{
  "data": [
    {
      "period": "2024-01-01",
      "count": 5,
      "total_weight": 2000.00,
      "total_amount": 60000.00
    }
  ],
  "total": {
    "count": 150,
    "weight": 60000.00,
    "amount": 1800000.00
  }
}
```

---

## Estadísticas de Despachos

### Datos para Gráfico de Despachos

```http
GET /api/v2/cebo-dispatches/dispatch-chart-data
```

#### Query Parameters (Opcionales)

Mismos que [Datos para Gráfico de Recepciones](#datos-para-gráfico-de-recepciones).

#### Response Exitosa (200)

```json
{
  "data": [
    {
      "period": "2024-01-01",
      "count": 3,
      "total_weight": 1500.00,
      "total_amount": 45000.00
    }
  ],
  "total": {
    "count": 90,
    "weight": 45000.00,
    "amount": 1350000.00
  }
}
```

---

## Respuestas Erróneas

### Error de Autenticación (401)

```json
{
  "message": "No autenticado."
}
```

### Error de Permisos (403)

```json
{
  "message": "No tiene permisos para acceder a estas estadísticas."
}
```

---

## Headers Requeridos

Todos los endpoints requieren:

```http
X-Tenant: {subdomain}
Authorization: Bearer {access_token}
```

---

## Notas

- Todas las fechas deben estar en formato `YYYY-MM-DD`
- Los valores numéricos pueden ser decimales
- Las estadísticas pueden estar sujetas a caché para mejor rendimiento
- Los parámetros de fecha son opcionales, pero recomendados para obtener resultados precisos

