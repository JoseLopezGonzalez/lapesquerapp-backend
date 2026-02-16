# CORS: diagnóstico y opciones (archivado — ver CORS-GUIA-DEFINITIVA.md)

---
**Archivado:** 2026-02-16. Contenido consolidado en `CORS-GUIA-DEFINITIVA.md`.

---

# CORS: diagnóstico y opciones antes de seguir

## Lo que sabemos

- El **servidor responde 200 OK** (la petición llega a la API y Laravel responde bien).
- El **navegador dice** que no hay cabecera `Access-Control-Allow-Origin`.
- Pasa en **cualquier tenant** (brisamar, pymcolorao, etc.).

Eso solo puede significar una de estas dos cosas:

1. **Laravel no está enviando la cabecera** (porque no tiene `Origin` en la petición, o no aplica CORS por algún motivo).
2. **Laravel sí la envía**, pero **algo entre Laravel y el navegador la quita o la oculta** (proxy, CDN, Service Worker).

---

## Dónde puede estar fallando

| Lugar | Qué puede pasar |
|------|------------------|
| **Proxy (Nginx, etc.)** | No reenvía `Origin` a Laravel → Laravel no puede poner CORS. O usa `add_header` y en tu versión/config solo se envían las cabeceras de Nginx y no las del upstream (Laravel). |
| **Laravel** | Si `Origin` no llega, nuestro código no añade CORS (solo añadimos cuando hay `Origin` permitido). |
| **Service Worker (sw.js)** | Intercepta el `fetch`, puede devolver una respuesta cacheada que no tiene cabeceras CORS, o una respuesta “opaca”. |
| **CDN / WAF** | Algunos quitan o reescriben cabeceras CORS. |

---

## Diagnóstico rápido (recomendado hacerlo primero)

Desde tu máquina (o un servidor con acceso a internet):

```bash
curl -s -D - -o /dev/null -H "Origin: https://pymcolorao.lapesquerapp.es" \
  https://api.lapesquerapp.es/api/v2/public/tenant/pymcolorao
```

- **Si en la salida aparece** `Access-Control-Allow-Origin: https://pymcolorao.lapesquerapp.es`  
  → El servidor (Laravel + proxy) **sí** está enviando CORS. Entonces el problema es casi seguro el **Service Worker** o el navegador (cache, extensión). Habría que ir por la opción B + C.

- **Si no aparece** esa cabecera  
  → O Laravel no la está enviando (p. ej. porque no recibe `Origin`), o el proxy la está quitando. Habría que ir por la **opción A (Nginx)** y, si quieres, comprobar que el proxy reenvía `Origin` a Laravel.

---

## Opciones a discutir

### A) Solución en Nginx (recomendada si tienes acceso al proxy)

**Idea:** Nginx sí recibe `Origin` del navegador. Nos olvidamos de depender de que llegue a Laravel y **añadimos CORS en Nginx** para las peticiones a `/api/`.

**Ventajas:**
- No depende de que el proxy reenvíe `Origin` a Laravel.
- Funciona igual para todos los tenants (un solo `map` con el patrón `*.lapesquerapp.es`).
- Es la práctica habitual cuando hay un proxy delante.

**Qué hacer:**
- En el `server`/`http` donde está el `location` de la API, definir un `map $http_origin $cors_origin` con tus orígenes permitidos (p. ej. `https://*.lapesquerapp.es`).
- En el `location` que hace `proxy_pass` a Laravel para `/api/`:
  - Añadir `add_header "Access-Control-Allow-Origin" $cors_origin always;` (y el resto de cabeceras CORS que quieras) **solo cuando** `$cors_origin` no esté vacío.
  - Opcional pero recomendable: `proxy_set_header Origin $http_origin;` para que Laravel también reciba `Origin`.

Ya tienes un ejemplo en `docs/21-instrucciones/nginx-cors-api.conf`.

**Requiere:** Acceso a la configuración de Nginx en el servidor donde está api.lapesquerapp.es.

---

### B) Asegurar que el proxy reenvía `Origin` a Laravel

**Idea:** Si Nginx (u otro proxy) no reenvía la cabecera `Origin`, Laravel nunca la ve y nuestro código no añade CORS.

**Qué hacer:**
- En el `location` de la API:  
  `proxy_set_header Origin $http_origin;`
- Recargar Nginx.

Si con esto el `curl` de arriba ya devuelve `Access-Control-Allow-Origin`, no haría falta añadir CORS en Nginx (aunque tenerlo en Nginx sigue siendo un buen respaldo).

---

### C) Service Worker: no cachear el endpoint de tenant

**Idea:** Si existe un Service Worker (p. ej. PWA con `sw.js`) que intercepta `fetch`, puede estar:
- cacheando la respuesta del tenant, y la versión cacheada no tiene cabeceras CORS, o
- devolviendo una respuesta opaca.

**Qué hacer (en el frontend):**
- No cachear peticiones a `https://api.lapesquerapp.es/api/v2/public/tenant/*` (o al menos usar “network first” para esa URL).
- En Workbox (o equivalente): excluir esa URL del cache o usar estrategia que siempre vaya a red.

Así el navegador siempre recibe la respuesta real del servidor, con las cabeceras que envíe (Laravel o Nginx).

---

### D) No tocar Nginx y “forzar” CORS solo en Laravel

**Idea:** Poner CORS en Laravel **sin depender de `Origin`** (p. ej. devolver un origen por defecto o una lista fija).

**Problema:** Con CORS y credenciales no puedes usar `*`; tienes que devolver **el origen concreto** que pide el navegador. Ese valor viene en la cabecera `Origin`. Si el proxy no reenvía `Origin`, Laravel no sabe qué valor poner y no puede cumplir bien el estándar CORS. Por eso no es una solución fiable si el proxy no reenvía `Origin`.

Solo tendría sentido si primero aplicas la opción B y confirmas que `Origin` llega a Laravel.

---

## Propuesta de orden

1. **Diagnóstico:** Ejecutar el `curl` de arriba y ver si aparece `Access-Control-Allow-Origin` en la respuesta.
2. **Si no aparece:**
   - **A)** Añadir CORS en Nginx (y opcionalmente B: `proxy_set_header Origin $http_origin`).
3. **Si aparece en `curl` pero el navegador sigue fallando:**
   - **C)** Revisar el Service Worker y excluir (o network-first) la URL del tenant.
4. Mantener los cambios actuales en Laravel como respaldo (Exception Handler, middleware, controlador, OPTIONS); no contradicen A/B/C.

Si comentas qué resultado te da el `curl` y si tienes acceso a Nginx (y si usas Service Worker en ese dominio), se puede concretar el siguiente paso exacto (ej. solo Nginx, solo SW, o ambos).