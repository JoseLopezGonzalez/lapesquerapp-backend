# Análisis: ausencia de rastro de la implementación con contraseña (usuario)

Documento de referencia que enumera **dónde se ha eliminado** la lógica de contraseña de usuario y **qué referencias a "password" quedan** (y por qué son correctas).

---

## ✅ Eliminado (contraseña de usuario)

| Ubicación | Estado |
|-----------|--------|
| Columna `users.password` | Eliminada por migración `2026_02_10_150000_drop_password_from_users_table`. |
| Tabla `password_reset_tokens` | Eliminada por migración `2026_02_10_150001_drop_password_reset_tokens_table`. |
| `AuthController::login()` | Ya no valida email/password; devuelve 400. |
| `UserController::store` / `update` | No aceptan ni guardan `password`. |
| `User` model | `password` quitado de `$fillable`, `$hidden`, `$casts`. |
| `createTenantUser()` | Sin parámetro password; no asigna password. |
| Comando `tenant:create-user` | Sin argumento password. |
| `UserFactory` | Sin atributo `password`. |
| Seeders (StoreOperator, AlgarSeafood) | Sin `password` en el array de creación. |
| **Docs actualizados** | 80-Usuarios, 95-Modelos-Referencia, 96-Restricciones, 02-Autenticacion-Autorizacion, 00-Introduccion, Guias frontend, API-references, 89, 87. |

---

## 🔍 Referencias a "password" que quedan (y son correctas)

Son **contraseñas de servicios** o **código/config genérico**, no de usuario:

| Archivo | Uso |
|---------|-----|
| `config/company.php` | `mail.password` → contraseña SMTP del tenant. |
| `config/database.php` | `DB_PASSWORD`, `REDIS_PASSWORD` → conexión BD/Redis. |
| `config/mail.php` | `MAIL_PASSWORD` → correo. |
| `config/hashing.php` | Comentarios genéricos de Laravel sobre hashing. |
| `config/auth.php` | `passwords` broker y `password_timeout`; el broker apunta a `password_reset_tokens` (tabla ya eliminada); hay comentario indicando que no se usa. |
| `SettingController.php` | `company.mail.password` → configuración SMTP en settings. |
| `TenantMailConfigService.php` | `company.mail.password` → SMTP. |
| `Handler.php` | `'password', 'password_confirmation', 'current_password'` en `$dontFlash` → no exponer en sesión/redirect (genérico Laravel). |
| `TrimStrings.php` | Exclusión de `password` etc. al recortar (genérico Laravel). |
| `Kernel.php` | Alias `password.confirm` → middleware estándar Laravel (no usado en nuestras rutas). |
| `SETTINGS-EMAIL-*.md` | Contraseña del **correo** (SMTP), no de usuario. |
| Migraciones **históricas** | `2014_10_12_000000_create_users_table` y `2014_10_12_100000_create_password_reset_tokens` crean la columna/tabla que **luego** se eliminan en migraciones posteriores; no se borran las migraciones antiguas. |
| Migraciones de **drop** | `2026_02_10_150000` y `2026_02_10_150001` mencionan "password" en el nombre/comentario/down(); es esperado. |
| Documentación | Guías y 89/87 hablan de "sin password" o "password eliminado"; son aclaraciones, no lógica. |

---

## Conclusión

**No queda lógica activa de contraseña de usuario** en código, BD ni documentación de producto. Las únicas apariciones de "password" son:

- Contraseñas de **servicios** (SMTP, BD, Redis, mail).
- Configuración y middleware **genéricos** de Laravel.
- Migraciones **históricas** o de **eliminación**.
- Documentación que **aclara** que no se usa contraseña.

Para comprobar en el futuro: buscar `Hash::` en código (no debe haber uso para contraseña de usuario), `password` en modelo `User` y en validación de auth/usuarios, y columna/tabla en migraciones; no debe haber flujo de login ni de creación/actualización de usuario que use contraseña.
