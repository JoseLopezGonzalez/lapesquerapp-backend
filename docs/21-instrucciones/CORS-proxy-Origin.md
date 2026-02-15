# CORS y cabecera Origin en producción

Si el frontend (p. ej. `https://brisamar.lapesquerapp.es`) recibe **"No 'Access-Control-Allow-Origin' header"** al llamar a la API (`https://api.lapesquerapp.es`), el servidor debe poder ver la cabecera **`Origin`** que envía el navegador.

## Comprobación

En el servidor donde corre Laravel (o en el proxy delante de él):

```bash
# Simular petición con Origin (como haría el navegador)
curl -s -I -H "Origin: https://brisamar.lapesquerapp.es" \
  https://api.lapesquerapp.es/api/v2/public/tenant/brisamar
```

La respuesta debe incluir:

```
Access-Control-Allow-Origin: https://brisamar.lapesquerapp.es
```

Si **no** aparece esa cabecera, o si al depurar en Laravel el header `Origin` llega vacío, el proxy está eliminando o no reenviando `Origin`.

## Solución en el proxy

### Nginx

Asegurar que el proxy reenvía las cabeceras al backend. Por ejemplo:

```nginx
location /api {
    proxy_pass http://backend_laravel;
    proxy_http_version 1.1;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_set_header Origin $http_origin;   # necesario para CORS
}
```

### Caddy

Caddy suele reenviar las cabeceras por defecto. Si usas `reverse_proxy`, no suele hacer falta añadir nada para `Origin`.

## Backend

En esta app se ha añadido:

- **Exception Handler**: respuestas 4xx/5xx de la API llevan CORS si el origen está permitido.
- **Middleware `EnsureCorsOnApiResponse`**: refuerza CORS en todas las respuestas de `api/*` cuando la petición trae un `Origin` permitido.

Si aun así no hay cabecera CORS, lo más probable es que **`Origin` no llegue al backend** (proxy o caché). Revisar la configuración del proxy y la comprobación con `curl` anterior.
