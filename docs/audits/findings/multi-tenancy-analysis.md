# Análisis Multi-Tenancy — PesquerApp Backend

**Documento de hallazgos** | Auditoría global Laravel  
**Fecha**: 2026-02-15

---

## 1. Modelo de Aislamiento

- **Estrategia**: Una base de datos MySQL por tenant (database-per-tenant).
- **Base central** (`mysql`): Solo la tabla `tenants` (catálogo de empresas: name, subdomain, database, active, branding_image_url).
- **Bases tenant** (`tenant`): Mismas tablas para todos los tenants (users, orders, products, productions, settings, etc.), ubicadas en `database/migrations/companies/`.

**Ventajas**: Aislamiento fuerte de datos, posibilidad de backup/restore por tenant, cumplimiento más sencillo si se exigen fronteras físicas por cliente.  
**Inconvenientes**: Más bases que mantener; migraciones y seeds deben ejecutarse por tenant (cubierto con `tenants:migrate`).

---

## 2. Resolución del Tenant

- **Mecanismo**: Header HTTP `X-Tenant` con el subdominio (identificador del tenant).
- **Middleware**: `TenantMiddleware` (registrado como `tenant`).
  - Excluye rutas `api/v2/public/*`.
  - Busca en la base central: `Tenant::where('subdomain', $subdomain)->where('active', true)->first()`.
  - Si no hay tenant o está inactivo: 400/404 JSON.
  - Configura: `config(['database.connections.tenant.database' => $tenant->database])`, `DB::purge('tenant')`, `DB::reconnect('tenant')`, y `app()->instance('currentTenant', $subdomain)`.
- **Ruta pública**: `GET v2/public/tenant/{subdomain}` devuelve `active` y `name` del tenant (para que el frontend valide antes de login).

**Hallazgo positivo**: El flujo está documentado en `docs/fundamentos/01-Arquitectura-Multi-Tenant.md` y es coherente con un frontend que envía siempre `X-Tenant`.

---

## 3. Uso de la Conexión Tenant en el Código

- **Modelos de negocio**: Usan el trait `UsesTenantConnection`, que en `initializeUsesTenantConnection()` hace `$this->setConnection('tenant')`. Correcto.
- **Modelo Tenant**: No usa el trait; vive en la conexión por defecto (`mysql`). Correcto.
- **Modelo Setting**: Existe modelo `Setting` con `UsesTenantConnection`; `SettingService` encapsula lectura/escritura de configuración. **Mejora respecto a auditoría anterior** (2026-02-14): ya no se usa `DB::connection('tenant')->table('settings')` en SettingController.
- **SettingController**: Usa `SettingService` y `Setting` model; ya no accede directamente a `DB::connection('tenant')->table('settings')`.
- **Único uso residual**: El helper `tenantSetting()` en `app/Support/helpers.php` usa `DB::connection('tenant')->table('settings')` para lectura con caché por petición. Se ejecuta siempre en contexto request (después del middleware tenant). Riesgo bajo; podría migrarse a `Setting::query()` para unificar acceso.

**Conclusión**: **Mejora significativa** respecto a la auditoría anterior. Los controladores ya no usan `DB::connection('tenant')->table()`; solo el helper `tenantSetting()` mantiene acceso directo para lectura de settings.

---

## 4. Recursos Compartidos vs por Tenant

- **Compartido**: Código de aplicación, almacenamiento de archivos (paths o buckets pueden incluir identificador de tenant).
- **Por tenant**: Base de datos, usuarios, datos de negocio, configuración (settings), correo (TenantMailConfigService aplica configuración de correo por tenant).

No se ha detectado uso de caché por tenant (por ejemplo Redis con prefijo por subdomain); si se introduce, incluir el identificador del tenant en la clave.

---

## 5. Onboarding / Offboarding de Tenants

- **Onboarding**: Comandos existentes (`tenants:migrate`, `tenants:seed`, MigrateTenants, SeedTenants) permiten operar sobre todos los tenants. No hay un flujo único "CreateTenant" de extremo a extremo que cree la BD y el registro en una sola operación.
- **Offboarding**: No hay comando ni flujo explícito para desactivar/eliminar un tenant. Recomendación: documentar o implementar un flujo seguro (desactivar, backup, luego eliminación de datos si procede).

---

## 6. Trabajos en Segundo Plano (Jobs)

- No se han encontrado clases Job en el proyecto.
- Si en el futuro se usan colas (envío de emails, reportes, integraciones con n8n), **el contexto tenant no se propaga por defecto**.
- **Recomendación**: Definir convención: payload con `tenant_subdomain` o `tenant_database`; al ejecutarse, configurar la conexión `tenant` antes de usar modelos. Alternativamente, middleware de cola que lea el tenant del job y configure la conexión.

---

## 7. Resumen de Madurez Multi-Tenant

| Aspecto | Valoración | Nota |
|---------|------------|------|
| Aislamiento de datos | Alta | Una BD por tenant, bien separada de la central. |
| Resolución y middleware | Alta | Header, validación, configuración dinámica y documentada. |
| Consistencia de uso de conexión | Alta | Eloquent vía trait; Setting model; solo `tenantSetting()` helper usa acceso directo. |
| Onboarding/offboarding | Media | Herramientas presentes; falta flujo completo documentado. |
| Jobs y contexto tenant | Pendiente | Sin jobs hoy; definir convención cuando se usen colas. |

**Conclusión**: La implementación multi-tenant es sólida. **Mejora respecto a auditoría anterior**: uso de Setting model; eliminación de acceso directo a `DB::connection('tenant')->table()` en controladores.
