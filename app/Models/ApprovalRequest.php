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
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalRequest extends Model
{
    use HasFactory;

    protected $table = 'dashboard_approval_requests';

    protected $attributes = [
        'status' => 'PENDING',
        'risk_level' => 'HIGH',
    ];

    protected $fillable = [
        'thread_id',
        'agent_id',
        'task_id',
        'status',
        'risk_level',
        'processing_started_at',
        'proposed_plan_json',
        'compensation_commands_json',
        'user_id',
        'justification',
        'resolved_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'proposed_plan_json' => 'array',
            'compensation_commands_json' => 'array',
            'processing_started_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * Agent originating the task/plan.
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'agent_id', 'agent_id');
    }

    /**
     * User (operator/admin) resolving the approval request.
     */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'PENDING';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'PROCESSING';
    }

    public function isApproved(): bool
    {
        return $this->status === 'APPROVED';
    }

    public function isRejected(): bool
    {
        return $this->status === 'REJECTED';
    }

    public function isResolved(): bool
    {
        return in_array($this->status, ['APPROVED', 'REJECTED', 'TIMEOUT', 'CANCELLED']);
    }

    /**
     * Approve the proposed plan with optional operator justification.
     */
    public function approve(User|int $user, ?string $justification = null): bool
    {
        if (! $this->isPending() && ! $this->isProcessing()) {
            return false;
        }

        $userId = $user instanceof User ? $user->id : $user;

        return $this->update([
            'status' => 'APPROVED',
            'user_id' => $userId,
            'justification' => $justification,
            'resolved_at' => now(),
        ]);
    }

    /**
     * Reject the proposed plan with mandatory or optional operator justification.
     */
    public function reject(User|int $user, ?string $justification = null): bool
    {
        if (! $this->isPending() && ! $this->isProcessing()) {
            return false;
        }

        $userId = $user instanceof User ? $user->id : $user;

        return $this->update([
            'status' => 'REJECTED',
            'user_id' => $userId,
            'justification' => $justification,
            'resolved_at' => now(),
        ]);
    }

    /**
     * Scope for pending approval requests.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'PENDING');
    }

    /**
     * Scope for resolved approval requests (APPROVED or REJECTED).
     */
    public function scopeResolved(Builder $query): Builder
    {
        return $query->whereIn('status', ['APPROVED', 'REJECTED']);
    }

    /**
     * Scope for approved requests.
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'APPROVED');
    }

    /**
     * Scope for rejected requests.
     */
    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', 'REJECTED');
    }

    /**
     * Scope by risk level.
     */
    public function scopeByRisk(Builder $query, string $riskLevel): Builder
    {
        return $query->where('risk_level', strtoupper($riskLevel));
    }

    /**
     * Scope for stale processing approval requests exceeding TTL.
     */
    public function scopeStaleProcessing(Builder $query, int $ttlSeconds = 120): Builder
    {
        return $query->where('status', 'PROCESSING')
            ->whereNotNull('processing_started_at')
            ->where('processing_started_at', '<=', now()->subSeconds($ttlSeconds));
    }

    /**
     * Transition cleanly to PROCESSING storing the started_at timestamp.
     */
    public function markProcessing(): bool
    {
        return $this->update([
            'status' => 'PROCESSING',
            'processing_started_at' => now(),
        ]);
    }

    /**
     * Atomically revert a stale PROCESSING request back to PENDING.
     */
    public function revertToPendingFromStale(string $reason = 'TTL exceeded'): bool
    {
        if ($this->status !== 'PROCESSING') {
            return false;
        }

        return $this->update([
            'status' => 'PENDING',
            'processing_started_at' => null,
        ]);
    }
}
