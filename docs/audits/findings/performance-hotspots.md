# Hotspots de Rendimiento y Escalabilidad — PesquerApp Backend

**Fecha**: 2026-03-23

---

## Hotspots observados

1. **Fichajes**
   - `PunchController` concentra demasiadas rutas y caminos.
   - Dashboard, calendario, estadísticas y bulk merecen medición específica.

2. **Estadísticas e informes**
   - Rankings, totales y chart endpoints son candidatos naturales a cuellos de botella.
   - Los gaps actuales son más de índice/medición que de diseño catastrófico.

3. **Documentos y exportes**
   - PDFs y Excel siguen siendo síncronos.
   - Si la carga crece, serán la primera superficie donde valorar cola y límites operativos.

4. **Producción**
   - Árboles, reconciliación, costes y trazabilidad siguen siendo el bloque más sensible del core.

5. **Conmutación tenant por request**
   - `DB::purge()` + `DB::reconnect()` es correcto en este modelo, pero debe vigilarse bajo mayor concurrencia.

## Señales de mitigación ya presentes

- Servicios dedicados a listados y estadísticas.
- Uso frecuente de eager loading.
- Redis y worker disponibles en entorno Docker.

## Prioridad

- **P1**: estadísticas, fichajes, producción, documentos
- **P2**: revisar índices y contratos de paginación/filtros

## Conclusión

El backend no muestra una crisis de rendimiento evidente desde código estático, pero sí varios caminos donde hace falta medición antes de declarar madurez `9/10`.
