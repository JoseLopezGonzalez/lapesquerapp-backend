# Recepciones y Despachos - Recepciones de Materia Prima (Raw Material Receptions)

## ⚠️ Estado de la API
- **v1**: Obsoleta (no documentada)
- **v2**: Versión activa (este documento)

---

## 📋 Visión General

El modelo `RawMaterialReception` representa una **recepción de materia prima** de un proveedor. Registra qué productos fueron recibidos, en qué cantidades (peso neto), y de qué proveedor. Permite hacer seguimiento de las entradas de materia prima al sistema.

**Archivo del modelo**: `app/Models/RawMaterialReception.php`

**Relación con Inventario**: Las recepciones **crean automáticamente palets y cajas** en inventario. Los palets son la unidad mínima almacenable según la lógica del ERP. Cada recepción genera palets con sus cajas correspondientes, estableciendo una trazabilidad completa desde la recepción hasta el inventario físico.

---

## 🗄️ Estructura de Base de Datos

### Tabla: `raw_material_receptions`

**Migración base**: `database/migrations/companies/2024_05_29_160225_create_raw_material_receptions_table.php`

**Migraciones adicionales**:
- `2025_04_25_093016_add_declared_total_amount_to_raw_material_receptions_table.php` - Agrega `declared_total_amount`
- `2025_04_25_101036_add_declared_total_net_weight_to_raw_material_receptions_table.php` - Agrega `declared_total_net_weight`
- `2025_12_09_181100_add_creation_mode_to_raw_material_receptions_table.php` - Agrega `creation_mode`

**Campos**:

| Campo | Tipo | Nullable | Descripción |
|-------|------|----------|-------------|
| `id` | bigint | NO | ID único de la recepción |
| `supplier_id` | bigint | NO | FK a `suppliers` - Proveedor |
| `date` | date | NO | Fecha de recepción |
| `declared_total_amount` | decimal(10,2) | YES | Importe total declarado (agregado después) |
| `declared_total_net_weight` | decimal(10,2) | YES | Peso neto total declarado (agregado después) |
| `notes` | text | YES | Notas adicionales |
| `creation_mode` | string(20) | YES | Modo de creación: `'lines'` (por líneas) o `'pallets'` (por palets) |
| `created_at` | timestamp | NO | Fecha de creación |
| `updated_at` | timestamp | NO | Fecha de última actualización |

**Índices**:
- `id` (primary key)
- Foreign key a `suppliers`

**Constraints**:
- `supplier_id` → `suppliers.id` (onDelete: cascade)

### Tabla: `raw_material_reception_products`

**Migración**: `database/migrations/companies/2024_05_29_160329_create_raw_material_reception_products_table.php`

**Campos**:

| Campo | Tipo | Nullable | Descripción |
|-------|------|----------|-------------|
| `id` | bigint | NO | ID único |
| `reception_id` | bigint | NO | FK a `raw_material_receptions` |
| `product_id` | bigint | NO | FK a `products` |
| `lot` | string | YES | Lote del producto (agregado en migración 2025_12_11) |
| `net_weight` | decimal(8,2) | NO | Peso neto recibido |
| `price` | decimal(10,2) | YES | Precio unitario por kg |
| `created_at` | timestamp | NO | Fecha de creación |
| `updated_at` | timestamp | NO | Fecha de última actualización |

**Índices**:
- `id` (primary key)
- Foreign keys a `raw_material_receptions` y `products`

**Constraints**:
- `reception_id` → `raw_material_receptions.id` (onDelete: cascade)
- `product_id` → `products.id` (onDelete: cascade)

**⚠️ Notas**: 
- El campo `price` se agregó en migración `2025_04_25_094428_add_price_to_raw_material_reception_products_table.php`
- El campo `lot` se agregó en migración `2025_12_11_093042_add_lot_to_raw_material_reception_products_table.php`

---

## 📦 Modelo Eloquent

### RawMaterialReception

#### Fillable Attributes

```php
protected $fillable = [
    'supplier_id',
    'date',
    'notes',
    'declared_total_amount',
    'declared_total_net_weight',
    'creation_mode',
];

// Constantes para creation_mode
const CREATION_MODE_LINES = 'lines';
const CREATION_MODE_PALLETS = 'pallets';
```

#### Appended Attributes

```php
protected $appends = ['total_amount', 'can_edit', 'cannot_edit_reason'];
```

**Nuevos accessors**:
- `canEdit`: Boolean que indica si la recepción se puede editar
- `cannotEditReason`: String con la razón si no se puede editar (null si se puede editar)

### Traits

- `UsesTenantConnection`: Usa conexión tenant (multi-tenant)
- `HasFactory`: Para testing y seeders

### RawMaterialReceptionProduct

#### Fillable Attributes

```php
protected $fillable = [
    'reception_id',
    'product_id',
    'net_weight',
    'price', // ⚠️ No existe en migración
];
```

---

## 🔗 Relaciones

### RawMaterialReception

#### 1. `supplier()` - Proveedor
```php
public function supplier()
{
    return $this->belongsTo(Supplier::class);
}
```

#### 2. `products()` - Productos Recibidos
```php
public function products()
{
    return $this->hasMany(RawMaterialReceptionProduct::class, 'reception_id');
}
```

#### 3. `pallets()` - Palets Creados
```php
public function pallets()
{
    return $this->hasMany(Pallet::class, 'reception_id');
}
```

**Nueva relación**: Cada recepción puede tener múltiples palets asociados. Los palets se crean automáticamente al crear la recepción.

### RawMaterialReceptionProduct

#### 1. `reception()` - Recepción
```php
public function reception()
{
    return $this->belongsTo(RawMaterialReception::class, 'reception_id');
}
```

#### 2. `product()` - Producto
```php
public function product()
{
    return $this->belongsTo(Product::class, 'product_id');
}
```

---

## 🔢 Accessors (Atributos Calculados)

### RawMaterialReception

#### `getNetWeightAttribute()`

Suma el peso neto de todos los productos recibidos.

```php
return $this->products->sum('net_weight');
```

#### `getSpeciesAttribute()`

Obtiene la especie del primer producto recibido.

**⚠️ Problema**: Solo retorna la especie del primer producto. Si hay múltiples especies, retorna solo una.

#### `getTotalAmountAttribute()`

Calcula el total multiplicando peso neto por precio de cada producto.

```php
return $this->products->sum(function ($product) {
    return ($product->net_weight ?? 0) * ($product->price ?? 0);
});
```

### RawMaterialReceptionProduct

#### `getAliasAttribute()`

Intenta obtener el alias del producto desde `RawMaterial`.

**⚠️ Problema**: Código incompleto/comentado. Busca `RawMaterial` por `id` pero debería buscar por `product_id`.

---

## 📡 Controlador

**Archivo**: `app/Http/Controllers/v2/RawMaterialReceptionController.php`

### Métodos del Controlador

#### `index(Request $request)` - Listar Recepciones
```php
GET /v2/raw-material-receptions
```

**Eager Loading**: `supplier`, `products.product`

**Filtros disponibles** (query parameters):
- `id`: Filtrar por ID
- `ids`: Filtrar por múltiples IDs (array)
- `suppliers`: Filtrar por proveedores (array de IDs)
- `dates[start]`: Fecha inicio
- `dates[end]`: Fecha fin
- `species`: Filtrar por especies (array de IDs) - Busca en productos
- `products`: Filtrar por productos (array de IDs)
- `notes`: Buscar por notas (LIKE)

**Orden**: Por fecha descendente (más reciente primero)

**Query parameters**: `perPage` (default: 12)

**Respuesta**: Collection paginada de `RawMaterialReceptionResource`

#### `store(Request $request)` - Crear Recepción
```php
POST /v2/raw-material-receptions
```

**Validación** (Modo Automático - details):
```php
[
    'supplier.id' => 'required',
    'date' => 'required|date',
    'notes' => 'nullable|string',
    'details' => 'required_without:pallets|array',
    'details.*.product.id' => 'required_with:details|exists:tenant.products,id',
    'details.*.netWeight' => 'required_with:details|numeric',
    'details.*.price' => 'nullable|numeric|min:0',
    'details.*.lot' => 'nullable|string',
    'details.*.boxes' => 'nullable|integer|min:0', // Número de cajas (0 = 1)
]
```

**Validación** (Modo Manual - pallets):
```php
[
    'supplier.id' => 'required',
    'date' => 'required|date',
    'notes' => 'nullable|string',
    'pallets' => 'required_without:details|array',
    'pallets.*.observations' => 'nullable|string',
    'pallets.*.palletTareWeightKg' => 'nullable|numeric|min:0',
    'pallets.*.store.id' => 'nullable|integer|exists:tenant.stores,id',
    'pallets.*.boxes' => 'required_with:pallets|array|min:1',
    'pallets.*.boxes.*.product.id' => 'required|exists:tenant.products,id',
    'pallets.*.boxes.*.lot' => 'nullable|string',
    'pallets.*.boxes.*.gs1128' => 'required|string',
    'pallets.*.boxes.*.grossWeight' => 'required|numeric',
    'pallets.*.boxes.*.netWeight' => 'required|numeric',
    'pallets.*.prices' => 'required_with:pallets|array',
    'pallets.*.prices.*.product.id' => 'required|exists:tenant.products,id',
    'pallets.*.prices.*.lot' => 'required|string',
    'pallets.*.prices.*.price' => 'required|numeric|min:0',
]
```

**Request body** (Modo Automático):
```json
{
    "supplier": {
        "id": 1
    },
    "date": "2025-01-15",
    "notes": "Recepción normal",
    "details": [
        {
            "product": {
                "id": 10
            },
            "netWeight": 1000.50,
            "price": 12.50,
            "lot": "LOT-2025-001",
            "boxes": 20
        }
    ]
}
```

**Request body** (Modo Manual):
```json
{
    "supplier": {
        "id": 1
    },
    "date": "2025-01-15",
    "notes": "Recepción normal",
    "prices": [
        {
            "product": {
                "id": 10
            },
            "lot": "LOT-A",
            "price": 12.50
        },
        {
            "product": {
                "id": 10
            },
            "lot": "LOT-B",
            "price": 13.00
        }
    ],
    "pallets": [
        {
            "observations": "Palet 1",
            "palletTareWeightKg": 22.25,
            "store": {
                "id": 1
            },
            "boxes": [
                {
                    "product": {
                        "id": 10
                    },
                    "lot": "LOT-A",
                    "gs1128": "GS1-001",
                    "grossWeight": 25.5,
                    "netWeight": 25.0
                },
                {
                    "product": {
                        "id": 10
                    },
                    "lot": "LOT-B",
                    "gs1128": "GS1-002",
                    "grossWeight": 25.5,
                    "netWeight": 25.0
                }
            ]
        }
    ]
}
```

**Comportamiento**:
- **Modo Automático (details)**: Crea 1 palet por recepción, distribuye el peso entre las cajas según el campo `boxes`, crea líneas de recepción automáticamente
- **Modo Manual (pallets)**: Crea palets según especificación, cada caja puede tener su propio producto y lote
- **Precios**: Se especifican en el array `prices` en la raíz de la recepción (compartido por todos los palets). Si dos palets comparten el mismo producto+lote, solo se especifica el precio una vez. Si falta un precio, se busca del histórico
- **Lotes**: Cada caja puede tener su propio lote. Si no se proporciona, se genera automáticamente
- **Flexibilidad**: Un palet puede contener múltiples productos y lotes diferentes. Múltiples palets pueden compartir productos y lotes
- Las líneas de recepción se crean automáticamente agrupando por producto y lote
- Si `boxes` es 0 o null (en modo automático), se cuenta como 1

#### `show($id)` - Mostrar Recepción
```php
GET /v2/raw-material-receptions/{id}
```

**Eager Loading**: `supplier`, `products.product`, `pallets`

#### `update(Request $request, $id)` - Actualizar Recepción
```php
PUT /v2/raw-material-receptions/{id}
```

**Validación**: Según el `creation_mode` de la recepción:
- Si `creation_mode === 'lines'` → Acepta `details` (modo automático)
- Si `creation_mode === 'pallets'` → Acepta `pallets` (modo manual) con IDs opcionales:
  - `pallets.*.id` (opcional): ID del palet existente
  - `pallets.*.boxes.*.id` (opcional): ID de la caja existente
- Si `creation_mode === null` (recepciones antiguas) → Acepta `details`

**Restricciones importantes**:
- **Restricciones comunes** (aplican a ambos modos):
  - No se puede editar si algún palet está vinculado a un pedido (`order_id !== null`)
  - No se puede editar si alguna caja está siendo usada en producción (`productionInputs()->exists()`)
- **Modo de edición debe coincidir con modo de creación**:
  - Si `creation_mode === 'lines'` → Solo se puede editar con `details`
  - Si `creation_mode === 'pallets'` → Solo se puede editar con `pallets`

**Comportamiento**:
- Valida las restricciones comunes antes de editar
- Valida que el modo de edición coincida con el modo de creación
- **Modo PALLETS**: Edita palets y cajas existentes (si vienen con `id`), crea nuevos (si no vienen con `id`), elimina los que no están en el request
- **Modo LINES**: Mantiene el palet único, recrea las cajas según los nuevos detalles
- Regenera las líneas de recepción automáticamente
- El `creation_mode` se mantiene (no se puede cambiar)

**Lógica de Edición**:
- Si `pallets[].id` existe → actualiza el palet existente
- Si `pallets[].id` no existe → crea un nuevo palet
- Si `boxes[].id` existe → actualiza la caja existente
- Si `boxes[].id` no existe → crea una nueva caja
- Elimina palets/cajas que no están en el request

#### `destroy($id)` - Eliminar Recepción
```php
DELETE /v2/raw-material-receptions/{id}
```

**Mensaje de error**: Dice "Palet eliminado correctamente" pero elimina una recepción.

#### `destroyMultiple(Request $request)` - Eliminar Múltiples Recepciones
```php
DELETE /v2/raw-material-receptions
```

**Body**:
```json
{
    "ids": [1, 2, 3]
}
```

---

## 📊 Estadísticas

**Controlador**: `app/Http/Controllers/v2/RawMaterialReceptionStatisticsController.php`

**Servicio**: `app/Services/v2/RawMaterialReceptionStatisticsService.php`

### `receptionChartData(Request $request)` - Datos para Gráficos
```php
GET /v2/raw-material-receptions/reception-chart-data
```

**Query parameters**:
- `dateFrom`: Fecha inicio (YYYY-MM-DD) - **requerido**
- `dateTo`: Fecha fin (YYYY-MM-DD) - **requerido**
- `valueType`: `amount` o `quantity` - **requerido**
- `groupBy`: `day`, `week`, o `month` (default: `day`)
- `speciesId`: ID de especie (opcional)
- `familyId`: ID de familia (opcional)
- `categoryId`: ID de categoría (opcional)

**Respuesta**:
```json
[
    {
        "date": "2025-01-15",
        "value": 1234.56
    },
    ...
]
```

**Lógica**:
- Agrupa recepciones por período (día/semana/mes)
- Filtra por especie/familia/categoría si se especifica
- Calcula `amount` (precio * peso) o `quantity` (peso total)
- Solo incluye períodos con datos

---

## 📤 Exportaciones

**Controlador**: `app/Http/Controllers/v2/ExcelController.php`

### Exportar a Facilcom
```php
GET /v2/raw-material-receptions/facilcom-xls
```

### Exportar a A3ERP
```php
GET /v2/raw-material-receptions/a3erp-xls
```

---

## 📄 API Resources

### RawMaterialReceptionResource

**Archivo**: `app/Http/Resources/v2/RawMaterialReceptionResource.php`

**Campos expuestos**:
```json
{
    "id": 1,
    "supplier": {...},
    "date": "2025-01-15",
    "notes": "...",
    "creationMode": "lines", // 'lines' o 'pallets'
    "netWeight": 1000.50,
    "species": {...},
    "details": [...],
    "pallets": [...], // Palets creados desde esta recepción
    "totalAmount": 12500.00,
    "canEdit": true, // Nuevo: indica si se puede editar
    "cannotEditReason": null // Nuevo: razón si no se puede editar
}
```

### RawMaterialReceptionProductResource

**Archivo**: `app/Http/Resources/v2/RawMaterialReceptionProductResource.php`

**Campos expuestos**:
```json
{
    "id": 1,
    "product": {...},
    "netWeight": 500.25,
    "alias": "..."
}
```

---

## 🔐 Permisos y Autenticación

**Middleware requerido**:
- `auth:sanctum`: Autenticación requerida
- `role:superuser,manager,admin,store_operator`: Roles permitidos

**Rutas**: Todas bajo `/v2/raw-material-receptions/*`

---

## Observaciones Críticas y Mejoras Recomendadas

### ⚠️ Campo price No Existe en BD

1. **price en Fillable Pero No en Migración** (`app/Models/RawMaterialReceptionProduct.php:15`)
   - Campo `price` está en fillable pero no existe en la tabla
   - **Líneas**: 15, migración línea 18
   - **Problema**: No se puede guardar precio
   - **Recomendación**: 
     - Agregar migración para crear campo `price`
     - O eliminar de fillable si no se necesita

### ⚠️ Campos declared_total_amount y declared_total_net_weight No Se Usan

2. **Campos Declared No Se Usan en v2** (`app/Http/Controllers/v2/RawMaterialReceptionController.php`)
   - Campos `declared_total_amount` y `declared_total_net_weight` existen en BD
   - **Problema**: No se guardan ni validan en v2
   - **Recomendación**: 
     - Usar si se necesita comparar valores declarados vs reales
     - O eliminar si no se usan

### ⚠️ getSpeciesAttribute Retorna Solo Primera Especie

3. **Species Solo Primera** (`app/Models/RawMaterialReception.php:40-43`)
   - Solo retorna especie del primer producto
   - **Líneas**: 40-43
   - **Problema**: Si hay múltiples especies, se pierde información
   - **Recomendación**: Retornar array de especies o cambiar lógica

### ⚠️ Mensaje de Error Incorrecto

4. **Mensaje "Palet eliminado"** (`app/Http/Controllers/v2/RawMaterialReceptionController.php:156`)
   - Mensaje dice "Palet eliminado" pero elimina recepción
   - **Líneas**: 156
   - **Recomendación**: Corregir mensaje

### ⚠️ Update Elimina y Recrea Productos

5. **Eliminación Completa de Productos** (`app/Http/Controllers/v2/RawMaterialReceptionController.php:137`)
   - Elimina todos los productos y los vuelve a crear
   - **Líneas**: 137-143
   - **Problema**: 
     - Pierde IDs históricos
     - No es eficiente
   - **Recomendación**: Actualizar solo los que cambiaron

### ⚠️ getAliasAttribute Código Incompleto

6. **Alias Busca RawMaterial Incorrectamente** (`app/Models/RawMaterialReceptionProduct.php:29-35`)
   - Busca `RawMaterial` por `id` en lugar de `product_id`
   - **Líneas**: 29-35
   - **Problema**: Lógica incorrecta
   - **Recomendación**: Corregir lógica o eliminar si no se usa

### ⚠️ Sin Validación de Precio en Store

7. **No Se Valida ni Guarda Precio** (`app/Http/Controllers/v2/RawMaterialReceptionController.php:100-103`)
   - No valida ni guarda precios
   - **Problema**: No se puede registrar costos
   - **Recomendación**: Agregar validación y guardado de precios si se necesita

### ⚠️ Código Comentado en getAliasAttribute

8. **Código Comentado** (`app/Models/RawMaterialReceptionProduct.php:34`)
   - Hay código comentado que confunde
   - **Recomendación**: Eliminar comentarios obsoletos

---

**Última actualización**: Documentación generada desde código fuente en fecha de generación.
