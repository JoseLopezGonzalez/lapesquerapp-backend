# CRM Comercial API

## Documento canónico para frontend

La referencia única que debe usar frontend para integrar este módulo es:

- [frontend-integration.md](./frontend-integration.md)

Ese documento ya reúne en un solo lugar:

- endpoints
- payloads
- respuestas
- estados y transiciones
- permisos
- flujos
- relación con `customers` y `orders`
- notas específicas para el handoff de `/comercial/pedidos`

## Documentos de apoyo

Los siguientes archivos se mantienen como desglose por recurso o soporte interno, pero no son la referencia principal para el equipo frontend:

- [prospects.md](./prospects.md)
- [prospect-to-customer.md](./prospect-to-customer.md)
- [commercial-interactions.md](./commercial-interactions.md)
- [offers.md](./offers.md)
- [dashboard.md](./dashboard.md)
- [flows.md](./flows.md)

## Convenciones generales

- Base path: `/api/v2`
- Todas las rutas requieren `X-Tenant` y autenticación Sanctum salvo las rutas públicas generales del sistema.
- Respuesta estándar en escrituras:
  - `message`
  - `data`
  - `warnings` cuando hay duplicados o avisos no bloqueantes
- El rol `comercial` trabaja solo con sus propios registros CRM.
- `administrador`, `tecnico` y `direccion` pueden ver todos los registros CRM.
