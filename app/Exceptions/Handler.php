<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\QueryException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /* Render */
    public function render($request, Throwable $exception)
    {
        // Si la solicitud espera JSON o es una API, forzar la respuesta JSON
        if ($request->expectsJson() || $request->is('api/*')) {

            // Manejar errores de validación
            if ($exception instanceof ValidationException) {
                $errors = $exception->errors();
                $userMessage = $this->formatValidationErrorsForUser($errors);
                
                return response()->json([
                    'message' => 'Error de validación.',
                    'userMessage' => $userMessage,
                    'errors' => $errors, // Detalles técnicos para programadores
                ], 422); // 422 Unprocessable Entity
            }

            // Manejar errores de autenticación
            if ($exception instanceof AuthenticationException) {
                return response()->json([
                    'message' => 'No autenticado.',
                    'userMessage' => 'Debes iniciar sesión para acceder a este recurso.',
                ], 401); // 401 Unauthorized
            }

            // Manejar errores de autorización (policy devuelve false)
            if ($exception instanceof AuthorizationException) {
                return response()->json([
                    'message' => $exception->getMessage() ?: 'No autorizado.',
                    'userMessage' => 'No tienes permisos para realizar esta acción.',
                ], 403); // 403 Forbidden
            }

            // Manejar errores HTTP estándar (404, 403, etc.)
            if ($exception instanceof HttpException) {
                $statusCode = $exception->getStatusCode();
                $userMessage = $this->formatHttpExceptionMessage($statusCode, $exception->getMessage());
                
                return response()->json([
                    'message' => $exception->getMessage() ?: 'Error HTTP.',
                    'userMessage' => $userMessage,
                ], $statusCode);
            }

            // Manejar errores de base de datos (QueryException)
            if ($exception instanceof QueryException) {
                $errorMessage = $exception->getMessage();
                $errorCode = $exception->getCode();
                $sqlState = $exception->errorInfo[0] ?? null;
                
                // Detectar violación de clave única
                // MySQL: código 1062, SQLSTATE 23000
                // PostgreSQL: código 23505, SQLSTATE 23505
                if ($errorCode == 23000 || $errorCode == '23000' || 
                    $errorCode == 1062 || $errorCode == '1062' ||
                    $sqlState == '23000' || $sqlState == '23505' ||
                    stripos($errorMessage, 'Duplicate entry') !== false ||
                    stripos($errorMessage, 'UNIQUE constraint') !== false ||
                    stripos($errorMessage, 'duplicate key value') !== false ||
                    stripos($errorMessage, 'unique constraint') !== false) {
                    
                    $userMessage = $this->formatUniqueConstraintViolationForUser($errorMessage, $request);
                    
                    return response()->json([
                        'message' => 'Error de validación.',
                        'userMessage' => $userMessage,
                        'error' => $errorMessage, // Detalles técnicos para programadores
                    ], 422); // 422 Unprocessable Entity
                }
                
                // Otros errores de base de datos (foreign key, not null, etc.)
                $userMessage = $this->formatQueryExceptionForUser($errorMessage, $request);
                
                return response()->json([
                    'message' => 'Error de base de datos.',
                    'userMessage' => $userMessage,
                    'error' => $errorMessage, // Detalles técnicos para programadores
                ], 500); // 500 Internal Server Error
            }

            // Manejar cualquier otra excepción como error interno del servidor
            $errorMessage = $exception->getMessage();
            $userMessage = $this->formatExceptionMessageForUser($errorMessage, $request);
            
            return response()->json([
                'message' => 'Ocurrió un error inesperado.',
                'userMessage' => $userMessage,
                'error' => $errorMessage, // Detalles técnicos para programadores
            ], 500); // 500 Internal Server Error
        }

        // Si no es una API o no espera JSON, usar el manejo por defecto
        return parent::render($request, $exception);
    }


    /**
     * Formatea los errores de validación en un mensaje legible para el usuario
     * 
     * @param array $errors Array de errores de validación
     * @return string Mensaje en lenguaje natural
     */
    private function formatValidationErrorsForUser(array $errors): string
    {
        $messages = [];
        
        // Primero, intentar usar los mensajes personalizados directamente
        foreach ($errors as $field => $fieldErrors) {
            foreach ($fieldErrors as $error) {
                // Si el mensaje ya está en lenguaje natural (no es un mensaje técnico de Laravel),
                // usarlo directamente
                if (!$this->isTechnicalErrorMessage($error)) {
                    if (!in_array($error, $messages)) {
                        $messages[] = $error;
                    }
                    continue;
                }
                
                // Si es un mensaje técnico, intentar traducirlo
                $translated = $this->translateErrorMessage($error, $field);
                if ($translated && !in_array($translated, $messages)) {
                    $messages[] = $translated;
                }
            }
        }
        
        // Si tenemos mensajes personalizados, usarlos
        if (!empty($messages)) {
            // Si hay un solo error, devolverlo directamente
            if (count($messages) === 1) {
                return $messages[0];
            }
            
            // Si hay múltiples errores, combinarlos
            if (count($messages) > 1) {
                $lastMessage = array_pop($messages);
                return implode('. ', $messages) . ' y ' . $lastMessage;
            }
        }
        
        // Si no hay mensajes personalizados, usar la lógica de categorización
        $groupedErrors = [];
        
        foreach ($errors as $field => $fieldErrors) {
            $category = $this->getFieldCategory($field);
            $errorType = $this->getErrorType($fieldErrors[0]);
            
            if (!isset($groupedErrors[$category])) {
                $groupedErrors[$category] = [];
            }
            
            if (!isset($groupedErrors[$category][$errorType])) {
                $groupedErrors[$category][$errorType] = 0;
            }
            
            $groupedErrors[$category][$errorType]++;
        }
        
        // Generar mensajes genéricos por categoría
        $genericMessages = [];
        
        foreach ($groupedErrors as $category => $errorTypes) {
            foreach ($errorTypes as $errorType => $count) {
                $message = $this->getGenericErrorMessage($category, $errorType);
                if ($message && !in_array($message, $genericMessages)) {
                    $genericMessages[] = $message;
                }
            }
        }
        
        // Si hay un solo error, devolverlo directamente
        if (count($genericMessages) === 1) {
            return $genericMessages[0];
        }
        
        // Si hay múltiples errores, combinarlos
        if (count($genericMessages) > 1) {
            $lastMessage = array_pop($genericMessages);
            return implode('. ', $genericMessages) . ' y ' . $lastMessage;
        }
        
        return 'Hay errores en los datos enviados.';
    }
    
    /**
     * Verifica si un mensaje de error es técnico (de Laravel) o ya está en lenguaje natural
     * 
     * @param string $error Mensaje de error
     * @return bool true si es técnico, false si ya está en lenguaje natural
     */
    private function isTechnicalErrorMessage(string $error): bool
    {
        // Si el mensaje empieza con "Ya existe", "Debe", "Falta", etc., ya está en lenguaje natural
        $naturalLanguagePatterns = [
            '/^Ya existe/i',
            '/^Debe/i',
            '/^Falta/i',
            '/^El .+ (?:es|debe|no puede)/i', // "El nombre es obligatorio", "El nombre debe ser texto", "El nombre no puede tener"
            '/^La .+ (?:es|debe|no puede)/i', // "La descripción es obligatoria", "La descripción debe ser texto", "La descripción no puede tener"
            '/^El .+ no es válido/i',
            '/^La .+ no es válida/i',
            '/^Los datos/i',
            '/^No se puede/i',
            '/^Uno o más/i',
            '/^El formato debe/i', // "El formato debe ser un objeto o array"
        ];
        
        foreach ($naturalLanguagePatterns as $pattern) {
            if (preg_match($pattern, $error)) {
                return false; // Ya está en lenguaje natural
            }
        }
        
        // Patrones de mensajes técnicos de Laravel
        $technicalPatterns = [
            '/^The .+ field is required\.?$/i',
            '/^The .+ must be (?:a|an) .+\.?$/i',
            '/^The .+ format is invalid\.?$/i',
            '/^The selected .+ is invalid\.?$/i',
            '/^The .+ does not exist\.?$/i',
            '/^The .+ has already been taken\.?$/i',
            '/^The .+ may not be greater than/i',
            '/^The .+ may not be less than/i',
            '/^The .+ must be at least/i',
            '/^The .+ must be an? .+\.?$/i',
        ];
        
        foreach ($technicalPatterns as $pattern) {
            if (preg_match($pattern, $error)) {
                return true;
            }
        }
        
        // Si contiene palabras técnicas comunes y empieza con "The", probablemente es técnico
        $technicalKeywords = ['field', 'must be', 'format', 'selected', 'does not exist', 'has already been taken'];
        foreach ($technicalKeywords as $keyword) {
            if (stripos($error, $keyword) !== false && stripos($error, 'The ') === 0) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Obtiene la categoría del campo (prices, details, pallets, etc.)
     * 
     * @param string $field Nombre del campo
     * @return string Categoría del campo
     */
    private function getFieldCategory(string $field): string
    {
        // Campos de precios
        if (preg_match('/^prices\./', $field)) {
            return 'prices';
        }
        
        // Campos de detalles
        if (preg_match('/^details\./', $field)) {
            return 'details';
        }
        
        // Campos de palets
        if (preg_match('/^pallets\./', $field)) {
            return 'pallets';
        }
        
        // Campo supplier.id
        if (preg_match('/^supplier\.id$/', $field)) {
            return 'supplier.id';
        }
        
        // Campos simples
        if (preg_match('/^(date|notes|declaredTotalAmount|declaredTotalNetWeight)$/', $field)) {
            return $field;
        }
        
        return 'other';
    }
    
    /**
     * Obtiene el tipo de error (required, invalid, etc.)
     * 
     * @param string $error Mensaje de error
     * @return string Tipo de error
     */
    private function getErrorType(string $error): string
    {
        if (preg_match('/field is required\.?$/i', $error) || stripos($error, 'required') !== false) {
            return 'required';
        }
        
        if (preg_match('/must be (?:a|an) number\.?$/i', $error)) {
            return 'number';
        }
        
        if (preg_match('/must be (?:a|an) integer\.?$/i', $error)) {
            return 'integer';
        }
        
        if (preg_match('/must be (?:a|an) valid date\.?$/i', $error)) {
            return 'date';
        }
        
        if (preg_match('/is invalid\.?$/i', $error) || preg_match('/does not exist\.?$/i', $error)) {
            return 'invalid';
        }
        
        return 'other';
    }
    
    /**
     * Genera un mensaje genérico basado en la categoría y tipo de error
     * 
     * @param string $category Categoría del campo
     * @param string $errorType Tipo de error
     * @return string|null Mensaje genérico o null si no hay mensaje
     */
    private function getGenericErrorMessage(string $category, string $errorType): ?string
    {
        $messages = [
            'prices' => [
                'required' => 'Falta algún precio',
                'number' => 'Algún precio no es válido',
                'invalid' => 'Algún precio no es válido',
            ],
            'details' => [
                'required' => 'Faltan datos en los detalles',
                'number' => 'Algún dato en los detalles no es válido',
                'invalid' => 'Algún dato en los detalles no es válido',
            ],
            'pallets' => [
                'required' => 'Faltan datos en los palets',
                'number' => 'Algún dato en los palets no es válido',
                'invalid' => 'Algún dato en los palets no es válido',
            ],
            'supplier.id' => [
                'required' => 'Falta el proveedor',
                'invalid' => 'El proveedor no es válido',
            ],
            'date' => [
                'required' => 'Falta la fecha',
                'date' => 'La fecha no es válida',
            ],
            'notes' => [
                'required' => 'Faltan las notas',
            ],
            'declaredTotalAmount' => [
                'required' => 'Falta el importe total declarado',
                'number' => 'El importe total declarado no es válido',
            ],
            'declaredTotalNetWeight' => [
                'required' => 'Falta el peso neto total declarado',
                'number' => 'El peso neto total declarado no es válido',
            ],
            'other' => [
                'required' => 'Faltan datos obligatorios',
                'number' => 'Algún dato no es válido',
                'invalid' => 'Algún dato no es válido',
                'date' => 'Alguna fecha no es válida',
                'integer' => 'Algún dato numérico no es válido',
            ],
        ];
        
        return $messages[$category][$errorType] ?? $messages['other'][$errorType] ?? 'Hay errores en los datos enviados';
    }
    
    
    /**
     * Traduce el mensaje de error a lenguaje natural
     * 
     * @param string $error Mensaje de error técnico
     * @param string $field Nombre del campo
     * @return string Mensaje de error en lenguaje natural
     */
    private function translateErrorMessage(string $error, string $field): string
    {
        // Patrones de mensajes de Laravel (con :attribute reemplazado)
        // "The prices.1.price field is required." -> "Este campo es obligatorio"
        if (preg_match('/field is required\.?$/i', $error)) {
            return 'Este campo es obligatorio';
        }
        
        // "The prices.1.price must be a number." -> "Debe ser un número"
        if (preg_match('/must be (?:a|an) number\.?$/i', $error)) {
            return 'Debe ser un número';
        }
        
        // "The prices.1.price must be an integer." -> "Debe ser un número entero"
        if (preg_match('/must be (?:a|an) integer\.?$/i', $error)) {
            return 'Debe ser un número entero';
        }
        
        // "The prices.1.price must be a valid date." -> "Debe ser una fecha válida"
        if (preg_match('/must be (?:a|an) valid date\.?$/i', $error)) {
            return 'Debe ser una fecha válida';
        }
        
        // "The prices.1.price must be a string." -> "Debe ser texto"
        if (preg_match('/must be (?:a|an) string\.?$/i', $error)) {
            return 'Debe ser texto';
        }
        
        // "The prices.1.price must be an array." -> "Debe ser una lista"
        if (preg_match('/must be (?:a|an) array\.?$/i', $error)) {
            return 'Debe ser una lista';
        }
        
        // "The selected prices.1.price is invalid." -> "El valor seleccionado no es válido"
        if (preg_match('/selected .+ is invalid\.?$/i', $error)) {
            return 'El valor seleccionado no es válido';
        }
        
        // "The prices.1.price does not exist." -> "No existe"
        if (preg_match('/does not exist\.?$/i', $error)) {
            return 'No existe';
        }
        
        // "The name has already been taken." -> "Ya existe un registro con este valor"
        if (preg_match('/has already been taken\.?$/i', $error)) {
            return 'Ya existe un registro con este valor';
        }
        
        // "The prices.1.price must be at least :min." -> "Debe ser al menos X"
        if (preg_match('/must be at least (.+?)\.?$/i', $error, $matches)) {
            return 'Debe ser al menos ' . $matches[1];
        }
        
        // "The prices.1.price must be greater than :min." -> "Debe ser mayor que X"
        if (preg_match('/must be greater than (.+?)\.?$/i', $error, $matches)) {
            return 'Debe ser mayor que ' . $matches[1];
        }
        
        // "The prices.1.price must be less than :max." -> "Debe ser menor que X"
        if (preg_match('/must be less than (.+?)\.?$/i', $error, $matches)) {
            return 'Debe ser menor que ' . $matches[1];
        }
        
        // Si el mensaje contiene "required", traducirlo
        if (stripos($error, 'required') !== false) {
            return 'Este campo es obligatorio';
        }
        
        // Si no se puede traducir, devolver el mensaje original
        return $error;
    }

    /**
     * Formatea el mensaje de excepción genérica para el usuario
     * 
     * @param string $errorMessage Mensaje de error técnico
     * @param \Illuminate\Http\Request|null $request Request para obtener contexto
     * @return string Mensaje en lenguaje natural
     */
    private function formatExceptionMessageForUser(string $errorMessage, $request = null): string
    {
        // Detectar si es una recepción de tipo líneas (modo automático)
        if (stripos($errorMessage, 'RECEPTION_LINES_MODE:') !== false) {
            if (stripos($errorMessage, 'modificar la recepción') !== false) {
                return 'No se puede modificar la recepción porque hay materia prima siendo usada en producción';
            }
            // Extraer el mensaje después del prefijo
            $message = trim(str_ireplace('RECEPTION_LINES_MODE:', '', $errorMessage));
            return $message ?: 'No se puede modificar la recepción porque hay materia prima siendo usada en producción';
        }
        
        // Detectar violaciones de clave foránea relacionadas con cajas y producción
        if (stripos($errorMessage, 'Integrity constraint violation') !== false || 
            stripos($errorMessage, 'foreign key constraint fails') !== false) {
            
            // Detectar si es una violación relacionada con boxes y production_inputs
            if (stripos($errorMessage, 'production_inputs') !== false && 
                stripos($errorMessage, 'boxes') !== false) {
                
                // Verificar si el error viene de una recepción de materia prima
                $isReceptionContext = $request && (
                    $request->is('api/v2/raw-material-receptions/*')
                );
                
                if ($isReceptionContext) {
                    // Intentar obtener el ID de la recepción de la ruta
                    $receptionId = null;
                    if ($request->route('raw_material_reception')) {
                        $receptionId = $request->route('raw_material_reception');
                    } elseif (preg_match('/raw-material-receptions\/(\d+)/', $request->path(), $matches)) {
                        $receptionId = $matches[1];
                    }
                    
                    // Si tenemos el ID, verificar el tipo de recepción
                    if ($receptionId) {
                        try {
                            $reception = \App\Models\RawMaterialReception::find($receptionId);
                            if ($reception && $reception->creation_mode === \App\Models\RawMaterialReception::CREATION_MODE_LINES) {
                                return 'No se puede modificar la recepción porque hay materia prima siendo usada en producción';
                            }
                        } catch (\Exception $e) {
                            // Si falla la consulta, continuar con el mensaje genérico
                        }
                    }
                    
                    // Si es contexto de recepción pero no sabemos el tipo, usar mensaje genérico
                    return 'No se puede modificar la recepción porque hay materia prima siendo usada en producción';
                }
                
                // Si no es contexto de recepción, usar mensaje específico de cajas
                // Intentar extraer el ID de la caja si está en el mensaje
                if (preg_match('/where `id` = (\d+)/i', $errorMessage, $matches)) {
                    $boxId = $matches[1];
                    return "No se puede eliminar la caja #{$boxId} porque está siendo usada en producción";
                }
                return 'No se puede eliminar la caja porque está siendo usada en producción';
            }
            
            // Otras violaciones de clave foránea genéricas
            if (stripos($errorMessage, 'Cannot delete or update a parent row') !== false) {
                return 'No se puede realizar esta operación porque hay datos relacionados que dependen de este registro';
            }
        }
        
        // Mensajes comunes de excepciones del sistema
        if (stripos($errorMessage, 'cajas usadas') !== false || stripos($errorMessage, 'cajas siendo usadas') !== false) {
            if (stripos($errorMessage, 'agregado un nuevo producto') !== false) {
                return 'No se pueden agregar nuevos productos cuando hay cajas usadas en producción';
            }
            if (stripos($errorMessage, 'eliminar todos los productos') !== false) {
                return 'No se pueden eliminar productos cuando hay cajas usadas en producción';
            }
            if (stripos($errorMessage, 'crear nuevos palets') !== false) {
                return 'No se pueden crear nuevos palets cuando hay cajas usadas en producción';
            }
            if (stripos($errorMessage, 'crear nuevas cajas') !== false) {
                return 'No se pueden crear nuevas cajas cuando hay cajas usadas en producción';
            }
            if (stripos($errorMessage, 'eliminar el palet') !== false) {
                return 'No se puede eliminar el palet porque tiene cajas usadas en producción';
            }
            if (stripos($errorMessage, 'eliminar la caja') !== false) {
                return 'No se puede eliminar la caja porque está siendo usada en producción';
            }
            if (stripos($errorMessage, 'modificar la caja') !== false) {
                return 'No se puede modificar la caja porque está siendo usada en producción';
            }
            return 'No se puede realizar esta operación porque hay cajas usadas en producción';
        }
        
        // Si no se puede traducir, devolver un mensaje genérico en lenguaje natural
        return 'Ocurrió un error al procesar la solicitud. Por favor, verifica los datos e intenta nuevamente.';
    }

    /**
     * Formatea el mensaje de excepción de base de datos (QueryException) para el usuario
     * 
     * @param string $errorMessage Mensaje de error técnico
     * @param \Illuminate\Http\Request|null $request Request para obtener contexto
     * @return string Mensaje en lenguaje natural
     */
    private function formatQueryExceptionForUser(string $errorMessage, $request = null): string
    {
        // Detectar violaciones de clave foránea
        if (stripos($errorMessage, 'Integrity constraint violation') !== false || 
            stripos($errorMessage, 'foreign key constraint fails') !== false ||
            stripos($errorMessage, 'Cannot delete or update a parent row') !== false) {
            
            return $this->formatExceptionMessageForUser($errorMessage, $request);
        }
        
        // Detectar errores de campo no nulo
        if (stripos($errorMessage, 'cannot be null') !== false ||
            stripos($errorMessage, 'Column') !== false && stripos($errorMessage, 'cannot be null') !== false) {
            return 'Faltan datos obligatorios. Por favor, completa todos los campos requeridos.';
        }
        
        // Detectar errores de tabla no encontrada
        if (stripos($errorMessage, "doesn't exist") !== false ||
            stripos($errorMessage, 'Table') !== false && stripos($errorMessage, "doesn't exist") !== false) {
            return 'Ocurrió un error en la base de datos. Por favor, contacta al administrador.';
        }
        
        // Detectar errores de conexión
        if (stripos($errorMessage, 'Connection') !== false ||
            stripos($errorMessage, 'SQLSTATE[HY000]') !== false) {
            return 'No se pudo conectar con la base de datos. Por favor, intenta nuevamente más tarde.';
        }
        
        // Mensaje genérico para otros errores de base de datos
        return 'Ocurrió un error al guardar los datos. Por favor, verifica la información e intenta nuevamente.';
    }

    /**
     * Formatea el mensaje de violación de clave única para el usuario
     * 
     * @param string $errorMessage Mensaje de error técnico
     * @param \Illuminate\Http\Request|null $request Request para obtener contexto
     * @return string Mensaje en lenguaje natural
     */
    private function formatUniqueConstraintViolationForUser(string $errorMessage, $request = null): string
    {
        // Detectar violaciones específicas por tabla y campo
        
        // Etiquetas (labels)
        if (stripos($errorMessage, 'labels') !== false && stripos($errorMessage, 'name') !== false) {
            return 'Ya existe una etiqueta con este nombre.';
        }
        
        // Productos (products)
        if (stripos($errorMessage, 'products') !== false) {
            if (stripos($errorMessage, 'name') !== false) {
                return 'Ya existe un producto con este nombre.';
            }
            if (stripos($errorMessage, 'article_gtin') !== false || stripos($errorMessage, 'articleGtin') !== false) {
                return 'Ya existe un producto con este GTIN de artículo.';
            }
            if (stripos($errorMessage, 'box_gtin') !== false || stripos($errorMessage, 'boxGtin') !== false) {
                return 'Ya existe un producto con este GTIN de caja.';
            }
            if (stripos($errorMessage, 'pallet_gtin') !== false || stripos($errorMessage, 'palletGtin') !== false) {
                return 'Ya existe un producto con este GTIN de palet.';
            }
        }
        
        // Clientes (customers)
        if (stripos($errorMessage, 'customers') !== false && stripos($errorMessage, 'name') !== false) {
            return 'Ya existe un cliente con este nombre.';
        }
        
        // Usuarios (users)
        if (stripos($errorMessage, 'users') !== false && stripos($errorMessage, 'email') !== false) {
            return 'Ya existe un usuario con este correo electrónico.';
        }
        
        // Zonas de captura (capture_zones)
        if (stripos($errorMessage, 'capture_zones') !== false && stripos($errorMessage, 'name') !== false) {
            return 'Ya existe una zona de captura con este nombre.';
        }
        
        // Categorías de productos (product_categories)
        if (stripos($errorMessage, 'product_categories') !== false && stripos($errorMessage, 'name') !== false) {
            return 'Ya existe una categoría de producto con este nombre.';
        }
        
        // Familias de productos (product_families)
        if (stripos($errorMessage, 'product_families') !== false && stripos($errorMessage, 'name') !== false) {
            return 'Ya existe una familia de producto con este nombre.';
        }
        
        // Especies (species)
        if (stripos($errorMessage, 'species') !== false) {
            if (stripos($errorMessage, 'name') !== false) {
                return 'Ya existe una especie con este nombre.';
            }
            if (stripos($errorMessage, 'scientific_name') !== false || stripos($errorMessage, 'scientificName') !== false) {
                return 'Ya existe una especie con este nombre científico.';
            }
            if (stripos($errorMessage, 'fao') !== false) {
                return 'Ya existe una especie con este código FAO.';
            }
        }
        
        // Transportes (transports)
        if (stripos($errorMessage, 'transports') !== false) {
            if (stripos($errorMessage, 'name') !== false) {
                return 'Ya existe un transporte con este nombre.';
            }
            if (stripos($errorMessage, 'vat_number') !== false || stripos($errorMessage, 'vatNumber') !== false) {
                return 'Ya existe un transporte con este NIF/CIF.';
            }
        }
        
        // Incoterms
        if (stripos($errorMessage, 'incoterms') !== false && stripos($errorMessage, 'code') !== false) {
            return 'Ya existe un incoterm con este código.';
        }
        
        // Países (countries)
        if (stripos($errorMessage, 'countries') !== false && stripos($errorMessage, 'name') !== false) {
            return 'Ya existe un país con este nombre.';
        }
        
        // Términos de pago (payment_terms)
        if (stripos($errorMessage, 'payment_terms') !== false && stripos($errorMessage, 'name') !== false) {
            return 'Ya existe un término de pago con este nombre.';
        }
        
        // Comerciales (salespeople)
        if (stripos($errorMessage, 'salespeople') !== false && stripos($errorMessage, 'name') !== false) {
            return 'Ya existe un comercial con este nombre.';
        }
        
        // Almacenes (stores)
        if (stripos($errorMessage, 'stores') !== false && stripos($errorMessage, 'name') !== false) {
            return 'Ya existe un almacén con este nombre.';
        }
        
        // Intentar extraer el nombre del campo del mensaje de error
        if (preg_match("/Duplicate entry.*for key ['\"]?(\w+)['\"]?/i", $errorMessage, $matches)) {
            $keyName = $matches[1];
            
            // Si el nombre de la clave contiene el nombre del campo, usarlo
            if (stripos($keyName, 'name') !== false) {
                return 'Ya existe un registro con este nombre.';
            }
            if (stripos($keyName, 'email') !== false) {
                return 'Ya existe un registro con este correo electrónico.';
            }
        }
        
        // Mensaje genérico si no se puede identificar específicamente
        return 'Ya existe un registro con estos datos. Por favor, verifica que no estés duplicando información.';
    }

    /**
     * Formatea el mensaje de excepción HTTP para el usuario
     * 
     * @param int $statusCode Código de estado HTTP
     * @param string|null $message Mensaje original de la excepción
     * @return string Mensaje en lenguaje natural
     */
    private function formatHttpExceptionMessage(int $statusCode, ?string $message = null): string
    {
        switch ($statusCode) {
            case 403:
                return 'No tienes permisos para realizar esta acción.';
            case 404:
                return 'El recurso solicitado no existe.';
            case 405:
                return 'El método utilizado no está permitido para este recurso.';
            case 422:
                return 'Los datos enviados no son válidos.';
            case 429:
                return 'Has realizado demasiadas solicitudes. Por favor, espera un momento antes de intentar nuevamente.';
            case 500:
                return 'Ocurrió un error en el servidor. Por favor, intenta nuevamente más tarde.';
            case 503:
                return 'El servicio no está disponible temporalmente. Por favor, intenta nuevamente más tarde.';
            default:
                // Si el mensaje original ya está en lenguaje natural, usarlo
                if ($message && !$this->isTechnicalErrorMessage($message)) {
                    return $message;
                }
                return 'Ocurrió un error al procesar la solicitud.';
        }
    }

    /* public function render($request, Throwable $exception)
    {
        return parent::render($request, $exception); // Esto muestra los errores normales en pantalla
    }
 */
}
