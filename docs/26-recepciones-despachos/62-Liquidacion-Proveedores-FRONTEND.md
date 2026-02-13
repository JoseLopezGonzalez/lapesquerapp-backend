# Liquidación de Proveedores - Guía de Implementación Frontend

## 📋 Visión General

El módulo de **Liquidación de Proveedores** permite generar un documento tipo albarán que consolida todas las recepciones de materia prima y salidas de cebo de un proveedor dentro de un rango de fechas específico. Este documento sirve como resumen contable y logístico para la liquidación con proveedores.

**Flujo de usuario**:
1. El usuario selecciona un rango de fechas
2. Se muestra un listado de proveedores que tienen actividad (recepciones o salidas de cebo) en ese rango
3. El usuario selecciona un proveedor
4. Se muestra el detalle completo de la liquidación con todas las recepciones y salidas agrupadas
5. El usuario puede generar un PDF de la liquidación

---

## 🔌 Endpoints Disponibles

### 1. Listar Proveedores con Actividad

**Endpoint**: `GET /v2/supplier-liquidations/suppliers`

**Query Parameters**:
- `dates[start]` (required): Fecha de inicio en formato `YYYY-MM-DD`
- `dates[end]` (required): Fecha de fin en formato `YYYY-MM-DD`

**Ejemplo de Request**:
```
GET /v2/supplier-liquidations/suppliers?dates[start]=2024-01-01&dates[end]=2024-01-31
```

**Respuesta**:
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
    }
  ]
}
```

**Campos**:
- `id`: ID del proveedor
- `name`: Nombre del proveedor
- `receptions_count`: Número de recepciones en el rango
- `dispatches_count`: Número de salidas de cebo en el rango
- `total_receptions_weight`: Peso total de recepciones (kg)
- `total_dispatches_weight`: Peso total de salidas de cebo (kg)
- `total_receptions_amount`: Importe total de recepciones (€)
- `total_dispatches_amount`: Importe total de salidas de cebo (€)

**Uso**: Mostrar un listado de proveedores con estadísticas resumidas. El usuario puede seleccionar uno para ver el detalle.

---

### 2. Obtener Liquidación Detallada

**Endpoint**: `GET /v2/supplier-liquidations/{supplierId}/details`

**Query Parameters**:
- `dates[start]` (required): Fecha de inicio en formato `YYYY-MM-DD`
- `dates[end]` (required): Fecha de fin en formato `YYYY-MM-DD`

**Ejemplo de Request**:
```
GET /v2/supplier-liquidations/1/details?dates[start]=2024-01-01&dates[end]=2024-01-31
```

**Respuesta**:
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
  "receptions": [
    {
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
      "average_price": 10.50,
      "related_dispatches": [
        {
          "id": 201,
          "date": "2024-01-16",
          "notes": "Salida relacionada",
          "products": [...],
          "total_net_weight": 50.00,
          "total_amount": 525.00
        }
      ]
    }
  ],
  "dispatches": [
    {
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
    "weight_difference": 0.50,
    "amount_difference": 25.00,
    "net_amount": 25.00
  }
}
```

**Estructura de Datos**:

La respuesta ahora separa las recepciones y las salidas de cebo en dos arrays independientes:

#### Array `receptions`
Cada elemento del array `receptions` contiene:
- `id`: ID de la recepción
- `date`: Fecha de la recepción
- `notes`: Notas de la recepción
- `products`: Array de productos de la recepción
  - Cada producto incluye: `id`, `product` (con `id`, `name`, `code`), `lot`, `net_weight`, `price`, `amount`, `boxes`
- `declared_total_net_weight`: Peso total declarado (kg)
- `declared_total_amount`: Importe total declarado (€)
- `calculated_total_net_weight`: Peso total calculado (suma de productos)
- `calculated_total_amount`: Importe total calculado (suma de productos)
- `average_price`: Precio medio (calculated_total_amount / calculated_total_net_weight)
- `related_dispatches`: Array de salidas de cebo relacionadas (mismo proveedor, fecha dentro de ±7 días)
  - Cada salida relacionada incluye: `id`, `date`, `notes`, `products`, `total_net_weight`, `total_amount`

#### Array `dispatches`
Cada elemento del array `dispatches` contiene salidas de cebo que **NO tienen recepción relacionada** (más de 7 días de diferencia):
- `id`: ID de la salida de cebo
- `date`: Fecha de la salida
- `notes`: Notas de la salida
- `products`: Array de productos de la salida
  - Cada producto incluye: `id`, `product` (con `id`, `name`, `code`), `net_weight`, `price`, `amount`
- `total_net_weight`: Peso total (suma de productos)
- `total_amount`: Importe total (suma de productos)

**Resumen (Summary)**:
- `total_receptions`: Número total de recepciones
- `total_dispatches`: Número total de salidas de cebo
- `total_receptions_weight`: Peso total calculado de recepciones (kg) - suma de productos
- `total_dispatches_weight`: Peso total de salidas de cebo (kg)
- `total_receptions_amount`: Importe total calculado de recepciones (€) - suma de productos
- `total_dispatches_amount`: Importe total de salidas de cebo (€)
- `total_declared_weight`: Peso total declarado en recepciones (kg)
- `total_declared_amount`: Importe total declarado en recepciones (€)
- `weight_difference`: Diferencia de peso (calculado - declarado) (kg)
- `amount_difference`: Diferencia de importe (calculado - declarado) (€)
- `net_amount`: Importe neto total (diferencia entre calculado y declarado) = `amount_difference` (€)

**Uso**: Mostrar el detalle completo de la liquidación. Las recepciones y salidas están ordenadas por fecha ascendente. Las salidas de cebo relacionadas con recepciones aparecen en el campo `related_dispatches` de cada recepción. Las salidas sin recepción relacionada aparecen en el array `dispatches` separado.

---

### 3. Generar PDF de Liquidación

**Endpoint**: `GET /v2/supplier-liquidations/{supplierId}/pdf`

**Query Parameters**:
- `dates[start]` (required): Fecha de inicio en formato `YYYY-MM-DD`
- `dates[end]` (required): Fecha de fin en formato `YYYY-MM-DD`
- `receptions[]` (optional): Array de IDs de recepciones a incluir en el PDF. Si no se especifica, se incluyen todas.
- `dispatches[]` (optional): Array de IDs de salidas de cebo a incluir en el PDF. Si no se especifica, se incluyen todas.

**Ejemplo de Request - Sin filtros (todas las recepciones y salidas)**:
```
GET /v2/supplier-liquidations/1/pdf?dates[start]=2024-01-01&dates[end]=2024-01-31
```

**Ejemplo de Request - Con selección específica**:
```
GET /v2/supplier-liquidations/1/pdf?dates[start]=2024-01-01&dates[end]=2024-01-31&receptions[]=101&receptions[]=102&dispatches[]=201&dispatches[]=202
```

**Comportamiento**:
- Si no se especifican `receptions[]`, se incluyen todas las recepciones del rango de fechas
- Si no se especifican `dispatches[]`, se incluyen todas las salidas de cebo del rango de fechas
- Si se especifican `receptions[]`, solo se incluyen las recepciones con esos IDs
- Si se especifican `dispatches[]`, solo se incluyen las salidas con esos IDs (tanto independientes como relacionadas dentro de recepciones)
- El resumen se recalcula automáticamente con los datos filtrados

**Respuesta**: 
- Content-Type: `application/pdf`
- Headers: `Content-Disposition: attachment; filename="Liquidacion_Proveedor_Proveedor_ABC_2024-01-01_2024-01-31.pdf"`
- Body: Archivo PDF binario

**Uso**: Descargar el PDF de la liquidación con las recepciones y salidas seleccionadas. El navegador debería abrir el diálogo de descarga automáticamente.

---

## 🎨 Recomendaciones de Implementación

### Pantalla 1: Listado de Proveedores

**Componentes sugeridos**:
- Selector de rango de fechas (date picker)
- Tabla o lista de proveedores con las estadísticas
- Botón o acción para seleccionar un proveedor y ver el detalle

**Datos a mostrar**:
- Nombre del proveedor
- Número de recepciones y salidas
- Peso total de recepciones y salidas
- Importe total de recepciones y salidas

**Interacciones**:
- Al cambiar el rango de fechas, recargar la lista de proveedores
- Al hacer clic en un proveedor, navegar a la pantalla de detalle pasando el `supplierId` y el rango de fechas

---

### Pantalla 2: Detalle de Liquidación

**Componentes sugeridos**:
- Encabezado con datos del proveedor y rango de fechas
- **Tabla de Recepciones** (separada)
- **Tabla de Salidas de Cebo** (separada)
- Resumen global con totales
- Botón para generar PDF

**Estructura de visualización**:

1. **Encabezado**:
   - Nombre del proveedor
   - Información de contacto (opcional)
   - Rango de fechas seleccionado

2. **Tabla de Recepciones**:
   - Columnas sugeridas: Fecha | Producto | Lote | Peso Neto (kg) | Precio (€/kg) | Importe (€)
   - Mostrar todas las recepciones ordenadas por fecha
   - Para cada recepción, mostrar sus productos
   - Después de cada recepción, mostrar una fila con:
     - Total Calculado: Peso | Importe | Precio Medio
     - Total Declarado: Peso | Importe (si existe)
   - Si la recepción tiene salidas relacionadas (`related_dispatches`), mostrarlas agrupadas debajo de la recepción con un estilo visual diferente (por ejemplo, con fondo de color diferente o indentadas)

3. **Tabla de Salidas de Cebo** (sin recepción relacionada):
   - Columnas sugeridas: Fecha | Producto | Peso Neto (kg) | Precio (€/kg) | Importe (€)
   - Mostrar todas las salidas que no tienen recepción relacionada
   - Después de cada salida, mostrar el total

4. **Resumen Global**:
   - Total Recepciones: Cantidad | Peso (kg) | Importe (€)
   - Total Salidas de Cebo: Cantidad | Peso (kg) | Importe (€)
   - Total Declarado: Peso (kg) | Importe (€)
   - **Importe Neto**: (Recepciones - Salidas) - Este es el valor más importante

**Interacciones**:
- Checkboxes o toggles para seleccionar/deseleccionar recepciones individuales
- Checkboxes o toggles para seleccionar/deseleccionar salidas de cebo individuales
- Botones "Seleccionar todo" / "Deseleccionar todo" para facilitar la selección
- Botón "Generar PDF": Descargar el PDF con las recepciones y salidas seleccionadas
- Botón "Volver": Regresar al listado de proveedores

**Selección de Items para PDF**:
- Cada recepción debe tener un checkbox/toggle para incluirla o no en el PDF
- Cada salida de cebo debe tener un checkbox/toggle para incluirla o no en el PDF
- Al generar el PDF, enviar los IDs seleccionados como arrays en los query parameters:
  - `receptions[]=101&receptions[]=102` para recepciones
  - `dispatches[]=201&dispatches[]=202` para salidas
- Si no se selecciona ninguna recepción o salida, se incluyen todas por defecto

---

## 📊 Estructura de Datos - Detalles Importantes

### Recepciones y Salidas Ordenadas
- Las recepciones en el array `receptions` están ordenadas por fecha ascendente
- Las salidas de cebo en el array `dispatches` están ordenadas por fecha ascendente
- Esto facilita la visualización cronológica en tablas separadas

### Agrupación de Salidas con Recepciones
Las salidas de cebo se consideran "relacionadas" con una recepción si:
- Pertenecen al mismo proveedor
- La fecha de la salida está dentro de ±7 días de la fecha de la recepción

Si una salida de cebo tiene recepción relacionada, aparece en el campo `related_dispatches` de esa recepción.
Si una salida de cebo no tiene recepción relacionada (más de 7 días de diferencia), aparece en el array `dispatches` separado.

### Totales Calculados vs Declarados
- **Totales Calculados**: Se obtienen sumando los productos de la recepción
- **Totales Declarados**: Vienen del campo `declared_total_*` de la recepción (pueden ser diferentes)

Ambos deben mostrarse para permitir comparación.

### Manejo de Valores Nulos
- `product.code`: Puede ser `null` si el producto no tiene código
- `product.name`: Puede ser `null` si el producto no está cargado (aunque no debería pasar)
- `lot`: Puede ser `null` en algunos casos
- `declared_total_*`: Pueden ser `null` si no se declararon valores

---

## 🎯 Casos de Uso Especiales

### Proveedor sin Actividad
Si un proveedor no tiene recepciones ni salidas en el rango de fechas, no aparecerá en el listado.

### Recepción sin Productos
Aunque no debería pasar, si una recepción no tiene productos, los totales serán 0.

### Salida sin Recepción Relacionada
Las salidas de cebo que no tienen recepción relacionada (más de 7 días de diferencia) aparecen en el array `dispatches` separado.

### Múltiples Salidas Relacionadas
Una recepción puede tener múltiples salidas de cebo relacionadas. Todas aparecen en el array `related_dispatches`.

---

## 🔄 Flujo de Navegación Recomendado

```
[Pantalla Principal]
    ↓
[Selector de Fechas] → [Listado de Proveedores]
    ↓
[Seleccionar Proveedor] → [Detalle de Liquidación]
    ↓
[Generar PDF] → [Descarga de PDF]
    ↓
[Volver] → [Listado de Proveedores]
```

---

## 💡 Sugerencias de UX

1. **Carga de Datos**:
   - Mostrar indicadores de carga mientras se obtienen los datos
   - Manejar errores de red de forma amigable

2. **Visualización**:
   - Usar colores diferentes para recepciones (verde/azul) y salidas de cebo (rojo/naranja)
   - Agrupar visualmente las salidas relacionadas bajo sus recepciones
   - Destacar el importe neto en el resumen

3. **Interacciones**:
   - Permitir exportar los datos a Excel/CSV (opcional)
   - Permitir imprimir la vista de detalle (opcional)
   - Guardar el rango de fechas en el estado/localStorage para persistencia

4. **Validaciones**:
   - Validar que la fecha de inicio sea anterior a la fecha de fin
   - Mostrar mensajes de error si no hay datos en el rango seleccionado

---

## 📝 Notas Técnicas

### Formato de Fechas
- Las fechas se envían y reciben en formato `YYYY-MM-DD`
- Las fechas en los items están en formato `YYYY-MM-DD`

### Manejo de Errores
- Si el proveedor no existe: 404 Not Found
- Si las fechas son inválidas: 422 Unprocessable Entity
- Si hay error al generar PDF: 500 Internal Server Error

### Rendimiento
- El endpoint de listado puede ser lento si hay muchos proveedores. Considerar paginación si es necesario.
- El endpoint de detalles carga todas las recepciones y salidas. Para rangos muy grandes, puede ser lento.
- El PDF se genera en el servidor, puede tardar unos segundos dependiendo del tamaño de los datos.

---

## 🎨 Ejemplo de Estructura de Componente (Conceptual)

```
SupplierLiquidationModule
├── SupplierLiquidationList
│   ├── DateRangePicker
│   └── SupplierListTable
│       └── SupplierRow
├── SupplierLiquidationDetail
│   ├── SupplierHeader
│   ├── LiquidationItemsTable
│   │   ├── ReceptionRow
│   │   │   └── RelatedDispatchesGroup
│   │   └── DispatchRow
│   ├── LiquidationSummary
│   └── ActionsBar
│       └── GeneratePdfButton
```

---

## ✅ Checklist de Implementación

- [ ] Implementar selector de rango de fechas
- [ ] Implementar listado de proveedores con estadísticas
- [ ] Implementar navegación a detalle
- [ ] Implementar vista de detalle con dos tablas separadas (recepciones y salidas)
- [ ] Implementar tabla de recepciones con sus productos y totales
- [ ] Implementar visualización de salidas relacionadas dentro de cada recepción
- [ ] Implementar tabla de salidas de cebo sin recepción relacionada
- [ ] Implementar visualización de totales (calculados y declarados)
- [ ] Implementar resumen global
- [ ] Implementar descarga de PDF
- [ ] Implementar manejo de errores
- [ ] Implementar indicadores de carga
- [ ] Implementar validaciones de fechas
- [ ] Probar con diferentes rangos de fechas
- [ ] Probar con proveedores sin actividad
- [ ] Probar con recepciones sin salidas relacionadas
- [ ] Probar con salidas sin recepciones relacionadas

---

## 📚 Referencias

- Documentación Backend: `docs/26-recepciones-despachos/62-Liquidacion-Proveedores.md`
- Errores Corregidos: `docs/26-recepciones-despachos/62-Liquidacion-Proveedores-ERRORES-CORREGIDOS.md`

