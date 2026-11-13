<?php

declare(strict_types=1);

namespace App\Services\Agents;

use App\Models\Agent;
use App\Models\AuditEvent;
use App\Models\BootstrapToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BootstrapTokenService
{
    /**
     * Regenerate a bootstrap token for a PENDING agent, invalidating existing ones.
     *
     * @return array{agent: Agent, bootstrap_token: string, expires_at: string}
     *
     * @throws RuntimeException
     */
    public function regenerateBootstrapToken(
        Agent|string $agent,
        int $ttlHours = 24,
        ?User $adminUser = null
    ): array {
        $agentModel = $agent instanceof Agent ? $agent : Agent::find($agent);

        if (! $agentModel) {
            throw new RuntimeException('Agente no encontrado.');
        }

        if ($agentModel->status !== 'PENDING') {
            throw new RuntimeException("No se puede regenerar el bootstrap token para un agente en estado [{$agentModel->status}].");
        }

        return DB::transaction(function () use ($agentModel, $ttlHours, $adminUser) {
            // Invalidate existing unconsumed tokens
            $agentModel->bootstrapTokens()
                ->where('consumed', false)
                ->update(['expires_at' => now()]);

            $tokenData = BootstrapToken::createTokenForAgent($agentModel, $ttlHours);

            AuditEvent::record(
                eventType: 'AGENT_BOOTSTRAP_TOKEN_REGENERATED',
                actionSummary: "Bootstrap token regenerado para el agente [{$agentModel->name}]",
                payload: [
                    'agent_id' => $agentModel->agent_id,
                    'ttl_hours' => $ttlHours,
                ],
                user: $adminUser,
                agent: $agentModel
            );

            return [
                'agent' => $agentModel,
                'bootstrap_token' => $tokenData['plaintext_token'],
                'expires_at' => $tokenData['token']->expires_at->toIso8601String(),
            ];
        });
    }

    /**
     * Validate a presented plaintext bootstrap token.
     *
     * @return array{
     *     valid: bool,
     *     reason: ?string,
     *     token: ?BootstrapToken,
     *     agent: ?Agent
     * }
     */
    public function validateBootstrapToken(string $plainToken): array
    {
        $hash = BootstrapToken::hashToken(trim($plainToken));
        $token = BootstrapToken::where('token_hash', $hash)->with('agent')->first();

        if (! $token) {
            return [
                'valid' => false,
                'reason' => 'TOKEN_NOT_FOUND',
                'token' => null,
                'agent' => null,
            ];
        }

        if ($token->isConsumed()) {
            return [
                'valid' => false,
                'reason' => 'TOKEN_ALREADY_CONSUMED',
                'token' => $token,
                'agent' => $token->agent,
            ];
        }

        if ($token->isExpired()) {
            return [
                'valid' => false,
                'reason' => 'TOKEN_EXPIRED',
                'token' => $token,
                'agent' => $token->agent,
            ];
        }

        return [
            'valid' => true,
            'reason' => null,
            'token' => $token,
            'agent' => $token->agent,
        ];
    }

    /**
     * Revoke all active bootstrap tokens for an agent.
     */
    public function revokeBootstrapTokens(
        Agent|string $agent,
        ?User $adminUser = null
    ): int {
        $agentModel = $agent instanceof Agent ? $agent : Agent::find($agent);

        if (! $agentModel) {
            throw new RuntimeException('Agente no encontrado.', 404);
        }

        $count = $agentModel->bootstrapTokens()
            ->where('consumed', false)
            ->where('expires_at', '>', now())
            ->update(['expires_at' => now()]);

        AuditEvent::record(
            eventType: 'AGENT_BOOTSTRAP_TOKENS_REVOKED',
            actionSummary: "Se revocaron {$count} bootstrap token(s) para el agente [{$agentModel->name}]",
            payload: [
                'agent_id' => $agentModel->agent_id,
                'revoked_count' => $count,
            ],
            user: $adminUser,
            agent: $agentModel
        );

        return $count;
    }
}
