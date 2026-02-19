# Campos nullable en Orders y Customers — Guía para Frontend

**Estado**: Referencia para integración  
**Última actualización**: 2026-02-18

Resumen de los campos que pueden ser `null` en las respuestas de la API para **orders** (pedidos) y **customers** (clientes), de modo que el frontend pueda adaptar la lógica (optional chaining, comprobaciones de null) y evitar errores.

---

## 1. Orders (pedidos)

### 1.1 Campos nullable en el modelo

Los siguientes campos de un pedido pueden ser `null` en la base de datos y, por tanto, en la respuesta API:

| Campo (API)      | Campo BD           | Cuándo es null |
|------------------|--------------------|----------------|
| `paymentTerm`    | `payment_term_id`  | Pedidos tipo autoventa (y otros que no tengan condición de pago) |
| `transport`      | `transport_id`     | Pedidos tipo autoventa (y otros que no tengan transporte) |
| `incoterm`       | `incoterm_id`      | Pedidos sin incoterm (p. ej. autoventa) |
| `billingAddress` | `billing_address`  | Autoventas y pedidos creados sin dirección de facturación |
| `shippingAddress`| `shipping_address` | Autoventas y pedidos creados sin dirección de envío |
| `emails`         | `emails`           | Autoventas y pedidos sin emails |
| `buyerReference` | `buyer_reference`  | Pedidos sin referencia del comprador (siempre ha sido opcional) |

En **listado** (GET `/api/v2/orders`) y **detalle** (GET `/api/v2/orders/{id}`):

- `customer` puede ser `null` en teoría (pedidos huérfanos); en la práctica suele estar siempre.
- `salesperson` puede ser `null` en teoría; en autoventas siempre va informado.
- `transport` e `incoterm` serán **`null`** en autoventas.

### 1.2 Implicaciones para el frontend

- No asumir que `order.transport`, `order.incoterm` o `order.paymentTerm` son objetos. Comprobar si son `null` antes de acceder a propiedades (p. ej. `order.transport?.name`, `order.incoterm?.code`).
- En listados y detalle, las relaciones se serializan como objeto (cuando existen) o `null`. Ejemplo: `transport: null`, `incoterm: null` en autoventas.
- Para mostrar texto en UI (p. ej. “Transporte: …”), usar valor por defecto cuando sea null: `order.transport?.name ?? '—'` o similar.

---

## 2. Customers (clientes)

### 2.1 Campos nullable en el modelo

Los siguientes campos de un cliente pueden ser `null`:

| Campo (API)       | Campo BD            | Cuándo es null |
|-------------------|---------------------|----------------|
| `vatNumber`       | `vat_number`        | Cliente creado sin NIF/CIF (p. ej. creación rápida en autoventa) |
| `paymentTerm`     | `payment_term_id`   | Cliente sin condición de pago asignada |
| `billingAddress`  | `billing_address`   | Cliente sin dirección de facturación |
| `shippingAddress` | `shipping_address`  | Cliente sin dirección de envío |
| `emails`          | `emails`            | Cliente sin emails |
| `contactInfo`     | `contact_info`      | Cliente sin datos de contacto |
| `country`         | `country_id`        | Cliente sin país asignado |
| `transport`       | `transport_id`      | Cliente sin transporte por defecto |

En el flujo de **creación rápida de cliente** (paso 1 de autoventa, POST `/api/v2/customers` con solo `name`), el backend solo exige el nombre; el resto de campos pueden no enviarse y quedarán `null` en la base de datos.

### 2.2 Implicaciones para el frontend

- No asumir que `customer.paymentTerm`, `customer.transport`, `customer.country`, etc. existen. Usar optional chaining: `customer.paymentTerm?.name`, `customer.country?.name`.
- En formularios de edición de cliente, tratar estos campos como opcionales; no dar por hecho que vienen rellenados tras crear un cliente desde autoventa.
- Al mostrar datos del cliente en pedidos o listados, prever `null` en cualquiera de los campos de la tabla anterior.

---

## 3. Resumen rápido

| Entidad   | Campos que pueden ser null en la API |
|-----------|--------------------------------------|
| **Order** | `paymentTerm`, `transport`, `incoterm`, `billingAddress`, `shippingAddress`, `emails`, `buyerReference`; en listado/detalle también puede ser null la relación `transport` o `incoterm` (objeto completo). |
| **Customer** | `vatNumber`, `paymentTerm`, `billingAddress`, `shippingAddress`, `emails`, `contactInfo`, `country`, `transport` (y sus objetos anidados si se exponen). |

**Regla práctica:** Para cualquier campo que sea FK o texto opcional en el backend, el frontend debe comprobar `null` antes de acceder a propiedades anidadas o usarlo en concatenaciones.

---

## 4. Referencias

- **Autoventa API (crear pedido/cliente)**: [71-autoventa-api-frontend.md](71-autoventa-api-frontend.md)
- **Migraciones**: `database/migrations/companies/2026_02_18_160000_make_orders_optional_fields_nullable.php`, `2026_02_18_170000_make_customers_optional_fields_nullable.php`
