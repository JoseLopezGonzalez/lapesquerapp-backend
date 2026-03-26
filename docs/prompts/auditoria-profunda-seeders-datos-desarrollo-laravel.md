# Auditoría profunda del sistema de seeders, factories y dataset de desarrollo

## Rol que debes asumir

Actúa como un **Senior Laravel Backend Engineer** especializado en:

- **seeders complejos**
- **factories avanzadas**
- **modelado de datos de prueba**
- **simulación de entornos reales**
- **consistencia relacional**
- **multi-tenancy**
- **cobertura de edge cases**
- **preparación de entornos de desarrollo ricos y mantenibles**

Tu trabajo es realizar una **auditoría profunda del sistema actual de seeders y factories** de este proyecto Laravel, y proponer un rediseño o mejora para que el entorno de desarrollo quede sembrado con un dataset **muy realista, amplio, útil y mantenible**.

## Objetivo exacto

Quiero que audites el sistema actual de datos de desarrollo porque el objetivo no es simplemente “tener datos para que arranque”, sino dejar un entorno dev que:

- se parezca mucho a un entorno real
- permita recorrer flujos importantes del sistema
- sirva para demos internas
- sirva para QA manual
- sirva para probar formularios, listados, filtros, estados y permisos
- cubra combinaciones normales, raras y borde
- tenga relaciones creíbles y cronologías plausibles
- sea mantenible a futuro

Necesito que detectes qué carencias tiene el sistema actual de seeders/factories y cómo debería evolucionar para convertirse en un **sistema maestro de datos de desarrollo** de verdad.

## Contexto real del proyecto que debes usar

Debes basarte en el repositorio real y, como punto de partida, asumir al menos este contexto técnico comprobable:

- Proyecto **Laravel 10**.
- Aplicación **API-first** orientada a ERP multiempresa del sector pesquero.
- Arquitectura **multi-tenant database-per-tenant**:
  - base central
  - bases por tenant
  - resolución tenant por `X-Tenant`
- Existen dos niveles claros de siembra:
  - **central**: `DatabaseSeeder.php`
  - **tenant**: `TenantDatabaseSeeder.php`
- Existe también un seeder separado para onboarding/producción:
  - `database/seeders/TenantProductionSeeder.php`
- El flujo de siembra de tenant está integrado en comandos reales del proyecto:
  - `tenants:migrate --seed`
  - `tenants:seed`
  - `tenants:dev-migrate --seed`
- `TenantDatabaseSeeder` actúa hoy como seeder de desarrollo amplio para tenant.
- `TenantProductionSeeder` actúa como seeder mínimo/seguro para onboarding de nuevos tenants.
- `DatabaseSeeder.php` central es actualmente muy pequeño y apenas crea un conjunto mínimo.
- Hay documentación específica del propio proyecto sobre estándares de seeders:
  - `docs/instrucciones/seeders-estandares-desarrollo.md`
- El repositorio contiene actualmente:
  - **70 modelos**
  - **43 seeders**
  - **8 factories**
  - **172 migraciones** totales
  - **157 migraciones de tenant** en `database/migrations/companies`
- La cobertura de factories es muy inferior al número de modelos, así que debes evaluar si el sistema actual está demasiado apoyado en seeders manuales y poco en factories reutilizables.
- Los seeders tenant actuales cubren varias áreas:
  - usuarios internos
  - operario/repartidor de autoventa
  - catálogos de producto
  - zonas
  - especies
  - procesos
  - países
  - formas de pago
  - incoterms
  - transportes
  - comerciales
  - impuestos
  - proveedores
  - zonas FAO
  - almacenes
  - usuarios externos
  - empleados
  - fichajes
  - clientes
  - productos
  - recepciones
  - despachos
  - pedidos
  - líneas de pedido
  - cajas
  - palets
  - timeline de palets
- También hay seeders específicos para:
  - `FeatureFlagSeeder`
  - `DevTenantFeatureOverridesSeeder`
  - `SuperadminUserSeeder`
- El código del proyecto ya soporta módulos adicionales que debes verificar si están cubiertos o no por datos de desarrollo útiles:
  - CRM (`Prospect`, `ProspectContact`, `CommercialInteraction`, `AgendaAction`)
  - ofertas (`Offer`, `OfferLine`)
  - rutas (`DeliveryRoute`, `RouteTemplate`, `RouteStop`, `RouteTemplateStop`)
  - incidencias (`Incident`)
  - etiquetas (`Label`)
  - costes (`CostCatalog`, `ProductionCost`)
  - producción avanzada (`Production`, `ProductionRecord`, `ProductionInput`, `ProductionOutput`, `ProductionOutputConsumption`, `ProductionOutputSource`)
  - configuración (`Setting`)
  - superadmin y overrides por tenant
- En el estado actual ya hay seeders inspirados en datos reales o realistas del dominio:
  - productos y clientes con referencias tipo Brisamar / sector pesquero
  - uso de `Faker::create('es_ES')`
  - seeders con comments que hablan de “backup_reduced” o variantes reales
- El volumen sembrado actual parece moderado o limitado en varias entidades operativas, por ejemplo:
  - pedidos: objetivo de 20
  - palets: objetivo de 20
  - cajas: objetivo de 30
  - recepciones: objetivo de 10
  - despachos: objetivo de 10
  - fichajes: objetivo de 40
- Hay seeders que usan `firstOrCreate` / `updateOrCreate`, pero otros siguen creando datos con `create()` para entidades transaccionales.
- `RoleSeeder` hoy es **no-op**, porque los roles viven en enum y no en tabla.
- El proyecto usa autenticación por magic link / OTP, por lo que los usuarios de desarrollo no dependen de un sistema clásico de passwords.
- En tests ya aparecen señales de que el sistema actual de seeders/factories no siempre es suficientemente reutilizable:
  - `tests/Feature/StockBlockApiTest.php` evita usar `ProductSeeder` directamente en PHPUnit y crea parte del dataset manualmente
  - eso sugiere revisar la compatibilidad real del sistema de seeders con tests, factories y flujos automatizados

Debes utilizar este contexto como base y ampliarlo leyendo el repositorio real antes de concluir nada.

## Qué se quiere conseguir con la auditoría

La auditoría debe terminar en una propuesta para que este proyecto disponga de un sistema de datos de desarrollo que:

- simule un entorno real
- no se quede en datos mínimos
- cubra relaciones completas
- incluya históricos y registros activos
- incluya estados distintos
- incluya combinaciones normales y poco frecuentes
- incluya edge cases útiles
- permita probar la app con naturalidad
- sirva tanto para desarrollo como para demos y QA manual
- siga siendo mantenible cuando el sistema crezca

## Alcance obligatorio

Debes revisar en profundidad todo lo relacionado con seeders, factories y datasets de desarrollo.

### 1. Auditoría del sistema actual de seeders

Analiza:

- seeders existentes
- seeders centrales
- seeders tenant
- seeders de onboarding/producción
- orden real de ejecución
- clases llamadas desde `DatabaseSeeder`
- clases llamadas desde `TenantDatabaseSeeder`
- seeders que no están integrados en el flujo principal
- seeders que parecen legacy o parciales
- qué tablas cubre hoy el sistema
- qué tablas no cubre
- si la cobertura actual sirve de verdad para trabajar en desarrollo

### 2. Auditoría del sistema actual de factories

Revisa:

- factories existentes
- factories ausentes
- si las factories actuales son demasiado básicas
- si faltan states útiles
- si las factories reflejan estados reales del sistema
- si el proyecto depende demasiado de seeders manuales por falta de factories ricas
- si las factories permiten composición útil entre entidades relacionadas

### 3. Cobertura funcional del dataset actual

Evalúa si los datos actuales cubren correctamente:

- entidades maestras
- catálogos
- entidades operativas
- entidades transaccionales
- relaciones entre módulos
- históricos
- datos activos
- datos inactivos
- datos completos
- datos incompletos
- datos conflictivos razonables
- combinaciones útiles para probar filtros, buscadores, listados y formularios

### 4. Realismo del dataset

Debes evaluar si el dataset actual parece un sistema real o una demo vacía.

Analiza:

- nombres plausibles
- referencias plausibles
- códigos plausibles
- nomenclatura del dominio pesquero
- distribución temporal de fechas
- proporciones creíbles entre estados
- variedad real entre registros
- coherencia entre maestros y transaccionales
- riqueza de textos, notas y referencias
- utilidad real en pantallas y operaciones

### 5. Variantes y combinatorias

Revisa si el sistema actual cubre:

- todos los estados importantes
- combinaciones entre estados
- variantes por tipo de entidad
- roles distintos
- permisos y visibilidad
- tenants distintos si aplica
- registros recientes y antiguos
- registros completos e incompletos
- casos normales
- casos raros
- casos borde
- anomalías razonables
- situaciones que ayuden a detectar bugs de UI, backend o validación

### 6. Coherencia relacional e integridad

Audita:

- claves foráneas
- coherencia entre padres e hijos
- secuencias temporales plausibles
- compatibilidad entre estados de entidades relacionadas
- realismo de relaciones uno a muchos
- realismo de relaciones muchos a muchos
- jerarquías si aplica
- separación entre datos “normales” y edge cases

### 7. Organización técnica del sistema de seeders

Evalúa:

- estructura actual de seeders
- legibilidad
- mantenibilidad
- modularidad
- reutilización
- composición por módulos
- orden de ejecución
- facilidad para resembrar el entorno
- idempotencia cuando aplique
- coexistencia entre seeder de desarrollo y seeder de producción/onboarding
- facilidad para ampliar el sistema más adelante

### 8. Entorno dev parecido a producción

Debes responder:

- qué le falta al entorno actual para sentirse real
- qué módulos están poco representados
- qué flujos no están bien simulados
- qué volumen sería razonable por entidad en local
- qué subconjunto base debe existir siempre
- qué escenarios adicionales conviene tener para demos o QA
- cómo equilibrar realismo y facilidad de uso

### 9. Casos especiales y visibilidad

Revisa si hay datos que permitan probar:

- roles distintos
- usuarios internos y externos
- permisos
- comerciales con clientes/pedidos propios
- repartidor/autoventa
- tenant con flags u overrides
- entidades con diferentes estados
- registros archivados, cerrados, cancelados, en curso, históricos o incompletos
- escenarios con incidencias o anomalías

### 10. Estrategia de mejora

Debes proponer con detalle:

- qué seeders rehacer
- qué seeders dividir
- qué seeders añadir
- qué factories enriquecer
- qué factories crear
- cómo estructurar un sistema base
- cómo estructurar escenarios avanzados
- cómo estructurar edge cases
- cómo dejar un flujo de siembra claro y sostenible

## Exclusiones obligatorias

La auditoría **no debe**:

- centrarse en rendimiento del backend salvo que afecte al uso práctico de los seeders
- convertirse en una auditoría general de lógica de negocio
- evaluar UX o diseño del producto
- convertirse en una auditoría genérica de clean code
- proponer cambios funcionales que no afecten al sistema de datos de desarrollo
- limitarse a decir “faltan más datos”
- quedarse en recomendaciones vagas sin concretar tablas, relaciones, escenarios o módulos

Si una observación no afecta directamente a:

- calidad del dataset
- utilidad del entorno dev
- coherencia de los datos
- cobertura de escenarios
- mantenibilidad del sistema de seeders/factories

entonces debe quedar fuera.

## Metodología obligatoria

Debes trabajar por fases y reflejar esa metodología en tu respuesta.

### Fase 1. Inventario real del dominio sembrable

Haz un inventario real de:

- modelos
- módulos
- tablas
- relaciones
- entidades maestras
- entidades transaccionales
- entidades tenant
- entidades centrales

No inventes nada. Todo debe salir del repositorio.

### Fase 2. Mapeo del sistema actual de seeders y factories

Mapea:

- `DatabaseSeeder`
- `TenantDatabaseSeeder`
- `TenantProductionSeeder`
- seeders independientes
- factories existentes
- comandos Artisan de seed
- documentación interna sobre seeders

### Fase 3. Detección de cobertura y huecos

Determina:

- qué modelos/tablas están cubiertos
- cuáles no
- cuáles están solo cubiertos de forma superficial
- cuáles tienen datos útiles
- cuáles tienen datos triviales
- qué módulos enteros carecen de dataset real de trabajo

### Fase 4. Evaluación del realismo y la utilidad del dataset actual

Valora si los datos actuales sirven de verdad para:

- desarrollar
- probar filtros
- probar validaciones
- recorrer flujos
- hacer demos
- ejercer QA manual

### Fase 5. Evaluación de variantes, estados y edge cases

Analiza si existen:

- estados relevantes
- cronologías útiles
- datos incompletos
- conflictos razonables
- usuarios con roles variados
- relaciones cruzadas suficientes
- escenarios raros pero plausibles

### Fase 6. Propuesta de rediseño del sistema de seeders

Define cómo debería organizarse el sistema final:

- seeders base
- seeders de catálogos
- seeders de maestros
- seeders transaccionales
- seeders históricos
- seeders de edge cases
- seeders de demo
- seeders tenant
- seeders central/superadmin

### Fase 7. Propuesta de rediseño de factories

Especifica:

- qué factories crear
- qué states añadir
- qué relaciones deben ser componibles
- qué combinaciones deben poder generarse desde factories
- qué debe quedar fijo y qué aleatorio controlado

### Fase 8. Plan de implantación priorizado

Propón un plan en fases para evolucionar desde el estado actual al sistema objetivo.

### Fase 9. Visión del dataset final objetivo

Describe cómo debería quedar el entorno dev final:

- qué entidades base siempre existirán
- qué volumen habrá
- qué combinaciones habrá
- qué escenarios especiales habrá
- qué tenants o perfiles representativos habrá

## Archivos y zonas que debes revisar sí o sí

Debes inspeccionar explícitamente, como mínimo:

### Seeders y factories

- `database/seeders/DatabaseSeeder.php`
- `database/seeders/TenantDatabaseSeeder.php`
- `database/seeders/TenantProductionSeeder.php`
- `database/seeders/*.php`
- `database/factories/*.php`

### Comandos de siembra y migración tenant

- `app/Console/Commands/MigrateTenants.php`
- `app/Console/Commands/SeedTenants.php`
- `app/Console/Commands/TenantDevMigrate.php`
- cualquier otro comando relacionado con onboarding o seed

### Dominio y relaciones

- `app/Models`
- migraciones en `database/migrations`
- migraciones tenant en `database/migrations/companies`

### Tests y señales de fricción

- tests que montan datos manualmente
- tests que evitan usar ciertos seeders
- helpers de test que dupliquen dataset mínimo

### Documentación del proyecto

- `README.md`
- `docs/instrucciones/seeders-estandares-desarrollo.md`
- documentación sobre multi-tenancy y flujo tenant
- documentación sobre SaaS/onboarding si afecta a seeders

## Zonas del proyecto que merecen atención especial

Debes prestar especial atención a la cobertura real de datos en estos dominios:

- usuarios internos
- usuarios externos
- roles
- comerciales
- field operators / autoventa
- clientes
- proveedores
- productos
- especies
- familias y categorías
- almacenes
- palets
- cajas
- pedidos
- líneas de pedido
- recepciones
- despachos
- empleados y fichajes
- configuración y settings
- feature flags y overrides
- superadmin

Y además debes comprobar si existen o faltan datasets útiles para módulos que el código sí soporta:

- CRM
- prospectos
- contactos
- interacciones comerciales
- agenda
- ofertas
- incidencias
- rutas y plantillas de ruta
- etiquetas
- producción avanzada
- costes

## Preguntas que debes responder

Tu auditoría debe responder con claridad:

- ¿El sistema actual de seeders sirve de verdad para trabajar en desarrollo?
- ¿Qué módulos están bien cubiertos y cuáles no?
- ¿Qué seeders actuales son demasiado pobres o demasiado “demo”?
- ¿Qué entidades tienen datos creíbles pero insuficientes en volumen o variantes?
- ¿Qué relaciones importantes no están siendo sembradas?
- ¿Qué casos límite faltan?
- ¿Dónde faltan factories reutilizables?
- ¿Qué partes del dataset actual no reflejan un sistema real?
- ¿Cómo debería quedar un sistema final de datos dev realmente útil?

## Nivel de exigencia

La auditoría debe ser exigente y concreta.

### Reglas obligatorias de calidad

- Nada de consejos vagos.
- Nada de “sería bueno meter más datos”.
- Cada hallazgo debe ir con **justificación concreta**.
- Señala, cuando sea posible:
  - seeder
  - factory
  - modelo
  - tabla
  - relación
  - módulo
  - comando
  - flujo
- Diferencia claramente entre:
  - **hallazgo comprobado**
  - **inferencia razonable**
  - **riesgo potencial**
- Razona siempre por qué un dato faltante o pobre perjudica el uso real del entorno dev.
- Prioriza utilidad real para desarrollo, demo y QA.
- Propón una estructura mantenible, no solo una colección de seeders sueltos.

## Formato de salida obligatorio

La salida final de la auditoría debe venir en estas secciones:

### 1. Resumen ejecutivo

- diagnóstico del estado actual
- nivel real de madurez del sistema de seeders
- si hoy sirve solo para arrancar o también para trabajar de verdad

### 2. Estado actual del sistema de seeders y factories

- seeders centrales
- seeders tenant
- seeders de onboarding/producción
- factories existentes
- comandos de siembra
- flujo actual de uso

### 3. Cobertura actual por módulos y tablas

Debes indicar:

- qué está cubierto
- qué está cubierto de forma parcial
- qué no está cubierto

### 4. Carencias críticas

Para cada carencia crítica incluye:

- título
- gravedad
- evidencia
- modelos/tablas afectadas
- por qué perjudica el dataset dev
- recomendación concreta

### 5. Carencias importantes

Mismo formato que en críticas.

### 6. Variantes, estados y edge cases faltantes

Lista:

- estados no representados
- combinaciones no cubiertas
- usuarios/permisos faltantes
- cronologías no cubiertas
- edge cases útiles ausentes

### 7. Problemas de realismo del dataset actual

Lista:

- datos demasiado vacíos
- datos demasiado repetitivos
- proporciones poco creíbles
- nomenclatura artificial
- cronologías pobres
- falta de históricos
- falta de escenarios conflictivos razonables

### 8. Problemas de coherencia relacional

Lista:

- relaciones incompletas
- padres sin hijos cuando debería haberlos
- hijos sin variedad suficiente
- estados incompatibles
- secuencias temporales poco plausibles

### 9. Problemas de diseño técnico del sistema de seeders/factories

Lista:

- falta de modularidad
- factories insuficientes
- seeders demasiado acoplados
- seeders poco reutilizables
- incompatibilidades con tests
- duplicación de lógica de datos
- mezcla confusa entre datos base y datos demo

### 10. Propuesta de estructura ideal

Debes proponer la estructura objetivo del sistema, por ejemplo:

- seeders base imprescindibles
- seeders de catálogos
- seeders de entidades maestras
- seeders transaccionales
- seeders históricos
- seeders de edge cases
- seeders de demo
- seeders central/superadmin
- seeders tenant

### 11. Propuesta de factories enriquecidas

Incluye:

- factories a crear
- factories a rehacer
- states recomendados
- combinaciones que deben poder generarse
- relaciones que deben resolverse automáticamente

### 12. Recomendaciones concretas por módulo

Debes bajar al detalle por módulo relevante del proyecto.

### 13. Quick wins

Lista de mejoras de alto valor y bajo coste.

### 14. Riesgos o precauciones

Explica:

- qué escenarios complejos hay que sembrar con cuidado
- qué datos podrían confundir si no se separan bien
- cómo diferenciar dataset base de dataset extremo
- qué no debería mezclarse con el seed de onboarding/producción

### 15. Plan de implementación por fases

Propón un orden realista de ejecución.

### 16. Dataset final objetivo

Describe cómo debería verse el entorno dev final una vez implantado el rediseño.

### 17. Conclusión final

Debes cerrar con una visión clara del sistema deseado.

## Entregable esperado de la auditoría

Además del análisis, quiero que la auditoría deje muy clara una propuesta final de cómo debería quedar el sistema:

- seeders base imprescindibles
- seeders avanzados con escenarios reales
- seeders de edge cases
- factories enriquecidas
- orden de ejecución recomendado
- capas de volumen de datos
- flujo para regenerar fácilmente la base en dev
- separación clara entre:
  - onboarding/producción
  - desarrollo base
  - desarrollo avanzado
  - edge cases / QA / demo

## Instrucciones finales de comportamiento

- Basa todo en el código real del repositorio.
- No inventes módulos ni relaciones.
- Si algo no puedes comprobar, dilo.
- Si algo es inferencia, márcalo como inferencia.
- Si detectas contradicciones entre la documentación y el estado actual del código, destácalas.
- Si encuentras módulos presentes en modelos/rutas pero no en seeders/factories, señálalo expresamente.
- Si detectas que parte de los tests necesitan sembrar datos a mano por carencias del sistema actual, considéralo una señal importante.
- No te limites a decir “faltan seeders”; concreta exactamente qué falta, por qué importa y cómo debería cubrirse.

## Qué espero de ti

Quiero una auditoría útil de verdad para transformar este backend en un entorno de desarrollo sembrado con datos creíbles, variados y potentes.

Necesito que identifiques:

- qué hay hoy
- qué falta
- qué está mal planteado
- qué está bien pero corto
- cómo debe reorganizarse
- qué dataset final haría que esta aplicación se sienta realmente viva en desarrollo

Si una recomendación no mejora la calidad, utilidad o mantenibilidad del dataset de desarrollo, no la incluyas.
