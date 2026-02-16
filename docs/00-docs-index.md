# Índice de Documentación Técnica — API v2

**Objetivo:** Punto de entrada a la documentación del backend PesquerApp (Laravel, API v2, multi-tenant). Índice por carpetas y dominio funcional.

**Alcance:** Desarrolladores backend, frontend, DevOps. Cubre todos los módulos del ERP pesquero.

---

## Advertencia importante

Esta documentación cubre **EXCLUSIVAMENTE la API v2**, que es la versión activa y actual del sistema.

- **API v1**: Ha sido **ELIMINADA** (2025-01-27). Ya no existe en el código base.
- **API v2**: Es la única versión activa. Toda la documentación hace referencia a esta versión.

---

## Índice alternativo

**Estructura estándar 00–15:** [00-overview.md](./00-overview.md) — setup, env, multi-tenant, deployment, troubleshooting.

---

## Estructura de la documentación

### Fundamentos ([./fundamentos/](./fundamentos/))

Documentación esencial para entender la arquitectura:
- [00-introduccion.md](./fundamentos/00-introduccion.md): Visión general, arquitectura, principios
- [01-Arquitectura-Multi-Tenant.md](./fundamentos/01-Arquitectura-Multi-Tenant.md): Multi-tenant, middleware, conexiones BD
- [02-autenticacion-autorizacion.md](./fundamentos/02-autenticacion-autorizacion.md): Sanctum, roles, permisos, sesiones
- [03-configuracion-entorno.md](./fundamentos/03-configuracion-entorno.md): Variables de entorno, configuración

> **Empieza aquí si eres nuevo en el proyecto**

### Instrucciones ([./instrucciones/](./instrucciones/))

Despliegue y entorno de desarrollo:
- [deploy-desarrollo-guiado.md](./instrucciones/deploy-desarrollo-guiado.md): Guía paso a paso (primera vez)
- [actualizacion-seeders-migraciones.md](./instrucciones/actualizacion-seeders-migraciones.md): Seeders y migraciones
- [guia-completa-entorno-sail-windows.md](./instrucciones/guia-completa-entorno-sail-windows.md): Sail + Windows/WSL
- [instalar-docker-wsl.md](./instrucciones/instalar-docker-wsl.md): Docker en WSL
- [implementation-plan-docker-sail.md](./instrucciones/implementation-plan-docker-sail.md): Plan Sail
- [execution-checklist.md](./instrucciones/execution-checklist.md): Checklist de ejecución
- [final-validation-report.md](./instrucciones/final-validation-report.md): Validación final

### Frontend ([./frontend/](./frontend/))

Guías para integración: auth, roles, configuración. Guia-Auth-Magic-Link-OTP, Guia-Cambios-Roles-API-Paso-2, SETTINGS-EMAIL-*.

### API References ([./api-references/](./api-references/))

Referencia por módulo. Ver [readme](./api-references/readme.md).

### Producción ([./produccion/](./produccion/))

Módulo de producción pesquera (el más complejo):
- [10-produccion-general.md](./produccion/10-produccion-general.md), [11-produccion-lotes.md](./produccion/11-produccion-lotes.md), [12-produccion-procesos.md](./produccion/12-produccion-procesos.md)
- [13-produccion-entradas.md](./produccion/13-produccion-entradas.md), [14-produccion-salidas.md](./produccion/14-produccion-salidas.md), [15-produccion-consumos-outputs-padre.md](./produccion/15-produccion-consumos-outputs-padre.md)
- Subcarpetas: [frontend/](./produccion/frontend/), [analisis/](./produccion/analisis/), [cambios/](./produccion/cambios/)

### Pedidos ([./pedidos/](./pedidos/))

- [20-Pedidos-General.md](./pedidos/20-Pedidos-General.md), [21-Pedidos-Detalles-Planificados.md](./pedidos/21-Pedidos-Detalles-Planificados.md)
- [22-Pedidos-Documentos.md](./pedidos/22-Pedidos-Documentos.md), [23-Pedidos-Incidentes.md](./pedidos/23-Pedidos-Incidentes.md), [24-Pedidos-Estadisticas.md](./pedidos/24-Pedidos-Estadisticas.md)

### Inventario ([./inventario/](./inventario/))

- [30-almacenes.md](./inventario/30-almacenes.md), [31-palets.md](./inventario/31-palets.md), [32-cajas.md](./inventario/32-cajas.md), [33-estadisticas-stock.md](./inventario/33-estadisticas-stock.md)

### Catálogos ([./catalogos/](./catalogos/))

40-Productos, 41-Categorias-Familias, 42-Especies, 43-Zonas-Captura, 44-Clientes, 45-Proveedores, 46-Transportes, 47-Vendedores, 48-Terminos-Pago, 49-Paises, 50-Incoterms, 51-Arte-Pesquera, 52-Impuestos, 53-Procesos, 54-Productos-Variantes-GS1-Resumen.

### Recepciones y Despachos ([./recepciones-despachos/](./recepciones-despachos/))

- [60-recepciones-materia-prima.md](./recepciones-despachos/60-recepciones-materia-prima.md), [61-despachos-cebo.md](./recepciones-despachos/61-despachos-cebo.md)

### Etiquetas ([./etiquetas/](./etiquetas/))

- [70-etiquetas.md](./etiquetas/70-etiquetas.md)

### Sistema ([./sistema/](./sistema/))

- [80-usuarios.md](./sistema/80-usuarios.md), [81-roles.md](./sistema/81-roles.md), [82-sesiones.md](./sistema/82-sesiones.md)
- [83-logs-actividad.md](./sistema/83-logs-actividad.md), [84-configuracion.md](./sistema/84-configuracion.md)

### Utilidades ([./utilidades/](./utilidades/))

- [90-generacion-pdf.md](./utilidades/90-generacion-pdf.md), [91-exportacion-excel.md](./utilidades/91-exportacion-excel.md)
- [92-extraccion-documentos-ai.md](./utilidades/92-extraccion-documentos-ai.md), [93-plan-integracion-tesseract-ocr.md](./utilidades/93-plan-integracion-tesseract-ocr.md)

### Referencia ([./referencia/](./referencia/))

- [95-modelos-referencia.md](./referencia/95-modelos-referencia.md), [96-recursos-api.md](./referencia/96-recursos-api.md), [96-restricciones-entidades.md](./referencia/96-restricciones-entidades.md)
- [97-rutas-completas.md](./referencia/97-rutas-completas.md), [98-errores-comunes.md](./referencia/98-errores-comunes.md), [99-glosario.md](./referencia/99-glosario.md)
- [100-rendimiento-endpoints.md](./referencia/100-rendimiento-endpoints.md), 101/102 planes de mejora
- [analisis-api-frontend-backend.md](./referencia/analisis-api-frontend-backend.md)

### Ejemplos ([./ejemplos/](./ejemplos/))

Ejemplos JSON (process-tree, producción, palets). Ver [readme](./ejemplos/readme.md).

---

## Problemas críticos

**Resumen ejecutivo:** [problemas-criticos.md](./audits/problemas-criticos.md) — 25 problemas prioritarios.

**Detalle completo:** [referencia/98-errores-comunes.md](./referencia/98-errores-comunes.md) — 59 problemas.

---

## Cómo usar esta documentación

1. **Desarrolladores nuevos:** Fundamentos + Instrucciones (Sail).
2. **Módulo específico:** Carpeta correspondiente o API References.
3. **Frontend (Production Tree):** Producción > frontend/; auth/roles: Frontend.
4. **Problemas:** problemas-criticos.md y "Observaciones Críticas" en cada archivo.
5. **Referencia rápida:** Referencia/.
6. **Ejemplos JSON:** Ejemplos/.
7. **Agentes IA:** `.ai_standards/` en la raíz (PROTOCOLO_PARA_CHAT, QUICK_START_GUIDE).

---

## Convenciones

- Rutas: **API v2** (`/v2/*`)
- Rutas de archivos: relativas a la raíz (ej. `app/Models/Production.php`)
- "Observaciones Críticas": problemas conocidos, código incompleto
- Ejemplos: estado actual del código

---

## Relacionado

- [00-overview.md](./00-overview.md) — índice por número 00–15
- [00-overview/00-docs-map.md](./00-overview/00-docs-map.md) — mapa general
- [README del proyecto](../README.md) — instalación, Sail

---

**Última actualización:** 2026-02-16
