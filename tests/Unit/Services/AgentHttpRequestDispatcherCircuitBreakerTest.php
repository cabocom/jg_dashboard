<?php

/**
 * Part of the javiergalvez-ia ecosystem.
 *
 * @author    Javier Gálvez
 * @license   GNU AGPLv3 (or Commercial Enterprise License)
 * @link      https://javiergalvez.com
 * @link      https://gitlab.com/javiergalvez-ia/jg_dashboard
 */

use App\Models\CircuitBreakerState;
use App\Services\Agents\AgentHttpRequestDispatcher;
use App\Services\Resilience\CircuitBreakerService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    CircuitBreakerState::truncate();
});

test('AgentHttpRequestDispatcher aborts with 503 when CircuitBreaker is OPEN', function () {
    $cb = new CircuitBreakerService(failureThreshold: 2, cooldownSeconds: 60);
    $cb->trip('test-agent-123', 'Servicio caído por sobrecarga');

    $dispatcher = new AgentHttpRequestDispatcher(circuitBreaker: $cb);

    $res = $dispatcher->dispatch(
        targetUrl: 'http://test-agent:8000',
        method: 'POST',
        endpoint: '/executions/trigger',
        data: ['prompt' => 'ping'],
        customHeaders: [],
        signatureHeaders: [],
        timeout: 5,
        retryTimes: 0,
        retrySleepMs: 0,
        verifySsl: false,
        agentId: 'test-agent-123'
    );

    expect($res['success'])->toBeFalse()
        ->and($res['status_code'])->toBe(503)
        ->and($res['error'])->toContain('Circuit Breaker')
        ->and($res['error'])->toContain('ABIERTO');
});

test('AgentHttpRequestDispatcher dispatches and records success when CircuitBreaker is CLOSED', function () {
    Http::fake([
        'http://test-agent:8000/executions/trigger' => Http::response(['status' => 'ok'], 200),
    ]);

    $cb = new CircuitBreakerService(failureThreshold: 2, cooldownSeconds: 60);
    $dispatcher = new AgentHttpRequestDispatcher(circuitBreaker: $cb);

    $res = $dispatcher->dispatch(
        targetUrl: 'http://test-agent:8000',
        method: 'POST',
        endpoint: '/executions/trigger',
        data: ['prompt' => 'ping'],
        customHeaders: [],
        signatureHeaders: [],
        timeout: 5,
        retryTimes: 0,
        retrySleepMs: 0,
        verifySsl: false,
        agentId: 'test-agent-healthy'
    );

    expect($res['success'])->toBeTrue()
        ->and($res['status_code'])->toBe(200)
        ->and($cb->getState('test-agent-healthy'))->toBe(CircuitBreakerService::STATE_CLOSED);
});
