# CORS en producción — Diagnóstico y solución

## Causa raíz identificada

El error `No 'Access-Control-Allow-Origin' header is present` ocurre porque **Laravel solo añade CORS cuando recibe el header `Origin` en la petición**. Si el proxy (Coolify/Traefik/Nginx) no lo reenvía, Laravel no añade el header.

```
Browser (envía Origin) → Proxy → ¿Reenvía Origin? → Laravel
                                    │
                                    ├─ SÍ → HandleCors añade Access-Control-Allow-Origin ✓
                                    └─ NO → HandleCors NO añade el header ✗
```

## Cambios implementados

### 1. `config/cors.php` — Uso de CORS_ALLOWED_ORIGINS

- El `.env` de producción ya tiene `CORS_ALLOWED_ORIGINS` correcto.
- La config ahora **usa** esa variable además de los orígenes de desarrollo.
- Los patrones regex cubren `*.lapesquerapp.es`, `*.congeladosbrisamar.es`, `app.lapesquerapp.es`.

### 2. Dockerfile — Apache CORS como respaldo

- Se añade `docker/apache-cors.conf` en Apache.
- Si Laravel no devuelve CORS (por config cacheada o similar), Apache puede devolver los headers.
- Apache recibe la petición **después** del proxy; el proxy debe reenviar `Origin` al contenedor.

### 3. Scribe — Early return en producción

- `config/scribe.php` hace early return cuando Scribe no está instalado.
- Evita que `config:cache` falle en producción con `composer install --no-dev`.

---

## Pasos de validación en producción

### 1. Reconstruir imagen sin caché

```bash
docker build --no-cache -t pesquerapp-api .
```

### 2. Limpiar y regenerar config cache

```bash
php artisan config:clear
php artisan config:cache
```

Si `config:cache` falla, `config:clear` y no usar caché en producción hasta resolver la causa.

### 3. Comprobar que el proxy reenvía Origin

Desde el servidor (o con acceso al contenedor):

```bash
# OPTIONS preflight simulando el navegador
curl -X OPTIONS 'https://api.lapesquerapp.es/api/v2/auth/request-access' \
  -H 'Origin: https://brisamar.lapesquerapp.es' \
  -H 'Access-Control-Request-Method: POST' \
  -H 'Access-Control-Request-Headers: X-Tenant, Content-Type, Accept' \
  -v
```

Debe verse en la respuesta:
- `Access-Control-Allow-Origin: https://brisamar.lapesquerapp.es`
- `Access-Control-Allow-Headers` con `X-Tenant` incluido

### 4. Si sigue fallando: verificar proxy (Coolify/Traefik)

Coolify suele usar Traefik. Por defecto reenvía los headers, pero conviene revisar:

- Headers permitidos/pasados al backend.
- Manejo de peticiones OPTIONS (OPTIONS debe llegar a Laravel, no ser respondido solo por el proxy).

### 5. Prueba directa al contenedor

Para comprobar que Laravel devuelve CORS correctamente:

```bash
# Dentro del contenedor o red interna
curl -X OPTIONS 'http://127.0.0.1/api/v2/auth/request-access' \
  -H 'Origin: https://brisamar.lapesquerapp.es' \
  -H 'Access-Control-Request-Method: POST' \
  -H 'Access-Control-Request-Headers: X-Tenant, Content-Type, Accept' \
  -H 'Host: api.lapesquerapp.es' \
  -v
```

Si aquí aparecen los headers CORS correctos pero no en la URL pública, el problema está en el proxy.

---

## Variables .env relevantes

| Variable | Ejemplo | Uso |
|----------|---------|-----|
| `CORS_ALLOWED_ORIGINS` | `https://brisamar.lapesquerapp.es,https://pymcolorao.lapesquerapp.es,...` | Orígenes permitidos (comas, sin espacios) |
| `SANCTUM_STATEFUL_DOMAINS` | `brisamar.lapesquerapp.es,pymcolorao.lapesquerapp.es,...` | Dominios con cookies de sesión |

---

## Service Worker (PWA)

Si usas PWA con Service Worker:

1. Prueba en ventana de incógnito (sin SW cacheado).
2. Desregistra el SW temporalmente para descartar caché del preflight.
3. Actualiza el SW para evitar cachear respuestas OPTIONS.

---

## Resumen de comprobaciones

- [ ] Imagen reconstruida con `--no-cache`
- [ ] `config:clear` y `config:cache` ejecutados
- [ ] `curl` con `Origin` devuelve `Access-Control-Allow-Origin`
- [ ] Proxy reenvía `Origin` al backend
- [ ] Headers `Access-Control-Allow-Headers` incluyen `X-Tenant`
