# Referencia Técnica - Rutas Completas API v2

## ⚠️ Estado de la API
- **v1**: Eliminada (2025-01-27) - Ya no existe en el código base
- **v2**: Versión activa (este documento) - Única versión disponible

---

## 📋 Visión General

Este documento proporciona una referencia completa de todas las rutas de la API v2. Todas las rutas están bajo el prefijo `/api/v2` y requieren el header `X-Tenant` para identificar el tenant (multi-tenancy).

**Estructura de Rutas**:
- **Prefijo Base**: `/api/v2`
- **Middleware Global**: `tenant` (identificación de tenant)
- **Autenticación**: Laravel Sanctum (excepto rutas públicas)
- **Autorización**: Roles (`tecnico`, `administrador`, `direccion`, `administracion`, `comercial`, `operario`)

**Convenciones**:
- Rutas públicas: Sin autenticación
- Rutas protegidas: Requieren `auth:sanctum`
- Rutas por rol: Middleware específico de roles
- Rutas genéricas: Accesibles para múltiples roles

---

## 🔓 Rutas Públicas

Estas rutas no requieren autenticación pero sí requieren el header `X-Tenant`.

### Autenticación

| Método | Ruta | Controlador | Método | Descripción |
|--------|------|-------------|--------|-------------|
| `POST` | `/api/v2/login` | `V2AuthController` | `login` | Iniciar sesión |
| `POST` | `/api/v2/logout` | `V2AuthController` | `logout` | Cerrar sesión (requiere auth) |
| `GET` | `/api/v2/me` | `V2AuthController` | `me` | Obtener usuario actual (requiere auth) |

### Opciones de Clientes (Pública)

| Método | Ruta | Controlador | Método | Descripción |
|--------|------|-------------|--------|-------------|
| `GET` | `/api/v2/customers/op` | `V2CustomerController` | `options` | Opciones de clientes (pública) |

### Tenant Público

| Método | Ruta | Controlador | Método | Descripción |
|--------|------|-------------|--------|-------------|
| `GET` | `/api/v2/public/tenant/{subdomain}` | `TenantController` | `showBySubdomain` | Obtener información de tenant por subdominio |

---

## 🔐 Rutas Protegidas por Autenticación

Todas las rutas siguientes requieren autenticación con Sanctum (`auth:sanctum`) y el header `X-Tenant`.

---

## 👤 Rutas para Técnico

Estas rutas están protegidas por el middleware `role:tecnico`.

### Opciones

| Método | Ruta | Controlador | Método | Descripción |
|--------|------|-------------|--------|-------------|
| `GET` | `/api/v2/roles/options` | `RoleController` | `options` | Opciones de roles |
| `GET` | `/api/v2/users/options` | `UserController` | `options` | Opciones de usuarios |

### Descargas

| Método | Ruta | Controlador | Método | Descripción |
|--------|------|-------------|--------|-------------|
| `GET` | `/api/v2/orders_report` | `OrdersReportController` | `exportToExcel` | Exportar reporte de pedidos |

### Gestión de Sistema

| Método | Ruta | Controlador | Método | Descripción |
|--------|------|-------------|--------|-------------|
| `GET` | `/api/v2/sessions` | `SessionController` | `index` | Listar sesiones activas |
| `DELETE` | `/api/v2/sessions/{id}` | `SessionController` | `destroy` | Revocar sesión |
| `GET` | `/api/v2/users` | `UserController` | `index` | Listar usuarios |
| `POST` | `/api/v2/users` | `UserController` | `store` | Crear usuario |
| `GET` | `/api/v2/users/{id}` | `UserController` | `show` | Mostrar usuario |
| `PUT` | `/api/v2/users/{id}` | `UserController` | `update` | Actualizar usuario |
| `DELETE` | `/api/v2/users/{id}` | `UserController` | `destroy` | Eliminar usuario |
| `GET` | `/api/v2/activity-logs` | `ActivityLogController` | `index` | Listar logs de actividad |
| `GET` | `/api/v2/activity-logs/{id}` | `ActivityLogController` | `show` | Mostrar log |

**Nota:** No existe CRUD de roles; solo `GET /api/v2/roles/options` (ver tabla Opciones arriba).

### Extracción de Documentos con IA

| Método | Ruta | Controlador | Método | Descripción |
|--------|------|-------------|--------|-------------|
| `POST` | `/api/v2/document-ai/parse` | `AzureDocumentAIController` | `processPdf` | Procesar PDF con Azure Document AI |

---

## 👥 Rutas para Múltiples Roles

Estas rutas son accesibles para roles: `tecnico`, `administrador`, `direccion`, `administracion`, `comercial`, `operario`.

### Configuración

| Método | Ruta | Controlador | Método | Descripción |
|--------|------|-------------|--------|-------------|
| `GET` | `/api/v2/settings` | `SettingController` | `index` | Obtener configuración |
| `PUT` | `/api/v2/settings` | `SettingController` | `update` | Actualizar configuración |

### Opciones (Dropdowns)

| Método | Ruta | Controlador | Método | Descripción |
|--------|------|-------------|--------|-------------|
| `GET` | `/api/v2/customers/options` | `V2CustomerController` | `options` | Opciones de clientes |
| `GET` | `/api/v2/salespeople/options` | `V2SalespersonController` | `options` | Opciones de vendedores |
| `GET` | `/api/v2/transports/options` | `V2TransportController` | `options` | Opciones de transportes |
| `GET` | `/api/v2/incoterms/options` | `V2IncotermController` | `options` | Opciones de incoterms |
| `GET` | `/api/v2/suppliers/options` | `V2SupplierController` | `options` | Opciones de proveedores |
| `GET` | `/api/v2/species/options` | `V2SpeciesController` | `options` | Opciones de especies |
| `GET` | `/api/v2/products/options` | `V2ProductController` | `options` | Opciones de productos |
| `GET` | `/api/v2/product-categories/options` | `ProductCategoryController` | `options` | Opciones de categorías |
| `GET` | `/api/v2/product-families/options` | `ProductFamilyController` | `options` | Opciones de familias |
| `GET` | `/api/v2/taxes/options` | `TaxController` | `options` | Opciones de impuestos |
| `GET` | `/api/v2/capture-zones/options` | `V2CaptureZoneController` | `options` | Opciones de zonas de captura |
| `GET` | `/api/v2/processes/options` | `V2ProcessController` | `options` | Opciones de procesos |
| `GET` | `/api/v2/pallets/options` | `V2PalletController` | `options` | Opciones de palets |
| `GET` | `/api/v2/pallets/stored-options` | `V2PalletController` | `storedOptions` | Opciones de palets almacenados |
| `GET` | `/api/v2/pallets/shipped-options` | `V2PalletController` | `shippedOptions` | Opciones de palets enviados |
| `GET` | `/api/v2/stores/options` | `V2StoreController` | `options` | Opciones de almacenes |
| `GET` | `/api/v2/orders/options` | `V2OrderController` | `options` | Opciones de pedidos |
| `GET` | `/api/v2/active-orders/options` | `V2OrderController` | `activeOrdersOptions` | Opciones de pedidos activos |
| `GET` | `/api/v2/fishing-gears/options` | `FishingGearController` | `options` | Opciones de artes de pesca |
| `GET` | `/api/v2/countries/options` | `CountryController` | `options` | Opciones de países |
| `GET` | `/api/v2/payment-terms/options` | `V2PaymentTermController` | `options` | Opciones de términos de pago |
| `GET` | `/api/v2/labels/options` | `LabelController` | `options` | Opciones de etiquetas |

### Acciones de Palets

| Método | Ruta | Controlador | Método | Descripción |
|--------|------|-------------|--------|-------------|
| `POST` | `/api/v2/pallets/assign-to-position` | `V2PalletController` | `assignToPosition` | Asignar palet a posición |
| `POST` | `/api/v2/pallets/move-to-store` | `V2PalletController` | `moveToStore` | Mover palet a almacén |
| `POST` | `/api/v2/pallets/{id}/unassign-position` | `V2PalletController` | `unassignPosition` | Desasignar posición |
| `POST` | `/api/v2/pallets/{id}/unlink-order` | `V2PalletController` | `unlinkOrder` | Desvincular pedido |

### Estadísticas

#### Estadísticas de Pedidos

| Método | Ruta | Controlador | Método | Descripción |
|--------|------|-------------|--------|-------------|
| `GET` | `/api/v2/statistics/orders/total-net-weight` | `OrderStatisticsController` | `totalNetWeightStats` | Estadísticas de peso neto total |
| `GET` | `/api/v2/statistics/orders/total-amount` | `OrderStatisticsController` | `totalAmountStats` | Estadísticas de monto total |
| `GET` | `/api/v2/statistics/orders/ranking` | `OrderStatisticsController` | `orderRankingStats` | Ranking de pedidos |
| `GET` | `/api/v2/orders/sales-by-salesperson` | `V2OrderController` | `salesBySalesperson` | Ventas por vendedor |
| `GET` | `/api/v2/orders/sales-chart-data` | `OrderStatisticsController` | `salesChartData` | Datos para gráfico de ventas |
| `GET` | `/api/v2/orders/transport-chart-data` | `V2OrderController` | `transportChartData` | Datos para gráfico de transportes |

#### Estadísticas de Stock

| Método | Ruta | Controlador | Método | Descripción |
|--------|------|-------------|--------|-------------|
| `GET` | `/api/v2/statistics/stock/total` | `StockStatisticsController` | `totalStockStats` | Estadísticas de stock total |
| `GET` | `/api/v2/statistics/stock/total-by-species` | `StockStatisticsController` | `totalStockBySpeciesStats` | Stock total por especie |
| `GET` | `/api/v2/stores/total-stock-by-products` | `V2StoreController` | `totalStockByProducts` | Stock total por productos en almacenes |

#### Estadísticas de Recepciones

| Método | Ruta | Controlador | Método | Descripción |
|--------|------|-------------|--------|-------------|
| `GET` | `/api/v2/raw-material-receptions/reception-chart-data` | `RawMaterialReceptionStatisticsController` | `receptionChartData` | Datos para gráfico de recepciones |

#### Estadísticas de Despachos

| Método | Ruta | Controlador | Método | Descripción |
|--------|------|-------------|--------|-------------|
| `GET` | `/api/v2/cebo-dispatches/dispatch-chart-data` | `CeboDispatchStatisticsController` | `dispatchChartData` | Datos para gráfico de despachos |

### Acciones Especiales de Palets

| Método | Ruta | Controlador | Método | Descripción | Roles |
|--------|------|-------------|--------|-------------|-------|
| `POST` | `/api/v2/pallets/update-state` | `V2PalletController` | `bulkUpdateState` | Actualizar estado masivo de palets | `tecnico,administrador,administracion` |

---

## 📦 CRUD de Entidades

Todas las rutas siguientes siguen el patrón RESTful estándar con `apiResource`.

### Pedidos (Orders)

| Método | Ruta | Controlador | Método | Descripción |
|--------|------|-------------|--------|-------------|
| `GET` | `/api/v2/orders` | `V2OrderController` | `index` | Listar pedidos |
| `POST` | `/api/v2/orders` | `V2OrderController` | `store` | Crear pedido |
| `GET` | `/api/v2/orders/{id}` | `V2OrderController` | `show` | Mostrar pedido |
| `PUT` | `/api/v2/orders/{id}` | `V2OrderController` | `update` | Actualizar pedido |
| `DELETE` | `/api/v2/orders/{id}` | `V2OrderController` | `destroy` | Eliminar pedido |
| `DELETE` | `/api/v2/orders` | `V2OrderController` | `destroyMultiple` | Eliminar múltiples pedidos |
| `PUT` | `/api/v2/orders/{order}/status` | `V2OrderController` | `updateStatus` | Actualizar estado del pedido |

### Detalles Planificados de Pedidos

| Método | Ruta | Controlador | Método | Descripción |
|--------|------|-------------|--------|-------------|
| `GET` | `/api/v2/order-planned-product-details` | `OrderPlannedProductDetailController` | `index` | Listar detalles |
| `POST` | `/api/v2/order-planned-product-details` | `OrderPlannedProductDetailController` | `store` | Crear detalle |
| `GET` | `/api/v2/order-planned-product-details/{id}` | `OrderPlannedProductDetailController` | `show` | Mostrar detalle |
| `PUT` | `/api/v2/order-planned-product-details/{id}` | `OrderPlannedProductDetailController` | `update` | Actualizar detalle |
| `DELETE` | `/api/v2/order-planned-product-details/{id}` | `OrderPlannedProductDetailController` | `destroy` | Eliminar detalle |

### Incidentes de Pedidos

| Método | Ruta | Controlador | Método | Descripción |
|--------|------|-------------|--------|-------------|
| `GET` | `/api/v2/orders/{orderId}/incident` | `IncidentController` | `show` | Mostrar incidencia |
| `POST` | `/api/v2/orders/{orderId}/incident` | `IncidentController` | `store` | Crear incidencia |
| `PUT` | `/api/v2/orders/{orderId}/incident` | `IncidentController` | `update` | Actualizar incidencia |
| `DELETE` | `/api/v2/orders/{orderId}/incident` | `IncidentController` | `destroy` | Eliminar incidencia |

### Recepciones de Materia Prima

| Método | Ruta | Controlador | Método | Descripción |
|--------|------|-------------|--------|-------------|
| `GET` | `/api/v2/raw-material-receptions` | `V2RawMaterialReceptionController` | `index` | Listar recepciones |
| `POST` | `/api/v2/raw-material-receptions` | `V2RawMaterialReceptionController` | `store` | Crear recepción |
| `GET` | `/api/v2/raw-material-receptions/{id}` | `V2RawMaterialReceptionController` | `show` | Mostrar recepción |
| `PUT` | `/api/v2/raw-material-receptions/{id}` | `V2RawMaterialReceptionController` | `update` | Actualizar recepción |
| `DELETE` | `/api/v2/raw-material-receptions/{id}` | `V2RawMaterialReceptionController` | `destroy` | Eliminar recepción |
| `DELETE` | `/api/v2/raw-material-receptions` | `V2RawMaterialReceptionController` | `destroyMultiple` | Eliminar múltiples |

### Despachos de Cebo

| Método | Ruta | Controlador | Método | Descripción |
|--------|------|-------------|--------|-------------|
| `GET` | `/api/v2/cebo-dispatches` | `V2CeboDispatchController` | `index` | Listar despachos |
| `POST` | `/api/v2/cebo-dispatches` | `V2CeboDispatchController` | `store` | Crear despacho |
| `GET` | `/api/v2/cebo-dispatches/{id}` | `V2CeboDispatchController` | `show` | Mostrar despacho |
| `PUT` | `/api/v2/cebo-dispatches/{id}` | `V2CeboDispatchController` | `update` | Actualizar despacho |
| `DELETE` | `/api/v2/cebo-dispatches/{id}` | `V2CeboDispatchController` | `destroy` | Eliminar despacho |
| `DELETE` | `/api/v2/cebo-dispatches` | `V2CeboDispatchController` | `destroyMultiple` | Eliminar múltiples |

### Transportes

| Método | Ruta | Controlador | Método | Descripción |
|--------|------|-------------|--------|-------------|
| `GET` | `/api/v2/transports` | `V2TransportController` | `index` | Listar transportes |
| `POST` | `/api/v2/transports` | `V2TransportController` | `store` | Crear transporte |
| `GET` | `/api/v2/transports/{id}` | `V2TransportController` | `show` | Mostrar transporte |
| `PUT` | `/api/v2/transports/{id}` | `V2TransportController` | `update` | Actualizar transporte |
| `DELETE` | `/api/v2/transports/{id}` | `V2TransportController` | `destroy` | Eliminar transporte |
| `DELETE` | `/api/v2/transports` | `V2TransportController` | `destroyMultiple` | Eliminar múltiples |

### Productos

| Método | Ruta | Controlador | Método | Descripción |
|--------|------|-------------|--------|-------------|
| `GET` | `/api/v2/products` | `V2ProductController` | `index` | Listar productos |
| `POST` | `/api/v2/products` | `V2ProductController` | `store` | Crear producto |
| `GET` | `/api/v2/products/{id}` | `V2ProductController` | `show` | Mostrar producto |
| `PUT` | `/api/v2/products/{id}` | `V2ProductController` | `update` | Actualizar producto |
| `DELETE` | `/api/v2/products/{id}` | `V2ProductController` | `destroy` | Eliminar producto |
| `DELETE` | `/api/v2/products` | `V2ProductController` | `destroyMultiple` | Eliminar múltiples |

### Categorías de Productos

| Método | Ruta | Controlador | Método | Descripción |
|--------|------|-------------|--------|-------------|
| `GET` | `/api/v2/product-categories` | `ProductCategoryController` | `index` | Listar categorías |
| `POST` | `/api/v2/product-categories` | `ProductCategoryController` | `store` | Crear categoría |
| `GET` | `/api/v2/product-categories/{id}` | `ProductCategoryController` | `show` | Mostrar categoría |
| `PUT` | `/api/v2/product-categories/{id}` | `ProductCategoryController` | `update` | Actualizar categoría |
| `DELETE` | `/api/v2/product-categories/{id}` | `ProductCategoryController` | `destroy` | Eliminar categoría |
| `DELETE` | `/api/v2/product-categories` | `ProductCategoryController` | `destroyMultiple` | Eliminar múltiples |

### Familias de Productos

| Método | Ruta | Controlador | Método | Descripción |
|--------|------|-------------|--------|-------------|
| `GET` | `/api/v2/product-families` | `ProductFamilyController` | `index` | Listar familias |
| `POST` | `/api/v2/product-families` | `ProductFamilyController` | `store` | Crear familia |
| `GET` | `/api/v2/product-families/{id}` | `ProductFamilyController` | `show` | Mostrar familia |
| `PUT` | `/api/v2/product-families/{id}` | `ProductFamilyController` | `update` | Actualizar familia |
| `DELETE` | `/api/v2/product-families/{id}` | `ProductFamilyController` | `destroy` | Eliminar familia |
| `DELETE` | `/api/v2/product-families` | `ProductFamilyController` | `destroyMultiple` | Eliminar múltiples |

### Almacenes (Stores)

| Método | Ruta | Controlador | Método | Descripción | Roles |
|--------|------|-------------|--------|-------------|-------|
| `GET` | `/api/v2/stores` | `V2StoreController` | `index` | Listar almacenes | Todos |
| `POST` | `/api/v2/stores` | `V2StoreController` | `store` | Crear almacén | Todos |
| `GET` | `/api/v2/stores/{id}` | `V2StoreController` | `show` | Mostrar almacén | Todos |
| `PUT` | `/api/v2/stores/{id}` | `V2StoreController` | `update` | Actualizar almacén | Todos |
| `DELETE` | `/api/v2/stores/{id}` | `V2StoreController` | `destroy` | Eliminar almacén | Todos |
| `DELETE` | `/api/v2/stores` | `V2StoreController` | `deleteMultiple` | Eliminar múltiples | `tecnico,administrador,administracion` |

### Términos de Pago

| Método | Ruta | Controlador | Método | Descripción |
|--------|------|-------------|--------|-------------|
| `GET` | `/api/v2/payment-terms` | `V2PaymentTermController` | `index` | Listar términos |
| `POST` | `/api/v2/payment-terms` | `V2PaymentTermController` | `store` | Crear término |
| `GET` | `/api/v2/payment-terms/{id}` | `V2PaymentTermController` | `show` | Mostrar término |
| `PUT` | `/api/v2/payment-terms/{id}` | `V2PaymentTermController` | `update` | Actualizar término |
| `DELETE` | `/api/v2/payment-terms/{id}` | `V2PaymentTermController` | `destroy` | Eliminar término |
| `DELETE` | `/api/v2/payment-terms` | `V2PaymentTermController` | `destroyMultiple` | Eliminar múltiples |

### Países

| Método | Ruta | Controlador | Método | Descripción |
|--------|------|-------------|--------|-------------|
| `GET` | `/api/v2/countries` | `CountryController` | `index` | Listar países |
| `POST` | `/api/v2/countries` | `CountryController` | `store` | Crear país |
| `GET` | `/api/v2/countries/{id}` | `CountryController` | `show` | Mostrar país |
| `PUT` | `/api/v2/countries/{id}` | `CountryController` | `update` | Actualizar país |
| `DELETE` | `/api/v2/countries/{id}` | `CountryController` | `destroy` | Eliminar país |
| `DELETE` | `/api/v2/countries` | `CountryController` | `destroyMultiple` | Eliminar múltiples |

### Cajas (Boxes)

| Método | Ruta | Controlador | Método | Descripción |
|--------|------|-------------|--------|-------------|
| `GET` | `/api/v2/boxes` | `BoxesController` | `index` | Listar cajas |
| `POST` | `/api/v2/boxes` | `BoxesController` | `store` | Crear caja |
| `GET` | `/api/v2/boxes/{id}` | `BoxesController` | `show` | Mostrar caja |
| `PUT` | `/api/v2/boxes/{id}` | `BoxesController` | `update` | Actualizar caja |
| `DELETE` | `/api/v2/boxes/{id}` | `BoxesController` | `destroy` | Eliminar caja |
| `DELETE` | `/api/v2/boxes` | `BoxesController` | `destroyMultiple` | Eliminar múltiples |

### Palets

| Método | Ruta | Controlador | Método | Descripción | Roles |
|--------|------|-------------|--------|-------------|-------|
| `GET` | `/api/v2/pallets` | `V2PalletController` | `index` | Listar palets | Todos |
| `POST` | `/api/v2/pallets` | `V2PalletController` | `store` | Crear palet | Todos |
| `GET` | `/api/v2/pallets/{id}` | `V2PalletController` | `show` | Mostrar palet | Todos |
| `GET` | `/api/v2/pallets/{id}/timeline` | `V2PalletController` | `timeline` | Timeline de modificaciones (F-01) | Todos |
| `PUT` | `/api/v2/pallets/{id}` | `V2PalletController` | `update` | Actualizar palet | Todos |
| `DELETE` | `/api/v2/pallets/{id}` | `V2PalletController` | `destroy` | Eliminar palet | Todos |
| `DELETE` | `/api/v2/pallets` | `V2PalletController` | `destroyMultiple` | Eliminar múltiples | `tecnico,administrador,administracion` |

### Clientes

| Método | Ruta | Controlador | Método | Descripción |
|--------|------|-------------|--------|-------------|
| `GET` | `/api/v2/customers` | `V2CustomerController` | `index` | Listar clientes |
| `POST` | `/api/v2/customers` | `V2CustomerController` | `store` | Crear cliente |
| `GET` | `/api/v2/customers/{id}` | `V2CustomerController` | `show` | Mostrar cliente |
| `PUT` | `/api/v2/customers/{id}` | `V2CustomerController` | `update` | Actualizar cliente |
| `DELETE` | `/api/v2/customers/{id}` | `V2CustomerController` | `destroy` | Eliminar cliente |
| `DELETE` | `/api/v2/customers` | `V2CustomerController` | `destroyMultiple` | Eliminar múltiples |

### Proveedores

| Método | Ruta | Controlador | Método | Descripción |
|--------|------|-------------|--------|-------------|
| `GET` | `/api/v2/suppliers` | `V2SupplierController` | `index` | Listar proveedores |
| `POST` | `/api/v2/suppliers` | `V2SupplierController` | `store` | Crear proveedor |
| `GET` | `/api/v2/suppliers/{id}` | `V2SupplierController` | `show` | Mostrar proveedor |
| `PUT` | `/api/v2/suppliers/{id}` | `V2SupplierController` | `update` | Actualizar proveedor |
| `DELETE` | `/api/v2/suppliers/{id}` | `V2SupplierController` | `destroy` | Eliminar proveedor |
| `DELETE` | `/api/v2/suppliers` | `V2SupplierController` | `destroyMultiple` | Eliminar múltiples |

### Zonas de Captura

| Método | Ruta | Controlador | Método | Descripción |
|--------|------|-------------|--------|-------------|
| `GET` | `/api/v2/capture-zones` | `V2CaptureZoneController` | `index` | Listar zonas |
| `POST` | `/api/v2/capture-zones` | `V2CaptureZoneController` | `store` | Crear zona |
| `GET` | `/api/v2/capture-zones/{id}` | `V2CaptureZoneController` | `show` | Mostrar zona |
| `PUT` | `/api/v2/capture-zones/{id}` | `V2CaptureZoneController` | `update` | Actualizar zona |
| `DELETE` | `/api/v2/capture-zones/{id}` | `V2CaptureZoneController` | `destroy` | Eliminar zona |
| `DELETE` | `/api/v2/capture-zones` | `V2CaptureZoneController` | `destroyMultiple` | Eliminar múltiples |

### Especies

| Método | Ruta | Controlador | Método | Descripción |
|--------|------|-------------|--------|-------------|
| `GET` | `/api/v2/species` | `V2SpeciesController` | `index` | Listar especies |
| `POST` | `/api/v2/species` | `V2SpeciesController` | `store` | Crear especie |
| `GET` | `/api/v2/species/{id}` | `V2SpeciesController` | `show` | Mostrar especie |
| `PUT` | `/api/v2/species/{id}` | `V2SpeciesController` | `update` | Actualizar especie |
| `DELETE` | `/api/v2/species/{id}` | `V2SpeciesController` | `destroy` | Eliminar especie |
| `DELETE` | `/api/v2/species` | `V2SpeciesController` | `destroyMultiple` | Eliminar múltiples |

### Incoterms

| Método | Ruta | Controlador | Método | Descripción |
|--------|------|-------------|--------|-------------|
| `GET` | `/api/v2/incoterms` | `V2IncotermController` | `index` | Listar incoterms |
| `POST` | `/api/v2/incoterms` | `V2IncotermController` | `store` | Crear incoterm |
| `GET` | `/api/v2/incoterms/{id}` | `V2IncotermController` | `show` | Mostrar incoterm |
| `PUT` | `/api/v2/incoterms/{id}` | `V2IncotermController` | `update` | Actualizar incoterm |
| `DELETE` | `/api/v2/incoterms/{id}` | `V2IncotermController` | `destroy` | Eliminar incoterm |
| `DELETE` | `/api/v2/incoterms` | `V2IncotermController` | `destroyMultiple` | Eliminar múltiples |

### Vendedores

| Método | Ruta | Controlador | Método | Descripción |
|--------|------|-------------|--------|-------------|
| `GET` | `/api/v2/salespeople` | `V2SalespersonController` | `index` | Listar vendedores |
| `POST` | `/api/v2/salespeople` | `V2SalespersonController` | `store` | Crear vendedor |
| `GET` | `/api/v2/salespeople/{id}` | `V2SalespersonController` | `show` | Mostrar vendedor |
| `PUT` | `/api/v2/salespeople/{id}` | `V2SalespersonController` | `update` | Actualizar vendedor |
| `DELETE` | `/api/v2/salespeople/{id}` | `V2SalespersonController` | `destroy` | Eliminar vendedor |
| `DELETE` | `/api/v2/salespeople` | `V2SalespersonController` | `destroyMultiple` | Eliminar múltiples |

### Artes de Pesca

| Método | Ruta | Controlador | Método | Descripción |
|--------|------|-------------|--------|-------------|
| `GET` | `/api/v2/fishing-gears` | `FishingGearController` | `index` | Listar artes |
| `POST` | `/api/v2/fishing-gears` | `FishingGearController` | `store` | Crear arte |
| `GET` | `/api/v2/fishing-gears/{id}` | `FishingGearController` | `show` | Mostrar arte |
| `PUT` | `/api/v2/fishing-gears/{id}` | `FishingGearController` | `update` | Actualizar arte |
| `DELETE` | `/api/v2/fishing-gears/{id}` | `FishingGearController` | `destroy` | Eliminar arte |
| `DELETE` | `/api/v2/fishing-gears` | `FishingGearController` | `destroyMultiple` | Eliminar múltiples |

### Etiquetas

| Método | Ruta | Controlador | Método | Descripción |
|--------|------|-------------|--------|-------------|
| `GET` | `/api/v2/labels` | `LabelController` | `index` | Listar etiquetas |
| `POST` | `/api/v2/labels` | `LabelController` | `store` | Crear etiqueta |
| `GET` | `/api/v2/labels/{id}` | `LabelController` | `show` | Mostrar etiqueta |
| `PUT` | `/api/v2/labels/{id}` | `LabelController` | `update` | Actualizar etiqueta |
| `DELETE` | `/api/v2/labels/{id}` | `LabelController` | `destroy` | Eliminar etiqueta |

### Producción

#### Lotes de Producción

| Método | Ruta | Controlador | Método | Descripción |
|--------|------|-------------|--------|-------------|
| `GET` | `/api/v2/productions` | `V2ProductionController` | `index` | Listar lotes |
| `POST` | `/api/v2/productions` | `V2ProductionController` | `store` | Crear lote |
| `GET` | `/api/v2/productions/{id}` | `V2ProductionController` | `show` | Mostrar lote |
| `PUT` | `/api/v2/productions/{id}` | `V2ProductionController` | `update` | Actualizar lote |
| `DELETE` | `/api/v2/productions/{id}` | `V2ProductionController` | `destroy` | Eliminar lote |
| `GET` | `/api/v2/productions/{id}/diagram` | `V2ProductionController` | `getDiagram` | Obtener diagrama |
| `GET` | `/api/v2/productions/{id}/process-tree` | `V2ProductionController` | `getProcessTree` | Obtener árbol de procesos |
| `GET` | `/api/v2/productions/{id}/totals` | `V2ProductionController` | `getTotals` | Obtener totales |
| `GET` | `/api/v2/productions/{id}/reconciliation` | `V2ProductionController` | `getReconciliation` | Obtener reconciliación |

#### Registros de Producción

| Método | Ruta | Controlador | Método | Descripción |
|--------|------|-------------|--------|-------------|
| `GET` | `/api/v2/production-records` | `ProductionRecordController` | `index` | Listar registros |
| `POST` | `/api/v2/production-records` | `ProductionRecordController` | `store` | Crear registro |
| `GET` | `/api/v2/production-records/{id}` | `ProductionRecordController` | `show` | Mostrar registro |
| `PUT` | `/api/v2/production-records/{id}` | `ProductionRecordController` | `update` | Actualizar registro |
| `DELETE` | `/api/v2/production-records/{id}` | `ProductionRecordController` | `destroy` | Eliminar registro |
| `GET` | `/api/v2/production-records/{id}/tree` | `ProductionRecordController` | `tree` | Obtener árbol |
| `POST` | `/api/v2/production-records/{id}/finish` | `ProductionRecordController` | `finish` | Finalizar proceso |

#### Entradas de Producción

| Método | Ruta | Controlador | Método | Descripción |
|--------|------|-------------|--------|-------------|
| `GET` | `/api/v2/production-inputs` | `ProductionInputController` | `index` | Listar entradas |
| `POST` | `/api/v2/production-inputs` | `ProductionInputController` | `store` | Crear entrada |
| `POST` | `/api/v2/production-inputs/multiple` | `ProductionInputController` | `storeMultiple` | Crear múltiples entradas |
| `GET` | `/api/v2/production-inputs/{id}` | `ProductionInputController` | `show` | Mostrar entrada |
| `PUT` | `/api/v2/production-inputs/{id}` | `ProductionInputController` | `update` | Actualizar entrada |
| `DELETE` | `/api/v2/production-inputs/{id}` | `ProductionInputController` | `destroy` | Eliminar entrada |

#### Salidas de Producción

| Método | Ruta | Controlador | Método | Descripción |
|--------|------|-------------|--------|-------------|
| `GET` | `/api/v2/production-outputs` | `ProductionOutputController` | `index` | Listar salidas |
| `POST` | `/api/v2/production-outputs` | `ProductionOutputController` | `store` | Crear salida |
| `GET` | `/api/v2/production-outputs/{id}` | `ProductionOutputController` | `show` | Mostrar salida |
| `PUT` | `/api/v2/production-outputs/{id}` | `ProductionOutputController` | `update` | Actualizar salida |
| `DELETE` | `/api/v2/production-outputs/{id}` | `ProductionOutputController` | `destroy` | Eliminar salida |

#### Procesos

| Método | Ruta | Controlador | Método | Descripción |
|--------|------|-------------|--------|-------------|
| `GET` | `/api/v2/processes` | `V2ProcessController` | `index` | Listar procesos |
| `POST` | `/api/v2/processes` | `V2ProcessController` | `store` | Crear proceso |
| `GET` | `/api/v2/processes/{id}` | `V2ProcessController` | `show` | Mostrar proceso |
| `PUT` | `/api/v2/processes/{id}` | `V2ProcessController` | `update` | Actualizar proceso |
| `DELETE` | `/api/v2/processes/{id}` | `V2ProcessController` | `destroy` | Eliminar proceso |

---

## 📄 Generación de PDFs

Todas las rutas de PDF retornan archivos PDF para descarga directa.

### PDFs de Pedidos

| Método | Ruta | Controlador | Método | Descripción |
|--------|------|-------------|--------|-------------|
| `GET` | `/api/v2/orders/{orderId}/pdf/order-sheet` | `PDFController` | `generateOrderSheet` | Hoja de pedido |
| `GET` | `/api/v2/orders/{orderId}/pdf/order-signs` | `PDFController` | `generateOrderSigns` | Letreros de transporte |
| `GET` | `/api/v2/orders/{orderId}/pdf/order-packing-list` | `PDFController` | `generateOrderPackingList` | Lista de empaque |
| `GET` | `/api/v2/orders/{orderId}/pdf/loading-note` | `PDFController` | `generateLoadingNote` | Nota de carga |
| `GET` | `/api/v2/orders/{orderId}/pdf/restricted-loading-note` | `PDFController` | `generateRestrictedLoadingNote` | Nota de carga restringida |
| `GET` | `/api/v2/orders/{orderId}/pdf/order-cmr` | `PDFController` | `generateOrderCMR` | Documento CMR |
| `GET` | `/api/v2/orders/{orderId}/pdf/valued-loading-note` | `PDFController` | `generateValuedLoadingNote` | Nota de carga valorada |
| `GET` | `/api/v2/orders/{orderId}/pdf/incident` | `PDFController` | `generateIncident` | Documento de incidencia |
| `GET` | `/api/v2/orders/{orderId}/pdf/order-confirmation` | `PDFController` | `generateOrderConfirmation` | Confirmación de pedido |
| `GET` | `/api/v2/orders/{orderId}/pdf/transport-pickup-request` | `PDFController` | `generateTransportPickupRequest` | Solicitud de recogida |

### Envío de Documentos por Email

| Método | Ruta | Controlador | Método | Descripción |
|--------|------|-------------|--------|-------------|
| `POST` | `/api/v2/orders/{orderId}/send-custom-documents` | `OrderDocumentController` | `sendCustomDocumentation` | Enviar documentos personalizados |
| `POST` | `/api/v2/orders/{orderId}/send-standard-documents` | `OrderDocumentController` | `sendStandardDocumentation` | Enviar documentos estándar |

---

## 📊 Exportaciones Excel

Todas las rutas de Excel retornan archivos Excel para descarga directa.

### Exportaciones de Pedidos

| Método | Ruta | Controlador | Método | Descripción |
|--------|------|-------------|--------|-------------|
| `GET` | `/api/v2/orders/{orderId}/xlsx/lots-report` | `ExcelController` | `exportProductLotDetails` | Detalles de lotes |
| `GET` | `/api/v2/orders/{orderId}/xlsx/boxes-report` | `ExcelController` | `exportBoxList` | Lista de cajas |
| `GET` | `/api/v2/orders/{orderId}/xls/A3ERP-sales-delivery-note` | `ExcelController` | `exportA3ERPOrderSalesDeliveryNote` | Albarán A3ERP individual |
| `GET` | `/api/v2/orders/xls/A3ERP-sales-delivery-note-filtered` | `ExcelController` | `exportA3ERPOrderSalesDeliveryNoteWithFilters` | Albaranes A3ERP filtrados |
| `GET` | `/api/v2/orders/{orderId}/xls/A3ERP2-sales-delivery-note` | `ExcelController` | `exportA3ERP2OrderSalesDeliveryNote` | Albarán A3ERP2 individual |
| `GET` | `/api/v2/orders/xls/A3ERP2-sales-delivery-note-filtered` | `ExcelController` | `exportA3ERP2OrderSalesDeliveryNoteWithFilters` | Albaranes A3ERP2 filtrados |
| `GET` | `/api/v2/orders/xls/facilcom-sales-delivery-note` | `ExcelController` | `exportFacilcomOrderSalesDeliveryNoteWithFilters` | Albaranes Facilcom filtrados |
| `GET` | `/api/v2/orders/{orderId}/xls/facilcom-single` | `ExcelController` | `exportFacilcomSingleOrder` | Albarán Facilcom individual |
| `GET` | `/api/v2/orders/xlsx/active-planned-products` | `ExcelController` | `exportActiveOrderPlannedProducts` | Productos planificados activos |

### Exportaciones de Recepciones

| Método | Ruta | Controlador | Método | Descripción |
|--------|------|-------------|--------|-------------|
| `GET` | `/api/v2/raw-material-receptions/facilcom-xls` | `ExcelController` | `exportRawMaterialReceptionFacilcom` | Recepciones Facilcom |
| `GET` | `/api/v2/raw-material-receptions/a3erp-xls` | `ExcelController` | `exportRawMaterialReceptionA3erp` | Recepciones A3ERP |

### Exportaciones de Despachos

| Método | Ruta | Controlador | Método | Descripción |
|--------|------|-------------|--------|-------------|
| `GET` | `/api/v2/cebo-dispatches/facilcom-xlsx` | `ExcelController` | `exportCeboDispatchFacilcom` | Despachos Facilcom |
| `GET` | `/api/v2/cebo-dispatches/a3erp-xlsx` | `ExcelController` | `exportCeboDispatchA3erp` | Despachos A3ERP |
| `GET` | `/api/v2/cebo-dispatches/a3erp2-xlsx` | `ExcelController` | `exportCeboDispatchA3erp2` | Despachos A3ERP2 |

### Exportaciones de Cajas

| Método | Ruta | Controlador | Método | Descripción |
|--------|------|-------------|--------|-------------|
| `GET` | `/api/v2/boxes/xlsx` | `ExcelController` | `exportBoxesReport` | Reporte completo de cajas |

---

## 🔑 Notas sobre Autorización

### Roles del Sistema

1. **tecnico**: Acceso completo, gestión de usuarios, sesiones, logs y opciones de roles
2. **administrador**: Superuser de la empresa
3. **direccion**: Solo lectura y análisis (sin rutas específicas aún)
4. **administracion**: Administración
5. **comercial**: Comercial
6. **operario**: Operario, acceso a operaciones diarias

### Middleware de Roles

- `role:tecnico`: Solo técnicos
- `role:tecnico,administrador,administracion`: Roles administrativos
- `role:tecnico,administrador,direccion,administracion,comercial,operario`: Todos los roles autenticados

### Acciones Especiales

Algunas acciones requieren roles específicos:
- Eliminación masiva de almacenes: `tecnico,administrador,administracion`
- Eliminación masiva de palets: `tecnico,administrador,administracion`
- Actualización masiva de estado de palets: `tecnico,administrador,administracion`

---

## 📝 Observaciones Importantes

1. **Todas las rutas requieren el header `X-Tenant`**: Esto es obligatorio debido al middleware `tenant` aplicado globalmente.

2. **Orden de las rutas es importante**: Algunas rutas personalizadas deben definirse antes de los `apiResource` para evitar conflictos (ej: exportaciones de recepciones y despachos).

3. **Rutas comentadas**: Algunas rutas están comentadas en el código (ej: `pdf-extractor`, `document-ai/parse` de Google). Solo están documentadas las rutas activas.

4. **Rutas v1**: Las rutas v1 no están documentadas aquí ya que están obsoletas. Este documento solo cubre v2.

---

## 🔗 Referencias Cruzadas

Para información detallada sobre cada módulo y sus endpoints:

- **Autenticación**: [Fundamentos - Autenticación y Autorización](../fundamentos/02-Autenticacion-Autorizacion.md)
- **Pedidos**: [Pedidos - General](../pedidos/20-Pedidos-General.md)
- **Producción**: [Producción - General](../produccion/10-Produccion-General.md)
- **Inventario**: [Inventario - Almacenes](../inventario/30-Almacenes.md)
- **Utilidades**: [Utilidades - Generación PDF](../utilidades/90-Generacion-PDF.md), [Utilidades - Exportación Excel](../utilidades/91-Exportacion-Excel.md)

