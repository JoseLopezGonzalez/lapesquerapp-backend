# Auditoría Arquitectónica Global — Backend Laravel PesquerApp

**Fecha**: 2026-02-15  
**Alcance**: Backend Laravel 10, multi-tenant, API v2  
**Tipo**: Auditoría arquitectónica y sistémica (sin refactors ni revisión línea a línea)

---

## 1. Resumen Ejecutivo

El backend de PesquerApp es una aplicación Laravel 10 multi-tenant con **una base de datos por tenant**, API REST v2 consumida por un frontend Next.js 16, y dominios de negocio claros: pedidos, recepciones de materia prima, despachos de cebo, producción, inventario (cajas/palets/almacenes), fichajes y catálogos del sector pesquero. La arquitectura es **coherente** con un estilo **MVC + capa de servicios en dominios complejos**: controladores delgados, Form Requests en prácticamente toda la API, API Resources, Policies aplicadas en la mayoría de recursos críticos, y servicios para producción, estadísticas, PDF y correo. No se sigue un patrón Repository ni DDD estricto; la identidad es **Laravel idiomático con servicios de aplicación**.

**Fortalezas principales**: Aislamiento tenant por base de datos, documentación de arquitectura multi-tenant, Form Requests con cobertura amplia (137 clases), Policies registradas y aplicadas en la mayoría de controladores, modelo `Setting` y `SettingService` para configuración, controladores refactorizados (Order, Punch) que delegan en servicios, y suite de tests Feature y Unit en bloques críticos. **Riesgos residuales**: uso de `tenantSetting()` helper que aún emplea `DB::connection('tenant')->table('settings')` para lectura en caché por petición; ausencia de Jobs/colas (si se añaden, definir convención de contexto tenant); y documentación formal de API (OpenAPI) pendiente. La madurez arquitectónica ha mejorado de forma significativa desde la auditoría anterior.

---

## 2. Identidad Arquitectónica del Proyecto

El proyecto **no** sigue un checklist rígido de patrones; implícitamente adopta:

- **Arquitectura**: MVC + **Application Service Layer** en dominios complejos (Producción, estadísticas, listados, envío de documentos).
- **Multi-tenancy**: **Database-per-tenant** con conexión dinámica `tenant` configurada por middleware a partir del header `X-Tenant`.
- **API**: REST v2 con prefijo `v2`, Form Requests en prácticamente todos los endpoints, API Resources para serialización, Policies aplicadas en controladores.
- **Autenticación**: Sanctum (API tokens), roles por usuario (`role` en `users`), middleware `role:tecnico,administrador,...` para rutas protegidas.
- **Persistencia**: Eloquent con trait `UsesTenantConnection` en modelos de negocio; modelo `Tenant` en conexión por defecto (base central); modelo `Setting` para configuración por tenant.
- **Sin**: Repositorios genéricos, capa de dominio explícita (DDD), colas/jobs en el código. Policies registradas para ~30 modelos y **usadas** en Order, Customer, Pallet, Punch, Production, Setting, Supplier, Employee, etc.

La identidad es **Laravel estándar con servicios** y multi-tenant por base de datos, adecuada para un ERP de cooperativas pesqueras con necesidad de aislamiento fuerte de datos.

---

## 3. Fortalezas

- **Aislamiento multi-tenant**: Una base de datos por tenant y conexión dinámica bien documentada reducen el riesgo de fuga de datos entre empresas.
- **Documentación de arquitectura**: `docs/20-fundamentos/01-Arquitectura-Multi-Tenant.md` describe flujo, middleware, trait y uso de conexiones.
- **Form Requests extensivos**: 137 clases en `Http/Requests/v2/` cubriendo prácticamente toda la API v2; validación en frontera HTTP coherente.
- **Policies aplicadas**: AuthServiceProvider registra ~30 políticas; la mayoría de controladores llaman a `authorize()` (Order, Customer, Pallet, Punch, Production, Setting, Supplier, Employee, etc.).
- **Modelo Setting**: `Setting` con `UsesTenantConnection` y `SettingService` encapsulan configuración; ofuscación de contraseña de correo en respuestas.
- **Controladores refactorizados**: OrderController delega en OrderListService, OrderDetailService, OrderStoreService, OrderUpdateService; PunchController en PunchDashboardService, PunchCalendarService, PunchStatisticsService, PunchEventListService, PunchEventWriteService.
- **Manejo de excepciones API**: `Handler` unifica respuestas JSON, traduce errores de validación y QueryException a mensajes comprensibles.
- **Uso consistente de `UsesTenantConnection`**: Los modelos de negocio usan el trait; solo `Tenant` vive en la base central.
- **Tests**: Feature tests para Auth, Order, Customer, Productos, Stock, Settings, Fichajes, Label, Catalogos, Suppliers, Documents, Tenant, OrderStatistics; Unit tests para OrderDetailService, OrderListService, OrderStoreService, OrderUpdateService. Trait `ConfiguresTenantConnection` para tests multi-tenant.
- **Transacciones**: Uso de `DB::transaction()` en servicios y controladores donde hay operaciones compuestas.

---

## 4. Riesgos o Debilidades Estructurales

- **Helper `tenantSetting()`**: El helper en `app/Support/helpers.php` usa `DB::connection('tenant')->table('settings')` para lectura. Funciona en contexto request (después del middleware tenant) y tiene caché por petición; riesgo bajo. Podría migrarse a `Setting::query()` para unificar acceso.
- **Sin jobs/colas**: No hay clases Job; si en el futuro se añaden (envío de emails masivos, reportes, integraciones), habrá que asegurar que el contexto tenant esté en el payload.
- **Documentación API**: Falta OpenAPI/Swagger; convenciones de paginación y filtrado no están formalizadas en un contrato.
- **Algunos controladores aún con lógica**: PunchController index construye la query con `listService->applyFiltersToQuery()` pero la paginación y ordenación siguen en el controlador; aceptable para el tamaño actual.

---

## 5. Alineación con Prácticas Laravel Profesionales

| Área | Estado | Comentario |
|------|--------|------------|
| Estructura de carpetas | ✅ | app/Http/Controllers/v2, Requests/v2, Resources/v2, Services y Services/Production; Enums, Traits, Helpers. |
| Rutas y middleware | ✅ | Rutas api bajo prefijo v2, middleware `tenant` y `auth:sanctum`, agrupación por rol. |
| Validación | ✅ | Form Requests en prácticamente toda la API v2 (137 clases). |
| Serialización API | ✅ | API Resources para entidades principales; respuestas JSON homogéneas. |
| Autenticación | ✅ | Sanctum, magic link y OTP; throttling en endpoints sensibles. |
| Autorización | ✅ | Policies registradas y usadas en la mayoría de controladores de recursos críticos. |
| Eloquent y conexiones | ✅ | Uso correcto del trait en modelos; Setting como modelo; solo `tenantSetting()` helper usa acceso directo para lectura. |
| Transacciones | ✅ | Usadas en servicios y controladores donde hay operaciones compuestas. |
| Excepciones | ✅ | Handler personalizado para API, ValidationException, QueryException, HttpException. |
| Testing | ⚠️ | Feature y Unit tests presentes en bloques críticos; cobertura no medida; base sólida para ampliar. |
| Documentación | ⚠️ | Buena documentación de arquitectura; falta documentación formal de API (OpenAPI). |

En conjunto, el proyecto está **muy alineado con Laravel**; las áreas de mejora son documentación API y eventual convención de tenant para jobs futuros.

---

## 6. Uso de componentes estructurales Laravel

| Componente | Presencia | Corrección / consistencia |
|------------|-----------|---------------------------|
| **Services** | ✅ | Servicios de aplicación en dominios complejos; listados (OrderListService, PunchEventListService), estadísticas, producción, PDF, correo. Responsabilidad única y reutilización adecuadas. |
| **Actions** | ❌ | No existe carpeta `Actions` ni invocables. La lógica está en servicios o controladores; no es obligatorio. |
| **Jobs** | ❌ | No hay clases Job. Si se añaden, definir convención de contexto tenant en el payload. |
| **Events / Listeners** | Solo por defecto | EventServiceProvider solo registra `Registered` → `SendEmailVerificationNotification`. Adecuado para el flujo actual. |
| **Form Requests** | ✅ | 137 clases; cobertura amplia en toda la API v2. Consistente. |
| **Policies / Gates** | ✅ | ~30 políticas registradas; la mayoría de controladores llaman a `authorize()`. Coherente. |
| **Middleware** | ✅ | `tenant`, `auth:sanctum`, `role`, `throttle`, `LogActivity`. Correcto y coherente. |
| **API Resources** | ✅ | Recursos v2 para entidades principales; serialización estable. |

**Resumen**: Form Requests y Policies han pasado de uso parcial a uso amplio y coherente. La capa de servicios sigue bien utilizada. Mejora significativa respecto a la auditoría anterior.

---

## 7. Evaluación OOP (SRP, acoplamiento, cohesión)

- **Responsabilidad única**: Los servicios de producción, estadísticas, listados y correo/PDF tienen responsabilidades bien delimitadas. OrderController y PunchController han sido refactorizados y delegan en servicios.
- **Acoplamiento**: Los controladores acoplan Request, Model y servicios; las dependencias están inyectadas. El acoplamiento a la base de datos se ha reducido al eliminar `DB::connection('tenant')->table()` de los controladores (queda solo en el helper `tenantSetting`).
- **Cohesión**: Los módulos de producción, pedidos, fichajes y configuración son cohesivos; la lógica de listados y reportes está en servicios dedicados.

---

## 8. API y Diseño de Serialización

- **Recursos**: Uso de API Resources (OrderResource, OrderDetailsResource, ActiveOrderCardResource, PunchEventResource, etc.) mantiene la forma de salida estable.
- **Consistencia**: Respuestas con `message`, `userMessage` y `errors` en errores; estructura predecible en listados y detalle.
- **Options/endpoints auxiliares**: Múltiples rutas `options` para desplegables.
- **Recomendación**: Documentar convenciones de paginación y filtrado; considerar OpenAPI/Swagger para v2.

---

## 9. Distribución de la Lógica de Dominio

- **En modelos**: Constantes de estado, relaciones, accessors y scopes. Lógica de presentación y reglas de estado.
- **En controladores**: Orquestación; delegan en servicios para listados, estadísticas, escritura y documentos.
- **En servicios**: Producción (ProductionRecordService, ProductionOutputService, etc.), estadísticas (OrderStatisticsService, StockStatisticsService, etc.), listados (OrderListService, PunchEventListService), SettingService, OrderPDFService, OrderMailerService.

La distribución es **adecuada**; la lógica compleja está en servicios y los controladores actúan como orquestadores.

---

## 10. Integridad Transaccional y Efectos Secundarios

- **Transacciones**: Usadas en servicios de producción, recepciones, despachos, palets y fichajes.
- **Efectos secundarios**: Envío de correo y generación de PDFs se invocan desde controladores; si en el futuro se movieran a colas, garantizar tenant en el payload del job.
- **Eliminaciones**: En RawMaterialReception y otros dominios se usa Eloquent o servicios; revisar si hay eliminaciones manuales que puedan unificarse.

---

## 11. Testing y Mantenibilidad

- **Cobertura**: Feature tests para Auth, Order, Customer, Productos, Stock, Settings, Fichajes, Label, Catalogos, Suppliers, Documents, Tenant, OrderStatistics, Infraestructura; Unit tests para OrderDetailService, OrderListService, OrderStoreService, OrderUpdateService. Trait `ConfiguresTenantConnection` para tenant en tests.
- **Mantenibilidad**: Base de código legible; controladores refactorizados; tests documentan comportamiento esperado en flujos críticos.
- **Recomendación**: Medir cobertura; ampliar tests a producción y recepciones si se considera crítico; mantener consistencia en uso de ConfiguresTenantConnection.

---

## 12. Señales de Rendimiento y Escalabilidad

- **Consultas N+1**: Uso de `with()` en listados (Order con customer, salesperson, transport, etc.); servicios centralizan eager loading.
- **Índices**: Revisar índices en tablas grandes (orders, punch_events, production_*) para filtros por fechas y estado.
- **Conexiones**: Una conexión `tenant` por request; purge/reconnect en cada request.
- **Escalabilidad horizontal**: Posible con varias instancias detrás de balanceador; cada request lleva `X-Tenant`.

---

## 13. Seguridad y Autorización

- **Aislamiento tenant**: Middleware garantiza tenant activo por request; modelos usan conexión `tenant`.
- **Autorización**: Policies aplicadas en la mayoría de recursos; `authorize()` en Order, Customer, Pallet, Punch, Production, Setting, Supplier, Employee, etc. Filtro fino por recurso además del middleware de rol.
- **Datos sensibles**: SettingService ofusca `company.mail.password` en respuestas; contraseña no se actualiza si no se envía en el request.
- **Input**: Validación mediante Form Requests en prácticamente toda la API.

---

## 14. Oportunidades de Mejora (Priorizadas)

1. **Convención de tenant en jobs futuros**: Si se incorporan colas, definir payload con `tenant_subdomain` o `tenant_database` y configurar conexión en el worker.
2. **Migrar `tenantSetting()` a Setting model**: Usar `Setting::query()` en lugar de `DB::connection('tenant')->table('settings')` en el helper para unificar acceso; el helper puede seguir existiendo como capa de abstracción.
3. **Documentar API (OpenAPI/Swagger)**: Convenciones de paginación, filtros y errores; facilita evolución del frontend.
4. **Medir y ampliar cobertura de tests**: Objetivo ≥80% en bloques críticos según CLAUDE.md; priorizar producción y recepciones si faltan.
5. **Revisar índices en tablas grandes**: orders, punch_events, production_* para optimizar listados filtrados.

---

## 15. Trayectoria de Evolución Sugerida

- **Fase 1 (corto plazo)**: Refactor de `tenantSetting()` para usar Setting model; documentar convenciones de API.
- **Fase 2 (medio plazo)**: OpenAPI/Swagger si se expone API a terceros o se prioriza onboarding; ampliar tests a bloques sin cobertura.
- **Fase 3 (según necesidad)**: Si aparecen colas, estándar de tenant en jobs; revisión de índices en tablas críticas.

La evolución es **adaptativa**; el proyecto está en buen estado para el CORE v1.0 Consolidation.

---

## 16. Marco de Madurez Arquitectónica

Evaluación por dimensión (1–10):

| Dimensión | Puntuación | Comentario |
|-----------|------------|------------|
| **Madurez multi-tenant** | 8 | Aislamiento por BD claro; modelo Setting; solo helper `tenantSetting` usa acceso directo. Sin jobs con contexto tenant. |
| **Claridad del modelo de dominio** | 8 | Conceptos del sector en modelos y servicios; controladores delegan en servicios. |
| **Consistencia del diseño API** | 7 | Form Requests, API Resources, Policies coherentes; falta documentación formal. |
| **Cobertura y estrategia de tests** | 6 | Feature y Unit tests en bloques críticos; base sólida; cobertura no medida. |
| **Prácticas de despliegue/DevOps** | 6 | Docker/Coolify; no revisado en detalle pipelines. |
| **Calidad de documentación** | 7 | Buena documentación de arquitectura; falta documentación de API. |
| **Nivel de deuda técnica** | 7 | Controladores refactorizados; Setting como modelo; políticas aplicadas. Deuda residual menor. |

**Puntuación global de madurez**: **7,0 / 10** — Proyecto sólido y coherente; mejora notable respecto a la auditoría anterior (5,7).

---

## 17. Top 5 Riesgos Sistémicos

1. **Jobs/colas sin convención de tenant**: Si se añaden tareas en segundo plano sin definir cómo se pasa el tenant, riesgo de ejecutar trabajos en el tenant equivocado.
2. **Helper `tenantSetting()` con acceso directo**: Uso de `DB::connection('tenant')->table('settings')`; migrar a Setting model unificaría acceso.
3. **Documentación API ausente**: Sin OpenAPI, la evolución del frontend y posibles integraciones externas dependen de conocimiento tribal.
4. **Cobertura de tests no medida**: Aunque hay tests, no se conoce el porcentaje de cobertura; puede haber huecos en dominios críticos.
5. **Índices no revisados en detalle**: En tablas grandes, filtros por fechas/estado podrían beneficiarse de índices compuestos.

---

## 18. Top 5 Mejoras de Mayor Impacto

1. **Definir convención de tenant para jobs** (cuando se usen colas): Payload con tenant_subdomain; middleware o lógica de arranque en worker.
2. **Refactorizar `tenantSetting()`** para usar Setting model: Un solo punto de acceso a settings; menor riesgo.
3. **Documentar API (OpenAPI/Swagger)**: Convenciones y contratos; facilita evolución y onboarding.
4. **Medir y ampliar cobertura de tests**: Priorizar bloques sin tests o con flujos críticos no cubiertos.
5. **Revisar índices en tablas orders, punch_events, production_***: Optimizar listados filtrados.

---

## 19. Preguntas de Contexto (opcionales)

1. ¿Está previsto usar colas (Laravel Queue) para envío de emails, reportes o integraciones? Si sí, definir desde el inicio el payload con identificador de tenant.
2. ¿Hay planes de exponer la API a terceros? En ese caso, OpenAPI y versionado estable serían prioritarios.
3. ¿Qué nivel de cobertura de tests se considera objetivo (solo flujos críticos vs. ≥80%)?
4. ¿`tenantSetting()` debe mantenerse para rendimiento (lectura directa) o se prefiere migrar a Setting model para consistencia?

---

*Documento generado en el marco de una auditoría arquitectónica global (actualización 2026-02-15). No sustituye una revisión de seguridad ni una auditoría de rendimiento dedicada.*
