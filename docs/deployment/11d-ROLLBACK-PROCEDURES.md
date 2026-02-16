# Procedimientos de rollback — PesquerApp Backend

**Última actualización:** 2026-02-16

---

## Alcance

Estos procedimientos aplican al backend Laravel desplegado en entorno multi-tenant (Coolify/Docker). Un rollback puede ser de **código** (volver a una versión anterior del contenedor) o de **datos** (restaurar backup de bases de datos).

---

## 1. Rollback de código (contenedor / imagen)

### 1.1 Con Coolify

1. En Coolify, abrir la aplicación del backend.
2. En **Deployments** / historial de despliegues, localizar el despliegue estable anterior.
3. Ejecutar **Redeploy** sobre ese despliegue (o usar la opción de rollback si está disponible).
4. Verificar que el servicio arranca y que el health check pasa.

### 1.2 Con Docker Compose manual

```bash
# Listar imágenes/etiquetas disponibles
docker images | grep pesquerapp-backend

# Parar el stack actual, cambiar en docker-compose.yml o .env la etiqueta de la imagen
# a la versión anterior (ej. :v1.2.3 → :v1.2.2), luego:
docker compose pull
docker compose up -d
```

### 1.3 Post-rollback

- Comprobar logs: `docker compose logs -f app` (o equivalente).
- Comprobar que la API responde: `GET /api/v2/...` con header `X-Tenant` y token válido.
- Si se usan migraciones automáticas en arranque, asegurarse de que la versión anterior es compatible con el estado actual de las BBDD (ver sección 2).

---

## 2. Rollback de migraciones (multi-tenant)

Las migraciones se ejecutan por tenant. **No** se recomienda hacer rollback de migraciones en producción sin ventana de mantenimiento y backup reciente.

### 2.1 Si el despliegue ya ejecutó migraciones nuevas

- **Opción A (preferida):** Hacer rollback de código y dejar las migraciones aplicadas si son compatibles hacia atrás (columnas nuevas nullable, tablas nuevas no usadas aún). Revertir en el siguiente despliegue con una migración que deshaga el cambio.
- **Opción B:** Restaurar backup de cada base tenant (ver 3) y luego desplegar la versión anterior del código.

### 2.2 Rollback manual de migraciones (solo en emergencia)

```bash
# Por cada tenant (conexión a la BD del tenant):
php artisan migrate:rollback --database=tenant --step=1
```

Debe ejecutarse con el **código de la versión anterior** (que contiene la migración a revertir). Coordinar con el equipo para no dejar BBDD en estados inconsistentes entre tenants.

---

## 3. Restauración de bases de datos (backup)

- **Base central:** Contiene solo la tabla `tenants`. Restaurar desde backup según procedimiento del equipo (MySQL dump / restauración).
- **Bases tenant:** Cada tenant tiene su propia base. Restaurar solo la base del tenant afectado desde el backup correspondiente.
- Tras restaurar, verificar que el backend apunta a las mismas BBDD y que no hay desajustes de versión de código vs esquema.

---

## 4. Comunicación y registro

- Documentar en el runbook o en un postmortem: motivo del rollback, versión desplegada anterior, incidencias observadas.
- Si el rollback fue por un bug, abrir seguimiento (issue/tarea) para corregir y volver a desplegar cuando esté estable.

---

## Véase también

- [11c-PRODUCTION.md](11c-PRODUCTION.md) — Despliegue en producción.
- [11e-RUNBOOK.md](11e-RUNBOOK.md) — Runbook operativo (health checks, logs, incidentes).
