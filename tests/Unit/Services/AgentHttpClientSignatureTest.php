<?php

/**
 * Part of the javiergalvez-ia ecosystem.
 *
 * @author    Javier Gálvez
 * @license   GNU AGPLv3 (or Commercial Enterprise License)
 * @link      https://javiergalvez.com
 * @link      https://gitlab.com/javiergalvez-ia/jg_dashboard
 */

use App\Services\Agents\AgentHttpClient;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    Config::set('agent.base_url', 'http://test-agent:8000');
    Config::set('agent.internal_secret', 'cross_platform_shared_secret_key_42');
});

test('canonical HMAC signature matches deterministic cross-platform contract', function () {
    $client = new AgentHttpClient(
        baseUrl: 'http://test-agent:8000',
        internalSecret: 'cross_platform_shared_secret_key_42',
        agentId: 'a0000000-0000-0000-0000-000000000001'
    );

    $body = '{"execution_id":"exec-cross-01","prompt":"ping"}';
    $method = 'POST';
    $path = '/executions/trigger';

    $headers = $client->generateSignatureHeaders($body, $method, $path);

    // Assert headers structure
    expect($headers)->toHaveKeys(['X-Signature', 'X-Timestamp', 'X-Agent-ID']);
    expect($headers['X-Agent-ID'])->toBe('a0000000-0000-0000-0000-000000000001');

    // Manually reconstruct canonical string
    $timestamp = $headers['X-Timestamp'];
    $bodyHash = hash('sha256', $body);
    $canonicalString = "POST\n/executions/trigger\n{$timestamp}\n{$bodyHash}";

    $expectedSignature = hash_hmac('sha256', $canonicalString, 'cross_platform_shared_secret_key_42');

    expect($headers['X-Signature'])->toBe($expectedSignature);

    // Assert that mutating 1 byte of the body produces a completely different signature
    $tamperedBody = '{"execution_id":"exec-cross-01","prompt":"pingx"}';
    $tamperedBodyHash = hash('sha256', $tamperedBody);
    $tamperedCanonical = "POST\n/executions/trigger\n{$timestamp}\n{$tamperedBodyHash}";
    $tamperedSignature = hash_hmac('sha256', $tamperedCanonical, 'cross_platform_shared_secret_key_42');

    expect($headers['X-Signature'])->not->toBe($tamperedSignature);
});

test('canonical HMAC signature for empty body (GET requests) uses standard empty sha256', function () {
    $client = new AgentHttpClient(
        baseUrl: 'http://test-agent:8000',
        internalSecret: 'cross_platform_shared_secret_key_42',
        agentId: 'a0000000-0000-0000-0000-000000000001'
    );

    $headers = $client->generateSignatureHeaders('', 'GET', '/executions/thread-123');
    $timestamp = $headers['X-Timestamp'];

    $emptySha256 = 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855';
    $canonicalString = "GET\n/executions/thread-123\n{$timestamp}\n{$emptySha256}";
    $expectedSignature = hash_hmac('sha256', $canonicalString, 'cross_platform_shared_secret_key_42');

    expect($headers['X-Signature'])->toBe($expectedSignature);
});
