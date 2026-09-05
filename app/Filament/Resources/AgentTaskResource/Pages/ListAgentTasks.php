<?php

namespace App\Filament\Resources\AgentTaskResource\Pages;

use App\Filament\Resources\AgentTaskResource;
use Filament\Resources\Pages\ListRecords;

class ListAgentTasks extends ListRecords
{
    protected static string $resource = AgentTaskResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
