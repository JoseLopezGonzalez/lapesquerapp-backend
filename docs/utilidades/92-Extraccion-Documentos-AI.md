# Utilidades - Extracción de Documentos con IA

## ⚠️ Estado de la API
- **v1**: Obsoleta (no documentada)
- **v2**: Versión activa (este documento)

---

## 📋 Visión General

El sistema de extracción de documentos con IA permite procesar archivos PDF y extraer información estructurada utilizando servicios de IA en la nube. Actualmente, el sistema soporta dos proveedores:

1. **Azure Document AI** (Azure Form Recognizer): Proveedor activo
2. **Google Document AI**: Implementado pero comentado/deshabilitado

El sistema está diseñado para procesar documentos como facturas, albaranes, o cualquier documento estructurado y extraer campos específicos automáticamente.

---

## 🔧 Controladores

### AzureDocumentAIController (Activo)

**Archivo**: `app/Http/Controllers/v2/AzureDocumentAIController.php`

#### Método: `processPdf(Request $request)`

Procesa un PDF usando Azure Form Recognizer (prebuilt-document model).

**Validación**:
- `pdf`: Requerido, archivo, tipo `pdf`, máximo `20480 KB` (20MB)

**Flujo de Procesamiento**:
1. Valida el archivo PDF
2. Guarda temporalmente el PDF en `storage/app/pdfs/`
3. Lee el contenido del PDF
4. Obtiene configuración desde `.env`:
   - `AZURE_DOCUMENT_AI_ENDPOINT`: Endpoint de Azure Document AI
   - `AZURE_DOCUMENT_AI_KEY`: API Key de Azure
5. Construye URL para llamar a Azure Form Recognizer:
   - Modelo: `prebuilt-document`
   - Versión API: `2024-02-29-preview`
   - URL: `{endpoint}formrecognizer/documentModels/prebuilt-document:analyze?api-version={version}`
6. Hace POST al endpoint con el contenido del PDF
7. Azure retorna un header `Operation-Location` con la URL del resultado
8. **Polling**: Espera mientras Azure procesa el documento (polling cada 2 segundos)
   - Estados: `running`, `notStarted`, `succeeded`
9. Retorna el resultado completo en `analyzeResult`

**Respuesta Exitosa**:
```json
{
    "message": "Procesado con éxito",
    "analysis": {
        // Resultado completo de Azure Form Recognizer
        // Incluye: texto extraído, tablas, campos detectados, etc.
    }
}
```

**Manejo de Errores**:
- Si el estado final no es `succeeded`, retorna error 500
- Cualquier excepción se captura y retorna como error 500

---

### GoogleDocumentAIController (Deshabilitado)

**Archivo**: `app/Http/Controllers/v2/GoogleDocumentAIController.php`

#### Método: `processPdf(Request $request)`

Procesa un PDF usando Google Document AI.

**Validación**:
- `pdf`: Requerido, archivo, tipo `pdf`, máximo `20480 KB` (20MB)

**Configuración Hardcoded**:
- **Credenciales**: `storage/app/google-credentials.json`
- **Project ID**: `223147234811`
- **Location**: `eu`
- **Processor ID**: `3c49f1160f79a1af`

**Flujo de Procesamiento**:
1. Valida el archivo PDF
2. Guarda temporalmente el PDF
3. Configura credenciales y datos del procesador
4. Crea cliente `DocumentProcessorServiceClient` con endpoint EU
5. Construye nombre del procesador
6. Lee el PDF como `RawDocument`
7. Prepara la solicitud `ProcessRequest`
8. Llama a Document AI
9. Obtiene el documento resultante
10. Extrae texto completo y entidades etiquetadas
11. Retorna JSON con texto y entidades

**Respuesta Exitosa**:
```json
{
    "message": "Procesado con éxito",
    "fullText": "Texto completo extraído del PDF",
    "entities": [
        {
            "type": "nombre_campo",
            "value": "valor_detectado",
            "confidence": 0.95
        }
    ]
}
```

**Estado**: La ruta está comentada en `routes/api.php:289`, por lo que este controlador no está activo.

---

### PdfExtractionController (Deshabilitado)

**Archivo**: `app/Http/Controllers/v2/PdfExtractionController.php`

#### Método: `extract(Request $request)`

Procesa un PDF usando la librería `smalot/pdfparser` (extracción local, sin IA).

**Validación**:
- `pdf`: Requerido, archivo, tipo `pdf`, máximo `20480 KB` (20MB)

**Flujo de Procesamiento**:
1. Valida el archivo PDF
2. Guarda temporalmente el PDF
3. Parsea el PDF usando `Smalot\PdfParser\Parser`
4. Extrae texto plano del PDF
5. Procesa el texto usando heurísticas para identificar:
   - Comprador (`Comprador:`)
   - Empresa
   - Fecha (`Fecha:`)
   - Líneas de compra (patrón: `{boxes} M{weight} {product} {price} {total} {seller}`)
   - Servicios (`TARIFA`, `CUOTA`, `SERV.`)
   - Totales (`Total Pesca`, `IVA Pesca`, `Total`)

**Respuesta Exitosa**:
```json
{
    "message": "PDF procesado correctamente",
    "data": {
        "buyer": "Comprador detectado",
        "company": "Empresa detectada",
        "date": "Fecha detectada",
        "purchases": [
            {
                "boxes": "1",
                "weight": "19,40",
                "product": "PULPO ABUELO PURGA816",
                "pricePerKg": "4,00",
                "total": "77,60",
                "seller": "GARCIA RAMOS, ANTONIO JOSE"
            }
        ],
        "services": [...],
        "totals": {
            "totalFishing": "...",
            "ivaFishing": "...",
            "grandTotal": "..."
        }
    }
}
```

**Limitaciones**:
- Basado en patrones de texto y regex, no en IA
- Específico para un formato de PDF particular
- Puede fallar si el formato del PDF cambia

**Estado**: La ruta está comentada en `routes/api.php:288`, por lo que este controlador no está activo.

---

## 🛣️ Rutas API

Todas las rutas están protegidas por autenticación Sanctum y solo accesibles para el rol `superuser`.

### Ruta Activa

| Método HTTP | Ruta | Método del Controlador | Descripción |
|------------|------|----------------------|-------------|
| `POST` | `/api/v2/document-ai/parse` | `AzureDocumentAIController::processPdf` | Procesa PDF con Azure Document AI |

**Request**:
- Content-Type: `multipart/form-data`
- Campo: `pdf` (archivo PDF, máximo 20MB)

**Respuesta**: JSON con resultado del análisis

### Rutas Deshabilitadas

Las siguientes rutas están comentadas en `routes/api.php`:

- `POST /api/v2/pdf-extractor` → `PdfExtractionController::extract` (comentado línea 288)
- `POST /api/v2/document-ai/parse` → `GoogleDocumentAIController::processPdf` (comentado línea 289)

---

## ⚙️ Configuración

### Azure Document AI

**Variables de Entorno Requeridas** (`.env`):
```env
AZURE_DOCUMENT_AI_ENDPOINT=https://your-resource.cognitiveservices.azure.com/
AZURE_DOCUMENT_AI_KEY=your-api-key
```

**Ubicación en el Código**: `AzureDocumentAIController.php:27-28`

### Google Document AI

**Archivo de Credenciales Requerido**:
- Ruta: `storage/app/google-credentials.json`
- Formato: JSON con credenciales de cuenta de servicio de Google Cloud

**Configuración Hardcoded**:
- Project ID: `223147234811`
- Location: `eu`
- Processor ID: `3c49f1160f79a1af`
- API Endpoint: `eu-documentai.googleapis.com`

**Ubicación en el Código**: `GoogleDocumentAIController.php:26-29`

---

## 🏗️ Dependencias

### Azure Document AI

- **Librería HTTP**: `GuzzleHttp\Client` (incluido en Laravel)
- **Servicio**: Azure Cognitive Services - Form Recognizer
- **Modelo Usado**: `prebuilt-document` (modelo preentrenado de Azure)

### Google Document AI

- **Librería**: `google/cloud-documentai` (paquete de Google Cloud)
- **Servicio**: Google Cloud Document AI
- **Procesador**: Procesador personalizado con ID `3c49f1160f79a1af`

### PdfExtractionController

- **Librería**: `smalot/pdfparser`
- **Procesamiento**: Local (sin servicios en la nube)

---

## 🔄 Flujo de Procesamiento (Azure)

```
1. Cliente HTTP → POST /api/v2/document-ai/parse (con PDF)
2. AzureDocumentAIController → Valida PDF
3. AzureDocumentAIController → Guarda PDF temporal
4. AzureDocumentAIController → Lee contenido PDF
5. AzureDocumentAIController → Construye URL Azure
6. Guzzle Client → POST a Azure Form Recognizer
7. Azure → Retorna Operation-Location header
8. AzureDocumentAIController → Polling cada 2 segundos
   └─ GET Operation-Location
   └─ Verifica status (running/notStarted/succeeded)
   └─ Si no terminado, espera 2 segundos y repite
9. AzureDocumentAIController → Retorna analyzeResult
10. Cliente HTTP ← JSON con resultado
```

---

## 📝 Ejemplos de Uso

### Procesar PDF con Azure Document AI

```bash
POST /api/v2/document-ai/parse
Authorization: Bearer {token}
X-Tenant: {tenant_slug}
Content-Type: multipart/form-data

pdf: [archivo PDF]
```

**Respuesta**:
```json
{
    "message": "Procesado con éxito",
    "analysis": {
        "apiVersion": "2024-02-29-preview",
        "modelId": "prebuilt-document",
        "content": "Texto extraído...",
        "pages": [...],
        "tables": [...],
        "paragraphs": [...],
        "keyValuePairs": [...],
        "styles": [...]
    }
}
```

---

## 🔒 Seguridad

### Validaciones

1. **Tipo de Archivo**: Solo acepta archivos PDF
2. **Tamaño Máximo**: 20MB (20480 KB)
3. **Autenticación**: Requiere token Sanctum válido
4. **Autorización**: Solo rol `superuser` puede acceder

### Almacenamiento Temporal

- Los PDFs se guardan temporalmente en `storage/app/pdfs/`
- **Problema**: No hay limpieza automática de archivos temporales
- **Recomendación**: Implementar limpieza periódica o usar sistema de archivos temporales

---

## Observaciones Críticas y Mejoras Recomendadas

1. **Ruta Hardcoded para Archivos Temporales** (`AzureDocumentAIController.php:20`, `GoogleDocumentAIController.php:22`)
   - Los PDFs se guardan en `storage/app/pdfs/` sin verificación de directorio
   - **Problema**: Si el directorio no existe, fallará
   - **Recomendación**: Verificar/crear directorio antes de guardar

2. **Falta de Limpieza de Archivos Temporales**
   - Los PDFs se guardan pero nunca se eliminan
   - **Problema**: Acumulación de archivos en el storage
   - **Recomendación**: Eliminar archivos después de procesar o implementar limpieza automática
   - **Ubicaciones**: Todos los controladores

3. **Polling con Sleep Fijo** (`AzureDocumentAIController.php:52`)
   - El polling espera 2 segundos fijos entre requests
   - **Problema**: No adaptativo, puede ser lento para documentos simples
   - **Recomendación**: Implementar backoff exponencial o timeout máximo

4. **Falta de Timeout en Polling** (`AzureDocumentAIController.php:51-64`)
   - El bucle de polling no tiene límite de tiempo máximo
   - **Problema**: Puede quedarse en loop infinito si Azure falla
   - **Recomendación**: Agregar timeout máximo (ej: 5 minutos) y máximo de intentos

5. **Uso de `env()` Directo** (`AzureDocumentAIController.php:27-28`)
   - Se usa `env()` directamente en lugar de `config()`
   - **Problema**: No funciona con cache de configuración en producción
   - **Recomendación**: Mover a `config/document-ai.php` y usar `config()`

6. **Configuración Hardcoded en GoogleDocumentAIController** (`GoogleDocumentAIController.php:27-29`)
   - Project ID, Location y Processor ID están hardcoded
   - **Problema**: No flexible, difícil de cambiar entre entornos
   - **Recomendación**: Mover a configuración o variables de entorno

7. **Falta de Validación de Credenciales**
   - No se valida si las credenciales/configuración existen antes de usar
   - **Problema**: Errores crípticos si falta configuración
   - **Recomendación**: Validar configuración al inicio del método

8. **Manejo de Errores Genérico** (`AzureDocumentAIController.php:76-78`)
   - Cualquier excepción retorna mensaje genérico
   - **Problema**: Dificulta debugging
   - **Recomendación**: Logging detallado y mensajes de error más descriptivos

9. **Falta de Logging**
   - No hay logging de procesamientos exitosos o fallidos
   - **Problema**: Dificulta auditoría y monitoreo
   - **Recomendación**: Agregar logging de todas las operaciones

10. **Límite de Tamaño Fijo** (`AzureDocumentAIController.php:16`)
    - El límite de 20MB está hardcoded
    - **Problema**: Puede ser insuficiente para algunos documentos
    - **Recomendación**: Hacer configurable o verificar límites de Azure

11. **Versión de API Hardcoded** (`AzureDocumentAIController.php:29`)
    - La versión de API está hardcoded: `2024-02-29-preview`
    - **Problema**: Puede quedar obsoleta
    - **Recomendación**: Mover a configuración para facilitar actualizaciones

12. **Controladores Deshabilitados con Código Completo**
    - `GoogleDocumentAIController` y `PdfExtractionController` están implementados pero deshabilitados
    - **Problema**: Código que no se usa puede confundir
    - **Recomendación**: Documentar claramente el estado o mover a rama separada

13. **Falta de Validación de Respuesta de Azure**
    - No se valida la estructura de la respuesta antes de retornar
    - **Problema**: Si Azure cambia el formato, puede fallar silenciosamente
    - **Recomendación**: Validar estructura de respuesta

14. **PdfExtractionController con Lógica Específica de Dominio** (`PdfExtractionController.php:37-144`)
    - El procesamiento de texto está específico para un formato de PDF particular
    - **Problema**: No es genérico, difícil de mantener
    - **Recomendación**: Si se reactiva, considerar hacer más genérico o documentar el formato esperado

15. **Falta de Rate Limiting**
    - No hay límite de requests por usuario/tiempo
    - **Problema**: Puede abusarse del servicio (costos)
    - **Recomendación**: Implementar rate limiting

16. **Falta de Validación de Tenant**
    - Aunque las rutas están en grupo con middleware `tenant`, no hay validación explícita
    - **Problema**: Documentos de un tenant podrían ser procesados incorrectamente
    - **Recomendación**: Verificar que el proceso esté aislado por tenant si es necesario

17. **Falta de Almacenamiento de Resultados**
    - Los resultados no se almacenan en la base de datos
    - **Problema**: No hay historial de procesamientos
    - **Recomendación**: Considerar almacenar resultados si se necesita auditoría

18. **Método `processPdfText` Muy Largo** (`PdfExtractionController.php:37-144`)
    - El método tiene más de 100 líneas
    - **Problema**: Difícil de mantener y testear
    - **Recomendación**: Dividir en métodos más pequeños

