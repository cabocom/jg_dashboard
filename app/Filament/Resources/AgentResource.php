<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AgentResource\Pages;
use App\Models\Agent;
use App\Models\BootstrapToken;
use App\Services\Agents\AgentRegistrationService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;

class AgentResource extends Resource
{
    protected static ?string $model = Agent::class;

    protected static ?string $navigationIcon = 'heroicon-o-server-stack';

    protected static ?string $navigationGroup = 'Gobernanza de Agentes';

    protected static ?string $modelLabel = 'Agente';

    protected static ?string $pluralModelLabel = 'Agentes';

    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        $active = static::getModel()::where('status', 'ACTIVE')->count();
        $total = static::getModel()::count();
        return $total > 0 ? "{$active}/{$total}" : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Card::make([
                    Forms\Components\TextInput::make('name')
                        ->label('Nombre del Agente')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('version')
                        ->label('Versión del Agente')
                        ->default('0.1.0')
                        ->maxLength(64),

                    Forms\Components\Select::make('status')
                        ->label('Estado Operativo')
                        ->options([
                            'ACTIVE' => 'Activo (ACTIVE)',
                            'PENDING' => 'Pendiente de Aprovisionamiento (PENDING)',
                            'OFFLINE' => 'Desconectado (OFFLINE)',
                        ])
                        ->default('PENDING')
                        ->required(),

                    Forms\Components\KeyValue::make('metadata_json')
                        ->label('Metadatos Arbitrarios')
                        ->keyLabel('Clave')
                        ->valueLabel('Valor'),
                ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->poll('5s')
            ->columns([
                Tables\Columns\TextColumn::make('agent_id')
                    ->label('Agent ID')
                    ->fontFamily('mono')
                    ->copyable()
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->weight(FontWeight::Bold)
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->colors([
                        'success' => 'ACTIVE',
                        'warning' => 'PENDING',
                        'danger' => 'OFFLINE',
                    ]),

                Tables\Columns\TextColumn::make('version')
                    ->label('Versión')
                    ->fontFamily('mono')
                    ->placeholder('N/A')
                    ->sortable(),

                Tables\Columns\TextColumn::make('last_heartbeat_at')
                    ->label('Último Heartbeat')
                    ->since()
                    ->sortable(),

                Tables\Columns\TextColumn::make('capabilities_json')
                    ->label('Capacidades')
                    ->state(function (Agent $record): string {
                        $tools = $record->capabilities_json['tools'] ?? [];
                        $count = is_array($tools) ? count($tools) : 0;
                        return $count > 0 ? "{$count} herramientas" : "Sin reportar";
                    })
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Registrado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Filtrar por Estado')
                    ->options([
                        'ACTIVE' => 'Activo',
                        'PENDING' => 'Pendiente',
                        'OFFLINE' => 'Desconectado',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),

                Action::make('generate_token')
                    ->label('Nuevo Token')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Generar nuevo Bootstrap Token')
                    ->modalDescription('Se creará un nuevo token de aprovisionamiento de un solo uso con 24 horas de validez.')
                    ->action(function (Agent $record) {
                        $tokenData = BootstrapToken::createTokenForAgent($record, 24);
                        $plaintext = $tokenData['plaintext_token'];

                        Notification::make()
                            ->title('Token Generado Exitosamente')
                            ->body("Token de aprovisionamiento: <strong>{$plaintext}</strong> (Copia este token ahora, no volverá a mostrarse)")
                            ->success()
                            ->persistent()
                            ->send();
                    }),

                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Ficha Técnica del Agente')
                    ->columns(3)
                    ->schema([
                        Infolists\Components\TextEntry::make('agent_id')
                            ->label('Identificador UUID')
                            ->fontFamily('mono')
                            ->copyable(),

                        Infolists\Components\TextEntry::make('name')
                            ->label('Nombre'),

                        Infolists\Components\TextEntry::make('version')
                            ->label('Versión')
                            ->fontFamily('mono'),

                        Infolists\Components\TextEntry::make('status')
                            ->label('Estado')
                            ->badge()
                            ->colors([
                                'success' => 'ACTIVE',
                                'warning' => 'PENDING',
                                'danger' => 'OFFLINE',
                            ]),

                        Infolists\Components\TextEntry::make('last_heartbeat_at')
                            ->label('Última Señal Heartbeat')
                            ->dateTime('d/m/Y H:i:s'),

                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Fecha de Creación')
                            ->dateTime('d/m/Y H:i'),
                    ]),

                Infolists\Components\Section::make('Telemetría y Capacidades')
                    ->columns(2)
                    ->schema([
                        Infolists\Components\TextEntry::make('capabilities_json')
                            ->label('Capacidades Declaradas (Tools / Engines)')
                            ->state(function (Agent $record): string {
                                $data = $record->capabilities_json;
                                if (empty($data)) {
                                    return 'Sin capacidades declaradas';
                                }
                                return is_array($data)
                                    ? json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                                    : (string) $data;
                            })
                            ->formatStateUsing(fn (string $state): string => '<pre class="p-3 bg-gray-950 text-emerald-400 rounded-lg text-xs font-mono overflow-x-auto max-h-96"><code>' . e($state) . '</code></pre>')
                            ->html(),

                        Infolists\Components\TextEntry::make('metrics_json')
                            ->label('Métricas de Host / Proceso')
                            ->state(function (Agent $record): string {
                                $data = $record->metrics_json;
                                if (empty($data)) {
                                    return 'Sin métricas recolectadas';
                                }
                                return is_array($data)
                                    ? json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                                    : (string) $data;
                            })
                            ->formatStateUsing(fn (string $state): string => '<pre class="p-3 bg-gray-950 text-sky-400 rounded-lg text-xs font-mono overflow-x-auto max-h-96"><code>' . e($state) . '</code></pre>')
                            ->html(),
                    ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAgents::route('/'),
            'create' => Pages\CreateAgent::route('/create'),
            'view' => Pages\ViewAgent::route('/{record}'),
            'edit' => Pages\EditAgent::route('/{record}/edit'),
        ];
    }
}
