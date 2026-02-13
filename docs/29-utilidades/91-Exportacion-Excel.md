# Utilidades - Exportación a Excel

## ⚠️ Estado de la API
- **v1**: Obsoleta (no documentada)
- **v2**: Versión activa (este documento)

---

## 📋 Visión General

El sistema de exportación a Excel permite generar archivos Excel (.xlsx y .xls) desde datos de diferentes entidades del sistema. Utiliza la librería **Laravel Excel** (Maatwebsite) que a su vez está basada en PhpSpreadsheet.

**Arquitectura**:
- **ExcelController** (`app/Http/Controllers/v2/ExcelController.php`): Controlador que expone endpoints HTTP para generar archivos Excel
- **Clases Export** (`app/Exports/v2/`): Clases que implementan las interfaces de Laravel Excel para definir estructura y datos de cada exportación

**Características**:
- Soporte para múltiples formatos: `.xlsx` (Excel 2007+) y `.xls` (Excel 97-2003)
- Filtrado avanzado de datos antes de exportar
- Estilos y formato personalizado (opcional)
- Soporte para límites de datos (útil para testing)
- Configuración de memoria y tiempo de ejecución para exportaciones grandes

---

## 🔧 Controlador: ExcelController

**Archivo**: `app/Http/Controllers/v2/ExcelController.php`

### Método Privado: `generateExport()`

Método auxiliar para generar exportaciones genéricas.

```php
private function generateExport($exportClass, $fileName)
```

**Parámetros**:
- `$exportClass`: Nombre de la clase Export a instanciar
- `$fileName`: Nombre del archivo (sin extensión)

**Retorna**: Descarga directa del archivo Excel usando `Excel::download()`

### Métodos Públicos de Exportación

#### Exportaciones de Pedidos (Orders)

##### `exportOrders(Request $request)`
- **Clase Export**: `OrdersExport`
- **Archivo**: `orders_report.xlsx`
- **Formato**: `.xlsx`
- **Memoria**: 1024M
- **Descripción**: Exporta todos los pedidos con filtros opcionales

##### `exportProductLotDetails($orderId)`
- **Clase Export**: `ProductLotDetailsExport`
- **Archivo**: `product_lot_details_{formattedId}.xlsx`
- **Formato**: `.xlsx`
- **Memoria**: 1024M
- **Descripción**: Exporta detalles de lotes de productos para un pedido específico

##### `exportBoxList($orderId)`
- **Clase Export**: `OrderBoxListExport`
- **Archivo**: `box_list_{formattedId}.xlsx`
- **Formato**: `.xlsx`
- **Memoria**: 1024M
- **Descripción**: Exporta lista de cajas de un pedido específico

##### `exportActiveOrderPlannedProducts()`
- **Clase Export**: `ActiveOrderPlannedProductsExport`
- **Archivo**: `productos_previstos_pedidos_activos.xlsx`
- **Formato**: `.xlsx`
- **Memoria**: 1024M, Tiempo: 300s
- **Descripción**: Exporta productos planificados de pedidos activos

#### Exportaciones A3ERP (Formato A3 ERP)

##### `exportA3ERPOrderSalesDeliveryNote($orderId)`
- **Clase Export**: `A3ERPOrderSalesDeliveryNoteExport`
- **Archivo**: `albaran_venta_{formattedId}.xls`
- **Formato**: `.xls` (Excel 97-2003)
- **Memoria**: 1024M
- **Descripción**: Albarán de venta individual en formato A3ERP

##### `exportA3ERPOrderSalesDeliveryNoteWithFilters(Request $request)`
- **Clase Export**: `A3ERPOrdersSalesDeliveryNotesExport`
- **Archivo**: `albaran_venta_filtrado.xls`
- **Formato**: `.xls`
- **Memoria**: 1024M, Tiempo: 300s
- **Descripción**: Múltiples albaranes filtrados en formato A3ERP

**Filtros soportados**:
- `active`: `true`/`false` (pedidos activos/finalizados)
- `customers`: Array de IDs de clientes
- `id`: ID parcial del pedido (LIKE)
- `ids`: Array de IDs de pedidos
- `buyerReference`: Referencia de compra (LIKE)
- `status`: Estado del pedido
- `loadDate`: Rango de fechas `{start, end}`
- `entryDate`: Rango de fechas `{start, end}`
- `transports`: Array de IDs de transportes
- `salespeople`: Array de IDs de vendedores
- `palletsState`: `stored`/`shipping`
- `incoterm`: ID de incoterm
- `transport`: ID de transporte

##### `exportA3ERP2OrderSalesDeliveryNote($orderId)`
- **Clase Export**: `A3ERP2OrderSalesDeliveryNoteExport`
- **Archivo**: `albaran_venta_a3erp2_{formattedId}.xls`
- **Formato**: `.xls`
- **Memoria**: 1024M
- **Descripción**: Albarán en formato A3ERP2 (A3 con códigos Facilcom), solo clientes con `facilcom_code`

##### `exportA3ERP2OrderSalesDeliveryNoteWithFilters(Request $request)`
- **Clase Export**: `A3ERP2OrdersSalesDeliveryNotesExport`
- **Archivo**: `albaran_venta_a3erp2_filtrado.xls`
- **Formato**: `.xls`
- **Memoria**: 1024M, Tiempo: 300s
- **Descripción**: Múltiples albaranes filtrados en formato A3ERP2

**Restricción especial**: Solo exporta pedidos de clientes con código Facilcom (`facilcom_code` no nulo)

#### Exportaciones Facilcom

##### `exportFacilcomOrderSalesDeliveryNoteWithFilters(Request $request)`
- **Clase Export**: `FacilcomOrdersSalesDeliveryNotesExport`
- **Archivo**: `albaran_facilcom.xls`
- **Formato**: `.xls`
- **Memoria**: 1024M, Tiempo: 300s
- **Descripción**: Múltiples albaranes filtrados en formato Facilcom

**Filtros**: Mismos que `exportA3ERPOrderSalesDeliveryNoteWithFilters`

##### `exportFacilcomSingleOrder($orderId)`
- **Clase Export**: `FacilcomOrderSalesDeliveryNoteExport`
- **Archivo**: `albaran_facilcom_{formattedId}.xls`
- **Formato**: `.xls`
- **Memoria**: 1024M
- **Descripción**: Albarán individual en formato Facilcom

#### Exportaciones de Recepciones de Materia Prima

##### `exportRawMaterialReceptionFacilcom(Request $request)`
- **Clase Export**: `RawMaterialReceptionFacilcomExport`
- **Archivo**: `recepciones_materia_prima_facilcom.xls`
- **Formato**: `.xls`
- **Memoria**: 2048M, Tiempo: 600s
- **Descripción**: Recepciones en formato Facilcom

**Parámetros**:
- `limit`: (opcional) Límite de registros para testing

##### `exportRawMaterialReceptionA3erp(Request $request)`
- **Clase Export**: `RawMaterialReceptionA3erpExport`
- **Archivo**: `recepciones_materia_prima_a3erp.xls`
- **Formato**: `.xls`
- **Memoria**: 2048M, Tiempo: 600s
- **Descripción**: Recepciones en formato A3ERP

**Parámetros**:
- `limit`: (opcional) Límite de registros para testing

#### Exportaciones de Despachos de Cebo

##### `exportCeboDispatchFacilcom(Request $request)`
- **Clase Export**: `CeboDispatchFacilcomExport`
- **Archivo**: `despachos_cebo_facilcom.xlsx`
- **Formato**: `.xlsx`
- **Memoria**: 2048M, Tiempo: 600s
- **Descripción**: Despachos de cebo en formato Facilcom

**Parámetros**:
- `limit`: (opcional) Límite de registros para testing

##### `exportCeboDispatchA3erp(Request $request)`
- **Clase Export**: `CeboDispatchA3erpExport`
- **Archivo**: `despachos_cebo_a3erp.xls`
- **Formato**: `.xls`
- **Memoria**: 2048M, Tiempo: 600s
- **Descripción**: Despachos de cebo en formato A3ERP

**Parámetros**:
- `limit`: (opcional) Límite de registros para testing

##### `exportCeboDispatchA3erp2(Request $request)`
- **Clase Export**: `CeboDispatchA3erp2Export`
- **Archivo**: `despachos_cebo_a3erp2.xls`
- **Formato**: `.xls`
- **Memoria**: 2048M, Tiempo: 600s
- **Descripción**: Despachos de cebo en formato A3ERP2 (A3 con códigos Facilcom), solo tipo facilcom

**Parámetros**:
- `limit`: (opcional) Límite de registros para testing

#### Exportaciones de Cajas

##### `exportBoxesReport(Request $request)`
- **Clase Export**: `BoxesReportExport`
- **Archivo**: `reporte_cajas.xlsx`
- **Formato**: `.xlsx`
- **Memoria**: 2048M, Tiempo: 600s
- **Descripción**: Reporte completo de cajas con filtros avanzados

**Parámetros**:
- `limit`: (opcional) Límite de registros para testing
- Filtros: Ver [Inventario - Cajas](../23-inventario/32-Cajas.md) para lista completa de filtros

---

## 🏗️ Clases Export

Todas las clases Export están ubicadas en `app/Exports/v2/` e implementan interfaces de Laravel Excel.

### Interfaces Comunes

#### `FromCollection`
- Define que los datos provienen de una colección Eloquent
- Requiere método `collection()` que retorna una `Collection`

#### `FromQuery`
- Define que los datos provienen de una query Eloquent
- Más eficiente para grandes volúmenes de datos (streaming)
- Requiere método `query()` que retorna un `Builder`

#### `WithHeadings`
- Define encabezados de columnas
- Requiere método `headings(): array`

#### `WithMapping`
- Mapea cada fila de datos antes de escribir
- Requiere método `map($row): array`

#### `WithStyles`
- Aplica estilos personalizados a la hoja
- Requiere método `styles(Worksheet $sheet): array`

#### `WithTitle`
- Define el título/nombre de la hoja
- Requiere método `title(): string`

#### `Exportable`
- Trait que proporciona métodos útiles de Laravel Excel

### Estructura Típica

```php
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\Exportable;

class MyExport implements FromCollection, WithHeadings, WithMapping
{
    use Exportable;
    
    protected $data;
    
    public function __construct($data)
    {
        $this->data = $data;
    }
    
    public function collection()
    {
        return $this->data;
    }
    
    public function headings(): array
    {
        return ['Columna 1', 'Columna 2', ...];
    }
    
    public function map($row): array
    {
        return [
            $row->field1,
            $row->field2,
            ...
        ];
    }
}
```

### Clases Export Disponibles

1. **OrderExport**: Exportación general de pedidos
2. **ProductLotDetailsExport**: Detalles de lotes de productos por pedido
3. **OrderBoxListExport**: Lista de cajas por pedido
4. **ActiveOrderPlannedProductsExport**: Productos planificados de pedidos activos
5. **A3ERPOrderSalesDeliveryNoteExport**: Albarán individual A3ERP
6. **A3ERPOrdersSalesDeliveryNotesExport**: Múltiples albaranes A3ERP
7. **A3ERP2OrderSalesDeliveryNoteExport**: Albarán individual A3ERP2
8. **A3ERP2OrdersSalesDeliveryNotesExport**: Múltiples albaranes A3ERP2
9. **FacilcomOrderSalesDeliveryNoteExport**: Albarán individual Facilcom
10. **FacilcomOrdersSalesDeliveryNotesExport**: Múltiples albaranes Facilcom
11. **RawMaterialReceptionFacilcomExport**: Recepciones Facilcom
12. **RawMaterialReceptionA3erpExport**: Recepciones A3ERP
13. **CeboDispatchFacilcomExport**: Despachos de cebo Facilcom
14. **CeboDispatchA3erpExport**: Despachos de cebo A3ERP
15. **CeboDispatchA3erp2Export**: Despachos de cebo A3ERP2
16. **BoxesReportExport**: Reporte completo de cajas

---

## 🛣️ Rutas API

Todas las rutas están protegidas por autenticación Sanctum y son accesibles para roles: `superuser`, `manager`, `admin`, `store_operator`.

### Rutas de Exportación de Pedidos

| Método HTTP | Ruta | Método del Controlador | Descripción |
|------------|------|----------------------|-------------|
| `GET` | `/api/v2/orders/xlsx/lots-report?orderId={id}` | `exportProductLotDetails` | Detalles de lotes |
| `GET` | `/api/v2/orders/{orderId}/xlsx/boxes-report` | `exportBoxList` | Lista de cajas |
| `GET` | `/api/v2/orders/{orderId}/xls/A3ERP-sales-delivery-note` | `exportA3ERPOrderSalesDeliveryNote` | Albarán A3ERP individual |
| `GET` | `/api/v2/orders/xls/A3ERP-sales-delivery-note-filtered` | `exportA3ERPOrderSalesDeliveryNoteWithFilters` | Albaranes A3ERP filtrados |
| `GET` | `/api/v2/orders/{orderId}/xls/A3ERP2-sales-delivery-note` | `exportA3ERP2OrderSalesDeliveryNote` | Albarán A3ERP2 individual |
| `GET` | `/api/v2/orders/xls/A3ERP2-sales-delivery-note-filtered` | `exportA3ERP2OrderSalesDeliveryNoteWithFilters` | Albaranes A3ERP2 filtrados |
| `GET` | `/api/v2/orders/xls/facilcom-sales-delivery-note` | `exportFacilcomOrderSalesDeliveryNoteWithFilters` | Albaranes Facilcom filtrados |
| `GET` | `/api/v2/orders/{orderId}/xls/facilcom-single` | `exportFacilcomSingleOrder` | Albarán Facilcom individual |
| `GET` | `/api/v2/orders/xlsx/active-planned-products` | `exportActiveOrderPlannedProducts` | Productos planificados activos |

### Rutas de Exportación de Recepciones

| Método HTTP | Ruta | Método del Controlador | Descripción |
|------------|------|----------------------|-------------|
| `GET` | `/api/v2/raw-material-receptions/facilcom-xls` | `exportRawMaterialReceptionFacilcom` | Recepciones Facilcom |
| `GET` | `/api/v2/raw-material-receptions/a3erp-xls` | `exportRawMaterialReceptionA3erp` | Recepciones A3ERP |

### Rutas de Exportación de Despachos de Cebo

| Método HTTP | Ruta | Método del Controlador | Descripción |
|------------|------|----------------------|-------------|
| `GET` | `/api/v2/cebo-dispatches/facilcom-xlsx` | `exportCeboDispatchFacilcom` | Despachos Facilcom |
| `GET` | `/api/v2/cebo-dispatches/a3erp-xlsx` | `exportCeboDispatchA3erp` | Despachos A3ERP |
| `GET` | `/api/v2/cebo-dispatches/a3erp2-xlsx` | `exportCeboDispatchA3erp2` | Despachos A3ERP2 |

### Rutas de Exportación de Cajas

| Método HTTP | Ruta | Método del Controlador | Descripción |
|------------|------|----------------------|-------------|
| `GET` | `/api/v2/boxes/xlsx` | `exportBoxesReport` | Reporte completo de cajas |

### Rutas de Exportación de Pedidos (Superuser)

| Método HTTP | Ruta | Método del Controlador | Descripción |
|------------|------|----------------------|-------------|
| `GET` | `/api/v2/orders_report` | `exportOrders` | Exportación general de pedidos (solo superuser) |

**Respuesta**: Descarga directa del archivo Excel con Content-Type según formato (`.xlsx` o `.xls`).

---

## ⚙️ Configuración de Memoria y Tiempo

### Límites de Memoria

Las exportaciones grandes aumentan el límite de memoria según el tipo:

- **Exportaciones pequeñas**: `1024M` (1GB)
  - Pedidos individuales
  - Albaranes individuales
  - Listas pequeñas

- **Exportaciones grandes**: `2048M` (2GB)
  - Reportes completos de cajas
  - Recepciones/Despachos masivos
  - Múltiples albaranes filtrados

### Límites de Tiempo de Ejecución

- **Exportaciones rápidas**: Sin límite explícito (usa PHP default)
- **Exportaciones largas**: `300s` (5 minutos) o `600s` (10 minutos)

**Configuración**:
```php
ini_set('memory_limit', '2048M');
ini_set('max_execution_time', 600);
```

**Ubicaciones**:
- Métodos individuales de `ExcelController`
- Se aplican solo durante la ejecución del método

---

## 🔗 Integración con Otros Módulos

### Pedidos
- Exportaciones de albaranes, listas de cajas, lotes de productos
- Ver [Pedidos - General](../22-pedidos/20-Pedidos-General.md)

### Recepciones de Materia Prima
- Exportaciones en formato Facilcom y A3ERP
- Ver [Recepciones - Materia Prima](../26-recepciones-despachos/60-Recepciones-Materia-Prima.md)

### Despachos de Cebo
- Exportaciones en formato Facilcom y A3ERP
- Ver [Despachos - Cebo](../26-recepciones-despachos/61-Despachos-Cebo.md)

### Inventario
- Exportación de reportes de cajas
- Ver [Inventario - Cajas](../23-inventario/32-Cajas.md)

---

## 📝 Ejemplos de Uso

### Exportar Albarán A3ERP Individual

```bash
GET /api/v2/orders/123/xls/A3ERP-sales-delivery-note
Authorization: Bearer {token}
X-Tenant: {tenant_slug}
```

**Respuesta**: Descarga directa de `albaran_venta_#123.xls`

### Exportar Albaranes Filtrados

```bash
GET /api/v2/orders/xls/A3ERP-sales-delivery-note-filtered?active=true&customers[]=1&customers[]=2&loadDate[start]=2024-01-01&loadDate[end]=2024-12-31
Authorization: Bearer {token}
X-Tenant: {tenant_slug}
```

**Respuesta**: Descarga directa de `albaran_venta_filtrado.xls` con pedidos activos de los clientes 1 y 2 en el rango de fechas especificado.

### Exportar Reporte de Cajas con Límite

```bash
GET /api/v2/boxes/xlsx?limit=100
Authorization: Bearer {token}
X-Tenant: {tenant_slug}
```

**Respuesta**: Descarga directa de `reporte_cajas.xlsx` con máximo 100 registros (útil para testing).

---

## 🏗️ Formatos de Integración

### A3ERP
- Formato legado de sistema ERP A3
- Columnas específicas: `CABSERIE`, `CABNUMDOC`, `CABFECHA`, `CABCODCLI`, etc.
- Formato de archivo: `.xls` (Excel 97-2003)

### A3ERP2
- Variante de A3ERP que usa códigos Facilcom
- Solo para clientes con `facilcom_code` configurado
- Formato de archivo: `.xls`

### Facilcom
- Formato para sistema Facilcom
- Columnas y estructura específica de Facilcom
- Formato de archivo: `.xls` o `.xlsx` según implementación

---

## Observaciones Críticas y Mejoras Recomendadas

1. **Límites de Memoria Hardcoded** (múltiples métodos)
   - Los límites de memoria están hardcoded en cada método
   - **Problema**: No permite configuración centralizada
   - **Recomendación**: Mover a configuración en `config/excel.php` o método helper
   - **Ubicaciones**: `ExcelController.php:39`, `ExcelController.php:46`, etc.

2. **Límites de Tiempo Hardcoded** (múltiples métodos)
   - Los límites de tiempo están hardcoded
   - **Problema**: No permite ajuste según entorno
   - **Recomendación**: Configuración centralizada o variable de entorno
   - **Ubicaciones**: `ExcelController.php:69`, `ExcelController.php:280`, etc.

3. **Manejo de Errores Inconsistente**
   - Algunos métodos tienen try-catch (ej: `exportCeboDispatchFacilcom`), otros no
   - **Problema**: Errores no manejados pueden exponer información sensible
   - **Recomendación**: Implementar manejo de errores uniforme en todos los métodos
   - **Ubicaciones**: Métodos con try-catch: `405-460`, `434-460`, `463-489`, `492-518`, `521-547`

4. **Duplicación de Lógica de Filtrado** (`ExcelController.php:66-158`, `160-249`, `277-375`)
   - La lógica de filtrado está duplicada en múltiples métodos
   - **Problema**: Cambios en filtros requieren actualizar múltiples lugares
   - **Recomendación**: Extraer a método privado o trait compartido
   - **Ubicaciones**: `exportA3ERPOrderSalesDeliveryNoteWithFilters`, `exportFacilcomOrderSalesDeliveryNoteWithFilters`, `exportA3ERP2OrderSalesDeliveryNoteWithFilters`

5. **Falta de Validación de Filtros**
   - Los filtros se aplican directamente sin validación
   - **Problema**: Filtros mal formados pueden causar errores SQL
   - **Recomendación**: Agregar validación de Request usando Form Requests
   - **Ubicaciones**: Todos los métodos que aceptan `Request $request`

6. **N+1 Queries Potenciales**
   - Algunas clases Export cargan relaciones pero pueden tener N+1
   - **Problema**: Exportaciones grandes pueden ser lentas
   - **Recomendación**: Revisar y optimizar eager loading en todas las clases Export
   - **Ubicaciones**: Verificar métodos `collection()` en clases Export

7. **Formato de Fecha Inconsistente**
   - Algunas exportaciones usan `date('d/m/Y')`, otras pueden usar formato diferente
   - **Problema**: Inconsistencia en formato de fechas entre exportaciones
   - **Recomendación**: Centralizar formato de fecha en helper o configuración
   - **Ubicaciones**: Múltiples clases Export

8. **Falta de Límite de Registros por Defecto**
   - Algunas exportaciones pueden exportar millones de registros
   - **Problema**: Puede causar timeouts o problemas de memoria
   - **Recomendación**: Agregar límite máximo por defecto y permitir override
   - **Ubicaciones**: Métodos que no tienen parámetro `limit`

9. **Parámetro `limit` Solo para Testing**
   - El parámetro `limit` está documentado como "útil para testing"
   - **Problema**: No hay validación ni documentación clara de cuándo usarlo
   - **Recomendación**: Documentar mejor o crear endpoint separado para testing
   - **Ubicaciones**: `exportBoxesReport`, `exportRawMaterialReceptionFacilcom`, etc.

10. **Falta de Paginación en Exportaciones Grandes**
    - Las exportaciones cargan todos los datos en memoria
    - **Problema**: Exportaciones muy grandes pueden fallar
    - **Recomendación**: Considerar usar `FromQuery` con chunking o streaming
    - **Ubicaciones**: Clases Export que usan `FromCollection`

11. **Código Comentado en OrderExport** (si existe)
    - Verificar si hay código comentado o métodos no utilizados
    - **Problema**: Código muerto puede confundir
    - **Recomendación**: Limpiar código comentado

12. **Falta de Logging de Exportaciones**
    - No hay logging de exportaciones generadas
    - **Problema**: Dificulta auditoría y debugging
    - **Recomendación**: Agregar logging de exportaciones (quién, qué, cuándo)
    - **Ubicaciones**: Todos los métodos de exportación

13. **Falta de Validación de Permisos Específicos**
    - Las rutas están protegidas por roles generales
    - **Problema**: No hay validación de permisos específicos por tipo de exportación
    - **Recomendación**: Considerar permisos más granulares si es necesario
    - **Ubicaciones**: Rutas en `routes/api.php`

14. **Estilos No Consistidos**
    - Solo algunas clases Export implementan `WithStyles`
    - **Problema**: Inconsistencia visual entre exportaciones
    - **Recomendación**: Establecer estilo base y aplicarlo a todas las exportaciones
    - **Ubicaciones**: Clases Export sin `WithStyles`

15. **Falta de Validación de Existencia de Datos**
    - Algunos métodos no validan si existen datos antes de exportar
    - **Problema**: Puede generar archivos Excel vacíos sin aviso
    - **Recomendación**: Validar existencia de datos y retornar error si no hay
    - **Ubicaciones**: Métodos que exportan datos filtrados

