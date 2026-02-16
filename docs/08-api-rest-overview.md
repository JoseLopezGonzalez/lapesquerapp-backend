---
title: API REST
description: Referencia de la API REST v2 del backend PesquerApp.
updated: 2026-02-13
audience: Backend Engineers, Frontend Engineers
---

# API REST

**Propósito:** Punto de entrada a la documentación de la API REST v2 (endpoints, autenticación, recursos, rutas).

**Audiencia:** Desarrolladores backend y frontend.

---

## API v2 únicamente

- **API v1**: Eliminada (2025-01-27). No existe en el código.
- Todas las rutas documentadas son **v2** (`/api/v2/*`).

---

## Contenido principal

- **[api-references/](api-references/)** — Índice por módulo (autenticación, catálogos, pedidos, inventario, producción, recepciones-despachos, utilidades, estadísticas, productos, sistema).
- **[referencia/97-rutas-completas.md](./referencia/97-rutas-completas.md)** — Lista completa de rutas v2.
- **[referencia/96-recursos-api.md](./referencia/96-recursos-api.md)** — Recursos API (transformación de modelos a JSON).
- **[referencia/96-restricciones-entidades.md](./referencia/96-restricciones-entidades.md)** — Restricciones entre entidades.

Autenticación:

- [fundamentos/02-autenticacion-autorizacion.md](./fundamentos/02-autenticacion-autorizacion.md) — Sanctum, magic link, OTP, roles.

---

## Véase también

- [03-architecture-overview.md](./03-architecture-overview.md) — Arquitectura.
- [troubleshooting/common-errors.md](./troubleshooting/common-errors.md) — Errores comunes de API.
