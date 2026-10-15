<?php

/**
 * Part of the javiergalvez-ia ecosystem.
 *
 * @author    Javier Gálvez <contacto@javiergalvez.com>
 * @license   GNU AGPLv3 (or Commercial Enterprise License)
 * @link      https://javiergalvez.com
 * @link      https://gitlab.com/javiergalvez-ia/jg_dashboard
 */

return [
    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', App\Models\User::class),
        ],
    ],

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

    'jwt' => [
        'secret' => env('JWT_SECRET'),
        'key_id' => env('JWT_KEY_ID', 'v1'),
        'keys' => [
            // Associative map of 'kid' => 'secret' for seamless key rotation
        ],
        'ttl' => (int) env('JWT_TTL_MINUTES', 60),
        'algo' => 'HS256',
        'blacklist_enabled' => env('JWT_BLACKLIST_ENABLED', true),
        'blacklist_cache_ttl' => (int) env('JWT_BLACKLIST_CACHE_TTL', 3600),
    ],
];
