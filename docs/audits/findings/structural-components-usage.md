# Uso de componentes estructurales Laravel — PesquerApp Backend

**Documento de hallazgos** | Auditoría global Laravel  
**Fecha**: 2026-02-14

---

## 1. Objetivo

Evaluar la presencia, corrección y consistencia de los bloques estructurales que Laravel y la comunidad recomiendan: Services, Actions, Jobs, Events/Listeners, Form Requests, Policies/Gates, Middleware, API Resources y otros (DTOs, Observers).

---

## 2. Services (capa de aplicación)

| Aspecto | Valoración | Detalle |
|---------|------------|---------|
| Presencia | ✅ | Múltiples servicios en `app/Services/`, `app/Services/v2/`, `app/Services/Production/`. |
| Responsabilidad | ✅ | Producción (ProductionRecordService, ProductionOutputService, etc.), estadísticas (OrderStatisticsService, StockStatisticsService, etc.), OrderPDFService, OrderMailerService, TenantMailConfigService, MagicLinkService. |
| Reutilización | ✅ | Servicios inyectados en controladores; transacciones y lógica compleja centralizadas. |

**Conclusión**: Uso correcto y coherente. La lógica de aplicación está bien separada de controladores en dominios complejos.

---

## 3. Actions (invocables de un solo propósito)

| Aspecto | Valoración | Detalle |
|---------|------------|---------|
| Presencia | ❌ | No existe carpeta `app/Actions` ni clases invocables de un solo propósito. |
| Necesidad | Opcional | La lógica está en servicios o en controladores; no es obligatorio introducir Actions. Si en el futuro se quieren operaciones muy acotadas (por ejemplo “Confirmar pedido”, “Cerrar producción”), una Action por operación podría mejorar claridad. |

---

## 4. Jobs (colas)

| Aspecto | Valoración | Detalle |
|---------|------------|---------|
| Presencia | ❌ | No hay clases Job en el proyecto. |
| Riesgo futuro | ⚠️ | Si se añaden colas (envío de correo asíncrono, reportes, integraciones), el **contexto tenant** no se propaga por defecto. Debe definirse convención (payload con tenant_subdomain o tenant_database) y configurar la conexión tenant en el worker. |

---

## 5. Events y Listeners

| Aspecto | Valoración | Detalle |
|---------|------------|---------|
| Presencia | Solo por defecto | EventServiceProvider registra únicamente `Registered` → `SendEmailVerificationNotification`. |
| Eventos de dominio | ❌ | No hay eventos propios (por ejemplo OrderShipped, ProductionFinished). |
| Decoupling | N/A | Los efectos secundarios (correo, PDF) se invocan desde controladores o servicios de forma síncrona. Adecuado para el volumen actual; si crece la necesidad de desacoplar, introducir eventos sería el siguiente paso. |

---

## 6. Form Requests

| Aspecto | Valoración | Detalle |
|---------|------------|---------|
| Presencia | ⚠️ Parcial | 14 clases en `app/Http/Requests/v2/`, **todas** del módulo Production (Store/Update/Sync/Multiple para Production, ProductionRecord, ProductionInput, ProductionOutput, ProductionOutputConsumption). |
| Resto de la API | ❌ | Endpoints de Orders, Customers, RawMaterialReceptions, Pallets, etc. no usan Form Request dedicado; validación en controlador con `$request->validate()` o sin validación explícita. |
| Consistencia | ⚠️ | Inconsistente: un módulo (Production) tiene validación en frontera HTTP bien encapsulada; el resto depende de controladores. |

**Recomendación**: Extender Form Requests a recursos críticos (Order, RawMaterialReception, Customer, etc.) para unificar validación y autorización en la frontera HTTP.

---

## 7. Policies y Gates

| Aspecto | Valoración | Detalle |
|---------|------------|---------|
| Registro | ✅ | AuthServiceProvider registra `Order::class => OrderPolicy::class`, `User::class => UserPolicy::class`. |
| Uso en controladores | ⚠️ Parcial | **Solo UserController** llama a `authorize()` (viewAny, create, view, update, delete). OrderController y el resto de controladores **no** llaman a `authorize()` para Order ni para otros modelos. |
| Consistencia | ❌ | Policies definidas pero no aplicadas en la mayoría de recursos. La autorización efectiva es solo por rol a nivel de ruta. |

**Recomendación**: Usar `$this->authorize('action', $model)` en todos los controladores de recursos críticos (Order, RawMaterialReception, User, etc.) para que las políticas tengan efecto real.

---

## 8. Middleware

| Aspecto | Valoración | Detalle |
|---------|------------|---------|
| Presencia | ✅ | `tenant`, `auth:sanctum`, `role:...`, `throttle`, `LogActivity`. |
| Coherencia | ✅ | Rutas v2 bajo `tenant`; rutas protegidas con `auth:sanctum` y rol; throttling en login y magic link/OTP. |

---

## 9. API Resources

| Aspecto | Valoración | Detalle |
|---------|------------|---------|
| Presencia | ✅ | Múltiples recursos en `app/Http/Resources/v2/` (User, Order, Species, Store, Box, etc.). |
| Uso | ✅ | Controladores devuelven recursos para serialización; respuestas JSON homogéneas. |

---

## 10. Otros (DTOs, Observers, Repositories)

- **DTOs**: No utilizados. Los datos se pasan como arrays o como modelos; no es crítico para el tamaño actual.
- **Observers**: No registrados. Si se necesitan efectos secundarios al crear/actualizar/eliminar modelos (auditoría, invalidación de caché), los Observers serían la opción Laravel idiomática.
- **Repositories**: No hay capa de repositorios; acceso directo vía Eloquent y en varios sitios `DB::connection('tenant')->table()`. La mejora prioritaria es reducir el acceso directo a `table()` más que introducir un patrón Repository completo.

---

## 11. Resumen

| Componente | Presencia | Consistencia | Acción sugerida |
|------------|-----------|--------------|-----------------|
| Services | ✅ | ✅ | Mantener; ampliar si aparecen nuevos dominios complejos. |
| Actions | ❌ | — | Opcional; valorar para operaciones muy acotadas. |
| Jobs | ❌ | — | Al añadir colas: convención de contexto tenant. |
| Events/Listeners | Solo default | — | Valorar si se quiere desacoplar efectos secundarios. |
| Form Requests | ⚠️ | ⚠️ | Extender a más recursos (Order, Customer, etc.). |
| Policies | ✅ registro | ❌ uso | Llamar a `authorize()` en todos los controladores de recursos críticos. |
| Middleware | ✅ | ✅ | Sin cambios. |
| API Resources | ✅ | ✅ | Sin cambios. |

El proyecto utiliza bien Services y API Resources; el mayor gap es el **uso desigual** de Form Requests y de Policies (registradas pero no aplicadas en la mayoría de controladores).
