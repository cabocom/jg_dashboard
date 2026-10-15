<?php

/**
 * Part of the javiergalvez-ia ecosystem.
 *
 * @author    Javier Gálvez <contacto@javiergalvez.com>
 * @license   GNU AGPLv3 (or Commercial Enterprise License)
 * @link      https://javiergalvez.com
 * @link      https://gitlab.com/javiergalvez-ia/jg_dashboard
 */

use App\Adapters\Formatters\ClientOutputFormatter;

beforeEach(function () {
    $this->formatter = new ClientOutputFormatter();
});

test('formats generic response correctly', function () {
    $agentResponse = [
        'success' => true,
        'status_code' => 200,
        'data' => [
            'execution_id' => 'exec-123',
            'thread_id' => 'thread-456',
            'status' => 'COMPLETED',
            'output' => 'Todos los contenedores están operativos.',
        ],
    ];

    $formatted = $this->formatter->format($agentResponse, 'generic');

    expect($formatted)->toBeArray()
        ->and($formatted['success'])->toBeTrue()
        ->and($formatted['status'])->toBe('COMPLETED')
        ->and($formatted['execution_id'])->toBe('exec-123')
        ->and($formatted['output'])->toBe('Todos los contenedores están operativos.');
});

test('formats chatbot response with text and conversation details', function () {
    $agentResponse = [
        'success' => true,
        'status_code' => 200,
        'data' => [
            'execution_id' => 'exec-789',
            'thread_id' => 'chat-channel-1',
            'status' => 'COMPLETED',
            'output' => 'El clima en Madrid es 18 grados.',
        ],
    ];

    $formatted = $this->formatter->format($agentResponse, 'chatbot');

    expect($formatted)->toBeArray()
        ->and($formatted['response'])->toBe('El clima en Madrid es 18 grados.')
        ->and($formatted['text'])->toBe('El clima en Madrid es 18 grados.')
        ->and($formatted['thread_id'])->toBe('chat-channel-1')
        ->and($formatted['status'])->toBe('completed')
        ->and($formatted['conversation']['chat_id'])->toBe('chat-channel-1');
});

test('formats CI/CD response with job status and details', function () {
    $agentResponse = [
        'success' => true,
        'status_code' => 200,
        'data' => [
            'execution_id' => 'exec-ci-1',
            'thread_id' => 'pipe-42',
            'status' => 'COMPLETED',
            'output' => 'Pipeline analizado sin anomalías detectadas.',
            'cognitive_steps' => [['step' => 1], ['step' => 2]],
        ],
    ];

    $formatted = $this->formatter->format($agentResponse, 'ci_cd');

    expect($formatted)->toBeArray()
        ->and($formatted['status'])->toBe('success')
        ->and($formatted['job_status'])->toBe('COMPLETED')
        ->and($formatted['pipeline_status'])->toBe('PASSED')
        ->and($formatted['summary'])->toBe('Pipeline analizado sin anomalías detectadas.')
        ->and($formatted['details']['cognitive_steps_count'])->toBe(2);
});
