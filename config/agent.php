<?php

return [
    /*
    |--------------------------------------------------------------------------
    | jg_agente Internal API Connection Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for interacting with the internal REST API of jg_agente
    | instances located within the private isolated network.
    |
    */

    'base_url' => env('JG_AGENTE_API_URL', env('AGENT_API_URL', 'http://jg_agente:8000')),

    'internal_secret' => env('JG_AGENTE_SECRET', env('AGENT_INTERNAL_SECRET', null)),

    'devops_secret' => env('JG_DEVOPS_AGENT_SECRET', env('DEVOPS60K_SECRET', env('DEVOPS60K_API_KEY', 'sec_devops_agent_preprovisioned_key_12345'))),

    'default_agent_id' => env('DEFAULT_AGENT_ID', env('AGENT_ID', 'a0000000-0000-0000-0000-000000000002')),

    'timeout' => (int) env('AGENT_HTTP_TIMEOUT', 15),

    'retry_times' => (int) env('AGENT_HTTP_RETRY_TIMES', 2),

    'retry_sleep_ms' => (int) env('AGENT_HTTP_RETRY_SLEEP_MS', 200),

    'verify_ssl' => (bool) env('AGENT_HTTP_VERIFY_SSL', true),
];
