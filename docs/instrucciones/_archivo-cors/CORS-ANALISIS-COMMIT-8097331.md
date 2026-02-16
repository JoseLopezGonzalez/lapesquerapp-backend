# Análisis CORS: commit 8097331 (funcionaba) vs actual

**Commit que funcionaba**: `8097331aaec1a3cde741968ff4d77c2b7697b07a`

---

## Cambios clave que rompieron CORS

### 1. Dockerfile — Apache CORS añadido en ca740d7

| 8097331 (OK) | ca740d7 → actual |
|--------------|------------------|
| **Sin** Apache CORS | Se añadió `docker/apache-cors.conf` + a2enconf cors |
| Laravel gestionaba todo | Apache añadía headers restrictivos (sin X-Tenant) |
| | **Actual**: Lo quitamos de nuevo, correcto |

**Conclusión**: El commit `ca740d7` ("Refactor PDF extraction process and enhance CORS support") añadió CORS en Apache. Esa configuración es la que está devolviendo los headers que ves en curl (Authorization,Content-Type,Accept,Origin — sin X-Tenant). Si la imagen desplegada se construyó con ese Dockerfile, Apache está aplicando CORS y pisando Laravel.

### 2. config/cors.php — De hardcoded a env

| 8097331 (OK) | Actual |
|--------------|--------|
| `allowed_origins` hardcoded (array con dominios) | `allowed_origins` desde `env('CORS_ALLOWED_ORIGINS')` |
| `allowed_origins_patterns` con regex lapesquerapp | Mismo + más patrones |
| `allowed_headers` => `['*']` | Igual |

**Riesgo**: Si `CORS_ALLOWED_ORIGINS` está vacío o mal al cachear config, el array queda vacío. Con orígenes hardcoded no hay esa dependencia.

### 3. Kernel — HandleCors duplicado vs EnsureCorsOnApiResponse

| 8097331 (OK) | Actual |
|--------------|--------|
| HandleCors en **global** y en **api** | HandleCors solo en global |
| Sin EnsureCorsOnApiResponse | EnsureCorsOnApiResponse en api |

**Nota**: La duplicación en 8097331 era redundante; no debería explicar el fallo.

---

## Causa raíz probable

La imagen desplegada en producción se construyó con el Dockerfile de `ca740d7` o `f7776ff`, que incluye Apache CORS. Esa configuración de Apache devuelve headers restrictivos y sin `Access-Control-Allow-Origin` en algunos casos.

En 8097331 **no había** Apache CORS; CORS lo hacía solo Laravel.

---

## Solución recomendada

1. **Reconstruir imagen** con el Dockerfile actual (sin Apache CORS) y desplegar.
2. **Restaurar `config/cors.php`** al esquema de 8097331: orígenes hardcoded + patrones, sin depender de env para la lista principal.
3. **Forzar nuevo build** sin caché: `docker build --no-cache` para evitar capas con Apache CORS.
