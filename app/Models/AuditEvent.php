<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditEvent extends Model
{
    use HasFactory;

    protected $table = 'dashboard_audit_events';

    const UPDATED_AT = null;

    protected $fillable = [
        'event_type',
        'user_id',
        'agent_id',
        'ip_address',
        'user_agent',
        'action_summary',
        'payload_json',
        'created_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload_json' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * User who triggered the administrative action (if initiated by a human).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Agent associated with the audit event (if applicable).
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'agent_id', 'agent_id');
    }

    /**
     * Scope for filtering events by specific type.
     */
    public function scopeOfType(Builder $query, string $eventType): Builder
    {
        return $query->where('event_type', $eventType);
    }

    /**
     * Scope for filtering events by agent.
     */
    public function scopeForAgent(Builder $query, string $agentId): Builder
    {
        return $query->where('agent_id', $agentId);
    }

    /**
     * Scope for filtering events by user.
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Static helper for logging audit records in the control plane.
     */
    public static function record(
        string $eventType,
        string $actionSummary,
        ?array $payload = null,
        User|int|null $user = null,
        Agent|string|null $agent = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): self {
        $userId = $user instanceof User ? $user->id : $user;
        $agentId = $agent instanceof Agent ? $agent->agent_id : $agent;

        return static::create([
            'event_type' => $eventType,
            'action_summary' => $actionSummary,
            'payload_json' => $payload,
            'user_id' => $userId,
            'agent_id' => $agentId,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'created_at' => now(),
        ]);
    }
}
