<?php

namespace App\Filament\Widgets;

use App\Models\Agent;
use App\Services\DevOps60kApiClient;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AgentHealthWidget extends BaseWidget
{
    protected static ?int $sort = 0;
    protected static ?string $pollingInterval = '10s';

    protected function getStats(): array
    {
        /** @var DevOps60kApiClient $client */
        $client = app(DevOps60kApiClient::class);
        $health = $client->checkHealth();

        $isOnline = $health['online'] ?? false;
        $statusText = $isOnline ? 'Online / Healthy' : 'Inaccesible';
        $color = $isOnline ? 'success' : 'danger';
        $icon = $isOnline ? 'heroicon-m-signal' : 'heroicon-m-signal-slash';

        $totalAgents = Agent::count();
        $activeAgents = Agent::where('status', 'ACTIVE')->count();

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
