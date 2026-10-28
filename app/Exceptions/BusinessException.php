<?php

namespace App\Exceptions;

use Exception;

/**
 * Error de regla de negocio (stock insuficiente, orden no pagable, etc.).
 * Se renderiza como JSON con el código HTTP indicado.
 */
class BusinessException extends Exception
{
    public function __construct(
        string $message,
        protected int $status = 422,
        protected array $errors = [],
    ) {
        parent::__construct($message);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function errors(): array
    {
        return $this->errors;
    }
}
