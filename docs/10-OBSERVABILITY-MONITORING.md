---
title: Observabilidad y monitoreo
description: Logging, métricas, health checks y procedimientos de monitoreo del backend PesquerApp.
updated: 2026-02-13
audience: Backend Engineers, DevOps
---

# Observabilidad y monitoreo

**Propósito:** Documentar cómo se registran eventos, se exponen métricas y se comprueba el estado del sistema (health checks) para operación y respuesta a incidentes.

**Audiencia:** Desarrolladores backend, DevOps.

---

## Logging

- **Canal principal:** Laravel usa el canal configurado en `config/logging.php` (por defecto `stack` → `daily` en `storage/logs/laravel.log`).
- **Nivel:** Controlado por `LOG_LEVEL` en `.env` (debug, info, warning, error, critical).
- **Logs de actividad:** El módulo de actividad (logs de usuario/entidad) está documentado en [sistema/83-Logs-Actividad.md](./28-sistema/83-Logs-Actividad.md).
- **Producción:** Evitar `LOG_LEVEL=debug` en producción; usar `info` o `warning` para reducir volumen y datos sensibles.

**Ubicación típica:** `storage/logs/laravel.log` (rotación diaria si se usa el driver `daily`).

---

## Métricas y rendimiento

- **Análisis de endpoints:** Rendimiento y planes de mejora de endpoints concretos en [referencia/100-Rendimiento-Endpoints.md](./30-referencia/100-Rendimiento-Endpoints.md).
- **Planes de mejora:** [referencia/101-Plan-Mejoras-GET-orders-id.md](./30-referencia/101-Plan-Mejoras-GET-orders-id.md), [referencia/102-Plan-Mejoras-GET-orders-active.md](./30-referencia/102-Plan-Mejoras-GET-orders-active.md).
- **Métricas externas:** Si se usa un APM o métricas (New Relic, Datadog, Prometheus, etc.), documentar en este apartado la integración y dashboards relevantes.

---

## Health checks

- **Comprobaciones básicas:** Disponibilidad de la aplicación (p. ej. ruta pública o `/api/v2/health` si existe), conexión a base de datos, colas y almacenamiento según el stack.
- **Multi-tenant:** En entornos con varias BBDD por tenant, considerar un health check que valide la conexión central y, si aplica, un muestreo de tenants.
- **Runbook:** Procedimientos operativos detallados (reinicio, escalado, respuesta a incidentes) en [11-DEPLOYMENT/11e-RUNBOOK.md](./11-DEPLOYMENT/11e-RUNBOOK.md).

---

## Véase también

- [12-TROUBLESHOOTING/COMMON-ERRORS.md](./12-TROUBLESHOOTING/COMMON-ERRORS.md) — Errores comunes.
- [12-TROUBLESHOOTING/PERFORMANCE-ISSUES.md](./12-TROUBLESHOOTING/PERFORMANCE-ISSUES.md) — Problemas de rendimiento.
- [sistema/83-Logs-Actividad.md](./28-sistema/83-Logs-Actividad.md) — Logs de actividad de la aplicación.
- [referencia/100-Rendimiento-Endpoints.md](./30-referencia/100-Rendimiento-Endpoints.md) — Análisis de rendimiento de endpoints.
