# Análisis Multi-Tenancy — PesquerApp Backend

**Fecha**: 2026-03-23

---

## Estado actual

- Estrategia **database-per-tenant** mantenida y coherente.
- Resolución de tenant por cabecera `X-Tenant`.
- `TenantMiddleware` valida existencia y estado, fija `currentTenant`, conmuta `database.default` a `tenant`, actualiza la BD del tenant y fuerza `DB::purge()` + `DB::reconnect()`.
- El dominio tenant usa de forma amplia el trait `UsesTenantConnection`.
- La tabla `tenants` sigue residiendo en la base central (`mysql`) mediante el modelo `Tenant`.

## Hallazgos positivos

- La separación central vs tenant sigue siendo clara y fácil de razonar.
- El helper `tenantSetting()` ya usa `Setting::query()` y no acceso SQL directo ad hoc.
- La auditoría previa estaba desactualizada en un punto importante: el sistema **sí** tiene jobs tenant-relevantes en la capa SaaS.
- Existen tests de tenant público, CORS y varios bloques multi-tenant que usan explícitamente `X-Tenant`.

## Riesgos residuales

- La convención tenant-aware para jobs existe de facto, pero no está formalizada como estándar transversal reusable.
- El middleware hace reconnect por request; correcto para este patrón, pero debe vigilarse en escenarios de más carga o workers persistentes.
- No hay evidencia suficiente en repo de una estrategia tenant-aware explícita para cache keys más allá del uso puntual y el prefijo global por app.

## Valoración

| Aspecto | Nota | Comentario |
|--------|------|------------|
| Aislamiento de datos | 9/10 | Muy fuerte por diseño físico. |
| Resolución por request | 8.5/10 | Clara y segura. |
| Consistencia de uso de conexión | 8.5/10 | Buena, con escasas excepciones problemáticas. |
| Asincronía tenant-aware | 7.5/10 | Presente pero todavía sin patrón formal documentado. |

**Conclusión**: la madurez multi-tenant es una fortaleza real del backend y se mantiene en la franja **8.5/10**.
