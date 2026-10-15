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

use App\Filament\Resources\CircuitBreakerResource\Pages;
use App\Models\CircuitBreakerState;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;

class CircuitBreakerResource extends Resource
{
    protected static ?string $model = CircuitBreakerState::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-exclamation';

    protected static ?string $navigationGroup = 'Observabilidad y Resiliencia';

    protected static ?string $modelLabel = 'Interruptor de Circuito';

    protected static ?string $pluralModelLabel = 'Circuit Breakers';

    protected static ?int $navigationSort = 2;

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('is_tripped', true)->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->poll('5s')
            ->columns([
                Tables\Columns\TextColumn::make('target')
                    ->label('Objetivo / Servicio')
                    ->fontFamily('mono')
                    ->weight(FontWeight::Bold)
                    ->searchable(),

                Tables\Columns\IconColumn::make('is_tripped')
                    ->label('Estado')
                    ->boolean()
                    ->trueIcon('heroicon-m-exclamation-triangle')
                    ->falseIcon('heroicon-m-check-badge')
                    ->trueColor('danger')
                    ->falseColor('success'),

                Tables\Columns\TextColumn::make('tripped_reason')
                    ->label('Motivo del Disparo')
                    ->wrap()
                    ->placeholder('Operativo / Sin fallos activos')
                    ->limit(50),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Última Actualización')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_tripped')
                    ->label('Filtrar Disparados')
                    ->trueLabel('Disparados (Tripped)')
                    ->falseLabel('Normales (Healthy)'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Estado del Circuit Breaker')
                    ->columns(3)
                    ->schema([
                        Infolists\Components\TextEntry::make('target')
                            ->label('Servicio / Target')
                            ->fontFamily('mono'),
                        Infolists\Components\IconEntry::make('is_tripped')
                            ->label('¿Está Disparado?')
                            ->boolean(),
                        Infolists\Components\TextEntry::make('updated_at')
                            ->label('Fecha')
                            ->dateTime('d/m/Y H:i:s'),
                        Infolists\Components\TextEntry::make('tripped_reason')
                            ->label('Motivo')
                            ->columnSpanFull(),
                    ]),

                Infolists\Components\Section::make('Historial de Fallos')
                    ->schema([
                        Infolists\Components\TextEntry::make('failure_history_json')
                            ->label('')
                            ->formatStateUsing(function ($state): string {
                                if (empty($state)) {
                                    return '// Sin fallos registrados';
                                }
                                return is_array($state)
                                    ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                                    : (string) $state;
                            })
                            ->fontFamily('mono')
                            ->extraAttributes([
                                'class' => 'bg-gray-950 text-rose-400 p-4 rounded-xl font-mono text-xs overflow-x-auto whitespace-pre block border border-gray-800 shadow-inner',
                            ])
                            ->copyable(),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCircuitBreakers::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return in_array(auth()->user()?->role, ['admin', 'operator']);
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

