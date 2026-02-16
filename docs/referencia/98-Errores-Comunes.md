# Referencia Técnica - Errores Comunes y Problemas Conocidos

## ⚠️ Estado de la API
- **v1**: Eliminada (2025-01-27) - Ya no existe en el código base
- **v2**: Versión activa (este documento) - Única versión disponible

---

## 📋 Visión General

Este documento compila todos los errores, problemas, inconsistencias y código incompleto identificados en el código del sistema v2. Los problemas están organizados por categorías para facilitar la búsqueda y priorización de correcciones.

**Nota Importante**: Este documento **NO propone soluciones**, solo documenta los problemas tal como están en el código actual, según las instrucciones recibidas.

---

## 🗂️ Organización por Categorías

1. [Configuración y Hardcoding](#configuración-y-hardcoding)
2. [Validaciones Faltantes](#validaciones-faltantes)
3. [Manejo de Errores](#manejo-de-errores)
4. [Performance y Optimización](#performance-y-optimización)
5. [Código Incompleto y Dead Code](#código-incompleto-y-dead-code)
6. [Inconsistencias y Ambiguidades](#inconsistencias-y-ambiguidades)
7. [Seguridad](#seguridad)
8. [Relaciones y Modelos](#relaciones-y-modelos)
9. [Base de Datos y Migraciones](#base-de-datos-y-migraciones)
10. [Logging y Auditoría](#logging-y-auditoría)

---

## ⚙️ Configuración y Hardcoding

### 1. Rutas Hardcoded

**Problema**: Rutas de archivos y servicios hardcodeadas en múltiples lugares.

**Ubicaciones**:
- `app/Http/Controllers/v2/PDFController.php:30` - Ruta de Chromium: `/usr/bin/google-chrome`
- `app/Services/OrderPDFService.php:50` - Ruta de Chromium: `/usr/bin/google-chrome`
- `app/Http/Controllers/v2/GoogleDocumentAIController.php:26` - Credenciales: `storage/app/google-credentials.json`
- `app/Http/Controllers/v2/AzureDocumentAIController.php:20` - Ruta de archivos temporales: `storage/app/pdfs/`

**Impacto**: Dificulta el despliegue en diferentes entornos y configuración por tenant.

**Referencias**: [Utilidades - Generación PDF](../utilidades/90-Generacion-PDF.md), [Utilidades - Extracción AI](../utilidades/92-Extraccion-Documentos-AI.md)

---

### 2. Configuración de Azure Document AI

**Problema**: Uso de `env()` directamente en lugar de `config()`.

**Ubicación**: `app/Http/Controllers/v2/AzureDocumentAIController.php:27-28`

**Impacto**: No funciona con cache de configuración en producción.

**Referencias**: [Utilidades - Extracción AI](../utilidades/92-Extraccion-Documentos-AI.md)

---

### 3. Configuración Hardcoded en Google Document AI

**Problema**: Project ID, Location y Processor ID hardcodeados.

**Ubicación**: `app/Http/Controllers/v2/GoogleDocumentAIController.php:27-29`

**Valores Hardcoded**:
- Project ID: `223147234811`
- Location: `eu`
- Processor ID: `3c49f1160f79a1af`

**Impacto**: No flexible para diferentes entornos o tenants.

**Referencias**: [Utilidades - Extracción AI](../utilidades/92-Extraccion-Documentos-AI.md)

---

### 4. Límites de Memoria y Tiempo Hardcoded

**Problema**: Límites de memoria y tiempo de ejecución hardcoded en múltiples métodos.

**Ubicaciones**:
- `app/Http/Controllers/v2/ExcelController.php:39, 46, 69, 280` - Límites: `1024M`, `2048M`, `300s`, `600s`
- Varios métodos de exportación con límites diferentes

**Impacto**: No permite configuración centralizada o ajuste según entorno.

**Referencias**: [Utilidades - Exportación Excel](../utilidades/91-Exportacion-Excel.md)

---

### 5. Márgenes de PDF Hardcoded

**Problema**: Márgenes de PDF fijos en el código.

**Ubicaciones**:
- `app/Http/Controllers/v2/PDFController.php:33-36`
- `app/Services/OrderPDFService.php:54-57`

**Valores**: top=10mm, right=30mm, bottom=10mm, left=10mm

**Impacto**: No permite personalización por tipo de documento.

**Referencias**: [Utilidades - Generación PDF](../utilidades/90-Generacion-PDF.md)

---

### 6. Caché de PDF con Tiempo Fijo

**Problema**: Tiempo de caché hardcoded a 30 segundos.

**Ubicación**: `app/Services/OrderPDFService.php:36`

**Impacto**: No permite configurar el tiempo de caché según necesidades.

**Referencias**: [Utilidades - Generación PDF](../utilidades/90-Generacion-PDF.md)

---

### 7. Umbrales de Conciliación Hardcoded

**Problema**: Umbrales de conciliación hardcodeados.

**Ubicación**: `app/Models/Production.php:440-445`

**Valores**: 5% para red, 1% para yellow

**Impacto**: No son configurables por tenant o usuario.

**Referencias**: [Producción - General](../produccion/10-Produccion-General.md)

---

### 8. Versión de API de Azure Hardcoded

**Problema**: Versión de API hardcoded.

**Ubicación**: `app/Http/Controllers/v2/AzureDocumentAIController.php:29`

**Valor**: `2024-02-29-preview`

**Impacto**: Puede quedar obsoleta y requiere actualización manual.

**Referencias**: [Utilidades - Extracción AI](../utilidades/92-Extraccion-Documentos-AI.md)

---

## ✅ Validaciones Faltantes

### 9. Falta de Validación de Tenant en Controladores

**Problema**: Algunos controladores no validan explícitamente el tenant aunque usan middleware.

**Referencias**: [Fundamentos - Arquitectura Multi-Tenant](../fundamentos/01-Arquitectura-Multi-Tenant.md)

---

### 10. Falta de Validación de Estado en Producción

**Problema**: No valida si el lote está cerrado antes de crear procesos.

**Ubicación**: `app/Http/Controllers/v2/ProductionRecordController.php:61-81`

**Impacto**: Pueden crearse procesos en lotes cerrados.

**Referencias**: [Producción - General](../produccion/10-Produccion-General.md)

---

### 11. Falta de Validación de Integridad al Eliminar

**Problema**: No valida si el proceso tiene inputs/outputs antes de eliminar.

**Ubicación**: `app/Http/Controllers/v2/ProductionRecordController.php:133-141`

**Impacto**: Puede dejar datos huérfanos o inconsistencia en cálculos.

**Referencias**: [Producción - General](../produccion/10-Produccion-General.md)

---

### 12. Falta de Validación de Filtros en Exportaciones

**Problema**: Los filtros se aplican directamente sin validación.

**Ubicación**: Múltiples métodos en `app/Http/Controllers/v2/ExcelController.php`

**Impacto**: Filtros mal formados pueden causar errores SQL.

**Referencias**: [Utilidades - Exportación Excel](../utilidades/91-Exportacion-Excel.md)

---

### 13. Falta de Validación de Orden Existente en PDF

**Problema**: Usa `findOrFail()` pero no valida permisos del usuario sobre el pedido.

**Ubicación**: `app/Http/Controllers/v2/PDFController.php` (múltiples métodos)

**Referencias**: [Utilidades - Generación PDF](../utilidades/90-Generacion-PDF.md)

---

### 14. Falta de Validación de Credenciales

**Problema**: No valida si las credenciales/configuración existen antes de usar.

**Ubicación**: `app/Http/Controllers/v2/AzureDocumentAIController.php`, `GoogleDocumentAIController.php`

**Impacto**: Errores crípticos si falta configuración.

**Referencias**: [Utilidades - Extracción AI](../utilidades/92-Extraccion-Documentos-AI.md)

---

### 15. Falta de Validación de Existencia de Datos

**Problema**: Algunos métodos no validan si existen datos antes de exportar.

**Ubicación**: Métodos de exportación en `app/Http/Controllers/v2/ExcelController.php`

**Impacto**: Puede generar archivos Excel vacíos sin aviso.

**Referencias**: [Utilidades - Exportación Excel](../utilidades/91-Exportacion-Excel.md)

---

### 16. Falta de Validación de Usuario Activo en Login

**Problema**: No verifica si el usuario está activo antes de autenticar.

**Ubicación**: `app/Http/Controllers/v2/AuthController.php:24-31`

**Impacto**: Usuarios desactivados pueden autenticarse.

**Referencias**: [Fundamentos - Autenticación](../fundamentos/02-Autenticacion-Autorizacion.md)

---

### 17. Falta de Validación de orderId en Incidentes

**Problema**: No valida `orderId` en métodos `show` y `destroy`.

**Ubicación**: `app/Http/Controllers/v2/IncidentController.php`

**Referencias**: [Pedidos - Incidentes](../pedidos/23-Pedidos-Incidentes.md)

---

## 🚨 Manejo de Errores

### 18. Manejo de Errores Inconsistente

**Problema**: Algunos métodos tienen try-catch, otros no.

**Ubicaciones**:
- `app/Http/Controllers/v2/ExcelController.php` - Inconsistente entre métodos
- `app/Http/Controllers/v2/AzureDocumentAIController.php:76-78` - Errores genéricos

**Impacto**: Errores no manejados pueden exponer información sensible o ser difíciles de debuggear.

**Referencias**: [Utilidades - Exportación Excel](../utilidades/91-Exportacion-Excel.md), [Utilidades - Extracción AI](../utilidades/92-Extraccion-Documentos-AI.md)

---

### 19. Mensajes de Error Genéricos

**Problema**: Mensajes de error poco descriptivos.

**Ubicaciones**:
- `app/Http/Controllers/v2/AzureDocumentAIController.php:76-78`
- `app/Http/Controllers/v2/AzureDocumentAIController.php:67` - "Error en análisis del documento"

**Impacto**: Dificulta debugging.

**Referencias**: [Utilidades - Extracción AI](../utilidades/92-Extraccion-Documentos-AI.md)

---

### 20. Falta de Manejo de Errores en PDFController

**Problema**: No hay try-catch explícito en los métodos públicos.

**Ubicación**: `app/Http/Controllers/v2/PDFController.php`

**Impacto**: Si Chromium falla o la vista no existe, el error será genérico.

**Referencias**: [Utilidades - Generación PDF](../utilidades/90-Generacion-PDF.md)

---

### 21. Falta de Validación de Vista Blade

**Problema**: No valida que la vista Blade exista antes de renderizarla.

**Ubicación**: `app/Http/Controllers/v2/PDFController.php`

**Impacto**: Si la vista no existe, el error será genérico de Blade.

**Referencias**: [Utilidades - Generación PDF](../utilidades/90-Generacion-PDF.md)

---

## ⚡ Performance y Optimización

### 22. Queries N+1 en Attributes Calculados

**Problema**: Attributes calculados hacen queries en cada acceso.

**Ubicaciones**:
- `app/Models/Order.php:229-241` - `getTotalNetWeightAttribute()`, `getTotalBoxesAttribute()`
- Múltiples modelos con attributes que hacen queries

**Impacto**: Si se accede múltiples veces, se ejecutan múltiples queries.

**Referencias**: [Pedidos - General](../pedidos/20-Pedidos-General.md), [Producción - Lotes](../produccion/11-Produccion-Lotes.md)

---

### 23. Nested Loops en Métodos

**Problema**: Métodos con loops anidados que pueden ser ineficientes.

**Ubicaciones**:
- `app/Models/Order.php:90-123, 356-413`
- `app/Models/Production.php` - Métodos de cálculo

**Impacto**: Complejidad O(n²) o mayor en grandes volúmenes.

**Referencias**: [Pedidos - General](../pedidos/20-Pedidos-General.md)

---

### 24. Falta de Eager Loading

**Problema**: Recursos que usan `toArrayAssoc()` pueden causar N+1 si relaciones no están cargadas.

**Ubicaciones**: Múltiples Resources en `app/Http/Resources/v2/`

**Impacto**: Múltiples queries adicionales.

**Referencias**: [Referencia - Recursos API](./96-Recursos-API.md)

---

### 25. Falta de Paginación en Exportaciones Grandes

**Problema**: Las exportaciones cargan todos los datos en memoria.

**Ubicación**: Clases Export que usan `FromCollection` en `app/Exports/v2/`

**Impacto**: Exportaciones muy grandes pueden fallar.

**Referencias**: [Utilidades - Exportación Excel](../utilidades/91-Exportacion-Excel.md)

---

### 26. Falta de Límite de Registros por Defecto

**Problema**: Algunas exportaciones pueden exportar millones de registros.

**Ubicación**: Métodos de exportación sin parámetro `limit`

**Impacto**: Puede causar timeouts o problemas de memoria.

**Referencias**: [Utilidades - Exportación Excel](../utilidades/91-Exportacion-Excel.md)

---

### 27. Polling sin Timeout

**Problema**: Bucle de polling sin límite de tiempo máximo.

**Ubicación**: `app/Http/Controllers/v2/AzureDocumentAIController.php:51-64`

**Impacto**: Puede quedarse en loop infinito si Azure falla.

**Referencias**: [Utilidades - Extracción AI](../utilidades/92-Extraccion-Documentos-AI.md)

---

### 28. Límites de Tiempo y Memoria Altos

**Problema**: Límites muy altos para algunas operaciones.

**Ubicaciones**:
- `app/Http/Controllers/v2/OrderStatisticsController.php:51-52` - `memory_limit: 512M`, `max_execution_time: 600`
- Exportaciones con `2048M` y `600s`

**Impacto**: Puede afectar otros procesos del servidor.

**Referencias**: [Pedidos - Estadísticas](../pedidos/24-Pedidos-Estadisticas.md)

---

## 🗑️ Código Incompleto y Dead Code

### 29. Métodos Vacíos

**Problema**: Métodos definidos pero sin implementación.

**Ubicaciones**:
- `app/Models/Order.php:73-75` - `getSummaryAttribute()` vacío
- `app/Http/Controllers/v2/RoleController.php:14-16` - `index()` incorrecto

**Impacto**: Puede causar errores si se accede o confundir a desarrolladores.

**Referencias**: [Pedidos - General](../pedidos/20-Pedidos-General.md), [Sistema - Roles](../sistema/81-Roles.md)

---

### 30. Código Comentado

**Problema**: Código comentado extensamente que confunde.

**Ubicaciones**:
- `app/Models/Order.php:246-354` - Métodos comentados
- `app/Http/Middleware/LogActivity.php` - Código comentado referenciando campos eliminados
- `app/Http/Controllers/v2/PDFController.php:59-61` - Bucle comentado/vacío

**Impacto**: Código muerto que confunde y dificulta mantenimiento.

**Referencias**: [Pedidos - General](../pedidos/20-Pedidos-General.md), [Sistema - Logs](../sistema/83-Logs-Actividad.md), [Utilidades - Generación PDF](../utilidades/90-Generacion-PDF.md)

---

### 31. Métodos con Comentarios TODO

**Problema**: Comentarios indicando trabajo pendiente.

**Ubicaciones**: Múltiples archivos con comentarios `TODO`, `FIXME`, etc.

**Impacto**: Funcionalidades incompletas.

**Referencias**: Varios módulos

---

### 32. Controladores con Métodos Vacíos

**Problema**: Métodos de controladores vacíos o incorrectamente implementados.

**Ubicaciones**:
- `app/Http/Controllers/v2/RoleController.php` - `index()`, `store()`, `show()`, `update()`, `destroy()` vacíos o incorrectos
- `app/Http/Controllers/v2/OrderPlannedProductDetailController.php` - Falta `index()` y `show()`

**Impacto**: Funcionalidades no disponibles o incorrectas.

**Referencias**: [Sistema - Roles](../sistema/81-Roles.md), [Pedidos - Detalles Planificados](../pedidos/21-Pedidos-Detalles-Planificados.md)

---

## 🔄 Inconsistencias y Ambiguidades

### 33. Inconsistencia en Lógica de isActive()

**Problema**: Comentario contradice la implementación.

**Ubicación**: `app/Models/Order.php:82-86`

**Detalle**: 
- Comentario dice: "Order is active when status is 'finished' and loadDate is < now"
- Lógica real: `status == 'pending' || load_date >= now()`

**Referencias**: [Pedidos - General](../pedidos/20-Pedidos-General.md)

---

### 34. Inconsistencia en Campos de Email

**Problema**: Campo `emails` no presente en migración base pero sí en `fillable`.

**Ubicación**: 
- Modelo: `app/Models/Salesperson.php` - `fillable` incluye `emails`
- Migración: `database/migrations/companies/2023_12_19_152319_create_salespeople_table.php` - No tiene columna `emails`

**Referencias**: [Catálogos - Vendedores](../catalogos/47-Vendedores.md)

---

### 35. Inconsistencia en species_id de Process

**Problema**: Modelo sugiere `species_id` pero migración base no lo tiene.

**Ubicación**:
- Modelo: `app/Models/Process.php` - `fillable` incluye `species_id`
- Migración base: No incluye `species_id`
- Migración posterior: `database/migrations/companies/2024_05_27_143913_add_species_id_to_processes_table.php` lo agrega

**Referencias**: [Catálogos - Procesos](../catalogos/53-Procesos.md)

---

### 36. Duplicación de Lógica de Filtrado

**Problema**: Lógica de filtrado duplicada en múltiples métodos.

**Ubicación**: `app/Http/Controllers/v2/ExcelController.php:66-158, 160-249, 277-375`

**Impacto**: Cambios requieren actualizar múltiples lugares.

**Referencias**: [Utilidades - Exportación Excel](../utilidades/91-Exportacion-Excel.md)

---

### 37. Argumentos de Chromium Duplicados

**Problema**: Lista de argumentos duplicada en dos archivos.

**Ubicaciones**:
- `app/Http/Controllers/v2/PDFController.php:39-61`
- `app/Services/OrderPDFService.php:53-72`

**Impacto**: Cambios requieren actualizar dos lugares.

**Referencias**: [Utilidades - Generación PDF](../utilidades/90-Generacion-PDF.md)

---

### 38. Formato de Fecha Inconsistente

**Problema**: Algunas exportaciones usan diferentes formatos de fecha.

**Ubicación**: Múltiples clases Export

**Impacto**: Inconsistencia en formato de fechas entre exportaciones.

**Referencias**: [Utilidades - Exportación Excel](../utilidades/91-Exportacion-Excel.md)

---

### 39. Bucle For Sin Ejecución

**Problema**: Bucle `foreach` existe pero está comentado/vacío.

**Ubicación**: `app/Http/Controllers/v2/PDFController.php:59-61`

**Referencias**: [Utilidades - Generación PDF](../utilidades/90-Generacion-PDF.md)

---

## 🔒 Seguridad

### 40. Falta de Rate Limiting

**Problema**: No hay límite de requests por usuario/tiempo en varios endpoints.

**Ubicaciones**:
- `app/Http/Controllers/v2/AuthController.php:15-51` - Login sin rate limiting
- `app/Http/Controllers/v2/AzureDocumentAIController.php` - Sin rate limiting

**Impacto**: Vulnerable a abuso y ataques de fuerza bruta.

**Referencias**: [Fundamentos - Autenticación](../fundamentos/02-Autenticacion-Autorizacion.md), [Utilidades - Extracción AI](../utilidades/92-Extraccion-Documentos-AI.md)

---

### 41. Logout Elimina Todos los Tokens

**Problema**: `logout()` elimina TODOS los tokens del usuario.

**Ubicación**: `app/Http/Controllers/v2/AuthController.php:54-59`

**Impacto**: Cierra todas las sesiones cuando solo se quiere cerrar una.

**Referencias**: [Fundamentos - Autenticación](../fundamentos/02-Autenticacion-Autorizacion.md)

---

### 42. Información del Usuario Expuesta

**Problema**: `me()` retorna información sensible sin filtrado.

**Ubicación**: `app/Http/Controllers/v2/AuthController.php:44-48`

**Referencias**: [Fundamentos - Autenticación](../fundamentos/02-Autenticacion-Autorizacion.md)

---

### 43. Falta de Validación de Permisos Específicos

**Problema**: Rutas protegidas por roles generales pero no hay validación granular.

**Ubicación**: Varias rutas en `routes/api.php`

**Referencias**: Varios módulos

---

## 🔗 Relaciones y Modelos

### 44. Relación 1:1 con ID Compartido

**Problema**: `Product` y `Article` comparten el mismo `id`, relación especial no obvia.

**Ubicación**: `app/Models/Product.php`, `app/Models/Article.php`

**Impacto**: Puede confundir a desarrolladores.

**Referencias**: [Catálogos - Productos](../catalogos/40-Productos.md)

---

### 45. Falta de Relación Inversa Eficiente

**Problema**: `Box` tiene método `isAvailable` pero no usa relación eficientemente.

**Ubicación**: `app/Models/Box.php:41-90`

**Impacto**: Puede haber N+1 queries si no se carga eager loading.

**Referencias**: [Producción - General](../produccion/10-Produccion-General.md)

---

### 46. Falta de Validación de Consistencia de Lotes

**Problema**: `ProductionOutput` tiene `lot_id` como string pero no valida consistencia.

**Ubicación**: `app/Models/ProductionOutput.php:17`

**Impacto**: No hay validación de consistencia entre lotes.

**Referencias**: [Producción - General](../produccion/10-Produccion-General.md)

---

## 🗄️ Base de Datos y Migraciones

### 47. Campos Faltantes en Migraciones

**Problema**: Modelos referencian campos que no existen en migraciones base.

**Ubicaciones**:
- `app/Models/ActivityLog.php` - Campo `token_id` en `fillable` pero no en migración
- `app/Models/Salesperson.php` - Campo `emails` en `fillable` pero no en migración base
- Tabla `personal_access_tokens` - Faltan campos: `ip_address`, `platform`, `browser`

**Referencias**: [Sistema - Logs](../sistema/83-Logs-Actividad.md), [Sistema - Sesiones](../sistema/82-Sesiones.md), [Catálogos - Vendedores](../catalogos/47-Vendedores.md)

---

### 48. Campos Eliminados pero Referenciados

**Problema**: Código comentado referencia campos eliminados en migración.

**Ubicación**: 
- Migración: `database/migrations/companies/2025_01_12_211945_update_activity_logs_table.php:24` - Elimina `action` y `details`
- Middleware: `app/Http/Middleware/LogActivity.php` - Código comentado referencia estos campos

**Referencias**: [Sistema - Logs](../sistema/83-Logs-Actividad.md)

---

### 49. Falta de Índices en Tablas

**Problema**: Algunas tablas pueden no tener índices apropiados para queries frecuentes.

**Referencias**: Varios módulos

---

## 📝 Logging y Auditoría

### 50. Falta de Logging

**Problema**: No hay logging de operaciones importantes.

**Ubicaciones**:
- Generación de PDFs
- Exportaciones Excel
- Procesamiento de documentos con IA
- Operaciones críticas de negocio

**Impacto**: Dificulta debugging, auditoría y monitoreo.

**Referencias**: [Utilidades - Generación PDF](../utilidades/90-Generacion-PDF.md), [Utilidades - Exportación Excel](../utilidades/91-Exportacion-Excel.md), [Utilidades - Extracción AI](../utilidades/92-Extraccion-Documentos-AI.md)

---

### 51. Falta de Limpieza de Archivos Temporales

**Problema**: Archivos temporales no se eliminan automáticamente.

**Ubicaciones**:
- `app/Http/Controllers/v2/AzureDocumentAIController.php:20` - PDFs temporales
- `app/Services/OrderPDFService.php:27` - PDFs generados

**Impacto**: Acumulación de archivos en el storage.

**Referencias**: [Utilidades - Extracción AI](../utilidades/92-Extraccion-Documentos-AI.md), [Utilidades - Generación PDF](../utilidades/90-Generacion-PDF.md)

---

### 52. Falta de Auditoría de Exportaciones

**Problema**: No se registra quién, qué y cuándo se exporta.

**Ubicación**: Métodos de exportación

**Impacto**: Dificulta auditoría.

**Referencias**: [Utilidades - Exportación Excel](../utilidades/91-Exportacion-Excel.md)

---

## 📊 Métodos y Lógica de Negocio

### 53. Métodos de Cálculo Duplicados

**Problema**: Lógica de cálculo duplicada entre modelos.

**Ubicación**: `app/Models/Production.php` vs `app/Models/ProductionRecord.php`

**Referencias**: [Producción - General](../produccion/10-Produccion-General.md)

---

### 54. Documentación PHP Doc Incorrecta

**Problema**: Documentación no coincide con implementación.

**Ubicación**: `app/Http/Controllers/v2/PDFController.php:16-25`

**Detalle**: Comentario menciona parámetros que no existen.

**Referencias**: [Utilidades - Generación PDF](../utilidades/90-Generacion-PDF.md)

---

### 55. Actualización Directa de Estado

**Problema**: Estado del pedido se actualiza directamente en controlador de incidentes.

**Ubicación**: `app/Http/Controllers/v2/IncidentController.php`

**Impacto**: Lógica de negocio fuera del modelo.

**Referencias**: [Pedidos - Incidentes](../pedidos/23-Pedidos-Incidentes.md)

---

### 56. Controlador Usa Modelo Incorrecto

**Problema**: `RoleController::index()` consulta modelo `User` en lugar de `Role`.

**Ubicación**: `app/Http/Controllers/v2/RoleController.php:14-16`

**Referencias**: [Sistema - Roles](../sistema/81-Roles.md)

---

## 🎨 Estilos y Consistencia

### 57. Estilos No Consistidos en Exportaciones

**Problema**: Solo algunas clases Export implementan `WithStyles`.

**Ubicación**: Clases Export en `app/Exports/v2/`

**Impacto**: Inconsistencia visual entre exportaciones.

**Referencias**: [Utilidades - Exportación Excel](../utilidades/91-Exportacion-Excel.md)

---

### 58. Método processPdfText Muy Largo

**Problema**: Método con más de 100 líneas.

**Ubicación**: `app/Http/Controllers/v2/PdfExtractionController.php:37-144`

**Impacto**: Difícil de mantener y testear.

**Referencias**: [Utilidades - Extracción AI](../utilidades/92-Extraccion-Documentos-AI.md)

---

## 🔄 Control de Transacciones

### 59. Falta de Transacciones en Operaciones Críticas

**Problema**: Algunas operaciones críticas no usan transacciones de base de datos.

**Ubicaciones**: Varios controladores con operaciones múltiples

**Impacto**: Posible inconsistencia si falla a mitad de proceso.

**Referencias**: [Producción - General](../produccion/10-Produccion-General.md)

---

## 📋 Resumen por Prioridad

### 🔴 Crítico (Seguridad y Datos)

- Rate limiting faltante
- Validaciones de seguridad faltantes
- Manejo de errores que expone información
- Falta de validación de integridad referencial

### 🟠 Alto (Funcionalidad y Performance)

- Código incompleto
- N+1 queries
- Falta de paginación
- Validaciones de negocio faltantes

### 🟡 Medio (Mantenibilidad)

- Código duplicado
- Hardcoding
- Inconsistencias
- Dead code

### 🟢 Bajo (Mejoras)

- Logging
- Documentación
- Estilos
- Organización de código

---

## 📚 Referencias

Para información detallada de cada problema, consultar las secciones "Observaciones Críticas y Mejoras Recomendadas" en:

- [Fundamentos](../fundamentos/)
- [Producción](../produccion/)
- [Pedidos](../pedidos/)
- [Inventario](../inventario/)
- [Catálogos](../catalogos/)
- [Recepciones y Despachos](../recepciones-despachos/)
- [Sistema](../sistema/)
- [Utilidades](../utilidades/)

