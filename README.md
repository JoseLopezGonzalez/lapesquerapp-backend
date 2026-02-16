# PesquerApp – Laravel API (Backend)

**PesquerApp** es una plataforma ERP multiempresa (_multi-tenant_) diseñada especialmente para pequeñas y medianas industrias del sector pesquero y distribuidores. Este repositorio contiene la API principal, desarrollada en Laravel, que sirve como núcleo de comunicación entre las interfaces de usuario y las bases de datos de cada empresa.

---

## 🚀 Características principales

- 🌐 Arquitectura SaaS multi-tenant con subdominios tipo `empresa.pesquerapp.es`
- 🔁 Cambio dinámico de base de datos según el subdominio (`X-Tenant`)
- 🧾 Módulo avanzado de gestión de pedidos con generación de documentos PDF y envío por email
- 🏷️ Generación e impresión de etiquetas con códigos de barras y QR
- 📦 Control de stock en almacenes reales mediante mapas interactivos de palets y cajas
- 🧠 Análisis de producción con sistema de diagrama de nodos
- 🤖 Extracción de datos con IA desde PDFs de lonjas locales
- 🔐 Sistema de autenticación por token (Laravel Sanctum)

---

## 🧱 Tecnologías utilizadas

- **Laravel 10**
- **MySQL** (una base central + una por tenant)
- **Sanctum** para autenticación
- **Docker / Coolify** para despliegue

---

## 📚 Documentación

La documentación técnica completa está disponible en:

**📁 [`/docs/`](docs/)**

La documentación incluye:
- Fundamentos (Arquitectura, Autenticación, Configuración)
- Módulos de negocio (Producción, Pedidos, Inventario, Catálogos, etc.)
- Utilidades (PDF, Excel, IA)
- Referencia técnica (Modelos, Rutas, Errores, Glosario)

**📋 Resumen de problemas críticos**: Ver [`docs/audits/problemas-criticos.md`](docs/audits/problemas-criticos.md) para los 25 problemas más críticos.

- **Índice estándar (00-15):** [`docs/00-overview.md`](docs/00-overview.md)
- **Índice por dominio:** [`docs/00-docs-index.md`](docs/00-docs-index.md)

---

## ⚙️ Instalación Local

```bash
# 1. Clonar repositorio
git clone <repository-url>
cd pesquerapp-backend

# 2. Instalar dependencias
composer install
npm install

# 3. Configurar entorno
cp .env.example .env
php artisan key:generate

# 4. Ejecutar migraciones
php artisan migrate

# 5. Iniciar servidor
php artisan serve
```

Para más detalles, ver [`docs/fundamentos/03-Configuracion-Entorno.md`](docs/fundamentos/03-Configuracion-Entorno.md).

---

## 🐳 Desarrollo con Docker Sail

Para un entorno local reproducible con MySQL, Redis y Mailpit:

```bash
# 1. Usar variables de entorno para Sail
cp .env.sail.example .env
php artisan key:generate

# 2. Levantar contenedores
./vendor/bin/sail up -d

# 3. Migraciones (central + tenants)
php artisan migrate
php artisan tenants:migrate --seed   # requiere al menos un tenant activo con BD creada
```

- **Backend:** http://localhost  
- **Mailpit:** http://localhost:8025  
- **Health API:** `GET /api/health`  

Si el frontend (Next.js) usa Sanctum con cookies, configurar **`withCredentials: true`** en axios/fetch. Ver [Guía completa entorno Sail](docs/instrucciones/guia-completa-entorno-sail-windows.md) y [Plan Sail](docs/instrucciones/IMPLEMENTATION_PLAN_DOCKER_SAIL.md).

---

## 🚀 Despliegue

El proyecto está preparado para desplegarse en Coolify. Ver sección de despliegue en [`docs/fundamentos/03-Configuracion-Entorno.md`](docs/fundamentos/03-Configuracion-Entorno.md).

---

## 📄 Licencia

Este proyecto es privado y propiedad de [La Pesquerapp S.L.](https://lapesquerapp.es).  
No distribuir sin autorización.
