# Restricciones por Assigned Store ID para Store Operator

## 📋 Resumen
Este documento describe las restricciones que deben implementarse para que los usuarios con rol `store_operator` solo puedan acceder y operar con el almacén asignado (`assigned_store_id`).

## 🎯 Objetivo
Limitar el acceso de los operadores de tienda para que solo puedan:
- Ver el almacén asignado
- Operar con palets del almacén asignado
- Realizar acciones solo dentro de su ámbito de responsabilidad

## 🔧 Implementaciones Pendientes

### 1. Middleware de Filtrado por Assigned Store
**Archivo**: `app/Http/Middleware/FilterByAssignedStore.php`

**Propósito**: Middleware que filtra automáticamente las consultas basándose en el `assigned_store_id` del usuario.

**Funcionalidad**:
- Verificar si el usuario tiene rol `store_operator`
- Si es `store_operator`, agregar filtro `WHERE store_id = user.assigned_store_id`
- Permitir acceso completo a otros roles (`superuser`, `manager`, `admin`)

**Código base**:
```php
public function handle(Request $request, Closure $next)
{
    $user = $request->user();
    
    if ($user && $user->hasRole('store_operator') && $user->assigned_store_id) {
        // Agregar filtro global para el store_id
        app()->instance('assigned_store_filter', $user->assigned_store_id);
    }
    
    return $next($request);
}
```

### 2. Modificaciones en StoreController
**Archivo**: `app/Http/Controllers/v2/StoreController.php`

**Métodos a modificar**:
- `index()` - Filtrar almacenes por `assigned_store_id`
- `show($id)` - Verificar que el almacén pertenece al usuario
- `update()` - Restringir actualizaciones al almacén asignado
- `destroy()` - Restringir eliminación al almacén asignado

**Implementación**:
```php
public function index(Request $request)
{
    $query = Store::query();
    
    // Filtrar por assigned_store_id si es store_operator
    if ($request->user()->hasRole('store_operator') && $request->user()->assigned_store_id) {
        $query->where('id', $request->user()->assigned_store_id);
    }
    
    return StoreResource::collection($query->paginate(10));
}
```

### 3. Modificaciones en PalletController
**Archivo**: `app/Http/Controllers/v2/PalletController.php`

**Métodos a modificar**:
- `index()` - Filtrar palets por almacén asignado
- `show($id)` - Verificar que el palet pertenece al almacén asignado
- `store()` - Restringir creación de palets al almacén asignado
- `update()` - Restringir actualizaciones
- `assignToPosition()` - Verificar que la posición pertenece al almacén asignado
- `moveToStore()` - Restringir movimiento solo al almacén asignado

**Implementación**:
```php
public function index(Request $request)
{
    $query = Pallet::query();
    
    // Filtrar por assigned_store_id si es store_operator
    if ($request->user()->hasRole('store_operator') && $request->user()->assigned_store_id) {
        $query->whereHas('storedPallet', function($q) use ($request) {
            $q->where('store_id', $request->user()->assigned_store_id);
        });
    }
    
    return PalletResource::collection($query->paginate(10));
}
```

### 4. Modificaciones en Rutas
**Archivo**: `routes/api.php`

**Cambios necesarios**:
- Aplicar middleware `FilterByAssignedStore` a rutas específicas
- Crear grupos de rutas separados para operaciones restringidas

**Implementación**:
```php
// Rutas con restricción por assigned_store_id
Route::middleware(['auth:sanctum', 'role:store_operator'])->group(function () {
    Route::get('stores/assigned', [V2StoreController::class, 'assignedStore']);
    Route::get('pallets/assigned-store', [V2PalletController::class, 'assignedStorePallets']);
});
```

### 5. Validaciones en Modelos
**Archivos**: `app/Models/Store.php`, `app/Models/Pallet.php`

**Funcionalidad**:
- Agregar scopes para filtrar por almacén asignado
- Métodos de verificación de pertenencia

**Implementación**:
```php
// En Store.php
public function scopeForUser($query, $user)
{
    if ($user->hasRole('store_operator') && $user->assigned_store_id) {
        return $query->where('id', $user->assigned_store_id);
    }
    return $query;
}

// En Pallet.php
public function scopeForAssignedStore($query, $storeId)
{
    return $query->whereHas('storedPallet', function($q) use ($storeId) {
        $q->where('store_id', $storeId);
    });
}
```

### 6. Modificaciones en Resources
**Archivos**: `app/Http/Resources/v2/StoreResource.php`, `app/Http/Resources/v2/PalletResource.php`

**Funcionalidad**:
- Ocultar información sensible para `store_operator`
- Mostrar solo datos relevantes para su almacén

## 🚫 Restricciones Específicas

### Para Store Operator:
1. **Solo puede ver su almacén asignado**
2. **Solo puede operar con palets de su almacén**
3. **No puede crear/eliminar almacenes**
4. **No puede mover palets a otros almacenes**
5. **No puede ver estadísticas globales**
6. **No puede acceder a configuraciones del sistema**

### Operaciones Permitidas:
- ✅ Ver su almacén asignado
- ✅ Ver palets de su almacén
- ✅ Crear palets en su almacén
- ✅ Actualizar palets de su almacén
- ✅ Asignar posiciones dentro de su almacén
- ✅ Cambiar estados de palets de su almacén

### Operaciones Restringidas:
- ❌ Ver otros almacenes
- ❌ Ver palets de otros almacenes
- ❌ Mover palets entre almacenes
- ❌ Crear/eliminar almacenes
- ❌ Acceder a estadísticas globales
- ❌ Operaciones masivas

## 🧪 Casos de Prueba

### Usuario: `app@algarseafood.pt` (assigned_store_id: 1)

**Pruebas a realizar**:
1. **GET /v2/stores** - Debe retornar solo el almacén con ID 1
2. **GET /v2/pallets** - Debe retornar solo palets del almacén 1
3. **GET /v2/stores/2** - Debe retornar error 403 (Forbidden)
4. **POST /v2/pallets/move-to-store** - Debe fallar si intenta mover a otro almacén
5. **POST /v2/stores** - Debe retornar error 403 (Forbidden)

## 📝 Orden de Implementación

1. **Fase 1**: Crear middleware `FilterByAssignedStore`
2. **Fase 2**: Modificar `StoreController` con filtros básicos
3. **Fase 3**: Modificar `PalletController` con filtros básicos
4. **Fase 4**: Agregar validaciones en modelos
5. **Fase 5**: Crear casos de prueba
6. **Fase 6**: Aplicar restricciones a rutas específicas
7. **Fase 7**: Modificar resources para ocultar información sensible

## 🔍 Consideraciones Adicionales

### Seguridad:
- Validar `assigned_store_id` en cada request
- Verificar permisos antes de cada operación
- Log de intentos de acceso no autorizados

### Performance:
- Usar índices en `store_id` y `assigned_store_id`
- Optimizar consultas con `whereHas`
- Cache de permisos del usuario

### UX:
- Mensajes de error claros
- Indicadores visuales de restricciones
- Documentación de funcionalidades disponibles

## 📊 Impacto en el Sistema

### Base de Datos:
- No requiere cambios en estructura
- Solo filtros adicionales en consultas

### API:
- Respuestas filtradas automáticamente
- Códigos de error apropiados (403, 404)

### Frontend:
- Adaptar UI para mostrar solo datos permitidos
- Manejar errores de permisos
- Ocultar funcionalidades no disponibles

---

**Estado**: 📋 Planificado  
**Prioridad**: 🔴 Alta  
**Estimación**: 2-3 días de desarrollo  
**Dependencias**: Sistema de roles implementado ✅
