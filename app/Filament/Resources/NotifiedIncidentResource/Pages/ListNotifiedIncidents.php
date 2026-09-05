<?php

namespace App\Filament\Resources\NotifiedIncidentResource\Pages;

use App\Filament\Resources\NotifiedIncidentResource;
use Filament\Resources\Pages\ListRecords;

class ListNotifiedIncidents extends ListRecords
{
    protected static string $resource = NotifiedIncidentResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
