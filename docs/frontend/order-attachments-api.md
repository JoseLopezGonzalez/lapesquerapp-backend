# Adjuntos de Pedidos — Guía de integración frontend

**Fecha:** 2026-06-25
**Base path:** `/api/v2/`
**Bloque:** A.2 Ventas — Fase 3: adjuntos en pedidos

---

## 1. Resumen del feature

Los pedidos ahora permiten adjuntar archivos (PDFs, documentos Office, imágenes). La API sigue el mismo patrón que los adjuntos de pallets, con los que el frontend puede estar familiarizado.

### Colecciones disponibles

| Colección | Para qué se usa | Tipos admitidos | Tamaño máx. | Máx. archivos |
|-----------|----------------|-----------------|-------------|---------------|
| `order_document` | Contratos, proformas, facturas, packing lists, BL, albaranes, etc. | PDF, DOC, DOCX, XLS, XLSX | 20 MB | 50 |
| `order_image` | Fotos del producto, evidencias de entrega, daños, etc. | JPEG, PNG, WEBP | 10 MB | 20 |

> **Regla práctica:** Para todo lo que sea un documento use `order_document`. Para fotos/capturas use `order_image`.

---

## 2. Headers obligatorios

Igual que el resto de la API:

```
X-Tenant: {subdominio}
Authorization: Bearer {token}
Accept: application/json
```

---

## 3. Endpoints

### 3.1 Listar adjuntos de un pedido

```
GET /api/v2/orders/{orderId}/attachments
```

**Query params opcionales:**

| Param | Tipo | Descripción |
|-------|------|-------------|
| `collection` | string | Filtrar por colección: `order_document` o `order_image`. Sin filtro devuelve todos. |
| `per_page` | int | Elementos por página (default: 20). |
| `page` | int | Número de página (default: 1). |

**Ejemplo:**
```
GET /api/v2/orders/42/attachments?collection=order_document&per_page=10
```

**Respuesta 200:**
```json
{
  "data": [
    {
      "id": 15,
      "collection": "order_document",
      "originalName": "factura-cliente-XYZ.pdf",
      "mimeType": "application/pdf",
      "extension": "pdf",
      "size": 204800,
      "notes": "Factura definitiva firmada",
      "metadata": null,
      "uploadedBy": {
        "id": 3,
        "name": "María García"
      },
      "createdAt": "2026-06-25T10:30:00+02:00"
    }
  ],
  "links": {
    "first": "...",
    "last": "...",
    "prev": null,
    "next": null
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 1,
    "per_page": 20,
    "to": 1,
    "total": 1
  }
}
```

---

### 3.2 Subir un adjunto

```
POST /api/v2/orders/{orderId}/attachments
Content-Type: multipart/form-data
```

**Body (form-data):**

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| `file` | File | Sí | El archivo a subir |
| `collection` | string | Sí | `order_document` o `order_image` |
| `notes` | string | No | Texto libre descriptivo (máx. 500 caracteres) |
| `metadata` | object | No | JSON arbitrario para datos extra |

**Ejemplo con `fetch`:**
```js
const formData = new FormData();
formData.append('file', fileInput.files[0]);
formData.append('collection', 'order_document');
formData.append('notes', 'Factura definitiva firmada');

const res = await fetch(`/api/v2/orders/${orderId}/attachments`, {
  method: 'POST',
  headers: {
    'X-Tenant': tenant,
    'Authorization': `Bearer ${token}`,
    // NO poner Content-Type aquí — el navegador lo pone automáticamente con el boundary
  },
  body: formData,
});
```

> **Importante:** No establecer `Content-Type` manualmente al usar `FormData`. El navegador lo fija con el `boundary` correcto. Si se fuerza `application/json` la subida fallará.

**Respuesta 201:**
```json
{
  "id": 16,
  "collection": "order_document",
  "originalName": "factura-cliente-XYZ.pdf",
  "mimeType": "application/pdf",
  "extension": "pdf",
  "size": 204800,
  "notes": "Factura definitiva firmada",
  "metadata": null,
  "uploadedBy": {
    "id": 3,
    "name": "María García"
  },
  "createdAt": "2026-06-25T10:31:00+02:00"
}
```

---

### 3.3 Ver detalle de un adjunto

```
GET /api/v2/orders/{orderId}/attachments/{attachmentId}
```

**Respuesta 200:**
```json
{
  "data": {
    "id": 16,
    "collection": "order_document",
    "originalName": "factura-cliente-XYZ.pdf",
    "mimeType": "application/pdf",
    "extension": "pdf",
    "size": 204800,
    "notes": "Factura definitiva firmada",
    "metadata": null,
    "uploadedBy": {
      "id": 3,
      "name": "María García"
    },
    "createdAt": "2026-06-25T10:31:00+02:00"
  }
}
```

---

### 3.4 Editar notas / metadata

Solo se puede editar `notes` y `metadata`. El archivo físico nunca se modifica con este endpoint.

```
PATCH /api/v2/orders/{orderId}/attachments/{attachmentId}
Content-Type: application/json
```

**Body:**
```json
{
  "notes": "Factura corregida — versión 2",
  "metadata": { "revision": 2 }
}
```

Ambos campos son opcionales. Solo se actualiza lo que se envía.

**Respuesta 200:**
```json
{
  "data": {
    "id": 16,
    "notes": "Factura corregida — versión 2",
    ...
  }
}
```

---

### 3.5 Descargar archivo

```
GET /api/v2/orders/{orderId}/attachments/{attachmentId}/download
```

La respuesta es el archivo binario con `Content-Disposition: attachment; filename="nombre-original.pdf"`.

**Cómo abrir/descargar en el frontend:**

```js
// Opción A — abrir en nueva pestaña (para PDFs que el navegador puede renderizar)
window.open(
  `/api/v2/orders/${orderId}/attachments/${attachmentId}/download`,
  '_blank'
);

// Opción B — forzar descarga con fetch (cuando se necesita pasar el token en la cabecera)
const res = await fetch(
  `/api/v2/orders/${orderId}/attachments/${attachmentId}/download`,
  { headers: { 'Authorization': `Bearer ${token}`, 'X-Tenant': tenant } }
);
const blob = await res.blob();
const url = URL.createObjectURL(blob);
const a = document.createElement('a');
a.href = url;
a.download = attachment.originalName;
a.click();
URL.revokeObjectURL(url);
```

> **Nota sobre PDFs:** Si el frontend usa la opción A, los navegadores modernos renderizan el PDF inline en vez de descargarlo. Si se prefiere siempre descargar, usar la opción B.

---

### 3.6 Thumbnail (solo imágenes)

```
GET /api/v2/orders/{orderId}/attachments/{attachmentId}/thumbnail
```

Devuelve una imagen JPEG redimensionada a ≤300px, generada y cacheada automáticamente.

**Solo usar para `order_image`.** Si se llama sobre un PDF devuelve `415 Unsupported Media Type`.

---

### 3.7 Eliminar adjunto

```
DELETE /api/v2/orders/{orderId}/attachments/{attachmentId}
```

**Restricción de roles:** Solo usuarios con rol `administrador` o `tecnico` pueden eliminar. Los demás reciben `403 Forbidden`.

**Respuesta:** `204 No Content` (sin body).

---

## 4. Gestión de errores

| Código | Cuándo ocurre | Qué mostrar al usuario |
|--------|---------------|------------------------|
| `401` | Token inválido o expirado | Redirigir a login |
| `403` | Sin permiso (ej. intentar borrar sin ser admin) | "No tienes permiso para realizar esta acción" |
| `404` | Pedido o adjunto no encontrado | "El archivo no existe o ha sido eliminado" |
| `413` | Archivo demasiado grande (límite HTTP del servidor) | "El archivo supera el tamaño máximo permitido" |
| `415` | Llamada a `/thumbnail` sobre un PDF | No llamar thumbnail en documentos |
| `422` | MIME no permitido, tamaño excedido (por colección), límite de cantidad alcanzado, colección inválida | Mostrar `userMessage` del body |

**Estructura de error 422:**
```json
{
  "message": "No se pudo adjuntar el archivo.",
  "userMessage": "Tipo de archivo 'text/plain' no permitido en la colección 'order_document'."
}
```

**Estructura de error de validación (campos faltantes):**
```json
{
  "message": "El archivo es obligatorio.",
  "errors": {
    "file": ["El archivo es obligatorio."],
    "collection": ["La colección es obligatoria."]
  }
}
```

---

## 5. Permisos por rol

| Acción | Todos los roles autenticados | Solo admin / técnico |
|--------|------------------------------|----------------------|
| Listar adjuntos | ✅ (si puede ver el pedido) | — |
| Ver detalle | ✅ (si puede ver el pedido) | — |
| Descargar | ✅ (si puede ver el pedido) | — |
| Subir | ✅ (si puede editar el pedido) | — |
| Editar notas | ✅ (si puede editar el pedido) | — |
| **Eliminar** | ❌ | ✅ |

> "Puede ver/editar el pedido" se rige por la `OrderPolicy` existente — los mismos permisos que el usuario ya tiene sobre el pedido se heredan automáticamente para sus adjuntos.

---

## 6. Dónde integrar en la UI

### 6.1 Vista de detalle de pedido

Es el lugar principal. Se recomienda una pestaña o sección colapsable **"Documentos"** dentro del detalle del pedido.

**Estructura sugerida:**

```
[Detalle Pedido #42]
  Datos generales | Líneas | Documentos ← nueva pestaña/sección
  ─────────────────────────────────────
  Documentos (3)          [+ Adjuntar]
  ┌─────────────────────────────────────────────────────────┐
  │ 📄 factura-cliente-XYZ.pdf        2.1 MB  Ayer 10:31   │
  │    "Factura definitiva firmada"        [↓] [✏️] [🗑️]   │
  │─────────────────────────────────────────────────────────│
  │ 📊 packing-list.xlsx              150 KB  Ayer 09:00   │
  │                                        [↓] [✏️] [🗑️]   │
  │─────────────────────────────────────────────────────────│
  │ 🖼️ foto-dano-embalaje.jpg (thumb) 890 KB  Hoy 08:15    │
  │    "Daño en palé 3"                    [↓] [✏️] [🗑️]   │
  └─────────────────────────────────────────────────────────┘
```

- **Botón "Adjuntar"** → abre un modal/drawer con selector de archivo + campo colección + notas
- **Icono ↓** → trigger de descarga (endpoint `/download`)
- **Icono ✏️** → editar notas inline (endpoint `PATCH`)
- **Icono 🗑️** → eliminar con confirmación (solo visible para admin/técnico)
- Para **imágenes**: mostrar thumbnail usando el endpoint `/thumbnail` como `<img src="...">` (con el token en la cabecera, usar blob URL o proxy)

### 6.2 Listado de pedidos (tabla)

Mostrar un badge con el número de adjuntos si el dato viene en el payload del listado (actualmente no viene; se puede añadir al `OrderResource` si se necesita sin coste de query con `loadCount`).

### 6.3 Vista de impresión / PDF del pedido

No aplica — los adjuntos son archivos externos, no se embeben en el PDF generado.

---

## 7. Selección de colección en el formulario de subida

El campo `collection` es requerido. La forma más sencilla es un selector desplegable o radio buttons:

```
Tipo de documento:
  ○ Documento (PDF, Word, Excel)   → collection: "order_document"
  ○ Imagen / Foto                  → collection: "order_image"
```

También se puede inferir automáticamente por el tipo MIME del archivo seleccionado para mejorar el UX, y que el campo se autocomplete (con posibilidad de corregir).

**Lógica de autoselección sugerida:**
```js
function inferCollection(file) {
  const imageTypes = ['image/jpeg', 'image/png', 'image/webp'];
  return imageTypes.includes(file.type) ? 'order_image' : 'order_document';
}
```

---

## 8. Mostrar tamaño legible

El campo `size` viene en bytes. Para mostrarlo:

```js
function formatBytes(bytes) {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}
```

---

## 9. Iconos por tipo de archivo

Referencia rápida para asignar icono según `mimeType` o `extension`:

| mimeType / extension | Icono sugerido |
|----------------------|----------------|
| `application/pdf` / `.pdf` | 📄 o icono PDF rojo |
| `application/msword`, `.doc` | 📝 o icono Word azul |
| `application/vnd.openxmlformats-officedocument.wordprocessingml.document`, `.docx` | 📝 o icono Word azul |
| `application/vnd.ms-excel`, `.xls` | 📊 o icono Excel verde |
| `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`, `.xlsx` | 📊 o icono Excel verde |
| `image/jpeg`, `image/png`, `image/webp` | 🖼️ o thumbnail real |

---

## 10. Consideraciones de UX

- **Feedback de progreso:** Para archivos grandes (cercanos a 20 MB) mostrar barra de progreso usando el evento `upload.onprogress` de `XMLHttpRequest` o el equivalente de la librería HTTP usada.
- **Estado pedido:** La subida de adjuntos está permitida independientemente del estado del pedido (`pending`, `finished`, `incident`). No bloquear la UI por estado.
- **Límites visibles:** Al llegar a 50 documentos o 20 imágenes, el backend devuelve 422 con `userMessage` explicativo. Se puede precargar el conteo y deshabilitar el botón antes.
- **Reload tras subida:** Refrescar la lista de adjuntos después de un upload exitoso (o actualizar el estado local con el objeto devuelto en la respuesta 201).
- **Confirmación al borrar:** Siempre pedir confirmación antes de llamar a `DELETE` — la eliminación es definitiva (soft delete en base de datos, pero el archivo físico se elimina del storage).

---

## 11. Referencia rápida — URLs

```
Listar:    GET    /api/v2/orders/:id/attachments
Subir:     POST   /api/v2/orders/:id/attachments
Detalle:   GET    /api/v2/orders/:id/attachments/:aid
Editar:    PATCH  /api/v2/orders/:id/attachments/:aid
Descargar: GET    /api/v2/orders/:id/attachments/:aid/download
Thumbnail: GET    /api/v2/orders/:id/attachments/:aid/thumbnail
Eliminar:  DELETE /api/v2/orders/:id/attachments/:aid
```
