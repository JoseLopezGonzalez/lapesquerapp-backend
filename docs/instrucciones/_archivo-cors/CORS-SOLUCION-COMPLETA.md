# Solución CORS completa — "No 'Access-Control-Allow-Origin' header"

## Diagnóstico

Cuando el navegador muestra **"No 'Access-Control-Allow-Origin' header"** y a la vez **"200 (OK)"**, la petición llega al servidor y la respuesta es correcta, pero **la respuesta que recibe el cliente no incluye la cabecera CORS**. Causas habituales:

| Causa | Dónde | Solución |
|-------|--------|----------|
| **Origin no llega a Laravel** | Proxy (Nginx, etc.) no reenvía `Origin` | Reenviar con `proxy_set_header Origin $http_origin;` o añadir CORS en Nginx |
| **Respuestas de error sin CORS** | Exception Handler no pasa por HandleCors | ✅ Aplicado: `Handler::ensureCorsOnApiResponse()` |
| **Respuestas 200 sin CORS** | HandleCors no aplica o proxy quita cabeceras | ✅ Aplicado: CORS en controlador + middleware + Nginx |
| **Service Worker** | SW devuelve respuesta cacheada sin cabeceras | No cachear `/api/v2/public/tenant/*` en el SW |

## Cambios aplicados en el backend (Laravel)

### 1. Exception Handler (`app/Exceptions/Handler.php`)
- Todas las respuestas JSON de error (4xx/5xx) para rutas `api/*` pasan por `ensureCorsOnApiResponse()`.
- Así las respuestas generadas en el Handler llevan CORS aunque no pasen por el middleware.

### 2. Middleware `EnsureCorsOnApiResponse`
- Añadido al grupo `api` en `Kernel.php`.
- Para cada respuesta de `api/*` que no tenga ya `Access-Control-Allow-Origin`, si el `Origin` de la petición está permitido, añade las cabeceras CORS.

### 3. Clase `CorsResponse` y controlador público
- **`App\Http\Support\CorsResponse`**: utilidad para añadir CORS a una respuesta y para generar la respuesta preflight (OPTIONS).
- **`TenantController`**:
  - Todas las respuestas (200 y 404) pasan por `CorsResponse::addToResponse($request, $response)`.
  - Ruta **OPTIONS** `api/v2/public/tenant/{subdomain}` que devuelve 204 con cabeceras CORS (preflight).

### 4. Configuración CORS
- `config/cors.php`: `https://brisamar.lapesquerapp.es` añadido explícitamente en `allowed_origins`.
- Patrones `allowed_origins_patterns` para `*.lapesquerapp.es`.

## Acción obligatoria en producción: Nginx

Aunque Laravel ya añade CORS en varios puntos, **si el proxy no reenvía `Origin` a Laravel**, la aplicación no puede saber qué origen permitir. La solución más fiable es **añadir CORS en Nginx**, que sí recibe `Origin` del cliente.

1. **Reenviar Origin a Laravel** (recomendado):
   ```nginx
   location /api/ {
       proxy_pass http://backend_laravel;
       proxy_set_header Origin $http_origin;
       # ... resto de proxy_set_header
   }
   ```

2. **Añadir cabeceras CORS en Nginx** (recomendado si el problema persiste):
   - Ver **`docs/instrucciones/nginx-cors-api.conf`** para el `map` de orígenes permitidos y los bloques `add_header ... always` dentro del `location /api/`.
   - El parámetro **`always`** es importante: hace que las cabeceras se envíen también en respuestas 4xx/5xx.

## Comprobación rápida

Desde un equipo con acceso a la red del servidor:

```bash
curl -s -I -H "Origin: https://brisamar.lapesquerapp.es" \
  https://api.lapesquerapp.es/api/v2/public/tenant/brisamar
```

La respuesta debe incluir:

```
Access-Control-Allow-Origin: https://brisamar.lapesquerapp.es
```

Si no aparece, el fallo está en el proxy o en que Nginx no está configurado con el snippet CORS.

## Service Worker (frontend)

Si usas PWA o un Service Worker que intercepta `fetch`:

- **No cachear** las peticiones a `https://api.lapesquerapp.es/api/v2/public/tenant/*`.
- Usar estrategia "network first" o excluir esa URL del cache para que el navegador reciba siempre la respuesta real del servidor (con CORS).

## Resumen de archivos tocados

| Archivo | Cambio |
|---------|--------|
| `app/Exceptions/Handler.php` | `ensureCorsOnApiResponse()` en todas las respuestas JSON de la API |
| `app/Http/Middleware/EnsureCorsOnApiResponse.php` | Nuevo middleware |
| `app/Http/Kernel.php` | Middleware en el grupo `api` |
| `app/Http/Support/CorsResponse.php` | Nueva clase de utilidad CORS |
| `app/Http/Controllers/Public/TenantController.php` | CORS en respuestas + método OPTIONS |
| `routes/api.php` | Ruta OPTIONS para tenant |
| `config/cors.php` | Origen `brisamar.lapesquerapp.es` explícito |
| `docs/instrucciones/nginx-cors-api.conf` | Snippet Nginx para CORS en API |
| `docs/instrucciones/CORS-proxy-Origin.md` | Diagnóstico y proxy + SW |
| `docs/instrucciones/CORS-SOLUCION-COMPLETA.md` | Este resumen |
