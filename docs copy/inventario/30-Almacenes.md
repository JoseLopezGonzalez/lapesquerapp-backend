# Inventario - Almacenes (Stores)

## ⚠️ Estado de la API
- **v1**: Obsoleta (no documentada)
- **v2**: Versión activa (este documento)

---

## 📋 Visión General

El modelo `Store` representa un **almacén** donde se almacenan palets con cajas de productos. Los almacenes tienen capacidad, temperatura de conservación y un mapa de posiciones para ubicar los palets físicamente.

**Concepto clave**: Los almacenes no almacenan directamente cajas, sino **palets** que contienen cajas. La relación se hace a través de la tabla intermedia `stored_pallets` que también guarda la posición del palet en el almacén.

**Archivo del modelo**: `app/Models/Store.php`

---

## 🗄️ Estructura de Base de Datos

### Tabla: `stores`

**Migración**: `database/migrations/companies/2023_08_09_145720_create_stores_table.php`

**Campos**:

| Campo | Tipo | Nullable | Descripción |
|-------|------|----------|-------------|
| `id` | bigint | NO | ID único del almacén |
| `name` | string | NO | Nombre del almacén |
| `temperature` | decimal(4,2) | NO | Temperatura de conservación |
| `capacity` | decimal(9,2) | NO | Capacidad del almacén (en kg o unidades) |
| `map` | json | YES | Mapa de posiciones del almacén |
| `created_at` | timestamp | NO | Fecha de creación |
| `updated_at` | timestamp | NO | Fecha de última actualización |

**Índices**:
- `id` (primary key)

**Nota**: El campo `category_id` está en `fillable` pero **no existe en la migración**. Ver observaciones críticas.

---

## 📦 Modelo Eloquent

### Fillable Attributes

```php
protected $fillable = [
    'name',
    'category_id',  // ⚠️ Campo que no existe en BD
    'temperature',
    'capacity',
    'map',
];
```

**Nota**: `category_id` está en fillable pero no está en la tabla según la migración.

### Traits

- `UsesTenantConnection`: Usa conexión tenant (multi-tenant)
- `HasFactory`: Para testing y seeders

---

## 🔗 Relaciones

### 1. `categoria()` - Categoría
```php
public function categoria()
{
    return $this->belongsTo(ArticleCategory::class, 'category_id');
}
```
- **Estado**: ⚠️ Relación definida pero el campo `category_id` no existe en BD
- Relación muchos-a-uno con `ArticleCategory`

### 2. `pallets()` - Palets Almacenados (StoredPallet)
```php
public function pallets()
{
    return $this->hasMany(StoredPallet::class, 'store_id');
}
```
- Relación uno-a-muchos con `StoredPallet`
- **Nota**: Retorna `StoredPallet`, no `Pallet` directamente

### 3. `palletsV2()` - Palets (Many-to-Many)
```php
public function palletsV2()
{
    return $this->belongsToMany(Pallet::class, 'stored_pallets', 'store_id', 'pallet_id')
        ->withPivot('position');
}
```
- Relación muchos-a-muchos con `Pallet` a través de `stored_pallets`
- Incluye el campo `position` del pivot

**Diferencia entre `pallets()` y `palletsV2()`**:
- `pallets()`: Retorna modelos `StoredPallet` (tabla intermedia)
- `palletsV2()`: Retorna modelos `Pallet` directamente con posición en pivot

---

## 🔢 Accessors (Atributos Calculados)

### `getNetWeightPalletsAttribute()`

Calcula el peso neto total de todos los palets almacenados.

```php
return $this->pallets->reduce(function ($carry, $pallet) {
    return $carry + $pallet->pallet->netWeight;
}, 0);
```

**Lógica**: Suma el `netWeight` de cada `Pallet` a través de `StoredPallet`.

### `getNetWeightBoxesAttribute()`

**Estado**: ⚠️ **NO IMPLEMENTADO** - Retorna siempre `0`

```php
public function getNetWeightBoxesAttribute()
{
    //Implementar...
    return 0;
}
```

### `getNetWeightBigBoxesAttribute()`

**Estado**: ⚠️ **NO IMPLEMENTADO** - Retorna siempre `0`

```php
public function getNetWeightBigBoxesAttribute()
{
    //Implementar...
    return 0;
}
```

### `getTotalNetWeightAttribute()`

Peso neto total del almacén.

```php
return $this->netWeightPallets + $this->netWeightBigBoxes + $this->netWeightBoxes;
```

**Nota**: Actualmente solo cuenta `netWeightPallets` porque los otros retornan 0.

---

## 📡 Controlador

**Archivo**: `app/Http/Controllers/v2/StoreController.php`

### Métodos del Controlador

#### `index(Request $request)` - Listar Almacenes
```php
GET /v2/stores
```

**Query parameters**:
- `id` (optional): Filtrar por ID
- `ids` (optional): Filtrar por múltiples IDs (array)
- `name` (optional): Buscar por nombre (LIKE)
- `perPage` (optional): Elementos por página (default: 12)

**Orden**: Por nombre ascendente

**Respuesta**: Collection paginada de `StoreResource`

#### `store(Request $request)` - Crear Almacén
```php
POST /v2/stores
```

**Validación**:
```php
[
    'name' => 'required|string|min:3|max:255',
    'temperature' => 'required|string|max:255',
    'capacity' => 'required|numeric|min:0',
]
```

**Comportamiento**:
- Crea el almacén con un mapa por defecto (`getDefaultMap()`)
- El mapa se guarda como JSON

**Respuesta** (201):
```json
{
    "message": "Almacén creado correctamente",
    "data": { ... }
}
```

#### `show(string $id)` - Mostrar Almacén
```php
GET /v2/stores/{id}
```

**Respuesta**: `StoreDetailsResource` con información completa incluyendo palets

#### `update(Request $request, string $id)` - Actualizar Almacén
```php
PUT /v2/stores/{id}
```

**Validación**: Igual que `store()`

**Comportamiento**: Actualiza campos excepto `map` (no se puede actualizar desde aquí)

**Respuesta**:
```json
{
    "message": "Almacén actualizado correctamente",
    "data": { ... }
}
```

#### `destroy(string $id)` - Eliminar Almacén
```php
DELETE /v2/stores/{id}
```

**Comportamiento**: Elimina el almacén (cascade elimina `stored_pallets`)

**Respuesta**:
```json
{
    "message": "Almacén eliminado correctamente."
}
```

#### `deleteMultiple(Request $request)` - Eliminar Múltiples Almacenes
```php
DELETE /v2/stores
```

**Request body**:
```json
{
    "ids": [1, 2, 3]
}
```

**Validación**:
```php
[
    'ids' => 'required|array',
    'ids.*' => 'integer|exists:tenant.stores,id',
]
```

#### `options()` - Opciones para Select
```php
GET /v2/stores/options
```

**Respuesta**: Array simple con `id` y `name`

#### `totalStockByProducts()` - Stock Total por Productos
```php
GET /v2/stores/total-stock-by-products
```

**Comportamiento**:
- Carga todos los `StoredPallet` y todos los `Product`
- Recorre palets y cajas para calcular peso neto por producto
- Retorna array con productos, peso total y porcentaje

**Respuesta**:
```json
[
    {
        "id": 1,
        "name": "Filetes de atún",
        "total_kg": 1250.50,
        "percentage": 25.50
    },
    ...
]
```

**Ordenado**: Descendente por `total_kg`

**Nota**: ⚠️ Este método es **muy ineficiente** (ver observaciones críticas).

---

## 📄 API Resources

### StoreResource

**Archivo**: `app/Http/Resources/v2/StoreResource.php`

Usa el método `toArrayAssoc()` del modelo.

### StoreDetailsResource

**Archivo**: `app/Http/Resources/v2/StoreDetailsResource.php`

**Campos expuestos**:
```json
{
    "id": 1,
    "name": "Almacén Principal",
    "temperature": "-18.00",
    "capacity": 50000.00,
    "netWeightPallets": 12500.50,
    "totalNetWeight": 12500.50,
    "content": {
        "pallets": [...],
        "boxes": [],
        "bigBoxes": []
    },
    "map": { ... }
}
```

**Nota**: Usa `palletsV2()` para obtener palets con posición.

---

## 🗺️ Mapa del Almacén

El campo `map` almacena un JSON con la estructura del almacén:

**Estructura por defecto** (`getDefaultMap()`):
```json
{
    "posiciones": [
        {
            "id": 1,
            "nombre": "U1",
            "x": 40,
            "y": 40,
            "width": 460,
            "height": 238,
            "tipo": "center",
            "nameContainer": {
                "x": 0,
                "y": 0,
                "width": 230,
                "height": 180
            }
        }
    ],
    "elementos": {
        "fondos": [
            {
                "x": 0,
                "y": 0,
                "width": 3410,
                "height": 900
            }
        ],
        "textos": []
    }
}
```

**Nota**: El mapa se crea automáticamente al crear el almacén, pero no hay endpoints para actualizarlo.

---

## 🔐 Permisos y Autenticación

**Middleware requerido**:
- `auth:sanctum`: Autenticación requerida
- `role:superuser,manager,admin,store_operator`: Roles permitidos

**Rutas**: Todas bajo `/v2/stores/*`

---

## 📝 Ejemplos de Uso

### Crear un Almacén
```http
POST /v2/stores
Content-Type: application/json
Authorization: Bearer {token}
X-Tenant: empresa1

{
    "name": "Almacén Principal",
    "temperature": "-18",
    "capacity": 50000
}
```

### Listar Almacenes
```http
GET /v2/stores?name=Principal&perPage=10
Authorization: Bearer {token}
X-Tenant: empresa1
```

### Obtener Stock por Productos
```http
GET /v2/stores/total-stock-by-products
Authorization: Bearer {token}
X-Tenant: empresa1
```

---

## 🔄 Tabla Intermedia: stored_pallets

**Archivo del modelo**: `app/Models/StoredPallet.php`

**Migración**: `database/migrations/companies/2023_08_10_084355_create_stored_pallets_table.php`

**Campos**:
- `id`: ID único
- `store_id`: FK a `stores`
- `pallet_id`: FK a `pallets`
- `position`: Posición del palet en el almacén (nullable)
- `created_at`, `updated_at`

**Constraints**:
- Foreign keys con `onDelete('cascade')`

**Scope**: `stored()` - Filtra palets con `state_id = 2` (almacenado)

---

## Observaciones Críticas y Mejoras Recomendadas

### ⚠️ Campo category_id No Existe

1. **category_id en Fillable Pero No en BD** (`app/Models/Store.php:17`)
   - Campo `category_id` está en fillable y hay relación `categoria()`
   - Pero **no existe en la migración** de la tabla
   - **Líneas**: 17, 20-23
   - **Problema**: La relación y el fillable no funcionarán correctamente
   - **Recomendación**: 
     - Agregar migración para `category_id` si se necesita
     - O eliminar del fillable y la relación si no se usa

### ⚠️ Métodos No Implementados

2. **getNetWeightBoxesAttribute() Vacío** (`app/Models/Store.php:68-72`)
   - Método retorna siempre `0`
   - **Líneas**: 68-72
   - **Problema**: El cálculo de `totalNetWeight` no es completo
   - **Recomendación**: Implementar o eliminar si no se va a usar

3. **getNetWeightBigBoxesAttribute() Vacío** (`app/Models/Store.php:75-79`)
   - Método retorna siempre `0`
   - **Líneas**: 75-79
   - **Problema**: Igual que el anterior
   - **Recomendación**: Implementar o eliminar

### ⚠️ Código Comentado

4. **Relación Comentada** (`app/Models/Store.php:26-29`)
   - Hay una relación `pallets()` comentada que usa `Pallet` directamente
   - **Líneas**: 26-29
   - **Problema**: Código muerto que confunde
   - **Recomendación**: Eliminar código comentado

5. **Código Comentado en getNetWeightPalletsAttribute()** (`app/Models/Store.php:54-64`)
   - Hay múltiples versiones comentadas del cálculo
   - **Líneas**: 54-64
   - **Problema**: Código muerto
   - **Recomendación**: Limpiar código comentado

### ⚠️ Performance: Método totalStockByProducts() Muy Ineficiente

6. **Carga Todos los Datos en Memoria** (`app/Http/Controllers/v2/StoreController.php:172-215`)
   - Carga todos los `StoredPallet` y todos los `Product` en memoria
   - Hace loops anidados (productos → palets → cajas)
   - **Líneas**: 174-215
   - **Problema**: Con muchos datos puede ser extremadamente lento
   - **Recomendación**: 
     - Optimizar con queries SQL directas
     - Usar agregaciones en base de datos
     - Considerar caché para resultados

### ⚠️ No Se Puede Actualizar Map

7. **Map No Se Actualiza en Update()** (`app/Http/Controllers/v2/StoreController.php:117-133`)
   - El método `update()` no permite actualizar el campo `map`
   - **Líneas**: 117-133
   - **Problema**: No hay forma de actualizar el mapa del almacén
   - **Recomendación**: Agregar endpoint específico para actualizar mapa o permitir en update

### ⚠️ Validación de Temperature

8. **Temperature Como String** (`app/Http/Controllers/v2/StoreController.php:90, 123`)
   - Se valida como `string` pero en BD es `decimal(4,2)`
   - **Líneas**: 90, 123
   - **Problema**: Inconsistencia de tipos
   - **Recomendación**: Validar como `numeric` o `decimal`

### ⚠️ No Valida Capacidad vs Peso Actual

9. **No Valida Superación de Capacidad** (`app/Http/Controllers/v2/StoreController.php:86-102`)
   - No valida si al crear/actualizar se supera la capacidad
   - **Problema**: Puede crear almacenes con capacidad menor al peso actual
   - **Recomendación**: Validar que `capacity >= totalNetWeight` antes de actualizar

### ⚠️ Eliminación Sin Validar Contenido

10. **Puede Eliminar Almacén con Palets** (`app/Http/Controllers/v2/StoreController.php:139-145`)
    - No valida si hay palets almacenados antes de eliminar
    - **Líneas**: 139-145
    - **Problema**: Eliminación en cascade puede ser peligrosa
    - **Recomendación**: 
      - Validar que no haya palets antes de eliminar
      - O mover palets a otro almacén antes de eliminar

### ⚠️ Relaciones Duplicadas

11. **Dos Métodos Para Palets** (`app/Models/Store.php:32-42`)
    - Hay `pallets()` que retorna `StoredPallet`
    - Y `palletsV2()` que retorna `Pallet` directamente
    - **Líneas**: 32-42
    - **Problema**: Puede causar confusión sobre cuál usar
    - **Recomendación**: 
      - Documentar claramente cuándo usar cada uno
      - O unificar en un solo método

### ⚠️ Método getDefaultMap() Hardcodeado

12. **Mapa Por Defecto Fijo** (`app/Http/Controllers/v2/StoreController.php:18-50`)
    - El mapa por defecto es siempre el mismo
    - **Líneas**: 18-50
    - **Estado**: Puede ser intencional, pero limita flexibilidad
    - **Recomendación**: Considerar si debe ser configurable

---

**Última actualización**: Documentación generada desde código fuente en fecha de generación.

