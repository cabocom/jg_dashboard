<?php

/**
 * Part of the javiergalvez-ia ecosystem.
 *
 * @author    Javier Gálvez
 * @license   GNU AGPLv3 (or Commercial Enterprise License)
 * @link      https://javiergalvez.com
 * @link      https://gitlab.com/javiergalvez-ia/jg_dashboard
 */

namespace App\Filament\Resources\AgentTaskResource\Tables;

use App\Models\Agent;
use App\Models\AgentTask;
use App\Models\ApprovalRequest;
use App\Services\Agents\AgentHttpClient;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AgentTaskTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->poll('3s')
            ->columns(static::getColumns())
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

                static::makeApproveHitlAction(),
                static::makeRejectHitlAction(),
            ])
            ->bulkActions([]);
    }

    public static function getColumns(): array
    {
        return [
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
        ];
    }

    protected static function makeApproveHitlAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('approve_hitl')
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
                    $targetAgent = ApprovalRequest::where('thread_id', $record->thread_id)->first()?->agent
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
            });
    }

    protected static function makeRejectHitlAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('reject_hitl')
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
                    $targetAgent = ApprovalRequest::where('thread_id', $record->thread_id)->first()?->agent
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

                    $pendingApproval = ApprovalRequest::where('thread_id', $record->thread_id)->pending()->first();
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
            });
    }
}
