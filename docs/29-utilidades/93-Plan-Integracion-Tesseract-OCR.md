# Plan de Integración de Tesseract OCR al Backend

## Objetivo

Exponer un endpoint que reciba un PDF (escaneado o con imágenes) y devuelva el texto extraído mediante OCR usando Tesseract.

## Contexto Actual

- **Framework**: Laravel 10
- **Controlador existente**: `PdfExtractionController` (deprecado, usa `smalot/pdfparser` para PDFs con texto extraíble)
- **Deployment**: Docker/Coolify (las dependencias del sistema se agregan al Dockerfile)
- **Ubicación de rutas**: `routes/api.php` con prefijo `v2` y middleware `auth:sanctum`
- **Dependencias relacionadas**: Ya tienen `spatie/pdf-to-text` en composer.json
- **Autenticación actual**: Laravel Sanctum con tokens Bearer para usuarios
- **Requisito adicional**: Sistema de API Keys para servicios externos (n8n) que no pueden usar autenticación de usuario normal

## Requisitos Técnicos

### 1. Dependencias del Sistema

Como usamos **Docker/Coolify**, todas las dependencias del sistema se instalan en el Dockerfile:

- **Tesseract OCR**: Para realizar el reconocimiento óptico de caracteres
- **ImageMagick**: Para convertir PDF a imágenes
- **Ghostscript**: Requerido por ImageMagick para procesar PDFs

### 2. Dependencias PHP (Composer)

- **thiagoalessio/tesseract_ocr**: Wrapper PHP para Tesseract

  ```bash
  composer require thiagoalessio/tesseract_ocr
  ```
- **spatie/pdf-to-image**: Para convertir PDF a imágenes (alternativa)

  ```bash
  composer require spatie/pdf-to-image
  ```

  O usar **imagick** (extensión PHP) si está disponible

## Arquitectura de la Solución

### Opción Recomendada: Crear un nuevo controlador OcrController

Dado que `PdfExtractionController` está deprecado, se creará un nuevo controlador dedicado para OCR:

**Archivo**: `app/Http/Controllers/v2/OcrController.php`

**Responsabilidades**:

1. Recibir PDFs mediante endpoint
2. Detectar si el PDF tiene texto extraíble (opcional, para optimización)
3. Convertir PDF a imágenes si es necesario
4. Procesar imágenes con Tesseract OCR
5. Retornar texto extraído en formato JSON

**Ventajas**:

- Separación clara de responsabilidades
- No depende de código deprecado
- Fácil de mantener y extender
- Puede reutilizar lógica de `PdfExtractionController` si es necesario mediante servicios

## Plan de Implementación

### Fase 1: Preparación del Entorno (USAREMOS COOLIFY Y DOCKER POR LO TANTO)

#### 1.1 Instalación de Dependencias del Sistema

Agregar al Dockerfile del proyecto (en la etapa final, después de instalar Chrome y fuentes):

```dockerfile
# Instalar Tesseract OCR y dependencias para conversión PDF a imágenes
RUN apt-get update && apt-get install -y \
    tesseract-ocr \
    tesseract-ocr-spa \
    tesseract-ocr-eng \
    imagemagick \
    ghostscript \
    libmagickwand-dev \
    && docker-php-ext-install imagick \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*
```

**Nota**: 
- Coolify construirá automáticamente la imagen con todas las dependencias al hacer el deploy
- `libmagickwand-dev` es necesario para compilar la extensión PHP `imagick`
- La extensión `imagick` es requerida por `spatie/pdf-to-image`

#### 1.2 Instalación de Dependencias PHP

```bash
composer require thiagoalessio/tesseract_ocr
composer require spatie/pdf-to-image
```

#### 1.3 Verificación de Extensiones PHP

Las siguientes extensiones deben estar habilitadas en el contenedor:

- `imagick` - **Requerido** por `spatie/pdf-to-image` (se instala en el Dockerfile)
- `exec()` - Necesario para ejecutar Tesseract desde PHP (habilitado por defecto en PHP)

**Verificación después del deploy**:
```bash
php -m | grep imagick  # Debe mostrar "imagick"
```

### Fase 2: Desarrollo del Código

#### 2.1 Crear OcrController

**Archivo**: `app/Http/Controllers/v2/OcrController.php`

**Estructura**:

1. **Método principal `extract()`**:

   - Validar el archivo PDF recibido
   - Guardar temporalmente el PDF
   - Convertir PDF a imágenes (una por página)
   - Procesar cada imagen con Tesseract OCR
   - Combinar el texto de todas las páginas
   - Limpiar archivos temporales
   - Retornar JSON con el texto extraído
2. **Métodos auxiliares privados**:

   - `convertPdfToImages(string $pdfPath): array`: Convierte PDF a array de rutas de imágenes
   - `extractTextFromImage(string $imagePath, string $language = 'spa'): string`: Extrae texto de una imagen con Tesseract
   - `cleanupTempFiles(array $files): void`: Limpia archivos temporales
3. **Imports necesarios**:

   - `Thiagoalessio\TesseractOCR\TesseractOCR`
   - `Spatie\PdfToImage\Pdf`
   - `Illuminate\Support\Facades\Storage`

#### 2.2 Sistema de Autenticación para n8n

Para permitir que n8n acceda al endpoint OCR sin usar la autenticación normal de usuarios, se implementará un sistema de **API Keys**.

**Opciones de implementación**:

1. **API Keys en tabla dedicada** (Recomendada)
   - Tabla `api_keys` con campos: `id`, `name`, `key` (hasheado), `tenant_id`, `active`, `last_used_at`, `expires_at`
   - Middleware personalizado `AuthenticateApiKey`
   - Ventajas: Control total, revocable, rastreable

2. **Sanctum Personal Access Tokens con nombre especial**
   - Usar `$user->createToken('n8n-integration')` con expiración larga
   - Ventajas: Reutiliza infraestructura existente
   - Desventajas: Requiere usuario, menos control granular

3. **API Keys en configuración (.env)**
   - Claves estáticas en variables de entorno
   - Ventajas: Simple, rápido
   - Desventajas: Menos flexible, difícil de rotar

**Implementación recomendada: Opción 1 (API Keys en tabla)**

#### 2.2.1 Crear Migración para API Keys

**Archivo**: `database/migrations/companies/YYYY_MM_DD_HHMMSS_create_api_keys_table.php`

```php
Schema::create('api_keys', function (Blueprint $table) {
    $table->id();
    $table->string('name'); // Nombre descriptivo (ej: "n8n-production")
    $table->string('key', 64)->unique(); // Hash de la API key
    $table->foreignId('tenant_id')->nullable()->constrained('tenants')->onDelete('cascade');
    $table->boolean('active')->default(true);
    $table->timestamp('last_used_at')->nullable();
    $table->timestamp('expires_at')->nullable();
    $table->timestamps();
});
```

#### 2.2.2 Crear Modelo ApiKey

**Archivo**: `app/Models/ApiKey.php`

```php
use App\Traits\UsesTenantConnection;

class ApiKey extends Model
{
    use UsesTenantConnection;
    
    protected $fillable = ['name', 'key', 'tenant_id', 'active', 'expires_at'];
    protected $hidden = ['key'];
    
    // Generar API key aleatoria
    public static function generate(): string
    {
        return Str::random(64);
    }
}
```

#### 2.2.3 Crear Middleware AuthenticateApiKey

**Archivo**: `app/Http/Middleware/AuthenticateApiKey.php`

```php
public function handle(Request $request, Closure $next)
{
    $apiKey = $request->header('X-API-Key') ?? $request->header('Authorization');
    
    // Si viene en Authorization, extraer después de "Bearer " o usar directo
    if (str_starts_with($apiKey, 'Bearer ')) {
        $apiKey = substr($apiKey, 7);
    }
    
    if (!$apiKey) {
        return response()->json(['message' => 'API Key requerida'], 401);
    }
    
    $hashedKey = hash('sha256', $apiKey);
    $key = ApiKey::where('key', $hashedKey)
        ->where('active', true)
        ->where(function($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        })
        ->first();
    
    if (!$key) {
        return response()->json(['message' => 'API Key inválida'], 401);
    }
    
    // Actualizar último uso
    $key->update(['last_used_at' => now()]);
    
    // Agregar tenant_id al request si existe
    if ($key->tenant_id) {
        $request->merge(['api_key_tenant_id' => $key->tenant_id]);
    }
    
    return $next($request);
}
```

#### 2.2.4 Registrar Middleware

**Archivo**: `app/Http/Kernel.php` o `bootstrap/app.php` (Laravel 11)

```php
protected $middlewareAliases = [
    // ...
    'api.key' => \App\Http\Middleware\AuthenticateApiKey::class,
];
```

#### 2.2.5 Crear Comando Artisan para Generar API Keys

**Archivo**: `app/Console/Commands/GenerateApiKey.php`

```php
php artisan make:command GenerateApiKey

// Uso:
php artisan api-key:generate --name="n8n-production" --tenant=1
```

#### 2.2.6 Agregar Ruta con Autenticación Alternativa

**Archivo**: `routes/api.php`

```php
use App\Http\Controllers\v2\OcrController;

// Opción 1: Ruta pública con middleware de API Key
Route::post('pdf/extract-ocr', [OcrController::class, 'extract'])
    ->middleware(['tenant', 'api.key'])
    ->name('pdf.extract_ocr');

// Opción 2: Ruta alternativa solo para API Keys (sin auth:sanctum)
Route::middleware(['tenant', 'api.key'])->group(function () {
    Route::post('pdf/extract-ocr', [OcrController::class, 'extract'])
        ->name('pdf.extract_ocr.api_key');
});

// Opción 3: Ruta con ambas autenticaciones (Sanctum O API Key)
Route::post('pdf/extract-ocr', [OcrController::class, 'extract'])
    ->middleware(['tenant', 'auth:sanctum|api.key'])
    ->name('pdf.extract_ocr');
```

**Recomendación**: Usar **Opción 2** para mantener separación clara entre autenticación de usuarios y servicios externos.

#### 2.2.7 Gestión de API Keys

**Generar nueva API Key**:

```bash
php artisan api-key:generate --name="n8n-production" --tenant=1
# Output: API Key generada: abc123def456... (guardar inmediatamente)
```

**Listar API Keys activas**:

```bash
php artisan api-key:list
```

**Revocar API Key**:

```bash
php artisan api-key:revoke --id=1
```

**O desde código**:

```php
use App\Models\ApiKey;

// Crear API Key
$key = ApiKey::create([
    'name' => 'n8n-production',
    'key' => hash('sha256', $plainKey = ApiKey::generate()),
    'tenant_id' => 1,
    'active' => true,
]);

// Guardar $plainKey en lugar seguro (solo se muestra una vez)
echo "API Key: {$plainKey}\n";

// Revocar
$key->update(['active' => false]);
```

**Consideraciones de Seguridad**:

- Las API Keys se almacenan hasheadas (SHA-256) en la base de datos
- La clave plana solo se muestra una vez al generarla
- Implementar rate limiting específico para API Keys
- Registrar todos los usos en `last_used_at` para auditoría
- Permitir expiración opcional con `expires_at`
- Rotar keys periódicamente

### Fase 3: Configuración y Optimización

#### 3.1 Configuración de Tesseract

- Idioma por defecto: Español (`spa`)
- Idiomas adicionales: Inglés (`eng`)
- Configuración de calidad: PSM (Page Segmentation Mode) y OEM (OCR Engine Mode)

#### 3.2 Manejo de Archivos Temporales

- Guardar PDFs e imágenes en `storage/app/temp/ocr/`
- Limpiar archivos temporales después del procesamiento
- Considerar uso de jobs para PDFs grandes

#### 3.3 Validaciones

- Tamaño máximo del PDF (ej: 50MB)
- Número máximo de páginas (ej: 50)
- Timeout para procesamiento (ej: 5 minutos)

### Fase 4: Manejo de Errores

#### 4.1 Casos de Error

- PDF corrupto o inválido
- Tesseract no instalado o no accesible
- Error al convertir PDF a imágenes
- Timeout en procesamiento
- Memoria insuficiente

#### 4.2 Respuestas de Error

Mantener formato consistente con el resto de la API:

```json
{
    "message": "Error al procesar PDF",
    "userMessage": "No se pudo extraer el texto del PDF. Verifique que el archivo sea válido.",
    "error": "Detalles técnicos del error"
}
```

### Fase 5: Testing

#### 5.1 Casos de Prueba

- PDF escaneado (debe usar OCR)
- PDF con múltiples páginas
- PDF corrupto o inválido
- PDF muy grande (probar límites)
- PDF con diferentes idiomas (español, inglés)
- Archivo que no es PDF

#### 5.2 Pruebas de Rendimiento

- Tiempo de procesamiento por página
- Uso de memoria
- Manejo de archivos grandes

## Estructura del Endpoint

### Autenticación

El endpoint soporta dos métodos de autenticación:

#### Opción 1: API Key (Recomendado para n8n)

```http
POST /api/v2/pdf/extract-ocr
X-Tenant: empresa1
X-API-Key: tu-api-key-generada
Content-Type: multipart/form-data
```

O usando el header `Authorization`:

```http
POST /api/v2/pdf/extract-ocr
X-Tenant: empresa1
Authorization: Bearer tu-api-key-generada
Content-Type: multipart/form-data
```

#### Opción 2: Sanctum Token (Para usuarios normales)

```http
POST /api/v2/pdf/extract-ocr
X-Tenant: empresa1
Authorization: Bearer 1|token-sanctum...
Content-Type: multipart/form-data
```

### Request

```
POST /api/v2/pdf/extract-ocr
Content-Type: multipart/form-data

{
    "pdf": <archivo PDF>,
    "language": "spa" (opcional, default: "spa"),
    "pages": "all" | [1, 3, 5] (opcional, default: "all")
}
```

### Uso en n8n

**Configuración del nodo HTTP Request en n8n**:

1. **Method**: `POST`
2. **URL**: `https://tu-dominio.com/api/v2/pdf/extract-ocr`
3. **Headers**:
   - `X-Tenant`: `empresa1`
   - `X-API-Key`: `{{ $env.OCR_API_KEY }}` (usar variable de entorno)
   - `Content-Type`: `multipart/form-data`
4. **Body**:
   - `pdf`: Archivo PDF (desde nodo anterior o URL)
   - `language`: `spa` (opcional)

**Ejemplo de workflow n8n**:
```
1. Trigger (Webhook/Manual)
2. HTTP Request → Descargar PDF desde URL
3. HTTP Request → POST a /api/v2/pdf/extract-ocr
   - Headers: X-Tenant, X-API-Key
   - Body: pdf (binary), language: "spa"
4. Procesar respuesta JSON con texto extraído
```

### Response (Éxito)

```json
{
    "message": "PDF procesado correctamente con OCR",
    "data": {
        "text": "Texto completo extraído...",
        "pages": [
            {
                "page": 1,
                "text": "Texto de la página 1..."
            },
            {
                "page": 2,
                "text": "Texto de la página 2..."
            }
        ],
        "metadata": {
            "totalPages": 2,
            "processingTime": 3.45,
            "method": "ocr" | "text_extraction" | "mixed"
        }
    }
}
```

### Response (Error)

```json
{
    "message": "Error al procesar PDF",
    "userMessage": "No se pudo extraer el texto del PDF.",
    "error": "Tesseract no está instalado en el servidor"
}
```

## Consideraciones Adicionales

### Rendimiento

- **Procesamiento asíncrono**: Para PDFs grandes, considerar usar Laravel Jobs/Queues
- **Caché**: Cachear resultados si el mismo PDF se procesa múltiples veces
- **Límites**: Establecer límites de tamaño y páginas

### Seguridad

- **Validación de archivos**:
  - Validar tipo MIME del archivo
  - Escanear archivos por malware (opcional)
  - Limitar tamaño de archivo (50MB recomendado)
  
- **Rate limiting**:
  - Implementar rate limiting específico para API Keys
  - Ejemplo: 100 requests/hora por API Key
  - Diferente a rate limiting de usuarios normales
  
- **API Keys**:
  - Almacenar hasheadas (SHA-256)
  - Permitir revocación inmediata
  - Registrar todos los accesos
  - Implementar expiración opcional
  
- **Headers requeridos**:
  - `X-Tenant`: Obligatorio para multi-tenant
  - `X-API-Key` o `Authorization`: Obligatorio para autenticación

### Escalabilidad

- Considerar servicio externo de OCR (Google Cloud Vision, AWS Textract) para producción
- Usar colas para procesamiento asíncrono
- Implementar progreso para PDFs grandes

## Orden de Implementación Recomendado

1. ✅ **Fase 1**: Instalación de dependencias
2. ✅ **Fase 2.1**: Crear sistema de API Keys (migración, modelo, middleware)
3. ✅ **Fase 2.2**: Crear comando para generar API Keys
4. ✅ **Fase 2.3**: Desarrollo del código base (OcrController)
5. ✅ **Fase 2.4**: Agregar ruta con autenticación API Key
6. ✅ **Fase 3**: Agregar ruta y probar endpoint básico
7. ✅ **Fase 4**: Manejo de errores y validaciones
8. ✅ **Fase 5**: Optimizaciones y mejoras
9. ✅ **Fase 6**: Testing completo (incluyendo pruebas con n8n)
10. ✅ **Fase 7**: Documentación y generación de API Key para producción

## Notas Técnicas

### Conversión PDF a Imágenes

- **spatie/pdf-to-image**: Usa Imagick, requiere Ghostscript
- **imagick nativo**: Más control, mejor rendimiento
- **Resolución recomendada**: 300 DPI para buena calidad OCR

### Configuración Tesseract

- **PSM (Page Segmentation Mode)**:
  - `6`: Asume un bloque uniforme de texto
  - `11`: Sparse text (texto disperso)
  - `12`: OCR de una sola palabra
- **OEM (OCR Engine Mode)**:
  - `3`: Default, basado en LSTM

### Idiomas Soportados

Instalar paquetes de idiomas según necesidad:

- `tesseract-ocr-spa`: Español
- `tesseract-ocr-eng`: Inglés
- `tesseract-ocr-fra`: Francés
- etc.

## Configuración para n8n - Guía Rápida

### Paso 1: Generar API Key

```bash
php artisan api-key:generate --name="n8n-production" --tenant=1
```

**Importante**: Guardar la API Key mostrada inmediatamente (solo se muestra una vez).

### Paso 2: Configurar Variable de Entorno en n8n

En n8n, agregar variable de entorno:
- Nombre: `OCR_API_KEY`
- Valor: La API Key generada en el paso 1

### Paso 3: Configurar Nodo HTTP Request en n8n

**Configuración básica**:

```json
{
  "method": "POST",
  "url": "https://tu-dominio.com/api/v2/pdf/extract-ocr",
  "authentication": "none",
  "sendHeaders": true,
  "headerParameters": {
    "parameters": [
      {
        "name": "X-Tenant",
        "value": "empresa1"
      },
      {
        "name": "X-API-Key",
        "value": "={{ $env.OCR_API_KEY }}"
      }
    ]
  },
  "sendBody": true,
  "bodyParameters": {
    "parameters": [
      {
        "name": "pdf",
        "value": "={{ $binary.data }}"
      },
      {
        "name": "language",
        "value": "spa"
      }
    ]
  },
  "options": {
    "bodyContentType": "multipart-form-data"
  }
}
```

### Paso 4: Probar la Conexión

1. Crear workflow de prueba en n8n
2. Usar nodo "Manual Trigger"
3. Agregar nodo "Read Binary File" con un PDF de prueba
4. Agregar nodo HTTP Request configurado como arriba
5. Ejecutar y verificar respuesta JSON

### Respuesta Esperada

```json
{
  "message": "PDF procesado correctamente con OCR",
  "data": {
    "text": "Texto extraído...",
    "pages": [...],
    "metadata": {...}
  }
}
```

## Referencias

- [Tesseract OCR](https://github.com/tesseract-ocr/tesseract)
- [thiagoalessio/tesseract_ocr](https://github.com/thiagoalessio/tesseract_ocr)
- [spatie/pdf-to-image](https://github.com/spatie/pdf-to-image)
- [Laravel File Storage](https://laravel.com/docs/10.x/filesystem)
- [Laravel Sanctum](https://laravel.com/docs/10.x/sanctum)
- [n8n HTTP Request Node](https://docs.n8n.io/integrations/builtin/core-nodes/n8n-nodes-base.httprequest/)
