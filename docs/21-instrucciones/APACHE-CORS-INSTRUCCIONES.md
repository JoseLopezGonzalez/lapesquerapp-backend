# CORS en Apache (api.lapesquerapp.es)

Tu servidor es **Apache/2.4**, no Nginx. La respuesta del `curl` no incluye `Access-Control-Allow-Origin`, así que hay que añadir CORS en Apache.

## 1. Activar módulos

```bash
sudo a2enmod headers setenvif
```

## 2. Añadir CORS en el VirtualHost de la API

Edita el VirtualHost que sirve `api.lapesquerapp.es` (por ejemplo en `/etc/apache2/sites-available/` o donde tengas el sitio).

Dentro del bloque `<VirtualHost>`, añade un bloque **`<Location "/api">`** con lo siguiente (ajusta si tu ruta no es exactamente `/api`):

```apache
<Location "/api">
    SetEnvIf Origin "^https://[a-z0-9-]+\.lapesquerapp\.es$" ORIGIN_ALLOWED=$0
    SetEnvIf Origin "^https://lapesquerapp\.es$" ORIGIN_ALLOWED=$0
    SetEnvIf Origin "^http://localhost(:[0-9]+)?$" ORIGIN_ALLOWED=$0
    SetEnvIf Origin "^http://127\.0\.0\.1(:[0-9]+)?$" ORIGIN_ALLOWED=$0

    Header always set Access-Control-Allow-Origin "%{ORIGIN_ALLOWED}e" env=ORIGIN_ALLOWED
    Header always set Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS, PATCH" env=ORIGIN_ALLOWED
    Header always set Access-Control-Allow-Headers "Authorization, Accept, Origin, Content-Type" env=ORIGIN_ALLOWED
    Header always set Access-Control-Allow-Credentials "true" env=ORIGIN_ALLOWED
    Header always set Vary "Origin" env=ORIGIN_ALLOWED
</Location>
```

(Opcional) Para que Laravel también reciba la cabecera Origin:

```apache
RequestHeader set Origin %{HTTP_Origin}e
```

Puedes ponerlo dentro del mismo VirtualHost, fuera del `<Location>`.

## 3. Comprobar y recargar

```bash
sudo apachectl configtest
sudo systemctl reload apache2
```

## 4. Verificar

```bash
curl -s -D - -o /dev/null -H "Origin: https://pymcolorao.lapesquerapp.es" \
  https://api.lapesquerapp.es/api/v2/public/tenant/pymcolorao
```

Debe aparecer en la salida:

```
Access-Control-Allow-Origin: https://pymcolorao.lapesquerapp.es
```

---

**Nota:** El archivo `apache-cors-api.conf` en esta misma carpeta es un snippet que puedes incluir con `Include` si prefieres mantener la config CORS en un archivo aparte.
