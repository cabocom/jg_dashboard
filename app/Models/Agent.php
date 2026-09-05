<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Agent extends Model
{
    use HasFactory;

    protected $table = 'dashboard_agents';
    protected $primaryKey = 'agent_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $attributes = [
        'status' => 'PENDING',
    ];

    protected $fillable = [
        'agent_id',
        'name',
        'version',
        'status',
        'permanent_secret_hash',
        'last_heartbeat_at',
        'capabilities_json',
        'metrics_json',
        'metadata_json',
    ];

    protected $hidden = [
        'permanent_secret_hash',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'capabilities_json' => 'array',
            'metrics_json' => 'array',
            'metadata_json' => 'array',
            'last_heartbeat_at' => 'datetime',
        ];
    }

    /**
     * Boot function to automatically generate UUID if not provided.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->agent_id)) {
                $model->agent_id = (string) Str::uuid();
            }
        });
    }

    /**
     * Scope for active registered agents.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'ACTIVE');
    }

    /**
     * Scope for pending registration agents.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'PENDING');
    }

    /**
     * Scope for offline agents.
     */
    public function scopeOffline(Builder $query): Builder
    {
        return $query->where('status', 'OFFLINE');
    }

    public function isActive(): bool
    {
        return $this->status === 'ACTIVE';
    }

    public function isPending(): bool
    {
        return $this->status === 'PENDING';
    }

    public function isOffline(): bool
    {
        return $this->status === 'OFFLINE';
    }

    /**
     * Determines if the agent is considered healthy based on heartbeat recency.
     */
    public function isHealthy(int $timeoutSeconds = 90): bool
    {
        if (!$this->isActive() || !$this->last_heartbeat_at) {
            return false;
        }

        return $this->last_heartbeat_at->diffInSeconds(now()) <= $timeoutSeconds;
    }

    /**
     * Transition agent to ACTIVE with permanent credential.
     */
    public function markAsActive(string $secretHash): void
    {
        $this->update([
            'status' => 'ACTIVE',
            'permanent_secret_hash' => $secretHash,
            'last_heartbeat_at' => now(),
        ]);
    }

    /**
     * Transition agent to OFFLINE.
     */
    public function markAsOffline(): void
    {
        $this->update([
            'status' => 'OFFLINE',
        ]);
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

    /**
     * Update agent heartbeat timestamp and latest metrics.
     */
    public function recordHeartbeat(?array $metrics = null): void
    {
        $payload = [
            'last_heartbeat_at' => now(),
            'status' => 'ACTIVE',
        ];

        if ($metrics !== null) {
            $payload['metrics_json'] = $metrics;
        }

        $this->update($payload);
    }

    /**
     * Bootstrap tokens generated for onboarding this agent.
     */
    public function bootstrapTokens(): HasMany
    {
        return $this->hasMany(BootstrapToken::class, 'agent_id', 'agent_id');
    }

    /**
     * Approval requests originated by or involving this agent.
     */
    public function approvalRequests(): HasMany
    {
        return $this->hasMany(ApprovalRequest::class, 'agent_id', 'agent_id');
    }

    /**
     * Audit events associated with this agent.
     */
    public function auditEvents(): HasMany
    {
        return $this->hasMany(AuditEvent::class, 'agent_id', 'agent_id');
    }
}
