<?php

/**
 * Part of the javiergalvez-ia ecosystem.
 *
 * @author    Javier Gálvez <contacto@javiergalvez.com>
 * @license   GNU AGPLv3 (or Commercial Enterprise License)
 * @link      https://javiergalvez.com
 * @link      https://gitlab.com/javiergalvez-ia/jg_dashboard
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CircuitBreakerState extends Model
{
    protected $table = 'circuit_breaker_state';
    protected $primaryKey = 'target';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'target',
        'is_tripped',
        'tripped_reason',
        'failure_history_json',
        'updated_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_tripped' => 'boolean',
            'failure_history_json' => 'array',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Scope for tripped circuit breakers.
     */
    public function scopeTripped(Builder $query): Builder
    {
        return $query->where('is_tripped', true);
    }

    /**
     * Scope for healthy / closed circuit breakers.
     */
    public function scopeHealthy(Builder $query): Builder
    {
        return $query->where('is_tripped', false);
    }
}
