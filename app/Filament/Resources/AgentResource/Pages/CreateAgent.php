<?php

/**
 * Part of the javiergalvez-ia ecosystem.
 *
 * @author    Javier Gálvez <contacto@javiergalvez.com>
 * @license   GNU AGPLv3 (or Commercial Enterprise License)
 * @link      https://javiergalvez.com
 * @link      https://gitlab.com/javiergalvez-ia/jg_dashboard
 */

namespace App\Filament\Resources\AgentResource\Pages;

use App\Filament\Resources\AgentResource;
use App\Services\Agents\AgentRegistrationService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateAgent extends CreateRecord
{
    protected static string $resource = AgentResource::class;

    public ?string $generatedToken = null;

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Card::make([
                    Forms\Components\TextInput::make('name')
                        ->label('Nombre del Agente')
                        ->required()
                        ->placeholder('Ej: Agente SRE Infraestructura')
                        ->maxLength(255),

                    Forms\Components\TextInput::make('version')
                        ->label('Versión Inicial')
                        ->default('0.1.0')
                        ->maxLength(64),

                    Forms\Components\TextInput::make('ttl_hours')
                        ->label('Validez del Token Bootstrap (Horas)')
                        ->numeric()
                        ->default(24)
                        ->minValue(1)
                        ->maxValue(720)
                        ->required(),
                ])->columns(2),
            ]);
    }

    protected function handleRecordCreation(array $data): Model
    {
        /** @var AgentRegistrationService $service */
        $service = app(AgentRegistrationService::class);

        $result = $service->createAgent(
            name: $data['name'],
            version: $data['version'] ?? '0.1.0',
            capabilities: null,
            metadata: null,
            ttlHours: (int) ($data['ttl_hours'] ?? 24),
            adminUser: auth()->user()
        );

        $this->generatedToken = $result['bootstrap_token'];

        Notification::make()
            ->title('Agente Aprovisionado Exitosamente')
            ->body("Token de arranque: <strong>{$this->generatedToken}</strong> (Copia este token, expira en {$result['ttl_hours']}h)")
            ->success()
            ->persistent()
            ->send();

        return $result['agent'];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
