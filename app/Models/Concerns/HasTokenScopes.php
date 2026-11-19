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

trait HasTokenScopes
{
    /**
     * Scope for valid tokens (not consumed and not yet expired).
     */
    public function scopeValid(Builder $query): Builder
    {
        return $query->where('consumed', false)
            ->where('expires_at', '>', now());
    }

    /**
     * Scope for consumed tokens.
     */
    public function scopeConsumed(Builder $query): Builder
    {
        return $query->where('consumed', true);
    }

    /**
     * Scope for expired tokens that were never consumed.
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('consumed', false)
            ->where('expires_at', '<=', now());
    }
}
