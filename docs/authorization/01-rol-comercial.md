# Restricciones de lógica de negocio — Rol Comercial

**Última actualización**: 2026-02-18
**Estado**: Documento de especificación. Implementación pendiente.
**Relación**: User vinculado a Salesperson (`salespeople.user_id`). Ver [00-autorizacion-permisos-estado-completo.md](../por-hacer/00-autorizacion-permisos-estado-completo.md).

Este documento define de forma **persistente** las restricciones de autorización y datos aplicables al rol **comercial**. Sirve como referencia única para implementación y para futuros documentos de otros roles en este mismo directorio.

---

## 1. Precondición

- El usuario tiene `role = 'comercial'`.
- Debe existir un **Salesperson** con `user_id = $user->id` (vinculación ya implementada). Si no existe, el comercial no puede realizar acciones restringidas a “sus” datos (listar pedidos/clientes propios, crear pedidos/clientes como él mismo).

---

## 2. Resumen ejecutivo de restricciones

| Ámbito                   | Permitido                                                                                                                                                                         | No permitido                                                                                                                  |
| ------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------- |
| **Pedidos**         | Listar solo los suyos (paginated, active, options, export, report). Crear solo para clientes suyos. Ver detalle solo de pedidos suyos. Estadísticas y production-view solo con sus datos (solo lectura). Ver incidencias de sus pedidos. PDFs: hoja de pedido, nota de carga, nota de carga valorada. | Editar, borrar, cambiar estado, vincular/desvincular palets. Ver pedidos de otros. Gestionar incidencias (crear/editar/borrar). Enviar documentos por email (por lo pronto). Otros PDFs (por lo pronto). |
| **Clientes**        | Listar solo los suyos. Crear clientes asociándolos a sí mismo como comercial. Ver detalle solo de clientes suyos.                                                               | Editar, borrar. Ver/listar clientes de otros comerciales.                                                                     |
| **Otras entidades** | Ver únicamente lo necesario en **options** para sus acciones. **Salespeople/options**: solo su propio registro. Ver **settings** (whitelist de keys genéricas, no críticas). | Editar, borrar, ver detalle o listados completos de entidades que no sean Order/Customer (salvo options y settings acotados). |
| **Settings**        | Ver whitelist (company.name, company.logo_url, etc.; excluir company.mail.* y sensibles).                                                                                        | Editar settings.                                                                                                              |

---

## 3. Restricciones detalladas por recurso

### 3.1 Pedidos (Order)

#### 3.1.1 Listados (solo pedidos asociados al comercial)

- **Regla**: Cualquier endpoint que devuelva una lista de pedidos debe filtrar por `orders.salesperson_id = $user->salesperson->id`.
- **Endpoints afectados**:
  - `GET /api/v2/orders` (index paginado) — `OrderListService::list()`
  - `GET /api/v2/orders/active` — `OrderListService::active()`
  - `GET /api/v2/orders/options` — `OrderListService::options()`
  - `GET /api/v2/active-orders/options` — `OrderListService::activeOrdersOptions()`
  - `GET /api/v2/orders_report` (export Excel) — filtro en export
  - Cualquier otro endpoint que liste pedidos (reportes, estadísticas de listado, etc.) debe aplicar el mismo filtro cuando el usuario es comercial.
- **Implementación sugerida**: Un único “query base” de pedidos para el usuario actual (p. ej. `Order::queryForCurrentUser()` o en un servicio compartido) que aplique `where('salesperson_id', $user->salesperson->id)` si el usuario es comercial; todos los listados y exports usan ese query base.

#### 3.1.2 Crear pedido

- **Regla**: Solo puede crear pedidos para **clientes que tengan como comercial al mismo** (`customer.salesperson_id = $user->salesperson->id`).
- **Validación**: En `StoreOrderRequest` o en el servicio: si el usuario es comercial, comprobar que `customer_id` corresponde a un Customer con `salesperson_id = $user->salesperson->id`. Si no, 403 o 422.
- **Campo comercial en el pedido**: Al crear, el pedido debe quedar con `salesperson_id = $user->salesperson->id` (forzar; el comercial no puede asignar otro comercial).

#### 3.1.3 Ver detalle de un pedido

- **Regla**: Solo puede ver (show) un pedido si `order.salesperson_id = $user->salesperson->id`.
- **Policy**: `OrderPolicy::view($user, $order)` debe devolver `false` para comercial si el pedido no es suyo.

#### 3.1.4 Editar / Borrar / Cambio de estado / Palets

- **Regla**: El comercial **no puede** editar pedidos, borrarlos, cambiar estado ni vincular/desvincular palets.
- **Policy**: `OrderPolicy::update`, `delete`, `restore`, `forceDelete` → para rol comercial devolver `false`.
- **Rutas**: update, updateStatus, destroy, destroyMultiple, linkOrders, unlinkOrders, etc. quedan denegadas por policy para comercial.
- **Palets disponibles para orden** (`GET /orders/{orderId}/available-pallets`): denegar para comercial, ya que no puede vincular palets a pedidos.

#### 3.1.5 Exportaciones y reportes de pedidos

- **Regla**: Cualquier export o reporte que liste pedidos debe usar el mismo filtro por comercial (solo sus pedidos).

#### 3.1.6 Estadísticas de pedidos

- **Regla**: Endpoints como `orders/sales-by-salesperson`, `orders/transport-chart-data` y similares: el comercial **sí** puede acceder, pero los datos deben estar **limitados a sus pedidos (y/o clientes suyos)**. Es decir, mismo filtro `salesperson_id` en el backend para que solo vea su propia fila/dato, no datos de otros comerciales.

#### 3.1.7 Vista de producción

- **Regla**: El comercial **sí** puede ver `orders/production-view`, pero solo con **sus pedidos** y en **solo lectura**: sin editar, sin vincular/desvincular palets ni ninguna acción de escritura.

#### 3.1.8 Incidencias de pedido

- **Regla**: El comercial **no puede gestionar** incidencias (no crear, editar ni borrar). **Sí puede ver** la incidencia de sus pedidos (solo lectura). Endpoints `orders/{id}/incident`: GET permitido si el pedido es suyo; POST, PUT, DELETE denegados para comercial.

#### 3.1.9 Envío de documentos por email

- **Regla**: **No por lo pronto** — El comercial no puede enviar documentos por email (send-standard-documents, send-custom-documents). Valorar en el futuro si se permite para sus pedidos y ciertos tipos.
- **Recordatorio futuro**: Revisar si permitir envío solo para sus pedidos y solo documentos permitidos (hoja de pedido, nota de carga, nota de carga valorada).

#### 3.1.10 PDFs y documentos

- **Regla**: El comercial solo puede generar estos PDFs: **hoja de pedido**, **nota de carga**, **nota de carga valorada**. Cualquier otro PDF (CMR, confirmación, etc.) **no por lo pronto**.
- **Implementación**: Autorizar en el controlador de PDF por tipo de documento cuando el usuario es comercial; para los tres permitidos, comprobar que el pedido es suyo (`order.salesperson_id = $user->salesperson->id`).
- **Recordatorio futuro**: Valorar si añadir más documentos (CMR, confirmación, etc.) para uso diario del comercial.

#### 3.1.11 Autoventas

- **Regla**: Las **autoventas** son pedidos con `order_type = 'autoventa'`. Solo los usuarios con rol **comercial** pueden crear autoventas (`POST /api/v2/orders` con `orderType: 'autoventa'`).
- **Mismas restricciones**: Crear solo para clientes suyos; listar y ver detalle solo sus pedidos (las autoventas aparecen en el listado de pedidos y pueden filtrarse con `orderType=autoventa`). El comercial no puede editar, borrar ni cambiar estado de una autoventa, igual que con el resto de pedidos.
- **Implementación**: `OrderController::store` valida que, si `orderType === 'autoventa'`, el usuario tenga rol comercial y Salesperson asociado; `AutoventaStoreService` crea el Order con `order_type = 'autoventa'`, las líneas planificadas, un palet en estado enviado y las cajas asociadas.

---

### 3.2 Clientes (Customer)

#### 3.2.1 Listados

- **Regla**: Solo puede listar clientes cuyo `salesperson_id = $user->salesperson->id`.
- **Endpoint**: `GET /api/v2/customers` — el servicio de listado (p. ej. `CustomerListService::list()`) debe recibir o inferir el filtro por comercial y aplicarlo cuando el usuario es comercial.

#### 3.2.2 Crear cliente

- **Regla**: Solo puede crear clientes **asociándolos a sí mismo** como comercial. Es decir, en el payload o en el servicio, `salesperson_id` debe quedar fijado a `$user->salesperson->id` (y no aceptar otro valor si el usuario es comercial).
- **Validación**: Si el request envía `salesperson_id` y el usuario es comercial, ignorarlo o rechazarlo y forzar `$user->salesperson->id`.

#### 3.2.3 Ver detalle de un cliente

- **Regla**: Solo puede ver (show) un cliente si `customer.salesperson_id = $user->salesperson->id`.
- **Policy**: `CustomerPolicy::view($user, $customer)` → para comercial, devolver true solo si el cliente es suyo.

#### 3.2.4 Editar / Borrar

- **Regla**: El comercial **no puede** editar ni borrar clientes.
- **Policy**: `CustomerPolicy::update`, `delete`, `restore`, `forceDelete` → para rol comercial devolver `false`.

---

### 3.3 Options (desplegables / selects)

- **Regla**: El comercial debe poder usar los endpoints **options** estrictamente necesarios para:
  - Crear y ver sus pedidos: p. ej. **customers/options** (solo sus clientes), **transports/options**, **incoterms/options**, **countries/options**, **payment-terms/options**, **products/options** (o los que use el formulario de pedido), etc.
  - Crear clientes: p. ej. **countries/options**, **payment-terms/options**, **transports/options**, etc.
- **Restricción**: No debe poder ver **detalle** (show) ni **listados completos** (index) de esas entidades; solo el formato “options” (id + name o equivalente) para rellenar formularios.
- **Customers/options**: Debe devolver **solo los clientes del comercial** (`customer.salesperson_id = $user->salesperson->id`). Los demás options (transports, countries, etc.) pueden seguir siendo listas completas del tenant, ya que son catálogos compartidos.
- **Salespeople/options**: Para un comercial, el endpoint debe devolver **solo su propio registro** (id + name) para que el frontend pueda mostrar “Asignado a mí” o similar. No puede ver la lista de otros comerciales.

---

### 3.4 Settings

- **Regla**: El comercial debe poder **ver** los settings necesarios para la interfaz (títulos, nombre de empresa en el nav, etc.). No debe poder **editar** settings.
- **Whitelist para comercial**: Devolver solo las keys **más genéricas y menos críticas** (p. ej. `company.name`, `company.logo_url`, u otras de presentación/nav). Excluir del GET para comercial las sensibles (p. ej. `company.mail.*` u otras de configuración crítica). Implementar en `SettingService::getAllKeyValue()` (o equivalente) filtrando por rol cuando el usuario es comercial.
- **Estado actual**: `SettingPolicy::viewAny` devuelve `true` para todos; `update` está restringido a administrador y tecnico. Falta aplicar la whitelist de keys en la respuesta para comercial.

---

## 4. Entidades a las que el comercial no tiene acceso (listado/detalle/edición/borrado)

Salvo lo indicado en Options y Settings, el comercial **no** debe tener acceso a:

- User, Salesperson (no listar ni ver detalle; options de salespeople acotado o solo él).
- Store, Pallet, Box, Product (como entidad completa; solo options si aplica para crear pedido).
- RawMaterialReception, CeboDispatch, Production, Label, PunchEvent, Employee, etc.
- Cualquier otra entidad no mencionada arriba: sin listado, sin detalle, sin edición, sin borrado. Solo options cuando sea imprescindible para crear pedido/cliente.

Las **policies** de esas entidades deben denegar `viewAny` / `view` / `update` / `delete` para el rol comercial (o el middleware/ruta ya no expone esos endpoints al comercial, según diseño).

---

## 5. Estado actual vs deseado (resumen)

| Recurso / Acción                               | Estado actual                                 | Deseado (este documento)                                           |
| ----------------------------------------------- | --------------------------------------------- | ------------------------------------------------------------------ |
| Order list (index, active, options)             | Todos los roles ven todos los pedidos         | Comercial solo ve los suyos (salesperson_id)                       |
| Order show                                      | Todos pueden ver cualquier pedido             | Comercial solo si order.salesperson_id = su Salesperson            |
| Order create                                    | Todos pueden; salesperson opcional en payload | Comercial solo para clientes suyos; salesperson_id forzado a su id |
| Order update/delete/updateStatus                | Todos pueden                                  | Comercial no puede                                                 |
| Customer list                                   | Todos ven todos                               | Comercial solo sus clientes                                        |
| Customer show                                   | Todos pueden                                  | Comercial solo si customer.salesperson_id = su Salesperson         |
| Customer create                                 | Todos; salesperson_id opcional                | Comercial solo creando con salesperson_id = su id                  |
| Customer update/delete                          | Todos pueden                                  | Comercial no puede                                                 |
| Customers/options                               | Todos los clientes                            | Comercial solo sus clientes                                        |
| Settings view                                   | Todos (viewAny true)                          | Comercial puede ver; opcional filtrar keys (company.name, etc.)    |
| Settings update                                 | Solo admin/tecnico                            | Sin cambio (comercial no puede)                                    |
| Otras entidades (viewAny, view, update, delete) | Todos los roles permitidos                    | Comercial denegado; solo options necesarios para sus flujos        |

---

## 6. Decisiones tomadas (resumen)

- **PDFs**: Solo hoja de pedido, nota de carga y nota de carga valorada. Otros (CMR, confirmación, etc.) no por lo pronto — ver recordatorios.
- **Estadísticas**: El comercial puede acceder; datos limitados a sus pedidos / clientes suyos (mismo filtro en backend).
- **Vista de producción**: Puede verla en solo lectura, solo sus pedidos; sin editar ni vincular palets.
- **Incidencias**: Solo ver (GET); no crear, editar ni borrar.
- **Envío por email**: No puede enviar documentos por lo pronto — ver recordatorios.
- **Settings**: Whitelist de keys genéricas y no críticas (p. ej. company.name, company.logo_url); excluir sensibles (company.mail.*, etc.).
- **Salespeople/options**: Devolver solo su propio registro (id + name).

---

## 7. Recordatorios para futuro (“no por lo pronto”)

Puntos dejados explícitamente para valorar más adelante:

- **Más PDFs para comercial**: Valorar si permitir CMR, confirmación u otros documentos para uso diario del comercial.
- **Envío de documentos por email**: Valorar si permitir al comercial enviar (solo sus pedidos y solo tipos permitidos: hoja de pedido, nota de carga, nota de carga valorada).

---

## 8. Implementación pendiente (checklist de alto nivel)

- [ ] **OrderPolicy**: viewAny (scope), view($order) por salesperson_id; update, delete, restore, forceDelete → false para comercial.
- [ ] **CustomerPolicy**: viewAny (scope), view($customer) por salesperson_id; update, delete, restore, forceDelete → false para comercial.
- [ ] **OrderListService** (y todos los puntos que construyan query de orders): aplicar filtro por salesperson_id cuando user es comercial (o usar query base común).
- [ ] **CustomerListService**: aplicar filtro por salesperson_id cuando user es comercial.
- [ ] **StoreOrderRequest / OrderStoreService**: validar customer suyo para comercial; forzar salesperson_id al del usuario.
- [ ] **StoreCustomerRequest / CustomerController::store**: forzar salesperson_id al del usuario cuando es comercial.
- [ ] **Customers/options**: filtrar por salesperson_id cuando user es comercial.
- [ ] **Orders/options y active-orders/options**: ya filtrados si el query base de orders se aplica.
- [ ] **Export orders_report** (y otros exports de pedidos): aplicar mismo filtro comercial.
- [ ] **PDFs**: restringir por tipo de documento para comercial; comprobar ownership del pedido.
- [ ] **Policies de otras entidades**: denegar viewAny/view/update/delete para comercial donde corresponda; mantener o exponer solo options necesarios.
- [ ] **Settings**: whitelist de keys para comercial en GET (genéricas, no críticas).
- [ ] **Estadísticas** (sales-by-salesperson, transport-chart-data, etc.): aplicar filtro por salesperson_id para comercial.
- [ ] **Production-view**: permitir acceso solo lectura; query filtrado por sus pedidos.
- [ ] **Incidencias**: GET permitido si pedido suyo; POST/PUT/DELETE denegados para comercial.
- [ ] **Envío documentos por email**: denegado para comercial (por lo pronto).
- [ ] **Salespeople/options**: para comercial, devolver solo el registro del usuario (su Salesperson).
- [ ] **Available pallets for order** (`GET /orders/{orderId}/available-pallets`): denegar para comercial.

---

## 9. Referencias

- [00-autorizacion-permisos-estado-completo.md](../por-hacer/00-autorizacion-permisos-estado-completo.md) — Matriz general y User–Salesperson.
- [CLAUDE.md](../../CLAUDE.md) — Convenciones y arquitectura del proyecto.
- Modelos: `App\Models\Order`, `App\Models\Customer`, `App\Models\User`, `App\Models\Salesperson`.
- Policies: `App\Policies\OrderPolicy`, `App\Policies\CustomerPolicy`, `App\Policies\SettingPolicy`.

Este directorio (`docs/authorization/`) contendrá en el futuro documentos análogos para otros roles (operario, administración, etc.).
