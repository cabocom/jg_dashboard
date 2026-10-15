<?php

/**
 * Part of the javiergalvez-ia ecosystem.
 *
 * @author    Javier Gálvez
 * @license   GNU AGPLv3 (or Commercial Enterprise License)
 * @link      https://javiergalvez.com
 * @link      https://gitlab.com/javiergalvez-ia/jg_dashboard
 */

use App\Models\Agent;
use App\Models\AuditEvent;
use App\Models\BootstrapToken;
use App\Models\User;
use App\Services\Agents\AgentRegistrationService;

beforeEach(function () {
    $this->service = app(AgentRegistrationService::class);
    $this->admin = User::create([
        'name' => 'Admin User',
        'email' => 'admin_test@example.com',
        'password' => 'secret123',
        'role' => 'admin',
    ]);
});

test('createAgent generates uuid, pending status, and secure 24h bootstrap token', function () {
    $result = $this->service->createAgent(
        name: 'Cluster-Worker-Alpha',
        version: '1.0.0',
        capabilities: ['db:restart', 'cache:flush'],
        metadata: ['region' => 'eu-west-1'],
        ttlHours: 24,
        adminUser: $this->admin
    );

    expect($result)->toHaveKeys(['agent', 'agent_id', 'name', 'status', 'bootstrap_token', 'expires_at', 'ttl_hours'])
        ->and($result['name'])->toBe('Cluster-Worker-Alpha')
        ->and($result['status'])->toBe('PENDING')
        ->and(strlen($result['agent_id']))->toBe(36) // UUID format
        ->and(strlen($result['bootstrap_token']))->toBeGreaterThanOrEqual(32)
        ->and($result['ttl_hours'])->toBe(24);

    // Verify Agent in DB
    $agent = Agent::find($result['agent_id']);
    expect($agent)->not->toBeNull()
        ->and($agent->name)->toBe('Cluster-Worker-Alpha')
        ->and($agent->isPending())->toBeTrue()
        ->and($agent->capabilities_json)->toBe(['db:restart', 'cache:flush'])
        ->and($agent->metadata_json)->toBe(['region' => 'eu-west-1']);

    // Verify ONLY the hash is in the database (never the plaintext token)
    $tokenHash = hash('sha256', $result['bootstrap_token']);
    $storedToken = BootstrapToken::where('agent_id', $agent->agent_id)->first();
    expect($storedToken)->not->toBeNull()
        ->and($storedToken->getRawOriginal('token_hash'))->toBe($tokenHash)
        ->and($storedToken->consumed)->toBeFalse()
        ->and($storedToken->isExpired())->toBeFalse();

    // Verify audit event was recorded
    $audit = AuditEvent::where('agent_id', $agent->agent_id)->first();
    expect($audit)->not->toBeNull()
        ->and($audit->event_type)->toBe('AGENT_CREATED')
        ->and($audit->user_id)->toBe($this->admin->id);
});

test('createAgent throws exception if name is empty', function () {
    $this->expectException(InvalidArgumentException::class);
    $this->service->createAgent('   ');
});

test('validateBootstrapToken correctly classifies valid, missing, consumed and expired tokens', function () {
    $created = $this->service->createAgent('Validator-Agent');
    $plainToken = $created['bootstrap_token'];

    // 1. Valid token
    $validCheck = $this->service->validateBootstrapToken($plainToken);
    expect($validCheck['valid'])->toBeTrue()
        ->and($validCheck['reason'])->toBeNull()
        ->and($validCheck['agent']->agent_id)->toBe($created['agent_id']);

    // 2. Token not found
    $missingCheck = $this->service->validateBootstrapToken('non-existent-token-xyz');
    expect($missingCheck['valid'])->toBeFalse()
        ->and($missingCheck['reason'])->toBe('TOKEN_NOT_FOUND');

    // 3. Consumed token
    $tokenRecord = $validCheck['token'];
    $tokenRecord->consume();
    $consumedCheck = $this->service->validateBootstrapToken($plainToken);
    expect($consumedCheck['valid'])->toBeFalse()
        ->and($consumedCheck['reason'])->toBe('TOKEN_ALREADY_CONSUMED');

    // 4. Expired token
    $created2 = $this->service->createAgent('Expired-Agent', ttlHours: -1);
    $expiredCheck = $this->service->validateBootstrapToken($created2['bootstrap_token']);
    expect($expiredCheck['valid'])->toBeFalse()
        ->and($expiredCheck['reason'])->toBe('TOKEN_EXPIRED');
});

test('regenerateBootstrapToken expires previous token and issues a new one', function () {
    $created = $this->service->createAgent('Regen-Agent', ttlHours: 24, adminUser: $this->admin);
    $firstToken = $created['bootstrap_token'];

    $regen = $this->service->regenerateBootstrapToken($created['agent_id'], ttlHours: 24, adminUser: $this->admin);

    expect($regen['bootstrap_token'])->not->toBe($firstToken);

    // Old token is now expired/invalid
    $oldCheck = $this->service->validateBootstrapToken($firstToken);
    expect($oldCheck['valid'])->toBeFalse()
        ->and($oldCheck['reason'])->toBe('TOKEN_EXPIRED');

    // New token is valid
    $newCheck = $this->service->validateBootstrapToken($regen['bootstrap_token']);
    expect($newCheck['valid'])->toBeTrue();

    // Verify audit event
    $regenAudit = AuditEvent::where('agent_id', $created['agent_id'])
        ->where('event_type', 'AGENT_BOOTSTRAP_TOKEN_REGENERATED')
        ->first();
    expect($regenAudit)->not->toBeNull();
});

test('regenerateBootstrapToken rejects active agents', function () {
    $agent = Agent::create([
        'name' => 'Active Agent',
        'status' => 'ACTIVE',
        'permanent_secret_hash' => hash('sha256', 'secret'),
    ]);

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('No se puede regenerar el bootstrap token para un agente en estado [ACTIVE]');

    $this->service->regenerateBootstrapToken($agent);
});

test('consumeBootstrapTokenAndActivate activates agent, marks token consumed, and issues permanent secret', function () {
    $created = $this->service->createAgent('Worker-Activation-Test', ttlHours: 24);
    $plainToken = $created['bootstrap_token'];
    $agentId = $created['agent_id'];

    $result = $this->service->consumeBootstrapTokenAndActivate(
        plainToken: $plainToken,
        version: '2.1.0',
        capabilities: ['telemetry:push', 'exec:isolated'],
        metadata: ['datacenter' => 'dc-europe-1']
    );

    expect($result['agent_id'])->toBe($agentId)
        ->and($result['status'])->toBe('ACTIVE')
        ->and($result['permanent_secret'])->toStartWith('sec_')
        ->and(strlen($result['permanent_secret']))->toBeGreaterThan(40);

    // Verify Agent in DB is ACTIVE with permanent secret hash
    $agent = Agent::find($agentId);
    expect($agent->status)->toBe('ACTIVE')
        ->and($agent->isActive())->toBeTrue()
        ->and($agent->version)->toBe('2.1.0')
        ->and($agent->capabilities_json)->toBe(['telemetry:push', 'exec:isolated'])
        ->and($agent->metadata_json)->toBe(['datacenter' => 'dc-europe-1'])
        ->and($agent->last_heartbeat_at)->not->toBeNull();

    $expectedHash = hash('sha256', $result['permanent_secret']);
    expect($agent->getRawOriginal('permanent_secret_hash'))->toBe($expectedHash)
        ->and($agent->getDecryptedSecret())->toBe($result['permanent_secret']);

    // Verify token is now consumed
    $tokenRecord = BootstrapToken::where('agent_id', $agentId)->first();
    expect($tokenRecord->consumed)->toBeTrue()
        ->and($tokenRecord->consumed_at)->not->toBeNull()
        ->and($tokenRecord->isConsumed())->toBeTrue()
        ->and($tokenRecord->isValid())->toBeFalse();

    // Verify audit event
    $audit = AuditEvent::where('agent_id', $agentId)
        ->where('event_type', 'AGENT_ACTIVATED')
        ->first();
    expect($audit)->not->toBeNull();
});

test('consumeBootstrapTokenAndActivate rejects invalid or not found token', function () {
    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('Bootstrap token no válido o inexistente.');

    $this->service->consumeBootstrapTokenAndActivate('invalid-token-12345');
});

test('consumeBootstrapTokenAndActivate rejects already consumed token', function () {
    $created = $this->service->createAgent('Worker-Double-Consume');
    $this->service->consumeBootstrapTokenAndActivate($created['bootstrap_token']);

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('Bootstrap token ya consumido.');

    $this->service->consumeBootstrapTokenAndActivate($created['bootstrap_token']);
});

test('consumeBootstrapTokenAndActivate rejects expired token', function () {
    $created = $this->service->createAgent('Worker-Expired-Consume', ttlHours: -2);

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('Bootstrap token expirado (>24h).');

    $this->service->consumeBootstrapTokenAndActivate($created['bootstrap_token']);
});

test('revokeBootstrapTokens invalidates active tokens and logs audit event', function () {
    $created = $this->service->createAgent('Worker-To-Revoke', ttlHours: 24, adminUser: $this->admin);
    $agentId = $created['agent_id'];
    $token = $created['bootstrap_token'];

    // Verify token initially valid
    $checkBefore = $this->service->validateBootstrapToken($token);
    expect($checkBefore['valid'])->toBeTrue();

    // Revoke tokens
    $revokedCount = $this->service->revokeBootstrapTokens($agentId, adminUser: $this->admin);
    expect($revokedCount)->toBe(1);

    // Verify token is now expired/invalid
    $checkAfter = $this->service->validateBootstrapToken($token);
    expect($checkAfter['valid'])->toBeFalse()
        ->and($checkAfter['reason'])->toBe('TOKEN_EXPIRED');

    // Attempting activation fails with 401
    try {
        $this->service->consumeBootstrapTokenAndActivate($token);
        test()->fail('Expected RuntimeException was not thrown');
    } catch (RuntimeException $e) {
        expect($e->getCode())->toBe(401)
            ->and($e->getMessage())->toBe('Bootstrap token expirado (>24h).');
    }

    // Verify audit event
    $audit = AuditEvent::where('agent_id', $agentId)
        ->where('event_type', 'AGENT_BOOTSTRAP_TOKENS_REVOKED')
        ->first();
    expect($audit)->not->toBeNull()
        ->and($audit->payload_json['revoked_count'])->toBe(1);
});



