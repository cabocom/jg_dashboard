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
        'permanent_secret_encrypted',
        'last_heartbeat_at',
        'capabilities_json',
        'metrics_json',
        'metadata_json',
    ];

    protected $hidden = [
        'permanent_secret_hash',
        'permanent_secret_encrypted',
    ];

    protected $appends = [
        'type',
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
            'permanent_secret_encrypted' => 'encrypted',
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

    /**
     * Scope for agents that have timed out without heartbeat in the last N seconds.
     */
    public function scopeTimedOut(Builder $query, int $timeoutSeconds = 90): Builder
    {
        $threshold = now()->subSeconds($timeoutSeconds);

        return $query->where('status', 'ACTIVE')
            ->where(function (Builder $subQuery) use ($threshold) {
                $subQuery->whereNull('last_heartbeat_at')
                    ->orWhere('last_heartbeat_at', '<', $threshold);
            });
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
        } catch (\Throwable) {
            try {
                $raw = $this->getRawOriginal('permanent_secret_encrypted');
                return $raw ? \Illuminate\Support\Facades\Crypt::decryptString($raw) : null;
            } catch (\Throwable) {
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
            return config('agent.devops_secret')
                ?: env('JG_DEVOPS_SECRET', env('JG_DEVOPS_AGENT_SECRET', env('DEVOPS60K_SECRET', env('DEVOPS60K_API_KEY'))));
        }

        if ($this->agent_id === 'a0000000-0000-0000-0000-000000000001') {
            return config('agent.internal_secret')
                ?: env('JG_CONVERSATIONAL_SECRET', env('JG_AGENTE_SECRET'));
        }

        $envKey = 'AGENT_SECRET_' . strtoupper(str_replace(['-', ' '], '_', $this->agent_id));
        return env($envKey) ?: config('agent.internal_secret');
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

    /**
     * Resuelve deterministamente la tipología del agente (DevOps, Conversacional, etc.).
     */
    public function getTypeAttribute(): string
    {
        if (!empty($this->metadata_json['type'])) {
            return (string) $this->metadata_json['type'];
        }
        if (!empty($this->metadata_json['agent_type'])) {
            return (string) $this->metadata_json['agent_type'];
        }
        if (!empty($this->metadata_json['plugin'])) {
            $plugin = strtolower((string) $this->metadata_json['plugin']);
            if (str_contains($plugin, 'devops')) {
                return 'DevOps';
            }
            if (str_contains($plugin, 'conversational') || str_contains($plugin, 'chat')) {
                return 'Conversacional';
            }
            return ucfirst($plugin);
        }
        if (stripos($this->name, 'devops') !== false) {
            return 'DevOps';
        }
        if (stripos($this->name, 'conversacional') !== false || stripos($this->name, 'chat') !== false) {
            return 'Conversacional';
        }
        return 'General';
    }

    /**
     * Tareas y ejecuciones asociadas a este agente.
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(AgentTask::class, 'agent_id', 'agent_id');
    }
}
