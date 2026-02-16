# Guía de documentación API de PesquerApp

## Visión general

La API de PesquerApp se documenta con **Scribe** (knuckleswtf/scribe), generando:

- Documentación HTML en `public/docs/`
- Especificación **OpenAPI 3.1.0** (`public/docs/openapi.yaml`)
- Colección **Postman** (`public/docs/collection.json`)

## Cómo ver la documentación (UI)

La documentación HTML está en `public/docs/`. En desarrollo el backend se levanta con **Docker Sail** (ver [Deploy en desarrollo](instrucciones/deploy-desarrollo.md)).

1. **Con Sail (entorno de desarrollo del proyecto)**:
   ```bash
   ./vendor/bin/sail up -d
   ```
   Luego en el navegador:
   - Si usas puerto **80**: **http://localhost/docs**
   - Si usas **APP_PORT=8000** (p. ej. en WSL): **http://localhost:8000/docs**

   La misma base que el backend; solo añade `/docs`.

2. **Con el servidor embebido de Laravel** (sin Sail):
   ```bash
   php artisan serve
   ```
   **http://localhost:8000/docs**

3. **Sin servidor**: abre el archivo en el navegador:
   ```text
   file:///ruta/al/proyecto/public/docs/index.html
   ```
   Algunos enlaces o “Try it out” pueden no funcionar bien con `file://`.

### Acceso en producción

Con el tipo **static**, la documentación son archivos en `public/docs/`. El servidor web los sirve como contenido estático **sin pasar por Laravel**, así que:

- **Por defecto es pública**: en producción, `https://tu-dominio.com/docs` (o `/docs/index.html`) es accesible por cualquiera; no hay middleware ni autenticación.

Si quieres restringir el acceso en producción puedes, por ejemplo:

- **No desplegar** la carpeta `public/docs/` (ver más abajo).
- **Proteger con el servidor web**: en nginx/Apache, autenticación HTTP básica o regla por IP para `/docs`.
- **Cambiar a tipo `laravel`** en `config/scribe.php`: la documentación se sirve por una ruta de Laravel y puedes aplicar middleware (p. ej. solo en entorno local o con auth).

#### Cómo no desplegar la doc en producción

Así la doc solo existe en desarrollo (o donde tú la generes); en producción la ruta `/docs` no existirá (404).

1. **Excluir del repositorio (recomendado)**  
   `public/docs/` está en **`.gitignore`**. No se hace commit de la documentación generada.  
   - En **producción**: el deploy (Git → build Docker/Coolify) no incluye `public/docs/`, así que la imagen no tiene esa carpeta y `/docs` da 404.  
   - En **desarrollo**: cada persona (o tu CI) ejecuta `./scripts/generate-docs.sh` y tiene la doc en local.  
   - Si `public/docs/` estaba ya versionado, deja de trackearlo con: `git rm -r --cached public/docs` y luego commit.

2. **Excluir solo del build Docker** (si quisieras commitear la doc pero no servirla en prod):  
   Crea o edita **`.dockerignore`** en la raíz del proyecto y añade una línea:
   ```text
   public/docs
   ```
   Así la carpeta no se copia al construir la imagen y en producción no existirá.

## Requisitos

- Laravel 10, PHP 8.1+
- Header obligatorio en todas las peticiones: **`X-Tenant`** (identificador del tenant)
- Autenticación: **Bearer** (Sanctum) en el header `Authorization`

## Regenerar documentación

### Manual

```bash
# Opción recomendada: script
./scripts/generate-docs.sh

# O directamente
php artisan scribe:generate
```

### Archivos generados

| Archivo            | Descripción                    |
|--------------------|--------------------------------|
| `public/docs/index.html` | Documentación HTML navegable   |
| `public/docs/openapi.yaml` | OpenAPI 3.1.0 para IA / clientes |
| `public/docs/collection.json` | Colección Postman              |

## Cómo documentar nuevos endpoints

### 1. Docblocks en el controller

```php
/**
 * Título del endpoint
 *
 * Descripción detallada.
 *
 * @group NombreDelGrupo
 * @queryParam filter string Filtro opcional. Example: active
 * @queryParam page integer Página. Example: 1
 */
public function index(Request $request) { ... }
```

### 2. FormRequests con @bodyParam

En la clase FormRequest:

```php
/**
 * @bodyParam name string required Nombre. Example: Ejemplo
 * @bodyParam price numeric required Precio. Example: 25.50
 */
class StoreSomethingRequest extends FormRequest { ... }
```

### 3. Atributos PHP (Scribe 5)

Si prefieres atributos:

```php
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;

#[Group("Products", "Descripción")]
#[QueryParam('filter', 'string', 'Filtro', required: false, example: 'active')]
public function index(Request $request) { ... }
```

## Configuración relevante

- **Config:** `config/scribe.php`
- **Rutas documentadas:** prefijo `api/*`
- **Exclusiones:** p. ej. `GET /api/health`
- **Grupos por defecto:** Authentication, Users, Products, Orders, Production, Reports

## Uso con IA (Cursor / ChatGPT)

1. Comparte o referencia `public/docs/openapi.yaml`.
2. Reglas para la IA:
   - Todos los requests deben incluir el header `X-Tenant: {tenant_id}`.
   - Requests autenticados: `Authorization: Bearer {token}`.
   - Usar solo endpoints y esquemas definidos en OpenAPI.

## Tests

```bash
php artisan test --filter ApiDocumentationTest
```

Comprueban que `scribe:generate` termina bien y que en la documentación aparecen OpenAPI, X-Tenant y el esquema de autenticación.

## Referencias

- [Scribe para Laravel](https://scribe.knuckles.wtf/laravel/)
- [OpenAPI 3.1](https://spec.openapis.org/oas/v3.1.0)

---

**Última actualización:** 2026-02-14 · Scribe v5.x
