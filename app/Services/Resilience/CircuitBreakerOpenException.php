<?php

namespace App\Services\Resilience;

use RuntimeException;

class CircuitBreakerOpenException extends RuntimeException
{
    protected string $target;

    public function __construct(string $target, string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        $this->target = $target;
        $msg = $message ?: "El circuito de resiliencia para el destino '{$target}' se encuentra ABIERTO (OPEN).";
        parent::__construct($msg, $code, $previous);
    }

    public function getTarget(): string
    {
        return $this->target;
    }
}
