# Auditoría profunda de rendimiento del backend Laravel

## Rol que debes asumir

Actúa como un **Senior Backend Performance Engineer** con especialización en:

- **Laravel 10**
- **PHP 8.1+**
- **MySQL / MariaDB**
- **profiling y optimization**
- **auditoría de dependencias Composer**
- **Eloquent performance**
- **consultas SQL**
- **colas, jobs y procesamiento asíncrono**
- **caché y bootstrap del framework**
- **tiempos de respuesta de APIs**
- **consumo de CPU, RAM y coste operativo**
- **developer experience y rendimiento en entorno local**

Tu trabajo es realizar una **auditoría técnica profunda de rendimiento** sobre este backend concreto, no una revisión genérica de Laravel.

## Objetivo exacto de la auditoría

Necesito que audites este proyecto porque existe la sospecha real de que el backend está **pesado, lento o potencialmente ineficiente**, tanto en desarrollo como en ejecución real.

Debes detectar todo lo que pueda estar afectando negativamente a:

- tiempos de respuesta
- consumo de CPU
- consumo de memoria
- latencia de consultas
- coste de bootstrap por request
- coste de middlewares y providers
- escalabilidad técnica
- saturación por carga relacional o serialización
- carga innecesaria en autenticación, autorización o resolución de contexto
- tareas síncronas que deberían ir en background
- velocidad de comandos Artisan y flujos de desarrollo

El objetivo no es revisar si la lógica funcional del ERP es correcta, sino **descubrir cuellos de botella técnicos reales o potenciales** en este backend Laravel.

## Contexto real del proyecto que debes usar

La auditoría debe basarse en el contexto real de este repositorio, incluyendo como mínimo estos hechos técnicos ya visibles en el código:

- Backend **Laravel 10** con `php: ^8.1` en Composer.
- Runtime de contenedores presente en varias variantes:
  - `docker-compose.yml` con Sail sobre runtime **8.3**
  - `Dockerfile` propio basado en **PHP 8.2**
- Arquitectura **API-first** con gran superficie de rutas en `routes/api.php`.
- Proyecto **multi-tenant** con:
  - base central
  - base por tenant
  - cabecera `X-Tenant`
  - cambio dinámico de conexión en `TenantMiddleware`
  - `DB::purge('tenant')` y `DB::reconnect('tenant')` por request tenant
- Separación entre:
  - rutas tenant
  - rutas públicas
  - rutas superadmin fuera de `TenantMiddleware`
- Autenticación principal con **Sanctum**, pero coexistiendo también dependencias como **`tymon/jwt-auth`**.
- Sistema con mucha carga de dominio y varios módulos grandes:
  - pedidos
  - palets
  - cajas
  - producción
  - recepciones de materia prima
  - despachos
  - CRM
  - rutas de reparto
  - usuarios internos y externos
  - superadmin
  - impersonación
  - feature flags
  - observabilidad
- El código contiene una cantidad relevante de superficie técnica:
  - ~291 declaraciones de rutas en `routes/api.php`
  - 74 controladores
  - 64 servicios
  - 70 modelos
  - 51 API Resources
  - 157 migraciones de tenant en `database/migrations/companies`
  - 38 tests detectados
- Existen tareas de generación y procesamiento pesado de documentos:
  - PDFs
  - Excel
  - parsing/extracción de PDFs
  - integración con `google/cloud-document-ai`
- Existen varias librerías relacionadas con PDF que podrían solaparse o tener coste relevante:
  - `barryvdh/laravel-dompdf`
  - `barryvdh/laravel-snappy`
  - `beganovich/snappdf`
  - `spatie/laravel-pdf`
  - `spatie/browsershot`
  - `spatie/pdf-to-text`
  - `smalot/pdfparser`
- Existen librerías de exportación y procesamiento que pueden ser pesadas:
  - `maatwebsite/excel`
  - `phpoffice/phpspreadsheet`
- Hay configuración de Redis disponible, pero en el `.env.example` aparecen por defecto:
  - `CACHE_DRIVER=file`
  - `QUEUE_CONNECTION=sync`
  - `SESSION_DRIVER=file`
- `docker-compose.yml` incluye servicio dedicado `queue`, Redis, MySQL y Mailpit.
- Hay cron/scheduler en `app/Console/Kernel.php` para limpieza de PDFs, limpieza de tokens, detección de actividad sospechosa, revisión de onboarding atascado y poda de logs.
- El proyecto usa helpers globales autoloaded por Composer:
  - `app/Helpers/tenant_helpers.php`
  - `app/Support/helpers.php`
  - `app/Support/mbstring_polyfill.php`
- En `app/Http/Kernel.php` existe middleware global personalizado relevante para el coste por request:
  - `DynamicCorsMiddleware`
  - `TrustProxies`
  - `TrimStrings`
- En `AppServiceProvider` hay lógica de bootstrap y personalización de Sanctum.
- En `DynamicCorsMiddleware` y `TenantMiddleware` hay uso de caché y consultas a tabla central para resolver tenant/origen.

Debes usar este contexto como punto de partida, y completarlo leyendo el repositorio real antes de emitir conclusiones.

## Alcance obligatorio

Debes revisar de forma seria, técnica y profunda todo lo que impacte en el rendimiento del backend, incluyendo:

### 1. Dependencias Composer y paquetes PHP

Analiza:

- dependencias no usadas o prácticamente no usadas
- paquetes redundantes, solapados o que duplican responsabilidad
- librerías costosas para el valor real que aportan
- coste de autoload y package discovery
- service providers descubiertos automáticamente
- aliases, macros, listeners o hooks registrados por paquetes
- peso real de paquetes de PDF, Excel, parsing, IA, auth y tooling
- coexistencia de varias soluciones para el mismo problema
- dependencias que puedan penalizar bootstrap, memoria o tiempo de arranque

Presta especial atención a:

- stack de PDF/renderizado
- stack de exportación Excel
- `Sanctum` junto con `tymon/jwt-auth`
- paquetes de geolocalización, parsing y tooling
- paquetes de desarrollo que puedan estar colándose en flujos no deseados

### 2. Bootstrap del framework y coste global por request

Revisa:

- `bootstrap/app.php`
- `config/app.php`
- providers del proyecto
- providers descubiertos por Composer
- middlewares globales y de grupo
- bindings del contenedor
- helpers cargados globalmente
- lógica ejecutada en `boot()`
- coste del middleware multi-tenant
- coste del CORS dinámico
- coste de autenticación y resolución de actor
- coste añadido por configuración dinámica de conexiones o mailers

Debes detectar:

- lógica global excesiva
- trabajo que se ejecuta en todas las requests sin necesidad
- boot logic que debería moverse a ejecución diferida
- middlewares sobredimensionados
- consultas o accesos a caché innecesarios en el ciclo de request
- cambios de configuración en caliente que añadan latencia evitable

### 3. Eloquent, modelos y consultas SQL

Haz una auditoría profunda de:

- N+1 queries
- eager loading mal aplicado
- eager loading excesivo
- `with()`, `load()`, `loadMissing()`, `whereHas()`, `withCount()`
- scopes pesados
- relaciones anidadas de mucho coste
- consultas repetidas
- `paginate()` sobre queries complejas
- uso de colecciones en memoria cuando debería usarse SQL
- `exists`, `count`, `pluck`, `first`, `get` usados de forma ineficiente
- atributos calculados costosos
- accessors, mutators, casts y appends que disparen más trabajo del necesario
- modelos con demasiada responsabilidad
- consultas en controladores, recursos, requests, policies o servicios donde generen carga innecesaria

Debes revisar especialmente zonas del proyecto donde ya se observa alta densidad de carga relacional o composición compleja:

- `Order`, `Pallet`, `Box`, `Production`, `ProductionRecord`, `ProductionOutput`
- listados y vistas de pedidos
- inventario de palets y cajas
- recepciones y producción
- CRM
- panel superadmin
- autenticación y feature flags
- recursos API que serialicen árboles grandes

### 4. Base de datos y estructura de persistencia

Analiza:

- tablas centrales y tablas tenant de uso intensivo
- índices existentes y ausentes
- índices compuestos potencialmente necesarios
- joins problemáticos
- `whereHas` que puedan forzar planes subóptimos
- scans completos evitables
- tablas calientes por frecuencia de lectura o escritura
- tablas de auditoría, login attempts, logs y tokens
- efectos de la arquitectura multi-tenant en conexiones, locking y latencia
- transacciones demasiado amplias
- operaciones que puedan causar lock contention
- patrones de escritura costosos o excesivamente chatty

Debes prestar atención también a:

- tablas de tokens
- tablas de login attempts
- tablas de logs y observabilidad
- tablas de órdenes, palets, cajas, producción y relaciones pivote
- impacto de las migraciones de tenant en la mantenibilidad y performance evolutiva

### 5. API, endpoints y tiempo de respuesta

Audita:

- endpoints especialmente pesados
- endpoints con demasiadas responsabilidades
- endpoints que hagan trabajo síncrono costoso
- serialización excesiva en Resources
- respuestas demasiado amplias
- payloads sobredimensionados
- carga innecesaria en `index`, `show`, `stats`, `dashboard`, exportaciones y endpoints operativos
- coste de policies, auth, actor scoping y filtros
- composición de respuesta que cargue demasiadas relaciones
- endpoints que podrían devolver menos datos o usar estrategias más eficientes

Debes revisar especialmente:

- autenticación tenant y superadmin
- paneles de dashboard y estadísticas
- módulos de pedidos, palets, cajas, producción y recepciones
- exportaciones Excel
- generación y descarga de PDFs
- extracción de PDFs
- endpoints de observabilidad y seguridad del superadmin

### 6. Caché, colas y trabajo asíncrono

Revisa de forma crítica:

- si existe caché donde debería existir
- si la caché actual aporta valor real
- si el uso de `file` como cache/session default está penalizando desarrollo o ejecución
- si Redis está infrautilizado
- invalidaciones costosas o incompletas
- claves de caché por tenant
- caché de tenant lookup, CORS, feature flags y configuraciones derivadas
- jobs que faltan
- jobs que deberían desacoplar trabajo pesado
- listeners o tareas inline que deberían ir a background
- generación de PDF/Excel ejecutada síncronamente
- onboarding de tenants
- migraciones y procesos de aprovisionamiento
- limpieza de archivos, logs y tokens
- configuración de workers
- impacto real de `QUEUE_CONNECTION=sync`

Debes evaluar si hay partes del sistema que hoy están resolviendo trabajo pesado dentro del request cuando deberían ejecutarse:

- en cola
- por lotes
- mediante procesos offline
- con precomputación
- con almacenamiento en caché

### 7. Logs, trazabilidad y coste operativo

Analiza:

- logging excesivo
- logs en rutas calientes
- logs condicionados a `APP_DEBUG`
- escritura de logs dentro de requests frecuentes
- activity feeds, error logs y observabilidad propia del proyecto
- impacto de capturar demasiada información
- coste de persistir trazas o auditorías
- riesgo de que ciertos middlewares/controladores existan solo por observabilidad y penalicen latencia
- impacto de `LOG_LEVEL=debug` y canales actuales

Debes revisar tanto logging del framework como logging funcional/técnico propio del proyecto.

### 8. Configuración técnica y tooling

Revisa:

- `composer.json`
- `composer.lock`
- autoload y autoload-dev
- scripts de Composer
- `config/*.php`
- `.env.example`
- configuración efectiva de cache, session, queue, logs, filesystem y database
- `docker-compose.yml`
- `Dockerfile`
- consistencia entre versiones de PHP usadas en distintos entornos
- si hay diferencias entre entorno local, contenedores y producción que penalicen rendimiento o DX
- si se usa o debería usarse `config:cache`, `route:cache`, `event:cache`
- si OPcache está contemplado o ausente
- si el stack de contenedores favorece o perjudica el tiempo de respuesta y el tiempo de trabajo local

### 9. Arquitectura técnica del backend

Evalúa solo desde el punto de vista de rendimiento técnico:

- controladores demasiado pesados
- servicios que acumulan demasiada orquestación y consulta
- capas que repiten trabajo
- lógica duplicada entre controller/service/model/resource
- queries en sitios inadecuados
- validaciones o transformaciones demasiado caras
- acoplamientos que obligan a cargar datos de más
- mezcla deficiente entre trabajo síncrono y asíncrono
- multi-tenancy que añada sobrecoste innecesario
- resolución dinámica de tenant, conexión, contexto o actor con coste relevante
- endpoints o módulos que podrían separarse por responsabilidades para reducir latencia

### 10. Developer experience y rendimiento en entorno dev

Analiza:

- lentitud de `artisan`
- lentitud de tests
- lentitud de migraciones y seeders
- lentitud de procesos de tenant onboarding o tenant migrate
- impacto de autoload global
- impacto de package discovery
- tooling de desarrollo innecesario
- paquetes de debugging o documentación que ralenticen el ciclo local
- coste de trabajar con Docker/Sail en este proyecto
- scripts de exportación, PDF o IA que puedan afectar DX

También debes valorar si ciertas decisiones técnicas están empeorando la productividad del equipo sin compensar con suficiente valor.

## Exclusiones obligatorias

La auditoría **no debe**:

- centrarse en lógica de negocio
- validar si los flujos del ERP cumplen su propósito funcional
- rediseñar reglas de negocio
- hacer una auditoría genérica de clean code
- proponer refactors estilísticos sin beneficio técnico claro
- mezclar seguridad, arquitectura o producto salvo cuando afecten directamente a rendimiento, uso de recursos o escalabilidad técnica
- quedarse en recomendaciones vagas del tipo “sería bueno revisar esto”

Si una observación no afecta de forma clara a:

- rendimiento
- latencia
- CPU
- RAM
- throughput
- escalabilidad
- coste de operación
- velocidad de desarrollo

entonces debe quedar fuera.

## Metodología obligatoria

Debes seguir esta metodología por fases y reflejarla en tu trabajo:

### Fase 1. Inventario técnico real del backend

Levanta un inventario real y explícito del stack:

- versión de Laravel
- versión objetivo de PHP
- runtimes reales detectados
- paquetes principales
- forma de despliegue
- forma de ejecución local
- tipo de multitenancy
- auth stack
- cola
- caché
- logs
- cron/scheduler
- recursos pesados presentes en el proyecto

No inventes nada. Todo debe salir del repositorio.

### Fase 2. Mapeo de dependencias y coste de bootstrap

Analiza:

- dependencias Composer
- providers
- aliases
- autoload files
- middlewares globales
- piezas que se cargan siempre

Identifica qué añade coste fijo a cada request o a cada comando Artisan.

### Fase 3. Revisión del ciclo de request y del contexto multi-tenant

Estudia de extremo a extremo:

- resolución de tenant
- caché del tenant
- reconexión de base de datos
- CORS dinámico
- autenticación
- actor scope
- feature flags
- resolución de contexto adicional

Determina qué parte del coste por request viene del framework y cuál del código del proyecto.

### Fase 4. Auditoría de modelos, relaciones y consultas

Inspecciona:

- modelos principales
- scopes
- queries complejas
- listados pesados
- estadísticas
- relaciones profundas
- paginaciones
- agregaciones
- zonas con `whereHas` repetidos o árboles relacionales grandes

### Fase 5. Auditoría de endpoints, resources y serialización

Revisa:

- controladores
- services
- resources
- exportaciones
- endpoints de dashboards
- endpoints operativos

Determina dónde el backend hace más trabajo del necesario para responder.

### Fase 6. Auditoría de caché, colas, jobs, scheduler y trabajo offline

Revisa:

- uso actual y potencial de caché
- colas y jobs existentes
- tareas que siguen en sync
- limpieza de archivos/logs/tokens
- pipeline de onboarding
- exportaciones y generación documental

### Fase 7. Auditoría de configuración y entorno

Revisa:

- `composer.json`
- config
- `.env.example`
- Docker/Sail
- PHP runtime
- Redis
- MySQL
- sesiones
- logs
- filesystem
- optimizaciones de despliegue

### Fase 8. Priorización de cuellos de botella

Clasifica los hallazgos por:

- impacto real
- probabilidad
- coste técnico actual
- facilidad de mitigación
- riesgo del cambio

### Fase 9. Plan de acción

Propón acciones:

- concretas
- justificadas
- seguras
- ordenadas por impacto/esfuerzo

Debes diferenciar claramente:

- quick wins
- mejoras medias
- cambios más profundos pero rentables

## Archivos y zonas que debes inspeccionar sí o sí

Como mínimo, debes revisar de forma explícita estas áreas del repositorio:

### Núcleo y configuración

- `composer.json`
- `composer.lock`
- `bootstrap/app.php`
- `config/app.php`
- `config/database.php`
- `config/cache.php`
- `config/queue.php`
- `config/logging.php`
- `config/session.php`
- `config/cors.php`
- `config/sanctum.php`
- `.env.example`
- `docker-compose.yml`
- `Dockerfile`

### HTTP / request lifecycle

- `app/Http/Kernel.php`
- `app/Http/Middleware/TenantMiddleware.php`
- `app/Http/Middleware/DynamicCorsMiddleware.php`
- middlewares de auth/role/feature/actor/superadmin
- `routes/api.php`

### Providers y bootstrap

- `app/Providers/AppServiceProvider.php`
- `app/Providers/AuthServiceProvider.php`
- `app/Providers/EventServiceProvider.php`
- `app/Providers/RouteServiceProvider.php`
- `bootstrap/cache/packages.php`
- `bootstrap/cache/services.php`

### Dominio con potencial de alto coste

- `app/Models`
- `app/Services`
- `app/Http/Controllers/v2`
- `app/Http/Resources/v2`
- `app/Jobs`
- `app/Console/Kernel.php`
- `app/Console/Commands`

### Zonas especialmente sensibles en este proyecto

- autenticación por magic link / OTP / Sanctum
- feature flags por tenant
- onboarding y migración de tenants
- superadmin, impersonación y observabilidad
- generación de PDF
- exportaciones Excel
- extracción de PDF con IA/parsers
- listados de pedidos
- inventario de palets y cajas
- producción y consumos
- recepciones de materia prima
- CRM y dashboard

## Nivel de exigencia

La auditoría debe ser exigente y no superficial.

### Reglas obligatorias de calidad

- No des consejos vagos.
- No uses frases blandas como “podrías revisar”.
- Cada hallazgo debe tener **justificación técnica**.
- Cuando sea posible, señala:
  - archivo
  - clase
  - método
  - provider
  - middleware
  - dependencia
  - query
  - patrón concreto
- Explica por qué cada punto afecta realmente a:
  - latencia
  - bootstrap
  - CPU
  - memoria
  - I/O
  - escalabilidad
  - throughput
  - DX
- Diferencia claramente entre:
  - **hallazgo comprobado**
  - **hipótesis razonable**
  - **riesgo potencial**
- Prioriza cambios seguros y con retorno real.
- Evita convertir la respuesta en una clase teórica sobre performance.
- Habla del proyecto real, no de Laravel en abstracto.

## Formato de salida obligatorio

La salida final de la auditoría debe venir exactamente estructurada en secciones claras como estas:

### 1. Resumen ejecutivo

- diagnóstico general del backend
- principales sospechas confirmadas
- qué está penalizando más el rendimiento
- nivel de riesgo actual

### 2. Mapa técnico del stack auditado

- stack detectado
- multitenancy
- auth
- queue/cache/logs
- contenedores
- librerías pesadas relevantes
- puntos de bootstrap relevantes

### 3. Hallazgos críticos

Para cada hallazgo crítico incluye:

- título
- severidad
- impacto
- evidencia
- archivos implicados
- por qué afecta al rendimiento
- recomendación concreta
- riesgo de tocarlo

### 4. Hallazgos importantes

Mismo formato que en críticos.

### 5. Hallazgos menores

Mismo formato resumido.

### 6. Dependencias o paquetes sospechosos

Incluye:

- paquete
- rol actual aparente
- motivo de sospecha
- coste técnico potencial
- si parece redundante, infrautilizado o demasiado pesado

### 7. Providers, middlewares, bootstrap y ciclo global

Enumera:

- providers problemáticos
- middlewares globales o de grupo costosos
- boot logic pesada
- helpers/autoload con sobrecoste
- resolución dinámica costosa

### 8. Problemas de Eloquent y SQL

Lista:

- N+1 detectados o muy probables
- relaciones excesivas
- paginaciones pesadas
- `whereHas`/joins problemáticos
- consultas repetidas
- zonas que cargan demasiados datos
- sugerencias concretas de mejora

### 9. Problemas de endpoints, resources y serialización

Lista:

- endpoints pesados
- dashboards costosos
- resources sobredimensionados
- exportaciones/documentos con alto coste
- payloads innecesariamente grandes

### 10. Problemas de caché, colas y trabajo asíncrono

Lista:

- oportunidades claras de caché
- caché incorrecta o insuficiente
- trabajo síncrono que debería ir a cola
- jobs ausentes o mejorables
- impacto del modo `sync`

### 11. Problemas de configuración y entorno

Lista:

- configuración que penaliza rendimiento
- inconsistencias entre entornos
- problemas en Docker/Sail
- riesgos de DX
- oportunidades de optimización operativa

### 12. Quick wins

Lista de mejoras:

- de bajo riesgo
- bajo esfuerzo
- alto retorno

### 13. Acciones recomendadas priorizadas

Presenta una tabla o lista ordenada por:

- impacto
- esfuerzo
- riesgo
- plazo sugerido

### 14. Riesgos y cautelas al aplicar cambios

Explica qué áreas requieren especial cuidado por posibles regresiones:

- multi-tenant
- auth
- serialización
- exports
- procesos batch
- integraciones documentales

### 15. Conclusión final

Debes cerrar con:

- diagnóstico sintetizado
- principales focos de sobrecoste
- orden recomendado de intervención

## Instrucciones finales de comportamiento

- Basa todo en el código real del repositorio.
- No inventes ni asumas componentes que no existan.
- Si algo no puedes comprobar, dilo explícitamente.
- Si una conclusión es inferida, márcala como inferencia.
- Si detectas contradicciones entre configuración y runtime, destácalas.
- Si hay coexistencia de múltiples librerías para un mismo problema, analízala con detalle.
- Si encuentras patrones costosos repetidos en varios módulos, no los menciones solo una vez: cuantifica su extensión.
- Señala especialmente cualquier cosa que pueda hacer que este backend se sienta “pesado” incluso sin tráfico alto.

## Qué espero de ti

Quiero una auditoría de verdad, no una lista de obviedades.

Necesito que identifiques:

- qué está costando tiempo por request
- qué está costando memoria
- qué está costando complejidad técnica sin retorno
- qué puede impedir escalar bien
- qué está perjudicando la velocidad del equipo en local

Si una recomendación no está conectada con un coste técnico real, no la incluyas.
