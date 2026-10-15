<?php

/**
 * Part of the javiergalvez-ia ecosystem.
 *
 * @author    Javier Gálvez
 * @license   GNU AGPLv3 (or Commercial Enterprise License)
 * @link      https://javiergalvez.com
 * @link      https://gitlab.com/javiergalvez-ia/jg_dashboard
 */

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    protected JwtService $jwtService;

    public function __construct(JwtService $jwtService)
    {
        $this->jwtService = $jwtService;
    }

    /**
     * Authenticate user credentials and return JWT payload on success.
     *
     * @return array{access_token: string, token_type: string, expires_in: int, user: array<string, mixed>}|null
     */
    public function attempt(string $email, string $password): ?array
    {
        $user = User::where('email', $email)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            return null;
        }

        $ttlMinutes = (int) config('auth.jwt.ttl', 60);
        $token = $this->jwtService->generateToken($user, $ttlMinutes);

        return [
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => $ttlMinutes * 60,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ];
    }

    /**
     * Hash a plain-text password using Laravel configured hasher (Bcrypt / Argon2id).
     */
    public function hashPassword(string $plainPassword): string
    {
        return Hash::make($plainPassword);
    }

    /**
     * Verify if a plain password matches a given hash.
     */
    public function verifyPassword(string $plainPassword, string $hashedPassword): bool
    {
        return Hash::check($plainPassword, $hashedPassword);
    }

    /**
     * Get underlying JWT service.
     */
    public function jwt(): JwtService
    {
        return $this->jwtService;
    }
}
