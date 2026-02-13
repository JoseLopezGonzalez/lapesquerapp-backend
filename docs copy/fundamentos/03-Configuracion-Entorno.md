# Configuración del Entorno

## ⚠️ Estado de la API
- **v1**: Obsoleta (no documentada)
- **v2**: Versión activa (este documento)

---

## 📋 Visión General

Este documento describe la configuración del entorno de desarrollo y producción para el backend de PesquerApp. Incluye variables de entorno, archivos de configuración y estructura de conexiones.

---

## 🔧 Variables de Entorno

### Archivo `.env`

El archivo `.env` contiene todas las variables de configuración específicas del entorno. **Nunca** debe commitearse al repositorio (está en `.gitignore`).

### Variables Principales

#### Aplicación

```env
APP_NAME=PesquerApp
APP_ENV=local|staging|production
APP_KEY=base64:xxxxxxxxxxxxx
APP_DEBUG=true|false
APP_URL=https://api.pesquerapp.es
```

- **`APP_NAME`**: Nombre de la aplicación
- **`APP_ENV`**: Entorno (`local`, `staging`, `production`)
- **`APP_KEY`**: Clave de encriptación (generada con `php artisan key:generate`)
- **`APP_DEBUG`**: Modo debug (solo `true` en desarrollo)
- **`APP_URL`**: URL base de la API

#### Base de Datos

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pesquerapp_central
DB_USERNAME=usuario
DB_PASSWORD=contraseña
```

**Importante**: 
- `DB_DATABASE` se usa para la **base central** (tabla `tenants`)
- Las bases de tenant se configuran dinámicamente desde la tabla `tenants`

#### Logging

```env
LOG_CHANNEL=stack|single|daily|stderr
LOG_LEVEL=debug|info|notice|warning|error|critical|alert|emergency
```

- **`LOG_CHANNEL`**: Canal de logs (en producción usar `stderr` para Docker)
- **`LOG_LEVEL`**: Nivel mínimo de logs

#### Mail

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS=noreply@pesquerapp.es
MAIL_FROM_NAME="${APP_NAME}"
```

Configuración del servidor SMTP para envío de emails (documentos de pedidos, etc.).

---

## 🗄️ Configuración de Base de Datos

### Archivo: `config/database.php`

#### Conexión Central (mysql)

```php
'mysql' => [
    'driver' => 'mysql',
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => env('DB_PORT', '3306'),
    'database' => env('DB_DATABASE', 'forge'),
    'username' => env('DB_USERNAME', 'forge'),
    'password' => env('DB_PASSWORD', ''),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
],
```

**Uso**: Solo para la tabla `tenants` y otras tablas administrativas.

#### Conexión Tenant (dinámica)

```php
'tenant' => [
    'driver' => 'mysql',
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => env('DB_PORT', '3306'),
    'database' => '', // Se rellena dinámicamente
    'username' => env('DB_USERNAME', 'forge'),
    'password' => env('DB_PASSWORD', ''),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
],
```

**Uso**: Para todos los modelos de negocio. El campo `database` se completa en tiempo de ejecución por `TenantMiddleware`.

---

## 🔐 Configuración de Sanctum

### Archivo: `config/sanctum.php`

```php
'expiration' => 43200, // 30 días en minutos

'stateful' => [], // Vacío para APIs puras

'guard' => ['web'],
```

**Variables relevantes**:
- `expiration`: Tiempo de expiración de tokens (en minutos)
- `stateful`: Dominios para autenticación stateful (SPAs)
- `guard`: Guards de autenticación a verificar

---

## 📁 Estructura de Configuración

### Archivos de Configuración Principales

```
config/
├── app.php          # Configuración general de la aplicación
├── auth.php         # Configuración de autenticación
├── database.php     # Configuración de bases de datos
├── sanctum.php      # Configuración de Laravel Sanctum
├── mail.php         # Configuración de email
├── logging.php      # Configuración de logs
└── ...
```

### Caché de Configuración

En producción, siempre cachear la configuración:

```bash
php artisan config:cache
```

Para limpiar:

```bash
php artisan config:clear
```

---

## 🚀 Configuración por Entorno

### Desarrollo Local

**.env**:
```env
APP_ENV=local
APP_DEBUG=true
LOG_CHANNEL=stack
LOG_LEVEL=debug
```

**Características**:
- Debug habilitado
- Logs detallados
- Errores visibles en pantalla

### Staging

**.env**:
```env
APP_ENV=staging
APP_DEBUG=false
LOG_CHANNEL=daily
LOG_LEVEL=info
```

**Características**:
- Debug deshabilitado
- Logs en archivos diarios
- Configuración similar a producción

### Producción

**.env**:
```env
APP_ENV=production
APP_DEBUG=false
LOG_CHANNEL=stderr
LOG_LEVEL=error
```

**Características**:
- Debug deshabilitado
- Logs a stderr (para Docker)
- Solo errores y críticos

---

## 🐳 Configuración para Docker/Coolify

### Variables de Entorno en Coolify

Al desplegar en Coolify, configurar estas variables:

```env
APP_NAME=PesquerApp
APP_ENV=production
APP_KEY=base64:... (generar antes)
APP_DEBUG=false
APP_URL=https://api.pesquerapp.es

LOG_CHANNEL=stderr
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=nombre-servicio-db
DB_PORT=3306
DB_DATABASE=pesquerapp_central
DB_USERNAME=usuario
DB_PASSWORD=contraseña

MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=usuario
MAIL_PASSWORD=contraseña
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@pesquerapp.es
```

### Volúmenes Persistentes

En Coolify, montar estos volúmenes:

| Ruta del Contenedor | Nombre del Volumen |
|---------------------|-------------------|
| `/app/storage` | `laravel-storage` |
| `/app/bootstrap/cache` | `laravel-bootstrap-cache` |

---

## 🔑 Generación de APP_KEY

La clave de la aplicación es crítica para encriptación. **Nunca** debe compartirse.

### Generar Nueva Clave

```bash
php artisan key:generate
```

Esto actualiza `APP_KEY` en `.env`.

### Verificar Clave

```bash
php artisan tinker
>>> config('app.key')
```

---

## 📝 Comandos Post-Deploy

Después de cada despliegue, ejecutar:

```bash
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear
php artisan migrate --force
php artisan storage:link
```

**Nota**: `--force` es necesario en producción para evitar confirmación interactiva.

---

## 🔍 Verificación de Configuración

### Verificar Variables de Entorno

```bash
php artisan tinker
>>> config('app.env')
>>> config('database.connections.tenant.host')
>>> config('sanctum.expiration')
```

### Verificar Conexión a Base de Datos

```bash
php artisan tinker
>>> DB::connection('mysql')->getPdo(); // Base central
>>> DB::connection('tenant')->getPdo(); // Base tenant (requiere tenant configurado)
```

### Verificar Configuración de Sanctum

```bash
php artisan tinker
>>> config('sanctum.expiration')
```

---

## ⚠️ Consideraciones de Seguridad

### Variables Sensibles

**NUNCA commitear**:
- `.env`
- `APP_KEY`
- `DB_PASSWORD`
- `MAIL_PASSWORD`
- Cualquier credencial

### Rotación de Credenciales

- Rotar `APP_KEY` periódicamente (requiere re-encriptar datos)
- Cambiar contraseñas de base de datos regularmente
- Usar diferentes credenciales por entorno

### Permisos de Archivos

```bash
chmod 600 .env  # Solo lectura/escritura para propietario
```

---

## 🛠️ Configuración de Desarrollo

### Local Setup

1. **Copiar `.env.example` a `.env`**:
   ```bash
   cp .env.example .env
   ```

2. **Generar APP_KEY**:
   ```bash
   php artisan key:generate
   ```

3. **Configurar base de datos**:
   Editar `.env` con credenciales locales

4. **Ejecutar migraciones**:
   ```bash
   php artisan migrate
   ```

5. **Ejecutar migraciones de tenants**:
   ```bash
   php artisan tenants:migrate
   ```

### Servidor de Desarrollo

```bash
php artisan serve
```

Acceso: `http://127.0.0.1:8000`

---

## 📊 Configuración de Logs

### Canales Disponibles

- **`stack`**: Múltiples canales combinados
- **`single`**: Un solo archivo
- **`daily`**: Archivos diarios (recomendado para producción)
- **`stderr`**: Salida estándar de errores (Docker)

### Ubicación de Logs

```
storage/logs/
├── laravel.log
├── laravel-2024-01-15.log
└── ...
```

### Niveles de Log

En orden de severidad:
1. `debug`: Información detallada
2. `info`: Eventos informativos
3. `notice`: Eventos normales pero importantes
4. `warning`: Advertencias
5. `error`: Errores
6. `critical`: Errores críticos
7. `alert`: Acción inmediata requerida
8. `emergency`: Sistema inutilizable

---

## 🔄 Caché y Optimización

### Limpiar Todo el Caché

```bash
php artisan optimize:clear
```

Equivale a:
```bash
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear
```

### Cachear para Producción

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

**⚠️ Importante**: Después de cambiar `.env`, siempre ejecutar `php artisan config:clear`.

---

## Observaciones Críticas y Mejoras Recomendadas

### ⚠️ Falta de Validación de Variables de Entorno

1. **No Hay Validación al Iniciar** (`config/`)
   - No valida que todas las variables requeridas estén presentes
   - **Problema**: Puede fallar en runtime con errores confusos
   - **Recomendación**: 
     - Crear comando `php artisan config:validate`
     - O validar en `AppServiceProvider::boot()`

### ⚠️ APP_KEY Sin Verificación

2. **No Verifica Si APP_KEY Está Configurado** (`config/app.php`)
   - Si falta `APP_KEY`, puede causar errores de encriptación
   - **Recomendación**: Validar en bootstrap o mostrar error claro si falta

### ⚠️ Credenciales de BD Hardcodeadas en Código

3. **Valores por Defecto en Config** (`config/database.php`)
   - Valores como `'forge'` están hardcodeados
   - **Líneas**: 40, 43-44, 69-71
   - **Problema**: Pueden confundir si no se lee `.env`
   - **Recomendación**: Usar valores más obvios o null

### ⚠️ Falta de Documentación de Variables Opcionales

4. **Variables No Documentadas** (`.env.example`)
   - Algunas variables pueden existir pero no están documentadas
   - **Recomendación**: Mantener `.env.example` actualizado con todas las variables

### ⚠️ Configuración de Mail Puede Fallar Silenciosamente

5. **Mail Sin Validación** (`config/mail.php`)
   - Si la configuración de mail es incorrecta, puede fallar silenciosamente
   - **Recomendación**: Validar conexión SMTP al iniciar o con comando de prueba

---

**Última actualización**: Documentación generada desde código fuente en fecha de generación.

