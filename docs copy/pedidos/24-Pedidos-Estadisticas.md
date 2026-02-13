# Pedidos - Estadísticas y Reportes

## ⚠️ Estado de la API
- **v1**: Obsoleta (no documentada)
- **v2**: Versión activa (este documento)

---

## 📋 Visión General

El módulo de estadísticas proporciona análisis comparativos y rankings de pedidos basados en peso neto, importes y otros indicadores. Las estadísticas comparan períodos con el mismo rango del año anterior y permiten filtrado por especies.

**Servicio principal**: `OrderStatisticsService`
**Controlador**: `OrderStatisticsController`

**Archivos clave**:
- `app/Services/v2/OrderStatisticsService.php`
- `app/Http/Controllers/v2/OrderStatisticsController.php`

---

## 🔧 Servicio: OrderStatisticsService

### Métodos Principales

#### `getNetWeightStatsComparedToLastYear()`

Calcula estadísticas de peso neto comparadas con el año anterior.

**Parámetros**:
- `$dateFrom`: Fecha inicio (YYYY-MM-DD)
- `$dateTo`: Fecha fin (YYYY-MM-DD)
- `$speciesId` (opcional): ID de especie para filtrar

**Retorna**:
```php
[
    'value' => float,              // Total peso neto período actual
    'comparisonValue' => float,    // Total peso neto mismo período año anterior
    'percentageChange' => float|null, // Cambio porcentual
    'range' => [
        'from' => string,
        'to' => string,
        'fromPrev' => string,
        'toPrev' => string,
    ]
]
```

**Lógica**:
1. Calcula peso neto total del período actual usando `calculateTotalNetWeight()`
2. Calcula peso neto total del mismo período del año anterior
3. Calcula cambio porcentual

**Método auxiliar**: `calculateTotalNetWeight()`
- Usa `Order::joinBoxesAndArticles()` para unir con boxes y articles
- Filtra por especie si se proporciona
- Suma `boxes.net_weight`

#### `getAmountStatsComparedToLastYear()`

Calcula estadísticas de importe total comparadas con el año anterior.

**Parámetros**: Igual que `getNetWeightStatsComparedToLastYear()`

**Retorna**:
```php
[
    'value' => float,              // Total período actual
    'subtotal' => float,           // Subtotal período actual
    'tax' => float,                // Impuesto período actual
    'comparisonValue' => float,    // Total año anterior
    'comparisonSubtotal' => float, // Subtotal año anterior
    'comparisonTax' => float,      // Impuesto año anterior
    'percentageChange' => float|null,
    'range' => [...]
]
```

**Lógica**:
1. Usa `calculateAmountDetails()` que hace JOIN directo con SQL
2. Calcula desde `order_planned_product_details` (productos planificados)
3. Aplica impuestos con `COALESCE(taxes.rate, 0)`

**Método auxiliar**: `calculateAmountDetails()`
- Optimizado con SQL directo (no carga modelos)
- Calcula subtotal, total con impuesto y diferencia (tax)

#### `getOrderRankingStats()`

Obtiene ranking de pedidos agrupado por cliente, país o producto.

**Parámetros**:
- `$groupBy`: `'client'`, `'country'` o `'product'`
- `$valueType`: `'totalAmount'` o `'totalQuantity'`
- `$dateFrom`: Fecha inicio (formato: Y-m-d H:i:s)
- `$dateTo`: Fecha fin (formato: Y-m-d H:i:s)
- `$speciesId` (opcional): ID de especie

**Retorna**: Collection de arrays:
```php
[
    ['name' => 'Cliente A', 'value' => 12345.67],
    ['name' => 'Cliente B', 'value' => 9876.54],
    // ...
]
```

**Ordenado**: Descendente por valor

**Lógica**:
- Usa SQL directo con JOINs
- Agrupa por campo según `$groupBy`
- Ordena por valor descendente

#### `getSalesChartData()`

Obtiene datos de ventas agrupados por período temporal (día, semana o mes).

**Parámetros**:
- `$dateFrom`: Fecha inicio (formato: Y-m-d H:i:s)
- `$dateTo`: Fecha fin (formato: Y-m-d H:i:s)
- `$valueType`: `'amount'` o `'quantity'`
- `$groupBy`: `'day'`, `'week'` o `'month'`
- `$speciesId` (opcional): ID de especie
- `$familyId` (opcional): ID de familia
- `$categoryId` (opcional): ID de categoría

**Retorna**: Collection de arrays:
```php
[
    ['date' => '2025-01-01', 'value' => 1234.56],
    ['date' => '2025-01-02', 'value' => 2345.67],
    // ...
]
```

**Lógica**:
- Si `valueType == 'quantity'`: Usa boxes reales (pallets → boxes → net_weight)
- Si `valueType == 'amount'`: Usa productos planificados (order_planned_product_details)
- Agrupa por fecha según `$groupBy` usando `DATE_FORMAT`

**Formato de fechas**:
- `day`: `YYYY-MM-DD`
- `week`: `YYYY-W##` (ej: `2025-W03`)
- `month`: `YYYY-MM`

### Métodos Auxiliares

#### `prepareDateRangeAndPrevious()`

Prepara rangos de fechas actual y año anterior.

#### `compareTotals()`

Calcula cambio porcentual entre dos valores.
- Retorna `null` si el valor anterior es 0 (evita división por cero)

---

## 📡 Controlador: OrderStatisticsController

**Archivo**: `app/Http/Controllers/v2/OrderStatisticsController.php`

### Endpoints

#### `totalNetWeightStats()`
```php
GET /v2/statistics/orders/total-net-weight
```

**Query parameters**:
- `dateFrom` (required): Fecha inicio (YYYY-MM-DD)
- `dateTo` (required): Fecha fin (YYYY-MM-DD)
- `speciesId` (optional): ID de especie

**Respuesta** (200):
```json
{
    "value": 12500.50,
    "comparisonValue": 11800.00,
    "percentageChange": 5.93,
    "range": {
        "from": "2025-01-01 00:00:00",
        "to": "2025-01-31 23:59:59",
        "fromPrev": "2024-01-01 00:00:00",
        "toPrev": "2024-01-31 23:59:59"
    }
}
```

#### `totalAmountStats()`
```php
GET /v2/statistics/orders/total-amount
```

**Query parameters**: Igual que `totalNetWeightStats()`

**Nota**: Aumenta límites de tiempo y memoria para consultas pesadas:
- `set_time_limit(300)` (5 minutos)
- `ini_set('memory_limit', '512M')` (512MB)

**Respuesta** (200):
```json
{
    "value": 125000.75,
    "subtotal": 113636.14,
    "tax": 11364.61,
    "comparisonValue": 118000.50,
    "comparisonSubtotal": 107272.27,
    "comparisonTax": 10728.23,
    "percentageChange": 5.93,
    "range": { ... }
}
```

#### `orderRankingStats()`
```php
GET /v2/statistics/orders/ranking
```

**Query parameters**:
- `groupBy` (required): `client`, `country` o `product`
- `valueType` (required): `totalAmount` o `totalQuantity`
- `dateFrom` (required): Fecha inicio (YYYY-MM-DD)
- `dateTo` (required): Fecha fin (YYYY-MM-DD)
- `speciesId` (optional): ID de especie

**Nota**: Aumenta límites de tiempo y memoria.

**Respuesta** (200):
```json
[
    { "name": "Congelados Brisamar", "value": 12830.50 },
    { "name": "Frostmar S.L.", "value": 9740.00 },
    ...
]
```

#### `salesChartData()`
```php
GET /v2/orders/sales-chart-data
```

**Query parameters**:
- `dateFrom` (required): Fecha inicio (YYYY-MM-DD)
- `dateTo` (required): Fecha fin (YYYY-MM-DD)
- `valueType` (required): `amount` o `quantity`
- `groupBy` (optional): `day`, `week` o `month` (default: `day`)
- `speciesId` (optional): ID de especie
- `familyId` (optional): ID de familia
- `categoryId` (optional): ID de categoría

**Respuesta** (200):
```json
[
    { "date": "2025-01-01", "value": 1234.56 },
    { "date": "2025-01-02", "value": 2345.67 },
    ...
]
```

---

## 🔍 Endpoints Adicionales en OrderController

### `salesBySalesperson()`
```php
GET /v2/orders/sales-by-salesperson
```

**Query parameters**:
- `dateFrom` (required): Fecha inicio (YYYY-MM-DD)
- `dateTo` (required): Fecha fin (YYYY-MM-DD)

**Respuesta** (200):
```json
[
    { "name": "Juan Pérez", "quantity": 12500.50 },
    { "name": "María García", "quantity": 9800.00 },
    ...
]
```

**Lógica**:
- Agrupa por vendedor (`salesperson`)
- Suma peso neto desde palets y boxes
- Ordena por cantidad descendente

### `transportChartData()`
```php
GET /v2/orders/transport-chart-data
```

**Query parameters**:
- `dateFrom` (required): Fecha inicio (YYYY-MM-DD)
- `dateTo` (required): Fecha fin (YYYY-MM-DD)

**Respuesta** (200):
```json
[
    { "name": "Transportes ABC", "netWeight": 15000.50 },
    { "name": "Logística XYZ", "netWeight": 12000.00 },
    ...
]
```

**Lógica**:
- Agrupa por transportista
- Suma peso neto total de pedidos
- Filtra solo pedidos con `transport_id` no null

---

## 🔐 Permisos y Autenticación

**Middleware requerido**:
- `auth:sanctum`: Autenticación requerida
- `role:superuser,manager,admin,store_operator`: Roles permitidos

**Rutas**: Todas bajo `/v2/statistics/orders/*` y `/v2/orders/*-chart-data`

---

## 📝 Ejemplos de Uso

### Estadísticas de Peso Neto
```http
GET /v2/statistics/orders/total-net-weight?dateFrom=2025-01-01&dateTo=2025-01-31&speciesId=5
Authorization: Bearer {token}
X-Tenant: empresa1
```

### Ranking de Clientes por Importe
```http
GET /v2/statistics/orders/ranking?groupBy=client&valueType=totalAmount&dateFrom=2025-01-01&dateTo=2025-01-31
Authorization: Bearer {token}
X-Tenant: empresa1
```

### Gráfico de Ventas por Día
```http
GET /v2/orders/sales-chart-data?dateFrom=2025-01-01&dateTo=2025-01-31&valueType=amount&groupBy=day&speciesId=5
Authorization: Bearer {token}
X-Tenant: empresa1
```

---

## ⚡ Optimizaciones Implementadas

### 1. Queries SQL Directas

Los métodos `calculateAmountDetails()` y `getOrderRankingStats()` usan SQL directo en lugar de cargar modelos en memoria:

```php
$query->selectRaw('SUM(...) as total')
    ->first();
```

**Ventaja**: Más rápido y usa menos memoria.

### 2. Límites de Tiempo y Memoria

Los métodos `totalAmountStats()` y `orderRankingStats()` aumentan límites:
```php
set_time_limit(300); // 5 minutos
ini_set('memory_limit', '512M'); // 512MB
```

**Razón**: Consultas pueden ser pesadas con muchos pedidos.

### 3. Comparación con Año Anterior

El método `prepareDateRangeAndPrevious()` calcula automáticamente el mismo rango del año anterior:
```php
$fromPrev = date('Y-m-d H:i:s', strtotime($from . ' -1 year'));
```

---

## Observaciones Críticas y Mejoras Recomendadas

### ⚠️ Código Comentado

1. **Métodos Comentados** (`app/Services/v2/OrderStatisticsService.php:70-88`)
   - `calculateTotalAmount()` y `calculateSubtotalAmount()` están comentados
   - **Líneas**: 70-88
   - **Problema**: Código muerto que confunde
   - **Recomendación**: Eliminar si no se van a usar

### ⚠️ Inconsistencia en Filtrado

2. **Filtrado por Especie Diferente** (`app/Services/v2/OrderStatisticsService.php`)
   - `calculateTotalNetWeight()` filtra por `articles.species_id`
   - `calculateAmountDetails()` filtra por `products.species_id`
   - **Líneas**: 29-34, 99-101
   - **Problema**: Pueden dar resultados diferentes si hay inconsistencia entre articles y products
   - **Recomendación**: Asegurar que la relación article-product-species sea consistente

### ⚠️ Join Complejo en getSalesChartData

3. **Join con products.id = articles.id** (`app/Services/v2/OrderStatisticsService.php:221`)
   - Asume relación 1:1 entre `products.id` y `articles.id`
   - **Líneas**: 221
   - **Problema**: Si la relación cambia, el query fallará
   - **Recomendación**: Verificar que la relación sea realmente 1:1 o ajustar el join

### ⚠️ Cálculo de Tax

4. **Tax Calculado como Diferencia** (`app/Services/v2/OrderStatisticsService.php:111`)
   - `$tax = $total - $subtotal`
   - **Líneas**: 111
   - **Estado**: Correcto matemáticamente, pero si hay redondeos puede haber pequeñas diferencias
   - **Recomendación**: Considerar calcular tax directamente si se necesita más precisión

### ⚠️ Manejo de Nulos

5. **Valores Nulos en Ranking** (`app/Services/v2/OrderStatisticsService.php:182`)
   - Usa `$item->name ?? 'Sin nombre'` para campos nulos
   - **Líneas**: 182
   - **Estado**: Correcto, pero puede ocultar problemas de datos
   - **Recomendación**: Considerar loguear cuando hay valores nulos

### ⚠️ Performance en salesBySalesperson

6. **Carga Todos los Pedidos** (`app/Http/Controllers/v2/OrderController.php:453-481`)
   - Carga todos los pedidos con relaciones (`with(['salesperson', 'pallets.boxes.box'])`)
   - **Líneas**: 453-481
   - **Problema**: Con muchos pedidos puede ser lento
   - **Recomendación**: Optimizar con SQL directo similar a otros métodos

### ⚠️ Validación de Fechas

7. **No Valida Que dateTo >= dateFrom** (`app/Http/Controllers/v2/OrderStatisticsController.php`)
   - Solo valida formato, no orden
   - **Problema**: Puede recibir rangos inválidos
   - **Recomendación**: Agregar validación `dateTo >= dateFrom`

### ⚠️ Formato de Fecha en getSalesChartData

8. **Formato de Semana Puede Confundir** (`app/Services/v2/OrderStatisticsService.php:267`)
   - Usa `DATE_FORMAT(..., '%Y-%u')` para semanas
   - **Líneas**: 267
   - **Problema**: El formato `YYYY-W##` puede no ser estándar
   - **Recomendación**: Documentar formato o usar ISO 8601 (`YYYY-W##`)

### ⚠️ Cambio Porcentual Null

9. **Retorna Null Si Anterior Es Cero** (`app/Services/v2/OrderStatisticsService.php:40-41`)
   - `compareTotals()` retorna `null` si valor anterior es 0
   - **Líneas**: 40-41
   - **Estado**: Correcto para evitar división por cero
   - **Recomendación**: Documentar comportamiento en respuesta API

---

**Última actualización**: Documentación generada desde código fuente en fecha de generación.

