<?php

use App\Models\Agent;
use App\Models\BootstrapToken;

test('bootstrap token creation and relationship with agent', function () {
    $agent = Agent::create([
        'name' => 'db-cluster-agent',
        'status' => 'PENDING',
    ]);

    $token = BootstrapToken::create([
        'token_hash' => hash('sha256', 'super-secret-token'),
        'agent_id' => $agent->agent_id,
        'expires_at' => now()->addHours(24),
    ]);

    expect($token->id)->not->toBeNull()
        ->and($token->agent_id)->toBe($agent->agent_id)
        ->and($token->consumed)->toBeFalse()
        ->and($token->consumed_at)->toBeNull()
        ->and($token->isExpired())->toBeFalse()
        ->and($token->isValid())->toBeTrue();

    // Verify relations
    expect($token->agent->agent_id)->toBe($agent->agent_id);
    expect($agent->bootstrapTokens)->toHaveCount(1);
    expect($agent->bootstrapTokens->first()->id)->toBe($token->id);
});

test('bootstrap token hides token_hash when serialized', function () {
    $agent = Agent::create([
        'name' => 'worker-01',
    ]);

    $token = BootstrapToken::create([
        'token_hash' => hash('sha256', 'raw-token-value'),
        'agent_id' => $agent->agent_id,
        'expires_at' => now()->addHours(24),
    ]);

    $serialized = $token->toArray();
    expect($serialized)->not->toHaveKey('token_hash');
});

test('bootstrap token expiration detection with 24h TTL', function () {
    $agent = Agent::create(['name' => 'expiring-agent']);

    $validToken = BootstrapToken::create([
        'token_hash' => hash('sha256', 'valid-token'),
        'agent_id' => $agent->agent_id,
        'expires_at' => now()->addHours(24),
    ]);

    $expiredToken = BootstrapToken::create([
        'token_hash' => hash('sha256', 'expired-token'),
        'agent_id' => $agent->agent_id,
        'expires_at' => now()->subMinute(),
    ]);

    expect($validToken->isExpired())->toBeFalse()
        ->and($validToken->isValid())->toBeTrue();

    expect($expiredToken->isExpired())->toBeTrue()
        ->and($expiredToken->isValid())->toBeFalse();
});

test('bootstrap token one-time consumption mechanics', function () {
    $agent = Agent::create(['name' => 'consume-agent']);

    $token = BootstrapToken::create([
        'token_hash' => hash('sha256', 'token-to-consume'),
        'agent_id' => $agent->agent_id,
        'expires_at' => now()->addHours(24),
    ]);

    expect($token->isConsumed())->toBeFalse();

    // First consumption succeeds
    $result = $token->consume();
    expect($result)->toBeTrue()
        ->and($token->fresh()->isConsumed())->toBeTrue()
        ->and($token->fresh()->consumed_at)->not->toBeNull()
        ->and($token->fresh()->isValid())->toBeFalse();

    // Second consumption fails (prevent reuse)
    $secondAttempt = $token->fresh()->consume();
    expect($secondAttempt)->toBeFalse();
});

test('expired token cannot be consumed', function () {
    $agent = Agent::create(['name' => 'agent-expired-consume']);

    $expiredToken = BootstrapToken::create([
        'token_hash' => hash('sha256', 'expired-token-try-consume'),
        'agent_id' => $agent->agent_id,
        'expires_at' => now()->subHours(2),
    ]);

    expect($expiredToken->consume())->toBeFalse()
        ->and($expiredToken->fresh()->isConsumed())->toBeFalse()
        ->and($expiredToken->fresh()->consumed_at)->toBeNull();
});

test('bootstrap token query scopes', function () {
    $agent = Agent::create(['name' => 'scoped-agent']);

    // Valid
    BootstrapToken::create([
        'token_hash' => hash('sha256', 't1'),
        'agent_id' => $agent->agent_id,
        'expires_at' => now()->addHours(24),
        'consumed' => false,
    ]);

    // Consumed
    BootstrapToken::create([
        'token_hash' => hash('sha256', 't2'),
        'agent_id' => $agent->agent_id,
        'expires_at' => now()->addHours(12),
        'consumed' => true,
        'consumed_at' => now(),
    ]);

    // Expired
    BootstrapToken::create([
        'token_hash' => hash('sha256', 't3'),
        'agent_id' => $agent->agent_id,
        'expires_at' => now()->subHours(1),
        'consumed' => false,
    ]);

    expect(BootstrapToken::valid()->count())->toBe(1)
        ->and(BootstrapToken::consumed()->count())->toBe(1)
        ->and(BootstrapToken::expired()->count())->toBe(1);
});

test('createTokenForAgent helper generates secure plaintext and persists hash with 24h TTL', function () {
    $agent = Agent::create(['name' => 'auto-token-agent']);

    $result = BootstrapToken::createTokenForAgent($agent, 24);

    expect($result)->toHaveKeys(['plaintext_token', 'token'])
        ->and(strlen($result['plaintext_token']))->toBeGreaterThanOrEqual(32);

    $tokenModel = $result['token'];
    expect($tokenModel->agent_id)->toBe($agent->agent_id)
        ->and($tokenModel->isConsumed())->toBeFalse()
        ->and($tokenModel->isValid())->toBeTrue();

    // Verify hash verification
    $expectedHash = hash('sha256', $result['plaintext_token']);
    expect($tokenModel->getRawOriginal('token_hash'))->toBe($expectedHash);

    // Verify TTL is ~24 hours
    $diffInHours = (int) round(now()->diffInHours($tokenModel->expires_at));
    expect($diffInHours)->toBe(24);
});
