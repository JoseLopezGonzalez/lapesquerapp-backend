<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
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
                ], 401); // 401 Unauthorized
            }

            // Manejar errores HTTP estándar (404, 403, etc.)
            if ($exception instanceof HttpException) {
                return response()->json([
                    'message' => $exception->getMessage() ?: 'Error HTTP.',
                ], $exception->getStatusCode());
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
        // Agrupar errores por categoría
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
        $messages = [];
        
        foreach ($groupedErrors as $category => $errorTypes) {
            foreach ($errorTypes as $errorType => $count) {
                $message = $this->getGenericErrorMessage($category, $errorType);
                if ($message && !in_array($message, $messages)) {
                    $messages[] = $message;
                }
            }
        }
        
        // Si hay un solo error, devolverlo directamente
        if (count($messages) === 1) {
            return $messages[0];
        }
        
        // Si hay múltiples errores, combinarlos
        if (count($messages) > 1) {
            $lastMessage = array_pop($messages);
            return implode('. ', $messages) . ' y ' . $lastMessage;
        }
        
        return 'Hay errores en los datos enviados.';
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
        
        // Si no se puede traducir, devolver el mensaje original
        return $errorMessage;
    }

    /* public function render($request, Throwable $exception)
    {
        return parent::render($request, $exception); // Esto muestra los errores normales en pantalla
    }
 */
}
