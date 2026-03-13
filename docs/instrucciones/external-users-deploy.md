---
title: External Users Deploy
description: Despliegue de la funcionalidad External Users en desarrollo, staging y producción.
updated: 2026-03-13
audience: Backend Engineers
---

# External Users Deploy

## Resumen

La implementación de `ExternalUser` añade solo **migraciones tenant** y cambios de código backend.

No hay migraciones de base central.
No deben ejecutarse seeders de desarrollo en producción.

## Qué se ha añadido

- Tabla tenant `external_users`
- Campos tenant nuevos en `stores`:
  - `store_type`
  - `external_user_id`
- Nuevas rutas API para:
  - CRUD admin de `external-users`
  - autenticación unificada de actores internos/externos
  - acceso restringido de usuarios externos a stores y pallets

## Desarrollo local

### Opción recomendada con Sail

Arranca Docker/Sail y ejecuta:

```bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan tenants:dev-migrate --seed
```

Esto:

- aplica las migraciones tenant en la BD local de desarrollo
- ejecuta `TenantDatabaseSeeder`
- incluye los seeders nuevos:
  - `ExternalUsersSeeder`
  - `ExternalStoresSeeder`

### Opción host sin Sail

Solo úsala si tu MySQL local está realmente accesible desde el `.env`.

```bash
php artisan tenants:dev-migrate --seed
```

Si falla con `getaddrinfo for mysql failed`, tu entorno local no está levantado o tu `DB_HOST` no es resoluble fuera de Docker.

## Staging

En staging la recomendación es **no usar `tenants:dev-migrate`** salvo que staging esté montado exactamente como entorno local de desarrollo con una sola BD tenant compartida.

Si staging usa tenants reales registrados en la base central, ejecuta:

```bash
php artisan tenants:migrate
```

Si necesitas poblar datos de prueba en staging, revisa primero la política de datos del entorno. El seeder completo `TenantDatabaseSeeder` incluye datos de desarrollo y normalmente no debe ejecutarse en un staging integrado con datos reales.

## Producción

### Comando correcto

En producción ejecuta únicamente:

```bash
php artisan tenants:migrate
```

### No ejecutar en producción

No ejecutes:

```bash
php artisan tenants:dev-migrate
php artisan tenants:dev-migrate --seed
php artisan db:seed --database=tenant --class=TenantDatabaseSeeder
```

Esos comandos son para desarrollo y pueden introducir datos de ejemplo.

### Orden recomendado de despliegue

1. Desplegar código nuevo.
2. Ejecutar `php artisan optimize:clear`.
3. Ejecutar `php artisan tenants:migrate`.
4. Reiniciar workers si aplica:

```bash
php artisan queue:restart
```

5. Verificar manualmente el flujo.

## Validación post-deploy

### Validación mínima backend

1. Entrar con un usuario interno administrador.
2. Crear un `external_user`.
3. Crear o editar un `store` con:
   - `store_type = externo`
   - `external_user_id` asignado
4. Reenviar acceso al usuario externo.
5. Verificar login OTP/magic link del usuario externo.
6. Verificar que `GET /api/v2/me` devuelve:
   - `actorType = external_user`
   - `externalUserType = maquilador`
   - `allowedStoreIds` con sus almacenes
7. Verificar que el externo:
   - ve solo sus almacenes
   - crea/edita palets solo en sus almacenes
   - puede mover palets solo entre sus almacenes
   - no puede entrar en `/users`, `/sessions`, `/orders`, estadísticas
8. Desactivar el usuario externo y comprobar que sus sesiones quedan revocadas.

## Observaciones importantes

- La funcionalidad depende del correo tenant; si el mail del tenant está mal configurado, el acceso externo no podrá enviarse.
- El frontend debe usar el nuevo contrato de sesión:
  - `actorType`
  - `externalUserType`
  - `allowedStoreIds`
- La seguridad está en backend; el frontend solo adapta navegación/render.

## Estado de ejecución en este entorno

Intenté ejecutar en este workspace:

```bash
php artisan tenants:dev-migrate --seed
```

Resultado: fallo por resolución de `mysql`.

Intenté también:

```bash
./vendor/bin/sail artisan tenants:dev-migrate --seed
```

Resultado: `Docker is not running`.

Conclusión: el código está preparado, pero la aplicación real de migraciones/seeders queda pendiente de ejecutarse en un entorno con MySQL o Sail levantado.
