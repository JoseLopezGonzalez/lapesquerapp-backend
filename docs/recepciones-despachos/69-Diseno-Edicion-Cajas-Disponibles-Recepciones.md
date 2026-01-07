# Diseño: Edición de Cajas Disponibles en Recepciones

## 📋 Resumen Ejecutivo

Este documento describe el diseño para permitir la edición de recepciones en modo PALLETS cuando algunas cajas ya están siendo utilizadas en producción. La solución permite modificar solo las cajas disponibles (no usadas en producción), manteniendo los totales globales de la recepción.

**Fecha de diseño**: 2025-01-XX  
**Estado**: Pendiente de aprobación  
**Alcance**: Solo modo `CREATION_MODE_PALLETS`

---

## 🎯 Objetivo

Permitir reorganizar el peso neto (`net_weight`) de las cajas disponibles dentro de un mismo palet, para poder cuadrar gastar una cantidad específica en producción, sin afectar las cajas que ya están siendo utilizadas.

---

## 🔍 Problema Actual

### Restricción Actual

Actualmente, cuando una recepción tiene alguna caja siendo utilizada en producción, **no se puede editar en absoluto**:

```php
private function validateCanEdit(RawMaterialReception $reception): void
{
    foreach ($reception->pallets as $pallet) {
        // ❌ Bloquea toda la edición si alguna caja está en producción
        foreach ($pallet->boxes as $palletBox) {
            if ($palletBox->box && $palletBox->box->productionInputs()->exists()) {
                throw new \Exception("No se puede modificar la recepción: la caja #{$palletBox->box->id} está siendo usada en producción");
            }
        }
    }
}
```

### Caso de Uso

**Escenario**: 
- Recepción con palet que tiene 10 cajas de producto X
- 3 cajas ya están siendo usadas en producción (no disponibles)
- 7 cajas están disponibles
- Necesito reorganizar los pesos de las 7 cajas disponibles para cuadrar un peso específico

**Problema**: No puedo editar la recepción porque hay cajas en producción.

---

## ✅ Solución Propuesta

### Principios

1. **Solo editar cajas disponibles**: Permitir modificar únicamente el `net_weight` de cajas que no tienen `productionInputs`
2. **Mantener totales**: Los totales por producto y generales deben mantenerse exactamente iguales
3. **Mismo palet**: Solo se pueden reorganizar cajas dentro del mismo palet
4. **Ajuste automático de redondeos**: Si hay diferencias por redondeos, ajustarlas automáticamente

### Restricciones

- ✅ **Solo modo PALLETS**: Esta funcionalidad aplica únicamente a recepciones creadas en modo `CREATION_MODE_PALLETS`
- ✅ **Solo mismo palet**: No se pueden mover cajas entre palets diferentes
- ✅ **Solo cajas disponibles**: No se pueden modificar cajas que tienen `productionInputs`
- ✅ **Solo `net_weight` y `gs1_128`**: No se pueden modificar otros campos (producto, lote, precio, etc.). El GS1-128 puede cambiar al modificar el peso neto.
- ✅ **Solo modificar existentes**: No se pueden crear ni eliminar cajas, solo modificar las existentes

---

## 🔧 Cambios Necesarios

### 1. Modificar `validateCanEdit()`

**Ubicación**: `app/Http/Controllers/v2/RawMaterialReceptionController.php`

**Cambio**: En lugar de bloquear toda la edición si hay cajas en producción, validar que:
- No se intenten modificar cajas usadas
- Los totales se mantengan iguales

**Nueva lógica**:
```php
private function validateCanEdit(RawMaterialReception $reception): void
{
    foreach ($reception->pallets as $pallet) {
        // Validar que el palet no esté vinculado a un pedido
        if ($pallet->order_id !== null) {
            throw new \Exception("No se puede modificar la recepción: el palet #{$pallet->id} está vinculado a un pedido");
        }
        
        // ✅ NUEVO: Ya no bloqueamos si hay cajas en producción
        // La validación de que no se modifiquen cajas usadas se hará en updatePalletsFromRequest()
    }
}
```

### 2. Modificar `updatePalletsFromRequest()`

**Ubicación**: `app/Http/Controllers/v2/RawMaterialReceptionController.php`

**Cambios necesarios**:

#### 2.1. Validar que solo se modifiquen cajas disponibles

Antes de procesar las cajas, validar que:
- Si una caja tiene `id` (existe), verificar que esté disponible
- Si una caja no tiene `id` (nueva), permitirla (pero esto no debería pasar según restricciones)

```php
// Para cada caja en el request
foreach ($palletData['boxes'] as $boxData) {
    $boxId = $boxData['id'] ?? null;
    
    if ($boxId) {
        // Verificar que la caja existe y está disponible
        $box = Box::with('productionInputs')->find($boxId);
        if (!$box) {
            throw new \Exception("La caja #{$boxId} no existe");
        }
        
        if ($box->productionInputs()->exists()) {
            throw new \Exception("No se puede modificar la caja #{$boxId}: está siendo usada en producción");
        }
        
        // Verificar que la caja pertenece al palet
        $palletBox = PalletBox::where('pallet_id', $pallet->id)
            ->where('box_id', $boxId)
            ->first();
        if (!$palletBox) {
            throw new \Exception("La caja #{$boxId} no pertenece al palet #{$pallet->id}");
        }
    }
}
```

#### 2.2. Validar que no se modifiquen otros campos

Validar que solo se modifique `net_weight`, no otros campos:

```php
if ($boxId && $existingBoxes->has($boxId)) {
    $box = $existingBoxes->get($boxId)->box;
    
    // ✅ Validar que solo se modifique net_weight
    $originalBox = Box::find($boxId);
    
    // Verificar que no se cambien otros campos
    if (isset($boxData['product']['id']) && $boxData['product']['id'] != $originalBox->article_id) {
        throw new \Exception("No se puede modificar el producto de la caja #{$boxId}");
    }
    if (isset($boxData['lot']) && $boxData['lot'] != $originalBox->lot) {
        throw new \Exception("No se puede modificar el lote de la caja #{$boxId}");
    }
                    // ✅ NUEVO: Permitir modificar GS1-128 (puede cambiar al modificar el peso neto)
                    // No validamos gs1128 porque puede cambiar al modificar el peso
    if (isset($boxData['grossWeight']) && $boxData['grossWeight'] != $originalBox->gross_weight) {
        throw new \Exception("No se puede modificar el peso bruto de la caja #{$boxId}");
    }
    
    // ✅ Solo permitir modificar net_weight
    $box->net_weight = $boxData['netWeight'];
    $box->save();
}
```

#### 2.3. Validar y mantener totales por producto

**Antes de guardar**, calcular los totales y compararlos con los originales:

```php
// 1. Obtener totales originales por producto+lote
$originalTotals = [];
foreach ($reception->products as $receptionProduct) {
    $key = "{$receptionProduct->product_id}_{$receptionProduct->lot}";
    $originalTotals[$key] = [
        'product_id' => $receptionProduct->product_id,
        'lot' => $receptionProduct->lot,
        'net_weight' => $receptionProduct->net_weight,
        'price' => $receptionProduct->price,
    ];
}

// 2. Calcular totales nuevos después de procesar todas las cajas
$newTotals = [];
foreach ($palletsData as $palletData) {
    foreach ($palletData['boxes'] as $boxData) {
        $productId = $boxData['product']['id'];
        $lot = $boxData['lot'] ?? $this->generateLotFromReception($reception, $productId);
        $key = "{$productId}_{$lot}";
        
        if (!isset($newTotals[$key])) {
            $newTotals[$key] = [
                'product_id' => $productId,
                'lot' => $lot,
                'net_weight' => 0,
            ];
        }
        $newTotals[$key]['net_weight'] += $boxData['netWeight'];
    }
}

// 3. Incluir cajas usadas (que no están en el request) en los totales nuevos
foreach ($reception->pallets as $pallet) {
    foreach ($pallet->boxes as $palletBox) {
        $box = $palletBox->box;
        if ($box && $box->productionInputs()->exists()) {
            // Esta caja no está en el request, pero debe incluirse en los totales
            $key = "{$box->article_id}_{$box->lot}";
            if (!isset($newTotals[$key])) {
                $newTotals[$key] = [
                    'product_id' => $box->article_id,
                    'lot' => $box->lot,
                    'net_weight' => 0,
                ];
            }
            $newTotals[$key]['net_weight'] += $box->net_weight;
        }
    }
}

// 4. Validar que los totales coincidan (con tolerancia de redondeos)
foreach ($originalTotals as $key => $original) {
    if (!isset($newTotals[$key])) {
        throw new \Exception("El producto {$original['product_id']} con lote {$original['lot']} ya no tiene cajas");
    }
    
    $difference = abs($original['net_weight'] - $newTotals[$key]['net_weight']);
    $tolerance = 0.01; // 0.01 kg de tolerancia
    
    if ($difference > $tolerance) {
        throw new \Exception(
            "El total del producto {$original['product_id']} con lote {$original['lot']} ha cambiado. " .
            "Original: {$original['net_weight']} kg, Nuevo: {$newTotals[$key]['net_weight']} kg"
        );
    }
    
    // ✅ Ajustar automáticamente si hay diferencia pequeña por redondeos
    if ($difference > 0 && $difference <= $tolerance) {
        // Ajustar la última caja procesada del producto para cuadrar
        // (esto se puede hacer ajustando el net_weight de la última caja disponible del producto)
    }
}
```

#### 2.4. Ajuste automático de redondeos

Si hay diferencias pequeñas (≤ 0.01 kg) por redondeos, ajustar automáticamente:

```php
// Después de validar totales, ajustar diferencias pequeñas
foreach ($originalTotals as $key => $original) {
    $difference = $original['net_weight'] - $newTotals[$key]['net_weight'];
    
    if (abs($difference) > 0 && abs($difference) <= 0.01) {
        // Encontrar la última caja disponible del producto que se procesó
        // y ajustar su peso para cuadrar
        $productId = $original['product_id'];
        $lot = $original['lot'];
        
        // Buscar la última caja disponible del producto en el palet
        $lastBox = null;
        foreach ($pallet->boxes as $palletBox) {
            $box = $palletBox->box;
            if ($box && 
                $box->article_id == $productId && 
                $box->lot == $lot && 
                !$box->productionInputs()->exists()) {
                $lastBox = $box;
            }
        }
        
        if ($lastBox) {
            // Ajustar el peso de la última caja para cuadrar
            $lastBox->net_weight += $difference;
            $lastBox->save();
        }
    }
}
```

### 3. Prevenir eliminación de cajas usadas

**Ubicación**: `app/Http/Controllers/v2/RawMaterialReceptionController.php` - método `updatePalletsFromRequest()`

**Cambio**: No eliminar cajas que están en producción:

```php
// Eliminar cajas que ya no están en el request
$boxesToDelete = $pallet->boxes->filter(function ($palletBox) use ($processedBoxIds) {
    return !in_array($palletBox->box_id, $processedBoxIds);
});

foreach ($boxesToDelete as $palletBox) {
    $box = $palletBox->box;
    
    // ✅ NUEVO: No eliminar si está en producción
    if ($box && $box->productionInputs()->exists()) {
        throw new \Exception("No se puede eliminar la caja #{$box->id}: está siendo usada en producción");
    }
    
    // Eliminar caja disponible
    $palletBox->box->delete();
    $palletBox->delete();
}
```

### 4. Prevenir creación de nuevas cajas

**Ubicación**: `app/Http/Controllers/v2/RawMaterialReceptionController.php` - método `updatePalletsFromRequest()`

**Cambio**: Si hay cajas usadas, no permitir crear nuevas cajas:

```php
foreach ($palletData['boxes'] as $boxData) {
    $boxId = $boxData['id'] ?? null;
    
    if (!$boxId) {
        // ✅ NUEVO: Verificar si hay cajas usadas en el palet
        $hasUsedBoxes = $pallet->boxes->contains(function ($palletBox) {
            return $palletBox->box && $palletBox->box->productionInputs()->exists();
        });
        
        if ($hasUsedBoxes) {
            throw new \Exception("No se pueden crear nuevas cajas cuando hay cajas siendo usadas en producción");
        }
        
        // Crear nueva caja (solo si no hay cajas usadas)
        // ... código existente ...
    }
}
```

### 5. Prevenir eliminación de palets con cajas usadas

**Ubicación**: `app/Http/Controllers/v2/RawMaterialReceptionController.php` - método `updatePalletsFromRequest()`

**Cambio**: No eliminar palets que tienen cajas en producción:

```php
// Eliminar palets que ya no están en el request
foreach ($reception->pallets as $pallet) {
    if (!in_array($pallet->id, $processedPalletIds)) {
        // ✅ NUEVO: Verificar si tiene cajas en producción
        $hasUsedBoxes = $pallet->boxes->contains(function ($palletBox) {
            return $palletBox->box && $palletBox->box->productionInputs()->exists();
        });
        
        if ($hasUsedBoxes) {
            throw new \Exception("No se puede eliminar el palet #{$pallet->id}: tiene cajas siendo usadas en producción");
        }
        
        // Eliminar palet (solo si no tiene cajas usadas)
        // ... código existente ...
    }
}
```

### 6. Mantener precios sin cambios

**Ubicación**: `app/Http/Controllers/v2/RawMaterialReceptionController.php` - método `updatePalletsFromRequest()`

**Cambio**: No permitir modificar precios cuando hay cajas usadas:

```php
// Al regenerar líneas de recepción, mantener los precios originales
foreach ($groupedByProduct as $key => $group) {
    // ✅ NUEVO: Si hay cajas usadas, mantener el precio original
    $originalProduct = $reception->products()
        ->where('product_id', $group['product_id'])
        ->where('lot', $group['lot'])
        ->first();
    
    $price = $originalProduct ? $originalProduct->price : ($pricesMap[$key] ?? $this->getDefaultPrice($group['product_id'], $reception->supplier_id));
    
    $reception->products()->create([
        'product_id' => $group['product_id'],
        'lot' => $group['lot'],
        'net_weight' => $group['net_weight'],
        'price' => $price, // ✅ Mantener precio original
    ]);
}
```

---

## 📊 Flujo de Validación

```
Request de edición
    ↓
¿Modo PALLETS?
    ├─ NO → Validación actual (bloquear si hay cajas usadas)
    └─ SÍ → Continuar
        ↓
¿Hay cajas usadas en algún palet?
    ├─ NO → Edición normal (sin restricciones)
    └─ SÍ → Validación estricta
        ↓
Para cada palet con cajas usadas:
    ├─ ¿Se intenta modificar caja usada? → ❌ Error
    ├─ ¿Se intenta crear nueva caja? → ❌ Error
    ├─ ¿Se intenta eliminar caja usada? → ❌ Error
    ├─ ¿Se intenta eliminar palet con cajas usadas? → ❌ Error
    ├─ ¿Se modifica campo distinto a net_weight? → ❌ Error
    └─ ¿Se modifica precio? → ❌ Error
        ↓
Calcular totales nuevos (incluyendo cajas usadas)
    ↓
¿Totales coinciden con originales?
    ├─ NO (diferencia > 0.01 kg) → ❌ Error
    └─ SÍ (diferencia ≤ 0.01 kg) → ✅ Ajustar automáticamente
        ↓
Guardar cambios
```

---

## 🧪 Casos de Prueba

### Caso 1: Edición Normal (Sin Cajas Usadas)

**Estado inicial**:
- Recepción con palet #15
- 10 cajas disponibles (ninguna en producción)

**Request**:
```json
{
  "pallets": [
    {
      "id": 15,
      "boxes": [
        { "id": 1, "netWeight": 25.5 },
        { "id": 2, "netWeight": 24.5 }
      ]
    }
  ]
}
```

**Resultado esperado**: ✅ Edición normal sin restricciones

---

### Caso 2: Reorganizar Pesos (Con Cajas Usadas)

**Estado inicial**:
- Recepción con palet #15
- 10 cajas: 3 usadas (IDs: 1, 2, 3), 7 disponibles (IDs: 4-10)
- Total original: 250 kg (30 kg cajas usadas + 220 kg cajas disponibles)

**Request**:
```json
{
  "pallets": [
    {
      "id": 15,
      "boxes": [
        { "id": 1, "netWeight": 10.0 },  // ← Caja usada (no se debe modificar)
        { "id": 2, "netWeight": 10.0 },  // ← Caja usada (no se debe modificar)
        { "id": 3, "netWeight": 10.0 },  // ← Caja usada (no se debe modificar)
        { "id": 4, "netWeight": 30.0 },  // ← Disponible (modificada: era 25.0)
        { "id": 5, "netWeight": 35.0 },  // ← Disponible (modificada: era 30.0)
        { "id": 6, "netWeight": 40.0 },  // ← Disponible (modificada: era 35.0)
        { "id": 7, "netWeight": 35.0 },  // ← Disponible (modificada: era 40.0)
        { "id": 8, "netWeight": 30.0 },  // ← Disponible (modificada: era 35.0)
        { "id": 9, "netWeight": 25.0 },  // ← Disponible (modificada: era 30.0)
        { "id": 10, "netWeight": 25.0 }  // ← Disponible (modificada: era 25.0)
      ]
    }
  ]
}
```

**Validaciones**:
1. ✅ Cajas usadas (1, 2, 3) no se modifican (se ignoran o se valida que sean iguales)
2. ✅ Total nuevo: 30 + 30 + 30 + 30 + 35 + 40 + 35 + 30 + 25 + 25 = 300 kg
3. ❌ **Error esperado**: Total no coincide (original: 250 kg, nuevo: 300 kg)

**Request corregido**:
```json
{
  "pallets": [
    {
      "id": 15,
      "boxes": [
        { "id": 1, "netWeight": 10.0 },  // ← Caja usada (se mantiene igual)
        { "id": 2, "netWeight": 10.0 },  // ← Caja usada (se mantiene igual)
        { "id": 3, "netWeight": 10.0 },  // ← Caja usada (se mantiene igual)
        { "id": 4, "netWeight": 30.0 },  // ← Disponible (modificada: +5 kg)
        { "id": 5, "netWeight": 30.0 },  // ← Disponible (sin cambio)
        { "id": 6, "netWeight": 30.0 },  // ← Disponible (modificada: -5 kg)
        { "id": 7, "netWeight": 35.0 },  // ← Disponible (modificada: -5 kg)
        { "id": 8, "netWeight": 30.0 },  // ← Disponible (modificada: -5 kg)
        { "id": 9, "netWeight": 30.0 },  // ← Disponible (sin cambio)
        { "id": 10, "netWeight": 25.0 }  // ← Disponible (sin cambio)
      ]
    }
  ]
}
```

**Validaciones**:
1. ✅ Cajas usadas (1, 2, 3) se mantienen iguales
2. ✅ Total nuevo: 30 + 30 + 30 + 30 + 30 + 30 + 35 + 30 + 30 + 25 = 300 kg
3. ✅ Total original: 30 + 30 + 30 + 220 = 310 kg
4. ❌ **Error esperado**: Total no coincide (diferencia: 10 kg)

**Request final correcto**:
```json
{
  "pallets": [
    {
      "id": 15,
      "boxes": [
        { "id": 1, "netWeight": 10.0 },  // ← Caja usada (se mantiene igual)
        { "id": 2, "netWeight": 10.0 },  // ← Caja usada (se mantiene igual)
        { "id": 3, "netWeight": 10.0 },  // ← Caja usada (se mantiene igual)
        { "id": 4, "netWeight": 31.43 }, // ← Disponible (reorganizada)
        { "id": 5, "netWeight": 31.43 }, // ← Disponible (reorganizada)
        { "id": 6, "netWeight": 31.43 }, // ← Disponible (reorganizada)
        { "id": 7, "netWeight": 31.43 }, // ← Disponible (reorganizada)
        { "id": 8, "netWeight": 31.43 }, // ← Disponible (reorganizada)
        { "id": 9, "netWeight": 31.43 }, // ← Disponible (reorganizada)
        { "id": 10, "netWeight": 31.42 }  // ← Disponible (reorganizada, ajuste por redondeo)
      ]
    }
  ]
}
```

**Validaciones**:
1. ✅ Cajas usadas (1, 2, 3) se mantienen iguales
2. ✅ Total nuevo: 30 + 31.43*6 + 31.42 = 30 + 188.58 + 31.42 = 250 kg
3. ✅ Total original: 250 kg
4. ✅ **Éxito**: Totales coinciden

---

### Caso 3: Intentar Modificar Caja Usada

**Request**:
```json
{
  "pallets": [
    {
      "id": 15,
      "boxes": [
        { "id": 1, "netWeight": 15.0 }  // ← Caja usada (intento de modificación)
      ]
    }
  ]
}
```

**Resultado esperado**: ❌ Error: "No se puede modificar la caja #1: está siendo usada en producción"

---

### Caso 4: Intentar Crear Nueva Caja (Con Cajas Usadas)

**Request**:
```json
{
  "pallets": [
    {
      "id": 15,
      "boxes": [
        { "id": 1, "netWeight": 10.0 },  // ← Caja usada (existe)
        { "netWeight": 25.0 }              // ← Nueva caja (intento de creación)
      ]
    }
  ]
}
```

**Resultado esperado**: ❌ Error: "No se pueden crear nuevas cajas cuando hay cajas siendo usadas en producción"

---

### Caso 5: Intentar Eliminar Caja Usada

**Request**:
```json
{
  "pallets": [
    {
      "id": 15,
      "boxes": [
        { "id": 4, "netWeight": 25.0 }  // ← Solo caja disponible (caja usada #1 no está en el request)
      ]
    }
  ]
}
```

**Estado inicial**: Palet tiene cajas #1 (usada), #2 (usada), #3 (usada), #4 (disponible)

**Resultado esperado**: ❌ Error: "No se puede eliminar la caja #1: está siendo usada en producción"

---

### Caso 6: Ajuste Automático de Redondeos

**Estado inicial**:
- Total original: 100.00 kg
- 3 cajas disponibles: 33.33 kg cada una (suma: 99.99 kg)

**Request**:
```json
{
  "pallets": [
    {
      "id": 15,
      "boxes": [
        { "id": 1, "netWeight": 33.33 },
        { "id": 2, "netWeight": 33.33 },
        { "id": 3, "netWeight": 33.33 }
      ]
    }
  ]
}
```

**Resultado esperado**: 
- ✅ Diferencia detectada: 0.01 kg
- ✅ Ajuste automático: última caja pasa a 33.34 kg
- ✅ Total final: 100.00 kg

---

## 📝 Resumen de Validaciones

| Validación | Condición | Acción |
|------------|-----------|--------|
| Modificar caja usada | `box.productionInputs()->exists()` | ❌ Error |
| Crear nueva caja | Hay cajas usadas en el palet | ❌ Error |
| Eliminar caja usada | Caja no está en request y tiene `productionInputs` | ❌ Error |
| Eliminar palet | Palet tiene cajas usadas | ❌ Error |
| Modificar campo distinto a `net_weight` y `gs1_128` | Cualquier campo diferente (excepto gs1_128) | ❌ Error |
| Modificar precio | Precio en request diferente al original | ❌ Error |
| Total no coincide | Diferencia > 0.01 kg | ❌ Error |
| Total con diferencia pequeña | Diferencia ≤ 0.01 kg | ✅ Ajustar automáticamente |
| Mover caja entre palets | Caja en palet diferente | ❌ Error (solo mismo palet) |

---

## 🔄 Cambios en el Modelo `RawMaterialReception`

### Método `getCanEditAttribute()`

**Ubicación**: `app/Models/RawMaterialReception.php`

**Cambio**: Modificar para permitir edición parcial cuando hay cajas usadas:

```php
public function getCanEditAttribute(): bool
{
    // Cargar relaciones si no están cargadas
    if (!$this->relationLoaded('pallets')) {
        $this->load('pallets.boxes.box.productionInputs');
    }

    foreach ($this->pallets as $pallet) {
        // Verificar si el palet está vinculado a un pedido
        if ($pallet->order_id !== null) {
            return false;
        }
    }

    // ✅ NUEVO: Ya no bloqueamos si hay cajas en producción
    // La edición parcial se permitirá, pero con validaciones estrictas
    return true;
}
```

### Método `getCannotEditReasonAttribute()`

**Ubicación**: `app/Models/RawMaterialReception.php`

**Cambio**: Actualizar mensaje para reflejar edición parcial:

```php
public function getCannotEditReasonAttribute(): ?string
{
    if ($this->can_edit) {
        return null;
    }

    // Cargar relaciones si no están cargadas
    if (!$this->relationLoaded('pallets')) {
        $this->load('pallets.boxes.box.productionInputs');
    }

    foreach ($this->pallets as $pallet) {
        if ($pallet->order_id !== null) {
            return "El palet #{$pallet->id} está vinculado a un pedido";
        }
    }

    return "No se puede editar la recepción";
}
```

---

## 🎨 Consideraciones de Frontend

### Información a Mostrar

1. **Indicador de cajas usadas**: Mostrar claramente qué cajas están siendo usadas en producción
2. **Campos bloqueados**: Mostrar campos de cajas usadas como read-only
3. **Validación en tiempo real**: Validar que los totales coincidan mientras el usuario edita
4. **Mensajes de error claros**: Explicar por qué no se puede modificar una caja

### Ejemplo de UI

```
Palet #15
├─ Caja #1: 10.0 kg [🔒 USADA EN PRODUCCIÓN]
├─ Caja #2: 10.0 kg [🔒 USADA EN PRODUCCIÓN]
├─ Caja #3: 10.0 kg [🔒 USADA EN PRODUCCIÓN]
├─ Caja #4: 25.0 kg [✏️ EDITABLE]
├─ Caja #5: 30.0 kg [✏️ EDITABLE]
└─ ...

Total: 250.0 kg (30.0 kg usadas + 220.0 kg disponibles)
```

---

## ✅ Checklist de Implementación

- [ ] Modificar `validateCanEdit()` para permitir edición parcial
- [ ] Agregar validación de cajas usadas en `updatePalletsFromRequest()`
- [ ] Agregar validación de campos modificables
- [ ] Implementar cálculo y validación de totales
- [ ] Implementar ajuste automático de redondeos
- [ ] Prevenir eliminación de cajas usadas
- [ ] Prevenir creación de nuevas cajas cuando hay cajas usadas
- [ ] Prevenir eliminación de palets con cajas usadas
- [ ] Mantener precios sin cambios
- [ ] Actualizar `getCanEditAttribute()` en modelo
- [ ] Actualizar `getCannotEditReasonAttribute()` en modelo
- [ ] Agregar tests unitarios
- [ ] Agregar tests de integración
- [ ] Documentar cambios en API

---

## 🔗 Referencias

- [Guía Backend Edición Recepciones](./65-Guia-Backend-Edicion-Recepciones.md)
- [Guía Frontend Edición Recepciones](./64-Guia-Frontend-Edicion-Recepciones.md)
- [Documentación Recepciones](./60-Recepciones-Materia-Prima.md)
- [Investigación Impacto Cajas Disponibles](../produccion/analisis/INVESTIGACION-Impacto-Cajas-Disponibles-Palets.md)

---

**Última actualización**: 2025-01-XX  
**Estado**: Pendiente de aprobación

