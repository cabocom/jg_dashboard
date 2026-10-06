<?php

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
