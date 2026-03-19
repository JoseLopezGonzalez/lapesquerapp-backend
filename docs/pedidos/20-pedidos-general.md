# Pedidos - Visión General

## ⚠️ Estado de la API
- **v1**: Obsoleta (no documentada)
- **v2**: Versión activa (este documento)

---

## 📋 Visión General

El módulo de pedidos es el núcleo del sistema de ventas y distribución de PesquerApp. Gestiona el ciclo completo de pedidos desde su creación hasta la entrega, incluyendo planificación de productos, asignación de palets, generación de documentos y seguimiento de incidentes.

**Concepto clave**: Un pedido representa una solicitud de envío de productos a un cliente, con fechas de entrada y carga, información de transporte, y productos planificados que luego se materializan en palets y cajas reales.

---

## 🏗️ Arquitectura del Módulo

### Entidades Principales

1. **Order** (Pedido): Cabecera del pedido con información general
2. **OrderPlannedProductDetail**: Productos planificados en el pedido (cantidades y precios)
3. **Incident**: Incidentes relacionados con el pedido
4. **Pallet**: Palets asignados al pedido (relación con módulo de inventario)
5. **Box**: Cajas dentro de los palets (relación con módulo de inventario)

### Flujo de Trabajo

```
1. Crear Pedido (Order)
   ↓
2. Planificar Productos (OrderPlannedProductDetail)
   ↓
3. Asignar Palets con Cajas (Pallet → Box)
   ↓
4. Generar Documentos PDF
   ↓
5. Enviar por Email
   ↓
6. Gestionar Incidentes (si es necesario)
   ↓
7. Finalizar Pedido
```

---

## 📊 Conceptos Clave

### Estados del Pedido

Los pedidos tienen un campo `status` que puede ser:

- **`pending`**: Pedido pendiente (no finalizado)
- **`finished`**: Pedido finalizado
- **`incident`**: Pedido con incidente

**Lógica de estado activo**:
Un pedido se considera **activo** cuando:
- `status == 'pending'` O
- `load_date >= hoy`

Un pedido se considera **inactivo** cuando:
- `status == 'finished'` Y
- `load_date < hoy`

### Productos Planificados vs Productos Reales

**Productos Planificados** (`OrderPlannedProductDetail`):
- Cantidades y precios planificados al crear el pedido
- Se usan para presupuestos y documentos iniciales

**Productos Reales** (desde `Pallet` → `Box` → `Product`):
- Productos físicos realmente asignados al pedido
- Se calculan desde los palets y cajas vinculados

**Conciliación**: El sistema calcula diferencias entre lo planificado y lo real en `productDetails`.

### Relación con Inventario

Los pedidos se vinculan con el inventario mediante:
- **Palets**: `Order` → `Pallet` (relación directa)
- **Cajas**: A través de `Pallet` → `PalletBox` → `Box`

Los cálculos de productos, pesos y totales se hacen desde estas relaciones.

---

## 🔗 Relaciones con Otros Módulos

### Módulo de Inventario
- **Palets**: Un pedido puede tener múltiples palets
- **Cajas**: Se calculan desde los palets asignados

### Módulo de Producción
- **Indirecta**: Las cajas pueden venir de producción, pero no hay relación directa

### Catálogos
- **Customer**: Cliente del pedido
- **Product**: Productos planificados y reales
- **Salesperson**: Vendedor asignado
- **Transport**: Transportista asignado
- **PaymentTerm**: Términos de pago
- **Incoterm**: Incoterm comercial

---

## 📍 Rutas API Principales

Todas las rutas están bajo `/v2` y requieren autenticación (`auth:sanctum`) y roles (`superuser,manager,admin,store_operator`).

### Orders (Pedidos)
- `GET /v2/orders` - Listar pedidos (con múltiples filtros)
- `POST /v2/orders` - Crear pedido
- `GET /v2/orders/{id}` - Mostrar pedido (con detalles completos)
- `PUT /v2/orders/{id}` - Actualizar pedido
- `DELETE /v2/orders/{id}` - Eliminar pedido
- `DELETE /v2/orders` - Eliminar múltiples pedidos (body: `{ids: [...]}`)
- `PUT /v2/orders/{id}/status` - Actualizar estado del pedido
- `GET /v2/orders/options` - Opciones para select
- `GET /v2/active-orders/options` - Opciones de pedidos activos

### Order Planned Product Details (Productos Planificados)
- `POST /v2/order-planned-product-details` - Crear producto planificado
- `PUT /v2/order-planned-product-details/{id}` - Actualizar producto planificado
- `DELETE /v2/order-planned-product-details/{id}` - Eliminar producto planificado

### Order Documents (Documentos)
- `GET /v2/orders/{id}/pdf/order-sheet` - Generar hoja de pedido PDF
- `GET /v2/orders/{id}/pdf/order-signs` - Generar letreros de transporte PDF
- `GET /v2/orders/{id}/pdf/restricted-order-signs` - Generar letreros de transporte restringidos PDF (sin consignatario ni transporte)
- `GET /v2/orders/{id}/pdf/order-packing-list` - Generar packing list PDF
- `GET /v2/orders/{id}/pdf/loading-note` - Generar nota de carga PDF
- `GET /v2/orders/{id}/pdf/restricted-loading-note` - Generar nota de carga restringida PDF
- `GET /v2/orders/{id}/pdf/order-cmr` - Generar CMR PDF
- `POST /v2/orders/{id}/send-standard-documents` - Enviar documentación estándar por email
- `POST /v2/orders/{id}/send-custom-documents` - Enviar documentación personalizada por email

### Order Incidents (Incidentes)
- `GET /v2/orders/{id}/incident` - Obtener incidente del pedido
- `POST /v2/orders/{id}/incident` - Crear incidente
- `PUT /v2/orders/{id}/incident` - Actualizar incidente
- `DELETE /v2/orders/{id}/incident` - Eliminar incidente

### Order Statistics (Estadísticas)
- `GET /v2/statistics/orders/total-net-weight` - Estadísticas de peso neto
- `GET /v2/statistics/orders/total-amount` - Estadísticas de importe total
- `GET /v2/statistics/orders/ranking` - Ranking de pedidos
- `GET /v2/orders/sales-chart-data` - Datos para gráficos de ventas
- `GET /v2/orders/sales-by-salesperson` - Ventas por vendedor
- `GET /v2/orders/transport-chart-data` - Datos por transportista

---

## 📚 Documentación Específica

Para detalles completos de cada componente, consultar:
- [21-Pedidos-Detalles-Planificados.md](./21-Pedidos-Detalles-Planificados.md) - OrderPlannedProductDetail
- [22-Pedidos-Documentos.md](./22-Pedidos-Documentos.md) - Generación de PDFs y envío por email
- [23-Pedidos-Incidentes.md](./23-Pedidos-Incidentes.md) - Gestión de incidentes
- [24-Pedidos-Estadisticas.md](./24-Pedidos-Estadisticas.md) - Estadísticas y reportes

---

## Observaciones Críticas y Mejoras Recomendadas

### ⚠️ Métodos Incompletos

1. **`getSummaryAttribute()` Vacío** (`app/Models/Order.php:73-75`)
   - Método está definido pero sin implementación
   - **Líneas**: 73-75
   - **Problema**: No retorna nada, puede causar errores si se accede
   - **Recomendación**: Implementar o eliminar si no se usa

### ⚠️ Lógica de isActive() Potencialmente Incorrecta

2. **isActive() No Coincide con Comentario** (`app/Models/Order.php:82-86`)
   - Comentario dice "Order is active when status is 'finished' and loadDate is < now"
   - Pero la lógica es: `status == 'pending' || load_date >= now()`
   - **Líneas**: 82-86
   - **Problema**: Comentario contradice la implementación
   - **Recomendación**: Corregir comentario o implementación según intención real

### ⚠️ Código Comentado y Código Muerto

3. **Métodos Comentados** (`app/Models/Order.php:246-354`)
   - Hay métodos comentados extensamente (ej: `getProductsWithLotsDetailsBySpeciesAndCaptureZoneAttribute()`)
   - **Líneas**: 246-284, 287-354
   - **Problema**: Código muerto que confunde
   - **Recomendación**: Eliminar si no se van a usar o descomentar y completar

### ⚠️ Performance: Queries N+1 en Attributes

4. **Attributes Calculados con Queries** (`app/Models/Order.php:229-241`)
   - `getTotalNetWeightAttribute()` y `getTotalBoxesAttribute()` hacen queries en cada acceso
   - **Líneas**: 229-234, 236-241
   - **Problema**: Si se accede múltiples veces, se ejecutan múltiples queries
   - **Recomendación**: Cachear resultados o usar eager loading agregado

5. **Métodos con Nested Loops** (`app/Models/Order.php:90-123, 356-413`)
   - `getProductsBySpeciesAndCaptureZoneAttribute()` y otros tienen loops anidados
   - **Líneas**: 90-123, 356-413
   - **Problema**: Con muchos palets/cajas puede ser lento
   - **Recomendación**: Optimizar con queries más eficientes o caching

### ⚠️ Falta de Validación en Estado

6. **No Validar Estado Antes de Operaciones** (`app/Http/Controllers/v2/OrderController.php`)
   - No valida si el pedido está finalizado antes de permitir modificaciones
   - **Problema**: Pueden modificarse pedidos finalizados
   - **Recomendación**: Validar estado antes de update/destroy

### ⚠️ Eliminación sin Validaciones

7. **Eliminación Sin Validar Relaciones** (`app/Http/Controllers/v2/OrderController.php:384-389`)
   - `destroy()` no valida si tiene palets asignados
   - **Líneas**: 384-389
   - **Problema**: Puede eliminar pedidos con palets, rompiendo relaciones
   - **Recomendación**: Validar antes de eliminar o usar soft deletes

### ⚠️ Validaciones Incompletas en Store

8. **Validación de Fechas** (`app/Http/Controllers/v2/OrderController.php:167-168`)
   - Valida que sean fechas pero no que `loadDate >= entryDate`
   - **Líneas**: 167-168
   - **Problema**: Puede crearse pedido con fecha de carga anterior a entrada
   - **Recomendación**: Agregar validación `loadDate >= entryDate`

### ⚠️ Formato de Emails Complejo

9. **Emails en Campo Texto** (`app/Models/Order.php:181-224`)
   - Los emails se almacenan como texto con formato `email1;CC:email2;`
   - **Líneas**: 181-224
   - **Problema**: Formato frágil, difícil de mantener
   - **Recomendación**: Considerar tabla separada `order_emails` o campo JSON

### ⚠️ Cálculo de Totales sin Validación de Tax

10. **Cálculo de Total Asume Tax Válido** (`app/Models/Order.php:485`)
    - `getProductDetailsAttribute()` calcula total asumiendo que tax tiene rate
    - **Líneas**: 485
    - **Problema**: Si tax es null o no tiene rate, puede fallar
    - **Recomendación**: Validar que tax exista y tenga rate antes de calcular

---

**Última actualización**: Documentación generada desde código fuente en fecha de generación.

