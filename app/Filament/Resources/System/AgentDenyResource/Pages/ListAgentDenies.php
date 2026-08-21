<?php

namespace App\Filament\Resources\System\AgentDenyResource\Pages;

use App\Filament\PageList;
use App\Filament\Resources\System\AgentDenyResource;
use Filament\Actions\CreateAction;

class ListAgentDenies extends PageList
{
    protected static string $resource = AgentDenyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
