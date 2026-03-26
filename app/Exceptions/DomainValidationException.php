<?php

namespace App\Exceptions;

use Exception;

class DomainValidationException extends Exception
{
    public function __construct(
        public readonly string $domainCode,
        public readonly string $userMessage,
        public readonly array $errors = [],
        public readonly int $status = 422,
        string $message = 'Error de validación de dominio.'
    ) {
        parent::__construct($message, $status);
    }
}

