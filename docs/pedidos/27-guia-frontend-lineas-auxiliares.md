# Guía Frontend: Líneas Auxiliares (Productos No Pesqueros)

## 📋 Resumen

Esta guía documenta la implementación **ya realizada en backend** de las líneas auxiliares (nieve, envases, palets, servicios puntuales) descritas en [26-lineas-auxiliares-productos-no-pesqueros.md](./26-lineas-auxiliares-productos-no-pesqueros.md). Está orientada al equipo de frontend y describe el comportamiento real de la API tal como quedó implementada.

Dos entidades nuevas:
- **`AuxiliaryProduct`** — catálogo opcional de artículos no pesqueros (nieve, tarrinas, palets...).
- **`OrderAuxiliaryLine`** — línea de venta directa dentro de un pedido, con o sin producto de catálogo.

Un pedido puede tener solo líneas pesqueras (`plannedProductDetails`), solo líneas auxiliares, o ambas (pedido mixto). El backend **no bloquea** la creación de un pedido sin líneas pesqueras.

---

## 1. Modelo de Datos

### `AuxiliaryProduct` (catálogo)

| Campo | Tipo | Notas |
|-------|------|-------|
| `id` | number | |
| `name` | string | Único en el tenant |
| `reference` | string\|null | Código interno/comercial |
| `unit` | string | Default `'ud'`; libre (kg, ud, saco, palet, servicio...) |
| `defaultPrice` | number\|null | Precio orientativo, 4 decimales |
| `notes` | string\|null | |
| `active` | boolean | Default `true` |
| `createdAt` / `updatedAt` | string | |

### `OrderAuxiliaryLine` (línea de pedido)

| Campo | Tipo | Notas |
|-------|------|-------|
| `id` | number | |
| `orderId` | number | |
| `auxiliaryProduct` | object\|null | Presente si la línea viene de catálogo |
| `description` | string\|null | Libre; requerida si no hay `auxiliaryProductId` |
| `effectiveDescription` | string | **Calculado**: `auxiliaryProduct.name` si existe, si no `description` |
| `quantity` | string numérico | Decimal, 3 decimales (ej. `"500.000"`) |
| `unit` | string | |
| `unitPrice` | string numérico | Decimal, 4 decimales |
| `tax` | object\|null | `{ id, name, rate }` |
| `subtotal` | number | **Calculado**: `unitPrice * quantity`, redondeado a 4 decimales |
| `total` | number | **Calculado**: `subtotal * (1 + tax.rate/100)` |

> ⚠️ `quantity` y `unitPrice` llegan como **strings numéricos** en el JSON (cast Eloquent `decimal:3`/`decimal:4`), no como `number`. Convertir con `Number(...)` o `parseFloat(...)` antes de operar si es necesario, aunque para mostrar suele bastar usarlos directamente en un `<input type="number">`.

Una línea auxiliar **siempre necesita** `auxiliaryProductId` **o** `description` (al menos uno). Si se manda `auxiliaryProductId`, el nombre efectivo se toma del catálogo; `description` y `unit` propios de la línea son igualmente editables y no se sobrescriben automáticamente al elegir un producto — es responsabilidad del frontend rellenar `unit`/`unitPrice` con los valores del catálogo (`unit`, `defaultPrice`) al seleccionar un artículo en el selector, si se quiere ese autocompletado.

---

## 2. Catálogo de Artículos Auxiliares — `/api/v2/auxiliary-products`

Requiere `X-Tenant` + `Authorization: Bearer <token>` como el resto de la API v2.

### Listado (paginado)

```
GET /api/v2/auxiliary-products?perPage=12&active=true&name=nieve
```

Filtros soportados (todos opcionales, se combinan con AND):
- `id`, `ids[]` (array de ids, **no** string separado por comas)
- `name` — `LIKE %valor%`
- `reference` — `LIKE %valor%`
- `active` — `true`/`false`

Respuesta — colección paginada estándar de Laravel:
```json
{
  "data": [
    {
      "id": 3,
      "name": "Tarrina 500g",
      "reference": "ENV-T500",
      "unit": "ud",
      "defaultPrice": "0.1500",
      "notes": null,
      "active": true,
      "createdAt": "2026-07-01T10:00:00.000000Z",
      "updatedAt": "2026-07-01T10:00:00.000000Z"
    }
  ],
  "links": { "first": "...", "last": "...", "prev": null, "next": null },
  "meta": { "current_page": 1, "per_page": 12, "total": 1, "..." : "..." }
}
```

No autorizado (rol `comercial`) → **403** en `viewAny`/`view` (el catálogo completo NO es visible para comerciales; ver `options` más abajo para el caso de selects).

### Opciones para selects (sin paginar)

```
GET /api/v2/auxiliary-products/options
```

Devuelve **array plano** (no envuelto en `data`), solo artículos `active = true`, ordenados por nombre:
```json
[
  { "id": 3, "name": "Tarrina 500g", "reference": "ENV-T500", "unit": "ud", "defaultPrice": "0.1500" }
]
```
Este endpoint **sí está disponible para cualquier rol autenticado**, incluido `comercial` — úsalo para el selector de artículo dentro del formulario de línea auxiliar en el pedido, aunque el usuario no tenga acceso a la pantalla de mantenimiento del catálogo.

### Crear

```
POST /api/v2/auxiliary-products
{
  "name": "Nieve granulada",
  "reference": "NIE-01",
  "unit": "kg",
  "defaultPrice": 0.08,
  "notes": "Producción propia",
  "active": true
}
```
- `name` requerido, único, máx 255.
- `unit` requerido, máx 50.
- `reference`, `notes`, `active` opcionales (`active` default `true`).
- `defaultPrice` opcional, numérico ≥ 0.

`201` con `{ "message": "...", "data": { ...AuxiliaryProduct... } }`.

### Actualizar

```
PATCH /api/v2/auxiliary-products/{id}
{ "defaultPrice": 0.09, "unit": "kg" }
```
Todos los campos son `sometimes` (parciales). `200` con el mismo shape que crear.

### Eliminar (individual)

```
DELETE /api/v2/auxiliary-products/{id}
```
- `200` si no está en uso.
- **400** si el artículo tiene líneas de pedido asociadas:
```json
{
  "message": "No se puede eliminar el artículo auxiliar porque está en uso",
  "details": "...",
  "userMessage": "No se puede eliminar el artículo auxiliar porque está siendo utilizado en pedidos."
}
```
Mostrar `userMessage` al usuario. La UI de catálogo debería deshabilitar o avisar antes de intentar borrar un artículo con uso conocido (aunque el backend ya lo protege).

### Eliminar (múltiple)

```
DELETE /api/v2/auxiliary-products
{ "ids": [3, 5, 8] }
```
Si **alguno** está en uso, la operación completa se rechaza con 400 y el mensaje lista los nombres afectados; no hay borrado parcial.

---

## 3. Líneas Auxiliares de un Pedido — `/api/v2/orders/{order}/auxiliary-lines`

Anidadas al pedido. La autorización se resuelve contra el **pedido** (`Policy::update` de `Order`), no contra una policy propia de la línea — si el usuario puede editar el pedido, puede gestionar sus líneas auxiliares.

### Listar

```
GET /api/v2/orders/{orderId}/auxiliary-lines
```
```json
{ "data": [ { "id": 1, "orderId": 42, "auxiliaryProduct": {...}, ... } ] }
```
No paginado (se asume que un pedido tiene pocas líneas auxiliares).

### Crear

```
POST /api/v2/orders/{orderId}/auxiliary-lines
{
  "auxiliaryProductId": 3,      // o null
  "description": null,          // requerido si no hay auxiliaryProductId
  "quantity": 50,
  "unit": "ud",
  "unitPrice": 0.15,
  "taxId": 1                    // opcional, null = sin IVA (rate 0)
}
```
Validaciones (mensajes en español, ya traducidos por el backend):
- `quantity` > 0 → si no: *"La cantidad debe ser mayor que cero."*
- `unitPrice` ≥ 0 → si no: *"El precio unitario no puede ser negativo."*
- Sin `auxiliaryProductId` **ni** `description` → *"Se requiere un artículo del catálogo o una descripción libre."*

`201` con `{ "message": "Línea auxiliar creada correctamente.", "data": {...OrderAuxiliaryLine...} }`.

### Actualizar

```
PATCH /api/v2/orders/{orderId}/auxiliary-lines/{lineId}
{ "quantity": 20, "unitPrice": 3 }
```
Todos los campos opcionales (`sometimes`); igual set de validaciones que en creación cuando el campo está presente.

### Eliminar

```
DELETE /api/v2/orders/{orderId}/auxiliary-lines/{lineId}
```

### Errores comunes en este bloque

- **404** si `lineId` no pertenece a `orderId` (protección explícita, no solo un `findOrFail` genérico).
- **403** si el usuario no puede editar el pedido (p. ej. comercial sobre pedido de otro comercial).

---

## 4. Integración en el Recurso del Pedido

`GET /api/v2/orders/{id}` (detalle) incluye ahora, cuando la relación está cargada (siempre lo está en el `show`):

```json
{
  "id": 42,
  "plannedProductDetails": [ ... ],       // null si la relación no está cargada
  "auxiliaryLines": [ ... ],              // [] si no está cargada (nunca null)
  "auxiliarySubtotal": 7.5,               // suma de subtotales de auxiliaryLines
  "auxiliaryTotal": 7.8,                  // suma de totales (con IVA) de auxiliaryLines
  "...": "resto de campos sin cambios"
}
```

> Nota para el frontend: `auxiliaryLines` es **siempre un array** (nunca `null`) aunque la relación no esté cargada — a diferencia de `plannedProductDetails`, que puede ser `null`. Puedes hacer `.map()`/`.reduce()` sobre `auxiliaryLines` sin comprobación previa de nulidad; con `plannedProductDetails` sigue siendo necesaria.

No hay un endpoint de "resumen combinado" por pedido individual (`fullSummary`) expuesto vía API — ese accessor existe en el modelo (`Order::getFullSummaryAttribute()`) pero **no aparece en `OrderDetailsResource` ni en ningún otro Resource actualmente**. Si el frontend necesita el desglose seafood/auxiliary/combined a nivel de un pedido concreto, calcularlo en cliente a partir de `summary` (si existe en el payload que uses) + `auxiliarySubtotal`/`auxiliaryTotal`, o pedir que se añada al Resource.

---

## 5. Manejo de Errores (formato estándar del backend)

Todas las respuestas de error siguen el handler global del proyecto:

| Código | Caso | Shape |
|--------|------|-------|
| 422 | Validación (Form Request / modelo) | `{ message, userMessage, code, errors: { campo: [mensajes] } }` |
| 403 | Policy deniega la acción | `{ message, userMessage, error }` |
| 401 | No autenticado | `{ message, userMessage }` |
| 400 | Regla de negocio (ej. borrar artículo en uso) | `{ message, details, userMessage }` |
| 404 | Recurso no encontrado / línea no pertenece al pedido | `{ message }` (Laravel estándar) |

Usa siempre `userMessage` para mostrar al usuario final; `message`/`errors`/`details` son para logs o debug.

---

## 6. Estadísticas de Líneas Auxiliares (nuevos endpoints)

Todos requieren solo `viewAny` sobre `Order` (mismo nivel de permiso que las estadísticas pesqueras existentes). Los parámetros de fecha son **camelCase** (`dateFrom`/`dateTo`), no `date_from`/`date_to`.

### Total con comparación año anterior

```
GET /api/v2/statistics/auxiliary-lines/total-amount?dateFrom=2026-06-01&dateTo=2026-06-30
```
```json
{
  "value": 3472.00,
  "subtotal": 3200.00,
  "tax": 272.00,
  "comparisonValue": 2900.00,
  "comparisonSubtotal": 2700.00,
  "comparisonTax": 200.00,
  "percentageChange": 19.72,
  "range": { "from": "...", "to": "...", "fromPrev": "...", "toPrev": "..." }
}
```

### Ranking por artículo

```
GET /api/v2/statistics/auxiliary-lines/by-product?dateFrom=...&dateTo=...
```
```json
[
  { "name": "Nieve granulada", "quantity": 500, "unit": "kg", "subtotal": 40.00 }
]
```
Array plano ordenado por `subtotal` descendente. `name` agrupa por `auxiliaryProduct.name` o, si no hay catálogo, por `description` de la línea — dos líneas con la misma descripción libre y misma unidad se agregan juntas.

### Por cliente

```
GET /api/v2/statistics/auxiliary-lines/by-customer?dateFrom=...&dateTo=...
```
```json
[
  { "customerName": "Pescados García SL", "subtotal": 120.00, "total": 124.80 }
]
```

### Serie temporal (gráfico)

```
GET /api/v2/statistics/auxiliary-lines/chart-data?dateFrom=...&dateTo=...&groupBy=day
```
`groupBy` acepta `day` (default) | `week` | `month`.
```json
[
  { "date": "2026-06-15", "subtotal": 40.00, "total": 41.60 }
]
```

> Todos estos endpoints solo contemplan pedidos en estado **cerrado** (`finished`/`incident`), igual que las estadísticas pesqueras equivalentes — un pedido `pending` con líneas auxiliares no aparece todavía en estas cifras.
>
> Si el usuario autenticado tiene rol `comercial`, todos estos endpoints se filtran automáticamente a sus propios pedidos (igual que en `OrderStatisticsService`).

---

## 7. Vista de Negocio Combinada (pesquero + auxiliar)

El endpoint **ya existente** de total de ventas ganó un parámetro opcional:

```
GET /api/v2/statistics/orders/total-amount?dateFrom=...&dateTo=...&includeAuxiliary=true
```

Sin `includeAuxiliary` (o `false`), el shape de respuesta **no cambia** respecto al comportamiento actual (solo pesquero). Con `includeAuxiliary=true`:

```json
{
  "seafood":   { "subtotal": 142000.00, "tax": 14916.00, "total": 156916.00 },
  "auxiliary": { "subtotal": 3200.00,   "tax": 272.00,   "total": 3472.00 },
  "combined": {
    "subtotal": 145200.00,
    "tax": 15188.00,
    "total": 160388.00,
    "comparisonValue": 148000.00,
    "percentageChange": 8.37
  },
  "range": { "from": "...", "to": "...", "fromPrev": "...", "toPrev": "..." }
}
```

`percentageChange` y `comparisonValue` van anidados dentro de `combined` (`combined.percentageChange`, `combined.comparisonValue`).

---

## 8. PDF y Emails — Sin acción requerida en frontend

Las líneas auxiliares se añaden automáticamente en backend a las plantillas PDF (`loading_note`, `valued_loading_note`, `order_sheet`, `order_sheets_combined`, `restricted_loading_note`, `order_confirmation`, `transport_pickup_request`) y a los emails (`shipped`, `commercial`, `generic`) que ya se generan hoy desde el frontend (botones de "generar PDF" / "enviar documentos"). **No hay nuevos endpoints ni parámetros que el frontend deba enviar** para esto — si el pedido tiene líneas auxiliares, aparecerán solas en el documento/email generado con los endpoints actuales.

Adicionalmente, las líneas auxiliares también se incluyen en las exportaciones contables Excel existentes (A3ERP, A3ERP2, Facilcom, individuales y masivas) — tampoco requiere cambios en frontend, es transparente para los botones de exportación ya existentes.

No se incluyen en: `CMR`, `transport_details` (email transportista), `maquilador_*` — decisión consciente, esos documentos son solo de circuito pesquero/transporte de palets físicos.

---

## 9. Checklist de Implementación Frontend

- [ ] Pantalla de mantenimiento de catálogo `AuxiliaryProduct` (listado paginado + CRUD + borrado múltiple), visible solo para roles ≠ `comercial`.
- [ ] Selector de artículo auxiliar en el formulario de línea, usando `/auxiliary-products/options` (disponible para todos los roles, incluido `comercial`).
- [ ] Formulario de línea auxiliar dentro del pedido: soporta artículo de catálogo **o** descripción libre (al menos uno), cantidad, unidad, precio unitario, IVA opcional (select desde `/taxes/options`).
- [ ] Al elegir un artículo de catálogo en el selector, autocompletar (pero permitir editar) `unit` y `unitPrice` con `unit`/`defaultPrice` del artículo — el backend no lo hace automáticamente.
- [ ] Mostrar bloque "Otros artículos" en el detalle del pedido junto a las líneas pesqueras, con `auxiliarySubtotal`/`auxiliaryTotal` en el resumen de totales.
- [ ] Permitir crear un pedido sin líneas pesqueras (solo auxiliares) — el backend ya lo admite.
- [ ] Dashboard/estadísticas: nuevos widgets con los 4 endpoints de `/statistics/auxiliary-lines/*`, y toggle "incluir auxiliares" en el widget de ventas totales que ya existe (`includeAuxiliary=true`).
- [ ] Manejo de errores: leer siempre `userMessage`; distinguir 400 (regla de negocio, ej. artículo en uso) de 422 (validación de campos).
