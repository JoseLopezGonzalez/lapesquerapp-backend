# Guía Completa: Entorno de Desarrollo PesquerApp con Docker Sail

## 📋 Tabla de Contenidos

1. [Requisitos Previos](#requisitos-previos)
2. [Instalación de Docker](#instalación-de-docker)
3. [Configuración Backend Laravel con Sail](#configuración-backend-laravel-con-sail)
4. [Creación de Seeders Completos](#creación-de-seeders-completos)
5. [Configuración Frontend Next.js](#configuración-frontend-nextjs)
6. [Flujo de Trabajo Diario](#flujo-de-trabajo-diario)
7. [Comandos Útiles](#comandos-útiles)
8. [Troubleshooting](#troubleshooting)

---

## 🔧 Requisitos Previos

### Windows
- **Docker Desktop para Windows** (incluye Docker Engine + Docker Compose)
- **WSL 2** (Windows Subsystem for Linux)
- **Git**
- **Node.js 18+** (para Next.js)

### Verificar instalaciones existentes:
```bash
# Verificar Node.js
node --version

# Verificar npm
npm --version

# Verificar Git
git --version
```

---

## 🐳 Instalación de Docker

### Paso 1: Instalar WSL 2 (Windows)

```powershell
# Ejecutar en PowerShell como Administrador
wsl --install

# Reiniciar el equipo

# Verificar instalación
wsl --list --verbose
```

### Paso 2: Instalar Docker Desktop

1. Descargar desde: https://www.docker.com/products/docker-desktop/
2. Ejecutar instalador
3. Durante instalación, marcar: "Use WSL 2 instead of Hyper-V"
4. Reiniciar equipo
5. Abrir Docker Desktop y completar configuración inicial

### Paso 3: Verificar Docker

```bash
# Abrir terminal (PowerShell o CMD)
docker --version
docker compose version

# Probar que funciona
docker run hello-world
```

**Salida esperada:** Mensaje de "Hello from Docker!"

---

## 🚀 Configuración Backend Laravel con Sail

### Paso 1: Preparar proyecto Laravel existente

```bash
# Navegar a tu proyecto Laravel
cd C:\ruta\a\tu\pesquerapp-backend

# Verificar que tengas composer.json
dir composer.json
```

### Paso 2: Instalar Sail en proyecto existente

```bash
# Instalar Sail como dependencia de desarrollo
composer require laravel/sail --dev

# Publicar configuración de Sail
php artisan sail:install
```

**Te preguntará qué servicios quieres:**
```
Which services would you like to install?
  [0] mysql
  [1] pgsql
  [2] mariadb
  [3] redis
  [4] memcached
  [5] meilisearch
  [6] minio
  [7] mailpit
  [8] selenium
```

**Selecciona (separados por coma):**
```
0,3,7
```
Esto instala: MySQL, Redis, Mailpit

### Paso 3: Configurar alias de Sail (opcional pero recomendado)

**Windows PowerShell:**
```powershell
# Editar perfil de PowerShell
notepad $PROFILE

# Agregar esta línea:
function sail { docker compose exec laravel.test php artisan $args }

# Guardar y recargar
. $PROFILE
```

**Alternativa (sin alias):**
Usar siempre `./vendor/bin/sail` en lugar de `sail`

### Paso 4: Configurar archivo .env

```bash
# Copiar .env.example si no tienes .env
copy .env.example .env

# Editar .env con estos valores:
```

**Archivo `.env` - Configuración Docker:**
```env
APP_NAME=PesquerApp
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost

# Database - Docker
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=pesquerapp
DB_USERNAME=sail
DB_PASSWORD=password

# Redis - Docker
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379

# Mail - Docker (Mailpit)
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="noreply@pesquerapp.local"
MAIL_FROM_NAME="${APP_NAME}"

# Cache & Queue
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
```

### Paso 5: Generar APP_KEY

```bash
# Si aún no tienes APP_KEY generada
./vendor/bin/sail artisan key:generate
```

### Paso 6: Levantar contenedores por primera vez

```bash
# Primera vez (descarga imágenes, puede tardar 5-10 min)
./vendor/bin/sail up -d

# Ver logs en tiempo real
./vendor/bin/sail logs -f
```

**Salida esperada:**
```
✔ Container pesquerapp-mysql-1      Started
✔ Container pesquerapp-redis-1      Started
✔ Container pesquerapp-mailpit-1    Started
✔ Container pesquerapp-laravel.test-1  Started
```

### Paso 7: Ejecutar migraciones

```bash
# Crear base de datos y tablas
./vendor/bin/sail artisan migrate

# Si da error de base de datos no existe:
./vendor/bin/sail artisan migrate:fresh
```

### Paso 8: Verificar que funciona

**Abrir navegador:**
- Backend: http://localhost
- Mailpit (emails): http://localhost:8025

**Deberías ver la página de bienvenida de Laravel o tu aplicación**

---

## 🌱 Creación de Seeders Completos

### Paso 1: Estructura de Seeders

```bash
# Crear seeders principales
./vendor/bin/sail artisan make:seeder RolesAndPermissionsSeeder
./vendor/bin/sail artisan make:seeder UsersSeeder
./vendor/bin/sail artisan make:seeder ClientsSeeder
./vendor/bin/sail artisan make:seeder ProductsSeeder
./vendor/bin/sail artisan make:seeder FAOZonesSeeder
./vendor/bin/sail artisan make:seeder CalibersSeeder
./vendor/bin/sail artisan make:seeder OrdersSeeder
```

### Paso 2: DatabaseSeeder.php

**Archivo: `database/seeders/DatabaseSeeder.php`**

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Orden importante: de lo general a lo específico
        $this->call([
            RolesAndPermissionsSeeder::class,
            UsersSeeder::class,
            FAOZonesSeeder::class,
            CalibersSeeder::class,
            ClientsSeeder::class,
            ProductsSeeder::class,
            OrdersSeeder::class,
        ]);
    }
}
```

### Paso 3: Ejemplo - RolesAndPermissionsSeeder

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Crear permisos
        $permissions = [
            'view orders',
            'create orders',
            'edit orders',
            'delete orders',
            'view products',
            'create products',
            'edit products',
            'delete products',
            'view clients',
            'create clients',
            'edit clients',
            'delete clients',
            'view production',
            'manage production',
            'view reports',
            'manage users',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Crear roles
        $adminRole = Role::create(['name' => 'admin']);
        $adminRole->givePermissionTo(Permission::all());

        $managerRole = Role::create(['name' => 'manager']);
        $managerRole->givePermissionTo([
            'view orders',
            'create orders',
            'edit orders',
            'view products',
            'view clients',
            'view production',
            'manage production',
            'view reports',
        ]);

        $operatorRole = Role::create(['name' => 'operator']);
        $operatorRole->givePermissionTo([
            'view orders',
            'view products',
            'view production',
            'manage production',
        ]);
    }
}
```

### Paso 4: Ejemplo - UsersSeeder

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UsersSeeder extends Seeder
{
    public function run(): void
    {
        // Usuario admin
        $admin = User::create([
            'name' => 'José Admin',
            'email' => 'admin@pesquerapp.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('admin');

        // Usuario manager
        $manager = User::create([
            'name' => 'Carlos Manager',
            'email' => 'manager@pesquerapp.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        $manager->assignRole('manager');

        // Usuario operator
        $operator = User::create([
            'name' => 'Ana Operadora',
            'email' => 'operator@pesquerapp.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        $operator->assignRole('operator');

        // Crear 5 usuarios adicionales de prueba
        User::factory(5)->create()->each(function ($user) {
            $user->assignRole('operator');
        });
    }
}
```

### Paso 5: Ejemplo - FAOZonesSeeder

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\FAOZone;

class FAOZonesSeeder extends Seeder
{
    public function run(): void
    {
        $zones = [
            ['code' => '27', 'name' => 'Atlántico Nordeste', 'description' => 'FAO 27 - Zona pesquera del Atlántico Nordeste'],
            ['code' => '34', 'name' => 'Atlántico Centro-Este', 'description' => 'FAO 34 - Zona pesquera del Atlántico Centro-Este'],
            ['code' => '37', 'name' => 'Mediterráneo', 'description' => 'FAO 37 - Mar Mediterráneo y Mar Negro'],
            ['code' => '41', 'name' => 'Atlántico Sudoeste', 'description' => 'FAO 41 - Zona pesquera del Atlántico Sudoeste'],
            ['code' => '47', 'name' => 'Atlántico Sudeste', 'description' => 'FAO 47 - Zona pesquera del Atlántico Sudeste'],
            ['code' => '51', 'name' => 'Océano Índico Occidental', 'description' => 'FAO 51 - Zona pesquera del Índico Occidental'],
            ['code' => '87', 'name' => 'Pacífico Sudeste', 'description' => 'FAO 87 - Zona pesquera del Pacífico Sudeste'],
        ];

        foreach ($zones as $zone) {
            FAOZone::create($zone);
        }

        // Subzonas para FAO 27 (ejemplo)
        $subzones27 = [
            ['code' => '27.8.c', 'name' => 'Cantábrico', 'parent_id' => 1],
            ['code' => '27.9.a', 'name' => 'Golfo de Cádiz', 'parent_id' => 1],
        ];

        foreach ($subzones27 as $subzone) {
            FAOZone::create($subzone);
        }
    }
}
```

### Paso 6: Ejemplo - CalibersSeeder

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Caliber;

class CalibersSeeder extends Seeder
{
    public function run(): void
    {
        $calibers = [
            // Pulpo
            ['name' => 'T1 (>2kg)', 'min_weight' => 2000, 'max_weight' => null, 'species' => 'octopus'],
            ['name' => 'T2 (1-2kg)', 'min_weight' => 1000, 'max_weight' => 2000, 'species' => 'octopus'],
            ['name' => 'T3 (500g-1kg)', 'min_weight' => 500, 'max_weight' => 1000, 'species' => 'octopus'],
            ['name' => 'T4 (<500g)', 'min_weight' => null, 'max_weight' => 500, 'species' => 'octopus'],
            
            // Calamar
            ['name' => 'U5 (<150g)', 'min_weight' => null, 'max_weight' => 150, 'species' => 'squid'],
            ['name' => 'U8 (150-200g)', 'min_weight' => 150, 'max_weight' => 200, 'species' => 'squid'],
            ['name' => 'U10 (200-300g)', 'min_weight' => 200, 'max_weight' => 300, 'species' => 'squid'],
            ['name' => 'U15 (>300g)', 'min_weight' => 300, 'max_weight' => null, 'species' => 'squid'],
            
            // Sepia
            ['name' => '1-2 (>500g)', 'min_weight' => 500, 'max_weight' => null, 'species' => 'cuttlefish'],
            ['name' => '2-4 (200-500g)', 'min_weight' => 200, 'max_weight' => 500, 'species' => 'cuttlefish'],
            ['name' => '4-6 (100-200g)', 'min_weight' => 100, 'max_weight' => 200, 'species' => 'cuttlefish'],
            ['name' => '6+ (<100g)', 'min_weight' => null, 'max_weight' => 100, 'species' => 'cuttlefish'],
        ];

        foreach ($calibers as $caliber) {
            Caliber::create($caliber);
        }
    }
}
```

### Paso 7: Ejecutar todos los seeders

```bash
# Resetear base de datos y ejecutar seeders
./vendor/bin/sail artisan migrate:fresh --seed

# O si ya tienes datos y solo quieres ejecutar seeders:
./vendor/bin/sail artisan db:seed
```

### Paso 8: Verificar datos

```bash
# Conectar a MySQL
./vendor/bin/sail mysql

# Dentro de MySQL:
USE pesquerapp;
SELECT * FROM users;
SELECT * FROM roles;
SELECT * FROM fao_zones;
SELECT * FROM calibers;
exit;
```

---

## ⚛️ Configuración Frontend Next.js

### Paso 1: Identificar URL del backend

```bash
# El backend estará disponible en:
http://localhost
# O si configuraste un puerto específico:
http://localhost:80
```

### Paso 2: Configurar variables de entorno

**Archivo: `pesquerapp-frontend/.env.local`**

```env
# Backend API URL
NEXT_PUBLIC_API_URL=http://localhost/api
NEXT_PUBLIC_API_BASE_URL=http://localhost

# Si usas autenticación con Sanctum
NEXT_PUBLIC_SANCTUM_URL=http://localhost/sanctum/csrf-cookie

# Modo desarrollo
NODE_ENV=development
```

### Paso 3: Actualizar configuración de API (si usas axios)

**Archivo: `lib/axios.js` o similar:**

```javascript
import axios from 'axios';

const api = axios.create({
  baseURL: process.env.NEXT_PUBLIC_API_URL || 'http://localhost/api',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
  withCredentials: true, // Para cookies de Sanctum
});

export default api;
```

### Paso 4: Configurar CORS en Laravel

**Archivo: `config/cors.php` (backend):**

```php
<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    
    'allowed_methods' => ['*'],
    
    'allowed_origins' => [
        'http://localhost:3000',
        'http://localhost:3001',
        'http://127.0.0.1:3000',
    ],
    
    'allowed_origins_patterns' => [],
    
    'allowed_headers' => ['*'],
    
    'exposed_headers' => [],
    
    'max_age' => 0,
    
    'supports_credentials' => true,
];
```

### Paso 5: Instalar dependencias y levantar Next.js

```bash
# Navegar al proyecto frontend
cd C:\ruta\a\tu\pesquerapp-frontend

# Instalar dependencias (si es necesario)
npm install

# Levantar servidor de desarrollo
npm run dev
```

**Salida esperada:**
```
- ready started server on 0.0.0.0:3000, url: http://localhost:3000
- info Loaded env from .env.local
```

### Paso 6: Probar conexión

**Crear archivo de prueba: `pages/api/test.js`**

```javascript
import api from '@/lib/axios';

export default async function handler(req, res) {
  try {
    const response = await api.get('/health'); // O cualquier endpoint que tengas
    res.status(200).json({ 
      success: true, 
      message: 'Conexión exitosa con backend',
      data: response.data 
    });
  } catch (error) {
    res.status(500).json({ 
      success: false, 
      message: 'Error conectando con backend',
      error: error.message 
    });
  }
}
```

**Visitar:** http://localhost:3000/api/test

---

## 💼 Flujo de Trabajo Diario

### Iniciar entorno completo

```bash
# Terminal 1: Backend Laravel con Docker
cd C:\ruta\a\pesquerapp-backend
./vendor/bin/sail up

# Terminal 2: Frontend Next.js
cd C:\ruta\a\pesquerapp-frontend
npm run dev
```

### URLs de desarrollo

| Servicio | URL | Descripción |
|----------|-----|-------------|
| Frontend | http://localhost:3000 | Next.js |
| Backend API | http://localhost/api | Laravel API |
| Backend Web | http://localhost | Laravel Web |
| Mailpit | http://localhost:8025 | Ver emails |
| MySQL | localhost:3306 | Base de datos |

### Detener entorno

```bash
# En la terminal del backend (Ctrl+C o):
./vendor/bin/sail down

# En la terminal del frontend:
Ctrl+C
```

---

## 🛠️ Comandos Útiles

### Laravel Sail

```bash
# Levantar contenedores
./vendor/bin/sail up -d

# Ver logs
./vendor/bin/sail logs -f

# Detener contenedores
./vendor/bin/sail down

# Ejecutar comandos Artisan
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan db:seed
./vendor/bin/sail artisan route:list
./vendor/bin/sail artisan tinker

# Ejecutar tests
./vendor/bin/sail test
./vendor/bin/sail test --filter=OrderTest

# Composer
./vendor/bin/sail composer install
./vendor/bin/sail composer require package-name

# NPM (dentro del contenedor Laravel)
./vendor/bin/sail npm install
./vendor/bin/sail npm run build

# Acceder al contenedor
./vendor/bin/sail shell

# Acceder a MySQL
./vendor/bin/sail mysql

# Ver procesos
./vendor/bin/sail ps
```

### Base de datos

```bash
# Recrear base de datos con seeders
./vendor/bin/sail artisan migrate:fresh --seed

# Solo migraciones
./vendor/bin/sail artisan migrate

# Rollback última migración
./vendor/bin/sail artisan migrate:rollback

# Crear nueva migración
./vendor/bin/sail artisan make:migration create_table_name

# Crear nuevo seeder
./vendor/bin/sail artisan make:seeder TableNameSeeder

# Ejecutar seeder específico
./vendor/bin/sail artisan db:seed --class=UsersSeeder
```

### Next.js

```bash
# Desarrollo
npm run dev

# Build producción
npm run build

# Iniciar producción
npm start

# Limpiar caché
rm -rf .next

# Actualizar dependencias
npm update
```

### Docker

```bash
# Ver contenedores corriendo
docker ps

# Ver todos los contenedores
docker ps -a

# Ver imágenes
docker images

# Eliminar contenedores detenidos
docker container prune

# Eliminar imágenes sin usar
docker image prune

# Ver uso de espacio
docker system df

# Limpiar todo (cuidado)
docker system prune -a
```

---

## 🔍 Troubleshooting

### Problema 1: Puerto 3306 ya en uso (MySQL)

**Error:**
```
Error: bind: address already in use
```

**Solución:**

```bash
# Opción A: Detener MySQL local
services.msc  # Buscar MySQL y detener

# Opción B: Cambiar puerto en docker-compose.yml
# Editar: pesquerapp-backend/docker-compose.yml
mysql:
    ports:
        - '3307:3306'  # Cambiar 3306 a 3307

# Actualizar .env
DB_PORT=3307
```

### Problema 2: Puerto 80 ya en uso (Apache/IIS)

**Solución:**

```bash
# Editar docker-compose.yml
laravel.test:
    ports:
        - '8000:80'  # Cambiar 80 a 8000

# Actualizar .env
APP_URL=http://localhost:8000

# Actualizar frontend .env.local
NEXT_PUBLIC_API_URL=http://localhost:8000/api
```

### Problema 3: Contenedores no levantan

```bash
# Ver logs detallados
./vendor/bin/sail logs

# Reconstruir contenedores
./vendor/bin/sail down
./vendor/bin/sail build --no-cache
./vendor/bin/sail up -d
```

### Problema 4: CORS errors en frontend

**Verificar:**

1. `config/cors.php` incluye `http://localhost:3000`
2. `.env` tiene `SESSION_DRIVER=cookie` y `SANCTUM_STATEFUL_DOMAINS=localhost:3000`
3. Frontend usa `withCredentials: true` en axios

**Archivo `.env` (backend):**
```env
SESSION_DRIVER=cookie
SANCTUM_STATEFUL_DOMAINS=localhost:3000,127.0.0.1:3000
```

### Problema 5: Migraciones fallan

```bash
# Verificar que MySQL está corriendo
./vendor/bin/sail ps

# Verificar conexión
./vendor/bin/sail artisan tinker
DB::connection()->getPdo();

# Recrear base de datos
./vendor/bin/sail artisan db:wipe
./vendor/bin/sail artisan migrate:fresh
```

### Problema 6: Performance lento en Windows

**Solución: Mover proyecto a WSL**

```bash
# Dentro de WSL Ubuntu
cd ~
mkdir projects
cd projects
git clone [tu-repo]

# Usar VS Code con WSL
code .
```

**Beneficio:** 10-20x más rápido en I/O de archivos

### Problema 7: Docker Desktop no inicia

1. Verificar que WSL 2 está instalado
2. Verificar que virtualización está habilitada en BIOS
3. Ejecutar como administrador: `wsl --update`
4. Reiniciar Docker Desktop

### Problema 8: Frontend no conecta con backend

**Checklist:**

```bash
# 1. Backend está corriendo
curl http://localhost/api/health

# 2. CORS configurado
cat config/cors.php

# 3. Variables de entorno correctas
cat .env.local

# 4. Axios configurado con withCredentials
# Verificar lib/axios.js
```

### Problema 9: Seeders dan error

```bash
# Ver error específico
./vendor/bin/sail artisan db:seed --verbose

# Limpiar y volver a intentar
./vendor/bin/sail artisan migrate:fresh
./vendor/bin/sail artisan db:seed

# Si falla seeder específico
./vendor/bin/sail artisan db:seed --class=UsersSeeder
```

### Problema 10: No puedo acceder a Mailpit

**Verificar:**

```bash
# Ver si contenedor corre
docker ps | grep mailpit

# Acceder directamente
http://localhost:8025

# Si usa otro puerto, verificar docker-compose.yml
```

---

## 📊 Verificación Final: ¿Todo funciona?

### Checklist de verificación

```bash
# ✅ Docker Desktop corriendo
# ✅ Backend: http://localhost → Laravel funcionando
# ✅ Frontend: http://localhost:3000 → Next.js funcionando
# ✅ Mailpit: http://localhost:8025 → Interface visible
# ✅ Base de datos tiene datos:
./vendor/bin/sail mysql -e "USE pesquerapp; SELECT COUNT(*) FROM users;"

# ✅ API responde:
curl http://localhost/api/users

# ✅ Frontend conecta con backend:
# Verificar en navegador en http://localhost:3000
```

### Test de integración completo

1. **Login en frontend** → Debe autenticar contra Laravel
2. **Crear orden** → Debe guardarse en MySQL
3. **Ver lista de productos** → Debe mostrar datos de seeders
4. **Enviar email** → Debe aparecer en Mailpit

---

## 🎉 Resultado Final

Con esta configuración tendrás:

✅ **Entorno aislado** - No contaminas tu sistema
✅ **Replicable** - Mismo entorno que producción
✅ **Portátil** - Funciona en cualquier máquina con Docker
✅ **Completo** - Backend + Frontend + BD + Redis + Mail
✅ **Datos de prueba** - Seeders con información realista
✅ **Profesional** - Flujo de trabajo estándar de la industria

---

## 📚 Recursos Adicionales

- **Laravel Sail Docs:** https://laravel.com/docs/sail
- **Docker Docs:** https://docs.docker.com/
- **Next.js Docs:** https://nextjs.org/docs
- **Laravel Docs:** https://laravel.com/docs

---

## 🆘 Soporte

Si encuentras algún problema no cubierto aquí:

1. Revisar logs: `./vendor/bin/sail logs -f`
2. Buscar en Laravel Sail GitHub Issues
3. Verificar Docker Desktop está corriendo
4. Reiniciar contenedores: `sail down && sail up -d`

---

**¡Tu entorno de desarrollo PesquerApp está listo! 🚀**
