# Guía de depuración — PesquerApp Backend

**Última actualización:** 2026-02-16

Procedimientos para depurar el backend Laravel (local, Sail, o contenedor).

---

## 1. Logs

- **Laravel:** `storage/logs/laravel.log`. En Sail: `./vendor/bin/sail exec laravel.test tail -f storage/logs/laravel.log`.
- **Nivel:** Aumentar temporalmente en `.env`: `LOG_LEVEL=debug` (no dejar en producción).
- **Canal:** Para depurar un request concreto, añadir `Log::debug('...', $context)` y filtrar por request ID si se usa.

## 2. Base de datos y tenant

- **Conexión actual:** En middleware/contexto tenant, la conexión es `tenant`; en tareas fuera del request (jobs), hay que configurar la conexión al arrancar según el tenant del payload.
- **Queries:** Habilitar query log: `DB::enableQueryLog();` antes del código a depurar; después `dd(DB::getQueryLog());`. No dejar en producción.
- **Tenant equivocado:** Comprobar header `X-Tenant` y que el subdominio exista en la tabla `tenants` de la base central; que la BD del tenant exista y esté accesible.

## 3. Errores 4xx/5xx en API

- Revisar el stack trace en `laravel.log`; la primera línea suele indicar el archivo y la línea.
- **ValidationException:** Los mensajes están en la respuesta JSON bajo `errors`; revisar reglas en el Form Request.
- **404:** Comprobar ruta en `routes/api.php` y que el recurso exista en la BD del tenant correcto.
- **500:** Revisar excepciones no capturadas; en local, Whoops muestra el detalle; en producción, solo el log.

## 4. CORS y frontend

- Ver [../instrucciones/CORS-GUIA-DEFINITIVA.md](../instrucciones/CORS-GUIA-DEFINITIVA.md). Comprobar con `curl -H "Origin: ..."` que el servidor devuelve `Access-Control-Allow-Origin`; si no, proxy o Laravel no reciben `Origin`.

## 5. Colas y jobs

- Si el job no se ejecuta: comprobar que `php artisan queue:work` (o supervisor) está en marcha.
- Jobs fallidos: `php artisan queue:failed`; reintentar con `php artisan queue:retry <id>` o inspeccionar el payload.
- Logs del worker en el proceso que ejecuta `queue:work` o en el contenedor.

## Véase también

- [COMMON-ERRORS.md](COMMON-ERRORS.md) — Errores frecuentes.
- [../10-observability-monitoring.md](../10-observability-monitoring.md) — Observabilidad.
- [../deployment/11e-RUNBOOK.md](../deployment/11e-RUNBOOK.md) — Runbook (logs, incidentes).
