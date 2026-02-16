# Runbook operativo — PesquerApp Backend

**Última actualización:** 2026-02-16

---

## 1. Health checks

### 1.1 Comprobar que la API responde

```bash
# Sustituir BASE_URL por la URL del backend (ej. https://api.pesquerapp.es)
curl -s -o /dev/null -w "%{http_code}" "https://BASE_URL/api/v2/health"
# Esperado: 200 (si existe ruta de health) o 401 (si requiere auth y no se envía token)
```

### 1.2 Comprobar desde el frontend

- Login en un subdominio tenant: si el magic link o OTP llega y el usuario entra, el backend está operativo.
- Si hay errores CORS o 5xx en la pestaña Red del navegador, revisar logs (sección 2).

### 1.3 Contenedor / proceso

```bash
docker compose ps
# Ver que el servicio app (o equivalente) está "Up"
docker compose logs app --tail 50
```

---

## 2. Logs

### 2.1 Laravel

- **Ubicación típica:** Dentro del contenedor, `storage/logs/laravel.log`.
- Ver en tiempo real: `docker compose exec app tail -f storage/logs/laravel.log`.
- Errores recientes: buscar `[ERROR]` o niveles críticos en ese archivo.

### 2.2 Nginx / Apache (si aplica)

- Logs de acceso y error del reverse proxy según configuración del host (Coolify suele exponer logs por servicio).

### 2.3 Base de datos

- Revisar conexión: que el backend pueda conectar a la base central y a las bases tenant.
- Errores tipo "Unknown database" o "Connection refused" indican problema de red o de configuración de BD.

---

## 3. Respuesta a incidentes

### 3.1 API devuelve 5xx

1. Revisar `storage/logs/laravel.log` para el stack trace.
2. Comprobar espacio en disco y memoria del contenedor.
3. Si es error de BD (timeout, deadlock), revisar estado del servidor MySQL y queries largas.
4. Si el error es recurrente tras un despliegue reciente, valorar rollback (véase [11d-ROLLBACK-PROCEDURES.md](11d-ROLLBACK-PROCEDURES.md)).

### 3.2 Errores CORS en el navegador

- Ver [docs/21-instrucciones/CORS-GUIA-DEFINITIVA.md](../../21-instrucciones/CORS-GUIA-DEFINITIVA.md) (o documentación CORS consolidada) para diagnóstico y configuración de `Allow-Origin` y headers.

### 3.3 Un tenant no puede acceder (otros sí)

- Comprobar que la base de datos de ese tenant existe y está accesible.
- Comprobar que el subdominio o header `X-Tenant` coincide con el identificador del tenant en la base central.
- Revisar logs para excepciones al conectar a la BD del tenant.

### 3.4 Cola de jobs (si se usa)

- `php artisan queue:work` debe estar en ejecución (supervisor o proceso en contenedor).
- Revisar jobs fallidos: `php artisan queue:failed` y reintentar o inspeccionar según procedimiento del equipo.

---

## 4. Tareas de mantenimiento rutinario

- **Backups:** Asegurar backups programados de la base central y de cada base tenant según política definida.
- **Limpieza de sesiones/tokens:** Si hay comandos o jobs programados para limpiar tokens o sesiones expiradas, verificar que el scheduler está activo (`php artisan schedule:run` en cron).

---

## Véase también

- [11c-PRODUCTION.md](11c-PRODUCTION.md) — Despliegue en producción.
- [11d-ROLLBACK-PROCEDURES.md](11d-ROLLBACK-PROCEDURES.md) — Procedimientos de rollback.
- [../12-TROUBLESHOOTING/COMMON-ERRORS.md](../12-TROUBLESHOOTING/COMMON-ERRORS.md) — Errores comunes.
