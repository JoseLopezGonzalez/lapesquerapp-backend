---
title: Base de datos
description: Schema, modelos y estrategia de datos del backend PesquerApp.
updated: 2026-02-13
audience: Backend Engineers, Architects
---

# Base de datos

**Propósito:** Referencia de modelos, schema y estrategia de datos (multi-tenant: BD central + BD por tenant).

**Audiencia:** Desarrolladores backend, arquitectos.

---

## Contenido principal

- **[referencia/95-Modelos-Referencia.md](./referencia/95-Modelos-Referencia.md)** — Referencia de modelos Eloquent.
- **[referencia/96-Restricciones-Entidades.md](./referencia/96-Restricciones-Entidades.md)** — Restricciones entre entidades.
- **[database/migrations/](../database/migrations/)** — Migraciones (incl. `database/migrations/companies/` para tenant).
- **[database/migrations/companies/README.md](../database/migrations/companies/README.md)** — Documentación de migraciones por empresa/tenant.

Módulos que describen entidades y flujos de datos:

- [inventario/](./inventario/) — Almacenes, palets, cajas.
- [catalogos/](./catalogos/) — Productos, clientes, proveedores, etc.
- [produccion/](./produccion/) — Lotes, procesos, entradas, salidas.

---

## Véase también

- [03-ARCHITECTURE.md](./03-ARCHITECTURE.md) — Arquitectura y multi-tenant.
- [15-MULTI-TENANT-SPECIFICS.md](./15-MULTI-TENANT-SPECIFICS.md) — Multi-tenant.
