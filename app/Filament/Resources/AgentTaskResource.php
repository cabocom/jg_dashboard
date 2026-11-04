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

use App\Filament\Resources\AgentTaskResource\Pages;
use App\Models\Agent;
use App\Models\AgentTask;
use App\Services\Agents\AgentHttpClient;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
        return $table
            ->poll('3s') // Auto-refresh reactivo para monitorización en tiempo real
            ->columns([
                Tables\Columns\TextColumn::make('prompt')
                    ->label('Prompt / Instrucción')
                    ->limit(60)
                    ->tooltip(fn (AgentTask $record): ?string => $record->prompt)
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where('payload_json->prompt', 'like', "%{$search}%")
                            ->orWhere('payload_json->user_prompt', 'like', "%{$search}%");
                    })
                    ->wrap(),

                Tables\Columns\TextColumn::make('task_id')
                    ->label('Task ID')
                    ->fontFamily('mono')
                    ->weight(FontWeight::Bold)
                    ->copyable()
                    ->searchable()
                    ->limit(12),

                Tables\Columns\TextColumn::make('thread_id')
                    ->label('Thread ID')
                    ->fontFamily('mono')
                    ->copyable()
                    ->searchable()
                    ->limit(16),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'COMPLETED' => 'success',
                        'PROCESSING' => 'info',
                        'QUEUED' => 'warning',
                        'WAITING_APPROVAL', 'PAUSED' => 'danger',
                        'FAILED' => 'danger',
                        default => 'gray',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'COMPLETED' => 'heroicon-m-check-circle',
                        'PROCESSING' => 'heroicon-m-arrow-path',
                        'QUEUED' => 'heroicon-m-clock',
                        'WAITING_APPROVAL', 'PAUSED' => 'heroicon-m-hand-raised',
                        'FAILED' => 'heroicon-m-x-circle',
                        default => 'heroicon-m-question-mark-circle',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),

                Tables\Columns\TextColumn::make('started_at')
                    ->label('Inicio')
                    ->dateTime('H:i:s')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('completed_at')
                    ->label('Fin')
                    ->dateTime('H:i:s')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('duration')
                    ->label('Duración')
                    ->badge()
                    ->color('gray')
                    ->placeholder('-'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Filtrar por Estado')
                    ->options([
                        'QUEUED' => 'En Cola (QUEUED)',
                        'PROCESSING' => 'En Ejecución (PROCESSING)',
                        'WAITING_APPROVAL' => 'Pausado HITL (WAITING_APPROVAL)',
                        'COMPLETED' => 'Completado (COMPLETED)',
                        'FAILED' => 'Fallido (FAILED)',
                    ]),
            ])
            ->headerActions([])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Detalles & Trazas')
                    ->icon('heroicon-o-eye'),

                // ACCIÓN HUMAN-IN-THE-LOOP (HITL): APROBAR
                Tables\Actions\Action::make('approve_hitl')
                    ->label('Aprobar (HITL)')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Autorizar Ejecución HITL')
                    ->modalDescription('¿Confirmas la reanudación y ejecución de las acciones remediadoras para este hilo?')
                    ->form([
                        Forms\Components\TextInput::make('notes')
                            ->label('Notas de Aprobación')
                            ->default('Aprobado por operador vía panel web')
                            ->required(),
                    ])
                    ->visible(fn (AgentTask $record): bool => in_array($record->status, ['WAITING_APPROVAL', 'PAUSED']) && in_array(auth()->user()?->role, ['admin', 'operator']))
                    ->action(function (AgentTask $record, array $data, AgentHttpClient $apiClient): void {
                        try {
                            $user = auth()->user()?->name ?? 'operator';
                            $targetAgent = \App\Models\ApprovalRequest::where('thread_id', $record->thread_id)->first()?->agent
                                ?? Agent::where('agent_id', $record->payload_json['agent_id'] ?? null)->first()
                                ?? Agent::active()->first();
                            $client = $targetAgent ? AgentHttpClient::forAgent($targetAgent) : $apiClient;

                            $res = $client->approveExecution($record->thread_id, [
                                'justification' => $data['notes'],
                                'approved' => true,
                                'user' => [
                                    'name' => $user,
                                ],
                            ]);

                            if (!$res['success']) {
                                throw new \Exception($res['error'] ?? 'Fallo al aprobar ejecución');
                            }

                            $record->update(['status' => 'COMPLETED']);

                            Notification::make()
                                ->title('Ejecución aprobada y reanudada')
                                ->body("Thread: {$record->thread_id}")
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Error al aprobar ejecución')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                // ACCIÓN HUMAN-IN-THE-LOOP (HITL): RECHAZAR
                Tables\Actions\Action::make('reject_hitl')
                    ->label('Rechazar')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Rechazar y Detener Ejecución HITL')
                    ->form([
                        Forms\Components\TextInput::make('reason')
                            ->label('Motivo del Rechazo')
                            ->default('Rechazado por riesgo de impacto operacional')
                            ->required(),
                    ])
                    ->visible(fn (AgentTask $record): bool => in_array($record->status, ['WAITING_APPROVAL', 'PAUSED']) && in_array(auth()->user()?->role, ['admin', 'operator']))
                    ->action(function (AgentTask $record, array $data, AgentHttpClient $apiClient): void {
                        try {
                            $user = auth()->user()?->name ?? 'operator';
                            $targetAgent = \App\Models\ApprovalRequest::where('thread_id', $record->thread_id)->first()?->agent
                                ?? Agent::where('agent_id', $record->payload_json['agent_id'] ?? null)->first()
                                ?? Agent::active()->first();
                            $client = $targetAgent ? AgentHttpClient::forAgent($targetAgent) : $apiClient;

                            $res = $client->rejectExecution($record->thread_id, [
                                'justification' => $data['reason'],
                                'user' => [
                                    'name' => $user,
                                ],
                            ]);

                            if (!$res['success']) {
                                throw new \Exception($res['error'] ?? 'Fallo al rechazar ejecución');
                            }

                            $record->update([
                                'status' => 'CANCELLED',
                                'completed_at' => now(),
                            ]);

                            $pendingApproval = \App\Models\ApprovalRequest::where('thread_id', $record->thread_id)->pending()->first();
                            if ($pendingApproval && auth()->user()) {
                                $pendingApproval->reject(auth()->user(), $data['reason']);
                            }

                            Notification::make()
                                ->title('Ejecución rechazada y abortada')
                                ->body("Thread: {$record->thread_id}")
                                ->warning()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Error al rechazar ejecución')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Metadatos de la Tarea')
                    ->columns(4)
                    ->schema([
                        Infolists\Components\TextEntry::make('task_id')
                            ->label('Task ID')
                            ->fontFamily('mono')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('thread_id')
                            ->label('Thread ID')
                            ->fontFamily('mono')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('status')
                            ->label('Estado')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'COMPLETED' => 'success',
                                'PROCESSING' => 'info',
                                'QUEUED' => 'warning',
                                'WAITING_APPROVAL', 'PAUSED' => 'danger',
                                'FAILED' => 'danger',
                                default => 'gray',
                            }),
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Fecha de Creación')
                            ->dateTime('d/m/Y H:i:s'),
                    ]),

                Infolists\Components\Tabs::make('Detalles y Trazabilidad')
                    ->columnSpanFull()
                    ->tabs([
                        Infolists\Components\Tabs\Tab::make('Historial de Trazas (Trace History)')
                            ->icon('heroicon-o-list-bullet')
                            ->schema([
                                Infolists\Components\ViewEntry::make('trace_history_view')
                                    ->label('')
                                    ->view('filament.infolists.trace-history'),
                            ]),

                        Infolists\Components\Tabs\Tab::make('Payload Inicial')
                            ->icon('heroicon-o-arrow-down-tray')
                            ->schema([
                                Infolists\Components\ViewEntry::make('payload_display')
                                    ->label('')
                                    ->view('filament.infolists.json-tree-viewer', [
                                        'field' => 'payload_json',
                                    ]),
                            ]),

                        Infolists\Components\Tabs\Tab::make('Resultado del Grafo')
                            ->icon('heroicon-o-check-badge')
                            ->schema([
                                Infolists\Components\ViewEntry::make('result_display')
                                    ->label('')
                                    ->view('filament.infolists.json-tree-viewer', [
                                        'field' => 'result_json',
                                    ]),
                            ]),
                    ]),
            ]);
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

