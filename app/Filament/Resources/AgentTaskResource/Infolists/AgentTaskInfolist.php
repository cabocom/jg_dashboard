<?php

/**
 * Part of the javiergalvez-ia ecosystem.
 *
 * @author    Javier Gálvez
 * @license   GNU AGPLv3 (or Commercial Enterprise License)
 * @link      https://javiergalvez.com
 * @link      https://gitlab.com/javiergalvez-ia/jg_dashboard
 */

namespace App\Filament\Resources\AgentTaskResource\Infolists;

use Filament\Infolists;
use Filament\Infolists\Infolist;

class AgentTaskInfolist
{
    public static function configure(Infolist $infolist): Infolist
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
}
