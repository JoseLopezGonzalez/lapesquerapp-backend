# Líneas Auxiliares en Pedidos — Productos No Pesqueros

**Estado:** Implementado — ver notas de implementación real marcadas con 🔧 en este documento; guía de uso para frontend en [27-guia-frontend-lineas-auxiliares.md](./27-guia-frontend-lineas-auxiliares.md)  
**Fecha:** 2026-07-01  
**Bloque:** A.2 Ventas (extensión)  
**Objetivo:** Permitir registrar y facturar productos no pesqueros (nieve, envases, palets, material de embalaje, servicios ocasionales) dentro del workflow de pedidos existente, como **entidad propia de línea de venta directa**, con soporte completo de catálogo, líneas ad-hoc y estadísticas independientes.

---

## 1. Contexto y Motivación

Congelados Brisamar, como otras industrias del sector, realiza ventas ocasionales de productos que **no forman parte del circuito pesquero**:

- **Nieve / hielo** (producción propia o reventa)
- **Envases**: tarrinas, cajas de cartón
- **Material de paletizado**: palets de madera, strech film
- **Servicios de favor** a empresas del sector (venta puntual sin catálogo previo)

Estos artículos comparten con los pedidos normales:

- Van a los mismos **clientes**
- Los gestiona el mismo **comercial**
- Necesitan un **albarán / documento de entrega**
- Deben aparecer en **estadísticas de ventas** (importes, IVA, por cliente, por período)

Y se diferencian en que:

- **No tienen especie, zona de captura ni zona FAO**
- **No generan palets ni cajas** en el circuito de stock
- **No tienen lote** de producción trazable
- **No necesitan GS1**
- La unidad de medida es variable: kg, unidad, saco, palet, servicio...
- A veces el artículo ni siquiera existe en catálogo (venta esporádica)

---

## 2. Decisión de Diseño: Nueva Entidad `order_auxiliary_lines`

### Por qué NO mezclar con `order_planned_product_details`

`order_planned_product_details` representa una **línea de previsión pesquera**: qué producto pesquero se espera enviar, a qué precio estimado, para luego confrontarlo con los palets/cajas reales expedidos. Toda la lógica de kg asignados, rentabilidad y estadísticas de volumen se construye sobre esa relación entre previsión y expedición real.

Una venta de 50 tarrinas o 500 kg de nieve **no es una previsión**: es una línea de venta directa, cerrada en el momento en que se escribe. Mezclarlas en `order_planned_product_details` introduciría:

- Lógica condicional en toda la capa de estadísticas (filtros `line_type`)
- Semántica incorrecta: "detalle planificado de producto" para un artículo que no va en palets
- Riesgo permanente de contaminar KPIs pesqueros
- Deuda técnica creciente en cada nuevo query que alguien escriba sobre esa tabla

### La solución: entidad propia

```
order_auxiliary_lines   (NUEVA)
├── id
├── order_id              → orders
├── auxiliary_product_id  → auxiliary_products (nullable)
├── description           VARCHAR(500) — libre si no hay producto de catálogo
├── quantity              DECIMAL(10,3)
├── unit                  VARCHAR(50)   — kg, ud, saco, palet, servicio...
├── unit_price            DECIMAL(10,4)
├── tax_id                → taxes (nullable)
├── created_at / updated_at
```

Una línea auxiliar necesita **al menos uno de los dos**: `auxiliary_product_id` O `description`.  
Si hay `auxiliary_product_id`, `description` y `unit` pueden heredarse del catálogo pero son sobreescribibles.

### Pedido válido con solo líneas auxiliares

Un pedido puede contener:
- Solo `plannedProductDetails` (comportamiento actual, sin cambio)
- Solo `auxiliaryLines` (pedido de nieve, envases, etc.)
- Ambos (pedido mixto: pulpo + tarrinas en el mismo albarán)

La validación actual que exige al menos una línea de previsión **se relaja**: un pedido es válido si tiene al menos una línea de cualquier tipo.

---

## 3. Cambios en Base de Datos

### 3.1 Nueva tabla: `auxiliary_products`

Catálogo simple para artículos/servicios no pesqueros.

```sql
CREATE TABLE auxiliary_products (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(255) NOT NULL,
    reference     VARCHAR(100) NULL,        -- código interno o referencia comercial
    unit          VARCHAR(50)  NOT NULL DEFAULT 'ud',  -- kg, ud, saco, palet, servicio...
    default_price DECIMAL(10,4) NULL,       -- precio orientativo por unidad
    notes         TEXT NULL,
    active        TINYINT(1) NOT NULL DEFAULT 1,
    created_at    TIMESTAMP NULL,
    updated_at    TIMESTAMP NULL,
    UNIQUE KEY auxiliary_products_name_unique (name)
);
```

Ejemplos de registros:

| name | reference | unit | default_price |
|------|-----------|------|---------------|
| Nieve granulada | NIE-01 | kg | 0.08 |
| Tarrina 500g | ENV-T500 | ud | 0.15 |
| Caja cartón 5kg | ENV-C5 | ud | 0.40 |
| Palet de madera EUR | PAL-EUR | ud | 8.00 |

### 3.2 Nueva tabla: `order_auxiliary_lines`

```sql
CREATE TABLE order_auxiliary_lines (
    id                   BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id             BIGINT UNSIGNED NOT NULL,
    auxiliary_product_id BIGINT UNSIGNED NULL,
    description          VARCHAR(500) NULL,
    quantity             DECIMAL(10,3) NOT NULL,
    unit                 VARCHAR(50) NOT NULL DEFAULT 'ud',
    unit_price           DECIMAL(10,4) NOT NULL DEFAULT 0,
    tax_id               BIGINT UNSIGNED NULL,
    created_at           TIMESTAMP NULL,
    updated_at           TIMESTAMP NULL,
    CONSTRAINT fk_oal_order
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_oal_auxiliary_product
        FOREIGN KEY (auxiliary_product_id) REFERENCES auxiliary_products(id) ON DELETE SET NULL,
    CONSTRAINT fk_oal_tax
        FOREIGN KEY (tax_id) REFERENCES taxes(id) ON DELETE SET NULL
);
```

### 3.3 Sin cambios en `order_planned_product_details`

Esta tabla **no se modifica**. Sigue siendo exclusivamente para líneas de previsión pesquera con `product_id` obligatorio.

---

## 4. Modelos y Lógica de Aplicación

### 4.1 Nuevo modelo `AuxiliaryProduct`

```php
// app/Models/AuxiliaryProduct.php
class AuxiliaryProduct extends Model
{
    use UsesTenantConnection;

    protected $fillable = [
        'name', 'reference', 'unit', 'default_price', 'notes', 'active',
    ];

    protected $casts = [
        'active'        => 'boolean',
        'default_price' => 'decimal:4',
    ];

    public function orderLines(): HasMany
    {
        return $this->hasMany(OrderAuxiliaryLine::class, 'auxiliary_product_id');
    }

    public function isInUse(): bool
    {
        return $this->orderLines()->exists();
    }
}
```

### 4.2 Nuevo modelo `OrderAuxiliaryLine`

```php
// app/Models/OrderAuxiliaryLine.php
class OrderAuxiliaryLine extends Model
{
    use UsesTenantConnection;

    protected $fillable = [
        'order_id', 'auxiliary_product_id',
        'description', 'quantity', 'unit', 'unit_price', 'tax_id',
    ];

    public function order(): BelongsTo     { return $this->belongsTo(Order::class); }
    public function auxiliaryProduct(): BelongsTo { return $this->belongsTo(AuxiliaryProduct::class); }
    public function tax(): BelongsTo       { return $this->belongsTo(Tax::class); }

    // Nombre efectivo: catálogo > descripción libre
    public function getEffectiveDescriptionAttribute(): string
    {
        return $this->auxiliaryProduct?->name ?? $this->description ?? '';
    }

    // Subtotal sin IVA
    public function getSubtotalAttribute(): float
    {
        return round($this->unit_price * $this->quantity, 4);
    }

    // Total con IVA
    public function getTotalAttribute(): float
    {
        $rate = $this->tax?->rate ?? 0;
        return round($this->subtotal * (1 + $rate / 100), 4);
    }

    protected static function boot(): void
    {
        parent::boot();
        static::saving(function (self $line) {
            if (empty($line->auxiliary_product_id) && empty($line->description)) {
                throw ValidationException::withMessages([
                    'description' => 'Se requiere un artículo del catálogo o una descripción libre.',
                ]);
            }
            if ($line->quantity <= 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'La cantidad debe ser mayor que cero.',
                ]);
            }
            if ($line->unit_price < 0) {
                throw ValidationException::withMessages([
                    'unit_price' => 'El precio unitario no puede ser negativo.',
                ]);
            }
        });
    }
}
```

### 4.3 Cambios en el modelo `Order`

Nueva relación:

```php
public function auxiliaryLines(): HasMany
{
    return $this->hasMany(OrderAuxiliaryLine::class);
}
```

Nuevo accessor para el resumen del pedido que contempla ambos tipos de línea:

```php
// Subtotal auxiliar (base sin IVA)
public function getAuxiliarySubtotalAttribute(): float
{
    return $this->auxiliaryLines->sum('subtotal');
}

// Total auxiliar (con IVA)
public function getAuxiliaryTotalAttribute(): float
{
    return $this->auxiliaryLines->sum('total');
}
```

El accessor `getSummaryAttribute()` existente se mantiene igual (solo líneas de previsión). Se añade un `getFullSummaryAttribute()` que agrega ambos tipos para el dashboard del pedido.

> 🔧 **Implementación real**: `getFullSummaryAttribute()` existe en el modelo `Order` pero **no está expuesto todavía en `OrderResource`/`OrderDetailsResource` ni en ningún endpoint**. Lo que sí se expone en `OrderDetailsResource` son `auxiliaryLines`, `auxiliarySubtotal` y `auxiliaryTotal` por separado (ver §9). Si se necesita el desglose `seafood`/`auxiliary`/`combined` a nivel de pedido individual desde el frontend, hay que añadirlo explícitamente al Resource.

---

## 5. API Endpoints

### 5.1 CRUD de `auxiliary_products` (nuevo)

```
GET    /api/v2/auxiliary-products             → index (con filtro ?active=true)
POST   /api/v2/auxiliary-products             → store
GET    /api/v2/auxiliary-products/{id}        → show
PATCH  /api/v2/auxiliary-products/{id}        → update
DELETE /api/v2/auxiliary-products/{id}        → destroy (solo si !isInUse())
GET    /api/v2/auxiliary-products/options     → listado mínimo para selects del frontend
```

### 5.2 CRUD de `order_auxiliary_lines` (nuevo)

```
GET    /api/v2/orders/{order}/auxiliary-lines             → index
POST   /api/v2/orders/{order}/auxiliary-lines             → store
PATCH  /api/v2/orders/{order}/auxiliary-lines/{line}      → update
DELETE /api/v2/orders/{order}/auxiliary-lines/{line}      → destroy
```

### 5.3 Validación en store/update de `order_auxiliary_lines`

```php
// StoreOrderAuxiliaryLineRequest
'auxiliary_product_id' => 'nullable|exists:tenant.auxiliary_products,id',
'description'          => 'required_without:auxiliary_product_id|nullable|string|max:500',
'quantity'             => 'required|numeric|gt:0',
'unit'                 => 'required|string|max:50',
'unit_price'           => 'required|numeric|min:0',
'tax_id'               => 'nullable|exists:tenant.taxes,id',
```

### 5.4 Ajuste en la validación de creación de pedido

La regla que exige al menos una línea de previsión se sustituye por: un pedido es válido si tiene al menos una `plannedProductDetail` O al menos una `auxiliaryLine`. En la práctica, la validación suele estar en el frontend; el backend no bloquea la creación de un pedido vacío de palets.

---

## 6. Estadísticas y Dashboard

### 6.1 Principio

> Las estadísticas pesqueras (kg, especies, palets, rentabilidad) son **completamente independientes** de las líneas auxiliares y **nunca las incluyen**.  
> Las estadísticas de facturación/importe pueden mostrar una vista pesquera pura, una vista auxiliar pura, o el **total del negocio combinando ambas**.

### 6.2 Queries existentes — sin cambios necesarios

Ningún query del `OrderStatisticsService` ni `OrderProfitabilityStatsService` toca `order_auxiliary_lines` (la tabla no existía). Por tanto, **no hay riesgo de contaminación y no se requiere añadir filtros a las queries actuales**. Esto es una ventaja directa de mantener entidades separadas.

### 6.3 Nuevo servicio: `AuxiliaryLineStatisticsService`

```php
// app/Services/v2/AuxiliaryLineStatisticsService.php

public static function calculateTotalAmount(string $from, string $to, ...): array
// → { subtotal, tax, total }
// Cálculo: SUM(unit_price * quantity) como subtotal, IVA desde taxes.rate

public static function getAmountStatsComparedToLastYear(string $from, string $to, ...): array
// → { value, subtotal, tax, comparisonValue, comparisonSubtotal, comparisonTax, percentageChange }

public static function getByProduct(string $from, string $to): Collection
// → [{ name, quantity, unit, subtotal }] — ranking de artículos auxiliares vendidos

public static function getByCustomer(string $from, string $to): Collection
// → [{ customer_name, subtotal, total }]

public static function getChartData(string $from, string $to, string $groupBy): Collection
// → [{ date, subtotal, total }] — serie temporal

public static function getTopAuxiliaryProducts(string $from, string $to, int $limit = 10): Collection
// → top N artículos auxiliares por importe vendido
```

### 6.4 Nuevos endpoints de estadísticas

```
GET /api/v2/statistics/auxiliary-lines/total-amount
    ?date_from=&date_to=&customer_id=&salesperson_id=

GET /api/v2/statistics/auxiliary-lines/by-product
    ?date_from=&date_to=

GET /api/v2/statistics/auxiliary-lines/by-customer
    ?date_from=&date_to=

GET /api/v2/statistics/auxiliary-lines/chart-data
    ?date_from=&date_to=&group_by=day|week|month
```

> 🔧 **Implementación real**: los query params se implementaron en `camelCase` (`dateFrom`, `dateTo`, `groupBy`), no en `snake_case`, para ser consistentes con el resto de la API v2. `by-product`/`by-customer`/`chart-data` no aceptan `customer_id`/`salesperson_id` como filtro explícito (el scoping por comercial se aplica automáticamente según el usuario autenticado, igual que en `OrderStatisticsService`). Detalle completo de shapes de respuesta en la guía de frontend.

### 6.5 Endpoint de facturación total del negocio (pesquero + auxiliar)

Para el dashboard con el **total real de ventas del negocio**:

```
GET /api/v2/statistics/orders/total-amount
    ?include_auxiliary=true   ← nuevo parámetro opcional (default: false)
```

Con `include_auxiliary=true` el response incluye:

```json
{
  "seafood": {
    "subtotal": 142000.00,
    "tax": 14916.00,
    "total": 156916.00
  },
  "auxiliary": {
    "subtotal": 3200.00,
    "tax": 272.00,
    "total": 3472.00
  },
  "combined": {
    "subtotal": 145200.00,
    "tax": 15188.00,
    "total": 160388.00
  },
  "percentageChange": ...
}
```

> 🔧 **Implementación real**: el parámetro se llama `includeAuxiliary` (camelCase). Además, `comparisonValue` y `percentageChange` se devuelven anidados dentro de `combined` (`combined.comparisonValue`, `combined.percentageChange`), no en la raíz del objeto. Shape real completo:
> ```json
> {
>   "seafood":   { "subtotal": 142000.00, "tax": 14916.00, "total": 156916.00 },
>   "auxiliary": { "subtotal": 3200.00,   "tax": 272.00,   "total": 3472.00 },
>   "combined": {
>     "subtotal": 145200.00,
>     "tax": 15188.00,
>     "total": 160388.00,
>     "comparisonValue": 148000.00,
>     "percentageChange": 8.37
>   },
>   "range": { "from": "...", "to": "...", "fromPrev": "...", "toPrev": "..." }
> }
> ```

---

## 7. Documentos PDF — Plantillas afectadas

### 7.1 Principio general

Las líneas auxiliares aparecen en la **misma tabla principal de productos** de cada documento, a continuación de todas las líneas pesqueras. No hay segunda tabla ni sección separada, y tampoco hay fila ni separador visual que anuncie el cambio de bloque: las líneas auxiliares se listan correlativas justo después de las pesqueras, como si fueran más filas de la misma tabla. Si el pedido tiene únicamente líneas auxiliares, la tabla empieza directamente con ellas.

```
┌──────────────────────────────────────────────────────────────────────┐
│ Artículo           │ Ud. │ Cant.    │ Precio   │ Importe             │
├──────────────────────────────────────────────────────────────────────┤
│ Pulpo cocido 1kg   │ kg  │ 820,500  │  4,20 €  │  3.446,10 €  (🐟)  │
│ Sepia limpia 400g  │ kg  │ 310,200  │  3,80 €  │  1.178,76 €  (🐟)  │
│ Nieve granulada    │ kg  │ 500,000  │  0,08 €  │     40,00 €  (📦)  │
│ Tarrina 500g       │ ud  │  50,000  │  0,15 €  │      7,50 €  (📦)  │
├──────────────────────────────────────────────────────────────────────┤
│                                    Base imponible:       4.672,36 €  │
│                                    IVA (4%):               186,89 €  │
│                                    TOTAL:                4.859,25 €  │
└──────────────────────────────────────────────────────────────────────┘
```

> 🔧 **Implementación real (revisada tras probar la primera versión)**: la primera versión implementada sí incluía una fila separadora con fondo gris y el texto "Otros artículos" antes de las líneas auxiliares, en las 7 plantillas PDF de §7.3. Se eliminó esa fila en todas ellas porque rompía la lectura de la tabla como un listado único y correlativo de artículos del pedido. Las plantillas de email (§8) mantienen su propio bloque separado con el encabezado "Otros artículos..." porque ahí sí es una tabla independiente, no una fila dentro de la tabla de productos.

### 7.2 Columnas en líneas auxiliares

Cada plantilla tiene sus propias columnas (algunas incluyen GS1, lotes, peso neto, precio...). Las líneas auxiliares rellenan solo las columnas que les aplican; el resto queda vacío o con `—`.

| Campo en plantilla | Línea pesquera | Línea auxiliar |
|--------------------|---------------|----------------|
| Descripción / Artículo | `product.name` | `effectiveDescription` |
| Especie / Zona FAO | `species.name`, `captureZone` | — (vacío) |
| GS1 / GTIN caja | `boxGtin` | — (vacío) |
| Lote | `lot` | — (vacío) |
| Nº cajas | Conteo de cajas | — (vacío) |
| Unidad | kg (implícito) | `unit` del catálogo o de la línea |
| Cantidad / Peso neto | `netWeight` de cajas | `quantity` |
| Precio unitario | `unit_price` | `unit_price` |
| Importe | `price × kg` | `unit_price × quantity` |

### 7.3 Inventario de plantillas

| Plantilla | Muestra líneas de producto | Incluye aux. | Observaciones |
|-----------|---------------------------|--------------|---------------|
| `loading_note.blade.php` | Sí — `productsWithLotsDetails` | **Sí** | Nota de carga. Añadir filas aux. al final de la tabla |
| `valued_loading_note.blade.php` | Sí — `productDetails` (con precio) | **Sí** | Nota valorada. Añadir filas aux. con precio e importe |
| `order_sheet.blade.php` | Sí — `productsWithLotsDetails` | **Sí** | Hoja de pedido |
| `order_sheets_combined.blade.php` | Sí | **Sí** | Hojas combinadas; misma lógica |
| `restricted_loading_note.blade.php` | Sí | **Sí** | Nota restringida (sin precios). Aux. sin columnas de precio |
| `order_confirmation.blade.php` | Sí — `productsWithLotsDetails` | **Sí** | Confirmación de pedido |
| `transport_pickup_request.blade.php` | Sí | **Sí** | Solicitud de recogida de transporte |
| `order_signs.blade.php` | No (letreros, no tabla) | No | No aplica |
| `restricted_order_signs.blade.php` | No | No | No aplica |
| `CMR.blade.php` | No (documento de transporte) | **No** | **Decisión consciente**: el CMR no incluye auxiliares porque no tenemos datos de bultos físicos. El operario los anota a mano si aplica. Revisable en el futuro añadiendo `packages` a las líneas auxiliares (Opción A documentada) |
| `maquilador_cmr.blade.php` | No | No | Solo datos de maquilador |
| `maquilador_order_signs.blade.php` | No | No | No aplica |
| `incident.blade.php` | No | No | No aplica |
| `pallet_expedition_labels.blade.php` | No (etiquetas GS1) | No | No aplica |

### 7.4 Cambio en `OrderPDFService`

Antes de renderizar cualquier vista, el servicio debe asegurar que la relación `auxiliaryLines` esté cargada:

```php
// En generateDocument(), antes de view()->render()
$order->loadMissing([
    'auxiliaryLines.auxiliaryProduct',
    'auxiliaryLines.tax',
]);
```

De este modo las plantillas pueden hacer `$entity->auxiliaryLines` sin N+1.

> 🔧 **Implementación real — alcance ampliado a exports contables**: además de las 7 plantillas PDF listadas arriba, las líneas auxiliares se añadieron también a las exportaciones Excel contables existentes (`A3ERPOrderSalesDeliveryNoteExport`, `A3ERPOrdersSalesDeliveryNotesExport`, `A3ERP2OrderSalesDeliveryNoteExport`, `A3ERP2OrdersSalesDeliveryNotesExport`, `FacilcomOrderSalesDeliveryNoteExport`, `FacilcomOrdersSalesDeliveryNotesExport`), individuales y masivas. Esto no estaba en el alcance original de este documento, pero es necesario para que las ventas de artículos auxiliares también se declaren a contabilidad/ERP externo. Se implementó mediante una clase de soporte compartida `App\Support\OrderErpExportLines` (mapea cada `OrderAuxiliaryLine` a una fila con el formato de cada exportador; usa `auxiliaryProduct.reference` como código de artículo ERP si existe, o placeholder `-` en líneas ad-hoc sin catálogo). No requiere cambios en frontend: es transparente para los botones de exportación ya existentes. Sección [12](#12-plan-de-implementación-orden-sugerido) actualizada con esta tarea adicional.

---

## 8. Emails — Cuerpos afectados

### 8.1 Criterio de inclusión

Se incluyen líneas auxiliares en el cuerpo del email cuando:
- El destinatario necesita saber qué se le está enviando (cliente, comercial).
- El email ya muestra un resumen de contenido del pedido.

Los emails de notificación pura (solo texto de "pedido enviado") no necesitan desglose de líneas.

### 8.2 Inventario de plantillas de email

| Plantilla | Destinatario | Muestra artículos hoy | Incluye aux. | Cómo |
|-----------|-------------|----------------------|--------------|------|
| `shipped.blade.php` | Cliente | No (solo texto multilingual) | **Sí** | Añadir tabla resumen con artículos pesqueros + aux. si los hay |
| `commercial.blade.php` | Comercial | No (solo texto) | **Sí** | Añadir tabla resumen completa |
| `transport_details.blade.php` | Transportista | Sí — tabla de palets | **No** | **Decisión consciente**: el transportista solo necesita datos de palets (pesqueros). Los artículos auxiliares no tienen bultos físicos registrados en el sistema, por lo que no se incluyen en este email. Sin cambios. |
| `maquilador.blade.php` | Maquilador | Sí — tabla de palets | No | El maquilador solo gestiona producto pesquero; no necesita ver artículos auxiliares |
| `generic.blade.php` | Varios | No (datos básicos) | **Sí** | Añadir lista de artículos aux. en "Detalles del Pedido" si los hay |

### 8.3 Estructura del bloque auxiliar en emails

Donde aplica, se añade un bloque condicional con la directiva `@if ($order->auxiliaryLines->isNotEmpty())`:

```blade
@if ($order->auxiliaryLines->isNotEmpty())
**Otros artículos incluidos en este envío:**

<x-mail::table>
| Artículo | Cantidad | Ud. | Precio unit. | Importe |
|:---------|:--------:|:---:|:------------:|--------:|
@foreach ($order->auxiliaryLines as $line)
| {{ $line->effective_description }} | {{ number_format($line->quantity, 3, ',', '.') }} | {{ $line->unit }} | {{ number_format($line->unit_price, 2, ',', '.') }} € | {{ number_format($line->subtotal, 2, ',', '.') }} € |
@endforeach
</x-mail::table>
@endif
```

El email al transportista (`transport_details.blade.php`) **no incluye líneas auxiliares** — sin cambios en esa plantilla.

### 8.4 Carga de la relación en el `OrderMailerService`

El servicio construye el mailable pasando el modelo `$order`. Se debe asegurar la carga de la relación antes de enviar:

```php
// En sendDocument() y sendStandardDocuments(), antes de crear el mailable
$order->loadMissing([
    'auxiliaryLines.auxiliaryProduct',
    'auxiliaryLines.tax',
]);
```

---

## 9. Resource de respuesta

### `OrderAuxiliaryLineResource`

```json
{
  "id": 1,
  "orderId": 42,
  "auxiliaryProduct": { "id": 3, "name": "Tarrina 500g", "reference": "ENV-T500", "unit": "ud" },
  "description": null,
  "effectiveDescription": "Tarrina 500g",
  "quantity": "50.000",
  "unit": "ud",
  "unitPrice": "0.1500",
  "tax": { "id": 1, "name": "IVA 4%", "rate": 4 },
  "subtotal": 7.50,
  "total": 7.80
}
```

### Cambio en `OrderResource`

Se añade `auxiliaryLines` (cargadas con `with('auxiliaryLines.auxiliaryProduct', 'auxiliaryLines.tax')`):

```json
{
  "id": 42,
  "plannedProductDetails": [...],
  "auxiliaryLines": [...],
  "auxiliarySubtotal": 7.50,
  "auxiliaryTotal": 7.80
}
```

---

## 10. Hoja de Ruta y Visión de Futuro

### Fase actual (implementación inmediata)
- Catálogo `auxiliary_products` con CRUD completo
- Entidad `order_auxiliary_lines` dentro del pedido existente
- Estadísticas auxiliares propias sin interferir con las pesqueras
- PDF con secciones condicionales

### Próxima fase (cuando el volumen lo justifique)
- Módulo propio "Ventas Auxiliares" con sus propios pedidos simplificados
- Dashboard específico con métricas de artículos auxiliares
- Posibilidad de exportar a Excel las ventas de artículos auxiliares

### Módulo futuro: Comisiones/ventas como servicio
Las líneas auxiliares son el semillero. Un `auxiliary_product` de tipo "servicio" (gestión comercial, intermediación) puede evolucionar en:
- `ServiceSale` con reglas de liquidación
- Vinculado a proveedor, porcentaje de comisión, periodo de liquidación
- Extracción limpia desde la estructura actual

---

## 11. Impacto por Bloque

| Bloque | Cambio | Impacto |
|--------|--------|---------|
| A.2 Ventas — Orders | Nueva relación `auxiliaryLines`, accessor `fullSummary` | Bajo-medio |
| A.2 Ventas — OrderAuxiliaryLine | Nueva entidad, CRUD, validaciones | Medio |
| A.7/A.8 Productos y Catálogos | Nuevo `AuxiliaryProduct` independiente | Bajo |
| A.12 Estadísticas | **Sin cambios en queries existentes.** Solo nuevos endpoints auxiliares | Bajo |
| A.15 Documentos PDF | Sección condicional en `OrderPDFService` | Bajo |

---

## 12. Plan de Implementación (orden sugerido)

| # | Tarea | Archivos afectados |
|---|-------|--------------------|
| 1 | Migración: crear `auxiliary_products` | `database/migrations/companies/` |
| 2 | Migración: crear `order_auxiliary_lines` | `database/migrations/companies/` |
| 3 | Modelo `AuxiliaryProduct` + Policy | `Models/`, `Policies/` |
| 4 | Modelo `OrderAuxiliaryLine` | `Models/` |
| 5 | Relación `Order::auxiliaryLines()` + accessors | `Models/Order.php` |
| 6 | Controller + Requests + Resource para `auxiliary_products` | `Controllers/v2/`, `Requests/v2/`, `Resources/v2/` |
| 7 | Controller + Requests + Resource para `order_auxiliary_lines` | ídem |
| 8 | Registrar rutas | `routes/api.php` |
| 9 | `AuxiliaryLineStatisticsService` + controller + rutas | `Services/v2/`, `Controllers/v2/` |
| 10 | `loadMissing` en `OrderPDFService::generateDocument()` | `Services/OrderPDFService.php` |
| 11 | Actualizar 7 plantillas PDF (`loading_note`, `valued_loading_note`, `order_sheet`, `order_sheets_combined`, `restricted_loading_note`, `order_confirmation`, `transport_pickup_request`) | `resources/views/pdf/v2/orders/` |
| 12 | `loadMissing` en `OrderMailerService` | `Services/OrderMailerService.php` |
| 13 | Actualizar 3 plantillas de email (`shipped`, `commercial`, `generic`) | `resources/views/emails/orders/` |
| 14 | Actualizar `OrderResource` con `auxiliaryLines` | `Resources/v2/OrderResource.php` |
| 15 | Feature tests | `tests/Feature/` |
| 16 🔧 | Añadir líneas auxiliares a exports contables A3ERP/A3ERP2/Facilcom vía `OrderErpExportLines` (no en el alcance original; añadido en implementación) | `app/Support/OrderErpExportLines.php`, `app/Exports/v2/*DeliveryNote*Export.php` |

---

*Documento v2 — revisado el 2026-07-01 tras decisión de mantener entidad propia y no mezclar con `order_planned_product_details`.*
*Anotado el 2026-07-01 (🔧) tras la implementación real: nombres de parámetros en camelCase, shape real de `combined` en estadísticas, alcance ampliado a exports ERP/contables, y estado de `fullSummary`. Guía de consumo para frontend: [27-guia-frontend-lineas-auxiliares.md](./27-guia-frontend-lineas-auxiliares.md).*
