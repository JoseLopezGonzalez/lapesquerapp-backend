# Introducción al Backend de PesquerApp

## ⚠️ Estado de la API
- **v1**: Obsoleta (no documentada) - Se mantiene únicamente por razones de compatibilidad
- **v2**: Versión activa (este documento) - Toda la documentación hace referencia a esta versión

---

## 📋 Visión General

**PesquerApp** es una plataforma ERP multi-tenant (SaaS) diseñada específicamente para pequeñas y medianas industrias del sector pesquero y distribuidores. Este repositorio contiene el backend desarrollado en Laravel que sirve como núcleo de comunicación entre las interfaces de usuario y las bases de datos de cada empresa cliente.

### Propósito del Sistema

El sistema permite gestionar:
- **Producción pesquera**: Lotes, procesos, trazabilidad por caja individual
- **Pedidos y ventas**: Gestión completa del ciclo de pedidos, documentos PDF, envío por email
- **Inventario y almacenes**: Control de stock mediante palets y cajas, mapas interactivos
- **Catálogos maestros**: Productos, especies, clientes, proveedores, transportes
- **Análisis y reportes**: Estadísticas de producción, ventas, stock

---

## 🏗️ Arquitectura General

### Modelo Multi-Tenant

El sistema utiliza una arquitectura **multi-tenant** donde:
- **Una sola API** (`api.pesquerapp.es`) sirve a todas las empresas clientes
- **Cada empresa tiene su propia base de datos** (`db_empresa1`, `db_empresa2`, etc.)
- **Identificación por subdominio**: Cada empresa se identifica mediante la cabecera HTTP `X-Tenant`
- **Conexión dinámica**: El middleware cambia automáticamente la conexión de base de datos según el tenant

### Estructura de Bases de Datos

```
┌─────────────────────────────────────┐
│   Base Central (mysql)              │
│   - tenants (catálogo de empresas)  │
└─────────────────────────────────────┘
              │
              ├─── db_empresa1 (tenant)
              ├─── db_empresa2 (tenant)
              ├─── db_empresa3 (tenant)
              └─── ... (más tenants)
```

Cada base de datos tenant contiene:
- Todas las tablas de negocio (orders, products, productions, etc.)
- Usuarios específicos del tenant
- Configuración propia

---

## 🚀 Características Principales

### 1. Arquitectura SaaS Multi-Tenant
- Subdominios tipo `empresa.pesquerapp.es`
- Cambio dinámico de base de datos según subdominio (`X-Tenant`)
- Aislamiento completo de datos entre empresas

### 2. Módulo de Producción
- Sistema complejo de trazabilidad por caja individual
- Árboles de procesos jerárquicos
- Cálculo dinámico de diagramas y mermas
- Conciliación entre producción declarada y stock real

### 3. Gestión de Pedidos
- Ciclo completo de pedidos (creación, planificación, despacho)
- Generación automática de documentos PDF (albaranes, CMR, hojas de pedido)
- Envío de documentación por email
- Gestión de incidentes en pedidos

### 4. Control de Inventario
- Gestión de almacenes reales
- Control de palets y cajas individuales
- Mapas interactivos de ubicación de palets
- Estadísticas de stock por producto y especie

### 5. Extracción de Datos con IA
- Extracción automática de datos desde PDFs de lonjas locales
- Integración con Azure Document AI
- Procesamiento de recepciones de materia prima

### 6. Sistema de Autenticación y Autorización
- Laravel Sanctum para autenticación por token
- Sistema de roles basado en permisos (superuser, manager, admin, store_operator)
- Gestión de sesiones activas

---

## 🧱 Tecnologías Utilizadas

### Backend Framework
- **Laravel 10**: Framework PHP moderno y robusto
- **PHP 8.1+**: Lenguaje de programación

### Base de Datos
- **MySQL**: Motor de base de datos relacional
- Estructura multi-tenant: una base central + una por tenant

### Autenticación
- **Laravel Sanctum**: Autenticación por tokens API
- Tokens con expiración configurable (30 días por defecto)

### Despliegue
- **Docker**: Containerización
- **Coolify**: Plataforma de despliegue automático
- **Nginx/Apache**: Servidor web

### Herramientas Adicionales
- **Vite**: Build tool para assets frontend
- **Tailwind CSS**: Framework CSS (si hay frontend)
- **Azure Document AI**: Servicio de IA para extracción de documentos

---

## 📂 Estructura del Proyecto

```
pesquerapp-backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── v1/          # Controladores obsoletos (v1)
│   │   │   └── v2/          # Controladores activos (v2)
│   │   ├── Middleware/      # Middlewares personalizados
│   │   └── Resources/       # API Resources
│   ├── Models/              # Modelos Eloquent
│   ├── Traits/              # Traits reutilizables
│   └── Console/             # Comandos Artisan
├── database/
│   ├── migrations/
│   │   └── companies/       # Migraciones específicas de tenants
│   └── seeders/
├── routes/
│   └── api.php              # Definición de rutas API
├── config/                  # Archivos de configuración
└── docs/                    # Esta documentación
```

---

## 🔑 Conceptos Clave

### API v2

Toda la documentación hace referencia a la **API v2**, que es la versión activa y en desarrollo. La v1 está obsoleta y se mantiene únicamente por compatibilidad.

**Rutas v2**: Todas bajo el prefijo `/v2/`

### Multi-Tenant

Cada empresa cliente es un **tenant** con:
- Su propia base de datos
- Sus propios usuarios
- Aislamiento completo de datos

### Tenant Connection

Los modelos usan el trait `UsesTenantConnection` para usar automáticamente la conexión `tenant` configurada dinámicamente por el middleware.

### Autenticación Sanctum

La autenticación usa **tokens Bearer** generados por Laravel Sanctum. Cada usuario tiene tokens que expiran después de 30 días (configurable).

### Sistema de Roles

El sistema tiene 4 roles principales:
- `superuser`: Acceso total, gestión técnica
- `manager`: Gestión y administración
- `admin`: Administración de datos
- `store_operator`: Operador de almacén (acceso limitado)

---

## 📡 Endpoints Base

### Autenticación
- `POST /v2/login` - Iniciar sesión
- `POST /v2/logout` - Cerrar sesión (requiere auth)
- `GET /v2/me` - Obtener usuario autenticado (requiere auth)

### Información Pública
- `GET /v2/public/tenant/{subdomain}` - Obtener información de tenant

### Rutas Protegidas
Todas las demás rutas requieren:
- Header `X-Tenant`: Subdominio de la empresa
- Header `Authorization: Bearer {token}`: Token de autenticación
- Rol adecuado según el endpoint

---

## 🔐 Requisitos de Autenticación

### Cabeceras Requeridas

Todas las requests a la API v2 (excepto rutas públicas) requieren:

```http
X-Tenant: empresa1
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...
Content-Type: application/json
```

### Flujo de Autenticación

**No hay login con contraseña.** El acceso es por **Magic Link** u **OTP** por email:

1. **Solicitar acceso**: `POST /v2/auth/magic-link/request` o `POST /v2/auth/otp/request` con el email.
2. **Canjear**: Tras el enlace del correo o el código, `POST /v2/auth/magic-link/verify` o `POST /v2/auth/otp/verify` retorna `access_token` y datos del usuario.
3. **Requests subsiguientes**: Incluir `Authorization: Bearer {token}` y `X-Tenant` en cada request.
4. **Logout**: `POST /v2/logout` invalida el token.

---

## 📚 Navegación de la Documentación

Esta documentación está organizada en secciones:

### Fundamentos
- **[00-Introduccion.md](./00-Introduccion.md)** (este archivo): Visión general
- **[01-Arquitectura-Multi-Tenant.md](./01-Arquitectura-Multi-Tenant.md)**: Sistema multi-tenant
- **[02-Autenticacion-Autorizacion.md](./02-Autenticacion-Autorizacion.md)**: Sanctum y roles
- **[03-Configuracion-Entorno.md](./03-Configuracion-Entorno.md)**: Configuración

### Módulos Principales
- **[10-Produccion-General.md](../25-produccion/10-Produccion-General.md)**: Módulo de producción
- **[20-Pedidos-General.md](../22-pedidos/20-Pedidos-General.md)**: Módulo de pedidos
- Y más módulos documentados...

### Referencia Técnica
- **[95-Modelos-Referencia.md](../30-referencia/95-Modelos-Referencia.md)**: Todos los modelos
- **[97-Rutas-Completas.md](../30-referencia/97-Rutas-Completas.md)**: Todas las rutas v2

---

## 🎯 Principios de Diseño

### 1. Multi-Tenancy First
- Todos los modelos de negocio usan conexión tenant
- Aislamiento total de datos entre empresas
- Middleware global para selección automática

### 2. API RESTful
- Endpoints siguiendo convenciones REST
- Uso de HTTP verbs apropiados (GET, POST, PUT, DELETE)
- Códigos de estado HTTP semánticos

### 3. Resource Transformers
- Uso de API Resources para formatear respuestas
- Separación entre modelo y formato de respuesta
- Inclusión condicional de relaciones

### 4. Seguridad
- Autenticación requerida por defecto
- Autorización basada en roles
- Validación exhaustiva de inputs

### 5. Trazabilidad
- Logs de actividad (ActivityLog)
- Timestamps en todas las tablas
- Relaciones bien definidas para auditoría

---

## ⚠️ Advertencias Importantes

### API v1 Eliminada

- **API v1 ha sido completamente eliminada** (2025-01-27)
- Ya no existe en el código base
- Solo existe la API v2, que es la versión activa y única disponible

### Middleware Tenant Obligatorio

- Todas las rutas v2 requieren la cabecera `X-Tenant`
- Sin esta cabecera, la request fallará con error 400

### Tokens con Expiración

- Los tokens de Sanctum expiran después de 30 días (configurable)
- El frontend debe manejar la renovación de tokens
- Tokens expirados retornan error 401

---

## 🔄 Estado del Proyecto

### Módulos Completados
- ✅ Sistema multi-tenant
- ✅ Autenticación y autorización
- ✅ Gestión de usuarios y roles
- ✅ Módulo de producción (en transición v1→v2)
- ✅ Gestión de pedidos
- ✅ Control de inventario
- ✅ Catálogos maestros

### Módulos en Desarrollo
- 🚧 Mejoras en producción (migración completa a v2)
- 🚧 Sistema de auditoría avanzado
- 🚧 Restricciones para store_operator

### Pendientes
- ⏳ Panel de control para administrador global
- ⏳ Comandos automáticos para crear nuevos tenants
- ⏳ Mejoras en sistema de logs

---

## 📞 Soporte y Contribución

Esta documentación está diseñada para:
1. **Desarrolladores humanos**: Comprender y modificar el backend
2. **IAs**: Usar como contexto técnico fiable
3. **Onboarding**: Familiarizar nuevos miembros del equipo

Para dudas o mejoras, consultar la sección "Observaciones Críticas" en cada documento.

---

**Última actualización**: Documentación generada desde código fuente en fecha de generación.

