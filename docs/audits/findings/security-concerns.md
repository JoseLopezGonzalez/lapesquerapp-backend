# Observaciones de Seguridad — PesquerApp Backend

**Documento de hallazgos** | Auditoría global Laravel  
**Fecha**: 2026-02-15

---

## 1. Aislamiento Multi-Tenant

- **Diseño**: Una base de datos por tenant; el middleware resuelve el tenant por header `X-Tenant` y configura la conexión `tenant` para ese request. Los modelos de negocio usan `UsesTenantConnection`.
- **Fortaleza**: Reduce el riesgo de que un request sirva datos de otro tenant, siempre que todo el acceso a datos pase por la conexión `tenant` configurada en ese request.
- **Estado actual**: Los controladores ya no usan `DB::connection('tenant')->table()` directamente. El único uso residual está en el helper `tenantSetting()` para lectura de settings, que se ejecuta siempre en contexto request (después del middleware tenant). **Mejora respecto a auditoría anterior** (2026-02-14).
- **Recomendación**: Valorar migrar `tenantSetting()` para usar `Setting::query()` en lugar de acceso directo, a fin de unificar el punto de acceso.

---

## 2. Autenticación

- **Sanctum**: Uso de API tokens para autenticación; adecuado para SPA/frontend.
- **Throttling**: Aplicado en login y en flujos de magic link y OTP (`throttle:5,1`, `throttle:10,1`) para limitar fuerza bruta y abuso.
- **Magic link y OTP**: Limpieza de tokens antiguos con comando programado; reduce ventana de uso de tokens obsoletos.
- Se asume configuración estándar de expiración de tokens Sanctum y almacenamiento de contraseñas (bcrypt).

---

## 3. Autorización

- **Estado actual**: **Mejora significativa respecto a auditoría anterior** (2026-02-14). Hay ~30 políticas registradas en AuthServiceProvider y **la mayoría de controladores** llaman a `authorize()` para Order, Customer, Pallet, Punch, Production, Setting, Supplier, Employee, Transport, Incoterm, PaymentTerm, Country, Tax, FishingGear, etc.
- **Implicación**: La autorización se aplica a nivel de recurso (view, create, update, delete); el middleware de rol actúa como filtro grueso y las políticas como filtro fino.
- **Recomendación**: Mantener y verificar que los nuevos recursos incluyan `authorize()` en controladores.

---

## 4. Datos Sensibles y Configuración

- **Settings por tenant**: Tabla `settings` (key-value) con modelo `Setting` y `SettingService`.
- **Contraseña de correo**: `SettingService` ofusca `company.mail.password` en respuestas (devuelve `********`); evita sobrescribir la contraseña si no se envía en el request.
- **Exposición al frontend**: El endpoint de settings devuelve solo valores necesarios; la clave sensible se filtra u ofusca.
- **Logs**: Evitar volcar contraseñas ni tokens en logs; el middleware tenant solo loguea en `debug`.

---

## 5. Entrada de Usuario y Validación

- **Form Requests**: **137 clases** cubriendo prácticamente toda la API v2; validación en frontera HTTP coherente.
- **Validación**: Reduce riesgo de inyección y de datos malformados; Eloquent y Query Builder con bindings mitigan SQL injection.

---

## 6. Errores y Respuestas

- **Handler**: Excepciones traducidas a JSON con `message` y `userMessage`; en 500 puede exponerse `error` (mensaje técnico). Valorar ocultar `error` en producción o limitarlo a códigos.
- **CORS**: Configuración según `config/cors.php` y entorno; asegurar que solo orígenes permitidos tengan acceso en producción.

---

## 7. Resumen de Prioridades

| Prioridad | Tema | Estado / Acción sugerida |
|-----------|------|---------------------------|
| ~~Alta~~ | Autorización por recurso | ✅ **Resuelto**: Policies aplicadas en la mayoría de controladores. |
| ~~Alta~~ | Exposición de settings | ✅ **Mejorado**: SettingService ofusca contraseña; modelo Setting en uso. |
| Media | Acceso directo en `tenantSetting()` | Valorar migrar a Setting model para unificar acceso. |
| Media | Respuestas de error en producción | Revisar si `error` en 500 debe ocultarse o acotarse. |
| Baja (preventiva) | Jobs futuros | Si se usan colas, asegurar tenant en payload y configuración en worker. |

**Conclusión**: La seguridad y la autorización han mejorado de forma significativa respecto a la auditoría anterior. Las prioridades altas anteriores están resueltas o mitigadas.
