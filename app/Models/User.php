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

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Determine if the user can access the Filament panel (FINDv2-H-003).
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return in_array($this->role, ['admin', 'operator']);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isOperator(): bool
    {
        return in_array($this->role, ['admin', 'operator']);
    }

    public function isViewer(): bool
    {
        return in_array($this->role, ['admin', 'operator', 'viewer']);
    }

    /**
     * Check if user has any of the given roles.
     * Supports array or comma-separated string, case-insensitive.
     *
     * @param string|array<string> $roles
     */
    public function hasRole(string|array $roles): bool
    {
        $roleList = is_array($roles) 
            ? $roles 
            : array_map('trim', explode(',', $roles));

        $normalizedRoles = array_map('strtolower', $roleList);
        $userRole = strtolower($this->role ?? '');

        // Direct match
        if (in_array($userRole, $normalizedRoles, true)) {
            return true;
        }

        // Role hierarchy: admin satisfies operator and viewer; operator satisfies viewer
        if ($userRole === 'admin') {
            return true;
        }

        if ($userRole === 'operator' && in_array('viewer', $normalizedRoles, true)) {
            return true;
        }

        return false;
    }

    /**
     * Check if user has a specific permission based on RBAC matrix.
     */
    public function hasPermission(string $permission): bool
    {
        $matrix = [
            'agents:manage' => ['admin'],
            'agents:register' => ['admin'],
            'hitl:approve' => ['admin', 'operator'],
            'hitl:reject' => ['admin', 'operator'],
            'tasks:trigger' => ['admin', 'operator'],
            'dashboard:view' => ['admin', 'operator', 'viewer'],
            'audit:view' => ['admin'],
        ];

        $allowedRoles = $matrix[$permission] ?? ['admin'];

        return $this->hasRole($allowedRoles);
    }

    /**
     * Get list of effective permissions for the user based on RBAC matrix.
     *
     * @return array<string>
     */
    public function getPermissions(): array
    {
        $matrix = [
            'agents:manage' => ['admin'],
            'agents:register' => ['admin'],
            'hitl:approve' => ['admin', 'operator'],
            'hitl:reject' => ['admin', 'operator'],
            'tasks:trigger' => ['admin', 'operator'],
            'dashboard:view' => ['admin', 'operator', 'viewer'],
            'audit:view' => ['admin'],
        ];

        $effective = [];
        foreach ($matrix as $permission => $roles) {
            if ($this->hasRole($roles)) {
                $effective[] = $permission;
            }
        }

        return $effective;
    }

    /**
     * Approval requests resolved by this user.
     */
    public function approvalRequests(): HasMany
    {
        return $this->hasMany(ApprovalRequest::class, 'user_id');
    }

    /**
     * Audit events initiated by this user.
     */
    public function auditEvents(): HasMany
    {
        return $this->hasMany(AuditEvent::class, 'user_id');
    }
}

