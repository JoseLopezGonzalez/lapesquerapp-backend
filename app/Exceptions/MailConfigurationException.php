<?php

namespace App\Exceptions;

use Exception;

class MailConfigurationException extends Exception
{
    public function __construct(string $message = 'La configuración de email del tenant no está completa', int $code = 500)
    {
        parent::__construct($message, $code);
    }

    public function render($request)
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => 'Mail configuration incomplete',
            'userMessage' => 'La configuración de email no está completa. Por favor, configure el servidor SMTP en Settings.',
        ], $this->getCode());
    }
}

