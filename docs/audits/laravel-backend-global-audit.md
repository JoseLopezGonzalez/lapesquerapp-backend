# Auditoría Arquitectónica Global — Backend Laravel PesquerApp

**Fecha**: 2026-03-23  
**Alcance**: Backend Laravel 10, API v2, multi-tenant database-per-tenant, capa SaaS/superadmin  
**Tipo**: Auditoría principal integral basada en repo y validación local no invasiva

---

## 1. Executive Summary

PesquerApp Backend presenta una base arquitectónica claramente por encima del nivel "funciona pero duele". El proyecto combina un núcleo Laravel idiomático con separación real por `FormRequest`, `Policy`, `Resource` y una capa de `Services` ya muy extendida, especialmente en ventas, producción, listados operativos, CRM y SaaS. La madurez multi-tenant sigue siendo una fortaleza diferencial: el middleware tenant resuelve `X-Tenant`, conmuta la conexión a base de datos del tenant y la mayor parte del dominio usa `UsesTenantConnection` de forma consistente.

La foto actual, sin embargo, ya no encaja con la auditoría canónica previa en varios puntos importantes. Ya existen `Jobs` reales para onboarding y migraciones de tenants, hay una capa `superadmin` con superficie SaaS relevante, el sistema supera los `430` endpoints `api/v2`, y siguen presentes controladores grandes con lógica e inline validation en algunos bordes, especialmente en fichajes. La suite de tests es amplia en número de bloques, pero en el entorno revisado no es ejecutable end-to-end porque `php artisan test` falla al no poder conectar con MySQL en `127.0.0.1:3306`, lo que penaliza la nota de operabilidad y la fiabilidad de verificación local.

Conclusión ejecutiva: el backend está en una franja **7.8/10** global, con un core clásico estable y bien protegido, pero con una capa de plataforma SaaS y algunos bloques operativos todavía en fase de consolidación. Llegar a **9/10** no exige rehacer la arquitectura; exige cerrar deuda estructural concreta: endurecer el circuito de tests, seguir adelgazando controladores grandes, formalizar contratos/observabilidad de exportes y estadísticas, y definir una convención explícita tenant-aware para la ejecución asíncrona.

---

## 2. Identidad Arquitectónica del Proyecto

- **Arquitectura real**: Laravel MVC + capa de servicios de aplicación, sin DDD estricto ni repositorios genéricos.
- **API**: REST v2 con prefijo `api/v2`, mezcla de `apiResource` y endpoints de acción/reporte/exportación.
- **Multi-tenancy**: database-per-tenant resuelta por header `X-Tenant` y `TenantMiddleware`.
- **Seguridad**: `auth:sanctum`, middleware de rol, `Policies` por recurso, throttling en accesos públicos sensibles.
- **Plataforma SaaS**: bloque `superadmin` fuera del middleware tenant, con onboarding, impersonation, migrations panel, feature flags, observabilidad y alertas.
- **Operación**: Sail + `docker-compose.yml` con servicio `queue`, MySQL, Redis y Mailpit.

El proyecto ya no es solo "ERP tenant-aware"; es también una plataforma SaaS operable, aunque esa capa todavía no tiene la misma madurez homogénea que el core clásico.

---

## 3. Hallazgos Sistémicos Clave

### Fortalezas principales

- **Multi-tenancy sólido**: `TenantMiddleware` valida tenant y estado, conmuta `database.default` a `tenant` y reconecta la BD por request.
- **Uso amplio de componentes Laravel**: `185` Form Requests, `42` Policies, `51` API Resources, `63` Services y `71` controladores `v2`.
- **Cobertura funcional amplia por bloques**: `30` Feature tests y `4` Unit tests, con suites específicas para Auth, Stock, CRM, Settings, Documents, Superadmin, canal operativo y usuarios externos.
- **Bloques maduros**: ventas, inventario, recepciones, despachos, documentos y gran parte de producción conservan una estructura profesional.
- **Capa SaaS tangible**: existen `OnboardTenantJob` y `MigrateTenantJob`, además de servicios y controladores dedicados a la operación superadmin.

### Riesgos sistémicos

- **Controladores aún demasiado grandes**: `PunchController` (`459` líneas), `PalletController` (`310`), `OrderController` (`298`), `BoxesController` (`277`), `SpeciesController` (`264`), `StoreController` (`258`) y `AuthController` (`258`).
- **Validación inline residual**: sigue habiendo `request->validate()` en puntos relevantes como `PunchController`, `SpeciesController`, `CaptureZoneController` y varios controladores `superadmin`.
- **Autorización gruesa aún presente en rutas**: el grupo principal `v2` mantiene un comentario explícito de transición "por ahora todas accesibles para todos los roles", señal de que el diseño fino depende mucho de `Policies` y no siempre de segmentación de rutas.
- **Operabilidad/testability incompleta**: `php artisan test` no es reproducible en este entorno por dependencia de MySQL no levantado/accesible, con `11` fallos, `48` warnings y `222` tests saltados.
- **Asincronía tenant-aware no formalizada como patrón transversal**: los jobs SaaS existentes resuelven el tenant, pero la convención aún no está documentada como estándar reutilizable del sistema.

---

## 4. Evaluación por Dimensión

| Dimensión | Nota | Justificación |
|-----------|------|---------------|
| Multi-tenancy implementation maturity | **8.5/10** | Diseño database-per-tenant consistente, modelo `Tenant` central y dominio tenant-aware. Falta formalizar patrón transversal para jobs/cache/observabilidad. |
| Domain modeling clarity | **8.5/10** | El dominio ERP + CRM + SaaS está bien expresado en modelos y servicios, aunque algunos flujos complejos siguen repartidos entre controlador, servicio y modelo. |
| API design consistency | **7.5/10** | API rica y usable, pero con mezcla de recursos REST, acciones ad hoc, exports y contratos no formalizados por OpenAPI. |
| Testing coverage and strategy | **6/10** | Hay amplitud de suites por bloque, pero poca profundidad unitaria y la ejecución local actual no es confiable sin infraestructura adicional. |
| Deployment and DevOps practices | **6.5/10** | Docker/Sail, Redis, queue worker y cron en `Console\\Kernel`; pero `queue` y `cache` siguen con defaults conservadores y la reproducibilidad local/test no está cerrada. |
| Documentation quality | **7/10** | Buen nivel de documentación de arquitectura y circuito interno; falta contrato formal de API y actualizar más rápido la auditoría canónica. |
| Technical debt level | **7.5/10** | La deuda ya no es caótica, pero persisten hotspots concretos: controladores grandes, validación inline y bloques SaaS/operativos aún no plenamente cerrados. |
| Code quality and maintainability | **7.5/10** | El uso de `Services`, `Requests`, `Policies` y `Resources` es sólido. La mantenibilidad baja en controladores pesados y bloques con mucha superficie transaccional. |
| Performance and scalability | **7/10** | Hay señales sanas de eager loading y servicios de listado, pero faltan evidencias cerradas sobre índices, tiempos de respuesta y estrés en estadísticas/exports. |
| Security and authorization maturity | **8/10** | Tenant isolation y `Policies` bien implantados. El riesgo ya no es ausencia de autorización, sino mantener coherencia fina mientras crece la superficie SaaS y operativa. |

**Nota global actual**: **7.8/10**

### Qué separa al proyecto de un 9/10

- Suite de tests reproducible y útil en local/CI.
- Reducción real de controladores monstruo en los bloques restantes.
- Criterio uniforme para exports, estadísticas y errores operativos.
- Convención tenant-aware explícita para jobs, cache y observabilidad.
- Cierre de los bloques A.19-A.22 con auditoría y trazabilidad equivalente al core clásico.

### Qué separa al proyecto de un 10/10

- Contrato formal de API, métricas de rendimiento y cobertura cuantificada.
- Pipeline DevOps endurecido y validable desde repo.
- Homogeneidad estructural total entre core ERP, CRM y SaaS.
- Deuda residual reducida a pulido no estructural.

---

## 5. Componentes Estructurales Laravel

| Componente | Estado | Observación |
|-----------|--------|-------------|
| Services | Fuerte | `63` servicios repartidos entre núcleo, `v2`, `Production` y `Superadmin`. |
| Actions | Ausente | No hay `app/Actions`; no es un problema por sí mismo. |
| Jobs | Presente | Existen al menos `OnboardTenantJob` y `MigrateTenantJob`; la auditoría previa estaba desactualizada en este punto. |
| Events / Listeners | Débil | No hay capa explícita de eventos de dominio. Los side effects siguen mayoritariamente síncronos. |
| Form Requests | Fuerte | `185` clases; gran parte de la API está validada así. |
| Policies / Gates | Fuerte | `42` policies registradas y uso amplio de `authorize()` en controladores `v2`. |
| API Resources | Fuerte | `51` resources; serialización más consistente que en auditorías previas. |
| Middleware | Fuerte | `tenant`, `superadmin`, `actor`, `external.active`, CORS dinámico y `LogActivity`. |
| DTOs / Data objects | Ausente | El sistema resuelve esto con `Request + Resource + Service`. |

Valoración: la base Laravel es profesional y reconocible. La mejora prioritaria no es "meter más patrones", sino reducir inconsistencias donde el proyecto aún no los aplica de forma homogénea.

---

## 6. Lógica de Negocio, Transacciones y Side Effects

- **Ventas**: `OrderController` ya delega bastante en `OrderListService`, `OrderStoreService`, `OrderUpdateService` y `OrderDetailService`, pero conserva reglas de ownership comercial y algunos side effects en controlador.
- **Fichajes**: mezcla de dashboard, calendario, estadísticas, bulk, NFC y manual punch dentro de un único controlador todavía muy ancho.
- **Producción**: el reparto por servicios especializados es una fortaleza; sigue siendo el dominio más complejo del core.
- **SaaS/superadmin**: onboarding, impersonation, migrations y feature flags ya forman un subdominio con identidad propia.
- **Side effects**: siguen predominantemente síncronos en exportes, PDFs, emails y ciertos flujos de CRM; eso simplifica hoy, pero deja deuda de capacidad si crece la carga.

La lógica de negocio ya no está desperdigada "sin remedio", pero sí existen dominios donde el controlador continúa actuando como mini-application-service.

---

## 7. Rendimiento y Escalabilidad

### Señales positivas

- Servicios de listado y estadísticas dedicados en varios bloques.
- Uso frecuente de `with(...)` y queries especializadas en listados pesados.
- Redis y worker de cola previstos en `docker-compose.yml`.
- Conmutación tenant por request simple y explícita.

### Hotspots observados

- `PunchController` concentra demasiados caminos de ejecución en un solo borde HTTP.
- `OrderController`, `PalletController`, `BoxesController`, `StoreController` y `SpeciesController` aún son candidatos claros a seguir adelgazando.
- Estadísticas, dashboards y exports siguen siendo los principales candidatos a problemas de índices o N+1 ocultos.
- `TenantMiddleware` hace `DB::purge()` + `DB::reconnect()` en cada request; es correcto para este modelo, pero debe vigilarse si el volumen crece o si se introducen workers de larga vida.

### Riesgo operativo relevante

- La configuración base del repo deja `QUEUE_CONNECTION=sync` y `CACHE_DRIVER=file` por defecto. Eso no es malo en dev, pero impide leer el proyecto como "production-ready por defecto" sin endurecimiento por entorno.

---

## 8. Seguridad y Autorización

- **Tenant isolation**: fuerte; el tenant se resuelve desde cabecera, se valida el estado y el dominio usa conexión `tenant`.
- **Policies**: implantación amplia y útil; la seguridad fina ya depende más de políticas que de simples middlewares de rol.
- **Rutas públicas**: `v2/public/tenant/{subdomain}` y aprobación/rechazo de impersonation firmada están claramente separadas.
- **Superadmin**: existe middleware específico con modelo de token distinto, lo cual aísla bien esta superficie.
- **Deuda residual**: mantener la coherencia entre middleware de rol, actores internos/externos y políticas en bloques nuevos.

No he detectado un problema sistémico actual de fuga cross-tenant en el código revisado. El riesgo ahora está en la complejidad creciente de la plataforma, no en una base insegura.

---

## 9. Testing y Runtime

### Estado del testing

- `30` suites Feature y `4` Unit.
- Cobertura visible en Auth, Stock, CRM, Settings, Documents, Superadmin, canal operativo, usuarios externos y fichajes.
- Cobertura unitaria todavía estrecha y muy concentrada en `Order*Service`.

### Resultado de validación local

- `php artisan test` ejecutado el **23 de marzo de 2026** en este entorno termina con:
  - `11` fallos
  - `48` warnings
  - `222` tests saltados
  - causa dominante: **MySQL no accesible en `127.0.0.1:3306`**
- Esto no prueba una regresión funcional de negocio, pero sí evidencia que el circuito de verificación local no está autocontenido ni suficientemente robusto.

### Runtime / producción

- `docker-compose.yml` define app, queue worker, MySQL, Redis y Mailpit.
- `Console\\Kernel` agenda limpieza de PDFs, cleanup de magic tokens y tareas SaaS de seguridad/onboarding.
- El repo no expone todavía una configuración cerrada de PHP-FPM/OPcache/supervisor, por lo que la nota de runtime debe seguir siendo prudente.

---

## 10. Evaluación por Bloques

| Bloque | Nota | Estado actual | Fortalezas | Riesgos / gap principal | Prioridad |
|--------|------|---------------|------------|--------------------------|-----------|
| A.1 Auth + Roles/Permisos | 9/10 | Maduro | Policies, throttling, auth flows ricos | Más cobertura unitaria y homogeneización final | P2 |
| A.2 Ventas / Pedidos | 9/10 | Maduro | Buen uso de servicios y recursos | Contratos de reporting/export y variantes | P2 |
| A.3 Inventario / Stock | 10/10 | Referencia interna | Bloque más consistente del core | Mantener estándar y evitar regresiones | P3 |
| A.4 Recepciones | 9/10 | Maduro | Bulk y estadísticas ya estructurados | Stress/performance en bulk y export | P2 |
| A.5 Despachos de Cebo | 9/10 | Maduro | Cohesión buena y tests de bloque | Variantes de export y carga | P2 |
| A.6 Producción | 9/10 | Complejo pero sólido | Servicios especializados y policies | Complejidad inherente, trazabilidad y costes | P1 |
| A.7 Productos y maestros | 9/10 | Maduro | Requests, policies y opciones | Filtros/opciones y cobertura fina | P2 |
| A.8 Catálogos transaccionales | 9/10 | Estable | CRUDs coherentes y encapsulados | Pulido menor | P3 |
| A.9 Proveedores + Liquidaciones | 9/10 | Estable | Bloque coherente y con pruebas | Casos complejos de liquidación | P2 |
| A.10 Etiquetas | 9/10 | Estable | Simple y bien contenido | Pulido menor | P3 |
| A.11 Fichajes / Control horario | 9/10 | Funcional con deuda localizada | Tests amplios y servicios de apoyo | `PunchController` aún demasiado ancho e inline validation | P1 |
| A.12 Estadísticas e informes | 9/10 | Bueno con riesgo operativo | Capa dedicada por dominio | Índices, tiempos y consistencia de export/report | P1 |
| A.13 Configuración por tenant | 9/10 | Robusto | `Setting` model + servicio | Mantener coherencia y evitar volver a acceso ad hoc | P2 |
| A.14 Sistema / usuarios / logs | 9/10 | Maduro | Muy alineado con A.1 | No duplicar deuda de auth | P3 |
| A.15 Documentos / PDF / Excel | 9/10 | Amplio y útil | Gran cobertura funcional | Contrato uniforme de errores y asíncronía futura | P1 |
| A.16 Tenants públicos y resolución tenant | 9/10 | Sólido | Superficie pequeña y crítica bien resuelta | Mantener mínimo y seguro | P2 |
| A.17 Infraestructura API | 9/10 | Estable | Health y endpoints técnicos claros | Mantenimiento | P3 |
| A.18 Utilidades PDF / extracción | 9/10 | Estable | Bloque acotado | Límites de uso y consumo | P3 |
| A.19 CRM comercial | 8.5/10 | Buen nivel, no totalmente cerrado | Tests amplios y servicios dedicados | Estados, conversión y side effects comerciales | P1 |
| A.20 Canal operativo / Autoventa / Reparto | 8.5/10 | Bien encaminado | Buen set de rutas y tests | Límites de acceso y sincronización ruta-pedido | P1 |
| A.21 Usuarios externos | 8.5/10 | Casi cerrado | Identidad propia y tests | Consolidar onboarding y reglas actor externo | P1 |
| A.22 Superadmin SaaS | 8.5/10 | Estratégico, aún consolidándose | Jobs, servicios, rutas y tests propios | Hardening operacional, observabilidad y patrón tenant-aware transversal | P0 |

---

## 11. Top 5 Riesgos Sistémicos

1. **A.22 Superadmin SaaS aún no tiene la misma madurez homogénea que el core clásico**.
2. **Circuito de tests no reproducible localmente sin infraestructura externa levantada**.
3. **Controladores grandes en bordes críticos, especialmente fichajes y operaciones de almacén**.
4. **Exports/estadísticas/documentos como superficie principal de deuda de rendimiento y contrato**.
5. **Patrón asíncrono tenant-aware todavía implícito, no formalizado como estándar del backend**.

---

## 12. Top 5 Mejoras de Mayor Impacto

1. Convertir la ejecución de tests en un flujo fiable de repo/CI, con bootstrap claro de MySQL tenant y central.
2. Cerrar A.22 Superadmin SaaS con auditoría e implementación por sub-bloques: onboarding, migrations, impersonation, observability y feature flags.
3. Seguir adelgazando `PunchController` y otros controladores hotspot con `FormRequest` y servicios más finos.
4. Formalizar el contrato de exports/estadísticas/documentos y medir queries/índices de los caminos pesados.
5. Documentar el patrón tenant-aware para jobs, cache keys y tareas operativas.

---

## 13. Quick Wins de Bajo Riesgo

- Migrar la validación inline restante de `PunchController`, `SpeciesController`, `CaptureZoneController` y controladores `superadmin` a `FormRequest`.
- Añadir una guía operativa breve para ejecutar tests en local con MySQL/tenant listos.
- Crear una convención documental simple para jobs tenant-aware.
- Homogeneizar respuestas de error en exports y documentos.
- Medir y documentar los endpoints más pesados de estadísticas antes de refactorar.

---

## 14. Roadmap Recomendado para Llegar a 9/10

1. **Operabilidad y verificación**
   - hacer reproducible `php artisan test`
   - fijar comandos soportados para entorno host vs Sail
2. **Consolidación SaaS**
   - auditar/implementar A.22 por sub-bloques
   - reforzar observabilidad y retry/failure semantics de jobs
3. **Refactor estructural focalizado**
   - adelgazar controladores hotspot
   - mover validación residual a Requests
4. **Performance y contratos**
   - revisar índices y tiempos en dashboards, rankings, exports y PDFs
   - estandarizar respuestas y límites operativos
5. **Cierre documental**
   - mantener auditoría canónica sincronizada
   - dejar matriz de prioridad por bloque como fuente de ejecución

---

## 15. Cierre Obligatorio

1. **Nota global actual**: **7.8/10**
2. **Nota potencial tras quick wins**: **8.3/10**
3. **Nota potencial tras plan completo**: **9/10**
4. **Secuencia recomendada de ejecución**: tests/operabilidad -> A.22 SaaS -> controladores hotspot -> performance/contracts -> cierre documental
5. **Criterios objetivos para declarar el backend en 9/10**:
   - tests reproducibles y útiles
   - A.19-A.22 cerrados con trazabilidad equivalente al core
   - hotspots de controlador reducidos
   - exports/estadísticas endurecidos con medición
   - convención tenant-aware documentada para asincronía y operación

---

## Evidencia principal consultada

- `routes/api.php`
- `app/Http/Middleware/TenantMiddleware.php`
- `app/Http/Kernel.php`
- `app/Providers/AuthServiceProvider.php`
- `app/Http/Controllers/v2/*`
- `app/Jobs/*`
- `app/Services/*`
- `app/Models/*`
- `config/database.php`, `config/cache.php`, `config/queue.php`, `config/sanctum.php`
- `docker-compose.yml`, `Dockerfile`
- `tests/Feature/*`, `tests/Unit/*`

Esta auditoría sustituye la síntesis canónica previa y se apoya en una corrida de revisión local fechada el **23 de marzo de 2026**.
