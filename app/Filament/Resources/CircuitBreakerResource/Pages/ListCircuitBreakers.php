<?php

namespace App\Filament\Resources\CircuitBreakerResource\Pages;

use App\Filament\Resources\CircuitBreakerResource;
use Filament\Resources\Pages\ListRecords;

class ListCircuitBreakers extends ListRecords
{
    protected static string $resource = CircuitBreakerResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
