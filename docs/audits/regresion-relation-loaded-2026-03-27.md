# Regresión: Guards `relationLoaded` sin eager-load correspondiente

**Detectado**: 2026-03-27  
**Commit causante**: `a86f091`  
**Descripción**: El commit introdujo guards `$this->relationLoaded('X') ? ... : null/[]` en 38 archivos
(modelos y resources). La intención era correcta (evitar lazy loading y N+1), pero varios endpoints
no tenían el eager-load correspondiente en sus controllers/services, dejando campos vacíos o `null`
que antes siempre se devolvían.

---

## Entidades afectadas por el commit

Archivos PHP modificados en `a86f091` con impacto en contrato de API:

| Archivo | Tipo | Relaciones ahora condicionales |
|---------|------|-------------------------------|
| `Models/Box.php` | Modelo | `product`, `productionInputs`, `palletBox` |
| `Models/Cebo.php` | Modelo | `product` |
| `Models/CommercialInteraction.php` | Modelo | `salesperson` |
| `Models/Customer.php` | Modelo | `payment_term`, `salesperson`, `fieldOperator`, `country`, `transport` |
| `Models/Offer.php` | Modelo | `prospect`, `customer`, `salesperson`, `incoterm`, `paymentTerm` |
| `Models/OfferLine.php` | Modelo | `product`, `tax` |
| `Models/OrderPlannedProductDetail.php` | Modelo | `product`, `tax` |
| `Models/Pallet.php` | Modelo | `boxes`, `boxesV2` |
| `Models/PalletBox.php` | Modelo | `box` |
| `Models/Product.php` | Modelo | `species`, `captureZone`, `family`, `family.category` |
| `Models/ProductFamily.php` | Modelo | `category` |
| `Models/Prospect.php` | Modelo | `country`, `salesperson`, `customer`, `primaryContact`, `latestInteraction`, `contacts`, `interactions`, `offers` |
| `Models/RawMaterial.php` | Modelo | `product` |
| `Models/Species.php` | Modelo | `fishingGear` |
| `Models/Store.php` | Modelo | `palletsV2`, `externalUser` |
| `Models/StoredPallet.php` | Modelo | `pallet` |
| `Resources/v2/BoxResource.php` | Resource | `product`, `product.species`, `product.captureZone` |
| `Resources/v2/DeliveryRouteResource.php` | Resource | `salesperson`, `fieldOperator` |
| `Resources/v2/FieldOrderDetailsResource.php` | Resource | `customer`, `plannedProductDetails`, `pallets` |
| `Resources/v2/FieldOrderResource.php` | Resource | `customer`, `plannedProductDetails` |
| `Resources/v2/OrderDetailsResource.php` | Resource | `customer`, `paymentTerm`, `salesperson`, `fieldOperator`, `transport`, `pallets`, `incoterm`, `plannedProductDetails`, `incident`, `offer`, `route`, `routeStop` |
| `Resources/v2/OrderPlannedProductDetailResource.php` | Resource | `product`, `tax` |
| `Resources/v2/OrderResource.php` | Resource | `customer`, `salesperson`, `fieldOperator`, `transport`, `incoterm`, `offer` |
| `Resources/v2/PalletResource.php` | Resource | `boxes`, `store` |
| `Resources/v2/ProductionInputResource.php` | Resource | `box`, `box.product`, `pallet` |
| `Resources/v2/ProductionOutputConsumptionResource.php` | Resource | `productionOutput`, `productionOutput.product`, `productionOutput.productionRecord.process` |
| `Resources/v2/ProductionOutputSourceResource.php` | Resource | `productionInput`, `productionOutputConsumption` |
| `Resources/v2/ProductionRecordResource.php` | Resource | `parent.process` |
| `Resources/v2/RouteTemplateResource.php` | Resource | `salesperson`, `fieldOperator` |
| `Resources/v2/SessionResource.php` | Resource | `tokenable` |
| `Resources/v2/SpeciesResource.php` | Resource | `fishingGear` |
| `Resources/v2/StoreDetailsResource.php` | Resource | `palletsV2`, `externalUser` |

---

## Problemas detectados por endpoint / dominio

### P-01 — `GET /stores/{id}` y `GET /stores` ✅ CORREGIDO
**Archivo**: `app/Http/Controllers/v2/StoreController.php`  
**Síntoma**: `content.pallets[].boxes` vacío; `product.species/captureZone/family` vacíos; `costPerKg/totalCost` null.  
**Causa**:
- Controller no cargaba `product.species`, `product.captureZone`, `product.family.category`.
- `Pallet::toArrayAssocV2()` buscaba `boxesV2` pero el controller cargaba `boxes`.
- `Box::getPalletAttribute()` retornaba null sin `palletBox` cargado.

**Fix aplicado**:
1. `StoreController@show` y `@index`: ampliado el `with()` con todas las sub-relaciones de producto y `palletBox.pallet.reception.products`.
2. `Pallet::toArrayAssocV2()`: fallback a relación `boxes` si `boxesV2` no está cargada.
3. `Box::getPalletAttribute()`: fallback con query explícita si `palletBox` no está cargado.

---

### P-02 — `GET /pallets` y `GET /pallets/{id}` ✅ CORREGIDO
**Archivo**: `app/Services/v2/PalletListService.php`  
**Síntoma**: En `PalletResource`, `boxes[].product.species = null`, `product.captureZone = null`.  

**Fix aplicado**:
1. `loadRelations()`: añadido `boxes.box.product.species`, `boxes.box.product.captureZone`, `boxes.box.product.family.category` (afecta `list`, `show`, `registeredPallets`).
2. `searchByLot()`: mismas sub-relaciones añadidas dentro del closure de `boxes.box`.
3. `availableForOrder()`: mismas sub-relaciones añadidas.

---

### P-03 — `GET /orders` ✅ YA CORRECTO (verificado)
**Archivo**: `app/Services/v2/OrderListService.php`  
**Verificación**: `OrderListService::list()` ya carga `customer`, `salesperson`, `fieldOperator`, `transport`, `incoterm`, `offer`, `plannedProductDetails`, `plannedProductDetails.tax` en las tres ramas de `list()`. No requería corrección.

---

### P-04 — `GET /orders/{id}` ✅ YA CORRECTO (verificado)
**Archivo**: `app/Services/v2/OrderDetailService.php`  
**Verificación**: `OrderController@show` delega en `OrderDetailService::getOrderForDetail()`, que realiza un eager load exhaustivo de más de 20 relaciones incluyendo `customer.*`, `payment_term`, `salesperson`, `fieldOperator`, `transport`, `incoterm`, `plannedProductDetails.product.species.*`, `pallets.boxes.box.product.*`, `offer`, `route`, `routeStop`, `incident`. No requería corrección.

---

### P-05 — `GET /orders/{id}/pallets` (palets de un pedido) ✅ YA CORRECTO (verificado)
**Archivo**: `app/Services/v2/OrderDetailService.php`  
**Verificación**: `OrderDetailService` ya carga `pallets.boxes.box.product.species`, `pallets.boxes.box.product.captureZone`, `pallets.boxes.box.product.family.category` para el endpoint de detalle de pedido. No requería corrección.

---

### P-06 — `GET /orders/{id}/planned-details` ✅ YA CORRECTO (verificado)
**Archivo**: `app/Services/v2/OrderDetailService.php`  
**Verificación**: `OrderDetailService` carga `plannedProductDetails.product.species.fishingGear`, `plannedProductDetails.product.captureZone`, `plannedProductDetails.product.family.category`, `plannedProductDetails.tax`. No requería corrección.

---

### P-07 — `GET /customers/{id}` ✅ CORREGIDO
**Archivo**: `app/Http/Controllers/v2/CustomerController.php`  
**Síntoma**: `Customer::toArrayAssoc()` devolvía `paymentTerm/salesperson/fieldOperator/country/transport = null`.  
**Causa**: `CustomerController@show` hacía `Customer::findOrFail($id)` sin eager-load. `CustomerResource` delega en `Customer::toArrayAssoc()` que tiene guards `relationLoaded` para todas esas relaciones.

**Fix aplicado**: `show()` reemplazado por `Customer::with(['payment_term', 'salesperson', 'fieldOperator', 'country', 'transport'])->findOrFail($id)`, alineado con lo que `CustomerListService` ya cargaba en el index.

---

### P-08 — `GET /species` y `GET /species/{id}` ✅ YA CORRECTO (verificado)
**Archivo**: `app/Http/Controllers/v2/SpeciesController.php`  
**Verificación**: `@index` hace `$query->with('fishingGear')` y `@show` hace `$species->load('fishingGear')`. Ambos endpoints cargan la relación correctamente. No requería corrección.

---

### P-09 — Producción: inputs, consumptions ✅ CORREGIDO
**Archivos corregidos**:
- `app/Http/Controllers/v2/ProductionRecordController.php` (`show`)
- `app/Http/Controllers/v2/ProductionInputController.php` (`index`, `show`)
- `app/Http/Controllers/v2/ProductionOutputConsumptionController.php` (`index`, `show`)
- `app/Services/Production/ProductionRecordService.php` (método `list`)
- `app/Services/Production/ProductionInputService.php` (`create`, `createMultiple`)

**Síntomas corregidos**:
- `BoxResource.product.species = null` y `BoxResource.product.captureZone = null` en inputs de producción.
- `ProductionOutputConsumptionResource.parentRecord = null` porque `productionOutput.productionRecord.process` no se cargaba.

**Fix aplicado**:
1. Añadido `inputs.box.product.species`, `inputs.box.product.captureZone` en `ProductionRecordController@show` y `ProductionRecordService::list`.
2. Añadido `box.product.species`, `box.product.captureZone` en `ProductionInputController@index/@show` y `ProductionInputService::create/createMultiple`.
3. Añadido `productionOutput.productionRecord.process` en `ProductionOutputConsumptionController@index/@show`.

---

### P-10 — `GET /delivery-routes` y `GET /route-templates` ✅ YA CORRECTO (verificado)
**Archivos**: `DeliveryRouteController`, `RouteTemplateController`  
**Verificación**: Ambos controllers cargan `salesperson` y `fieldOperator` tanto en `index` como en `show`. No requería corrección.

---

### P-11 — CRM: Prospects, Offers, Interactions ✅ YA CORRECTO (verificado)
**Archivo**: `app/Http/Controllers/v2/ProspectController.php`  
**Verificación**: `ProspectController@show` carga `country`, `salesperson`, `customer`, `contacts`, `primaryContact`, `latestInteraction.salesperson`, `interactions.salesperson`, `offers.lines.product`, `offers.lines.tax`. No requería corrección.

---

### P-12 — `GET /cebo-dispatches` / `GET /raw-material-receptions` ✅ YA CORRECTO (verificado)
**Archivos**: `CeboDispatchController`, `RawMaterialReceptionController`  
**Verificación**: `CeboDispatchController@show` carga `products.product`. `RawMaterialReceptionController@show` carga `supplier`, `products.product`, `pallets.*`. Ambos endpoints ya cargan `product` correctamente. No requería corrección.

---

### P-13 — `GET /sessions` ✅ YA CORRECTO (verificado)
**Archivo**: `app/Http/Controllers/v2/SessionController.php`  
**Verificación**: `SessionController@index` hace `PersonalAccessToken::with('tokenable')->orderBy(...)`. La relación se carga correctamente. No requería corrección.

---

## Patrón de corrección

Para cada problema el fix es siempre en la **capa de carga** (controller o service), **no** en el resource/modelo:

```php
// Añadir en el with() del controller o service:
->with([
    'relation',
    'relation.subrelation',
    'relation.subrelation.deeper',
])
```

Solo en casos donde el campo lo calcula un accesor que depende de la relación (ej. `getPalletAttribute`)
se añade un fallback en el modelo.

---

## Criterio de cierre

Un problema se marca ✅ cuando:
1. Se verifica el `with()` del controller/service correspondiente.
2. Se comprueba en el endpoint real que los campos ya no son `null/[]`.
3. No se introducen N+1 queries adicionales.

---

## Historial de cambios

| Fecha | Problema | Acción |
|-------|----------|--------|
| 2026-03-27 | P-01 `/stores/{id}` y `/stores` | Corregido en `StoreController`, `Pallet::toArrayAssocV2()`, `Box::getPalletAttribute()` |
| 2026-03-27 | P-02 `/pallets`, `/pallets/{id}`, `/pallets/registered`, `/pallets/lot/{lot}`, `/pallets/available-for-order/{id}` | Corregido en `PalletListService`: `loadRelations()`, `searchByLot()`, `availableForOrder()` |
| 2026-03-27 | P-03 a P-06 `/orders*` | Verificado — `OrderListService` y `OrderDetailService` ya cargan todo correctamente |
| 2026-03-27 | P-07 `/customers/{id}` | Corregido en `CustomerController@show`: añadido `with(['payment_term','salesperson','fieldOperator','country','transport'])` |
| 2026-03-27 | P-08 `/species*` | Verificado — `SpeciesController` ya carga `fishingGear` en index y show |
| 2026-03-27 | P-09 producción inputs/records/consumptions | Corregido en `ProductionRecordController@show`, `ProductionRecordService::list`, `ProductionInputController@index/@show`, `ProductionInputService::create/createMultiple`, `ProductionOutputConsumptionController@index/@show` |
| 2026-03-27 | P-10 a P-13 delivery-routes, CRM, cebo, sessions | Verificados — todos cargaban sus relaciones correctamente |
