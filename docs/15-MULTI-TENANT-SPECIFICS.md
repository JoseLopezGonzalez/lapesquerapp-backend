---
title: Especificidades Multi-Tenant
description: Cómo la multi-tenancy afecta desarrollo, datos y despliegue en PesquerApp Backend.
updated: 2026-02-13
audience: Backend Engineers, Architects, DevOps
---

# Especificidades Multi-Tenant

**Propósito:** Punto único de referencia para multi-tenant: conexiones BD, middleware, X-Tenant, migraciones por tenant.

**Audiencia:** Backend, arquitectos, DevOps.

## Contenido principal

- [fundamentos/01-Arquitectura-Multi-Tenant.md](./20-fundamentos/01-Arquitectura-Multi-Tenant.md) — Sistema multi-tenant, middleware, conexiones.
- [fundamentos/03-Configuracion-Entorno.md](./20-fundamentos/03-Configuracion-Entorno.md) — Configuración y variables.
- [database/migrations/companies/README.md](../database/migrations/companies/README.md) — Migraciones por tenant.
- [fundamentos/02-Autenticacion-Autorizacion.md](./20-fundamentos/02-Autenticacion-Autorizacion.md), [sistema/80-Usuarios.md](./28-sistema/80-Usuarios.md), [sistema/81-Roles.md](./28-sistema/81-Roles.md) — Auth y usuarios por tenant.

## Véase también

- [03-ARCHITECTURE.md](./03-ARCHITECTURE.md) — Arquitectura general.
- [04-DATABASE.md](./04-DATABASE.md) — Base de datos.
- [11-DEPLOYMENT/](./11-DEPLOYMENT/) — Despliegue (migraciones central + tenant).
