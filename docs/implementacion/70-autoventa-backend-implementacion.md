# Autoventa — Implementación backend

**Estado**: Implementado  
**Última actualización**: 2026-02-18

Documento de referencia de la implementación backend del flujo **Autoventa** (rol comercial): modelo de datos, API, autorización y archivos modificados.

---

## 1. Objetivo y alcance (backend)

- Persistir autoventas como pedidos con `order_type = 'autoventa'`.
- Al confirmar: crear Order, OrderPlannedProductDetails, un Pallet con `order_id` y `status = STATE_SHIPPED`, y las cajas (Box + PalletBox) a partir del payload.
- Autorización: solo usuarios con rol **comercial** y Salesperson asociado pueden crear autoventas; mismo criterio de cliente propio que el resto de pedidos.
- No hay endpoint de escaneo QR: el frontend parsea GS1-128 y envía `items` y `boxes` ya estructurados.

---

## 2. Modelo de datos

### 2.1 Cambio en `orders`

- **Migración**: [database/migrations/companies/2026_02_18_120000_add_order_type_to_orders_table.php](database/migrations/companies/2026_02_18_120000_add_order_type_to_orders_table.php)
- Columna `order_type` (string, default `'standard'`). Valores: `'standard'`, `'autoventa'`.
- **Order**: `$fillable` incluye `order_type`; constantes `ORDER_TYPE_STANDARD`, `ORDER_TYPE_AUTOVENTA`; scope `scopeAutoventas()`; accessor `is_autoventa`.

### 2.2 Flujo de persistencia (AutoventaStoreService)

1. **Order**: `order_type = 'autoventa'`, `customer_id`, `entry_date`, `load_date`, `salesperson_id` (del usuario), `accounting_notes` ("Con factura"/"Sin factura" + observaciones), `status = 'pending'`, resto nullable.
2. **OrderPlannedProductDetail**: una línea por producto en `items` (product_id, quantity = totalWeight, boxes = boxesCount, unit_price, tax_id por línea o tax por defecto).
3. **Pallet**: uno por autoventa; `order_id` = id del Order, `status = Pallet::STATE_SHIPPED`; sin `reception_id`, sin almacén.
4. **Box + PalletBox**: por cada elemento de `boxes` se crea Box (article_id, lot, gs1_128, net_weight, gross_weight) y PalletBox. Si `lot` viene vacío se usa `AUTOVENTA-{order_id}-{index}`.

---

## 3. API

### 3.1 Crear autoventa: `POST /api/v2/orders`

Mismo endpoint que pedidos estándar. Si el body incluye `orderType: 'autoventa'`, se aplica validación y flujo de autoventa.

**Headers**: `Authorization: Bearer {token}`, `X-Tenant: {subdomain}`

**Body (autoventa)**:

```json
{
  "orderType": "autoventa",
  "customer": 10,
  "entryDate": "2026-02-18",
  "loadDate": "2026-02-18",
  "invoiceRequired": true,
  "observations": "Texto opcional",
  "items": [
    {
      "productId": 45,
      "boxesCount": 3,
      "totalWeight": 37.5,
      "unitPrice": 15.00,
      "subtotal": 562.50,
      "tax": 1
    }
  ],
  "boxes": [
    {
      "productId": 45,
      "lot": "LOT-2024-001",
      "netWeight": 12.5,
      "gs1128": "01...3100...10...",
      "grossWeight": 12.6
    }
  ]
}
```

- `items`: líneas agregadas por producto (para totales y OrderPlannedProductDetail). `tax` es opcional; si no se envía se usa el primer impuesto del tenant.
- `boxes`: una entrada por caja física; se crean Box + PalletBox. `lot` y `gs1128` opcionales; `grossWeight` opcional (por defecto = netWeight).

**Response (201)**: Mismo formato que detalle de pedido (`OrderDetailsResource`), con `orderType: 'autoventa'` y pallets/cajas creados.

### 3.2 Listado con filtro: `GET /api/v2/orders?orderType=autoventa`

- Parámetro opcional `orderType`: `standard` | `autoventa`.
- El comercial sigue viendo solo sus pedidos (filtro por `salesperson_id`); al añadir `orderType=autoventa` solo se devuelven sus autoventas.

---

## 4. Validaciones (backend)

- **orderType = autoventa**: Solo usuarios con rol comercial y Salesperson asociado (si no, 422 con mensaje).
- Cliente: debe existir y, si el usuario es comercial, `customer.salesperson_id` debe ser el del usuario.
- **items**: al menos uno; cada uno con `productId` (exists tenant.products), `boxesCount` (entero ≥ 1), `totalWeight` (numérico ≥ 0), `unitPrice` (numérico ≥ 0). `tax` opcional (exists tenant.taxes).
- **boxes**: al menos una; cada una con `productId` (exists), `netWeight` (numérico ≥ 0.01). `lot`, `gs1128`, `grossWeight` opcionales.
- **invoiceRequired**: booleano obligatorio cuando `orderType = autoventa`; **observations**: opcional, max 1000 caracteres.

---

## 5. Archivos creados/modificados

| Acción   | Archivo |
|----------|---------|
| Crear    | [database/migrations/companies/2026_02_18_120000_add_order_type_to_orders_table.php](database/migrations/companies/2026_02_18_120000_add_order_type_to_orders_table.php) |
| Crear    | [app/Services/v2/AutoventaStoreService.php](app/Services/v2/AutoventaStoreService.php) |
| Modificar| [app/Models/Order.php](app/Models/Order.php) (fillable, constantes, scope, accessor) |
| Modificar| [app/Http/Requests/v2/StoreOrderRequest.php](app/Http/Requests/v2/StoreOrderRequest.php) (orderType, items, boxes, invoiceRequired, observations) |
| Modificar| [app/Services/v2/OrderStoreService.php](app/Services/v2/OrderStoreService.php) (delegación a AutoventaStoreService; order_type en create estándar) |
| Modificar| [app/Http/Controllers/v2/OrderController.php](app/Http/Controllers/v2/OrderController.php) (restricción autoventa a rol comercial) |
| Modificar| [app/Services/v2/OrderListService.php](app/Services/v2/OrderListService.php) (filtro orderType) |
| Modificar| [app/Http/Requests/v2/IndexOrderRequest.php](app/Http/Requests/v2/IndexOrderRequest.php) (orderType en rules) |
| Modificar| [app/Http/Resources/v2/OrderResource.php](app/Http/Resources/v2/OrderResource.php) (orderType en respuesta) |
| Modificar| [app/Http/Resources/v2/OrderDetailsResource.php](app/Http/Resources/v2/OrderDetailsResource.php) (orderType en respuesta) |
| Modificar| [docs/authorization/01-rol-comercial.md](docs/authorization/01-rol-comercial.md) (sección 3.1.11 Autoventas) |

---

## 6. Referencias

- Especificación funcional: [docs/por-hacer/69-autoventa-rol-comercial-especificacion.md](docs/por-hacer/69-autoventa-rol-comercial-especificacion.md).
- Restricciones rol comercial: [docs/authorization/01-rol-comercial.md](docs/authorization/01-rol-comercial.md).
- PalletWriteService (creación de cajas): [app/Services/v2/PalletWriteService.php](app/Services/v2/PalletWriteService.php).
- Pallet estados: [app/Models/Pallet.php](app/Models/Pallet.php) (STATE_SHIPPED, changeToShipped).
