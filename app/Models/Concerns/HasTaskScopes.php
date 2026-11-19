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

trait HasTaskScopes
{
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
}
