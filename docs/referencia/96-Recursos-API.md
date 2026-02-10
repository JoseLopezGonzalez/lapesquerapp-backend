# Referencia Técnica - Recursos API (API Resources)

## ⚠️ Estado de la API
- **v1**: Obsoleta (no documentada)
- **v2**: Versión activa (este documento)

---

## 📋 Visión General

Este documento proporciona una referencia completa de todos los API Resources de Laravel en el sistema v2. Los Resources transforman los modelos Eloquent en estructuras JSON consistentes para las respuestas de la API.

**Características Comunes**:
- Todos los Resources extienden `Illuminate\Http\Resources\Json\JsonResource`
- Implementan el método `toArray(Request $request): array`
- Algunos Resources usan `whenLoaded()` para incluir relaciones solo si están cargadas (lazy loading)
- Algunos Resources delegan a métodos `toArrayAssoc()` de los modelos

**Patrones de Transformación**:
1. **Delegación a Modelo**: Algunos Resources simplemente retornan `$this->toArrayAssoc()`
2. **Estructura Personalizada**: Resources con estructura JSON específica
3. **Relaciones Condicionales**: Uso de `whenLoaded()` para evitar N+1 queries

---

## 🗂️ Organización por Módulos

1. [Sistema y Autenticación](#sistema-y-autenticación)
2. [Producción](#producción)
3. [Pedidos](#pedidos)
4. [Inventario y Almacén](#inventario-y-almacén)
5. [Catálogos y Maestros](#catálogos-y-maestros)
6. [Recepciones y Despachos](#recepciones-y-despachos)
7. [Etiquetas](#etiquetas)

---

## 🔐 Sistema y Autenticación

### UserResource

**Archivo**: `app/Http/Resources/v2/UserResource.php`

**Patrón**: Estructura personalizada

**Campos**:
- `id`: ID del usuario
- `name`: Nombre del usuario
- `email`: Email del usuario
- `emailVerifiedAt`: Fecha de verificación de email (ISO 8601)
- `assignedStoreId`: ID del almacén asignado
- `companyName`: Nombre de la compañía
- `companyLogoUrl`: URL del logo de la compañía
- `createdAt`: Fecha de creación (ISO 8601)
- `updatedAt`: Fecha de actualización (ISO 8601)
- `role`: String — Rol del usuario (valor del enum)

**Uso**: Transformación de usuarios en respuestas API

---

### Roles (opciones)

El endpoint `GET /v2/roles/options` devuelve un array de objetos `{ "id": "tecnico", "name": "Técnico" }` generado desde `App\Enums\Role::optionsForApi()`. No existe RoleResource; los roles son un enum.

---

### SessionResource

**Archivo**: `app/Http/Resources/v2/SessionResource.php`

**Patrón**: Estructura personalizada

**Campos**:
- `id`: ID del token de sesión
- `user_id`: ID del usuario (desde `tokenable_id`)
- `user_name`: Nombre del usuario
- `email`: Email del usuario
- `last_used_at`: Última vez usado (formato: `Y-m-d H:i:s`)
- `created_at`: Fecha de creación (formato: `Y-m-d H:i:s`)
- `expires_at`: Fecha de expiración (formato: `Y-m-d H:i:s`)

**Nota**: Transforma tokens de Sanctum (`PersonalAccessToken`)

---

### ActivityLogResource

**Archivo**: `app/Http/Resources/v2/ActivityLogResource.php`

**Patrón**: Estructura personalizada

**Campos**:
- `id`: ID del log
- `user`: Usuario (usando `toArrayAssoc()` del modelo `User`)
- `path`: Ruta accedida
- `method`: Método HTTP
- `ip`: Dirección IP
- `userAgent`: User agent del navegador
- `createdAt`: Fecha de creación

---

## 🏭 Producción

### ProductionResource

**Archivo**: `app/Http/Resources/v2/ProductionResource.php`

**Patrón**: Estructura personalizada con relaciones condicionales

**Campos Base**:
- `id`: ID del lote de producción
- `lot`: Número de lote
- `speciesId`: ID de la especie
- `captureZoneId`: ID de la zona de captura
- `notes`: Notas
- `openedAt`: Fecha de apertura (ISO 8601)
- `closedAt`: Fecha de cierre (ISO 8601)
- `isOpen`: Estado de apertura (boolean)
- `isClosed`: Estado de cierre (boolean)
- `date`: Fecha del lote (formato: `Y-m-d`)
- `createdAt`: Fecha de creación (ISO 8601)
- `updatedAt`: Fecha de actualización (ISO 8601)

**Relaciones Condicionales** (usando `whenLoaded()`):
- `species`: Objeto especie (solo si está cargada)
  - `id`: ID de la especie
  - `name`: Nombre de la especie
- `captureZone`: Objeto zona de captura (solo si está cargada)
  - `id`: ID de la zona
  - `name`: Nombre de la zona
- `records`: Array de registros de producción (solo si está cargada)
  - `id`: ID del registro
  - `processId`: ID del proceso
  - `startedAt`: Fecha de inicio (ISO 8601)
  - `finishedAt`: Fecha de finalización (ISO 8601)

**Campos Condicionales** (usando `when()`):
- `diagramData`: Datos del diagrama (solo si `include_diagram` está en el request)
- `totals`: Totales globales (solo si `include_totals` está en el request)

---

### ProductionRecordResource

**Archivo**: `app/Http/Resources/v2/ProductionRecordResource.php`

**Patrón**: Estructura personalizada con recursos anidados

**Campos Base**:
- `id`: ID del registro
- `productionId`: ID de la producción
- `parentRecordId`: ID del registro padre
- `processId`: ID del proceso
- `startedAt`: Fecha de inicio (ISO 8601)
- `finishedAt`: Fecha de finalización (ISO 8601)
- `notes`: Notas
- `isRoot`: Si es raíz del árbol (boolean)
- `isFinal`: Si es final (boolean)
- `isCompleted`: Si está completado (boolean)
- `totalInputWeight`: Peso total de entradas
- `totalOutputWeight`: Peso total de salidas
- `totalInputBoxes`: Total de cajas de entrada
- `totalOutputBoxes`: Total de cajas de salida
- `createdAt`: Fecha de creación (ISO 8601)
- `updatedAt`: Fecha de actualización (ISO 8601)

**Relaciones Condicionales**:
- `production`: Objeto producción (solo si está cargada)
- `parent`: Objeto registro padre (solo si está cargada)
- `process`: Objeto proceso (solo si está cargada)
- `inputs`: Colección de `ProductionInputResource` (solo si está cargada)
- `outputs`: Colección de `ProductionOutputResource` (solo si está cargada)
- `children`: Colección recursiva de `ProductionRecordResource` (solo si está cargada)

---

### ProductionInputResource

**Archivo**: `app/Http/Resources/v2/ProductionInputResource.php`

**Patrón**: Delegación a modelo

**Campos**: Retorna `$this->toArrayAssoc()` del modelo `ProductionInput`

---

### ProductionOutputResource

**Archivo**: `app/Http/Resources/v2/ProductionOutputResource.php`

**Patrón**: Delegación a modelo

**Campos**: Retorna `$this->toArrayAssoc()` del modelo `ProductionOutput`

---

## 📦 Pedidos

### OrderResource

**Archivo**: `app/Http/Resources/v2/OrderResource.php`

**Patrón**: Estructura personalizada

**Campos**:
- `id`: ID del pedido
- `customer`: Cliente (usando `toArrayAssoc()`)
- `buyerReference`: Referencia del comprador
- `status`: Estado del pedido
- `loadDate`: Fecha de carga
- `salesperson`: Vendedor (usando `toArrayAssoc()`)
- `transport`: Transporte (usando `toArrayAssoc()`)
- `pallets`: Número de palets
- `totalBoxes`: Total de cajas
- `incoterm`: Incoterm (usando `toArrayAssoc()`)
- `totalNetWeight`: Peso neto total
- `subtotalAmount`: Subtotal
- `totalAmount`: Total

**Uso**: Listado de pedidos (resumen)

---

### OrderDetailsResource

**Archivo**: `app/Http/Resources/v2/OrderDetailsResource.php`

**Patrón**: Estructura personalizada completa

**Campos Base**:
- `id`: ID del pedido
- `buyerReference`: Referencia del comprador
- `billingAddress`: Dirección de facturación
- `shippingAddress`: Dirección de envío
- `transportationNotes`: Notas de transporte
- `productionNotes`: Notas de producción
- `accountingNotes`: Notas contables
- `entryDate`: Fecha de entrada
- `loadDate`: Fecha de carga
- `status`: Estado del pedido
- `createdAt`: Fecha de creación
- `updatedAt`: Fecha de actualización
- `truckPlate`: Matrícula del camión
- `trailerPlate`: Matrícula del remolque
- `temperature`: Temperatura

**Objetos Relacionados**:
- `customer`: Cliente (usando `toArrayAssoc()`)
- `paymentTerm`: Término de pago (usando `toArrayAssoc()`)
- `salesperson`: Vendedor (usando `toArrayAssoc()`)
- `transport`: Transporte (usando `toArrayAssoc()`)
- `incoterm`: Incoterm (usando `toArrayAssoc()`)
- `incident`: Incidencia (usando `toArrayAssoc()`, puede ser null)

**Colecciones**:
- `pallets`: Array de palets (usando `toArrayAssoc()` de cada palet)
- `plannedProductDetails`: Array de detalles planificados (usando `toArrayAssoc()`)
- `productionProductDetails`: Detalles de productos de producción
- `productDetails`: Detalles de productos

**Campos Calculados**:
- `totalNetWeight`: Peso neto total
- `numberOfPallets`: Número de palets
- `totalBoxes`: Total de cajas
- `subTotalAmount`: Subtotal
- `totalAmount`: Total
- `emails`: Array de emails (usando `emailsArray` accessor)
- `ccEmails`: Array de emails CC (usando `ccEmailsArray` accessor)

**Campos Especiales**:
- `customerHistory`: Historial de pedidos del cliente (calculado en el Resource)
  - Array de productos con:
    - `product`: Objeto producto
    - `total_boxes`: Total de cajas
    - `total_net_weight`: Peso neto total
    - `average_unit_price`: Precio unitario promedio
    - `last_order_date`: Fecha del último pedido
    - `lines`: Array de líneas de pedido
    - `total_amount`: Monto total

**Uso**: Detalle completo de un pedido

---

### OrderPlannedProductDetailResource

**Archivo**: `app/Http/Resources/v2/OrderPlannedProductDetailResource.php`

**Patrón**: Estructura personalizada

**Campos**:
- `id`: ID del detalle
- `orderId`: ID del pedido
- `product`: Producto (usando `toArrayAssoc()`)
- `tax`: Impuesto (usando `toArrayAssoc()`)
- `boxes`: Número de cajas
- `netWeight`: Peso neto
- `unitPrice`: Precio unitario
- `notes`: Notas
- `createdAt`: Fecha de creación
- `updatedAt`: Fecha de actualización

---

## 📊 Inventario y Almacén

### StoreResource

**Archivo**: `app/Http/Resources/v2/StoreResource.php`

**Patrón**: Delegación a modelo

**Campos**: Retorna `$this->toArrayAssoc()` del modelo `Store`

**Estructura esperada**:
- `id`: ID del almacén
- `name`: Nombre
- `temperature`: Temperatura
- `capacity`: Capacidad
- `netWeightPallets`: Peso neto de palets
- `totalNetWeight`: Peso neto total
- `content`: Contenido (pallets, boxes, bigBoxes)
- `map`: Mapa (JSON decodificado)

---

### StoreDetailsResource

**Archivo**: `app/Http/Resources/v2/StoreDetailsResource.php`

**Patrón**: Estructura personalizada

**Campos**:
- `id`: ID del almacén
- `name`: Nombre
- `temperature`: Temperatura
- `capacity`: Capacidad
- `netWeightPallets`: Peso neto de palets
- `totalNetWeight`: Peso neto total
- `content`: Objeto con:
  - `pallets`: Array de palets (usando `toArrayAssocV2()`)
  - `boxes`: Array vacío (reservado para futuras implementaciones)
  - `bigBoxes`: Array vacío (reservado para futuras implementaciones)
- `map`: Mapa (JSON decodificado)

---

### PalletResource

**Archivo**: `app/Http/Resources/v2/PalletResource.php`

**Patrón**: Estructura personalizada

**Campos**:
- `id`: ID del palet
- `observations`: Observaciones
- `state`: Estado del palet
- `productsNames`: Array de nombres de productos
- `boxes`: Array de cajas (usando `toArrayAssocV2()` de cada caja)
- `lots`: Array de lotes
- `netWeight`: Peso neto (redondeado a 3 decimales)
- `position`: Posición en el almacén
- `store`: Objeto almacén (si está almacenado)
  - `id`: ID del almacén
  - `name`: Nombre del almacén
- `orderId`: ID del pedido
- `numberOfBoxes`: Número de cajas
- `availableBoxesCount`: Conteo de cajas disponibles
- `usedBoxesCount`: Conteo de cajas usadas
- `totalAvailableWeight`: Peso total disponible (redondeado a 3 decimales)
- `totalUsedWeight`: Peso total usado (redondeado a 3 decimales)

---

### BoxResource

**Archivo**: `app/Http/Resources/v2/BoxResource.php`

**Patrón**: Estructura personalizada

**Campos**:
- `id`: ID de la caja
- `palletId`: ID del palet (puede ser null)
- `product`: Objeto producto con:
  - `species`: Especie (usando `toArrayAssoc()`)
  - `captureZone`: Zona de captura (usando `toArrayAssoc()`)
  - `articleGtin`: GTIN del artículo
  - `boxGtin`: GTIN de la caja
  - `palletGtin`: GTIN del palet
  - `fixedWeight`: Peso fijo
  - `name`: Nombre del producto
  - `id`: ID del producto
- `lot`: Lote
- `gs1128`: Código GS1-128
- `grossWeight`: Peso bruto
- `netWeight`: Peso neto
- `createdAt`: Fecha de creación

---

## 🗂️ Catálogos y Maestros

### ProductResource

**Archivo**: `app/Http/Resources/v2/ProductResource.php`

**Patrón**: Delegación a modelo

**Campos**: Retorna `$this->toArrayAssoc()` del modelo `Product`

---

### ProductCategoryResource

**Archivo**: `app/Http/Resources/v2/ProductCategoryResource.php`

**Patrón**: Estructura personalizada

**Campos**:
- `id`: ID de la categoría
- `name`: Nombre
- `description`: Descripción
- `active`: Activo (boolean)
- `createdAt`: Fecha de creación
- `updatedAt`: Fecha de actualización

---

### ProductFamilyResource

**Archivo**: `app/Http/Resources/v2/ProductFamilyResource.php`

**Patrón**: Estructura personalizada

**Campos**: Similar a `ProductCategoryResource`

---

### SpeciesResource

**Archivo**: `app/Http/Resources/v2/SpeciesResource.php`

**Patrón**: Estructura personalizada

**Campos**:
- `id`: ID de la especie
- `name`: Nombre
- `scientificName`: Nombre científico
- `faoCode`: Código FAO
- `imageUrl`: URL de imagen
- `fishingGear`: Arte de pesca (usando `toArrayAssoc()`)
- `createdAt`: Fecha de creación (ISO 8601)
- `updatedAt`: Fecha de actualización (ISO 8601)

---

### CustomerResource

**Archivo**: `app/Http/Resources/v2/CustomerResource.php`

**Patrón**: Delegación a modelo

**Campos**: Retorna `parent::toArrayAssoc()` del modelo `Customer`

---

### SupplierResource

**Archivo**: `app/Http/Resources/v2/SupplierResource.php`

**Patrón**: Delegación a modelo

**Campos**: Retorna `$this->toArrayAssoc()` del modelo `Supplier`

---

### TransportResource

**Archivo**: `app/Http/Resources/v2/TransportResource.php`

**Patrón**: Estructura personalizada

**Campos**:
- `id`: ID del transporte
- `name`: Nombre
- `vatNumber`: Número de IVA
- `address`: Dirección
- `emails`: Array de emails
- `ccEmails`: Array de emails CC
- `createdAt`: Fecha de creación (ISO 8601)
- `updatedAt`: Fecha de actualización (ISO 8601)

---

### SalespersonResource

**Archivo**: `app/Http/Resources/v2/SalespersonResource.php`

**Patrón**: Delegación a modelo

**Campos**: Retorna `$this->toArrayAssoc()` del modelo `Salesperson`

---

### PaymentTermResource

**Archivo**: `app/Http/Resources/v2/PaymentTermResource.php`

**Patrón**: Delegación a modelo

**Campos**: Retorna `$this->toArrayAssoc()` del modelo `PaymentTerm`

---

### CountryResource

**Archivo**: `app/Http/Resources/v2/CountryResource.php`

**Patrón**: Delegación a modelo

**Campos**: Retorna `$this->toArrayAssoc()` del modelo `Country`

---

### IncotermResource

**Archivo**: `app/Http/Resources/v2/IncotermResource.php`

**Patrón**: Estructura personalizada

**Campos**:
- `id`: ID del incoterm
- `code`: Código
- `description`: Descripción
- `createdAt`: Fecha de creación (ISO 8601)
- `updatedAt`: Fecha de actualización (ISO 8601)

---

### FishingGearResource

**Archivo**: `app/Http/Resources/v2/FishingGearResource.php`

**Patrón**: Delegación a modelo

**Campos**: Retorna `$this->toArrayAssoc()` del modelo `FishingGear`

---

### ProcessResource

**Archivo**: `app/Http/Resources/v2/ProcessResource.php`

**Patrón**: Estructura personalizada

**Campos**:
- `id`: ID del proceso
- `name`: Nombre
- `type`: Tipo
- `createdAt`: Fecha de creación (ISO 8601)
- `updatedAt`: Fecha de actualización (ISO 8601)

---

## 📥 Recepciones y Despachos

### RawMaterialReceptionResource

**Archivo**: `app/Http/Resources/v2/RawMaterialReceptionResource.php`

**Patrón**: Delegación a modelo

**Campos**: Retorna `$this->toArrayAssoc()` del modelo `RawMaterialReception`

---

### RawMaterialReceptionProductResource

**Archivo**: `app/Http/Resources/v2/RawMaterialReceptionProductResource.php`

**Patrón**: Estructura personalizada

**Campos**: Similar a otros recursos de productos de recepción

---

### CeboDispatchResource

**Archivo**: `app/Http/Resources/v2/CeboDispatchResource.php`

**Patrón**: Delegación a modelo

**Campos**: Retorna `$this->toArrayAssoc()` del modelo `CeboDispatch`

---

### CeboDispatchProductResource

**Archivo**: `app/Http/Resources/v2/CeboDispatchProductResource.php`

**Patrón**: Estructura personalizada

**Campos**: Similar a otros recursos de productos de despacho

---

## 🏷️ Etiquetas

### LabelResource

**Archivo**: `app/Http/Resources/v2/LabelResource.php`

**Patrón**: Delegación a modelo

**Campos**: Retorna `$this->toArrayAssoc()` del modelo `Label`

---

## 🔑 Patrones y Convenciones

### Método `toArrayAssoc()` en Modelos

Muchos modelos implementan un método `toArrayAssoc()` que retorna una estructura asociativa consistente. Los Resources que usan este patrón simplemente delegan la transformación al modelo.

**Ventajas**:
- Consistencia entre Resources y uso directo de modelos
- Reutilización de código
- Mantenibilidad

**Desventajas**:
- Menos control sobre la estructura de respuesta API
- Posibles problemas de N+1 si no se cargan relaciones

### Uso de `whenLoaded()`

El método `whenLoaded()` de Laravel Resources permite incluir relaciones solo si están cargadas (eager loading), evitando N+1 queries.

**Ejemplo**:
```php
'species' => $this->whenLoaded('species', function () {
    return [
        'id' => $this->species->id,
        'name' => $this->species->name,
    ];
})
```

### Uso de `when()`

El método `when()` permite incluir campos condicionalmente basado en parámetros del request.

**Ejemplo**:
```php
'diagramData' => $this->when($request->has('include_diagram'), function () {
    return $this->getDiagramData();
})
```

### Formato de Fechas

Los Resources utilizan diferentes formatos de fecha:
- **ISO 8601**: `toIso8601String()` para timestamps completos
- **Fecha simple**: `format('Y-m-d')` para solo fecha
- **Fecha y hora**: `format('Y-m-d H:i:s')` para formato legible

---

## 📝 Notas Importantes

1. **Recursos que Delegan**: Muchos Resources simplemente retornan `toArrayAssoc()` del modelo. Para ver la estructura exacta, consultar el método `toArrayAssoc()` del modelo correspondiente.

2. **Recursos Personalizados**: Algunos Resources tienen estructuras completamente personalizadas (ej: `OrderDetailsResource`, `ProductionResource`).

3. **N+1 Queries**: Los Resources que usan `toArrayAssoc()` directamente pueden causar N+1 queries si las relaciones no están cargadas. Siempre usar eager loading en los controladores.

4. **Recursos Anidados**: Algunos Resources usan otros Resources en colecciones (ej: `ProductionRecordResource` usa `ProductionInputResource`).

5. **Campos Calculados**: Algunos Resources incluyen campos calculados o accessors de los modelos (ej: `emailsArray`, `ccEmailsArray`).

---

## 🔗 Referencias Cruzadas

Para información detallada de cada Resource y su uso en los controladores, consultar:

- **Producción**: [Producción - General](../produccion/10-Produccion-General.md)
- **Pedidos**: [Pedidos - General](../pedidos/20-Pedidos-General.md)
- **Inventario**: [Inventario - Almacenes](../inventario/30-Almacenes.md)
- **Catálogos**: [Catálogos - Productos](../catalogos/40-Productos.md)
- **Sistema**: [Sistema - Usuarios](../sistema/80-Usuarios.md)

