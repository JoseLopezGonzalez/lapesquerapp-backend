---
title: Testing
description: Estrategia de pruebas (PHPUnit, unitarias, feature) y cómo ejecutarlas en PesquerApp Backend.
updated: 2026-02-13
audience: Backend Engineers
---

# Testing

## Propósito

Documentar la estrategia de tests del backend (PHPUnit), la estructura de pruebas (Unit, Feature), el entorno de testing y cómo ejecutar y extender las pruebas en local y con Sail.

## Audiencia

Desarrolladores backend.

---

## Tabla de contenidos

1. [Entorno de testing](#entorno-de-testing)
2. [Estructura de tests](#estructura-de-tests)
3. [Ejecutar tests](#ejecutar-tests)
4. [Configuración PHPUnit](#configuración-phpunit)
5. [Ejemplos](#ejemplos)
6. [Véase también](#véase-también)

---

## Entorno de testing

- **APP_ENV:** `testing`
- **Base de datos:** `DB_DATABASE=testing` (crear BD `testing` en tu entorno o usar SQLite en memoria si se configura).
- **Caché / colas / sesión:** `array` o `sync` para que los tests no dependan de Redis ni de workers.
- **Mail:** `array` (no se envían correos).
- No se requiere tenant activo para los tests básicos; los tests que usen multi-tenant deben configurar el contexto (tenant) según sea necesario.

---

## Estructura de tests

- **tests/Unit/** — Pruebas unitarias (lógica aislada).
- **tests/Feature/** — Pruebas de integración/API (HTTP, base de datos).
- **tests/TestCase.php** — Base para tests (crea la aplicación).
- **tests/CreatesApplication.php** — Trait para boot de la aplicación.

El proyecto incluye ejemplos: `tests/Unit/ExampleTest.php`, `tests/Feature/ExampleTest.php`. A partir de ellos se pueden añadir tests de modelos, servicios y endpoints de la API v2.

---

## Ejecutar tests

**En local (sin Sail):**

```bash
php artisan test
# o
./vendor/bin/phpunit
```

**Con Docker Sail:**

```bash
./vendor/bin/sail test
# o, si el alias sail está disponible:
sail test
```

**Solo un testsuite:**

```bash
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature
```

**Un archivo o clase concreta:**

```bash
php artisan test tests/Unit/ExampleTest.php
php artisan test --filter ExampleTest
```

---

## Configuración PHPUnit

En la raíz del proyecto: **phpunit.xml**.

- **Testsuites:** `Unit` (tests/Unit), `Feature` (tests/Feature).
- **Source (cobertura):** se incluye el directorio `app` (para cobertura de código si se habilita).
- **Variables de entorno en tests:** `APP_ENV=testing`, `CACHE_DRIVER=array`, `QUEUE_CONNECTION=sync`, `DB_DATABASE=testing`, `MAIL_MAILER=array`, `SESSION_DRIVER=array`.

Asegurar que exista la base de datos `testing` o ajustar `phpunit.xml` / `.env.testing` si se usa otra configuración (por ejemplo SQLite en memoria).

---

## Ejemplos

**Ejemplo de test unitario (estructura típica):**

```php
// tests/Unit/ExampleTest.php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    public function test_that_true_is_true(): void
    {
        $this->assertTrue(true);
    }
}
```

**Ejemplo de test de feature (Laravel):**

```php
// tests/Feature/ExampleTest.php
namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);
    }
}
```

Para tests de API v2: usar `$this->get('/api/v2/...')` o `$this->post(...)` con headers (p. ej. `X-Tenant`, `Authorization`) según [08-API-REST.md](./08-API-REST.md) y [fundamentos/02-Autenticacion-Autorizacion.md](./20-fundamentos/02-Autenticacion-Autorizacion.md).

---

## Decisiones y trade-offs

- **QUEUE_CONNECTION=sync** en tests: los jobs se ejecutan de forma síncrona; no hace falta levantar un worker.
- **RefreshDatabase:** en Feature tests permite resetear la BD por test; en proyectos multi-tenant puede requerir inicializar la BD central y/o tenant según el caso.
- Cobertura: el proyecto tiene la estructura lista para añadir más Unit/Feature tests; la prioridad puede ser endpoints críticos y lógica de negocio compleja (p. ej. producción, pedidos).

---

## Véase también

- [01-SETUP-LOCAL.md](./01-SETUP-LOCAL.md) — Entorno local y Sail.
- [08-API-REST.md](./08-API-REST.md) — API v2 (endpoints a probar).
- [02-ENVIRONMENT-VARIABLES.md](./02-ENVIRONMENT-VARIABLES.md) — Variables que afectan a tests (DB_DATABASE, etc.).
- [12-TROUBLESHOOTING/COMMON-ERRORS.md](./12-TROUBLESHOOTING/COMMON-ERRORS.md) — Errores comunes.

---

## Historial de cambios

| Fecha       | Cambio                          |
|------------|----------------------------------|
| 2026-02-13 | Documento creado (FASE 5).      |
