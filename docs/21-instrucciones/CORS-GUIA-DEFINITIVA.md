# CORS — Guía definitiva (PesquerApp Backend)

**Última actualización:** 2026-02-16 | **Estado:** Consolidación de 9 documentos. Originales en `_archivo-cors/`.

---

## 1. Diagnóstico

**Síntoma:** Servidor responde 200 OK pero el navegador muestra "No 'Access-Control-Allow-Origin' header". **Causa habitual:** Laravel solo añade CORS cuando recibe el header `Origin`. Si el proxy (Coolify/Traefik/Nginx/Apache) no reenvía `Origin`, Laravel no puede añadir la cabecera. Otras causas: Service Worker cachea respuesta sin CORS; proxy quita cabeceras.

**Comprobación rápida:**
```bash
curl -s -D - -o /dev/null -H "Origin: https://pymcolorao.lapesquerapp.es" \
  https://api.lapesquerapp.es/api/v2/public/tenant/pymcolorao
```
Si no aparece `Access-Control-Allow-Origin`, el proxy no reenvía `Origin` o quita CORS. Si sí aparece, el problema suele ser SW o caché (probar en incógnito).

**Service Worker (PWA):** No cachear en el SW las peticiones a `https://api.lapesquerapp.es/api/v2/public/tenant/*` (network first o excluir del cache).

---

## 2. Solución en Laravel

- **Exception Handler:** `ensureCorsOnApiResponse()` para respuestas JSON de error en `api/*`.
- **Middleware** `EnsureCorsOnApiResponse`: refuerza CORS en respuestas de `api/*`.
- **TenantController / CorsResponse:** CORS en endpoint público y ruta OPTIONS para preflight.
- **config/cors.php:** `allowed_origins`, `allowed_origins_patterns` (ej. `*.lapesquerapp.es`), **`allowed_headers` con X-Tenant**.
- Variables: `CORS_ALLOWED_ORIGINS`, `SANCTUM_STATEFUL_DOMAINS` en `.env`.

Si el proxy no reenvía `Origin`, la solución fiable es configurar CORS en el proxy (secciones 3–4).

---

## 3. Solución en Apache

Activar: `sudo a2enmod headers setenvif`. En el VirtualHost de la API, bloque `<Location "/api">`:

```apache
SetEnvIf Origin "^https://[a-z0-9-]+\\.lapesquerapp\\.es$" ORIGIN_ALLOWED=$0
SetEnvIf Origin "^https://lapesquerapp\\.es$" ORIGIN_ALLOWED=$0
SetEnvIf Origin "^http://localhost(:[0-9]+)?$" ORIGIN_ALLOWED=$0
SetEnvIf Origin "^http://127\\.0\\.0\\.1(:[0-9]+)?$" ORIGIN_ALLOWED=$0
Header always set Access-Control-Allow-Origin "%{ORIGIN_ALLOWED}e" env=ORIGIN_ALLOWED
Header always set Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS, PATCH" env=ORIGIN_ALLOWED
Header always set Access-Control-Allow-Headers "Authorization, Accept, Origin, Content-Type, X-Tenant" env=ORIGIN_ALLOWED
Header always set Access-Control-Allow-Credentials "true" env=ORIGIN_ALLOWED
Header always set Vary "Origin" env=ORIGIN_ALLOWED
```
Incluir **X-Tenant** en Allow-Headers. Luego: `apachectl configtest` y `systemctl reload apache2`. Ver también `apache-cors-api.conf` en esta carpeta.

---

## 4. Solución en Coolify/Traefik

Coolify usa Traefik; los headers CORS de la app pueden no llegar al cliente ([issue #2570](https://github.com/coollabsio/coolify/issues/2570)). Configurar CORS en Traefik.

En Coolify: **Proxy → Dynamic Configurations**. Crear `cors-api.yaml`:
```yaml
http:
  middlewares:
    pesquerapp-cors:
      headers:
        accessControlAllowMethods: ["GET","POST","PUT","DELETE","OPTIONS","PATCH"]
        accessControlAllowHeaders: ["*"]
        accessControlAllowOriginList: ["https://brisamar.lapesquerapp.es","https://pymcolorao.lapesquerapp.es","https://app.lapesquerapp.es","https://lapesquerapp.es"]
        accessControlAllowCredentials: true
        accessControlMaxAge: 86400
        addVaryHeader: true
```
En la aplicación API, label: `traefik.http.routers.[NOMBRE_ROUTER].middlewares=pesquerapp-cors@file`. Ref: [Traefik Headers](https://doc.traefik.io/traefik/middlewares/http/headers/).

---

## 5. Validación y troubleshooting

**Checklist:** `CORS_ALLOWED_ORIGINS` y `SANCTUM_STATEFUL_DOMAINS` en `.env`; `config/cors.php` con `api/*` y Allow-Headers con X-Tenant.

**Preflight:** `curl -H "Origin: https://brisamar.lapesquerapp.es" -H "Access-Control-Request-Method: POST" -H "Access-Control-Request-Headers: X-Tenant,Content-Type,Authorization" -X OPTIONS -v https://api.lapesquerapp.es/api/v2/public/tenant/brisamar` — esperado: 200/204 con Access-Control-Allow-Origin, Allow-Credentials, Allow-Headers con X-Tenant.

**Errores:** Sin CORS en preflight → SW cache o OPTIONS bloqueado (incógnito). Sin CORS en respuesta → Origin no llega (revisar proxy y CORS_ALLOWED_ORIGINS). Preflight falla con X-Tenant → incluir X-Tenant en `allowed_headers` en config y en proxy.

**Producción:** `CORS_ALLOWED_ORIGINS=https://brisamar.lapesquerapp.es,...`; `SANCTUM_STATEFUL_DOMAINS=brisamar.lapesquerapp.es,...`; `SESSION_DOMAIN=.lapesquerapp.es`.

---

## 6. Apéndice: análisis commit 8097331

En 8097331 CORS funcionaba: Dockerfile sin Apache CORS, Laravel con HandleCors. El commit ca740d7 añadió Apache CORS sin X-Tenant en Allow-Headers; si la imagen desplegada usa esa config, Apache devuelve headers incompletos. Las respuestas del Exception Handler no pasan por el middleware → se añadió `Handler::ensureCorsOnApiResponse()`. Coolify/Traefik puede quitar los headers; solución: CORS en Traefik (sección 4).