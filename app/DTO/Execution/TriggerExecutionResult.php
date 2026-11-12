<?php

declare(strict_types=1);

/**
 * Part of the javiergalvez-ia ecosystem.
 *
 * @author    Javier Gálvez
 * @license   GNU AGPLv3 (or Commercial Enterprise License)
 * @link      https://javiergalvez.com
 * @link      https://gitlab.com/javiergalvez-ia/jg_dashboard
 */

namespace App\DTO\Execution;

use Illuminate\Http\JsonResponse;

readonly class TriggerExecutionResult
{
    public function __construct(
        public int $statusCode,
        public array $payload,
        public string $correlationId,
    ) {}

    public function toResponse(): JsonResponse
    {
        return response()
            ->json($this->payload, $this->statusCode)
            ->header('X-Correlation-ID', $this->correlationId);
    }
}

