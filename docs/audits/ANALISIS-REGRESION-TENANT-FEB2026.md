# Análisis de regresión: Tenant "no encontrado" (Feb 2026)

**Fecha análisis:** 2026-02-17  
**Período de cambios:** 12–16 febrero 2026  
**Commit referencia "último que funcionaba":** 8097331 (14 feb)

---

## Resumen ejecutivo

El error `"Tenant no encontrado"` al llamar a `GET /api/v2/public/tenant/dev` se debe a que **el tenant `dev` no existe** en la tabla `tenants` de la base central. Los cambios en el código entre el 12 y 16 de febrero **corrigieron un bug** en el modelo Tenant; el comportamiento actual es el esperado.

---

## Cambios relevantes (commits 12–16 feb)

### 1. **Tenant model: conexión a BD** (commit f97f371, 15 feb)

| Antes (8097331) | Después (HEAD) |
|-----------------|----------------|
| `UsesTenantConnection` → conexión `tenant` | `protected $connection = 'mysql'` |

**Análisis:** El modelo `Tenant` debe consultar la **base central** (`pesquerapp`), donde está la tabla `tenants`. Con `UsesTenantConnection` usaba la conexión `tenant`, que tiene `database => ''` y se rellena dinámicamente en `TenantMiddleware`. La ruta pública `/api/v2/public/tenant/{subdomain}` **no pasa por TenantMiddleware**, así que la conexión `tenant` nunca se configura y la consulta a `tenants` fallaría o usaría una BD incorrecta. Usar explícitamente `mysql` es la corrección adecuada.

### 2. **TenantController** (commits b73732b, f97f371)

- Form Request `ShowTenantBySubdomainRequest` para validar subdominio
- `TenantPublicResource` para la respuesta (`{ data: { active, name } }`)
- Mensaje de error en español: `"Tenant no encontrado"`

### 3. **Otras modificaciones**

- CORS, Exception Handler, TenantMiddleware (OPTIONS, CORS)
- Limpieza de caché de configuración (`bootstrap/cache/config.php`)

---

## Causa del error actual

Con el modelo Tenant corregido, `Tenant::where('subdomain', 'dev')->first()` consulta correctamente la base central. Si devuelve `null`, es porque **no existe** el tenant `dev` en la tabla `tenants`.

Posibles motivos:

1. **BD nueva o reiniciada:** `sail down -v` borra volúmenes y datos
2. **Migraciones sin seed de tenant:** `sail artisan migrate` no crea tenants
3. **Nunca se ejecutó** `insert-tenant-dev.sql` o el INSERT manual

---

## Solución: crear el tenant `dev`

```bash
# 1. Crear BD del tenant
./vendor/bin/sail mysql -e "CREATE DATABASE IF NOT EXISTS pesquerapp_dev CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 2. Insertar tenant en tabla central
./vendor/bin/sail mysql -e "USE pesquerapp; INSERT INTO tenants (name, subdomain, \`database\`, active, created_at, updated_at) VALUES ('Desarrollo', 'dev', 'pesquerapp_dev', 1, NOW(), NOW()) ON DUPLICATE KEY UPDATE active=1, updated_at=NOW();"

# 3. Migraciones y seed del tenant
./vendor/bin/sail artisan tenants:migrate --seed
```

O usando el script:

```bash
./vendor/bin/sail mysql < insert-tenant-dev.sql
./vendor/bin/sail artisan tenants:migrate --seed
```

**Comprobar:**

```bash
./vendor/bin/sail mysql -e "USE pesquerapp; SELECT id, name, subdomain, \`database\`, active FROM tenants;"
curl -s http://localhost:8000/api/v2/public/tenant/dev
```

---

## Conclusión

- Los cambios de febrero **arreglaron** el uso incorrecto de la conexión en el modelo Tenant.
- El fallo actual viene de la **ausencia del tenant `dev`** en la base central.
- La solución es crear el tenant con los comandos anteriores y mantenerlo documentado en `deploy-desarrollo-guiado.md` e `insert-tenant-dev.sql`.
