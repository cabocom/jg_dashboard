<?php

namespace App\Filament\Widgets;

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

        return [
            Stat::make('Agente DevOps60K Backend', $statusText)
                ->description($isOnline ? 'API & Worker comunicando con normalidad' : ($health['error'] ?? 'Sin respuesta de http://devops60k_app:8000'))
                ->descriptionIcon($icon)
                ->color($color),
        ];
    }
}
