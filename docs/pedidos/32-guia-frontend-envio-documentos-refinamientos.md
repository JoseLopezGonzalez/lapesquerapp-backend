# Guía Frontend: Envío de Documentación (Exportación Marítima) — Refinamientos v2

## 📋 Resumen

Cambios sobre lo ya descrito en [30-guia-frontend-envio-documentos-exportacion-maritima.md](./30-guia-frontend-envio-documentos-exportacion-maritima.md), mismo endpoint (`POST /orders/{orderId}/send-maritime-export-documents`), **sin cambios de ruta**:

1. **`subject` y `body` pasan a ser opcionales** — ya no hace falta que el usuario escriba nada para poder enviar.
2. **El texto que escribe el usuario ahora es "Notas"**, y aparece al final del email, no justo debajo de la cabecera.
3. **El cuerpo fijo del email muestra más datos del pedido** automáticamente (Buyer Reference, Booking, puertos, Incoterm, nº de factura de exportación), sin que el frontend tenga que enviarlos — ya están guardados en el pedido / en los datos de envío marítimo.
4. Cambios de redacción: ya no se usa "pedido de exportación" (queda "pedido" a secas) y se añade una frase indicando que se envía la documentación disponible en ese momento y que el resto llegará después si se recibe.

---

## 1. `subject` y `body` ahora opcionales

```json
POST /orders/{orderId}/send-maritime-export-documents
{
  "attachmentIds": [45, 46]
}
```
Ese payload ya es válido por sí solo — `subject` y `body` pueden omitirse por completo.

| Campo | Antes | Ahora |
|-------|-------|-------|
| `subject` | Obligatorio | Opcional. Si se omite, el backend usa por defecto: **"Pedido `{referencia}` despachado - Documentación adjunta"**. |
| `body` | Obligatorio | Opcional. Si se omite, el email no incluye ninguna nota adicional (el resto del contenido, ver punto 3, se muestra igual). |

**Acción frontend**: quitar la validación de "obligatorio" en los campos Asunto y Mensaje del formulario de envío. Pueden quedar vacíos y el botón de enviar debe seguir habilitado. Sugerencia de placeholder para el campo Asunto: mostrar como placeholder (no como valor) el asunto por defecto, para que el usuario sepa qué se usará si lo deja en blanco.

---

## 2. El texto del usuario ahora es "Notas", al final del email

Antes, lo que el usuario escribía en `body` aparecía justo debajo de la cabecera del email, como si fuera el cuerpo principal del mensaje. Ahora:

- El email siempre lleva primero un bloque fijo (ver punto 4) con la confirmación de envío y el detalle del pedido — **no editable, no depende de `body`**.
- Si el usuario escribe algo en `body`, aparece **al final**, bajo un apartado "Notas", justo antes de la despedida.
- Si `body` se omite, no aparece ningún apartado de notas — no queda un hueco vacío ni un título suelto.

**Acción frontend**: puede ayudar renombrar la etiqueta del campo en el formulario de "Mensaje" a algo como "Notas adicionales (opcional)", para que el usuario entienda que es un añadido al final y no el cuerpo principal del email.

---

## 3. Datos nuevos en el cuerpo del email (automáticos)

La sección "Detalles del Pedido" del email ahora puede incluir, además de Cliente/Nº de Pedido/Buque/Nº de Viaje (que ya se mostraban):

| Dato | Origen | Se muestra si... |
|------|--------|-------------------|
| Buyer Reference | `Order.buyerReference` | El pedido lo tiene relleno |
| Booking | `OrderMaritimeShippingDetail.bookingNumber` | Está relleno en los datos de envío marítimo |
| Puerto de Carga | `OrderMaritimeShippingDetail.loadingPort` | Está relleno |
| Puerto de Descarga | `OrderMaritimeShippingDetail.dischargePort` | Está relleno |
| Incoterm | `Order.incoterm.code` | El pedido tiene incoterm asignado |
| Nº de Factura de Exportación | `OrderMaritimeShippingDetail.exportInvoiceNumber` | Está relleno |

**No hay ningún campo nuevo que enviar** en `send-maritime-export-documents` para esto — es 100% automático a partir de lo que ya esté guardado en el pedido y en sus datos de envío marítimo. Cada línea es condicional: si el dato no está relleno, esa línea simplemente no aparece (no se muestra en blanco ni con un guion).

**Acción frontend**: si se quiere que el email salga lo más completo posible, conviene recordar/animar a rellenar `buyerReference` (formulario de pedido), y `bookingNumber`/`loadingPort`/`dischargePort`/`exportInvoiceNumber` (formulario de datos de envío marítimo, ya existente) antes de enviar la documentación — pero no es un requisito bloqueante, el envío funciona igual si faltan.

---

## 4. Cambios de redacción del mensaje fijo

- Antes: *"Su pedido de exportación con número **X** ha sido..."* / *"Your export order with number **X**..."*
  Ahora: *"Su pedido n.º **X**..."* / *"Your order No. **X**..."* — se elimina "de exportación"/"export" al referirse al pedido, y el número ya no lleva el `#` de `formattedId` (se usa el prefijo "n.º"/"No." en su lugar).
- Texto final del mensaje fijo:
  > **ES** — "Su pedido n.º **X** ha sido cargado y expedido. Adjuntamos la documentación disponible en este momento. Cualquier documentación adicional que esté disponible posteriormente le será remitida por este mismo medio."
  > **EN** — "Your order No. **X** has been loaded and shipped. Please find the documentation currently available attached. Any additional documentation that becomes available will be sent to you through this same channel."

Esto es contenido fijo del email (no configurable desde el request) — no requiere ningún cambio de payload, solo se documenta aquí para que el texto mostrado en cualquier vista previa del frontend (si existe) se mantenga alineado con lo que realmente se envía.

---

## 5. Checklist de Implementación Frontend (incremental sobre doc 30)

- [ ] Quitar validación "obligatorio" de los campos Asunto y Notas en el formulario de envío.
- [ ] Renombrar la etiqueta del campo `body` a algo tipo "Notas adicionales (opcional)" para reflejar que ahora aparece al final, no como cuerpo principal.
- [ ] (Opcional) Mostrar como placeholder del campo Asunto el valor por defecto: `Pedido {referencia} despachado - Documentación adjunta`.
- [ ] (Opcional) Recordar visualmente si faltan `buyerReference`, `bookingNumber`, puertos o `exportInvoiceNumber` antes de enviar, ya que ahora se muestran en el email si están rellenos.
