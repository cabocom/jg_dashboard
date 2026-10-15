<?php

/**
 * Part of the javiergalvez-ia ecosystem.
 *
 * @author    Javier Gálvez <contacto@javiergalvez.com>
 * @license   GNU AGPLv3 (or Commercial Enterprise License)
 * @link      https://javiergalvez.com
 * @link      https://gitlab.com/javiergalvez-ia/jg_dashboard
 */

use App\Models\Agent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

beforeEach(function () {
    Cache::flush();
    $this->plainSecret = 'sec_'.bin2hex(random_bytes(24));
    $this->secretHash = hash('sha256', $this->plainSecret);

    $this->agent = Agent::create([
        'name' => 'nonce-test-agent',
        'status' => 'ACTIVE',
        'permanent_secret_hash' => $this->secretHash,
        'permanent_secret_encrypted' => $this->plainSecret,
        'last_heartbeat_at' => now(),
    ]);
});

function buildSignedNonceHeaders(string $method, string $path, string $agentId, string $secret, array|string $body = '', ?string $nonce = null, ?int $timestamp = null): array
{
    $ts = $timestamp ?? time();
    $rawBody = is_array($body) ? json_encode($body, JSON_UNESCAPED_SLASHES) : $body;
    $bodyHash = hash('sha256', $rawBody);
    $normalizedPath = '/'.ltrim($path, '/');
    $normalizedMethod = strtoupper($method);

    $canonicalString = "{$normalizedMethod}\n{$normalizedPath}\n{$ts}\n{$bodyHash}";
    if ($nonce !== null && $nonce !== '') {
        $canonicalString .= "\n{$nonce}";
    }
    $signature = hash_hmac('sha256', $canonicalString, $secret);

    $headers = [
        'X-Agent-ID' => $agentId,
        'X-Timestamp' => (string) $ts,
        'X-Signature' => $signature,
        'Accept' => 'application/json',
    ];

    if ($nonce !== null && $nonce !== '') {
        $headers['X-Nonce'] = $nonce;
    }

    return $headers;
}

test('HMAC request with fresh X-Nonce passes and replay within window is rejected with 401', function () {
    $nonce = (string) Str::uuid();
    $payload = ['status' => 'healthy', 'timestamp' => time()];
    $headers = buildSignedNonceHeaders('POST', '/api/v1/agents/heartbeat', $this->agent->agent_id, $this->plainSecret, $payload, $nonce);

    // 1. Primera petición: 200 OK
    $this->withHeaders($headers)
        ->postJson('/api/v1/agents/heartbeat', $payload)
        ->assertStatus(200);

    // 2. Replay idéntico con el mismo nonce dentro de la ventana de 300s: 401 Nonce replayed
    $this->withHeaders($headers)
        ->postJson('/api/v1/agents/heartbeat', $payload)
        ->assertStatus(401)
        ->assertJson([
            'error' => 'Unauthorized',
            'message' => 'Nonce replayed',
        ]);
});

test('HMAC request with invalid nonce format is rejected with 401', function () {
    $invalidNonce = 'bad/nonce'; // Caracteres no permitidos
    $payload = ['status' => 'healthy'];
    $headers = buildSignedNonceHeaders('POST', '/api/v1/agents/heartbeat', $this->agent->agent_id, $this->plainSecret, $payload, $invalidNonce);

    $this->withHeaders($headers)
        ->postJson('/api/v1/agents/heartbeat', $payload)
        ->assertStatus(401)
        ->assertJson([
            'error' => 'Unauthorized',
            'message' => 'Formato de X-Nonce inválido.',
        ]);
});

test('HMAC request missing X-Nonce is rejected when hmac_require_nonce is enforced', function () {
    Config::set('security.hmac_require_nonce', true);

    $payload = ['status' => 'healthy'];
    // Sin nonce
    $headers = buildSignedNonceHeaders('POST', '/api/v1/agents/heartbeat', $this->agent->agent_id, $this->plainSecret, $payload, null);

    $this->withHeaders($headers)
        ->postJson('/api/v1/agents/heartbeat', $payload)
        ->assertStatus(401)
        ->assertJson([
            'error' => 'Unauthorized',
            'message' => 'Cabecera X-Nonce requerida.',
        ]);
});
