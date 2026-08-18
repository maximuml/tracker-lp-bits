<?php

namespace App\Filament\Resources\System\AgentDenyResource\Pages;

use App\Filament\Resources\System\AgentDenyResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateAgentDeny extends CreateRecord
{
    protected static string $resource = AgentDenyResource::class;

    public function afterCreate(): void
    {
        \App\Support\Cache::clearAgentAllowDeny();
    }
}
