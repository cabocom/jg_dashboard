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

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ApiToken extends Model
{
    use HasFactory;

    protected $table = 'dashboard_api_tokens';

    protected $fillable = [
        'user_id',
        'name',
        'token_hash',
        'token_prefix',
        'scopes_json',
        'last_used_at',
        'expires_at',
        'revoked',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scopes_json' => 'array',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked' => 'boolean',
        ];
    }

    /**
     * Relación con el usuario propietario del token.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Genera un nuevo token de acceso con prefijo seguro y hash SHA-256 en base de datos.
     *
     * @param  array<string>  $scopes
     * @return array{token: string, model: ApiToken}
     */
    public static function generate(
        string $name,
        ?User $user = null,
        array $scopes = ['mcp:read', 'mcp:execute'],
        ?DateTimeInterface $expiresAt = null
    ): array {
        $plainToken = 'jgmcp_' . Str::random(40);

        $model = static::create([
            'user_id' => $user?->id,
            'name' => $name,
            'token_hash' => hash('sha256', $plainToken),
            'token_prefix' => 'jgmcp_',
            'scopes_json' => $scopes,
            'expires_at' => $expiresAt,
            'revoked' => false,
        ]);

        return [
            'token' => $plainToken,
            'model' => $model,
        ];
    }

    /**
     * Comprueba si el token se encuentra vigente y no revocado.
     */
    public function isValid(): bool
    {
        if ($this->revoked) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Determina si el token posee el alcance (scope) solicitado.
     */
    public function hasScope(string $scope): bool
    {
        $scopes = $this->scopes_json ?? [];

        return in_array('*', $scopes, true) || in_array($scope, $scopes, true);
    }
}
