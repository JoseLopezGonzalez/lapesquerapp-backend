# Medición de cobertura de tests — PesquerApp

**Fecha:** 2026-02-15  
**Propósito:** Documentar cómo medir y visualizar la cobertura de tests del backend.

---

## 1. Requisitos

Para generar cobertura de código se requiere una de estas extensiones PHP:

- **pcov** (recomendado, más rápido): `pecl install pcov`
- **Xdebug** (alternativa): `pecl install xdebug` y configurar `xdebug.mode=coverage`

Comprobar disponibilidad:

```bash
php -m | grep -E 'pcov|xdebug'
```

---

## 2. Comandos

### Cobertura en texto (consola)

```bash
php artisan test --coverage
```

O con PHPUnit directamente:

```bash
vendor/bin/phpunit --coverage-text
```

Salida: porcentaje de líneas cubiertas por directorio/clase.

### Cobertura HTML (navegador)

```bash
php artisan test --coverage-html coverage
```

O:

```bash
vendor/bin/phpunit --coverage-html coverage
```

Genera la carpeta `coverage/`. Abrir `coverage/index.html` en el navegador para explorar la cobertura por archivo.

**Nota:** La carpeta `coverage/` está en `.gitignore`; no se versiona. La configuración de coverage en `phpunit.xml` define el directorio `app/` como incluido; el informe se genera solo cuando se usa `--coverage` o `--coverage-html`.

### Sin cobertura (solo ejecutar tests)

```bash
php artisan test
```

---

## 3. Configuración

La configuración de cobertura está en `phpunit.xml`:

```xml
<source>
    <include>
        <directory>app</directory>
    </include>
</source>
```

- **app/**: directorio incluido en la medición.

Los informes (texto o HTML) se generan mediante las opciones de línea de comandos (`--coverage`, `--coverage-html`, etc.).

---

## 4. Objetivo de cobertura

Según `CLAUDE.md` y el plan CORE:

- **Objetivo:** ≥ 80% en bloques críticos (ventas, producción, recepciones, despachos, inventario).
- **Estrategia:** Priorizar flujos de negocio críticos; no buscar 100% global.

Bloques con tests Feature: Auth, Order, Customer, Productos, Stock, Settings, Fichajes, Label, Catalogos, Suppliers, Documents, Tenant, OrderStatistics, Infraestructura.

---

## 5. CI/CD

Para integrar cobertura en pipelines (GitHub Actions, GitLab CI, etc.):

1. Instalar extensión pcov (o xdebug) en el runner.
2. Ejecutar: `php artisan test --coverage --min=80` (si se desea umbral mínimo).
3. Opcional: subir informe a Codecov, Coveralls u otro servicio.
