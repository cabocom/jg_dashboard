<?php

/**
 * Part of the javiergalvez-ia ecosystem.
 *
 * @author    Javier Gálvez
 * @license   GNU AGPLv3 (or Commercial Enterprise License)
 * @link      https://javiergalvez.com
 * @link      https://gitlab.com/javiergalvez-ia/jg_dashboard
 */

namespace App\Services\Resilience;

use App\Models\CircuitBreakerState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CircuitBreakerService
{
    public const STATE_CLOSED = 'CLOSED';

    public const STATE_OPEN = 'OPEN';

    public const STATE_HALF_OPEN = 'HALF_OPEN';

    protected int $failureThreshold;

    protected int $cooldownSeconds;

    public function __construct(int $failureThreshold = 5, int $cooldownSeconds = 30)
    {
        $this->failureThreshold = $failureThreshold;
        $this->cooldownSeconds = $cooldownSeconds;
    }

    public function getFailureThreshold(): int
    {
        return $this->failureThreshold;
    }

    public function getCooldownSeconds(): int
    {
        return $this->cooldownSeconds;
    }

    /**
     * Resuelve el estado actual de la máquina de estados para el target indicado.
     * Transiciones deterministas:
     * - CLOSED: tráfico normal, is_tripped = false.
     * - OPEN: circuito cortado, is_tripped = true y tiempo transcurrido < cooldown.
     * - HALF_OPEN: período de enfriamiento expirado (now >= updated_at + cooldown), permite tráfico de prueba/sonda.
     */
    public function getState(string $target): string
    {
        /** @var CircuitBreakerState|null $record */
        $record = CircuitBreakerState::where('target', $target)->first();

        if (! $record || ! $record->is_tripped) {
            return self::STATE_CLOSED;
        }

        $lastTrippedAt = $record->updated_at;
        if (! $lastTrippedAt) {
            return self::STATE_OPEN;
        }

        $now = now();
        if ($now->greaterThanOrEqualTo($lastTrippedAt->copy()->addSeconds($this->cooldownSeconds))) {
            return self::STATE_HALF_OPEN;
        }

        return self::STATE_OPEN;
    }

    /**
     * Determina si el circuito permite la ejecución de una llamada (CLOSED o prueba HALF_OPEN).
     */
    public function isAvailable(string $target): bool
    {
        $state = $this->getState($target);

        return $state === self::STATE_CLOSED || $state === self::STATE_HALF_OPEN;
    }

    /**
     * Registra un éxito operacional para el target.
     * Si estaba en HALF_OPEN, cierra y resetea el circuito (transición HALF_OPEN -> CLOSED).
     * Si estaba en CLOSED, resetea el acumulador de fallos consecutivos.
     */
    public function recordSuccess(string $target): void
    {
        DB::transaction(function () use ($target) {
            /** @var CircuitBreakerState|null $record */
            $record = CircuitBreakerState::where('target', $target)->lockForUpdate()->first();

            if (! $record) {
                return;
            }

            $currentState = $this->getState($target);

            if ($currentState === self::STATE_HALF_OPEN || $record->is_tripped) {
                Log::info("[CircuitBreaker] Sonda exitosa en target '{$target}'. Transicionando de {$currentState} a CLOSED.");
                $record->update([
                    'is_tripped' => false,
                    'tripped_reason' => null,
                    'failure_history_json' => [
                        'consecutive_failures' => 0,
                        'last_success_at' => now()->toIso8601String(),
                        'recent_failures' => [],
                    ],
                    'updated_at' => now(),
                ]);
            } else {
                $history = is_array($record->failure_history_json) ? $record->failure_history_json : [];
                $history['consecutive_failures'] = 0;
                $history['last_success_at'] = now()->toIso8601String();

                $record->update([
                    'failure_history_json' => $history,
                ]);
            }
        });
    }

    /**
     * Registra un fallo operacional.
     * Si estaba en HALF_OPEN: falla la sonda de sondeo y vuelve inmediatamente a OPEN (reseteando cooldown).
     * Si estaba en CLOSED: incrementa fallos; si alcanza failureThreshold, salta a OPEN.
     */
    public function recordFailure(string $target, string $reason = ''): void
    {
        DB::transaction(function () use ($target, $reason) {
            /** @var CircuitBreakerState $record */
            $record = CircuitBreakerState::where('target', $target)->lockForUpdate()->first();

            if (! $record) {
                $record = new CircuitBreakerState;
                $record->target = $target;
                $record->is_tripped = false;
                $record->failure_history_json = ['consecutive_failures' => 0, 'recent_failures' => []];
            }

            $currentState = $this->getState($target);
            $history = is_array($record->failure_history_json) ? $record->failure_history_json : [];
            $consecutive = (int) ($history['consecutive_failures'] ?? 0) + 1;
            $history['consecutive_failures'] = $consecutive;

            $recent = is_array($history['recent_failures'] ?? null) ? $history['recent_failures'] : [];
            $recent[] = [
                'timestamp' => now()->toIso8601String(),
                'reason' => $reason,
                'state_when_failed' => $currentState,
            ];
            // Mantener solo los últimos 10 fallos
            if (count($recent) > 10) {
                $recent = array_slice($recent, -10);
            }
            $history['recent_failures'] = $recent;

            if ($currentState === self::STATE_HALF_OPEN) {
                // Sonda en HALF_OPEN fallida: vuelta a OPEN de inmediato con nuevo timestamp de enfriamiento
                Log::warning("[CircuitBreaker] Sonda fallida en HALF_OPEN para target '{$target}'. Circuito vuelve a OPEN.");
                $record->is_tripped = true;
                $record->tripped_reason = 'Sonda fallida en estado HALF_OPEN: '.($reason ?: 'Error de conexión');
                $record->failure_history_json = $history;
                $record->updated_at = now();
                $record->save();

                return;
            }

            if ($consecutive >= $this->failureThreshold) {
                Log::error("[CircuitBreaker] Target '{$target}' alcanzó {$consecutive} fallos consecutivos (umbral {$this->failureThreshold}). Circuito DISPARADO (OPEN).");
                $record->is_tripped = true;
                $record->tripped_reason = $reason ?: "Superado umbral de fallos consecutivos ({$this->failureThreshold})";
                $record->failure_history_json = $history;
                $record->updated_at = now();
                $record->save();
            } else {
                $record->failure_history_json = $history;
                $record->updated_at = now();
                $record->save();
            }
        });
    }

    /**
     * Fuerza el disparo manual del circuito a OPEN.
     */
    public function trip(string $target, string $reason): void
    {
        CircuitBreakerState::updateOrCreate(
            ['target' => $target],
            [
                'is_tripped' => true,
                'tripped_reason' => $reason,
                'updated_at' => now(),
            ]
        );
    }

    /**
     * Fuerza el reseteo manual del circuito a CLOSED.
     */
    public function reset(string $target): void
    {
        CircuitBreakerState::updateOrCreate(
            ['target' => $target],
            [
                'is_tripped' => false,
                'tripped_reason' => null,
                'failure_history_json' => ['consecutive_failures' => 0, 'recent_failures' => []],
                'updated_at' => now(),
            ]
        );
    }

    /**
     * Ejecuta una llamada protegida por el circuit breaker.
     * Si el circuito está OPEN, ejecuta el fallback o lanza CircuitBreakerOpenException.
     * Si la llamada tiene éxito, registra éxito; si falla, registra fallo y propaga la excepción o invoca el fallback.
     */
    public function execute(string $target, callable $action, ?callable $fallback = null): mixed
    {
        if (! $this->isAvailable($target)) {
            if ($fallback !== null) {
                return $fallback(new CircuitBreakerOpenException($target));
            }
            throw new CircuitBreakerOpenException($target);
        }

        try {
            $result = $action();
            $this->recordSuccess($target);

            return $result;
        } catch (\Throwable $e) {
            $this->recordFailure($target, $e->getMessage());
            if ($fallback !== null) {
                return $fallback($e);
            }
            throw $e;
        }
    }
}
