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

use App\Models\Agent;
use App\Models\AuditEvent;
use App\Models\BootstrapToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class AgentRegistrationService
{
    /**
     * Create a new agent in PENDING state and generate a secure bootstrap token with 24h TTL.
     * The plaintext token is returned only once and only its hash is persisted in the database.
     *
     * @param array<string, mixed>|null $capabilities
     * @param array<string, mixed>|null $metadata
     * @return array{
     *     agent: Agent,
     *     agent_id: string,
     *     name: string,
     *     status: string,
     *     bootstrap_token: string,
     *     expires_at: string,
     *     ttl_hours: int
     * }
     */
    public function createAgent(
        string $name,
        ?string $version = null,
        ?array $capabilities = null,
        ?array $metadata = null,
        int $ttlHours = 24,
        ?User $adminUser = null
    ): array {
        $trimmedName = trim($name);
        if (empty($trimmedName)) {
            throw new InvalidArgumentException('El nombre del agente no puede estar vacío.');
        }

        return DB::transaction(function () use ($trimmedName, $version, $capabilities, $metadata, $ttlHours, $adminUser) {
            $agent = Agent::create([
                'name' => $trimmedName,
                'version' => $version,
                'status' => 'PENDING',
                'capabilities_json' => $capabilities,
                'metadata_json' => $metadata,
            ]);

            $tokenData = BootstrapToken::createTokenForAgent($agent, $ttlHours);

            AuditEvent::record(
                eventType: 'AGENT_CREATED',
                actionSummary: "Agente [{$agent->name}] creado en estado PENDING con bootstrap token de {$ttlHours}h",
                payload: [
                    'agent_id' => $agent->agent_id,
                    'name' => $agent->name,
                    'ttl_hours' => $ttlHours,
                    'expires_at' => $tokenData['token']->expires_at->toIso8601String(),
                ],
                user: $adminUser,
                agent: $agent
            );

            return [
                'agent' => $agent,
                'agent_id' => $agent->agent_id,
                'name' => $agent->name,
                'status' => $agent->status,
                'bootstrap_token' => $tokenData['plaintext_token'],
                'expires_at' => $tokenData['token']->expires_at->toIso8601String(),
                'ttl_hours' => $ttlHours,
            ];
        });
    }

    /**
     * Regenerate a new bootstrap token for a PENDING agent, expiring any unconsumed tokens.
     *
     * @return array{agent: Agent, bootstrap_token: string, expires_at: string}
     */
    public function __construct(
        protected ?BootstrapTokenService $tokenService = null,
    ) {
        $this->tokenService ??= new BootstrapTokenService();
    }

    public function regenerateBootstrapToken(
        Agent|string $agent,
        int $ttlHours = 24,
        ?User $adminUser = null
    ): array {
        return $this->tokenService->regenerateBootstrapToken($agent, $ttlHours, $adminUser);
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
        return $this->tokenService->validateBootstrapToken($plainToken);
    }

    /**
     * Consume a bootstrap token, transition agent from PENDING to ACTIVE,
     * and issue a permanent agent secret (returned in plaintext only once).
     *
     * @param array<string, mixed>|null $capabilities
     * @param array<string, mixed>|null $metadata
     * @return array{
     *     agent: Agent,
     *     agent_id: string,
     *     name: string,
     *     status: string,
     *     permanent_secret: string,
     *     registered_at: string
     * }
     *
     * @throws RuntimeException
     */
    public function consumeBootstrapTokenAndActivate(
        string $plainToken,
        ?string $version = null,
        ?array $capabilities = null,
        ?array $metadata = null
    ): array {
        $validation = $this->validateBootstrapToken($plainToken);

        if (!$validation['valid']) {
            $reason = $validation['reason'];
            if ($reason === 'TOKEN_ALREADY_CONSUMED') {
                throw new RuntimeException('Bootstrap token ya consumido.', 401);
            }
            if ($reason === 'TOKEN_EXPIRED') {
                throw new RuntimeException('Bootstrap token expirado (>24h).', 401);
            }
            throw new RuntimeException('Bootstrap token no válido o inexistente.', 401);
        }

        /** @var BootstrapToken $token */
        $token = $validation['token'];
        /** @var Agent $agent */
        $agent = $validation['agent'];

        if (!$agent) {
            throw new RuntimeException('Agente asociado no encontrado.', 401);
        }

        if ($agent->status !== 'PENDING') {
            throw new RuntimeException("El agente no se encuentra en estado pendiente (estado actual: [{$agent->status}]).", 401);
        }

        return DB::transaction(function () use ($token, $agent, $version, $capabilities, $metadata) {
            // Lock row for update to prevent concurrent double consumption
            $lockedToken = BootstrapToken::where('id', $token->id)->lockForUpdate()->first();

            if (!$lockedToken || !$lockedToken->isValid()) {
                throw new RuntimeException('Bootstrap token no válido o ya consumido.', 401);
            }

            // 1. Mark token as consumed
            $lockedToken->consume();

            // 2. Generate permanent secret
            $permanentSecret = 'sec_' . Str::random(48);
            $secretHash = hash('sha256', $permanentSecret);

            // 3. Update agent attributes and activate
            $agent->markAsActive($secretHash, $permanentSecret);

            if ($version !== null) {
                $agent->version = $version;
            }
            if ($capabilities !== null) {
                $agent->capabilities_json = $capabilities;
            }
            if ($metadata !== null) {
                $agent->metadata_json = array_merge($agent->metadata_json ?? [], $metadata);
            }
            $agent->save();

            // 4. Record audit event
            AuditEvent::record(
                eventType: 'AGENT_ACTIVATED',
                actionSummary: "Agente [{$agent->name}] activado con éxito tras consumir bootstrap token",
                payload: [
                    'agent_id' => $agent->agent_id,
                    'name' => $agent->name,
                    'version' => $agent->version,
                ],
                agent: $agent
            );

            return [
                'agent' => $agent,
                'agent_id' => $agent->agent_id,
                'name' => $agent->name,
                'status' => $agent->status,
                'permanent_secret' => $permanentSecret,
                'registered_at' => $agent->last_heartbeat_at?->toIso8601String() ?? now()->toIso8601String(),
            ];
        });
    }

    /**
     * Revoke all unconsumed bootstrap tokens for a given agent.
     *
     * @return int Number of tokens revoked.
     */
    public function revokeBootstrapTokens(
        Agent|string $agent,
        ?User $adminUser = null
    ): int {
        return $this->tokenService->revokeBootstrapTokens($agent, $adminUser);
    }
}


