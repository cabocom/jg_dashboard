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

interface OutputFormatterInterface
{
    /**
     * Format AgentOutput response to match client application expectations.
     *
     * @param  array  $agentResponse Raw response data returned by jg_agente / AgentHttpClient
     * @param  string  $format Client format flavor ('generic', 'chatbot', 'ci_cd')
     * @param  array  $metadata Additional metadata to include
     * @return array Client-formatted output
     */
    public function format(array $agentResponse, string $format = 'generic', array $metadata = []): array;
}
