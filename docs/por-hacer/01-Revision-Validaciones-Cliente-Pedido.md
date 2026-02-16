# Revisión de Validaciones: Cliente y Pedido

**Fecha de creación:** 2025-01-21  
**Estado:** Pendiente  
**Prioridad:** Media-Alta

## Problema Identificado

Tras la implementación de mensajes personalizados y validaciones mejoradas para las entidades **Cliente** y **Pedido**, se ha detectado que la mayoría de los campos son **opcionales** (`nullable`), lo cual puede ser problemático desde el punto de vista de integridad de datos y experiencia de usuario.

## Entidad: Cliente (Customer)

### Estado Actual de Validaciones

#### Campos Obligatorios
- ✅ `name` - **REQUIRED** (obligatorio)

#### Campos Opcionales (nullable)
- ⚠️ `vatNumber` - nullable
- ⚠️ `billing_address` - nullable
- ⚠️ `shipping_address` - nullable
- ⚠️ `transportation_notes` - nullable
- ⚠️ `production_notes` - nullable
- ⚠️ `accounting_notes` - nullable
- ⚠️ `emails` - nullable
- ⚠️ `ccEmails` - nullable
- ⚠️ `contact_info` - nullable
- ⚠️ `salesperson_id` - nullable
- ⚠️ `country_id` - nullable
- ⚠️ `payment_term_id` - nullable
- ⚠️ `transport_id` - nullable
- ⚠️ `a3erp_code` - nullable
- ⚠️ `facilcom_code` - nullable

### Observaciones

1. **Información de Contacto Crítica:**
   - `emails`: Un cliente sin emails puede ser problemático para comunicaciones importantes
   - `contact_info`: Información de contacto puede ser esencial

2. **Información Fiscal:**
   - `vatNumber`: Para facturación, el NIF/CIF puede ser obligatorio según normativa
   - `billing_address`: Dirección de facturación generalmente requerida

3. **Relaciones Clave:**
   - `country_id`: País puede ser necesario para validaciones fiscales y logísticas
   - `payment_term_id`: Términos de pago pueden ser necesarios para procesamiento de pedidos
   - `transport_id`: Transporte puede ser necesario para envíos

4. **Direcciones:**
   - `shipping_address`: Puede ser necesaria si difiere de la dirección de facturación

### Preguntas a Resolver

- ¿Es realmente opcional el `vatNumber` para todos los tipos de clientes?
- ¿Debe haber al menos un email obligatorio?
- ¿Son realmente opcionales las direcciones de facturación y envío?
- ¿Deben ser obligatorias las relaciones con `country`, `payment_term` o `transport`?

## Entidad: Pedido (Order)

### Estado Actual de Validaciones

#### Campos Obligatorios
- ✅ `customer` (customer_id) - **REQUIRED** (obligatorio)
- ✅ `entryDate` (entry_date) - **REQUIRED** (obligatorio)
- ✅ `loadDate` (load_date) - **REQUIRED** (obligatorio)

#### Campos Opcionales (nullable)
- ⚠️ `salesperson` (salesperson_id) - nullable
- ⚠️ `payment` (payment_term_id) - nullable
- ⚠️ `incoterm` (incoterm_id) - nullable
- ⚠️ `transport` (transport_id) - nullable
- ⚠️ `buyerReference` - nullable
- ⚠️ `truckPlate` - nullable
- ⚠️ `trailerPlate` - nullable
- ⚠️ `temperature` - nullable
- ⚠️ `billingAddress` - nullable
- ⚠️ `shippingAddress` - nullable
- ⚠️ `transportationNotes` - nullable
- ⚠️ `productionNotes` - nullable
- ⚠️ `accountingNotes` - nullable
- ⚠️ `emails` - nullable
- ⚠️ `ccEmails` - nullable
- ⚠️ `plannedProducts` - nullable (array completo)

### Observaciones

1. **Información de Envío Crítica:**
   - `transport_id`: ¿Cómo se gestiona un pedido sin transporte asignado?
   - `shippingAddress`: Dirección de envío puede ser esencial
   - `truckPlate` / `trailerPlate`: Pueden ser necesarios para logística

2. **Información Comercial:**
   - `salesperson_id`: Puede ser necesario para comisiones y seguimiento
   - `payment_term_id`: Puede ser necesario para facturación
   - `incoterm_id`: Puede ser necesario para términos de entrega

3. **Productos Planificados:**
   - `plannedProducts`: Un pedido sin productos planificados puede no tener sentido
   - ¿Debe haber al menos un producto planificado?

4. **Comunicaciones:**
   - `emails`: Puede ser necesario para notificaciones del pedido

5. **Información de Facturación:**
   - `billingAddress`: Puede ser necesaria si difiere de la del cliente

### Preguntas a Resolver

- ¿Puede existir un pedido sin productos planificados?
- ¿Es realmente opcional el `transport_id` para un pedido?
- ¿Debe ser obligatorio el `payment_term_id` para procesar facturación?
- ¿Debe ser obligatorio el `incoterm_id` para pedidos internacionales?
- ¿Debe haber al menos un email para notificaciones?

## Recomendaciones

### Para Cliente

1. **Revisar con el equipo de negocio:**
   - Determinar qué campos son realmente críticos según el flujo de trabajo
   - Considerar diferentes tipos de clientes (B2B vs B2C, nacionales vs internacionales)

2. **Campos que probablemente deberían ser obligatorios:**
   - `vatNumber`: Si es necesario para facturación
   - `billing_address`: Si es necesaria para facturación
   - `country_id`: Si es necesario para validaciones fiscales
   - `emails`: Al menos uno debería ser obligatorio

3. **Validaciones condicionales:**
   - Si `country_id` es internacional, `incoterm_id` podría ser obligatorio
   - Si hay transporte, `transport_id` podría ser obligatorio

### Para Pedido

1. **Revisar con el equipo de negocio:**
   - Determinar qué información es crítica para procesar un pedido
   - Considerar diferentes tipos de pedidos (nacionales vs internacionales)

2. **Campos que probablemente deberían ser obligatorios:**
   - `plannedProducts`: Debe haber al menos un producto planificado
   - `transport_id`: Si el pedido requiere envío
   - `payment_term_id`: Si es necesario para facturación
   - `shippingAddress`: Si es diferente de la dirección de facturación del cliente

3. **Validaciones condicionales:**
   - Si `incoterm_id` está presente, validar que sea apropiado para el país del cliente
   - Si hay transporte, `truckPlate` podría ser obligatorio

## Acciones Sugeridas

1. **Reunión con stakeholders:**
   - Revisar flujos de trabajo actuales
   - Identificar campos críticos según casos de uso reales

2. **Análisis de datos existentes:**
   - Revisar qué campos están siempre presentes en registros existentes
   - Identificar patrones de uso

3. **Implementación gradual:**
   - Agregar validaciones obligatorias en fases
   - Considerar migración de datos existentes si es necesario

4. **Documentación:**
   - Documentar qué campos son obligatorios y por qué
   - Crear guías para diferentes tipos de clientes/pedidos

## Notas Técnicas

- Las validaciones actuales están implementadas en:
  - `app/Http/Controllers/v2/CustomerController.php`
  - `app/Http/Controllers/v2/OrderController.php`
- Los mensajes de error ya están en lenguaje natural y se devuelven en `userMessage`
- Las validaciones del modelo (`boot()`) también deben revisarse si se cambian las reglas

## Referencias

- Controlador Cliente: `app/Http/Controllers/v2/CustomerController.php`
- Controlador Pedido: `app/Http/Controllers/v2/OrderController.php`
- Modelo Cliente: `app/Models/Customer.php`
- Modelo Pedido: `app/Models/Order.php`
- Migraciones: `database/migrations/companies/`

