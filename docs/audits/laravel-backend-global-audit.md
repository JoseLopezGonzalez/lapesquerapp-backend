# Auditoría Arquitectónica Global — Backend Laravel PesquerApp

**Fecha**: 2026-02-14  
**Alcance**: Backend Laravel 10, multi-tenant, API v2  
**Tipo**: Auditoría arquitectónica y sistémica (sin refactors ni revisión línea a línea)

---

## 1. Resumen Ejecutivo

El backend de PesquerApp es una aplicación Laravel 10 multi-tenant con **una base de datos por tenant**, API REST v2 consumida por un frontend Next.js 16, y dominios de negocio claros: pedidos, recepciones de materia prima, despachos de cebo, producción, inventario (cajas/palets/almacenes), fichajes y catálogos del sector pesquero. La arquitectura es **coherente** con un estilo **MVC + capa de servicios en dominios complejos**: controladores delgados en muchos recursos, Form Requests, API Resources y servicios para producción, estadísticas, PDF y correo. No se sigue un patrón Repository ni DDD estricto; la identidad es **Laravel idiomático con servicios de aplicación** donde la lógica compleja se extrae a clases de servicio.

**Fortalezas principales**: Aislamiento tenant por base de datos, documentación de arquitectura multi-tenant, manejo de excepciones API orientado al usuario, uso consistente de `UsesTenantConnection` en modelos de negocio, y comandos artesanales para migrar/sembrar tenants. **Riesgos estructurales**: ausencia de políticas de autorización (solo middleware por rol), uso directo de `DB::connection('tenant')->table()` en varios controladores (bypass de Eloquent y posible fuga de contexto), controladores muy gruesos en pedidos y fichajes, y cobertura de tests prácticamente nula. La evolución recomendada es incremental: introducir políticas y refinar la capa de acceso a datos sin reescribir el sistema.

---

## 2. Identidad Arquitectónica del Proyecto

El proyecto **no** sigue un checklist rígido de patrones; implícitamente adopta:

- **Arquitectura**: MVC + **Application Service Layer** en dominios complejos (Producción, estadísticas, envío de documentos).
- **Multi-tenancy**: **Database-per-tenant** con conexión dinámica `tenant` configurada por middleware a partir del header `X-Tenant`.
- **API**: REST v2 con prefijo `v2`, Form Requests para validación, API Resources para serialización, respuestas JSON consistentes.
- **Autenticación**: Sanctum (API tokens), roles por usuario (`role` en `users`), middleware `role:tecnico,administrador,...` para rutas protegidas.
- **Persistencia**: Eloquent con trait `UsesTenantConnection` en modelos de negocio; modelo `Tenant` en conexión por defecto (base central).
- **Sin**: Repositorios genéricos, capa de dominio explícita (DDD), colas/jobs en el código analizado, políticas (Policies) registradas.

La identidad es **Laravel estándar con servicios** y multi-tenant por base de datos, adecuada para un ERP de cooperativas pesqueras con necesidad de aislamiento fuerte de datos.

---

## 3. Fortalezas

- **Aislamiento multi-tenant**: Una base de datos por tenant y conexión dinámica bien documentada reducen el riesgo de fuga de datos entre empresas.
- **Documentación de arquitectura**: `docs/20-fundamentos/01-Arquitectura-Multi-Tenant.md` describe flujo, middleware, trait y uso de conexiones.
- **Manejo de excepciones API**: `Handler` unifica respuestas JSON, traduce errores de validación y QueryException a mensajes comprensibles (`userMessage`) y mantiene detalle técnico cuando conviene.
- **Uso consistente de `UsesTenantConnection`**: Los modelos de negocio usan el trait; solo `Tenant` vive en la base central, lo que evita confusiones en el modelo de datos.
- **Comandos de tenant**: `tenants:migrate`, `tenants:seed` (vía SeedTenants), MigrateTenants con path `database/migrations/companies` y opciones `--fresh` y `--seed` permiten operar sobre todos los tenants de forma repetible.
- **Servicios de dominio**: Producción (ProductionRecordService, ProductionOutputService, etc.), estadísticas (OrderStatisticsService, StockStatisticsService, etc.), OrderPDFService y OrderMailerService concentran lógica y transacciones en puntos claros.
- **Transacciones**: Uso de `DB::transaction()` en servicios de producción y en controladores críticos (RawMaterialReception, Pallet, CeboDispatch, Product, Punch) para operaciones compuestas.
- **Versionado API**: v2 como espacio único activo; ruta pública `v2/public/tenant/{subdomain}` para que el frontend resuelva el tenant antes de autenticación.
- **Throttling y seguridad básica**: Límites en login y flujos de magic link/OTP; CORS y health check expuestos.

---

## 4. Riesgos o Debilidades Estructurales

- **Autorización**: No hay políticas (Policy); `AuthServiceProvider::$policies` está vacío. La autorización se reduce a “estar autenticado + tener uno de los roles permitidos” en el middleware. No hay control fino por recurso (por ejemplo, “solo el comercial asignado puede editar este pedido”).
- **Uso directo de Query Builder en contexto tenant**: Varios controladores usan `DB::connection('tenant')->table(...)` (SettingController, OrderController, PunchController, RawMaterialReceptionController). Aumenta el riesgo de olvidar el contexto tenant en futuros cambios y rompe la cohesión con Eloquent (modelos, eventos, scopes).
- **Controladores muy gruesos**: OrderController y PunchController concentran gran cantidad de filtros, consultas y lógica de presentación/agregación. Dificulta pruebas y mantenimiento y mezcla responsabilidades.
- **Tests**: Solo tests de ejemplo (Feature/ExampleTest, Unit/ExampleTest). No hay tests de integración ni de dominio; la regresión se apoya en pruebas manuales o externas.
- **Falta de capa de acceso unificada**: No hay repositorios ni “query objects”; las consultas complejas se construyen en controladores o en modelos (scopes). La reutilización y el testing de reglas de listado/filtrado son limitados.
- **Settings como tabla key-value sin modelo**: La configuración por tenant se maneja con `DB::connection('tenant')->table('settings')`. No hay modelo `Setting` ni caché explícita; cualquier cambio implica acceso directo a BD.
- **Sin jobs/colas en código**: No se observan jobs que ejecuten tareas en segundo plano; si en el futuro se añaden (envío de emails masivos, reportes, integraciones), habrá que asegurar que el contexto tenant se inyecte o se fije en el payload.

---

## 5. Alineación con Prácticas Laravel Profesionales

| Área | Estado | Comentario |
|------|--------|------------|
| Estructura de carpetas | ✅ | app/Http/Controllers/v2, Requests/v2, Resources/v2, Services y Services/Production; Enums, Traits, Helpers. |
| Rutas y middleware | ✅ | Rutas api bajo prefijo v2, middleware `tenant` y `auth:sanctum`, agrupación por rol. |
| Validación | ✅ | Form Requests en operaciones críticas; mensajes y reglas en español donde se ha revisado. |
| Serialización API | ✅ | API Resources para entidades principales; respuestas JSON homogéneas. |
| Autenticación | ✅ | Sanctum, magic link y OTP; throttling en endpoints sensibles. |
| Autorización | ⚠️ | Solo por rol a nivel de grupo de rutas; sin Policies ni Gates por modelo/recurso. |
| Eloquent y conexiones | ⚠️ | Uso correcto del trait en modelos; pero acceso directo a `DB::connection('tenant')->table()` en varios sitios. |
| Transacciones | ✅ | Usadas en servicios y controladores donde hay operaciones compuestas. |
| Excepciones | ✅ | Handler personalizado para API, ValidationException, QueryException, HttpException. |
| Configuración | ✅ | Uso de `config()`, env; conexión tenant con database dinámico. |
| Comandos y scheduling | ✅ | MigrateTenants, cleanup de tokens/PDFs; Kernel con schedule. |
| Testing | ❌ | Sin tests de dominio ni de API; cobertura efectiva nula. |
| Documentación | ✅ | Documentación de arquitectura y fundamentos en español. |

En conjunto, el proyecto está **alineado con Laravel** en la mayoría de las áreas; los desvíos principales son autorización granular, capa de acceso a datos y testing.

---

## 6. Evaluación OOP (SRP, acoplamiento, cohesión)

- **Responsabilidad única**: Los servicios de producción y de correo/PDF tienen responsabilidades bien delimitadas. Los controladores grandes (Order, Punch) asumen demasiado: filtrado, agregaciones, exportaciones y reglas de negocio mezcladas.
- **Acoplamiento**: Los controladores acoplan directamente Request, Model y a veces `DB::connection('tenant')`; las dependencias están inyectadas en servicios. El acoplamiento a la base de datos es alto donde se usa Query Builder directo.
- **Cohesión**: Los módulos de producción (Production*, ProductionRecordService, etc.) son cohesivos. La cohesión en “pedidos” o “fichajes” es menor porque la lógica está repartida entre controlador y modelo (scopes, accessors).
- **Recomendación**: Extraer “casos de uso” o “query objects” para listados y reportes (por ejemplo, OrderListQuery, PunchDashboardQuery) mejoraría SRP y testabilidad sin imponer un estilo DDD completo.

---

## 7. API y Diseño de Serialización

- **Recursos**: Uso de API Resources (OrderResource, OrderDetailsResource, ActiveOrderCardResource, etc.) mantiene la forma de salida estable y evita exponer atributos internos.
- **Consistencia**: Respuestas con `message`, `userMessage` y `errors` en errores; estructura predecible en listados y detalle.
- **Options/endpoints auxiliares**: Múltiples rutas `options` para desplegables (clientes, productos, transportes, etc.) facilitan el uso desde el frontend.
- **Riesgo**: Algunos endpoints devuelven lógica de negocio embebida (por ejemplo filtros activos/inactivos en listados); conviene documentar o unificar criterios de paginación y filtrado para no fragmentar contratos.

---

## 8. Distribución de la Lógica de Dominio

- **En modelos**: Constantes de estado (Order::STATUS_*), métodos estáticos (getValidStatuses), relaciones, accessors (formatted_id, summary) y scopes (withTotals). Parte de la lógica de “qué es un pedido” está bien en el modelo.
- **En controladores**: Filtros de listado, agregaciones para dashboards y reportes, y en algunos casos reglas de negocio (por ejemplo condiciones de estado y fechas). Esta parte sería más mantenible en servicios o query objects.
- **En servicios**: Producción (crear/actualizar registros, outputs, consumos), generación de PDFs, envío de correo, estadísticas (órdenes, stock, recepciones, despachos). Bien ubicada.
- **Resumen**: La distribución es aceptable para el tamaño del proyecto; el desbalance está en controladores que asumen lógica que podría vivir en servicios o en el modelo cuando es claramente de dominio (estados, reglas de transición).

---

## 9. Integridad Transaccional y Efectos Secundarios

- **Transacciones**: Usadas en creación/actualización de recepciones de materia prima, palets, despachos de cebo, productos (duplicación), fichajes y en todos los servicios de producción. Coherente con operaciones multi-tabla.
- **Efectos secundarios**: Envío de correo (OrderMailerService) y generación de PDFs se invocan desde controladores; si en el futuro se movieran a colas, habría que garantizar que el tenant esté en el payload del job.
- **Eliminaciones en cascada**: En RawMaterialReceptionController se eliminan manualmente cajas, pallet_boxes y pallets con Query Builder; funcionalmente correcto pero frágil ante cambios de esquema. Valorar uso de eliminación en cascada a nivel de BD o de Eloquent donde sea posible.

---

## 10. Testing y Mantenibilidad

- **Cobertura**: Prácticamente nula; solo ExampleTest. No hay tests de API, de servicios ni de multi-tenant.
- **Mantenibilidad**: La base de código es legible y los nombres son descriptivos. La mantenibilidad se ve afectada por controladores muy largos y por la falta de tests que documenten el comportamiento esperado.
- **Recomendación**: Priorizar tests de integración para flujos críticos (login tenant, CRUD de pedidos, creación de producción) y tests unitarios de servicios de producción y de estadísticas, con tenant y base de datos de prueba o en memoria si se estandariza.

---

## 11. Señales de Rendimiento y Escalabilidad

- **Consultas N+1**: Uso de `with()` en listados (por ejemplo Order con customer, salesperson, transport, incoterm) indica conciencia del problema; en controladores grandes puede haber puntos sin eager loading.
- **Índices**: No se ha revisado el esquema de migraciones en detalle; para listados filtrados por fechas, estado y tenant (implícito por conexión), conviene revisar índices en tablas grandes (orders, punch_events, production_*).
- **Conexiones**: Una conexión `tenant` por request; purge y reconnect en cada request pueden ser costosos con muchos tenants muy activos; en escenarios de alto tráfico podría valorarse connection pooling o caché de configuración por tenant.
- **Escalabilidad horizontal**: La arquitectura permite varias instancias de la API detrás de un balanceador; la restricción es que cada request debe llevar `X-Tenant` y la configuración de BD es por request. No hay estado de sesión en servidor más allá de Sanctum.

---

## 12. Seguridad y Autorización

- **Aislamiento tenant**: El middleware garantiza que solo se use un tenant activo por request; los modelos de negocio usan la conexión `tenant` inyectada. Riesgo: cualquier código que use la conexión por defecto o que construya SQL sin conexión explícita podría fugarse a otra base.
- **Autorización**: Solo por rol a nivel de ruta; no hay comprobación “este usuario puede ver/editar este Order/Customer”. Para un ERP, es un gap si los requisitos exigen restricciones por comercial, almacén o cliente.
- **Datos sensibles**: Contraseña de correo en settings; SettingController evita sobrescribir la contraseña si no se envía. Conviene asegurar que `settings` no se expongan completos al frontend y que los secretos no se logueen.
- **Input**: Validación mediante Form Request; sanitización depende de reglas; no se ha revisado exhaustivamente cada endpoint.

---

## 13. Oportunidades de Mejora (Priorizadas)

1. **Introducir políticas de autorización**: Empezar por recursos críticos (Order, RawMaterialReception, User) y registrar políticas en AuthServiceProvider; sustituir o complementar el middleware de rol con `authorize()` en controladores.
2. **Eliminar o acotar el uso de `DB::connection('tenant')->table()`**: Preferir modelos Eloquent (por ejemplo un modelo `Setting` con conexión tenant) o servicios que encapsulen el acceso; reducir superficie de error y mantener un solo punto de configuración de conexión.
3. **Refactorizar controladores gruesos**: Extraer lógica de listado y reportes a clases dedicadas (por ejemplo OrderQuery, OrderReportService) y dejar en el controlador solo orquestación y respuestas HTTP.
4. **Tests de integración y de servicios**: Añadir tests que cubran flujo tenant + auth y al menos un flujo completo por dominio (pedido, producción o recepción) y tests unitarios de servicios de producción y estadísticas.
5. **Documentar y unificar criterios de API**: Paginación, filtros y convenciones de nombres para listados y opciones; considerar OpenAPI/Swagger para v2.
6. **Contexto tenant en jobs futuros**: Si se incorporan colas, definir convención (por ejemplo tenant_id o subdomain en el payload) y middleware o lógica de arranque que configure la conexión tenant en el worker.

---

## 14. Trayectoria de Evolución Sugerida

- **Fase 1 (corto plazo)**: Políticas para Order y User; modelo (o helper tipado) para Settings en tenant; revisión de índices en tablas críticas.
- **Fase 2 (medio plazo)**: Extracción de “query” o “report” para Order y Punch; tests de integración para login y un flujo de pedido y uno de producción.
- **Fase 3 (según necesidad)**: Paginación estándar en listados grandes; documentación OpenAPI; si aparece uso de colas, estándar de tenant en jobs y pruebas de jobs con tenant.

La evolución es **adaptativa**: el proyecto no requiere pasar a DDD o CQRS; mejorar autorización, capa de acceso y tests dará el mayor beneficio con menor impacto.

---

## 15. Marco de Madurez Arquitectónica

Evaluación por dimensión (1–10), con justificación breve:

| Dimensión | Puntuación | Comentario |
|-----------|------------|------------|
| **Madurez multi-tenant** | 7 | Aislamiento por BD claro, documentado y con comandos; riesgo en uso directo de Query Builder y ausencia de jobs con contexto tenant. |
| **Claridad del modelo de dominio** | 6 | Conceptos del sector (pedidos, recepciones, cebo, producción, cajas/palets) están en modelos y servicios; falta acotar responsabilidades en controladores y unificar “quién hace qué” en listados. |
| **Consistencia del diseño API** | 7 | Recursos, Form Requests, códigos HTTP y mensajes coherentes; faltan convenciones explícitas de paginación/filtrado y documentación formal. |
| **Cobertura y estrategia de tests** | 2 | Solo tests de ejemplo; no hay estrategia ni tests de regresión. |
| **Prácticas de despliegue/DevOps** | 6 | Docker/Coolify e instrucciones en docs; no se ha revisado en detalle pipelines ni gestión de secretos. |
| **Calidad de documentación** | 7 | Buena documentación de arquitectura y fundamentos; falta documentación de API (endpoints, contratos). |
| **Nivel de deuda técnica** | 5 | Controladores gruesos, acceso directo a BD en settings y algunos reportes, sin políticas; servicios y modelo de datos están razonablemente ordenados. |

**Puntuación global de madurez**: **5,7 / 10** — Proyecto sólido en multi-tenant y en uso de Laravel, con margen claro de mejora en autorización, capa de acceso a datos y testing.

---

## 16. Top 5 Riesgos Sistémicos

1. **Falta de autorización por recurso**: Cualquier usuario autenticado con el rol adecuado puede actuar sobre cualquier recurso del tenant (por ejemplo cualquier pedido); riesgo de abuso o error en entornos multi-usuario.
2. **Uso de `DB::connection('tenant')->table()` sin modelo**: Aumenta el riesgo de olvidar el contexto tenant en cambios futuros y dificulta refactors y tests.
3. **Ausencia de tests automatizados**: Cualquier cambio puede romper flujos críticos sin detección; el coste de refactor y de evolución aumenta con el tiempo.
4. **Controladores con demasiada lógica**: OrderController y PunchController son puntos frágiles; cambios en reglas de negocio o en filtros son costosos y propensos a regresiones.
5. **Jobs/colas sin convención de tenant**: Si se añaden tareas en segundo plano sin definir cómo se pasa el tenant, se corre el riesgo de ejecutar trabajos en el tenant equivocado o en la conexión por defecto.

---

## 17. Top 5 Mejoras de Mayor Impacto

1. **Implementar políticas (Policies) para recursos críticos** y usarlas en controladores: mayor seguridad y claridad de permisos sin cambiar la estructura actual.
2. **Sustituir acceso directo a `settings` por un modelo o servicio** que use Eloquent y, si aplica, caché: un solo punto de acceso y menor riesgo de fuga de contexto.
3. **Introducir tests de integración** para flujos tenant + auth + al menos un CRUD completo (por ejemplo pedidos o producción): base para refactors y despliegues más seguros.
4. **Extraer lógica de listados y reportes** de OrderController y PunchController a servicios o query objects: controladores más legibles y lógica reutilizable y testeable.
5. **Documentar convenciones de API (paginación, filtros, errores)** y, si es posible, exponer OpenAPI/Swagger: facilita evolución del frontend y onboarding de desarrolladores.

---

## 18. Preguntas de Contexto (opcionales)

Si se desea afinar recomendaciones, estas preguntas pueden ayudar:

1. ¿Existe requisito de negocio de que ciertos roles solo puedan ver/editar sus propios recursos (por ejemplo pedidos de “sus” clientes o “su” almacén)? Si sí, las políticas son prioritarias.
2. ¿Está previsto usar colas (Laravel Queue) para envío de emails, reportes o integraciones (p. ej. n8n)? Si sí, conviene definir desde el inicio el payload con identificador de tenant.
3. ¿Hay planes de exponer la API a terceros (socios, integraciones)? En ese caso, documentación OpenAPI y versionado estable de contratos serían más importantes.
4. ¿La tabla `settings` por tenant debe permanecer key-value o se planea migrar a estructura más rígida (tabla por tipo de configuración)? Esto afecta si se introduce un modelo Setting o un servicio de configuración.
5. ¿Qué nivel de cobertura de tests se considera objetivo a medio plazo (por ejemplo solo flujos críticos vs. cobertura alta)? Ayuda a priorizar entre tests de integración y unitarios.

---

*Documento generado en el marco de una auditoría arquitectónica global. No sustituye una revisión de seguridad ni una auditoría de rendimiento dedicada.*
