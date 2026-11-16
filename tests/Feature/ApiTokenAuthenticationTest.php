<?php

/**
 * Part of the javiergalvez-ia ecosystem.
 *
 * @author    Javier Gálvez
 * @license   GNU AGPLv3 (or Commercial Enterprise License)
 * @link      https://javiergalvez.com
 * @link      https://gitlab.com/javiergalvez-ia/jg_dashboard
 */

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApiTokenAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('auth.api_token')->get('/test-api-token-auth', function () {
            return response()->json([
                'user_id' => auth()->id(),
                'token_id' => request()->attributes->get('api_token')->id,
            ]);
        });

        Route::middleware('auth.api_token:mcp:execute')->get('/test-api-token-scope', function () {
            return response()->json(['status' => 'authorized']);
        });
    }

    private function createUser(string $email = 'test@example.com'): User
    {
        return User::create([
            'name' => 'Test User',
            'email' => $email,
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);
    }

    public function test_api_token_generation_stores_sha256_hash_only(): void
    {
        $user = $this->createUser('token1@example.com');
        $generated = ApiToken::generate('Test MCP Token', $user, ['mcp:read']);

        $plain = $generated['token'];
        $model = $generated['model'];

        $this->assertStringStartsWith('jgmcp_', $plain);
        $this->assertEquals(hash('sha256', $plain), $model->token_hash);
        $this->assertNotEquals($plain, $model->token_hash);
        $this->assertTrue($model->isValid());
        $this->assertTrue($model->hasScope('mcp:read'));
        $this->assertFalse($model->hasScope('mcp:execute'));
    }

    public function test_api_token_validity_and_expiration(): void
    {
        $user = $this->createUser('token2@example.com');
        $tokenData = ApiToken::generate('Expired Token', $user, ['*'], now()->subHour());
        $tokenModel = $tokenData['model'];

        $this->assertFalse($tokenModel->isValid());

        $validTokenData = ApiToken::generate('Active Token', $user);
        $activeModel = $validTokenData['model'];
        $this->assertTrue($activeModel->isValid());

        $activeModel->update(['revoked' => true]);
        $this->assertFalse($activeModel->fresh()->isValid());
    }

    public function test_middleware_rejects_missing_bearer_token(): void
    {
        $response = $this->getJson('/test-api-token-auth');

        $response->assertStatus(401)
            ->assertJson(['error' => 'Unauthorized: Missing or empty bearer token']);
    }

    public function test_middleware_rejects_invalid_token(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer jgmcp_invalid_fake_token')
            ->getJson('/test-api-token-auth');

        $response->assertStatus(401)
            ->assertJson(['error' => 'Unauthorized: Invalid, expired or revoked API token']);
    }

    public function test_middleware_rejects_revoked_or_expired_token(): void
    {
        $user = $this->createUser('token3@example.com');
        $tokenData = ApiToken::generate('Revoked Token', $user);
        $tokenData['model']->update(['revoked' => true]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $tokenData['token'])
            ->getJson('/test-api-token-auth');

        $response->assertStatus(401);
    }

    public function test_middleware_authenticates_valid_token_and_updates_last_used(): void
    {
        $user = $this->createUser('token4@example.com');
        $tokenData = ApiToken::generate('Valid Access Token', $user);

        $response = $this->withHeader('Authorization', 'Bearer ' . $tokenData['token'])
            ->getJson('/test-api-token-auth');

        $response->assertStatus(200)
            ->assertJson([
                'user_id' => $user->id,
                'token_id' => $tokenData['model']->id,
            ]);

        $this->assertNotNull($tokenData['model']->fresh()->last_used_at);
    }

    public function test_middleware_enforces_required_scopes(): void
    {
        $user = $this->createUser('token5@example.com');
        $readOnlyToken = ApiToken::generate('Read Only Token', $user, ['mcp:read']);

        $response = $this->withHeader('Authorization', 'Bearer ' . $readOnlyToken['token'])
            ->getJson('/test-api-token-scope');

        $response->assertStatus(403)
            ->assertJson(['error' => "Forbidden: Token lacks required scope 'mcp:execute'"]);

        $executeToken = ApiToken::generate('Execute Token', $user, ['mcp:execute']);
        $successResponse = $this->withHeader('Authorization', 'Bearer ' . $executeToken['token'])
            ->getJson('/test-api-token-scope');

        $successResponse->assertStatus(200)
            ->assertJson(['status' => 'authorized']);
    }
}
