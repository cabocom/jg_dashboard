<?php

/**
 * Part of the javiergalvez-ia ecosystem.
 *
 * @author    Javier Gálvez <contacto@javiergalvez.com>
 * @license   GNU AGPLv3 (or Commercial Enterprise License)
 * @link      https://javiergalvez.com
 * @link      https://gitlab.com/javiergalvez-ia/jg_dashboard
 */

namespace App\Console\Commands;

use App\Services\Agents\AgentHealthMonitorService;
use Illuminate\Console\Command;

class CheckAgentHealthCommand extends Command
{
    protected $signature = 'agents:check-health {--timeout=90 : Umbral de inactividad en segundos sin heartbeat}';
    protected $description = 'Identifica agentes activos sin heartbeat en los últimos N segundos y los marca como OFFLINE';

    public function handle(AgentHealthMonitorService $healthMonitor): int
    {
        $timeout = (int) $this->option('timeout');
        if ($timeout <= 0) {
            $timeout = 90;
        }

        $this->info("🔍 Comprobando agentes activos sin heartbeat durante más de {$timeout}s...");

        $offlineAgents = $healthMonitor->checkAndMarkOfflineAgents($timeout);
        $count = $offlineAgents->count();

        if ($count === 0) {
            $this->info("✅ No se detectaron agentes caídos. Todos los agentes activos están reportando salud.");
            return Command::SUCCESS;
        }

        $this->warn("⚠️  Se identificaron {$count} agente(s) inactivo(s). Marcados como OFFLINE:");
        foreach ($offlineAgents as $agent) {
            $this->line("   - [{$agent->agent_id}] {$agent->name} (Último heartbeat: {$agent->last_heartbeat_at})");
        }

        return Command::SUCCESS;
    }
}
