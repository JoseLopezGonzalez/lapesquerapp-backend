# Plan de mejoras: GET orders/active

Este documento describe el análisis de la implementación actual del endpoint **GET** `/api/v2/orders/active` y el plan de mejoras para **reducir tiempo y memoria** sin paginación (por lógica de negocio). Incluye la **mejora de índices en BD** y la **reducción de payload y relaciones** al mínimo necesario para la UI de tarjetas de pedidos activos.

**Estado:** Implementado (Mejora A + B). Ver §10 para contrato JSON y uso en frontend.

**Contexto de negocio:** No se puede paginar este listado. El frontend muestra tarjetas con: estado, número de pedido, nombre del cliente y fecha de carga (véase §1.2).

---

## 1. Implementación actual y necesidades del frontend

### 1.1 Controlador (v2)

**Archivo:** `app/Http/Controllers/v2/OrderController.php` → método `active()`.

```php
$orders = Order::with([
    'customer',
    'salesperson',
    'transport',
    'incoterm',
    'pallets.boxes.box.productionInputs',
    'pallets.boxes.box.product',
])
->where(function ($query) {
    $query->where('status', 'pending')
          ->orWhereDate('load_date', '>=', now());
})
->orderBy('load_date', 'desc')
->get();
return OrderResource::collection($orders);
```

- Una consulta con eager loading de todas esas relaciones.
- Filtro: `(status = 'pending' OR load_date >= today)`.
- Orden: `load_date DESC`.

**Problemas:** Se cargan pallets, cajas, productionInputs y product para calcular `totalNetWeight` y `totalBoxes`; además customer, salesperson, transport e incoterm completos. Con muchos pedidos activos la memoria y el tiempo crecen mucho.

### 1.2 Lo que realmente necesita el frontend (tarjeta de pedido activo)

Según la UI de referencia, cada tarjeta muestra:

- **Estado** (ej. "En producción") → `status` del pedido (el front puede mapear `pending` → "En producción").
- **Identificador** (#01967) → `id` del pedido; el front lo formatea (ej. # + id con 5 dígitos).
- **Cliente** (ej. "Clientes Varios") → nombre del cliente: `customer.name` (o alias).
- **Fecha de carga** (15/07/2025) → `loadDate` (load_date).

**Conclusión:** Para el listado de tarjetas no se necesitan totalNetWeight, totalBoxes, numberOfPallets, salesperson, transport, incoterm, buyerReference ni el objeto completo de customer. Tampoco hace falta cargar pallets ni cajas ni productionInputs ni product.

### 1.3 Recurso actual (OrderResource)

Devuelve: id, customer (toArrayAssoc completo), buyerReference, status, loadDate, salesperson, transport, pallets, totalBoxes, incoterm, totalNetWeight, subtotalAmount, totalAmount. Los accessors totalNetWeight y totalBoxes iteran sobre pallets/boxes/productionInputs; por eso el controlador carga esas relaciones profundas.

---

## 2. Mejoras propuestas

### 2.1 Resumen

- **Mejora A – Índices en BD:** Índice en `orders` para el filtro y orden de `active()`. Menor tiempo de consulta.
- **Mejora B – Payload y relaciones mínimas:** Nuevo recurso ligero solo con campos de la tarjeta; cargar solo `customer` con columnas mínimas; no cargar pallets, salesperson, transport, incoterm. Menos memoria y menos I/O.

No se implementa paginación (por requisito de negocio). No se unifica aquí con GET orders?active=true (el usuario lo eliminará más adelante).

---

## 3. Mejora A: Índices en base de datos

**Objetivo:** Asegurar que la consulta de `active()` (filtro por status y load_date, orden por load_date) use índices adecuados en la tabla `orders`.

### 3.1 Consulta actual sobre orders

- WHERE: `(status = 'pending' OR load_date >= today)`.
- ORDER BY: `load_date DESC`.

La tabla `orders` en la migración base no define índices explícitos sobre `status` ni `load_date`. Las FKs tienen índice por convención.

### 3.2 Índice recomendado

- **Recomendado:** Índice compuesto `(status, load_date)` en la tabla `orders`.
- Cubre filtro por estado y orden por fecha. Añadir en migración de tipo `companies`.

### 3.3 Tareas concretas (Mejora A)

1. Crear migración en `database/migrations/companies/` que compruebe si existe ya un índice que cubra status y load_date; si no, añadir `$table->index(['status', 'load_date'])` sobre `orders`. El `down()` debe eliminar ese índice.
2. Documentar el nombre del índice (p. ej. `orders_status_load_date_index`) en este documento.
3. No tocar índices de otras tablas (pallets, etc.); no afectan a active() si dejamos de cargar pallets.

### 3.4 Verificación

- Antes/después: ejecutar EXPLAIN de la query de active() y comprobar que se usa el nuevo índice.

---

## 4. Mejora B: Payload y relaciones mínimas

**Objetivo:** Devolver solo los datos necesarios para la tarjeta y cargar solo las relaciones necesarias.

### 4.1 Nuevo recurso (recomendado)

Crear **ActiveOrderCardResource** (o nombre similar) que devuelva solo:

- `id` — Identificador.
- Solo `id` (el front formatea para mostrar, ej. #01967).
- `status` — Estado del pedido.
- `customer` — Objeto `{ id, name }` (opcionalmente alias). No usar Customer::toArrayAssoc() completo.
- `loadDate` — Fecha de carga.

### 4.2 Cambios en el controlador active()

1. Relaciones: cargar solo `customer` con select mínimo: p. ej. `'customer' => fn($q) => $q->select('id','name')`.
2. Eliminar del with(): salesperson, transport, incoterm, pallets y toda la cadena boxes/productionInputs/product.
3. Opcional: Order::select('id','status','load_date','customer_id') para reducir columnas (incluir siempre la FK customer_id para que la relación customer se resuelva).
4. Respuesta: ActiveOrderCardResource::collection($orders).

### 4.3 Compatibilidad con el frontend

- Si el front ya usa solo id, status, customer.name, loadDate: no hay breaking change.
- Si el front usa totalNetWeight, totalBoxes, salesperson, transport, incoterm en la lista: actualizar el front para usar solo los campos del nuevo recurso; datos completos siguen en GET orders/{id}.

### 4.4 Tareas concretas (Mejora B)

1. Crear `app/Http/Resources/v2/ActiveOrderCardResource.php` con los campos indicados.
2. Modificar OrderController::active(): with() mínimo (solo customer con id,name), opcional select en Order, devolver ActiveOrderCardResource::collection($orders).
3. Comprobar que ningún otro cliente asume OrderResource completo; documentar cambio de contrato y actualizar tests si aplica.

---

## 5. Orden de implementación recomendado

1. **Primero Mejora B** (recurso + relaciones mínimas): reduce memoria y tiempo de inmediato; validar con frontend.
2. **Después Mejora A** (índices): migración en orders(status, load_date).

---

## 6. Archivos a tocar (resumen)

- **Mejora A:** Nueva migración en `database/migrations/companies/` para índice en `orders(status, load_date)`.
- **Mejora B:** `app/Http/Controllers/v2/OrderController.php` (método active()), nuevo `app/Http/Resources/v2/ActiveOrderCardResource.php`.

---

## 7. Criterios de aceptación

- **Mejora A:** La query del listado activo usa un índice que involucra status y load_date (EXPLAIN). Migración reversible e idempotente si se desea.
- **Mejora B:** GET orders/active devuelve objetos con id, status, customer (id, name), loadDate. No se cargan pallets ni totales. Memoria y tiempo reducidos.
- **Frontend:** Las tarjetas se muestran correctamente con los datos del nuevo recurso.

---

## 8. Riesgos y consideraciones

- **Cambio de contrato:** Quien espere todos los campos de OrderResource dejará de recibirlos. Documentar nuevo contrato y coordinar con frontend.
- **Índice:** En bases con poco volumen la ganancia puede ser pequeña; en listados grandes el beneficio es mayor.
- **Tenant:** La migración debe ejecutarse en contexto de migraciones companies.

---

## 9. Referencias

- 100-Rendimiento-Endpoints.md — GET orders/active clasificado como Alto tiempo/memoria.
- 101-Plan-Mejoras-GET-orders-id.md — Patrón de mejoras (select, relaciones, índices).
- Migración índices existente: 2025_12_12_100000_ensure_indexes_for_orders_show_path.php (no incluye tabla orders; este plan añade el índice para el listado activo).
- Migración añadida: 2026_02_07_120000_add_orders_status_load_date_index_for_active_list.php (índice `orders_status_load_date_index`).

---

## 10. Contrato JSON para el frontend (GET orders/active)

Tras la implementación, la respuesta es un objeto con clave `data` que contiene un array de tarjetas. Cada elemento tiene esta forma:

```json
{
  "data": [
    {
      "id": 1967,
      "status": "pending",
      "customer": {
        "id": 42,
        "name": "Clientes Varios"
      },
      "loadDate": "2025-07-15"
    }
  ]
}
```

**Campos por tarjeta:**

| Campo        | Tipo     | Uso en la tarjeta                          |
|-------------|----------|--------------------------------------------|
| `id`        | number   | Identificador del pedido; enlaces al detalle. El front puede formatear para mostrar (ej. #01967). |
| `status`    | string   | `"pending"` \| `"finished"` \| `"incident"`. El front puede mapear `pending` → "En producción". |
| `customer`  | object \| null | `{ id, name }`. Si el pedido no tiene cliente, `null`. |
| `loadDate`  | string   | Fecha de carga (YYYY-MM-DD). Formatear en locale (ej. 15/07/2025). |

**Ejemplo de mapeo en el front:**

- **Estado (badge):** `status === 'pending' ? 'En producción' : status === 'incident' ? 'Incidente' : 'Finalizado'` (o usar un mapa por idioma).
- **Número de pedido:** formatear `id` (ej. `#${String(id).padStart(5, '0')}`).
- **Cliente:** `customer?.name ?? '—'`.
- **Fecha de carga:** formatear `loadDate` con Intl o dayjs (ej. `new Date(loadDate).toLocaleDateString('es-ES')`).
