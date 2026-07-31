# Guía Frontend: Envío de Documentación al Cliente (Exportación Marítima)

## 📋 Resumen

Nuevo endpoint para enviar al cliente, en un solo email, la documentación de un pedido de exportación marítima (`order_type = maritime_export`):

- **Mensaje personalizado** (asunto + cuerpo libres, redactados por el usuario en el momento del envío).
- **Export Packing List de todos los contenedores del pedido**, adjuntado automáticamente (uno por contenedor, igual que la descarga individual ya existente — ver [28-guia-frontend-export-packing-list.md](./28-guia-frontend-export-packing-list.md)).
- **Adjuntos que el usuario elija**, de entre los ya subidos al pedido con el sistema de adjuntos genérico (documentación sanitaria, BL, facturas, etc. — ver [docs/frontend/order-attachments-api.md](../frontend/order-attachments-api.md)).

Este flujo es **distinto** del botón "enviar documentos" habitual (`send-custom-documents` / `send-standard-documents`, ver [22-pedidos-documentos.md](./22-pedidos-documentos.md)):
- Aquel envía a destinatarios de **rol fijo** (`customer`, `transport`, `salesperson`, `external_processor`) y documentos generados por el sistema, sin mensaje personalizado.
- Este envía **solo al cliente** del pedido, con mensaje libre y una mezcla de documentos generados + adjuntos sueltos. No lo sustituye — conviven ambos.

No hay pantalla ni catálogo nuevo que crear de cero: el circuito de usuario es "subir adjuntos al pedido (ya existe) → abrir el nuevo formulario de envío → escribir mensaje → marcar qué adjuntos van también → enviar".

---

## 1. Endpoint

```
POST /api/v2/orders/{orderId}/send-maritime-export-documents
```

**Permisos**: mismos que el resto de envíos de documentos — roles `tecnico`, `administrador`, `direccion`, `administracion`, `operario`, `supervisor` (autorización vía `OrderPolicy@view`). **No disponible para el rol `comercial`** (403), igual que `send-custom-documents`/`send-standard-documents`/`send-maquilador-documents`.

### Request

```json
{
  "subject": "Documentación de embarque - Pedido #BR26/377",
  "body": "Buenas,\n\nLes confirmamos que la mercancía ha sido cargada en el contenedor MSKU1234567.\n\nAdjuntamos el Export Packing List junto con la documentación sanitaria y el BL correspondientes.\n\nQuedamos a su disposición para cualquier consulta.",
  "attachmentIds": [45, 46]
}
```

| Campo | Tipo | Obligatorio | Notas |
|-------|------|:---:|-------|
| `subject` | string | Sí | Máx. 255 caracteres. Asunto literal del email — no hay plantilla ni sustitución de variables, se envía tal cual. |
| `body` | string | Sí | Máx. 5000 caracteres. Texto libre plano (no Markdown/HTML). Los saltos de línea (`\n`) se respetan en el email (se renderizan como `<br>`); cualquier otro carácter se muestra literal, no se interpreta como formato. |
| `attachmentIds` | number[] | No | IDs de `Attachment` ya subidos a este pedido (colección `order_document` u otra) que se quieren incluir en el envío. Puede omitirse o enviarse vacío (`[]`) si solo se quiere mandar el Export Packing List. |

**Cómo obtener los `attachmentIds` seleccionables**: usa el endpoint ya existente

```
GET /api/v2/orders/{order}/attachments
```

para listar los adjuntos del pedido (nombre original, tipo MIME, tamaño, fecha de subida, quién lo subió) y deja que el usuario marque cuáles quiere incluir con checkboxes. No hace falta ningún endpoint nuevo para esto — es el mismo listado que ya se usa en la pestaña de adjuntos del pedido.

### Validaciones

- `subject` y `body` obligatorios (**422** si faltan).
- Cada `attachmentIds[]` debe existir y pertenecer **a este pedido concreto** — si se envía un id de un adjunto de otro pedido (o inexistente), **422**:
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "attachmentIds.0": ["Uno de los adjuntos seleccionados no existe o no pertenece a este pedido."]
  }
}
```
- El pedido debe ser de tipo `maritime_export` — si no, **422**:
```json
{
  "message": "El pedido no es de tipo exportación marítima.",
  "userMessage": "Este envío solo está disponible para pedidos de exportación marítima."
}
```
- El pedido debe tener al menos un email configurado en su propio campo `emails` — si no, **422**:
```json
{
  "message": "El cliente del pedido no tiene emails configurados.",
  "userMessage": "El cliente de este pedido no tiene ninguna dirección de email. Añade un email antes de enviar."
}
```
  > ⚠️ **No es el email de la ficha del cliente (`Customer`)**, sino el campo `emails`/`ccEmails` del propio **pedido** (mismo campo que ya se edita en el formulario de pedido y que usan `send-custom-documents`/`send-standard-documents` para el destinatario `customer`). Se suele rellenar por defecto al crear el pedido a partir del email del cliente, pero queda desacoplado a partir de ahí: editar el email del cliente no actualiza pedidos ya creados, y se puede editar el email de un pedido puntual sin tocar la ficha del cliente. Comprueba `order.emailsArray` (no `order.customer.email`) en frontend **antes** de mostrar el botón de envío, para no hacer descubrir el error al usuario recién al enviar.
- El pedido debe tener al menos un contenedor (`OrderMaritimeContainer`) — si no hay ninguno, no hay Export Packing List que generar. No es un caso bloqueante documentado aparte porque en la práctica un pedido `maritime_export` sin contenedores no debería llegar a esta pantalla; si ocurre, el email se envía igualmente solo con los adjuntos seleccionados (sin Packing List).

### Respuesta OK

```json
{ "message": "Documentación de exportación enviada correctamente." }
```
`200`. El envío es **síncrono** (no hay cola/job) — la respuesta llega una vez el email ha sido enviado (o ha fallado con excepción). Para pedidos con varios contenedores puede tardar unos segundos (genera un PDF por contenedor antes de enviar), igual que ya ocurre hoy con `send-standard-documents`.

---

## 2. Qué recibe el cliente

Un único email, al `email` (+ `ccEmails` si los tiene) configurados en el pedido, con:

- Asunto: el `subject` enviado, o el asunto por defecto si se omite (ver [32-guia-frontend-envio-documentos-refinamientos.md](./32-guia-frontend-envio-documentos-refinamientos.md)).
- Cuerpo: mensaje fijo bilingüe ES/EN + detalles del pedido +, si el usuario escribió algo en `body`, un apartado "Notas" al final — ver doc 32 para el detalle completo del contenido actual.
- Adjuntos:
  - **Packing List del pedido** (`Packing_List_{numeroPedido}.pdf`), el mismo documento (todo el pedido, no por contenedor) que genera `GET /orders/{orderId}/pdf/order-packing-list` — se adjunta siempre, generado en el momento.
  - **Export Packing List de cada contenedor del pedido** (`Export_Packing_List_{numeroPedido}_{numeroContenedor}.pdf`), generado en el momento — mismo contenido que la descarga manual documentada en la sección 5 de [28-guia-frontend-export-packing-list.md](./28-guia-frontend-export-packing-list.md).
  - Cada adjunto de `attachmentIds`, con su nombre de archivo original (`original_name`).

No hay forma de excluir el Export Packing List del envío ni de elegir solo algunos contenedores — se adjuntan siempre todos los del pedido. Si en el futuro hace falta seleccionar contenedores concretos, hay que pedirlo explícitamente (no está contemplado en esta versión).

Si el pedido tiene una naviera (`shippingLine`) guardada en sus datos de envío marítimo, el cuerpo del email incluye además un enlace de seguimiento por contenedor — ver [31-guia-frontend-naviera-y-seguimiento.md](./31-guia-frontend-naviera-y-seguimiento.md). No requiere ninguna acción adicional al enviar.

---

## 3. Manejo de errores (formato estándar)

| Código | Caso | Shape |
|--------|------|-------|
| 422 | `subject`/`body` faltantes, `attachmentIds` inválido, pedido no es `maritime_export`, cliente sin email | `{ message, userMessage?, errors? }` |
| 403 | Rol `comercial`, o usuario sin permiso de `view` sobre el pedido (Policy) | `{ message, error }` |
| 404 | Pedido no existe | `{ message }` |

---

## 4. Checklist de Implementación Frontend

- [ ] Nuevo botón/acción "Enviar documentación al cliente" en el detalle de pedidos `maritime_export` (junto a los botones de descarga de Export Packing List por contenedor ya existentes), distinto del botón genérico "enviar documentos" del resto de pedidos.
- [ ] Formulario de envío con: campo **Asunto** (texto corto), campo **Mensaje** (textarea multilínea), y **listado de adjuntos del pedido** (reutilizando `GET /orders/{order}/attachments`) con checkbox por adjunto para incluirlo o no.
- [ ] Indicar en la UI, de forma no editable, que el Export Packing List de todos los contenedores se incluye siempre automáticamente (para que el usuario no lo busque entre los checkboxes de adjuntos).
- [ ] Validar en frontend antes de habilitar "Enviar": pedido con al menos un contenedor, cliente con email configurado (evita el 422 correspondiente).
- [ ] Deshabilitar el botón de envío mientras la petición está en curso (puede tardar varios segundos con varios contenedores) y mostrar spinner/feedback.
- [ ] Mensaje de éxito con el texto de la respuesta; mensaje de error usando `userMessage` cuando esté presente, o `errors` para errores de validación de campo.
