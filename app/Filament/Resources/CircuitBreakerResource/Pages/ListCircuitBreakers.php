<?php

/**
 * Part of the javiergalvez-ia ecosystem.
 *
 * @author    Javier Gálvez
 * @license   GNU AGPLv3 (or Commercial Enterprise License)
 * @link      https://javiergalvez.com
 * @link      https://gitlab.com/javiergalvez-ia/jg_dashboard
 */

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
