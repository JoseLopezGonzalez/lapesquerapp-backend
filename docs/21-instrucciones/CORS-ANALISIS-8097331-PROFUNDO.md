# Análisis CORS: 8097331 (funcionaba) vs producción actual

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

## Solución: volver al Dockerfile de 8097331

Para replicar el comportamiento que funcionaba, hay que **quitar Apache CORS** del Dockerfile y dejar que **solo Laravel** gestione CORS:

- Sin Apache CORS, el flujo es el mismo que en 8097331.
- Laravel tiene HandleCors, orígenes y patrones ya configurados.
- No se depende de que Apache reciba ni coincida el `Origin`.

---

## Pasos

1. Revertir el Dockerfile al estado de 8097331 (sin Apache CORS).
2. Mantener `config/cors.php` como en 8097331 (hardcodeado) o con el merge de `env`; ambos son válidos.
3. Reconstruir la imagen con `docker build --no-cache`.
4. Desplegar.
