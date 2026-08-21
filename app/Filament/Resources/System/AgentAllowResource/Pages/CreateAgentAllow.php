<?php

namespace App\Filament\Resources\System\AgentAllowResource\Pages;

use App\Filament\Resources\System\AgentAllowResource;
use App\Support\Cache;
use Filament\Resources\Pages\CreateRecord;

class CreateAgentAllow extends CreateRecord
{
    protected static string $resource = AgentAllowResource::class;

    public function afterCreate(): void
    {
        Cache::clearAgentAllowDeny();
    }
}
