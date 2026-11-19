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

trait HasAgentScopes
{
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
}
