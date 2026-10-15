<?php

/**
 * Part of the javiergalvez-ia ecosystem.
 *
 * @author    Javier Gálvez
 * @license   GNU AGPLv3 (or Commercial Enterprise License)
 * @link      https://javiergalvez.com
 * @link      https://gitlab.com/javiergalvez-ia/jg_dashboard
 */

namespace App\Filament\Widgets;

use App\Models\Agent;
use App\Services\Agents\AgentHttpClient;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AgentHealthWidget extends BaseWidget
{
    protected static ?int $sort = 0;
    protected static ?string $pollingInterval = '10s';

    protected function getStats(): array
    {
        /** @var AgentHttpClient $client */
        $client = app(AgentHttpClient::class);
        $health = $client->checkHealth();

        $isOnline = $health['success'] ?? false;
        $statusText = $isOnline ? 'Online / Healthy' : 'Inaccesible';
        $color = $isOnline ? 'success' : 'danger';
        $icon = $isOnline ? 'heroicon-m-signal' : 'heroicon-m-signal-slash';

        $agents = Agent::all();
        $totalAgents = $agents->count();
        $activeAgents = $agents->filter(fn (Agent $a) => $a->status === 'ACTIVE' && $a->isHealthy(90))->count();

        return [
            Stat::make('Motor jg_agente (Execution Plane)', $statusText)
                ->description($isOnline ? 'API & LangGraph comunicando con normalidad' : ($health['error'] ?? 'Sin respuesta de jg_agente:8000'))
                ->descriptionIcon($icon)
                ->color($color),

            Stat::make('Agentes Registrados', "{$activeAgents} / {$totalAgents} activos")
                ->description($totalAgents > 0 ? "{$activeAgents} agentes operando en red" : 'Aprovisione agentes desde Gobernanza')
                ->descriptionIcon('heroicon-m-server-stack')
                ->color($activeAgents > 0 ? 'success' : 'warning'),
        ];
    }
}
