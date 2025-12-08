# Sistema de Estados Fijos de Palets

**Fecha**: 2025-01-XX  
**Versión**: 2.0  
**Estado**: Implementado

---

## 📋 Resumen Ejecutivo

Los estados de los palets han sido migrados de una tabla dinámica (`pallet_states`) a un sistema de **estados fijos** definidos como constantes en el modelo `Pallet`. Esto mejora el rendimiento, simplifica el código y permite mejor control de la lógica de negocio.

---

## 🎯 Estados Disponibles

Los estados están definidos como constantes en el modelo `Pallet`:

| ID | Constante | Nombre | Descripción |
|----|-----------|--------|-------------|
| `1` | `STATE_REGISTERED` | `registered` | Palet registrado pero no almacenado |
| `2` | `STATE_STORED` | `stored` | Palet almacenado en un almacén |
| `3` | `STATE_SHIPPED` | `shipped` | Palet enviado (asociado a pedido terminado) |
| `4` | `STATE_PROCESSED` | `processed` | Palet procesado (consumido completamente en producción) |

---

## 📊 Lógica de Cambios Automáticos de Estado

### 1. Palet Completamente Consumido en Producción

**Cuándo**: Cuando todas las cajas del palet están usadas en producción (`usedBoxesCount === numberOfBoxes`)

**Acción automática**:
- Cambiar a `STATE_PROCESSED` (4)
- Eliminar almacenamiento (`StoredPallet`)

**Método**: `Pallet::updateStateBasedOnBoxes()`

**Se ejecuta en**:
- Al crear un `ProductionInput` (cuando se asigna una caja a producción)
- Al eliminar un `ProductionInput` (cuando se libera una caja de producción)

---

### 2. Palet Liberado de Producción

**Cuándo**: Cuando se elimina un `ProductionInput` y el palet queda con todas sus cajas disponibles (`usedBoxesCount === 0`)

**Acción automática**:
- Cambiar a `STATE_REGISTERED` (1)
- Eliminar almacenamiento (`StoredPallet`)

**Método**: `Pallet::updateStateBasedOnBoxes()`

**Se ejecuta en**:
- Al eliminar un `ProductionInput`

---

### 3. Pedido Terminado → Palets Enviados

**Cuándo**: Cuando un pedido cambia a `status = 'finished'`

**Acción automática**:
- Todos los palets del pedido cambian a `STATE_SHIPPED` (3)
- Eliminar almacenamiento de cada palet
- **Mantener** `order_id` (para trazabilidad)

**Método**: `Pallet::changeToShipped()`

**Se ejecuta en**:
- `OrderController::update()` cuando `status` cambia a `'finished'`
- `OrderController::updateStatus()` cuando se actualiza a `'finished'`
- `IncidentController::destroy()` cuando se elimina un incidente (pedido pasa a `'finished'`)

---

## 🔧 Implementación Técnica

### Modelo Pallet

```php
class Pallet extends Model
{
    // Constantes de estado
    const STATE_REGISTERED = 1;
    const STATE_STORED = 2;
    const STATE_SHIPPED = 3;
    const STATE_PROCESSED = 4;

    // Obtener nombre del estado
    public static function getStateName(int $stateId): string

    // Obtener estado como array (para API)
    public function getStateArrayAttribute(): array

    // Métodos de cambio de estado
    public function changeToRegistered(): void
    public function changeToShipped(): void
    public function changeToProcessed(): void

    // Lógica automática basada en cajas
    public function updateStateBasedOnBoxes(): void
}
```

### Uso en Código

**✅ Correcto**:
```php
// Usar constantes
$pallet->status = Pallet::STATE_STORED;

// Validar estado
if ($pallet->status === Pallet::STATE_SHIPPED) {
    // ...
}

// Cambiar estado con método
$pallet->changeToShipped();
```

**❌ Incorrecto**:
```php
// NO usar números mágicos
$pallet->status = 2;

// NO usar relación palletState (deprecated)
$pallet->palletState->id;
```

---

## 📝 Validaciones

### Validación en Requests

```php
'status' => 'required|integer|in:1,2,3,4'
'state.id' => 'sometimes|integer|in:1,2,3,4'
```

### Verificación de Estados Válidos

```php
Pallet::getValidStates(); // Retorna [1, 2, 3, 4]
```

---

## 🔄 Migración de Datos

### Cambios Realizados

1. **Migración de datos existentes**:
   - Palets con `status = 3` (enviado) **sin** `order_id` → Cambian a `4` (procesado)
   - Palets con `status = 3` (enviado) **con** `order_id` → Se mantienen en `3` (enviado)

2. **Eliminación de foreign key**:
   - Se elimina la constraint `pallets.status → pallet_states.id` (ya no existe)

3. **Eliminación de tabla**:
   - Se elimina la tabla `pallet_states`

4. **Renombrado de columna** (2025-12-08):
   - La columna `state_id` fue renombrada a `status` para evitar que Laravel intente resolver automáticamente relaciones `belongsTo` basadas en el nombre de la columna
   - Migración: `2025_12_08_124753_rename_state_id_to_status_in_pallets_table.php`

### Archivo de Migración

`database/migrations/companies/2025_12_05_182714_remove_pallet_states_foreign_key_and_migrate_data.php`

---

## 📡 Respuesta de API

Los estados se retornan en el mismo formato que antes:

```json
{
    "id": 123,
    "state": {
        "id": 3,
        "name": "shipped"
    },
    ...
}
```

---

## 🎨 Compatibilidad con Frontend

El frontend puede crear un "almacén fantasma" para mostrar palets en estado `registered` (1) que no tienen almacenamiento asignado.

### Filtros Disponibles

- `filters[state]=stored` → Solo palets almacenados
- `filters[state]=shipped` → Solo palets enviados
- `filters[state]=processed` → Solo palets procesados

---

## ⚠️ Consideraciones Importantes

### 1. Almacenamiento

- Solo palets con `status = 2` (STORED) pueden estar en `stored_pallets`
- Al cambiar a otro estado, se elimina automáticamente de `stored_pallets`
- Los palets en estados `registered`, `shipped` o `processed` no tienen almacenamiento

### 2. Vinculación con Pedidos

- Los palets en estado `shipped` mantienen su `order_id` para trazabilidad
- No se elimina `order_id` al cambiar a `shipped`

### 3. Procesamiento

- Un palet parcialmente consumido mantiene su estado actual
- Solo cambia automáticamente cuando:
  - Todas las cajas están usadas → `processed`
  - Todas las cajas están disponibles (después de estar usadas) → `registered`

---

## 📚 Referencias

- **Modelo**: `app/Models/Pallet.php`
- **Servicio**: `app/Services/Production/ProductionInputService.php`
- **Controlador**: `app/Http/Controllers/v2/OrderController.php`
- **Recursos**: `app/Http/Resources/v2/PalletResource.php`

---

## ✅ Checklist de Verificación

- [x] Constantes definidas en modelo `Pallet`
- [x] Migración de datos implementada
- [x] Foreign key eliminada
- [x] Tabla `pallet_states` eliminada
- [x] Lógica automática implementada
- [x] Validaciones actualizadas
- [x] Recursos API actualizados
- [x] Controladores actualizados
- [x] Exports actualizados

---

**Autor**: Sistema de Estados Fijos  
**Última actualización**: 2025-12-08

**Cambio reciente**: La columna `state_id` fue renombrada a `status` el 2025-12-08 para evitar que Laravel intente resolver automáticamente relaciones `belongsTo` basadas en el nombre de la columna.

