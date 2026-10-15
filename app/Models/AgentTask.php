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

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentTask extends Model
{
    protected $table = 'agent_tasks';
    protected $primaryKey = 'task_id';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'task_id',
        'thread_id',
        'agent_id',
        'status',
        'payload_json',
        'created_at',
        'started_at',
        'completed_at',
        'result_json',
        'trace_history_json',
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
            'result_json' => 'array',
            'trace_history_json' => 'array',
            'created_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Scope for tasks waiting for human approval or queued.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', ['QUEUED', 'WAITING_APPROVAL', 'PAUSED']);
    }

    /**
     * Scope for currently executing tasks.
     */
    public function scopeProcessing(Builder $query): Builder
    {
        return $query->where('status', 'PROCESSING');
    }

    /**
     * Scope for failed tasks.
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'FAILED');
    }

    /**
     * Scope for successfully completed tasks.
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'COMPLETED');
    }

    /**
     * Helper to retrieve formatted duration.
     */
    public function getDurationAttribute(): ?string
    {
        if (!$this->started_at) {
            return null;
        }

        $end = $this->completed_at ?? now();
        $diff = $this->started_at->diff($end);

        if ($diff->h > 0) {
            return sprintf('%dh %dm %ds', $diff->h, $diff->i, $diff->s);
        }

        if ($diff->i > 0) {
            return sprintf('%dm %ds', $diff->i, $diff->s);
        }

        return sprintf('%.2fs', $this->started_at->floatDiffInSeconds($end));
    }

    /**
     * Helper to retrieve prompt / instruction from payload.
     */
    public function getPromptAttribute(): ?string
    {
        $payload = $this->payload_json ?? [];
        return $payload['prompt']
            ?? $payload['user_prompt']
            ?? $this->result_json['prompt']
            ?? null;
    }

    /**
     * Helper to retrieve user / username from payload or user_context.
     */
    public function getUserNameAttribute(): ?string
    {
        $payload = $this->payload_json ?? [];
        return $payload['username']
            ?? $payload['user_context']['name']
            ?? $payload['user_context']['email']
            ?? null;
    }

    /**
     * Helper to retrieve agent_id from payload.
     */
    public function getAgentIdAttribute(): ?string
    {
        $payload = $this->payload_json ?? [];
        return $payload['agent_id'] ?? null;
    }

    /**
     * Helper to store agent_id transparently into payload_json.
     */
    public function setAgentIdAttribute(?string $value): void
    {
        $payload = $this->payload_json ?? [];
        $payload['agent_id'] = $value;
        $this->payload_json = $payload;
    }

    /**
     * Mutator for payload_json to preserve agent_id if already set.
     */
    public function setPayloadJsonAttribute($value): void
    {
        $payload = is_string($value) ? json_decode($value, true) : (array) $value;
        if (!empty($this->attributes['payload_json'])) {
            $existing = json_decode($this->attributes['payload_json'], true) ?: [];
            if (!empty($existing['agent_id']) && empty($payload['agent_id'])) {
                $payload['agent_id'] = $existing['agent_id'];
            }
        }
        $this->attributes['payload_json'] = json_encode($payload);
    }

    /**
     * Agente asociado a esta ejecución.
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'agent_id', 'agent_id');
    }
}
