# Plan: Infraestructura SaaS — CORS dinámico + Superadmin + Onboarding de Tenants

> Contexto: PesquerApp es un SaaS multi-tenant con arquitectura database-per-tenant sobre Laravel 10 + Next.js 16.
> Cada tenant tiene su propio subdominio (`{tenant}.lapesquerapp.com`) y su propia base de datos.
> Actualmente el CORS requiere añadir manualmente cada subdominio en `.env`. No existe panel de gestión de tenants ni flujo de onboarding automatizado.

---

## 1. CORS dinámico por subdominio de tenant

### Situación actual

El middleware de CORS en Laravel lee los dominios permitidos desde `FRONTEND_URL` en `.env`. Añadir un nuevo tenant requiere modificar el `.env` y redeployar.

### Objetivo

Que cualquier subdominio válido de `lapesquerapp.com` sea aceptado automáticamente sin tocar configuración. El sistema debe distinguir subdominios legítimos (tenants registrados en la BD central) de cualquier otro origen.

### Concepto de implementación — Backend

En lugar de leer CORS desde `.env`, el middleware de CORS consulta la tabla de tenants en la BD central y acepta el origen si el subdominio corresponde a un tenant activo. El patrón del dominio base (`lapesquerapp.com`) se mantiene en config pero los subdominios se resuelven dinámicamente en cada request.

El middleware actual (`HandleCors` o la config de `cors.php`) se reemplaza o extiende con lógica que:

1. Extrae el subdominio del header `Origin`.
2. Comprueba contra la tabla central de tenants si ese subdominio está registrado y activo.
3. Si lo está, permite el origen. Si no, rechaza con 403.

Cachear el resultado por subdominio en Redis/cache de Laravel para no golpear la BD en cada request.

### Concepto de implementación — Frontend

El frontend ya usa el subdominio actual para saber a qué tenant apuntar. No requiere cambios sustanciales, pero hay que revisar que `NEXT_PUBLIC_API_URL` se construya dinámicamente desde el subdominio actual y no esté hardcodeado por tenant.

---

## 2. Tabla central de tenants

### Situación actual

No existe una BD o tabla centralizada que liste todos los tenants con su estado, plan y metadatos. La gestión es completamente manual.

### Objetivo

Una BD central (o tabla en la BD principal) que sea la fuente de verdad de todos los tenants: estado, plan, subdominio, fechas, configuración inicial.

### Concepto de implementación — Backend

Crear una BD central separada (o usar la BD del sistema base) con una tabla `tenants` que contenga al menos:

* Identificador único, subdominio, nombre de empresa.
* Estado: `pending`, `active`, `suspended`, `cancelled`.
* Plan contratado y fecha de renovación.
* Nombre de la BD del tenant (para el sistema de resolución multi-tenant ya existente).
* Fecha de creación y de última actividad.
* Configuración inicial: logo, datos de empresa, zona horaria.

Esta tabla ya alimenta el sistema de resolución de tenant actual (que identifica qué BD usar por subdominio). Se trata de formalizarla y ampliarla, no de crear algo desde cero si ya existe alguna forma de mapeo subdominio→BD.

---

## 3. Panel Superadmin

### Objetivo

Interfaz separada de la app de clientes para que los propietarios de PesquerApp gestionen tenants y suscripciones.

### Concepto — Backend

Un conjunto de endpoints protegidos por un sistema de autenticación independiente al de los tenants. Los propietarios de PesquerApp tienen sus propios usuarios en la BD central (no en la BD de ningún tenant).

Estos endpoints permiten:

* Listar, crear, activar, suspender y eliminar tenants.
* Ver el estado de suscripción y plan de cada tenant.
* Consultar métricas básicas de uso por tenant (último acceso, volumen de datos).
* Forzar el onboarding de un tenant (trigger manual del flujo del punto 4).

La autenticación del superadmin puede reutilizar el sistema de magic link ya implementado pero apuntando a la BD central, no a la de ningún tenant.

### Concepto — Frontend

Ruta separada: `/superadmin` con su propio layout, sin relación con `/admin`, `/operator` ni `/comercial`. Login propio en `/superadmin/login`.

Vistas principales:

* Lista de tenants con estado, plan y acceso rápido a detalles.
* Detalle de tenant: datos, estado de suscripción, acciones (activar, suspender).
* Formulario de creación de nuevo tenant (dispara el flujo de onboarding del punto 4).
* Vista de suscripciones y facturación (en una primera fase puede ser solo informativa, sin pasarela de pago).

---

## 4. Onboarding automatizado de nuevo tenant

### Situación actual

Crear un nuevo tenant es un proceso completamente manual: crear BD, ejecutar migraciones, crear usuario admin, configurar subdominio, añadir al `.env`. Sin procedimiento definido ni trazabilidad.

### Objetivo

Un flujo automatizado que, dado los datos mínimos de un nuevo cliente, aprovisione todo lo necesario sin intervención manual.

### Concepto de implementación — Backend

Un comando Artisan o un Job en Laravel que recibe los datos del nuevo tenant y ejecuta en orden:

1. **Crear registro en tabla central de tenants** con estado `pending`.
2. **Crear la base de datos** del tenant con el nombre convenido (ya existe lógica de esto en el sistema multi-tenant actual, se trata de automatizarla).
3. **Ejecutar migraciones** en la nueva BD del tenant (`php artisan migrate --database=tenant_{id}`).
4. **Crear el usuario administrador** del tenant en su BD con los datos proporcionados.
5. **Seed de datos iniciales**: catálogos básicos (especies comunes, zonas de captura FAO, artes de pesca, incoterms estándar).
6. **Guardar configuración del tenant**: nombre de empresa, logo (URL o placeholder), zona horaria.
7. **Activar el tenant**: cambiar estado a `active` en la tabla central.
8. **Notificar**: enviar email al administrador del nuevo tenant con sus credenciales de acceso y URL de su subdominio.

Este Job debe ser idempotente: si falla en el paso 4, se puede relanzar sin romper lo ya creado en los pasos anteriores.

### Concepto — Frontend Superadmin

El formulario de creación de tenant en el panel Superadmin recoge los datos mínimos (nombre empresa, subdominio deseado, email del admin, plan) y lanza el Job. Muestra el progreso del onboarding en tiempo real o mediante polling hasta que el tenant esté activo.

---

## 5. Configuración del tenant desde dentro de la app

### Situación actual

Las imágenes y datos del tenant (logo, nombre de empresa) están fijados en el frontend. No hay UI de configuración para el tenant.

### Objetivo

Que cada tenant pueda configurar sus datos básicos desde su propio panel en `/admin/settings`: logo, nombre de empresa, datos fiscales para documentos, zona horaria, formato de fechas.

### Concepto — Backend

Tabla o campos en la BD del tenant con su configuración propia. Un endpoint autenticado (solo rol `administrador` o `direccion`) para leer y actualizar esta configuración. El logo se almacena en el sistema de archivos del servidor o en storage externo (S3/R2).

### Concepto — Frontend

`/admin/settings` ya existe como ruta (aunque actualmente sin contenido de configuración de tenant). Ampliar esa página con una sección de configuración de empresa: subida de logo, nombre, datos fiscales. El logo y nombre del tenant deben cargarse dinámicamente en el layout (navbar, documentos PDF, correos) en lugar de estar hardcodeados.

---

## Dependencias y orden sugerido al agente

1. **Tabla central de tenants** — es la base de todo lo demás.
2. **CORS dinámico** — depende de la tabla central para validar subdominios.
3. **Autenticación Superadmin** — independiente, puede ir en paralelo con CORS.
4. **Panel Superadmin (backend: endpoints)** — depende de tabla central y auth superadmin.
5. **Onboarding Job** — depende de tabla central y migraciones existentes.
6. **Panel Superadmin (frontend)** — depende de los endpoints del backend.
7. **Configuración de tenant desde app** — puede ir en paralelo con el panel superadmin.

---

## Notas para el agente

* El sistema de resolución de tenant por subdominio ya está implementado. No reinventar, extender.
* El magic link ya existe. Reutilizar el mecanismo para el login del superadmin apuntando a la BD central.
* Las migraciones de tenant ya existen. El onboarding solo necesita ejecutarlas automáticamente en la BD nueva.
* El Job de onboarding debe loguear cada paso en la tabla central de tenants para poder diagnosticar fallos.
* No implementar pasarela de pago en esta fase. La gestión de suscripciones es informativa (estado y fechas manuales o webhooks en fase posterior).
