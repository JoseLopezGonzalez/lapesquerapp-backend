# API de adjuntos — Referencia para frontend

Versión implementada: Fase 1+2 (piloto Pallet).  
Base URL: `/api/v2`  
Autenticación: igual que el resto de la API — header `X-Tenant` + `Authorization: Bearer {token}`.

---

## Entidades con adjuntos disponibles

| Entidad | Rutas disponibles |
|---|---|
| Pallet | `/api/v2/pallets/{pallet}/attachments` |

---

## Colecciones disponibles por entidad

### Pallet

| Colección | Tipos MIME aceptados | Tamaño máximo | Límite por palet |
|---|---|---|---|
| `pallet_image` | `image/jpeg`, `image/png`, `image/webp` | 10 MB | 20 imágenes |

El tipo MIME lo detecta el servidor. Si el archivo tiene extensión `.jpg` pero el contenido no es JPEG válido, será rechazado.

---

## Permisos por acción

| Acción | Quién puede |
|---|---|
| Ver listado | Cualquier rol con acceso al palet |
| Ver detalle | Cualquier rol con acceso al palet |
| Descargar archivo | Cualquier rol con acceso al palet |
| Subir imagen | Cualquier rol con permiso de edición del palet |
| Editar notas | Cualquier rol con permiso de edición del palet |
| Borrar | Solo `administrador` y `tecnico` |

---

## Endpoints

### `GET /pallets/{pallet}/attachments`

Lista los adjuntos de un palet. Respuesta paginada.

**Query params opcionales:**

| Param | Tipo | Default | Descripción |
|---|---|---|---|
| `per_page` | integer | 20 | Resultados por página |
| `collection` | string | — | Filtra por colección (`pallet_image`) |

**Respuesta 200:**

```json
{
  "data": [
    {
      "id": 1,
      "collection": "pallet_image",
      "originalName": "foto-palet.jpg",
      "mimeType": "image/jpeg",
      "extension": "jpg",
      "size": 482193,
      "notes": "Golpe en esquina",
      "metadata": null,
      "uploadedBy": {
        "id": 7,
        "name": "Operario"
      },
      "createdAt": "2026-06-04T10:31:18+00:00"
    }
  ],
  "links": {
    "first": "...",
    "last": "...",
    "prev": null,
    "next": "..."
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 2,
    "per_page": 20,
    "to": 20,
    "total": 35
  }
}
```

---

### `POST /pallets/{pallet}/attachments`

Sube una imagen al palet. La petición debe enviarse como `multipart/form-data`.

**Body (multipart/form-data):**

| Campo | Tipo | Requerido | Descripción |
|---|---|---|---|
| `file` | file | Sí | El archivo a subir |
| `collection` | string | Sí | Debe ser `pallet_image` |
| `notes` | string | No | Nota opcional, máx 500 caracteres |
| `metadata` | object/array | No | JSON libre para datos adicionales |

**Respuesta 201** — el objeto adjunto creado, sin envoltura `data`:

```json
{
  "id": 15,
  "collection": "pallet_image",
  "originalName": "foto-palet.jpg",
  "mimeType": "image/jpeg",
  "extension": "jpg",
  "size": 482193,
  "notes": "Golpe en esquina",
  "metadata": null,
  "uploadedBy": {
    "id": 7,
    "name": "Operario"
  },
  "createdAt": "2026-06-04T10:31:18+00:00"
}
```

---

### `GET /pallets/{pallet}/attachments/{attachment}`

Devuelve los metadatos de un adjunto concreto.

**Respuesta 200:**

```json
{
  "data": {
    "id": 15,
    "collection": "pallet_image",
    "originalName": "foto-palet.jpg",
    "mimeType": "image/jpeg",
    "extension": "jpg",
    "size": 482193,
    "notes": "Golpe en esquina",
    "metadata": null,
    "uploadedBy": {
      "id": 7,
      "name": "Operario"
    },
    "createdAt": "2026-06-04T10:31:18+00:00"
  }
}
```

---

### `PATCH /pallets/{pallet}/attachments/{attachment}`

Edita la nota o los metadatos de un adjunto. No modifica el archivo físico ni la colección.

**Body (JSON):**

| Campo | Tipo | Descripción |
|---|---|---|
| `notes` | string\|null | Nueva nota, máx 500 caracteres |
| `metadata` | object\|null | Nuevo objeto de metadatos |

Ambos campos son opcionales. Solo se actualiza lo que se envía.

**Respuesta 200:**

```json
{
  "data": {
    "id": 15,
    "collection": "pallet_image",
    "originalName": "foto-palet.jpg",
    "mimeType": "image/jpeg",
    "extension": "jpg",
    "size": 482193,
    "notes": "Nota actualizada",
    "metadata": null,
    "uploadedBy": {
      "id": 7,
      "name": "Operario"
    },
    "createdAt": "2026-06-04T10:31:18+00:00"
  }
}
```

---

### `GET /pallets/{pallet}/attachments/{attachment}/download`

Descarga el archivo físico. La respuesta es un stream binario, no JSON.

**Respuesta 200:**

- `Content-Type`: el MIME del archivo (`image/jpeg`, etc.)
- `Content-Disposition: attachment; filename="nombre-original.jpg"`
- Body: contenido binario del archivo

Para mostrar una imagen en el navegador sin forzar descarga, lee el stream y crea un `Blob URL` local, o usa el endpoint directamente como `src` con el token en el header mediante `fetch`.

> Los archivos **no tienen URL pública permanente**. Siempre hay que pasar el token de autenticación para acceder a ellos.

---

### `DELETE /pallets/{pallet}/attachments/{attachment}`

Borra el adjunto. Solo disponible para roles `administrador` y `tecnico`.

**Respuesta 204** — sin cuerpo.

---

## Errores comunes

| Código | Cuándo ocurre |
|---|---|
| 401 | Sin token o token inválido |
| 403 | El rol del usuario no tiene permiso para esa acción |
| 404 | El palet o el adjunto no existen |
| 422 | Validación fallida o archivo rechazado por tipo/tamaño/límite |

**Formato de error 422 de negocio** (MIME inválido, colección incorrecta, límite alcanzado):

```json
{
  "message": "No se pudo adjuntar el archivo.",
  "userMessage": "Tipo de archivo 'application/pdf' no permitido en la colección 'pallet_image'."
}
```

**Formato de error 422 de validación** (campo requerido faltante, etc.):

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "file": ["El archivo es obligatorio."],
    "collection": ["La colección es obligatoria."]
  }
}
```

---

## Notas de implementación

- El campo `size` está en **bytes**.
- El campo `createdAt` está en formato **ISO 8601** con timezone UTC.
- El campo `uploadedBy` solo aparece en la respuesta si la relación está cargada. En el listado siempre viene incluido.
- El campo `metadata` acepta cualquier objeto JSON. El backend no valida su estructura interna; es responsabilidad del frontend definir qué guarda ahí si lo necesita.
- No existe endpoint de previsualización/thumbnail. Para mostrar imágenes hay que usar el endpoint de descarga con autenticación.
