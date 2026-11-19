<?php

/**
 * Part of the javiergalvez-ia ecosystem.
 *
 * @author    Javier Gálvez
 * @license   GNU AGPLv3 (or Commercial Enterprise License)
 * @link      https://javiergalvez.com
 * @link      https://gitlab.com/javiergalvez-ia/jg_dashboard
 */

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait HasApprovalScopes
{
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
}
