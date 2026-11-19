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

use App\Models\Concerns\HasApprovalScopes;
use App\Models\Concerns\HasApprovalState;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalRequest extends Model
{
    use HasApprovalScopes;
    use HasApprovalState;
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
