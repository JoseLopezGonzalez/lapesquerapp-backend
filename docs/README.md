# Documentación Técnica del Backend - API v2

## ⚠️ ADVERTENCIA IMPORTANTE

Esta documentación cubre **EXCLUSIVAMENTE la API v2**, que es la versión activa y actual del sistema.

- **API v1**: Ha sido **ELIMINADA** (2025-01-27). Ya no existe en el código base.
- **API v2**: Es la única versión activa. Toda la documentación hace referencia a esta versión.

---

## 📚 Estructura de la Documentación

Esta documentación está organizada por carpetas según grandes apartados funcionales:

### 📁 [Fundamentos](./fundamentos/)
Documentación esencial para entender la arquitectura del sistema:
- **[00-Introduccion.md](./fundamentos/00-Introduccion.md)**: Visión general del proyecto, arquitectura y principios fundamentales
- **[01-Arquitectura-Multi-Tenant.md](./fundamentos/01-Arquitectura-Multi-Tenant.md)**: Sistema multi-tenant, middleware, conexiones de base de datos
- **[02-Autenticacion-Autorizacion.md](./fundamentos/02-Autenticacion-Autorizacion.md)**: Laravel Sanctum, roles, permisos, sesiones
- **[03-Configuracion-Entorno.md](./fundamentos/03-Configuracion-Entorno.md)**: Configuración del entorno, variables de entorno, conexiones

> **📌 Empieza aquí si eres nuevo en el proyecto**

---

### 📁 [Producción](./produccion/)
Módulo de gestión de producción pesquera (el más complejo del sistema):

**Documentación Principal:**
- **[10-Produccion-General.md](./produccion/10-Produccion-General.md)**: Visión general del módulo, conceptos y arquitectura
- **[11-Produccion-Lotes.md](./produccion/11-Produccion-Lotes.md)**: Gestión de lotes de producción (Production)
- **[12-Produccion-Procesos.md](./produccion/12-Produccion-Procesos.md)**: Procesos de producción (ProductionRecord)
- **[13-Produccion-Entradas.md](./produccion/13-Produccion-Entradas.md)**: Entradas de producción (ProductionInput)
- **[14-Produccion-Salidas.md](./produccion/14-Produccion-Salidas.md)**: Salidas de producción (ProductionOutput)
- **[15-Produccion-Consumos-Outputs-Padre.md](./produccion/15-Produccion-Consumos-Outputs-Padre.md)**: Consumos y outputs padre

**Subcarpetas Especializadas:**
- **[Frontend](./produccion/frontend/)** - Documentación del endpoint `process-tree` para frontend
- **[Análisis](./produccion/analisis/)** - Análisis, investigaciones y diseños del módulo
- **[Cambios](./produccion/cambios/)** - Migraciones, cambios y fixes realizados

> **📝 Nota**: Este módulo usa una arquitectura relacional completa (v2) con trazabilidad total a nivel de caja. Es el área más compleja del sistema.

---

### 📁 [Pedidos](./pedidos/)
Módulo de gestión de pedidos y ventas:
- **[20-Pedidos-General.md](./pedidos/20-Pedidos-General.md)**: Visión general del módulo de pedidos (Orders)
- **[21-Pedidos-Detalles-Planificados.md](./pedidos/21-Pedidos-Detalles-Planificados.md)**: Detalles planificados de productos (OrderPlannedProductDetail)
- **[22-Pedidos-Documentos.md](./pedidos/22-Pedidos-Documentos.md)**: Generación de documentos PDF y envío por email
- **[23-Pedidos-Incidentes.md](./pedidos/23-Pedidos-Incidentes.md)**: Gestión de incidentes en pedidos
- **[24-Pedidos-Estadisticas.md](./pedidos/24-Pedidos-Estadisticas.md)**: Estadísticas y reportes de pedidos

---

### 📁 [Inventario](./inventario/)
Módulo de gestión de inventario y almacenes:
- **[30-Almacenes.md](./inventario/30-Almacenes.md)**: Gestión de almacenes (Stores)
- **[31-Palets.md](./inventario/31-Palets.md)**: Gestión de palets (Pallets)
- **[32-Cajas.md](./inventario/32-Cajas.md)**: Gestión de cajas (Boxes)
- **[33-Estadisticas-Stock.md](./inventario/33-Estadisticas-Stock.md)**: Estadísticas de inventario

---

### 📁 [Catálogos](./catalogos/)
Catálogos y maestros de datos:
- **[40-Productos.md](./catalogos/40-Productos.md)**: Gestión de productos (Products)
- **[41-Categorias-Familias-Productos.md](./catalogos/41-Categorias-Familias-Productos.md)**: Categorías y familias de productos
- **[42-Especies.md](./catalogos/42-Especies.md)**: Gestión de especies (Species)
- **[43-Zonas-Captura.md](./catalogos/43-Zonas-Captura.md)**: Zonas de captura (CaptureZones)
- **[44-Clientes.md](./catalogos/44-Clientes.md)**: Gestión de clientes (Customers)
- **[45-Proveedores.md](./catalogos/45-Proveedores.md)**: Gestión de proveedores (Suppliers)
- **[46-Transportes.md](./catalogos/46-Transportes.md)**: Gestión de transportes (Transports)
- **[47-Vendedores.md](./catalogos/47-Vendedores.md)**: Gestión de vendedores (Salespeople)
- **[48-Terminos-Pago.md](./catalogos/48-Terminos-Pago.md)**: Términos de pago (PaymentTerms)
- **[49-Incoterms.md](./catalogos/49-Incoterms.md)**: Incoterms
- **[50-Paises.md](./catalogos/50-Paises.md)**: Países (Countries)
- **[51-Artemania-Pesquera.md](./catalogos/51-Artemania-Pesquera.md)**: Artes de pesca (FishingGears)
- **[52-Impuestos.md](./catalogos/52-Impuestos.md)**: Impuestos (Taxes)
- **[53-Procesos.md](./catalogos/53-Procesos.md)**: Procesos (Processes)

---

### 📁 [Recepciones y Despachos](./recepciones-despachos/)
Módulo de recepciones de materia prima y despachos de cebo:
- **[60-Recepciones-Materia-Prima.md](./recepciones-despachos/60-Recepciones-Materia-Prima.md)**: Recepciones de materia prima (RawMaterialReceptions)
- **[61-Despachos-Cebo.md](./recepciones-despachos/61-Despachos-Cebo.md)**: Despachos de cebo (CeboDispatches)

---

### 📁 [Etiquetas](./etiquetas/)
Sistema de gestión de etiquetas:
- **[70-Etiquetas.md](./etiquetas/70-Etiquetas.md)**: Gestión de etiquetas (Labels)

---

### 📁 [Sistema](./sistema/)
Administración y configuración del sistema:
- **[80-Usuarios.md](./sistema/80-Usuarios.md)**: Gestión de usuarios (Users)
- **[81-Roles.md](./sistema/81-Roles.md)**: Gestión de roles (Roles)
- **[82-Sesiones.md](./sistema/82-Sesiones.md)**: Gestión de sesiones activas
- **[83-Logs-Actividad.md](./sistema/83-Logs-Actividad.md)**: Logs de actividad (ActivityLogs)
- **[84-Configuracion.md](./sistema/84-Configuracion.md)**: Configuración del sistema (Settings)

---

### 📁 [Utilidades](./utilidades/)
Servicios y utilidades transversales:
- **[90-Generacion-PDF.md](./utilidades/90-Generacion-PDF.md)**: Sistema de generación de documentos PDF
- **[91-Exportacion-Excel.md](./utilidades/91-Exportacion-Excel.md)**: Sistema de exportación a Excel
- **[92-Extraccion-Documentos-AI.md](./utilidades/92-Extraccion-Documentos-AI.md)**: Extracción de datos con IA (Azure Document AI)

---

### 📁 [Referencia](./referencia/)
Documentación de referencia técnica:
- **[95-Modelos-Referencia.md](./referencia/95-Modelos-Referencia.md)**: Referencia completa de todos los modelos Eloquent
- **[96-Recursos-API.md](./referencia/96-Recursos-API.md)**: Referencia de todos los recursos de API (API Resources)
- **[97-Rutas-Completas.md](./referencia/97-Rutas-Completas.md)**: Lista completa de todas las rutas v2
- **[98-Errores-Comunes.md](./referencia/98-Errores-Comunes.md)**: Errores comunes y soluciones (59 problemas documentados)
- **[99-Glosario.md](./referencia/99-Glosario.md)**: Glosario de términos técnicos y de negocio

---

### 📁 [Ejemplos](./ejemplos/)
Ejemplos de respuestas JSON y documentación de ejemplos para diferentes endpoints:
- Ejemplos del endpoint `process-tree` (v3, v4, v5)
- Ejemplos de producción con conciliación
- Ejemplos de palets

Ver [README de ejemplos](./ejemplos/README.md) para la lista completa.

---

## ⚠️ Problemas Críticos

Para un resumen ejecutivo de los problemas más críticos del sistema:

**📄 [PROBLEMAS-CRITICOS.md](./PROBLEMAS-CRITICOS.md)**

Este documento resume los **25 problemas más críticos** organizados por prioridad:
- 🔴 **Crítico**: Seguridad y datos (funcionalidad rota, vulnerabilidades)
- 🟠 **Alto**: Funcionalidad incompleta, performance, configuración
- 🟡 **Medio**: Lógica de negocio, inconsistencias

Para ver todos los problemas detallados (59 en total), consultar [`referencia/98-Errores-Comunes.md`](./referencia/98-Errores-Comunes.md).

---

## 🔍 Cómo Usar Esta Documentación

1. **Para desarrolladores nuevos**: Comienza por los archivos en [Fundamentos](./fundamentos/)
2. **Para trabajar en un módulo específico**: Navega a la carpeta correspondiente
3. **Para frontend (Production Tree)**: Consulta [Producción > Frontend](./produccion/frontend/)
4. **Para entender problemas**: Revisa la sección "Observaciones Críticas" al final de cada archivo
5. **Para referencia rápida**: Usa los archivos en [Referencia](./referencia/)
6. **Para ejemplos de respuestas**: Consulta [Ejemplos](./ejemplos/)

---

## ⚠️ Convenciones de la Documentación

- Todas las rutas mencionadas son de la **API v2** (`/v2/*`)
- Todas las rutas de archivos son relativas a la raíz del proyecto (ej: `app/Models/Production.php`)
- Secciones de "Observaciones Críticas" documentan problemas conocidos, código incompleto, y mejoras recomendadas
- Los ejemplos de código reflejan el estado **actual** del código, no propuestas de mejora

---

**Última actualización**: Esta documentación se genera automáticamente desde el código fuente del repositorio.
