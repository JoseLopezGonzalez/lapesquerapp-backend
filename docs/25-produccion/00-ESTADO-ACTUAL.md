# Producción — Estado actual del módulo

**Última actualización:** 2026-02-16

Resumen ejecutivo del estado del módulo de producción (API v2, estructura relacional).

---

## Estructura de entidades

- **Production:** cabecera del lote (productions).
- **ProductionRecord:** procesos individuales en árbol jerárquico (production_records); relación padre-hijo por parent_record_id.
- **ProductionInput:** entradas (cajas consumidas) por proceso (production_inputs).
- **ProductionOutput:** salidas (productos producidos) por proceso (production_outputs).
- **ProductionOutputConsumption:** consumos de salidas de un proceso padre por procesos hijos (production_output_consumptions).

Trazabilidad a nivel de caja; integridad referencial en BD.

---

## Production tree (versión vigente)

- **Versión vigente:** v5 con conciliación (nodos venta/stock, nodos re-procesados y faltantes, conciliación detallada por productos).
- Endpoint de árbol: ver documentación del GET que devuelve el tree (process-tree v5).
- Ejemplo de respuesta: `docs/32-ejemplos/EJEMPLO-RESPUESTA-process-tree-v5-con-conciliacion.md`.

---

## Costes y trazabilidad

- Costes de producción: ProductionCost, CostCatalog; documentación en docs de referencia del módulo.
- Trazabilidad: inputs/outputs/consumptions en ProductionRecord; documentación frontend/trazabilidad en este mismo directorio y en frontend/.

---

## Documentos de referencia vigentes

| Documento | Contenido |
|-----------|-----------|
| [10-Produccion-General.md](10-Produccion-General.md) | Visión general, arquitectura, entidades |
| [11-Produccion-Lotes.md](11-Produccion-Lotes.md) | Cabecera Production, lotes |
| [12-Produccion-Procesos.md](12-Produccion-Procesos.md) | ProductionRecord, árbol de procesos |
| [12-Produccion-Procesos-ENDPOINT-GET.md](12-Produccion-Procesos-ENDPOINT-GET.md) | Endpoint GET del árbol |
| [13-Produccion-Entradas.md](13-Produccion-Entradas.md) | ProductionInput |
| [14-Produccion-Salidas.md](14-Produccion-Salidas.md) | ProductionOutput |
| [15-Produccion-Consumos-Outputs-Padre.md](15-Produccion-Consumos-Outputs-Padre.md) | Consumos de salidas del padre |
| [ENDPOINT-Available-Products-For-Outputs.md](ENDPOINT-Available-Products-For-Outputs.md) | Productos disponibles para salidas |

Propuestas, análisis e implementaciones ya aplicadas están en `_archivo/`, `analisis/_archivo/` y `cambios/_archivo/`.
