---
title: Deployment - Producción
description: Procedimientos de despliegue, migraciones y monitoreo en producción para PesquerApp Backend.
updated: 2026-02-13
audience: DevOps
---

# Deployment — Producción

## Propósito

Definir los procedimientos para desplegar el backend en producción: preparación del release, ejecución de migraciones (base central y por tenant), comprobaciones post-despliegue, monitoreo y alertas. Este documento es el punto de referencia operativo; los detalles de la plataforma (Coolify, Docker, etc.) deben completarse según el entorno real.

## Audiencia

DevOps y responsables de operación.

---

## Tabla de contenidos

1. [Requisitos previos](#requisitos-previos)
2. [Procedimiento de despliegue](#procedimiento-de-despliegue)
3. [Migraciones](#migraciones)
4. [Comprobaciones post-despliegue](#comprobaciones-post-despliegue)
5. [Monitoreo y alertas](#monitoreo-y-alertas)
6. [Véase también](#véase-también)

---

## Requisitos previos

- **Código:** branch/tag aprobado para producción; sin cambios no probados.
- **Secretos:** variables de entorno de producción configuradas (APP_KEY, DB_*, REDIS_*, mail, etc.). No usar valores de desarrollo. Ver [../environment-variables.md](../environment-variables.md).
- **Base de datos central:** accesible; backup reciente si aplica.
- **Bases de datos tenant:** listas para migrar; comunicación con equipos si el despliegue afecta a tenants en horario laboral.
- **Colas y scheduler:** en producción suelen usarse un driver de cola (redis/database) y un cron que ejecute `schedule:run`. Ver [../queues-jobs.md](../queues-jobs.md) y [../scheduler-cron.md](../scheduler-cron.md) cuando estén completos.

---

## Procedimiento de despliegue

*(Ajustar según la plataforma real: Coolify, Docker Compose, etc.)*

1. **Notificar** inicio de ventana de mantenimiento si aplica.
2. **Backup** de bases de datos (central y tenants críticos) según política.
3. **Desplegar código:** pull del tag/commit, `composer install --no-dev`, `npm ci && npm run build` si hay frontend de administración, o los pasos equivalentes en el pipeline.
4. **Ejecutar migraciones** según sección [Migraciones](#migraciones).
5. **Limpiar caché:** `php artisan config:clear`, `php artisan cache:clear`, `php artisan route:clear` (y `view:clear` si aplica).
6. **Reiniciar workers** de cola y/o procesos PHP (PHP-FPM, workers) para cargar el nuevo código.
7. **Comprobaciones** según [Comprobaciones post-despliegue](#comprobaciones-post-despliegue).
8. **Cerrar** ventana de mantenimiento y dejar constancia en logs o runbook.

---

## Migraciones

- **Base central:**  
  `php artisan migrate --force`  
  (o el comando que aplique a la conexión central). Ejecutar en el contexto del servidor de aplicación (un solo nodo en el despliegue para evitar carreras).

- **Bases por tenant:**  
  El proyecto incluye el comando `MigrateTenants` (ver `app/Console/Commands/MigrateTenants.php`). Ejecutar:  
  `php artisan tenants:migrate`  
  Opciones: `--fresh` (drop all tables y migrar de nuevo), `--seed` (ejecutar seeders tras migrar). En producción usar solo `tenants:migrate` sin `--fresh` salvo recuperación controlada. Orden y paralelismo según diseño del comando; en caso de muchos tenants, valorar ejecución en lotes o fuera de pico.

- **Rollback:** si algo falla, valorar `migrate:rollback` en central y/o por tenant según [11d-ROLLBACK-PROCEDURES.md](./11d-ROLLBACK-PROCEDURES.md). No ejecutar rollbacks sin haber comprobado impacto en la aplicación.

---

## Comprobaciones post-despliegue

- **Health/readiness:** si existe endpoint de salud (p. ej. `/up` o `/health`), comprobar que devuelve 200.
- **Login/API:** una petición de login (magic link o OTP) y al menos un request autenticado a un endpoint v2 para validar que la API responde y el tenant se resuelve correctamente.
- **Colas:** que los jobs pendientes se procesen (revisar driver de cola y workers).
- **Scheduler:** que el cron esté llamando a `schedule:run` y que los comandos programados (p. ej. limpieza de PDFs, tokens) se ejecuten según [../scheduler-cron.md](../scheduler-cron.md).
- **Logs:** revisar `storage/logs` (o el destino configurado) por errores justo después del despliegue.

---

## Monitoreo y alertas

*(Completar según la pila real: Prometheus, Grafana, Sentry, logs centralizados, etc.)*

- **Aplicación:** disponibilidad del endpoint principal o de salud; tiempo de respuesta.
- **Base de datos:** conexiones, errores, lentitud de consultas si hay métricas.
- **Colas:** tamaño de la cola, jobs fallidos (failed_jobs).
- **Logs:** errores 5xx y excepciones; alertas si el volumen supera umbrales.
- **Secretos y configuración:** no alertar con datos sensibles; usar solo indicadores de “configuración cargada” o “conexión OK” si aplica.

Documentación de observabilidad: [../observability-monitoring.md](../observability-monitoring.md). Runbook operativo: [11e-RUNBOOK.md](./11e-RUNBOOK.md).

---

## Véase también

- [11a-DEVELOPMENT.md](./11a-DEVELOPMENT.md) — Desarrollo (Sail).
- [11b-STAGING.md](./11b-STAGING.md) — Staging.
- [11d-ROLLBACK-PROCEDURES.md](./11d-ROLLBACK-PROCEDURES.md) — Rollback.
- [11e-RUNBOOK.md](./11e-RUNBOOK.md) — Runbook.
- [../environment-variables.md](../environment-variables.md) — Variables de entorno.
- [../multi-tenant-specs.md](../multi-tenant-specs.md) — Multi-tenant y despliegue.

---

## Historial de cambios

| Fecha       | Cambio                          |
|------------|----------------------------------|
| 2026-02-13 | Documento creado (FASE 5).      |
