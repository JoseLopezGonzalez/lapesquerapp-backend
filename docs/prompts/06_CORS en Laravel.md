# PROMPT: Análisis y Resolución Profesional de CORS en Laravel con Apache/Docker

## CONTEXTO DEL PROYECTO

Estás trabajando en **PesquerApp**, un sistema ERP multi-tenant para la industria pesquera con:

- **Backend**: Laravel 10 con arquitectura multi-tenant (database-per-tenant)
- **Frontend**: Next.js 16
- **Infraestructura**: Apache + Docker desplegado en Coolify
- **Autenticación**: Laravel Sanctum

## SITUACIÓN ACTUAL

Los problemas de CORS aparecieron recientemente después de auditar e implementar mejoras profesionales en el backend. Anteriormente funcionaba, posiblemente debido a configuraciones parcheadas o inconsistentes que se han modificado durante la refactorización.

## TU MISIÓN

### FASE 1: ANÁLISIS EXHAUSTIVO DEL ESTADO ACTUAL

#### 1.1 Configuración de CORS en Laravel

Analiza en profundidad:

**Archivo `config/cors.php`:**

```php
// Revisa configuración actual y compárala con mejores prácticas
- paths permitidos
- allowed_origins (wildcard vs específicos)
- allowed_methods
- allowed_headers
- exposed_headers
- credentials (true/false)
- max_age
```

**Middleware CORS:**

- Verifica orden de middleware en `app/Http/Kernel.php`
- Posición de `\Fruitcake\Cors\HandleCors::class` (debe estar al inicio del grupo 'api')
- Middleware personalizados que puedan interferir

**Configuración de Sanctum:**

```php
// config/sanctum.php
- stateful domains configurados correctamente
- paths de endpoints
- middleware groups
```

**Variables de entorno:**

```env
SESSION_DRIVER
SESSION_DOMAIN
SANCTUM_STATEFUL_DOMAINS
FRONTEND_URL
APP_URL
```

#### 1.2 Configuración de Apache en Docker

**Busca y analiza:**

- `Dockerfile` del proyecto Laravel
- `docker-compose.yml` si existe
- Configuración de Apache dentro del contenedor
- Virtual hosts configurados
- `.htaccess` en `/public`
- Headers modules habilitados (`a2enmod headers`)

**Específicamente verifica:**

```apache
# ¿Está habilitado mod_headers?
# ¿Hay configuración de CORS duplicada en Apache?
# ¿Headers de seguridad que puedan interferir?
Header set Access-Control-Allow-Origin "*"
Header set Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS"
# etc.
```

#### 1.3 Configuración de Coolify

Analiza:

- Variables de entorno definidas en Coolify
- Configuración de proxy/reverse proxy
- SSL/TLS certificates y redirects
- Custom nginx/apache configs inyectados por Coolify

#### 1.4 Requests de Preflight OPTIONS

Verifica en las rutas de `routes/api.php`:

- ¿Hay manejo explícito de OPTIONS requests?
- ¿Middleware aplicado a rutas OPTIONS?
- ¿Routes con preflight que no respondan 200 OK?

### FASE 2: COMPARACIÓN CON ESTADO ANTERIOR

**Identifica cambios recientes:**

- Commits git relacionados con:
  - Configuración de middleware
  - Cambios en Kernel.php
  - Modificaciones en config/cors.php
  - Actualizaciones de .env
  - Cambios en Dockerfile o docker-compose

**Busca "parches" anteriores:**

- Código comentado relacionado con CORS
- Middleware custom que manejaba CORS manualmente
- Headers seteados directamente en controladores
- Configuraciones duplicadas (Laravel + Apache)

### FASE 3: DIAGNÓSTICO DE CONFLICTOS

Identifica posibles **conflictos en capas**:

1. **Docker → Apache → Laravel**: ¿Headers duplicados en diferentes capas?
2. **Middleware stack**: ¿Orden incorrecto causando que CORS no se aplique?
3. **Multi-tenant**: ¿Dominios dinámicos no incluidos en stateful domains?
4. **Sanctum + CORS**: ¿Configuración inconsistente para cookies/credentials?

### FASE 4: SOLUCIÓN PROFESIONAL Y ROBUSTA

Proporciona una solución que:

#### 4.1 Configuración Unificada

- **CORS manejado SOLO en Laravel** (eliminar de Apache si existe)
- Configuración explícita por entorno (local, staging, production)
- Lista blanca de dominios específicos (nada de wildcards en producción)

#### 4.2 Plantilla de Configuración

**`config/cors.php` profesional:**

```php
return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', '')),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
```

**Variables de entorno sugeridas:**

```env
# Producción
CORS_ALLOWED_ORIGINS=https://app.pesquerapp.com,https://www.pesquerapp.com
SANCTUM_STATEFUL_DOMAINS=app.pesquerapp.com,www.pesquerapp.com
SESSION_DOMAIN=.pesquerapp.com
SESSION_DRIVER=cookie

# Local
CORS_ALLOWED_ORIGINS=http://localhost:3000,http://localhost:3001
SANCTUM_STATEFUL_DOMAINS=localhost:3000,localhost:3001
```

#### 4.3 Dockerfile Optimizado

Asegura que el Dockerfile:

```dockerfile
# Habilita mod_headers
RUN a2enmod headers rewrite

# NO incluir configuración CORS en Apache
# Dejar que Laravel lo maneje completamente
```

#### 4.4 Testing y Validación

Proporciona comandos para validar:

```bash
# Test CORS desde curl
curl -H "Origin: https://app.pesquerapp.com" \
     -H "Access-Control-Request-Method: POST" \
     -H "Access-Control-Request-Headers: Content-Type" \
     -X OPTIONS \
     --verbose \
     https://api.pesquerapp.com/api/endpoint

# Verificar headers en response
# Debe incluir:
# Access-Control-Allow-Origin
# Access-Control-Allow-Credentials
# Access-Control-Allow-Methods
```

### FASE 5: DOCUMENTACIÓN DE LA SOLUCIÓN

Genera:

1. **Checklist de verificación** para futuros cambios
2. **Diagrama de flujo** de request → CORS → Sanctum → Response
3. **Guía de troubleshooting** para errores comunes
4. **Configuración recomendada** por entorno (dev/staging/prod)

## OUTPUT ESPERADO

Estructura tu respuesta en:

### 1️⃣ DIAGNÓSTICO

- Lista de archivos analizados
- Problemas encontrados (específicos, con ubicación)
- Conflictos identificados entre capas

### 2️⃣ CAUSA RAÍZ

- Explicación clara de por qué funcionaba antes
- Qué cambio específico rompió CORS
- Por qué la configuración actual es inconsistente

### 3️⃣ SOLUCIÓN IMPLEMENTABLE

- Cambios exactos a realizar (archivo por archivo)
- Comandos a ejecutar
- Variables de entorno a actualizar en Coolify
- Orden de aplicación de cambios

### 4️⃣ VALIDACIÓN

- Checklist de pruebas
- Comandos curl de validación
- Expected vs actual responses

### 5️⃣ PREVENCIÓN

- Best practices para mantener CORS funcionando
- Qué NO hacer en futuras refactorizaciones
- Monitoring/alerts sugeridos

## CRITERIOS DE ÉXITO

✅ CORS funciona para todos los dominios permitidos
✅ Preflight OPTIONS requests responden 200 OK
✅ Cookies de Sanctum se envían/reciben correctamente
✅ Configuración consistente entre entornos
✅ Sin headers duplicados entre Apache y Laravel
✅ Documentado y mantenible a largo plazo

---

**IMPORTANTE**:

- Examina TODOS los archivos relevantes antes de concluir
- No asumas configuraciones, verifica el código real
- Proporciona soluciones copy-paste ready
- Explica el "por qué" de cada cambio sugerido
