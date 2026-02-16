# Guía Backend: Lógica de Edición de Recepciones de Materia Prima

## 📋 Resumen

Este documento explica la **lógica de edición de recepciones de materia prima** desde la perspectiva del backend. Describe cómo el sistema determina si se debe editar, crear o eliminar palets y cajas según los datos recibidos.

---

## 🎯 Principios de Diseño

### 1. Editar en lugar de Eliminar/Recrear

**Antes**: Se eliminaban todos los palets y cajas, luego se recreaban desde cero.

**Ahora**: Se editan los existentes, se crean los nuevos, y solo se eliminan los que ya no están en el request.

### 2. Identificación por ID

- Si un elemento viene con `id` → **Editar** el existente
- Si un elemento no viene con `id` → **Crear** uno nuevo
- Si un elemento existente no está en el request → **Eliminar**

### 3. Modo de Edición según Modo de Creación

- **Modo LINES** (`creation_mode = 'lines'`): Se edita con `details`
- **Modo PALLETS** (`creation_mode = 'pallets'`): Se edita con `pallets` (incluyendo IDs)

---

## 🔍 Flujo de Edición

### Paso 1: Validación de Restricciones

Antes de cualquier modificación, se valida que la recepción se pueda editar:

```php
private function validateCanEdit(RawMaterialReception $reception): void
{
    foreach ($reception->pallets as $pallet) {
        // No se puede editar si el palet está vinculado a un pedido
        if ($pallet->order_id !== null) {
            throw new \Exception("No se puede modificar la recepción: el palet #{$pallet->id} está vinculado a un pedido");
        }

        // No se puede editar si alguna caja está en producción
        foreach ($pallet->boxes as $palletBox) {
            if ($palletBox->box && $palletBox->box->productionInputs()->exists()) {
                throw new \Exception("No se puede modificar la recepción: la caja #{$palletBox->box->id} está siendo usada en producción");
            }
        }
    }
}
```

### Paso 2: Validación del Request según Modo

```php
if ($reception->creation_mode === RawMaterialReception::CREATION_MODE_LINES) {
    // Validar 'details'
} elseif ($reception->creation_mode === RawMaterialReception::CREATION_MODE_PALLETS) {
    // Validar 'pallets' con IDs opcionales
    'pallets.*.id' => 'nullable|integer|exists:tenant.pallets,id',
    'pallets.*.boxes.*.id' => 'nullable|integer|exists:tenant.boxes,id',
}
```

### Paso 3: Actualización según Modo

---

## 📦 Modo PALLETS: `updatePalletsFromRequest()`

### Lógica de Palets

```php
foreach ($palletsData as $palletData) {
    $palletId = $palletData['id'] ?? null;
    
    if ($palletId && $existingPallets->has($palletId)) {
        // ✅ EDITAR palet existente
        $pallet = $existingPallets->get($palletId);
        $pallet->observations = $palletData['observations'] ?? null;
        $pallet->save();
    } else {
        // ✅ CREAR nuevo palet
        $pallet = new Pallet();
        $pallet->reception_id = $reception->id;
        $pallet->observations = $palletData['observations'] ?? null;
        $pallet->status = Pallet::STATE_REGISTERED;
        $pallet->save();
    }
}
```

### Lógica de Cajas

```php
foreach ($palletData['boxes'] as $boxData) {
    $boxId = $boxData['id'] ?? null;
    
    if ($boxId && $existingBoxes->has($boxId)) {
        // ✅ EDITAR caja existente
        $box = $existingBoxes->get($boxId)->box;
        $box->article_id = $productId;
        $box->lot = $lot;
        $box->gs1_128 = $boxData['gs1128'];
        $box->gross_weight = $boxData['grossWeight'];
        $box->net_weight = $boxData['netWeight'];
        $box->save();
    } else {
        // ✅ CREAR nueva caja
        $box = new Box();
        $box->article_id = $productId;
        $box->lot = $lot;
        $box->gs1_128 = $boxData['gs1128'];
        $box->gross_weight = $boxData['grossWeight'];
        $box->net_weight = $boxData['netWeight'];
        $box->save();
        
        PalletBox::create([
            'pallet_id' => $pallet->id,
            'box_id' => $box->id,
        ]);
    }
}
```

### Eliminación de Elementos No Incluidos

```php
// Eliminar cajas que ya no están en el request
foreach ($pallet->boxes as $palletBox) {
    if (!in_array($palletBox->box_id, $processedBoxIds)) {
        $palletBox->box->delete();  // ✅ ELIMINAR caja
        $palletBox->delete();
    }
}

// Eliminar palets que ya no están en el request
foreach ($reception->pallets as $pallet) {
    if (!in_array($pallet->id, $processedPalletIds)) {
        // Usar eliminación directa de BD para evitar evento deleting
        DB::table('pallets')->where('id', $pallet->id)->delete();  // ✅ ELIMINAR palet
    }
}
```

### Regeneración de Líneas de Recepción

```php
// Agrupar por producto y lote
$groupedByProduct = [];
foreach ($palletsData as $palletData) {
    // ... procesar palets y cajas ...
    $key = "{$productId}_{$lot}";
    $groupedByProduct[$key]['net_weight'] += $totalWeight;
}

// Eliminar líneas antiguas y crear nuevas
$reception->products()->delete();
foreach ($groupedByProduct as $group) {
    $reception->products()->create([
        'product_id' => $group['product_id'],
        'net_weight' => $group['net_weight'],
        'price' => $group['price'],
    ]);
}
```

---

## 📋 Modo LINES: `updateDetailsFromRequest()`

### Lógica Especial

En modo LINES, las cajas se generan automáticamente, por lo que:

1. **Se mantiene el palet único** (no se elimina)
2. **Se eliminan todas las cajas existentes** (usando eliminación directa de BD)
3. **Se recrean las cajas** según los nuevos detalles

```php
// Mantener palet único
$pallet = $reception->pallets->first();
if (!$pallet) {
    $pallet = new Pallet();
    $pallet->reception_id = $reception->id;
    $pallet->save();
} else {
    $pallet->observations = "Auto-generado desde recepción #{$reception->id}";
    $pallet->save();
}

// Eliminar todas las cajas existentes (usando eliminación directa de BD)
foreach ($pallet->boxes as $palletBox) {
    DB::table('boxes')->where('id', $palletBox->box_id)->delete();
}
DB::table('pallet_boxes')->where('pallet_id', $pallet->id)->delete();

// Recrear cajas según nuevos detalles
foreach ($details as $detail) {
    $numBoxes = max(1, $detail['boxes'] ?? 1);
    $weightPerBox = $detail['netWeight'] / $numBoxes;
    
    for ($i = 0; $i < $numBoxes; $i++) {
        $box = new Box();
        // ... asignar valores ...
        $box->save();
        
        PalletBox::create([
            'pallet_id' => $pallet->id,
            'box_id' => $box->id,
        ]);
    }
}
```

---

## ⚠️ Manejo de Eventos de Eliminación

### Problema

El modelo `Pallet` tiene un evento `deleting` que bloquea la eliminación de palets con `reception_id !== null`:

```php
static::deleting(function ($pallet) {
    if ($pallet->reception_id !== null) {
        throw new \Exception('No se puede eliminar un palet que proviene de una recepción...');
    }
});
```

### Solución

Cuando se eliminan palets desde el contexto de recepción, usar **eliminación directa de BD** para evitar el evento:

```php
// ❌ NO usar: $pallet->delete(); (dispara evento)
// ✅ Usar: DB::table('pallets')->where('id', $pallet->id)->delete();
```

### Casos de Uso

1. **Eliminar palet completo** (no está en el request):
   ```php
   DB::table('pallets')->where('id', $pallet->id)->delete();
   ```

2. **Eliminar cajas de modo LINES** (se recrean todas):
   ```php
   DB::table('boxes')->where('id', $boxId)->delete();
   ```

3. **Eliminar cajas individuales** (modo PALLETS):
   ```php
   $box->delete(); // ✅ OK porque ya validamos que no está en producción
   ```

---

## 🔄 Regeneración de Líneas de Recepción

### Cuándo se Regeneran

Las líneas de recepción (`raw_material_reception_products`) se regeneran automáticamente:

1. **Después de editar recepción** (modo PALLETS o LINES)
2. **Después de editar palet individual** (solo modo PALLETS)

### Cómo se Regeneran

1. **Agrupar por producto y lote**:
   ```php
   $key = "{$productId}_{$lot}";
   $groupedByProduct[$key]['net_weight'] += $totalWeight;
   ```

2. **Eliminar líneas antiguas**:
   ```php
   $reception->products()->delete();
   ```

3. **Crear nuevas líneas**:
   ```php
   foreach ($groupedByProduct as $group) {
       $reception->products()->create([
           'product_id' => $group['product_id'],
           'net_weight' => $group['net_weight'],
           'price' => $group['price'],
       ]);
   }
   ```

### Mantenimiento de Precios

- **Modo PALLETS**: El precio viene del request (`pallets[].price`)
- **Modo LINES**: El precio viene del request o del histórico (`details[].price ?? getDefaultPrice()`)
- **Al editar palet individual**: Se mantiene el precio existente de las líneas de recepción

---

## 📊 Diagrama de Flujo

### Modo PALLETS

```
Request con palets[]
    ↓
¿Palet tiene id?
    ├─ SÍ → Actualizar palet existente
    └─ NO → Crear nuevo palet
        ↓
    Para cada caja:
    ¿Caja tiene id?
        ├─ SÍ → Actualizar caja existente
        └─ NO → Crear nueva caja
        ↓
    Eliminar cajas no incluidas
    ↓
Eliminar palets no incluidos
    ↓
Regenerar líneas de recepción
```

### Modo LINES

```
Request con details[]
    ↓
Mantener palet único
    ↓
Eliminar todas las cajas existentes
    ↓
Recrear cajas según details
    ↓
Regenerar líneas de recepción
```

---

## 🛡️ Validaciones y Seguridad

### Validaciones de Request

```php
// Modo PALLETS
'pallets.*.id' => 'nullable|integer|exists:tenant.pallets,id',
'pallets.*.boxes.*.id' => 'nullable|integer|exists:tenant.boxes,id',
```

### Validaciones de Negocio

1. **Restricciones comunes** (ambos modos):
   - No editar si palet vinculado a pedido
   - No editar si caja en producción

2. **Validación de pertenencia**:
   - Verificar que los IDs de palets pertenezcan a la recepción
   - Verificar que los IDs de cajas pertenezcan a los palets de la recepción

### Transacciones

Toda la operación de edición se ejecuta dentro de una transacción:

```php
return DB::transaction(function () use ($reception, $validated, $request) {
    // ... lógica de edición ...
});
```

---

## 🔧 Métodos Privados

### `validateCanEdit(RawMaterialReception $reception): void`

Valida que la recepción se pueda editar según las restricciones comunes.

### `updatePalletsFromRequest(RawMaterialReception $reception, array $palletsData): void`

Actualiza recepción en modo PALLETS:
- Edita/crea/elimina palets
- Edita/crea/elimina cajas
- Regenera líneas de recepción

### `updateDetailsFromRequest(RawMaterialReception $reception, array $details, int $supplierId): void`

Actualiza recepción en modo LINES:
- Mantiene palet único
- Recrea cajas según detalles
- Regenera líneas de recepción

### `createPalletsFromRequest(RawMaterialReception $reception, array $pallets): void`

Crea recepción en modo PALLETS (usado en `store()`).

### `createDetailsFromRequest(RawMaterialReception $reception, array $details, int $supplierId): void`

Crea recepción en modo LINES (usado en `store()`).

---

## 📝 Ejemplos de Casos de Uso

### Caso 1: Editar Palet Existente

**Request**:
```json
{
  "pallets": [
    {
      "id": 15,  // ← Palet existente
      "product": { "id": 5 },
      "price": 12.50,
      "boxes": [
        { "id": 42, "gs1128": "...", ... },  // ← Caja existente
        { "gs1128": "...", ... }              // ← Nueva caja
      ]
    }
  ]
}
```

**Acciones**:
1. ✅ Actualizar palet #15
2. ✅ Actualizar caja #42
3. ✅ Crear nueva caja
4. ✅ Eliminar cajas del palet #15 que no están en el request

### Caso 2: Agregar Nuevo Palet

**Request**:
```json
{
  "pallets": [
    {
      "id": 15,  // ← Palet existente
      ...
    },
    {
      // ← Sin ID = nuevo palet
      "product": { "id": 6 },
      "price": 15.00,
      "boxes": [...]
    }
  ]
}
```

**Acciones**:
1. ✅ Actualizar palet #15
2. ✅ Crear nuevo palet
3. ✅ Eliminar palets que no están en el request

### Caso 3: Eliminar Palet

**Request**:
```json
{
  "pallets": [
    {
      "id": 15,  // ← Solo este palet
      ...
    }
  ]
}
```

**Estado anterior**: Recepción tenía palets #15, #16, #17

**Acciones**:
1. ✅ Actualizar palet #15
2. ✅ Eliminar palets #16 y #17 (no están en el request)

---

## ⚠️ Consideraciones Importantes

### 1. Eliminación Directa de BD

**Usar cuando**:
- Se eliminan palets desde el contexto de recepción
- Se eliminan todas las cajas en modo LINES

**No usar cuando**:
- Se eliminan cajas individuales en modo PALLETS (ya validamos restricciones)

### 2. Recarga de Relaciones

Después de eliminar cajas, recargar la relación para evitar inconsistencias:

```php
if ($boxesToDelete->isNotEmpty()) {
    $pallet->load('boxes.box');
}
```

### 3. Agrupación por Producto y Lote

Las líneas de recepción se agrupan por `product_id` y `lot`. Si hay múltiples palets con el mismo producto pero diferente lote, se crearán líneas separadas.

### 4. Precios en Líneas de Recepción

- **Modo PALLETS**: El precio viene del request de cada palet
- **Modo LINES**: El precio viene del request o del histórico
- **Al editar palet individual**: Se mantiene el precio existente

---

## 🔗 Referencias

- [Guía Frontend de Edición](./64-Guia-Frontend-Edicion-Recepciones.md)
- [Guía Frontend Completa](./63-Guia-Frontend-Recepciones-Palets.md)
- [Documentación Técnica de Recepciones](./60-Recepciones-Materia-Prima.md)

---

**Última actualización**: 2025-01-XX

