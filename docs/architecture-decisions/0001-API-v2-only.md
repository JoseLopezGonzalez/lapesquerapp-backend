---
title: ADR — API v2 como única versión activa
date: 2025-01-27
status: accepted
deciders: Equipo PesquerApp
---

# 1. API v2 como única versión activa

## Contexto

El backend exponía inicialmente rutas bajo `/api/v1/` y posteriormente se introdujo `/api/v2/` con mejoras de diseño (recursos, autenticación, multi-tenant). Mantener dos versiones suponía duplicación de mantenimiento, riesgo de inconsistencias y confusión en frontend y documentación.

## Decisión

- **Eliminar por completo la API v1** del código y del despliegue.
- **API v2** (`/api/v2/*`) es la **única** versión activa. Toda la documentación, frontend y clientes deben usar exclusivamente v2.
- Fecha de eliminación de v1 documentada: 2025-01-27.

## Consecuencias

### Positivas
- Un solo contrato de API que mantener y documentar.
- Código más simple (sin rutas ni controladores v1).
- Menos superficie de ataque y menos superficie de pruebas.

### Negativas / Trade-offs
- Cualquier cliente o integración que aún usara v1 deja de funcionar hasta que migre a v2.

### Neutras
- La documentación debe referenciar siempre `/api/v2/` y dejar explícito que v1 no existe.

## Alternativas consideradas

- **Mantener v1 en modo solo lectura:** Se descartó para simplificar y evitar confusión.
- **Deprecar v1 con aviso largo:** Se optó por eliminación directa al no haber consumidores externos conocidos de v1.

## Referencias

- [overview.md](../overview.md) — Sección "API v2 únicamente".
- [api-rest.md](../api-rest.md) — Referencia API v2.
- [referencia/97-Rutas-Completas.md](../referencia/97-Rutas-Completas.md) — Rutas actuales.
