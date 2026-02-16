# Plan de Implementación: Recepciones, Palets y Sistema de Costes

## 📋 Resumen Ejecutivo

Este documento describe el plan de implementación para vincular las **recepciones de materia prima** con la creación automática de **palets** (unidades mínimas almacenables) y la propagación de **costes** desde las recepciones hasta los palets, cajas y kilogramos individuales.

### Objetivos Principales

1. **Obligar la creación de palets al crear recepciones** - Los palets son la unidad mínima almacenable según la lógica del ERP
2. **Permitir creación de palets desde la pantalla de recepción** - Flexibilidad en la UI para crear palets directamente o por líneas
3. **Propagar costes desde recepciones a palets/cajas/kg** - Trazabilidad completa del coste por unidad
4. **Preparar estructura para costes de producción** - Base para futura implementación de costes de productos generados en producción

**⚠️ Nota**: Esta implementación es exclusiva para la **API v2**. La v1 está deprecada.

---

## 🔍 Estado Actual del Sistema

### Estructura Actual

#### Recepciones (`RawMaterialReception`)

- **Modelo**: `app/Models/RawMaterialReception.php`
- **Tabla**: `raw_material_receptions`
- **Campos principales**:
  - `id`, `supplier_id`, `date`, `notes`
  - `declared_total_amount` (importe total declarado)
  - `declared_total_net_weight` (peso neto total declarado)
  - `creation_mode` (string, nullable): `'lines'` (creada por líneas) o `'pallets'` (creada por palets)
- **Productos de recepción** (`RawMaterialReceptionProduct`):
  - `reception_id`, `product_id`, `net_weight`, `price`
  - **✅ Confirmado**: El campo `price` existe en la base de datos

#### Palets (`Pallet`)

- **Modelo**: `app/Models/Pallet.php`
- **Tabla**: `pallets`
- **Campos principales**:
  - `id`, `observations`, `status` (1=registered, 2=stored, 3=shipped, 4=processed)
  - `order_id` (opcional, para pedidos)
- **Relación con cajas**: A través de `pallet_boxes` (tabla pivot)
- **⚠️ No existe relación con recepciones actualmente**

#### Cajas (`Box`)

- **Modelo**: `app/Models/Box.php`
- **Tabla**: `boxes`
- **Campos principales**:
  - `id`, `article_id` (product_id), `lot`, `gs1_128`
  - `gross_weight`, `net_weight`
- **⚠️ No existe campo de coste actualmente**

### Flujo Actual

```
Recepción → Registro contable/logístico
    ↓
[NO HAY VÍNCULO AUTOMÁTICO]
    ↓
Palets → Creados manualmente o desde otras fuentes
    ↓
Cajas → Asociadas a palets
```

**Problema**: No hay trazabilidad entre recepciones y el inventario físico (palets/cajas).

---

## 🎯 Cambios Propuestos

### 1. Relación Recepción ↔ Palet

#### 1.1 Migración de Base de Datos

**Nueva migración**: `add_reception_id_to_pallets_table.php`

```php
Schema::table('pallets', function (Blueprint $table) {
    $table->unsignedBigInteger('reception_id')->nullable()->after('order_id');
    $table->foreign('reception_id')
          ->references('id')
          ->on('raw_material_receptions')
          ->onDelete('cascade'); // Si se elimina recepción, se eliminan palets
    $table->index('reception_id');
});
```

**⚠️ Cambio importante**: Usar `onDelete('cascade')` en lugar de `set null` porque:
- Si se elimina una recepción, los palets asociados deben eliminarse también
- Las validaciones en el modelo impedirán eliminar recepciones si los palets están en uso

#### 1.1.1 Migración de `creation_mode` en Recepciones

**Nueva migración**: `add_creation_mode_to_raw_material_receptions_table.php`

```php
Schema::table('raw_material_receptions', function (Blueprint $table) {
    if (!Schema::hasColumn('raw_material_receptions', 'creation_mode')) {
        $table->string('creation_mode', 20)->nullable()->after('notes')
              ->comment('Modo de creación: "lines" (por líneas) o "pallets" (por palets)');
    }
});
```

**Propósito**: Distinguir si una recepción fue creada por líneas (modo automático) o por palets (modo manual). Esto permite validar que solo se puedan editar por líneas las recepciones que fueron creadas por líneas, evitando perder los pesos reales de las cajas en recepciones creadas por palets.

**Valores posibles**:
- `'lines'`: Recepción creada por líneas (modo automático) - Las cajas tienen pesos promedios
- `'pallets'`: Recepción creada por palets (modo manual) - Las cajas tienen pesos reales específicos
- `null`: Recepciones antiguas (antes de esta implementación)

#### 1.2 Actualización del Modelo Pallet

**Archivo**: `app/Models/Pallet.php`

```php
// Agregar a fillable (opcional, se puede asignar directamente)
// protected $fillable = ['observations', 'status', 'reception_id'];

// Nueva relación
public function reception()
{
    return $this->belongsTo(RawMaterialReception::class, 'reception_id');
}

/**
 * Determina si el palet proviene de una recepción
 */
public function getIsFromReceptionAttribute(): bool
{
    return $this->reception_id !== null;
}

/**
 * Validar que no se pueda eliminar un palet de recepción directamente
 */
protected static function boot()
{
    parent::boot();
    
    static::deleting(function ($pallet) {
        if ($pallet->reception_id !== null) {
            throw new \Exception('No se puede eliminar un palet que proviene de una recepción. Elimine la recepción o modifique desde la recepción.');
        }
    });
    
    static::updating(function ($pallet) {
        if ($pallet->reception_id !== null && $pallet->isDirty('reception_id')) {
            throw new \Exception('No se puede cambiar la recepción de un palet.');
        }
    });
}
```

**Archivo**: `app/Models/RawMaterialReception.php`

```php
// Agregar creation_mode a fillable
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

// Nueva relación
public function pallets()
{
    return $this->hasMany(Pallet::class, 'reception_id');
}

// Validación antes de eliminar
protected static function boot()
{
    parent::boot();
  
    static::deleting(function ($reception) {
        foreach ($reception->pallets as $pallet) {
            // Validar que el palet no esté en uso
            if ($pallet->order_id !== null) {
                throw new \Exception("No se puede eliminar la recepción: el palet #{$pallet->id} está vinculado a un pedido");
            }
          
            if ($pallet->status === Pallet::STATE_STORED) {
                throw new \Exception("No se puede eliminar la recepción: el palet #{$pallet->id} está almacenado");
            }
          
            // Validar que las cajas no estén en producción
            foreach ($pallet->boxes as $palletBox) {
                if ($palletBox->box->productionInputs()->exists()) {
                    throw new \Exception("No se puede eliminar la recepción: la caja #{$palletBox->box->id} está siendo usada en producción");
                }
            }
        }
    });
}
```

#### 1.3 Restricciones en PalletController (v2)

**Archivo**: `app/Http/Controllers/v2/PalletController.php`

```php
public function update(Request $request, string $id)
{
    $pallet = Pallet::findOrFail($id);
    
    // Validar que no se pueda modificar un palet de recepción
    if ($pallet->reception_id !== null) {
        return response()->json([
            'error' => 'No se puede modificar un palet que proviene de una recepción. Modifique desde la recepción.'
        ], 403);
    }
    
    // ... resto del código existente
}

public function destroy(string $id)
{
    $pallet = Pallet::findOrFail($id);
    
    // Validar que no se pueda eliminar un palet de recepción
    if ($pallet->reception_id !== null) {
        return response()->json([
            'error' => 'No se puede eliminar un palet que proviene de una recepción. Elimine la recepción o modifique desde la recepción.'
        ], 403);
    }
    
    // ... resto del código existente
}
```

**Restricciones adicionales**:
- No se pueden añadir cajas a un palet de recepción
- No se pueden modificar cajas de un palet de recepción
- No se pueden eliminar cajas de un palet de recepción
- Todo debe hacerse desde la recepción

---

### 2. Sistema de Costes

#### 2.1 Estrategia de Almacenamiento de Costes

**⚠️ IMPORTANTE**: Los campos de coste serán **calculados mediante accessors**, no almacenados directamente en la base de datos. Esto permite:

- Mantener la fuente de verdad en la recepción
- Recalcular automáticamente si cambian los precios
- Evitar inconsistencias por actualizaciones manuales

**Estructura de campos**:

1. **En `raw_material_reception_products`** (ya existe `price`):

   - `price` (decimal) - Precio por kg del producto en esta recepción
   - **Validación**: Si existe, debe ser ≥ 0
   - **Fuente de verdad**: Este es el precio base que se propaga

2. **En `pallets`** (accessors calculados):

   - `cost_per_kg` (accessor) - **Calculado**: Media ponderada del precio del kg en las cajas del palet
   - `total_cost` (accessor) - **Calculado**: Suma de `total_cost` de todas las cajas del palet

3. **En `boxes`** (accessors calculados):

   - `cost_per_kg` (accessor) - **Calculado**: Se obtiene desde la recepción a través del palet
   - `total_cost` (accessor) - **Calculado**: `net_weight × cost_per_kg`

#### 2.2 Implementación de Accessors

**En `Box`**:

```php
/**
 * Obtiene el coste por kg de la caja desde la recepción
 */
public function getCostPerKgAttribute(): ?float
{
    $pallet = $this->pallet;
    if (!$pallet || !$pallet->reception_id) {
        return null;
    }
  
    $reception = $pallet->reception;
    $receptionProduct = $reception->products()
        ->where('product_id', $this->article_id)
        ->first();
  
    return $receptionProduct?->price;
}

/**
 * Calcula el coste total de la caja
 */
public function getTotalCostAttribute(): ?float
{
    $costPerKg = $this->cost_per_kg;
    if ($costPerKg === null) {
        return null;
    }
  
    return $this->net_weight * $costPerKg;
}
```

**En `Pallet`**:

```php
/**
 * Calcula el coste por kg del palet (media ponderada de las cajas)
 */
public function getCostPerKgAttribute(): ?float
{
    if (!$this->boxes || $this->boxes->isEmpty()) {
        return null;
    }
  
    $totalCost = 0;
    $totalWeight = 0;
  
    foreach ($this->boxes as $palletBox) {
        $box = $palletBox->box;
        $boxCost = $box->total_cost;
        $boxWeight = $box->net_weight;
      
        if ($boxCost !== null && $boxWeight > 0) {
            $totalCost += $boxCost;
            $totalWeight += $boxWeight;
        }
    }
  
    if ($totalWeight == 0) {
        return null;
    }
  
    return $totalCost / $totalWeight;
}

/**
 * Calcula el coste total del palet (suma de costes de cajas)
 */
public function getTotalCostAttribute(): ?float
{
    if (!$this->boxes || $this->boxes->isEmpty()) {
        return null;
    }
  
    $totalCost = 0;
    $hasCost = false;
  
    foreach ($this->boxes as $palletBox) {
        $boxCost = $palletBox->box->total_cost;
        if ($boxCost !== null) {
            $totalCost += $boxCost;
            $hasCost = true;
        }
    }
  
    return $hasCost ? $totalCost : null;
}
```

**⚠️ Nota**: Si en el futuro se necesita almacenar estos valores (por rendimiento), se pueden agregar campos en BD y actualizarlos mediante eventos/observers.

---

### 3. Lógica de Creación de Palets desde Recepciones

#### 3.1 Opción Híbrida (Elegida)

**Comportamiento**:

- **Modo 1 - Creación Manual de Palets**: Si el usuario proporciona información de palets/cajas en la recepción:

  - Crear palets según especificación
  - Crear líneas de recepción automáticamente con el resumen
  - **El usuario debe indicar el precio** en cada palet
  - Los palets que se creen deben tener status **registrado** (`STATE_REGISTERED`)

- **Modo 2 - Creación Automática**: Si solo se proporcionan líneas:

  - Crear automáticamente **1 palet por recepción** (no por línea)
  - Crear cajas dentro del palet según el campo `boxes` (número de cajas) en cada `detail`
  - **⚠️ NUEVO**: El campo `boxes` debe agregarse a la estructura de `details`
  - Distribuir el peso neto de la línea entre las cajas (promedio)
  - El palet que se cree debe tener status **registrado** (`STATE_REGISTERED`)

#### 3.2 Gestión de Lotes

**Estrategia para lotes**:

- **Modo Manual (palets)**: El usuario indica el lote en cada palet
- **Modo Automático (líneas)**: El lote se indica en cada línea de `details`
  - **⚠️ NUEVO**: Agregar campo `lot` a cada `detail` en la estructura de request
  - Si no se proporciona, se genera automáticamente

**Estructura de Request**:

**Request completo con palets manuales**:

```json
{
  "supplier": { "id": 1 },
  "date": "2025-01-15",
  "notes": "Recepción de prueba",
  "pallets": [
    {
      "observations": "Palet 1",
      "product": { "id": 5 },
      "price": 12.50,
      "lot": "LOT-2025-001",
      "boxes": [
        {
          "gs1128": "GS1-001",
          "grossWeight": 25.5,
          "netWeight": 25.0
        },
        {
          "gs1128": "GS1-002",
          "grossWeight": 25.5,
          "netWeight": 25.0
        }
      ]
    }
  ]
}
```

**Request con líneas (creación automática)**:

```json
{
  "supplier": { "id": 1 },
  "date": "2025-01-15",
  "notes": "Recepción de prueba",
  "details": [
    {
      "product": { "id": 5 },
      "netWeight": 500.00,
      "price": 12.50,
      "lot": "LOT-2025-001",
      "boxes": 20
    }
  ]
}
```

**Validaciones**:

- `details.*.boxes` (integer, nullable) - **NUEVO**: Número de cajas. Si es 0 o null, se cuenta como 1
- `details.*.lot` (string, nullable) - **NUEVO**: Lote para esta línea. Si no se proporciona, se genera automáticamente
- `details.*.netWeight` (required, numeric) - Peso neto total de la línea
- `details.*.price` (nullable, numeric, min:0) - Precio por kg. Si no se indica, se intenta obtener del precio anterior del producto para ese proveedor

#### 3.3 Obtención de Precio por Defecto

**Lógica para obtener precio si no se proporciona**:

```php
private function getDefaultPrice(int $productId, int $supplierId): ?float
{
    // Buscar la última recepción del mismo proveedor con el mismo producto
    $lastReception = RawMaterialReception::where('supplier_id', $supplierId)
        ->whereHas('products', function ($query) use ($productId) {
            $query->where('product_id', $productId)
                  ->whereNotNull('price');
        })
        ->orderBy('date', 'desc')
        ->first();
    
    if ($lastReception) {
        $lastProduct = $lastReception->products()
            ->where('product_id', $productId)
            ->whereNotNull('price')
            ->orderBy('created_at', 'desc')
            ->first();
        
        return $lastProduct?->price;
    }
    
    return null;
}
```

#### 3.4 Algoritmo de Creación Automática

**Cuando `pallets` NO se proporciona**:

1. Crear **1 palet por recepción** (no por línea) con status `STATE_REGISTERED`
2. Para cada `detail` en `details`:
   - Obtener precio: `$price = $detail['price'] ?? $this->getDefaultPrice($productId, $supplierId)`
   - Calcular número de cajas: `$numBoxes = max(1, $detail['boxes'] ?? 1)`
   - Calcular peso por caja: `$weightPerBox = $detail['netWeight'] / $numBoxes`
   - Obtener lote: `$lot = $detail['lot'] ?? $this->generateLotFromReception($reception, $productId)`
   - Crear `$numBoxes` cajas dentro del palet:
     - `net_weight` = `$weightPerBox`
     - `gross_weight` = `$weightPerBox * 1.02` (2% estimado, o usar valor por defecto)
     - `lot` = `$lot`
     - `article_id` = `$detail['product']['id']`
     - `gs1_128` = generado automáticamente
3. Crear línea de recepción con el resumen:
   - `product_id` = `$detail['product']['id']`
   - `net_weight` = `$detail['netWeight']`
   - `price` = `$price` (puede ser null si no se encontró precio por defecto)

#### 3.5 Algoritmo de Creación Manual

**Cuando `pallets` se proporciona**:

1. Crear recepción
2. Para cada palet en `pallets`:
   - Crear palet asociado a la recepción con status `STATE_REGISTERED`
   - Crear cajas según especificación
   - Agrupar cajas por `product_id` y `lot`
   - Crear líneas de recepción automáticamente:
     - `product_id` = producto del palet
     - `net_weight` = suma de `net_weight` de cajas del mismo producto/lote
     - `price` = precio proporcionado en el palet (debe ser obligatorio)

---

### 4. Cálculo y Propagación de Costes

#### 4.1 Cálculo de Coste por Lote/Producto en Recepción

**Fórmula base**:

```
cost_per_kg = price (de RawMaterialReceptionProduct)
```

Si `price` es null o 0, el coste no se calcula (queda null).

#### 4.2 Propagación a Cajas (Automática mediante Accessors)

Los costes se propagan automáticamente mediante los accessors definidos en la sección 2.2:

- Cada caja consulta su palet → recepción → precio del producto
- El cálculo es dinámico y siempre refleja el precio actual de la recepción

**Ventajas**:

- No requiere propagación manual
- Siempre está actualizado
- No hay riesgo de inconsistencias

#### 4.3 Propagación a Palets (Automática mediante Accessors)

Los costes de palets se calculan automáticamente como media ponderada de las cajas:

- `cost_per_kg` = suma de costes de cajas / suma de pesos de cajas
- `total_cost` = suma de costes totales de cajas

---

### 5. Actualización del Controlador de Recepciones (v2)

#### 5.1 Modificar `store()` en `RawMaterialReceptionController`

**Cambios necesarios**:

```php
public function store(Request $request)
{
    $validator = Validator::make($request->all(), [
        'supplier.id' => 'required',
        'date' => 'required|date',
        'notes' => 'nullable|string',
        // Opción 1: Líneas con creación automática de palets
        'details' => 'required_without:pallets|array',
        'details.*.product.id' => 'required_with:details|exists:tenant.products,id',
        'details.*.netWeight' => 'required_with:details|numeric',
        'details.*.price' => 'nullable|numeric|min:0',
        'details.*.lot' => 'nullable|string', // NUEVO: Lote por línea
        'details.*.boxes' => 'nullable|integer|min:0', // NUEVO: Número de cajas (0 = 1)
        // Opción 2: Palets manuales con creación automática de líneas
        'pallets' => 'required_without:details|array',
        'pallets.*.product.id' => 'required_with:pallets|exists:tenant.products,id',
        'pallets.*.price' => 'required_with:pallets|numeric|min:0', // Obligatorio en modo manual
        'pallets.*.lot' => 'nullable|string',
        'pallets.*.observations' => 'nullable|string',
        'pallets.*.boxes' => 'required_with:pallets|array',
        'pallets.*.boxes.*.gs1128' => 'required|string',
        'pallets.*.boxes.*.grossWeight' => 'required|numeric',
        'pallets.*.boxes.*.netWeight' => 'required|numeric',
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    return DB::transaction(function () use ($request) {
        // 1. Crear recepción
        $reception = new RawMaterialReception();
        $reception->supplier_id = $request->supplier['id'];
        $reception->date = $request->date;
        $reception->notes = $request->notes ?? null;
        
        // Determinar y guardar el modo de creación
        if ($request->has('pallets') && !empty($request->pallets)) {
            $reception->creation_mode = RawMaterialReception::CREATION_MODE_PALLETS;
        } else {
            $reception->creation_mode = RawMaterialReception::CREATION_MODE_LINES;
        }
        
        $reception->save();

        // 2. Crear palets y líneas según el modo
        if ($request->has('pallets') && !empty($request->pallets)) {
            // Modo manual: crear palets y generar líneas
            $this->createPalletsFromRequest($reception, $request->pallets);
        } else {
            // Modo automático: crear líneas y generar palet
            $this->createDetailsFromRequest($reception, $request->details, $request->supplier['id']);
        }

        // 3. Cargar relaciones para respuesta
        $reception->load('supplier', 'products.product', 'pallets');
      
        return new RawMaterialReceptionResource($reception);
    });
}

private function createPalletsFromRequest(RawMaterialReception $reception, array $pallets): void
{
    $groupedByProduct = [];
  
    foreach ($pallets as $palletData) {
        $productId = $palletData['product']['id'];
        $lot = $palletData['lot'] ?? $this->generateLotFromReception($reception, $productId);
      
        // Crear palet
        $pallet = new Pallet();
        $pallet->reception_id = $reception->id;
        $pallet->observations = $palletData['observations'] ?? null;
        $pallet->status = Pallet::STATE_REGISTERED; // Status registrado
        $pallet->save();
      
        $totalWeight = 0;
      
        // Crear cajas
        foreach ($palletData['boxes'] as $boxData) {
            $box = new Box();
            $box->article_id = $productId;
            $box->lot = $lot;
            $box->gs1_128 = $boxData['gs1128'];
            $box->gross_weight = $boxData['grossWeight'];
            $box->net_weight = $boxData['netWeight'];
            $box->save();
          
            $totalWeight += $box->net_weight;
          
            PalletBox::create([
                'pallet_id' => $pallet->id,
                'box_id' => $box->id,
            ]);
        }
      
        // Agrupar por producto y lote para crear líneas
        $key = "{$productId}_{$lot}";
        if (!isset($groupedByProduct[$key])) {
            $groupedByProduct[$key] = [
                'product_id' => $productId,
                'lot' => $lot,
                'net_weight' => 0,
                'price' => $palletData['price'],
            ];
        }
        $groupedByProduct[$key]['net_weight'] += $totalWeight;
    }
  
    // Crear líneas de recepción
    foreach ($groupedByProduct as $group) {
        $reception->products()->create([
            'product_id' => $group['product_id'],
            'net_weight' => $group['net_weight'],
            'price' => $group['price'],
        ]);
    }
}

private function createDetailsFromRequest(RawMaterialReception $reception, array $details, int $supplierId): void
{
    // Crear un solo palet para toda la recepción
    $pallet = new Pallet();
    $pallet->reception_id = $reception->id;
    $pallet->observations = "Auto-generado desde recepción #{$reception->id}";
    $pallet->status = Pallet::STATE_REGISTERED; // Status registrado
    $pallet->save();
  
    foreach ($details as $detail) {
        $productId = $detail['product']['id'];
        
        // Obtener precio (del request o del histórico)
        $price = $detail['price'] ?? $this->getDefaultPrice($productId, $supplierId);
        
        // Crear línea de recepción
        $reception->products()->create([
            'product_id' => $productId,
            'net_weight' => $detail['netWeight'],
            'price' => $price,
        ]);
      
        $lot = $detail['lot'] ?? $this->generateLotFromReception($reception, $productId);
        $numBoxes = max(1, $detail['boxes'] ?? 1);
        $weightPerBox = $detail['netWeight'] / $numBoxes;
      
        // Crear cajas
        for ($i = 0; $i < $numBoxes; $i++) {
            $box = new Box();
            $box->article_id = $productId;
            $box->lot = $lot;
            $box->gs1_128 = $this->generateGS1128($reception, $productId, $i);
            $box->gross_weight = $weightPerBox * 1.02; // 2% estimado
            $box->net_weight = $weightPerBox;
            $box->save();
          
            PalletBox::create([
                'pallet_id' => $pallet->id,
                'box_id' => $box->id,
            ]);
        }
    }
}

private function getDefaultPrice(int $productId, int $supplierId): ?float
{
    // Buscar la última recepción del mismo proveedor con el mismo producto
    $lastReception = RawMaterialReception::where('supplier_id', $supplierId)
        ->whereHas('products', function ($query) use ($productId) {
            $query->where('product_id', $productId)
                  ->whereNotNull('price');
        })
        ->orderBy('date', 'desc')
        ->first();
    
    if ($lastReception) {
        $lastProduct = $lastReception->products()
            ->where('product_id', $productId)
            ->whereNotNull('price')
            ->orderBy('created_at', 'desc')
            ->first();
        
        return $lastProduct?->price;
    }
    
    return null;
}

private function generateLotFromReception(RawMaterialReception $reception, int $productId): string
{
    return date('Ymd', strtotime($reception->date)) . '-' . $reception->id . '-' . $productId;
}

private function generateGS1128(RawMaterialReception $reception, int $productId, int $index = 0): string
{
    return 'GS1-' . $reception->id . '-' . $productId . '-' . $index . '-' . time();
}
```

#### 5.2 Modificar `update()` en `RawMaterialReceptionController`

**Restricciones importantes**:

- **Solo se pueden editar por líneas si la recepción fue creada por líneas** (`creation_mode === 'lines'`)
- Si la recepción fue creada por palets (`creation_mode === 'pallets'`), no se puede editar por líneas. Debe modificar los palets directamente.
- Solo se pueden modificar las líneas si:
  - Existe **un solo palet** asociado a la recepción
  - El palet **NO está en uso** (no tiene `order_id`, no está almacenado, no tiene cajas en producción)
- Si hay más palets, será necesario actualizar la recepción mediante el método de palets directamente

**Razón**: Las recepciones creadas por palets tienen cajas con pesos reales específicos. Si se editan por líneas, se perderían esos pesos reales y se crearían cajas con pesos promedios, rompiendo la lógica de negocio.

```php
public function update(Request $request, $id)
{
    $validated = $request->validate([
        'supplier.id' => 'required',
        'date' => 'required|date',
        'notes' => 'nullable|string',
        'details' => 'required|array',
        'details.*.product.id' => 'required|exists:tenant.products,id',
        'details.*.netWeight' => 'required|numeric',
        'details.*.price' => 'nullable|numeric|min:0',
        'details.*.lot' => 'nullable|string',
        'details.*.boxes' => 'nullable|integer|min:0',
    ]);

    $reception = RawMaterialReception::findOrFail($id);
  
    return DB::transaction(function () use ($reception, $validated, $request) {
        // Validar que solo se puede editar por líneas si fue creada por líneas
        if ($reception->creation_mode === RawMaterialReception::CREATION_MODE_PALLETS) {
            throw new \Exception('No se puede modificar una recepción creada por palets usando el método de líneas. Debe modificar los palets directamente.');
        }
        
        // Validar que se puede modificar
        $pallets = $reception->pallets;
      
        if ($pallets->count() > 1) {
            throw new \Exception('No se puede modificar una recepción con más de un palet. Use el método de palets directamente.');
        }
      
        if ($pallets->count() === 1) {
            $pallet = $pallets->first();
          
            // Validar que el palet no esté en uso
            if ($pallet->order_id !== null) {
                throw new \Exception('No se puede modificar la recepción: el palet está vinculado a un pedido');
            }
          
            if ($pallet->status === Pallet::STATE_STORED) {
                throw new \Exception('No se puede modificar la recepción: el palet está almacenado');
            }
          
            // Validar que las cajas no estén en producción
            foreach ($pallet->boxes as $palletBox) {
                if ($palletBox->box->productionInputs()->exists()) {
                    throw new \Exception('No se puede modificar la recepción: hay cajas siendo usadas en producción');
                }
            }
          
            // Eliminar palet y cajas existentes
            foreach ($pallet->boxes as $palletBox) {
                $palletBox->box->delete();
            }
            $pallet->delete();
        }
      
        // Actualizar recepción
        $reception->update([
            'supplier_id' => $validated['supplier']['id'],
            'date' => $validated['date'],
            'notes' => $validated['notes'] ?? null,
        ]);
      
        // Eliminar líneas antiguas
        $reception->products()->delete();
      
        // Crear nuevas líneas y palets
        $this->createDetailsFromRequest($reception, $validated['details'], $request->supplier['id']);
      
        $reception->load('supplier', 'products.product', 'pallets');
        return new RawMaterialReceptionResource($reception);
    });
}
```

---

### 6. Actualización de Recursos (Resources)

#### 6.1 Actualizar `RawMaterialReceptionResource` (v2)

Incluir información de palets creados:

```php
public function toArray($request)
{
    return [
        'id' => $this->id,
        'supplier' => new SupplierResource($this->supplier),
        'date' => $this->date,
        'notes' => $this->notes,
        'creationMode' => $this->creation_mode, // 'lines' o 'pallets'
        'products' => RawMaterialReceptionProductResource::collection($this->products),
        'pallets' => PalletResource::collection($this->pallets),
        'totalNetWeight' => $this->netWeight,
        'totalAmount' => $this->totalAmount,
    ];
}
```

#### 6.2 Actualizar `PalletResource` (v2)

Incluir información de coste y recepción:

```php
public function toArray($request)
{
    return [
        'id' => $this->id,
        'receptionId' => $this->reception_id, // Nuevo
        'reception' => $this->reception ? [
            'id' => $this->reception->id,
            'date' => $this->reception->date,
        ] : null, // Nuevo
        'isFromReception' => $this->isFromReception, // Nuevo
        'costPerKg' => $this->cost_per_kg, // Nuevo (accessor)
        'totalCost' => $this->total_cost, // Nuevo (accessor)
        // ... resto de campos
    ];
}
```

#### 6.3 Actualizar `Box` (toArrayAssocV2)

Incluir información de coste:

```php
public function toArrayAssocV2()
{
    return [
        'id' => $this->id,
        'costPerKg' => $this->cost_per_kg, // Nuevo (accessor)
        'totalCost' => $this->total_cost, // Nuevo (accessor)
        // ... resto de campos
    ];
}
```

---

### 7. Consideraciones para Costes de Producción (Futuro)

#### 7.1 Estructura Propuesta

Cuando se implementen costes de producción, los productos generados tendrán coste calculado a partir de:

- Coste de materias primas usadas (de cajas con coste de recepción)
- Coste de mano de obra
- Coste de otros insumos

**Estrategia**:

- Los productos de producción NO tendrán `reception_id` en sus palets
- Se identificarán por el `lot` que coincida con una producción
- El coste se calculará dinámicamente mediante accessors

**⚠️ Nota**: La implementación de costes de producción se dejará para el futuro y no forma parte de este plan.

---

## 📝 Plan de Implementación por Fases

### Fase 1: Estructura Base (Semana 1)

- [ ] Crear migración `add_reception_id_to_pallets_table.php` con `onDelete('cascade')`
- [ ] Actualizar modelos `Pallet` y `RawMaterialReception` con relaciones
- [ ] Implementar validaciones de eliminación en `RawMaterialReception::boot()`
- [ ] Implementar validaciones en `Pallet::boot()` para impedir eliminación/modificación directa
- [ ] Agregar accessor `isFromReception` en `Pallet`

### Fase 2: Sistema de Costes (Semana 2)

- [ ] Implementar accessors de coste en `Box`
- [ ] Implementar accessors de coste en `Pallet`
- [ ] Actualizar `Box::toArrayAssocV2()` con costes
- [ ] Actualizar `PalletResource` con costes

### Fase 3: Creación de Palets (Semana 3)

- [ ] Implementar `createPalletsFromRequest()` (modo manual)
- [ ] Implementar `createDetailsFromRequest()` (modo automático)
- [ ] Implementar `getDefaultPrice()` para obtener precio histórico
- [ ] Actualizar `store()` en `RawMaterialReceptionController` (v2)
- [ ] Actualizar `update()` con validaciones de modificación
- [ ] Agregar validaciones de peso y número de cajas

### Fase 4: Restricciones en PalletController (Semana 4)

- [ ] Actualizar `PalletController::update()` para bloquear modificación de palets de recepción
- [ ] Actualizar `PalletController::destroy()` para bloquear eliminación de palets de recepción
- [ ] Validar que no se puedan añadir/modificar/eliminar cajas de palets de recepción

### Fase 5: Actualización de Recursos y UI (Semana 5)

- [ ] Actualizar `RawMaterialReceptionResource` (v2)
- [ ] Actualizar `PalletResource` (v2)
- [ ] Documentar cambios en API v2
- [ ] Actualizar documentación de endpoints

### Fase 6: Testing y Validación (Semana 6)

- [ ] Tests unitarios para creación de palets (modo manual y automático)
- [ ] Tests de accessors de coste
- [ ] Tests de validaciones de eliminación y modificación
- [ ] Tests de restricciones en PalletController
- [ ] Validación de integridad de datos
- [ ] Pruebas de rendimiento con recepciones grandes

---

## ⚠️ Consideraciones Importantes

### Validaciones

1. **Peso total**: La suma de pesos de palets debe coincidir con el peso de la recepción (con tolerancia)
2. **Precios**: Si `price` es null, no se calculan costes (pero se crean palets). Se intenta obtener del histórico.
3. **Lotes**: **PERMITIR DUPLICADOS** - No se valida unicidad de lotes
4. **Eliminación de recepción**: No se puede eliminar recepción si los palets están en uso (ver sección 1.2)
5. **Eliminación de palet**: No se puede borrar un palet de recepción directamente. Solo se puede eliminar desde la recepción o cuando se elimina la recepción (cascade)
6. **Modificación de palet**: No se puede modificar un palet que proviene de recepción (ni añadir, modificar ni eliminar cajas). Todo debe hacerse desde la recepción
7. **Modificación de recepción**: Solo se puede modificar recepción si hay un solo palet y no está en uso
8. **Número de cajas**: Si se indica 0 cajas, se cuenta como 1
9. **Lotes en líneas**: El campo `lot` se agrega a cada línea en `details` para permitir diferentes lotes por producto

### Rendimiento

- Los accessors de coste realizan consultas a la BD. Para recepciones grandes, considerar:
  - Eager loading de relaciones (`with('reception.products')`)
  - Cachear cálculos si es necesario (futuro)
  - Indexar `reception_id` en `pallets` para consultas rápidas

### Migración de Datos Existentes

- Los palets existentes tendrán `reception_id = null` (correcto)
- Los costes se calcularán automáticamente mediante accessors cuando se consulten
- Considerar script de migración para asignar recepciones a palets existentes si hay datos históricos

---

## 🔗 Referencias

- [Documentación de Recepciones](./60-Recepciones-Materia-Prima.md)
- [Documentación de Palets](../23-inventario/31-Palets.md)
- [Documentación de Cajas](../23-inventario/32-Cajas.md)
- Modelos: `app/Models/RawMaterialReception.php`, `app/Models/Pallet.php`, `app/Models/Box.php`
- Controladores: `app/Http/Controllers/v2/RawMaterialReceptionController.php`, `app/Http/Controllers/v2/PalletController.php`

---

## 📅 Fechas de Revisión

- **Creado**: 2025-01-XX
- **Última actualización**: 2025-01-XX
- **Próxima revisión**: Después de Fase 1

---

## ✅ Checklist de Implementación

### Base de Datos

- [ ] Migración `reception_id` en palets con `onDelete('cascade')`
- [ ] Verificar que `price` existe en `raw_material_reception_products` (✅ confirmado)

### Modelos

- [ ] Relación `Pallet::reception()`
- [ ] Relación `RawMaterialReception::pallets()`
- [ ] Accessor `isFromReception` en `Pallet`
- [ ] Validaciones de eliminación en `RawMaterialReception::boot()`
- [ ] Validaciones de eliminación/modificación en `Pallet::boot()`
- [ ] Accessor `getCostPerKgAttribute()` en `Box`
- [ ] Accessor `getTotalCostAttribute()` en `Box`
- [ ] Accessor `getCostPerKgAttribute()` en `Pallet`
- [ ] Accessor `getTotalCostAttribute()` en `Pallet`

### Controladores (v2)

- [ ] Actualizar `RawMaterialReceptionController::store()`
- [ ] Implementar `createPalletsFromRequest()` (modo manual)
- [ ] Implementar `createDetailsFromRequest()` (modo automático)
- [ ] Implementar `getDefaultPrice()` para precio histórico
- [ ] Actualizar `RawMaterialReceptionController::update()` con validaciones
- [ ] Actualizar `PalletController::update()` para bloquear palets de recepción
- [ ] Actualizar `PalletController::destroy()` para bloquear palets de recepción
- [ ] Métodos helper para generación de lotes y GS1-128

### Recursos (v2)

- [ ] Actualizar `RawMaterialReceptionResource`
- [ ] Actualizar `PalletResource` con `isFromReception`
- [ ] Actualizar `Box::toArrayAssocV2()`

### Testing

- [ ] Tests de creación de palets desde recepción (modo manual)
- [ ] Tests de creación de palets desde recepción (modo automático)
- [ ] Tests de obtención de precio por defecto
- [ ] Tests de accessors de coste
- [ ] Tests de validaciones de eliminación
- [ ] Tests de validaciones de modificación
- [ ] Tests de restricciones en PalletController

### Documentación

- [ ] Actualizar documentación de API v2
- [ ] Documentar nuevos endpoints
- [ ] Ejemplos de uso (request/response)
- [ ] Documentar comportamiento de accessors de coste
- [ ] Documentar restricciones de palets de recepción
