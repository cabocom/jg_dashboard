<?php

/**
 * Part of the javiergalvez-ia ecosystem.
 *
 * @author    Javier Gálvez <contacto@javiergalvez.com>
 * @license   GNU AGPLv3 (or Commercial Enterprise License)
 * @link      https://javiergalvez.com
 * @link      https://gitlab.com/javiergalvez-ia/jg_dashboard
 */

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
