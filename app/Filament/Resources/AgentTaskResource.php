<?php

/**
 * Part of the javiergalvez-ia ecosystem.
 *
 * @author    Javier Gálvez
 * @license   GNU AGPLv3 (or Commercial Enterprise License)
 * @link      https://javiergalvez.com
 * @link      https://gitlab.com/javiergalvez-ia/jg_dashboard
 */

namespace App\Filament\Resources;

use App\Filament\Resources\AgentTaskResource\Infolists\AgentTaskInfolist;
use App\Filament\Resources\AgentTaskResource\Pages;
use App\Filament\Resources\AgentTaskResource\Tables\AgentTaskTable;
use App\Models\AgentTask;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables\Table;

class AgentTaskResource extends Resource
{
    protected static ?string $model = AgentTask::class;

    protected static ?string $navigationIcon = 'heroicon-o-cpu-chip';

    protected static ?string $navigationGroup = 'Gobernanza de Agentes';

    protected static ?string $modelLabel = 'Ejecución';

    protected static ?string $pluralModelLabel = 'Historial de Ejecuciones';

    protected static ?int $navigationSort = 2;

    public static function getNavigationUrl(): string
    {
        return url('/console?tab=executions');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::whereIn('status', ['QUEUED', 'PROCESSING', 'WAITING_APPROVAL'])->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        return AgentTaskTable::configure($table);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return AgentTaskInfolist::configure($infolist);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAgentTasks::route('/'),
            'view' => Pages\ViewAgentTask::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function canViewAny(): bool
    {
        return in_array(auth()->user()?->role, ['admin', 'operator']);
    }
}
