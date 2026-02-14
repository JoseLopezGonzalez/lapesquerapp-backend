# Observaciones de Seguridad — PesquerApp Backend

**Documento de hallazgos** | Auditoría global Laravel  
**Fecha**: 2026-02-14

---

## 1. Aislamiento Multi-Tenant

- **Diseño**: Una base de datos por tenant; el middleware resuelve el tenant por header `X-Tenant` y configura la conexión `tenant` para ese request. Los modelos de negocio usan `UsesTenantConnection`.
- **Fortaleza**: Reduce el riesgo de que un request sirva datos de otro tenant, siempre que todo el acceso a datos pase por la conexión `tenant` configurada en ese request.
- **Riesgo**: El uso de `DB::connection('tenant')->table(...)` en varios controladores depende de que el middleware haya ejecutado antes. Cualquier ruta que no lleve el middleware `tenant` o cualquier código que se ejecute fuera del request (por ejemplo un job sin contexto) podría usar la conexión por defecto o una conexión no actualizada. Recomendación: minimizar acceso directo a `DB::connection('tenant')` y centralizar en modelos o servicios que asuman siempre la conexión ya configurada.
- **Ruta pública**: `GET v2/public/tenant/{subdomain}` devuelve solo `active` y `name`. No expone datos sensibles; adecuado para que el frontend compruebe el tenant antes de login.

---

## 2. Autenticación

- **Sanctum**: Uso de API tokens para autenticación; adecuado para SPA/frontend.
- **Throttling**: Aplicado en login y en flujos de magic link y OTP (`throttle:5,1`, `throttle:10,1`) para limitar fuerza bruta y abuso.
- **Magic link y OTP**: Limpieza de tokens antiguos con comando programado; reduce ventana de uso de tokens obsoletos.
- No se ha revisado en detalle la política de expiración de tokens Sanctum ni el almacenamiento de contraseñas (Laravel por defecto usa bcrypt); se asume configuración estándar.

---

## 3. Autorización

- **Estado actual**: Hay políticas registradas en `AuthServiceProvider` para Order y User, pero **solo `UserController`** llama a `authorize()`. El resto de controladores (incluido OrderController) no usan políticas. La restricción efectiva de acceso se hace por middleware de rol: `role:tecnico,administrador,direccion,administracion,comercial,operario` en el grupo de rutas protegidas.
- **Implicación**: Cualquier usuario autenticado que tenga uno de esos roles puede acceder a **todos** los recursos del tenant (todos los pedidos, todos los clientes, etc.), excepto donde se aplica la política de User. No hay comprobación del tipo “este usuario solo puede ver pedidos de sus clientes” o “solo puede editar su almacén” en Order ni en el resto de recursos.
- **Riesgo**: En entornos multi-usuario por tenant, puede ser un problema de negocio o de cumplimiento si se requieren restricciones por comercial, almacén o cliente. También dificulta auditorías “quién pudo hacer qué”.
- **Recomendación**: Introducir políticas para recursos críticos (Order, User, RawMaterialReception, etc.) y usar `$this->authorize(...)` en controladores; mantener el middleware de rol como filtro grueso y las políticas como filtro fino.

---

## 4. Datos Sensibles y Configuración

- **Settings por tenant**: La tabla `settings` (key-value) en cada tenant puede contener datos sensibles (por ejemplo `company.mail.password`). El `SettingController` evita sobrescribir la contraseña si no se envía en el request, lo que es correcto para no borrarla al actualizar otros campos.
- **Exposición al frontend**: El endpoint de settings devuelve `pluck('value', 'key')`; si hay claves sensibles (passwords, API keys), no deberían enviarse al cliente. Recomendación: filtrar en backend las claves que no deban exponerse o exponer solo un subconjunto seguro (nombre de empresa, idioma, etc.) y mantener la gestión de correo/secretos en endpoints separados con validación estricta.
- **Logs**: En el middleware tenant solo se loguea en `debug`; se evita volcar información de tenant en logs en producción. Conviene asegurar que en ningún log se escriban contraseñas ni tokens.

---

## 5. Entrada de Usuario y Validación

- **Form Requests**: Uso generalizado para validar entrada en operaciones críticas; reglas y mensajes en español en los revisados.
- **Validación**: Reduce riesgo de inyección y de datos malformados; no sustituye una revisión exhaustiva de cada endpoint (listados, filtros, búsquedas) para inyección SQL o lógica. El uso de Eloquent y Query Builder con bindings mitiga SQL injection en el código revisado; el uso directo de `DB::connection('tenant')->table()` con condiciones construidas desde el request debe revisarse para que no se concatenen valores sin binding.

---

## 6. Errores y Respuestas

- **Handler**: Las excepciones se traducen a JSON con `message` y `userMessage`; en 500 se puede exponer `error` (mensaje técnico). En producción suele ser recomendable no exponer mensajes internos detallados; valorar ocultar `error` en producción o limitarlo a códigos o identificadores de incidencia.
- **CORS**: Existe ruta de prueba de CORS; la configuración real depende de `config/cors.php` y del entorno. Asegurar que solo orígenes permitidos tengan acceso en producción.

---

## 7. Resumen de Prioridades

| Prioridad | Tema | Acción sugerida |
|-----------|------|------------------|
| Alta | Autorización por recurso | Añadir Policies y usarlas en controladores. |
| Alta | Exposición de settings | No devolver claves sensibles al frontend; filtrar por clave o por endpoint. |
| Media | Acceso directo a BD tenant | Reducir `DB::connection('tenant')->table()`; usar modelos o servicios. |
| Media | Respuestas de error en producción | Revisar si `error` en 500 debe ocultarse o acotarse. |
| Baja (preventiva) | Jobs futuros | Cuando existan colas, asegurar que el tenant esté en el payload y se configure en el worker. |

Este documento refleja hallazgos de una auditoría arquitectónica; no sustituye una auditoría de seguridad ni un pentest.
