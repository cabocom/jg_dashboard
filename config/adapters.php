<?php

/**
 * Part of the javiergalvez-ia ecosystem.
 *
 * @author    Javier Gálvez
 * @license   GNU AGPLv3 (or Commercial Enterprise License)
 * @link      https://javiergalvez.com
 * @link      https://gitlab.com/javiergalvez-ia/jg_dashboard
 */

return [
    /*
    |--------------------------------------------------------------------------
    | External Adapters Configuration (Fase 08)
    |--------------------------------------------------------------------------
    |
    | Configuration for external stimuli adapters (HTTP Webhooks, Chatbots,
    | CI/CD triggers) that normalize arbitrary payloads into canonical
    | AgentInput contracts.
    |
    */

    'webhook_secret' => env('ADAPTER_WEBHOOK_SECRET', null),

    'require_auth' => (bool) env('ADAPTER_REQUIRE_AUTH', false),

    'default_agent_id' => env('ADAPTER_DEFAULT_AGENT_ID', env('DEFAULT_AGENT_ID', 'a0000000-0000-0000-0000-000000000001')),

    'default_format' => env('ADAPTER_DEFAULT_FORMAT', 'generic'),

    'timeout' => (int) env('ADAPTER_TIMEOUT', 120),
];
