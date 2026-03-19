# Pedidos - Generación de Documentos PDF y Envío por Email

## ⚠️ Estado de la API
- **v1**: Obsoleta (no documentada)
- **v2**: Versión activa (este documento)

---

## 📋 Visión General

El sistema de generación de documentos permite crear PDFs de diferentes tipos relacionados con pedidos (hojas de pedido, albaranes, CMR, etc.) y enviarlos automáticamente por email a clientes, transportistas y vendedores.

**Arquitectura**:
- **OrderPDFService**: Servicio que genera PDFs usando Snappdf (headless Chrome)
- **OrderMailerService**: Servicio que envía emails con documentos adjuntos
- **PDFController**: Controlador que expone endpoints para generar PDFs
- **OrderDocumentController**: Controlador que expone endpoints para enviar documentos por email

---

## 🔧 Servicios

### OrderPDFService

**Archivo**: `app/Services/OrderPDFService.php`

**Responsabilidad**: Generar documentos PDF desde vistas Blade usando headless Chrome.

#### Método Principal: `generateDocument()`

```php
public function generateDocument(Order $order, string $docType, string $viewPath): string
```

**Parámetros**:
- `$order`: Instancia del modelo Order
- `$docType`: Tipo de documento (para nombre de archivo)
- `$viewPath`: Ruta de la vista Blade (ej: `pdf.v2.orders.loading_note`)

**Retorna**: Ruta completa del archivo PDF generado

**Comportamiento**:
1. Construye ruta de archivo: `storage/app/public/{docType}-{formattedId}.pdf`
2. **Caché de 30 segundos**: Si el PDF existe y fue generado hace menos de 30 segundos, lo reutiliza
3. Renderiza la vista Blade pasando `$order` como variable `entity`
4. Genera PDF usando Snappdf con Chromium headless
5. Guarda el archivo en el storage

**Configuración de Chromium**:
- Márgenes: top=10mm, right=30mm, bottom=10mm, left=10mm
- Argumentos: `--no-sandbox`, `--disable-gpu`, etc. (optimizados para Docker)

### OrderMailerService

**Archivo**: `app/Services/OrderMailerService.php`

**Responsabilidad**: Enviar documentos por email a diferentes destinatarios.

#### Método: `sendStandardDocuments()`

Envía documentos estándar según configuración en `config/order_documents.php`.

**Destinatarios configurados**:
- **customer**: Recibe `loading-note`, `packing-list`
- **salesperson**: Recibe `loading-note`, `packing-list`
- **transport**: Recibe `CMR`, `packing-list`

#### Método: `sendDocuments()`

Envía documentos personalizados según array de configuración recibido.

**Estructura del array**:
```php
[
    [
        'type' => 'loading-note',
        'recipients' => ['customer', 'transport']
    ],
    // ...
]
```

#### Método Privado: `getEmailsFromEntities()`

Obtiene emails desde las entidades relacionadas:
- `customer`: Desde `Order.emailsArray` y `Order.ccEmailsArray`
- `transport`: Desde `Order.transport.emailsArray`
- `salesperson`: Desde `Order.salesperson.emailsArray`

---

## 📡 Controladores

### PDFController

**Archivo**: `app/Http/Controllers/v2/PDFController.php`

Controlador para generar y descargar PDFs individuales.

#### Método Genérico: `generatePdf()`

```php
private function generatePdf($entity, $viewPath, $fileName, $extraData = [])
```

Método privado reutilizado por todos los métodos específicos.

#### Métodos Específicos

Todos retornan un stream download del PDF:

##### `generateOrderSheet($orderId)`
```php
GET /v2/orders/{orderId}/pdf/order-sheet
```
- **Vista**: `pdf.v2.orders.order_sheet`
- **Nombre archivo**: `Hoja_de_pedido_{formattedId}.pdf`

##### `generateOrderSigns($orderId)`
```php
GET /v2/orders/{orderId}/pdf/order-signs
```
- **Vista**: `pdf.v2.orders.order_signs`
- **Nombre archivo**: `Letreros_transporte_{formattedId}.pdf`

##### `generateRestrictedOrderSigns($orderId)`
```php
GET /v2/orders/{orderId}/pdf/restricted-order-signs
```
- **Vista**: `pdf.v2.orders.restricted_order_signs`
- **Nombre archivo**: `Letreros_transporte_restringidos_{formattedId}.pdf`

##### `generateOrderPackingList($orderId)`
```php
GET /v2/orders/{orderId}/pdf/order-packing-list
```
- **Vista**: `pdf.v2.orders.order_packing_list`
- **Nombre archivo**: `Packing_list_{formattedId}.pdf`

##### `generateLoadingNote($orderId)`
```php
GET /v2/orders/{orderId}/pdf/loading-note
```
- **Vista**: `pdf.v2.orders.loading_note`
- **Nombre archivo**: `Nota_de_carga_{formattedId}.pdf`

##### `generateRestrictedLoadingNote($orderId)`
```php
GET /v2/orders/{orderId}/pdf/restricted-loading-note
```
- **Vista**: `pdf.v2.orders.restricted_loading_note`
- **Nombre archivo**: `Nota_de_carga_restringida_{formattedId}.pdf`

##### `generateOrderCMR($orderId)`
```php
GET /v2/orders/{orderId}/pdf/order-cmr
```
- **Vista**: `pdf.v2.orders.CMR`
- **Nombre archivo**: `CMR_{formattedId}.pdf`

##### `generateValuedLoadingNote($orderId)`
```php
GET /v2/orders/{orderId}/pdf/valued-loading-note
```
- **Vista**: `pdf.v2.orders.valued_loading_note`
- **Nombre archivo**: `Nota_de_carga_valorada_{formattedId}.pdf`

##### `generateIncident($orderId)`
```php
GET /v2/orders/{orderId}/pdf/incident
```
- **Vista**: `pdf.v2.orders.incident`
- **Nombre archivo**: `Incidente_{formattedId}.pdf`

##### `generateOrderConfirmation($orderId)`
```php
GET /v2/orders/{orderId}/pdf/order-confirmation
```
- **Vista**: `pdf.v2.orders.order_confirmation`
- **Nombre archivo**: `Confirmacion_pedido_{formattedId}.pdf`

##### `generateTransportPickupRequest($orderId)`
```php
GET /v2/orders/{orderId}/pdf/transport-pickup-request
```
- **Vista**: `pdf.v2.orders.transport_pickup_request`
- **Nombre archivo**: `Solicitud_recogida_{formattedId}.pdf`

### OrderDocumentController

**Archivo**: `app/Http/Controllers/v2/OrderDocumentController.php`

Controlador para enviar documentos por email.

#### `sendStandardDocumentation($orderId)`
```php
POST /v2/orders/{orderId}/send-standard-documents
```

**Comportamiento**:
- Usa configuración de `config/order_documents.standard_recipients`
- Envía documentos según destinatarios configurados
- Usa plantillas de email específicas por destinatario

#### `sendCustomDocumentation(Request $request, $orderId)`
```php
POST /v2/orders/{orderId}/send-custom-documents
```

**Validación**:
```php
[
    'documents' => 'required|array',
    'documents.*.type' => 'required|string',
    'documents.*.recipients' => 'required|array',
]
```

**Request body**:
```json
{
    "documents": [
        {
            "type": "loading-note",
            "recipients": ["customer", "transport"]
        },
        {
            "type": "CMR",
            "recipients": ["transport"]
        }
    ]
}
```

---

## 📄 Configuración

### Archivo: `config/order_documents.php`

Define los tipos de documentos disponibles y sus configuraciones:

```php
'documents' => [
    'loading-note' => [
        'document_name' => 'Nota de Carga',
        'view_path' => 'pdf.v2.orders.loading_note',
        'subject_template' => 'Nota de Carga - Pedido #{order_id}',
        'body_template' => 'emails.orders.generic',
    ],
    // ... más documentos
],

'standard_recipients' => [
    'customer' => ['loading-note', 'packing-list'],
    'salesperson' => ['loading-note', 'packing-list'],
    'transport' => ['CMR', 'packing-list'],
],
```

---

## 📧 Plantillas de Email

### StandardOrderDocuments

**Archivo**: `app/Mail/StandardOrderDocuments.php`

Mailable para envío estándar con múltiples documentos adjuntos.

### GenericOrderDocument

**Archivo**: `app/Mail/GenericOrderDocument.php`

Mailable genérico para envío personalizado con un documento.

---

## 🔍 Flujo de Envío Estándar

1. **Usuario solicita**: `POST /v2/orders/{id}/send-standard-documents`
2. **OrderMailerService**:
   - Lee configuración `standard_recipients`
   - Para cada destinatario (customer, transport, salesperson):
     - Obtiene emails desde entidades relacionadas
     - Genera PDFs de los documentos configurados
     - Crea email con adjuntos
     - Envía email con BCC a `tenantSetting('company.bcc_email')`

---

## 📍 Rutas API

### Generación de PDFs (Descarga)
- `GET /v2/orders/{id}/pdf/order-sheet`
- `GET /v2/orders/{id}/pdf/order-signs`
- `GET /v2/orders/{id}/pdf/restricted-order-signs`
- `GET /v2/orders/{id}/pdf/order-packing-list`
- `GET /v2/orders/{id}/pdf/loading-note`
- `GET /v2/orders/{id}/pdf/restricted-loading-note`
- `GET /v2/orders/{id}/pdf/order-cmr`
- `GET /v2/orders/{id}/pdf/valued-loading-note`
- `GET /v2/orders/{id}/pdf/incident`
- `GET /v2/orders/{id}/pdf/order-confirmation`
- `GET /v2/orders/{id}/pdf/transport-pickup-request`

### Envío por Email
- `POST /v2/orders/{id}/send-standard-documents`
- `POST /v2/orders/{id}/send-custom-documents`

---

## 🔐 Permisos y Autenticación

**Middleware requerido**:
- `auth:sanctum`: Autenticación requerida
- `role:superuser,manager,admin,store_operator`: Roles permitidos

**Rutas**: Todas bajo `/v2/orders/{id}/pdf/*` y `/v2/orders/{id}/send-*`

---

## 📝 Ejemplos de Uso

### Generar y Descargar PDF
```http
GET /v2/orders/5/pdf/loading-note
Authorization: Bearer {token}
X-Tenant: empresa1
```

**Respuesta**: Stream download del PDF

### Enviar Documentación Estándar
```http
POST /v2/orders/5/send-standard-documents
Authorization: Bearer {token}
X-Tenant: empresa1
```

### Enviar Documentación Personalizada
```http
POST /v2/orders/5/send-custom-documents
Content-Type: application/json
Authorization: Bearer {token}
X-Tenant: empresa1

{
    "documents": [
        {
            "type": "loading-note",
            "recipients": ["customer"]
        },
        {
            "type": "CMR",
            "recipients": ["transport"]
        }
    ]
}
```

---

## 🎨 Vistas Blade

Las vistas se encuentran en `resources/views/pdf/v2/orders/`:

- `order_sheet.blade.php`
- `order_signs.blade.php`
- `order_packing_list.blade.php`
- `loading_note.blade.php`
- `restricted_loading_note.blade.php`
- `CMR.blade.php`
- `valued_loading_note.blade.php`
- `incident.blade.php`
- `order_confirmation.blade.php`
- `transport_pickup_request.blade.php`

**Variable disponible**: `$entity` (instancia de Order)

---

## Observaciones Críticas y Mejoras Recomendadas

### ⚠️ Caché de 30 Segundos Arbitrario

1. **Tiempo de Caché Hardcodeado** (`app/Services/OrderPDFService.php:36`)
   - Caché de 30 segundos está hardcodeado
   - **Líneas**: 36
   - **Problema**: No es configurable, puede ser demasiado o poco según caso
   - **Recomendación**: 
     - Mover a configuración
     - O permitir parámetro opcional

### ⚠️ Path de Chromium Hardcodeado

2. **Ruta de Chrome Hardcodeada** (`app/Services/OrderPDFService.php:50, app/Http/Controllers/v2/PDFController.php:30`)
   - `/usr/bin/google-chrome` está hardcodeado
   - **Líneas**: 50, 30
   - **Problema**: Puede no existir en diferentes entornos
   - **Recomendación**: 
     - Usar variable de entorno `CHROMIUM_PATH`
     - O detectar automáticamente la ruta

### ⚠️ Manejo de Errores

3. **No Valida Vista Existe** (`app/Services/OrderPDFService.php:46`)
   - No valida que la vista Blade exista antes de renderizar
   - **Líneas**: 46
   - **Problema**: Error genérico si la vista no existe
   - **Recomendación**: Validar existencia de vista o manejar excepción

4. **No Valida PDF Generado Correctamente** (`app/Services/OrderPDFService.php:78`)
   - No valida que el PDF se haya generado correctamente antes de retornar
   - **Líneas**: 78
   - **Problema**: Puede retornar ruta de archivo corrupto
   - **Recomendación**: Validar tamaño de archivo o intentar leer PDF

### ⚠️ Almacenamiento de Archivos

5. **Archivos en Storage Público** (`app/Services/OrderPDFService.php:27`)
   - PDFs se guardan en `storage/app/public/`
   - **Líneas**: 27
   - **Problema**: Pueden acumularse indefinidamente
   - **Recomendación**: 
     - Limpieza automática de archivos antiguos
     - O usar storage temporal y eliminar después de enviar

6. **No Hay Limpieza de Archivos** (`app/Services/OrderPDFService.php`)
   - Los PDFs se acumulan en storage
   - **Problema**: Puede llenar el disco
   - **Recomendación**: Implementar comando de limpieza o usar storage temporal

### ⚠️ Envío de Email

7. **No Valida Configuración de Email** (`app/Services/OrderMailerService.php:88-91`)
   - No valida que la configuración de mail esté correcta antes de enviar
   - **Líneas**: 88-91
   - **Problema**: Puede fallar silenciosamente
   - **Recomendación**: Validar configuración o capturar excepciones explícitamente

8. **BCC Hardcodeado** (`app/Services/OrderMailerService.php:90`)
   - Usa `tenantSetting('company.bcc_email')` sin validar que exista
   - **Líneas**: 90, 144
   - **Problema**: Puede fallar si el setting no existe
   - **Recomendación**: Validar existencia o usar valor por defecto

### ⚠️ Manejo de Emails Vacíos

9. **Continúa Si No Hay Emails** (`app/Services/OrderMailerService.php:40-41`)
   - Si no hay emails, simplemente continúa con el siguiente destinatario
   - **Líneas**: 40-41
   - **Estado**: Correcto, pero podría loguearse para debugging

### ⚠️ Referencia a Relación Inexistente

10. **Referencia a `$order->comercial`** (`app/Services/OrderMailerService.php:174-177`)
    - En `getEmailsFromEntities()` hay referencia a `$order->comercial` que no existe
    - **Líneas**: 174-177
    - **Problema**: Código muerto que nunca se ejecutará
    - **Recomendación**: Eliminar código muerto

### ⚠️ Falta de Validación de Orden

11. **No Valida Estado del Order** (`app/Http/Controllers/v2/OrderDocumentController.php`)
    - No valida si el pedido está en estado válido antes de generar documentos
    - **Problema**: Puede generar documentos para pedidos sin productos
    - **Recomendación**: Validar que el pedido tenga datos necesarios

### ⚠️ Error Handling en PDFService

12. **No Maneja Errores de Chromium** (`app/Services/OrderPDFService.php:75`)
    - Si Chromium falla, no hay manejo de excepción
    - **Líneas**: 75
    - **Problema**: Error genérico difícil de debuggear
    - **Recomendación**: Capturar excepciones y loguear errores específicos

---

**Última actualización**: Documentación generada desde código fuente en fecha de generación.

