# Inventario - Estadísticas de Stock

## ⚠️ Estado de la API
- **v1**: Obsoleta (no documentada)
- **v2**: Versión activa (este documento)

---

## 📋 Visión General

El módulo de estadísticas de stock proporciona análisis del inventario almacenado, incluyendo totales generales y desglose por especies. Las estadísticas se calculan solo para palets en estado "almacenado" (`state_id = 2`).

**Servicio principal**: `StockStatisticsService`
**Controlador**: `StockStatisticsController`

**Archivos clave**:
- `app/Services/v2/StockStatisticsService.php`
- `app/Http/Controllers/v2/StockStatisticsController.php`

---

## 🔧 Servicio: StockStatisticsService

### Métodos Principales

#### `getTotalStockStats()`

Calcula estadísticas generales del stock almacenado.

**Retorna**:
```php
[
    'totalNetWeight' => float,  // Peso neto total en kg
    'totalPallets' => int,      // Número total de palets almacenados
    'totalBoxes' => int,        // Número total de cajas
    'totalSpecies' => int,      // Especies distintas
    'totalStores' => int,       // Almacenes distintos
]
```

**Lógica**:
1. **Total Peso Neto**: Suma de `boxes.net_weight` de palets almacenados
2. **Total Palets**: Cuenta palets con `state_id = 2`
3. **Total Cajas**: Cuenta `pallet_boxes` de palets almacenados
4. **Total Especies**: Cuenta especies distintas en productos almacenados
5. **Total Almacenes**: Cuenta almacenes distintos con palets almacenados

**Queries utilizadas**:
- `Pallet::stored()` - Filtra palets almacenados
- `joinBoxes()` - JOIN con `pallet_boxes` y `boxes`
- `joinProducts()` - JOIN con productos
- `StoredPallet::stored()` - Filtra palets almacenados con scope

#### `getTotalStockBySpeciesStats()`

Calcula stock total agrupado por especie con porcentajes.

**Retorna**: Array de objetos:
```php
[
    [
        'id' => int,
        'name' => string,
        'totalNetWeight' => float,
        'percentage' => float,
    ],
    // ...
]
```

**Ordenado**: Descendente por `totalNetWeight`

**Lógica**:
1. Obtiene totales por especie usando `getSpeciesTotalsRaw()`
2. Obtiene información de especies desde `Species`
3. Calcula porcentajes respecto al total global
4. Filtra especies con `id !== null`
5. Ordena por peso descendente

#### `getSpeciesTotalsRaw()`

Método auxiliar que obtiene totales por especie usando SQL directo.

**Retorna**: Collection con `species_id` y `totalNetWeight`

**Query**:
```sql
SELECT products.species_id, SUM(boxes.net_weight) as totalNetWeight
FROM pallets
JOIN pallet_boxes ON ...
JOIN boxes ON ...
JOIN products ON ...
WHERE pallets.state_id = 2
GROUP BY products.species_id
```

---

## 📡 Controlador: StockStatisticsController

**Archivo**: `app/Http/Controllers/v2/StockStatisticsController.php`

### Endpoints

#### `totalStockStats()`
```php
GET /v2/statistics/stock/total
```

**Respuesta** (200):
```json
{
    "totalNetWeight": 125000.50,
    "totalPallets": 250,
    "totalBoxes": 3750,
    "totalSpecies": 5,
    "totalStores": 3
}
```

#### `totalStockBySpeciesStats()`
```php
GET /v2/statistics/stock/total-by-species
```

**Respuesta** (200):
```json
[
    {
        "id": 1,
        "name": "Atún rojo",
        "totalNetWeight": 50000.25,
        "percentage": 40.00
    },
    {
        "id": 2,
        "name": "Merluza",
        "totalNetWeight": 37500.50,
        "percentage": 30.00
    },
    ...
]
```

---

## 🔐 Permisos y Autenticación

**Middleware requerido**:
- `auth:sanctum`: Autenticación requerida
- `role:superuser,manager,admin,store_operator`: Roles permitidos

**Rutas**: Todas bajo `/v2/statistics/stock/*`

---

## 📝 Ejemplos de Uso

### Estadísticas Generales
```http
GET /v2/statistics/stock/total
Authorization: Bearer {token}
X-Tenant: empresa1
```

### Stock por Especies
```http
GET /v2/statistics/stock/total-by-species
Authorization: Bearer {token}
X-Tenant: empresa1
```

---

## ⚡ Optimizaciones Implementadas

### 1. Queries SQL Directas

Los métodos usan queries SQL optimizadas con JOINs directos en lugar de cargar modelos:
- `joinBoxes()` y `joinProducts()` para reducir queries
- `selectRaw()` para cálculos agregados

### 2. Scope stored()

El scope `stored()` filtra automáticamente palets con `state_id = 2`:
```php
Pallet::stored()  // WHERE state_id = 2
```

### 3. Carga Eficiente de Especies

En `getTotalStockBySpeciesStats()`:
- Obtiene IDs primero con query directo
- Carga especies solo para IDs necesarios
- Usa `keyBy('id')` para lookup O(1)

---

## 🔍 Relación con Otros Módulos

### Módulo de Inventario
- **Palets**: Usa `Pallet::stored()` para filtrar
- **Cajas**: Calcula desde `boxes` a través de `pallet_boxes`
- **Almacenes**: Cuenta desde `StoredPallet`

### Catálogos
- **Productos**: JOIN con productos para obtener especies
- **Especies**: Obtiene nombres de especies

---

## Observaciones Críticas y Mejoras Recomendadas

### ⚠️ Distinct con count() Puede Ser Incorrecto

1. **distinct() con count()** (`app/Services/v2/StockStatisticsService.php:26-27`)
   - `distinct('products.species_id')->count('products.species_id')`
   - **Líneas**: 26-27
   - **Problema**: En MySQL, `distinct(col)` dentro de `count()` puede no funcionar como se espera
   - **Recomendación**: 
     - Usar `selectRaw('COUNT(DISTINCT products.species_id)')`
     - O `->groupBy()->count()` con subquery

### ⚠️ Scope stored() en StoredPallet

2. **Scope stored() en StoredPallet** (`app/Models/StoredPallet.php:45-50`)
   - El scope hace JOIN con `pallets` y filtra `state_id = 2`
   - **Líneas**: 45-50
   - **Estado**: Correcto, pero podría ser más eficiente si se filtra antes

### ⚠️ Manejo de Especies Desconocidas

3. **Especies Null o No Encontradas** (`app/Services/v2/StockStatisticsService.php:59-62`)
   - Si `species_id` es null o no se encuentra, usa nombre "Desconocida"
   - **Líneas**: 59-62
   - **Estado**: Correcto, pero podría loguearse para debugging

### ⚠️ Filtro de null en getTotalStockBySpeciesStats()

4. **Filtro de null Después de Mapear** (`app/Services/v2/StockStatisticsService.php:65`)
   - Filtra `id !== null` después de mapear
   - **Líneas**: 65
   - **Problema**: Podría filtrarse antes en el query
   - **Recomendación**: Agregar `->whereNotNull('products.species_id')` en el query

### ⚠️ División por Cero en Porcentajes

5. **Validación de totalNetWeight** (`app/Services/v2/StockStatisticsService.php:70-72`)
   - Valida `$totalNetWeight > 0` antes de dividir
   - **Líneas**: 70-72
   - **Estado**: ✅ Correcto, evita división por cero

### ⚠️ Redondeo a 2 Decimales

6. **Redondeo Consistente** (`app/Services/v2/StockStatisticsService.php:35, 63, 71`)
   - Todo se redondea a 2 decimales
   - **Líneas**: 35, 63, 71
   - **Estado**: ✅ Consistente, pero podría ser configurable

### ⚠️ No Hay Caché

7. **Sin Sistema de Caché** (`app/Services/v2/StockStatisticsService.php`)
   - Las estadísticas se calculan en cada request
   - **Problema**: Con muchos datos puede ser lento
   - **Recomendación**: 
     - Implementar caché con TTL corto (ej: 5 minutos)
     - Invalidar cuando cambie stock

### ⚠️ No Hay Filtros Temporales

8. **Solo Muestra Estado Actual** (`app/Services/v2/StockStatisticsService.php`)
   - No permite filtrar por fechas o períodos
   - **Problema**: No hay histórico ni comparativas
   - **Recomendación**: Considerar agregar filtros de fecha si se necesita histórico

### ⚠️ No Filtra por Almacén

9. **Solo Estadísticas Globales** (`app/Services/v2/StockStatisticsService.php`)
   - No permite filtrar por almacén específico
   - **Problema**: No hay estadísticas por almacén
   - **Recomendación**: Agregar parámetro opcional `store_id`

### ⚠️ Posible Duplicación de Cajas

10. **Count de Cajas Puede Duplicar** (`app/Services/v2/StockStatisticsService.php:20-22`)
    - Usa `count('pallet_boxes.id')` pero podría contar duplicados si hay JOIN múltiple
    - **Líneas**: 20-22
    - **Estado**: Debería estar bien con `count()` específico, pero verificar

### ⚠️ Total Stores Usa StoredPallet

11. **Consulta Separada para Stores** (`app/Services/v2/StockStatisticsService.php:30-32`)
    - Usa `StoredPallet::stored()` en lugar de JOIN con pallets
    - **Líneas**: 30-32
    - **Estado**: Correcto pero podría unificarse en una query si se optimiza

---

**Última actualización**: Documentación generada desde código fuente en fecha de generación.

