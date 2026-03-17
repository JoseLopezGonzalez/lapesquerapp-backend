# CRM Comercial Backend — Resumen de Implementación para Revisión

## Objetivo de este documento

Este documento resume **lo que realmente se ha implementado** en el backend respecto al plan del CRM comercial, para que otro agente o revisor pueda comparar:

1. lo planeado,
2. lo efectivamente desarrollado,
3. los huecos, simplificaciones o decisiones que conviene validar.

No describe la UI. Solo backend Laravel/API y documentación de integración frontend.

---

## 1. Alcance implementado

Se ha implementado un bloque nuevo de CRM comercial en este repo Laravel multi-tenant con:

- Prospectos
- Contactos de prospecto
- Interacciones comerciales
- Ofertas y líneas de oferta
- Dashboard CRM agregado
- Conversión de prospecto a cliente
- Creación de pedido desde oferta aceptada
- Documentación de integración para frontend
- Tests feature específicos del CRM

También se ha enlazado parcialmente con entidades existentes:

- `Customer`
- `Order`
- `Salesperson`
- `Incoterm`
- `PaymentTerm`
- `Tax`
- `Product`

Importante sobre pedidos del comercial:

- el backend ya expone lo necesario para listar y ver pedidos propios del comercial
- pero eso **no significa** que el frontend de `/comercial/pedidos` esté “resuelto” solo con ocultar botones
- en frontend sigue haciendo falta, como mínimo:
  - hook específico de consumo para pedidos del comercial
  - ruta `/comercial/pedidos`
  - adaptación del flujo de consumo del Order Manager

---

## 2. Archivos principales creados o modificados

### Migraciones nuevas

- [2026_03_17_120000_create_prospects_table.php](/home/jose/lapesquerapp-backend/database/migrations/companies/2026_03_17_120000_create_prospects_table.php)
- [2026_03_17_120100_create_prospect_contacts_table.php](/home/jose/lapesquerapp-backend/database/migrations/companies/2026_03_17_120100_create_prospect_contacts_table.php)
- [2026_03_17_120200_create_commercial_interactions_table.php](/home/jose/lapesquerapp-backend/database/migrations/companies/2026_03_17_120200_create_commercial_interactions_table.php)
- [2026_03_17_120300_create_offers_table.php](/home/jose/lapesquerapp-backend/database/migrations/companies/2026_03_17_120300_create_offers_table.php)
- [2026_03_17_120400_create_offer_lines_table.php](/home/jose/lapesquerapp-backend/database/migrations/companies/2026_03_17_120400_create_offer_lines_table.php)

### Modelos nuevos

- [Prospect.php](/home/jose/lapesquerapp-backend/app/Models/Prospect.php)
- [ProspectContact.php](/home/jose/lapesquerapp-backend/app/Models/ProspectContact.php)
- [CommercialInteraction.php](/home/jose/lapesquerapp-backend/app/Models/CommercialInteraction.php)
- [Offer.php](/home/jose/lapesquerapp-backend/app/Models/Offer.php)
- [OfferLine.php](/home/jose/lapesquerapp-backend/app/Models/OfferLine.php)

### Policies nuevas

- [ProspectPolicy.php](/home/jose/lapesquerapp-backend/app/Policies/ProspectPolicy.php)
- [CommercialInteractionPolicy.php](/home/jose/lapesquerapp-backend/app/Policies/CommercialInteractionPolicy.php)
- [OfferPolicy.php](/home/jose/lapesquerapp-backend/app/Policies/OfferPolicy.php)

### Servicios nuevos

- [ProspectService.php](/home/jose/lapesquerapp-backend/app/Services/v2/ProspectService.php)
- [CommercialInteractionService.php](/home/jose/lapesquerapp-backend/app/Services/v2/CommercialInteractionService.php)
- [OfferService.php](/home/jose/lapesquerapp-backend/app/Services/v2/OfferService.php)
- [CrmDashboardService.php](/home/jose/lapesquerapp-backend/app/Services/v2/CrmDashboardService.php)

### Controladores nuevos

- [ProspectController.php](/home/jose/lapesquerapp-backend/app/Http/Controllers/v2/ProspectController.php)
- [CommercialInteractionController.php](/home/jose/lapesquerapp-backend/app/Http/Controllers/v2/CommercialInteractionController.php)
- [OfferController.php](/home/jose/lapesquerapp-backend/app/Http/Controllers/v2/OfferController.php)
- [CrmDashboardController.php](/home/jose/lapesquerapp-backend/app/Http/Controllers/v2/CrmDashboardController.php)

### Requests nuevos

- Prospectos: index/store/update/contactos/schedule
- Interacciones: index/store
- Ofertas: index/store/update/send/reject/create-order

Todos viven bajo [app/Http/Requests/v2/](/home/jose/lapesquerapp-backend/app/Http/Requests/v2).

### Resources nuevos

- [ProspectResource.php](/home/jose/lapesquerapp-backend/app/Http/Resources/v2/ProspectResource.php)
- [CommercialInteractionResource.php](/home/jose/lapesquerapp-backend/app/Http/Resources/v2/CommercialInteractionResource.php)
- [OfferResource.php](/home/jose/lapesquerapp-backend/app/Http/Resources/v2/OfferResource.php)

### Integraciones modificadas

- [routes/api.php](/home/jose/lapesquerapp-backend/routes/api.php)
- [AuthServiceProvider.php](/home/jose/lapesquerapp-backend/app/Providers/AuthServiceProvider.php)
- [Customer.php](/home/jose/lapesquerapp-backend/app/Models/Customer.php)
- [Order.php](/home/jose/lapesquerapp-backend/app/Models/Order.php)
- [Salesperson.php](/home/jose/lapesquerapp-backend/app/Models/Salesperson.php)
- [OrderResource.php](/home/jose/lapesquerapp-backend/app/Http/Resources/v2/OrderResource.php)
- [OrderDetailsResource.php](/home/jose/lapesquerapp-backend/app/Http/Resources/v2/OrderDetailsResource.php)
- [OrderListService.php](/home/jose/lapesquerapp-backend/app/Services/v2/OrderListService.php)
- [OrderDetailService.php](/home/jose/lapesquerapp-backend/app/Services/v2/OrderDetailService.php)

### Documentación nueva

- [docs/api-references/crm/README.md](/home/jose/lapesquerapp-backend/docs/api-references/crm/README.md)
- [prospects.md](/home/jose/lapesquerapp-backend/docs/api-references/crm/prospects.md)
- [commercial-interactions.md](/home/jose/lapesquerapp-backend/docs/api-references/crm/commercial-interactions.md)
- [offers.md](/home/jose/lapesquerapp-backend/docs/api-references/crm/offers.md)
- [dashboard.md](/home/jose/lapesquerapp-backend/docs/api-references/crm/dashboard.md)
- [flows.md](/home/jose/lapesquerapp-backend/docs/api-references/crm/flows.md)

### Tests nuevos

- [CrmApiTest.php](/home/jose/lapesquerapp-backend/tests/Feature/CrmApiTest.php)

---

## 3. Modelo de datos implementado

### `prospects`

Campos implementados:

- `salesperson_id`
- `company_name`
- `country_id`
- `species_interest` JSON nullable
- `origin`
- `status`
- `customer_id`
- `next_action_at`
- `notes`
- `commercial_interest_notes`
- `last_contact_at`
- `last_offer_at`
- `lost_reason`
- timestamps

Índices implementados:

- `salesperson_id + status`
- `next_action_at`
- `last_contact_at`
- `last_offer_at`

### `prospect_contacts`

Campos implementados:

- `prospect_id`
- `name`
- `role`
- `phone`
- `email`
- `is_primary`
- timestamps

Índice:

- `prospect_id + is_primary`

### `commercial_interactions`

Campos implementados:

- `prospect_id` nullable
- `customer_id` nullable
- `salesperson_id`
- `type`
- `occurred_at`
- `summary`
- `result`
- `next_action_note`
- `next_action_at`
- `created_at`

Decisión real:

- **No tiene `updated_at`**
- Se añadió un `CHECK` para forzar exactamente uno entre `prospect_id` y `customer_id`

### `offers`

Campos implementados:

- `prospect_id`
- `customer_id`
- `salesperson_id`
- `status`
- `send_channel`
- `sent_at`
- `valid_until`
- `incoterm_id`
- `payment_term_id`
- `currency`
- `notes`
- `accepted_at`
- `rejected_at`
- `rejection_reason`
- `order_id` unique nullable
- timestamps

Decisión real:

- `CHECK` de exclusión mutua entre `prospect_id` y `customer_id`
- `prospect_id` y `customer_id` usan borrado restringido para no romper la regla de integridad al eliminar targets relacionados

### `offer_lines`

Campos implementados:

- `offer_id`
- `product_id`
- `description`
- `quantity`
- `unit`
- `unit_price`
- `tax_id`
- `boxes`
- `currency`
- `created_at`

Decisión real:

- **No tiene `updated_at`**

---

## 4. API implementada

### Prospectos

Implementado:

- `GET /api/v2/prospects`
- `POST /api/v2/prospects`
- `GET /api/v2/prospects/{id}`
- `PUT /api/v2/prospects/{id}`
- `DELETE /api/v2/prospects/{id}`
- `GET /api/v2/prospects/{id}/contacts`
- `POST /api/v2/prospects/{id}/contacts`
- `PUT /api/v2/prospects/{id}/contacts/{contactId}`
- `DELETE /api/v2/prospects/{id}/contacts/{contactId}`
- `POST /api/v2/prospects/{id}/convert-to-customer`
- `POST /api/v2/prospects/{id}/schedule-action`
- `DELETE /api/v2/prospects/{id}/next-action`

### Interacciones

Implementado:

- `GET /api/v2/commercial-interactions`
- `POST /api/v2/commercial-interactions`
- `GET /api/v2/commercial-interactions/{id}`

No implementado:

- update/delete de interacciones

Esto es coherente con la decisión V1 de que las interacciones no se editan.

### Ofertas

Implementado:

- `GET /api/v2/offers`
- `POST /api/v2/offers`
- `GET /api/v2/offers/{id}`
- `PUT /api/v2/offers/{id}`
- `DELETE /api/v2/offers/{id}`
- `POST /api/v2/offers/{id}/send`
- `POST /api/v2/offers/{id}/accept`
- `POST /api/v2/offers/{id}/reject`
- `POST /api/v2/offers/{id}/expire`
- `GET /api/v2/offers/{id}/pdf`
- `GET /api/v2/offers/{id}/whatsapp-text`
- `POST /api/v2/offers/{id}/email`
- `POST /api/v2/offers/{id}/create-order`

### Dashboard CRM

Implementado:

- `GET /api/v2/crm/dashboard`

Respuesta agregada con:

- `reminders_today`
- `overdue_actions`
- `inactive_customers`
- `prospects_without_activity`
- `counters`

---

## 5. Reglas de negocio implementadas

### Prospectos

Implementado:

- Scoping por comercial autenticado
- Alta/edición con contacto primario inline opcional
- Detección de duplicados informativa
- Reprogramación y limpieza de `next_action_at`

Importante:

- Los duplicados **no bloquean**, se devuelven en `warnings`

### Contactos de prospecto

Implementado:

- CRUD básico dentro del contexto del prospecto
- Si un contacto se marca como primario, se desmarca el anterior

Importante:

- La unicidad del primario está resuelta **a nivel de servicio**, no con índice único parcial en BD

### Interacciones

Implementado:

- Validación de target exclusivo: prospecto o cliente
- Alta de interacción
- Filtros de listado
- Al crear interacción sobre prospecto:
  - se actualiza `last_contact_at`
  - se actualiza `next_action_at` del prospecto

Importante:

- La lógica de “resolver acción pendiente dejando `nextActionAt` vacío” se traduce en que el prospecto queda con `next_action_at = null` cuando la interacción es sobre prospecto
- Para cliente no existe un campo espejo equivalente, solo se registra la interacción

### Ofertas

Implementado:

- Solo se crean/editar/borran en `draft`
- Transiciones:
  - `draft -> sent`
  - `sent -> accepted`
  - `sent -> rejected`
  - `draft|sent|rejected -> expired` con restricción sobre aceptadas
- Al enviar oferta de prospecto:
  - el prospecto pasa a `offer_sent`
  - se actualiza `last_offer_at`

### Conversión prospecto -> cliente

Implementado en transacción:

1. valida `status === offer_sent`
2. exige contacto primario
3. exige teléfono o email en contacto primario
4. crea `Customer`
5. actualiza prospecto a `customer`
6. rellena `customer_id`
7. mueve las ofertas del prospecto al cliente, dejando `prospect_id = null` y rellenando `customer_id`

Mapeo real implementado:

- `company_name -> customers.name`
- `country_id -> customers.country_id`
- `salesperson_id -> customers.salesperson_id`
- `email primario -> customers.emails`
- `nombre/cargo/teléfono -> customers.contact_info`
- `payment_term_id` desde oferta aceptada si existe

### Crear pedido desde oferta aceptada

Implementado:

- Requiere `offer.status === accepted`
- Rechaza si la oferta ya tiene `order_id`
- Si la oferta cuelga de prospecto sin cliente:
  - convierte el prospecto antes de crear el pedido
- Copia líneas de oferta a `plannedProducts`
- Permite líneas extra via payload `plannedProducts`
- Usa internamente `OrderStoreService::store()`
- Guarda `offers.order_id`

Restricción real importante:

- Para crear pedido desde oferta, cada línea de oferta debe tener:
  - `product_id`
  - `tax_id`
  - `boxes`

Si faltan, devuelve `422`.

---

## 6. Autorización implementada

### Policies nuevas

Se añadieron policies para:

- Prospect
- CommercialInteraction
- Offer

### Comportamiento real

`comercial`:

- solo puede ver/crear/editar/borrar sus propios prospectos
- solo puede ver/crear sus propias interacciones
- solo puede ver/crear/editar/borrar sus propias ofertas

`administrador`, `tecnico`, `direccion`:

- acceso completo a CRM

### Validaciones complementarias dentro de servicios

Además de las policies:

- `CommercialInteractionService` valida que un comercial no cree interacción sobre target ajeno
- `OfferService` valida que un comercial no cree/edite ofertas sobre target ajeno

Esto cierra huecos donde la policy por sí sola no bastaba para el target del payload.

---

## 7. Integraciones con código existente

### Pedido <-> oferta

Se añadió relación:

- `Order::offer()`

Y ahora los resources de pedidos exponen:

- `offerId`

Archivos:

- [OrderResource.php](/home/jose/lapesquerapp-backend/app/Http/Resources/v2/OrderResource.php)
- [OrderDetailsResource.php](/home/jose/lapesquerapp-backend/app/Http/Resources/v2/OrderDetailsResource.php)

También se cargó `offer` en:

- [OrderListService.php](/home/jose/lapesquerapp-backend/app/Services/v2/OrderListService.php)
- [OrderDetailService.php](/home/jose/lapesquerapp-backend/app/Services/v2/OrderDetailService.php)

### Customer y Salesperson

Se añadieron relaciones nuevas en modelos existentes para CRM:

- `Customer -> commercialInteractions/offers/prospects`
- `Salesperson -> prospects/commercialInteractions/offers`

---

## 8. PDF, email y WhatsApp de ofertas

### PDF

Implementado con vista:

- [resources/views/pdf/v2/offers/offer.blade.php](/home/jose/lapesquerapp-backend/resources/views/pdf/v2/offers/offer.blade.php)

Generado desde:

- [OfferController.php](/home/jose/lapesquerapp-backend/app/Http/Controllers/v2/OfferController.php)

### Email

Implementado:

- [OfferMail.php](/home/jose/lapesquerapp-backend/app/Mail/OfferMail.php)
- [resources/views/emails/offers/offer.blade.php](/home/jose/lapesquerapp-backend/resources/views/emails/offers/offer.blade.php)

Importante:

- El email adjunta el PDF generado
- El envío usa `Mail::to(...)->send(...)`

### Texto WhatsApp

Implementado como texto generado por backend:

- `OfferService::buildWhatsappText()`

No se integra ninguna API externa.

---

## 9. Dashboard CRM implementado

### Secciones reales

#### `reminders_today`

Actualmente incluye:

- prospectos con `next_action_at = hoy`
- interacciones con `next_action_at = hoy`

#### `overdue_actions`

Actualmente mezcla:

- prospectos con `next_action_at < hoy`
- interacciones con `next_action_at < hoy`

#### `inactive_customers`

Calculado como:

- clientes del scope
- sin pedido o con último pedido anterior a 30 días

#### `prospects_without_activity`

Calculado como:

- prospectos sin `last_contact_at`
- o con `last_contact_at < hoy - 7 días`

### Estado actual respecto a la idea original

La agenda diaria ya mezcla prospectos e interacciones de hoy.  
`overdue_actions` queda reservado para elementos vencidos de ambos tipos.

---

## 10. Documentación entregada para frontend

Se añadió documentación específica del CRM en:

- [README.md](/home/jose/lapesquerapp-backend/docs/api-references/crm/README.md)
- [prospects.md](/home/jose/lapesquerapp-backend/docs/api-references/crm/prospects.md)
- [commercial-interactions.md](/home/jose/lapesquerapp-backend/docs/api-references/crm/commercial-interactions.md)
- [offers.md](/home/jose/lapesquerapp-backend/docs/api-references/crm/offers.md)
- [dashboard.md](/home/jose/lapesquerapp-backend/docs/api-references/crm/dashboard.md)
- [flows.md](/home/jose/lapesquerapp-backend/docs/api-references/crm/flows.md)

El contenido incluye:

- contratos
- ejemplos de request/response
- flujos
- reglas de estado
- permisos
- compatibilidad con `customers` y `orders`

---

## 11. Tests implementados

Se añadió:

- [CrmApiTest.php](/home/jose/lapesquerapp-backend/tests/Feature/CrmApiTest.php)

Casos cubiertos:

- scoping de prospectos por comercial
- warnings de duplicados
- interacción actualizando `last_contact_at` y agenda
- interacción inválida con ambos targets a la vez
- conversión prospecto -> cliente
- ciclo de vida de oferta
- oferta inválida con ambos targets a la vez
- una oferta aceptada no puede rechazarse
- creación de pedido desde oferta
- intento de crear pedido desde oferta ya enlazada
- dashboard CRM
- regresión básica de `salespeople/options` y `settings`

### Limitación real de verificación

La suite no pudo ejecutarse completamente en este entorno porque el helper de tests no encontró MySQL accesible en `127.0.0.1:3306`.

Estado real:

- sintaxis verificada con `php -l`
- rutas verificadas con `php artisan route:list`
- formato aplicado con `vendor/bin/pint --dirty`
- tests preparados pero no ejecutados end-to-end por dependencia externa de BD

---

## 12. Diferencias o simplificaciones respecto al plan que conviene revisar

### 1. Unicidad de contacto primario no está reforzada con constraint SQL

Está resuelta en servicio, no con índice parcial/constraint adicional.

### 2. `OfferPolicy` permite update/delete por ownership, pero el bloqueo fuerte por estado vive en servicio

Esto es válido, pero conviene revisar si el equipo prefiere endurecer también a nivel policy/abilities separadas.

### 3. Rechazo de oferta desde `accepted`

La implementación ya no lo permite. `accepted` queda como estado terminal frente a rechazo.

### 4. Conversión a cliente usa oferta aceptada para `payment_term_id`

La implementación toma la oferta aceptada más reciente.  
Si se esperaba cualquier “oferta activa” o una regla distinta, conviene validarlo.

### 5. La salida PDF/email de ofertas existe, pero es mínima

La implementación es funcional, pero la plantilla PDF/email es simple.  
Si el plan esperaba salida formal más rica, eso seguiría siendo una mejora posterior.

### 6. La integridad de ofertas depende de mantener el cambio de target en conversión

La regla `prospect_id XOR customer_id` ya está alineada con migración y servicios.  
Conviene revisar que cualquier cambio futuro siga moviendo la oferta de prospecto a cliente, y no intente dejar ambos campos rellenos.

### 7. No se añadieron tests sobre todos los casos de error

Hay cobertura principal y ya se añadieron varios casos finos, pero sigue habiendo margen para ampliar escenarios límite.

---

## 13. Juicio rápido sobre la implementación

### Lo que sí cumple bien

- estructura del dominio
- multi-tenancy sin `tenant_id`
- rutas principales del CRM
- policies nuevas
- servicios de negocio separados
- conversión a cliente
- oferta -> pedido
- documentación de integración

### Lo que parece correcto pero merece revisión funcional

- exactitud de transiciones de estado de oferta
- nivel de robustez SQL para “un único contacto primario”
- si el frontend necesita más datos embebidos en ciertos resources
- precisión del handoff frontend para `/comercial/pedidos`, porque el backend está listo pero el consumo frontend sigue requiriendo hook/ruta/adaptación

### Lo que no parece un bloqueo ahora mismo

- plantillas visuales de PDF/email sencillas
- ausencia de edición de interacciones

Eso es coherente con una V1 backend-first.

---

## 14. Preguntas concretas para el agente revisor

Puedes pedirle que revise específicamente:

1. Si la implementación respeta correctamente el flujo de estados pensado para ofertas, dejando `accepted` como estado terminal frente a rechazo.
2. Si `CrmDashboardService` refleja fielmente la agenda deseada ahora que mezcla prospectos e interacciones del día.
3. Si la conversión `prospect -> customer` mapea correctamente los campos legacy.
4. Si la validación “exactamente uno entre prospecto y cliente” está bien resuelta en requests + BD + servicios.
5. Si el uso de servicios para restringir ownership en payload es suficiente o conviene endurecer más a nivel policy.
6. Si la creación de pedido desde oferta debería exigir payload más estricto o precargar más campos del cliente/oferta.
7. Si el nivel actual de tests es suficiente antes de considerar el bloque estable.
