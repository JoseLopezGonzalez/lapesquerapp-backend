# Guía del Circuito de Auditoría e Implementación

## Objetivo de esta guía

Este documento explica **qué prompts existen**, **para qué sirve cada uno**, **en qué orden deben usarse** y **qué documentos se actualizan** durante el ciclo de auditoría y mejora del backend de PesquerApp.

La idea es que cualquier persona o agente pueda:

- lanzar una auditoría principal
- interpretar el estado del proyecto
- ejecutar mejoras bloque por bloque
- registrar correctamente las puntuaciones
- mejorar los propios prompts y documentos sin romper el circuito

---

## Vista general del circuito

El circuito recomendado es este:

1. **Auditoría principal**
2. **Actualización o validación del registro de bloques**
3. **Implementación por bloques**
4. **Actualización de puntuaciones**
5. **Re-auditoría o cierre**

En forma práctica:

1. Usar [12-prompt-auditoria-principal-backend.md](/home/jose/lapesquerapp-backend/docs/prompts/12-prompt-auditoria-principal-backend.md)
2. Revisar y actualizar [core-consolidation-plan-erp-saas.md](/home/jose/lapesquerapp-backend/docs/core-consolidation-plan-erp-saas.md)
3. Ejecutar [13-prompt-implementacion-mejoras-por-bloques.md](/home/jose/lapesquerapp-backend/docs/prompts/13-prompt-implementacion-mejoras-por-bloques.md)
4. Volver a reflejar el estado en [core-consolidation-plan-erp-saas.md](/home/jose/lapesquerapp-backend/docs/core-consolidation-plan-erp-saas.md)
5. Si hace falta, regenerar o actualizar [laravel-backend-global-audit.md](/home/jose/lapesquerapp-backend/docs/audits/laravel-backend-global-audit.md)

---

## Documentos principales del circuito

### 1. Prompt de auditoría principal

Archivo:

- [12-prompt-auditoria-principal-backend.md](/home/jose/lapesquerapp-backend/docs/prompts/12-prompt-auditoria-principal-backend.md)

Sirve para:

- auditar arquitectura
- evaluar calidad de código
- revisar rendimiento
- revisar seguridad
- puntuar el sistema
- detectar gaps para llegar a `9/10` o `10/10`

Salida esperada principal:

- [laravel-backend-global-audit.md](/home/jose/lapesquerapp-backend/docs/audits/laravel-backend-global-audit.md)

Usarlo cuando:

- quieras una foto global del backend
- haya pasado tiempo desde la última revisión
- se hayan abierto bloques nuevos
- quieras recalcular prioridades

### 2. Registro maestro de bloques y puntuaciones

Archivo:

- [core-consolidation-plan-erp-saas.md](/home/jose/lapesquerapp-backend/docs/core-consolidation-plan-erp-saas.md)

Zona clave:

- `ANEXO A — Inventario Vivo de Bloques y Puntuaciones`

Sirve para:

- mantener el inventario real de bloques
- registrar la nota actual
- dejar el objetivo de cada bloque
- marcar estado y gap principal
- actuar como **fuente de verdad**

Usarlo cuando:

- vayas a implementar mejoras
- cierres un bloque
- abras un bloque nuevo
- cambie la nota de un bloque

### 3. Prompt de implementación por bloques

Archivo:

- [13-prompt-implementacion-mejoras-por-bloques.md](/home/jose/lapesquerapp-backend/docs/prompts/13-prompt-implementacion-mejoras-por-bloques.md)

Sirve para:

- ejecutar mejoras reales
- trabajar por bloques
- validar cambios
- actualizar la puntuación
- dejar el core en `9/10` o mejor

Regla importante:

- este prompt debe actualizar [core-consolidation-plan-erp-saas.md](/home/jose/lapesquerapp-backend/docs/core-consolidation-plan-erp-saas.md) tras cada bloque relevante

### 4. Auditoría histórica y supporting docs

Archivos útiles:

- [00-laravel-backend-deep-audit.md](/home/jose/lapesquerapp-backend/docs/prompts/antiguos/00-laravel-backend-deep-audit.md)
- [rendimiento-auditoría-performance-laravel.md](/home/jose/lapesquerapp-backend/docs/prompts/antiguos/rendimiento-auditoría-performance-laravel.md)
- [laravel-backend-global-audit.md](/home/jose/lapesquerapp-backend/docs/audits/laravel-backend-global-audit.md)
- [laravel-evolution-log.md](/home/jose/lapesquerapp-backend/docs/audits/laravel-evolution-log.md)

Sirven para:

- recuperar contexto histórico
- comparar notas antiguas
- detectar mejoras ya ejecutadas
- no rehacer trabajo innecesario

---

## Orden recomendado de uso

### Escenario A. Quiero saber cómo está el proyecto hoy

1. Ejecutar el prompt de auditoría principal.
2. Contrastar resultado con el anexo de bloques del core.
3. Ajustar notas si hay bloques nuevos o si cambió el alcance.

### Escenario B. Quiero mejorar el proyecto bloque por bloque

1. Revisar el anexo del core.
2. Elegir bloque por prioridad.
3. Ejecutar el prompt de implementación.
4. Actualizar nota, estado y gap en el anexo.
5. Repetir con el siguiente bloque.

### Escenario C. Quiero mejorar los prompts o el propio circuito

1. Revisar esta guía.
2. Revisar los prompts `12` y `13`.
3. Cambiar primero el flujo conceptual.
4. Luego reflejar el cambio en el anexo del core si afecta a notas, estados o bloques.

---

## Fuente de verdad de puntuaciones

La fuente principal es:

- [core-consolidation-plan-erp-saas.md](/home/jose/lapesquerapp-backend/docs/core-consolidation-plan-erp-saas.md)

Eso significa:

- si un bloque sube de nota, debe quedar reflejado ahí
- si se añade un bloque nuevo, debe quedar reflejado ahí
- si un bloque cambia de alcance, debe quedar reflejado ahí

Documentos secundarios posibles:

- `docs/audits/block-scoreboard.md`
- `docs/audits/block-improvement-roadmap.md`

Pero esos, si existen, son auxiliares. No sustituyen el anexo del core.

---

## Qué actualizar después de cada implementación

Después de tocar un bloque, hay que actualizar como mínimo en el anexo del core:

1. `Estado`
2. `Nota actual`
3. `Objetivo`
4. `Fuente`
5. `Ultima revision`
6. `Gap principal`
7. Observaciones si cambió el alcance del bloque

Si la implementación cambia la visión global del proyecto, también conviene actualizar:

- [laravel-backend-global-audit.md](/home/jose/lapesquerapp-backend/docs/audits/laravel-backend-global-audit.md)

Si la implementación añade hitos relevantes, conviene registrar también:

- [laravel-evolution-log.md](/home/jose/lapesquerapp-backend/docs/audits/laravel-evolution-log.md)

---

## Cómo mejorar este circuito

Puedes mejorar el circuito de tres formas:

### 1. Mejorar la auditoría

Hazlo cuando:

- falten dimensiones de evaluación
- haya nuevos dominios del producto
- haya nuevas reglas SaaS o multi-tenant
- quieras endurecer criterios para `9/10`

Documentos a tocar:

- [12-prompt-auditoria-principal-backend.md](/home/jose/lapesquerapp-backend/docs/prompts/12-prompt-auditoria-principal-backend.md)
- [core-consolidation-plan-erp-saas.md](/home/jose/lapesquerapp-backend/docs/core-consolidation-plan-erp-saas.md)

### 2. Mejorar la implementación por bloques

Hazlo cuando:

- el prompt no obligue suficiente validación
- falten checklists
- no quede claro cuándo un bloque está cerrado
- quieras introducir olas o prioridades mejores

Documentos a tocar:

- [13-prompt-implementacion-mejoras-por-bloques.md](/home/jose/lapesquerapp-backend/docs/prompts/13-prompt-implementacion-mejoras-por-bloques.md)
- [core-consolidation-plan-erp-saas.md](/home/jose/lapesquerapp-backend/docs/core-consolidation-plan-erp-saas.md)

### 3. Mejorar el mapa de bloques

Hazlo cuando:

- el proyecto abra un módulo nuevo
- un bloque actual esté mal agrupado
- aparezcan features SaaS, CRM o canales nuevos

Documento principal:

- [core-consolidation-plan-erp-saas.md](/home/jose/lapesquerapp-backend/docs/core-consolidation-plan-erp-saas.md)

---

## Regla práctica para no romper nada

Si dudas sobre dónde registrar algo:

- si afecta a la nota o al inventario de bloques -> va al `core-consolidation-plan`
- si afecta a la evaluación global -> va a la auditoría global
- si afecta al modo de trabajar del agente -> va al prompt correspondiente
- si afecta al circuito entero -> actualiza también esta guía

---

## Resumen rápido

Si quieres una versión corta:

- `12` audita
- `13` implementa
- `core-consolidation-plan` registra el estado real y la puntuación
- `laravel-backend-global-audit` resume la visión global
- `laravel-evolution-log` guarda la historia de mejoras
- este documento explica cómo se conectan entre sí

---

## Mantenimiento recomendado

Revisar esta guía cuando ocurra una de estas situaciones:

- aparece un bloque nuevo importante
- cambia la estrategia de puntuación
- cambia el flujo entre auditoría e implementación
- se crean nuevos documentos obligatorios dentro del circuito

Si el circuito cambia, esta guía debe actualizarse en la misma sesión para no dejar incoherencias.
