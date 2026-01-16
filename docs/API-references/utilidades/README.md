# Utilidades

Documentación de endpoints para generación de PDFs y exportación a Excel.

## Índice

- [Generación de PDFs](#generación-de-pdfs)
- [Exportación a Excel](#exportación-a-excel)
- [Envío de Documentos](#envío-de-documentos)

---

## Generación de PDFs

Todos los endpoints de PDF retornan archivos PDF para descarga directa. Los headers de respuesta incluyen `Content-Type: application/pdf` y `Content-Disposition: attachment; filename="..."`.

### Headers Requeridos

```http
X-Tenant: {subdomain}
Authorization: Bearer {access_token}
```

---

## PDFs de Pedidos

### Hoja de Pedido

```http
GET /api/v2/orders/{orderId}/pdf/order-sheet
```

#### Path Parameters

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| orderId | integer | ID del pedido |

#### Response Exitosa (200)

**Content-Type:** `application/pdf`

**Content-Disposition:** `attachment; filename="order-sheet-{orderId}.pdf"`

Retorna un archivo PDF con la hoja de pedido.

---

### Letreros de Transporte

```http
GET /api/v2/orders/{orderId}/pdf/order-signs
```

#### Response Exitosa (200)

Retorna un archivo PDF con los letreros de transporte del pedido.

---

### Lista de Empaque

```http
GET /api/v2/orders/{orderId}/pdf/order-packing-list
```

#### Response Exitosa (200)

Retorna un archivo PDF con la lista de empaque del pedido.

---

### Nota de Carga

```http
GET /api/v2/orders/{orderId}/pdf/loading-note
```

#### Response Exitosa (200)

Retorna un archivo PDF con la nota de carga del pedido.

---

### Nota de Carga Restringida

```http
GET /api/v2/orders/{orderId}/pdf/restricted-loading-note
```

#### Response Exitosa (200)

Retorna un archivo PDF con la nota de carga restringida del pedido.

---

### Nota de Carga Valorada

```http
GET /api/v2/orders/{orderId}/pdf/valued-loading-note
```

#### Response Exitosa (200)

Retorna un archivo PDF con la nota de carga valorada del pedido.

---

### Documento CMR

```http
GET /api/v2/orders/{orderId}/pdf/order-cmr
```

#### Response Exitosa (200)

Retorna un archivo PDF con el documento CMR del pedido.

---

### Documento de Incidencia

```http
GET /api/v2/orders/{orderId}/pdf/incident
```

#### Response Exitosa (200)

Retorna un archivo PDF con el documento de incidencia del pedido.

#### Response Errónea (404) - Sin Incidencia

```json
{
  "message": "Incidencia no encontrada.",
  "userMessage": "No se encontró incidencia para este pedido."
}
```

---

### Confirmación de Pedido

```http
GET /api/v2/orders/{orderId}/pdf/order-confirmation
```

#### Response Exitosa (200)

Retorna un archivo PDF con la confirmación del pedido.

---

### Solicitud de Recogida

```http
GET /api/v2/orders/{orderId}/pdf/transport-pickup-request
```

#### Response Exitosa (200)

Retorna un archivo PDF con la solicitud de recogida de transporte.

---

## Exportación a Excel

Todos los endpoints de Excel retornan archivos Excel para descarga directa. Los headers de respuesta incluyen `Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet` y `Content-Disposition: attachment; filename="..."`.

### Headers Requeridos

```http
X-Tenant: {subdomain}
Authorization: Bearer {access_token}
```

---

## Exportaciones de Pedidos

### Detalles de Lotes

```http
GET /api/v2/orders/{orderId}/xlsx/lots-report
```

#### Path Parameters

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| orderId | integer | ID del pedido |

#### Response Exitosa (200)

**Content-Type:** `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`

**Content-Disposition:** `attachment; filename="lots-report-{orderId}.xlsx"`

---

### Lista de Cajas

```http
GET /api/v2/orders/{orderId}/xlsx/boxes-report
```

#### Response Exitosa (200)

Retorna un archivo Excel con la lista de cajas del pedido.

---

### Albarán A3ERP (Individual)

```http
GET /api/v2/orders/{orderId}/xls/A3ERP-sales-delivery-note
```

**Nota:** Retorna un archivo `.xls` (formato antiguo de Excel).

---

### Albaranes A3ERP (Filtrados)

```http
GET /api/v2/orders/xls/A3ERP-sales-delivery-note-filtered
```

#### Query Parameters

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| startDate | date | Fecha de inicio |
| endDate | date | Fecha de fin |
| customer_ids | array | IDs de clientes |
| status | string | Estado del pedido |

---

### Albarán A3ERP2 (Individual)

```http
GET /api/v2/orders/{orderId}/xls/A3ERP2-sales-delivery-note
```

---

### Albaranes A3ERP2 (Filtrados)

```http
GET /api/v2/orders/xls/A3ERP2-sales-delivery-note-filtered
```

#### Query Parameters

Mismos que [Albaranes A3ERP (Filtrados)](#albaranes-a3erp-filtrados).

---

### Albaranes Facilcom (Filtrados)

```http
GET /api/v2/orders/xls/facilcom-sales-delivery-note
```

#### Query Parameters

Mismos que [Albaranes A3ERP (Filtrados)](#albaranes-a3erp-filtrados).

---

### Albarán Facilcom (Individual)

```http
GET /api/v2/orders/{orderId}/xls/facilcom-single
```

---

### Productos Planificados Activos

```http
GET /api/v2/orders/xlsx/active-planned-products
```

#### Response Exitosa (200)

Retorna un archivo Excel con los productos planificados activos.

---

### Reporte de Pedidos

```http
GET /api/v2/orders_report
```

**Nota:** Requiere rol `superuser`.

#### Response Exitosa (200)

Retorna un archivo Excel con el reporte completo de pedidos.

---

## Exportaciones de Recepciones

### Recepciones Facilcom

```http
GET /api/v2/raw-material-receptions/facilcom-xls
```

#### Response Exitosa (200)

Retorna un archivo Excel con las recepciones en formato Facilcom.

---

### Recepciones A3ERP

```http
GET /api/v2/raw-material-receptions/a3erp-xls
```

#### Response Exitosa (200)

Retorna un archivo Excel con las recepciones en formato A3ERP.

---

## Exportaciones de Despachos

### Despachos Facilcom

```http
GET /api/v2/cebo-dispatches/facilcom-xlsx
```

#### Response Exitosa (200)

Retorna un archivo Excel con los despachos en formato Facilcom.

---

### Despachos A3ERP

```http
GET /api/v2/cebo-dispatches/a3erp-xlsx
```

#### Response Exitosa (200)

Retorna un archivo Excel con los despachos en formato A3ERP.

---

### Despachos A3ERP2

```http
GET /api/v2/cebo-dispatches/a3erp2-xlsx
```

#### Response Exitosa (200)

Retorna un archivo Excel con los despachos en formato A3ERP2.

---

## Exportaciones de Cajas

### Reporte de Cajas

```http
GET /api/v2/boxes/xlsx
```

#### Response Exitosa (200)

Retorna un archivo Excel con el reporte completo de cajas.

---

## Envío de Documentos

### Enviar Documentos Personalizados

```http
POST /api/v2/orders/{orderId}/send-custom-documents
```

#### Headers
```http
X-Tenant: {subdomain}
Authorization: Bearer {access_token}
Content-Type: application/json
```

#### Path Parameters

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| orderId | integer | ID del pedido |

#### Request Body

```json
{
  "emails": ["cliente@example.com", "copia@example.com"],
  "ccEmails": ["otro@example.com"],
  "subject": "Documentos del pedido",
  "message": "Adjunto encontrará los documentos solicitados.",
  "documents": [
    {
      "type": "order-sheet",
      "generate": true
    },
    {
      "type": "loading-note",
      "generate": true
    }
  ]
}
```

#### Campos Requeridos

| Campo | Tipo | Descripción |
|-------|------|-------------|
| emails | array | Array de emails destinatarios |
| documents | array | Array de documentos a generar/enviar |

#### Response Exitosa (200)

```json
{
  "message": "Documentos enviados correctamente.",
  "data": {
    "sent_to": ["cliente@example.com", "copia@example.com"],
    "documents_sent": 2,
    "sent_at": "2024-01-15T12:00:00.000000Z"
  }
}
```

---

### Enviar Documentos Estándar

```http
POST /api/v2/orders/{orderId}/send-standard-documents
```

#### Request Body

```json
{
  "emails": ["cliente@example.com"],
  "ccEmails": ["copia@example.com"],
  "message": "Adjunto encontrará los documentos estándar del pedido."
}
```

#### Response Exitosa (200)

```json
{
  "message": "Documentos estándar enviados correctamente.",
  "data": {
    "sent_to": ["cliente@example.com"],
    "documents_sent": 3,
    "sent_at": "2024-01-15T12:00:00.000000Z"
  }
}
```

---

## Respuestas Erróneas

### Error de Autenticación (401)

```json
{
  "message": "No autenticado."
}
```

### Error de Permisos (403)

```json
{
  "message": "No tiene permisos para generar este documento."
}
```

### Recurso No Encontrado (404)

```json
{
  "message": "Pedido no encontrado."
}
```

### Error de Validación (422)

```json
{
  "message": "Error de validación.",
  "userMessage": "El campo emails es obligatorio.",
  "errors": {
    "emails": ["The emails field is required."]
  }
}
```

---

## Notas

- Los archivos PDF/Excel se generan dinámicamente en cada petición
- Los nombres de archivo incluyen IDs y fechas para evitar conflictos
- Los archivos pueden ser grandes; asegúrate de tener suficiente tiempo de espera en el cliente
- Algunos endpoints pueden requerir roles específicos (ver documentación de cada endpoint)
- Los documentos se generan según la configuración del tenant actual

