# Prompt Maestro — Implementacion de Mejoras por Bloques hasta 9/10

## Objetivo

Actua como **Lead Engineer de estabilizacion y consolidacion** sobre el backend Laravel de PesquerApp. Tu mision es **ejecutar mejoras bloque por bloque** a partir de la auditoria principal, con el objetivo de dejar el sistema en un estado **9/10 o 10/10** en calidad de codigo, coherencia de negocio, seguridad, rendimiento y readiness multi-tenant.

Este prompt no es para analizar solamente. Es para **planificar, implementar, validar, documentar y cerrar** mejoras reales con un enfoque incremental y seguro.

---

## Dependencia obligatoria

Antes de empezar, debes tomar como referencia:

- la auditoria principal vigente del backend
- `docs/core-consolidation-plan-erp-saas.md` como registro maestro de bloques y puntuaciones
- los hallazgos de rendimiento
- el estado actual del repo

Si falta la auditoria principal, debes generarla o reconstruir su estado minimo antes de continuar.

---

## Principio operativo

No intentes arreglar todo a la vez. Debes trabajar **por bloques funcionales**, dejando cada bloque en estado profesional antes de pasar al siguiente.

Cada bloque debe salir con:

- logica coherente
- responsabilidades bien separadas
- permisos correctos
- consultas razonables
- tests suficientes
- documentacion minima actualizada
- nota objetivo de `9/10` o superior

---

## Resultado final esperado

Cuando termines, el proyecto debe tener:

- core estable y predecible
- deuda tecnica priorizada o resuelta
- backend consistente entre modulos
- rendimiento controlado
- seguridad tenant-aware
- tests utiles en flujos criticos
- documentacion tecnica viva
- una matriz clara de bloques completados y bloques pendientes

---

## Orden de trabajo obligatorio

### Fase 0. Preparacion

1. Lee la auditoria principal.
2. Construye una matriz de bloques con:
   - nota actual
   - impacto en negocio
   - riesgo tecnico
   - complejidad
   - dependencias
3. Define el orden de ejecucion.
4. Empieza por bloques:
   - de alto impacto
   - bajo o medio riesgo
   - con capacidad de arrastre positivo sobre otros modulos

### Fase 1. Auditoria operativa del bloque

Antes de tocar el bloque:

- identifica entidades, controladores, rutas, policies, requests, services, resources y tests
- resume reglas de negocio reales
- localiza deuda tecnica repetida
- detecta riesgos de seguridad y multi-tenant
- detecta problemas de rendimiento

### Fase 2. Definicion del objetivo del bloque

Para cada bloque, define explicitamente:

- que significa dejarlo en 9/10
- que problemas impiden esa nota
- que quick wins caben
- que cambios estructurales necesitas
- que no vas a tocar todavia para no romper otras areas

### Fase 3. Implementacion

Aplica mejoras en este orden:

1. seguridad y aislamiento tenant
2. permisos y autorizacion
3. consistencia de reglas de negocio
4. estructura del codigo
5. rendimiento
6. tests
7. documentacion del bloque

### Fase 4. Validacion

Tras cada bloque:

- ejecuta tests relevantes
- valida regresiones del flujo critico
- revisa impacto en otros modulos
- mide mejora si hubo cambios de rendimiento
- actualiza nota del bloque

### Fase 5. Cierre del bloque

No cierres un bloque sin dejar:

- resumen de cambios
- riesgos residuales
- rollback mental o tecnico si aplica
- nota antes/despues
- checklist de validacion

---

## Bloques funcionales de referencia

Trabaja como minimo sobre:

1. Auth + Roles/Permisos
2. Ventas / Pedidos
3. Inventario / Stock
4. Recepciones de Materia Prima
5. Despachos de Cebo
6. Produccion
7. Productos y maestros anidados
8. Catalogos transaccionales
9. Proveedores + Liquidaciones
10. Etiquetas
11. Fichajes / Control horario
12. Configuracion por tenant
13. Sistema / sesiones / logs / usuarios
14. Estadisticas e informes
15. Documentos y utilidades
16. Infraestructura API

Si el repo revela bloques mas adecuados o una agrupacion mas profesional, adapta el plan y justificas el cambio.

---

## Checklist obligatorio por bloque

### Calidad de codigo

- controllers finos
- validacion en `FormRequest`
- logica de negocio en services o actions coherentes
- ausencia de duplicacion relevante
- nombres y responsabilidades claros
- resources o DTOs consistentes

### Negocio

- estados definidos
- transiciones claras
- totales consistentes
- side effects controlados
- reglas concentradas en pocos puntos fiables

### Seguridad

- policies correctas
- authorize consistente
- aislamiento tenant respetado
- endpoints sensibles revisados

### Rendimiento

- N+1 eliminados
- eager loading correcto
- queries pesadas revisadas
- indices sugeridos o implementados cuando toque
- serializacion y paginacion razonables
- cache solo si aporta valor y es tenant-aware

### Testing

- tests de permisos
- tests de estados
- tests de edge cases
- tests de regresion del flujo principal

### Documentacion

- reglas del bloque actualizadas
- endpoints o contratos aclarados si cambiaron
- notas de despliegue si hubo impacto

---

## Criterios para considerar un bloque en 9/10

Un bloque solo puede darse por cerrado en `9/10` si:

- no tiene incoherencias graves de negocio
- no presenta riesgos claros de seguridad
- no depende de controladores gigantes o logica dispersa
- tiene validacion y autorizacion consistentes
- el rendimiento es razonable para su carga esperable
- dispone de tests utiles de regresion
- la deuda restante es localizada y no estructural

Para `10/10`, ademas:

- el diseño es especialmente limpio y estable
- la evolucion futura esta bien encaminada
- el bloque es un modelo replicable para otros modulos

---

## Prioridad de mejoras

Clasifica las mejoras de cada bloque en:

- `P0`: seguridad, fugas tenant, corrupcion de datos, roturas graves
- `P1`: permisos, reglas de negocio inconsistentes, deuda estructural fuerte
- `P2`: rendimiento, simplificacion, consolidacion, cobertura de tests
- `P3`: pulido, naming, limpieza menor, documentacion adicional

---

## Matriz de implementacion obligatoria

Manten una tabla viva con:

- bloque
- nota inicial
- nota objetivo
- prioridad
- estado
- dependencias
- riesgos
- quick wins
- cambios estructurales
- tests pendientes

La **fuente de verdad obligatoria** para esta matriz es:

- `docs/core-consolidation-plan-erp-saas.md` en su anexo de bloques y puntuaciones

Si generas un scoreboard auxiliar, debe reflejar exactamente los mismos datos, pero nunca sustituir ese documento.

---

## Entregables recomendados

Genera y manten actualizados, cuando aporten valor:

- `docs/audits/block-improvement-roadmap.md`
- `docs/audits/block-scoreboard.md` como vista auxiliar, nunca como fuente principal
- `docs/audits/block-validation-checklist.md`
- `docs/audits/performance-follow-up.md`
- `docs/audits/security-follow-up.md`
- `docs/audits/evolution-log-implementation.md`

Si el repo ya tiene documentos equivalentes, reutilizalos en vez de duplicar.

---

## Reglas de seguridad para implementar

- Nunca comprometas aislamiento tenant por optimizar.
- Cualquier cache debe incluir namespace tenant.
- Cualquier cambio en jobs, colas o eventos debe preservar contexto tenant.
- Cambios de autorizacion deben cubrirse con tests.
- Cambios de rendimiento deben medirse y documentarse.
- Cambios de riesgo alto deben hacerse en pasos pequenos y verificables.

---

## Estrategia de ejecucion recomendada

### Ola 1. Quick wins de alto impacto

- autorizaciones faltantes
- validaciones fuera de lugar
- N+1 obvios
- controladores sobredimensionados faciles de adelgazar
- tests de regresion indispensables

### Ola 2. Consolidacion estructural

- mover logica a services o actions
- unificar reglas de estados
- centralizar side effects
- ordenar resources, policies y requests

### Ola 3. Rendimiento y operabilidad

- listados pesados
- indices y consultas
- colas, cron y workers
- logs y observabilidad
- tuning de runtime cuando aplique

### Ola 4. Cierre profesional

- documentacion interna
- score final por bloque
- checklist de release
- decision de core v1.0

---

## Formato de trabajo que debes seguir

Para cada bloque, responde siempre con esta secuencia:

1. estado actual
2. nota actual
3. problemas que impiden llegar a 9/10
4. plan de mejora
5. implementacion realizada
6. validacion ejecutada
7. nota final del bloque
8. riesgos residuales

Y tras cerrar ese bloque, actualiza inmediatamente en `docs/core-consolidation-plan-erp-saas.md`:

1. `Estado`
2. `Nota actual`
3. `Ultima revision`
4. `Gap principal`
5. observaciones del bloque si cambian

---

## Criterio de parada

No declares victoria por tener mucho codigo tocado. Solo cuenta como avance real cuando el bloque mejora en:

- claridad
- seguridad
- coherencia
- rendimiento
- testabilidad

Si una mejora no sube la calidad real, no la priorices.

---

## Cierre global obligatorio

Al terminar la ejecucion completa:

1. muestra una tabla final de bloques con nota antes/despues
2. identifica que bloques quedaron en 9/10 o 10/10
3. lista deuda pendiente no bloqueante
4. indica si el sistema puede declararse **Core v1.0 estable**
5. deja roadmap minimo para mantener la nota en futuras evoluciones

---

## Instruccion final al agente

Trabaja como un ingeniero senior responsable de dejar el producto listo para crecer sin caos. No persigas refactors esteticos. Persigue bloques estables, reglas claras, rendimiento razonable, seguridad real y una calidad sostenida que permita vender, implantar y evolucionar PesquerApp con confianza.
