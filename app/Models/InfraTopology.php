<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InfraTopology extends Model
{
    protected $table = 'infra_topology';
    protected $primaryKey = 'target';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'target',
        'nodes_json',
        'edges_json',
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
            'nodes_json' => 'array',
            'edges_json' => 'array',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Total number of nodes in snapshot.
     */
    public function getNodesCountAttribute(): int
    {
        return is_array($this->nodes_json) ? count($this->nodes_json) : 0;
    }

    /**
     * Total number of edges in snapshot.
     */
    public function getEdgesCountAttribute(): int
    {
        return is_array($this->edges_json) ? count($this->edges_json) : 0;
    }

    /**
     * Number of healthy / running nodes.
     */
    public function getHealthyCountAttribute(): int
    {
        if (!is_array($this->nodes_json)) {
            return 0;
        }

        return count(array_filter($this->nodes_json, fn ($n) => in_array($n['status'] ?? '', ['healthy', 'running'])));
    }

    /**
     * Number of down / exited nodes.
     */
    public function getDownCountAttribute(): int
    {
        if (!is_array($this->nodes_json)) {
            return 0;
        }

        return count(array_filter($this->nodes_json, fn ($n) => in_array($n['status'] ?? '', ['down', 'exited', 'dead'])));
    }

    /**
     * Number of warning / degraded nodes.
     */
    public function getWarningCountAttribute(): int
    {
        if (!is_array($this->nodes_json)) {
            return 0;
        }

        return count(array_filter($this->nodes_json, fn ($n) => in_array($n['status'] ?? '', ['warning', 'restarting', 'paused'])));
    }
}
