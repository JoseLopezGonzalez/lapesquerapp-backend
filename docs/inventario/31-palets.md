# Inventario - Palets (Pallets)

## ⚠️ Estado de la API
- **v1**: Obsoleta (no documentada)
- **v2**: Versión activa (este documento)

---

## 📋 Visión General

El modelo `Pallet` representa un **palet** que contiene múltiples cajas de productos. Los palets tienen estados (registrado, almacenado, enviado), pueden estar asignados a pedidos, almacenados en almacenes con posiciones específicas, y contienen información sobre las cajas que transportan.

**Concepto clave**: Los palets son la unidad intermedia entre almacenes/pedidos y las cajas individuales. Un palet puede contener múltiples cajas del mismo o diferente producto.

**Archivo del modelo**: `app/Models/Pallet.php`

---

## 🗄️ Estructura de Base de Datos

### Tabla: `pallets`

**Migración**: `database/migrations/companies/2023_08_09_145908_create_pallets_table.php`

**Campos**:

| Campo | Tipo | Nullable | Descripción |
|-------|------|----------|-------------|
| `id` | bigint | NO | ID único del palet |
| `observations` | text | YES | Observaciones sobre el palet |
| `status` | bigint | NO | Estado del palet (1=registered, 2=stored, 3=shipped, 4=processed) |
| `order_id` | bigint | YES | FK a `orders` - Pedido asignado (opcional) |
| `reception_id` | bigint | YES | FK a `raw_material_receptions` - Recepción de origen (opcional) |
| `timeline` | json | YES | Historial de modificaciones del palet (F-01) |
| `created_at` | timestamp | NO | Fecha de creación |
| `updated_at` | timestamp | NO | Fecha de última actualización |

**Índices**:
- `id` (primary key)
- Foreign key a `orders`

**Constraints**:
- `order_id` → `orders.id` (onDelete: set null)

**⚠️ Nota**: `status` ya no tiene foreign key. Los estados son valores fijos (1, 2, 3, 4). La columna fue renombrada de `state_id` a `status` para evitar que Laravel intente resolver automáticamente relaciones.

---

## 📦 Modelo Eloquent

### Fillable Attributes

```php
protected $fillable = [
    'observations',
    'status',
    'reception_id',
    'timeline',
];
```

**Casts**: `timeline` → `array`

**Nota**: `order_id` no está en fillable pero se puede asignar directamente.

### Traits

- `UsesTenantConnection`: Usa conexión tenant (multi-tenant)
- `HasFactory`: Para testing y seeders

---

## 🔗 Relaciones

### 1. `palletState()` - Estado del Palet (⚠️ Deprecated)
```php
public function palletState()
{
    // ⚠️ DEPRECATED: Ya no usa tabla pallet_states
    // Retorna objeto compatible para retrocompatibilidad
    // Usar $pallet->state_id o $pallet->stateArray en su lugar
}
```

**⚠️ Deprecated**: Esta relación ya no existe. Usar:
- `$pallet->status` para obtener el ID del estado
- `$pallet->stateArray` para obtener `['id' => X, 'name' => '...']`
- `Pallet::getStateName($status)` para obtener el nombre del estado

### 2. `order()` - Pedido Asignado
```php
public function order()
{
    return $this->belongsTo(Order::class);
}
```
- Relación muchos-a-uno con `Order`
- Puede ser `null` si el palet no está asignado a ningún pedido

### 3. `boxes()` - Cajas (PalletBox)
```php
public function boxes()
{
    return $this->hasMany(PalletBox::class, 'pallet_id');
}
```
- Relación uno-a-muchos con `PalletBox` (tabla intermedia)

### 4. `boxesV2()` - Cajas (Many-to-Many)
```php
public function boxesV2()
{
    return $this->belongsToMany(Box::class, 'pallet_boxes', 'pallet_id', 'box_id');
}
```
- Relación muchos-a-muchos directa con `Box`

### 5. `storedPallet()` - Almacenamiento
```php
public function storedPallet()
{
    return $this->hasOne(StoredPallet::class, 'pallet_id');
}
```
- Relación uno-a-uno con `StoredPallet` (si está almacenado)

### 6. `palletBoxes()` - PalletBoxes (alias)
```php
public function palletBoxes()
{
    return $this->hasMany(PalletBox::class);
}
```
- Igual que `boxes()` pero sin especificar foreign key

---

## 🏷️ Estados del Palet

**⚠️ IMPORTANTE**: Los estados ahora son **fijos** definidos como constantes en el modelo `Pallet`. Ya no dependen de la tabla `pallet_states`.

**Estados disponibles** (constantes en `Pallet`):
- **ID 1** (`STATE_REGISTERED`): `registered` - Registrado pero no almacenado
- **ID 2** (`STATE_STORED`): `stored` - Almacenado en un almacén
- **ID 3** (`STATE_SHIPPED`): `shipped` - Enviado (asociado a pedido terminado)
- **ID 4** (`STATE_PROCESSED`): `processed` - Procesado (consumido completamente en producción)

**Lógica de estados**:
- Solo palets con `status = 2` (almacenado) pueden estar en un almacén
- Al cambiar a otro estado, se elimina automáticamente de `stored_pallets`
- Los estados cambian automáticamente según el uso en producción y pedidos

**📖 Documentación detallada**: Ver [31b-Palets-Estados-Fijos.md](./31b-Palets-Estados-Fijos.md) para información completa sobre la lógica automática de cambios de estado.

---

## 🔢 Accessors (Atributos Calculados)

### Peso y Cantidades

#### `getNetWeightAttribute()`
Peso neto total del palet (suma de todas las cajas).
```php
return $this->boxes->reduce(function ($carry, $box) {
    return $carry + $box->net_weight;
}, 0);
```

#### `getNumberOfBoxesAttribute()`
Número total de cajas en el palet.

#### `getAvailableBoxesCountAttribute()`
Cantidad de cajas disponibles (no usadas en producción).
```php
return $this->boxes->filter(function ($palletBox) {
    return $palletBox->box->isAvailable;
})->count();
```

#### `getUsedBoxesCountAttribute()`
Cantidad de cajas usadas en producción.

#### `getTotalAvailableWeightAttribute()`
Peso neto total de cajas disponibles.

#### `getTotalUsedWeightAttribute()`
Peso neto total de cajas usadas.

### Ubicación

#### `getPositionAttribute()`
Posición del palet en el almacén (query directo).
```php
$pallet = StoredPallet::where('pallet_id', $this->id)->first();
return $pallet ? $pallet->position : null;
```

#### `getPositionV2Attribute()`
Posición del palet usando relación.
```php
return $this->storedPallet?->position;
```

#### `getStoreIdAttribute()`
ID del almacén donde está almacenado.

#### `getStoreAttribute()`
Modelo `Store` donde está almacenado.

### Productos y Artículos

#### `getProductsAttribute()`
Array de productos únicos en el palet.

#### `getProductsNamesAttribute()`
Array de nombres de productos.

#### `getArticlesAttribute()` y `getArticlesNamesAttribute()`
Artículos y nombres (legacy, usa `article.article`).

### Lotes

#### `getLotsAttribute()`
Array de lotes únicos en el palet.

### Resumen

#### `getSummaryAttribute()`
Resumen agrupado por producto con cantidades y pesos.

#### `getTotalsAttribute()`
Totales generales (cajas y peso neto).

---

## 🔧 Métodos

### `unStore()`
Elimina el palet del almacén (elimina registro en `stored_pallets`).

### `delete()`
Override del método delete para eliminar también las cajas asociadas.

---

## 📡 Controlador

**Archivo**: `app/Http/Controllers/v2/PalletController.php`

### Métodos del Controlador

#### `index(Request $request)` - Listar Palets
```php
GET /v2/pallets
```

**Filtros disponibles** (query parameters o body `filters`):
- `id`: Filtrar por ID (LIKE)
- `ids`: Filtrar por múltiples IDs (array)
- `state`: `'stored'` o `'shipped'` (IDs 2 o 3)
- `orderState`: `'pending'`, `'finished'`, `'without_order'`
- `position`: `'located'` o `'unlocated'`
- `dates.start` y `dates.end`: Rango de fechas de creación
- `notes`: Buscar en observaciones (LIKE)
- `lots`: Array de lotes
- `products`: Array de IDs de productos
- `species`: Array de IDs de especies
- `stores`: Array de IDs de almacenes
- `orders`: Array de IDs de pedidos
- `weights.netWeight.min` y `weights.netWeight.max`: Rango de peso neto
- `weights.grossWeight.min` y `weights.grossWeight.max`: Rango de peso bruto

**Query parameters**:
- `perPage`: Elementos por página (default: 10)

**Orden**: Por ID descendente

**Respuesta**: Collection paginada de `PalletResource`

#### `store(Request $request)` - Crear Palet
```php
POST /v2/pallets
```

**Request body**:
```json
{
    "observations": "Observaciones del palet",
    "boxes": [
        {
            "product": { "id": 1 },
            "lot": "LOT123",
            "gs1128": "1234567890123",
            "grossWeight": 10.5,
            "netWeight": 9.5
        }
    ],
    "store": { "id": 1 },
    "orderId": 5,
    "state": { "id": 1 }
}
```

**Validación**:
- `boxes`: Array requerido
- `boxes.*.product.id`: ID de producto requerido
- `boxes.*.lot`: Lote requerido
- `boxes.*.gs1128`: Código GS1-128 requerido
- `boxes.*.grossWeight`, `boxes.*.netWeight`: Numéricos requeridos
- `store.id`, `orderId`, `state.id`: Opcionales

**Comportamiento**:
1. Crea el palet con estado por defecto `1` (registrado)
2. Si se proporciona `store.id`, crea registro en `stored_pallets`
3. Crea todas las cajas y las vincula al palet mediante `pallet_boxes`

**Respuesta** (201): `PalletResource`

#### `show(string $id)` - Mostrar Palet
```php
GET /v2/pallets/{id}
```

**Respuesta**: `PalletResource` completo

#### `timeline(string $id)` - Timeline de modificaciones (F-01)
```php
GET /v2/pallets/{id}/timeline
```

**Autorización**: Misma política que `show` (view del palet).

**Respuesta**: Lista cronológica de cambios (más reciente primero):
```json
{
    "timeline": [
        {
            "timestamp": "2026-02-25T10:30:00.000000Z",
            "userId": 5,
            "userName": "José García",
            "type": "state_changed",
            "action": "Estado cambiado de Registrado a Almacenado",
            "details": {
                "fromId": 1,
                "from": "registered",
                "toId": 2,
                "to": "stored"
            }
        }
    ]
}
```

**Descripción**: Historial ligero de cambios sobre el palet (quién lo movió, cuándo cambió de estado, vinculación a pedidos, cambios de cajas, etc.). No reemplaza el ActivityLog general; es específico y legible para el usuario final. Ver sección [Timeline de modificaciones (F-01)](#-timeline-de-modificaciones-f-01) para tipos de evento y formato completo.

#### `update(Request $request, string $id)` - Actualizar Palet
```php
PUT /v2/pallets/{id}
```

**Comportamiento complejo**:
- Actualiza `observations`
- Cambia `status` (si cambia y no es almacenado, elimina de almacén)
- Cambia `order_id` (puede ser `null` para desvincular)
- Cambia almacén (actualiza `stored_pallets`)
- Actualiza/crea/elimina cajas según el array recibido

**Lógica de cajas**:
- Si una caja existe (por ID), se actualiza
- Si una caja no existe, se crea
- Si una caja existente no está en el array, se elimina

**Respuesta** (201): `PalletResource`

#### `destroy(string $id)` - Eliminar Palet
```php
DELETE /v2/pallets/{id}
```

**Comportamiento**:
- Elimina `stored_pallet` si existe
- Elimina todas las cajas asociadas (`pallet_boxes` y `boxes`)
- Elimina el palet

**Transacción**: Usa `DB::transaction()` para garantizar consistencia

#### `destroyMultiple(Request $request)` - Eliminar Múltiples Palets
```php
DELETE /v2/pallets
```

**Request body**:
```json
{
    "ids": [1, 2, 3]
}
```

#### `options()` - Opciones para Select
```php
GET /v2/pallets/options
```

Retorna todos los palets con `id` y `name` (id).

#### `storedOptions()` - Opciones de Palets Almacenados
```php
GET /v2/pallets/stored-options
```

Solo palets con `status = 2`.

#### `shippedOptions()` - Opciones de Palets Enviados
```php
GET /v2/pallets/shipped-options
```

Solo palets con `status = 3` (shipped).

#### `registeredPallets()` - Palets Registrados (Almacén Fantasma)
```php
GET /v2/pallets/registered
```

Retorna todos los palets en estado `registered` (status = 1) con un formato similar a `StoreDetailsResource`, simulando un "almacén fantasma".

**Respuesta**: Similar a un almacén pero para palets registrados
```json
{
    "id": null,
    "name": "Palets Registrados",
    "temperature": null,
    "capacity": null,
    "netWeightPallets": 1250.50,
    "totalNetWeight": 1250.50,
    "content": {
        "pallets": [...],
        "boxes": [],
        "bigBoxes": []
    },
    "map": null
}
```

**📄 Ejemplo completo**: Ver [EJEMPLO-RESPUESTA-registered-pallets.json](../ejemplos/EJEMPLO-RESPUESTA-registered-pallets.json)

**Comportamiento**:
- Obtiene todos los palets con `status = 1` (registered)
- Calcula pesos totales (similar a un almacén)
- Retorna formato compatible con frontend (mismo formato que almacenes)
- Útil para crear un "almacén fantasma" en el frontend que muestre palets sin almacén asignado

**Relaciones cargadas**:
- `boxes.box.productionInputs.productionRecord.production`
- `boxes.box.product`

**Filtros disponibles en `index()`**:
- `filters[state]=stored` → Solo palets almacenados (status = 2)
- `filters[state]=shipped` → Solo palets enviados (status = 3)
- `filters[state]=processed` → Solo palets procesados (status = 4)

#### `assignToPosition(Request $request)` - Asignar Posición
```php
POST /v2/pallets/assign-to-position
```

**Request body**:
```json
{
    "position_id": 1,
    "pallet_ids": [1, 2, 3]
}
```

Asigna la misma posición a múltiples palets.

#### `moveToStore(Request $request)` - Mover a Almacén
```php
POST /v2/pallets/move-to-store
```

**Request body**:
```json
{
    "pallet_id": 1,
    "store_id": 2
}
```

**Validación**: El palet debe estar en estado almacenado (`status = 2` / `Pallet::STATE_STORED`).

**Comportamiento**: Crea/actualiza `StoredPallet` y resetea la posición.

#### `unassignPosition($id)` - Desasignar Posición
```php
POST /v2/pallets/{id}/unassign-position
```

Pone `position = null` en `stored_pallets`.

#### `bulkUpdateState(Request $request)` - Actualizar Estado Masivo
```php
POST /v2/pallets/bulk-update-state
```

**Request body**:
```json
{
    "status": 2,
    "ids": [1, 2, 3],
    // O
    "filters": { ... },
    // O
    "applyToAll": true
}
```

**Comportamiento**:
- Si cambia a estado no almacenado, elimina de almacén
- Si cambia a almacenado (`status = 2`) y no tiene almacén, crea en almacén ID 4 (hardcodeado)

**⚠️ Nota**: El almacén ID 4 está hardcodeado. Considerar hacerlo configurable.

#### `unlinkOrder($id)` - Desvincular de Pedido
```php
POST /v2/pallets/{id}/unlink-order
```

Pone `order_id = null`.

---

## 📜 Timeline de modificaciones (F-01)

**Estado**: Implementado  
**Prioridad**: Media  
**Complejidad**: Baja — autocontenida

### Descripción

Historial ligero de cambios sobre la entidad Palet. Cualquier usuario puede ver qué ha pasado con un palet a lo largo del tiempo: quién lo movió, cuándo cambió de estado, cuándo se vinculó o desvinculó de un pedido, cambios en cajas, etc.

- **Campo**: `timeline` (JSON, nullable) en la tabla `pallets`.
- **Lógica**: El backend detecta cambios en el palet y en sus relaciones; cada entrada se añade al array.
- **Contenido por entrada**: `timestamp` (ISO 8601), `userId`, `userName`, `type`, `action` (texto en lenguaje natural), `details` (objeto según el tipo).
- **Servicio**: `App\Services\v2\PalletTimelineService::record()`.
- **No reemplaza** el ActivityLog general; es específico y legible para el usuario final.

### Endpoint

- **GET** `/api/v2/pallets/{id}/timeline` — Devuelve `{ "timeline": [...] }` (orden: más reciente primero).

### Estructura común de cada entrada

| Campo     | Tipo   | Descripción |
|----------|--------|-------------|
| `timestamp` | string | ISO 8601 (ej. `2026-02-25T10:30:00.000000Z`) |
| `userId` | int \| null | ID del usuario; `null` si acción automática (Sistema) |
| `userName` | string | Nombre del usuario o `"Sistema"` |
| `type`    | string | Tipo de evento (ver tabla siguiente) |
| `action`  | string | Descripción en lenguaje natural |
| `details` | object | Datos específicos del tipo (ver por tipo) |

### Tipos de evento y formato JSON de `details`

| Tipo | Descripción | Detalles (resumen) |
|------|-------------|--------------------|
| `pallet_created` | Palet creado manualmente | `boxesCount`, `totalNetWeight`, `initialState`, `storeId`, `storeName`, `orderId` |
| `pallet_created_from_reception` | Palet creado desde recepción | `receptionId`, `boxesCount`, `totalNetWeight` |
| `state_changed` | Cambio de estado manual | `fromId`, `from`, `toId`, `to` |
| `state_changed_auto` | Cambio automático (producción) | `fromId`, `from`, `toId`, `to`, `reason`, `usedBoxesCount`, `totalBoxesCount` |
| `store_assigned` | Movido a almacén | `storeId`, `storeName`, `previousStoreId`, `previousStoreName` |
| `store_removed` | Retirado del almacén | `previousStoreId`, `previousStoreName` |
| `position_assigned` | Posición asignada | `positionId`, `positionName`, `storeId`, `storeName` |
| `position_unassigned` | Posición eliminada | `previousPositionId`, `previousPositionName` |
| `order_linked` | Vinculado a pedido | `orderId`, `orderReference` |
| `order_unlinked` | Desvinculado de pedido | `orderId`, `orderReference` |
| `box_added` | Caja añadida | `boxId`, `productId`, `productName`, `lot`, `gs1128`, `netWeight`, `grossWeight`, `newBoxesCount`, `newTotalNetWeight` |
| `box_removed` | Caja eliminada | Igual que `box_added` + totales actuales |
| `box_updated` | Caja modificada | `boxId`, `productId`, `productName`, `lot`, `changes` (objeto con `from`/`to` por campo) |
| `observations_updated` | Observaciones cambiadas | `from`, `to` |

**Valores de `reason`** (solo `state_changed_auto`): `all_boxes_in_production`, `boxes_released_from_production`, `partial_boxes_released`.

### Dónde se registra

- **PalletWriteService**: creación (`pallet_created`), actualización (diff de estado, almacén, pedido, observaciones, cajas).
- **PalletActionService**: mover a almacén, asignar/desasignar posición, vincular/desvincular pedido, cambio de estado masivo.
- **Pallet** (modelo): `changeToShipped()`, `updateStateBasedOnBoxes()` (cambios automáticos).
- **RawMaterialReceptionWriteService**: palets creados desde recepción (`pallet_created_from_reception`).

---

## 📄 API Resource

**Archivo**: `app/Http/Resources/v2/PalletResource.php`

**Campos expuestos**:
```json
{
    "id": 1,
    "observations": "...",
    "state": { "id": 2, "name": "stored" },
    "productsNames": ["Filetes de atún"],
    "boxes": [...],
    "lots": ["LOT123"],
    "netWeight": 125.50,
    "position": 1,
    "store": { "id": 1, "name": "Almacén Principal" },
    "orderId": 5,
    "numberOfBoxes": 10,
    "availableBoxesCount": 8,
    "usedBoxesCount": 2,
    "totalAvailableWeight": 100.00,
    "totalUsedWeight": 25.50
}
```

---

## 🔍 Scopes (Query Scopes)

### `scopeStored($query)`
Filtra palets almacenados (`status = 2` / `Pallet::STATE_STORED`).

### Métodos de Cambio de Estado

#### `changeToRegistered()`
Cambia el palet a estado `registered` (1) y elimina almacenamiento.

#### `changeToShipped()`
Cambia el palet a estado `shipped` (3), elimina almacenamiento, pero **mantiene** `order_id`.

#### `changeToProcessed()`
Cambia el palet a estado `processed` (4) y elimina almacenamiento.

#### `updateStateBasedOnBoxes()`
Actualiza automáticamente el estado basado en las cajas disponibles/usadas:
- Todas las cajas usadas → `processed`
- Todas las cajas disponibles (después de estar usadas) → `registered`
- Parcialmente consumido → mantiene estado actual

### `scopeJoinBoxes($query)`
Hace JOIN con `pallet_boxes` y `boxes`.

### `scopeJoinProducts($query)`
Hace JOIN con `pallet_boxes`, `boxes` y `products`.

---

## 🔐 Permisos y Autenticación

**Middleware requerido**:
- `auth:sanctum`: Autenticación requerida
- `role:superuser,manager,admin,store_operator`: Roles permitidos

**Excepciones**:
- `destroyMultiple()` requiere `role:superuser,manager,admin` (no store_operator)

**Rutas**: Todas bajo `/v2/pallets/*`

---

## 📝 Ejemplos de Uso

### Crear un Palet
```http
POST /v2/pallets
Content-Type: application/json
Authorization: Bearer {token}
X-Tenant: empresa1

{
    "observations": "Palet de prueba",
    "boxes": [
        {
            "product": { "id": 1 },
            "lot": "LOT123",
            "gs1128": "1234567890123",
            "grossWeight": 10.5,
            "netWeight": 9.5
        }
    ],
    "store": { "id": 1 },
    "state": { "id": 1 }
}
```

### Filtrar Palets
```http
GET /v2/pallets?filters[state]=stored&filters[stores][]=1&perPage=20
Authorization: Bearer {token}
X-Tenant: empresa1
```

### Mover Palet a Almacén
```http
POST /v2/pallets/move-to-store
Content-Type: application/json
Authorization: Bearer {token}

{
    "pallet_id": 1,
    "store_id": 2
}
```

---

## Observaciones Críticas y Mejoras Recomendadas

### ⚠️ Almacén Hardcodeado en bulkUpdateState

1. **store_id = 4 Hardcodeado** (`app/Http/Controllers/v2/PalletController.php:618`)
   - Al cambiar estado a almacenado automáticamente, usa almacén ID 4
   - **Líneas**: 618
   - **Problema**: No es configurable ni correcto
   - **Recomendación**: 
     - Pedir `store_id` en el request
     - O usar el almacén actual si existe
     - O requerir que el palet ya tenga almacén antes de cambiar estado

### ⚠️ Queries Ineficientes en Accessors

2. **getPositionAttribute() Hace Query Directo** (`app/Models/Pallet.php:161-170`)
   - Hace query directo en lugar de usar relación
   - **Líneas**: 161-170
   - **Problema**: Query N+1 si se accede desde una colección
   - **Recomendación**: Usar siempre `positionV2` que usa relación, o eager loading

3. **getStoreIdAttribute() y getStoreAttribute()** (`app/Models/Pallet.php:181-201`)
   - Hacen queries directos
   - **Líneas**: 181-201
   - **Problema**: Queries N+1
   - **Recomendación**: Usar `storedPallet->store` con eager loading

### ⚠️ Eliminación en Cascade Peligrosa

4. **delete() Elimina Cajas** (`app/Models/Pallet.php:231-238`)
   - Elimina todas las cajas al eliminar palet
   - **Líneas**: 231-238
   - **Problema**: Puede eliminar cajas que están en producción
   - **Recomendación**: 
     - Validar que no haya cajas usadas antes de eliminar
     - O solo desvincular cajas en lugar de eliminarlas

5. **PalletBox->delete() También Elimina Box** (`app/Models/PalletBox.php:51-57`)
   - Al eliminar `PalletBox`, elimina la `Box`
   - **Problema**: Puede eliminar cajas que están en otros palets o producción
   - **Recomendación**: Validar que la caja no esté en uso antes de eliminar

### ⚠️ Validaciones Faltantes

6. **No Valida Estado Antes de Operaciones** (`app/Http/Controllers/v2/PalletController.php`)
   - `moveToStore()` valida estado, pero `update()` no valida consistencia
   - **Problema**: Puede actualizar almacén de palet no almacenado
   - **Recomendación**: Validar estado antes de permitir cambios de almacén

7. **No Valida Capacidad del Almacén** (`app/Http/Controllers/v2/PalletController.php:529-560`)
   - No valida si el almacén tiene capacidad antes de mover palet
   - **Problema**: Puede exceder capacidad del almacén
   - **Recomendación**: Validar capacidad disponible

### ⚠️ Lógica Compleja en Update

8. **Update de Cajas Complejo** (`app/Http/Controllers/v2/PalletController.php:351-399`)
   - Lógica de actualizar/crear/eliminar cajas es compleja y propensa a errores
   - **Líneas**: 351-399
   - **Problema**: Difícil de mantener y debuggear
   - **Recomendación**: 
     - Separar en métodos privados
     - O usar sincronización de relaciones de Laravel

### ⚠️ Validación de Pesos

9. **No Valida netWeight <= grossWeight** (`app/Http/Controllers/v2/PalletController.php:181-189`)
   - No valida que peso neto sea menor o igual a peso bruto
   - **Problema**: Puede crear cajas con datos inválidos
   - **Recomendación**: Agregar validación custom

### ⚠️ Código Comentado

10. **Código Comentado en Modelo** (`app/Models/Pallet.php:53-56, 98-104`)
    - Hay código comentado y métodos legacy
    - **Líneas**: 53-56, 98-104
    - **Problema**: Confunde
    - **Recomendación**: Eliminar código muerto

### ⚠️ Relaciones Duplicadas

11. **boxes() y boxesV2()** (`app/Models/Pallet.php:58-67`)
    - Dos métodos para obtener cajas con ligeras diferencias
    - **Problema**: Confusión sobre cuál usar
    - **Recomendación**: Documentar claramente o unificar

### ⚠️ Filtros de Peso con havingRaw

12. **Filtros de Peso Usan havingRaw** (`app/Http/Controllers/v2/PalletController.php:118-138`)
    - Los filtros de peso usan `havingRaw` con `whereHas`
    - **Líneas**: 118-138
    - **Problema**: Puede no funcionar correctamente con `whereHas`
    - **Recomendación**: Revisar lógica o usar subqueries

### ⚠️ Orden en bulkUpdateState

13. **Orden de Operaciones** (`app/Http/Controllers/v2/PalletController.php:609-625`)
    - Primero elimina de almacén, luego cambia estado
    - **Estado**: Parece correcto, pero podría ser más claro

### ⚠️ Sin Validación de Unique GS1-128

14. **No Valida GS1-128 Único** (`app/Http/Controllers/v2/PalletController.php`)
    - No valida que `gs1_128` sea único
    - **Problema**: Puede haber cajas duplicadas con mismo código
    - **Recomendación**: Validar unicidad de `gs1_128` por caja

---

---

## 🔄 Cambios Recientes - Sistema de Estados Fijos

**Fecha**: 2025-01-XX  
**Versión**: 2.0

### Cambios Implementados

1. **Estados fijos**: Los estados ya no dependen de la tabla `pallet_states`, son constantes en el modelo
2. **Nuevo estado**: Agregado estado `processed` (4) para palets completamente consumidos en producción
3. **Lógica automática**: Los estados cambian automáticamente según:
   - Uso en producción (completamente consumido → `processed`)
   - Liberación de producción (todas las cajas disponibles → `registered`)
   - Finalización de pedidos (todos los palets → `shipped`)

**📖 Para más detalles**: Ver [31b-Palets-Estados-Fijos.md](./31b-Palets-Estados-Fijos.md)

---

## 🔄 Cambio reciente - Timeline de modificaciones (F-01)

**Fecha**: 2026-02-25  
**Estado**: Implementado

### Cambios Implementados

1. **Campo `timeline`**: Columna JSON nullable en `pallets` para historial de cambios.
2. **Endpoint**: `GET /api/v2/pallets/{id}/timeline` devuelve la lista cronológica (más reciente primero).
3. **Servicio**: `PalletTimelineService::record()` centraliza el registro; se invoca desde escritura de palets, acciones (mover, posición, vincular pedido) y modelo (cambios automáticos de estado).
4. **Tipos de evento**: `pallet_created`, `pallet_created_from_reception`, `state_changed`, `state_changed_auto`, `store_assigned`, `store_removed`, `position_assigned`, `position_unassigned`, `order_linked`, `order_unlinked`, `box_added`, `box_removed`, `box_updated`, `observations_updated`.

**📖 Detalle**: Ver sección [Timeline de modificaciones (F-01)](#-timeline-de-modificaciones-f-01) en este documento.

---

**Última actualización**: 2026-02-25

**Cambio reciente**: Implementación F-01 — Timeline de modificaciones en palet (campo `timeline`, endpoint `GET /pallets/{id}/timeline`, tipos de evento y detalles documentados arriba).

