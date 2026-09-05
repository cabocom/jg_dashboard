<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NotifiedIncidentResource\Pages;
use App\Models\NotifiedIncident;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;

class NotifiedIncidentResource extends Resource
{
    protected static ?string $model = NotifiedIncident::class;

    protected static ?string $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?string $navigationGroup = 'Observabilidad y Resiliencia';

    protected static ?string $modelLabel = 'Incidente Notificado';

    protected static ?string $pluralModelLabel = 'Historial de Notificaciones';

    protected static ?int $navigationSort = 3;

    public static function table(Table $table): Table
    {
        return $table
            ->poll('10s')
            ->columns([
                Tables\Columns\TextColumn::make('incident_id')
                    ->label('ID de Incidente')
                    ->fontFamily('mono')
                    ->weight(FontWeight::Bold)
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('notified_at')
                    ->label('Fecha de Notificación')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
            ])
            ->defaultSort('notified_at', 'desc')
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNotifiedIncidents::route('/'),
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

