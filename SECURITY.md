# Seguridad

**PesquerApp Backend — Laravel API**

---

## Propósito

Definir políticas de seguridad del backend: autenticación, gestión de secretos, datos sensibles y procedimiento de reporting de vulnerabilidades. Este documento no modifica código ni configuraciones; sirve como referencia y punto de entrada a la documentación técnica existente.

## Audiencia

Equipo de desarrollo, responsables de seguridad y compliance.

---

## Tabla de contenidos

1. [Autenticación](#autenticación)
2. [Gestión de secretos y variables de entorno](#gestión-de-secretos-y-variables-de-entorno)
3. [Datos y multi-tenant](#datos-y-multi-tenant)
4. [Reporting de vulnerabilidades](#reporting-de-vulnerabilidades)
5. [Véase también](#véase-también)

---

## Autenticación

- El backend utiliza **Laravel Sanctum** para autenticación por token (API).
- No se usan contraseñas para acceso de usuarios; el flujo estándar es **magic link** y/o **OTP** por correo. Las contraseñas han sido eliminadas del modelo de usuario en el diseño actual.
- Roles y permisos se gestionan por tenant; la documentación de autorización y sesiones está en los enlaces siguientes.

**Documentación técnica:**

- [docs/20-fundamentos/02-Autenticacion-Autorizacion.md](docs/20-fundamentos/02-Autenticacion-Autorizacion.md) — Sanctum, magic link, OTP, flujos.
- [docs/28-sistema/80-Usuarios.md](docs/28-sistema/80-Usuarios.md) — Usuarios.
- [docs/28-sistema/81-Roles.md](docs/28-sistema/81-Roles.md) — Roles y permisos.
- [docs/28-sistema/82-Sesiones.md](docs/28-sistema/82-Sesiones.md) — Sesiones activas.
- [docs/28-sistema/88-Auth-Limpieza-Tokens-Reenvio-Invitacion.md](docs/28-sistema/88-Auth-Limpieza-Tokens-Reenvio-Invitacion.md) — Limpieza de tokens y reenvío de invitación.
- [docs/28-sistema/89-Auth-Contrasenas-Eliminadas.md](docs/28-sistema/89-Auth-Contrasenas-Eliminadas.md) — Contexto de eliminación de contraseñas.

---

## Gestión de secretos y variables de entorno

- **Nunca** versionar `.env` ni incluir claves, contraseñas de BD o tokens en el repositorio.
- Usar **.env.example** como plantilla sin valores sensibles; cada entorno (local, staging, producción) tiene su propio `.env`.
- Variables críticas típicas: `APP_KEY`, `DB_*`, `REDIS_*`, claves de servicios externos (mail, Azure Document AI, etc.). Documentación de variables: [docs/02-ENVIRONMENT-VARIABLES.md](docs/02-ENVIRONMENT-VARIABLES.md) y [docs/20-fundamentos/03-Configuracion-Entorno.md](docs/20-fundamentos/03-Configuracion-Entorno.md).
- En producción, los secretos deben gestionarse mediante mecanismos seguros (variables de entorno del host o gestor de secretos), no en archivos commiteados.

---

## Datos y multi-tenant

- El sistema es **multi-tenant**: una base de datos central (empresas/tenants) y una base de datos por tenant para datos de negocio.
- El aislamiento de datos entre tenants es un requisito de seguridad; la identificación del tenant (p. ej. subdominio o cabecera `X-Tenant`) y el uso correcto de la conexión de BD por tenant están documentados en [docs/15-MULTI-TENANT-SPECIFICS.md](docs/15-MULTI-TENANT-SPECIFICS.md) y [docs/20-fundamentos/01-Arquitectura-Multi-Tenant.md](docs/20-fundamentos/01-Arquitectura-Multi-Tenant.md).
- No exponer información de un tenant a otro; validar siempre el contexto tenant en endpoints y jobs.

---

## Reporting de vulnerabilidades

- Si detectas una vulnerabilidad de seguridad en este proyecto, **no** la abras como issue público.
- Contacta de forma privada al mantenedor del repositorio o al equipo indicado en el proyecto (email, canal seguro) con una descripción clara y pasos de reproducción si es posible.
- Se evaluará y se dará respuesta en un plazo razonable; las correcciones se coordinarán antes de hacer pública la existencia del problema si aplica.

*(Si el proyecto define un email o proceso concreto de reporting, actualizar esta sección.)*

---

## Véase también

- [docs/02-ENVIRONMENT-VARIABLES.md](docs/02-ENVIRONMENT-VARIABLES.md) — Variables de entorno.
- [docs/15-MULTI-TENANT-SPECIFICS.md](docs/15-MULTI-TENANT-SPECIFICS.md) — Multi-tenant y seguridad de datos.
- [docs/08-API-REST.md](docs/08-API-REST.md) — API y autenticación de requests.
- [docs/11-DEPLOYMENT/11c-PRODUCTION.md](docs/11-DEPLOYMENT/11c-PRODUCTION.md) — Despliegue en producción (configuración segura).

---

## Historial de cambios

| Fecha       | Cambio                          |
|------------|----------------------------------|
| 2026-02-13 | Documento creado (FASE 5).      |
