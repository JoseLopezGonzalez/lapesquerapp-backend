# PROMPT COMPLETO: Implementación de Scribe para Documentación API en PesquerApp

## 🎯 CONTEXTO DEL PROYECTO

Eres un agente IA especializado en Laravel trabajando en **PesquerApp**, un ERP multi-tenant para la industria pesquera desarrollado con:

* **Backend:** Laravel 10
* **Frontend:** Next.js 16
* **Arquitectura:** Multi-tenant con base de datos separada por tenant
* **Autenticación:** Laravel Sanctum con tokens Bearer
* **Header obligatorio:**`X-Tenant` para resolución de tenant

## 🎯 OBJETIVO DE ESTA TAREA

Implementar **Scribe** (knuckleswtf/scribe) para generar documentación API profesional que:

1. Genere documentación HTML navegable para humanos
2. Exporte especificación OpenAPI 3.1.0 para consumo por IA (Cursor, ChatGPT)
3. Incluya colección Postman
4. Documente correctamente la arquitectura multi-tenant
5. Sirva como contrato oficial de la API

## 📋 REQUISITOS PREVIOS

Antes de empezar, verifica:

* [ ] Laravel 10 instalado y funcionando
* [ ] Composer disponible
* [ ] Rutas API definidas en `routes/api.php`
* [ ] Controllers en `app/Http/Controllers/Api/`
* [ ] FormRequests o validación en controllers
* [ ] API Resources en `app/Http/Resources/`

## 🚀 INSTRUCCIONES PASO A PASO

### PASO 1: Instalación de Scribe

```bash
# Instalar Scribe como dependencia de desarrollo
composer require --dev knuckleswtf/scribe

# Publicar archivo de configuración
php artisan vendor:publish --tag=scribe-config
```

**Verificación:**

* Debe aparecer el archivo `config/scribe.php`
* Confirmar que se instaló correctamente ejecutando `php artisan list` y verificar que aparece el comando `scribe:generate`

---

### PASO 2: Configuración Base de Scribe

Edita el archivo `config/scribe.php` con la siguiente configuración adaptada a PesquerApp:

```php
<?php

return [
    /*
     * Tipo de output: 'static' genera HTML en public/docs
     * 'laravel' crea una ruta /docs en la aplicación
     */
    'type' => 'static',

    /*
     * Configuración básica de la documentación
     */
    'title' => 'PesquerApp API Documentation',
    'description' => 'Documentación oficial de la API REST de PesquerApp - Sistema ERP para la industria pesquera',
    'base_url' => env('APP_URL', 'http://localhost'),
  
    /*
     * Rutas a documentar
     */
    'routes' => [
        [
            'match' => [
                'prefixes' => ['api/*'],
                'domains' => ['*'],
            ],
            'include' => [
                // Incluir todas las rutas que comiencen con api/
            ],
            'exclude' => [
                // Excluir rutas internas o de debug
                'api/debug/*',
                'api/internal/*',
            ],
        ],
    ],

    /*
     * Autenticación
     */
    'auth' => [
        'enabled' => true,
        'default' => false,
        'in' => 'bearer',
        'name' => 'Authorization',
        'use_value' => env('SCRIBE_AUTH_TOKEN', 'your-token-here'),
        'placeholder' => '{YOUR_AUTH_TOKEN}',
        'extra_info' => 'Obtén tu token de autenticación mediante el endpoint /api/auth/login. El token debe incluirse en el header Authorization con el prefijo "Bearer ".',
    ],

    /*
     * Headers adicionales (CRÍTICO para multi-tenant)
     */
    'headers' => [
        'X-Tenant' => 'demo-tenant',
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
    ],

    /*
     * Información adicional sobre headers en la documentación
     */
    'example_languages' => [
        'bash',
        'javascript',
        'php',
        'python',
    ],

    /*
     * Generar ejemplos reales llamando a los endpoints
     */
    'strategies' => [
        'metadata' => [
            \Knuckles\Scribe\Extracting\Strategies\Metadata\GetFromDocBlocks::class,
        ],
        'urlParameters' => [
            \Knuckles\Scribe\Extracting\Strategies\UrlParameters\GetFromLaravelAPI::class,
            \Knuckles\Scribe\Extracting\Strategies\UrlParameters\GetFromUrlParamAttribute::class,
        ],
        'queryParameters' => [
            \Knuckles\Scribe\Extracting\Strategies\QueryParameters\GetFromFormRequest::class,
            \Knuckles\Scribe\Extracting\Strategies\QueryParameters\GetFromInlineValidator::class,
            \Knuckles\Scribe\Extracting\Strategies\QueryParameters\GetFromQueryParamAttribute::class,
        ],
        'headers' => [
            \Knuckles\Scribe\Extracting\Strategies\Headers\GetFromRouteRules::class,
            \Knuckles\Scribe\Extracting\Strategies\Headers\GetFromHeaderAttribute::class,
        ],
        'bodyParameters' => [
            \Knuckles\Scribe\Extracting\Strategies\BodyParameters\GetFromFormRequest::class,
            \Knuckles\Scribe\Extracting\Strategies\BodyParameters\GetFromInlineValidator::class,
            \Knuckles\Scribe\Extracting\Strategies\BodyParameters\GetFromBodyParamAttribute::class,
        ],
        'responses' => [
            \Knuckles\Scribe\Extracting\Strategies\Responses\UseResponseAttributes::class,
            \Knuckles\Scribe\Extracting\Strategies\Responses\UseApiResourceTags::class,
            \Knuckles\Scribe\Extracting\Strategies\Responses\UseTransformerTags::class,
            \Knuckles\Scribe\Extracting\Strategies\Responses\ResponseCalls::class,
            \Knuckles\Scribe\Extracting\Strategies\Responses\UseResponseFileTag::class,
        ],
        'responseFields' => [
            \Knuckles\Scribe\Extracting\Strategies\ResponseFields\GetFromResponseFieldAttribute::class,
        ],
    ],

    /*
     * Configuración de llamadas a endpoints para ejemplos
     */
    'apply' => [
        'response_calls' => [
            'methods' => ['GET'],
            'config' => [
                'app.env' => 'documentation',
            ],
            'headers' => [
                'X-Tenant' => 'demo-tenant',
            ],
        ],
    ],

    /*
     * Fractal transformer
     */
    'fractal' => [
        'serializer' => null,
    ],

    /*
     * Postman collection
     */
    'postman' => [
        'enabled' => true,
        'overrides' => [
            'info.version' => '1.0.0',
        ],
    ],

    /*
     * OpenAPI (Swagger) Specification
     */
    'openapi' => [
        'enabled' => true,
        'overrides' => [
            'info.version' => '1.0.0',
            'info.contact' => [
                'name' => 'PesquerApp Support',
                'email' => env('SUPPORT_EMAIL', '[email protected]'),
            ],
        ],
    ],

    /*
     * Configuración de grupos
     */
    'groups' => [
        'default' => 'Endpoints',
        'order' => [
            'Authentication',
            'Users',
            'Products',
            'Orders',
            'Production',
            'Reports',
        ],
    ],

    /*
     * Logo (opcional)
     */
    'logo' => false,

    /*
     * Último paso de modificación
     */
    'last_updated' => null,

    /*
     * Ejemplos de parámetros
     */
    'faker_seed' => null,
];
```

**Notas importantes sobre la configuración:**

1. **`X-Tenant` header:** Es CRÍTICO para tu arquitectura multi-tenant. Todos los ejemplos lo incluirán.
2. **`type => 'static'`:** Genera archivos HTML estáticos en `public/docs`. Cambiar a `'laravel'` si prefieres ruta dinámica.
3. **`auth`:** Configurado para Sanctum con Bearer tokens.
4. **`strategies`:** Define cómo Scribe extrae información (FormRequests, validation rules, etc.)
5. **`openapi.enabled => true`:** ESENCIAL para generar el archivo que consumirá Cursor.

---

### PASO 3: Documentar Endpoints con Atributos PHP

Scribe puede extraer mucha información automáticamente, pero para documentación de calidad necesitas añadir atributos PHP o docblocks a tus controllers.

**Ejemplo de Controller Documentado:**

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ProductCollection;
use App\Models\Product;
use Illuminate\Http\Request;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;
use Knuckles\Scribe\Attributes\UrlParam;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\Header;

#[Group("Products", "Gestión de productos pesqueros")]
class ProductController extends Controller
{
    /**
     * Listar productos
     * 
     * Obtiene una lista paginada de todos los productos disponibles en el sistema.
     * Soporta filtrado por categoría, caladero y estado de procesamiento.
     *
     * @authenticated
     */
    #[Authenticated]
    #[Header('X-Tenant', 'demo-tenant', 'Identificador del tenant (obligatorio)')]
    #[QueryParam('category', 'string', 'Filtrar por categoría de producto', required: false, example: 'pescado-blanco')]
    #[QueryParam('caladero', 'string', 'Filtrar por caladero de origen', required: false, example: 'golfo-cadiz')]
    #[QueryParam('page', 'integer', 'Número de página para paginación', required: false, example: 1)]
    #[QueryParam('per_page', 'integer', 'Elementos por página (max: 100)', required: false, example: 15)]
    #[ResponseFromApiResource(ProductCollection::class, Product::class, collection: true, paginate: 15)]
    public function index(Request $request)
    {
        $query = Product::query();

        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        if ($request->has('caladero')) {
            $query->where('caladero', $request->caladero);
        }

        $products = $query->paginate($request->get('per_page', 15));

        return new ProductCollection($products);
    }

    /**
     * Crear producto
     * 
     * Crea un nuevo producto en el sistema con todos sus atributos.
     * Requiere autenticación y permisos de administrador.
     *
     * @authenticated
     */
    #[Authenticated]
    #[Header('X-Tenant', 'demo-tenant', 'Identificador del tenant (obligatorio)')]
    #[ResponseFromApiResource(ProductResource::class, Product::class, status: 201)]
    #[Response(['message' => 'Validation failed'], 422, 'Datos de validación incorrectos')]
    public function store(StoreProductRequest $request)
    {
        $product = Product::create($request->validated());

        return new ProductResource($product);
    }

    /**
     * Mostrar producto
     * 
     * Obtiene los detalles completos de un producto específico por su ID.
     *
     * @authenticated
     */
    #[Authenticated]
    #[Header('X-Tenant', 'demo-tenant', 'Identificador del tenant (obligatorio)')]
    #[UrlParam('id', 'integer', 'ID del producto', required: true, example: 1)]
    #[ResponseFromApiResource(ProductResource::class, Product::class)]
    #[Response(['message' => 'Product not found'], 404, 'Producto no encontrado')]
    public function show(Product $product)
    {
        return new ProductResource($product);
    }

    /**
     * Actualizar producto
     * 
     * Actualiza los datos de un producto existente.
     *
     * @authenticated
     */
    #[Authenticated]
    #[Header('X-Tenant', 'demo-tenant', 'Identificador del tenant (obligatorio)')]
    #[UrlParam('id', 'integer', 'ID del producto', required: true, example: 1)]
    #[ResponseFromApiResource(ProductResource::class, Product::class)]
    #[Response(['message' => 'Product not found'], 404, 'Producto no encontrado')]
    public function update(UpdateProductRequest $request, Product $product)
    {
        $product->update($request->validated());

        return new ProductResource($product);
    }

    /**
     * Eliminar producto
     * 
     * Elimina un producto del sistema (soft delete).
     *
     * @authenticated
     */
    #[Authenticated]
    #[Header('X-Tenant', 'demo-tenant', 'Identificador del tenant (obligatorio)')]
    #[UrlParam('id', 'integer', 'ID del producto', required: true, example: 1)]
    #[Response(['message' => 'Product deleted successfully'], 200)]
    #[Response(['message' => 'Product not found'], 404, 'Producto no encontrado')]
    public function destroy(Product $product)
    {
        $product->delete();

        return response()->json([
            'message' => 'Product deleted successfully'
        ]);
    }
}
```

**Atributos PHP más importantes:**

* `#[Group("Nombre", "Descripción")]` - Agrupa endpoints
* `#[Authenticated]` - Marca como requiere autenticación
* `#[Header('X-Tenant', 'value')]` - Documenta headers obligatorios
* `#[QueryParam(...)]` - Documenta parámetros de query string
* `#[UrlParam(...)]` - Documenta parámetros de URL
* `#[ResponseFromApiResource(...)]` - Genera respuesta desde API Resource
* `#[Response([...], status)]` - Respuesta manual de ejemplo

---

### PASO 4: Documentar FormRequests

Scribe puede extraer validaciones automáticamente de FormRequests, pero puedes mejorarlas con docblocks:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @bodyParam name string required El nombre del producto. Example: Merluza del Golfo de Cádiz
 * @bodyParam category string required Categoría del producto. Example: pescado-blanco
 * @bodyParam caladero string required Caladero de origen. Example: golfo-cadiz
 * @bodyParam fao_zone string La zona FAO. Example: 27.9.a
 * @bodyParam caliber string El calibre del producto. Example: G1
 * @bodyParam estado string required Estado de procesamiento. Example: fresco
 * @bodyParam price numeric required Precio por kilogramo en euros. Example: 12.50
 * @bodyParam stock_quantity integer Cantidad en stock (kg). Example: 500
 */
class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'category' => 'required|string|in:pescado-blanco,pescado-azul,marisco,cefalopodo',
            'caladero' => 'required|string|max:100',
            'fao_zone' => 'nullable|string|max:50',
            'caliber' => 'nullable|string|max:20',
            'estado' => 'required|string|in:fresco,congelado,procesado',
            'price' => 'required|numeric|min:0',
            'stock_quantity' => 'nullable|integer|min:0',
        ];
    }
}
```

---

### PASO 5: Preparar Datos de Ejemplo (Seeders)

Para que Scribe genere ejemplos realistas, necesitas datos en la base de datos. Crea o actualiza seeders:

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;

class DocumentationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Solo ejecutar en entorno de documentación
        if (config('app.env') !== 'documentation') {
            return;
        }

        // Crear productos de ejemplo
        Product::create([
            'name' => 'Merluza del Golfo de Cádiz',
            'category' => 'pescado-blanco',
            'caladero' => 'golfo-cadiz',
            'fao_zone' => '27.9.a',
            'caliber' => 'G1',
            'estado' => 'fresco',
            'price' => 12.50,
            'stock_quantity' => 500,
        ]);

        Product::create([
            'name' => 'Gamba Blanca de Huelva',
            'category' => 'marisco',
            'caladero' => 'costa-huelva',
            'fao_zone' => '27.9.a',
            'caliber' => 'Extra',
            'estado' => 'fresco',
            'price' => 45.00,
            'stock_quantity' => 120,
        ]);
    }
}
```

Añadir al `DatabaseSeeder.php`:

```php
public function run(): void
{
    if (config('app.env') === 'documentation') {
        $this->call([
            DocumentationSeeder::class,
        ]);
    }
}
```

---

### PASO 6: Crear Script de Generación

Crea un script bash para automatizar la generación de documentación:

**Archivo: `scripts/generate-docs.sh`**

```bash
#!/bin/bash

echo "🚀 Generando documentación de PesquerApp API..."

# Colores para output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Paso 1: Configurar entorno de documentación
echo -e "${YELLOW}📝 Configurando entorno de documentación...${NC}"
export APP_ENV=documentation

# Paso 2: Preparar base de datos con datos de ejemplo
echo -e "${YELLOW}🗄️  Preparando base de datos de ejemplo...${NC}"
php artisan migrate:fresh --seed --force

# Paso 3: Generar documentación
echo -e "${YELLOW}📚 Generando documentación con Scribe...${NC}"
php artisan scribe:generate

# Verificar si se generó correctamente
if [ -f "public/docs/index.html" ] && [ -f "public/docs/openapi.yaml" ]; then
    echo -e "${GREEN}✅ Documentación generada exitosamente${NC}"
    echo -e "${GREEN}📄 HTML: public/docs/index.html${NC}"
    echo -e "${GREEN}📋 OpenAPI: public/docs/openapi.yaml${NC}"
    echo -e "${GREEN}📮 Postman: public/docs/collection.json${NC}"
    echo ""
    echo -e "${GREEN}🌐 Para visualizar, abre: http://localhost/docs${NC}"
else
    echo -e "${RED}❌ Error al generar documentación${NC}"
    exit 1
fi

# Restaurar entorno
export APP_ENV=local

echo -e "${GREEN}✨ ¡Proceso completado!${NC}"
```

Dar permisos de ejecución:

```bash
chmod +x scripts/generate-docs.sh
```

---

### PASO 7: Generar Documentación

```bash
# Ejecutar el script
./scripts/generate-docs.sh

# O manualmente:
php artisan scribe:generate
```

**Archivos generados:**

* `public/docs/index.html` - Documentación HTML navegable
* `public/docs/openapi.yaml` - Especificación OpenAPI 3.1.0
* `public/docs/collection.json` - Colección de Postman

---

### PASO 8: Configurar .gitignore

Añade al `.gitignore`:

```gitignore
# Scribe generated docs (opcional - puedes commitearlos si quieres)
/public/docs/
/.scribe/

# O si quieres commitear los docs pero no los archivos temporales:
/.scribe/
```

---

### PASO 9: Añadir Ruta de Acceso (Opcional)

Si usas `type => 'static'`, puedes servir los docs directamente desde `public/docs/index.html`.

Si prefieres una ruta más amigable, añade en `routes/web.php`:

```php
// Redirigir /docs a la documentación estática
Route::redirect('/docs', '/docs/index.html');

// O servir dinámicamente (si usaste type => 'laravel')
// La ruta se crea automáticamente en /docs
```

---

### PASO 10: Integrar con Cursor

**Archivo a compartir con Cursor:**`public/docs/openapi.yaml`

**Instrucciones para Cursor:**

1. Copia el contenido de `public/docs/openapi.yaml`
2. En Cursor, crea un archivo `.cursorrules` o añade a tu prompt base:

```markdown
## CONTRATO API DE PESQUERAPP

La API de PesquerApp está documentada en el archivo OpenAPI adjunto.

**Reglas obligatorias:**
1. TODOS los requests deben incluir el header `X-Tenant: {tenant_id}`
2. TODOS los requests autenticados deben incluir `Authorization: Bearer {token}`
3. Los endpoints siguen el contrato especificado en openapi.yaml
4. No inventar endpoints - usar solo los documentados

**Cuando generes código del frontend:**
- Lee primero el contrato OpenAPI
- Genera tipos TypeScript desde las definiciones de schemas
- Usa los ejemplos de request/response como referencia
- Valida que los fetches coincidan con los endpoints documentados

**Archivo OpenAPI:** Ver `public/docs/openapi.yaml`
```

---

### PASO 11: Automatizar Regeneración (CI/CD)

**Opción A: GitHub Actions**

Crear `.github/workflows/generate-docs.yml`:

```yaml
name: Generate API Documentation

on:
  push:
    branches: [ main, develop ]
    paths:
      - 'app/Http/Controllers/Api/**'
      - 'app/Http/Requests/**'
      - 'app/Http/Resources/**'
      - 'routes/api.php'

jobs:
  generate-docs:
    runs-on: ubuntu-latest
  
    steps:
      - uses: actions/checkout@v3
    
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: mbstring, xml, ctype, json, mysql
        
      - name: Install Dependencies
        run: composer install --no-interaction --prefer-dist
      
      - name: Generate Documentation
        run: |
          php artisan scribe:generate
        
      - name: Commit Documentation
        run: |
          git config --local user.email "action@github.com"
          git config --local user.name "GitHub Action"
          git add public/docs/
          git diff --quiet && git diff --staged --quiet || git commit -m "docs: Update API documentation [skip ci]"
          git push
```

**Opción B: Hook de Git Pre-commit**

Crear `.git/hooks/pre-commit`:

```bash
#!/bin/bash

# Detectar cambios en archivos relacionados con la API
CHANGED_FILES=$(git diff --cached --name-only --diff-filter=ACM | grep -E "(app/Http/Controllers/Api|app/Http/Requests|app/Http/Resources|routes/api.php)")

if [ -n "$CHANGED_FILES" ]; then
    echo "🔄 Detectados cambios en la API, regenerando documentación..."
    php artisan scribe:generate
    git add public/docs/
fi
```

---

### PASO 12: Testing y Validación

Crea un test para validar que la documentación se genera correctamente:

**Archivo: `tests/Feature/ApiDocumentationTest.php`**

```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiDocumentationTest extends TestCase
{
    public function test_openapi_spec_is_valid(): void
    {
        $this->artisan('scribe:generate')
            ->assertExitCode(0);

        $this->assertFileExists(public_path('docs/openapi.yaml'));
        $this->assertFileExists(public_path('docs/index.html'));
        $this->assertFileExists(public_path('docs/collection.json'));
    }

    public function test_openapi_spec_contains_tenant_header(): void
    {
        $openapi = file_get_contents(public_path('docs/openapi.yaml'));
      
        $this->assertStringContainsString('X-Tenant', $openapi);
    }

    public function test_openapi_spec_contains_authentication(): void
    {
        $openapi = file_get_contents(public_path('docs/openapi.yaml'));
      
        $this->assertStringContainsString('bearerAuth', $openapi);
    }
}
```

Ejecutar:

```bash
php artisan test --filter ApiDocumentationTest
```

---

## 📚 DOCUMENTACIÓN FINAL PARA EL DESARROLLADOR

Al finalizar, crea este archivo de documentación:

**Archivo: `docs/API_DOCUMENTATION_GUIDE.md`**

```markdown
# Guía de Documentación API de PesquerApp

## 🎯 Visión General

La API de PesquerApp está documentada automáticamente usando **Scribe**, generando:
- Documentación HTML interactiva
- Especificación OpenAPI 3.1.0
- Colección de Postman

## 📍 Acceso a la Documentación

### Documentación HTML
- **Local:** http://localhost/docs
- **Producción:** https://api.pesquerapp.com/docs

### Archivos Exportados
- **OpenAPI Spec:** `public/docs/openapi.yaml`
- **Postman Collection:** `public/docs/collection.json`

## 🔄 Regenerar Documentación

### Automático
La documentación se regenera automáticamente cuando:
- Haces push a `main` o `develop` (GitHub Actions)
- Modificas archivos en `app/Http/Controllers/Api/**`

### Manual

```bash
# Opción 1: Script completo (recomendado)
./scripts/generate-docs.sh

# Opción 2: Solo Scribe
php artisan scribe:generate

# Opción 3: Con datos frescos
php artisan migrate:fresh --seed
php artisan scribe:generate
```

## ✍️ Cómo Documentar Nuevos Endpoints

### 1. Usa Atributos PHP (Recomendado)

```php
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Header;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;

#[Group("Nombre del Grupo", "Descripción")]
#[Authenticated]
#[Header('X-Tenant', 'demo-tenant')]
#[ResponseFromApiResource(YourResource::class, YourModel::class)]
public function yourMethod()
{
    // ...
}
```

### 2. O Usa Docblocks

```php
/**
 * Título del endpoint
 * 
 * Descripción detallada del endpoint.
 *
 * @group Nombre del Grupo
 * @authenticated
 * 
 * @header X-Tenant demo-tenant
 * 
 * @queryParam filter string Filtro opcional. Example: active
 * @queryParam page integer Número de página. Example: 1
 * 
 * @response 200 {
 *   "data": [...]
 * }
 */
public function yourMethod()
{
    // ...
}
```

### 3. Documenta FormRequests

```php
/**
 * @bodyParam name string required Nombre. Example: Producto X
 * @bodyParam price numeric required Precio. Example: 25.50
 */
class YourRequest extends FormRequest
{
    // ...
}
```

## 🎨 Personalización

### Cambiar Título/Descripción

Edita `config/scribe.php`:

```php
'title' => 'Tu Título',
'description' => 'Tu descripción',
```

### Añadir Logo

```php
'logo' => 'path/to/logo.png',
```

### Cambiar Tema

Publica las vistas y personaliza:

```bash
php artisan vendor:publish --tag=scribe-views
```

Editar archivos en `resources/views/vendor/scribe/`.

## 🔐 Headers Obligatorios

**Todos los requests requieren:**

```bash
X-Tenant: {tenant_id}
Authorization: Bearer {token}
```

## 🤖 Uso con IA (Cursor/ChatGPT)

1. Comparte el archivo `public/docs/openapi.yaml` con la IA
2. Instrucciones para la IA:

```
Lee el contrato OpenAPI en public/docs/openapi.yaml antes de generar código.
Todos los requests deben incluir X-Tenant y Authorization headers.
Genera tipos TypeScript desde los schemas OpenAPI.
```

## 📦 Comandos Útiles

```bash
# Generar docs
php artisan scribe:generate

# Generar solo si hay cambios
php artisan scribe:generate --force

# Limpiar cache de Scribe
rm -rf .scribe/

# Ver rutas documentadas
php artisan route:list --path=api
```

## 🐛 Troubleshooting

### Problema: "No se generan ejemplos de respuesta"

**Solución:** Asegúrate de tener datos en la BD o usa `#[ResponseFromApiResource]`

### Problema: "Header X-Tenant no aparece"

**Solución:** Verifica `config/scribe.php`:

```php
'headers' => [
    'X-Tenant' => 'demo-tenant',
],
```

### Problema: "Endpoint no aparece en docs"

**Solución:** Verifica que la ruta está en `routes/api.php` y no está excluida en `config/scribe.php`

## 📖 Referencias

* [Scribe Docs Oficial](https://scribe.knuckles.wtf/laravel/)
* [OpenAPI Specification](https://swagger.io/specification/)
* [Postman Learning](https://learning.postman.com/)

## 🤝 Contribuir a la Documentación

1. Documenta TODOS los endpoints nuevos
2. Usa FormRequests para validación (Scribe las extrae automáticamente)
3. Añade ejemplos realistas en los seeders
4. Regenera docs antes de hacer PR
5. Revisa que el OpenAPI se genera sin errores

---

**Última actualización:** \$(date +%Y-%m-%d) **Generado con:** Scribe v4.x

```

---

## ✅ CHECKLIST FINAL

Antes de dar por completada la tarea, verifica:

- [ ] Scribe instalado correctamente (`composer.json` muestra knuckleswtf/scribe)
- [ ] Archivo `config/scribe.php` creado y configurado
- [ ] Headers multi-tenant (`X-Tenant`) configurados
- [ ] Al menos un controller documentado con atributos PHP
- [ ] FormRequests documentados (al menos uno de ejemplo)
- [ ] Seeders con datos de ejemplo creados
- [ ] Script `generate-docs.sh` funcional
- [ ] Documentación generada exitosamente:
  - [ ] `public/docs/index.html` existe y se ve correctamente
  - [ ] `public/docs/openapi.yaml` existe y es válido
  - [ ] `public/docs/collection.json` existe
- [ ] Tests de documentación pasan (`ApiDocumentationTest`)
- [ ] Archivo `docs/API_DOCUMENTATION_GUIDE.md` creado
- [ ] `.gitignore` actualizado
- [ ] CI/CD configurado (opcional pero recomendado)

## 🎯 ENTREGABLES

Al finalizar, debes proporcionar:

1. **Confirmación de instalación:**
```

✅ Scribe instalado en versión X.X.X ✅ Configuración aplicada en config/scribe.php

```

2. **Ejemplos de código:**
- Al menos 1 controller completamente documentado
- Al menos 1 FormRequest documentado
- 1 seeder con datos de ejemplo

3. **Documentación generada:**
- Screenshot o confirmación de `public/docs/index.html` funcionando
- Contenido de `public/docs/openapi.yaml` (primeras 50 líneas)

4. **Guía de uso:**
- Archivo `docs/API_DOCUMENTATION_GUIDE.md` completo
- Instrucciones de regeneración
- Ejemplos de uso con Cursor

5. **Validación:**
- Output del comando `php artisan scribe:generate`
- Tests pasando correctamente

## 🚨 CONSIDERACIONES IMPORTANTES

### Seguridad
- NO incluir tokens reales en la configuración
- Usar valores de ejemplo tipo `demo-tenant`, `your-token-here`
- NO commitear datos sensibles en seeders

### Performance
- La generación puede tardar si tienes muchos endpoints
- Considera excluir rutas de debug/internal
- Usa `response_calls => false` si tienes problemas

### Multi-tenant
- SIEMPRE incluir `X-Tenant` en headers
- Configurar correctamente el tenant de ejemplo
- Documentar este requisito claramente

## 📞 SIGUIENTE PASO DESPUÉS DE COMPLETAR

Una vez implementado Scribe, el siguiente paso será:

1. **Compartir el OpenAPI con Cursor**
- Copiar contenido de `public/docs/openapi.yaml`
- Añadir a las reglas de Cursor
- Configurar generación automática de tipos TypeScript

2. **Entrenar al equipo**
- Compartir `docs/API_DOCUMENTATION_GUIDE.md`
- Hacer un walkthrough de la documentación generada
- Establecer proceso de actualización

3. **Integrar en workflow**
- Hacer obligatoria la documentación en PRs
- Configurar checks automáticos
- Revisar docs en code reviews

---

## 🎬 CONCLUSIÓN

Siguiendo este prompt paso a paso, tendrás:

✅ Documentación API profesional generada automáticamente
✅ Especificación OpenAPI lista para consumo por IA
✅ Proceso reproducible y mantenible
✅ Base sólida para escalar PesquerApp

**Tiempo estimado de implementación:** 2-4 horas

**Dificultad:** Media

**Beneficio:** Alto (ROI inmediato para desarrollo asistido por IA)

---

*Este prompt ha sido diseñado específicamente para PesquerApp y su arquitectura multi-tenant. Ajusta según necesidades específicas.*
```
