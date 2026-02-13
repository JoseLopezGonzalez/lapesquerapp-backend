# Recepciones y Despachos - Liquidación de Proveedores

## ⚠️ Estado de la API
- **v2**: Versión en desarrollo (este documento)

---

## 📋 Visión General

El módulo de **Liquidación de Proveedores** permite generar un documento tipo albarán que consolida todas las recepciones de materia prima y salidas de cebo de un proveedor dentro de un rango de fechas específico. Este documento sirve como resumen contable y logístico para la liquidación con proveedores.

**Concepto clave**: Una liquidación agrupa:
- **Recepciones** (RawMaterialReception): Entradas de materia prima del proveedor
- **Salidas de Cebo** (CeboDispatch): Salidas de cebo al proveedor

Las salidas de cebo que coincidan con recepciones (mismo proveedor, fechas cercanas) se mostrarán agrupadas junto a la recepción correspondiente.

---

## 🎯 Casos de Uso

1. **Listar proveedores con actividad**: Filtrar por rango de fechas para ver qué proveedores tienen recepciones o salidas de cebo
2. **Ver liquidación detallada**: Obtener el desglose completo de recepciones y salidas de cebo de un proveedor
3. **Generar PDF de liquidación**: Crear un documento PDF imprimible con el formato de albarán

---

## 🔌 Endpoints

### 1. Listar Proveedores con Actividad

**Endpoint**: `GET /v2/supplier-liquidations/suppliers`

**Descripción**: Devuelve un listado de proveedores que tienen recepciones o salidas de cebo dentro del rango de fechas especificado.

**Query Parameters**:
- `dates[start]` (required): Fecha de inicio (formato: `YYYY-MM-DD`)
- `dates[end]` (required): Fecha de fin (formato: `YYYY-MM-DD`)

**Ejemplo de Request**:
```
GET /v2/supplier-liquidations/suppliers?dates[start]=2024-01-01&dates[end]=2024-01-31
```

**Ejemplo de Response**:
```json
{
  "data": [
    {
      "id": 1,
      "name": "Proveedor ABC",
      "receptions_count": 5,
      "dispatches_count": 3,
      "total_receptions_weight": 1250.50,
      "total_dispatches_weight": 450.25,
      "total_receptions_amount": 12500.00,
      "total_dispatches_amount": 4500.00
    },
    {
      "id": 2,
      "name": "Proveedor XYZ",
      "receptions_count": 3,
      "dispatches_count": 0,
      "total_receptions_weight": 850.75,
      "total_dispatches_weight": 0,
      "total_receptions_amount": 8500.00,
      "total_dispatches_amount": 0
    }
  ]
}
```

**Campos de Response**:
- `id`: ID del proveedor
- `name`: Nombre del proveedor
- `receptions_count`: Número de recepciones en el rango
- `dispatches_count`: Número de salidas de cebo en el rango
- `total_receptions_weight`: Peso total de recepciones (kg)
- `total_dispatches_weight`: Peso total de salidas de cebo (kg)
- `total_receptions_amount`: Importe total de recepciones (€)
- `total_dispatches_amount`: Importe total de salidas de cebo (€)

**Lógica de Filtrado**:
- Un proveedor aparece en la lista si tiene **al menos una recepción O una salida de cebo** dentro del rango de fechas
- Las fechas se comparan con el campo `date` de `raw_material_receptions` y `cebo_dispatches`

---

### 2. Obtener Liquidación Detallada de un Proveedor

**Endpoint**: `GET /v2/supplier-liquidations/{supplierId}/details`

**Descripción**: Devuelve el desglose completo de recepciones y salidas de cebo de un proveedor, agrupadas y ordenadas por fecha.

**Query Parameters**:
- `dates[start]` (required): Fecha de inicio (formato: `YYYY-MM-DD`)
- `dates[end]` (required): Fecha de fin (formato: `YYYY-MM-DD`)

**Ejemplo de Request**:
```
GET /v2/supplier-liquidations/1/details?dates[start]=2024-01-01&dates[end]=2024-01-31
```

**Ejemplo de Response**:
```json
{
  "supplier": {
    "id": 1,
    "name": "Proveedor ABC",
    "contact_person": "Juan Pérez",
    "phone": "+34 123 456 789",
    "address": "Calle Principal 123"
  },
  "date_range": {
    "start": "2024-01-01",
    "end": "2024-01-31"
  },
  "items": [
    {
      "type": "reception",
      "id": 101,
      "date": "2024-01-15",
      "reception": {
        "id": 101,
        "date": "2024-01-15",
        "notes": "Recepción normal",
        "products": [
          {
            "id": 1,
            "product": {
              "id": 10,
              "name": "Atún Rojo",
              "code": "ATR001"
            },
            "lot": "150124FAO01REC",
            "net_weight": 250.50,
            "price": 10.50,
            "amount": 2630.25,
            "boxes": 5
          }
        ],
        "declared_total_net_weight": 250.00,
        "declared_total_amount": 2625.00,
        "calculated_total_net_weight": 250.50,
        "calculated_total_amount": 2630.25,
        "average_price": 10.50
      },
      "related_dispatches": [
        {
          "id": 201,
          "date": "2024-01-16",
          "products": [
            {
              "id": 1,
              "product": {
                "id": 10,
                "name": "Atún Rojo",
                "code": "ATR001"
              },
              "net_weight": 50.00,
              "price": 10.50,
              "amount": 525.00
            }
          ],
          "total_net_weight": 50.00,
          "total_amount": 525.00
        }
      ]
    },
    {
      "type": "dispatch",
      "id": 202,
      "date": "2024-01-20",
      "dispatch": {
        "id": 202,
        "date": "2024-01-20",
        "notes": "Salida de cebo sin recepción asociada",
        "products": [
          {
            "id": 2,
            "product": {
              "id": 11,
              "name": "Sardina",
              "code": "SAR001"
            },
            "net_weight": 100.00,
            "price": 8.00,
            "amount": 800.00
          }
        ],
        "total_net_weight": 100.00,
        "total_amount": 800.00
      },
      "related_reception": null
    }
  ],
  "summary": {
    "total_receptions": 5,
    "total_dispatches": 3,
    "total_receptions_weight": 1250.50,
    "total_dispatches_weight": 450.25,
    "total_receptions_amount": 12500.00,
    "total_dispatches_amount": 4500.00,
    "total_declared_weight": 1250.00,
    "total_declared_amount": 12475.00,
    "net_amount": 8000.00
  }
}
```

**Estructura de Response**:

#### Supplier
- `id`: ID del proveedor
- `name`: Nombre del proveedor
- `contact_person`: Persona de contacto
- `phone`: Teléfono
- `address`: Dirección

#### Items
Array de items ordenados por fecha. Cada item puede ser:
- **Tipo "reception"**: Una recepción con sus salidas de cebo relacionadas (si las hay)
- **Tipo "dispatch"**: Una salida de cebo sin recepción relacionada

**Item tipo "reception"**:
- `type`: "reception"
- `id`: ID de la recepción
- `date`: Fecha de la recepción
- `reception`: Objeto con los datos de la recepción
  - `id`: ID de la recepción
  - `date`: Fecha
  - `notes`: Notas
  - `products`: Array de productos de la recepción
    - `id`: ID del producto de recepción
    - `product`: Datos del producto
    - `lot`: Lote
    - `net_weight`: Peso neto (kg)
    - `price`: Precio por kg
    - `amount`: Importe (net_weight * price)
    - `boxes`: Número de cajas
  - `declared_total_net_weight`: Peso total declarado (kg)
  - `declared_total_amount`: Importe total declarado (€)
  - `calculated_total_net_weight`: Peso total calculado (suma de productos)
  - `calculated_total_amount`: Importe total calculado (suma de productos)
  - `average_price`: Precio medio (calculated_total_amount / calculated_total_net_weight)
- `related_dispatches`: Array de salidas de cebo relacionadas (mismo proveedor, fecha dentro de ±7 días)

**Item tipo "dispatch"**:
- `type`: "dispatch"
- `id`: ID de la salida de cebo
- `date`: Fecha de la salida
- `dispatch`: Objeto con los datos de la salida
  - `id`: ID de la salida
  - `date`: Fecha
  - `notes`: Notas
  - `products`: Array de productos de la salida
    - `id`: ID del producto de salida
    - `product`: Datos del producto
    - `net_weight`: Peso neto (kg)
    - `price`: Precio por kg
    - `amount`: Importe (net_weight * price)
  - `total_net_weight`: Peso total (suma de productos)
  - `total_amount`: Importe total (suma de productos)
- `related_reception`: null (no hay recepción relacionada)

#### Summary
Resumen global de toda la liquidación:
- `total_receptions`: Número total de recepciones
- `total_dispatches`: Número total de salidas de cebo
- `total_receptions_weight`: Peso total de recepciones (kg)
- `total_dispatches_weight`: Peso total de salidas de cebo (kg)
- `total_receptions_amount`: Importe total de recepciones (€)
- `total_dispatches_amount`: Importe total de salidas de cebo (€)
- `total_declared_weight`: Peso total declarado en recepciones (kg)
- `total_declared_amount`: Importe total declarado en recepciones (€)
- `net_amount`: Importe neto (total_receptions_amount - total_dispatches_amount)

**Lógica de Agrupación**:
- Las salidas de cebo se consideran "relacionadas" con una recepción si:
  - Pertenecen al mismo proveedor
  - La fecha de la salida está dentro de ±7 días de la fecha de la recepción
- Si una salida de cebo no tiene recepción relacionada, aparece como item independiente tipo "dispatch"
- Los items se ordenan por fecha (ascendente)

---

### 3. Generar PDF de Liquidación

**Endpoint**: `GET /v2/supplier-liquidations/{supplierId}/pdf`

**Descripción**: Genera un PDF con el formato de albarán/liquidación del proveedor.

**Query Parameters**:
- `dates[start]` (required): Fecha de inicio (formato: `YYYY-MM-DD`)
- `dates[end]` (required): Fecha de fin (formato: `YYYY-MM-DD`)

**Ejemplo de Request**:
```
GET /v2/supplier-liquidations/1/pdf?dates[start]=2024-01-01&dates[end]=2024-01-31
```

**Response**: 
- Content-Type: `application/pdf`
- Headers: `Content-Disposition: attachment; filename="Liquidacion_Proveedor_ABC_2024-01-01_2024-01-31.pdf"`

**Formato del PDF**:

El PDF seguirá el estilo de los PDFs existentes en el sistema (ver `app/Http/Controllers/v2/PDFController.php`). Estructura:

1. **Encabezado**:
   - Título: "LIQUIDACIÓN DE PROVEEDOR"
   - Nombre del proveedor
   - Rango de fechas
   - Fecha de generación

2. **Tabla de Recepciones y Salidas**:
   - Columnas: Fecha | Tipo | Producto | Lote | Peso Neto (kg) | Precio (€/kg) | Importe (€)
   - Agrupación visual: Recepciones con sus salidas relacionadas agrupadas debajo
   - Línea de totales por recepción (al final de cada recepción):
     - Total Calculado: Peso | Importe | Precio Medio
     - Total Declarado: Peso | Importe

3. **Resumen Final**:
   - Total Recepciones: Cantidad | Peso (kg) | Importe (€)
   - Total Salidas de Cebo: Cantidad | Peso (kg) | Importe (€)
   - Total Declarado: Peso (kg) | Importe (€)
   - Importe Neto: (Recepciones - Salidas)

**Vista Blade**: `resources/views/pdf/v2/supplier_liquidations/liquidation.blade.php`

---

## 🗄️ Estructura de Base de Datos

### Tablas Utilizadas

#### `raw_material_receptions`
- `id`: ID de la recepción
- `supplier_id`: FK a `suppliers`
- `date`: Fecha de la recepción
- `notes`: Notas
- `declared_total_amount`: Importe total declarado
- `declared_total_net_weight`: Peso total declarado

#### `raw_material_reception_products`
- `id`: ID del producto de recepción
- `reception_id`: FK a `raw_material_receptions`
- `product_id`: FK a `products`
- `net_weight`: Peso neto
- `price`: Precio por kg
- `lot`: Lote

#### `cebo_dispatches`
- `id`: ID de la salida de cebo
- `supplier_id`: FK a `suppliers`
- `date`: Fecha de la salida
- `notes`: Notas

#### `cebo_dispatch_products`
- `id`: ID del producto de salida
- `dispatch_id`: FK a `cebo_dispatches`
- `product_id`: FK a `products`
- `net_weight`: Peso neto
- `price`: Precio por kg

#### `suppliers`
- `id`: ID del proveedor
- `name`: Nombre
- `contact_person`: Persona de contacto
- `phone`: Teléfono
- `address`: Dirección

---

## 📦 Modelos y Relaciones

### SupplierLiquidationController

**Archivo**: `app/Http/Controllers/v2/SupplierLiquidationController.php`

**Métodos**:
1. `getSuppliers(Request $request)`: Lista proveedores con actividad
2. `getDetails(Request $request, $supplierId)`: Obtiene liquidación detallada
3. `generatePdf(Request $request, $supplierId)`: Genera PDF

**Dependencias**:
- `App\Models\Supplier`
- `App\Models\RawMaterialReception`
- `App\Models\CeboDispatch`
- `App\Http\Controllers\v2\PDFController` (para generar PDF)

---

## 🔧 Lógica de Implementación

### 1. Listar Proveedores

```php
// Pseudocódigo
$startDate = $request->input('dates.start');
$endDate = $request->input('dates.end');

// Obtener proveedores con recepciones en el rango
$suppliersWithReceptions = Supplier::whereHas('rawMaterialReceptions', function($query) use ($startDate, $endDate) {
    $query->whereBetween('date', [$startDate, $endDate]);
})->get();

// Obtener proveedores con salidas de cebo en el rango
$suppliersWithDispatches = Supplier::whereHas('ceboDispatches', function($query) use ($startDate, $endDate) {
    $query->whereBetween('date', [$startDate, $endDate]);
})->get();

// Unir y calcular estadísticas
```

### 2. Obtener Detalles

```php
// Pseudocódigo
$supplier = Supplier::findOrFail($supplierId);
$startDate = $request->input('dates.start');
$endDate = $request->input('dates.end');

// Obtener recepciones
$receptions = RawMaterialReception::where('supplier_id', $supplierId)
    ->whereBetween('date', [$startDate, $endDate])
    ->with('products.product')
    ->orderBy('date', 'asc')
    ->get();

// Obtener salidas de cebo
$dispatches = CeboDispatch::where('supplier_id', $supplierId)
    ->whereBetween('date', [$startDate, $endDate])
    ->with('products.product')
    ->orderBy('date', 'asc')
    ->get();

// Agrupar salidas con recepciones (mismo proveedor, ±7 días)
foreach ($receptions as $reception) {
    $relatedDispatches = $dispatches->filter(function($dispatch) use ($reception) {
        $daysDiff = abs(strtotime($dispatch->date) - strtotime($reception->date)) / (60 * 60 * 24);
        return $daysDiff <= 7;
    });
    // Agregar al array de items
}

// Agregar salidas sin recepción relacionada
```

### 3. Generar PDF

```php
// Pseudocódigo
$details = $this->getDetails($request, $supplierId);
return $this->generatePdf($details, 'pdf.v2.supplier_liquidations.liquidation', 'Liquidacion_Proveedor_' . $supplier->name . '_' . $startDate . '_' . $endDate);
```

---

## 📝 Notas de Implementación

1. **Agrupación de Salidas con Recepciones**:
   - Ventana de tiempo: ±7 días desde la fecha de la recepción
   - Si una salida coincide con múltiples recepciones, se asocia a la más cercana
   - Si una salida no tiene recepción cercana, aparece como item independiente

2. **Cálculo de Totales**:
   - Los totales calculados se obtienen sumando los productos
   - Los totales declarados vienen del campo `declared_total_*` de la recepción
   - El precio medio se calcula: `total_amount / total_weight`

3. **Ordenamiento**:
   - Los items se ordenan por fecha ascendente
   - Dentro de cada recepción, las salidas relacionadas se ordenan por fecha

4. **Rendimiento**:
   - Usar `with()` para eager loading de relaciones
   - Considerar índices en `supplier_id` y `date` si no existen

---

## 🎨 Frontend - Estructura de Datos Esperada

### Pantalla 1: Listado de Proveedores

**Componente**: `SupplierLiquidationList.vue` (o similar)

**Datos necesarios**:
- Filtro de fechas (start, end)
- Lista de proveedores con estadísticas

**Acciones**:
- Seleccionar un proveedor → Navegar a pantalla de detalles

### Pantalla 2: Detalle de Liquidación

**Componente**: `SupplierLiquidationDetail.vue` (o similar)

**Datos necesarios**:
- Datos del proveedor
- Array de items (recepciones y salidas)
- Resumen global

**Visualización**:
- Tabla con columnas: Fecha | Tipo | Producto | Lote | Peso | Precio | Importe
- Agrupación visual de recepciones con sus salidas relacionadas
- Línea de totales por recepción (calculado y declarado)
- Resumen final con totales globales
- Botón para generar PDF

**Acciones**:
- Generar PDF → Llamar a endpoint de PDF
- Volver a listado

---

## ✅ Checklist de Implementación

### Backend
- [ ] Crear `SupplierLiquidationController`
- [ ] Implementar método `getSuppliers()`
- [ ] Implementar método `getDetails()`
- [ ] Implementar método `generatePdf()`
- [ ] Crear Resource `SupplierLiquidationResource` (opcional)
- [ ] Crear vista Blade para PDF
- [ ] Agregar rutas en `routes/api.php`
- [ ] Agregar relaciones en modelos si es necesario
- [ ] Probar endpoints con Postman/Insomnia
- [ ] Validar cálculos de totales
- [ ] Validar agrupación de salidas con recepciones

### Frontend
- [ ] Crear componente de listado de proveedores
- [ ] Crear componente de detalle de liquidación
- [ ] Implementar filtro de fechas
- [ ] Implementar tabla de items
- [ ] Implementar visualización de totales
- [ ] Implementar descarga de PDF
- [ ] Agregar navegación entre pantallas
- [ ] Agregar estilos y formato

---

## 📚 Referencias

- [Recepciones de Materia Prima](./60-Recepciones-Materia-Prima.md)
- [Despachos de Cebo](./61-Despachos-Cebo.md)
- [Proveedores](../24-catalogos/45-Proveedores.md)
- [Generación de PDFs](../29-utilidades/90-Generacion-PDF.md)

