# Documentación Técnica del Backend - API v2

**Índice alternativo por número (estructura estándar):** [00-OVERVIEW.md](./00-OVERVIEW.md) — acceso por 01-SETUP-LOCAL, 02-ENVIRONMENT-VARIABLES, … 15-MULTI-TENANT-SPECIFICS, 11-DEPLOYMENT/, 12-TROUBLESHOOTING/.

---

## ⚠️ ADVERTENCIA IMPORTANTE

Esta documentación cubre **EXCLUSIVAMENTE la API v2**, que es la versión activa y actual del sistema.

- **API v1**: Ha sido **ELIMINADA** (2025-01-27). Ya no existe en el código base.
- **API v2**: Es la única versión activa. Toda la documentación hace referencia a esta versión.

---

## 📚 Estructura de la Documentación

Esta documentación está organizada por carpetas según grandes apartados funcionales:

### 📁 [Fundamentos](./20-fundamentos/)
Documentación esencial para entender la arquitectura del sistema:
- **[00-Introduccion.md](./20-fundamentos/00-Introduccion.md)**: Visión general del proyecto, arquitectura y principios fundamentales
- **[01-Arquitectura-Multi-Tenant.md](./20-fundamentos/01-Arquitectura-Multi-Tenant.md)**: Sistema multi-tenant, middleware, conexiones de base de datos
- **[02-Autenticacion-Autorizacion.md](./20-fundamentos/02-Autenticacion-Autorizacion.md)**: Laravel Sanctum, roles, permisos, sesiones
- **[03-Configuracion-Entorno.md](./20-fundamentos/03-Configuracion-Entorno.md)**: Configuración del entorno, variables de entorno, conexiones

> **📌 Empieza aquí si eres nuevo en el proyecto**

---

### 📁 [Instrucciones](./21-instrucciones/)
Despliegue y entorno de desarrollo:
- **[deploy-desarrollo.md](./21-instrucciones/deploy-desarrollo.md)**: Deploy con Docker Sail (resumen y scripts)
- **[deploy-desarrollo-guiado.md](./21-instrucciones/deploy-desarrollo-guiado.md)**: Guía paso a paso (primera vez)
- **[guia-completa-entorno-sail-windows.md](./21-instrucciones/guia-completa-entorno-sail-windows.md)**: Guía completa Sail + Windows/WSL (seeders, frontend, troubleshooting)
- **[instalar-docker-wsl.md](./21-instrucciones/instalar-docker-wsl.md)**: Instalar Docker y Docker Compose en WSL
- **[IMPLEMENTATION_PLAN_DOCKER_SAIL.md](./21-instrucciones/IMPLEMENTATION_PLAN_DOCKER_SAIL.md)**: Plan de implementación Sail
- **[EXECUTION_CHECKLIST.md](./21-instrucciones/EXECUTION_CHECKLIST.md)**: Checklist de ejecución por bloques
- **[FINAL_VALIDATION_REPORT.md](./21-instrucciones/FINAL_VALIDATION_REPORT.md)**: Informe de validación final

---

### 📁 [Frontend](./33-frontend/)
Guías para integración frontend (auth, roles, configuración):
- **Guia-Auth-Magic-Link-OTP.md**, **Guia-Cambios-Roles-API-Paso-2.md**
- **SETTINGS-EMAIL-CONFIGURATION.md**, **SETTINGS-EMAIL-RESUMEN.md**

---

### 📁 [API References](./31-api-references/)
Referencia por módulo de la API (README por área): autenticación, catalogos, pedidos, inventario, produccion, recepciones-despachos, utilidades, estadísticas, productos. Ver [README](./31-api-references/README.md).

---

### 📁 [Producción](./25-produccion/)
Módulo de gestión de producción pesquera (el más complejo del sistema):

**Documentación Principal:**
- **[10-Produccion-General.md](./25-produccion/10-Produccion-General.md)**: Visión general del módulo, conceptos y arquitectura
- **[11-Produccion-Lotes.md](./25-produccion/11-Produccion-Lotes.md)**: Gestión de lotes de producción (Production)
- **[12-Produccion-Procesos.md](./25-produccion/12-Produccion-Procesos.md)**: Procesos de producción (ProductionRecord)
- **[13-Produccion-Entradas.md](./25-produccion/13-Produccion-Entradas.md)**: Entradas de producción (ProductionInput)
- **[14-Produccion-Salidas.md](./25-produccion/14-Produccion-Salidas.md)**: Salidas de producción (ProductionOutput)
- **[15-Produccion-Consumos-Outputs-Padre.md](./25-produccion/15-Produccion-Consumos-Outputs-Padre.md)**: Consumos y outputs padre

**Subcarpetas Especializadas:**
- **[Frontend](./25-produccion/frontend/)** - Documentación del endpoint `process-tree` para frontend
- **[Análisis](./25-produccion/analisis/)** - Análisis, investigaciones y diseños del módulo
- **[Cambios](./25-produccion/cambios/)** - Migraciones, cambios y fixes realizados

> **📝 Nota**: Este módulo usa una arquitectura relacional completa (v2) con trazabilidad total a nivel de caja. Es el área más compleja del sistema.

---

### 📁 [Pedidos](./22-pedidos/)
Módulo de gestión de pedidos y ventas:
- **[20-Pedidos-General.md](./22-pedidos/20-Pedidos-General.md)**: Visión general del módulo de pedidos (Orders)
- **[21-Pedidos-Detalles-Planificados.md](./22-pedidos/21-Pedidos-Detalles-Planificados.md)**: Detalles planificados de productos (OrderPlannedProductDetail)
- **[22-Pedidos-Documentos.md](./22-pedidos/22-Pedidos-Documentos.md)**: Generación de documentos PDF y envío por email
- **[23-Pedidos-Incidentes.md](./22-pedidos/23-Pedidos-Incidentes.md)**: Gestión de incidentes en pedidos
- **[24-Pedidos-Estadisticas.md](./22-pedidos/24-Pedidos-Estadisticas.md)**: Estadísticas y reportes de pedidos

---

### 📁 [Inventario](./23-inventario/)
Módulo de gestión de inventario y almacenes:
- **[30-Almacenes.md](./23-inventario/30-Almacenes.md)**: Gestión de almacenes (Stores)
- **[31-Palets.md](./23-inventario/31-Palets.md)**: Gestión de palets (Pallets)
- **[32-Cajas.md](./23-inventario/32-Cajas.md)**: Gestión de cajas (Boxes)
- **[33-Estadisticas-Stock.md](./23-inventario/33-Estadisticas-Stock.md)**: Estadísticas de inventario

---

### 📁 [Catálogos](./24-catalogos/)
Catálogos y maestros de datos:
- **[40-Productos.md](./24-catalogos/40-Productos.md)**: Gestión de productos (Products)
- **[41-Categorias-Familias-Productos.md](./24-catalogos/41-Categorias-Familias-Productos.md)**: Categorías y familias de productos
- **[42-Especies.md](./24-catalogos/42-Especies.md)**: Gestión de especies (Species)
- **[43-Zonas-Captura.md](./24-catalogos/43-Zonas-Captura.md)**: Zonas de captura (CaptureZones)
- **[44-Clientes.md](./24-catalogos/44-Clientes.md)**: Gestión de clientes (Customers)
- **[45-Proveedores.md](./24-catalogos/45-Proveedores.md)**: Gestión de proveedores (Suppliers)
- **[46-Transportes.md](./24-catalogos/46-Transportes.md)**: Gestión de transportes (Transports)
- **[47-Vendedores.md](./24-catalogos/47-Vendedores.md)**: Gestión de vendedores (Salespeople)
- **[48-Terminos-Pago.md](./24-catalogos/48-Terminos-Pago.md)**: Términos de pago (PaymentTerms)
- **[49-Paises.md](./24-catalogos/49-Paises.md)**: Países (Countries)
- **[50-Incoterms.md](./24-catalogos/50-Incoterms.md)**: Incoterms
- **[51-Arte-Pesquera.md](./24-catalogos/51-Arte-Pesquera.md)**: Artes de pesca (FishingGears)
- **[52-Impuestos.md](./24-catalogos/52-Impuestos.md)**: Impuestos (Taxes)
- **[53-Procesos.md](./24-catalogos/53-Procesos.md)**: Procesos (Processes)
- **[54-Productos-Variantes-GS1-Resumen.md](./24-catalogos/54-Productos-Variantes-GS1-Resumen.md)**: Resumen problema/solución productos, variantes y escaneo GS1

---

### 📁 [Recepciones y Despachos](./26-recepciones-despachos/)
Módulo de recepciones de materia prima y despachos de cebo:
- **[60-Recepciones-Materia-Prima.md](./26-recepciones-despachos/60-Recepciones-Materia-Prima.md)**: Recepciones de materia prima (RawMaterialReceptions)
- **[61-Despachos-Cebo.md](./26-recepciones-despachos/61-Despachos-Cebo.md)**: Despachos de cebo (CeboDispatches)

---

### 📁 [Etiquetas](./27-etiquetas/)
Sistema de gestión de etiquetas:
- **[70-Etiquetas.md](./27-etiquetas/70-Etiquetas.md)**: Gestión de etiquetas (Labels)

---

### 📁 [Sistema](./28-sistema/)
Administración y configuración del sistema:
- **[80-Usuarios.md](./28-sistema/80-Usuarios.md)**: Gestión de usuarios (Users)
- **[81-Roles.md](./28-sistema/81-Roles.md)**: Gestión de roles (Roles)
- **[82-Sesiones.md](./28-sistema/82-Sesiones.md)**: Gestión de sesiones activas
- **[83-Logs-Actividad.md](./28-sistema/83-Logs-Actividad.md)**: Logs de actividad (ActivityLogs)
- **[84-Configuracion.md](./28-sistema/84-Configuracion.md)**: Configuración del sistema (Settings)

---

### 📁 [Utilidades](./29-utilidades/)
Servicios y utilidades transversales:
- **[90-Generacion-PDF.md](./29-utilidades/90-Generacion-PDF.md)**: Sistema de generación de documentos PDF
- **[91-Exportacion-Excel.md](./29-utilidades/91-Exportacion-Excel.md)**: Sistema de exportación a Excel
- **[92-Extraccion-Documentos-AI.md](./29-utilidades/92-Extraccion-Documentos-AI.md)**: Extracción de datos con IA (Azure Document AI)
- **[93-Plan-Integracion-Tesseract-OCR.md](./29-utilidades/93-Plan-Integracion-Tesseract-OCR.md)**: Plan de integración Tesseract OCR

---

### 📁 [Referencia](./30-referencia/)
Documentación de referencia técnica:
- **[95-Modelos-Referencia.md](./30-referencia/95-Modelos-Referencia.md)**: Referencia completa de todos los modelos Eloquent
- **[96-Recursos-API.md](./30-referencia/96-Recursos-API.md)**: Referencia de todos los recursos de API (API Resources)
- **[96-Restricciones-Entidades.md](./30-referencia/96-Restricciones-Entidades.md)**: Restricciones de entidades
- **[97-Rutas-Completas.md](./30-referencia/97-Rutas-Completas.md)**: Lista completa de todas las rutas v2
- **[98-Errores-Comunes.md](./30-referencia/98-Errores-Comunes.md)**: Errores comunes y soluciones (59 problemas documentados)
- **[99-Glosario.md](./30-referencia/99-Glosario.md)**: Glosario de términos técnicos y de negocio
- **[100-Rendimiento-Endpoints.md](./30-referencia/100-Rendimiento-Endpoints.md)**, **[101-Plan-Mejoras-GET-orders-id.md](./30-referencia/101-Plan-Mejoras-GET-orders-id.md)**, **[102-Plan-Mejoras-GET-orders-active.md](./30-referencia/102-Plan-Mejoras-GET-orders-active.md)**: Planes de mejora
- **[ANALISIS-API-FRONTEND-BACKEND.md](./30-referencia/ANALISIS-API-FRONTEND-BACKEND.md)**: Análisis API frontend-backend
- **[PLAN-ELIMINACION-ARTICLE.md](./30-referencia/PLAN-ELIMINACION-ARTICLE.md)**: Plan eliminación Article (referenciado en PROBLEMAS-CRITICOS)

---

La guía completa de entorno Sail (Windows/WSL) y el resumen productos/variantes GS1 están en [instrucciones/guia-completa-entorno-sail-windows.md](./21-instrucciones/guia-completa-entorno-sail-windows.md) y [catalogos/54-Productos-Variantes-GS1-Resumen.md](./24-catalogos/54-Productos-Variantes-GS1-Resumen.md).

---

### 📁 [Ejemplos](./32-ejemplos/)
Ejemplos de respuestas JSON y documentación de ejemplos para diferentes endpoints:
- Ejemplos del endpoint `process-tree` (v3, v4, v5)
- Ejemplos de producción con conciliación
- Ejemplos de palets

Ver [README de ejemplos](./32-ejemplos/README.md) para la lista completa.

---

## ⚠️ Problemas Críticos

Para un resumen ejecutivo de los problemas más críticos del sistema:

**📄 [PROBLEMAS-CRITICOS.md](./PROBLEMAS-CRITICOS.md)**

Este documento resume los **25 problemas más críticos** organizados por prioridad:
- 🔴 **Crítico**: Seguridad y datos (funcionalidad rota, vulnerabilidades)
- 🟠 **Alto**: Funcionalidad incompleta, performance, configuración
- 🟡 **Medio**: Lógica de negocio, inconsistencias

Para ver todos los problemas detallados (59 en total), consultar [`referencia/98-Errores-Comunes.md`](./30-referencia/98-Errores-Comunes.md).

---

## 🔍 Cómo Usar Esta Documentación

1. **Para desarrolladores nuevos**: Comienza por [Fundamentos](./20-fundamentos/) y [Instrucciones](./21-instrucciones/) (deploy con Sail).
2. **Para trabajar en un módulo específico**: Navega a la carpeta correspondiente o a [API References](./31-api-references/).
3. **Para frontend (Production Tree)**: Consulta [Producción > Frontend](./25-produccion/frontend/); para auth/roles/email, [Frontend](./33-frontend/).
4. **Para entender problemas**: Revisa [PROBLEMAS-CRITICOS.md](./PROBLEMAS-CRITICOS.md) y la sección "Observaciones Críticas" al final de cada archivo.
5. **Para referencia rápida**: Usa los archivos en [Referencia](./30-referencia/).
6. **Para ejemplos de respuestas**: Consulta [Ejemplos](./32-ejemplos/).
7. **Para agentes IA (Cursor)** — Sistema de memoria de trabajo: **`.ai_standards/`** en la raíz del proyecto (README y QUICK_START_GUIDE).

---

## ⚠️ Convenciones de la Documentación

- Todas las rutas mencionadas son de la **API v2** (`/v2/*`)
- Todas las rutas de archivos son relativas a la raíz del proyecto (ej: `app/Models/Production.php`)
- Secciones de "Observaciones Críticas" documentan problemas conocidos, código incompleto, y mejoras recomendadas
- Los ejemplos de código reflejan el estado **actual** del código, no propuestas de mejora

---

**Última actualización**: Esta documentación se genera automáticamente desde el código fuente del repositorio.
