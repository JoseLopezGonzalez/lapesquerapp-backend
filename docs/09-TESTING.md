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

1. [Inventario de tests](#inventario-de-tests)
2. [Instrucciones para ejecutar tests](#instrucciones-para-ejecutar-tests)
3. [Entorno de testing](#entorno-de-testing)
4. [Estructura de tests](#estructura-de-tests)
5. [Ejecutar tests (comandos)](#ejecutar-tests-comandos)
6. [Configuración PHPUnit](#configuración-phpunit)
7. [Ejemplos](#ejemplos)
8. [Véase también](#véase-también)

---

## Inventario de tests

**Total: 33+ tests** (ejecuta `php artisan test` para ver el resultado actual).

| Archivo | Tests | Descripción |
|--------|-------|-------------|
| **Unit** | | |
| `tests/Unit/Services/OrderStoreServiceTest.php` | 1 | `test_store_creates_order_with_minimal_data` — crea pedido con datos mínimos. |
| `tests/Unit/Services/OrderUpdateServiceTest.php` | 2 | Validación load_date y cambio de buyer_reference. |
| `tests/Unit/Services/OrderDetailServiceTest.php` | 1 | `test_get_order_for_detail_returns_order_with_relations`. |
| `tests/Unit/Services/OrderListServiceTest.php` | 4 | options, active, list (con y sin active). |
| **Feature** | | |
| `tests/Feature/OrderApiTest.php` | 1 | `test_can_create_order_via_api_with_tenant_and_auth` — POST /api/v2/orders con tenant + Sanctum. |
| `tests/Feature/CustomerApiTest.php` | 5 | list, create, show, update, destroy customer. |
| `tests/Feature/LabelApiTest.php` | 10 | list, options, create, show, update, destroy, duplicate, validación. |
| `tests/Feature/StockBlockApiTest.php` | 11 | Bloque Inventario/Stock: list receptions/pallets/stores, stock stats, reception/dispatch chart data (422/200), show store, auth 401. |
| `tests/Feature/ApiDocumentationTest.php` | 4 | Scribe, OpenAPI, header X-Tenant, esquema de autenticación. |

**Tests que requieren base de datos:** OrderStoreServiceTest, OrderUpdateServiceTest, OrderDetailServiceTest, OrderListServiceTest, OrderApiTest, CustomerApiTest, LabelApiTest y StockBlockApiTest usan el trait `ConfiguresTenantConnection` y necesitan MySQL (o BD configurada) y migraciones de `database/migrations/companies` aplicadas en la BD de testing.

---

## Instrucciones para ejecutar tests

### Requisitos previos

1. **Base de datos:** Debe existir la BD indicada en tu entorno (p. ej. `testing`). Si usas Sail, la BD se crea al levantar los contenedores.
2. **Variables de entorno:** En tests se usa `APP_ENV=testing`, `DB_DATABASE=testing` (definido en `phpunit.xml`). Si ejecutas **en el host** y tu `.env` tiene `DB_HOST=mysql`, la config usa `127.0.0.1` en testing para evitar error de resolución (ver `config/database.php`).
3. **MySQL accesible:** Los tests de Ventas (OrderStore*, OrderUpdate*, OrderDetail*, OrderList*, OrderApiTest) hacen conexión real; si no hay MySQL, se marcan como *skipped* con un mensaje claro.

### Paso a paso

**Opción A — Con Docker Sail (recomendado si usas Sail):**

```bash
# Desde la raíz del proyecto, con los contenedores levantados (sail up -d)
./vendor/bin/sail test
# o
sail test
```

**Opción B — En local (sin Docker):**

```bash
# 1. Asegúrate de tener MySQL en marcha y la BD "testing" creada (o la que uses).
# 2. Opcional: limpiar caché de config para que se use DB_CONNECT_TIMEOUT, etc.
php artisan config:clear

# 3. Ejecutar todos los tests
php artisan test
```

**Ejecutar solo parte de los tests:**

```bash
# Solo tests unitarios (no tocan HTTP ni tenant de forma pesada; algunos sí usan BD)
php artisan test --testsuite=Unit

# Solo tests de integración/API
php artisan test --testsuite=Feature

# Solo el módulo Ventas (servicios + API de pedidos)
php artisan test tests/Unit/Services/OrderStoreServiceTest.php tests/Unit/Services/OrderUpdateServiceTest.php tests/Unit/Services/OrderDetailServiceTest.php tests/Unit/Services/OrderListServiceTest.php tests/Feature/OrderApiTest.php

# Un solo archivo
php artisan test tests/Unit/Services/OrderStoreServiceTest.php

# Por nombre de clase o método (filter)
php artisan test --filter OrderStoreServiceTest
php artisan test --filter test_store_creates_order

# Solo tests de documentación (OpenAPI / Scribe)
php artisan test --filter ApiDocumentationTest
```

**Si los tests “no hacen nada” o tardan mucho:**  
Puede deberse a que `DB_HOST=mysql` no resuelve en tu máquina. La app en testing usa entonces `127.0.0.1`; asegúrate de tener MySQL escuchando en `127.0.0.1:3306` o ejecuta los tests dentro de Sail.

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
- **tests/TestCase.php** — Base para tests (crea la aplicación Laravel).
- **tests/CreatesApplication.php** — Trait para boot de la aplicación (usado por TestCase).
- **tests/Concerns/ConfiguresTenantConnection.php** — Configura conexión tenant y migraciones companies en tests que usan BD.

Todos los tests actuales extienden `Tests\TestCase` y pueden usar RefreshDatabase y el trait de tenant para tests de API v2 y servicios.

---

## Ejecutar tests (comandos)

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
php artisan test tests/Unit/Services/OrderStoreServiceTest.php
php artisan test --filter OrderStoreServiceTest
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

**Ejemplo de test unitario (servicio con BD y tenant):**

```php
namespace Tests\Unit\Services;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ConfiguresTenantConnection;
use Tests\TestCase;

class MiServicioTest extends TestCase
{
    use RefreshDatabase;
    use ConfiguresTenantConnection;

    protected function setUp(): void
    {
        $this->ensureDatabaseReachable();
        parent::setUp();
        $this->setUpTenantConnection();
    }

    public function test_algo(): void
    {
        $this->assertTrue(true);
    }
}
```

**Ejemplo de test de feature (API v2 con tenant y auth):** ver `tests/Feature/OrderApiTest.php` para POST con `X-Tenant` y Sanctum.

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
| 2026-02-14 | Añadido inventario de tests (14 tests, 7 archivos) e instrucciones detalladas de ejecución. |
| 2026-02-14 | Eliminados tests por defecto ExampleTest (Unit y Feature); inventario actualizado (12 tests, 5 archivos). |
| 2026-02-14 | Añadido StockBlockApiTest (11 tests) para bloque Inventario/Stock; inventario 33+ tests. |
