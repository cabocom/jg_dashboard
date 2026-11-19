<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Crypt;
use Throwable;

trait HasAgentSecrets
{
    /**
     * Transition agent to ACTIVE with permanent credential.
     */
    public function markAsActive(string $secretHash, ?string $plainSecret = null): void
    {
        $payload = [
            'status' => 'ACTIVE',
            'permanent_secret_hash' => $secretHash,
            'last_heartbeat_at' => now(),
        ];

        if ($plainSecret !== null) {
            $payload['permanent_secret_encrypted'] = $plainSecret;
        }

        $this->update($payload);
    }

    /**
     * Safely decrypts and retrieves the permanent secret for HMAC computation.
     */
    public function getDecryptedSecret(): ?string
    {
        if (empty($this->permanent_secret_encrypted)) {
            return null;
        }

        try {
            return $this->permanent_secret_encrypted;
        } catch (Throwable) {
            try {
                $raw = $this->getRawOriginal('permanent_secret_encrypted');
                return $raw ? Crypt::decryptString($raw) : null;
            } catch (Throwable) {
                return null;
            }
        }
    }

    /**
     * Resolve cryptographic HMAC secret for this agent.
     */
    public function resolveHmacSecret(): ?string
    {
        $secret = $this->getDecryptedSecret();
        if (!empty($secret)) {
            return $secret;
        }

        if (is_array($this->metadata_json)) {
            $metaSecret = $this->metadata_json['secret'] ?? $this->metadata_json['internal_secret'] ?? null;
            if (!empty($metaSecret)) {
                return (string) $metaSecret;
            }
        }

        if ($this->agent_id === 'a0000000-0000-0000-0000-000000000002') {
            return config('agent.devops_secret');
        }

        if ($this->agent_id === 'a0000000-0000-0000-0000-000000000001') {
            return config('agent.conversational_secret') ?: config('agent.internal_secret');
        }

        $configKey = 'agent.secrets.' . $this->agent_id;
        return config($configKey) ?: config('agent.internal_secret');
    }

    /**
     * Verify a presented permanent secret against the stored hash.
     */
    public function verifyPermanentSecret(string $plainSecret): bool
    {
        if (empty($this->permanent_secret_hash)) {
            return false;
        }

        $hash = hash('sha256', trim($plainSecret));
        return hash_equals((string) $this->permanent_secret_hash, $hash);
    }
}
