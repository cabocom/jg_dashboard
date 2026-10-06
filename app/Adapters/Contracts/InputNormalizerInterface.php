<?php

namespace App\Adapters\Contracts;

use Illuminate\Http\Request;

interface InputNormalizerInterface
{
    /**
     * Normalize an arbitrary external stimulus (HTTP Request, array, webhook payload)
     * into a canonical AgentInput array contract.
     *
     * @param  Request|array  $stimulus The raw stimulus from external client
     * @param  array  $context Extra contextual parameters (IP, headers, credentials)
     * @return array Canonical AgentInput structure ready for jg_agente
     */
    public function normalize(Request|array $stimulus, array $context = []): array;
}
