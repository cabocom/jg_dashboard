<?php

/**
 * Part of the javiergalvez-ia ecosystem.
 *
 * @author    Javier Gálvez
 * @license   GNU AGPLv3 (or Commercial Enterprise License)
 * @link      https://javiergalvez.com
 * @link      https://gitlab.com/javiergalvez-ia/jg_dashboard
 */

namespace App\Services\Agents;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use RuntimeException;

class AgentHmacSigner
{
    /**
     * Generate security headers and HMAC signature following the canonical contract:
     * canonical_string = METHOD\nPATH\nTIMESTAMP\nSHA256(RAW_BODY)[\nNONCE]
     *
     * @return array<string, string>
     */
    public function generateSignatureHeaders(
        string $body = '',
        string $method = 'POST',
        string $path = '/executions/trigger',
        ?string $internalSecret = null,
        ?string $agentId = null,
        ?string &$correlationId = null,
        ?string $nonce = null
    ): array {
        if (empty($internalSecret)) {
            throw new RuntimeException('Seguridad (CWE-798): El secreto interno para firma HMAC no está configurado (JG_AGENTE_SECRET).');
        }

        $timestamp = (string) time();
        $normalizedMethod = strtoupper(trim($method));
        $normalizedPath = '/'.ltrim($path, '/');
        $bodyHash = hash('sha256', $body);

        $signature = $this->computeHmac($normalizedMethod, $normalizedPath, $timestamp, $bodyHash, $internalSecret, $nonce);
        $resolvedAgentId = $this->resolveAgentId($agentId);

        if (empty($correlationId)) {
            $correlationId = (string) Str::uuid();
        }

        $headers = [
            'Accept' => 'application/json',
            'User-Agent' => 'jg_dashboard-ControlPlane/1.0',
            'X-Control-Plane' => 'jg_dashboard',
            'X-Timestamp' => $timestamp,
            'X-Signature' => $signature,
            'X-Correlation-ID' => $correlationId,
            'X-Agent-ID' => $resolvedAgentId,
        ];

        if (! empty($nonce)) {
            $headers['X-Nonce'] = $nonce;
        }

        return $headers;
    }

    /**
     * Compute HMAC signature string.
     */
    public function computeHmac(
        string $method,
        string $path,
        string $timestamp,
        string $bodyHash,
        string $secret,
        ?string $nonce = null
    ): string {
        $canonicalString = $method."\n".$path."\n".$timestamp."\n".$bodyHash;
        if (! empty($nonce)) {
            $canonicalString .= "\n".$nonce;
        }

        return hash_hmac('sha256', $canonicalString, $secret);
    }

    /**
     * Resolve effective agent ID with default fallbacks.
     */
    protected function resolveAgentId(?string $agentId): string
    {
        return $agentId ?: Config::get(
            'agent.default_agent_id',
            env('DEFAULT_AGENT_ID', env('AGENT_ID', 'a0000000-0000-0000-0000-000000000002'))
        );
    }
}

