# Análisis Multi-Tenancy — PesquerApp Backend

**Documento de hallazgos** | Auditoría global Laravel  
**Fecha**: 2026-02-14

---

## 1. Modelo de Aislamiento

- **Estrategia**: Una base de datos MySQL por tenant (database-per-tenant).
- **Base central** (`mysql`): Solo la tabla `tenants` (catálogo de empresas: name, subdomain, database, active, branding_image_url).
- **Bases tenant** (`tenant`): Mismas tablas para todos los tenants (users, orders, products, productions, etc.), ubicadas en `database/migrations/companies/`.

**Ventajas**: Aislamiento fuerte de datos, posibilidad de backup/restore por tenant, cumplimiento más sencillo si se exigen fronteras físicas por cliente.  
**Inconvenientes**: Más bases que mantener; migraciones y seeds deben ejecutarse por tenant (ya cubierto con `tenants:migrate`).

---

## 2. Resolución del Tenant

- **Mecanismo**: Header HTTP `X-Tenant` con el subdominio (identificador del tenant).
- **Middleware**: `TenantMiddleware` (registrado como `tenant`).
  - Excluye rutas `api/v2/public/*`.
  - Busca en la base central: `Tenant::where('subdomain', $subdomain)->where('active', true)->first()`.
  - Si no hay tenant o está inactivo: 400/404 JSON.
  - Configura: `config(['database.connections.tenant.database' => $tenant->database])`, `DB::purge('tenant')`, `DB::reconnect('tenant')`, y `app()->instance('currentTenant', $subdomain)`.
- **Ruta pública**: `GET v2/public/tenant/{subdomain}` devuelve `active` y `name` del tenant (para que el frontend valide antes de login).

**Hallazgo positivo**: El flujo está documentado en `docs/20-fundamentos/01-Arquitectura-Multi-Tenant.md` y es coherente con un frontend que envía siempre `X-Tenant`.

---

## 3. Uso de la Conexión Tenant en el Código

- **Modelos de negocio**: Usan el trait `UsesTenantConnection`, que en `initializeUsesTenantConnection()` hace `$this->setConnection('tenant')`. Así, todo Eloquent usa la conexión ya configurada por el middleware.
- **Modelo Tenant**: No usa el trait; vive en la conexión por defecto (`mysql`). Correcto.
- **Comandos**: `MigrateTenants`, `SeedTenants`, `CreateTenantUser`, `CopyTenantEntities`, `ResetTenantDatabase`, etc., iteran sobre tenants y en cada iteración configuran `database.connections.tenant.database` y purgan/reconectan antes de operar.

**Riesgo**: En varios puntos se usa **Query Builder directo** sobre la conexión tenant:
- `SettingController`: `DB::connection('tenant')->table('settings')` para leer/actualizar configuración.
- `OrderController`: `DB::connection('tenant')->table('orders')` en consultas de reportes.
- `PunchController`: `DB::connection('tenant')->table(...)` en lógica de dashboard/estadísticas.
- `RawMaterialReceptionController`: `DB::connection('tenant')->table('boxes')`, `pallet_boxes`, `pallets` en eliminaciones.

**Impacto**: Si en el futuro alguien añade código que use `DB::connection('tenant')` sin haber pasado por el middleware (p. ej. en un job o un comando que no establezca el tenant), o si se copia código a un contexto sin tenant, puede haber lecturas/escrituras en la base equivocada. Además, el uso de `table()` evita el modelo Eloquent (eventos, scopes, mutadores) y duplica la responsabilidad de “saber que estamos en tenant”.

**Recomendación**: Reducir el uso directo de `DB::connection('tenant')->table()`. Donde corresponda, usar modelos con `UsesTenantConnection` (por ejemplo un modelo `Setting` para la tabla `settings`) o encapsular en un servicio que reciba el tenant/subdomain y configure la conexión una sola vez.

---

## 4. Recursos Compartidos vs por Tenant

- **Compartido**: Código de aplicación, cola (si se usa), almacenamiento de archivos (no revisado en detalle; asumir que paths o buckets pueden incluir identificador de tenant).
- **Por tenant**: Base de datos, usuarios, datos de negocio, configuración (settings) y correo (TenantMailConfigService aplica configuración de correo por tenant).

No se ha detectado uso de caché por tenant (por ejemplo Redis con prefijo por subdomain); si se introduce caché de configuración o de datos, conviene incluir el identificador del tenant en la clave.

---

## 5. Onboarding / Offboarding de Tenants

- **Onboarding**: No hay un flujo único documentado en código. Implica: (1) crear registro en `tenants` (base central), (2) crear la base de datos del tenant, (3) ejecutar migraciones en `database/migrations/companies` para ese tenant (por ejemplo con `tenants:migrate` que recorre todos los activos, o un comando que reciba un solo tenant), (4) opcionalmente seed. Los comandos existentes permiten hacerlo; no hay un “CreateTenant” de extremo a extremo que cree la BD y el registro en una sola operación.
- **Offboarding**: No hay comando ni flujo explícito para desactivar/eliminar un tenant (desactivar `active`, borrar BD o archivos). Recomendación: documentar o implementar un flujo seguro (desactivar, backup, luego eliminación de datos si procede).

---

## 6. Trabajos en Segundo Plano (Jobs)

- No se han encontrado clases Job en el proyecto. Si más adelante se usan colas (envío de emails, reportes, integraciones con n8n, etc.), **el contexto tenant no se propaga por defecto**.
- **Recomendación**: Definir una convención: por ejemplo que cada job reciba `tenant_subdomain` o `tenant_database` en el payload y que al ejecutarse configure la conexión `tenant` antes de usar modelos. Alternativamente, usar un middleware de cola que lea el tenant del job y configure la conexión. Así se evita ejecutar trabajos en la conexión por defecto (base central) o en un tenant equivocado.

---

## 7. Resumen de Madurez Multi-Tenant

| Aspecto | Valoración | Nota |
|---------|------------|------|
| Aislamiento de datos | Alta | Una BD por tenant, bien separada de la central. |
| Resolución y middleware | Alta | Header, validación, configuración dinámica y documentada. |
| Consistencia de uso de conexión | Media | Eloquent correcto vía trait; uso directo de `DB::connection('tenant')->table()` en varios controladores. |
| Onboarding/offboarding | Media | Herramientas (migrate/seed) presentes; falta flujo completo y documentado para alta/baja de tenant. |
| Jobs y contexto tenant | Pendiente | Sin jobs hoy; hace falta convención cuando se usen colas. |

**Conclusión**: La implementación multi-tenant es sólida para el modelo request/response actual. Las mejoras de mayor impacto son: unificar el acceso a datos tenant (evitar Query Builder directo donde sea posible) y definir y aplicar una estrategia de contexto tenant para futuros jobs.
