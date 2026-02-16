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
- [00-Introduccion.md](./fundamentos/00-Introduccion.md): Visión general, arquitectura, principios
- [01-Arquitectura-Multi-Tenant.md](./fundamentos/01-Arquitectura-Multi-Tenant.md): Multi-tenant, middleware, conexiones BD
- [02-Autenticacion-Autorizacion.md](./fundamentos/02-Autenticacion-Autorizacion.md): Sanctum, roles, permisos, sesiones
- [03-Configuracion-Entorno.md](./fundamentos/03-Configuracion-Entorno.md): Variables de entorno, configuración

> **Empieza aquí si eres nuevo en el proyecto**

### Instrucciones ([./instrucciones/](./instrucciones/))

Despliegue y entorno de desarrollo:
- [deploy-desarrollo-guiado.md](./instrucciones/deploy-desarrollo-guiado.md): Guía paso a paso (primera vez)
- [actualizacion-seeders-migraciones.md](./instrucciones/actualizacion-seeders-migraciones.md): Seeders y migraciones
- [guia-completa-entorno-sail-windows.md](./instrucciones/guia-completa-entorno-sail-windows.md): Sail + Windows/WSL
- [instalar-docker-wsl.md](./instrucciones/instalar-docker-wsl.md): Docker en WSL
- [IMPLEMENTATION_PLAN_DOCKER_SAIL.md](./instrucciones/IMPLEMENTATION_PLAN_DOCKER_SAIL.md): Plan Sail
- [EXECUTION_CHECKLIST.md](./instrucciones/EXECUTION_CHECKLIST.md): Checklist de ejecución
- [FINAL_VALIDATION_REPORT.md](./instrucciones/FINAL_VALIDATION_REPORT.md): Validación final

### Frontend ([./frontend/](./frontend/))

Guías para integración: auth, roles, configuración. Guia-Auth-Magic-Link-OTP, Guia-Cambios-Roles-API-Paso-2, SETTINGS-EMAIL-*.

### API References ([./api-references/](./api-references/))

Referencia por módulo. Ver [README](./api-references/README.md).

### Producción ([./produccion/](./produccion/))

Módulo de producción pesquera (el más complejo):
- [10-Produccion-General.md](./produccion/10-Produccion-General.md), [11-Produccion-Lotes.md](./produccion/11-Produccion-Lotes.md), [12-Produccion-Procesos.md](./produccion/12-Produccion-Procesos.md)
- [13-Produccion-Entradas.md](./produccion/13-Produccion-Entradas.md), [14-Produccion-Salidas.md](./produccion/14-Produccion-Salidas.md), [15-Produccion-Consumos-Outputs-Padre.md](./produccion/15-Produccion-Consumos-Outputs-Padre.md)
- Subcarpetas: [frontend/](./produccion/frontend/), [analisis/](./produccion/analisis/), [cambios/](./produccion/cambios/)

### Pedidos ([./pedidos/](./pedidos/))

- [20-Pedidos-General.md](./pedidos/20-Pedidos-General.md), [21-Pedidos-Detalles-Planificados.md](./pedidos/21-Pedidos-Detalles-Planificados.md)
- [22-Pedidos-Documentos.md](./pedidos/22-Pedidos-Documentos.md), [23-Pedidos-Incidentes.md](./pedidos/23-Pedidos-Incidentes.md), [24-Pedidos-Estadisticas.md](./pedidos/24-Pedidos-Estadisticas.md)

### Inventario ([./inventario/](./inventario/))

- [30-Almacenes.md](./inventario/30-Almacenes.md), [31-Palets.md](./inventario/31-Palets.md), [32-Cajas.md](./inventario/32-Cajas.md), [33-Estadisticas-Stock.md](./inventario/33-Estadisticas-Stock.md)

### Catálogos ([./catalogos/](./catalogos/))

40-Productos, 41-Categorias-Familias, 42-Especies, 43-Zonas-Captura, 44-Clientes, 45-Proveedores, 46-Transportes, 47-Vendedores, 48-Terminos-Pago, 49-Paises, 50-Incoterms, 51-Arte-Pesquera, 52-Impuestos, 53-Procesos, 54-Productos-Variantes-GS1-Resumen.

### Recepciones y Despachos ([./recepciones-despachos/](./recepciones-despachos/))

- [60-Recepciones-Materia-Prima.md](./recepciones-despachos/60-Recepciones-Materia-Prima.md), [61-Despachos-Cebo.md](./recepciones-despachos/61-Despachos-Cebo.md)

### Etiquetas ([./etiquetas/](./etiquetas/))

- [70-Etiquetas.md](./etiquetas/70-Etiquetas.md)

### Sistema ([./sistema/](./sistema/))

- [80-Usuarios.md](./sistema/80-Usuarios.md), [81-Roles.md](./sistema/81-Roles.md), [82-Sesiones.md](./sistema/82-Sesiones.md)
- [83-Logs-Actividad.md](./sistema/83-Logs-Actividad.md), [84-Configuracion.md](./sistema/84-Configuracion.md)

### Utilidades ([./utilidades/](./utilidades/))

- [90-Generacion-PDF.md](./utilidades/90-Generacion-PDF.md), [91-Exportacion-Excel.md](./utilidades/91-Exportacion-Excel.md)
- [92-Extraccion-Documentos-AI.md](./utilidades/92-Extraccion-Documentos-AI.md), [93-Plan-Integracion-Tesseract-OCR.md](./utilidades/93-Plan-Integracion-Tesseract-OCR.md)

### Referencia ([./referencia/](./referencia/))

- [95-Modelos-Referencia.md](./referencia/95-Modelos-Referencia.md), [96-Recursos-API.md](./referencia/96-Recursos-API.md), [96-Restricciones-Entidades.md](./referencia/96-Restricciones-Entidades.md)
- [97-Rutas-Completas.md](./referencia/97-Rutas-Completas.md), [98-Errores-Comunes.md](./referencia/98-Errores-Comunes.md), [99-Glosario.md](./referencia/99-Glosario.md)
- [100-Rendimiento-Endpoints.md](./referencia/100-Rendimiento-Endpoints.md), 101/102 planes de mejora
- [ANALISIS-API-FRONTEND-BACKEND.md](./referencia/ANALISIS-API-FRONTEND-BACKEND.md)

### Ejemplos ([./ejemplos/](./ejemplos/))

Ejemplos JSON (process-tree, producción, palets). Ver [README](./ejemplos/README.md).

---

## Problemas críticos

**Resumen ejecutivo:** [PROBLEMAS-CRITICOS.md](./audits/PROBLEMAS-CRITICOS.md) — 25 problemas prioritarios.

**Detalle completo:** [referencia/98-Errores-Comunes.md](./referencia/98-Errores-Comunes.md) — 59 problemas.

---

## Cómo usar esta documentación

1. **Desarrolladores nuevos:** Fundamentos + Instrucciones (Sail).
2. **Módulo específico:** Carpeta correspondiente o API References.
3. **Frontend (Production Tree):** Producción > frontend/; auth/roles: Frontend.
4. **Problemas:** PROBLEMAS-CRITICOS.md y "Observaciones Críticas" en cada archivo.
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
