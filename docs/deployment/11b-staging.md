# Deployment — Staging

**Última actualización:** 2026-02-16

Entorno intermedio entre desarrollo y producción para validar releases antes de producción.

---

## Alcance

- Misma stack que producción (Laravel, MySQL, Redis, Coolify/Docker) pero con datos de prueba y variables de entorno de staging.
- Objetivo: probar migraciones, despliegue y smoke tests sin afectar producción.

## Procedimiento recomendado

1. **Build:** Usar la misma imagen o pipeline que producción (p. ej. tag o rama `staging`).
2. **Variables:** `.env` de staging con `APP_ENV=staging`, BBDD y dominios de staging, `CORS_ALLOWED_ORIGINS` y `SANCTUM_STATEFUL_DOMAINS` para el frontend de staging.
3. **Migraciones:** Ejecutar migraciones en staging antes de desplegar en producción; validar que no fallen.
4. **Health y smoke:** Tras el deploy, comprobar health check y un flujo mínimo (login, un recurso crítico). Ver [11e-RUNBOOK.md](11e-RUNBOOK.md).
5. **Rollback:** Si algo falla, seguir [11d-ROLLBACK-PROCEDURES.md](11d-ROLLBACK-PROCEDURES.md).

## Multi-tenant en staging

- Crear al menos un tenant de prueba en la base central y su base de datos; usar el mismo flujo que en producción (subdominio, BD por tenant).
- Documentar el subdominio y la URL del frontend de staging para el equipo.

## Véase también

- [11a-DEVELOPMENT.md](11a-DEVELOPMENT.md) — Desarrollo local.
- [11c-PRODUCTION.md](11c-PRODUCTION.md) — Producción.
- [11d-ROLLBACK-PROCEDURES.md](11d-ROLLBACK-PROCEDURES.md) — Rollback.
- [11e-RUNBOOK.md](11e-RUNBOOK.md) — Runbook operativo.
