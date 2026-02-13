# Investigación: Impacto de Cajas Disponibles vs Utilizadas en Palets

## 📋 Resumen Ejecutivo

Este documento analiza el impacto de haber agregado la distinción entre **cajas disponibles** y **cajas utilizadas en producción** en los palets. Se identifican todos los lugares del código que requieren atención, cambios necesarios y el nivel de peligrosidad de cada área afectada.

**Fecha de Investigación**: 2025-01-27  
**Estado**: Análisis Completo

---

## 🔍 Cambios Implementados

### 1. Modelo `Box` (`app/Models/Box.php`)

**Cambios realizados**:
- ✅ Agregado método `getIsAvailableAttribute()`: Determina si una caja está disponible (no tiene `productionInputs`)
- ✅ Agregado método `getProductionAttribute()`: Obtiene la producción más reciente donde se usó la caja
- ✅ Agregado campo `isAvailable` en `toArrayAssocV2()`: Flag booleano en la respuesta API
- ✅ Agregado campo `production` en `toArrayAssocV2()`: Información de la producción donde se usó

**Relación clave**:
```php
public function productionInputs()
{
    return $this->hasMany(ProductionInput::class, 'box_id');
}
```

### 2. Modelo `Pallet` (`app/Models/Pallet.php`)

**Cambios realizados**:
- ✅ `getAvailableBoxesCountAttribute()`: Cuenta cajas disponibles
- ✅ `getUsedBoxesCountAttribute()`: Cuenta cajas usadas
- ✅ `getTotalAvailableWeightAttribute()`: Suma peso de cajas disponibles
- ✅ `getTotalUsedWeightAttribute()`: Suma peso de cajas usadas

**✅ VERIFICADO**: El método `getTotalAvailableWeightAttribute()` está correctamente implementado con el filtro de disponibilidad.

### 3. `PalletResource` (`app/Http/Resources/v2/PalletResource.php`)

**Cambios realizados**:
- ✅ Agregados campos en respuesta API:
  - `availableBoxesCount`
  - `usedBoxesCount`
  - `totalAvailableWeight`
  - `totalUsedWeight`

### 4. `PalletController` (`app/Http/Controllers/v2/PalletController.php`)

**Cambios realizados**:
- ✅ Método `loadPalletRelations()` carga `productionInputs` para calcular disponibilidad:
```php
'boxes.box.productionInputs.productionRecord.production'
```

---

## 🎯 Áreas de Impacto Identificadas

### 🔴 ALTA PRIORIDAD - Cambios Críticos Necesarios

#### 1. **✅ Verificación de `Pallet::getTotalAvailableWeightAttribute()`**

**Ubicación**: `app/Models/Pallet.php:140-147`

**Estado**: ✅ **CORRECTO** - El método está correctamente implementado con el filtro de disponibilidad.

**Código actual**:
```php
public function getTotalAvailableWeightAttribute()
{
    return $this->boxes->filter(function ($palletBox) {
        return $palletBox->box->isAvailable; // ✅ Filtro correcto
    })->sum(function ($palletBox) {
        return $palletBox->box->net_weight ?? 0;
    });
}
```

**Nota**: No se requiere acción, el código está funcionando correctamente.

---

#### 2. **Validación de Disponibilidad en `ProductionInputController`**

**Ubicación**: `app/Http/Controllers/v2/ProductionInputController.php`

**Problema**: No valida si una caja está disponible antes de asignarla a producción.

**Impacto actual**:
- Se pueden asignar cajas ya utilizadas a nuevos procesos
- No hay validación de disponibilidad en `store()` ni `storeMultiple()`

**Código actual** (líneas 46-72):
```php
public function store(Request $request)
{
    // Solo valida que no esté duplicada en el MISMO proceso
    $existing = ProductionInput::where('production_record_id', ...)
        ->where('box_id', ...)
        ->first();
    
    // ❌ NO VALIDA isAvailable
}
```

**Solución requerida**:
```php
// Después de validar existencia de la caja
$box = Box::with('productionInputs')->findOrFail($validated['box_id']);

if (!$box->isAvailable) {
    return response()->json([
        'message' => 'La caja ya ha sido utilizada en producción y no está disponible.',
        'box_id' => $box->id,
        'production' => $box->production ? [
            'id' => $box->production->id,
            'lot' => $box->production->lot,
        ] : null,
    ], 422);
}
```

**Peligrosidad**: 🔴 **ALTA** - Permite duplicación de cajas en producción

---

#### 3. **✅ Estadísticas de Stock (`StockStatisticsService`) - CORREGIDO**

**Ubicación**: `app/Services/v2/StockStatisticsService.php`

**Estado**: ✅ **CORREGIDO** - Las estadísticas ahora filtran solo cajas disponibles.

**Métodos corregidos**:
- ✅ `getTotalStockStats()`: Ahora filtra solo cajas disponibles usando `leftJoin` con `whereNull`
- ✅ `getSpeciesTotalsRaw()`: Filtra cajas disponibles antes de sumar pesos
- ✅ `getTotalStockBySpeciesStats()`: Usa el método corregido que filtra cajas disponibles

**Cambios implementados**:
```php
// Se agregó leftJoin con production_inputs y whereNull para filtrar solo cajas disponibles
$totalWeight = Pallet::query()
    ->stored()
    ->joinBoxes()
    ->leftJoin('production_inputs', 'production_inputs.box_id', '=', 'boxes.id')
    ->whereNull('production_inputs.id') // Solo cajas sin production_inputs
    ->sum('boxes.net_weight');
```

**Impacto de la corrección**:
- ✅ Los reportes de stock ahora muestran solo inventario disponible
- ✅ Excluyen cajas que ya han sido utilizadas en producción
- ✅ Las estadísticas reflejan el stock real disponible para venta/despacho

**Peligrosidad**: ✅ **RESUELTO**

---

### 🟡 MEDIA PRIORIDAD - Cambios Recomendados

#### 4. **✅ Cálculos en Modelo `Order` - CORREGIDO**

**Ubicación**: `app/Models/Order.php`

**Estado**: ✅ **CORREGIDO** - Todos los métodos ahora filtran solo cajas disponibles.

**Métodos corregidos**:
- ✅ `getTotalsAttribute()`: Ahora filtra solo cajas disponibles
- ✅ `getTotalNetWeightAttribute()`: Peso total solo de cajas disponibles
- ✅ `getTotalBoxesAttribute()`: Cuenta solo cajas disponibles
- ✅ `getProductsBySpeciesAndCaptureZoneAttribute()`: Solo incluye cajas disponibles
- ✅ `getProductsWithLotsDetailsAttribute()`: Solo incluye cajas disponibles
- ✅ `getProductionProductDetailsAttribute()`: Solo incluye cajas disponibles
- ✅ `getSpeciesListAttribute()`: Solo incluye especies de cajas disponibles
- ✅ `getFamiliesListAttribute()`: Solo incluye familias de cajas disponibles
- ✅ `getCategoriesListAttribute()`: Solo incluye categorías de cajas disponibles

**Cambios implementados**:
- Todos los métodos ahora verifican `$box->box->isAvailable` antes de incluir cajas en los cálculos
- `OrderController` ahora carga las relaciones `productionInputs` necesarias para que `isAvailable` funcione correctamente

**Impacto de la corrección**:
- ✅ Los pedidos ahora muestran solo cajas disponibles para despacho
- ✅ Los totales, pesos y cantidades reflejan solo lo que está disponible
- ✅ Los documentos PDF y exports mostrarán información correcta

**Peligrosidad**: ✅ **RESUELTO**

---

#### 5. **Vistas PDF y Reportes**

**Ubicación**: `resources/views/pdf/v2/orders/*.blade.php`

**Archivos afectados**:
- `order_packing_list.blade.php`: Muestra `$pallet->netWeight` y `$pallet->numberOfBoxes`
- `order_signs.blade.php`: Muestra información de palets
- Otros documentos de pedidos

**Impacto**:
- Los documentos PDF pueden mostrar información que incluye cajas ya utilizadas
- Puede confundir a clientes si ven cajas que ya no están disponibles

**Recomendación**:
- Considerar mostrar información separada:
  - "Cajas Totales" vs "Cajas Disponibles"
  - "Peso Total" vs "Peso Disponible"

**Peligrosidad**: 🟡 **MEDIA** - Puede confundir pero no rompe funcionalidad

---

#### 6. **Exports de Excel**

**Ubicación**: `app/Exports/v2/*.php`

**Archivos afectados**:
- `BoxesReportExport.php`: Exporta cajas con información de palets
- `OrderExport.php`: Exporta pedidos con información de palets
- `OrderBoxListExport.php`: Lista de cajas por pedido

**Impacto**:
- Los exports pueden incluir cajas ya utilizadas sin indicarlo
- No hay distinción visual entre cajas disponibles y usadas

**Recomendación**:
- Agregar columna "Disponible" (Sí/No) en exports de cajas
- Filtrar opcionalmente por disponibilidad

**Peligrosidad**: 🟡 **MEDIA** - Mejora de funcionalidad

---

### 🟢 BAJA PRIORIDAD - Mejoras Opcionales

#### 7. **✅ Filtros en `PalletController` y `BoxesController` - IMPLEMENTADO**

**Ubicación**: 
- `app/Http/Controllers/v2/PalletController.php`
- `app/Http/Controllers/v2/BoxesController.php`

**Estado**: ✅ **IMPLEMENTADO** - Filtros y endpoints para frontend agregados.

**Funcionalidades implementadas**:

1. **Endpoint `/v2/boxes/available`**: Endpoint especializado para obtener solo cajas disponibles
   - Filtros: `lot`, `product_id`, `product_ids`, `pallet_id`, `pallet_ids`, `onlyStored`
   - Optimizado para selección de cajas en producción

2. **Filtro `available` en `/v2/boxes`**: 
   - `available=true`: Solo cajas disponibles
   - `available=false`: Solo cajas usadas

3. **Filtros en `/v2/pallets`**:
   - `filters[hasAvailableBoxes]=true`: Solo palets con cajas disponibles
   - `filters[hasUsedBoxes]=true`: Solo palets con cajas usadas

4. **Información en `BoxResource`**:
   - Campo `isAvailable`: Indica si la caja está disponible
   - Campo `production`: Información de la producción donde se usó (si aplica)

**Documentación**: Ver `docs/FRONTEND-Cajas-Disponibles.md` para ejemplos de uso.

**Peligrosidad**: ✅ **RESUELTO**

---

#### 8. **Documentación de API**

**Ubicación**: `docs/23-inventario/31-Palets.md`

**Recomendación**: Actualizar documentación para incluir:
- Explicación de campos `availableBoxesCount`, `usedBoxesCount`, etc.
- Ejemplos de uso
- Notas sobre cuándo usar cada métrica

**Peligrosidad**: 🟢 **BAJA** - Mejora de documentación

---

#### 9. **Validación en Frontend**

**Recomendación**: Si el frontend permite seleccionar cajas para producción:
- Mostrar solo cajas disponibles
- Indicar visualmente qué cajas están usadas
- Prevenir selección de cajas no disponibles

**Peligrosidad**: 🟢 **BAJA** - Mejora de UX

---

## 📊 Resumen de Impacto por Módulo

| Módulo | Archivos Afectados | Prioridad | Estado |
|--------|-------------------|-----------|--------|
| **Modelos** | `Pallet.php`, `Box.php` | ✅ Verificado | ✅ Implementación correcta |
| **Controladores** | `ProductionInputController.php` | 🔴 Alta | ❌ Falta validación |
| **Servicios** | `StockStatisticsService.php` | ✅ Corregido | ✅ Filtra solo cajas disponibles |
| **Modelos** | `Order.php` | ✅ Corregido | ✅ Filtra solo cajas disponibles |
| **Vistas PDF** | `resources/views/pdf/**/*.blade.php` | 🟡 Media | ⚠️ Mostrar info correcta |
| **Exports** | `app/Exports/v2/*.php` | 🟡 Media | ⚠️ Agregar columna disponibilidad |
| **API** | `PalletController.php` | 🟢 Baja | ✅ Funcional (mejoras opcionales) |
| **Documentación** | `docs/**/*.md` | 🟢 Baja | ⚠️ Actualizar |

---

## 🚨 Problemas Críticos a Resolver

### 1. ✅ Verificación de `getTotalAvailableWeightAttribute()`

**Estado**: ✅ **VERIFICADO Y CORRECTO** - No requiere acción.

---

### 2. Validación de Disponibilidad en Producción

**Prioridad**: 🔴 **ALTA**

**Acción**: Implementar validación en `ProductionInputController::store()` y `storeMultiple()`.

**Impacto si no se corrige**:
- Cajas pueden ser asignadas múltiples veces a diferentes procesos
- Inconsistencias en trazabilidad
- Problemas de conciliación de stock

---

### 3. ✅ Estadísticas de Stock - CORREGIDO

**Estado**: ✅ **RESUELTO** - Las estadísticas ahora muestran solo stock disponible.

**Acción tomada**: Se implementó filtrado de cajas disponibles en todos los métodos del servicio.

**Resultado**:
- ✅ Reportes de inventario muestran solo stock disponible
- ✅ Decisiones de negocio basadas en datos correctos

---

## 📝 Recomendaciones de Implementación

### Fase 1: Correcciones Críticas (URGENTE)

1. ✅ Verificado: `getTotalAvailableWeightAttribute()` está correcto
2. ⚠️ Agregar validación de disponibilidad en `ProductionInputController`
3. ✅ Actualizado `StockStatisticsService` para filtrar cajas disponibles

### Fase 2: Mejoras de Negocio (ALTA)

4. ✅ Corregido: Modelo `Order` ahora filtra solo cajas disponibles
5. ⚠️ Actualizar vistas PDF para mostrar información de disponibilidad (opcional, ya que los métodos filtran)
6. ⚠️ Agregar filtros de disponibilidad en `PalletController`

### Fase 3: Mejoras de UX (MEDIA)

7. ⚠️ Actualizar exports para incluir columna de disponibilidad
8. ⚠️ Actualizar documentación de API
9. ⚠️ Mejoras en frontend (si aplica)

---

## 🔍 Puntos de Atención Adicionales

### Rendimiento

**Preocupación**: Cargar `productionInputs` para cada caja puede ser costoso.

**Ubicación**: `PalletController::loadPalletRelations()`

**Solución actual**: Se carga con eager loading, lo cual es eficiente.

**Recomendación**: Monitorear rendimiento con grandes volúmenes de datos.

---

### Consistencia de Datos

**Preocupación**: ¿Qué pasa si se elimina un `ProductionInput`? ¿La caja vuelve a estar disponible?

**Análisis**: 
- Si se elimina `ProductionInput`, la relación `productionInputs()` retornará vacía
- `isAvailable` volverá a ser `true`
- Esto puede ser correcto si se permite "deshacer" una asignación

**Recomendación**: Documentar este comportamiento y considerar si es deseado.

---

### Trazabilidad

**Oportunidad**: Con la información de `production`, se puede rastrear:
- ¿De qué producción viene esta caja?
- ¿En qué proceso se usó?

**Recomendación**: Considerar agregar más información de trazabilidad en el futuro.

---

## ✅ Checklist de Verificación

- [x] **CRÍTICO**: Verificar `getTotalAvailableWeightAttribute()` ✅ CORRECTO
- [ ] **CRÍTICO**: Agregar validación en `ProductionInputController`
- [x] **ALTA**: Actualizar `StockStatisticsService` ✅ CORREGIDO
- [x] **ALTA**: Corregir cálculos en modelo `Order` ✅ CORREGIDO
- [ ] **MEDIA**: Revisar y actualizar vistas PDF
- [x] **MEDIA**: Agregar filtros de disponibilidad en API ✅ IMPLEMENTADO
- [ ] **MEDIA**: Actualizar exports de Excel
- [ ] **BAJA**: Actualizar documentación
- [ ] **BAJA**: Mejoras de frontend (si aplica)

---

## 📚 Referencias

- Modelo `Box`: `app/Models/Box.php`
- Modelo `Pallet`: `app/Models/Pallet.php`
- Controlador `PalletController`: `app/Http/Controllers/v2/PalletController.php`
- Controlador `ProductionInputController`: `app/Http/Controllers/v2/ProductionInputController.php`
- Servicio `StockStatisticsService`: `app/Services/v2/StockStatisticsService.php`
- Resource `PalletResource`: `app/Http/Resources/v2/PalletResource.php`
- Documentación Palets: `docs/23-inventario/31-Palets.md`
- Documentación Cajas: `docs/23-inventario/32-Cajas.md`
- Documentación Producción Entradas: `docs/25-produccion/13-Produccion-Entradas.md`

---

**Fin del Documento de Investigación**

