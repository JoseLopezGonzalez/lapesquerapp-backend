# Flujos de Integración

## 1. Crear prospecto con contacto primario

1. Llamar a `POST /api/v2/prospects`
2. Revisar `warnings`
3. Si el frontend necesita más contactos, usar `/prospects/{id}/contacts`

## 2. Registrar seguimiento comercial

1. Llamar a `POST /api/v2/commercial-interactions`
2. Si la interacción es sobre prospecto, la API actualiza `last_contact_at`
3. Si llega `nextActionAt`, la agenda queda reprogramada
4. Si no llega `nextActionAt`, la agenda pendiente se limpia en el prospecto

## 3. Enviar oferta desde prospecto

1. Crear oferta en `draft` con `POST /api/v2/offers`
2. Enviar con `POST /api/v2/offers/{id}/send`
3. Efecto colateral esperado:
   - la oferta pasa a `sent`
   - el prospecto pasa a `offer_sent`
   - `prospects.last_offer_at` se actualiza

## 4. Aceptar oferta y crear pedido

1. `POST /api/v2/offers/{id}/accept`
2. `POST /api/v2/offers/{id}/create-order`
3. Si la oferta aún cuelga de prospecto sin cliente:
   - el backend convierte el prospecto
   - crea el pedido
   - enlaza el pedido en `offers.order_id`

## 5. Detectar si un pedido viene de una oferta

Opciones:

- Leer `offerId` en el recurso de pedido
- O consultar `GET /api/v2/offers?orderId={id}`

## 6. Permisos por rol

### Comercial

- Prospectos: solo los suyos
- Interacciones: solo las suyas
- Ofertas: solo las suyas
- Dashboard CRM: solo sus datos

### Administrador / Técnico / Dirección

- Pueden ver y operar sobre todos los registros CRM

## 7. Estados editables y de solo lectura

### Prospecto

- Editable en cualquier estado por usuarios autorizados
- Conversión a cliente solo en `offer_sent`

### Oferta

- `draft`: editable y borrable
- `sent`: no editable, sí aceptable/rechazable
- `accepted`: no editable, permite `create-order` una sola vez
- `rejected` / `expired`: solo lectura

## 8. Compatibilidad con entidades legacy

- El cliente generado desde prospecto respeta el modelo existente:
  - `name`
  - `country_id`
  - `salesperson_id`
  - `emails` separado por `;`
  - `contact_info` en texto libre
- La creación real del pedido sigue usando el flujo estándar de `orders`
