<?php

use App\Services\Agents\AgentHttpClient;
use Illuminate\Support\Facades\Config;

test('config agent.internal_secret and devops_secret do not contain hardcoded insecure fallback', function () {
    // Force config to re-evaluate without environment secret
    putenv('JG_CONVERSATIONAL_SECRET');
    putenv('JG_AGENTE_SECRET');
    putenv('AGENT_INTERNAL_SECRET');
    putenv('JG_DEVOPS_SECRET');
    putenv('JG_DEVOPS_AGENT_SECRET');
    putenv('DEVOPS60K_SECRET');
    putenv('DEVOPS60K_API_KEY');
    unset(
        $_ENV['JG_CONVERSATIONAL_SECRET'],
        $_ENV['JG_AGENTE_SECRET'],
        $_ENV['AGENT_INTERNAL_SECRET'],
        $_ENV['JG_DEVOPS_SECRET'],
        $_ENV['JG_DEVOPS_AGENT_SECRET'],
        $_ENV['DEVOPS60K_SECRET'],
        $_ENV['DEVOPS60K_API_KEY'],
        $_SERVER['JG_CONVERSATIONAL_SECRET'],
        $_SERVER['JG_AGENTE_SECRET'],
        $_SERVER['AGENT_INTERNAL_SECRET'],
        $_SERVER['JG_DEVOPS_SECRET'],
        $_SERVER['JG_DEVOPS_AGENT_SECRET'],
        $_SERVER['DEVOPS60K_SECRET'],
        $_SERVER['DEVOPS60K_API_KEY']
    );

    // Load fresh config value directly from config file definition
    $config = require config_path('agent.php');
    expect($config['internal_secret'])->toBeNull();
    expect($config['devops_secret'])->toBeNull();
});

test('AgentHttpClient throws RuntimeException when internal secret is missing or empty', function () {
    Config::set('agent.internal_secret', null);
    Config::set('devops60k.api_key', null);

    $client = new AgentHttpClient(
        baseUrl: 'http://localhost:8000',
        internalSecret: null,
        agentId: 'a0000000-0000-0000-0000-000000000001'
    );

    expect(fn () => $client->generateSignatureHeaders('{"test":true}'))
        ->toThrow(\RuntimeException::class, 'Seguridad (CWE-798): El secreto interno para firma HMAC no está configurado (JG_AGENTE_SECRET).');
});

test('AgentHttpClient signs correctly when explicit or configured secret is provided', function () {
    $client = new AgentHttpClient(
        baseUrl: 'http://localhost:8000',
        internalSecret: 'secure_custom_test_secret_key',
        agentId: 'a0000000-0000-0000-0000-000000000001'
    );

    $headers = $client->generateSignatureHeaders('{"hello":"world"}');

    expect($headers)->toHaveKeys(['X-Signature', 'X-Timestamp', 'X-Agent-ID']);
    expect($headers['X-Signature'])->not->toBeEmpty();
});
