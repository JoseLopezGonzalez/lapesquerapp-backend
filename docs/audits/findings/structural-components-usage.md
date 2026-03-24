# Uso de Componentes Estructurales Laravel — PesquerApp Backend
**Fecha**: 2026-03-24 | **Auditoría**: Principal Backend v2 (actualización con evidencia de código)

---

## Inventario de Componentes

| Componente | Cantidad | Estado |
|---|---|---|
| Controladores v2 | 64 | Bueno con excepciones |
| Servicios (app/Services + sub-carpetas) | 63 | Fuerte |
| Modelos | 71 | Muy bueno |
| Form Requests | 185 | Fuerte |
| Policies | 42 | Fuerte |
| API Resources | 51 | Bueno |
| Jobs | 2 | Presente (solo Superadmin) |
| Events / Listeners | Escaso | Solo en capa Superadmin |
| Actions / DTOs | Ausente | Decisión arquitectónica explícita |
| Traits | `UsesTenantConnection` + varios helpers | Correcto |

---

## Controladores: Tamaño y Responsabilidades

### Controladores por encima de 200 líneas (target es < 200)

| Controlador | Líneas | Estado |
|---|---|---|
| `PunchController.php` | 459 | Delegado en 5 servicios pero aún tiene lógica de routing inline en `store()` |
| `ExcelController.php` | 315 | Fino pero concentra muchos endpoints de export |
| `PalletController.php` | 310 | Puede dividirse por responsabilidad (CRUD vs acciones) |
| `OrderController.php` | 298 | Delega bien en OrderListService/OrderStoreService/etc. |
| `BoxesController.php` | 277 | Revisable |
| `AuthController.php` | 268 | Complejo por multi-método de auth; aceptable |
| `SpeciesController.php` | 264 | Catalogo pesquero con mucha configuración |
| `StoreController.php` | 258 | Almacenes + posiciones |
| `ProductionRecordController.php` | 251 | Árbol de producción complejo |
| `CustomerController.php` | 248 | CRM + datos de cliente |

**PunchController en detalle**: Aunque delega correctamente en 5 servicios, el método `store()` tiene lógica de routing interno (líneas ~70-90):
```php
if ($request->has('timestamp') && $request->has('event_type')) {
    $this->authenticateManualRequest($request); // Autenticación manual dentro del controller
    return $this->storeManual($request);
}
// else: fichaje NFC
```
Este `authenticateManualRequest()` privado hace auth dentro del controller — debería ser middleware o un service de resolución de actores.

---

## Servicios: Distribución de Responsabilidades

### Servicios más grandes

| Servicio | Líneas | Observación |
|---|---|---|
| `RawMaterialReceptionWriteService.php` | 759 | Sin DB::transaction propio; depende del controller. Candidato a dividir |
| `CrmAgendaService.php` | 528 | Mezcla lectura (calendar, summary) y escritura (store, reschedule, cancel) |
| `PunchEventWriteService.php` | 521 | Bien estructurado en métodos privados; acceptable |
| `PalletWriteService.php` | 514 | Opera en dominio complejo; aceptable si cohesivo |
| `ProductionRecordService.php` | 487 | Árbol complejo; aceptable |
| `PunchStatisticsService.php` | 441 | Puro de lectura; acceptable |

**RawMaterialReceptionWriteService**: El problema no es el tamaño sino que la transacción está en el controller (`RawMaterialReceptionController.php:32, 57`) y no en el servicio. Si alguien reutiliza el servicio desde otro contexto sin envolver en transacción, puede quedar en estado inconsistente. El service debería gestionar su propia integridad transaccional.

---

## Form Requests: Calidad de Validaciones

### Fortalezas
- 185 Form Requests cubren la gran mayoría de operaciones
- Uso de `exists:tenant.{table},id` para validar relaciones del tenant (correcto)
- Mensajes de error en español en las reglas críticas

### Gap detectado
- `PunchController::store()` tiene validaciones inline con `$request->validate()` en el método:
  ```php
  $validated = $request->validate([
      'uid' => 'nullable|string|required_without:employee_id',
      'employee_id' => 'nullable|integer|exists:tenant.employees,id|required_without:uid',
      'device_id' => 'required|string',
  ]);
  ```
  Debería estar en un `StoreNfcPunchRequest` separado para coherencia con el resto.

---

## Policies: Cobertura y Calidad

- **42 Policies** para todos los modelos de negocio principales
- **246 llamadas a `$this->authorize()`** en controladores v2
- **6 controladores sin ninguna llamada a `authorize()`**:
  - `AuthController.php` — justificado (contexto de autenticación)
  - `CrmDashboardController.php` — usa solo role middleware
  - `CrmAgendaController.php` — usa solo role middleware
  - `OrdersReportController.php` — usa solo role middleware
  - `PdfExtractionController.php` — usa solo role middleware
  - `RoleController.php` — usa solo role middleware

El comentario en routes/api.php documenta la deuda explícitamente: "por ahora todas accesibles para todos los roles (luego: policies y restricciones)".

---

## Jobs: Patrón Correcto pero Solo en Superadmin

Los 2 jobs existentes están en la capa Superadmin:
- `OnboardTenantJob` — opera en BD central, correcto sin UsesTenantConnection
- `MigrateTenantJob` — opera en BD central, correcto

Cuando se implementen jobs para la capa de negocio (ej. export async, notifications), necesitarán:
1. Incluir `subdomain` en el constructor/payload
2. Ejecutar `TenantMiddleware::setupTenant()` o equivalente en `handle()`
3. Usar `UsesTenantConnection` en los modelos que accedan

Este patrón **no está formalizado como estándar** ni documentado en CLAUDE.md.

---

## Events y Listeners

La capa de eventos es escasa. `PalletWriteService` usa `Model::withoutEvents(fn () => $pallet->delete())` en la migración de recepción (línea 286), lo que indica que hay observers activos pero no hay una capa de eventos de dominio explícita.

---

## Valoración por Componente

| Componente | Nota | Comentario |
|---|---|---|
| Services | 8/10 | Bien divididos; algunos demasiado grandes |
| Form Requests | 8.5/10 | Alta cobertura; gap en PunchController |
| Policies | 8/10 | 42 policies; 5 controllers sin authorize() en CRM |
| API Resources | 8/10 | Buena cobertura; algunos serializan relaciones no siempre necesarias |
| Jobs | 6/10 | Solo Superadmin; sin patrón formalizado para negocio |
| Events | 5/10 | Escasos; no hay capa de dominio events |
| Controladores | 7/10 | 10 sobre 200 líneas; algunos con lógica no delegada |

**Madurez estructural Laravel**: **8/10**
