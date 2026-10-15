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
    /*
    |--------------------------------------------------------------------------
    | HMAC anti-replay (X-Nonce)
    |--------------------------------------------------------------------------
    |
    | Cuando es true, las peticiones firmadas sin cabecera X-Nonce son rechazadas (401).
    | Mantener en false mientras existan firmantes legacy (sin nonce); activar tras migrar
    | todos los clientes. Con nonce presente, este es single-use y forma parte de la firma.
    |
    */
    'hmac_require_nonce' => (bool) env('HMAC_REQUIRE_NONCE', false),
];
