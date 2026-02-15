# CORS en Coolify: solución en Traefik (proxy)

## Causa raíz confirmada

**Coolify usa Traefik como proxy.** Hay un [issue abierto](https://github.com/coollabsio/coolify/issues/2570) donde se describe que **los headers CORS de la aplicación no llegan al cliente** porque **Traefik/Caddy los elimina o modifica** antes de enviar la respuesta.

Por tanto, el problema **no está en Laravel**. Aunque Laravel devuelva los headers CORS correctos, el proxy puede estar quitándolos. Cambiar el commit del backend no soluciona el problema.

---

## Solución: CORS en Traefik (dentro de Coolify)

Hay que **configurar CORS en Traefik** (que recibe la petición antes que el contenedor). Así Traefik añade los headers CORS y estos sí llegan al cliente.

### Pasos (usando Traefik como proxy)

#### 1. Configuración dinámica de Traefik

En Coolify: **Servers → tu servidor → Proxy → Dynamic Configurations**

Crear un archivo de configuración (por ejemplo `cors-api.yaml`) con:

```yaml
http:
  middlewares:
    pesquerapp-cors:
      headers:
        accessControlAllowMethods:
          - "GET"
          - "POST"
          - "PUT"
          - "DELETE"
          - "OPTIONS"
          - "PATCH"
        accessControlAllowHeaders:
          - "*"
        accessControlAllowOriginList:
          - "https://brisamar.lapesquerapp.es"
          - "https://pymcolorao.lapesquerapp.es"
          - "https://app.lapesquerapp.es"
          - "https://nextjs.congeladosbrisamar.es"
          - "https://lapesquerapp.es"
        accessControlAllowCredentials: true
        accessControlMaxAge: 86400
        addVaryHeader: true
```

**Importante:** `accessControlAllowHeaders: ["*"]` permite todos los headers, incluido `X-Tenant`.

#### 2. Asociar el middleware al router de la API

En Coolify: **tu aplicación API → Labels (o configuración avanzada)**

Hay que aplicar el middleware al router que sirve `api.lapesquerapp.es`. El nombre del router lo genera Coolify.

Opciones para ver el router generado:
- **Service Stack → Edit Compose File → Show Deployable Compose**: ahí aparecen los labels de Traefik con el nombre del router.

Añadir o modificar el label para usar el middleware CORS:

```text
traefik.http.routers.[NOMBRE_ROUTER].middlewares=pesquerapp-cors@file
```

Si ya existe otro middleware, concatenar con coma:

```text
traefik.http.routers.[NOMBRE_ROUTER].middlewares=gzip,pesquerapp-cors@file
```

El sufijo `@file` indica que el middleware viene de la configuración dinámica por archivo.

#### 3. Guardar y reiniciar

Guardar la configuración dinámica de Traefik, guardar los labels de la aplicación y reiniciar el servicio.

---

## Variante: orígenes por regex

Si quieres permitir cualquier subdominio de `lapesquerapp.es` sin listarlos uno a uno:

```yaml
http:
  middlewares:
    pesquerapp-cors:
      headers:
        accessControlAllowMethods:
          - "GET"
          - "POST"
          - "PUT"
          - "DELETE"
          - "OPTIONS"
          - "PATCH"
        accessControlAllowHeaders:
          - "*"
        accessControlAllowOriginListRegex:
          - "^https://[a-z0-9-]+\\.lapesquerapp\\.es$"
          - "^https://app\\.lapesquerapp\\.es$"
          - "^https://lapesquerapp\\.es$"
          - "^https://[a-z0-9-]+\\.congeladosbrisamar\\.es$"
        accessControlAllowCredentials: true
        accessControlMaxAge: 86400
        addVaryHeader: true
```

**Nota:** En YAML, el backslash se escapa como `\\.`

---

## Referencias

- [Coolify Issue #2570](https://github.com/coollabsio/coolify/issues/2570): CORS headers no llegan al cliente
- [Solución aportada](https://github.com/coollabsio/coolify/issues/2570#issuecomment-2315314910) en el mismo issue (config dinámica + labels)
- [Traefik Headers Middleware](https://doc.traefik.io/traefik/middlewares/http/headers/) – CORS

---

## Resumen

| Capa      | Comportamiento actual | Solución                              |
|----------|------------------------|----------------------------------------|
| Laravel  | Devuelve headers CORS | No es suficiente si el proxy los quita |
| Traefik  | Quita/modifica CORS   | Configurar CORS en Traefik             |
| Coolify  | Gestiona Traefik      | Añadir config dinámica y labels        |

La solución pasa por configurar CORS en Traefik dentro de Coolify, no solo en el backend Laravel.
