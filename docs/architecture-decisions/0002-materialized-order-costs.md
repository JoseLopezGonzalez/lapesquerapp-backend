# ADR-0002: Costes de pedido — resolución dinámica vs. tabla materializada

**Estado**: Pendiente de implementar  
**Contexto**: Endpoints de estadísticas de rentabilidad (`profitability-summary`, `profitability-products`)

---

## Situación actual

El coste de cada caja se resuelve en tiempo de ejecución a través de `ProductionCostResolver` (servicio scoped por request). La cadena de resolución es:

```
Order → Pallets → PalletBoxes → Box → ProductionCostResolver
                                         ├─ vía recepción: RawMaterialReceptionProduct.price
                                         └─ vía producción: ProductionOutput → ProductionCost → cálculo ponderado
```

Este mecanismo funciona correctamente en endpoints de **pedido individual** (detail, cost-analysis) porque el número de cajas a resolver es acotado (~20–200 cajas por pedido).

Para los endpoints de **estadísticas de rango de fechas**, la situación es diferente: un rango de 3 meses puede abarcar 100+ pedidos × 50 palets × 20 cajas = **100.000 resoluciones de coste en una sola petición**, lo que hace inviable la ejecución en tiempo real a escala.

---

## Solución futura: tabla `order_cost_snapshots`

Añadir una tabla en la BD del tenant que almacene el coste total calculado por pedido, actualizándola cada vez que cambia algún dato que afecta al coste.

### Esquema propuesto

```sql
CREATE TABLE order_cost_snapshots (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id        BIGINT UNSIGNED NOT NULL,
    total_cost      DECIMAL(12, 4)  NULL,       -- null = sin coste calculable
    computed_at     TIMESTAMP       NOT NULL,
    UNIQUE KEY uq_order (order_id),
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);
```

O alternativamente una columna directa en `orders`:

```sql
ALTER TABLE orders
    ADD COLUMN total_cost      DECIMAL(12, 4) NULL,
    ADD COLUMN cost_computed_at TIMESTAMP     NULL;
```

### Cuándo actualizar el snapshot

El coste de un pedido cambia cuando cambia cualquiera de los siguientes datos:

| Evento | Entidad afectada |
|--------|-----------------|
| Se vincula/desvincula un palet al pedido | `Pallet.order_id` |
| Se añade/elimina una caja de un palet | `PalletBox` |
| Cambia el precio de la recepción de materia prima | `RawMaterialReceptionProduct.price` |
| Se registran o modifican costes de producción | `ProductionCost` |
| Cambia el `lot` o el `article_id` de una caja | `Box` |
| Se resuelve un output de producción que afecta al lote | `ProductionOutput` |

Todos estos eventos disparan la recalculación del `total_cost` del pedido afectado usando el `ProductionCostResolver` existente.

### Estrategia de recalculación

- **Síncrona** (simple): recalcular al guardar en los eventos listados. Válido si el volumen de pedidos activos es moderado.
- **Asíncrona** (escalable): encolar un job `RecalculateOrderCost` que se procesa en background. El job recibe `order_id` + tenant ID y recalcula usando `ProductionCostResolver`.

### Impacto en los endpoints de stats

Con `total_cost` almacenado, los endpoints de estadísticas se convierten en consultas SQL puras:

```sql
SELECT SUM(subtotal_amount) AS revenue, SUM(total_cost) AS cost
FROM orders
WHERE load_date BETWEEN :from AND :to
  AND total_cost IS NOT NULL;
```

Sin iteración PHP ni resolución dinámica de costes.

---

## Estado actual de los endpoints

Los endpoints de estadísticas de rentabilidad están implementados con resolución dinámica (sin snapshot). Son funcionales para rangos cortos (hasta ~30-60 días con volumen normal). Para rangos más amplios pueden ser lentos.

Cuando se implemente la tabla materializada, `OrderProfitabilityStatsService` deberá refactorizarse para leer de `orders.total_cost` en lugar de calcular caja a caja.

---

**Fecha de registro**: 2026-04-21
