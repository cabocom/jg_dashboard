<?php

use App\Models\CircuitBreakerState;
use App\Services\Resilience\CircuitBreakerOpenException;
use App\Services\Resilience\CircuitBreakerService;
use Illuminate\Support\Carbon;

beforeEach(function () {
    CircuitBreakerState::truncate();
    $this->service = new CircuitBreakerService(failureThreshold: 3, cooldownSeconds: 30);
});

test('circuit starts in CLOSED state and is available', function () {
    expect($this->service->getState('external-api'))->toBe(CircuitBreakerService::STATE_CLOSED)
        ->and($this->service->isAvailable('external-api'))->toBeTrue();
});

test('circuit trips from CLOSED to OPEN when failures reach threshold', function () {
    $target = 'payment-gateway';

    // 1er fallo: sigue CLOSED
    $this->service->recordFailure($target, 'Timeout 1');
    expect($this->service->getState($target))->toBe(CircuitBreakerService::STATE_CLOSED)
        ->and($this->service->isAvailable($target))->toBeTrue();

    // 2do fallo: sigue CLOSED
    $this->service->recordFailure($target, 'Timeout 2');
    expect($this->service->getState($target))->toBe(CircuitBreakerService::STATE_CLOSED)
        ->and($this->service->isAvailable($target))->toBeTrue();

    // 3er fallo (alcanza umbral = 3): transiciona a OPEN
    $this->service->recordFailure($target, 'HTTP 503');
    expect($this->service->getState($target))->toBe(CircuitBreakerService::STATE_OPEN)
        ->and($this->service->isAvailable($target))->toBeFalse();

    $record = CircuitBreakerState::where('target', $target)->first();
    expect($record->is_tripped)->toBeTrue()
        ->and($record->tripped_reason)->toBe('HTTP 503');
});

test('OPEN circuit rejects execute calls or routes through fallback', function () {
    $target = 'database-replica';
    $this->service->trip($target, 'Cluster down');

    // Sin fallback: lanza CircuitBreakerOpenException
    expect(fn () => $this->service->execute($target, fn () => 'should not run'))
        ->toThrow(CircuitBreakerOpenException::class);

    // Con fallback: ejecuta fallback limpiamente sin ejecutar la acción principal
    $executedMain = false;
    $result = $this->service->execute(
        $target,
        function () use (&$executedMain) {
            $executedMain = true;

            return 'main';
        },
        fallback: fn (Throwable $e) => 'fallback-result'
    );

    expect($executedMain)->toBeFalse()
        ->and($result)->toBe('fallback-result');
});

test('circuit transitions from OPEN to HALF_OPEN after cooldown period expires', function () {
    $target = 'llm-upstream';
    $now = Carbon::parse('2026-10-11 12:00:00');
    Carbon::setTestNow($now);

    $this->service->trip($target, 'Rate limited');
    expect($this->service->getState($target))->toBe(CircuitBreakerService::STATE_OPEN);

    // 15 segundos después (cooldown es 30s): sigue OPEN
    Carbon::setTestNow($now->copy()->addSeconds(15));
    expect($this->service->getState($target))->toBe(CircuitBreakerService::STATE_OPEN)
        ->and($this->service->isAvailable($target))->toBeFalse();

    // 31 segundos después: pasa a HALF_OPEN y permite sondeo
    Carbon::setTestNow($now->copy()->addSeconds(31));
    expect($this->service->getState($target))->toBe(CircuitBreakerService::STATE_HALF_OPEN)
        ->and($this->service->isAvailable($target))->toBeTrue();

    Carbon::setTestNow(); // reset
});

test('HALF_OPEN probe success closes and heals circuit (HALF_OPEN -> CLOSED)', function () {
    $target = 'search-service';
    $now = Carbon::parse('2026-10-11 12:00:00');
    Carbon::setTestNow($now);

    $this->service->trip($target, 'Down');
    // Avanzar a HALF_OPEN
    Carbon::setTestNow($now->copy()->addSeconds(35));
    expect($this->service->getState($target))->toBe(CircuitBreakerService::STATE_HALF_OPEN);

    // Registrar éxito de sonda
    $this->service->recordSuccess($target);

    expect($this->service->getState($target))->toBe(CircuitBreakerService::STATE_CLOSED)
        ->and($this->service->isAvailable($target))->toBeTrue();

    $record = CircuitBreakerState::where('target', $target)->first();
    expect($record->is_tripped)->toBeFalse()
        ->and($record->tripped_reason)->toBeNull();

    Carbon::setTestNow();
});

test('HALF_OPEN probe failure trips back immediately to OPEN with fresh cooldown', function () {
    $target = 'cache-cluster';
    $now = Carbon::parse('2026-10-11 12:00:00');
    Carbon::setTestNow($now);

    $this->service->trip($target, 'Down');
    // Avanzar a HALF_OPEN
    Carbon::setTestNow($now->copy()->addSeconds(35));
    expect($this->service->getState($target))->toBe(CircuitBreakerService::STATE_HALF_OPEN);

    // La sonda falla
    $this->service->recordFailure($target, 'Connection refused on probe');

    expect($this->service->getState($target))->toBe(CircuitBreakerService::STATE_OPEN)
        ->and($this->service->isAvailable($target))->toBeFalse();

    $record = CircuitBreakerState::where('target', $target)->first();
    expect($record->is_tripped)->toBeTrue()
        ->and($record->tripped_reason)->toContain('Sonda fallida en estado HALF_OPEN');

    Carbon::setTestNow();
});
