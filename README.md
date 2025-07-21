# PesquerApp – Laravel API (Backend)

**PesquerApp** es una plataforma ERP multiempresa (_multi-tenant_) diseñada especialmente para pequeñas y medianas industrias del sector pesquero y distribuidores. Este repositorio contiene la API principal, desarrollada en Laravel, que sirve como núcleo de comunicación entre las interfaces de usuario y las bases de datos de cada empresa.

---

## 🚀 Características principales

- 🌐 Arquitectura SaaS multi-tenant con subdominios tipo `empresa.pesquerapp.es`
- 🔁 Cambio dinámico de base de datos según el subdominio (`X-Tenant`)
- 🧾 Módulo avanzado de gestión de pedidos con generación de documentos PDF y envío por email
- 🏷️ Generación e impresión de etiquetas con códigos de barras y QR
- 📦 Control de stock en almacenes reales mediante mapas interactivos de palets y cajas
- 🧠 Análisis de producción con sistema de diagrama de nodos
- 🤖 Extracción de datos con IA desde PDFs de lonjas locales
- 🔐 Sistema de autenticación por token (Laravel Sanctum)

---

## 🧱 Tecnologías utilizadas

- **Laravel 11**
- **MySQL** (una base central + una por tenant)
- **Sanctum** para autenticación
- **Docker / Coolify** para despliegue

---

## ⚙️ Arquitectura

- Una sola API (`api.pesquerapp.es`) sirve a todas las empresas
- Cada empresa tiene su propia base de datos (`db_empresa1`, `db_empresa2`, etc.)
- Se utiliza un **middleware** que:
  - Detecta la cabecera `X-Tenant`
  - Busca el subdominio en la tabla `tenants` de la base central
  - Cambia la conexión activa a la base de datos correspondiente (`DB::setDefaultConnection`)

---

## 🧑‍💼 Superusuario (modo invisible)

- Existen usuarios `superadmin` definidos en la base central
- Estos pueden iniciar sesión desde cualquier subdominio sin estar presentes en su base de datos
- Laravel simula la sesión de forma segura y sin alterar el sistema de usuarios del tenant

---


## 🔐 Autenticación

Se utiliza **Laravel Sanctum** para proteger rutas y generar tokens para usuarios.

Para iniciar sesión:

```http
POST /api/login
Headers:
  X-Tenant: empresa1
Body:
  email, password
```

---

## 🗃️ Endpoints principales

| Método | Ruta               | Descripción                          |
|--------|--------------------|--------------------------------------|
| POST   | /login             | Login de usuarios o superuser        |
| GET    | /orders            | Listado de pedidos del tenant activo |
| GET    | /stores            | Consulta de stock por almacén        |
| POST   | /pallets           | Consulta entre los palets del stock  |

** Revisar endpoints y funciones reales.
---

## 🧠 Pendiente

- [ ] Sistema de auditoría y logs por empresa
- [ ] Comandos automáticos para crear nuevas empresas y bases de datos
- [ ] Panel de control para el administrador global

---

## 📄 Licencia

Este proyecto es privado y propiedad de [La Pesquerapp S.L.](https://lapesquerapp.es).  
No distribuir sin autorización.

---
&nbsp;

&nbsp;

&nbsp;

## 🛠️ Instalación local del proyecto en VS Code

Sigue los siguientes pasos para clonar, instalar dependencias y ejecutar el entorno de desarrollo localmente con VS Code.

---

### 🔁 1. Clona el repositorio

```bash
git clone https://github.com/tuusuario/nombre-del-repositorio.git
cd nombre-del-repositorio
```

---

### ⚙️ 2. Abre el proyecto en VS Code

```bash
code .
```

Asegúrate de tener la extensión de PHP, Laravel y soporte para Blade instaladas en VS Code para una mejor experiencia.

---

### 📦 3. Instala dependencias de backend (Laravel)

```bash
composer install
```

---

### 📦 4. Instala dependencias de frontend (Vite, Tailwind, etc.)

```bash
npm install
```

---

### 🔑 5. Copia y configura el archivo `.env`

```bash
cp .env.example .env
php artisan key:generate
```

Edita `.env` con tus credenciales de base de datos locales si es necesario.

---

### 🛢️ 6. Ejecuta migraciones (si aplica)

```bash
php artisan migrate
```

---

### 🧵 7. Compila los assets de frontend (modo desarrollo)

```bash
npm run dev
```

---

### 🚀 8. Inicia el servidor de desarrollo

```bash
php artisan serve
```

---

### ✅ Acceso local

Una vez ejecutado todo, accede a tu API o frontend en:

```
http://127.0.0.1:8000
```

---
&nbsp;

&nbsp;

&nbsp;

## 🚀 Despliegue de la API Laravel en Coolify con Dockerfile

Este proyecto está preparado para desplegarse automáticamente en [Coolify](https://coolify.io) utilizando un repositorio de GitHub y un `Dockerfile` personalizado.

---

### 📦 Requisitos previos

- Tener Coolify instalado y en ejecución.
- Tener acceso a un dominio (ej. `api.tudominio.com`) y poder modificar sus DNS.
- Repositorio de GitHub con este proyecto Laravel y su `Dockerfile`.

---

### 🔧 Pasos de despliegue

#### 1. Crear nueva aplicación en Coolify

- Tipo de aplicación: **Dockerfile**
- Fuente: **Repositorio de GitHub**
- Rama: `main` (o la que estés usando)
- Ruta del `Dockerfile`: raíz del repositorio (por defecto)

---

#### 2. Añadir variables de entorno

En el apartado `Environment Variables` del servicio, añade al menos:

```env
APP_NAME=PesquerApp
APP_ENV=production
APP_KEY=base64:XXXXXXXXXXXXXXX
APP_DEBUG=false
APP_URL=https://api.tudominio.com

LOG_CHANNEL=stderr

DB_CONNECTION=mysql
DB_HOST=nombre-servicio-db                
DB_PORT=3306
DB_DATABASE=nombre_bd
DB_USERNAME=usuario
DB_PASSWORD=contraseña
```

> ⚠️ Genera previamente el `APP_KEY` si no está generado en tu repositorio, o hazlo dentro del contenedor con:
>
> ```bash
> php artisan key:generate
> ```

---

#### 3. Montar volúmenes persistentes

En la pestaña **Volumes** del servicio Laravel:

| Ruta del contenedor           | Nombre del volumen (crea si no existe) |
|-------------------------------|----------------------------------------|
| `/app/storage`                | `laravel-storage`                      |
| `/app/bootstrap/cache`        | `laravel-bootstrap-cache`              |

---

#### 4. Configurar dominio personalizado

En la pestaña **Domains** del servicio:

- Añade el dominio completo (ej. `api.lapesquerapp.es`)
- Activa **HTTPS con Let's Encrypt**
- En tu proveedor DNS (ej. IONOS), crea un registro **A**:

```
Nombre: api
Tipo: A
Valor: <IP de tu servidor Coolify>
```

---

#### 5. Verificar y ajustar puertos

En la pestaña **Ports**, asegúrate de que:

| Host Port | Container Port |
|-----------|----------------|
| `80`      | `80`           |

> ⚠️ Si estás usando `php:apache`, este es el puerto correcto.  
> Si usas `php artisan serve`, el mapeo debe ser `8000:8000`.

---

#### 6. Desactivar caché de build

- Ve a la pestaña **Build Options**
- Activa ✅ `Disable build cache`

Esto evita problemas con versiones anteriores del contenedor.

---

#### 7. Post-deploy: comandos recomendados

Desde la Terminal de Coolify o como script post-deploy, ejecuta:

```bash
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear
php artisan migrate --force
php artisan storage:link
```

---

### ✅ Resultado esperado

Después del despliegue exitoso, la API estará accesible en:

```
https://api.lapesquerapp.es
```


---
&nbsp;

&nbsp;

&nbsp;

## 👤 Crear manualmente usuarios en tenants desde Tinker

En esta API multitenant, puedes crear usuarios directamente desde consola sin pasar por la interfaz, útil para entornos de prueba, recuperación o creación rápida de administradores.

### ✅ Paso 1: Usar el helper `createTenantUser`

El helper `createTenantUser()` está definido en `app/Helpers/tenant_helpers.php` y permite crear un usuario en la base de datos deseada, generando la contraseña cifrada correctamente y asignando un rol por nombre si se desea.

### 📦 Sintaxis

```php
createTenantUser(string $database, string $name, string $email, string $password, ?string $roleName = null)
```

- `database`: nombre de la base de datos (tenant) donde insertar el usuario.
- `name`: nombre del usuario.
- `email`: correo electrónico.
- `password`: contraseña en texto plano (se cifra con `bcrypt` automáticamente).
- `roleName` (opcional): nombre del rol a asignar (por ejemplo, `superuser`).

### 🚀 Ejemplo desde Tinker

```bash
php artisan tinker
```

```php
createTenantUser('test', 'Admin', 'admin@lapesquerapp.es', 'admin', 'superuser');
createTenantUser('pymcolorao', 'Gestor', 'gestor@pymcolorao.com', '12345678', 'manager');
```

Esto insertará los usuarios directamente en las tablas `users` de cada base de datos tenant y les asignará el rol indicado si existe.

### 🛠️ Requisitos

- Tener el helper registrado en `composer.json`:

```json
"autoload": {
    "files": [
        "app/Helpers/tenant_helpers.php"
    ]
}
```

- Ejecutar `composer dump-autoload` tras crear o editar el helper.

- El modelo `User` debe tener el método `assignRole($roleName)` implementado correctamente y la relación `roles()` definida mediante tabla pivote (`role_user`).

---

🧠 Tip: Este helper usa Eloquent con `setConnection('tenant')` para trabajar correctamente en entornos multitenant.



---
&nbsp;

&nbsp;

&nbsp;

# 📦 Migraciones para la base de datos de cada Tenant

Este documento resume cómo debes crear y ejecutar migraciones que afecten solo a la base de datos individual de cada empresa (Tenant) en tu arquitectura multitenant.

---

## 📁 Estructura de las migraciones

Guarda las migraciones específicas de los tenants en:

```
database/migrations/companies/
```

Por ejemplo:
```
database/migrations/companies/2025_07_21_000000_create_settings_table.php
```

---

## 🛠 Crear una migración para los tenants

Usa el comando Artisan con la ruta personalizada:

```bash
php artisan make:migration create_settings_table --path=database/migrations/companies
```

---

## 🔐 Proteger la migración para que no se ejecute en la base central

En el archivo de la migración, añade este control en `up()` y `down()`:

```php
if (config('database.default') !== 'tenant') {
    return;
}
```

---

## 🚀 Ejecutar las migraciones en todas las bases de datos de tenants

Usa el comando Artisan personalizado que recorre todos los tenants:

```bash
php artisan tenants:migrate
```

Este comando:
- Lee los tenants activos desde la base central
- Cambia dinámicamente la conexión a cada base
- Ejecuta solo las migraciones dentro de `migrations/companies/`

---

## ✅ Opciones adicionales

- `--fresh`: Borra y vuelve a ejecutar las migraciones
- `--seed`: Ejecuta también el seeder `TenantDatabaseSeeder` por cada tenant

Ejemplo:

```bash
php artisan tenants:migrate --fresh --seed
```
