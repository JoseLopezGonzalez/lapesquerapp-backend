# Observaciones de Seguridad y Autorización — PesquerApp Backend
**Fecha**: 2026-03-24 | **Auditoría**: Principal Backend v2 (actualización con evidencia de código)

---

## Fortalezas confirmadas

- **Aislamiento tenant**: `UsesTenantConnection` en 121 modelos. Solo 2 usos de `DB::connection('tenant')->table()` en todo el proyecto (`TenantOnboardingService.php:183` y `AuthController.php:217`) — ambos justificados y en contextos controlados.
- **42 Policies** registradas + **246 llamadas a `authorize()`** en controladores.
- **Rate limiting** en todos los endpoints de auth: `throttle:5,1` en login/request-access/magic-link, `throttle:10,1` en verify.
- **Superadmin segregado**: middleware `SuperadminMiddleware`, modelo `SuperadminUser` separado, tokens propios.
- **Settings**: `GET /settings` enmascara contraseñas (no expone secrets en texto plano).
- **CORS dinámico**: `DynamicCorsMiddleware` valida el origen contra tenants activos con cache.

---

## Riesgo SC1 — Magic Link / OTP: Vulnerabilidad TOCTOU en token consumption (ALTO)

**Archivos**:
- `app/Http/Controllers/v2/AuthController.php:100-128` (verifyMagicLink)
- `app/Http/Controllers/v2/AuthController.php:135-162` (verifyOtp)
- `app/Models/MagicLinkToken.php:45-48` (markAsUsed)

**Descripción**: El flujo de verificación de token one-time tiene una ventana de race condition (TOCTOU: Time-Of-Check vs Time-Of-Use):

```php
// AuthController.php:104-107
$record = MagicLinkToken::valid()          // CHECK: ¿es válido?
    ->magicLink()
    ->where('token', $hashedToken)
    ->first();                              // ← sin lockForUpdate

// ... 3 líneas de validación de actor ...

$record->markAsUsed();                      // USE: marcar como usado
// markAsUsed() = $this->update(['used_at' => now('UTC')])
```

**Escenario de explotación**: Si dos peticiones HTTP llegan simultáneamente con el mismo token (ej. doble-click del usuario, retry del cliente), ambas ejecutan `valid()->first()` antes de que cualquiera ejecute `markAsUsed()`. Ambas ven `used_at = null` → ambas proceden → se emiten **2 tokens Sanctum válidos** con 1 solo magic link one-time.

**Alcance**: Afecta a `verifyMagicLink()` (línea 100) y `verifyOtp()` (línea 135) con el mismo patrón.

**Mitigación** (fix atómico):
```php
// Opción recomendada: UPDATE atómico con verificación de affected rows
$affected = MagicLinkToken::valid()
    ->magicLink()
    ->where('token', $hashedToken)
    ->update(['used_at' => now('UTC')]);

if ($affected === 0) {
    return response()->json(['message' => 'El enlace no es válido o ha expirado.'], 400);
}
$record = MagicLinkToken::where('token', $hashedToken)->first();
```

---

## Riesgo SC2 — NFC Punch: Doble fichaje por race condition (MEDIO-ALTO)

**Archivo**: `app/Services/PunchEventWriteService.php:19-51, 321-335`

`storeFromNfc()` llama a `determineEventType()` fuera de la transacción. `determineEventType()` lee el último fichaje del empleado sin `lockForUpdate`. Si dos taps NFC del mismo empleado llegan en < 100ms:

1. Request A: lee último evento → determina TYPE_IN
2. Request B: lee último evento → determina TYPE_IN (aún no hay nuevo registro)
3. Request A: crea PunchEvent TYPE_IN ✓
4. Request B: crea PunchEvent TYPE_IN ✗ — debería ser TYPE_OUT

**Resultado**: dos fichajes consecutivos del mismo tipo. Los datos de control horario quedan inconsistentes. No hay unique constraint en la tabla que lo impida.

**Fix**: mover la determinación del tipo dentro de la transacción con `lockForUpdate`.

---

## Riesgo SC3 — 5 controladores sin Policy en rutas de negocio (MEDIO)

**Controladores sin ninguna llamada a `authorize()` o `Gate::`**:

| Controlador | Protección actual | Riesgo |
|---|---|---|
| `AuthController.php` | — | OK (contexto de auth) |
| `CrmDashboardController.php` | role middleware | Todos los roles con acceso pueden ver todos los datos de todos los comerciales |
| `CrmAgendaController.php` | role middleware | Un comercial puede leer/modificar agenda de otro comercial |
| `OrdersReportController.php` | role middleware | Cualquier rol con acceso descarga todos los pedidos |
| `PdfExtractionController.php` | role middleware | Sin restricción de uso por recurso |
| `RoleController.php` | role middleware | Opciones de roles visibles para todos los roles |

**Nota**: El comentario en `routes/api.php:228` documenta explícitamente la deuda: _"por ahora todas accesibles para todos los roles (luego: policies y restricciones)"_.

**Riesgo real para CRM**: Un usuario con rol `comercial` que acceda a `/crm/agenda` puede ver la agenda de cualquier otro comercial si el servicio no filtra por usuario. Requiere revisión de `CrmAgendaService` para confirmar si hay scope por usuario.

---

## Riesgo SC4 — perPage sin límite: vector de extracción masiva de datos (MEDIO)

**Patrón**: `$perPage = $request->input('perPage', 10)` sin cap en ~12 servicios.

Un usuario autenticado con rol básico puede ejecutar:
```
GET /api/v2/orders?perPage=999999
```
Y obtener todos los pedidos del tenant en una sola respuesta. Aunque la autorización de tenant está garantizada (datos propios), esto facilita la exfiltración masiva de datos de negocio por un usuario interno malintencionado o una sesión comprometida.

---

## Riesgo SC5 — .env.example con IP real de producción (BAJO)

**Archivo**: `.env.example`
```
DB_HOST=94.143.137.84   ← IP real del servidor de producción IONOS
DB_PASSWORD='PFkXCoswvTzsr8f5...'  ← contraseña real visible
```

`.env.example` está en el repositorio y puede ser indexado por GitHub si el repo es público o si se filtra. La contraseña debería ser un placeholder genérico.

---

## Riesgo SC6 — tymon/jwt-auth sin uso aparente (BAJO)

**Evidencia**: `composer.json` incluye `tymon/jwt-auth: ^2.1` pero todas las rutas activas usan `auth:sanctum`. JWT puede tener rutas residuales registradas en `AuthServiceProvider` o en providers del paquete. Requiere verificación de que no hay endpoints activos con autenticación JWT no auditados.

---

## Evaluación actualizada

| Aspecto | Nota anterior | Nota actual | Cambio |
|---|---|---|---|
| Aislamiento tenant | 9/10 | 9/10 | — |
| Autorización por recurso (Policies) | 8/10 | 7.5/10 | CRM sin Policy |
| Seguridad de autenticación | 8/10 | 7/10 | TOCTOU en magic link |
| Protección de endpoints sensibles | 8/10 | 7.5/10 | perPage unbounded |
| Seguridad operacional | 7/10 | 6.5/10 | .env.example con datos reales |

**Seguridad global**: **7.5/10** (vs 8/10 anterior — la bajada refleja evidencia concreta de race condition en auth y ausencia de policies en CRM)
