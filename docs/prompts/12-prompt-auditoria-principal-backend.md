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

- N+1 y eager loading: detecta listados que disparan queries por fila; evalua uso real de `with()` en controladores y servicios
- filtros y listados pesados: identifica endpoints que devuelven colecciones grandes sin paginacion o con paginacion mal configurada
- serializacion excesiva: API Resources que cargan relaciones innecesarias o exponen campos calculados costosos
- paginacion: coherencia de `per_page` entre bloques; riesgo de offset pagination en tablas grandes (preferir keyset)
- indices: presencia de indices en columnas de filtrado habitual (estado, fecha, foreign keys); indices compuestos donde el filtro combinado es frecuente
- locks y transacciones: transacciones largas que bloquean filas; ausencia de `lockForUpdate` donde hay race conditions
- overhead de conexion tenant: coste del ciclo `DB::purge + DB::reconnect` en cada request; impacto acumulado bajo carga
- cache tenant-aware: uso de `Cache::tags` o prefijo tenant; riesgo de servir cache de un tenant a otro; TTL adecuado por tipo de dato
- jobs y colas: dimensionamiento de workers; payloads con identificador tenant; comportamiento bajo backpressure
- logs y coste de observabilidad: queries de Telescope/ActivityLog ejecutadas en produccion; coste de logging por request
- señales de CPU, memoria, IO, DB y Redis: identificar patrones en el codigo que predigan cuellos de botella en produccion

### 5b. Concurrencia, tiempos de respuesta y comportamiento bajo carga

Esta seccion es critica. El sistema opera como ERP multi-tenant con usuarios simultaneos por tenant y potencialmente multiples tenants activos a la vez. Debes evaluar:

#### Modelo de concurrencia PHP-FPM

- Numero de workers (`pm.max_children`) configurados o esperables en el entorno Docker/Coolify
- Relacion entre workers y `max_connections` de MySQL: si hay N workers por tenant y T tenants activos, el pico de conexiones abiertas puede ser N×T; detectar si esto supera el limite de MySQL
- Tiempo de vida del worker (`pm.max_requests`) y su impacto en fugas de memoria o conexiones zombie
- Uso de `pm = dynamic` vs `pm = static` y su adecuacion a un ERP con carga variable

#### Overhead del middleware TenantMiddleware bajo concurrencia

- El ciclo `DB::purge('tenant') + DB::reconnect('tenant')` se ejecuta en **cada request**; bajo 50-100 requests/segundo este coste es medible
- Detectar si existe algun nivel de reutilizacion de conexion (persistent connections, ProxySQL, pool externo) o si cada request abre y cierra conexion en frio
- Evaluar si el lookup del tenant en la BD central (`tenants` table) esta cacheado (ej. `Cache::remember`) o si es una query adicional por request
- Riesgo de contaminacion de conexion si un worker reutiliza estado de una conexion anterior (sin purge correcto)

#### Tiempos de respuesta objetivo por tipo de endpoint

Define si el sistema cumple o puede cumplir estos thresholds bajo carga real:

| Tipo de endpoint | P50 objetivo | P95 objetivo | P99 maximo tolerable |
|---|---|---|---|
| Listados paginados (20-50 filas) | < 120ms | < 300ms | < 600ms |
| CRUD simple (create/update/delete) | < 100ms | < 250ms | < 500ms |
| Listados con filtros complejos (pedidos, produccion) | < 200ms | < 500ms | < 1s |
| Dashboards / estadisticas | < 400ms | < 1s | < 2s |
| Generacion PDF | < 1s | < 3s | < 5s (async si supera) |
| Auth / login / magic link | < 150ms | < 350ms | < 700ms |
| Endpoints publicos (NFC, fichajes) | < 80ms | < 200ms | < 400ms |

Identifica endpoints que **por su implementacion actual** no podrian cumplir estos thresholds bajo 10-20 usuarios simultaneos.

#### Identificacion de endpoints de alto riesgo bajo carga concurrente

Para cada bloque funcional, identifica el endpoint mas pesado y evalua:

- queries ejecutadas (numero y coste estimado)
- datos serializados (tamaño aproximado del payload)
- si usa transacciones y cuanto tiempo mantiene locks
- si hay efectos colaterales sincronos (eventos, notificaciones, recalculos) que aumentan el tiempo de respuesta
- si puede ejecutarse en paralelo con si mismo (ej. dos usuarios guardando stock simultaneamente)

#### Race conditions bajo concurrencia

- Operaciones de stock (entrada/salida de cajas, palets): detectar si hay `lockForUpdate` o equivalente que evite doble decremento
- Produccion: si dos usuarios crean ProductionRecord sobre el mismo Production simultaneamente, hay riesgo de inconsistencia en totales
- Fichajes: store de PunchEvent sin lock puede crear dobles entradas en el mismo segundo
- Magic link / OTP: consumo del token debe ser atomico (select + delete en transaccion o uso de `updateOrFail` con condicion)
- Pedidos: transicion de estado (pending → finished) debe ser idempotente y protegida contra doble submit

#### Comportamiento de colas bajo carga

- Si hay workers de cola en produccion: numero de workers, timeout, retry policy
- Jobs de larga duracion (PDF, exportaciones, importaciones) que bloquean workers y retrasan jobs cortos
- Ausencia de prioridad de colas: un job de generacion de informe no debe competir con un job de envio de notificacion critica
- Detectar si hay operaciones que deberian ser async pero se ejecutan sincronicamente en el request

#### Herramientas de diagnostico y profiling recomendadas

Evalua si el proyecto tiene o puede tener instrumentacion para medir tiempos reales:

- **Laravel Telescope**: detectar queries lentas, jobs fallidos, requests lentos; evaluar si esta deshabilitado en produccion (imprescindible)
- **Laravel Debugbar**: solo desarrollo; confirmar que no filtra a produccion
- **slow query log MySQL**: umbral recomendado 200ms; verificar si esta configurado en el entorno Docker
- **EXPLAIN / EXPLAIN ANALYZE**: para las queries mas frecuentes de los endpoints criticos
- **k6 / wrk / ApacheBench**: escenarios de carga recomendados para validar los thresholds definidos arriba

Para cada endpoint critico identificado, proporciona el escenario de carga minimo que deberia ejecutarse antes de considerar el bloque production-ready:

```
k6 run --vus 20 --duration 60s test-pedidos-list.js
# objetivo: P95 < 300ms, error rate < 0.1%
```

#### Escalabilidad horizontal

- El modelo database-per-tenant facilita escalar verticalmente cada tenant de forma independiente; evaluar si la arquitectura actual permite anadir un segundo servidor de aplicacion sin estado compartido conflictivo (sesiones, cache, colas)
- Identificar cualquier estado en memoria del proceso PHP que no sobreviva a un reinicio o a multiples instancias (ej. singletons con estado mutable)

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

**PHP-FPM:**
- `pm`, `pm.max_children`, `pm.start_servers`, `pm.min_spare_servers`, `pm.max_spare_servers`: evaluar si estan dimensionados para el VPS disponible (RAM/CPU) y para el numero esperado de tenants activos simultaneos
- `pm.max_requests`: debe limitar la acumulacion de memoria; valor recomendado 500-1000 para un ERP con carga moderada
- `request_terminate_timeout`: proteccion contra requests colgados que bloquean workers indefinidamente
- `slowlog` y `request_slowlog_timeout`: configurar para detectar requests lentos en produccion

**OPcache:**
- `opcache.enable`, `opcache.memory_consumption`, `opcache.max_accelerated_files`: verificar que estan activos y con valores adecuados para el tamaño del proyecto Laravel (> 10.000 archivos)
- `opcache.revalidate_freq`: debe ser 0 en produccion (no revalidar en cada request); gestion manual via `php artisan opcache:clear` en despliegue
- `opcache.jit` (PHP 8.1+): evaluar si esta habilitado y si aporta ganancia medible en este tipo de carga (IO-bound vs CPU-bound)

**MySQL bajo carga:**
- `max_connections`: debe superar el pico de conexiones esperado (FPM workers × tenants activos + workers de cola + conexiones de monitoreo)
- `wait_timeout` e `interactive_timeout`: evitar conexiones zombie que consuman slots
- `innodb_buffer_pool_size`: debe ser ~70-80% de la RAM disponible para MySQL en un servidor dedicado; verificar si esta configurado o usa el default (128MB, insuficiente)
- `slow_query_log` con `long_query_time = 0.2`: activar y revisar periodicamente; incluir `log_queries_not_using_indexes`

**Colas, workers y cron:**
- numero de workers por tipo de job; presencia de `--queue=high,default,low` con prioridad explícita
- `QUEUE_TIMEOUT` y `retry_after` por tipo de job; jobs de PDF/exportacion deben tener timeout mayor y cola separada
- supervisor o equivalente para mantener workers activos tras reinicios; detectar si esta configurado en el entorno Docker
- cron: `schedule:run` en Kernel; verificar que no hay jobs repetidos o solapados que puedan causar doble ejecucion

**Logs:**
- nivel de log en produccion: `LOG_LEVEL=warning` o `error`; nunca `debug` en produccion (coste IO y riesgo de exponer datos sensibles)
- rotacion de logs: `daily` con retencion limitada; logs de Laravel Telescope purgados periodicamente

**Despliegue y rollback:**
- secuencia de despliegue zero-downtime o estrategia de mantenimiento controlado
- `php artisan config:cache`, `route:cache`, `view:cache`, `event:cache`: verificar que se ejecutan en despliegue
- rollback: plan ante migracion fallida (backup previo, migracion reversible)
- consistencia entre `.env` de dev y produccion: variables criticas documentadas

**Consistencia dev/produccion:**
- versiones de PHP, MySQL y extensiones identicas entre Docker dev y produccion
- `APP_DEBUG=false` y `APP_ENV=production` verificados

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
- **performance under single-user load** *(N+1, indices, eager loading, serializacion)*
- **performance under concurrent load** *(race conditions, FPM dimensionado, conexiones DB, cache tenant-aware, tiempos P95)*
- security and authorization maturity

Ademas:

- da una **nota global**
- justifica cada nota con evidencia del codigo
- para las dos dimensiones de rendimiento, estima el P95 esperado de los 3 endpoints mas criticos del sistema
- indica explicitamente que separa al proyecto de un **9/10** y de un **10/10**

### Criterio de notas

- `9-10`: consistente, profesional, escalable, con riesgos controlados; P95 < 300ms en endpoints criticos bajo 20 usuarios concurrentes
- `7-8`: bueno, pero con deuda relevante o huecos estructurales; cumple tiempos bajo carga baja pero degrada con concurrencia
- `5-6`: funcional, con problemas repetidos que limitan la evolucion; race conditions conocidas o tiempos > 1s bajo carga moderada
- `<5`: riesgo alto, arquitectura fragil o inconsistente; bloqueos, N+1 sistemicos o falta de indices en tablas criticas

### Criterio especifico de performance bajo concurrencia

Para puntuar **9 o mas** en `performance under concurrent load`, el sistema debe cumplir:

1. No hay race conditions conocidas en operaciones de stock, produccion o fichajes
2. El lookup de tenant esta cacheado o tiene coste < 5ms
3. Las queries de los 5 endpoints mas usados tienen EXPLAIN con tipo `ref` o mejor (no `ALL`)
4. PHP-FPM esta dimensionado para soportar el pico esperado de workers sin agotar `max_connections` MySQL
5. Los jobs de larga duracion estan en cola separada de los jobs cortos
6. No hay transacciones que mantengan locks > 500ms en tablas de alta escritura concurrente

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
- `docs/audits/findings/performance-concurrency-analysis.md` *(nuevo; ver estructura abajo)*
- `docs/audits/findings/block-priority-matrix.md`

#### Estructura minima de `performance-concurrency-analysis.md`

1. **Modelo de concurrencia actual**: FPM workers estimados, max_connections MySQL, ratio workers/conexiones bajo N tenants activos
2. **Tabla de endpoints criticos**: endpoint, queries ejecutadas, locks, tiempo estimado P50/P95 en frio, riesgo bajo 20 usuarios concurrentes
3. **Race conditions detectadas**: por bloque; evidencia en codigo; propuesta de mitigacion
4. **Cache tenant-aware**: que esta cacheado, que deberia estarlo, riesgo de contaminacion detectado
5. **Cuellos de botella por capa**: PHP (CPU/mem), MySQL (queries, conexiones, locks), Redis (si aplica), colas
6. **Configuracion runtime recomendada**: valores concretos para PHP-FPM, OPcache y MySQL ajustados al hardware esperado
7. **Plan de validacion de carga**: escenarios k6/wrk con VUs, duracion y thresholds aceptables por endpoint
8. **Quick wins de rendimiento**: cambios de < 1 dia que mejoran P95 sin riesgo de regresion

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
9. Rendimiento bajo carga unitaria (N+1, indices, eager loading)
10. **Rendimiento bajo carga concurrente** *(nuevo)*
    - modelo de concurrencia PHP-FPM y MySQL bajo multiples tenants
    - tabla de endpoints criticos con P95 estimado
    - race conditions detectadas
    - overhead TenantMiddleware bajo carga
    - dimensionado de colas y workers
    - configuracion runtime: gaps detectados
11. Observaciones de seguridad y autorizacion
12. Testing y mantenibilidad
13. Evaluacion por bloques
14. Scoring por dimension *(incluye las dos sub-dimensiones de performance)*
15. Top 5 riesgos sistemicos
16. Top 5 mejoras de mayor impacto *(con estimacion de impacto en P95)*
17. Quick wins de bajo riesgo *(incluir quick wins de rendimiento)*
18. Mejoras estructurales de mayor alcance
19. Roadmap recomendado para llegar a 9/10 o 10/10

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
- **observaciones de rendimiento bajo carga unitaria** (N+1, queries, indices)
- **observaciones de rendimiento bajo carga concurrente**: identifica el endpoint mas critico del bloque y evalua su comportamiento con 10-20 usuarios simultaneos; indica si hay race conditions, locks prolongados o ausencia de cache
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
6. **Criterios objetivos para declarar el backend production-ready bajo carga concurrente**:
   - P95 < 300ms en los 5 endpoints mas usados bajo 20 usuarios concurrentes por tenant
   - cero race conditions conocidas en operaciones de stock, produccion y fichajes
   - PHP-FPM y MySQL dimensionados para el pico esperado con margen del 30%
   - slow query log sin queries > 200ms en uso normal
   - jobs de larga duracion en cola dedicada con timeout y retry configurados
   - lookup de tenant cacheado; coste del TenantMiddleware < 5ms medido

---

## Instruccion final al agente

Piensa como un auditor senior de verdad. No generes texto generico. No rellenes huecos con opinion vaga. Detecta el estado real del sistema, puntua con criterio profesional y deja un plan claro para convertir PesquerApp en un backend robusto, predecible, seguro y escalable.

**En materia de rendimiento y concurrencia**: no te limites a detectar N+1 o indices ausentes — eso es la capa superficial. El objetivo es responder a esta pregunta concreta: *"Si 20 usuarios de un tenant y 5 tenants activos a la vez hacen peticiones simultaneas en hora punta, ¿que se rompe primero, donde se degrada el tiempo de respuesta y que hay que cambiar para que el sistema aguante sin que ninguna peticion supere 500ms?"*. Esa es la auditoria de rendimiento que se espera.
