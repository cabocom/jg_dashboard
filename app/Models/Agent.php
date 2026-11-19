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

use App\Models\Concerns\HasAgentScopes;
use App\Models\Concerns\HasAgentSecrets;
use App\Models\Concerns\HasAgentState;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Agent extends Model
{
    use HasAgentScopes;
    use HasAgentSecrets;
    use HasAgentState;
    use HasFactory;

    protected $table = 'dashboard_agents';
    protected $primaryKey = 'agent_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $attributes = [
        'status' => 'PENDING',
    ];

    protected $fillable = [
        'agent_id',
        'name',
        'version',
        'status',
        'permanent_secret_hash',
        'permanent_secret_encrypted',
        'last_heartbeat_at',
        'capabilities_json',
        'metrics_json',
        'metadata_json',
    ];

    protected $hidden = [
        'permanent_secret_hash',
        'permanent_secret_encrypted',
    ];

    protected $appends = [
        'type',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'capabilities_json' => 'array',
            'metrics_json' => 'array',
            'metadata_json' => 'array',
            'last_heartbeat_at' => 'datetime',
            'permanent_secret_encrypted' => 'encrypted',
        ];
    }

    /**
     * Boot function to automatically generate UUID if not provided.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->agent_id)) {
                $model->agent_id = (string) Str::uuid();
            }
        });
    }



    /**
     * Bootstrap tokens generated for onboarding this agent.
     */
    public function bootstrapTokens(): HasMany
    {
        return $this->hasMany(BootstrapToken::class, 'agent_id', 'agent_id');
    }

    /**
     * Approval requests originated by or involving this agent.
     */
    public function approvalRequests(): HasMany
    {
        return $this->hasMany(ApprovalRequest::class, 'agent_id', 'agent_id');
    }

    /**
     * Audit events associated with this agent.
     */
    public function auditEvents(): HasMany
    {
        return $this->hasMany(AuditEvent::class, 'agent_id', 'agent_id');
    }

    /**
     * Resuelve deterministamente la tipología del agente (DevOps, Conversacional, etc.).
     */
    public function getTypeAttribute(): string
    {
        if (!empty($this->metadata_json['type'])) {
            return (string) $this->metadata_json['type'];
        }
        if (!empty($this->metadata_json['agent_type'])) {
            return (string) $this->metadata_json['agent_type'];
        }
        if (!empty($this->metadata_json['plugin'])) {
            $plugin = strtolower((string) $this->metadata_json['plugin']);
            if (str_contains($plugin, 'devops')) {
                return 'DevOps';
            }
            if (str_contains($plugin, 'conversational') || str_contains($plugin, 'chat')) {
                return 'Conversacional';
            }
            return ucfirst($plugin);
        }
        if (stripos($this->name, 'devops') !== false) {
            return 'DevOps';
        }
        if (stripos($this->name, 'conversacional') !== false || stripos($this->name, 'chat') !== false) {
            return 'Conversacional';
        }
        return 'General';
    }

    /**
     * Tareas y ejecuciones asociadas a este agente.
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(AgentTask::class, 'agent_id', 'agent_id');
    }
}
