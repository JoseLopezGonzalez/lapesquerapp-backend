# Referencia Técnica - Modelos Eloquent

## ⚠️ Estado de la API
- **v1**: Obsoleta (no documentada)
- **v2**: Versión activa (este documento)

---

## 📋 Visión General

Este documento proporciona una referencia completa de todos los modelos Eloquent del sistema v2. Los modelos están organizados por módulos funcionales para facilitar la navegación.

**Características Comunes**:
- La mayoría de los modelos usan el trait `UsesTenantConnection` para multi-tenancy
- Todos los modelos extienden `Illuminate\Database\Eloquent\Model`
- Algunos modelos especiales (ej: `User`, `Tenant`) extienden clases diferentes

---

## 🗂️ Organización por Módulos

1. [Sistema y Autenticación](#sistema-y-autenticación)
2. [Producción](#producción)
3. [Pedidos](#pedidos)
4. [Inventario y Almacén](#inventario-y-almacén)
5. [Catálogos y Maestros](#catálogos-y-maestros)
6. [Recepciones y Despachos](#recepciones-y-despachos)
7. [Etiquetas](#etiquetas)
8. [Modelos Auxiliares](#modelos-auxiliares)

---

## 🔐 Sistema y Autenticación

### User

**Archivo**: `app/Models/User.php`

**Extiende**: `Illuminate\Foundation\Auth\User`

**Traits**:
- `UsesTenantConnection`
- `HasApiTokens` (Sanctum)
- `HasFactory`
- `Notifiable`

**Fillable**:
- `name`, `email`, `active`, `role`, `assigned_store_id`, `company_name`, `company_logo_url`  
  (no hay campo `password`; el acceso es por magic link u OTP)

**Atributo**:
- `role`: string — Rol del usuario (valor de `App\Enums\Role`)

**Relaciones**:
- `activityLogs()`: HasMany → `ActivityLog`

**Métodos Especiales**:
- `hasRole($role)`: Verifica si tiene el rol (string o array de strings)
- `hasAnyRole(array $roles)`: Verifica si tiene alguno de los roles

**Documentación Completa**: [Sistema - Usuarios](../sistema/80-Usuarios.md)

---

### Role (enum, no modelo)

**Archivo**: `app/Enums/Role.php`

Los roles están fijados en código (enum). No existe modelo `Role` ni tabla `roles`. Valores: `tecnico`, `administrador`, `direccion`, `administracion`, `comercial`, `operario`. Ver [Sistema - Roles](../sistema/81-Roles.md).

**Documentación Completa**: [Sistema - Roles](../sistema/81-Roles.md)

---

### Tenant

**Archivo**: `app/Models/Tenant.php`

**Traits**: Ninguno (base de datos central)

**Fillable**:
- `name`, `subdomain`, `database`, `active`, `branding_image_url`

**Casts**:
- `active` → `boolean`

**Nota**: Este modelo NO usa `UsesTenantConnection` porque está en la base de datos central.

**Documentación Completa**: [Fundamentos - Arquitectura Multi-Tenant](../fundamentos/01-Arquitectura-Multi-Tenant.md)

---

### ActivityLog

**Archivo**: `app/Models/ActivityLog.php`

**Traits**:
- `UsesTenantConnection`
- `HasFactory`

**Relaciones**:
- `user()`: BelongsTo → `User`

**Documentación Completa**: [Sistema - Logs de Actividad](../sistema/83-Logs-Actividad.md)

---

## 🏭 Producción

### Production

**Archivo**: `app/Models/Production.php`

**Traits**:
- `UsesTenantConnection`
- `HasFactory`

**Soft Deletes**: Sí

**Fillable**:
- `lot`, `species_id`, `capture_zone_id`, `notes`, `opened_at`, `closed_at`, `date`

**Relaciones**:
- `species()`: BelongsTo → `Species`
- `captureZone()`: BelongsTo → `CaptureZone`
- `records()`: HasMany → `ProductionRecord`

**Métodos Especiales**:
- `isOpen()`: Verifica si está abierto
- `isClosed()`: Verifica si está cerrado
- `open()`: Abre el lote
- `close()`: Cierra el lote
- `getDiagramData()`: Retorna datos del diagrama
- `buildProcessTree()`: Construye árbol de procesos
- `calculateGlobalTotals()`: Calcula totales globales
- `reconcile()`: Reconciliación

**Documentación Completa**: [Producción - Lotes](../produccion/11-Produccion-Lotes.md)

---

### ProductionRecord

**Archivo**: `app/Models/ProductionRecord.php`

**Traits**:
- `UsesTenantConnection`
- `HasFactory`

**Fillable**:
- `production_id`, `process_id`, `parent_id`, `status`, `started_at`, `finished_at`, `notes`

**Relaciones**:
- `production()`: BelongsTo → `Production`
- `process()`: BelongsTo → `Process`
- `parent()`: BelongsTo → `ProductionRecord` (self)
- `children()`: HasMany → `ProductionRecord` (self)
- `inputs()`: HasMany → `ProductionInput`
- `outputs()`: HasMany → `ProductionOutput`

**Métodos Especiales**:
- `isPending()`: Verifica si está pendiente
- `isInProgress()`: Verifica si está en progreso
- `isFinished()`: Verifica si está finalizado
- `finish()`: Finaliza el proceso
- `buildTree()`: Construye árbol de procesos

**Documentación Completa**: [Producción - Procesos](../produccion/12-Produccion-Procesos.md)

---

### ProductionInput

**Archivo**: `app/Models/ProductionInput.php`

**Traits**:
- `UsesTenantConnection`
- `HasFactory`

**Fillable**:
- `production_record_id`, `box_id`, `quantity`, `net_weight`, `notes`

**Relaciones**:
- `productionRecord()`: BelongsTo → `ProductionRecord`
- `box()`: BelongsTo → `Box`

**Documentación Completa**: [Producción - Entradas](../produccion/13-Produccion-Entradas.md)

---

### ProductionOutput

**Archivo**: `app/Models/ProductionOutput.php`

**Traits**:
- `UsesTenantConnection`
- `HasFactory`

**Fillable**:
- `production_record_id`, `product_id`, `quantity`, `net_weight`, `notes`

**Relaciones**:
- `productionRecord()`: BelongsTo → `ProductionRecord`
- `product()`: BelongsTo → `Product`

**Métodos Especiales**:
- `getAverageWeightPerBoxAttribute()`: Calcula peso promedio por caja

**Documentación Completa**: [Producción - Salidas](../produccion/14-Produccion-Salidas.md)

---

## 📦 Pedidos

### Order

**Archivo**: `app/Models/Order.php`

**Traits**:
- `UsesTenantConnection`
- `HasFactory`

**Fillable**:
- `customer_id`, `payment_term_id`, `billing_address`, `shipping_address`, `transportation_notes`, `production_notes`, `accounting_notes`, `salesperson_id`, `emails`, `transport_id`, `entry_date`, `load_date`, `status`, `buyer_reference`, `incoterm_id`

**Relaciones**:
- `customer()`: BelongsTo → `Customer`
- `salesperson()`: BelongsTo → `Salesperson`
- `transport()`: BelongsTo → `Transport`
- `payment_term()`: BelongsTo → `PaymentTerm`
- `incoterm()`: BelongsTo → `Incoterm`
- `plannedProductDetails()`: HasMany → `OrderPlannedProductDetail`
- `pallets()`: HasMany → `Pallet`
- `incident()`: HasOne → `Incident`

**Métodos Especiales**:
- `getFormattedIdAttribute()`: Retorna ID formateado (ej: `#00123`)
- `isActive()`: Verifica si el pedido está activo

**Documentación Completa**: [Pedidos - General](../pedidos/20-Pedidos-General.md)

---

### OrderPlannedProductDetail

**Archivo**: `app/Models/OrderPlannedProductDetail.php`

**Traits**:
- `UsesTenantConnection`
- `HasFactory`

**Fillable**:
- `order_id`, `product_id`, `tax_id`, `boxes`, `net_weight`, `unit_price`, `notes`

**Relaciones**:
- `order()`: BelongsTo → `Order`
- `product()`: BelongsTo → `Product`
- `tax()`: BelongsTo → `Tax`

**Documentación Completa**: [Pedidos - Detalles Planificados](../pedidos/21-Pedidos-Detalles-Planificados.md)

---

### Incident

**Archivo**: `app/Models/Incident.php`

**Traits**:
- `UsesTenantConnection`
- `HasFactory`

**Fillable**:
- `order_id`, `status`, `resolution_type`, `notes`

**Relaciones**:
- `order()`: BelongsTo → `Order`

**Métodos Especiales**:
- `isOpen()`: Verifica si está abierto
- `isResolved()`: Verifica si está resuelto

**Documentación Completa**: [Pedidos - Incidentes](../pedidos/23-Pedidos-Incidentes.md)

---

### OrderPallet

**Archivo**: `app/Models/OrderPallet.php`

**Traits**:
- `UsesTenantConnection`
- `HasFactory`

**Nota**: Tabla pivot/intermedia entre `Order` y `Pallet`.

---

## 📊 Inventario y Almacén

### Store

**Archivo**: `app/Models/Store.php`

**Traits**:
- `UsesTenantConnection`
- `HasFactory`

**Fillable**:
- `name`, `map_data`, `notes`

**Relaciones**:
- `storedPallets()`: HasMany → `StoredPallet`

**Métodos Especiales**:
- `getTotalNetWeightAttribute()`: Calcula peso neto total
- `getTotalGrossWeightAttribute()`: Calcula peso bruto total
- `getDefaultMap()`: Retorna mapa por defecto (JSON)

**Documentación Completa**: [Inventario - Almacenes](../inventario/30-Almacenes.md)

---

### Pallet

**Archivo**: `app/Models/Pallet.php`

**Traits**:
- `UsesTenantConnection`
- `HasFactory`

**Fillable**:
- `state_id`, `order_id`, `observations`

**Relaciones**:
- `state()`: BelongsTo → `PalletState`
- `order()`: BelongsTo → `Order`
- `boxes()`: BelongsToMany → `Box` (through `PalletBox`)
- `palletBoxes()`: HasMany → `PalletBox`
- `storedPallet()`: HasOne → `StoredPallet`

**Métodos Especiales**:
- `getTotalNetWeightAttribute()`: Calcula peso neto total
- `getTotalGrossWeightAttribute()`: Calcula peso bruto total
- `getBoxCountAttribute()`: Cuenta cajas
- `getIsAvailableAttribute()`: Verifica disponibilidad

**Documentación Completa**: [Inventario - Palets](../inventario/31-Palets.md)

---

### PalletBox

**Archivo**: `app/Models/PalletBox.php`

**Traits**:
- `UsesTenantConnection`
- `HasFactory`

**Nota**: Tabla pivot/intermedia entre `Pallet` y `Box`.

**Relaciones**:
- `pallet()`: BelongsTo → `Pallet`
- `box()`: BelongsTo → `Box`

---

### Box

**Archivo**: `app/Models/Box.php`

**Traits**:
- `UsesTenantConnection`
- `HasFactory`

**Fillable**:
- `article_id`, `lot`, `net_weight`, `gross_weight`, `gs1_128`

**Relaciones**:
- `product()`: BelongsTo → `Product` (through `Article`)
- `palletBox()`: HasOne → `PalletBox`
- `productionInputs()`: HasMany → `ProductionInput`

**Métodos Especiales**:
- `getIsAvailableAttribute()`: Verifica disponibilidad
- `getProductionAttribute()`: Obtiene producción relacionada

**Documentación Completa**: [Inventario - Cajas](../inventario/32-Cajas.md)

---

### StoredPallet

**Archivo**: `app/Models/StoredPallet.php`

**Traits**:
- `UsesTenantConnection`
- `HasFactory`

**Fillable**:
- `pallet_id`, `store_id`, `position`

**Relaciones**:
- `pallet()`: BelongsTo → `Pallet`
- `store()`: BelongsTo → `Store`

---

### PalletState

**Archivo**: `app/Models/PalletState.php`

**Traits**:
- `UsesTenantConnection`
- `HasFactory`

**Nota**: Tabla maestra de estados de palet (1: Pendiente, 2: Almacenado, 3: Enviado).

---

### StoredBox

**Archivo**: `app/Models/StoredBox.php`

**Traits**:
- `UsesTenantConnection`
- `HasFactory`

**Nota**: Modelo para almacenamiento de cajas (puede no estar en uso completo).

---

## 🗂️ Catálogos y Maestros

### Product

**Archivo**: `app/Models/Product.php`

**Traits**:
- `UsesTenantConnection`
- `HasFactory`

**Fillable**:
- `id`, `article_id`, `family_id`, `species_id`, `capture_zone_id`, `article_gtin`, `box_gtin`, `pallet_gtin`, `fixed_weight`, `name`, `a3erp_code`, `facil_com_code`

**Relaciones**:
- `article()`: BelongsTo → `Article` (ID compartido)
- `species()`: BelongsTo → `Species`
- `captureZone()`: BelongsTo → `CaptureZone`
- `family()`: BelongsTo → `ProductFamily`

**Nota Especial**: `Product` comparte su `id` con `Article` (relación 1:1).

**Documentación Completa**: [Catálogos - Productos](../catalogos/40-Productos.md)

---

### Article

**Archivo**: `app/Models/Article.php`

**Traits**:
- `UsesTenantConnection`
- `HasFactory`

**Relaciones**:
- `product()`: HasOne → `Product`

**Nota Especial**: `Article` es la entidad base y `Product` es una extensión que comparte el mismo `id`.

---

### ProductCategory

**Archivo**: `app/Models/ProductCategory.php`

**Traits**:
- `UsesTenantConnection`
- `HasFactory`

**Fillable**:
- `name`, `description`, `active`, `parent_id`

**Relaciones**:
- `parent()`: BelongsTo → `ProductCategory` (self)
- `children()`: HasMany → `ProductCategory` (self)

**Documentación Completa**: [Catálogos - Categorías y Familias](../catalogos/41-Categorias-Familias-Productos.md)

---

### ProductFamily

**Archivo**: `app/Models/ProductFamily.php`

**Traits**:
- `UsesTenantConnection`
- `HasFactory`

**Fillable**:
- `name`, `description`, `active`, `category_id`

**Relaciones**:
- `category()`: BelongsTo → `ProductCategory`
- `products()`: HasMany → `Product`

**Documentación Completa**: [Catálogos - Categorías y Familias](../catalogos/41-Categorias-Familias-Productos.md)

---

### Species

**Archivo**: `app/Models/Species.php`

**Traits**:
- `UsesTenantConnection`
- `HasFactory`

**Fillable**:
- `name`, `scientific_name`, `fao_code`, `image_url`, `fishing_gear_id`

**Relaciones**:
- `fishingGear()`: BelongsTo → `FishingGear`
- `productions()`: HasMany → `Production`
- `products()`: HasMany → `Product`

**Documentación Completa**: [Catálogos - Especies](../catalogos/42-Especies.md)

---

### CaptureZone

**Archivo**: `app/Models/CaptureZone.php`

**Traits**:
- `UsesTenantConnection`
- `HasFactory`

**Fillable**:
- `name`

**Relaciones**:
- `productions()`: HasMany → `Production`
- `products()`: HasMany → `Product`

**Documentación Completa**: [Catálogos - Zonas de Captura](../catalogos/43-Zonas-Captura.md)

---

### Customer

**Archivo**: `app/Models/Customer.php`

**Traits**:
- `UsesTenantConnection`
- `HasFactory`

**Fillable**:
- `name`, `vat_number`, `payment_term_id`, `billing_address`, `shipping_address`, `transportation_notes`, `production_notes`, `accounting_notes`, `salesperson_id`, `emails`, `contact_info`, `country_id`, `transport_id`, `a3erp_code`, `facilcom_code`, `alias`

**Relaciones**:
- `orders()`: HasMany → `Order`
- `salesperson()`: BelongsTo → `Salesperson`
- `country()`: BelongsTo → `Country`
- `transport()`: BelongsTo → `Transport`
- `payment_term()`: BelongsTo → `PaymentTerm`

**Métodos Especiales**:
- `emailsArray()`: Parsea emails en array
- `ccEmailsArray()`: Parsea emails CC en array

**Documentación Completa**: [Catálogos - Clientes](../catalogos/44-Clientes.md)

---

### Supplier

**Archivo**: `app/Models/Supplier.php`

**Traits**:
- `UsesTenantConnection`
- `HasFactory`

**Fillable**:
- `name`, `type`, `contact_info`, `phone`, `emails`, `address`, `export_types`, `a3erp_code`, `facilcom_code`

**Relaciones**:
- `rawMaterialReceptions()`: HasMany → `RawMaterialReception`
- `ceboDispatches()`: HasMany → `CeboDispatch`

**Métodos Especiales**:
- `emailsArray()`: Parsea emails en array
- `ccEmailsArray()`: Parsea emails CC en array

**Documentación Completa**: [Catálogos - Proveedores](../catalogos/45-Proveedores.md)

---

### Transport

**Archivo**: `app/Models/Transport.php`

**Traits**:
- `UsesTenantConnection`
- `HasFactory`

**Fillable**:
- `name`, `vat_number`, `address`, `emails`

**Relaciones**:
- `orders()`: HasMany → `Order`
- `customers()`: HasMany → `Customer`

**Métodos Especiales**:
- `emailsArray()`: Parsea emails en array
- `ccEmailsArray()`: Parsea emails CC en array

**Documentación Completa**: [Catálogos - Transportes](../catalogos/46-Transportes.md)

---

### Salesperson

**Archivo**: `app/Models/Salesperson.php`

**Traits**:
- `UsesTenantConnection`
- `HasFactory`

**Fillable**:
- `name`, `emails`

**Relaciones**:
- `customers()`: HasMany → `Customer`
- `orders()`: HasMany → `Order`

**Métodos Especiales**:
- `emailsArray()`: Parsea emails en array
- `ccEmailsArray()`: Parsea emails CC en array

**Documentación Completa**: [Catálogos - Vendedores](../catalogos/47-Vendedores.md)

---

### PaymentTerm

**Archivo**: `app/Models/PaymentTerm.php`

**Traits**:
- `UsesTenantConnection`
- `HasFactory`

**Fillable**:
- `name`

**Relaciones**:
- `customers()`: HasMany → `Customer`
- `orders()`: HasMany → `Order`

**Documentación Completa**: [Catálogos - Términos de Pago](../catalogos/48-Terminos-Pago.md)

---

### Country

**Archivo**: `app/Models/Country.php`

**Traits**:
- `UsesTenantConnection`
- `HasFactory`

**Fillable**:
- `name`

**Relaciones**:
- `customers()`: HasMany → `Customer`

**Documentación Completa**: [Catálogos - Países](../catalogos/49-Paises.md)

---

### Incoterm

**Archivo**: `app/Models/Incoterm.php`

**Traits**:
- `UsesTenantConnection`
- `HasFactory`

**Fillable**:
- `code`, `description`

**Relaciones**:
- `orders()`: HasMany → `Order`

**Documentación Completa**: [Catálogos - Incoterms](../catalogos/50-Incoterms.md)

---

### FishingGear

**Archivo**: `app/Models/FishingGear.php`

**Traits**:
- `UsesTenantConnection`
- `HasFactory`

**Fillable**:
- `name`

**Relaciones**:
- `species()`: HasMany → `Species`

**Documentación Completa**: [Catálogos - Arte Pesquera](../catalogos/51-Arte-Pesquera.md)

---

### Tax

**Archivo**: `app/Models/Tax.php`

**Traits**:
- `UsesTenantConnection`
- `HasFactory`

**Fillable**:
- `name`, `rate`

**Relaciones**:
- `orderPlannedProductDetails()`: HasMany → `OrderPlannedProductDetail`

**Documentación Completa**: [Catálogos - Impuestos](../catalogos/52-Impuestos.md)

---

### Process

**Archivo**: `app/Models/Process.php`

**Traits**:
- `UsesTenantConnection`
- `HasFactory`

**Fillable**:
- `name`, `type`, `species_id`

**Relaciones**:
- `productionRecords()`: HasMany → `ProductionRecord`

**Documentación Completa**: [Catálogos - Procesos](../catalogos/53-Procesos.md)

---

## 📥 Recepciones y Despachos

### RawMaterialReception

**Archivo**: `app/Models/RawMaterialReception.php`

**Traits**:
- `UsesTenantConnection`
- `HasFactory`

**Fillable**:
- `supplier_id`, `date`, `notes`

**Relaciones**:
- `supplier()`: BelongsTo → `Supplier`
- `products()`: HasMany → `RawMaterialReceptionProduct`

**Documentación Completa**: [Recepciones - Materia Prima](../recepciones-despachos/60-Recepciones-Materia-Prima.md)

---

### RawMaterialReceptionProduct

**Archivo**: `app/Models/RawMaterialReceptionProduct.php`

**Traits**:
- `UsesTenantConnection`
- `HasFactory`

**Fillable**:
- `raw_material_reception_id`, `product_id`, `net_weight`, `price`

**Relaciones**:
- `rawMaterialReception()`: BelongsTo → `RawMaterialReception`
- `product()`: BelongsTo → `Product`

**Documentación Completa**: [Recepciones - Materia Prima](../recepciones-despachos/60-Recepciones-Materia-Prima.md)

---

### CeboDispatch

**Archivo**: `app/Models/CeboDispatch.php`

**Traits**:
- `UsesTenantConnection`
- `HasFactory`

**Fillable**:
- `supplier_id`, `date`, `notes`

**Relaciones**:
- `supplier()`: BelongsTo → `Supplier`
- `products()`: HasMany → `CeboDispatchProduct`

**Documentación Completa**: [Despachos - Cebo](../recepciones-despachos/61-Despachos-Cebo.md)

---

### CeboDispatchProduct

**Archivo**: `app/Models/CeboDispatchProduct.php`

**Traits**:
- `UsesTenantConnection`
- `HasFactory`

**Fillable**:
- `cebo_dispatch_id`, `product_id`, `net_weight`, `price`

**Relaciones**:
- `ceboDispatch()`: BelongsTo → `CeboDispatch`
- `product()`: BelongsTo → `Product`

**Documentación Completa**: [Despachos - Cebo](../recepciones-despachos/61-Despachos-Cebo.md)

---

### RawMaterial

**Archivo**: `app/Models/RawMaterial.php`

**Traits**:
- `UsesTenantConnection`
- `HasFactory`

**Nota**: Modelo que puede no estar en uso completo.

---

### Cebo

**Archivo**: `app/Models/Cebo.php`

**Traits**:
- `UsesTenantConnection`
- `HasFactory`

**Nota**: Modelo que puede no estar en uso completo.

---

## 🏷️ Etiquetas

### Label

**Archivo**: `app/Models/Label.php`

**Traits**:
- `UsesTenantConnection`
- `HasFactory`

**Fillable**:
- `name`, `format`

**Documentación Completa**: [Etiquetas](../etiquetas/70-Etiquetas.md)

---

## 📚 Modelos Auxiliares

### ArticleCategory

**Archivo**: `app/Models/ArticleCategory.php`

**Traits**:
- `UsesTenantConnection`
- `HasFactory`

**Nota**: Modelo que puede estar relacionado con el sistema antiguo (v1) o no estar en uso completo.

---

## 🔑 Patrones Comunes

### Multi-Tenancy

Todos los modelos excepto `Tenant` usan el trait `UsesTenantConnection`, que:
- Configura la conexión de base de datos dinámicamente según el tenant
- Se basa en el header `X-Tenant` en las requests

### Soft Deletes

Solo algunos modelos implementan soft deletes:
- `Production`

### Traits Comunes

- `HasFactory`: Para testing y seeders (Laravel estándar)
- `UsesTenantConnection`: Para multi-tenancy (custom)

### Relaciones Polimórficas

No se utilizan relaciones polimórficas en el sistema actual.

### Casts Comunes

- Fechas: `created_at`, `updated_at` → `datetime` (automático)
- Booleanos: `active` → `boolean`
- JSON: `map_data`, `format` → `array` o `json`

---

## 📝 Notas Importantes

1. **Modelos No Documentados Completamente**: Algunos modelos pueden estar en transición o no estar completamente implementados (ej: `RawMaterial`, `Cebo`, `ArticleCategory`).

2. **Relaciones 1:1 con ID Compartido**: `Product` y `Article` comparten el mismo `id`. Esta es una relación especial documentada en [Catálogos - Productos](../catalogos/40-Productos.md).

3. **Tablas Pivot**: Modelos como `PalletBox`, `OrderPallet` son tablas pivot para relaciones many-to-many.

4. **Modelos Maestros**: Algunos modelos son tablas maestras simples (ej: `PalletState`, `Country`, `PaymentTerm`).

5. **Campos de Email**: Muchos modelos almacenan emails en un campo string con formato especial (separados por `;`, con `CC:` para copias). Ver documentación específica de cada modelo para métodos de parsing.

---

## 🔗 Referencias Cruzadas

Para información detallada de cada modelo, consultar la documentación específica en sus respectivos módulos:

- **Fundamentos**: [Arquitectura Multi-Tenant](../fundamentos/01-Arquitectura-Multi-Tenant.md)
- **Producción**: [Producción - General](../produccion/10-Produccion-General.md)
- **Pedidos**: [Pedidos - General](../pedidos/20-Pedidos-General.md)
- **Inventario**: [Inventario - General](../inventario/30-Almacenes.md)
- **Catálogos**: [Catálogos - Productos](../catalogos/40-Productos.md)
- **Sistema**: [Sistema - Usuarios](../sistema/80-Usuarios.md)

