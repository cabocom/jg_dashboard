<?php

namespace App\Filament\Widgets;

use App\Models\CircuitBreakerState;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CircuitBreakersStatus extends BaseWidget
{
    protected static ?int $sort = 2;
    protected static ?string $pollingInterval = '6s';

    protected function getStats(): array
    {
        $total = CircuitBreakerState::count();
        $tripped = CircuitBreakerState::where('is_tripped', true)->count();
        $healthy = $total - $tripped;

        return [
            Stat::make('Circuit Breakers Operativos', "{$healthy} / {$total}")
                ->description($tripped === 0 ? 'Todos los servicios protegidos' : "¡{$tripped} circuito(s) disparado(s)!")
                ->descriptionIcon($tripped === 0 ? 'heroicon-m-shield-check' : 'heroicon-m-shield-exclamation')
                ->color($tripped === 0 ? 'success' : 'danger'),
        ];
    }
}
