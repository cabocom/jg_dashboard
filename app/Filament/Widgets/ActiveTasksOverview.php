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

use App\Models\AgentTask;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ActiveTasksOverview extends BaseWidget
{
    protected static ?int $sort = 1;
    protected static ?string $pollingInterval = '4s';

    protected function getStats(): array
    {
        $queued = AgentTask::where('status', 'QUEUED')->count();
        $processing = AgentTask::where('status', 'PROCESSING')->count();
        $waitingApproval = AgentTask::whereIn('status', ['WAITING_APPROVAL', 'PAUSED'])->count();
        $failed = AgentTask::where('status', 'FAILED')->count();
        $completed = AgentTask::where('status', 'COMPLETED')->count();

        return [
            Stat::make('En Cola / Esperando', $queued)
                ->description('Tareas pendientes de ejecución')
                ->descriptionIcon('heroicon-m-clock')
                ->color($queued > 0 ? 'warning' : 'gray'),

            Stat::make('En Ejecución Activa', $processing)
                ->description('Procesando grafos cognitivos')
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color($processing > 0 ? 'info' : 'gray'),

            Stat::make('Pendientes HITL', $waitingApproval)
                ->description('Requieren intervención humana')
                ->descriptionIcon('heroicon-m-hand-raised')
                ->color($waitingApproval > 0 ? 'danger' : 'success'),

            Stat::make('Completadas / Fallidas', "{$completed} / {$failed}")
                ->description("Total fallos: {$failed}")
                ->descriptionIcon($failed > 0 ? 'heroicon-m-x-circle' : 'heroicon-m-check-circle')
                ->color($failed > 0 ? 'danger' : 'success'),
        ];
    }
}
