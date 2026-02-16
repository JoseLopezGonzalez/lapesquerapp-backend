# CORS: Validación y Troubleshooting

**Estado**: Actualizado 2026-02-15  
**Contexto**: CORS gestionado exclusivamente en Laravel. Apache (Docker) no incluye configuración CORS.

---

## Checklist de verificación

- [ ] `CORS_ALLOWED_ORIGINS` definido en .env (producción: lista blanca explícita)
- [ ] `SANCTUM_STATEFUL_DOMAINS` incluye dominios del frontend (sin protocolo)
- [ ] `config/cors.php` paths incluye `api/*` y `sanctum/csrf-cookie`
- [ ] HandleCors en middleware global (app/Http/Kernel.php)
- [ ] Sin configuración CORS en Apache (Dockerfile no copia apache-cors.conf)

---

## Comandos de validación

### Preflight OPTIONS (curl)

```bash
curl -H "Origin: https://brisamar.lapesquerapp.es" \
     -H "Access-Control-Request-Method: POST" \
     -H "Access-Control-Request-Headers: X-Tenant,Content-Type,Authorization" \
     -X OPTIONS \
     -v \
     https://api.lapesquerapp.es/api/v2/public/tenant/brisamar
```

**Respuesta esperada**: 200 o 204 con headers:
- `Access-Control-Allow-Origin: https://brisamar.lapesquerapp.es`
- `Access-Control-Allow-Credentials: true`
- `Access-Control-Allow-Methods: ...`
- `Access-Control-Allow-Headers: ...` (debe incluir X-Tenant)

### Local (Sail / php artisan serve)

```bash
curl -H "Origin: http://localhost:3000" \
     -H "Access-Control-Request-Method: GET" \
     -X OPTIONS \
     -v \
     http://localhost/api/health
```

---

## Errores comunes

| Error | Causa | Solución |
|-------|-------|----------|
| No 'Access-Control-Allow-Origin' header (preflight) | PWA/Service Worker cachea respuesta sin CORS; o TenantMiddleware bloqueaba OPTIONS | Rutas OPTIONS explícitas en auth; TenantMiddleware deja pasar OPTIONS. Probar en incógnito o desregistrar SW |
| No 'Access-Control-Allow-Origin' header | Origin no permitido o no llega | Revisar CORS_ALLOWED_ORIGINS y patrones; verificar que proxy reenvíe Origin |
| CORS con credentials rechazado | Origin no coincide exactamente | No usar * con credentials; lista blanca explícita |
| Preflight falla con X-Tenant | Header no en Allow-Headers | config/cors.php debe tener allowed_headers => ['*'] |
| Funciona en local, falla en prod | Variables .env distintas | Definir CORS_ALLOWED_ORIGINS y SANCTUM_STATEFUL_DOMAINS en Coolify |

---

## Variables por entorno

### Local
```env
CORS_ALLOWED_ORIGINS=http://localhost:3000,http://localhost:3001,http://127.0.0.1:3000
SANCTUM_STATEFUL_DOMAINS=localhost:3000,localhost:3001,127.0.0.1:3000
```

### Producción (Coolify)
```env
CORS_ALLOWED_ORIGINS=https://brisamar.lapesquerapp.es,https://pymcolorao.lapesquerapp.es
# Los subdominios *.lapesquerapp.es se permiten vía allowed_origins_patterns
SANCTUM_STATEFUL_DOMAINS=brisamar.lapesquerapp.es,pymcolorao.lapesquerapp.es,lapesquerapp.es
SESSION_DOMAIN=.lapesquerapp.es
```
