# Pasos para deploy en desarrollo (PesquerApp Backend)

Entorno local con **Docker Sail** (MySQL, Redis, Mailpit). Sigue estos pasos en orden.

> **Primera vez:** Si prefieres una guía paso a paso con comprobaciones en cada etapa, usa **[Deploy en desarrollo – Guía paso a paso (primera vez)](./deploy-desarrollo-guiado.md)**.

---

## Requisitos previos

- **Docker Desktop** instalado y en ejecución (con WSL 2 si usas Windows).
- **Composer** y **PHP 8.1+** en tu máquina (para ejecutar `artisan` antes de levantar Sail, opcional).
- Repositorio clonado y en la raíz del proyecto: `pesquerapp-backend`.

---

## 1. Configurar variables de entorno

```bash
# Desde la raíz del proyecto
cp .env.sail.example .env
```

Si ya tienes un `.env` (por ejemplo de producción), **no lo sobrescribas**. Copia solo las variables que necesites para desarrollo desde `.env.sail.example` (DB_HOST=mysql, DB_DATABASE=pesquerapp, REDIS_HOST=redis, etc.).

Generar la clave de aplicación si falta:

```bash
php artisan key:generate
```

(O con Sail ya levantado: `./vendor/bin/sail artisan key:generate`.)

---

## 2. Levantar los contenedores (Sail)

```bash
./vendor/bin/sail up -d
```

La primera vez puede tardar unos minutos (descarga de imágenes).

Comprobar que todo está en marcha:

```bash
./vendor/bin/sail ps
```

Debes ver en estado **Running**: `laravel.test`, `mysql`, `redis`, `mailpit`.

---

## 3. Migraciones (base de datos central)

```bash
./vendor/bin/sail artisan migrate
```

Esto crea/actualiza las tablas de la base **central** (por ejemplo la tabla `tenants`).

---

## 4. Tenants (bases de datos por empresa)

El proyecto es **multi-tenant**. Para tener datos de desarrollo necesitas al menos un tenant activo con su base de datos creada.

- Si ya tienes tenants en la BD central, crea manualmente la base de datos de cada tenant en MySQL (mismo usuario/contraseña que en `.env`) y luego ejecuta:

```bash
./vendor/bin/sail artisan tenants:migrate --seed
```

- Si es la primera vez y no hay tenants, tendrás que dar de alta un tenant (subdominio, nombre de BD, etc.) según el flujo de tu aplicación o con un seeder/comando propio, crear su BD en MySQL y después ejecutar `tenants:migrate --seed`.

El `--seed` ejecuta en cada tenant los catálogos del menú y usuarios: **Categorías y Familias** (productos), **Zonas de Captura**, **Artes de Pesca**, **Especies**, **Países**, **Formas de Pago**, **Incoterms**, **Transportes**, **Comerciales**, **Zonas FAO**, **Usuarios** y operario de tienda. No se siembra la tabla `calibers` por defecto (no es entidad del menú). **Nota:** Los roles están en `users.role`, no hay tabla `roles`.

---

## 5. Comprobar que todo responde

- **Backend:**  
  `http://localhost` (o `http://localhost:8000` si en `.env` tienes `APP_PORT=8000`).

- **Health de la API** (requiere cabecera `X-Tenant`):  
  ```bash
  curl -s -H "X-Tenant: dev" http://localhost:8000/api/health
  ```  
  Debe devolver algo como: `{"status":"ok","app":"PesquerApp","env":"local"}`.

- **Mailpit (correos de desarrollo):**  
  http://localhost:8025  

---

## 6. URL para el frontend y tenant de desarrollo

### Qué URL darle al frontend (API)

La **base URL de la API** que debe usar el frontend es la misma que el backend:

- Si el backend está en el puerto **80**: `http://localhost`
- Si usas **APP_PORT=8000** (recomendado en WSL cuando el 80 está ocupado): `http://localhost:8000`

En el frontend configura por ejemplo:

- **Next.js:** `NEXT_PUBLIC_API_URL=http://localhost:8000` (las rutas son `/api/v2/...`; la base es la raíz del backend).
- Todas las peticiones a la API deben llevar la cabecera **`X-Tenant: dev`**.

### Tenant en desarrollo

En la BD central hay un tenant de desarrollo creado por `deploy-dev.sh` / `insert-tenant-dev.sql`:

| Campo      | Valor           |
|-----------|------------------|
| **Subdominio** (X-Tenant) | `dev` |
| **Nombre** | Desarrollo       |
| **Base de datos** | pesquerapp_dev |

**Importante:** Los usuarios, zonas FAO, calibres y settings están en la **base de datos del tenant** (`pesquerapp_dev`), no en la central (`pesquerapp`). Si no ves datos (usuarios, etc.), comprueba que estés mirando la BD `pesquerapp_dev` y, si está vacía, ejecuta el seed del tenant:

```bash
./vendor/bin/sail artisan tenants:seed --class=TenantDatabaseSeeder
```

Para comprobar usuarios en el tenant: en MySQL abre la BD **pesquerapp_dev** (no `pesquerapp`) y ejecuta `SELECT id, name, email FROM users;`.

El frontend debe enviar en **todas** las peticiones a la API la cabecera:

```http
X-Tenant: dev
```

### Usuarios y emails en desarrollo

Los seeders crean estos usuarios en el tenant `dev` (emails “inventados”):

| Email | Nombre | Rol |
|-------|--------|-----|
| admin@pesquerapp.com | José Admin | Administrador |
| manager@pesquerapp.com | Carlos Manager | Técnico |
| operator@pesquerapp.com | Ana Operadora | Operario |
| operator1@pesquerapp.com … operator5@pesquerapp.com | Operador Prueba 1…5 | Operario |
| store.operator@test.com | Store Operator Test | Operario |

**Resumen:** En el login del frontend introduce cualquiera de esos emails (por ejemplo **admin@pesquerapp.com**). El correo llegará a Mailpit (http://localhost:8025), donde puedes ver el magic link o el código OTP.

**Si el frontend muestra "Cuentas deshabilitadas / suscripción caducada":** El endpoint `GET /api/v2/public/tenant/dev` devuelve `active: false` si el tenant no está activo en la BD central. El script `deploy-dev.sh` y `insert-tenant-dev.sql` dejan el tenant `dev` con `active = 1`. Si ya tenías el tenant creado antes con `active = 0`, ejecuta de nuevo `./vendor/bin/sail mysql < insert-tenant-dev.sql` (el `UPDATE` del script lo reactiva) o en MySQL: `UPDATE pesquerapp.tenants SET active = 1 WHERE subdomain = 'dev';`. Opcionalmente el frontend puede hacer un bypass en desarrollo (subdominio `dev` + host localhost) para no bloquear el login; a largo plazo es mejor que el backend devuelva `active: true` para dev.

### Cómo probar Magic Link / OTP con emails inventados

En desarrollo **no se envían correos a internet**: todos van al contenedor **Mailpit**.

1. En el frontend, el usuario introduce uno de los emails anteriores (ej. `admin@pesquerapp.com`) y pide “Acceder” (magic link o OTP).
2. El backend envía el email (magic link + código OTP) a **Mailpit**.
3. Abres **http://localhost:8025** (Mailpit) y ves el correo entrante.
4. Opciones:
   - **Magic link:** haces clic en el enlace del correo. Ese enlace apunta al **frontend** (configurado en `FRONTEND_URL` del backend, ej. `http://localhost:3000`). El frontend debe leer el `token` de la URL y llamar a `POST /api/v2/auth/magic-link/verify` con ese token y `X-Tenant: dev`.
   - **OTP:** copias el código de 6 dígitos del correo y lo introduces en el frontend; el frontend llama a `POST /api/v2/auth/otp/verify` con `email` y `code`, y cabecera `X-Tenant: dev`.

Así la autenticación OTP/magic link funciona en local aunque el email sea inventado: solo tienes que mirar el correo en Mailpit.

En el backend, en `.env`, debe estar **`FRONTEND_URL=http://localhost:3000`** (o el puerto donde corra tu frontend) para que el enlace del magic link apunte a tu app.

**Si aparece "No se pudo enviar el correo. Compruebe la configuración de email del tenant":** En local, si el tenant no tiene configuración de email (`company.mail.*`), el backend usa la configuración por defecto de Laravel (`.env`: Mailpit). Comprueba que en `.env` tengas `MAIL_MAILER=smtp`, `MAIL_HOST=mailpit`, `MAIL_PORT=1025` y `MAIL_FROM_ADDRESS` (ej. `noreply@pesquerapp.local`). No hace falta configurar email por tenant en desarrollo.

---

## 7. Frontend (Next.js) en desarrollo

Si trabajas con el frontend en local:

1. **URL de la API:** `NEXT_PUBLIC_API_URL=http://localhost:8000` (o el puerto que tengas en `APP_PORT`). Todas las peticiones con cabecera **`X-Tenant: dev`**.
2. Para autenticación con Sanctum (cookies), usa **`withCredentials: true`** en axios/fetch.
3. CORS ya permite `http://localhost:3000` y `http://127.0.0.1:3000`.

---

## Cuando quieras desplegar de nuevo

Hay **dos situaciones** y un script para cada una:

### Opción A: Solo levantar el entorno (ya desplegaste antes)

Si ya tienes todo configurado y solo quieres que los contenedores estén en marcha:

```bash
./sail-up.sh
```

O manualmente: `./vendor/bin/sail up -d`

---

### Opción B: Desplegar desde cero (primera vez o reset completo)

Script que automatiza todo: Sail, migraciones central, BD del tenant, permisos, tenant en la BD, migraciones y seed del tenant:

```bash
# 1. Asegúrate de tener .env para Sail (si no lo tienes)
cp .env.sail.example .env
php artisan key:generate

# 2. Docker debe estar en ejecución; luego:
./deploy-dev.sh
```

**Qué hace `deploy-dev.sh`:**
- Comprueba que Docker está en marcha
- Levanta Sail (`sail up -d`)
- Ejecuta migraciones de la BD central
- Crea la BD `pesquerapp_dev` y da permisos al usuario `sail`
- Inserta el tenant "dev" (vía `insert-tenant-dev.sql`)
- Ejecuta `tenants:migrate --seed` y `tenants:seed --class=TenantDatabaseSeeder`
- Comprueba el health con `X-Tenant: dev`

**Requisitos:** Docker en marcha, `docker-compose` disponible (ver [instalar-docker-wsl.md](./instalar-docker-wsl.md) si hace falta). En WSL, si el puerto 80 está ocupado, el `.env` debe tener `APP_PORT=8000` (ya está en `.env.sail.example`).

---

## Comandos útiles

| Acción | Comando |
|--------|---------|
| Solo levantar contenedores | `./sail-up.sh` o `./vendor/bin/sail up -d` |
| Parar contenedores | `./sail-down.sh` o `./vendor/bin/sail down` |
| Deploy completo desde cero | `./deploy-dev.sh` |
| Ver logs de los contenedores | `./vendor/bin/sail logs -f` |
| Ejecutar Artisan | `./vendor/bin/sail artisan <comando>` |
| Entrar al contenedor de la app | `./vendor/bin/sail shell` |
| Conectar a MySQL | `./vendor/bin/sail mysql` |
| Recrear BD central y migrar | `./vendor/bin/sail artisan migrate:fresh` |
| Migrar y sembrar tenants | `./vendor/bin/sail artisan tenants:migrate --seed` |
| Health (con tenant) | `curl -H "X-Tenant: dev" http://localhost:8000/api/health` |

---

## Referencias

- [Plan de implementación Sail](./IMPLEMENTATION_PLAN_DOCKER_SAIL.md)
- [Informe de validación final](./FINAL_VALIDATION_REPORT.md)
- [Guía completa entorno Sail (Windows/WSL)](./guia-completa-entorno-sail-windows.md)
