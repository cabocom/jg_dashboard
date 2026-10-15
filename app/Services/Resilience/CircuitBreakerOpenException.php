<?php

/**
 * Part of the javiergalvez-ia ecosystem.
 *
 * @author    Javier Gálvez <contacto@javiergalvez.com>
 * @license   GNU AGPLv3 (or Commercial Enterprise License)
 * @link      https://javiergalvez.com
 * @link      https://gitlab.com/javiergalvez-ia/jg_dashboard
 */

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
