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
    | DevOps60K Backend API Connection
    |--------------------------------------------------------------------------
    |
    | Configuration for interacting with the FastAPI agent and worker backend.
    |
    */

    'api_url' => env('JG_AGENTE_API_URL', env('DEVOPS60K_API_URL', 'http://jg_agente:8000')),

    'api_key' => env('JG_AGENTE_API_KEY', env('DEVOPS60K_API_KEY', env('API_KEY_ADMIN', 'admin_key_pro'))),

    'timeout' => (int) env('DEVOPS60K_API_TIMEOUT', 15),

    'retry_times' => (int) env('DEVOPS60K_API_RETRY_TIMES', 2),

    'retry_sleep_ms' => (int) env('DEVOPS60K_API_RETRY_SLEEP_MS', 200),
];
