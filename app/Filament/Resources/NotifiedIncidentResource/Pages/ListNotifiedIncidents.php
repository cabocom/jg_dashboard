<?php

/**
 * Part of the javiergalvez-ia ecosystem.
 *
 * @author    Javier Gálvez
 * @license   GNU AGPLv3 (or Commercial Enterprise License)
 * @link      https://javiergalvez.com
 * @link      https://gitlab.com/javiergalvez-ia/jg_dashboard
 */

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
