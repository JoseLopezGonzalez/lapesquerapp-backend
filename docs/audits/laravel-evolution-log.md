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
