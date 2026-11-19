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

trait HasAgentState
{
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
        if (! $this->isActive() || ! $this->last_heartbeat_at) {
            return false;
        }

        return $this->last_heartbeat_at->diffInSeconds(now()) <= $timeoutSeconds;
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
}
