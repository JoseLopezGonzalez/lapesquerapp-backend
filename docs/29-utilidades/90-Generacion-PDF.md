# Utilidades - Generación de Documentos PDF

## ⚠️ Estado de la API
- **v1**: Obsoleta (no documentada)
- **v2**: Versión activa (este documento)

---

## 📋 Visión General

El sistema de generación de PDF permite crear documentos PDF dinámicos desde vistas Blade usando headless Chrome (Snappdf). La generación de PDF está centralizada en dos componentes principales:

1. **PDFController** (`app/Http/Controllers/v2/PDFController.php`): Controlador que expone endpoints HTTP para generar PDFs directamente como descarga
2. **OrderPDFService** (`app/Services/OrderPDFService.php`): Servicio especializado en generar PDFs de pedidos y guardarlos en el storage para envío por email

**Arquitectura**:
- Utiliza la librería **Snappdf** (wrapper para Chromium headless)
- Renderiza vistas Blade en HTML
- Convierte HTML a PDF usando Chromium
- Configurado específicamente para Docker/entornos serverless

---

## 🔧 Controlador: PDFController

**Archivo**: `app/Http/Controllers/v2/PDFController.php`

### Método Privado: `generatePdf()`

Método genérico para generar PDFs de cualquier entidad.

```php
private function generatePdf($entity, $viewPath, $fileName, $extraData = [])
```

**Parámetros**:
- `$entity`: Instancia del modelo (ej: `Order`)
- `$viewPath`: Ruta de la vista Blade (ej: `'pdf.v2.orders.order_sheet'`)
- `$fileName`: Nombre del archivo PDF (sin extensión)
- `$extraData`: Array opcional de datos adicionales para la vista

**Comportamiento**:
1. Renderiza la vista Blade pasando `$entity` como variable `entity`
2. Configura Snappdf con la ruta de Chromium: `/usr/bin/google-chrome`
3. Aplica márgenes: top=10mm, right=30mm, bottom=10mm, left=10mm
4. Agrega argumentos de Chromium para optimización en Docker
5. Genera el PDF y lo retorna como stream download

**Configuración de Chromium**:
- Márgenes personalizados
- Argumentos de optimización: `--no-sandbox`, `--disable-gpu`, `--disable-translate`, `--disable-extensions`, etc.
- Argumentos específicos para PDF: `--no-pdf-header-footer`, `--print-to-pdf-no-header`, `--hide-scrollbars`

### Métodos Públicos para Pedidos

Todos los métodos reciben `$orderId` como parámetro y generan un PDF específico:

#### `generateOrderSheet($orderId)`
- **Vista**: `pdf.v2.orders.order_sheet`
- **Archivo**: `Hoja_de_pedido_{formattedId}.pdf`
- **Descripción**: Hoja de pedido estándar

#### `generateOrderSigns($orderId)`
- **Vista**: `pdf.v2.orders.order_signs`
- **Archivo**: `Letreros_transporte_{formattedId}.pdf`
- **Descripción**: Letreros para transporte

#### `generateOrderPackingList($orderId)`
- **Vista**: `pdf.v2.orders.order_packing_list`
- **Archivo**: `Packing_list_{formattedId}.pdf`
- **Descripción**: Lista de empaque

#### `generateLoadingNote($orderId)`
- **Vista**: `pdf.v2.orders.loading_note`
- **Archivo**: `Nota_de_carga_{formattedId}.pdf`
- **Descripción**: Nota de carga estándar

#### `generateRestrictedLoadingNote($orderId)`
- **Vista**: `pdf.v2.orders.restricted_loading_note`
- **Archivo**: `Nota_de_carga_restringida_{formattedId}.pdf`
- **Descripción**: Nota de carga restringida

#### `generateOrderCMR($orderId)`
- **Vista**: `pdf.v2.orders.CMR`
- **Archivo**: `CMR_{formattedId}.pdf`
- **Descripción**: Documento CMR (Convention Merchandises Routiers)

#### `generateDeliveryNote($orderId)`
- **Vista**: `pdf.v2.orders.delivery_note`
- **Archivo**: `Nota_de_entrega_{formattedId}.pdf`
- **Descripción**: Nota de entrega

#### `generateInvoice($orderId)`
- **Vista**: `pdf.v2.orders.invoice`
- **Archivo**: `Factura_{formattedId}.pdf`
- **Descripción**: Factura

#### `generateValuedLoadingNote($orderId)`
- **Vista**: `pdf.v2.orders.valued_loading_note`
- **Archivo**: `Nota_de_carga_valorada_{formattedId}.pdf`
- **Descripción**: Nota de carga valorada

#### `generateOrderConfirmation($orderId)`
- **Vista**: `pdf.v2.orders.order_confirmation`
- **Archivo**: `Confirmacion_de_pedido_{formattedId}.pdf`
- **Descripción**: Confirmación de pedido

#### `generateTransportPickupRequest($orderId)`
- **Vista**: `pdf.v2.orders.transport_pickup_request`
- **Archivo**: `Solicitud_de_recogida_{formattedId}.pdf`
- **Descripción**: Solicitud de recogida por transporte

#### `generateIncident($orderId)`
- **Vista**: `pdf.v2.orders.incident`
- **Archivo**: `Incidencia_{formattedId}.pdf`
- **Descripción**: Documento de incidencia

---

## 🔧 Servicio: OrderPDFService

**Archivo**: `app/Services/OrderPDFService.php`

### Método Principal: `generateDocument()`

Genera un documento PDF y lo guarda en el storage, retornando la ruta completa del archivo.

```php
public function generateDocument(Order $order, string $docType, string $viewPath): string
```

**Parámetros**:
- `$order`: Instancia del modelo `Order`
- `$docType`: Tipo de documento (para nombre de archivo)
- `$viewPath`: Ruta de la vista Blade

**Retorna**: Ruta completa del archivo PDF generado (ej: `/path/to/storage/app/public/{docType}-{formattedId}.pdf`)

**Comportamiento**:
1. Construye la ruta del archivo: `storage/app/public/{docType}-{formattedId}.pdf`
2. **Caché de 30 segundos**: Si el PDF existe y fue generado hace menos de 30 segundos, lo reutiliza
3. Si el PDF existe pero es más antiguo de 30 segundos, lo elimina
4. Renderiza la vista Blade pasando `$order` como variable `entity`
5. Genera PDF usando Snappdf con la misma configuración que `PDFController`
6. Guarda el archivo en el storage
7. Retorna la ruta completa

**Nota**: Este servicio está diseñado específicamente para generar PDFs que luego serán enviados por email (ver `OrderMailerService` en [Pedidos - Documentos](../22-pedidos/22-Pedidos-Documentos.md)).

---

## 🛣️ Rutas API

Todas las rutas están protegidas por autenticación Sanctum y son accesibles para roles: `superuser`, `manager`, `admin`, `store_operator`.

### Rutas de Generación Directa (Stream Download)

Todas las rutas siguen el patrón: `GET /api/v2/orders/{orderId}/pdf/{document-type}`

| Método HTTP | Ruta | Método del Controlador | Descripción |
|------------|------|----------------------|-------------|
| `GET` | `/api/v2/orders/{orderId}/pdf/order-sheet` | `generateOrderSheet` | Hoja de pedido |
| `GET` | `/api/v2/orders/{orderId}/pdf/order-signs` | `generateOrderSigns` | Letreros de transporte |
| `GET` | `/api/v2/orders/{orderId}/pdf/order-packing-list` | `generateOrderPackingList` | Lista de empaque |
| `GET` | `/api/v2/orders/{orderId}/pdf/loading-note` | `generateLoadingNote` | Nota de carga |
| `GET` | `/api/v2/orders/{orderId}/pdf/restricted-loading-note` | `generateRestrictedLoadingNote` | Nota de carga restringida |
| `GET` | `/api/v2/orders/{orderId}/pdf/order-cmr` | `generateOrderCMR` | Documento CMR |
| `GET` | `/api/v2/orders/{orderId}/pdf/valued-loading-note` | `generateValuedLoadingNote` | Nota de carga valorada |
| `GET` | `/api/v2/orders/{orderId}/pdf/incident` | `generateIncident` | Documento de incidencia |
| `GET` | `/api/v2/orders/{orderId}/pdf/order-confirmation` | `generateOrderConfirmation` | Confirmación de pedido |
| `GET` | `/api/v2/orders/{orderId}/pdf/transport-pickup-request` | `generateTransportPickupRequest` | Solicitud de recogida |

**Respuesta**: Stream download directo del archivo PDF con Content-Type `application/pdf`.

---

## 🏗️ Arquitectura Técnica

### Dependencias

- **Snappdf** (`beganovich/snappdf`): Librería PHP que envuelve Chromium headless para generar PDFs desde HTML
- **Chromium/Chrome**: Debe estar instalado en `/usr/bin/google-chrome` (configuración específica para Docker)

### Flujo de Generación

```
1. Cliente HTTP → Endpoint (PDFController)
2. PDFController → Carga modelo (Order)
3. PDFController → Renderiza vista Blade
4. PDFController → Configura Snappdf
5. Snappdf → Inicia Chromium headless
6. Chromium → Renderiza HTML/CSS
7. Chromium → Genera PDF
8. PDFController → Retorna stream download
```

### Vista Blade

Las vistas están ubicadas en `resources/views/pdf/v2/orders/` y siguen la convención de nombres:
- `order_sheet.blade.php`
- `order_signs.blade.php`
- `loading_note.blade.php`
- etc.

**Variable disponible en la vista**:
- `$entity`: Instancia del modelo `Order`

---

## 📦 Almacenamiento

### PDFController
- **No almacena archivos**: Genera PDFs en memoria y los retorna como stream download
- Los PDFs no se persisten en el sistema de archivos

### OrderPDFService
- **Almacena en**: `storage/app/public/`
- **Formato de nombre**: `{docType}-{formattedId}.pdf`
- **Caché**: 30 segundos (reutiliza PDFs recientes)
- **Limpieza automática**: Elimina PDFs obsoletos (>30 segundos) antes de regenerar

---

## ⚙️ Configuración

### Ruta de Chromium

**Hardcoded en ambos archivos**: `/usr/bin/google-chrome`

**Ubicación**:
- `PDFController.php:30`
- `OrderPDFService.php:50`

### Márgenes

Configurados de forma fija:
- **Top**: 10mm
- **Right**: 30mm
- **Bottom**: 10mm
- **Left**: 10mm

### Argumentos de Chromium

Se aplican múltiples argumentos para optimización en entornos Docker/serverless:
- `--no-sandbox`: Necesario en contenedores
- `--disable-gpu`: Desactiva GPU (no disponible en servidores)
- `--disable-translate`: Desactiva traducción automática
- `--disable-extensions`: Desactiva extensiones
- `--disable-sync`: Desactiva sincronización
- `--disable-background-networking`: Optimización
- `--disable-software-rasterizer`: Optimización
- `--disable-default-apps`: Optimización
- `--disable-dev-shm-usage`: Evita problemas de memoria compartida
- `--safebrowsing-disable-auto-update`: Optimización
- `--run-all-compositor-stages-before-draw`: Optimización de renderizado
- `--no-first-run`: Evita primera ejecución
- `--no-margins`: Sin márgenes adicionales
- `--print-to-pdf-no-header`: Sin header en PDF
- `--no-pdf-header-footer`: Sin header/footer
- `--hide-scrollbars`: Oculta scrollbars
- `--ignore-certificate-errors`: Ignora errores de certificados

---

## 🔗 Integración con Otros Módulos

### OrderMailerService

El `OrderPDFService` está diseñado para trabajar con `OrderMailerService` (ver [Pedidos - Documentos](../22-pedidos/22-Pedidos-Documentos.md)):
- `OrderMailerService` llama a `OrderPDFService::generateDocument()` para generar PDFs antes de enviarlos por email
- Los PDFs se guardan temporalmente en `storage/app/public/` para adjuntarlos a los emails

---

## 📝 Ejemplos de Uso

### Generar PDF de Hoja de Pedido (Directo)

```bash
GET /api/v2/orders/123/pdf/order-sheet
Authorization: Bearer {token}
X-Tenant: {tenant_slug}
```

**Respuesta**: Descarga directa del archivo `Hoja_de_pedido_#123.pdf`

### Generar PDF para Email

```php
use App\Services\OrderPDFService;

$orderPDFService = new OrderPDFService();
$pdfPath = $orderPDFService->generateDocument(
    $order,
    'loading_note',
    'pdf.v2.orders.loading_note'
);
// $pdfPath = '/path/to/storage/app/public/loading_note-123.pdf'
```

---

## Observaciones Críticas y Mejoras Recomendadas

1. **Ruta de Chromium Hardcoded** (`PDFController.php:30`, `OrderPDFService.php:50`)
   - La ruta `/usr/bin/google-chrome` está hardcoded en ambos archivos
   - **Problema**: No es flexible para diferentes entornos (local, staging, producción con diferentes paths)
   - **Recomendación**: Mover a configuración en `config/pdf.php` o variable de entorno `CHROMIUM_PATH`
   - **Impacto**: Si Chromium está en otra ubicación, la generación de PDFs fallará

2. **Márgenes Hardcoded** (`PDFController.php:33-36`, `OrderPDFService.php:54-57`)
   - Los márgenes están fijos en el código
   - **Problema**: No permite personalización por tipo de documento
   - **Recomendación**: Mover a configuración o permitir pasar márgenes como parámetro

3. **Argumentos de Chromium Duplicados** (`PDFController.php:39-61`, `OrderPDFService.php:53-72`)
   - La misma lista de argumentos está duplicada en ambos archivos
   - **Problema**: Si se necesita cambiar un argumento, hay que hacerlo en dos lugares
   - **Recomendación**: Extraer a un método compartido o clase de configuración

4. **Bucle For Sin Ejecución** (`PDFController.php:59-61`)
   - El bucle `foreach ($chromiumArgs as $arg)` existe pero está comentado/vacío
   - **Problema**: Los argumentos no se están aplicando realmente (aunque cada uno se agrega manualmente)
   - **Recomendación**: Verificar si el bucle es necesario o eliminar código muerto

5. **Caché de 30 Segundos Fijo** (`OrderPDFService.php:36`)
   - El tiempo de caché está hardcoded a 30 segundos
   - **Problema**: No permite configurar el tiempo de caché según necesidades
   - **Recomendación**: Mover a configuración o parámetro opcional

6. **Limpieza de Archivos Antiguos Manual** (`OrderPDFService.php:41`)
   - Los PDFs antiguos se eliminan manualmente antes de regenerar
   - **Problema**: No hay limpieza automática de archivos huérfanos si el proceso falla
   - **Recomendación**: Implementar un comando Artisan para limpieza periódica de archivos antiguos

7. **Falta de Manejo de Errores en PDFController**
   - No hay try-catch explícito en los métodos públicos
   - **Problema**: Si Chromium falla o la vista no existe, el error será genérico
   - **Recomendación**: Agregar manejo de excepciones con mensajes descriptivos

8. **Falta de Validación de Orden Existente**
   - Los métodos públicos usan `findOrFail()` que lanza excepción automáticamente
   - **Problema**: Correcto, pero no hay validación adicional (ej: verificar permisos del usuario sobre el pedido)
   - **Recomendación**: Agregar validación de autorización si es necesario

9. **OrderPDFService Específico para Orders**
   - El servicio está diseñado solo para `Order`
   - **Problema**: Si se necesita generar PDFs de otras entidades, habría que crear servicios similares
   - **Recomendación**: Considerar un servicio genérico `PDFService` con métodos específicos por entidad

10. **Sin Límite de Tamaño de PDF**
    - No hay validación del tamaño máximo de PDF generado
    - **Problema**: PDFs muy grandes pueden causar problemas de memoria o timeout
    - **Recomendación**: Agregar límites de memoria/timeout o validación de tamaño

11. **Documentación PHP Doc Incompleta** (`PDFController.php:16-25`)
    - El comentario del método `generatePdf()` menciona parámetros que no existen (`$modelClass`, `$entityId`)
    - **Problema**: La documentación no coincide con la implementación real
    - **Recomendación**: Actualizar la documentación PHP Doc para reflejar los parámetros reales

12. **Falta de Logging**
    - No hay logging de generación de PDFs (exitosos o fallidos)
    - **Problema**: Dificulta debugging y monitoreo
    - **Recomendación**: Agregar logging para rastrear generación de PDFs, tiempos de ejecución y errores

13. **Vistas Blade No Validadas**
    - No hay validación de que la vista Blade exista antes de intentar renderizarla
    - **Problema**: Si la vista no existe, el error será genérico de Blade
    - **Recomendación**: Validar existencia de vista antes de renderizar o manejar excepción de Blade

