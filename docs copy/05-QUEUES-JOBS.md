---
title: Colas y Jobs
description: Uso de colas (queues) y jobs en PesquerApp Backend (Laravel Queue).
updated: 2026-02-13
audience: Backend Engineers, DevOps
---

# Colas y Jobs

## Propósito

Documentar la configuración de colas (Laravel Queue), el driver por defecto y el uso en desarrollo (Sail) y producción. El proyecto usa colas para envío de correos y otros jobs asíncronos.

## Audiencia

Desarrolladores backend, DevOps.

---

## Configuración

- **Archivo:** `config/queue.php`
- **Variable de entorno:** `QUEUE_CONNECTION` (por defecto `sync` si no se define).
- **Drivers típicos:** `sync` (ejecución inmediata, sin worker), `database` (tabla `jobs`), `redis` (recomendado con Sail y en producción cuando Redis está disponible).

En tests (phpunit.xml) se fuerza `QUEUE_CONNECTION=sync` para que los jobs se ejecuten en el mismo proceso.

---

## Uso en el proyecto

- Varias clases de **Mail** (p. ej. `AccessEmail`, `OtpEmail`, `MagicLinkEmail`, `OrderShipped`) se encolan cuando se usa el driver `redis` o `database`; con `sync` se envían de forma síncrona.
- Existe la tabla `failed_jobs` en las migraciones de tenant (`database/migrations/companies/`) para registrar jobs fallidos.
- Para procesar la cola en desarrollo con Sail: `./vendor/bin/sail artisan queue:work`. En producción configurar un supervisor o proceso equivalente que ejecute `queue:work` de forma persistente.

---

## Comandos útiles

```bash
# Procesar jobs (un worker)
php artisan queue:work

# Con Sail
./vendor/bin/sail artisan queue:work

# Reintentar jobs fallidos
php artisan queue:retry all
# o uno concreto
php artisan queue:retry <job-id>

# Listar jobs fallidos
php artisan queue:failed
```

---

## Véase también

- [02-ENVIRONMENT-VARIABLES.md](./02-ENVIRONMENT-VARIABLES.md) — Variables (QUEUE_CONNECTION, Redis).
- [06-SCHEDULER-CRON.md](./06-SCHEDULER-CRON.md) — Scheduler (algunos comandos pueden despachar jobs).
- [11-DEPLOYMENT/11c-PRODUCTION.md](./11-DEPLOYMENT/11c-PRODUCTION.md) — Producción (workers y reinicio).
