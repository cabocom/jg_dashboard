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

test('agents:check-health identifies inactive agents and marks them OFFLINE via Artisan', function () {
    $deadAgent = Agent::create([
        'name' => 'failing-agent-01',
        'status' => 'ACTIVE',
        'permanent_secret_hash' => hash('sha256', 'secret'),
        'last_heartbeat_at' => now()->subSeconds(120),
    ]);

    $this->artisan('agents:check-health', ['--timeout' => 90])
        ->assertExitCode(0)
        ->expectsOutputToContain('Se identificaron 1 agente(s) inactivo(s). Marcados como OFFLINE:')
        ->expectsOutputToContain('failing-agent-01');

    expect($deadAgent->fresh()->status)->toBe('OFFLINE')
        ->and($deadAgent->fresh()->isOffline())->toBeTrue();
});

test('agents:check-health outputs success message when all active agents are healthy', function () {
    Agent::create([
        'name' => 'active-healthy-agent',
        'status' => 'ACTIVE',
        'permanent_secret_hash' => hash('sha256', 'secret'),
        'last_heartbeat_at' => now()->subSeconds(15),
    ]);

    $this->artisan('agents:check-health')
        ->assertExitCode(0)
        ->expectsOutputToContain('No se detectaron agentes caídos. Todos los agentes activos están reportando salud.');
});
