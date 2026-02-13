# Referencia Técnica - Glosario de Términos

## ⚠️ Estado de la API
- **v1**: Obsoleta (no documentada)
- **v2**: Versión activa (este documento)

---

## 📋 Visión General

Este documento proporciona un glosario de términos técnicos y de negocio utilizados en el sistema PesquerApp. Los términos están organizados por categorías para facilitar la búsqueda.

---

## 🗂️ Organización por Categorías

1. [Arquitectura y Tecnología](#arquitectura-y-tecnología)
2. [Términos de Negocio](#términos-de-negocio)
3. [Entidades del Sistema](#entidades-del-sistema)
4. [Estados y Flujos](#estados-y-flujos)
5. [Formatos y Estándares](#formatos-y-estándares)
6. [Integraciones Externas](#integraciones-externas)

---

## 🏗️ Arquitectura y Tecnología

### API v2
Versión activa y en desarrollo de la API. Reemplaza a v1 con arquitectura relacional y mejores prácticas.

**Referencia**: [Fundamentos - Introducción](../20-fundamentos/00-Introduccion.md)

### Multi-Tenancy
Arquitectura donde múltiples clientes (tenants) comparten la misma aplicación pero con datos aislados en bases de datos separadas.

**Características**:
- Base de datos central para gestión de tenants
- Base de datos separada por tenant
- Identificación mediante header `X-Tenant`

**Referencia**: [Fundamentos - Arquitectura Multi-Tenant](../20-fundamentos/01-Arquitectura-Multi-Tenant.md)

### Tenant
Cliente o empresa que usa el sistema. Cada tenant tiene su propia base de datos con datos aislados.

**Componentes**:
- `subdomain`: Identificador único del tenant
- `database`: Nombre de la base de datos del tenant
- `active`: Estado activo/inactivo

**Referencia**: [Fundamentos - Arquitectura Multi-Tenant](../20-fundamentos/01-Arquitectura-Multi-Tenant.md)

### Laravel Sanctum
Sistema de autenticación API basado en tokens para Laravel. Usa Personal Access Tokens para autenticación stateless.

**Referencia**: [Fundamentos - Autenticación](../20-fundamentos/02-Autenticacion-Autorizacion.md)

### Eloquent ORM
Sistema ORM (Object-Relational Mapping) de Laravel para interactuar con bases de datos.

**Referencia**: [Referencia - Modelos](./95-Modelos-Referencia.md)

### API Resource
Clase de Laravel que transforma modelos Eloquent en estructuras JSON consistentes para respuestas de API.

**Referencia**: [Referencia - Recursos API](./96-Recursos-API.md)

### Middleware
Capas de procesamiento que interceptan requests HTTP antes de llegar al controlador.

**Middlewares Principales**:
- `tenant`: Identificación de tenant
- `auth:sanctum`: Autenticación
- `role:*`: Autorización por roles
- `LogActivity`: Registro de actividades

**Referencia**: [Fundamentos - Autenticación](../20-fundamentos/02-Autenticacion-Autorizacion.md)

### RBAC (Role-Based Access Control)
Sistema de control de acceso basado en roles. Roles definidos: `superuser`, `manager`, `admin`, `store_operator`.

**Referencia**: [Fundamentos - Autenticación](../20-fundamentos/02-Autenticacion-Autorizacion.md)

### UsesTenantConnection
Trait personalizado que configura modelos para usar la conexión de base de datos del tenant actual.

**Referencia**: [Fundamentos - Arquitectura Multi-Tenant](../20-fundamentos/01-Arquitectura-Multi-Tenant.md)

### Soft Deletes
Funcionalidad de Eloquent que permite "eliminar" registros marcándolos como eliminados sin borrarlos físicamente de la base de datos.

**Modelos con Soft Deletes**: `Production`

---

## 📦 Términos de Negocio

### Lote de Producción (Production Lot)
Unidad de producción que agrupa productos procesados juntos. Identificado por un número de lote (`lot`).

**Estados**:
- `open`: Lote abierto (en proceso)
- `closed`: Lote cerrado (finalizado)

**Referencia**: [Producción - Lotes](../25-produccion/11-Produccion-Lotes.md)

### Proceso de Producción (Production Record)
Etapa individual dentro de un lote de producción. Los procesos pueden formar una jerarquía (árbol de procesos).

**Estados**:
- `pending`: Pendiente
- `in_progress`: En progreso
- `finished`: Finalizado

**Referencia**: [Producción - Procesos](../25-produccion/12-Produccion-Procesos.md)

### Entrada de Producción (Production Input)
Registro de materia prima o productos consumidos en un proceso de producción. Relaciona cajas (`Box`) con procesos.

**Referencia**: [Producción - Entradas](../25-produccion/13-Produccion-Entradas.md)

### Salida de Producción (Production Output)
Registro de productos generados en un proceso de producción. Indica qué productos y cantidades se obtuvieron.

**Referencia**: [Producción - Salidas](../25-produccion/14-Produccion-Salidas.md)

### Pedido (Order)
Documento que representa una solicitud de productos de un cliente. Incluye información de transporte, fechas, y productos planificados.

**Estados**:
- `pending`: Pendiente
- `finished`: Finalizado

**Referencia**: [Pedidos - General](../22-pedidos/20-Pedidos-General.md)

### Producto Planificado (Order Planned Product Detail)
Producto incluido en un pedido con cantidad, precio, y otros detalles. Define qué productos se esperan entregar.

**Referencia**: [Pedidos - Detalles Planificados](../22-pedidos/21-Pedidos-Detalles-Planificados.md)

### Incidencia (Incident)
Problema o evento relacionado con un pedido. Puede ser devolución, compensación, o problema parcial.

**Estados**:
- `open`: Abierta
- `resolved`: Resuelta

**Tipos de Resolución**:
- `returned`: Devuelto
- `partially_returned`: Parcialmente devuelto
- `compensated`: Compensado

**Referencia**: [Pedidos - Incidentes](../22-pedidos/23-Pedidos-Incidentes.md)

### Almacén (Store)
Ubicación física donde se almacenan productos. Puede tener capacidad, temperatura controlada, y mapa de posiciones.

**Referencia**: [Inventario - Almacenes](../23-inventario/30-Almacenes.md)

### Palet (Pallet)
Unidad de almacenamiento que agrupa múltiples cajas. Se asigna a pedidos y puede almacenarse en posiciones específicas.

**Estados**:
- `1`: Pendiente
- `2`: Almacenado
- `3`: Enviado

**Referencia**: [Inventario - Palets](../23-inventario/31-Palets.md)

### Caja (Box)
Unidad mínima de trazabilidad. Cada caja tiene un código GS1-128 único, peso, y puede estar asociada a un palet.

**Referencia**: [Inventario - Cajas](../23-inventario/32-Cajas.md)

### Recepción de Materia Prima (Raw Material Reception)
Registro de recepción de materia prima de proveedores. Incluye productos, pesos netos, y precios.

**Referencia**: [Recepciones - Materia Prima](../26-recepciones-despachos/60-Recepciones-Materia-Prima.md)

### Despacho de Cebo (Cebo Dispatch)
Registro de despacho de cebo a proveedores. Incluye productos, pesos netos, y precios.

**Referencia**: [Despachos - Cebo](../26-recepciones-despachos/61-Despachos-Cebo.md)

### Especie (Species)
Tipo de pescado o marisco. Incluye nombre científico, código FAO, y arte de pesca asociada.

**Referencia**: [Catálogos - Especies](../24-catalogos/42-Especies.md)

### Zona de Captura (Capture Zone)
Área geográfica donde se capturó el pescado. Usada para trazabilidad.

**Referencia**: [Catálogos - Zonas de Captura](../24-catalogos/43-Zonas-Captura.md)

### Arte de Pesca (Fishing Gear)
Método utilizado para capturar el pescado (red, anzuelo, etc.).

**Referencia**: [Catálogos - Arte Pesquera](../24-catalogos/51-Arte-Pesquera.md)

### Incoterm
Términos comerciales internacionales que definen responsabilidades en el transporte y entrega de mercancías (ej: FOB, CIF, EXW).

**Referencia**: [Catálogos - Incoterms](../24-catalogos/50-Incoterms.md)

### Código A3ERP
Código utilizado para integración con el sistema ERP A3. Almacenado en productos y clientes.

**Referencia**: [Catálogos - Productos](../24-catalogos/40-Productos.md), [Catálogos - Clientes](../24-catalogos/44-Clientes.md)

### Código Facilcom
Código utilizado para integración con el sistema Facilcom. Almacenado en productos y clientes.

**Referencia**: [Catálogos - Productos](../24-catalogos/40-Productos.md), [Catálogos - Clientes](../24-catalogos/44-Clientes.md)

---

## 🏷️ Entidades del Sistema

### Producto (Product)
Entidad que extiende `Article`. Comparte el mismo ID con `Article` en una relación 1:1 especial.

**Características**:
- Relación con especie, zona de captura, familia
- Códigos GTIN (artículo, caja, palet)
- Códigos de integración (A3ERP, Facilcom)

**Referencia**: [Catálogos - Productos](../24-catalogos/40-Productos.md)

### Artículo (Article)
Entidad base para productos. `Product` es una extensión que comparte el mismo ID.

**Referencia**: [Referencia - Modelos](./95-Modelos-Referencia.md)

### Categoría de Producto (Product Category)
Clasificación jerárquica de productos. Puede tener categorías hijas.

**Referencia**: [Catálogos - Categorías y Familias](../24-catalogos/41-Categorias-Familias-Productos.md)

### Familia de Producto (Product Family)
Agrupación de productos dentro de una categoría.

**Referencia**: [Catálogos - Categorías y Familias](../24-catalogos/41-Categorias-Familias-Productos.md)

### Cliente (Customer)
Empresa o persona que realiza pedidos. Incluye información de contacto, direcciones, términos de pago.

**Referencia**: [Catálogos - Clientes](../24-catalogos/44-Clientes.md)

### Proveedor (Supplier)
Empresa o persona que provee materias primas o recibe cebo. Puede ser de diferentes tipos.

**Referencia**: [Catálogos - Proveedores](../24-catalogos/45-Proveedores.md)

### Transporte (Transport)
Empresa o vehículo responsable del transporte de pedidos. Asociado a pedidos y clientes.

**Referencia**: [Catálogos - Transportes](../24-catalogos/46-Transportes.md)

### Vendedor (Salesperson)
Persona responsable de las ventas. Asociado a clientes y pedidos.

**Referencia**: [Catálogos - Vendedores](../24-catalogos/47-Vendedores.md)

### Término de Pago (Payment Term)
Condiciones de pago acordadas con clientes.

**Referencia**: [Catálogos - Términos de Pago](../24-catalogos/48-Terminos-Pago.md)

### País (Country)
País de origen o destino. Asociado a clientes.

**Referencia**: [Catálogos - Países](../24-catalogos/49-Paises.md)

### Proceso (Process)
Proceso de producción maestro. Define tipos de procesos que pueden aplicarse en la producción.

**Referencia**: [Catálogos - Procesos](../24-catalogos/53-Procesos.md)

### Impuesto (Tax)
Tasa de impuesto aplicable a productos en pedidos.

**Referencia**: [Catálogos - Impuestos](../24-catalogos/52-Impuestos.md)

### Etiqueta (Label)
Plantilla para generación de etiquetas impresas. Define formato en JSON.

**Referencia**: [Etiquetas](../27-etiquetas/70-Etiquetas.md)

### Usuario (User)
Persona que accede al sistema. Tiene roles, puede estar asociado a un almacén.

**Referencia**: [Sistema - Usuarios](../28-sistema/80-Usuarios.md)

### Rol (Role)
Permisos y nivel de acceso del usuario en el sistema.

**Roles Definidos**:
- `superuser`: Acceso completo
- `manager`: Gerencia
- `admin`: Administración
- `store_operator`: Operador de almacén

**Referencia**: [Sistema - Roles](../28-sistema/81-Roles.md)

---

## 🔄 Estados y Flujos

### Pedido Activo
Pedido que está pendiente o cuya fecha de carga es futura o actual. Lógica: `status == 'pending' || load_date >= now()`.

**Referencia**: [Pedidos - General](../22-pedidos/20-Pedidos-General.md)

### Lote Abierto/Cerrado
Estado de un lote de producción:
- **Abierto**: Acepta nuevos procesos y modificaciones
- **Cerrado**: Solo lectura, finalizado

**Referencia**: [Producción - Lotes](../25-produccion/11-Produccion-Lotes.md)

### Proceso Pendiente/En Progreso/Finalizado
Estados de un proceso de producción dentro de un lote.

**Referencia**: [Producción - Procesos](../25-produccion/12-Produccion-Procesos.md)

### Palet Pendiente/Almacenado/Enviado
Estados de un palet en el flujo de almacén:
- **Pendiente (1)**: Creado pero no almacenado
- **Almacenado (2)**: En almacén, con posición asignada
- **Enviado (3)**: En tránsito o entregado

**Referencia**: [Inventario - Palets](../23-inventario/31-Palets.md)

### Caja Disponible
Caja que no está siendo usada en producción. Verifica que no tenga `productionInputs` asociados.

**Referencia**: [Inventario - Cajas](../23-inventario/32-Cajas.md)

---

## 📋 Formatos y Estándares

### GS1-128
Código de barras estándar GS1-128 usado para trazabilidad de cajas. Incluye información del producto, lote, fecha, etc.

**Referencia**: [Inventario - Cajas](../23-inventario/32-Cajas.md)

### GTIN (Global Trade Item Number)
Códigos estándar para identificación de productos:
- `article_gtin`: GTIN del artículo
- `box_gtin`: GTIN de la caja
- `pallet_gtin`: GTIN del palet

**Referencia**: [Catálogos - Productos](../24-catalogos/40-Productos.md)

### Formato A3ERP
Formato de archivo Excel específico para integración con sistema ERP A3. Usa columnas específicas (CABSERIE, CABNUMDOC, etc.).

**Referencia**: [Utilidades - Exportación Excel](../29-utilidades/91-Exportacion-Excel.md)

### Formato A3ERP2
Variante de A3ERP que usa códigos Facilcom. Solo para clientes con `facilcom_code`.

**Referencia**: [Utilidades - Exportación Excel](../29-utilidades/91-Exportacion-Excel.md)

### Formato Facilcom
Formato de archivo Excel específico para integración con sistema Facilcom.

**Referencia**: [Utilidades - Exportación Excel](../29-utilidades/91-Exportacion-Excel.md)

### Código FAO
Código estándar de la Organización de las Naciones Unidas para la Alimentación y la Agricultura (FAO) para especies de pescado.

**Referencia**: [Catálogos - Especies](../24-catalogos/42-Especies.md)

---

## 🔌 Integraciones Externas

### A3ERP
Sistema ERP externo. El sistema exporta archivos Excel en formato A3ERP para integración.

**Referencia**: [Utilidades - Exportación Excel](../29-utilidades/91-Exportacion-Excel.md)

### Facilcom
Sistema externo. El sistema exporta archivos Excel en formato Facilcom para integración.

**Referencia**: [Utilidades - Exportación Excel](../29-utilidades/91-Exportacion-Excel.md)

### Azure Document AI (Form Recognizer)
Servicio de Microsoft Azure para extracción de datos de documentos PDF usando IA.

**Referencia**: [Utilidades - Extracción AI](../29-utilidades/92-Extraccion-Documentos-AI.md)

### Google Document AI
Servicio de Google Cloud para extracción de datos de documentos PDF usando IA. Actualmente deshabilitado.

**Referencia**: [Utilidades - Extracción AI](../29-utilidades/92-Extraccion-Documentos-AI.md)

### Snappdf
Librería PHP que envuelve Chromium headless para generar PDFs desde HTML.

**Referencia**: [Utilidades - Generación PDF](../29-utilidades/90-Generacion-PDF.md)

### Laravel Excel (Maatwebsite)
Librería para importación y exportación de archivos Excel en Laravel.

**Referencia**: [Utilidades - Exportación Excel](../29-utilidades/91-Exportacion-Excel.md)

---

## 🔧 Términos Técnicos

### Eager Loading
Técnica de Eloquent para cargar relaciones de forma anticipada, evitando queries N+1.

**Ejemplo**: `Order::with('customer', 'pallets')->get()`

### N+1 Query Problem
Problema de performance donde se ejecutan múltiples queries innecesarias al acceder a relaciones no cargadas.

**Referencia**: Varios módulos

### toArrayAssoc()
Método común en modelos que retorna una representación asociativa del modelo. Usado por Resources API.

**Referencia**: [Referencia - Recursos API](./96-Recursos-API.md)

### whenLoaded()
Método de Laravel Resources para incluir relaciones solo si están cargadas, evitando N+1.

**Referencia**: [Referencia - Recursos API](./96-Recursos-API.md)

### Accessor
Método mágico de Eloquent que permite acceder a atributos calculados como propiedades del modelo.

**Ejemplo**: `$order->totalNetWeight` llama a `getTotalNetWeightAttribute()`

### Mutator
Método mágico de Eloquent que permite modificar valores antes de guardarlos en la base de datos.

### Soft Delete
Eliminación lógica de registros, marcándolos como eliminados sin borrarlos físicamente.

### Migration
Archivo que define cambios en la estructura de la base de datos de forma versionada.

### Seeder
Clase que pobla la base de datos con datos iniciales o de prueba.

### Form Request
Clase de Laravel para validación y autorización de requests HTTP.

### API Resource
Clase que transforma modelos en estructuras JSON consistentes para respuestas API.

---

## 📊 Estadísticas y Reportes

### Estadísticas de Pedidos
Métricas y reportes sobre pedidos: totales de peso neto, montos, rankings, gráficos de ventas.

**Referencia**: [Pedidos - Estadísticas](../22-pedidos/24-Pedidos-Estadisticas.md)

### Estadísticas de Stock
Métricas sobre inventario: totales de peso, palets, cajas, agrupado por especies, productos, almacenes.

**Referencia**: [Inventario - Estadísticas Stock](../23-inventario/33-Estadisticas-Stock.md)

### Estadísticas de Recepciones
Métricas sobre recepciones de materia prima: totales, gráficos, agrupados por fecha, proveedor, producto.

**Referencia**: [Recepciones - Materia Prima](../26-recepciones-despachos/60-Recepciones-Materia-Prima.md)

### Estadísticas de Despachos
Métricas sobre despachos de cebo: totales, gráficos, agrupados por fecha, proveedor, producto.

**Referencia**: [Despachos - Cebo](../26-recepciones-despachos/61-Despachos-Cebo.md)

---

## 📝 Documentos

### Hoja de Pedido
Documento PDF con información completa del pedido para uso interno.

**Referencia**: [Utilidades - Generación PDF](../29-utilidades/90-Generacion-PDF.md)

### Nota de Carga
Documento PDF que acompaña la mercancía durante el transporte.

**Referencia**: [Utilidades - Generación PDF](../29-utilidades/90-Generacion-PDF.md)

### CMR (Convention Merchandises Routiers)
Documento internacional de transporte por carretera. Formato estándar europeo.

**Referencia**: [Utilidades - Generación PDF](../29-utilidades/90-Generacion-PDF.md)

### Albarán de Venta
Documento PDF/Excel que documenta la venta y entrega de productos.

**Referencia**: [Pedidos - Documentos](../22-pedidos/22-Pedidos-Documentos.md), [Utilidades - Exportación Excel](../29-utilidades/91-Exportacion-Excel.md)

### Packing List
Lista de empaque que detalla el contenido de un pedido.

**Referencia**: [Utilidades - Generación PDF](../29-utilidades/90-Generacion-PDF.md)

---

## 🔐 Seguridad y Autenticación

### Token Sanctum
Token de autenticación API generado por Laravel Sanctum. Cada token tiene un nombre y puede tener abilidades (scopes).

**Referencia**: [Fundamentos - Autenticación](../20-fundamentos/02-Autenticacion-Autorizacion.md)

### Sesión
Token de autenticación activo de un usuario. Puede ser listado y revocado individualmente.

**Referencia**: [Sistema - Sesiones](../28-sistema/82-Sesiones.md)

### Activity Log
Registro de actividades del usuario: acciones realizadas, IP, dispositivo, navegador, ruta accedida.

**Referencia**: [Sistema - Logs de Actividad](../28-sistema/83-Logs-Actividad.md)

---

## ⚙️ Configuración

### Setting
Configuración dinámica del sistema almacenada en la base de datos del tenant. Permite personalización sin modificar código.

**Referencia**: [Sistema - Configuración](../28-sistema/84-Configuracion.md)

### tenantSetting()
Helper function para acceder a configuraciones del tenant actual.

**Referencia**: [Sistema - Configuración](../28-sistema/84-Configuracion.md)

---

## 🔗 Referencias Cruzadas

Para información detallada sobre términos específicos:

- **Arquitectura**: [Fundamentos](../20-fundamentos/)
- **Modelos**: [Referencia - Modelos](./95-Modelos-Referencia.md)
- **Recursos**: [Referencia - Recursos API](./96-Recursos-API.md)
- **Rutas**: [Referencia - Rutas Completas](./97-Rutas-Completas.md)
- **Errores**: [Referencia - Errores Comunes](./98-Errores-Comunes.md)

