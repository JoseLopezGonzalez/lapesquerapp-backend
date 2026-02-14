# Laravel Evolution Log

Registro de bloques de evolución incremental del backend (CORE v1.0 Consolidation Plan).  
Cada entrada sigue el formato de STEP 5 del prompt de evolución.

---

## [2026-02-14] Block 1: Auth (Políticas Order y User) - Fase 1

**Priority**: P1  
**Risk Level**: Low

### Problems Addressed
- Falta de autorización por recurso (auditoría: Top 5 riesgos #1).
- Sin políticas registradas; solo middleware por rol a nivel de grupo de rutas.

### Changes Applied
- Creada `App\Policies\OrderPolicy` (viewAny, view, create, update, delete, restore, forceDelete). Regla: cualquiera de los 6 roles. No se añaden llamadas `authorize()` en OrderController en este bloque (pendiente bloque Sales/Orders).
- Creada `App\Policies\UserPolicy` con los mismos métodos y regla. Autorización básica implementada.
- Registro en `AuthServiceProvider`: Order::class => OrderPolicy::class, User::class => UserPolicy::class.
- Añadido `authorize()` en UserController: index (viewAny), show (view), store (create), update (update), destroy (delete), resendInvitation (update), options (viewAny).

### Verification Results
- ✅ Rutas de usuarios cargan correctamente (`php artisan route:list`).
- ✅ Configuración y políticas cargan sin error.
- ⚠️ Suite de tests existente mínima (ExampleTest); se recomienda verificación manual: listar/ver/crear/editar/eliminar usuarios y reenviar invitación con un usuario autenticado con uno de los 6 roles.

### Rollback Plan
Si aparece algún problema: `git revert <commit-hash>` del commit que introduce las políticas y las llamadas en UserController.

### Next Recommended Block
Config (modelo Setting) o Sales/Orders (authorize en OrderController + refactor de controlador grueso).

---

## [2026-02-14] Block 2: Sales (Ventas) — Autorización y Form Requests - Fase 1

**Priority**: P1  
**Risk Level**: Low  
**Rating antes: 5/10** | **Rating después: 6/10**

### Problems Addressed
- OrderPolicy registrada pero no usada en OrderController (autorización solo por middleware).
- Validación de store/update de pedidos inline en el controlador; sin Form Requests en el módulo Ventas.

### Changes Applied
- **Bloque 2A**: Añadidas llamadas `$this->authorize()` en OrderController: index (viewAny), store (create), show (view), update (update), destroy (delete), destroyMultiple (viewAny), updateStatus (update), options (viewAny), active (viewAny), activeOrdersOptions (viewAny), productionView (viewAny), salesBySalesperson (viewAny), transportChartData (viewAny).
- **Bloque 2B**: Creados `App\Http\Requests\v2\StoreOrderRequest` y `App\Http\Requests\v2\UpdateOrderRequest` con reglas y mensajes en español. Validación entry_date ≤ load_date en StoreOrderRequest vía `withValidator()`. OrderController::store usa StoreOrderRequest y `$request->validated()`; OrderController::update usa UpdateOrderRequest; se mantiene la validación adicional entry_date ≤ load_date en update cuando ambas fechas están presentes.

### Verification Results
- ✅ `php artisan route:list` carga rutas correctamente.
- ✅ Sin errores de lint en OrderController y Form Requests.
- ⚠️ Tests: solo ExampleTest (Unit pasa; Feature falla en GET '/' — preexistente). Verificación manual recomendada: listar/crear/editar/ver/eliminar pedidos y usar active, options, productionView, salesBySalesperson, transportChartData con usuario autenticado con rol permitido.

### Rollback Plan
Si aparece algún problema: `git revert <commit-hash>`. Para deshacer solo Form Requests: revert del commit de 2B y restaurar validación inline en store/update.

### Next Recommended Block
Sales 2C: extraer lógica de listado/filtros de OrderController a clase dedicada (OrderListQuery / OrderIndexService). Sales 2D: sustituir `DB::connection('tenant')->table()` en salesBySalesperson y transportChartData por Eloquent/servicio.

---
