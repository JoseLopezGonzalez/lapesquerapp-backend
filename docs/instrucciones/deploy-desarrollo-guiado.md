# Deploy en desarrollo – Guía paso a paso (primera vez)

**Documento principal de deploy en desarrollo.** Para pasos resumidos y re-deploy, ver también [_archivo/deploy-desarrollo.md](_archivo/deploy-desarrollo.md).

Sigue cada paso en orden. Después de cada uno, comprueba el resultado antes de pasar al siguiente.

---

## Antes de empezar

- [ ] **Docker Desktop** está instalado y **en ejecución** (icono activo en la barra del sistema).
- [ ] Estás en la **raíz del proyecto** (`pesquerapp-backend`), por ejemplo:
  ```bash
  cd /ruta/a/lapesquerapp-backend
  ```

---

## Paso 1 – Variables de entorno para Sail

**Objetivo:** Tener un `.env` que apunte a los contenedores (MySQL, Redis, Mailpit).

**Si es la primera vez y no tienes un .env que quieras conservar:**

```bash
cp .env.sail.example .env
```

**Si ya tienes un .env** (por ejemplo de producción), ábrelo y asegúrate de que al menos tengan estos valores **cuando vayas a usar Sail**:

- `DB_HOST=mysql`
- `DB_PORT=3306`
- `DB_DATABASE=pesquerapp`
- `DB_USERNAME=sail`
- `DB_PASSWORD=password`
- `REDIS_HOST=redis`
- `REDIS_PORT=6379`
- `CACHE_DRIVER=redis`
- `QUEUE_CONNECTION=redis`
- `SESSION_DRIVER=redis`
- `SANCTUM_STATEFUL_DOMAINS=localhost:3000,127.0.0.1:3000`

**Generar la clave de la aplicación:**

```bash
php artisan key:generate
```

**Comprobar:** El archivo `.env` existe y tiene una línea `APP_KEY=base64:...` (no vacía).

---

## Paso 2 – Levantar Docker (Sail)

**Objetivo:** Que estén en marcha los contenedores de la app, MySQL, Redis y Mailpit.

```bash
./vendor/bin/sail up -d
```

La primera vez puede tardar varios minutos (descarga de imágenes). Cuando termine, ejecuta:

```bash
./vendor/bin/sail ps
```

**Comprobar:** Debes ver **4 contenedores** en estado **Running** (o "Up"):
- `...-laravel.test-1` (o similar)
- `...-mysql-1`
- `...-redis-1`
- `...-mailpit-1`

**Si algo falla:** Revisa que Docker Desktop esté abierto y que no tengas otro servicio usando los puertos 80, 3306, 6379 o 8025. Ver logs: `./vendor/bin/sail logs`.

---

## Paso 3 – Migraciones de la base central

**Objetivo:** Crear las tablas de la base de datos central (entre ellas, la tabla `tenants`).

```bash
./vendor/bin/sail artisan migrate
```

**Comprobar:** El comando termina sin errores y muestra algo como "Migrating: ..." y "Migrated: ...".

**Si falla "could not find driver" o conexión:** Revisa que en `.env` tengas `DB_HOST=mysql` (no 127.0.0.1) y que el contenedor MySQL esté en Running (Paso 2).

---

## Paso 4 – Crear un tenant de desarrollo (primera vez)

El proyecto es **multi-tenant**: cada "empresa" tiene su propia base de datos. Para poder usar la app en desarrollo necesitas **al menos un tenant** y **su base de datos creada**.

### 4.1 Crear la base de datos del tenant en MySQL

Entra a MySQL con Sail y crea una base (por ejemplo `pesquerapp_dev`):

```bash
./vendor/bin/sail mysql
```

Dentro de MySQL:

```sql
CREATE DATABASE IF NOT EXISTS pesquerapp_dev CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
exit;
```

### 4.2 Dar de alta el tenant en la aplicación

Tienes que insertar un registro en la tabla `tenants` de la base **central**. Desde tu máquina, usando la misma BD que usa Laravel (en Sail suele ser `pesquerapp`):

```bash
./vendor/bin/sail mysql -e "USE pesquerapp; INSERT INTO tenants (name, subdomain, database, active, created_at, updated_at) VALUES ('Desarrollo', 'dev', 'pesquerapp_dev', 1, NOW(), NOW());"
```

(Ajusta el nombre de la base central si en tu `.env` usas otra: `DB_DATABASE=pesquerapp`.)

**Comprobar:** Que el registro exista:

```bash
./vendor/bin/sail mysql -e "USE pesquerapp; SELECT id, name, subdomain, database, active FROM tenants;"
```

Deberías ver una fila con `subdomain = dev` y `database = pesquerapp_dev`.

---

## Paso 5 – Migrar y sembrar el tenant

**Objetivo:** Crear todas las tablas del tenant (usuarios, pedidos, etc.) y ejecutar los seeders (usuarios de prueba, zonas FAO, calibres).

```bash
./vendor/bin/sail artisan tenants:migrate --seed
```

**Comprobar:** El comando termina sin errores y muestra mensajes de migración y seed para el tenant (por ejemplo "Migrando tenant: dev" / "Migración completada para: dev").

**Si dice "0 tenants" o no hace nada:** Revisa el Paso 4: que exista un tenant con `active = 1` y que el campo `database` coincida con una base de datos creada en MySQL.

---

## Paso 6 – Comprobar que todo funciona

1. **Backend en el navegador**  
   Abre: **http://localhost**  
   Deberías ver la aplicación Laravel (página de bienvenida o tu app).

2. **Health de la API**  
   En la terminal:
   ```bash
   curl -s http://localhost/api/health
   ```
   Debe responder algo como: `{"status":"ok","app":"PesquerApp","env":"local"}`.

3. **Mailpit (emails de desarrollo)**  
   Abre: **http://localhost:8025**  
   Deberías ver la interfaz de Mailpit (sin emails aún es normal).

4. **Opcional – Base de datos del tenant**  
   ```bash
   ./vendor/bin/sail mysql -e "USE pesquerapp_dev; SELECT id, name, email, role FROM users LIMIT 5;"
   ```
   Deberías ver usuarios de prueba creados por los seeders.

---

## Resumen de comandos (orden)

```bash
# 1. Entorno
cp .env.sail.example .env
php artisan key:generate

# 2. Contenedores
./vendor/bin/sail up -d
./vendor/bin/sail ps

# 3. Migraciones central
./vendor/bin/sail artisan migrate

# 4. Tenant (primera vez): crear BD y registro
./vendor/bin/sail mysql
# Dentro: CREATE DATABASE pesquerapp_dev ... ; exit;

./vendor/bin/sail mysql -e "USE pesquerapp; INSERT INTO tenants (name, subdomain, database, active, created_at, updated_at) VALUES ('Desarrollo', 'dev', 'pesquerapp_dev', 1, NOW(), NOW());"

# 5. Migrar y sembrar tenant
./vendor/bin/sail artisan tenants:migrate --seed

# Alternativa (Sail dev): migrar la BD por defecto como tenant sin registrar tenant
./vendor/bin/sail artisan tenants:dev-migrate --seed

# 6. Comprobar
curl -s http://localhost/api/health
```

---

## Si algo falla

| Problema | Qué revisar |
|----------|--------------|
| `sail up` o `sail ps` falla | Docker Desktop en ejecución; puertos 80, 3306, 6379, 8025 libres. |
| Error de conexión a MySQL en migrate | `.env` con `DB_HOST=mysql`, contenedor mysql en Running. |
| `tenants:migrate` no hace nada | Que exista al menos un tenant activo en `tenants` y que su BD exista en MySQL. Si prefieres no crear tenant aún, usa `tenants:dev-migrate --seed` para migrar la BD por defecto. |
| "No database selected" al usar `migrate --database=tenant` | Usar `tenants:dev-migrate` en lugar de migrar a mano; ese comando configura la conexión tenant con `DB_DATABASE`. |
| Error en seeders (ej. tabla no existe) | Ejecutar primero `tenants:migrate` sin `--seed` y luego `tenants:seed`. |

Cuando hayas completado todos los pasos, tendrás el **deploy en desarrollo** listo para trabajar. Para más detalle, ver [deploy-desarrollo.md](_archivo/deploy-desarrollo.md).
