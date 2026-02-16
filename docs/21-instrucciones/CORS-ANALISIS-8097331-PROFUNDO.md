# Análisis CORS: 8097331 (funcionaba) vs producción actual

## Estado de referencia que funcionaba: d311027 (11 Feb 2026)

Commit **d311027** fue el último desplegado entre el 11 y 15 de febrero con CORS funcionando correctamente. Después de ese despliegue, commits del día 15 introdujeron cambios que rompieron CORS.

**Archivos restaurados a d311027:**
- `config/cors.php` — orígenes, patrones, max_age=0
- `config/sanctum.php` — `stateful => []`
- `app/Http/Middleware/TenantMiddleware.php` — sin exclusión OPTIONS (se mantiene api/health para health checks actuales)
- `Dockerfile` — solo `a2enmod rewrite` (sin headers/setenvif)

---

## Hallazgo clave: Exception Handler (15 Feb 2026)

**Síntoma:** `GET /api/v2/public/tenant/brisamar` devuelve CORS correctamente (200 OK). `POST /api/v2/auth/request-access` no devuelve `Access-Control-Allow-Origin` y el navegador bloquea.

**Causa raíz:** Las respuestas generadas por el **Exception Handler** (ValidationException, 404, 500, etc.) **no pasan por el middleware**. HandleCors solo se ejecuta en el flujo normal request → controller → response. Cuando se lanza una excepción, el Handler devuelve JSON directamente y esa respuesta nunca recibe cabeceras CORS.

**Solución aplicada:** `Handler::ensureCorsOnApiResponse()` — añade CORS a todas las respuestas JSON del Handler para rutas `api/*`. Ver `app/Exceptions/Handler.php`.

---

## Resumen

En **8097331** CORS funcionaba con la **misma configuración de servidor** (Coolify/proxy). La única diferencia relevante es la **imagen Docker desplegada**.

---

## Comparativa detallada

### Estado en 8097331 (14 Feb 2026, funcionaba)

| Componente | Estado |
|------------|--------|
| **Dockerfile** | Sin Apache CORS. Solo `a2enmod rewrite` |
| **docker/apache-cors.conf** | **No existía** |
| **config/cors.php** | Orígenes hardcodeados + `allowed_origins_patterns` |
| **Kernel** | HandleCors en global y en api |
| **TenantMiddleware** | Excluye `api/v2/public/*` |
| **Flujo** | Petición → proxy → Apache → **Laravel** → HandleCors añade CORS completos |

### Estado desde ca740d7 (15 Feb, rompió CORS)

| Componente | Estado |
|------------|--------|
| **Dockerfile** | Apache CORS añadido (`a2enconf cors`) |
| **docker/apache-cors.conf** | Allow-Headers: `Authorization, Accept, Origin, Content-Type` — **sin X-Tenant** |
| **Flujo** | Petición → proxy → Apache (aplica CORS) → Laravel |

### Respuesta actual del curl (producción)

```
access-control-allow-headers: Authorization,Content-Type,Accept,Origin   ← sin X-Tenant
access-control-allow-credentials: true
access-control-allow-methods: ...
Access-Control-Allow-Origin: (NO PRESENTE)
```

Esa lista de headers coincide exactamente con la config de **ca740d7**, no con el HEAD actual (que sí incluye X-Tenant). Por tanto: **producción está usando una imagen basada en ca740d7** (o similar), no en 8097331 ni en el HEAD actual.

---

## Causa raíz

1. **8097331**: No hay Apache CORS. El OPTIONS llega a Laravel. HandleCors devuelve los CORS correctos, incluidos `Access-Control-Allow-Origin` y `X-Tenant` en Allow-Headers.

2. **ca740d7 en adelante**: Apache CORS se aplica en `/api`. Apache añade sus headers, pero:
   - La versión ca740d7 no incluye X-Tenant en Allow-Headers.
   - Si `Origin` no llega al contenedor (proxy), `ORIGIN_ALLOWED` no se establece y Apache no añade `Access-Control-Allow-Origin`.
   - Otra capa (proxy/Traefik) podría estar respondiendo al OPTIONS antes con una configuración incompleta.

3. **Servidor sin cambios**: Coolify/proxy no se modificó. El único cambio relevante es la imagen Docker desplegada tras ca740d7.

---

## Estado que funcionaba: d8aab25 (13 feb 2026)

El commit **d8aab25657128e158c0207627b150aa865840bfd** (13 feb 2026, "Refactor TenantMiddleware logging") es el que funcionaba en producción.

**Restaurado exactamente:**
- `config/cors.php` — orígenes con lapesquerapp.es, *.lapesquerapp.es, patrones, supports_credentials
- `app/Http/Kernel.php` — HandleCors en global y api, sin EnsureCorsOnApiResponse
- `Dockerfile` — sin Apache CORS, solo `a2enmod rewrite`
- `TenantMiddleware` — excluye `api/v2/public/*`, log solo en debug

---

## Nota: Coolify/Traefik

Tras más análisis: **Coolify usa Traefik como proxy** y hay un [issue abierto](https://github.com/coollabsio/coolify/issues/2570) donde se indica que **los headers CORS que envía la aplicación no llegan al cliente** porque Traefik los elimina o altera.

Por eso **revertir a 8097331 no soluciona el problema**: el fallo está en la capa de proxy, no en el código Laravel.

**Solución:** Configurar CORS en Traefik dentro de Coolify. Ver:
**`docs/21-instrucciones/CORS-COOLIFY-TRAEFIK-SOLUCION.md`**

---

## Solución anterior (solo si el proxy no fuera el problema)

Para replicar el comportamiento que funcionaba, habría que **quitar Apache CORS** del Dockerfile y dejar que **solo Laravel** gestione CORS. Pero si Coolify/Traefik está quitando los headers, eso no basta.

### Pasos (si el proxy no interfiere)

1. Revertir el Dockerfile al estado de 8097331 (sin Apache CORS).
2. Mantener `config/cors.php` como en 8097331 (hardcodeado).
3. Reconstruir la imagen con `docker build --no-cache`.
4. Desplegar.
