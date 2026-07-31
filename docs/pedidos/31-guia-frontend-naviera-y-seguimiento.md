# Guía Frontend: Naviera y Enlace de Seguimiento (Exportación Marítima)

## 📋 Resumen

Campo nuevo en los datos de envío marítimo (`OrderMaritimeShippingDetail`): **`shippingLine`** (naviera). Se usa para generar automáticamente un enlace de seguimiento público por contenedor, que aparece en el email de [30-guia-frontend-envio-documentos-exportacion-maritima.md](./30-guia-frontend-envio-documentos-exportacion-maritima.md) sin que haya que hacer nada más al enviar.

No hay tracking en vivo (no se consulta ningún estado del envío) — es un enlace directo a la página pública de seguimiento de la naviera, pre-rellenado con el número de contenedor. Por eso solo hace falta guardar la naviera una vez en el formulario de datos de envío marítimo; el resto es automático.

---

## 1. Campo nuevo — `OrderMaritimeShippingDetail.shippingLine`

| Campo | Tipo | Notas |
|-------|------|-------|
| `shippingLine` | string\|null | **Nuevo**. Uno de: `"maersk"`, `"msc"`, `"other"`. `null` si no se ha indicado. |

Mismo endpoint de siempre, payload ampliado:

```
PUT /api/v2/orders/{order}/maritime-shipping-details
{
  "shippingLine": "maersk",
  "vesselName": "Mando 631S",
  "voyageNumber": "631S",
  "...": "resto de campos sin cambios"
}
```

`GET /api/v2/orders/{order}/maritime-shipping-details` devuelve `shippingLine` en el mismo shape.

### Valores válidos

| Valor | Naviera | ¿Genera enlace de seguimiento? |
|-------|---------|:---:|
| `maersk` | Maersk | Sí |
| `msc` | MSC | Sí |
| `other` | Otra naviera | No (se guarda igualmente, pero no hay plantilla de URL conocida para generar el enlace) |
| `null` | Sin indicar | No |

Solo Maersk y MSC tienen plantilla de URL de seguimiento pública conocida. Si en el futuro se añaden más navieras con enlace, es un cambio interno de `CarrierTrackingLinkService` (backend) — el contrato del campo `shippingLine` no cambia.

**Acción frontend**: añadir un select "Naviera" (Maersk / MSC / Otra) al formulario de datos de envío marítimo, opcional, junto al resto de campos ya existentes (buque, viaje, booking, agente de aduanas...).

---

## 2. Cómo se usa al enviar la documentación

No hay ningún campo nuevo que rellenar en `POST /orders/{orderId}/send-maritime-export-documents` (ver doc 30) — el enlace de seguimiento se resuelve automáticamente en el backend a partir de:
- `shippingLine` guardado en los datos de envío marítimo del pedido.
- El número de contenedor de cada `OrderMaritimeContainer` del pedido (uno por contenedor, igual que el Export Packing List).

Si `shippingLine` es `maersk` o `msc`, el email incluye una sección "Seguimiento del envío" con un enlace por contenedor. Si es `other`, `null`, o el pedido no tiene contenedores, esa sección simplemente no aparece — no es un error, no hay nada que el frontend deba validar ni avisar al respecto.

---

## 3. Alcance actual — qué NO hace esto

- **No** consulta el estado real del envío (ubicación del buque, aduana, entrega...). Es solo un enlace a la web pública de tracking de la naviera.
- **No** hay integración con la API de Maersk (existe, es viable a futuro) ni con la de MSC (existe pero requiere acuerdo comercial con su equipo de ventas — no es self-service). Si se decide abordar eso más adelante, será un cambio de alcance mayor, aparte de esta guía.

---

## 4. Checklist de Implementación Frontend

- [ ] Select "Naviera" (Maersk / MSC / Otra) en el formulario de datos de envío marítimo, opcional, guardando `shippingLine` (`maersk`/`msc`/`other`).
- [ ] No hace falta ningún cambio en la pantalla de envío de documentación (doc 30) — el enlace aparece solo si corresponde, sin selección manual.
- [ ] Opcional: si se quiere dar feedback visual antes de enviar, se puede mostrar en el detalle del pedido un aviso tipo "se incluirá enlace de seguimiento de Maersk" cuando `shippingLine` sea `maersk`/`msc`, pero no es obligatorio — el backend ya lo gestiona solo.
