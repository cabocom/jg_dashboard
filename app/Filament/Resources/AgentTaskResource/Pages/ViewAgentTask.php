<?php

/**
 * Part of the javiergalvez-ia ecosystem.
 *
 * @author    Javier Gálvez
 * @license   GNU AGPLv3 (or Commercial Enterprise License)
 * @link      https://javiergalvez.com
 * @link      https://gitlab.com/javiergalvez-ia/jg_dashboard
 */

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
