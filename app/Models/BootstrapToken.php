<?php

/**
 * Part of the javiergalvez-ia ecosystem.
 *
 * @author    Javier Gálvez
 * @license   GNU AGPLv3 (or Commercial Enterprise License)
 * @link      https://javiergalvez.com
 * @link      https://gitlab.com/javiergalvez-ia/jg_dashboard
 */

namespace App\Models;

use App\Models\Concerns\HasTokenScopes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class BootstrapToken extends Model
{
    use HasFactory;
    use HasTokenScopes;

    protected $table = 'dashboard_bootstrap_tokens';

    protected $attributes = [
        'consumed' => false,
    ];

    protected $fillable = [
        'token_hash',
        'agent_id',
        'expires_at',
        'consumed',
        'consumed_at',
    ];

    protected $hidden = [
        'token_hash',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'consumed' => 'boolean',
        ];
    }

    /**
     * Agent this bootstrap token belongs to.
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'agent_id', 'agent_id');
    }

    /**
     * Check if token is consumed.
     */
    public function isConsumed(): bool
    {
        return (bool) $this->consumed;
    }

    /**
     * Check if token has expired.
     * Enforces explicit expires_at and strict absolute TTL limit of 24h from creation.
     */
    public function isExpired(): bool
    {
        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return true;
        }

        if ($this->created_at !== null && $this->created_at->copy()->addHours(24)->isPast()) {
            return true;
        }

        return false;
    }

    /**
     * Check if token is currently valid (not consumed and not expired).
     */
    public function isValid(): bool
    {
        return !$this->isConsumed() && !$this->isExpired();
    }

    /**
     * Revoke the token immediately by expiring it.
     */
    public function revoke(): bool
    {
        return $this->update([
            'expires_at' => now(),
        ]);
    }

    /**
     * Consume the token for agent onboarding (one-time usage).
     */
    public function consume(): bool
    {
        if (!$this->isValid()) {
            return false;
        }

        return $this->update([
            'consumed' => true,
            'consumed_at' => now(),
        ]);
    }

    /**
     * Calculate SHA-256 hash for raw token string.
     */
    public static function hashToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    /**
     * Generate a new bootstrap token for an agent with standard TTL (default 24 hours).
     * Returns an associative array with the plaintext token and the created model instance.
     *
     * @return array{plaintext_token: string, token: self}
     */
    public static function createTokenForAgent(Agent|string $agent, int $ttlHours = 24): array
    {
        $agentId = $agent instanceof Agent ? $agent->agent_id : $agent;
        $plainToken = Str::random(48);
        $tokenHash = static::hashToken($plainToken);

        $model = static::create([
            'token_hash' => $tokenHash,
            'agent_id' => $agentId,
            'expires_at' => now()->addHours($ttlHours),
            'consumed' => false,
        ]);

        return [
            'plaintext_token' => $plainToken,
            'token' => $model,
        ];
    }


}
