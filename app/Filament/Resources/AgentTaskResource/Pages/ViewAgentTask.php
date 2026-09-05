<?php

namespace App\Filament\Resources\AgentTaskResource\Pages;

use App\Filament\Resources\AgentTaskResource;
use Filament\Resources\Pages\ViewRecord;

class ViewAgentTask extends ViewRecord
{
    protected static string $resource = AgentTaskResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
