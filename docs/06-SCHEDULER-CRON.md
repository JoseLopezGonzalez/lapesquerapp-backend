---
title: Scheduler y Cron
description: Tareas programadas (Laravel Scheduler) en PesquerApp Backend.
updated: 2026-02-13
audience: Backend Engineers, DevOps
---

# Scheduler y Cron

## Propósito

Documentar las tareas programadas definidas en el Scheduler de Laravel y cómo ejecutarlas en desarrollo y producción (cron).

## Audiencia

Desarrolladores backend, DevOps.

---

## Configuración

- **Archivo:** `app/Console/Kernel.php`, método `schedule(Schedule $schedule)`.
- En producción el servidor debe ejecutar cada minuto (o según buena práctica):  
  `* * * * * cd /ruta/al/proyecto && php artisan schedule:run >> /dev/null 2>&1`  
  (o equivalente con el usuario y path correctos).

---

## Tareas programadas actuales

Definidas en `app/Console/Kernel.php`:

| Comando | Frecuencia | Descripción |
|---------|------------|-------------|
| `app:clean-old-order-pdfs` | Diario a las 02:00 | Limpieza de PDFs de pedidos antiguos. |
| `auth:cleanup-magic-tokens` | Diario a las 03:00 | Limpieza de tokens de magic link ya usados o caducados. |

---

## Comandos útiles

```bash
# Listar tareas programadas
php artisan schedule:list

# Ejecutar el scheduler una vez (útil para pruebas)
php artisan schedule:run

# Ejecutar un comando programado manualmente (ejemplo)
php artisan app:clean-old-order-pdfs
php artisan auth:cleanup-magic-tokens
```

Con Sail: `./vendor/bin/sail artisan schedule:list` y `./vendor/bin/sail artisan schedule:run`.

---

## Comandos personalizados registrados

- `tenants:migrate` — Migraciones por tenant (`--fresh`, `--seed`). Ver [04-DATABASE.md](./04-DATABASE.md) y [11-DEPLOYMENT/11c-PRODUCTION.md](./11-DEPLOYMENT/11c-PRODUCTION.md).

---

## Véase también

- [05-QUEUES-JOBS.md](./05-QUEUES-JOBS.md) — Colas (algunos jobs pueden ser disparados por el scheduler).
- [11-DEPLOYMENT/11c-PRODUCTION.md](./11-DEPLOYMENT/11c-PRODUCTION.md) — Producción (cron y comprobaciones).
- [11-DEPLOYMENT/11e-RUNBOOK.md](./11-DEPLOYMENT/11e-RUNBOOK.md) — Runbook operativo.
