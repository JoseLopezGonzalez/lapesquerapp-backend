# Prompt Maestro — Auditoria Principal Backend Laravel PesquerApp

## Objetivo

Actua como **Staff/Principal Laravel Architect + Performance Engineer** y realiza una **auditoria principal integral** del backend de PesquerApp. Tu mision es producir una evaluacion profesional, accionable y basada en evidencias sobre:

- calidad arquitectonica
- calidad de codigo
- mantenibilidad
- rendimiento
- escalabilidad
- seguridad
- consistencia de negocio
- preparacion multi-tenant
- operabilidad en produccion

No hagas una revision superficial por archivos. Debes identificar **patrones sistemicos**, evaluar la madurez real del proyecto y dejar un plan claro para llevar el sistema a **9/10 o 10/10**.

---

## Contexto fijo del proyecto

- Proyecto: **PesquerApp Backend**
- Stack principal: **Laravel 10+**
- Arquitectura: **multi-tenant database-per-tenant**
- Aislamiento tenant: cabecera `X-Tenant` y middleware de cambio de conexion por request
- Excepciones publicas: rutas tipo `api/v2/public/*`
- Infraestructura esperable: Docker/Sail, Coolify, VPS IONOS
- Dominio: ERP pesquero con ventas, inventario, produccion, proveedores, etiquetas, fichajes, configuracion y utilidades documentales

---

## Reglas obligatorias

1. Trabaja con autonomia y minimiza preguntas.
2. No asumas nada importante sin evidencia en el repo.
3. Todo hallazgo debe incluir evidencia concreta:
   - archivo o ruta
   - componente implicado
   - patron detectado
   - impacto tecnico o funcional
4. No conviertas la auditoria en una lista plana de detalles menores.
5. Prioriza riesgos sistemicos, deuda repetida y cuellos de botella reales.
6. Cuando detectes una mejora, explica:
   - impacto esperado
   - riesgo
   - complejidad
   - como verificarla
   - rollback o mitigacion si aplica
7. Si consultas buenas practicas, contrasta con Laravel oficial, PHP oficial y fuentes reputadas.
8. Considera el multi-tenant como zona critica. Cualquier recomendacion debe evitar fugas cross-tenant.

---

## Que debes auditar

### 1. Arquitectura y diseno

- identidad arquitectonica real del proyecto
- reparto de responsabilidades entre controllers, services, actions, models, jobs y events
- coherencia del application layer
- acoplamiento y cohesion
- SRP, separacion de capas y deuda estructural

### 2. Calidad de codigo

- controladores demasiado grandes
- validaciones fuera de `FormRequest`
- logica de negocio en lugares incorrectos
- duplicacion
- metodos gigantes
- nomenclatura inconsistente
- ausencia de contratos o patrones coherentes

### 3. Componentes estructurales Laravel

Evalua de forma explicita:

- Services
- Actions
- Jobs
- Events y Listeners
- Form Requests
- Policies y Gates
- API Resources
- Middleware
- DTOs u objetos equivalentes
- Observers, traits o helpers transversales

### 4. Logica de negocio

- reglas por modulo
- estados y transiciones
- consistencia de totales
- integridad transaccional
- side effects
- trazabilidad
- lugares donde la verdad de negocio esta fragmentada

### 5. Rendimiento y escalabilidad

- N+1
- eager loading
- filtros y listados pesados
- serializacion excesiva
- paginacion
- indices
- locks y transacciones
- overhead de conexion tenant
- cache tenant-aware
- jobs y colas
- logs y coste de observabilidad
- señales de CPU, memoria, IO, DB y Redis

### 6. Seguridad y autorizacion

- aislamiento tenant
- policies
- endpoints publicos
- validacion de permisos
- acceso a datos por conexion correcta
- riesgo de data leaks
- seguridad operacional basica

### 7. Testing y mantenibilidad

- cobertura util por bloques
- tests de reglas de negocio
- tests de permisos
- tests de flujos criticos
- fiabilidad para evolucion futura

### 8. Runtime y produccion

- configuracion PHP-FPM y OPcache
- colas, workers y cron
- logs
- despliegue y rollback
- consistencia entre dev y produccion

---

## Bloques funcionales a considerar

Debes mapear y auditar el sistema por bloques, como minimo:

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
13. Sistema / usuarios / sesiones / logs
14. Estadisticas e informes
15. Documentos y utilidades PDF/IA si afectan al core
16. Infraestructura API y endpoints tecnicos

Si detectas bloques adicionales, incorporalos.

---

## Metodologia esperada

### Fase 0. Descubrimiento

- inspecciona estructura del repo
- identifica dominios, modulos y capas
- detecta patrones arquitectonicos ya existentes
- localiza cuellos de botella potenciales y zonas sensibles

### Fase 1. Validacion previa

Antes de profundizar, confirma:

- estructura accesible
- bloques detectados
- componentes Laravel presentes
- areas ambiguas que requieran una nota de contexto

### Fase 2. Auditoria sistemica

Analiza de forma transversal:

- arquitectura
- codigo
- negocio
- seguridad
- multi-tenant
- rendimiento
- testing
- operaciones

### Fase 3. Evaluacion por bloques

Para cada bloque:

- resume su estado actual
- identifica fortalezas
- enumera riesgos reales
- asigna una puntuacion
- estima prioridad de mejora

### Fase 4. Sintesis ejecutiva

Produce una conclusion clara con:

- riesgos sistemicos
- oportunidades de alto impacto
- quick wins
- cambios estructurales
- orden recomendado de intervencion

---

## Sistema de scoring obligatorio

Asigna puntuacion **1-10** en cada dimension:

- multi-tenancy implementation maturity
- domain modeling clarity
- API design consistency
- testing coverage and strategy
- deployment and DevOps practices
- documentation quality
- technical debt level
- code quality and maintainability
- performance and scalability
- security and authorization maturity

Ademas:

- da una **nota global**
- justifica cada nota
- indica explicitamente que separa al proyecto de un **9/10** y de un **10/10**

### Criterio de notas

- `9-10`: consistente, profesional, escalable, con riesgos controlados
- `7-8`: bueno, pero con deuda relevante o huecos estructurales
- `5-6`: funcional, con problemas repetidos que limitan la evolucion
- `<5`: riesgo alto, arquitectura fragil o inconsistente

---

## Entregables obligatorios

### Documento principal

Genera como salida principal:

- `docs/audits/laravel-backend-global-audit.md`

### Documentos de apoyo

Si aportan claridad, genera tambien:

- `docs/audits/findings/multi-tenancy-analysis.md`
- `docs/audits/findings/domain-model-review.md`
- `docs/audits/findings/integration-patterns.md`
- `docs/audits/findings/security-concerns.md`
- `docs/audits/findings/structural-components-usage.md`
- `docs/audits/findings/performance-hotspots.md`
- `docs/audits/findings/block-priority-matrix.md`

---

## Estructura minima del informe principal

1. Executive Summary
2. Identidad arquitectonica del proyecto
3. Fortalezas principales
4. Riesgos sistemicos
5. Evaluacion de calidad de codigo
6. Evaluacion de componentes estructurales Laravel
7. Distribucion de logica de negocio
8. Integridad transaccional y side effects
9. Observaciones de rendimiento y escalabilidad
10. Observaciones de seguridad y autorizacion
11. Testing y mantenibilidad
12. Evaluacion por bloques
13. Scoring por dimension
14. Top 5 riesgos sistemicos
15. Top 5 mejoras de mayor impacto
16. Quick wins de bajo riesgo
17. Mejoras estructurales de mayor alcance
18. Roadmap recomendado para llegar a 9/10 o 10/10

---

## Salida por bloque

Para cada bloque funcional, incluye:

- descripcion corta del bloque
- estado actual
- nota actual
- fortalezas
- debilidades
- riesgos
- quick wins
- cambios estructurales recomendados
- tests que faltan
- observaciones de rendimiento
- observaciones de seguridad

---

## Reglas de implementacion durante la auditoria

- No refactorices durante la fase de auditoria principal salvo quick wins muy seguros y justificables.
- Si implementas algo, mide antes y despues.
- No hagas cambios amplios sin plan de verificacion.
- No introduzcas cache sin namespacing tenant.
- No optimices sacrificando integridad o aislamiento.

---

## Cierre obligatorio

Termina siempre con:

1. **Nota global actual**
2. **Nota potencial tras quick wins**
3. **Nota potencial tras plan completo**
4. **Secuencia recomendada de ejecucion**
5. **Criterios objetivos para declarar el backend en 9/10**

---

## Instruccion final al agente

Piensa como un auditor senior de verdad. No generes texto generico. No rellenes huecos con opinion vaga. Detecta el estado real del sistema, puntua con criterio profesional y deja un plan claro para convertir PesquerApp en un backend robusto, predecible, seguro y escalable.
