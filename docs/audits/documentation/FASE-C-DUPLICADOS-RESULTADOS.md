# FASE C — Duplicados: resultados

**Fecha:** 2026-02-16  
**Plan:** PLAN-PENDIENTES-DOCUMENTACION.md

---

## C.1 Ámbitos revisados

### 22–28 (dominio) vs api-references

| Dominio | docs/22-28 (ej. pedidos) | docs/api-references (ej. pedidos/README) | Solapamiento | Decisión |
|---------|-----------------------------|--------------------------------------------|---------------|----------|
| Pedidos | Visión general, entidades, flujo, estados (20-Pedidos-General, 21–24) | Endpoints: método, URL, headers, params, request/response (tipo Swagger) | No duplican: 22 = negocio/contexto, 31 = contrato API | Sin consolidar. Canónico dominio: 22–28; canónico API: 31. Enlace cruzado añadido. |
| Resto módulos | Igual: visión, conceptos, flujos | Igual: endpoints por recurso | Mismo patrón | Sin consolidar. |

**Conclusión:** No hay contenido duplicado; son complementarios (dominio vs referencia de endpoints). Se mantienen ambos con enlaces claros.

### Enlaces corregidos en api-references/README.md

- Los enlaces de "Estructura" apuntaban a `./pedidos/README.md`, `./inventario/README.md`, etc., que **no existen** dentro de 31 (las subcarpetas se llaman `pedidos/`, `inventario/`, etc.). Corregidos a `./pedidos/README.md`, `./inventario/README.md`, etc.
- Añadida línea: "Visión general y contexto de negocio: pedidos, inventario, ..." con enlaces a las carpetas de dominio.
- Referencia "Fundamentos" apuntaba a `../fundamentos/README.md` (no existe). Corregida a `../fundamentos/00-Introduccion.md`.

### Ejemplos (ejemplos) vs ejemplos embebidos

- ejemplos contiene ejemplos de respuesta JSON (p. ej. process-tree v5, pallet). Los docs de produccion y pedidos incluyen a veces ejemplos inline. No se detectó duplicación literal; se mantiene ejemplos como referencia concentrada. Sin acción.

---

## C.2 Resumen

| Acción | Estado |
|--------|--------|
| Duplicación 22–28 vs 31 | No hay duplicación; roles distintos (dominio vs API). |
| Enlaces rotos api-references/README | Corregidos (rutas a pedidos/, inventario/, ...; fundamentos → 00-Introduccion). |
| Enlace dominio ↔ API | Añadido en api-references/README. |
| ejemplos vs embebidos | Sin consolidación; 32 sigue como referencia. |

**Entregable:** Este informe; api-references/README.md con enlaces corregidos y referencia a docs de dominio.
