<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

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
}
