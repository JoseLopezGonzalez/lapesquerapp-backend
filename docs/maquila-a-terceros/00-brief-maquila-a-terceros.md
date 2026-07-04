# Brief — Maquila a Terceros (Toll Processing)

**Fecha:** 2026-07-04
**Estado:** Borrador en elaboración — Fase de análisis (STEP 0a/STEP 0 del workflow de evolución), pendiente de aprobación explícita antes de pasar a implementación (STEP 2/3)
**Ámbito:** Nuevo bloque funcional completo — recepción de materia prima de terceros, integración con producción, expedición y facturación del servicio

---

## 1. Resumen ejecutivo

Algunos tenants, además de su operativa habitual (recepción de materia prima propia, producción, ventas, despachos de cebo), prestan un **servicio de maquila a terceros**: reciben materia prima que pertenece a un cliente externo (nunca pasa a ser propiedad del tenant), la procesan siguiendo el mismo patrón de producción ya existente, y finalmente la expiden de vuelta a ese cliente (o al destino que este indique). El tenant no compra ni vende la mercancía: **factura únicamente el servicio de transformación**.

Circuito objetivo:

```
Cliente externo (TollClient)
   │  aporta materia prima (nunca es del tenant)
   ▼
TollReception  ──►  Pallet/Box (owner = TollClient)
   │
   ▼
Production / ProductionRecord / ProductionInput / ProductionOutput   (reutilizado tal cual)
   │
   ▼
TollOrder (Order.order_type = TOLLING)  ──►  expedición al cliente o a quien él indique
   │
   ▼
TollTariff → importe del servicio (no de la mercancía)
```

### Advertencia de nomenclatura — no confundir con lo ya existente

El proyecto **ya tiene** un concepto de "maquila" implementado, pero en la **dirección inversa**: `ExternalProcessor` (alias de negocio "Maquilador") es una empresa externa a la que el **tenant** envía **su propio** producto para que lo transforme y se lo devuelva (ver `docs/catalogos/55-transformadores-externos-maquiladores.md`, `Order.external_processor_id`, `Order.maquilador_destination`, `ExternalUser::TYPE_MAQUILADOR`).

Este bloque nuevo es el **rol opuesto**: aquí **el tenant ES el maquilador** — presta el servicio, no lo contrata. Para evitar cualquier colisión conceptual o léxica en código y documentación:

- **Nomenclatura técnica (código, en inglés):** prefijo `Toll*` — `TollClient`, `TollReception`, `TollTariff`; `Order.order_type = 'tolling'`.
- **Nomenclatura funcional (UI, en español):** "Maquila a terceros" / "Cliente de maquila" / "Recepción de maquila" / "Expedición de maquila" / "Tarifa de maquila".
- La palabra suelta "maquilador" en la UI queda reservada, cuando haga falta desambiguar, al flujo existente (`ExternalProcessor`). En este bloque se usará siempre "cliente de maquila" para referirse al tercero.

---

## 2. Comparativa con entidades existentes

| Entidad existente | Rol | ¿Sirve para el cliente de maquila? |
|---|---|---|
| `Customer` | El tenant le **vende** producto propio | No: asume venta de mercancía propia con precio/impuestos |
| `Supplier` | El tenant le **compra** materia prima | No: asume compra con precio y liquidación al proveedor |
| `ExternalProcessor` (Maquilador) | Tercero que transforma **producto del tenant** por encargo del tenant | No: es el rol inverso (el tenant es cliente de ese tercero, no al revés) |
| `ExternalUser` (`type=maquilador`) | Usuario con login que opera en nombre del `ExternalProcessor` | No aplica: es un actor de login, no una empresa |
| **`TollClient` (nuevo)** | Empresa que aporta materia prima propia al tenant para que este la procese y se la devuelva | ✅ Es la entidad a crear |

Ya existe precedente directo de este mismo dilema (documentado en `docs/catalogos/55-...md` §3): *"Puede que una misma empresa sea ambas cosas en la realidad, pero en el ERP conviene mantener roles separados"*. Se aplica el mismo criterio aquí: `TollClient` es un catálogo propio, independiente de `Customer`/`Supplier`/`ExternalProcessor`, aunque en la realidad una misma empresa pueda tener varios roles dados de alta por separado.

---

## 3. Decisiones tomadas (resumen de respuestas)

| # | Decisión | Elegido |
|---|---|---|
| 1 | Nomenclatura técnica | Prefijo `Toll*` en inglés |
| 2 | Dónde vive "esta mercancía no es del tenant" | Campo owner (`toll_client_id`) directo en `Pallet`/`Box` |
| 3 | Entidad para el tercero que aporta materia prima | Nueva entidad dedicada `TollClient` |
| 4 | Registro de entrada de materia prima ajena | Nueva entidad `TollReception` (no se extiende `RawMaterialReception`) |
| 5 | Árbol de producción | Se reutiliza `Production`/`ProductionRecord`/`ProductionInput`/`ProductionOutput` tal cual, sin duplicar motor |
| 6 | Expedición de vuelta al cliente | Nuevo `Order.order_type = TOLLING` (reutiliza `Order`, transporte, CMR, letreros, ciclo de vida de `Pallet`) |
| 7 | Flexibilidad de destino de envío | Sí, destino distinto al propio `TollClient` (igual que `maquilador_destination`/`loading_address`) |
| 8 | Valor económico de la mercancía en la expedición | Sin precio/impuesto en la mercancía; solo se factura el servicio aparte |
| 9 | Alcance de facturación del servicio | Tarifas por `TollClient`/proceso (`TollTariff`), reutilizando el mecanismo de `CostCatalog`/`ProductionCost` |
| 10 | Acceso externo (portal) para el cliente de maquila | No en esta fase (diseño no lo bloquea para el futuro) |
| 11 | Referencia/lote propio del cliente | Sí, se guarda junto al lote interno |
| 12 | Datos legales/sanitarios del `TollClient` | Sí, igual que `ExternalProcessor` (CIF, registro sanitario, dirección fiscal, contacto) |
| 13 | Stock de terceros en informes de valorización | Excluido por defecto de los totales propios; visible en un filtro/vista específica |
| 14 | Alcance de la primera versión | Fase 1 completa: recepción + producción + expedición + tarifas |
| 15 | Organización en frontend | Módulo de menú propio "Maquila" |

---

## 4. Modelo de datos propuesto

> Descripción funcional de entidades y campos — sin código. Los nombres de columna son orientativos, a fijar en STEP 3.

### 4.1 `TollClient` (catálogo)

Inspirado directamente en `ExternalProcessor` (mismo nivel de completitud, mismos campos legales/sanitarios):

- `name`, `legal_name`, `vat_number` (único)
- `sanitary_registration_number`
- `contact_person`, `phone`, `emails`
- `address`, `city`, `postal_code`, `province`, `country_id`
- `is_active`, `notes`

### 4.2 `TollReception` (+ `TollReceptionProduct`)

Espejo de `RawMaterialReception`/`RawMaterialReceptionProduct`, pero **sin** `supplier_id` ni `price` (no hay compra):

- `TollReception`: `toll_client_id` (FK, obligatorio), `date`, `notes`, `client_reference` (referencia/pedido/lote propio del cliente), `declared_total_net_weight` (peso declarado, sin implicar valor de compra), `creation_mode` (`lines`|`pallets`, igual que hoy)
- `TollReceptionProduct`: `reception_id`, `product_id`, `net_weight`, `client_reference`/`lot` (si el cliente distingue lotes dentro de la misma recepción)

Genera `Pallet`/`Box` directamente, igual que `RawMaterialReceptionWriteService` hoy (mismo patrón de servicio, adaptado).

### 4.3 Cambios en `Pallet` / `Box` (núcleo de stock)

- Añadir `toll_client_id` (FK nullable) — marca de propiedad. `NULL` = stock propio del tenant (comportamiento actual sin cambios).
- `Pallet` necesita poder originarse desde `TollReception` además de `RawMaterialReception` (hoy `reception_id` apunta solo a `raw_material_receptions`); a resolver en STEP 3 si se añade `toll_reception_id` en paralelo o se generaliza el origen.
- **Propagación en producción:** cuando un `ProductionOutput` se genera a partir de `ProductionInput`/`ProductionOutputConsumption` de cajas con `toll_client_id`, las cajas de salida heredan el mismo `toll_client_id`. Regla a confirmar (ver §6 Puntos abiertos): qué ocurre si un proceso mezcla cajas propias y de un `TollClient` a la vez.

### 4.4 `Order` (reutilizado para la expedición)

- Nueva constante `ORDER_TYPE_TOLLING` (junto a `ORDER_TYPE_STANDARD`/`ORDER_TYPE_AUTOVENTA` ya existentes).
- Nuevo `toll_client_id` (FK nullable) — coexiste con `customer_id` igual que hoy coexisten `customer_id` y `external_processor_id`; se rellena cuando `order_type = TOLLING`.
- Reutilizar el patrón ya existente `maquilador_destination`/`loading_address` (o equivalente) para permitir que el envío vaya a una dirección distinta al `TollClient` registrado.
- Líneas de producto sin `unit_price`/impuestos cuando `order_type = TOLLING` (mercancía sin valor de venta).

### 4.5 `TollTariff` (facturación del servicio)

- `toll_client_id` (FK), `process_id` (nullable, FK a `Process`), `product_id`/`species_id` (nullable, para tarifas específicas), `cost_catalog_id` (nullable, reutiliza `CostCatalog` para el tipo de coste)
- `unit` (`total`|`per_kg`, igual que `CostCatalog.default_unit`), `rate`
- Cálculo del importe facturable: mismo mecanismo que `ProductionCost.getEffectiveTotalCostAttribute()` (tarifa × peso de output), aplicado sobre los procesos/lotes de un `TollClient` concreto.
- El resultado es un **importe de servicio calculado**, no una factura contable formal (el ERP no tiene hoy módulo de facturación más allá de PDFs/documentos); se expone en `TollOrder`/cierre de `Production` para que el tenant emita el documento fuera o mediante un futuro documento PDF análogo a los ya existentes.

---

## 5. Reglas de negocio a heredar del resto del ERP (sin cambios, por convención del proyecto)

- Multi-tenant: todo lo anterior vive en la conexión `tenant`, con `UsesTenantConnection`, sin excepciones.
- Validaciones `exists:tenant.toll_clients,id`, etc., en los nuevos Form Requests.
- Transacciones (`DB::transaction()`) en creación/actualización de `TollReception` y `TollOrder`, igual que `RawMaterialReceptionController`/`OrderController`.
- Políticas (`TollClientPolicy`, `TollReceptionPolicy`) siguiendo el patrón ya usado: CRUD abierto a los roles operativos, borrado restringido a `administrador`/`tecnico`.
- Trazabilidad a nivel de caja: no se rompe ni se duplica: `Box` sigue siendo la unidad mínima, ahora con marca de propiedad opcional.
- Reportes de stock/valorización existentes: excluyen por defecto `Pallet`/`Box` con `toll_client_id` no nulo de los totales de valor propio; se añade una vista/filtro "Stock de maquila" para consultarlo aparte.

---

## 6. Puntos abiertos — a confirmar antes de pasar a STEP 3

Estos matices surgieron durante el análisis y conviene cerrarlos explícitamente en esta misma iteración del brief:

1. **Producción mixta (propio + terceros):** ¿se permite que un mismo `ProductionRecord` consuma a la vez cajas propias y cajas de un `TollClient`? Recomendación: **no permitirlo** (un lote de producción es o 100% propio o 100% de un `TollClient` concreto), para que la propagación de `toll_client_id` a los outputs sea inequívoca y no haya que prorratear propiedad dentro de una misma caja de salida.
2. **Liquidación periódica del servicio:** ¿conviene un `TollLiquidation` análogo a `SupplierLiquidation` (cierre de periodo con un `TollClient`, agrupando varias `TollReception`/`Production`/`TollOrder`)? Recomendación: sí, incluirlo en esta misma fase 1 por coherencia con el patrón ya usado en recepciones/despachos de cebo, ya que sin él no hay forma limpia de agrupar el importe de varias órdenes de un mismo cliente en un periodo.
3. **Origen de `Pallet` desde `TollReception`:** confirmar si se generaliza `Pallet.reception_id` (polimórfico) o se añade una columna paralela `toll_reception_id`. Recomendación: columna paralela nullable (más simple, sin migrar el comportamiento actual de `reception_id`).
4. **Stock de maquila y almacenamiento físico:** ¿se almacena en los mismos `Store` que el stock propio (compartiendo capacidad/posiciones) o se exige un `Store` dedicado a mercancía de terceros? Recomendación: mismos almacenes, ya que `StoredPallet`/`StoredBox` no necesitan cambios; basta con que los informes filtren por `toll_client_id`.

---

## 7. Plan de implementación (borrador, a detallar en STEP 3)

**Backend:**
1. Catálogo `TollClient` (modelo, migración, controller, requests, resource, policy) — patrón calcado de `ExternalProcessor`.
2. `TollReception`/`TollReceptionProduct` + servicio de escritura (adaptado de `RawMaterialReceptionWriteService`) + columna `toll_client_id` en `pallets`/`boxes`.
3. Propagación de `toll_client_id` en la generación de `ProductionOutput` (ajuste puntual en el servicio de producción, sin tocar la estructura del árbol).
4. `Order.order_type = TOLLING` + `toll_client_id` + reglas de líneas sin precio/impuesto + reutilización de documentos de transporte existentes.
5. `TollTariff` + cálculo de importe de servicio (reutilizando `ProductionCost`/`CostCatalog`).
6. (Si se confirma en §6.2) `TollLiquidation`.
7. Reportes de stock: filtro/exclusión por `toll_client_id`.

**Frontend:**
1. Nuevo apartado de menú "Maquila".
2. Pantalla catálogo de Clientes de maquila + tarifas.
3. Pantalla de Recepciones de maquila (reutilizando componentes de recepción de materia prima).
4. Producción: filtro/badge para lotes de terceros dentro de la pantalla de producción existente.
5. Pantalla de Expediciones de maquila (reutilizando pantalla de Pedidos, filtrada por `order_type=TOLLING`).
6. Vista de stock de maquila (separada de la valorización propia).

---

## 8. Referencias

- `docs/catalogos/55-transformadores-externos-maquiladores.md` (precedente directo, flujo inverso)
- `docs/frontend/external-processors-api.md`, `external-processors-relations.md`, `maquilador-orders-documents.md`
- `docs/recepciones-despachos/60-recepciones-materia-prima.md`, `61-despachos-cebo.md`
- `docs/produccion/00-estado-actual.md`
- `docs/pedidos/20-Pedidos-General.md`
- `docs/referencia/99-glosario.md`
- Modelos clave: `app/Models/{ExternalProcessor,Order,RawMaterialReception,RawMaterialReceptionProduct,Pallet,Box,Store,Production,ProductionRecord,ProductionInput,ProductionOutput,ProductionOutputSource,ProductionCost,CostCatalog,CeboDispatch,SupplierLiquidation,Customer,Supplier}.php`

---

**Siguiente paso:** revisar este brief (especialmente §6 Puntos abiertos) y, tras aprobación, avanzar a STEP 2 del workflow de evolución (diseño detallado de migraciones/endpoints) antes de tocar código.
