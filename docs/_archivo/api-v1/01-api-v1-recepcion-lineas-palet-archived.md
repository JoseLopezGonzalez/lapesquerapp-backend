> **⚠️ ARCHIVADO — Solo histórico.** La API v1 fue eliminada (2025-01-27). Para la API actual: [recepciones-despachos](../recepciones-despachos/), [ADR 0001](../architecture-decisions/0001-API-v2-only.md).

---

# Guía Backend v1: Recepción por Líneas con Palet Automático (archivado)

## 📋 Resumen

Este documento describe la implementación necesaria para la **versión v1 del backend** que solo utilizará **recepción por líneas** (sin recepción de lote desde el frontend). El sistema debe crear automáticamente un palet y generar el lote con un formato específico basado en la fecha, código FAO y zona de captura del producto.

**Fecha**: Diciembre 2025

---

## 🎯 Objetivo

Implementar un flujo completo de recepción de materia prima que:
1. Recibe datos por líneas (producto, caja, peso neto)
2. **Genera automáticamente el lote** con formato: `DDMMAAFFFXXREC`
3. **Crea automáticamente un palet** para agrupar todas las cajas
4. Crea las cajas asociadas al palet
5. Crea las líneas de recepción

---

## 📦 Estructura del Request

### Endpoint: `POST /api/v1/raw-material-receptions` (o equivalente)

El frontend enviará un request con la siguiente estructura:

```json
{
  "supplier": { "id": 1 },
  "date": "2025-12-15",
  "notes": "Recepción de prueba",
  "details": [
    {
      "product": { "id": 5 },
      "netWeight": 250.50,
      "boxes": 10,
      "price": 12.50
    },
    {
      "product": { "id": 6 },
      "netWeight": 180.75,
      "boxes": 8,
      "price": 15.00
    }
  ]
}
```

### Campos del Request

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| `supplier.id` | integer | ✅ Sí | ID del proveedor |
| `date` | date (YYYY-MM-DD) | ✅ Sí | Fecha de la recepción |
| `notes` | string | ❌ No | Notas adicionales |
| `details` | array | ✅ Sí | Array de líneas de recepción |
| `details[].product.id` | integer | ✅ Sí | ID del producto |
| `details[].netWeight` | decimal | ✅ Sí | Peso neto total del producto |
| `details[].boxes` | integer | ❌ No | Número de cajas (default: 1) |
| `details[].price` | decimal | ❌ No | Precio por kg (si no se proporciona, buscar del histórico) |

**⚠️ IMPORTANTE**: El campo `lot` **NO se envía desde el frontend**. El backend debe generarlo automáticamente.

---

## 🔄 Flujo de Procesamiento

### Paso 1: Crear la Recepción

```php
$reception = new RawMaterialReception();
$reception->supplier_id = $request->supplier['id'];
$reception->date = $request->date;
$reception->notes = $request->notes ?? null;
$reception->creation_mode = 'lines'; // Indicar que es modo líneas
$reception->save();
```

### Paso 2: Crear el Palet Automático

**Un solo palet para toda la recepción**:

```php
$pallet = new Pallet();
$pallet->reception_id = $reception->id;
$pallet->observations = "Auto-generado desde recepción #{$reception->id}";
$pallet->status = Pallet::STATE_REGISTERED; // Estado: registrado
$pallet->save();
```

### Paso 3: Procesar cada Línea de Recepción

Para cada elemento en `details[]`:

#### 3.1. Obtener información del producto

```php
$product = Product::with(['species', 'captureZone'])->find($productId);

// Validar que el producto tiene especie y zona de captura
if (!$product->species || !$product->capture_zone_id) {
    throw new \Exception("El producto #{$productId} debe tener especie y zona de captura");
}
```

#### 3.2. Generar el lote automáticamente

**Formato del lote**: `DDMMAAFFFXXREC`

- **DD**: Día (2 dígitos, con cero a la izquierda si es necesario)
- **MM**: Mes (2 dígitos, con cero a la izquierda si es necesario)
- **AA**: Año (2 últimos dígitos)
- **F**: Código FAO del producto (obtenido de `product->species->fao`)
- **X**: ID de zona de captura (obtenido de `product->capture_zone_id`) - siempre 2 dígitos, rellenado con ceros a la izquierda si es necesario (ej: 3 → "03", 15 → "15")
- **REC**: Literal "REC" (de recepción)

**Ejemplo**:
- Fecha: 2025-12-15
- Código FAO: "27"
- Zona de captura ID: 3
- **Lote generado**: `151225273REC`

**Implementación**:

```php
private function generateLotFromReception(RawMaterialReception $reception, Product $product): string
{
    // Obtener fecha de la recepción
    $date = strtotime($reception->date);
    
    // DD: Día (2 dígitos)
    $day = date('d', $date);
    
    // MM: Mes (2 dígitos)
    $month = date('m', $date);
    
    // AA: Año (2 últimos dígitos)
    $year = date('y', $date);
    
    // F: Código FAO (del producto->species->fao)
    $faoCode = $product->species->fao ?? '';
    
    // X: ID de zona de captura (del producto->capture_zone_id) - siempre 2 dígitos con ceros a la izquierda
    $captureZoneId = str_pad((string)$product->capture_zone_id, 2, '0', STR_PAD_LEFT);
    
    // REC: Literal "REC"
    $rec = 'REC';
    
    // Construir lote: DDMMAAFFFXXREC
    return $day . $month . $year . $faoCode . $captureZoneId . $rec;
}
```

#### 3.3. Obtener el precio

```php
// Si viene en el request, usarlo
$price = $detail['price'] ?? null;

// Si no viene, buscar del histórico
if ($price === null) {
    $price = $this->getDefaultPrice($productId, $reception->supplier_id);
}
```

**Método para obtener precio del histórico**:

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

#### 3.4. Crear la línea de recepción

```php
$reception->products()->create([
    'product_id' => $productId,
    'lot' => $lot, // Lote generado automáticamente
    'net_weight' => $detail['netWeight'],
    'price' => $price,
]);
```

#### 3.5. Crear las cajas

```php
$numBoxes = max(1, $detail['boxes'] ?? 1);
$weightPerBox = $detail['netWeight'] / $numBoxes;

for ($i = 0; $i < $numBoxes; $i++) {
    $box = new Box();
    $box->article_id = $productId;
    $box->lot = $lot; // Mismo lote para todas las cajas del mismo producto
    $box->gs1_128 = $this->generateGS1128($reception, $productId, $i);
    $box->gross_weight = $weightPerBox * 1.02; // 2% estimado para peso bruto
    $box->net_weight = $weightPerBox;
    $box->save();
    
    // Vincular caja al palet
    PalletBox::create([
        'pallet_id' => $pallet->id,
        'box_id' => $box->id,
    ]);
}
```

**Generar GS1-128 único**:

```php
private function generateGS1128(RawMaterialReception $reception, int $productId, int $index = 0): string
{
    return 'GS1-' . $reception->id . '-' . $productId . '-' . $index . '-' . time();
}
```

---

## 📝 Implementación Completa

### Método Principal: `store()`

```php
public function store(Request $request)
{
    // Validar request
    $validated = $request->validate([
        'supplier.id' => 'required|exists:suppliers,id',
        'date' => 'required|date',
        'notes' => 'nullable|string',
        'details' => 'required|array|min:1',
        'details.*.product.id' => 'required|exists:products,id',
        'details.*.netWeight' => 'required|numeric|min:0',
        'details.*.boxes' => 'nullable|integer|min:1',
        'details.*.price' => 'nullable|numeric|min:0',
    ]);
    
    return DB::transaction(function () use ($request) {
        // 1. Crear recepción
        $reception = new RawMaterialReception();
        $reception->supplier_id = $request->supplier['id'];
        $reception->date = $request->date;
        $reception->notes = $request->notes ?? null;
        $reception->creation_mode = 'lines';
        $reception->save();
        
        // 2. Crear palet automático
        $pallet = new Pallet();
        $pallet->reception_id = $reception->id;
        $pallet->observations = "Auto-generado desde recepción #{$reception->id}";
        $pallet->status = Pallet::STATE_REGISTERED;
        $pallet->save();
        
        // 3. Procesar cada línea
        foreach ($request->details as $detail) {
            $productId = $detail['product']['id'];
            
            // Cargar producto con relaciones necesarias
            $product = Product::with(['species', 'captureZone'])->find($productId);
            
            if (!$product) {
                throw new \Exception("Producto #{$productId} no encontrado");
            }
            
            if (!$product->species) {
                throw new \Exception("El producto #{$productId} debe tener una especie asociada");
            }
            
            if (!$product->capture_zone_id) {
                throw new \Exception("El producto #{$productId} debe tener una zona de captura asociada");
            }
            
            // Generar lote automáticamente
            $lot = $this->generateLotFromReception($reception, $product);
            
            // Obtener precio
            $price = $detail['price'] ?? $this->getDefaultPrice($productId, $reception->supplier_id);
            
            // Crear línea de recepción
            $reception->products()->create([
                'product_id' => $productId,
                'lot' => $lot,
                'net_weight' => $detail['netWeight'],
                'price' => $price,
            ]);
            
            // Crear cajas
            $numBoxes = max(1, $detail['boxes'] ?? 1);
            $weightPerBox = $detail['netWeight'] / $numBoxes;
            
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
        
        // 4. Cargar relaciones para respuesta
        $reception->load('supplier', 'products.product', 'pallets');
        
        return new RawMaterialReceptionResource($reception);
    });
}
```

### Métodos Auxiliares

```php
/**
 * Generar lote desde recepción
 * Formato: DDMMAAFFFXXREC
 */
private function generateLotFromReception(RawMaterialReception $reception, Product $product): string
{
    $date = strtotime($reception->date);
    $day = date('d', $date);           // DD
    $month = date('m', $date);         // MM
    $year = date('y', $date);           // AA
    $faoCode = $product->species->fao ?? '';  // F
    $captureZoneId = str_pad((string)$product->capture_zone_id, 2, '0', STR_PAD_LEFT); // X - siempre 2 dígitos
    $rec = 'REC';                       // REC
    
    return $day . $month . $year . $faoCode . $captureZoneId . $rec;
}

/**
 * Obtener precio por defecto del histórico
 */
private function getDefaultPrice(int $productId, int $supplierId): ?float
{
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

/**
 * Generar GS1-128 único
 */
private function generateGS1128(RawMaterialReception $reception, int $productId, int $index = 0): string
{
    return 'GS1-' . $reception->id . '-' . $productId . '-' . $index . '-' . time();
}
```

---

## ✅ Checklist de Implementación

### Estructura de Base de Datos

- [ ] Tabla `raw_material_receptions` con campo `creation_mode`
- [ ] Tabla `raw_material_reception_products` con campo `lot`
- [ ] Tabla `pallets` con campo `reception_id`
- [ ] Tabla `boxes` con campos `article_id`, `lot`, `gs1_128`, `gross_weight`, `net_weight`
- [ ] Tabla `pallet_boxes` (tabla pivot entre palets y cajas)
- [ ] Tabla `products` con relaciones a `species` y `capture_zones`
- [ ] Tabla `species` con campo `fao`
- [ ] Tabla `capture_zones` con campo `id`

### Validaciones

- [ ] Validar que el producto existe
- [ ] Validar que el producto tiene especie asociada
- [ ] Validar que el producto tiene zona de captura asociada
- [ ] Validar que la especie tiene código FAO
- [ ] Validar que el proveedor existe
- [ ] Validar que la fecha es válida
- [ ] Validar que el peso neto es positivo
- [ ] Validar que el número de cajas es positivo (si se proporciona)

### Lógica de Negocio

- [ ] Crear recepción con `creation_mode = 'lines'`
- [ ] Crear un solo palet automático para toda la recepción
- [ ] Generar lote con formato `DDMMAAFFFXXREC` para cada producto
- [ ] Crear línea de recepción por cada elemento en `details[]`
- [ ] Crear cajas automáticamente según el número de cajas especificado
- [ ] Vincular todas las cajas al palet automático
- [ ] Obtener precio del request o del histórico
- [ ] Usar transacciones para garantizar consistencia

### Relaciones y Carga de Datos

- [ ] Cargar `Product` con `species` y `captureZone`
- [ ] Cargar recepción con `supplier`, `products.product`, `pallets` para la respuesta

---

## 🔍 Ejemplo Completo

### Request

```json
{
  "supplier": { "id": 1 },
  "date": "2025-12-15",
  "notes": "Recepción de prueba",
  "details": [
    {
      "product": { "id": 5 },
      "netWeight": 250.50,
      "boxes": 10,
      "price": 12.50
    }
  ]
}
```

### Datos del Producto #5

- **Especie**: ID 3, Código FAO: "27"
- **Zona de Captura**: ID 3

### Procesamiento

1. **Recepción creada**: ID 100
2. **Palet creado**: ID 200, `reception_id = 100`
3. **Lote generado**: `151225273REC`
   - DD: 15 (día)
   - MM: 12 (mes)
   - AA: 25 (año)
   - F: 27 (código FAO)
   - X: 3 (zona de captura ID)
   - REC: REC
4. **Línea de recepción creada**: `product_id = 5`, `lot = "151225273REC"`, `net_weight = 250.50`, `price = 12.50`
5. **10 cajas creadas**: Cada una con `lot = "151225273REC"`, `net_weight = 25.05` (250.50 / 10)
6. **Todas las cajas vinculadas al palet #200**

---

## ⚠️ Consideraciones Importantes

### 1. Formato del Lote

- El formato **debe ser exactamente**: `DDMMAAFFFXXREC`
- Todos los componentes deben estar presentes
- El código FAO puede tener diferentes longitudes (1-3 caracteres típicamente)
- El ID de zona de captura es numérico
- El literal "REC" debe estar en mayúsculas

### 2. Palet Automático

- **Solo se crea un palet** para toda la recepción
- El palet agrupa todas las cajas de todos los productos
- El estado del palet debe ser `STATE_REGISTERED` (registrado)
- Las observaciones deben indicar que es auto-generado

### 3. Cajas

- Se crean automáticamente según el número especificado en `boxes`
- Si no se especifica `boxes`, se crea 1 caja
- El peso se divide equitativamente entre las cajas
- Todas las cajas del mismo producto tienen el mismo lote
- El peso bruto se estima como 2% más que el peso neto

### 4. Precios

- Si viene en el request, se usa ese precio
- Si no viene, se busca del histórico (última recepción del mismo proveedor y producto)
- Si no se encuentra, el precio puede ser `null` (pero esto puede afectar cálculos de costes)

### 5. Validaciones Críticas

- El producto **debe tener** especie y zona de captura
- La especie **debe tener** código FAO
- Sin estos datos, **no se puede generar el lote** correctamente

---

## 🔗 Referencias

- Estructura de tablas: Ver migraciones de base de datos
- Modelos: `RawMaterialReception`, `Pallet`, `Box`, `Product`, `Species`, `CaptureZone`
- Relaciones: Ver modelos Eloquent para entender las relaciones entre entidades

---

## 📝 Notas Finales

Esta implementación garantiza que:
- ✅ El circuito completo de recepción funciona (recepción → palet → cajas → líneas)
- ✅ El lote se genera automáticamente con el formato especificado
- ✅ No se requiere intervención del frontend para el lote
- ✅ El palet se crea automáticamente agrupando todas las cajas
- ✅ La trazabilidad está completa desde la recepción hasta las cajas

**Última actualización**: Diciembre 2025

