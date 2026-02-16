# Referencia completa de variables .env — PesquerApp Backend

**Fecha**: 2026-02-15  
**Propósito**: Documento maestro de variables de entorno para local, Sail y producción.

---

## 1. Comparación Local vs Producción

| Variable | Local (actual) | Producción (actual) | Recomendación |
|----------|----------------|---------------------|---------------|
| APP_NAME | PesquerApp | La pesquerapp | Producción: coherente con marca |
| APP_ENV | local | local | **Producción: `production`** |
| APP_DEBUG | true | true | **Producción: `false`** (crítico) |
| APP_URL | http://localhost:8000 | http://api.lapesquerapp.es | **Producción: `https://api.lapesquerapp.es`** |
| FRONTEND_URL | http://localhost:3000 | https://{subdomain}.lapesquerapp.es | Correcto por entorno |
| DB_HOST | mysql | 94.143.137.84 | Correcto (Sail vs remoto) |
| DB_DATABASE | pesquerapp | default | Según configuración |
| SESSION_DRIVER | redis | cookie | Sail: redis; producción: cookie OK |
| CACHE_DRIVER | redis | file | Producción: file OK (o redis si hay) |
| CORS_ALLOWED_ORIGINS | (no en .env Sail) | Lista explícita | Local: añadir si frontend en otro puerto |
| SANCTUM_STATEFUL_DOMAINS | localhost:3000,127.0.0.1:3000 | Lista explícita | Correcto |
| SESSION_DOMAIN | (no definido) | .lapesquerapp.es | Producción: necesario para cookies cross-subdomain |
| SESSION_SECURE_COOKIE | (no definido) | true | Producción: true con HTTPS |
| SESSION_SAME_SITE | (no definido) | lax | **Nota**: config/session.php tiene `same_site => 'none'` hardcoded; SESSION_SAME_SITE no se lee aún |

---

## 2. Variables que faltan en .env.example

Las siguientes variables se usan en el proyecto pero no están en .env.example:

| Variable | Usada en | Requerida |
|----------|----------|-----------|
| CORS_ALLOWED_ORIGINS | config/cors.php | Sí (CORS) |
| SANCTUM_STATEFUL_DOMAINS | config/sanctum.php | Sí (SPA auth) |
| SESSION_DOMAIN | config/session.php | Producción |
| SESSION_SECURE_COOKIE | config/session.php | Producción con HTTPS |
| CHROMIUM_PATH | config/pdf.php | Producción (PDFs) |
| DELIVERY_NOTE_LOGO_PATH | vistas PDF | Sí |
| AZURE_DOCUMENT_AI_ENDPOINT | Extracción documentos | Opcional |
| AZURE_DOCUMENT_AI_KEY | Extracción documentos | Opcional |
| APP_TIMEZONE | config/app.php | Opcional (default Europe/Madrid) |
| MAGIC_LINK_EXPIRES_MINUTES | config/magic_link.php | Opcional (default 10) |
| MAGIC_LINK_CLEANUP_USED_DAYS | config/magic_link.php | Opcional |

---

## 3. Plantilla LOCAL (Sin Sail)

```env
# =============================================================================
# PesquerApp — Desarrollo local (php artisan serve)
# =============================================================================

APP_NAME=PesquerApp
APP_ENV=local
APP_KEY=base64:XXXXXXXX
APP_DEBUG=true
APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:3000

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

# Base de datos local
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pesquerapp
DB_USERNAME=root
DB_PASSWORD=tu_password

# Sin Redis en local simple
CACHE_DRIVER=file
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120

BROADCAST_DRIVER=log
FILESYSTEM_DISK=local
MEMCACHED_HOST=127.0.0.1
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Mail (Mailpit o log)
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="noreply@pesquerapp.local"
MAIL_FROM_NAME="${APP_NAME}"

# CORS y Sanctum — local
CORS_ALLOWED_ORIGINS=http://localhost:3000,http://localhost:3001,http://127.0.0.1:3000,http://localhost:5173
SANCTUM_STATEFUL_DOMAINS=localhost:3000,localhost:3001,127.0.0.1:3000,127.0.0.1:3001

# Opcionales
# SESSION_DOMAIN=  (no necesario en local)
# CHROMIUM_PATH=/usr/bin/google-chrome
# DELIVERY_NOTE_LOGO_PATH=images/logos/logo-b-color.svg

# AWS, Pusher, Vite (vacíos)
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false
PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
PUSHER_HOST=
PUSHER_PORT=443
PUSHER_SCHEME=https
PUSHER_APP_CLUSTER=mt1
VITE_APP_NAME="${APP_NAME}"
VITE_PUSHER_APP_KEY="${PUSHER_APP_KEY}"
VITE_PUSHER_HOST="${PUSHER_HOST}"
VITE_PUSHER_PORT="${PUSHER_PORT}"
VITE_PUSHER_SCHEME="${PUSHER_SCHEME}"
VITE_PUSHER_APP_CLUSTER="${PUSHER_APP_CLUSTER}"
```

---

## 4. Plantilla LOCAL con Sail

```env
# =============================================================================
# PesquerApp — Desarrollo con Docker Sail
# Uso: cp .env.sail.example .env
# =============================================================================

APP_NAME=PesquerApp
APP_ENV=local
APP_KEY=base64:XXXXXXXX
APP_DEBUG=true
APP_URL=http://localhost
APP_PORT=80
FRONTEND_URL=http://localhost:3000

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

# Base de datos — contenedor Sail
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=pesquerapp
DB_USERNAME=sail
DB_PASSWORD=password

# Redis — contenedor Sail
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379

# Cache, cola, sesión con Redis
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120

BROADCAST_DRIVER=log
FILESYSTEM_DISK=local
MEMCACHED_HOST=127.0.0.1

# Mail — Mailpit
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="noreply@pesquerapp.local"
MAIL_FROM_NAME="${APP_NAME}"

# CORS y Sanctum — local
CORS_ALLOWED_ORIGINS=http://localhost:3000,http://localhost:3001,http://127.0.0.1:3000,http://localhost:5173
SANCTUM_STATEFUL_DOMAINS=localhost:3000,127.0.0.1:3000

# AWS, Pusher, Vite (vacíos)
# ... (igual que plantilla anterior)
```

---

## 5. Plantilla PRODUCCIÓN (actualizada)

```env
# =============================================================================
# PesquerApp — Producción
# =============================================================================

# --- Aplicación ---
APP_NAME=La pesquerapp
APP_ENV=production
APP_KEY=base64:XXXXXXXX
APP_DEBUG=false
APP_URL=https://api.lapesquerapp.es
FRONTEND_URL=https://{subdomain}.lapesquerapp.es

# --- Logs ---
LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=error

# --- Base de datos ---
DB_CONNECTION=mysql
DB_HOST=94.143.137.84
DB_PORT=3308
DB_DATABASE=default
DB_USERNAME=root
DB_PASSWORD=tu_password_seguro

# --- Cache y cola ---
CACHE_DRIVER=file
QUEUE_CONNECTION=sync
BROADCAST_DRIVER=log
FILESYSTEM_DISK=local

# --- Redis (si se usa) ---
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
MEMCACHED_HOST=127.0.0.1

# --- CORS (crítico para SPA) ---
# Lista blanca explícita. Añadir nuevos tenants al desplegarlos.
CORS_ALLOWED_ORIGINS=https://brisamar.lapesquerapp.es,https://pymcolorao.lapesquerapp.es,https://app.lapesquerapp.es,https://nextjs.congeladosbrisamar.es

# --- Sanctum (SPA stateful) ---
# Sin protocolo. Mismos dominios que CORS + raíz si aplica.
SANCTUM_STATEFUL_DOMAINS=brisamar.lapesquerapp.es,pymcolorao.lapesquerapp.es,app.lapesquerapp.es,nextjs.congeladosbrisamar.es,lapesquerapp.es,congeladosbrisamar.es

# --- Sesión y cookies ---
SESSION_DRIVER=cookie
SESSION_LIFETIME=120
SESSION_DOMAIN=.lapesquerapp.es
SESSION_SECURE_COOKIE=true
# Nota: config/session.php tiene same_site hardcoded; para usar SESSION_SAME_SITE habría que añadirlo al config

# --- Mail ---
MAIL_MAILER=smtp
MAIL_HOST=smtp.ionos.es
MAIL_PORT=465
MAIL_USERNAME=app@congeladosbrisamar.es
MAIL_PASSWORD=tu_password
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS=app@congeladosbrisamar.es
MAIL_FROM_NAME="Congelados Brisamar App"

# --- PDFs y utilidades ---
CHROMIUM_PATH=/usr/bin/google-chrome
DELIVERY_NOTE_LOGO_PATH=images/logos/logo-b-color.svg

# --- Azure Document AI (opcional) ---
AZURE_DOCUMENT_AI_ENDPOINT=https://xxx.cognitiveservices.azure.com/
AZURE_DOCUMENT_AI_KEY=tu_key

# --- Límites PHP (Docker/php.ini) ---
UPLOAD_MAX_FILESIZE=128M
POST_MAX_SIZE=128M

# --- AWS, Pusher, Vite (vacíos si no se usan) ---
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false
PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
PUSHER_HOST=
PUSHER_PORT=443
PUSHER_SCHEME=https
PUSHER_APP_CLUSTER=mt1
VITE_APP_NAME="${APP_NAME}"
VITE_PUSHER_APP_KEY="${PUSHER_APP_KEY}"
VITE_PUSHER_HOST="${PUSHER_HOST}"
VITE_PUSHER_PORT="${PUSHER_PORT}"
VITE_PUSHER_SCHEME="${PUSHER_SCHEME}"
VITE_PUSHER_APP_CLUSTER="${PUSHER_APP_CLUSTER}"
```

---

## 6. Correcciones prioritarias para producción actual

| Cambio | Valor actual | Valor correcto |
|--------|--------------|----------------|
| APP_ENV | local | production |
| APP_DEBUG | true | false |
| APP_URL | http://api.lapesquerapp.es | https://api.lapesquerapp.es |
| LOG_LEVEL | debug | error o warning |
| MAIL_ENCRYPTION | tls (con puerto 465) | ssl |

**Nota MAIL**: Puerto 465 usa SSL implícito; `MAIL_ENCRYPTION=ssl`. Puerto 587 usa STARTTLS; `MAIL_ENCRYPTION=tls`. IONOS con 465 → ssl.

---

## 7. Variables no usadas por Laravel (PHP/Docker)

`UPLOAD_MAX_FILESIZE` y `POST_MAX_SIZE` son directivas de `php.ini`. Si las tienes en .env, deben aplicarse vía Dockerfile o php.ini personalizado; Laravel no las lee por defecto.

---

## 8. SESSION_SAME_SITE

Actualmente `config/session.php` tiene `'same_site' => 'none'` hardcoded. Para que `SESSION_SAME_SITE` del .env tenga efecto, habría que cambiar a:

```php
'same_site' => env('SESSION_SAME_SITE', 'lax'),
```

Para SPA con cookies en subdominios del mismo dominio (`*.lapesquerapp.es`), `lax` suele ser suficiente. `none` se usa cuando frontend y API están en dominios completamente distintos y se requieren cookies cross-site.

---

**Última actualización**: 2026-02-15
