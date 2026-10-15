<?php

/**
 * Part of the javiergalvez-ia ecosystem.
 *
 * @author    Javier Gálvez <contacto@javiergalvez.com>
 * @license   GNU AGPLv3 (or Commercial Enterprise License)
 * @link      https://javiergalvez.com
 * @link      https://gitlab.com/javiergalvez-ia/jg_dashboard
 */

use App\Adapters\Normalizers\InputNormalizer;
use Illuminate\Http\Request;

beforeEach(function () {
    $this->normalizer = new InputNormalizer();
});

test('normalizes generic payload with explicit prompt into valid AgentInput', function () {
    $payload = [
        'prompt' => '¿Cuál es el estado del clúster?',
        'thread_id' => 'custom-thread-123',
        'parameters' => ['engine' => 'fast'],
    ];

    $result = $this->normalizer->normalize($payload, ['ip' => '127.0.0.1']);

    expect($result)->toBeArray()
        ->and($result)->toHaveKeys(['execution_id', 'thread_id', 'correlation_id', 'prompt', 'user_prompt', 'parameters', 'user_context', 'metadata'])
        ->and($result['prompt'])->toBe('¿Cuál es el estado del clúster?')
        ->and($result['thread_id'])->toBe('custom-thread-123')
        ->and(Str::isUuid($result['execution_id']))->toBeTrue()
        ->and($result['parameters']->engine)->toBe('fast')
        ->and($result['user_context']['role'])->toBe('external_client');
});

test('synthesizes prompt from GitLab CI/CD push webhook payload', function () {
    $gitlabPayload = [
        'object_kind' => 'push',
        'ref' => 'refs/heads/main',
        'user_name' => 'DevOps Engineer',
        'project' => ['name' => 'infra-kubernetes'],
        'commits' => [
            ['message' => 'feat: agregar despliegue de ingress controller'],
        ],
    ];

    $result = $this->normalizer->normalize($gitlabPayload);

    expect($result['prompt'])->toContain('GitLab CI/CD: Push')
        ->and($result['prompt'])->toContain('infra-kubernetes')
        ->and($result['prompt'])->toContain('refs/heads/main')
        ->and($result['prompt'])->toContain('feat: agregar despliegue')
        ->and($result['metadata']->source)->toBe('gitlab');
});

test('synthesizes prompt from GitHub webhook PR payload', function () {
    $githubPayload = [
        'action' => 'opened',
        'repository' => ['full_name' => 'org/microservice-api'],
        'sender' => ['login' => 'developer_octocat'],
        'pull_request' => ['title' => 'Fix security vulnerability in auth module'],
    ];

    $result = $this->normalizer->normalize($githubPayload, ['source' => 'github']);

    expect($result['prompt'])->toContain('GitHub CI/CD: PR')
        ->and($result['prompt'])->toContain('org/microservice-api')
        ->and($result['prompt'])->toContain('Fix security vulnerability')
        ->and($result['metadata']->source)->toBe('github');
});

test('extracts text and thread_id from chatbot / conversational payload', function () {
    $chatPayload = [
        'chat_id' => 'telegram-chat-998877',
        'message' => [
            'text' => 'Hola asistente, muéstrame los reportes de ayer',
        ],
    ];

    $result = $this->normalizer->normalize($chatPayload);

    expect($result['prompt'])->toBe('Hola asistente, muéstrame los reportes de ayer')
        ->and($result['thread_id'])->toBe('telegram-chat-998877')
        ->and($result['metadata']->source)->toBe('chatbot');
});

test('maps async flag into parameters object', function () {
    $payload = [
        'message' => 'Procesar reporte masivo',
        'async' => true,
    ];

    $result = $this->normalizer->normalize($payload);

    expect($result['parameters']->async)->toBeTrue();
});

test('extracts correlation_id from headers if present', function () {
    $request = Request::create('/api/v1/adapters/webhook', 'POST', [
        'text' => 'Disparar chequeo',
    ]);
    $request->headers->set('X-Correlation-ID', 'corr-id-webhook-fixed');

    $result = $this->normalizer->normalize($request);

    expect($result['correlation_id'])->toBe('corr-id-webhook-fixed')
        ->and($result['metadata']->correlation_id)->toBe('corr-id-webhook-fixed');
});

test('normalizes stimulus arriving via URL query parameters', function () {
    $request = Request::create('/api/v1/adapters/webhook?query=reiniciar+proxy&session_id=sess-9988&is_async=1', 'GET');

    $result = $this->normalizer->normalize($request);

    expect($result['prompt'])->toBe('reiniciar proxy')
        ->and($result['thread_id'])->toBe('sess-9988')
        ->and($result['parameters']->async)->toBeTrue();
});

test('preserves explicit execution_id and pipeline_id as thread_id', function () {
    $payload = [
        'execution_id' => '00000000-1111-2222-3333-444444444444',
        'pipeline_id' => 'gitlab-ci-run-887766',
        'input' => 'Validar dependencias de seguridad',
    ];

    $result = $this->normalizer->normalize($payload);

    expect($result['execution_id'])->toBe('00000000-1111-2222-3333-444444444444')
        ->and($result['thread_id'])->toBe('gitlab-ci-run-887766')
        ->and($result['prompt'])->toBe('Validar dependencias de seguridad');
});

test('synthesizes fallback prompt for arbitrary raw payloads without text fields', function () {
    $payload = [
        'sensor' => 'temperature_zone_1',
        'metrics' => ['celsius' => 78.4, 'fan_rpm' => 4500],
    ];

    $result = $this->normalizer->normalize($payload);

    expect($result['prompt'])->toContain('[External Webhook Stimulus]')
        ->and($result['prompt'])->toContain('temperature_zone_1')
        ->and(Str::isUuid($result['execution_id']))->toBeTrue()
        ->and(Str::isUuid($result['thread_id']))->toBeTrue();
});

